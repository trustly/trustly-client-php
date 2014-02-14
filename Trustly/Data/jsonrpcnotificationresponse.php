<?php

class Trustly_Data_JSONRPCNotificationResponse extends Trustly_Data {

	public function __construct($request, $success=NULL) {

		parent::__construct();

		$uuid = $request->getUUID();
		$method = $request->getMethod();

		if(isset($uuid)) {
			$this->setResult('uuid', $uuid);
		}
		if(isset($method)) {
			$this->setResult('method', $method);
		}

		if(isset($success)) {
			$this->setSuccess($success);
		}

		$this->set('version', '1.1');
	}

	public function setSuccess($success=NULL) {
		$status = 'OK';

		if(isset($success) && $success !== TRUE) {
			$status = 'FAILURE';
		}
		$this->setData('status', $status);
		return $success;
	}

	public function setSignature($signature) {
		$this->setResult('signature', $signature);
	}

	public function setResult($name, $value) {
		if(!isset($this->payload['result'])) {
			$this->payload['result'] = array();
		}
		$this->payload['result'][$name] = $value;
		return $value;
	}

	public function getResult($name=NULL) {
		$result = NULL;
		if(isset($this->payload['result'])) {
			$result = $this->payload['result'];
		} else {
			return NULL;
		}

		if(isset($name)) {
			if(isset($result[$name])) {
				return $result[$name];
			}
		} else {
			return $result;
		}
	}

	public function getData($name=NULL) {
		$data = NULL;
		if(isset($this->payload['result']['data'])) {
			$data = $this->payload['result']['data'];
		}else {
			return NULL;
		}

		if(isset($name)) {
			if(isset($data[$name])) {
				return $data[$name];
			}
		} else {
			return $data;
		}
	}

	public function setData($name, $value) {
		if(!isset($this->payload['result'])) {
			$this->payload['result'] = array();
		}
		if(!isset($this->payload['result']['data'])) {
			$this->payload['result']['data'] = array($name => $value);
		} else {
			$this->payload['result']['data'][$name] = $value;
		}
		return $value;
	}

	public function getMethod() {
		return $this->getResult('method');
	}

	public function getUUID() {
		return $this->getResult('uuid');
	}
}


?>
