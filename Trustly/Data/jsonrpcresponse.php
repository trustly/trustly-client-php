<?php
/**
 * Trustly_Data_JSONRPCResponse class.
 *
 * @license https://opensource.org/licenses/MIT
 * @copyright Copyright (c) 2014 Trustly Group AB
 */

/*
 * The MIT License (MIT)
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
 * Class implementing a basic response for a JSON RPC call to the Trustly API
 */
class Trustly_Data_JSONRPCResponse extends Trustly_Data_Response {

	/**
	 * Constructor.
	 *
	 * @param string $response_body RAW response body from the API
	 */
	public function __construct($response_body) {
		parent::__construct($response_body);

		$version = (string)$this->get('version');
		if($version !== '1.1') {
			throw new Trustly_JSONRPCVersionException("JSON RPC Version $version is not supported. " . json_encode($this->payload));
		}

			/* An unsigned JSON RPC Error result basically looks like this:
			 * {
			 *		"version": "1.1",
			 *		"error": {
			 *			"name": "JSONRPCError",
			 *			"code": 620,
			 *			"message": "ERROR_UNKNOWN"
			 *		}
			 *	}
			 *
			 * And a unsigned result will be on the form:
			 * {
			 *		"version": "1.1",
			 *		"result": {
			 *			"now": "...",
			 *			"data": []
			 *		}
			 * }
			 *
			 * We want response_result to always be the result of the
			 * operation, The Trustly_Data will point response_result /result
			 * or /error respectivly, we need to do nothing extra here
			 * */
	}


	/**
	 * Get error code (if any) from the API response
	 *
	 * @return integer The error code (numerical)
	 */
	public function getErrorCode() {
		if($this->isError() && isset($this->response_result['code'])) {
			return $this->response_result['code'];
		}
		return null;
	}

	/**
	 * Get error message (if any) from the API response
	 *
	 * @return string The error message
	 */
	public function getErrorMessage() {
		if($this->isError() && isset($this->response_result['message'])) {
			return $this->response_result['message'];
		}
		return null;
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
