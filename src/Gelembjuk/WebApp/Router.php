<?php

namespace Gelembjuk\WebApp;

class Router {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $input = array();
	protected $files = null;
	
	protected $actiontype = '';
	protected $actionmethod = '';
	protected $responseformat = '';
	protected $httpmethod = 'GET';
	protected $options;
	protected $application;
	protected static $phpsessioninited;

	public function __construct($application,$options = array()) {
		self::$phpsessioninited = false;
		$this->application = $application;
		$this->options = $options;
		$this->httpmethod = $_SERVER['REQUEST_METHOD'];
		$this->clearInput();
		$this->parseInput();
		$this->setUpActionInfo();
	}
	/*
	* Init router. Can be used in shild classes to do some action right after object created
	*/
	public function init() {
	}
	public function detectLocale() {
		if ($this->options['locale'] != '') {
			$this->setLocale($this->options['locale']);
		} elseif ($this->getLocale() == '') {
			// get it from browser
			$this->setLocale($this->preferedUserLanguage(true));
		}		
		return $this->locale;
	}
	public function clearInput() {
		$this->input = array();
	}
	public function parseInput() {
		$this->parseRequest();
		$this->parseUrl('');
	}
	public function parseRequest() {
		$this->input = array_merge($this->input,$_REQUEST);
		
		foreach ($_COOKIE as $k => $v) {
            if (!isset($this->input[$k])) {
                $this->input[$k] = $v;
            }
		}
		return true;
	}
	public function parseGet() {
		$this->input = array_merge($this->input,$_GET);
		return true;
	}
	public function parsePost() {
		$this->input = array_merge($this->input,$_POST);
		return true;
	}
	public function parseFiles() {
		if ($this->files !== null) {
			return true;
		}
		$this->files = $_FILES;
		
		if (!is_array($this->files)) {
			$this->files = array();
		} else {
            foreach ($this->files as $file => $data) {
                if (is_array($data['name'])) {
                    $files = [];
                    
                    for ($i = 0; $i < count($data['name']); $i++) {
                        if (empty($data['name'][$i])) {
                            continue;
                        }
                        $files[] = array(
                            'name' => $data['name'][$i],
                            'type' => $data['type'][$i],
                            'tmp_name' => $data['tmp_name'][$i],
                            'error' => $data['error'][$i],
                            'size' => $data['size'][$i],
                            );
                    }
                    
                    $this->files[$file] = $files;
                }
            }
		}
		
		return true;
	}
	// parse request body to input array and then read top level keys as inputs
	public function parseBody() {
		$contenttype = strtolower($_SERVER["CONTENT_TYPE"]);
		
		if (empty($contenttype)) {
			$contenttype = strtolower($_SERVER["HTTP_CONTENT_TYPE"]);
		}

		if ($contenttype == 'application/json') {
			$jsondoc = $this->getRequestBody();

			if ($jsondoc != '') {
				$data = @json_decode($jsondoc,true);

				if (json_last_error() == JSON_ERROR_NONE) {
					foreach ($data as $key => $val) {
						$this->input[$key] = $val;
					}
				}
			}
		}
	}
	public function getRequestBody() {
		static $requestbody;

		if (empty($requestbody)) {
			$requestbody = file_get_contents("php://input");
		}
		return $requestbody;
	}
	public function parseCommandLine() {
		global $argv, $argc;

		$query = $argv[1];
		
		foreach (explode('&',$query) as $pair){
			list($key,$val) = explode('=',$pair);
			$this->input[$key] = $val;
		}

	}
	public function setInput($name,$value) {
		$this->input[$name] = $value;
	}
	public function unSetInput($name) {
		unset($this->input[$name]);
	}
	protected function filterVar($value,$filter) {
		
	}
	protected function filterXss($value) {
		static $xssconvertor = null;
		
		if ($xssconvertor === null) {
			$xssconvertor = new \Gelembjuk\Utils\XSS();
		}

		return $xssconvertor->xss_clean($value);
	}
	public function getInput($name,$filter = 'string', $default = '', $maxlength = 0) {
		
		if ($filter == 'file') {
			if (isset($this->files[$name]) && $this->files[$name]['name'] != '') {
				return $this->files[$name];
			}
			
			return null;
		}
		
		if ($filter == 'filelist') {
            if (isset($this->files[$name]) && is_array($this->files[$name]) && isset($this->files[$name][0]['name'])) {
                return $this->files[$name];
            }
            
            return null;
        }
		
		$v = $this->input[$name];
		
		if (empty($v)) {
			$v = $default;

			if (isset($this->files[$name])) {
				// load this file to memory. but check size before
				$tmofilepath = $this->files[$name]['tmp_name'];
				
				// for this feature support only files less 16M
				if (file_exists($tmofilepath) && @filesize($tmofilepath) && @filesize($tmofilepath) < 16*1024*1024) {
					$v = @file_get_contents($tmofilepath);
				}
			}
		}
		
		if ($filter != 'array' && $v != '' && get_magic_quotes_gpc() == 1) {
			if (is_array($v)) {
				$v = $v[0];
			}
			$v = stripslashes($v);
		}
		
		if (empty($v)) {
			$v = $default;
		}
				
		if ($filter == 'array' && !is_array($v)) {
			// maybe it is json. then try to recode from json
			if (substr($v,0,1) == '{' || substr($v,0,1) == '[') {
				$vv = @json_decode($v,true);

				if (json_last_error() == JSON_ERROR_NONE && is_array($vv)) {
					$v = $vv;
				}
			}
			if (!is_array($v)) {
				$v = array();
			}
		}
		
		if ($filter=='int' || $filter=='integer') {
			$v = strval(intval($v));
		}
		
		if ($filter=='float') {
            $v = strval(floatval($v));
        }
		
		if ($filter == 'nohtml' || $filter == 'plainline') {
			$v = preg_replace('!<.*?>!','',$v);
		}
		
		if ($filter == 'noxss' || $filter == 'nohtml') {
			$v = $this->filterXss($v);
		}
		
		if ($filter == 'plainline') {
			$v = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', $v);
		}
		
		if ($filter == 'alpha') {
			$v = preg_replace('![^a-z0-9]!i','',$v);
		}
		
		if ($filter == 'bool' || $filter == 'boolean') {
			$v = ($v === '1' || $v === 'y' || $v === 'yes' || $v === 1 || is_bool($v) && $v);
		}
		
		if ($filter == 'alphaext') {
			$v = preg_replace('![^a-z0-9_.-@]!i','',$v);
		}
		
		if ($filter == 'date') {
			if (strtotime($v) < 1000) {
				$v='';
			}
		}
		return $v;
	}
	public function getInputs($list) {
		if (!is_array($list)) {
			$list = explode(',',$list);
		}
		
		$inputs = array();
		
		foreach ($list as $input) {
			list($name,$filter,$default,$maxlen) = explode(':',$input);
			
			if (trim($name) == '') {
				continue;
			}
			
			if (empty($filter)) {
				$filter = 'string';
			}
			if (empty($default)) {
				$default = '';
			}
			if (empty($maxlen)) {
				$maxlen = 0;
			}
			
			$inputs[] = $this->getInput($name,$filter,$default,$maxlen);
		}
		
		return $inputs;
	}
	/* returns all inputs as one hash
	 * this function would not filter data. so use it carefully 
	 */
	public function getInputAsStructure($includefiles = false) {
		$data = array();

		$data = $this->input;

		if ($includefiles && count($this->files) > 0) {
			foreach ($this->files as $key => $file) {
				// get contents of a file
				$file['contents'] = @file_get_contents($file['tmp_name']);

				if (strlen($file['contents']) > 0) {
					unset($file['tmp_name']);
					$data[$key] = $file;
				}
			}
		}

		return $data;
	}
	protected function getHTTPHeader($header) {
		if (isset($_SERVER['HTTP_'.strtoupper($header)])) {
			return $_SERVER['HTTP_'.strtoupper($header)];
		}
		return '';
	}
	protected function getRequestUrlPath(){
		return $_SERVER['REQUEST_URI'];
	}

