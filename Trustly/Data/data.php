<?php
/**
 * Trustly_Data class.
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
 * Class implementing a basic datastructure that is either in response or in a
 * a JSON data request.
 */
class Trustly_Data {
	/**
	 * Data payload
	 * @var array
	 */
	protected $payload = null;

	/**
	 * Constructur.
	 */
	public function __construct() {
		$this->payload = array();
	}


	/**
	 * Utility function to vacuum the supplied data end remove unset
	 * values. This is used to keep the requests cleaner rather then
	 * supplying NULL values in the payload
	 *
	 * @param array $data data to clean
	 *
	 * @return array cleaned data
	 */
	public function vacuum($data) {
		if(is_null($data)) {
			return null;
		} elseif(is_array($data)) {
			$ret = array();
			foreach($data as $k => $v) {
				$nv = $this->vacuum($v);
				if(isset($nv)) {
					$ret[$k] = $nv;
				}
			}
			if(count($ret)) {
				return $ret;
			}
			return null;
		} else {
			return $data;
		}
	}


	/**
	 * Get the specific data value from the payload or the full payload if
	 * no value is supplied
	 *
	 * @param string $name The optional data parameter to get. If NULL then the
	 * entire payload will be returned.
	 *
	 * @return mixed value
	 */
	public function get($name=null) {
		if($name === null) {
			return $this->payload;
		} else {
			if(isset($this->payload[$name])) {
				return $this->payload[$name];
			}
		}
		return null;
	}


	/**
	 * Function to ensure that the given value is in UTF8. Used to make sure
	 * all outgoing data is properly encoded in the call
	 *
	 * @param string $str String to process
	 *
	 * @return string UTF-8 variant of string
	 */
	public static function ensureUTF8($str) {
		if($str == null) {
			return null;
		}
		$enc = mb_detect_encoding($str, array('ISO-8859-1', 'ISO-8859-15', 'UTF-8', 'ASCII'));
		if($enc !== false) {
			if($enc == 'ISO-8859-1' || $enc == 'ISO-8859-15') {
				$str = mb_convert_encoding($str, 'UTF-8', $enc);
			}
		}
		return $str;
	}


	/**
	 * Set a value in the payload to a given value.
	 *
	 * @param string $name
	 *
	 * @param mixed $value
	 */
	public function set($name, $value) {
		$this->payload[$name] = Trustly_Data::ensureUTF8($value);
	}


	/**
	 * pop a value from the payload, i.e. fetch value and clear it in the
	 * payload.
	 *
	 * @param string $name
	 *
	 * @return mixed The value
	 */
	public function pop($name) {
		$v = null;
		if(isset($this->payload[$name])) {
			$v = $this->payload[$name];
		}
		unset($this->payload[$name]);
		return $v;
	}


	/**
	 * Get the payload in JSON form.
	 *
	 * @param boolean $pretty Format the output in a prettified easy-to-read
	 *		formatting
	 *
	 * @return string The current payload in JSON
	 */
	public function json($pretty=false) {
		if($pretty) {
			$sorted = $this->payload;
			$this->sortRecursive($sorted);
			return json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} else {
			return json_encode($this->payload);
		}
	}


	/**
	 * Sort the data in the payload.
	 *
	 * Extremely naivly done and does not by far handle all cases, but
	 * handles the case it should, i.e. sort the data for the json
	 * pretty printer
	 *
	 * @param mixed $data Payload to sort. Will be sorted in place
	 * */
	private function sortRecursive(&$data) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				if(is_array($v)) {
					$this->sortRecursive($v);
					$data[$k] = $v;
				}
			}
			ksort($data);
		}
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
