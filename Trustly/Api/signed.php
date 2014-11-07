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

class Trustly_Api_Signed extends Trustly_Api {
	var $merchant_privatekey = NULL;

	function __construct($merchant_privatekeyfile, $username, $password, $host='trustly.com', $port=443, $is_https=TRUE) {

		parent::__construct($host, $port, $is_https);

		$this->api_username = $username;
		$this->api_password  = $password;
		if($merchant_privatekeyfile != NULL) {
			if($this->loadMerchantPrivateKey($merchant_privatekeyfile) === FALSE) {

				throw new InvalidArgumentException('Cannot load merchant private key file ' . $merchant_privatekeyfile);
			}
		}
	}

	/* Load up the merchants key for signing data from the supplied filename. 
	 * Inializes the internal openssl certificate needed for the signing */
	public function loadMerchantPrivateKey($filename) {
		$cert = @file_get_contents($filename);
		return $this->useMerchantPrivateKey($cert);
	}

	public function useMerchantPrivateKey($cert) {
		if($cert !== FALSE) {
			$this->merchant_privatekey = openssl_pkey_get_private($cert);
			return TRUE;
		}
		return FALSE;
	}

	/* Create a signature string suitable for including as the signature in an 
	 * outgoing request */
	public function signMerchantRequest($request) {
		if(!isset($this->merchant_privatekey)) {
			throw new Trustly_SignatureException('No private key has been loaded for signing');
		}

		$method = $request->getMethod();
		if($method === NULL) {
			$method = '';
		}
		$uuid = $request->getUUID();
		if($uuid === NULL) {
			$uuid = '';
		}

		$data = $request->getData();

		$serial_data = $method . $uuid . $this->serializeData($data);
		$raw_signature = '';

		$this->clearOpenSSLError();
		if(openssl_sign($serial_data, $raw_signature, $this->merchant_privatekey, OPENSSL_ALGO_SHA1) === TRUE) {
			return base64_encode($raw_signature);
		}

		throw new Trustly_SignatureException('Failed to sign the outgoing merchant request. '. openssl_error_string());

		return FALSE;
	}

	public function insertCredentials($request) {
		$request->setData('Username', $this->api_username);
		$request->setData('Password', $this->api_password);

		$signature = $this->signMerchantRequest($request);
		if($signature === FALSE) {
			return FALSE;
		}
		$request->setParam('Signature', $signature);

		return TRUE;
	}

	public function handleResponse($request, $body, $curl) {
		$response = new Trustly_Data_JSONRPCSignedResponse($body, $curl);

		if($this->verifyTrustlySignedResponse($response) !== TRUE) {
			throw new Trustly_SignatureException('Incomming message signature is not valid', $response);
		}

		if($response->getUUID() !== $request->getUUID()) {
			throw new Trustly_DataException('Incoming message is not related to request. UUID mismatch');
		}

		return $response;
	}

	public function notificationResponse($request, $success=TRUE) {
		$response = new Trustly_Data_JSONRPCNotificationResponse($request, $success);

		$signature = $this->signMerchantRequest($response);
		if($signature === FALSE) {
			return FALSE;
		}
		$response->setSignature($signature);

		return $response;
	}

	public function urlPath($request=NULL) {
		$url = '/api/1';
		return $url;
	}

	private function clearOpenSSLError() {
		/* Not really my favourite part of this library implementation. As 
		 * openssl queues error messages a single call to openssl_error_string 
		 * after a fail might get another "queued" message from before. And as 
		 * there is no way to clear the buffer... we will iterate until we will 
		 * get no more errors. Brilliant. */
		while ($err = openssl_error_string());
	}

	protected function generateUUID() {
		/* Not the classiest implementation, but to reduce the dependency of 
		 * non standard libraries we build it this way.  The risk of 
		 * collisions is low enough with a MD5 */
		$md5 = md5(uniqid('', true));
		return substr($md5, 0, 8).'-'.substr($md5, 8, 4).'-'.substr($md5, 12, 4).'-'.substr($md5, 16, 4).'-'.substr($md5, 20, 12);
	}

	public function call($request) {
		$uuid = $request->getUUID(); 
		if($uuid === NULL) {
			$request->setUUID($this->generateUUID());
		}
		return parent::call($request);
	}

