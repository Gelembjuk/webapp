<?php
/**
 * Blessed object means it is some object of the class where Application Trait is used and we create and assign application object to it 
 */
namespace Gelembjuk\WebApp;

abstract class Model {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	use FabricTrait;
	
	public function __construct($application,$options = []) 
	{
		$this->setApplication($application);
		$this->init($options);
	}
	public function init($options = array()) 
	{
	}
	protected function signinRequired() 
	{
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
