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

trait AppIntegratedTrait {
    // inherit from logger trait
    use \Gelembjuk\Logger\ApplicationLogger;
    // inherit from text translate trait
    use \Gelembjuk\Locale\GetTextTrait;
    
    /**
    * This is application object , instance of Gelembjuk\WebApp\Applicaion
    *
    * @var Gelembjuk\WebApp\Applicaion
    */
    protected $application;

    /**
     * Standard constructor. Will work fine in most of cases
     */

    public function __construct($application = null) 
    {
        if ($application) {
            $this->setApplication($application);
        }
    }
    
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
    
}
