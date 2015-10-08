<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to display uncatched error to a user
*/

class ViewException extends \Exception{
	protected $url;
	protected $textcode;
	protected $actionerrordisplay;
	
	public function __construct($url, $message = '' , $textcode = '', $number = 0, $actionerrordisplay = '') {
		parent::__construct($message,$number);
		$this->textcode = $textcode;
		$this->url = $url;
		$this->actionerrordisplay = $actionerrordisplay;
	}
	public function getUrl() {
		return $this->url;
	}
	public function getTextCode() {
		return $this->textcode;
	}
	public function getActionOnErrorInHTML($current = '') {
		if ($this->actionerrordisplay == '') {
			return $current;
		}
		
		return $this->actionerrordisplay;
	}
}