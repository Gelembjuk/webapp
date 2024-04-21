<?php

namespace Gelembjuk\WebApp\Exceptions;

/*
* This class helps to display uncatched error to a user
*/

class AuthRequiredException extends \Exception{
	
	public function __construct($message = 'User Auth required. Please login') 
    {
		parent::__construct($message,'auth_required',401);	
	}
}
