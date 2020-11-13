<?php

namespace Gelembjuk\WebApp;

use \Gelembjuk\WebApp\Exceptions\ViewException as ViewException;
use \Gelembjuk\WebApp\Exceptions\DoException as DoException;
use \Gelembjuk\WebApp\Exceptions\NotAuthorizedException as NotAuthorizedException;

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
	protected $defaultreaction = null;
	
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
					
					if ($result === true && $this->defaultreaction['success']) {
                        $result = $this->defaultreaction['success'];
                    }
				} catch (\Exception $exception) {
					
					$htmlaction = $this->actionerrordisplay;
					
					if ($exception instanceof DoException) {
						$htmlaction = $exception->getActionOnErrorInHTML($htmlaction);
					} elseif ($this->defaultreaction) {
                        $url = $this->defaultreaction['error']['url'];
                        
                        if (is_array($url)) {
                        
                            if (empty($url['message'])) {
                                $url['message'] = $exception->getMessage();
                            }
                        
                            $url = $this->makeUrl($url);
                        }
                        
                        $exception = new DoException(
                            $url,
                            $exception->getMessage(),
                            $this->defaultreaction['error']['code'],
                            $this->defaultreaction['error']['number'],
                            $this->defaultreaction['error']['htmltype']
                        );
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
					
					if ($exception instanceof ViewException || $exception instanceof NotAuthorizedException) {
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
	/**
	* Return this router name.
	*/
	public function getName() {
		$function = new \ReflectionClass(static::class);
		return $function->getShortName();
	}
	public function makeUrl($opts = array()) {
        // get more options to an url
        $opts = $this->completeUrlOpts($opts);
        
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
	/**
	* DO somethign when action complete 
	*/
	protected function beforeEnd() {
	}
	/**
	* Do somethign before any action started
	*/
	protected function beforeStart() {
	}
	/*
	* Should check if a user is loged in and set user id in the application 
	* This can be called when each request must be authentificated. No need to cal when straditional web session is used
	*/
	protected function initAuthSession() {
	}
	
	protected function filterRedirect($url,$script = false) {
		return array($url,$script);
	}
	/**
	* Returns an url of an error view for this controller.
	* If urls must be built with some specific rules, then this function should be reimplemented in a child class.
	*/
	protected function getErrorURI($message) {
        
        return $this->makeUrl(array('view'=>'error', 'message' => $message));
	}
	/**
	* Get viewer assiiated with this controller
	*/
	protected function getViewer($name = '') {
		if ($name == '') {
			if ($this->defviewname != '') {
				$name = $this->defviewname;
			} else {
                // if viewer name is not provided then name is same as for controller
                // but it can be in other name space (controller and viewers can be in different name spaces)
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
		$this->viewdata = array();
		return $data;
	}
	/**
    * Add some data to display with a viewer in an end of an action (without redirect)
    * This is useful for cases when non HTML response is used and controller must return somethign after an action
	*/
	public function addViewerData($name,$value) 
	{
		$this->viewdata[$name] = $value;
	}
	/**
	* Get inmput from a router 
	*/
	protected function getInput($name,$type='string',$default='',$maxlength=0) 
	{
		return $this->router->getInput($name,$type,$default,$maxlength);
	}
	/**
	* Call when an action requires a user is signed in
	*/
	protected function signinRequired($errormessage = '') 
	{
		if ($this->application->getUserID() == 0) {
			if ($errormessage == '') {
				$errormessage = $this->_('Login Required');
			}
			
			throw new \Exception($errormessage,401);
		}
	}
	/**
	* Function helps to build complete urls. It can be used
	* to add some more arguments to url. For example, some titles/texts for SEO optimization
	*/
	protected function completeUrlOpts($opts) 
	{
        return $opts;
	}
	/**
	* Returns a router for this controller to read input data from it.
	* This implementation returns a default router of an app.
	* If the app has more then 1 router then this function can be implemented in a controller class to work differently.
	*/
	protected function getRouter() 
	{
        return $this->application->getRouter();
    }
	/**
	* Returns a default orl of this controller. This url is used when no other 
	* redirect url is specified in an end of action.
	* Reimplement the function in a child class if some other specific lnk should be generated.
	*/
	protected function getDefaultURI($message = null) 
	{
        return $this->makeUrl(array('message'=>$message));
    }
    
    protected function defineReaction($defaultreaction)
    {
        $this->defaultreaction = $defaultreaction;
    }
}
