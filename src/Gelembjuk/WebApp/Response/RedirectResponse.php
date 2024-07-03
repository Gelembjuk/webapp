<?php

namespace Gelembjuk\WebApp\Response;

class RedirectResponse extends Response  {
    public function __construct($url = '') 
    {
        parent::__construct(self::REDIRECT, $url);
    }
}
