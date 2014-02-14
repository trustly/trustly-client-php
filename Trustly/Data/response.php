<?php

class Trustly_Data_Response extends Trustly_Data {
		/* Raw copy of the incoming response body */
	var $response_body = NULL;
		/* The response HTTP code */
	var $response_code = NULL;
		/* Shortcut to the part of the result being actually interesting. The 
		 * guts will contain all returned data. */
	var $response_result = NULL;

	public function __construct($response_body, $curl) {

		$this->response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
		$this->response_body = $response_body;

		$payload = json_decode($response_body, TRUE);
		if($payload === FALSE) {
			/* Only throw the connection error exception here if we did not 
			 * receive a valid JSON response, if we did recive one we will use 
			 * the error information in that response instead. */
			if($this->response_code !== 200) {
				throw new Trustly_ConnectionException('HTTP ' . $this->response_code);
			} else {
				throw new Trustly_DataException('Failed to decode response JSON, reason code ' . json_last_error());
			}
		}
		parent::__construct($payload);

			/* Attempt to detect the type of the response. A successful call 
				* will have a 'result' on toplevel in the payload, while an 
				* failure will have a 'error' on the tyoplevel */
		$this->response_result = &$this->payload['result'];
		if($this->response_result === NULL) {
			$this->response_result = &$this->payload['error'];
			if($this->response_result === NULL) {
				throw new Trustly_DataException('No result or error in response');
			}
		}
	}

	public function isError() {
		if($this->get('error') === NULL) {
			return FALSE;
		}
		return TRUE;
	}

	public function isSuccess() {
		if($this->get('result') === NULL) {
			return FALSE;
		}
		return TRUE;
	}

	public function getErrorMessage() {
		if($this->isError()) {
			if(isset($this->response_result['message'])) {
				return $this->response_result['message'];
			}
		}
		return NULL;
	}

	public function getErrorCode() {
		if($this->isError()) {
			if(isset($this->response_result['code'])) {
				return $this->response_result['code'];
			}
		}
		return NULL;
	}

	public function getResult($name=NULL) {
		if($name === NULL) {
				# An array is always copied
			return $this->response_result;
		}

		if(is_array($this->response_result) && isset($this->response_result[$name])) {
			return $this->response_result[$name];
		}

		return NULL;
	}

	public function getUUID() {
		if(isset($this->response_result['uuid'])) {
			return $this->response_result['uuid'];
		}
		return NULL;
	}

	public function getMethod() {
		if(isset($this->response_result['method'])) {
			return $this->response_result['method'];
		}
		return NULL;
	}

	public function getSignature() {
		if(isset($this->response_result['signature'])) {
			return $this->response_result['signature'];
		}
		return NULL;
	}
}

?>
