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
     * This is local builder object. It is used to build objects of classes that are not part of the application
     * If this is set then all build calls will be forwarded to it instead of application object
     */
    private $local_builder = null;
    
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

    public function setLocalBuilder($builder)
    {
        $this->local_builder = $builder;
    }

    protected function registerClassAlias($name,$class)
    {
        $this->class_alias[$name] = $class;
    }
    
    protected function new($class)
    {
        return $this->build($class, 'new');
    }

    protected function single($class)
    {
        return $this->build($class, 'single');
    }
    /**
     * This is used to call some sub class of given class. I this case given class woks like a feature pool
     */
    public function get($class)
    {
        return $this->build($class, 'get');
    }
    public function build($class, $method)
    {
        if ($this->local_builder) {
            return $this->local_builder->$method($class);
        }
        list ($class, $modified) = $this->getFinalClassName($class, true);

        $obj = $this->application->$method($class);

        if ($modified) {
            // if class name was modified then we need to set local builder to the object
            // it means next builds inside this new object should be made with the same local builder
            $obj->setLocalBuilder($this);
        }

        return $obj;
    }
    private function getFinalClassName($class, $extended = false)
    {
        $modified = false;

        if (!empty($this->class_alias[$class])) {
            $class = $this->class_alias[$class];
            $modified = true;
        } else {
            $n_class = $this->buildClassName($class);

            if ($n_class != $class) {
                $class = $n_class;
                $modified = true;
            }
        }
        if ($extended) {
            return [$class,$modified];
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
