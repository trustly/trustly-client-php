<?php
/**
 * Trustly_Api class.
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
 * Abstract base class for all the API's.
 *
 * Implements all of the basic communication, key handling, signature
 * verification and creation as well as defining some common stubs for the
 * class implementations to implements.
 */
abstract class Trustly_Api {
	/**
	 * API host used for communication. This also controls which host key file
	 * that is loaded to verify the communications from the API.
	 *
	 * @var string FQHN
	 */
	protected $api_host = null;
	/**
	 * API port used for communication.
	 *
	 * @var integer Normally either 443 (https) or 80 (http)
	 */
	protected $api_port = null;
	/**
	 * Inidicator wether the API host is communicating using https
	 *
	 * @var bool
	 */
	protected $api_is_https = true;

	/**
	 * The data in the last request made. Kept for diagnostics usage mostly.
	 *
	 * @see Trustly_Api::getLastRequest()
	 *
	 * @var array Last API call in data form.
	 */
	public $last_request = null;

	/**
	 * API Constructor
	 *
	 * @throws InvalidArgumentException If the public key for the API host
	 *		cannot be loaded.
	 *
	 * @throws Trustly_ConnectionException If the curl library is not
	 *		available.
	 *
	 * @param string $host API host used for communication. Fully qualified
	 *		hostname. When integrating with our public API this is typically
	 *		either 'test.trustly.com' or 'trustly.com'.
	 *
	 * @param integer $port Port on API host used for communicaiton. Normally
	 *		443 for https, or 80 for http.
	 *
	 * @param bool $is_https Indicator wether the port on the API host expects
	 *		https.
	 */
	public function __construct($host='trustly.com', $port=443, $is_https=true) {
		$this->api_is_https = $is_https;

		if($this->loadTrustlyPublicKey($host, $port, $is_https) === false) {
			$error = openssl_error_string();
			throw new InvalidArgumentException("Cannot load Trustly public key file for host $host".(isset($error)?", error $error":''));
		}

		/* Make sure the curl extension is loaded so we can open URL's */
		if(!in_array('curl', get_loaded_extensions())) {
			throw new Trustly_ConnectionException('curl is not installed. We cannot call the API, bailing');
		}
	}

	/**
	 * Load the public key used for for verifying incoming data responses from
	 * trustly. The keys are distributed as a part of the source code package
	 * and should be named to match the host under $PWD/HOSTNAME.public.pem
	 *
	 * @param string $host API host used for communication. Fully qualified
	 *		hostname. When integrating with our public API this is typically
	 *		either 'test.trustly.com' or 'trustly.com'.
	 *
	 * @param integer $port Port on API host used for communicaiton. Normally
	 *		443 for https, or 80 for http.
	 *
	 * @return boolean Indicating success or failure of loading the key for the current host.
	 */
	public function loadTrustlyPublicKey($host, $port) {
		$filename = sprintf('%s/keys/%s:%d.public.pem', realpath(dirname(__FILE__)), $host, $port);
		$altfilename = sprintf('%s/keys/%s.public.pem', realpath(dirname(__FILE__)), $host);

		$cert = @file_get_contents($filename);
		if($cert === false) {
			$cert = @file_get_contents($altfilename);
		}
		if($cert !== false) {
			$this->trustly_publickey = openssl_pkey_get_public($cert);
			if(!$this->trustly_publickey) {
				return false;
			}
			$this->api_host = $host;
			$this->api_port = $port;
			return true;
		}
		return false;
	}

	/**
	 * Serializes the given data in a form suitable for creating a signature.
	 *
	 * @link https://trustly.com/en/developer/api#/signature
	 *
	 * @param array $data Input data to serialize
	 *
	 * @return array The input data in a serialized form
	 */
	public function serializeData($data) {
		if(is_array($data)) {
			ksort($data);
			$return = '';
			foreach($data as $key => $value) {
				if(is_numeric($key)) {
					$return .= $this->serializeData($value);
				} else {
					$return .= $key . $this->serializeData($value);
				}
			}
			return $return;
		} else {
			return (string)$data;
		}
	}

