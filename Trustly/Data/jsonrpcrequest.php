<?php
/**
 * Trustly_Data_JSONRPCRequest class.
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
 * Class implementing the structure for data used in the signed API calls
 */
class Trustly_Data_JSONRPCRequest extends Trustly_Data_Request {

	/**
	 * Constructor.
	 *
	 * @throws Trustly_DataException If the combination of $data and
	 *		$attributes is invalid
	 *
	 * @param string $method Outgoing call API method
	 *
	 * @param mixed $data Outputgoing call Data (if any). This can be either an
	 *		array or a simple non-complex value.
	 *
	 * @param mixed $attributes Outgoing call attributes if any. If attributes
	 *		is set then $data needs to be an array.
	 */
	public function __construct($method=null, $data=null, $attributes=null) {
		$payload = null;

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


	/**
	 * Set a value in the params section of the request
	 *
	 * @param string $name Name of parameter
	 *
	 * @param mixed $value Value of parameter
	 *
	 * @return mixed $value
	 */
	public function setParam($name, $value) {
		$this->payload['params'][$name] = Trustly_Data::ensureUTF8($value);
		return $value;
	}


	/**
	 * Get the value of a params parameter in the request
	 *
	 * @param string $name Name of parameter of which to obtain the value
	 *
	 * @return mixed The value
	 */
	public function getParam($name) {
		if(isset($this->payload['params'][$name])) {
			return $this->payload['params'][$name];
		}
		return null;
	}


	/**
	 * Pop the value of a params parameter in the request. I.e. get the value
	 * and then remove the value from the params.
	 *
	 * @param string $name Name of parameter of which to obtain the value
	 *
	 * @return mixed The value
	 */
	public function popParam($name) {
		$v = null;
		if(isset($this->payload['params'][$name])) {
			$v = $this->payload['params'][$name];
		}
		unset($this->payload['params'][$name]);
		return $v;
	}


	/**
	 * Set the UUID value in the outgoing call.
	 *
	 * @param string $uuid The UUID
	 *
	 * @return string $uuid
	 */
	public function setUUID($uuid) {
		$this->payload['params']['UUID'] = Trustly_Data::ensureUTF8($uuid);
		return $uuid;
	}


	/**
	 * Get the UUID value from the outgoing call.
	 *
	 * @return string The UUID value
	 */
	public function getUUID() {
		if(isset($this->payload['params']['UUID'])) {
			return $this->payload['params']['UUID'];
		}
		return null;
	}

	/**
	 * Set the Method value in the outgoing call.
	 *
	 * @param string $method The name of the API method this call is for
	 *
	 * @return string $method
	 */
	public function setMethod($method) {
		return $this->set('method', $method);
	}


	/**
	 * Get the Method value from the outgoing call.
	 *
	 * @return string The Method value.
	 */
	public function getMethod() {
		return $this->get('method');
	}


	/**
	 * Set a value in the params->Data part of the payload.
	 *
	 * @param string $name The name of the Data parameter to set
	 *
	 * @param mixed $value The value of the Data parameter to set
	 *
	 * @return mixed $value
	 */
	public function setData($name, $value) {
		if(!isset($this->payload['params']['Data'])) {
			$this->payload['params']['Data'] = array();
		}
		$this->payload['params']['Data'][$name] = Trustly_Data::ensureUTF8($value);
		return $value;
	}


	/**
	 * Get the value of one parameter in the params->Data section of the
	 * request. Or the entire Data section if no name is given.
	 *
	 * @param string $name Name of the Data param to obtain. Leave as NULL to
	 *		get the entire structure.
	 *
	 * @return mixed The value or the entire Data depending on $name
	 */
	public function getData($name=null) {
		if(isset($name)) {
			if(isset($this->payload['params']['Data'][$name])) {
				return $this->payload['params']['Data'][$name];
			}
		} else {
			if(isset($this->payload['params']['Data'])) {
				return $this->payload['params']['Data'];
			}
		}
		return null;
	}


	/**
	 * Set a value in the params->Data->Attributes part of the payload.
	 *
	 * @param string $name The name of the Attributes parameter to set
	 *
	 * @param mixed $value The value of the Attributes parameter to set
	 *
	 * @return mixed $value
	 */
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


	/**
	 * Get the value of one parameter in the params->Data->Attributes section
	 * of the request. Or the entire Attributes section if no name is given.
	 *
	 * @param string $name Name of the Attributes param to obtain. Leave as NULL to
	 *		get the entire structure.
	 *
	 * @return mixed The value or the entire Attributes depending on $name
	 */
	public function getAttribute($name) {
		if(isset($this->payload['params']['Data']['Attributes'][$name])) {
			return $this->payload['params']['Data']['Attributes'][$name];
		}
		return null;
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