	/* Make a deposit call */
	public function deposit($notificationurl, $enduserid, $messageid, 
		$locale=NULL, $amount=NULL, $currency=NULL, $country=NULL, 
		$mobilephone=NULL, $firstname=NULL, $lastname=NULL, 
		$nationalidentificationnumber=NULL, $shopperstatement=NULL,
		$ip=NULL, $successurl=NULL, $failurl=NULL, $templateurl=NULL,
		$urltarget=NULL, $suggestedminamount=NULL, $suggestedmaxamount=NULL,
		$integrationmodule=NULL) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid,
			);

			$attributes = array(
				'Locale' => $locale, 
				'Amount' => $amount,
				'Currency' => $currency,
				'Country' => $country, 
				'MobilePhone' => $mobilephone,
				'Firstname' => $firstname, 
				'Lastname' => $lastname, 
				'NationalIdentificationNumber' => $nationalidentificationnumber, 
				'ShopperStatement' => $shopperstatement,
				'IP' => $ip,
				'SuccessURL' => $successurl, 
				'FailURL' => $failurl,
				'TemplateURL' => $templateurl,
				'URLTarget' => $urltarget,
				'SuggestedMinAmount' => $suggestedminamount,
				'SuggestedMaxAmount' => $suggestedmaxamount,
				'IntegrationModule' => $integrationmodule
			);

			$request = new Trustly_Data_JSONRPCRequest('Deposit', $data, $attributes);
			return $this->call($request);
		}

	/* Make a refund call */
	public function refund($orderid, $amount, $currency) {

		$data = array(
			'OrderID' => $orderid,
			'Amount' => $amount,
			'Currency' => $currency,
		);

		$request = new Trustly_Data_JSONRPCRequest('Refund', $data);
		return $this->call($request);
	}

	/* Make a withdraw call */
	public function withdraw($notificationurl, $enduserid, $messageid, 
		$locale=NULL, $currency=NULL, $country=NULL, 
		$mobilephone=NULL, $firstname=NULL, $lastname=NULL, 
		$nationalidentificationnumber=NULL, $clearinghouse=NULL,
		$banknumber=NULL, $accountnumber=NULL) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid,
				'Currency' => $currency,
				'Amount' => null
			);

			$attributes = array(
				'Locale' => $locale, 
				'Country' => $country, 
				'MobilePhone' => $mobilephone,
				'Firstname' => $firstname, 
				'Lastname' => $lastname, 
				'NationalIdentificationNumber' => $nationalidentificationnumber, 
				'ClearingHouse' => $clearinghouse,
				'BankNumber' => $banknumber,
				'AccountNumber' => $accountnumber,
			);

			$request = new Trustly_Data_JSONRPCRequest('Withdraw', $data, $attributes);
			return $this->call($request);
		}

	/* Make an approvewithdrawal call */
	public function approveWithdrawal($orderid) {

		$data = array(
			'OrderID' => $orderid,
		);

		$request = new Trustly_Data_JSONRPCRequest('ApproveWithdrawal', $data);
		return $this->call($request);
	}

	/* Make an denywithdrawal call */
	public function denyWithdrawal($orderid) {

		$data = array(
			'OrderID' => $orderid,
		);

		$request = new Trustly_Data_JSONRPCRequest('DenyWithdrawal', $data);
		return $this->call($request);
	}

	/* Make a select account call */
	public function selectAccount($notificationurl, $enduserid, $messageid,
		$locale=NULL, $country=NULL, $ip=NULL, $successurl=NULL, $urltarget=NULL,
		$mobilephone=NULL, $firstname=NULL, $lastname=NULL) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid,
			);

			$attributes = array(
				'Locale' => $locale,
				'Country' => $country,
				'IP' => $ip,
				'SuccessURL' => $successurl,
				'URLTarget' => $urltarget,
				'MobilePhone' => $mobilephone,
				'Firstname' => $firstname,
				'Lastname' => $lastname, 
			);

			$request = new Trustly_Data_JSONRPCRequest('SelectAccount', $data, $attributes);
			return $this->call($request);
	}

	public function registerAccount($enduserid, $clearinghouse, $banknumber, 
		$accountnumber, $firstname, $lastname, $mobilephone=NULL, 
		$nationalidentificationnumber=NULL, $address=NULL) {

			$data = array(
				'EndUserID' => $enduserid,
				'ClearingHouse' => $clearinghouse,
				'BankNumber' => $banknumber,
				'AccountNumber' => $accountnumber,
				'Firstname' => $firstname, 
				'Lastname' => $lastname, 
			);

			$attributes = array(
				'MobilePhone' => $mobilephone, 
				'NationalIdentificationNumber' => $nationalidentificationnumber, 
				'Address' => $address
			);

			$request = new Trustly_Data_JSONRPCRequest('RegisterAccount', $data, $attributes);
			return $this->call($request);
	}

	public function accountPayout($notificationurl, $accountid, $enduserid, 
		$messageid, $amount, $currency) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid,
				'AccountID' => $accountid,
				'Amount' => $amount, 
				'Currency' => $currency, 
			);

			$attributes = array(
			);

			$request = new Trustly_Data_JSONRPCRequest('AccountPayout', $data, $attributes);
			return $this->call($request);
	}

	public function p2p($notificationurl, $enduserid, $messageid, $ip,
            $authorizeonly=NULL, $templatedata=NULL, $successurl=NULL,
			$method=NULL, $lastname=NULL, $firstname=NULL, $urltarget=NULL,
			$locale=NULL, $amount=NULL, $currency=NULL, $templateurl=NULL,
			$displaycurrency=NULL) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid
			);

			$authorizeonly = $this->apiBool($authorizeonly);

			$attributes = array(
				'AuthorizeOnly' => $authorizeonly,
				'TemplateData' => $templatedata,
				'SuccessURL' => $successurl,
				'Method' => $method,
				'Lastname' => $lastname,
				'Firstname' => $firstname,
				'URLTarget' => $urltarget,
				'Locale' => $locale,
				'Amount' => $amount,
				'TemplateURL' => $templateurl,
				'Currency' => $currency,
				'DisplayCurrency' => $displaycurrency,
				'IP' => $ip
			);

			$request = new Trustly_Data_JSONRPCRequest('P2P', $data, $attributes);
			return $this->call($request);
			}

	public function capture($orderid, $amount, $currency) {

			$data = array(
				'OrderID' => $orderid,
				'Amount' => $amount,
				'Currency' => $currency
			);

			$attributes = array(
			);

			$request = new Trustly_Data_JSONRPCRequest('Capture', $data, $attributes);
			return $this->call($request);
	}

	public function void($orderid) {

			$data = array(
				'OrderID' => $orderid
			);

			$attributes = array(
			);

			$request = new Trustly_Data_JSONRPCRequest('Void', $data, $attributes);
			return $this->call($request);
	}

	public function hello() {
			# The hello call is not signed, use an unsigned API to do the request and then void it
		$api = new Trustly_Api_Unsigned($this->api_username, $this->api_password, $this->api_host, $this->api_port, $this->api_is_https);
		return $api->hello();
	}
}
