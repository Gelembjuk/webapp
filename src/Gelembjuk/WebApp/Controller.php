<?php

namespace Gelembjuk\WebApp;

use \Gelembjuk\WebApp\Exceptions\ViewException as ViewException;
use \Gelembjuk\WebApp\Exceptions\DoException as DoException;

abstract class Controller {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $router;
	protected $responseformat;
	protected $actionerrordisplay = 'redirect';
	protected $application;
	protected $defmodelname;
	protected $defmodel;
	protected $defviewname = '';
	protected $viewdata;
	protected $signinreqired;
	
	public function __construct($application,$router = null) {
		$this->application = $application;
		$this->router = $router;
		
		$this->viewdata = array();
		
		if (!$this->router) {
			$this->router = $this->getRouter();
		}
		
		if ($this->defmodelname != '') {
			$this->defmodel = $this->application->getModel($this->defmodelname);
		}
		
		$this->signinreqired = false;
	}
	public function init() {
	}
	public function action() {
		$this->application->setActionController($this);
		
		$this->initAuthSession();
		
		list($actiontype,$actionmethod,$this->responseformat) = $this->router->getActionInfo();
		
		try {
            $this->beforeStart();
		} catch(\Exception $e) {
            $actiontype = 'view';
            $actionmethod = 'error';
            
            $this->router->setInput('errormessage',$e->getMessage());
            $this->router->setInput('errornumber',$e->getCode());
		}
		
		// set response format to error handler. so if error happens 
		// after this place then reponse is in correct format
		$errorhandler = $this->application->getErrorHandler();
		
		if (is_object($errorhandler)) {
			$errorhandler->setViewFormat( ($this->responseformat != '') ? $this->responseformat:'html' );
		}
		
		if ($actiontype == 'do') {
			$methodname = 'do'.$actionmethod;
			
			if( method_exists($this,$methodname) ) {
				try {
					if ($this->signinreqired) {
						$this->signinRequired();
					}
					
					$result = $this->$methodname();
					
					if( $result === false ) {
						throw new \Exception('Unknown error on DO action');
					}
				} catch (\Exception $exception) {
					
					$htmlaction = $this->actionerrordisplay;
					
					if ($exception instanceof DoException) {
						$htmlaction = $exception->getActionOnErrorInHTML($htmlaction);
					}
					
					if ($exception instanceof DoException) {
						$htmlaction = $exception->getActionOnErrorInHTML($htmlaction);
					}
					
					if ($this->isHTMLResp() && $htmlaction == 'redirect') {
						// error can be displayed with redirect or html page
						$actiontype = 'redirect';
						
						if ($exception instanceof DoException) {
							$actionmethod = $exception->getUrl();
						} else {
							$actionmethod = $this->getErrorURI($exception->getMessage());
							
							if ($actionmethod == '') {
								$actionmethod = $this->getDefaultURI($exception->getMessage());
							}
						}
					} else {
						$code = 'error';
						
						if ($exception instanceof DoException) {
							$code = $exception->getTextCode();
						}
					
						$this->addViewerData('errortrace',$exception->getTraceAsString());
						
						$this->router->setErrorPage($exception->getMessage(),$code,$exception->getCode());
						list($actiontype,$actionmethod,$this->responseformat) = $this->router->getActionInfo();
					}
				}
				
				if( is_array($result) ) {
					;
					list($actiontype,$actionmethod,$responseformat) = $result;
					
					// this is short way to return universal 'success' for html and other type of response formats
					// format success:viewaction or just success
					if (strpos($actiontype,'success') === 0) {
						if ($this->isHTMLResp()) {
							// $actionmethod contains a redirect url
							$actiontype = 'redirect';
						} else {
							if ($actiontype == 'success') {
								$actionmethod = 'success';
							} else {
								$actionmethod = substr($actiontype,8);
							}
							
							$actiontype = 'view';
						}
					}
					
					if ($responseformat != '') {
						$this->responseformat = $responseformat;
					}
				} elseif($actiontype == 'do') {
					//$result is true and all other values except array and false
					// and $actiontype was not yet set before
					$actiontype = 'view';
					$actionmethod = ($this->isHTMLResp())?'':'success'; // default view for this controller
					
					if ($this->isHTMLResp() && $this->actionerrordisplay == 'redirect') {
						$actiontype = 'redirect';
						$actionmethod = $this->getDefaultURI();
					}
				}
				
			}
		}
		
		// do view action
		// view can be used as separate action or as part of DO action to display a state
		if ($actiontype == 'view') {
			$viewer = $this->getViewer();
			
			// to be sure the view points to this controller
			$viewer->setController($this);
			
			try {
				if ($this->signinreqired) {
					$this->signinRequired();
				}
				// inside this method must be done everything, headers, all output
				$result = $viewer->doView($actionmethod,$this->responseformat);
				
				$origactiontype = $actiontype;
				$actiontype = '';
				
				if (is_array($result)) {
					$origactionmethod = $actionmethod;
					
					list($actiontype,$actionmethod) = $result;
					
					if ($actiontype != 'redirect') {
						$actiontype = '';
						$actionmethod = $origactionmethod ;
					} 
					unset($origactionmethod );
					$result = true;
				}
				
				if ($result !== true && $result !== false) {
					$actiontype = $origactiontype;
					throw new \Exception('Unknown error on View action');
				}
				
			} catch (\Exception $exception) {
				// when view can not be executed
				// it throws exception
				$htmlaction = ($actionmethod != 'error') ? $this->actionerrordisplay:'view';
				
				if ($exception instanceof ViewException) {
					$htmlaction = $exception->getActionOnErrorInHTML($htmlaction);
				}
				
				if ($this->isHTMLResp() && $htmlaction == 'redirect') {
					// the exception can contain url
					$actiontype = 'redirect';
					
					if ($exception instanceof ViewException) {
						$actionmethod = $exception->getUrl();
						
						if($actionmethod == 'defaultview') {
							$actionmethod = $this->getDefaultURI($exception->getMessage());
						}
					} else {
						$actionmethod = $this->getErrorURI($exception->getMessage());
							
						if ($actionmethod == '') {
							$actionmethod = $this->getDefaultURI($exception->getMessage());
						}
					}
				} else {
					
					$actiontype = 'view';
					$actionmethod = 'error';
					$this->router->setInput('errormessage',$exception->getMessage());
					$this->router->setInput('errornumber',$exception->getCode());
					
					if ($exception instanceof ViewException) {
						$this->router->setInput('errorcode',$exception->getTextCode());
					}
					
					$this->addViewerData('errortrace',$exception->getTraceAsString());
				}
			}			// do view again. it can be only in case of error and response format is not html
			if ($actiontype == 'view') {
				$result = $viewer->doView($actionmethod,$this->responseformat);
				// don't catch errors. if there is error then will be catched as unknown
				// becase display of error page must be very stable and should not throw exceptions
			}
			
			if ($actiontype != 'redirect') {
				// all work should be done already
				$this->beforeEnd();
				return true;
			}
			
		}
		if ($actiontype == 'redirect') {
			$this->beforeEnd();
			$this->redirect($actionmethod);
		}
		
		throw new \Exception('Unknown action in a controller '.$this->getName());
	}
	public function actionOffline() {
		$this->application->setActionController($this);
		
		list($actiontype,$actionmethod,$this->responseformat) = $this->router->getActionInfo();
		
		// set response format to error handler. so if error happens 
		// after this place then reponse is in correct format
		$errorhandler = $this->application->getErrorHandler();
		
		if (is_object($errorhandler)) {
			$errorhandler->setViewFormat( ($this->responseformat != '') ? $this->responseformat:'html' );
		}
		
		$viewer = $this->getViewer();
		
		// to be sure the view points to this controller
		$viewer->setController($this);
		
		$result = $viewer->doView('offline',$this->responseformat);
			
		return true;
	}
	protected function isHTMLResp() {
		return ($this->responseformat == '' || $this->responseformat == 'html');
	}
	protected function redirect($url,$script = false) {
        // extract message from an url and set it to the session 
        
        $match = '/(message=([^&]*))/';
        
        if (preg_match($match, $url, $m)) {
            $message = urldecode($m[2]);

            $url = preg_replace($match, '', $url);
            
            if (substr($url,-2) == '?&') {
                $url = substr($url,0,-2);
            } elseif (substr($url,-1) == '&' || substr($url,-1) == '?') {
                $url = substr($url,0,-1);
            }
            
            $this->router->setMessageToSession($message);
        }
        
		list($url,$script) = $this->filterRedirect($url,$script);
		
		if ($script) {
			echo "<script type='text/javascript'>\n".
				"top.location.href='$url';\n".
				"</script>";
			exit;
		}
		header("Location: $url",true,301);
		exit;
	}
	public function getName() {
		$function = new \ReflectionClass(static::class);
		return $function->getShortName();
	}
	public function makeUrl($opts = array()) {
		$opts['controller'] = $this->getName();
		return $this->router->makeUrl($opts);
	}
	/*
	* Get native model of this controller
	* It works fine for simple combinations of controller/view/model
	*/
	public function getDefModel() {
		return $this->defmodel;
	}
	
