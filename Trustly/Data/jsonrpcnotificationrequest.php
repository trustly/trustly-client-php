<?php
/**
 * Trustly_Data_JSONRPCNotificationRequest class.
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
 * Class implementing the interface to the data in a notification request from
 * the Trustly API.
 */
class Trustly_Data_JSONRPCNotificationRequest extends Trustly_Data {

	/**
	 * The RAW incoming notification body
	 * @var string
	 */
	var $notification_body = null;


	/**
	 * Constructor.
	 *
	 * @throws Trustly_DataException When the incoming data is invalid.
	 *
	 * @throws Trustly_JSONRPCVersionException When the incoming notification
	 *		request seems to be valid but is for a JSON RPC version we do not
	 *		support.
	 *
	 * @param string $notification RAW incoming notification body
	 */
	public function __construct($notification_body) {
		parent::__construct();

		$this->notification_body = $notification_body;

		if(empty($notification_body)) {
			throw new Trustly_DataException('Empty notification body');
		}

		$payload = json_decode($notification_body, true);

		if(is_null($payload)) {
			$error = '';
			if(function_exists('json_last_error_msg')) {
				$error = ': ' . json_last_error_msg();
			}
			throw new Trustly_DataException('Failed to parse JSON' . $error);
		}

		$this->payload = $payload;

		if($this->getVersion() != '1.1') {
			throw new Trustly_JSONRPCVersionException('JSON RPC Version '. $this->getVersion() .'is not supported');
		}
	}


	/**
	 * Get value from or the entire params payload.
	 *
	 * @param string $name Name of the params parameter to obtain. Leave blank
	 *		to get the entire payload
	 *
	 * @return mixed The value for the params parameter or the entire payload
	 *		depending on $name
	 */
	public function getParams($name=null) {
		if(!isset($this->payload['params'])) {
			return null;
		}
		$params = $this->payload['params'];
		if(isset($name)) {
			if(isset($params[$name])) {
				return $params[$name];
			}
		} else {
			return $params;
		}
		return null;
	}


	/**
	 * Get the value of a parameter in the params->data section of the
	 * notification response.
	 *
	 * @param string $name The name of the parameter. Leave as NULL to get the
	 *		entire payload.
	 *
	 * @return mixed The value sought after or the entire payload depending on
	 *		$name.
	 */
	public function getData($name=null) {
		if(!isset($this->payload['params']['data'])) {
			return null;
		}
		$data = $this->payload['params']['data'];
		if(isset($name)) {
			if(isset($data[$name])) {
				return $data[$name];
			}
		} else {
			return $data;
		}
		return null;
	}


	/**
	 * Get the UUID from the request.
	 *
	 * @return string The UUID value
	 */
	public function getUUID() {
		return $this->getParams('uuid');
	}


	/**
	 * Get the Method from the request.
	 *
	 * @return string The Method value.
	 */
	public function getMethod() {
		return $this->get('method');
	}


	/**
	 * Get the Signature from the request.
	 *
	 * @return string The Signature value.
	 */
	public function getSignature() {
		return $this->getParams('signature');
	}


	/**
	 * Get the JSON RPC version from the request.
	 *
	 * @return string The Version.
	 */
	public function getVersion() {
		return $this->get('version');
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
