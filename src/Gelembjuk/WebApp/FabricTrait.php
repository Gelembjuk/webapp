<?php

/**
* This trait helps to include integrate application with a class.
* It allows to use logging and translation system of an application. And also to use any other builders inluded in app/
* 
* LICENSE: MIT
*
* @category   MVC
* @package    Gelembjuk/WebApp
* @copyright  Copyright (c) 2019 Roman Gelembjuk. (http://gelembjuk.com)
* @version    1.0
* @link       https://github.com/Gelembjuk/webapp
*/

namespace Gelembjuk\WebApp;

trait FabricTrait {
    // inherit from logger trait
    use \Gelembjuk\Logger\ApplicationLogger;
    // inherit from text translate trait
    use \Gelembjuk\Locale\GetTextTrait;

    // this are aliaces registered in specific class
    private $class_alias = [];
    
    /**
    * This is application object , instance of Gelembjuk\WebApp\Applicaion
    *
    * @var Gelembjuk\WebApp\Applicaion
    */
    protected $application;

    /**
     * Set application object function. It is required to call it before using any methods inherited from this trait
     *
     * @param object $application Gelembjuk\WebApp\Applicaion
     */
    
    public function setApplication($application) 
    {
        $this->application = $application;
        
        return $this;
    }

    protected function registerClassAlias($name,$class)
    {
        $this->class_alias[$name] = $class;
    }
    
    protected function new($class)
    {
        return $this->application->new($this->getFinalClassName($class));
    }

    protected function single($class)
    {
        return $this->application->single($this->getFinalClassName($class));
    }
    /**
     * This is used to call some sub class of given class. I this case given class woks like a feature pool
     */
    public function get($class)
    {
        return $this->application->single($this->getFinalClassName($class));
    }

    private function getFinalClassName($class)
    {
        if (!empty($this->class_alias[$class])) {
            $class = $this->class_alias[$class];

        } else {
            $class = $this->buildClassName($class);
        }
        return $class;
    }
    protected function buildClassName($class)
    {
        // by default this does nothing 
        return $class;
    }
    protected function getDBO($class)
    {
        return $this->application->getDBO($this->getFinalClassName($class));
    }
}