	public function getRequestHostName()
	{
        $hostinfo = new \Gelembjuk\WebApp\Server\Host();
        return $hostinfo->getRequestHost();
    }
	
	public function getActionInfo() {
		return array($this->actiontype,$this->actionmethod,$this->responseformat);
	}
	public function makeAbsoluteUrl($opts = array()) {
		$relurl = $this->application->makeUrl($opts);
		
		$baseurl = $this->appliation->getBasehost();
		
		return $baseurl.$relurl;
	}
	public function getName() {
		$function = new \ReflectionClass(static::class);
		return $function->getShortName();
	}
	public function setErrorPage($message,$code = '',$number = 0) {
		$this->setInput('view','error');
		$this->setInput('errormessage',$message);
		$this->setInput('errorcode',$code);
		$this->setInput('errornumber',$number);
		$this->setUpActionInfo();
	}
	public function dumpInput() {
		print_r($this->input);
	}
	protected function preferedUserLanguage($onlylanguage = false) { 
		$http_accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';  
			preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" . 
				"(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i", 
				$http_accept_language, $hits, PREG_SET_ORDER); 

		// default language (in case of no hits) is the first in the array 
		$bestlang = $available_languages[0]; 
		$bestqval = 0; 

		foreach ($hits as $arr) { 
			// read data from the array of this hit 
			$langprefix = strtolower ($arr[1]);
			
			if (!empty($arr[3])) { 
				$langrange = strtolower ($arr[3]); 
				$language = $langprefix . "-" . $langrange; 
			} else {
				$language = $langprefix;
			}
			$qvalue = 1.0; 
			if (!empty($arr[5])) {
				$qvalue = floatval($arr[5]);
			}
	
			// find q-maximal language  
			if ($qvalue > $bestqval) { 
				$bestlang = $language; 
				$bestqval = $qvalue; 
			} else if (($qvalue*0.9) > $bestqval) { 
				$bestlang = $langprefix; 
				$bestqval = $qvalue*0.9; 
			} 
		} 

