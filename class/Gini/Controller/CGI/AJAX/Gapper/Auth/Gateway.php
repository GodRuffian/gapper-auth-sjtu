<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class Gateway extends \Gini\Controller\CGI
{
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
            'redirect'=> $redirectURL
        ]);
    }

}