	/**
	 * Given that the communication is from the host configured for this API,
	 * verify if the $signature is indeed valid for the $method, $uuid and
	 * $data.
	 *
	 * @link https://trustly.com/en/developer/api#/signature
	 *
	 * @param string $method Method in the API call
	 *
	 * @param string $uuid UUID in the API call
	 *
	 * @param string $signature in the API call
	 *
	 * @param array $data in the API call
	 *
	 * @return boolean Indicating wether or not the host key was used for
	 *		signing this data.
	 */
	protected function verifyTrustlySignedData($method, $uuid, $signature, $data) {
		if($method === null) {
			$method = '';
		}
		if($uuid === null) {
			$uuid = '';
		}

		if(!isset($signature)) {
			return false;
		}

		$serial_data = $method . $uuid . $this->serializeData($data);
		$raw_signature = base64_decode($signature);
		if (version_compare(phpversion(), '5.2.0', '<')) {
			return (boolean)openssl_verify($serial_data, $raw_signature, $this->trustly_publickey);
		} else {
			return (boolean)openssl_verify($serial_data, $raw_signature, $this->trustly_publickey, OPENSSL_ALGO_SHA1);
		}
	}

	/**
	 * Check to make sure that the given response (instance of
	 * Trustly_Data_Response) has been signed with the correct key when
	 * originating from the host
	 *
	 * @param Trustly_Data_Response $response Response from the API call.
	 *
	 * @return boolean Indicating if the data was indeed properly signed by the
	 *		API we think we are talking to
	 */
	public function verifyTrustlySignedResponse($response) {
		$method = $response->getMethod();
		$uuid = $response->getUUID();
		$signature = $response->getSignature();
		$data = $response->getData();

		return $this->verifyTrustlySignedData($method, $uuid, $signature, $data);
	}

	/**
	 * Check to make sure that the given notification (instance of
	 * Trustly_Data_JSONRPCNotificationRequest) has been signed with the
	 * correct key originating from the host
	 *
	 * @param Trustly_Data_JSONRPCNotificationRequest incoming notification
	 *
	 * @return boolean Indicating if the data was indeed properly signed by the
	 *		API we think we are talking to
	 */
	public function verifyTrustlySignedNotification($notification) {
		$method = $notification->getMethod();
		$uuid = $notification->getUUID();
		$signature = $notification->getSignature();
		$data = $notification->getData();

		return $this->verifyTrustlySignedData($method, $uuid, $signature, $data);
	}

	/**
	 * Update the host settings within the API. Use this method to switch API peer.
	 *
	 * @throws InvalidArgumentException If the public key for the API host
	 *		cannot be loaded.
	 *
	 * @param string $host API host used for communication. Fully qualified
	 *		hostname. When integrating with our public API this is typically
	 *		either 'test.trustly.com' or 'trustly.com'. NULL means do not change.
	 *
	 * @param integer $port Port on API host used for communicaiton. Normally
	 *		443 for https, or 80 for http. NULL means do not change.
	 *
	 * @param bool $is_https Indicator wether the port on the API host expects
	 *		https. NULL means do not change.
	 */
	public function setHost($host=null, $port=null, $is_https=null) {
		if(!isset($host)) {
			$host = $this->api_host;
		}

		if(!isset($port)) {
			$port = $this->api_port;
		}

		if($this->loadTrustlyPublicKey($host, $port) === false) {
			$error = openssl_error_string();
			throw new InvalidArgumentException("Cannot load Trustly public key file for host $host".(isset($error)?", error $error":''));
		}

		if(isset($is_https)) {
			$this->api_is_https = $is_https;
		}
	}

	/**
	 * Setup and return a curl handle for submitting data to the API peer.
	 *
	 * @param string $url The URL to communicate with
	 *
	 * @param string $postdata The (optional) data to post.
	 *
	 * @return Array($body, $response_code)
	 */
	public function post($url=null, $postdata=null) {
		/* Do note that that if you are going to POST JSON you need to set the
		 * content-type of the transfer AFTER you set the postfields, this is done
		 * if you provide the postdata here, if not, take care to do it or the
		 * content-type will be wrong */
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_PORT, $this->api_port);

		if($this->api_is_https) {
			if(@CURLOPT_PROTOCOLS != 'CURLOPT_PROTOCOLS') {
				curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
			}
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		} else {
			if(@CURLOPT_PROTOCOLS != 'CURLOPT_PROTOCOLS') {
				curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
			}
		}
		if(isset($postdata)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_URL, $url);

