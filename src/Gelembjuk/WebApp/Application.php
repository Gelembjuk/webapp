<?php

namespace Gelembjuk\WebApp;

class Application {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $errorhandler = null;

	protected $created_objects = [];
	protected $created_forced_objects = [];

	protected $dbobjects;
	protected $dbengines;
	protected $views;
	protected $routers = [];

	protected $class_alias = [];
	protected $class_builders = [];

	/**
	* @var
	* First router object loaded. It can give some useful info about the application mode
	*/
	protected $routerfront = null;
	protected $defaultroutername = '';
	protected $actioncontroller;
	protected $defaultcontrollername = '';
	protected $cache;
	
	protected $localeautoload = false;
	protected $options = [];
	protected $config = null;
	
	protected $exceptiononurlmake = true;
	
	protected $userid = 0;

	protected $requestUniqueID;

	public function __construct() 
	{
		$this->dbobjects = [];
		$this->dbengines = [];
		$this->localeautoload = false;

		$this->requestUniqueID = substr(md5(uniqid()),0,15);
	}
	public static function getInstance() 
	{
		static $instance;

		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}
	
	public function init(object $config,$options = []) 
	{
		$this->config = $config;
		$this->options = $options;
		
		// LOGGER =====================================================================================
		if ($this->options['loggerstandard']) {
            // this is logger configuration that works fine for most cases
            $this->options['loggerclass'] = '\\Gelembjuk\\Logger\\FileLogger';
            
            $logdir = (!empty($this->getOption('logdirectory'))) ? $this->getOption('logdirectory') : $this->getOption('tmproot');
            
            $this->options['loggeroptions'] = [
                    'logfile' => $logdir . 'log.txt',
                    'groupfilter' => $this->getConfig('loggingfilter'),
					'requestid' => $this->getRequestUnieueID()
                    ];
		}
		
		// set logger
		if (isset($this->options['logger'])) {
			$this->logger = $this->options['logger'];
			unset($this->options['logger']);
			
		} elseif (isset($this->options['loggerclass'])) {
            // create logger
			if (!class_exists($this->options['loggerclass'])) {
				throw new \Exception('Logger class ' . $this->options['loggerclass'] . ' not found');
			}
			
			$this->logger = new $this->options['loggerclass']($this->options['loggeroptions']);
			unset($this->options['loggerclass']);
			unset($this->options['loggeroptions']);
		}
		
		// ERROR ======================================================================================
		// set error handler
		if (isset($this->options['errorhandler'])) {
			$this->errorhandler = $this->options['errorhandler'];
			unset($this->options['errorhandler']);
			
		} elseif (isset($this->options['errorhandlerclass'])) {
			
			if (!class_exists($this->options['errorhandlerclass'])) {
				throw new \Exception('Error handling class ' . $this->options['errorhandlerclass'] . ' not found');
			}

			$this->errorhandler = new $this->options['errorhandlerclass']($this->options['errorhandlerobjectoptions']);
			unset($this->options['errorhandlerclass']);
			unset($this->options['errorhandlerobjectoptions']);
		}
		
		if (is_object($this->errorhandler)) {
			if (is_object($this->logger)) {
				$this->errorhandler->setLogger($this->logger);
			}
			$this->errorhandler->setViewFormat('html');
		}
		// LOCALE ======================================================================================
		if ($this->options['languagesstandard'] && !$this->checkTranslateObjectIsSet()) {
            $this->initTranslateObject(
                array(
                    'locale' => $this->locale,
                    'localespath' => $this->getOption('languagespath'))
                );
        }
		
		$this->options['basehost'] = $this->getBasehost();
		
		if (isset($this->options['defaultroutername'])) {
            $this->defaultroutername = $this->options['defaultroutername'];
		}
		if (isset($this->options['defaultcontrollername'])) {
            $this->defaultcontrollername = $this->options['defaultcontrollername'];
        }
		
	}
	protected function getAppNameSpace() 
	{
		if (isset($this->options['namespace'])) {
			return $this->options['namespace'];
		}
		// automatically detect namespace of this class
		$namespace = get_class($this);
		$namespace = substr($namespace,0,strrpos($namespace,'\\'));
		return $namespace.'\\';
	}
	protected function registerClassAlias($alias,$class) 
	{
		$this->class_alias[$alias] = $class;
	}
	public function registerClassBuilder($name,$callback) 
	{
		if (is_object($callback) && $callback instanceof ClassBuilder) {
			$this->class_builders[strtolower($name)] = $callback;
			return;
		}
		$this->class_builders[$name] = new ClassBuilder($this, $callback);
	}
	// it can be used to set some options for the application
	// it should be redeclared in the application class ance
	public function inDebugMode():bool 
	{
		return false;
	}

