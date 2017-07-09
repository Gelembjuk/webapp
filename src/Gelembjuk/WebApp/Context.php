<?php

namespace Gelembjuk\WebApp;

trait Context {
    use \Gelembjuk\Logger\ApplicationLogger;
    use \Gelembjuk\Locale\GetTextTrait;
    
    protected $application;
    
    public function setApplication($application)
    {
        $this->application = $application;
    }
}
