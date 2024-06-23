<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to display uncatched error to a user
*/

class AuthRequiredException extends DoException{
	
	public function __construct($message = 'User Auth required. Please login', $url = '') 
    {
		parent::__construct($url, $message,'auth_required',401);	
	}
}
