<?php
/**
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

class Trustly_Data_JSONRPCNotificationResponse extends Trustly_Data {

	public function __construct($request, $success=NULL) {

		parent::__construct();

		$uuid = $request->getUUID();
		$method = $request->getMethod();

		if(isset($uuid)) {
			$this->setResult('uuid', $uuid);
		}
		if(isset($method)) {
			$this->setResult('method', $method);
		}

		if(isset($success)) {
			$this->setSuccess($success);
		}

		$this->set('version', '1.1');
	}

	public function setSuccess($success=NULL) {
		$status = 'OK';

		if(isset($success) && $success !== TRUE) {
			$status = 'FAILURE';
		}
		$this->setData('status', $status);
		return $success;
	}

	public function setSignature($signature) {
		$this->setResult('signature', $signature);
	}

	public function setResult($name, $value) {
		if(!isset($this->payload['result'])) {
			$this->payload['result'] = array();
		}
		$this->payload['result'][$name] = $value;
		return $value;
	}

	public function getResult($name=NULL) {
		$result = NULL;
		if(isset($this->payload['result'])) {
			$result = $this->payload['result'];
		} else {
			return NULL;
		}

		if(isset($name)) {
			if(isset($result[$name])) {
				return $result[$name];
			}
		} else {
			return $result;
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

	public function setData($name, $value) {
		if(!isset($this->payload['result'])) {
			$this->payload['result'] = array();
		}
		if(!isset($this->payload['result']['data'])) {
			$this->payload['result']['data'] = array($name => $value);
		} else {
			$this->payload['result']['data'][$name] = $value;
		}
		return $value;
	}

	public function getMethod() {
		return $this->getResult('method');
	}

	public function getUUID() {
		return $this->getResult('uuid');
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
