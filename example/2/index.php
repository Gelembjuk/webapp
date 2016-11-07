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
	'relativebaseurl' => '/example/2/', // this option is useful only if default Router is used.
	'loggerstandard' => true,
	'applicationnamespace' => '\\',
    'defaultcontrollername' => 'MyController',
	'htmltemplatespath' => $thisdirectory.'template/',
	'htmltemplatesoptions' => array('extension' => 'htm') // our templates will have HTML extension
);
 
// ==================== APPLICATION LOGIC ==================================
// controller class
class MyController extends \Gelembjuk\WebApp\Controller {
	// viewer name.
	protected $defviewname = 'MyViewer';

	// ========= the only action of the controler
	protected function doSendmessage() {
		// do somethign to send message
		// best is to use a model 
		
		// if this was JSON request then we will return success status
		// if normal web request then we will redirect to home page
		return array('success',$this->makeUrl(array('message' => 'Successfully sent')));
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

			$this->viewdata['applicationtitle'] = 'Demo application';
			
			// include more data that should be displayed on any page of a site
			
		}
		return true;
	}
}

$application = \Gelembjuk\WebApp\Application::getInstance();

$application->init(new appConfig(),$options);

$application->action();