		if (strlen($bestlang) > 2 && $onlylanguage) {
			if (preg_match('!^([a-z]{2,3})-!',$bestlang,$m)) {
				$bestlang = $m[1];
			}
		}

		return $bestlang; 
	}
	public function initSession() {
		if (!self::$phpsessioninited) {
			self::$phpsessioninited = true;
			
			session_start();
		}
	}
	public function getReferrer() {
		return $_SERVER['HTTP_REFERER'];
	}
	/*
	* Return message if is was set before
	*/
	public function getMessageFromSession()
	{
        $this->initSession();
        
        if (isset($_SESSION['APPLICATIONMESSAGE'])) {
            $message = $_SESSION['APPLICATIONMESSAGE'];
            
            unset($_SESSION['APPLICATIONMESSAGE']);
            
            return $message;
        }
        return "";
	}
	/*
	* Set message to a session to read it on a next view 
	*/
	public function setMessageToSession($message,$messagestyle = '')
	{
        $this->initSession();
        
        if ($messagestyle != '') {
            $message = $messagestyle . ':' . $message;
        }
        $_SESSION['APPLICATIONMESSAGE'] = $message;
	}
	
	public function getController()
	{
        // this is default case when there is only one controller. 
        // In most of application, it is expected, this function will be reimplemented in child class
        return $this->application->getOption('defaultcontrollername');
	}
	public function parseUrl($url = '')
	{
        // this does nothing by default. Use it to do action
        // similar to traditional mod_rewrite
        return true;
	}
	/**
	* It is expected this function will be overwritten in child classes
	* Here it implements simple way with some traditional argument names
	*/
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
    /**
    * It is recommended to overwrite this function if some custom urls are used in application
    */
	public function makeUrl($opts = array()) {
        $url = $this->application->getOption('relativebaseurl');
        
        if ($url == '') {
            $url = '/';
        }
        
        if (count($opts) > 0) {
            $url .= '?';
            
            foreach ($opts as $k=>$v) {
                $url .= $k . '=' . urlencode($v) .'&';
            }
        }
        
        return $url;
    }
	
}