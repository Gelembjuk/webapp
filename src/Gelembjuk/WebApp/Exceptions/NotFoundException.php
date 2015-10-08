<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to display uncatched error to a user
*/

class NotFoundException extends ViewException{
	
	public function __construct($url,$message = 'Not found', $actionerrordisplay = '') {
		parent::__construct($url,$message,'not_found',404, $actionerrordisplay );	
	}
}