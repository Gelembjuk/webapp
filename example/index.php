<?php 

/**
 * Example. Usage of Gelembjuk/WebApp . Example uses Smarty templating engine
 * 
 * This example is part of gelembjuk/webapp package by Roman Gelembjuk (@gelembjuk)
 */

// ==================== CONFIGURATION ==================================
// path to your composer autoloader
require ('vendor/autoload.php');

$thisdirectory = dirname(__FILE__) . '/'; // get parent directory of this script

// application settings
class appConfig {
	public $offline = false;
	public $loggingfilter = 'all'; // log everything
}

// application options
$options = array(
	'webroot' => $thisdirectory,
	'tmproot' => $thisdirectory.'tmp/',
	'applicationnamespace' => '\\',
	'htmltemplatespath' => $thisdirectory.'template/',
	'htmltemplatesoptions' => array('extension' => 'htm') // our templates will have HTML extension
);

// application class
class MyApplication extends \Gelembjuk\WebApp\Application{
	public function init($config,$options = array()) {
		parent::init($config,$options);

		$this->setLogger(new \Gelembjuk\Logger\FileLogger(
					array(
					'logfile'=>$this->getOption('tmproot').'log.txt',
					'groupfilter' => $this->getConfig('loggingfilter')
					)
				)
			);
	}
	protected function getDefaultRouter() {
		return 'MyRouter';
	}
	protected function getRouterNameFromRequest() {
		return 'MyRouter';
	}
	protected function getDefaultControllerName() {
		return 'MyController';
	}
} 
// controller class
class MyController extends \Gelembjuk\WebApp\Controller {
	// viewer name.
	protected $defviewname = 'MyViewer';
	
	protected function getRouter() {
		return $this->application->getRouter('MyRouter');
	}
	protected function getDefaultURI($message = null) {
		return $this->makeUrl(array('message'=>$message));
	}
	protected function getErrorURI($message) {
		return $this->makeUrl(array('view'=>'error'));
	}
	public function makeUrl($opts = array()) {
		unset($opts['controller']); // we have only one controller. so don't need this in url
		return $this->router->makeUrl($opts);
	}

	// ========= the only action of the controler
	protected function doSendmessage() {
		// do somethign to send message
		// best is to use a model 
		
		// if this was JSON request then we will return success status
		// if normal web request then we will redirect to home page
		return array('success',$this->makeUrl(array('message' => 'Successfully sent')));
	}
	
	
}
// router class
class MyRouter extends \Gelembjuk\WebApp\Router {
	public function init() {	
		$this->controllername = 'MyController';
	}
	public function getController() {
		// we have only one controller in this app
		return 'MyController';
	}
	
	protected function setUpActionInfo() {
		// we determine required action based on input arguments
		if ($this->getInput('view') != '') {
			// display something
			$this->actiontype = 'view';
			$this->actionmethod = $this->getInput('view','alpha');
			
		} elseif ($this->getInput('do') != '') {
			// do something
			$this->actiontype = 'do';
			$this->actionmethod = $this->getInput('do','alpha');
			
		} elseif ($this->getInput('redirect','plaintext') != '') {
			// redirect to other page
			$this->actiontype = 'redirect';
			$this->actionmethod = $this->getInput('redirect','plaintext');
		} else {
			// display default page
			$this->actiontype = 'view';
			$this->actionmethod = '';
		}
		if ($this->getInput('responseformat','alpha') != '') {
			// user requested not default response format
			$this->responseformat = $this->getInput('responseformat','alpha');
		}
		return true;
	}

	public function makeUrl($opts = array()) {
		$url = '/example/';
		
		if (count($opts) > 0) {
			$url .= '?';
			
			foreach ($opts as $k=>$v) {
				$url .= $k . '=' . urlencode($v) .'&';
			}
		}
		
		return $url;
	}
	public function parseUrl($url = '') {
		// do nothing. we don't use 'nice' urls there
	}
}
// view class
class MyViewer extends \Gelembjuk\WebApp\View{
	protected function view() {
		// just display default page
		$this->htmltemplate = 'default';
		
		$this->viewdata['welcomemessage'] = 'Hello there!';
		
		return true;
	}
	protected function viewForm() {
		// display form page
		$this->htmltemplate = 'form';
		return true;
	}
	protected function viewData() {
		// display form page
		$this->htmltemplate = 'data'; // template name
		
		// usually it can be loaded from a Model
		$this->viewdata['data'] = array(
			array('name'=>'User1','email'=>'email1@gmail.com'),
			array('name'=>'User2','email'=>'email2@gmail.com'),
			array('name'=>'User3','email'=>'email3@gmail.com'),
		);
		
		return true;
	}
	protected function beforeDisplay() {
		// set some extra information for any page
		
		if ($this->responseformat == 'html') {
			// only if this is html format to display
			if ($this->viewdata['message'] == '') {
				$this->viewdata['message'] = $this->router->getInput('message');
			}
			
			$this->viewdata['applicationtitle'] = 'Demo application';
			
		}
		return true;
	}
}

$application = MyApplication::getInstance();

$application->init(new appConfig(),$options);

$application->action();
