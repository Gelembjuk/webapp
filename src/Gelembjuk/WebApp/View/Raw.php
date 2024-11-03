<?php

namespace Gelembjuk\WebApp\View;

class Raw extends Display {

	public function setData($data) {
		$this->data = $data;
	
		return true;
	}
	public function display() 
	{
        foreach ($this->data as $value) {
            echo "$value\n";
        }

		return true;
	}
}
