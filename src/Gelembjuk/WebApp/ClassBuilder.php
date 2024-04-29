<?php

namespace Gelembjuk\WebApp;
/**
 * This is class class used to build other classes. 
 * It can be used to make feature pool object to refer to feature subclasses as easy as possible
 */
class ClassBuilder  {
    use FabricTrait;

    private $callback;

	public function __construct($application, callable $callback) 
    {
        if (!is_callable($callback)) {
            throw new \Exception('Callback is not callable');
        }
        $this->callback = $callback;
        $this->setApplication($application);
    }

    public function buildClassName($class)
    {
        return call_user_func($this->callback,$class);
    }
}
