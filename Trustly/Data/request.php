<?php
/**
 * Trustly_Data_Request class.
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
 * Data base class for outgoing API requests
 */
class Trustly_Data_Request extends Trustly_Data {
	/**
	 * Call method name
	 * @var string
	 */
	var $method = null;

	/**
	 * Constructor.
	 *
	 * @param string $method Method name for the call
	 *
	 * @param array $payload Call payload
	 */
	public function __construct($method=null, $payload=null) {
		parent::__construct();

		$vpayload = $this->vacuum($payload);
		if(isset($vpayload)) {
			$this->payload = $vpayload;
		}

		$this->method = $method;
	}


	/**
	 * Convenience function for getting the uuid from the call
	 *
	 * @return string uuid
	 */
	public function getUUID() {
		if(isset($this->payload['uuid'])) {
			return $this->payload['uuid'];
		}
		return null;
	}


	/**
	 * Convenience function for setting the uuid in the call
	 *
	 * @param string uuid
	 */
	public function setUUID($uuid) {
		$this->set('uuid', $uuid);
	}


	/**
	 * Get the method in the outgoing call
	 *
	 * @return string method name
	 */
	public function getMethod() {
		return $this->method;
	}


	/**
	 * Set the medhod in the call
	 *
	 * @param string method name
	 */
	public function setMethod($method) {
		$this->method = $method;
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
