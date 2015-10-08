<?php

namespace Gelembjuk\WebApp\View;

class HTTP extends Display {
	public function init($options) {
		
		if (!isset($options['status']) || $options['status'] == '') {
			$options['status'] = 'ok';
		}
		if (!isset($options['statuscode'])) {
			$options['statuscode'] = 200;
		} else {
			$options['statuscode'] = (int) $options['statuscode'];
			
			if ($options['statuscode'] < 1) {
				$options['statuscode'] = ($options['status'] == 'ok')?200:400;
			}
		}
		$this->options = $options;
		return true;
	}
	public function setData($data) {
		$this->data = $data;
	
		return true;
	}
	public function display() {
		$this->requireSettings();
		
		$message = 'OK';
		
		$biggermessage = 'Success';
		
		if ($this->options['status'] == 'error') {
			$biggermessage = $this->data['errormessage'];
		} elseif ($this->data['successmessage'] != '') {
			$biggermessage = $this->data['successmessage'];
		}
		
		$biggermessage = preg_replace('![^a-z0-9 .,_;:-]!i','',$biggermessage);
		
		if ($this->options['statuscode'] != 200) {
			
			if (strlen($biggermessage) < 25) {
				$message = $biggermessage;
			} elseif(preg_match('!^(.{3}[^.]*?)\\.!',$biggermessage,$m)) {
				$message = $m[1];
			} else {
				$message = $this->getMessageForCode($this->options['statuscode']);
			}
		}
		
		header("HTTP/1.0 ".$this->options['statuscode']." ".$message);
		header("X-Message: ".$biggermessage);
			
		return true;
	}
	public function getMessageForCode($code) {
		$codes = array(
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No content',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See other',
			400 => 'Bad request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not found',
			405 => 'Method not allowed',
			406 => 'Not acceptable'
			);
		if (isset($codes[$code])) {
			return $codes[$code];
		}
		return 'Unknown Error';
	}
}