<?php

class Trustly_Data_JSONRPCNotificationRequest extends Trustly_Data {
	var $notification_body = NULL;

	public function __construct($notification_body) {

		$this->notification_body = $notification_body;
		$payload = json_decode($notification_body, TRUE);

		parent::__construct($payload);

		if($this->getVersion() != '1.1') {
			throw new Trustly_JSONRPCVersionException('JSON RPC Version '. $this->getVersion() .'is not supported');
		}
	}

	public function getParams($name=NULL) {
		if(!isset($this->payload['params'])) {
			return NULL;
		}
		$params = $this->payload['params'];
		if(isset($name)) {
			if(isset($params[$name])) {
				return $params[$name];
			}
		} else {
			return $params;
		}
		return NULL;
	}

	public function getData($name=NULL) {
		if(!isset($this->payload['params']['data'])) {
			return NULL;
		}
		$data = $this->payload['params']['data'];
		if(isset($name)) {
			if(isset($data[$name])) {
				return $data[$name];
			}
		} else {
			return $data;
		}
		return NULL;
	}

	public function getUUID() {
		return $this->getParams('uuid');
	}

	public function getMethod() {
		return $this->get('method');
	}

	public function getSignature() {
		return $this->getParams('signature');
	}

	public function getVersion() {
		return $this->get('version');
	}
}

?>
