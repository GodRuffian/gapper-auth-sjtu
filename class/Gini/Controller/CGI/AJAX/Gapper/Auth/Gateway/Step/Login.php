<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway\Step;

class Login extends \Gini\Controller\CGI
{
    public function __index()
    {
        return \Gini\CGI::request("ajax/gapper/auth/gateway/get-form", $this->env)->execute();
    }
}