	public function getRequestUnieueID() 
	{
		return $this->requestUniqueID;
	}
	/*
	* To add some options after init executed
	*/
	public function addOption($key,$value) 
	{
		if (trim($key) == '') {
			return false;
		}
		$this->options[$key] = $value;
		return true;
	}
	/**
	 * This is the start function. It starts all the magic
	 */
	public function action() 
	{
		// controller will be detected by router and created
		$controller = $this->getController();
		// remember the controller for the future to know what is current running controller
		$this->actioncontroller = $controller;

		if ($this->getConfig('offline')) {
			return $controller->actionOffline();
		}
		// run controller action. This does all the job
		return $controller->action();
	}
	public function setUserID($userid) 
	{
		$this->userid = $userid;
	}
	public function getUserID() 
	{
		return $this->userid;
	}
	public function getUserRecord() 
	{
		return array('id' => $this->getUserID());
	}
	/**
	 * In case if we want to have controllers in a different location from standard , we need to modify our router
	 */
	protected function getControllerFullClass($controllerclass)
	{
        if (substr($controllerclass,0,1) == '\\') {
            // this is absolute class name
            return $controllerclass;
        }
        
        $controllerclass = ucfirst($controllerclass);

		$subspace = 'Controllers\\';

		if ($this->routerfront) {
			$subspace = $this->routerfront->getControllerSubSpace();
		}
        
        return $this->getAppNameSpace(). $subspace . $controllerclass;
    }

