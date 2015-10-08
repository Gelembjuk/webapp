<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to process form submit errors. it includes a form field where error happened
*/

class FormException extends \Exception{
	protected $input;
	
	public function __construct($input, $message = '' , $number = 0) {
		parent::__construct($message,$number);
		$this->input = $input;
	}
	public function getInput() {
		return $this->input;
	}
}