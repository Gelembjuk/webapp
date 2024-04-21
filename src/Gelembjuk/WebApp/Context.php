<?php
/**
 * This is same as ApplicationTrait but does not have default constructor . 
 * It is used for classes that have their own constructors
 */
namespace Gelembjuk\WebApp;

trait Context {
    use \Gelembjuk\Logger\ApplicationLogger;
    use \Gelembjuk\Locale\GetTextTrait;
    use FabricTrait;
}
