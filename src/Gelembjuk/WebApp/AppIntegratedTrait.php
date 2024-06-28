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

    use FabricTrait;

    protected $signinreqired = false;

    public function __construct($application) 
    {
        $this->setApplication($application);
    }
    protected function checkIfSignedInRequired()
    {
        if ($this->signinreqired) {
            $this->signinRequired();
        }
    }
    /**
     * This is basic function to call from any place to ensure a user is logged in to access a page
     * 
     * @param string $errormessage Error message to show if user is not logged in
     * @param string $url URL to redirect user to login page. In case if error action is redirect and not just view 
     */
    protected function signinRequired($errormessage = '', $url = '') 
	{
        if ($this->application->getUserID() > 0) {
            return true;
        }
		
        if (empty($errormessage)) {
            // this is needed for correct localisation
            $errormessage = $this->_('user_auth_required_please_login','exceptions');

            if ($errormessage == 'user_auth_required_please_login') {
                $errormessage = 'User Auth required. Please login';
            }
        }

        if (empty($url)) {
            $def_url = $this->application->getDefaultAuthExceptionRedirectUrl();

            if (!empty($def_url)) {
                $url = $def_url;
            }
        }

        throw new Exceptions\AuthRequiredException($errormessage, $url);
	}
    protected function actionRequiresSignin()
    {
        $this->signinreqired = true;
    }
    protected function actionDoesNotRequiresSignin()
    {
        $this->signinreqired = false;
    }
    public function init() 
	{
		// do nothing here.
		// it is used to have a constructor in a class that uses this trait
		// to do some actions after application is known and set
	}

	protected function getUserID() 
	{
		return $this->application->getUserID();
	}
}
