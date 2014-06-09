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

class Trustly_Data {
	var $payload = NULL;

	public function __construct($payload=NULL) {

		$this->payload = $this->vacuum($payload);

		if($this->payload === NULL) {
			$this->payload = array();
		}
	}

	/* Utility function to vacuum the supplied data end remove unset 
	 * values. This is used to keep the requests cleaner rather then 
	 * supplying NULL values in the payload */
	public function vacuum($data) {
		if(is_null($data)) {
			return NULL;
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
			return NULL;
		} else {
			return $data;
		}
	}

	/* Get the specific data value from the payload or the full payload if 
	 * no value is supplied */
	public function get($name=NULL) {
		if($name === NULL) {
			return $this->payload;
		} else {
			if(isset($this->payload[$name])) {
				return $this->payload[$name];
			}
		}
		return NULL;
	}

	/* Funciton to ensure that the given value is in UTF8. Used to make sure 
	 * all outgoing data is properly encoded in the call */
	public static function ensureUTF8($str) {
		if($str == NULL) {
			return NULL;
		}
		$enc = mb_detect_encoding($str, array('ISO-8859-1', 'ISO-8859-15', 'UTF-8', 'ASCII'));
		if($enc !== FALSE) {
			if($enc == 'ISO-8859-1' || $enc == 'ISO-8859-15') {
				$str = mb_convert_encoding($str, 'UTF-8', $enc);
			}
		}
		return $str;
	}

		/* Set a value in the payload to a given value */
	public function set($name, $value) {
		$this->payload[$name] = Trustly_Data::ensureUTF8($value);
	}

		/* Get and remove a value from the payload. */
	public function pop($name) {
		$v = NULL;
		if(isset($this->payload[$name])) {
			$v = $this->payload[$name];
		}
		unset($this->payload[$name]);
		return $v;
	}

		/* Get JSON copy of the payload */
	public function json($pretty=FALSE) {
		if($pretty) {
			$sorted = $this->payload;
			$this->sortRecursive($sorted);
			return json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} else {
			return json_encode($this->payload);
		}
	}

		/* Extremely naivly done and does not by far handle all cases, but 
		 * handles the case it should, i.e. sort the data for the json 
		 * pretty printer */
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

?>
