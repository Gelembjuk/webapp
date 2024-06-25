<?php

namespace Gelembjuk\WebApp;
/**
 * This is the base class for any class that is part of the application. It allows to get access to the application object
 * and some core methods.
 * Classes must not inherit this class. There is alternative - include AppIntegratedTrait in the class
 */
abstract class AppClass  {
	use AppIntegratedTrait;
}