	public function getController($controllername = '',$exceptiononnotfound = false, $alwayscreatenew = false) 
	{
		if ($controllername != '') {
			$controllername = ucfirst($controllername);
		}
		// this would build the default router
		$router = $this->getRouter();
		
		if ($controllername == '') {
			// when a script starts usually the first call is with the empty controller name
			if ($this->getOption('DefaultController') != '') {
				$controllername = ucfirst($this->getOption('DefaultController'));

			} else {
				if ($this->routerfront === null) {
                    $this->routerfront = $router;
                    $this->frontRouterLoaded();
				}
				
				$controllername = $router->getController();
			}
		} else {
			// if there was no any router used yet, remember this one as the starting router
			if ($this->routerfront === null) {
				$this->routerfront = $router;
				$this->frontRouterLoaded();
			}
		}
		
		$controllerpath = $this->getControllerFullClass($controllername);
		
		if (!class_exists($controllerpath) && $this->getDefaultControllerName() != '') {
			if ($exceptiononnotfound) {
				throw new \Exception(sprintf('Controller %s not found',$controllername));
			}
			// set error environment in the router to display error page
			if ($router) {
				$router->setErrorPage('Controller not found','not_found',404);
			}
			$controllerpath = $this->getControllerFullClass($this->getDefaultControllerName());
		}
		
		if (!class_exists($controllerpath)) {
			throw new \Exception('Default controller not found');
		}
		
		if (!is_subclass_of($controllerpath, '\\Gelembjuk\\WebApp\\Controller')) {
			throw new \Exception('Controller must be subclass of \\Gelembjuk\\WebApp\\Controller');
		}
		
		// this is for mocking on testing
		if (array_key_exists($controllerpath,$this->created_forced_objects)) {
            return $this->created_forced_objects[$controllerpath];
		}
		
		if (!$alwayscreatenew && isset($this->created_objects[$controllerpath])) {
            return $this->created_objects[$controllerpath];
        }

		$controller = new $controllerpath($this);
		
		$controller->withRouter($router);
		$controller->init();
		
		$this->created_objects[$controllerpath] = $controller;
		
		return $controller;
	}
	/**
	* This is the hook function to do some action when a front router loaded and controller is not loaded yet
	*/
	protected function frontRouterLoaded()
	{
        // Implement somethign in your application
	}
	public function getCache() 
	{
		if ($this->cache) {
			return $this->cache;
		}
		
		$this->cache = new \Doctrine\Common\Cache\FilesystemCache($this->options['tmproot'].'cache/');
		
		return $this->cache;
	}
	protected function getRouterFullClass($routerclass)
    {
        if (substr($routerclass,0,1) == '\\') {
            // this is absolute class name
            return $routerclass;
        }
		// ROuters are always in a same place. Inmomost cases we need only one router per application
        return $this->getAppNameSpace() .'Routers\\' . ucfirst($routerclass);
    }
	public function getRouter($routername = '', $alwayscreatenew = false) 
	{
		if ($routername == '') {
			$defroutername = $this->getRouterNameFromRequest();
			$routername = $defroutername;	
		}
		
		$routername = $this->getRouterFullClass($routername);
		
		if (!class_exists($routername)) {
			$defroutername = $this->getDefaultRouter();
			$routername = $this->getRouterFullClass($defroutername);
		}
		// this is for mocking on testing
        if (array_key_exists($routername,$this->created_forced_objects)) {
            return $this->created_forced_objects[$routername];
        }
		
		if (!$alwayscreatenew && isset($this->created_objects[$routername])) {
			return $this->created_objects[$routername];
		}
		
		if (!class_exists($routername)) {
			throw new \Exception('Default router not found');
		}

		if (!is_subclass_of($routername, '\\Gelembjuk\\WebApp\\Router') && $routername != '\\Gelembjuk\\WebApp\\Router') {
			throw new \Exception('Router must be subclass of \\Gelembjuk\\WebApp\\Router');
		}
		
		$router = new $routername($this,$this->options);
		
		$router->init();
		
		if ($this->localeautoload) {
			if ($this->getLocale() == '') {
				// if no locale then get it from router
				// router should load local from request information

				$this->setLocale($router->detectLocale());
				// if router can not extract then it should be empty string
				// or can be always same locale
			}
		}
		
		$this->created_objects[$routername] = $router;

		return $router;
	}
	
	protected function getDBEngine($profile = 'default') 
	{
		if ($profile == '') {
			$profile = 'default';
		}
		
		if (isset($this->dbengines[$profile])) {
			return $this->dbengines[$profile];
		}
		
		$engines = array(
			'mysql' => '\\Gelembjuk\\DB\\MySQL',
			'mysqli' => '\\Gelembjuk\\DB\\MySQLi'
			);
		
		$options = $this->getConfig('database');
		
		if (is_array($options[$profile])) {
			$options = $options[$profile];
		}
		
		$engineclass = (isset($options['engine'])) ? $options['engine'] : 'mysql';
		
		if (isset($engines[$engineclass])) {
			$engineclass = $engines[$engineclass];
		}

		// this is for mocking on testing
        if (array_key_exists($engineclass,$this->created_forced_objects)) {
            return $this->created_forced_objects[$engineclass];
        }

		if (!class_exists($engineclass)) {
			throw new \Exception(sprintf('DB class %s not found',$engineclass));
		}
		
		if (!is_subclass_of($engineclass, '\\Gelembjuk\\DB\\EngineInterface')) {
			throw new \Exception('DB Engine must be subclass of \\\Gelembjuk\\DB\\EngineInterface');
		}
		
		$options['application'] = $this;
		
		$object = new $engineclass($options);
		
		$this->dbengines[$profile] = $object;
		
		return $object;
	}
	protected function getDBOFullClass($dboclass)
    {
        if (substr($dboclass,0,1) == '\\') {
            // this is absolute class name
            return $dboclass;
        }
		$dboclass = str_replace('/','\\',$dboclass);

		$subspace = 'Database\\';

		if ($this->routerfront) {
			$subspace = $this->routerfront->getDatabaseSubSpace();
		}

        return $this->getAppNameSpace() .$subspace . ucfirst($dboclass);
    }
	public function getDBO($name,$profile = 'default') 
	{	
        // this is for mocking on testing
        $classpath = $this->getDBOFullClass($name);

        if (array_key_exists($classpath,$this->created_forced_objects)) {
            return $this->created_forced_objects[$classpath];
        }
        
		if (isset($this->dbobjects[$name.'_'.$profile])) {
			return $this->dbobjects[$name.'_'.$profile];
		}

		return $this->getDBONew($name,$profile);
	}
	
