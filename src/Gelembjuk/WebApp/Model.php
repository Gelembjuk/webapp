<?php

namespace Gelembjuk\WebApp;

abstract class Model {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $application;
	protected $controller;
	
	public function __construct($application,$options = array()) {
		$this->application = $application;
		$this->init($options);
	}
	public function init($options = array()) {
	}
	protected function signinRequired() {
		if ($this->getUserID() == 0) {
			throw new \Exception($this->_('Login Required'));
		}
		return true;
	}
	protected function getUserID() {
		return $this->application->getUserID();
	}
	/*
	* Shortcut for blessed objects creator function. 
	*/
	protected function newBlessed($class)
    {
        return $this->application->newBlessed($class);
    }
}
