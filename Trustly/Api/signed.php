<?php
/**
 * Trustly_Api_Signed class.
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
 * Communication class for communicating with the Trustly signed API. This is
 * the API used for all payments, deposits and refunds.
 */
class Trustly_Api_Signed extends Trustly_Api {
	/**
	 * Loaded merchant private key resource
	 * @var resource from openssl with the loaded privatekey
	 */
	private $merchant_privatekey = null;

	/**
	 * Constructor.
	 *
	 * @param string $merchant_privatekey Either a filename pointing to a File
	 *		containing the merchant private RSA key or the key itself in string
	 *		form as read from the file. The key is used for signing outgoing
	 *		requests. You can leave this blank here and instead use the
	 *		useMerchantPrivateKey()/loadMerchantPrivateKey() function to set a
	 *		key later, but it must be defined before issuing any API calls.
	 *
	 * @param string $username Username for the processing account used at Trustly.
	 *
	 * @param string $password Password for the processing account used at Trustly.
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
	public function __construct($merchant_privatekey, $username, $password, $host='trustly.com', $port=443, $is_https=true) {

		parent::__construct($host, $port, $is_https);

		$this->api_username = $username;
		$this->api_password  = $password;
		if($merchant_privatekey != null) {
			if(strpos($merchant_privatekey, "\n") !== false) {
				if($this->useMerchantPrivateKey($merchant_privatekey) === false) {
					throw new InvalidArgumentException('Cannot use merchant private key');
				}
			} else {
				if($this->loadMerchantPrivateKey($merchant_privatekey) === false) {
					throw new InvalidArgumentException('Cannot load merchant private key file ' . $merchant_privatekey);
				}
			}
		}
	}


	/**
	 * Load up the merchants key for signing data from the supplied filename.
	 * Inializes the internal openssl certificate needed for the signing
	 *
	 * @param string $filename Filename containing the private key to load.
	 *
	 * @return boolean indicating success.
	 */
	public function loadMerchantPrivateKey($filename) {
		$cert = @file_get_contents($filename);
		if($cert === false) {
			return false;
		}
		return $this->useMerchantPrivateKey($cert);
	}


	/**
	 * Use this RSA private key for signing outgoing requests.
	 *
	 * @see https://trustly.com/en/developer/api#/signature
	 *
	 * @param string $cert Loaded private RSA key as a string
	 */
	public function useMerchantPrivateKey($cert) {
		if($cert !== false) {
			$this->merchant_privatekey = openssl_pkey_get_private($cert);
			return true;
		}
		return false;
	}


	/**
	 * Insert a signature into the outgoing request.
	 *
	 * @throws Trustly_SignatureException if private key has not been loaded
	 *		yet or if we for some other reason fail to sign the request.
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Request to sign.
	 */
	public function signMerchantRequest($request) {
		if(!isset($this->merchant_privatekey)) {
			throw new Trustly_SignatureException('No private key has been loaded for signing');
		}

		$method = $request->getMethod();
		if($method === null) {
			$method = '';
		}
		$uuid = $request->getUUID();
		if($uuid === null) {
			$uuid = '';
		}

		$data = $request->getData();

		$serial_data = $method . $uuid . $this->serializeData($data);
		$raw_signature = '';

		$this->clearOpenSSLError();
		if(openssl_sign($serial_data, $raw_signature, $this->merchant_privatekey, OPENSSL_ALGO_SHA1) === true) {
			return base64_encode($raw_signature);
		}

		throw new Trustly_SignatureException('Failed to sign the outgoing merchant request. '. openssl_error_string());
	}


	/**
	 * Callback for populating the outgoing request with the criterias needed
	 * for communication. Username/password as well as a signature.
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Request to be used in the outgoing
	 *		call
	 */
	protected function insertCredentials($request) {
		$request->setData('Username', $this->api_username);
		$request->setData('Password', $this->api_password);

		$signature = $this->signMerchantRequest($request);
		if($signature === false) {
			return false;
		}
		$request->setParam('Signature', $signature);

		return true;
	}


