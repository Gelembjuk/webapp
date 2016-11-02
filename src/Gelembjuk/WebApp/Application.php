<?php

namespace Gelembjuk\WebApp;

abstract class Application {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected static $instance;

	protected $errorhandler = null;
	protected $dbobjects;
	protected $dbengines;
	protected $models;
	protected $controllers;
	protected $routers;
	protected $actioncontroller;
	protected $cache;
	
	protected $localeautoload;
	protected $config;
	protected $options;
	protected $configextra;
	
	protected $userid;
	
	/**
	* Class paces for MVC model components
	* This can be modified by application in case if multiple spaces are needed
	* For example, for admin side, MVC can be isolated 
	*/
	protected $controllerspace;
	protected $viewspace;
	protected $modelspace;
	protected $dbclassspace;
	protected $routerspace;

	public function __construct() {
		$this->config = null;
		$this->configextra = null;
		$this->userid = 0;
		$this->dbobjects = array();
		$this->dbengines = array();
		$this->models = array();
		$this->views = array();
		$this->controllers = array();
		$this->localeautoload = false;
	}
	public static function getInstance() {
		$class = get_called_class();
		
		if (!self::$instance[$class]) {
			self::$instance[$class] = new static();
		}
		return self::$instance[$class];
	}
	
	public function init($config,$options = array()) {
		$this->config = $config;
		$this->options = $options;
		
		// set logger
		if (isset($this->options['logger'])) {
			$this->logger = $this->options['logger'];
			unset($this->options['logger']);
			
		} elseif (isset($this->options['loggerclass'])) {
			if (!class_exists($this->options['loggerclass'])) {
				throw new \Exception('Logger class ' . $this->options['loggerclass'] . ' not found');
			}
			
			$this->logger = new $this->options['loggerclass']($this->options['loggeroptions']);
			unset($this->options['loggerclass']);
			unset($this->options['loggeroptions']);
		}
		
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
		
		if (isset($this->options['applicationnamespace'])) {
			if (!isset($this->options['modelsnamespace'])) {
				$this->options['modelsnamespace'] = $this->options['applicationnamespace'] . 'Models\\';
			}
			if (!isset($this->options['controllersnamespace'])) {
				$this->options['controllersnamespace'] = $this->options['applicationnamespace'] . 'Controllers\\';
			}
			if (!isset($this->options['databasenamespace'])) {
				$this->options['databasenamespace'] = $this->options['applicationnamespace'] . 'Database\\';
			}
			if (!isset($this->options['viewsnamespace'])) {
				$this->options['viewsnamespace'] = $this->options['applicationnamespace'] . 'Views\\';
			}
			if (!isset($this->options['routersnamespace'])) {
				$this->options['routersnamespace'] = $this->options['applicationnamespace'] . 'Routers\\';
			}
		}
		
        $this->controllerspace = $this->options['controllersnamespace'];
        $this->viewspace = $this->options['viewsnamespace'];
        $this->modelspace = $this->options['modelsnamespace'];
        $this->dbclassspace = $this->options['databasenamespace'];
        $this->routerspace = $this->options['routersnamespace'];
		
		$this->options['basehost'] = $this->getBasehost();
		
		/*
		No need in this yet
		if (isset($this->config->appoptions) && is_array($this->config->appoptions)) {
			// needed to send some config options to all other classes as one set
			$this->options = array_merge($this->options,$this->config->appoptions);
		}
		*/
	}
	/*
	* To add some options after init executed
	*/
	public function addOption($key,$value) {
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
	protected function getControllerFullClass($controllerclass)
	{
        if (substr($controllerclass,0,1) == '\\') {
            // this is absolute class name
            return $controllerclass;
        }
        return $this->controllerspace . $controllerclass;
    }
	public function getController($controllername = '',$exceptiononnotfound = false, $alwayscreatenew = false) {
		if ($controllername != '') {
			$controllername = ucfirst($controllername);
		}
		
		if (!$alwayscreatenew && $controllername != '' && isset($this->controllers[$controllername])) {
			return $this->controllers[$controllername];
		}
		
		$router = null;
		
		if ($controllername == '') {
			if ($this->getOption('DefaultController') != '') {
				$controllername = ucfirst($this->getOption('DefaultController'));
			} else {
				$router = $this->getRouter();
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
			$controllerpath = $this->cgetControllerFullClass($this->getDefaultControllerName());
		}
		
		if (!class_exists($controllerpath)) {
			throw new \Exception('Default controller not found');
		}
		
		if (!is_subclass_of($controllerpath, '\\Gelembjuk\\WebApp\\Controller')) {
			throw new \Exception('Controller must be subclass of \\Gelembjuk\\WebApp\\Controller');
		}

		$this->logQ('Make Controller '.$controllerpath,'application');
		
		$controller = new $controllerpath($this,$router);
		
		$controller->init();
		
		$this->controllers[$controllername] = $controller;
		
		return $controller;
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
        return $this->routerspace . $routerclass;
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
		
		if (!$alwayscreatenew && $routername != '' && isset($this->routers[$routername])) {
			return $this->routers[$routername];
		}
		
		if (!class_exists($routername)) {
			throw new \Exception('Default router not found');
		}

		if (!is_subclass_of($routername, '\\Gelembjuk\\WebApp\\Router')) {
			throw new \Exception('Router must be subclass of \\Gelembjuk\\WebApp\\Router');
		}

		$this->logQ('Make Router '.$routername,'application');
		
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
	
	public function getDBO($name,$profile = 'default') {		
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
        return $this->dbclassspace . $dboclass;
    }
	public function getDBONew($name,$profile = 'default') {		
		
		$engine = $this->getDBEngine($profile);
		
		$classpath = $this->getDBOFullClass($name);

		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('DB class %s not found',$classpath));
		}
		
		if (!is_subclass_of($classpath, '\\Gelembjuk\\DB\\Base')) {
			throw new \Exception('DB Object must be subclass of \\Gelembjuk\\DB\\Base');
		}
		
		$this->logQ('Make DBO '.$name,'application');
		
		$object = new $classpath($engine,$this);
		
		$this->dbobjects[$name.'_'.$profile] = $object;
		
		return $object;
	}
	
	protected function getModelFullClass($modelclass)
    {
        if (substr($modelclass,0,1) == '\\') {
            // this is absolute class name
            return $modelclass;
        }
        return $this->modelspace . $modelclass;
    }
	
	public function getModel($name,$options = array(),$alwayscreatenew = false) {
		$modelkey = md5(json_encode($options));	
		
		if (!$alwayscreatenew && isset($this->models[$name.$modelkey])) {
			return $this->models[$name.$modelkey];
		}

		$this->logQ('Make Model '.$name,'application');
		
		$classpath = $this->getModelFullClass($name);

		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('Model class %s not found',$classpath));
		}
		
