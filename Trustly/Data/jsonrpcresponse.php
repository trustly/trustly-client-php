<?php

class Trustly_Data_JSONRPCResponse extends Trustly_Data_Response {

	public function __construct($response_body, $curl) {
		parent::__construct($response_body, $curl);

		$version = (string)$this->get('version');
		if($version !== '1.1') {
			throw new Trustly_JSONRPCVersionException("JSON RPC Version $version is not supported. " . json_encode($this->payload));
		}

		if($this->isError()) {
			$this->response_result = $this->response_result['error'];
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

	public function getErrorCode() {
		if($this->isError() && isset($this->response_result['data']['code'])) {
			return $this->response_result['data']['code'];
		}
		return NULL;
	}

	public function getErrorMessage() {
		if($this->isError() && isset($this->response_result['data']['message'])) {
			return $this->response_result['data']['message'];
		}
		return NULL;
	}
}

?>
