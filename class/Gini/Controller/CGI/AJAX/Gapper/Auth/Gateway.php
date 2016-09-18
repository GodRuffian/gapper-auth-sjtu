<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class Gateway extends \Gini\Controller\CGI
{
    private static $identitySource = 'sjtu';
    public function actionGetForm()
    {
        $redirectURL = $_SERVER['HTTP_REFERER'];
        if (!$redirectURL) {
            $ui = parse_url(\Gini\URI::url());
            $redirectURL = "{$ui['scheme']}://{$ui['host']}";
            if (!$ui['port'] || $ui['port']!='80') {
                $redirectURL = "{$redirectURL}:{$ui['port']}";
            }
        }

        $confs = \Gini\Config::get('gapper.rpc');
        $clientId = $confs['client_id'];

        $appConfs = \Gini\Config::get('app.rpc');
        $gateway = $appConfs['gateway'];
        $gatewayURL = 'http://' . parse_url($gateway['url'])['host'] . '/login';
        $redirectURL = \Gini\URI::url($gatewayURL, [
            'from'=> $clientId,
            'relogin'=> 1,
            'redirect'=> $redirectURL
        ]);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'redirect'=> $redirectURL,
            'message'=> T('去统一身份认证登录')
        ]);
    }

    public static function addMember()
    {
        $args = func_get_args();
        $action = array_shift($args);
        switch ($action) {
        case 'get-add-modal':
            return call_user_func_array([self, '_getAddModal'], $args);
            break;
        case 'search':
            return call_user_func_array([self, '_getSearchResults'], $args);
            break;
        case 'post-add':
            return call_user_func_array([self, '_postAdd'], $args);
            break;
        }
    }

    private static function _getAddModal($type, $groupID)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('gapper/auth/sjtu/add-member/modal'));
    }

    private static function _getSearchResults($type, $keyword)
    {
        try {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getUserByIdentity(self::$identitySource, $keyword);
        } catch (\Exception $e) {
            return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
        }

        if ($info && $info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getRPC()->gapper->user->getGroups((int)$info['id']);
            } catch (\Exception $e) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $current = \Gini\Gapper\Client::getGroupID();
            if (isset($groups[$current])) {
                return \Gini\IoC::construct('\Gini\CGI\Response\Nothing');
            }
            $data = [
                'username'=> $keyword,
                'name'=> $info['name'],
                'initials'=> $info['initials'],
                'icon'=> $info['icon']
            ];
        } else {
            $info = (array)\Gini\Gapper\Auth\Gateway::getUserInfo($keyword);
            $data = [
                'username'=> $keyword,
                'name'=> $info['name'],
                'email'=> $info['email']
            ];
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', (string)V('gapper/auth/sjtu/add-member/match', $data));
    }

    private static function _postAdd($type, $form)
    {
        try {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getUserByIdentity(self::$identitySource, $form['username']);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        $current = \Gini\Gapper\Client::getGroupID();

        if ($info && $info['id']) {
            try {
                $groups = \Gini\Gapper\Client::getRPC()->gapper->user->getGroups((int)$info['id']);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if (isset($groups[$current])) {
                return self::_success($info);
            }

            try {
                $bool = \Gini\Gapper\Client::getRPC()->gapper->group->addMember((int)$current, (int)$info['id']);
            } catch (\Exception $e) {
                return self::_alert(T('操作失败，请您重试'));
            }
            if ($bool) {
                return self::_success($info);
            }
            return self::_alert(T('操作失败，请您重试'));
        }

        // 如果没有提交email和name, 展示确认name和email的表单
        if (empty($form['name']) || empty($form['email'])) {
            $error = [];
            if (empty($form['name'])) {
                $error['name'] = T('请补充用户姓名');
            }
            if (empty($form['email'])) {
                $error['email'] = T('请填写Email');
            }
        }

        $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
        if ($form['email'] && !preg_match($pattern, $form['email'])) {
            $error['email'] = T('请填写真实的Email');
        }

        if (!empty($error)) {
            return self::_showFillInfo([
                'username'=> $form['username'],
                'name'=> $form['name'],
                'email'=> $form['email'],
                'error'=> $error
            ]);
        }

        $email = $form['email'];
        $name = $form['name'];
        $username = $form['username'];
        try {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getInfo($email);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        if ($info['id']) {
            return self::_showFillInfo([
                'username'=> $username,
                'name'=> $name,
                'email'=> $email,
                'error'=> [
                    'email'=> T('Email已经被占用, 请换一个试试')
                ]
            ]);
        }

        try {
            $uid = \Gini\Gapper\Client::getRPC()->gapper->user->registerUser([
                'username'=> $email,
                'password'=> \Gini\Util::randPassword(),
                'name'=> $name,
                'email'=> $email
            ]);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }
        if (!$uid) return self::_alert(T('添加用户失败, 请重试!'));

        try {
            $bool = \Gini\Gapper\Client::getRPC()->gapper->user->linkIdentity((int)$uid, self::$identitySource, $username);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }
        if (!$bool) return self::_alert(T('用户添加失败, 请换一个Email试试!'));

        try {
            $bool = \Gini\Gapper\Client::getRPC()->gapper->group->addMember((int)$current, (int)$uid);
        } catch (\Exception $e) {
            return self::_alert(T('操作失败，请您重试'));
        }

        if ($bool) {
            $info = \Gini\Gapper\Client::getRPC()->gapper->user->getInfo((int)$uid);
            return self::_success($info);
        }

        return self::_alert(T('一卡通用户已经激活, 但是暂时无法将该用户加入当前组, 请联系网站管理员处理!'));
    }

    private static function _success(array $user=[])
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'replace'=> $user,
            'message'=> (string)V('gapper/client/add-member/success')
        ]);
    }

    private static function _alert($message)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'alert',
            'message'=> $message
        ]);
    }

    private static function _showFillInfo($vars)
    {
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'replace',
            'message'=> (string)V('gapper/auth/sjtu/add-member/fill-info', $vars)
        ]);
    }

}
