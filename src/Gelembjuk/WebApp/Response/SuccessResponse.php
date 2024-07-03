<?php

namespace Gelembjuk\WebApp\Response;

class SuccessResponse extends Response  {
    public function __construct($url = '', $view = '') 
    {
        if (!empty($view)) {
            // this is special acse to set custom view action if responseformat is not html (so not redirect) 
            parent::__construct(self::SUCCESS.':'.$view, $url);
        } else {
            parent::__construct(self::SUCCESS, $url);
        }
    }
}