	public function getDBONew($name,$profile = 'default') 
	{		
		$engine = $this->getDBEngine($profile);
		
		$classpath = $this->getDBOFullClass($name);

		// this is for mocking on testing
        if (array_key_exists($classpath,$this->created_forced_objects)) {
            return $this->created_forced_objects[$classpath];
        }
		
		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('DB class %s not found',$classpath));
		}
		
		if (!is_subclass_of($classpath, '\\Gelembjuk\\DB\\Base')) {
			throw new \Exception('DB Object must be subclass of \\Gelembjuk\\DB\\Base');
		}
		
		$object = new $classpath($engine,$this);
		
		$this->dbobjects[$name.'_'.$profile] = $object;
		
		return $object;
	}
	
	protected function getViewFullClass($viewclass)
    {
        if (substr($viewclass,0,1) == '\\') {
            // this is absolute class name
            return $viewclass;
        }
		$subspace = 'Views\\';

		if ($this->routerfront) {
			$subspace = $this->routerfront->getViewsSubSpace();
		}
        return $this->getAppNameSpace() .$subspace . ucfirst($viewclass);
    }
	public function getView($name,$controller) 
	{		
		$classpath = $this->getViewFullClass($name);

		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('View class %s not found',$classpath));
		}
		
		if (!$controller) {
			throw new \Exception(sprintf('View class %s requires a controller object',$classpath));
		}
		
		if (!is_subclass_of($classpath, '\\Gelembjuk\\WebApp\\View')) {
			throw new \Exception('View must be subclass of \\Gelembjuk\\WebApp\\View');
		}
		
		// this is for mocking on testing
        if (array_key_exists($classpath,$this->created_forced_objects)) {
            return $this->created_forced_objects[$classpath];
        }
		
		$object = new $classpath($this, $controller, $this->options);
		$object->withRouter($controller->getRouter());
		$object->init();
		
		return $object;
	}
	public function getWidget($widgetName)
	{
		// widgets are in the same folder as views as a subfolder
		$classpath = $this->getViewFullClass('Widgets');

		if (!class_exists($classpath.'\\'.$widgetName)) {
			$widgetName = ucfirst($widgetName);
		}
		$classpath .= '\\'.$widgetName;

		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('Widget class %s not found',$classpath));
		}

		if (!is_subclass_of($classpath, '\\Gelembjuk\\WebApp\\Widget')) {
			throw new \Exception('Widget must be subclass of \\Gelembjuk\\WebApp\\Widget');
		}

		$object = new $classpath($this);
		$object->init();
		$object->withRouter($this->routerfront);
		
		return $object;

	}
	public function getConfig($name) 
	{
		if (property_exists($this->config, $name)) {
			return $this->config->$name;
		}
		return null;
	}
	
	public function getOption($name) 
	{
		return $this->options[$name] ?? '';
	}
	public function setErrorHandler($errorhandlerobject) 
	{
		$this->errorhandler = $errorhandlerobject;
	}
	public function getErrorHandler() 
	{
		return $this->errorhandler;
	}
	// Overload this in your child application to return base hostname differently if needed
	public function getBasehost() 
	{
		if ($this->getConfig('basehost') != '') {
			return $this->getConfig('basehost');
		}
		$hostinfo = new \Gelembjuk\WebApp\Server\Host();
		return $hostinfo->getBaseHost();
	}
	/**
	 * Build url for the application
	 * Controller can be provided as an object or just a name
	 */
	public function makeUrl($controllername = '',$opts = []) 
	{
        try {
            if ($controllername == '') {
                // try to get currect action controller
				// if a controller name is empty then we just reuse current controller
                if (is_object($this->actioncontroller)) {
                    $controllername = $this->actioncontroller;
                }
            }
            
            // detect if default controller should be used
            if (!is_object($controllername) && 
                ($controllername == '' || $controllername == 'def')) {
                
                $controllername = $this->getDefaultControllerName();
            }
        
        
            if (is_object($controllername)) {
                $controller = $controllername;
            } else {
                $controller = $this->getController($controllername,true);
            }
            
            return $controller->makeUrl($opts);
		} catch (\Exception $e) {
			$this->logQ('Exception on url making '.$e->getMessage(),'error|debug|links');
			$this->logQ($e->getTraceAsString(),'debug');

            if ($this->exceptiononurlmake) {
                throw $e;
            }
            // if no error then return empty
            return '';
		}
	}
	// absolute url
	public function makeAbsUrl($controllername,$opts = array()) 
	{
        
        $relativeurl = $this->makeUrl($controllername,$opts);
        
        $baseurl = $this->getBasehost();
        
        if (substr($baseurl,-1) == '/' && substr($relativeurl,0,1) == '/') {
            $relativeurl = substr($relativeurl,1);
        }
        
        return $baseurl . $relativeurl;
    }
	public function makeUrlByRouter($router,$opts = []) 
	{
		return $router->makeUrl($opts);
	}
	protected function getRouterNameFromRequest() 
	{
		return $this->getDefaultRouter();
	}
	
	// Profiling
	public function profilerAction($type,$time,$string) 
	{
		return true;
	}
	protected function getDefaultRouter()
	{
        if (empty($this->defaultroutername)) {
            // no any router provided
            // use defauls router
            return '\\Gelembjuk\\WebApp\\Router';
        }
        return $this->defaultroutername;
	}
	protected function getDefaultControllerName()
	{
        return $this->defaultcontrollername;
	}
	/**
	 * This is used for testing. To set some Mock object to be used when
	 * a system requests to create a new object of this class
	 */
	public function setForcedClassObject($object, $classname)
	{
		$this->created_forced_objects[$classname] = $object;
        
        return true;
	}
	public function removeForcedClassObject($classname)
	{
		unset($this->created_forced_objects[$classname]);
        return true;
	}
	public function removeAllForcedClassObjects()
	{
        $this->created_forced_objects = [];
	}
	

	public function new($class) 
	{
		$lower_name = strtolower($class);

		if (isset($this->class_builders[$lower_name])) {
			// this is ready object. Justb return it
			return $this->class_builders[$lower_name];
		}
		if (isset($this->class_alias[$lower_name])) {
			$class = $this->class_alias[$lower_name];
		}
		if (!class_exists($class)) {
			$classspace = 'Classes\\';

			if ($this->routerfront) {
				$classspace = $this->routerfront->getClassesSubSpace();
			}

			$classspace = $this->getAppNameSpace() . $classspace;

			$custom_class = $classspace . str_replace('/','\\',$class);

			if (class_exists($custom_class)) {
				$class = $custom_class;

			} elseif (class_exists($classspace . ucfirst($class))) {
				$class = $classspace . ucfirst($class);
			}
		}
		if (!class_exists($class)) {
			throw new \Exception('Class ' . $class . ' not found');
		}
		// this is mocking for testing
		if (array_key_exists($class,$this->created_forced_objects)) {
            return $this->created_forced_objects[$class];
        }
		// this method should be used only for objects with standard constructor.
		// TODO . Verify the class has that constructor (uses trait or so)
		$object = new $class($this);
		
		if (method_exists($object, 'init')) { 
			// some classes can have extra constructor
        	$object->init();
		}
		return $object;
	}
	/**
	 * It is alias. It is used in the code to create objects
	 */
	public function get($class) 
	{
		return $this->single($class);
	}
	public function single($class) 
	{
		static $instances; 

		if (!is_array($instances)) {
			$instances = [];
		}

		if (!isset($instances[$class])) {
			$instances[$class] = $this->new($class);
		}
		return $instances[$class];
	}
} 
