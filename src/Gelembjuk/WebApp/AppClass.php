<?php

namespace Gelembjuk\WebApp;
/**
 * This is the base class for any class that is part of the application. It allows to get access to the application object
 * and some core methods.
 * Classes must not inherit this class. There is alternative - include AppIntegratedTrait in the class
 */
abstract class AppClass  {
	use AppIntegratedTrait;

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
