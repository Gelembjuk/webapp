<?php

namespace Gelembjuk\WebApp;

abstract class Router {
	use \Gelembjuk\Logger\ApplicationLogger;
	use \Gelembjuk\Locale\GetTextTrait;
	
	protected $input = array();
	protected $files = null;
	
	protected $actiontype = '';
	protected $actionmethod = '';
	protected $responseformat = '';
	protected $options;
	protected $controllername;
	protected $application;
	protected static $phpsessioninited;

	public function __construct($application,$options = array()) {
		self::$phpsessioninited = false;
		$this->application = $application;
		$this->options = $options;
		$this->controllername = '';
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
		}
		
		return true;
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
			if (isset($this->files[$file])) {
				return $this->files[$file];
			}
			
			return null;
		}
		
		$v = $this->input[$name];
		
		if (empty($v)) {
			$v = $default;
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
			$v = array();
		}
		
		if ($filter=='int' || $filter=='integer') {
			$v = strval(intval($v));
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
	protected function getRequestUrlPath(){
		return $_SERVER['REQUEST_URI'];
	}

	public function getActionInfo() {
		return array($this->actiontype,$this->actionmethod,$this->responseformat);
	}
	public function makeAbsoluteUrl($opts = array()) {
		$relurl = $this->makeUrl($opts);
		
		$baseurl = $this->getBasehost();
		
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
	
	abstract public function getController();
	abstract public function parseUrl($url = '');
	abstract protected function setUpActionInfo();
	abstract public function makeUrl($opts = array());
	
}