		if (!is_subclass_of($classpath, '\\Gelembjuk\\WebApp\\Model')) {
			throw new \Exception('Model must be subclass of \\Gelembjuk\\WebApp\\Model');
		}
		
		$object = new $classpath($this,$options);
		
		$this->models[$name.$modelkey] = $object;
		
		return $object;
	}
	protected function getViewFullClass($viewclass)
    {
        if (substr($viewclass,0,1) == '\\') {
            // this is absolute class name
            return $viewclass;
        }
        return $this->viewspace . $viewclass;
    }
	public function getView($name,$router,$controller = null) {		
		$classpath = $this->getViewFullClass($name);

		if (!class_exists($classpath)) {
			throw new \Exception(sprintf('View class %s not found',$classpath));
		}
		
		if (!$router) {
			throw new \Exception(sprintf('View class %s requires a router object',$classpath));
		}
		
		if (!is_subclass_of($classpath, '\\Gelembjuk\\WebApp\\View')) {
			throw new \Exception('Model must be subclass of \\Gelembjuk\\WebApp\\View');
		}
		
		$object = new $classpath($this,$router,$controller,$this->options);
		$object->init();
		
		return $object;
	}
	public function getConfig($name) {
		return $this->config->$name;
	}
	public function getConfigExtra($name) {
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
	public function makeUrl($controllername,$opts = array()) {
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
	}
	// absolute url
	public function makeAbsUrl($controllername,$opts = array()) {
        
        $relativeurl = $this->makeUrl($controllername,$opts);
        
        $baseurl = $this->getBasehost();
        
        if (substr($baseurl,-1) == '/' && substr($relativeurl,0,1) == '/') {
            $relativeurl = substr($relativeurl,1);
        }
        
        return $baseurl . $relativeurl;
    }
	public function makeUrlByRouter($router,$opts = array()) {
		return $router->makeUrl($opts);
	}
	protected function getRouterNameFromRequest() {
		return $this->getDefaultRouter();
	}
	
	// Profiling
	public function profilerAction($type,$time,$string) {
		return true;
	}
	// ================== ABSTRACT =================
	abstract protected function getDefaultRouter();
	abstract protected function getDefaultControllerName();
} 