	/**
	 * Callback for handling the response from an API call. Takes
	 * the input data and create an instance of a response object.
	 *
	 * @throws Trustly_SignatureException If the incoming message has an
	 *		invalid signature
	 *
	 * @throws Trustly_DataException If the incoming message fails the sanity
	 *		checks.
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Outgoing request
	 *
	 * @param string $body The body recieved in response to the request
	 *
	 * @param integer $response_code the HTTP response code for the call
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	protected function handleResponse($request, $body, $response_code) {
		$response = new Trustly_Data_JSONRPCSignedResponse($body, $response_code);

		if($this->verifyTrustlySignedResponse($response) !== true) {
			throw new Trustly_SignatureException('Incomming message signature is not valid', $response);
		}

		if($response->getUUID() !== $request->getUUID()) {
			throw new Trustly_DataException('Incoming message is not related to request. UUID mismatch');
		}

		return $response;
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

		$signature = $this->signMerchantRequest($response);
		if($signature === false) {
			return false;
		}
		$response->setSignature($signature);

		return $response;
	}


	/**
	 * Returns the PATH portion of the URL for communicating with the API. The
	 * API endpoint will typically differ with the type of te API we are
	 * communicating with.
	 *
	 * See specific class implementing the call for more information.
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Data to send in the request
	 *
	 * @return string The URL path
	 */
	protected function urlPath($request=null) {
		$url = '/api/1';
		return $url;
	}


	/**
	 * Quirks mode implementation of clearing all pending openssl error messages.
	 */
	private function clearOpenSSLError() {
		/* Not really my favourite part of this library implementation. As
		 * openssl queues error messages a single call to openssl_error_string
		 * after a fail might get another "queued" message from before. And as
		 * there is no way to clear the buffer... we will iterate until we will
		 * get no more errors. Brilliant. */
		while ($err = openssl_error_string());
	}


