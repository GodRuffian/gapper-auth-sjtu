<?php

namespace Gini\Module;

class GapperAuthSJTU
{
    public static function setup()
    {
        // TODO 如果发现是交大OAuth2的登录
        /*
        $oauth = \Gini\IoC::construct('\Gini\OAuth\Client', $_GET['oauth-sso']);
        $username = $oauth->getUserName();
         */
    }

    public static function diagnose()
    {
    }
}


