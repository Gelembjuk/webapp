<?php

namespace Gelembjuk\WebApp\Response;

class ViewResponse extends Response  {
    public function __construct($view = '') 
    {
        parent::__construct(self::VIEW, $view);
    }
}
