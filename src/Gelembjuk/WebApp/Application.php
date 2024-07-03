<?php

namespace Gelembjuk\WebApp;

class Application {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected static $instance;

	protected $errorhandler = null;
	protected $dbobjects;
	protected $dbengines;
	protected $views;
	protected $controllers;
	protected $routers = [];

	protected $class_alias = [];
	protected $class_builders = [];
	/*
	* Mocks for objects
	*/
	protected $dbobjectsready = [];
    protected $dbenginesready = [];
    protected $controllersready = [];
    protected $routersready = [];
    protected $viewsready = [];
	/**
	* @var
	* First router object loaded. It can give some useful info about the application mode
	*/
	protected $routerfront = null;
	protected $defaultroutername = '';
	protected $actioncontroller;
	protected $defaultcontrollername = '';
	protected $cache;
	
	protected $localeautoload;
	protected $config;
	protected $options;
	protected $configextra;
	
	protected $exceptiononurlmake = true;
	
	protected $userid;

	protected $requestUniqueID;
	
	/**
	* Class spaces for MVC model components
	* This can be modified by application in case if multiple spaces are needed
	* For example, for admin side, MVC can be isolated 
	*/
	protected $controllerspace;
	protected $viewspace;
	protected $classesspace;
	protected $dbclassspace;
	protected $routerspace;

	public function __construct() {
		$this->config = null;
		$this->configextra = null;
		$this->userid = 0;
		$this->dbobjects = array();
		$this->dbengines = array();
		$this->views = array();
		$this->controllers = array();
		$this->localeautoload = false;

		$this->requestUniqueID = substr(md5(uniqid()),0,15);
	}
	public static function getInstance() {
		$class = get_called_class();
		
		if (!is_array(self::$instance)) {
			self::$instance = [];
		}
		if (!isset( self::$instance[$class] )) {
			self::$instance[$class] = new static();
		}
		return self::$instance[$class];
	}
	
