<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to display uncatched error to a user
*/

class NotAuthorizedException extends ViewException{
	
	public function __construct($url,$message = 'Not Authorized', $actionerrordisplay = '') {
		parent::__construct($url,$message,'not_authorized',403, $actionerrordisplay );	
	}
}
