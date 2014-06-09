<?php
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
