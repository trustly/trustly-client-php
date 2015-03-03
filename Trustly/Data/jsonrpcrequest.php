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

class Trustly_Data_JSONRPCRequest extends Trustly_Data_Request {

	public function __construct($method=NULL, $data=NULL, $attributes=NULL) {
		$payload = NULL;

		if(isset($data) || isset($attributes)) {
			$payload = array('params' => array());

			if(isset($data)) {
				if(!is_array($data) && isset($attributes)) {
					throw new Trustly_DataException('Data must be array if attributes is provided');
				}
				$payload['params']['Data'] = $data;
			}

			if(isset($attributes)) {
				if(!isset($payload['params']['Data'])) {
					$payload['params']['Data'] = array();
				}
				$payload['params']['Data']['Attributes'] = $attributes;
			}
		}

		parent::__construct($method, $payload);

		if(isset($method)) {
			$this->payload['method'] = $method;
		}

		if(!isset($this->payload['params'])) {
			$this->payload['params'] = array();
		}

		$this->set('version', '1.1');

	}

		/* Three functions for getting, setting, or getting and removing value
		 * in the 'params' section of the request payload */
	public function setParam($name, $value) {
		$this->payload['params'][$name] = Trustly_Data::ensureUTF8($value);
		return $value;
	}

	public function getParam($name) {
		if(isset($this->payload['params'][$name])) {
			return $this->payload['params'][$name];
		}
		return NULL;
	}

	public function popParam($name) {
		$v = NULL;
		if(isset($this->payload['params'][$name])) {
			$v = $this->payload['params'][$name];
		}
		unset($this->payload['params'][$name]);
		return $v;
	}

	public function setUUID($uuid) {
		$this->payload['params']['UUID'] = Trustly_Data::ensureUTF8($uuid);
		return $uuid;
	}

	public function getUUID() {
		if(isset($this->payload['params']['UUID'])) {
			return $this->payload['params']['UUID'];
		}
		return NULL;
	}

	public function setMethod($method) {
		return $this->set('method', $method);
	}

	public function getMethod() {
		return $this->get('method');
	}

		/* Two utility function for setting or getting data from the
		 * 'params'->'Data' part of the payload. */
	public function setData($name, $value) {
		if(!isset($this->payload['params']['Data'])) {
			$this->payload['params']['Data'] = array();
		}
		$this->payload['params']['Data'][$name] = Trustly_Data::ensureUTF8($value);
		return $value;
	}

	public function getData($name=NULL) {
		if(isset($name)) {
			if(isset($this->payload['params']['Data'][$name])) {
				return $this->payload['params']['Data'][$name];
			}
		} else {
			if(isset($this->payload['params']['Data'])) {
				return $this->payload['params']['Data'];
			}
		}
		return NULL;
	}

		/* Two utility function for setting or getting data from the
		 * 'params'->'Data'->'Attributes' part of the payload. */
	public function setAttribute($name, $value) {
		if(!isset($this->payload['params']['Data'])) {
			$this->payload['params']['Data'] = array();
		}

		if(!isset($this->payload['params']['Data']['Attributes'])) {
			$this->payload['params']['Data']['Attributes'] = array();
		}
		$this->payload['params']['Data']['Attributes'][$name] = Trustly_Data::ensureUTF8($value);
		return $value;
	}

	public function getAttribute($name) {
		if(isset($this->payload['params']['Data']['Attributes'][$name])) {
			return $this->payload['params']['Data']['Attributes'][$name];
		}
		return NULL;
	}
}
/* vim: set noet cindent ts=4 ts=4 sw=4: */
