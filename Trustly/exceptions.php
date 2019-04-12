<?php
/**
 * Definition file for all the exceptions thrown nativly by the API.
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
 * Thrown whenever there is a connectivity problem with the API. Such as a
 * network problem or an overall problem with the service itself.
 */
class Trustly_ConnectionException extends Exception { }

/**
 * Thrown if we encounter a response or notification request from the API with
 * a JSON RPC version this API has not been built to handle.
 */
class Trustly_JSONRPCVersionException extends Exception { }

/**
 * Thrown whenever we encounter a response or notifiction request from the API
 * that is signed with an incorrect signature. This is serious and could be an
 * indication that message contents are being tampered with.
 */
class Trustly_SignatureException extends Exception {

	/**
	 * Constructor
	 *
	 * @param string $message Exception message
	 *
	 * @param array $data Data that was signed with an invalid signature
	 */
	public function __construct($message, $data=null) {
		parent::__construct($message);
		$this->signature_data = $data;
	}


	/**
	 * Get the data that had an invalid signature. This is the only way to get
	 * data from anything with a bad signature. This should be used for
	 * DEBUGGING ONLY. You should NEVER rely on the contents.
	 */
	public function getBadData() {
		return $this->signature_data;
	}
}

/**
 * General purpose exception for bad data in request, response or notifications.
 */
class Trustly_DataException extends Exception { }

/**
 * Thrown when the API cannot use the supplied credentials for communicating
 * with the API.
 */
class Trustly_AuthentificationException extends Exception { }

/* vim: set noet cindent sts=4 ts=4 sw=4: */
