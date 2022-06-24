<?php

namespace Gelembjuk\WebApp\View;

class JSON extends Display {
	public function init($options) {
        parent::init($options);
        
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
        if (is_array($this->options['cachedata'])) {
            if (!empty($this->options['cachedata'][1])) {
                header($this->options['cachedata'][1]);
            }

            header('Content-Type: application/json; charset=utf-8');
            echo $this->options['cachedata'][0];
            return true;
        }
		
		$this->requireSettings();
		
		$responsecode = 200;
	
		if ($this->data['errorcode'] > 0) {
			$responsecode = $this->data['errorcode'];
		} elseif ($this->data['statuscode'] > 0) {
			$responsecode = $this->data['statuscode'];
		} elseif ($this->data['errornumber'] > 0) {
			$responsecode = $this->data['errornumber'];
		}

		$displaydata = $this->prepareResponseStructure();
		
		$htmlheader = '';
		
		if ($responsecode != 200) {
			$http = new HTTP();
			$message = $http->getMessageForCode($responsecode);
            $htmlheader = "HTTP/1.0 ".$responsecode." ".$message;
			header($htmlheader);
		}
		header('Content-Type: application/json; charset=utf-8');
		$output = json_encode($displaydata);
		echo $output;
		
		$this->cacheData([$output, $htmlheader]);
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
