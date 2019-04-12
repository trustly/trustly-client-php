<?php
/**
 * Trustly_Api_Unsigned class.
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
 * Communication class for communicating with the Trustly signed API. The
 * unsigned API is used for communication with the backoffice and clients among
 * other things.
 */
class Trustly_Api_Unsigned extends Trustly_Api {
	/**
	 * Login username when using the API. Used only in the first API call to
	 * newSessionCookie after which the $session_uuid is used instead.
	 * @var string
	 */
	private $api_username = null;
	/**
	 * Login password when using the API. Used only in the first API call to
	 * newSessionCookie after which the $session_uuid is used instead.
	 * @var string
	 */
	private $api_password = null;
	/**
	 * Session UUID used for authenticating calls.
	 * @var string
	 */
	private $session_uuid = null;


	/**
	 * Constructor.
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
	 **/
	public function __construct($username, $password, $host='trustly.com', $port=443, $is_https=true) {
		parent::__construct($host, $port, $is_https);

		$this->api_username = $username;
		$this->api_password = $password;
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
		return '/api/Legacy';
	}


	/**
	 * Callback for handling the response from an API call. Takes
	 * the input data and create an instance of a response object.
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
	 * @return Trustly_Data_JSONRPCResponse
	 */
	protected function handleResponse($request, $body, $response_code) {
			/* No signature here, just build the response object */
		return new Trustly_Data_JSONRPCResponse($body, $response_code);
	}


	/**
	 * Callback for populating the outgoing request with the criterias needed
	 * for communication.
	 *
	 * @param Trustly_Data_JSONRPCRequest $request Request to be used in the outgoing
	 *		call
	 */
	public function insertCredentials($request) {
		$request->setParam('Username', $this->api_username);
		if(isset($this->session_uuid)) {
			$request->setParam('Password', $this->session_uuid);
		} else {
			$request->setParam('Password', $this->api_password);
		}
		return true;
	}


	/**
	 * Utility function for revealing wether or not we have a valid session UUID set
	 *
	 * @return boolean indicating wether we have a sessionuuid
	 */
	protected function hasSessionUUID() {
		return (bool)isset($this->session_uuid);
	}


	/**
	 * Call NewSessionCookie to obtain a session cookie we can use for the rest
	 * of our calls. This is automatically called when doing a call if we do
	 * not have a session. Call manually if needed at session timeout etc.
	 *
	 * @throws Trustly_AuthentificationException If the supplied credentials
	 *		cannot be used for communicating with the API.
	 *
	 * @return Trustly_Data_JSONRPCResponse Response from the API.
	 */
	public function newSessionCookie() {
		$this->session_uuid = null;

		$request = new Trustly_Data_JSONRPCRequest('NewSessionCookie');
			/* Call parent directly here as we will attempt to detect the
			 * missing session uuid here and call this function if it is not set */
		$response = parent::call($request);

		if(isset($response)) {
			if($response->isSuccess()) {
				$this->session_uuid = $response->getResult('sessionuuid');
			}
		}
		if(!isset($this->session_uuid)) {
			throw new Trustly_AuthentificationException();
		}
		return $response;
	}


	/**
	 * Utility wrapper around a call() to GetViewStable to simply getting data
	 * from a view.
	 *
	 * @param string $viewname Name of view
	 *
	 * @param string $dateorder 'OLDER'|'NEVER' or NULL
	 *
	 * @param string $datestamp Order used in relation with $dateorder
	 *
	 * @param array $filterkeys Array of arrays of filters to apply to the data.
	 *		Arrays in the array consists of 1. Key name, 2. Key value, 3.
	 *		Operator, 4. Key value 2. Operator is one of 'NOT', 'BETWEEN' (in
	 *		which case Key value 2 must be set), 'LIKE', 'DECRYPTED', 'IN'
	 *		,'NOT IN' or 'NULL'
	 *
	 * @param integer $limit Limit the number of records to fetch
	 *
	 * @param integer $offset Skip these many records in the start of the request
	 *
	 * @param string $params Parameters for the view
	 *
	 * @param string $sortby Column to sort by
	 *
	 * @param string $sortorder Sort order ASC or DESC
	 *
	 * @return Trustly_Data_JSONRPCResponse Response from the API.
	 *
	 */
	public function getViewStable($viewname, $dateorder=null, $datestamp=null,
		$filterkeys=null, $limit=100, $offset=0, $params=null, $sortby=null,
		$sortorder=null) {

		return $this->call('GetViewStable', array(
			'DateOrder' => $dateorder,
			'Datestamp' => $datestamp,
			'FilterKeys' => $filterkeys,
			'Limit' => $limit,
			'Offset' => $offset,
			'Params' => $params,
			'SortBy' => $sortby,
			'SortOrder' => $sortorder,
			'ViewName' => $viewname,
		));
	}


	/**
	 * Issue an unsigned API call. As the unsigned API contains a huge array of
	 * functions we will use the call() method directly for the majority of
	 * operations. The data in params will be matched into the parameters of
	 * the outgoing call. Take care when supplying the arguments for the call
	 * so they match the function prototype properly.
	 *
	 * @param string $method API method to call
	 *
	 * @param Trustly_Data_JSONRPCRequest $params Outgoing call params
	 *
	 * @return Trustly_Data_JSONRPCResponse Response from the API.
	 */
	public function call($method, $params=null)  {
		$request = new Trustly_Data_JSONRPCRequest($method);

		if(isset($params)) {
			foreach($params as $key => $value) {
				$request->setParam($key, $value);
			}
		}

		if(!$this->hasSessionUUID()) {
			$this->newSessionCookie();
		}

		return parent::call($request);
	}


	/**
	 * Basic communication test to the API.
	 *
	 * @return Trustly_Data_JSONRPCResponse Response from the API
	 */
	public function hello() {
		$request = new Trustly_Data_JSONRPCRequest('Hello');
			/* Call parent directly here we never want to get a new session
			 * uuid for just this single call, if we have it use it, but
			 * otherwise just live happliy */
		$response = parent::call($request);

		return $response;
	}
}
/* vim: set noet cindent sts=4 ts=4 sw=4: */