	public function init($config,$options = []) {
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
		
		// COMPONENTS LOCATION ===========================================================================
		if (isset($this->options['applicationnamespaceprefix'])) {
            // Use standard model where coponents are sub namespaces with standard names
            // this works for most cases, including multiple spaces
			if (!isset($this->options['classesnamespace'])) {
				$this->options['classesnamespace'] = $this->options['applicationnamespaceprefix'] . 'Classes\\';
			}
			if (!isset($this->options['controllersnamespace'])) {
				$this->options['controllersnamespace'] = $this->options['applicationnamespaceprefix'] . 'Controllers\\';
			}
			if (!isset($this->options['databasenamespace'])) {
				$this->options['databasenamespace'] = $this->options['applicationnamespaceprefix'] . 'Database\\';
			}
			if (!isset($this->options['viewsnamespace'])) {
				$this->options['viewsnamespace'] = $this->options['applicationnamespaceprefix'] . 'Views\\';
			}
			if (!isset($this->options['routersnamespace'])) {
				$this->options['routersnamespace'] = $this->options['applicationnamespaceprefix'] . 'Routers\\';
			}
		} elseif (isset($this->options['applicationnamespace'])) {
            // Use simplest approach when components have no sub namespaces and, in fact are in one folder
            // this works for small applications
			if (!isset($this->options['classesnamespace'])) {
                $this->options['classesnamespace'] = $this->options['applicationnamespace'];
            }
            if (!isset($this->options['controllersnamespace'])) {
                $this->options['controllersnamespace'] = $this->options['applicationnamespace'];
            }
            if (!isset($this->options['databasenamespace'])) {
                $this->options['databasenamespace'] = $this->options['applicationnamespace'];
            }
            if (!isset($this->options['viewsnamespace'])) {
                $this->options['viewsnamespace'] = $this->options['applicationnamespace'];
            }
            if (!isset($this->options['routersnamespace'])) {
                $this->options['routersnamespace'] = $this->options['applicationnamespace'];
            }
        }
		
        $this->controllerspace = $this->options['controllersnamespace'];
        $this->viewspace = $this->options['viewsnamespace'];
		$this->classesspace = $this->options['classesnamespace'];
        $this->dbclassspace = $this->options['databasenamespace'];
        $this->routerspace = $this->options['routersnamespace'];
		
		$this->options['basehost'] = $this->getBasehost();
		
		if (isset($this->options['defaultroutername'])) {
            $this->defaultroutername = $this->options['defaultroutername'];
		}
		if (isset($this->options['defaultcontrollername'])) {
            $this->defaultcontrollername = $this->options['defaultcontrollername'];
        }
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
	public function action() {
		// controller will be detected by router and created
		$controller = $this->getController();

		if ($this->getConfig('offline')) {
			return $controller->actionOffline();
		}
		
		return $controller->action();
	}
	public function setUserID($userid) {
		$this->userid = $userid;
	}
	public function getUserID() {
		return $this->userid;
	}
	public function getUserRecord() {
		return array('id' => $this->getUserID());
	}
	protected function getControllerFullClass($controllerclass, $prefix = '')
	{
        if (substr($controllerclass,0,1) == '\\') {
            // this is absolute class name
            return $controllerclass;
        }
        
        $controllerclass = ucfirst($controllerclass);
        
        if ($prefix == '') {
            return $this->controllerspace . $controllerclass;
        } else {
            return $prefix . $controllerclass;
        }
    }
	public function getController($controllername = '',$exceptiononnotfound = false, $alwayscreatenew = false) {
		if ($controllername != '') {
			$controllername = ucfirst($controllername);
		}
		
		$router = $this->getRouter();
		
		if ($controllername == '') {
			if ($this->getOption('DefaultController') != '') {
				$controllername = ucfirst($this->getOption('DefaultController'));
			} else {
				if ($this->routerfront === null) {
                    $this->routerfront = $router;
                    $this->frontRouterLoaded();
				}
				
				$controllername = $router->getController();
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
		if (array_key_exists($controllerpath,$this->controllersready)) {
            return $this->controllersready[$controllerpath];
		}
		
		if (!$alwayscreatenew && isset($this->controllers[$controllerpath])) {
            return $this->controllers[$controllerpath];
        }

		$controller = new $controllerpath($this);
		
		$controller->withRouter($router);
		$controller->init();
		
		$this->controllers[$controllerpath] = $controller;
		
		return $controller;
	}
	/**
	* This is the hook function to do some action when a front router loaded and controller is not loaded yet
	*/
	protected function frontRouterLoaded()
	{
        // Implement somethign in your application
	}
	public function setActionController($object) {
		$this->actioncontroller = $object;
	}
	public function getActionController() {
		return $this->actioncontroller;
	}
	public function getCache() {
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
        return $this->routerspace . ucfirst($routerclass);
    }
	public function getRouter($routername = '', $alwayscreatenew = false) {
		
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
        if (array_key_exists($routername,$this->routersready)) {
            return $this->routersready[$routername];
        }
		
		if (!$alwayscreatenew && $routername != '' && isset($this->routers[$routername])) {
			return $this->routers[$routername];
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
		
		$this->routers[$routername] = $router;
		
		unset($router);
		
		return $this->routers[$routername];
	}
	
	protected function getDBEngine($profile = 'default') {
		if ($profile == '') {
			$profile = 'default';
		}
		
		// this is for mocking on testing
        if (array_key_exists($engineclass,$this->dbenginesready)) {
            return $this->dbenginesready[$engineclass];
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
	
	public function getDBO($name,$profile = 'default') 
	{	
        // this is for mocking on testing
        $classpath = $this->getDBOFullClass($name);
        if (array_key_exists($classpath,$this->dbobjectsready)) {
            return $this->dbobjectsready[$classpath];
        }
        
		if (isset($this->dbobjects[$name.'_'.$profile])) {
			return $this->dbobjects[$name.'_'.$profile];
		}

		return $this->getDBONew($name,$profile);
	}
	protected function getDBOFullClass($dboclass)
    {
        if (substr($dboclass,0,1) == '\\') {
            // this is absolute class name
            return $dboclass;
        }
		$dboclass = str_replace('/','\\',$dboclass);
        return $this->dbclassspace . ucfirst($dboclass);
    }
	public function getDBONew($name,$profile = 'default') {		
		
		$engine = $this->getDBEngine($profile);
		
		$classpath = $this->getDBOFullClass($name);

		// this is for mocking on testing
        if (array_key_exists($classpath,$this->dbobjectsready)) {
            return $this->dbobjectsready[$classpath];
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
        return $this->viewspace . ucfirst($viewclass);
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
        if (array_key_exists($classpath,$this->viewsready)) {
            return $this->viewsready[$classpath];
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
			throw new \Exception(sprintf('View class %s not found',$classpath));
		}

		if (!is_subclass_of($classpath, '\\Gelembjuk\\WebApp\\Widget')) {
			throw new \Exception('Widget must be subclass of \\Gelembjuk\\WebApp\\Widget');
		}

		$object = new $classpath($this);
		$object->init();
		
		return $object;

	}
	public function getConfig($name) 
	{
		if (property_exists($this->config, $name)) {
			return $this->config->$name;
		}
		return null;
	}
	public function getConfigExtra($name) 
	{
		if ($this->configextra == null) {
			// load config before using
			
			if (is_array($this->options['extraconfig'])) {
				$configfile = $this->options['extraconfig'][0];
				$configclass = $this->options['extraconfig'][1];
				
				if (file_exists($configfile)) {
					require_once($configfile);
					
					if (class_exists($configclass)) {
						$this->configextra = new $configclass();
					}
				}
			}
			
			if ($this->configextra == null) {
				$this->configextra = new \stdClass();
			}
		}
		return $this->configextra->$name;
	}
	public function getOption($name) {
		return $this->options[$name];
	}
	public function setErrorHandler($errorhandlerobject) {
		$this->errorhandler = $errorhandlerobject;
	}
	public function getErrorHandler() {
		return $this->errorhandler;
	}
	public function getBasehost() {
		if ($this->getConfig('basehost') != '') {
			return $this->getConfig('basehost');
		}
		$hostinfo = new \Gelembjuk\WebApp\Server\Host();
		return $hostinfo->getBaseHost();
	}
	// build urls
	public function makeUrl($controllername,$opts = array()) 
	{
        try {
            if ($controllername == '') {
                // try to get currect action controller
                $curactioncontrollerobject = $this->getActionController();
                
                if (is_object($curactioncontrollerobject)) {
                    $controllername = $curactioncontrollerobject;
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
	public function makeUrlByRouter($router,$opts = array()) 
	{
		return $router->makeUrl($opts);
	}
	/**
	 * It can be reloaded in a child class to define a single url where to send a user in case if login is required
	 */
	public function getDefaultAuthExceptionRedirectUrl()
	{
		return null;
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
        if ($this->defaultroutername == '') {
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
	
	public function setStandardClassObjectReady($object, $type, $classname)
	{
        if ($type == 'controller') {
            $this->controllersready[$classname] = $object;
            
        } elseif ($type == 'dbobject') {
            $this->dbobjectsready[$classname] = $object;
            
        } elseif ($type == 'dbengine') {
            $this->dbenginesready[$classname] = $object;
            
        } elseif ($type == 'router') {
            $this->routersready[$classname] = $object;
            
        } elseif ($type == 'view') {
            $this->viewsready[$classname] = $object;
        } 
        
        return true;
	}
	public function removeStandardClassObjectReady($type, $classname)
	{
        if ($type == 'controller') {
            unset($this->controllersready[$classname]);
            
        } elseif ($type == 'dbobject') {
            unset($this->dbobjectsready[$classname]);
            
        } elseif ($type == 'dbengine') {
            unset($this->dbenginesready[$classname]);
            
        } elseif ($type == 'router') {
            unset($this->routersready[$classname]);
            
        } elseif ($type == 'view') {
            unset($this->viewsready[$classname]);
        } 
        
        return true;
	}
	public function removeAllStandardClassObjectReady()
	{
        $this->controllersready = [];
        $this->dbobjectsready = [];
        $this->dbenginesready = [];
        $this->routersready = [];
        $this->viewsready = [];
	}
	
	/*
	* Create new object and set application to it.
	* The class must use the trait Context
	* It is old method. Deprecated
	*/
	public function newBlessed($class)
	{
		$object = new $class();

		if (method_exists($object, 'setApplication')) { 
        	$object->setApplication($this);
		}
		
        return $object;
	}

	public function new($class) 
	{
		$lower_name = strtolower($class);

		if (isset($this->class_builders[$lower_name])) {
			// this is rady object. Justb return it
			return $this->class_builders[$lower_name];
		}
		if (isset($this->class_alias[$lower_name])) {
			$class = $this->class_alias[$lower_name];
		}
		if (!class_exists($class)) {
			$custom_class = $this->classesspace . str_replace('/','\\',$class);

			if (class_exists($custom_class)) {
				$class = $custom_class;

			} elseif (class_exists($this->classesspace . ucfirst($class))) {
				$class = $this->classesspace . ucfirst($class);
			}
		}
		if (!class_exists($class)) {
			throw new \Exception('Class ' . $class . ' not found');
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
	public function get($class) {
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
