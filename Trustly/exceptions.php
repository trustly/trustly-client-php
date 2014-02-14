<?php

class Trustly_ConnectionException extends Exception { }

class Trustly_JSONRPCVersionException extends Exception { }

class Trustly_SignatureException extends Exception {

	public function __construct($message, $data) {
		parent::__construct($message);
		$this->signature_data = $data;
	}

	public function getBadData() {
		return $this->signature_data;
	}
}

class Trustly_DataException extends Exception { }

class Trustly_AuthentificationException extends Exception { }

?>
