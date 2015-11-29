<?php

namespace Gelembjuk\WebApp;

abstract class View {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $responseformat;
	protected $router;
	protected $controller;
	protected $erroronnotfoundview = false;
	protected $viewdata;
	protected $viewstatus;
	protected $viewstatuscode;
	
	protected $htmlouttemplate;
	protected $htmlouttemplate_force = '';
	protected $htmlouttemplate_disable = false;
	protected $htmltemplate_extension;
	protected $htmltemplate;
	protected $headerssent;
	protected $defmodel;
	
	protected $signinreqired;
	
	protected $options;
	protected $application;
	
	public function __construct($application,$router,$controller = null,$options = array()) {
		$this->application = $application;
		$this->router = $router;
		$this->controller = $controller;
		
		$this->signinreqired = false;
		
		$this->viewdata = array();
		$this->headerssent = false;
		
		$this->options = $options;
	}
	/*
	* Is called right after constructor
	* to do some actions on initialisation
	*/
	public function init() {
	}
	public function setController($controller) {
		$this->controller = $controller;
	}
	public function getName() {
		$function = new \ReflectionClass(static::class);
		return $function->getShortName();
	}
	public function doView($actionmethod,$responseformat) {
		if ($this->signinreqired) {
			$this->signinRequired();
		}
		
		$this->responseformat = $responseformat;
		
		if ($this->responseformat == '') {
			$this->responseformat = 'html';
		}
		
		$this->viewstatus = 'ok';	// used to send some extra information from view prepare to display
		$this->viewstatuscode = 200;	// this are http codes. 200 means all is fine
		
		// call a method. it will prepare all data
		$viewmethodname = 'view'.ucfirst($actionmethod);
		
		if( !method_exists($this,$viewmethodname) ) {
			
			if ($this->erroronnotfoundview) {
				$viewmethodname = 'viewError';
				$this->viewstatus = 'not_found';
				$this->viewstatuscode = 404;
				$this->router->setInput('errormessage','Not found');
			} else {
				$viewmethodname = 'view';
			}
		}
		
		// get view data from controller. it can pass something
		$this->viewdata = array_merge($this->viewdata,$this->controller->shiftViewerData());
		
		// result is not important there. 
		// if view throws error then it will be catched above
		$this->$viewmethodname();
		
		// prepare some extra data to display
		$this->beforeDisplay();
		
		// dislay data
		
		$displaymethodname = 'display'.strtoupper($this->responseformat);
		
		if( !method_exists($this,$displaymethodname) ) {
			$displaymethodname = 'displayHTML';
		}
		
		$result = $this->$displaymethodname();
		
		return true;
	}
	// default view is abstract to force to have it in child classes
	abstract protected function view();
	
	/*
	* To set some extra view data in child classes
	*/
	protected function beforeDisplay() {
	}
	protected function viewError() {
		$this->htmlouttemplate_force = '';
		// in child classes this can be redefined to use some better error page
		$this->viewdata['errormessage'] = $this->router->getInput('errormessage');
		
		if ($this->viewdata['errormessage'] == '') {
			$this->viewdata['errormessage'] = $this->router->getInput('message');
		}
		
		$this->router->unSetInput('message');
		
		$this->viewdata['errorcode'] = $this->router->getInput('errorcode','alpha');
		$this->viewdata['errornumber'] = $this->router->getInput('errornumber','int');
		$this->viewstatus = 'error';
		$this->viewstatuscode = 400;
		
		if ($this->viewdata['errornumber'] > 0) {
			$this->viewstatuscode = $this->viewdata['errornumber'];
		} elseif ($this->viewstatuscode > 0) {
			$this->viewdata['errornumber'] = $this->viewstatuscode;
		}
		
		$this->htmltemplate = 'error';
		$this->htmlouttemplate_disable = true;
		
		if (!$this->application->getConfig('devsite')) {
			unset($this->viewdata['errortrace']);
		}

		return true;
	}
	protected function viewSuccess() {
		
		// for normal workflow this should not be executed as part of html response
		// but for case if somethign goes wrong or it is called directly then we will have this page
		$this->htmltemplate = 'success';
		$this->htmlouttemplate_disable = true;
		
		return true;
	}
	protected function displayWithObject($class,$altoption,$displayoptions = array()) {
		
		if (isset($this->options[$altoption])) {
			$class = $this->options[$altoption];
		}
		
		if (!class_exists($class)) {
			throw new \Exception(sprintf('Display class %s not found',$class));
		}
		
		$displayoptions['status'] = $this->viewstatus;
		$displayoptions['statuscode'] = $this->viewstatuscode;
		
		$displayobject = new $class($this->application);
		
		$displayobject->init($displayoptions);

		$displayobject->setData($this->viewdata);
		
		return $displayobject->display();
	}
	protected function displayHTML() {
		
		// all options to init html display object
		$displayoptions = array(
			'tmpdir' => $this->options['tmproot'],
			'templatingclass' => $this->options['templatingclass'],
			'templatepath' => $this->options['htmltemplatespath'],
			'templatesoptions' => $this->options['htmltemplatesoptions'],
			'template' => $this->htmltemplate,
			'view' => $this->getName(),
			'controller' => $this->router->getController(),
			'outtemplate' => $this->router->getInput('outtmpl','alpha','default'),
			'outtemplate_force' => $this->htmlouttemplate_force,
			'noouttemplate' => $this->htmlouttemplate_disable,
			'headerssent' => $this->headerssent,
			// next options are usually in config
			// if not in config then the getConfig can be redefined to return correct values
			'applicationtitle' => $this->application->getConfig('applicationtitle'),
			'applicationdescription' => $this->application->getConfig('applicationdescription'),
			'applicationkeywords' => $this->application->getConfig('applicationkeywords'),
			);
			
		if (empty($displayoptions['templatingclass'])) {
			$displayoptions['templatingclass'] = '\\Gelembjuk\\Templating\\SmartyTemplating';
		}
		
		$this->viewdata['baseurl'] = $this->application->getBasehost();
		$this->viewdata['locale'] = $this->application->getLocale();
		
		if (empty($this->viewdata['applicationtitle'])) {
			$this->viewdata['applicationtitle'] = $displayoptions['applicationtitle'];
		}
		
		return $this->displayWithObject('\\Gelembjuk\\WebApp\\View\\HTML','htmldisplayclass',$displayoptions);
	}
	
	protected function displayHTMLPART() {
		$this->htmlouttemplate_disable = true;
		
		return $this->displayHTML();
	}
	
	protected function displayJSON() {
		return $this->displayWithObject('\\Gelembjuk\\WebApp\\View\\JSON','jsondisplayclass');
	}
	protected function displayJSONDATA() {
		return $this->displayWithObject('\\Gelembjuk\\WebApp\\View\\JSONDATA','jsondatadisplayclass');
	}
	protected function displayXML() {
		
		return $this->displayWithObject('\\Gelembjuk\\WebApp\\View\\XML','xmldisplayclass');
	}
	protected function displayHTTP() {
		
		return $this->displayWithObject('\\Gelembjuk\WebApp\\\View\\HTTP','HTTPdisplayclass');
	}
	protected function getInput($name,$type='string',$default='',$maxlength=0) {
		return $this->router->getInput($name,$type,$default,$maxlength);
	}
	protected function signinRequired($errormessage = '') {
		if ($this->application->getUserID() == 0) {
			if ($errormessage == '') {
				$errormessage = $this->_('Login Required');
			}
			
			throw new \Exception($errormessage);
		}
	}
}