		$body = curl_exec($curl);
		if($body === false) {
			$error = curl_error($curl);
			if($error === null) {
				$error = 'Failed to connect to the Trusly API';
			}
			throw new Trustly_ConnectionException($error);
		}

		if($this->api_is_https) {
			$ssl_result = curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT);
			if($ssl_result !== 0) {

				$curl_x509_errors = array(
					'0' => 'X509_V_OK',
					'2' => 'X509_V_ERR_UNABLE_TO_GET_ISSUER_CERT',
					'3' => 'X509_V_ERR_UNABLE_TO_GET_CRL',
					'4' => 'X509_V_ERR_UNABLE_TO_DECRYPT_CERT_SIGNATURE',
					'5' => 'X509_V_ERR_UNABLE_TO_DECRYPT_CRL_SIGNATURE',
					'6' => 'X509_V_ERR_UNABLE_TO_DECODE_ISSUER_PUBLIC_KEY',
					'7' => 'X509_V_ERR_CERT_SIGNATURE_FAILURE',
					'8' => 'X509_V_ERR_CRL_SIGNATURE_FAILURE',
					'9' => 'X509_V_ERR_CERT_NOT_YET_VALID',
					'10' => 'X509_V_ERR_CERT_HAS_EXPIRED',
					'11' => 'X509_V_ERR_CRL_NOT_YET_VALID',
					'12' => 'X509_V_ERR_CRL_HAS_EXPIRED',
					'13' => 'X509_V_ERR_ERROR_IN_CERT_NOT_BEFORE_FIELD',
					'14' => 'X509_V_ERR_ERROR_IN_CERT_NOT_AFTER_FIELD',
					'15' => 'X509_V_ERR_ERROR_IN_CRL_LAST_UPDATE_FIELD',
					'16' => 'X509_V_ERR_ERROR_IN_CRL_NEXT_UPDATE_FIELD',
					'17' => 'X509_V_ERR_OUT_OF_MEM',
					'18' => 'X509_V_ERR_DEPTH_ZERO_SELF_SIGNED_CERT',
					'19' => 'X509_V_ERR_SELF_SIGNED_CERT_IN_CHAIN',
					'20' => 'X509_V_ERR_UNABLE_TO_GET_ISSUER_CERT_LOCALLY',
					'21' => 'X509_V_ERR_UNABLE_TO_VERIFY_LEAF_SIGNATURE',
					'22' => 'X509_V_ERR_CERT_CHAIN_TOO_LONG',
					'23' => 'X509_V_ERR_CERT_REVOKED',
					'24' => 'X509_V_ERR_INVALID_CA',
					'25' => 'X509_V_ERR_PATH_LENGTH_EXCEEDED',
					'26' => 'X509_V_ERR_INVALID_PURPOSE',
					'27' => 'X509_V_ERR_CERT_UNTRUSTED',
					'28' => 'X509_V_ERR_CERT_REJECTED',
					'29' => 'X509_V_ERR_SUBJECT_ISSUER_MISMATCH',
					'30' => 'X509_V_ERR_AKID_SKID_MISMATCH',
					'31' => 'X509_V_ERR_AKID_ISSUER_SERIAL_MISMATCH',
					'32' => 'X509_V_ERR_KEYUSAGE_NO_CERTSIGN',
					'50' => 'X509_V_ERR_APPLICATION_VERIFICATION'
				);

				$ssl_error_str = null;
				if(isset($curl_x509_errors[$ssl_result])) {
					$ssl_error_str = $curl_x509_errors[$ssl_result];
				}

				$error = 'Failed to connect to the Trusly API. SSL Verification error #' . $ssl_result . ($ssl_error_str?': ' . $ssl_error_str:'');
				throw new Trustly_ConnectionException($error);
			}
		}
		$response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		return array($body, $response_code);
	}

	/**
	 * Return a properly formed url to communicate with this API.
	 *
	 * @return URL pointing to the API peer.
	 */
	public function baseURL() {
		if($this->api_is_https) {
			$url = 'https://' . $this->api_host . ($this->api_port != 443?':'.$this->api_port:'');
		} else {
			$url = 'http://' . $this->api_host . ($this->api_port != 80?':'.$this->api_port:'');
		}
		return $url;
	}

	/**
	 * Return a URL to the API to the given request path.
	 *
	 * @param Trustly_Data_Request $request Data to send in the request
	 *
	 * @return URL to the API peer with the given query path.
	 */
	public function url($request=null) {
		return $this->baseURL() . $this->urlPath($request);
	}


	/**
	 * Return the last request that we attempted to make via this API
	 *
	 * @return array Last request data structure.
	 */
	public function getLastRequest() {
		return $this->last_request;
	}

	/**
	 * Given the http(s) body of an (presumed) notification from Trustly. Verify
	 * that signatures are valid and build a
	 * Trustly_Data_JSONRPCNotificationRequest object from the incoming data.
	 *
	 * This should ALWAYS be the first steps when
	 * accessing data in the notification, a noficiation with a poor or invalid
	 * signature should be discarded.
	 *
	 * @throws Trustly_SignatureException When signature holds an invalid
	 *		signature.
	 *
	 * @param string $httpbody Incoming raw notification body.
	 *
	 * @return Trustly_Data_JSONRPCNotificationRequest of the notification.
	 */
	public function handleNotification($httpbody) {
		$request = new Trustly_Data_JSONRPCNotificationRequest($httpbody);

		if($this->verifyTrustlySignedNotification($request) !== true) {
			throw new Trustly_SignatureException('Incomming notification signature is not valid', $httpbody);
		}

		return $request;
	}


	/**
	 * Given an object from an incoming notification request build a response
	 * object that can be used to respond to trustly with
	 *
	 * @param Trustly_Data_JSONRPCNotificationRequest $request
	 *
	 * @param boolean $success Indicator if we should respond with processing
	 *		success or failure to the notification.
	 *
	 * @return Trustly_Data_JSONRPCNotificationResponse response object.
	 */
	public function notificationResponse($request, $success=true) {
		$response = new Trustly_Data_JSONRPCNotificationResponse($request, $success);
		return $response;
	}


	/**
	 * Call the trustly API with the given request.
	 *
	 * @throws Trustly_DataException Upon failure to add all the communication
	 *		parameters to the data.
	 *
	 * @throws Trustly_ConnectionException When failing to communicate with the
	 *		API.
	 *
	 * @param Trustly_Data_Request $request Outgoing data request.
	 *
	 */
	public function call($request) {
		if($this->insertCredentials($request) !== true) {
			throw new Trustly_DataException('Unable to add authorization criterias to outgoing request');
		}
		$this->last_request = $request;

		$jsonstr = $request->json();

		$url = $this->url($request);
		list($body, $response_code) = $this->post($url, $jsonstr);

		$result = $this->handleResponse($request, $body, $response_code);
		return $result;
	}

	/**
	 * Return a boolean value formatted for communicating with the API.
	 *
	 * @param boolean $value Boolean value to encode
	 *
	 * @return API encoded boolean value
	 */
	protected function apiBool($value) {
		if(isset($value)) {
			if($value) {
				return '1';
			} else {
				return '0';
			}
		}
		return null;
	}

	/**
	 * Returns the PATH portion of the URL for communicating with the API. The
	 * API endpoint will typically differ with the type of te API we are
	 * communicating with.
	 *
	 * See specific class implementing the call for more information.
	 *
	 * @param Trustly_Data_Request $request Data to send in the request
	 *
	 * @return string The URL path
	 */
	abstract protected function urlPath($request=null);

	/**
	 * Callback for handling the response from an API call. This call is
	 * expected to take the input data and create an instance of a response
	 * object.
	 *
	 * See specific class implementing the call for more information.
	 *
	 * @param Trustly_Data_Request $request Outgoing request
	 *
	 * @param string $body The body recieved in response to the request
	 *
	 * @param integer $response_code the HTTP response code for the call
	 */
	abstract protected function handleResponse($request, $body, $response_code);

	/**
	 * Callback for populating the outgoing request with the criterias needed
	 * for communication. This can be username/password as well as a signature.
	 *
	 * See specific class implementing the call for more information.
	 *
	 * @param Trustly_Data_Request $request Request to be used in the outgoing
	 *		call
	 */
	abstract protected function insertCredentials($request);

}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
