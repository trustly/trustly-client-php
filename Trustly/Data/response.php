<?php
/**
 * Trustly_Data_Response class.
 *
 * @license https://opensource.org/licenses/MIT
 * @copyright Copyright (c) 2014 Trustly Group AB
 */

/* The MIT License (MIT)
 *
 * Copyright (c) 2014 Trustly Group AB
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


/**
 * Base class for incoming responses from the API
 */
class Trustly_Data_Response extends Trustly_Data {

	/**
	 * Raw copy of the incoming response body
	 * @var integer
	 */
	var $response_body = null;

	/**
	 * The response HTTP code
	 * @var integer
	 */
	var $response_code = null;

	/**
	 * Shortcut to the part of the result being actually interesting. The guts will contain all returned data.
	 * @var mixed
	 */
	var $response_result = null;


	/**
	 * Constructor.
	 *
	 * @throws Trustly_ConnectionException When the response was invalid and
	 *		the HTTP response code indicates an error.
	 *
	 * @throws Trustly_DataException When the response is not valid.
	 *
	 * @param string $response_body RAW response body from the API call
	 *
	 * @param integer $response_code HTTP response code from the API call
	 */
	public function __construct($response_body, $response_code=null) {
		parent::__construct();

		$this->response_code = $response_code;
		$this->response_body = $response_body;

		$payload = json_decode($response_body, true);
		if($payload === false) {
			/* Only throw the connection error exception here if we did not
			 * receive a valid JSON response, if we did recive one we will use
			 * the error information in that response instead. */
			if(isset($this->response_code) and $this->response_code !== 200) {
				throw new Trustly_ConnectionException('HTTP ' . $this->response_code);
			} else {
				throw new Trustly_DataException('Failed to decode response JSON, reason code ' . json_last_error());
			}
		}

		if(isset($payload)) {
			$this->payload = $payload;
		}

		/* Attempt to detect the type of the response. A successful call will
		 * have a 'result' on toplevel in the payload, while an failure will
		 * have a 'error' on the tyoplevel
		 */
		$this->response_result = &$this->payload['result'];
		if($this->response_result === null) {
			$this->response_result = &$this->payload['error'];
			if($this->response_result === null) {
				throw new Trustly_DataException('No result or error in response');
			}
		}
	}


	/**
	 * Return basic status revealing wether or not he the API call resulted in
	 * an error. Do note that this does not nessecarily imply that the call was
	 * a success, just that the response did not include a proper error
	 * response.
	 *
	 * @return boolean revealing if the response had an error element in it.
	 */
	public function isError() {
		if($this->get('error') === null) {
			return false;
		}
		return true;
	}


	/**
	 * Return basic status revealing wether or not the API response has a
	 * result indicating success.
	 *
	 * @return boolean revealing if the response has a result element in it
	 */
	public function isSuccess() {
		if($this->get('result') === null) {
			return false;
		}
		return true;
	}


	/**
	 * Get error message (if any) from the API response
	 *
	 * @return string The error message
	 */
	public function getErrorMessage() {
		if($this->isError()) {
			if(isset($this->response_result['message'])) {
				return $this->response_result['message'];
			}
		}
		return null;
	}


	/**
	 * Get error code (if any) from the API response
	 *
	 * @return integer The error code (numerical)
	 */
	public function getErrorCode() {
		if($this->isError()) {
			if(isset($this->response_result['code'])) {
				return $this->response_result['code'];
			}
		}
		return null;
	}


	/**
	 * Get data from the result section of the response
	 *
	 * @param string $name Name of the result parameter to fetch. NULL value
	 *		will return entire result section.
	 *
	 * @return mixed The value for parameter $name or the entire result block
	 *		if no name was given
	 */
	public function getResult($name=null) {
		if($name === null) {
				# An array is always copied
			return $this->response_result;
		}

		if(is_array($this->response_result) && isset($this->response_result[$name])) {
			return $this->response_result[$name];
		}

		return null;
	}


	/**
	 * Convenience function for getting the uuid in the response
	 *
	 * @param string uuid
	 */
	public function getUUID() {
		if(isset($this->response_result['uuid'])) {
			return $this->response_result['uuid'];
		}
		return null;
	}


	/**
	 * Get the method from the response
	 *
	 * @return string method name
	 */
	public function getMethod() {
		if(isset($this->response_result['method'])) {
			return $this->response_result['method'];
		}
		return null;
	}


	/**
	 * Get the signature from the response
	 *
	 * @return string signature
	 */
	public function getSignature() {
		if(isset($this->response_result['signature'])) {
			return $this->response_result['signature'];
		}
		return null;
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
