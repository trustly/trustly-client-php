<?php
/**
 * Main include file for working with the trustly-client-php code. This file
 * contains to code, instead it includes all of the files needed for
 * communicating with the API.
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

require_once('Trustly/exceptions.php');
require_once('Trustly/Data/data.php');
require_once('Trustly/Data/request.php');
require_once('Trustly/Data/jsonrpcrequest.php');
require_once('Trustly/Data/response.php');
require_once('Trustly/Data/jsonrpcresponse.php');
require_once('Trustly/Data/jsonrpcsignedresponse.php');
require_once('Trustly/Data/jsonrpcnotificationrequest.php');
require_once('Trustly/Data/jsonrpcnotificationresponse.php');

require_once('Trustly/Api/api.php');
require_once('Trustly/Api/signed.php');
require_once('Trustly/Api/unsigned.php');

/* vim: set noet cindent sts=4 ts=4 sw=4: */
