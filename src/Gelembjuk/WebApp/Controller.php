<?php

namespace Gelembjuk\WebApp;

use Exceptions\ViewException as ViewException;
use Exceptions\DoException as DoException;
use Exceptions\FormException as FormException;

abstract class Controller {
	use RouterAccessTrait;// includes also AppIntegratedTrait
	
	protected $actionerrordisplay = 'redirect';
	/**
	 * Define where a redirect should go it error happens.
	 * It is the name of view in corresponding view.
	 */
	protected $viewInCaseOfError = '';
	/**
	 * The url where a user should be redirected in case of error during an action
	 * This will be used only if there is no custom url in an exception object
	 */
	protected $redirectUrlInCaseOfError = '';

	/**
	 * Optional properties. Some defaul object to do most of work for this controller
	 */
	protected $def_pool = null;
	protected $def_objects_pool_name = null;

	protected $defviewname = '';
	protected $viewdata = [];
	
	protected $defaultreaction = null;
	
	public function init() 
	{
		if ($this->def_objects_pool_name != '') {
			$this->def_pool = $this->single($this->def_objects_pool_name);
		}
	}
	public function action() 
	{
		$this->application->setActionController($this);
		
		$this->initAuthSession();
		
		list($actiontype,$actionmethod,$this->responseformat) = $this->router->getActionInfo();

		// this is used for message to display to a user after redirect
		$messageToDisplay = null;
		
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

		// widget can return a part of page
		if ($actiontype == 'widget') {
			try {
				$widgetObj = $this->application->getWidget($actionmethod)
					->withRouter($this->router)
					->withResponseFormat($this->responseformat);

				$widgetObj->display();
				
				return ;
			} catch (\Exception $exception) {
				$actiontype = 'view';
				$actionmethod = 'error';
				$this->router->setInput('errormessage',$exception->getMessage());
				$this->router->setInput('errornumber',$exception->getCode());
				
				if ($exception instanceof ViewException) {
					$this->router->setInput('errorcode',$exception->getTextCode());
				}
			}
		}
		
		if ($actiontype == 'do') {
			$methodname = 'do'.$actionmethod;
			
			if( method_exists($this,$methodname) ) {
				try {
					$this->checkIfSignedInRequired();
					
					$result = $this->callMethodExternally($methodname);
					
					if( $result === false ) {
						throw new \Exception('Unknown error on DO action');
					}
					
					if ($result === true) {
						$result = $this->getDefaultSuccessResponse() ?: $result;
                    }

					if ($result === true) {
						$result = new Response\SuccessResponse();
                    }
				} catch (\Exception $exception) {
					
					$htmlaction = $this->actionerrordisplay;
					
					if ($exception instanceof DoException) {// DoException is a child class for ViewException
						// get action from exception
						$htmlaction = $this->getActionOnErrorInHTML($exception, $htmlaction);

					} else {
						if ($exception instanceof FormException) {
							// this helps to return input name where an error appeared. It is useful for automation of forms processing
							$this->addViewerData('input',$exception->getInput());
						}
						// get default reaction, for example if errors should be redirected to some specific page 
						$exception = $this->getDefaultDoException($exception) ?: $exception;
					}
					
					// error can be displayed with redirect or html page
					if ($this->isHTMLResp() && $htmlaction == 'redirect') {
						
						$actiontype = 'redirect';

						$messageToDisplay = 'e:'.$exception->getMessage();

						$actionmethod = $this->getRedirectUrlOnError($exception, $actionmethod);

					} else {
						$code = 'error';
						
						if ($exception instanceof DoException) {
							$code = $exception->getTextCode();
						}

						$this->addViewerData('errortrace',$exception->getFile().' '.$exception->getLine().'; '.$exception->getTraceAsString());
						
						$this->router->setErrorPage($exception->getMessage(),$code,$exception->getCode(), $this->responseformat);
						list($actiontype,$actionmethod,$this->responseformat) = $this->router->getActionInfo();
					}
				}
				
				if( is_array($result) || $result instanceof Response\Response ) {
					
					if (is_array($result)) {
						list($actiontype,$actionmethod,$responseformat,$message) = $result;
					} else {
						list($actiontype,$actionmethod,$responseformat, $message) = $result->getActionInfo();
					}
					
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

					if ($actiontype == 'redirect' && empty($actionmethod)) {
						$actionmethod = $this->getDefaultURI();
					}
					
					if (!empty($responseformat)) {
						$this->responseformat = $responseformat;
					}
					if (!empty($message)) {
						$messageToDisplay = $message;
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
				$this->checkIfSignedInRequired();
				
				// inside this method must be done everything, headers, all output
				$result = $viewer->doView($actionmethod,$this->responseformat);
				
				$origactiontype = $actiontype;
				$actiontype = '';
				
				if (is_array($result) || $result instanceof Response\Response) {
					$origactionmethod = $actionmethod;
					
					if (is_array($result)) {
						list($actiontype,$actionmethod,$responseformat,$message) = $result;
					} else {
						list($actiontype,$actionmethod,$responseformat,$message) = $result->getActionInfo();
					}
					
					if ($actiontype != 'redirect') {
						$actiontype = '';
						$actionmethod = $origactionmethod ;
					} 
					unset($origactionmethod );
					$result = true;

					if (!empty($message)) {
						$messageToDisplay = $message;
					}
				}
				
				if ($result !== true && $result !== false) {
					$actiontype = $origactiontype;
					throw new \Exception('Unknown error on View action');
				}
				
			} catch (\Exception $exception) {
				// when view can not be executed
				// it throws exception
				$htmlaction = ($actionmethod != 'error') ? $this->actionerrordisplay:'view';
				
				// this will check the exception class and extract action from it if possible
				$htmlaction = $this->getActionOnErrorInHTML($exception, $htmlaction);
				
				if ($this->isHTMLResp() && $htmlaction == 'redirect') {
					
					$actiontype = 'redirect';

					$messageToDisplay = 'e:'.$exception->getMessage(); // e: means eror and it is used to select correct display class
					// the exception can contain url
					$actionmethod = $this->getRedirectUrlOnError($exception, $actionmethod);

				} else {
					
					$actiontype = 'view';
					$actionmethod = 'error';
					$this->router->setInput('errormessage',$exception->getMessage());
					$this->router->setInput('errornumber',$exception->getCode());
					
					if ($exception instanceof ViewException) {
						$this->router->setInput('errorcode',$exception->getTextCode());
					}
					
					$this->addViewerData('errortrace',$exception->getFile().' '.$exception->getLine().'; '.$exception->getTraceAsString());
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
			if (!empty($messageToDisplay)) {
				$this->router->setMessageToSession($messageToDisplay);
			}
			$this->beforeEnd();
			$this->redirect($actionmethod);
		}
		
		throw new \Exception('Unknown action in a controller '.$this->getName());
	}
	public function actionOffline() 
	{
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
	protected function isHTMLResp() 
	{
		return ($this->responseformat == '' || $this->responseformat == 'html');
	}
	/**
	 * This can be redefined in a child class. This is useful when reaction in many actions is same,
	 * for example, always redirect to same page 
	 */
	protected function getDefaultSuccessResponse() 
	{
		return null;
	}
	/**
	 * This can be redefined in a child class. This is useful when reaction in many actions is same,
	 * for example, always redirect to same page 
	 */
	protected function getDefaultDoException(\Exception $e)
	{
		return null;
	}
	/**
	 * In case of exception for Do or View this function will be called to get an action (redirect or just view)
	 * View means an error will be displayed as a html page in same call.
	 * Redirect means there will be additional redirect to some other page
	 * The action can be defined inside the exception object
	 * Or this method can be reloaded in a child class (def controller for example) to prepare own logic 
	 */
	protected function getActionOnErrorInHTML($exception, $cuurrentHTMLAction)
	{
		if ($exception instanceof ViewException) {
			return $exception->getActionOnErrorInHTML($cuurrentHTMLAction);
		}
		
		return $cuurrentHTMLAction;
	}
	protected function getRedirectUrlOnError($exception, $originalActionMethod) 
	{
		$actionmethod = '';

		if ($exception instanceof ViewException) {
			$actionmethod = $exception->getUrl();
			
			if($actionmethod == 'defaultview') {
				$actionmethod = $this->getDefaultURI();
			}
		}
		if (empty($actionmethod) && !empty($this->redirectUrlInCaseOfError)) {
			// use some default url
			$actionmethod = $this->redirectUrlInCaseOfError;
		}
		if (empty($actionmethod) && !empty($this->viewInCaseOfError)) {
			// it can be custom view. so redirect to it
			$actionmethod = $this->makeUrl(['view' => $this->viewInCaseOfError]);
		}
		if (empty($actionmethod)) {
			$actionmethod = $this->getErrorURI();
		}
		if (empty($actionmethod)) {
			$actionmethod = $this->getDefaultURI();
		}
		if (empty($actionmethod)) {
			$actionmethod = $originalActionMethod;
		}
		
		return $actionmethod;
	}
	protected function redirect($url,$script = false) 
	{
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
		return $this->getRouter()->makeUrl($opts);
	}
	/*
	* Get native model of this controller
	* It works fine for simple combinations of controller/view/model
	*/
	public function getDefModel() 
	{
		return $this->defmodel;
	}

	public function getDefPool() 
	{
		return $this->def_pool;
	}
	/**
	* DO somethign when action complete 
	*/
	protected function beforeEnd() 
	{
	}
	/**
	* Do somethign before any action started
	*/
	protected function beforeStart() 
	{
	}
	/*
	* Should check if a user is loged in and set user id in the application 
	* This can be called when each request must be authentificated. No need to cal when straditional web session is used
	*/
	protected function initAuthSession() 
	{
	}
	
	protected function filterRedirect($url,$script = false) 
	{
		return array($url,$script);
	}
	/**
	* Returns an url of an error view for this controller.
	* If urls must be built with some specific rules, then this function should be reimplemented in a child class.
	*/
	protected function getErrorURI() 
	{
        
        return $this->makeUrl(array('view'=>'error'));
	}
	/**
	* Get viewer associated with this controller
	*/
	protected function getViewer($name = '') 
	{
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
		return $this->application->getView($name,$this);
	}
	public function getViewerData() 
	{
		return $this->viewdata;
	}
	public function shiftViewerData() 
	{
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
	* Function helps to build complete urls. It can be used
	* to add some more arguments to url. For example, some titles/texts for SEO optimization
	*/
	protected function completeUrlOpts($opts) 
	{
        return $opts;
	}
	/**
	* Returns a default url of this controller. This url is used when no other 
	* redirect url is specified in an end of action.
	* Reimplement the function in a child class if some other specific link should be generated.
	*/
	protected function getDefaultURI() 
	{
        return $this->makeUrl();
    }
}