	protected function beforeEnd() {
	}
	protected function beforeStart() {
	}
	/*
	* Should check if a user is loged in and set user id in the application
	*/
	protected function initAuthSession() {
	}
	
	protected function filterRedirect($url,$script = false) {
		return array($url,$script);
	}
	protected function getErrorURI($message) {
		return ''; // should be defined in child class
	}
	
	protected function getViewer($name = '') {
		if ($name == '') {
			if ($this->defviewname != '') {
				$name = $this->defviewname;
			} else {
				$name = $this->getName();
			}
		}
		// if this was not reloaded in child class then it means view name is same as controller
		return $this->application->getView($name,$this->router,$this);
	}
	public function getViewerData() {
		return $this->viewdata;
	}
	public function shiftViewerData() {
		$data = $this->viewdata;
		$this->logQ('return '.print_r($data,true));
		$this->viewdata = array();
		return $data;
	}
	public function addViewerData($name,$value) {
		$this->viewdata[$name] = $value;
		$this->logQ('add '.$name.' = '.$value);
	}
	protected function getInput($name,$type='string',$default='',$maxlength=0) {
		return $this->router->getInput($name,$type,$default,$maxlength);
	}
	protected function signinRequired($errormessage = '') {
		if ($this->application->getUserID() == 0) {
			if ($errormessage == '') {
				$errormessage = $this->_('Login Required');
			}
			
			throw new \Exception($errormessage,401);
		}
	}
	
	abstract protected function getRouter();
	
	abstract protected function getDefaultURI($message = null);
}
