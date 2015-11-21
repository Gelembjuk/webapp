<?php

namespace Gelembjuk\WebApp\View;

class JSON extends Display {
	public function init($options) {
		if (!isset($options['status']) || $options['status'] == '') {
			$options['status'] = 'ok';
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
		
		$responsecode = 200;
	
		if ($this->data['errorcode'] != '') {
			$responsecode = $this->data['errorcode'];
		} elseif ($this->data['statuscode'] != '') {
			$responsecode = $this->data['statuscode'];
		}

		$displaydata = $this->prepareResponseStructure();
		
		if ($responsecode != 200) {
			$http = new HTTP();
			$message = $http->getMessageForCode($responsecode);
			header("HTTP/1.0 ".$responsecode." ".$message);
		}
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($displaydata);
		return true;
	}
	
	protected function prepareResponseStructure() {
		if ($this->options['status'] != 'ok') {
			$status = 'error';
			
			if ($this->data['errorcode'] != '') {
				$status = 'error_'.$this->data['errorcode'];
			}
			
			$message = $this->data['errormessage'];

			if ($message == '') {
				$message = 'Unlnown error';
			}
			
			$displaydata = array('status' => $status,'message'=>$this->data['errormessage']);

			if ($this->data['errornumber'] > 0) {
				$displaydata['code'] = $this->data['errornumber'];
			}

			unset($this->data['errorcode']);
			unset($this->data['errormessage']);
			unset($this->data['errornumber']);

			if (count($this->data) > 0) {
				$displaydata['context'] = $this->data;
			}
		} else {
			$successmessage = 'Success';
			
			if ($this->data['successmessage'] != '') {
				$successmessage = $this->data['successmessage'];
				unset($this->data['successmessage']);
			}
			$displaydata = array('status' => 'ok','message' => $successmessage,'response'=>$this->data);
		}
		return $displaydata;
	}
}