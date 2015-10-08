<?php

namespace Gelembjuk\WebApp\View;

class HTML extends Display {

	public function init($options) {
		
		if ($options['outtemplate_force'] != '') {
			$options['outtemplate'] = $options['outtemplate_force'];
		}
		
		if (!isset($options['templatepath'])) {
			throw new \Exception('No temlates path provided');
		}
		
		if (!isset($options['template'])) {
			throw new \Exception('No HTML temlate provided');
		}
		
		if (!isset($options['templatingclass'])) {
			throw new \Exception('No templating class provided');
		}
		
		if (!isset($options['outtemplate'])) {
			$options['outtemplate'] = 'default';
		}
		
		if (!empty($options['noouttemplate']) && $options['noouttemplate']) {
			$options['outtemplate'] = ''; // means don't use out template
		}
		
		// check if templates exists
		if (!is_dir($options['templatepath'])) {
			throw new \Exception('Temlates directory is not found');
		}
		
		if (substr($options['templatepath'],-1) != '/') {
			$options['templatepath'] .= '/';
		}
		
		if (isset($options['templatescomplpath']) &&
			substr($options['templatescomplpath'],-1) != '/') {
			$options['templatescomplpath'] .= '/';
		}
		
		if (!isset($options['templatesoptions'])) {
			$options['templatesoptions'] = array();
		}
		
		if (!isset($options['templatesoptions']['compiledir'])) {
			$options['templatesoptions']['compiledir'] = $options['tmpdir'].'templates_c';
		}
		
		if (!isset($options['templatesoptions']['cachedir'])) {
			$options['templatesoptions']['cachedir'] = $options['tmpdir'].'cache';
		}
		
		if (!isset($options['templatesoptions']['usecache'])) {
			$options['templatesoptions']['usecache'] = false;
		}
		
		$this->options = $options;
		return true;
	}
	public function setData($data) {
		$this->data = $data;
		
		if (!isset($this->data['html_title'])) {
			$this->data['html_title'] = $this->options['applicationtitle'];
		} elseif (isset($this->data['html_addtitleprefix'])) {
			$this->data['html_title'] = $this->options['applicationtitle'].': '.$this->data['html_title'];
		}
		
		if (!isset($this->data['html_metadescription'])) {
			$this->data['html_metadescription'] = $this->options['applicationdescription'];
		}
		
		if (!isset($this->data['html_keywords'])) {
			$this->data['html_keywords'] = $this->options['applicationkeywords'];
		} elseif (isset($this->viewdata['html_addstandardkeywords'])) {
			$this->data['html_keywords'] = $this->options['applicationkeywords'].', '.$this->data['html_keywords'];
		}
		
		$this->data['view'] = $this->options['view'];
		$this->data['controller'] = $this->options['controler'];
		
		return true;
	}
	public function display() {
		$html = $this->getHTML();
		if (!$this->options['headerssent']) {
			
			if ($this->options['statuscode'] > 0 && $this->options['statuscode'] != 200) {
				$http = new HTTP();
				$message = $http->getMessageForCode($this->options['statuscode']);
				
				header("HTTP/1.0 ".$this->options['statuscode']." ".$message);
			}
			
			header('Content-Type: text/html; charset=utf-8');
		}
		echo $html;
		return true;
	}
	protected function getHTML() {
		$this->requireSettings();
		
		// create templating class
		$class = $this->options['templatingclass'];
		
		if (!class_exists($class)) {
			throw new \Exception(sprintf('Temlating class %s not found',$class));
		}

		$options = $this->options['templatesoptions'];
		$options['templatepath'] = $this->options['templatepath'];
		
		$templating = new $class();
		
		$templating->init($options);

		$templating->setApplication($this->application);
		
		//find messages and understand paths for templates
		// first try to find in subfolder named with view name
		
		$templateset = false;
		$outtemplate = $this->options['outtemplate'];
		if ($this->options['view'] !='') {
			if ($templating->checkTemplateExists($this->options['view'].'/'.$this->options['template'])) {
				$templating->setTemplate($this->options['view'].'/'.$this->options['template']);
				$templateset = true;
				
				if ($outtemplate !='' ) {
					$outtemplate = '_outer/'.$outtemplate;
				}
			}
		}
		
		if (!$templateset) {
			if ($templating->checkTemplateExists($this->options['template'])) {
				$templating->setTemplate($this->options['template']);
				$templateset = true;
				
				if ($outtemplate !='' && 
					!$templating->checkTemplateExists($outtemplate) &&
					$templating->checkTemplateExists('_outer/'.$outtemplate)) {
				
					$outtemplate = '_outer/'.$outtemplate;
				}
			}
		}
		
		if (!$templateset && $this->options['template'] == 'error' &&
			$this->application) {
				// this case means there was error reported and catched
				// error page template is not found
				// don't throw exception as it can beck to this place again
				// report error directly to errohandler and exit
				$errorhandler = $this->application->getErrorHandler();
		
				if (is_object($errorhandler)) {
					$errorhandler->processError(new \Exception($this->data['errormessage']));
				} else {
					echo 'Error template not found';
				}
				exit;
		}
		
		if (!$templateset) {
			throw new \Exception(sprintf('Template %s not found',$this->options['template']));
		}
		
		$templating->setVars($this->data);
		
		$html = $templating->fetchTemplate();
		
		if ($outtemplate != '') {
			$templating->setVar('INNERCONTENTS',$html);
			$templating->setTemplate($outtemplate);
			$html = $templating->fetchTemplate();
		}
		
		return $html;
	}
}
