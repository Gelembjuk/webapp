<?php

/**
* This trait helps to execute methods with arguments received from outside
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

trait RouterAccessTrait {
    use AppIntegratedTrait;

    protected $router = null;
    protected $responseformat = '';

    public function withRouter($router) 
    {
        $this->router = $router;

        return $this;
    }

    protected function getInput($name,$type='string',$default='',$maxlength=0) 
	{
		return $this->getRouter()->getInput($name,$type,$default,$maxlength);
	}
    /**
	* Returns a router for this controller to read input data from it.
	* This implementation returns a default router of an app.
	* If the app has more then 1 router then this function can be implemented in a controller class to work differently.
	*/
	public function getRouter() 
	{
        if ($this->router) {
            return $this->router;
        }
        return $this->application->getRouter();
    }

    protected function callMethodExternally($methodname) 
	{
		if( !method_exists($this,$methodname) ) {
			throw new \Exception('Method not found');
		}
		$args = [];

		$r = new \ReflectionMethod($this, $methodname);
		$params = $r->getParameters();
		
		foreach ($params as $param) {
			//$param is an instance of ReflectionParameter
            $type = $this->getParamType($param);
            
			$args[$param->getName()] = $this->getInput($param->getName(),$type, $param->isDefaultValueAvailable() ? $param->getDefaultValue() : '');
		}
		return call_user_func_array(array($this, $methodname), $args);;
	}
	protected function getParamType(\ReflectionParameter $param) 
	{
        if (!$param->hasType()) {
            return 'string';
        }
        
		switch ($param->getType()->getName()) {
			case 'int':
				return 'int';
			case 'string':
				return 'string';
			case 'array':
				return 'array';
			case 'bool':
				return 'bool';
			case 'float':
				return 'float';
		}
		return 'string';
	}
}
