<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth;

class SJTU extends \Gini\Controller\CGI
{
    use \Gini\Module\Gapper\Client\CGITrait;
    use \Gini\Module\Gapper\Client\LoggerTrait;

    public function actionGetForm()
    {
        $url = 'oauth/client/auth';
        
        $redirectURL = $_SERVER['HTTP_REFERER'];
        if (!$redirectURL) {
            $ui = parse_url(\Gini\URI::url());
            $redirectURL = "{$ui['scheme']}://{$ui['host']}";
            if (!$ui['port'] || $ui['port']!='80') {
                $redirectURL = "{$redirectURL}:{$ui['port']}";
            }
        }

        $redirectURL = \Gini\URI::url($url, [
            'source'=> 'sjtu.oauth2', 
            'redirect_uri'=> $redirectURL
        ]);

        return $this->showJSON([
            'redirect'=> $redirectURL
        ]);
    }

}
