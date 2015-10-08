<?php

namespace Gelembjuk\WebApp\View;

class XML extends JSON {
	public function display() {
		$this->requireSettings();
		
		$displaydata = $this->prepareResponseStructure();
		
		header('Content-type: application/xml');
		
		$xml = \LSS\Array2XML::createXML('document', $displaydata);
		echo $xml->saveXML();
		
		return true;
	}
}