	/**
	 * Generate a somewhat unique outgoing message id
	 *
	 * @see http://php.net/manual/en/function.uniqid.php#94959
	 *
	 * @return string "UUID v4"
	 */
	protected function generateUUID() {
		/*
		*/
	 	return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		      // 32 bits for "time_low"
		      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
		      // 16 bits for "time_mid"
		      mt_rand(0, 0xffff),
		      // 16 bits for "time_hi_and_version",
		      // four most significant bits holds version number 4
		      mt_rand(0, 0x0fff) | 0x4000,
		      // 16 bits, 8 bits for "clk_seq_hi_res",
		      // 8 bits for "clk_seq_low",
		      // two most significant bits holds zero and one for variant DCE1.1
		      mt_rand(0, 0x3fff) | 0x8000,
		      // 48 bits for "node"
		      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		    );
	}


	/**
	 * Call the API with the prepared request
	 *
	 * @throws Trustly_DataException Upon failure to add all the communication
	 *		parameters to the data or if the incoming data fails the basic
	 *		sanity checks
	 *
	 * @throws Trustly_ConnectionException When failing to communicate with the
	 *		API.
	 *
	 * @throws Trustly_SignatureException If the incoming message has an
	 *		invalid signature
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Outgoing request
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse Response from the API.
	 */
	public function call($request) {
		$uuid = $request->getUUID();
		if($uuid === null) {
			$request->setUUID($this->generateUUID());
		}
		return parent::call($request);
	}


	/**
	 * Call the Deposit API Method.
	 *
	 * Initiates a new deposit by generating a new OrderID and returning the
	 * url where the end-user can complete the deposit.
	 *
	 * @see https://trustly.com/en/developer/api#/deposit
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		payment should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user requesting the withdrawal. Preferably the
	 *		same ID/username as used in the merchant's own backoffice in order
	 *		to simplify for the merchant's support department.
	 *
	 * @param string $messageid Your unique ID for the deposit.
	 *
	 * @param string $locale The end-users localization preference in the
	 *		format [language[_territory]]. Language is the ISO 639-1 code and
	 *		territory the ISO 3166-1-alpha-2 code.
	 *
	 * @param float $amount with exactly two decimals in the currency specified
	 *		by Currency. Do not use this attribute in combination with
	 *		SuggestedMinAmount or SuggestedMaxAmount. Only digits. Use dot (.)
	 *		as decimal separator.
	 *
	 * @param string $currency The currency of the end-user's account in the
	 *		merchant's system.
	 *
	 * @param string $country The ISO 3166-1-alpha-2 code of the end-user's
	 *		country. This will be used for preselecting the correct country for
	 *		the end-user in the iframe.
	 *
	 * @param string $mobilephone The mobile phonenumber to the end-user in
	 *		international format. This is used for KYC and AML routines.
	 *
	 * @param string $firstname The end-user's firstname. Useful for some banks
	 *		for identifying transactions.
	 *
	 * @param string $lastname The end-user's lastname. Useful for some banks
	 *		for identifying transactions.
	 *
	 * @param string $nationalidentificationnumber The end-user's social
	 *		security number / personal number / birth number / etc. Useful for
	 *		some banks for identifying transactions and KYC/AML.
	 *
	 * @param string $shopperstatement The text to show on the end-user's bank
	 *		statement.
	 *
	 * @param string $ip The IP-address of the end-user.
	 *
	 * @param string $successurl The URL to which the end-user should be
	 *		redirected after a successful deposit. Do not put any logic on that
	 *		page since it's not guaranteed that the end-user will in fact visit
	 *		it.
	 *
	 * @param string $failurl The URL to which the end-user should be
	 *		redirected after a failed deposit. Do not put any logic on that
	 *		page since it's not guaranteed that the end-user will in fact visit
	 *		it.
	 *
	 * @param string $templateurl The URL to your template page for the
	 *		checkout process.
	 *
	 * @param string $urltarget The html target/framename of the SuccessURL.
	 *		Only _top, _self and _parent are suported.
	 *
	 * @param float $suggestedminamount The minimum amount the end-user is
	 *		allowed to deposit in the currency specified by Currency. Only
	 *		digits. Use dot (.) as decimal separator.
	 *
	 * @param float $suggestedmaxamount The maximum amount the end-user is
	 *		allowed to deposit in the currency specified by Currency. Only
	 *		digits. Use dot (.) as decimal separator.
	 *
	 * @param string $integrationmodule Version information for your
	 *		integration module. This is for informational purposes only and can
	 *		be useful when troubleshooting problems. Should contain enough
	 *		version information to be useful.
	 *
	 * @param boolean $holdnotifications Do not deliver notifications for this
	 *		order. This is a parameter available when using test.trustly.com
	 *		and can be used for manually delivering notifications to your local
	 *		system during development. Intead you can get you notifications on
	 *		https://test.trustly.com/notifications.html . Never set this in the
	 *		live environment.
	 *
	 * @param string $email The email address of the end user.
	 *
	 * @param string $shippingaddresscountry The ISO 3166-1-alpha-2 code of the
	 *		shipping address country.
	 *		Shipping address should be provided for merchants sending
	 *		physical goods to customers. Use either these separated fields or
	 *		use the $shippingaddress field below if you do not keep separated
	 *		address fields.
	 *
	 * @param string $shippingaddresspostalcode The postal code of the shipping
	 *		address.
	 *
	 * @param string $shippingaddresscity The city of the shipping address.
	 *
	 * @param string $shippingaddressline1 The first line of the shipping
	 *		address field.
	 *
	 * @param string $shippingaddressline2 The second line of the shipping
	 *		address information.
	 *
	 * @param string $shippingaddress The full shipping address. Use either the
	 *		separated fields or use this combined field if you do not keep
	 *		separated address fields.
	 *
	 * @param string $unchangeablenationalidentificationnumber The supplied
	 *		$nationalidentificationnumber should be considered read only information
	 *		and will not be changable by end the enduser. Only valid in Sweden,
	 *		the $nationalidentification number needs to be well formed.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function deposit($notificationurl, $enduserid, $messageid,
		$locale=null, $amount=null, $currency=null, $country=null,
		$mobilephone=null, $firstname=null, $lastname=null,
		$nationalidentificationnumber=null, $shopperstatement=null,
		$ip=null, $successurl=null, $failurl=null, $templateurl=null,
		$urltarget=null, $suggestedminamount=null, $suggestedmaxamount=null,
		$integrationmodule=null, $holdnotifications=null,
		$email=null, $shippingaddresscountry=null,
		$shippingaddresspostalcode=null, $shippingaddresscity=null,
		$shippingaddressline1=null, $shippingaddressline2=null,
		$shippingaddress=null, $unchangeablenationalidentificationnumber=null) {

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
				'IntegrationModule' => $integrationmodule,
				'Email' => $email,
				'ShippingAddressCountry' => $shippingaddresscountry,
				'ShippingAddressPostalcode' => $shippingaddresspostalcode,
				'ShippingAddressCity' => $shippingaddresscity,
				'ShippingAddressLine1' => $shippingaddressline1,
				'ShippingAddressLine2' => $shippingaddressline2,
				'ShippingAddress' => $shippingaddress,
			);

			if($holdnotifications) {
				$attributes['HoldNotifications'] = 1;
			}
			if($unchangeablenationalidentificationnumber) {
				$attributes['UnchangeableNationalIdentificationNumber'] = 1;
			}

			$request = new Trustly_Data_JSONRPCRequest('Deposit', $data, $attributes);
			return $this->call($request);
	}

	/**
	 * Call the Refund API Method.
	 *
	 * Refunds the customer on a previous deposit.
	 *
	 * @see https://trustly.com/en/developer/api#/refund
	 *
	 * @param integer $orderid The OrderID of the initial deposit.
	 *
	 * @param float $amount The amount to refund the customer with exactly two
	 *		decimals. Only digits. Use dot (.) as decimal separator.
	 *
	 * @param string currency The currency of the amount to refund the
	 *		customer.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function refund($orderid, $amount, $currency) {

		$data = array(
			'OrderID' => $orderid,
			'Amount' => $amount,
			'Currency' => $currency,
		);

		$request = new Trustly_Data_JSONRPCRequest('Refund', $data);
		return $this->call($request);
	}

	/**
	 * Call the Withdraw API Method.
	 *
	 * Initiates a new withdrawal returning the url where the end-user can
	 * complete the process.
	 *
	 * @see https://trustly.com/en/developer/api#/withdraw
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		payment should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user requesting the withdrawal. Preferably the
	 *		same ID/username as used in the merchant's own backoffice in order
	 *		to simplify for the merchant's support department.
	 *
	 * @param string $messageid Your unique ID for the withdrawal.
	 *
	 * @param string $locale The end-users localization preference in the
	 *		format [language[_territory]]. Language is the ISO 639-1 code and
	 *		territory the ISO 3166-1-alpha-2 code.
	 *
	 * @param string $currency The currency of the end-user's account in the
	 *		merchant's system.
	 *
	 * @param string $country The ISO 3166-1-alpha-2 code of the end-user's
	 *		country. This will be used for preselecting the correct country for
	 *		the end-user in the iframe.
	 *
	 * @param string $mobilephone The mobile phonenumber to the end-user in
	 *		international format. This is used for KYC and AML routines.
	 *
	 * @param string $firstname The end-user's firstname. Some banks require
	 *		the recipients name.
	 *
	 * @param string $lastname The end-user's lastname. Some banks require the
	 *		recipients name.
	 *
	 * @param string $nationalidentificationnumber The end-user's social
	 *		security number / personal number / birth number / etc. Useful for
	 *		some banks for identifying transactions and KYC/AML.
	 *
	 * @param string $clearinghouse The clearing house of the end-user's bank
	 *		account. Typically the name of a country in uppercase but might
	 *		also be IBAN. This will be used to automatically fill-in the
	 *		withdrawal form for the end-user.
	 *
	 * @param string $banknumber The bank number identifying the end-user's
	 *		bank in the given clearing house. This will be used to
	 *		automatically fill-in the withdrawal form for the end-user.
	 *
	 * @param string $accountnumber The account number, identifying the
	 *		end-user's account in the bank. This will be used to automatically
	 *		fill-in the withdrawal form for the end-user. If using Spanish
	 *		Banks, send full IBAN number in this attribute.
	 *
	 * @param boolean $holdnotifications Do not deliver notifications for this
	 *		order. This is a parameter available when using test.trustly.com
	 *		and can be used for manually delivering notifications to your local
	 *		system during development. Intead you can get you notifications on
	 *		https://test.trustly.com/notifications.html
	 *
	 * @param string $email The email address of the end user.
	 *
	 * @param string $dateofbirth The ISO 8601 date of birth of the end user.
	 *
	 * @param string $addresscountry The ISO 3166-1-alpha-2 code of the
	 *		account holders country.
	 *		Use either these separated fields or use the $address field below
	 *		if you do not keep separated address fields.
	 *
	 * @param string $addresspostalcode The postal code of the account holder
	 *		address.
	 *
	 * @param string $addresscity The city of the account holder address.
	 *
	 * @param string $addressline1 The first line of the account holder
	 *		address field.
	 *
	 * @param string $addressline2 The second line of the account holder
	 *		address information.
	 *
	 * @param string $address The account holders address
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function withdraw($notificationurl, $enduserid, $messageid,
		$locale=null, $currency=null, $country=null,
		$mobilephone=null, $firstname=null, $lastname=null,
		$nationalidentificationnumber=null, $clearinghouse=null,
		$banknumber=null, $accountnumber=null, $holdnotifications=null,
		$email=null, $dateofbirth=null,
		$addresscountry=null, $addresspostalcode=null,
		$addresscity=null, $addressline1=null,
		$addressline2=null, $address=null) {

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
				'Email' => $email,
				'DateOfBirth' => $dateofbirth,
				'AddressCountry' => $addresscountry,
				'AddressPostalcode' => $addresspostalcode,
				'AddressCity' => $addresscity,
				'AddressLine1' => $addressline1,
				'AddressLine2' => $addressline2,
				'Address' => $address,
			);

			if($holdnotifications) {
				$attributes['HoldNotifications'] = 1;
			}

			$request = new Trustly_Data_JSONRPCRequest('Withdraw', $data, $attributes);
			return $this->call($request);
	}

	/**
	 * Call the approveWithdrawal API Method.
	 *
	 * Approves a withdrawal prepared by the user. Please contact your
	 * integration manager at Trustly if you want to enable automatic approval
	 * of the withdrawals.
	 *
	 * @see https://trustly.com/en/developer/api#/approvwwithdrawal
	 *
	 * @param integer $orderid The OrderID of the withdrawal to approve.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function approveWithdrawal($orderid) {

		$data = array(
			'OrderID' => $orderid,
		);

		$request = new Trustly_Data_JSONRPCRequest('ApproveWithdrawal', $data);
		return $this->call($request);
	}

	/**
	 * Call the denyWithdrawal API Method.
	 *
	 * Denies a withdrawal prepared by the user. Please contact your
	 * integration manager at Trustly if you want to enable automatic approval
	 * of the withdrawals
	 *
	 * @see https://trustly.com/en/developer/api#/denywithdrawal
	 *
	 * @param integer $orderid The OrderID of the withdrawal to deny.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function denyWithdrawal($orderid) {

		$data = array(
			'OrderID' => $orderid,
		);

		$request = new Trustly_Data_JSONRPCRequest('DenyWithdrawal', $data);
		return $this->call($request);
	}

	/**
	 * Call the selectAccount API Method.
	 *
	 * Initiates a new order where the end-user can select and verify one of
	 * his/her bank accounts.
	 *
	 * @see https://trustly.com/en/developer/api#/selectaccount
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		order should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user to be identified. Preferably the same
	 *		ID/username as used in the merchant's own backoffice in order to
	 *		simplify for the merchant's support department.
	 *
	 * @param string $messageid Your unique ID for the account selection order.
	 *		Each order you create must have an unique MessageID.
	 *
	 * @param string $locale The end-users localization preference in the
	 *		format [language[_territory]]. Language is the ISO 639-1 code and
	 *		territory the ISO 3166-1-alpha-2 code.
	 *
	 * @param string $country The ISO 3166-1-alpha-2 code of the end-user's
	 *		country. This will be used for preselecting the correct country for
	 *		the end-user in the iframe.
	 *
	 * @param string $ip The IP-address of the end-user.
	 *
	 * @param string $successurl The URL to which the end-user should be
	 *		redirected after he/she has completed the initial identification
	 *		process. Do not put any logic on that page since it's not
	 *		guaranteed that the end-user will in fact visit it.
	 *
	 * @param string $urltarget The html target/framename of the SuccessURL. Only _top, _self and _parent are suported.
	 *
	 * @param string $mobilephone The mobile phonenumber to the end-user in international format.
	 *
	 * @param string $firstname The end-user's firstname.
	 *
	 * @param string $lastname The end-user's lastname.
	 *
	 * @param boolean $holdnotifications Do not deliver notifications for this
	 *		order. This is a parameter available when using test.trustly.com
	 *		and can be used for manually delivering notifications to your local
	 *		system during development. Intead you can get you notifications on
	 *		https://test.trustly.com/notifications.html
	 *
	 * @param string $email The email address of the end user.
	 *
	 * @param string $dateofbirth The ISO 8601 date of birth of the end user.
	 *
	 * @param boolean $requestdirectdebitmandate Initiate a direct debit
	 *		mandate request for the selected account.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function selectAccount($notificationurl, $enduserid, $messageid,
		$locale=null, $country=null, $ip=null, $successurl=null, $urltarget=null,
		$mobilephone=null, $firstname=null, $lastname=null, $holdnotifications=null,
		$email=null, $dateofbirth=null, $requestdirectdebitmandate=null) {

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
				'Email' => $email,
				'DateOfBirth' => $dateofbirth,
			);

			if($holdnotifications) {
				$attributes['HoldNotifications'] = 1;
			}
			if($requestdirectdebitmandate) {
				$attributes['RequestDirectDebitMandate'] = 1;
			}

			$request = new Trustly_Data_JSONRPCRequest('SelectAccount', $data, $attributes);
			return $this->call($request);
	}

	/**
	 * Call the registerAccount API Method.
	 *
	 * Registers and verifies the format of an account to be used in
	 * AccountPayout.
	 *
	 * @see https://trustly.com/en/developer/api#/registeraccount
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user holding this account. Preferably the same
	 *		ID/username as used in the merchant's own backoffice in order to
	 *		simplify for the merchant's support department.
	 *
	 * @param string $clearinghouse The clearing house of the end-user's bank
	 *		account. Typically the name of a country in uppercase but might
	 *		also be IBAN.
	 *
	 * @param string $banknumber The bank number identifying the end-user's
	 *		bank in the given clearing house.
	 *
	 * @param string $accountnumber The account number, identifying the
	 *		end-user's account in the bank.
	 *
	 * @param string $firstname The account holders firstname.
	 *
	 * @param string $lastname The account holders lastname.
	 *
	 * @param string $mobilephone The mobile phonenumber to the account holder
	 *		in international format. This is used for KYC and AML routines.
	 *
	 * @param string $email The email address of the end user.
	 *
	 * @param string $dateofbirth The ISO 8601 date of birth of the end user.
	 *
	 * @param string $nationalidentificationnumber The account holder's social
	 *		security number / personal number / birth number / etc. Useful for
	 *		some banks for identifying transactions and KYC/AML.
	 *
	 * @param string $addresscountry The ISO 3166-1-alpha-2 code of the
	 *		account holders country.
	 *		Use either these separated fields or use the $address field below
	 *		if you do not keep separated address fields.
	 *
	 * @param string $addresspostalcode The postal code of the account holder
	 *		address.
	 *
	 * @param string $addresscity The city of the account holder address.
	 *
	 * @param string $addressline1 The first line of the account holder
	 *		address field.
	 *
	 * @param string $addressline2 The second line of the account holder
	 *		address information.
	 *
	 * @param string $address The account holders address
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function registerAccount($enduserid, $clearinghouse, $banknumber,
		$accountnumber, $firstname, $lastname, $mobilephone=null,
		$nationalidentificationnumber=null, $address=null,
		$email=null, $dateofbirth=null,
		$addresscountry=null, $addresspostalcode=null,
		$addresscity=null, $addressline1=null,
		$addressline2=null) {

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
				'Email' => $email,
				'DateOfBirth' => $dateofbirth,
				'AddressCountry' => $addresscountry,
				'AddressPostalcode' => $addresspostalcode,
				'AddressCity' => $addresscity,
				'AddressLine1' => $addressline1,
				'AddressLine2' => $addressline2,
				'Address' => $address,
			);

			$request = new Trustly_Data_JSONRPCRequest('RegisterAccount', $data, $attributes);
			return $this->call($request);
	}

	/**
	 * Call the accountPayout API Method.
	 *
	 * Creates a payout to a specific AccountID. You get the AccountID from the
	 * account notification which is sent after a SelectAccount order has been
	 * completed.
	 *
	 * @see https://trustly.com/en/developer/api#/accountpayout
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		payment should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $accountid The AccountID received from an account
	 *		notification to which the money shall be sent.
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user requesting the withdrawal. Preferably the
	 *		same ID/username as used in the merchant's own backoffice in order
	 *		to simplify for the merchant's support department.

	 * @param string $messageid Your unique ID for the payout. If the MessageID
	 *		is a previously initiated P2P order then the payout will be
	 *		attached to that P2P order and the amount must be equal to or lower
	 *		than the previously deposited amount.
	 *
	 * @param float $amount The amount to send with exactly two decimals. Only
	 *		digits. Use dot (.) as decimal separator. If the end-user holds a
	 *		balance in the merchant's system then the amount must have been
	 *		deducted from that balance before calling this method.
	 *
	 * @param string $currency The currency of the amount to send.
	 *
	 * @param boolean $holdnotifications Do not deliver notifications for this
	 *		order. This is a parameter available when using test.trustly.com
	 *		and can be used for manually delivering notifications to your local
	 *		system during development. Intead you can get you notifications on
	 *		https://test.trustly.com/notifications.html
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function accountPayout($notificationurl, $accountid, $enduserid,
		$messageid, $amount, $currency, $holdnotifications=null) {

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

			if($holdnotifications) {
				$attributes['HoldNotifications'] = 1;
			}

			$request = new Trustly_Data_JSONRPCRequest('AccountPayout', $data, $attributes);
			return $this->call($request);
	}


	/**
	 * Call the P2P API Method.
	 *
	 * Initiates a new P2P transfer by generating a new OrderID and returning
	 * an URL to which the end-user should be redirected.
	 *
	 * @see https://trustly.com/en/developer/api#/p2p
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		payment should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user requesting the withdrawal. Preferably the
	 *		same ID/username as used in the merchant's own backoffice in order
	 *		to simplify for the merchant's support department.
	 *
	 * @param string $messageid Your unique ID for the deposit.
	 *
	 * @param string $locale The end-users localization preference in the
	 *		format [language[_territory]]. Language is the ISO 639-1 code and
	 *		territory the ISO 3166-1-alpha-2 code.
	 *
	 * @param float $amount with exactly two decimals in the currency specified
	 *		by Currency. Do not use this attribute in combination with
	 *		SuggestedMinAmount or SuggestedMaxAmount. Only digits. Use dot (.)
	 *		as decimal separator.
	 *
	 * @param string $currency The currency of the end-user's account in the
	 *		merchant's system.
	 *
	 * @param string $country The ISO 3166-1-alpha-2 code of the end-user's
	 *		country. This will be used for preselecting the correct country for
	 *		the end-user in the iframe.
	 *
	 * @param string $mobilephone The mobile phonenumber to the end-user in
	 *		international format. This is used for KYC and AML routines.
	 *
	 * @param string $firstname The end-user's firstname. Useful for some banks
	 *		for identifying transactions.
	 *
	 * @param string $lastname The end-user's lastname. Useful for some banks
	 *		for identifying transactions.
	 *
	 * @param string $nationalidentificationnumber The end-user's social
	 *		security number / personal number / birth number / etc. Useful for
	 *		some banks for identifying transactions and KYC/AML.
	 *
	 * @param string $shopperstatement The text to show on the end-user's bank
	 *		statement.
	 *
	 * @param string $ip The IP-address of the end-user.
	 *
	 * @param string $successurl The URL to which the end-user should be
	 *		redirected after a successful deposit. Do not put any logic on that
	 *		page since it's not guaranteed that the end-user will in fact visit
	 *		it.
	 *
	 * @param string $failurl The URL to which the end-user should be
	 *		redirected after a failed deposit. Do not put any logic on that
	 *		page since it's not guaranteed that the end-user will in fact visit
	 *		it.
	 *
	 * @param string $templateurl The URL to your template page for the
	 *		checkout process.
	 *
	 * @param string $urltarget The html target/framename of the SuccessURL.
	 *		Only _top, _self and _parent are suported.
	 *
	 * @param float $suggestedminamount The minimum amount the end-user is
	 *		allowed to deposit in the currency specified by Currency. Only
	 *		digits. Use dot (.) as decimal separator.
	 *
	 * @param float $suggestedmaxamount The maximum amount the end-user is
	 *		allowed to deposit in the currency specified by Currency. Only
	 *		digits. Use dot (.) as decimal separator.
	 *
	 * @param string $integrationmodule Version information for your
	 *		integration module. This is for informational purposes only and can
	 *		be useful when troubleshooting problems. Should contain enough
	 *		version information to be useful.
	 *
	 * @param boolean $holdnotifications Do not deliver notifications for this
	 *		order. This is a parameter available when using test.trustly.com
	 *		and can be used for manually delivering notifications to your local
	 *		system during development. Intead you can get you notifications on
	 *		https://test.trustly.com/notifications.html
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function p2p($notificationurl,$enduserid, $messageid,
		$locale=null, $amount=null, $currency=null, $country=null,
		$mobilephone=null, $firstname=null, $lastname=null,
		$nationalidentificationnumber=null, $shopperstatement=null,
		$ip=null, $successurl=null, $failurl=null, $templateurl=null,
		$urltarget=null, $suggestedminamount=null, $suggestedmaxamount=null,
		$integrationmodule=null, $holdnotifications=null,
		$authorizeonly=null, $templatedata=null) {

			$data = array(
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid
			);

			$attributes = array(
				'AuthorizeOnly' => $this->apiBool($authorizeonly),
				'TemplateData' => $templatedata,

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

			if($holdnotifications) {
				$attributes['HoldNotifications'] = 1;
			}

			$request = new Trustly_Data_JSONRPCRequest('P2P', $data, $attributes);
			return $this->call($request);
	}


	/**
	 * Call the Capture API Method.
	 *
	 * Captures a previously completed Deposit where
	 * Deposit.Attributes.AuthorizeOnly was set to 1
	 *
	 * @see https://trustly.com/en/developer/api#/capture
	 *
	 * @param integer $orderid The OrderID of the deposit to capture
	 *
	 * @param integer $amount The amount to capture with exactly two decimals.
	 *		Only digits. Use dot (.) as decimal separator. The amount must be
	 *		less than or equal to the authorized amount. If not specified, the
	 *		authorized amount will be captured.
	 *
	 * @param integer $currency The currency of the amount
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
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


	/**
	 * Call the Void API Method.
	 *
	 * Voids a previously completed Deposit where Deposit.Attributes.AuthorizeOnly was set to 1.
	 *
	 * @see https://trustly.com/en/developer/api#/void
	 *
	 * @param integer $orderid The OrderID of the deposit to void
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function void($orderid) {

			$data = array(
				'OrderID' => $orderid
			);

			$attributes = array(
			);

			$request = new Trustly_Data_JSONRPCRequest('Void', $data, $attributes);
			return $this->call($request);
	}


	/**
	 * Call the Charge API Method.
	 *
	 * Initiates a new drect debit charge.
	 *
	 * @see https://trustly.com/en/developer/api#/charge
	 *
	 * @param string $accountid The AccountID received from an account
	 *		notification with granted direct debit mandate from which the money 
	 *		should be sent.
	 *
	 * @param string $notificationurl The URL to which notifications for this
	 *		payment should be sent to. This URL should be hard to guess and not
	 *		contain a ? ("question mark").
	 *
	 * @param string $enduserid ID, username, hash or anything uniquely
	 *		identifying the end-user requesting the withdrawal. Preferably the
	 *		same ID/username as used in the merchant's own backoffice in order
	 *		to simplify for the merchant's support department.
	 *
	 * @param string $messageid Your unique ID for the charge.
	 *
	 * @param float $amount with exactly two decimals in the currency specified
	 *		by Currency. Only digits. Use dot (.) as decimal separator.
	 *
	 * @param string $currency The currency of the end-user's account in the
	 *		merchant's system.
	 *
	 * @param string $shopperstatement The text to show on the end-user's bank
	 *		statement.
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function charge($accountid, $notificationurl, $enduserid, $messageid,
		$amount, $currency, $shopperstatement=null) {

			$data = array(
				'AccountID' => $accountid,
				'NotificationURL' => $notificationurl,
				'EndUserID' => $enduserid,
				'MessageID' => $messageid,
				'Amount' => $amount,
				'Currency' => $currency,
			);

			$attributes = array(
				'ShopperStatement' => $shopperstatement,
			);

			$request = new Trustly_Data_JSONRPCRequest('Charge', $data, $attributes);
			return $this->call($request);
	}

	/**
	 * Call the getWithdrawals API Method.
	 *
	 * Get a list of withdrawals from an executed order.
	 *
	 * @see https://trustly.com/en/developer/api#/getwithdrawals
	 *
	 * @param integer $orderid The OrderID of the order to query
	 *
	 * @return Trustly_Data_JSONRPCSignedResponse
	 */
	public function getWithdrawals($orderid) {

		$data = array(
			'OrderID' => $orderid,
		);

		$request = new Trustly_Data_JSONRPCRequest('getWithdrawals', $data);
		return $this->call($request);
	}


	/**
	 * Basic communication test to the API.
	 *
	 * @return Trustly_Data_JSONRPCResponse Response from the API
	 */
	public function hello() {
		/* The hello call is not signed, use an unsigned API to do the request and then void it */
		$api = new Trustly_Api_Unsigned($this->api_username, $this->api_password, $this->api_host, $this->api_port, $this->api_is_https);
		return $api->hello();
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
