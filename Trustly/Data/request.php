<?php

class Trustly_Data_Request extends Trustly_Data {

	var $method = NULL;

	public function __construct($method=NULL, $payload=NULL) {

		parent::__construct($payload);

		$this->method = $method;
	}

	public function getUUID() {
		if(isset($this->payload['uuid'])) {
			return $this->payload['uuid'];
		}
		return NULL;
	}

	public function setUUID($uuid) {
		$this->set('uuid', $uuid);
	}

	public function getMethod() {
		return $this->method;
	}

	public function setMethod($method) {
		$this->method = $method;
	}
}

?>
