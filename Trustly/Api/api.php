<?php

abstract class Trustly_Api {
	/* Hostname, port and protocol information about how to reach the API  */
	var $api_host = NULL;
	var $api_port = NULL;
	var $api_is_https = TRUE;

	/* The data of the last request performed. */
	var $last_request = NULL;

	function __construct($host, $port, $is_https) {
		$this->api_host = $host;
		$this->api_port = $port;
		$this->api_is_https = $is_https;

		if($this->loadTrustlyPublicKey() === FALSE) {
			throw new InvalidArgumentException('Cannot load Trustly public key file ' . $trustly_publickeyfile);
		}
	}

	/* Load the public key used for for verifying incoming data responses from 
	 * trustly. The keys are distributed as a part of the source code package 
	 * and should be named to match the host under $PWD/HOSTNAME.public.pem */
	public function loadTrustlyPublicKey() {
		$filename = sprintf('%s/keys/%s.public.pem', __DIR__, $this->api_host);
		$cert = file_get_contents($filename);
		if($cert !== NULL) {
			$this->trustly_publickey = openssl_pkey_get_public($cert);
			return TRUE;
		}
		return FALSE;
	}

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

	/* Given all the components to verify and work with, check if the given 
	 * signature has been used to sign the data */
	protected function verifyTrustlySignedData($method, $uuid, $signature, 
		$data) {
		if($method === NULL) {
			$method = '';
		}
		if($uuid === NULL) {
			$uuid = '';
		}

		if(!isset($signature)) {
			return FALSE;
		}

		$serial_data = $method . $uuid . $this->serializeData($data);
		$raw_signature = base64_decode($signature);

		return (boolean)openssl_verify($serial_data, $raw_signature, $this->trustly_publickey, OPENSSL_ALGO_SHA1);
	}

	/* Check to make sure that the given response (instance of 
	 * Trustly_Data_Response) has been signed with the correct key when 
	 * originating from the host */
	public function verifyTrustlySignedResponse($response) {
		$method = $response->getMethod();
		$uuid = $response->getUUID();
		$signature = $response->getSignature();
		$data = $response->getResult();
		if($data !== NULL) {
			$data = $data['data'];
		}

		return $this->verifyTrustlySignedData($method, $uuid, $signature, $data);
	}

	/* Check to make sure that the given notification (instance of 
	 * Trustly_Data_JSONRPCNotificationRequest) has been signed with the 
	 * correct key originating from the host */
	public function verifyTrustlySignedNotification($notification) {
		$method = $notification->getMethod();
		$uuid = $notification->getUUID();
		$signature = $notification->getSignature();
		$data = $notification->getData();

		return $this->verifyTrustlySignedData($method, $uuid, $signature, $data);
	}

	/* Update the current host settings, leave out any parameter to leave as is  */
	public function setHost($host=NULL, $port=NULL, $is_https=NULL) {
		if(isset($host)) {
			$this->api_host = $host;
			if($this->loadTrustlyPublicKey() === FALSE) {
				throw new InvalidArgumentException('Cannot load Trustly public key file ' . $trustly_publickeyfile);
			}
		}
		if(isset($port)) {
			$this->api_port = $port;
		}
		if(isset($is_https)) {
			$this->api_is_https = $is_https;
		}
	}

	/* Do note that that if you are going to POST JSON you need to set the 
	 * content-type of the transfer AFTER you set the postfields, this is done 
	 * if you provide the postdata here, if not, take care to do it or the 
	 * content-type will be wrong */
	public function connect($url=NULL, $postdata=NULL) {
		$cu = curl_init();
		curl_setopt($cu, CURLOPT_FAILONERROR, FALSE);
		curl_setopt($cu, CURLOPT_FOLLOWLOCATION, FALSE);
		curl_setopt($cu, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($cu, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($cu, CURLOPT_TIMEOUT, 30);
		curl_setopt($cu, CURLOPT_PORT, $this->api_port);

		if($this->api_is_https) {
			curl_setopt($cu, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
			curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, TRUE);
		} else {
			curl_setopt($cu, CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
		}
		if(isset($postdata)) {
			curl_setopt($cu, CURLOPT_POSTFIELDS, $postdata);
		}
		curl_setopt($cu, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8')); 
		curl_setopt($cu, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($cu, CURLOPT_URL, $url);
		return $cu;
	}

	public function baseURL() {
		if($this->api_is_https) {
			$url = 'https://' . $this->api_host . ($this->api_port != 443?':'.$this->api_port:'');
		} else {
			$url = 'http://' . $this->api_host . ($this->api_port != 80?':'.$this->api_port:'');
		}
		return $url;
	}

	public function url($request=NULL) {
		return $this->baseURL() . $this->urlPath($request);
	}

	public function getLastRequest() {
		return $this->last_request;
	}

	/* Given the http body of an (presumed) notification from trustly. Verify 
	 * signatures and build a Trustly_Data_JSONRPCNotificationRequest object 
	 * from the incoming data. This should ALWAYS be the first steps when 
	 * accessing data in the notification, a noficiation with a poor or invalid 
	 * signature should be discarded. */
	public function handleNotification($httpbody) {
		$request = new Trustly_Data_JSONRPCNotificationRequest($httpbody);

		if($this->verifyTrustlySignedNotification($request) !== TRUE) {
			throw new Trustly_SignatureException('Incomming notification signature is not valid');
		}

		return $request;
	}


	/* Given an object from an incoming notification request build a response 
	 * object that can be used to respond to trustly with */
	public function notificationResponse($request, $success=TRUE) {
		$response = new Trustly_Data_JSONRPCNotificationResponse($request, $success);
		return $response;
	}

	/* Call the trustly API with the given request. */
	public function call($request) {
		if($this->insertCredentials($request) !== TRUE) {
			throw new Trustly_DataException('Unable to add authorization criterias to outgoing request');
		}
		$this->last_request = $request;

		$jsonstr = $request->json();

		$url = $this->url($request);
		$curl = $this->connect($url, $jsonstr);


		$body = curl_exec($curl);
		if($body === FALSE) {
			$error = curl_error($curl);
			if($error === NULL) {
				$error = 'Failed to connect to the Trusly API';
			}
			throw new Trustly_ConnectionException($error);
		}

		if($this->api_is_https) {
			$ssl_result = curl_getinfo($curl, CURLINFO_SSL_VERIFYRESULT); #FIXME
		}
		$result = $this->handleResponse($request, $body, $curl);
		curl_close($curl);
		return $result;
	}

	protected function apiBool($value) {
		if(isset($value)) {
			if($value) {
				return '1';
			} else {
				return '0';
			}
		}
		return NULL;
	}

	abstract public function urlPath($request=NULL);

	abstract public function handleResponse($request, $body, $curl);

	abstract public function insertCredentials($request);
}

?>
