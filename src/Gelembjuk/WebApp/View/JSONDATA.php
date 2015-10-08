<?php

namespace Gelembjuk\WebApp\View;

class JSONDATA extends JSON {
		
	protected function prepareResponseStructure() {
		return $this->data;
	}
}