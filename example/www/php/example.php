<?php
/* This is a very simple example of how to use the code in the 
 * trustly-client-php library. This is the backend PHP used to make deposit 
 * calls to Trustly and to respond to incoming notification requests. 
 *
 * This backend is used in two ways, it responds to a numerous of simple AJAX 
 * (GET requests) calls (orders, clear_orders and deposit) with a JSON 
 * structure. 
 *
 * It also handles incoming notifications (notification) where it reads an 
 * incoming POST and responds with data to Trustly. The NotificationURL in the 
 * call to Trustly wil be set to whatever this script will detect itself as 
 * being called as and in order for the processing of notifications to work it 
 * is important that this URL is reachable from the Internet as well.
 */

set_include_path('../../..');
include '../../../Trustly.php';

$order_info_dir = $_SERVER['REGRESSDIR'] . '/var/run/orders';
$base_url = 'http://' . $_SERVER['HTTP_HOST'];

/* This is your merchant processing account information. The username and 
 * password you should receive when signing up for the Trustly service and the 
 * private RSA key should be generated by you, the correspondong public key 
 * should have been communicated with your account manager.
 *
 * To generate your keypair you can use openssl:
 *
 * Private:
 * openssl genrsa -out private.pem 2048
 *
 * Public:
 * openssl rsa -pubout -in private.pem -out public.pem -outform PEM
 *
 * If the key information is wrong your deposit calls will likely be answered 
 * with 636/ERROR_UNABLE_TO_VERIFY_RSA_SIGNATURE. The key in this repository is 
 * just an example key and is not connected to any account.
 * */
$trustly_rsa_private_key = $_SERVER['REGRESSDIR'] . '/example.private.pem';
$trustly_username = 'USERNAME';
$trustly_password = 'PASSWORD';


function _http_response_code($code) {
    if(function_exists('http_response_code')) {
        http_response_code($code);
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code);
    }
}

function respond_json($http_response = 200, $data = NULL) {
    _http_response_code($http_response);
    header('Content-type: application/json; charset=UTF-8');
    print json_encode($data);
}

function check_extensions() {
    /* Just a prerequisite test that all the modules we need is loaded
     * Of course responding in JSON without the JSON extension will be
     * "difficult" so hard code this json structure */

    _http_response_code(200);
    header('Content-type: application/json; charset=UTF-8');

    printf('{"bcmath": %s, "openssl": %s, "curl": %s, "mbstring": %s, "json": %s}',
        (extension_loaded('bcmath')?'true':'false'),
        (extension_loaded('openssl')?'true':'false'),
        (extension_loaded('curl')?'true':'false'),
        (extension_loaded('mbstring')?'true':'false'),
        (extension_loaded('json')?'true':'false')
    );
}

function _orders_sort_orders_callback($a, $b) {
    if(isset($a['created']) && isset($b['created'])) {
        if($a['created'] == $b['created']) {
            return 0;
        }
        return $a['created'] < $b['created'] ? 1 : -1 ;
    } elseif(isset($a['created'])) {
        return 1;
    } elseif(isset($b['created'])) {
        return -1;
    } else {
        if($a['orderid'] == $b['orderid']) {
            return 0;
        }
        return $a['orderid'] < $b['orderid'] ? 1 : -1 ;
    }
}

function orders() {
    global $order_info_dir;

    /* We keep a fairly flat and simple database of all the orders we have 
        * processed in a files. Read all the files and return the JSON data as 
        * is to the client. */
    $orders = Array();
    foreach(glob("$order_info_dir/*.order.json") as $orderfile) {
        error_log($orderfile);
        $json = file_get_contents($orderfile);
        error_log($json === FALSE?'FALSE':$json);
        $data = json_decode($json, true);
        if(isset($data)) {
            array_push($orders, $data);
        }
    }
    # For presentation, sort the orders according to their creation time, 
    # newest first.
    usort($orders, '_orders_sort_orders_callback');
    respond_json(200, Array('result' => 'ok', 'orders' => $orders));
}

function clear_orders() {
    global $order_info_dir;

    /* Simply remove all of the data files with order information. */
    foreach(glob("$order_info_dir/*.order.json") as $orderfile) {
        unlink($orderfile);
    }
    respond_json(200, Array('result' => 'ok'));
}

function save_order_data($orderid, $data) {
    global $order_info_dir;

    /* Save the information to a file containing the order data information.
     *
     * It is VERY likely that there will be notification arriving at (almost) 
     * the same time from Trustly. As most notification processing is 
     * non-atomic make sure to place the appropriate locks on the processing of 
     * data to make sure you do not run into concurrency problems when updating 
     * the order.  */
    $filename = "$order_info_dir/$orderid.order.json";
    $retries = 10;

    while($retries > 0) {
        if(!file_exists($filename)) {
            $fh = fopen($filename, 'c');
            if(!isset($fh)) {
                usleep(10000);
                $retries--;
                continue;
            }
            flock($fh, LOCK_EX);
        } else {
            $fh = fopen($filename, 'r+');
            flock($fh, LOCK_EX);

            $json = fread($fh, filesize($filename));
            $old_data = json_decode($json, true);

            $data = array_merge($old_data, $data);
        }
        $data['orderid'] = $orderid;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return TRUE;
    }
    return FALSE;
}

function get_api() {
    global $trustly_rsa_private_key;
    global $trustly_username;
    global $trustly_password;

    try {
        /* Create a new instance of the signed API. This is the class that 
         * encapsulates all the communication with Trustly. Supply it with your 
         * processing account login criterias and a filename poiting to the 
         * file containing your private key. If you do not have the key in a 
         * file set this parameter to NULL and then use the 
         * useMerchantPrivateKey() method call to set the certificate 
         * information from a string instead.
         *
         * Creating the class is an offline operation, no communication with 
         * Trustly will be made here.
         */
        $api = new Trustly_Api_Signed($trustly_rsa_private_key, $trustly_username, $trustly_password, 'test.trustly.com');
        return $api;
    } catch(InvalidArgumentException $e) {
        /* If there is a problem with your supplied data (problem reading the 
         * key file for instance) you will be rewarded with an 
         * InvalidArgumentException */
        respond_json(200, Array('result' => 'error', 'error' => 'InvalidArgumentException ' . $e->getMessage()));
    } catch(Exception $e) {
        respond_json(200, Array('result' => 'error', 'error' => 'Exception ' . $e->getMessage()));
    }
    return NULL;
}

function deposit() {
    global $base_url;

    $api = get_api();

    if(isset($api)) {
        /* The messageid is the system local identifier for this request. It 
         * must be unique for each call and you can use it to tie this order to 
         * a local equivalent. A common mistake here is to tie this to a 
         * cart-id or similar that will be preserved if the user cancels the 
         * payment and re-selects the trustly method. This can be worked around 
         * by either locally saving the connection between the orderid (from 
         * trustly) and your local identifier or by using the local identifier 
         * in combination with a unique element to build the message id.
         *
         * Here we have no local information so I will simply randomize it. */
        $messageid = substr(md5(microtime()), 0, 16);

        /* Sending in an empty amount will cause the trustly iframe to present 
         * the user with an amount selector dialogue. For e-commerce payments 
         * this is not especially useful, but if you are dealing with an online 
         * wallet this can come in handy */
        $amount = $_GET['amount'];
        if(empty($amount)) {
            $amount = NULL;
        }
        if(isset($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }
        $currency = $_GET['currency'];
        if(empty($currency)) {
            respond_json(200, Array('result' => 'error', 'error' => 'No currency given'));
            return ;
        }

        /* We need to send in the remote client address, this is normally not 
         * as simple as just looking at the REMOTE_ADDR field as this can point 
         * to internal proxies etc. */
        $ip = $_SERVER['REMOTE_ADDR'];
        $ip = preg_replace('/[, ].*/', '', $ip);

        try {
            /* The deposit call is the main work horse here. It will issue a 
             * RPC call to Trustly and start a new payment. It will return an 
             * url to a page we should present to the end user and an orderid 
             * of the newly created order. See 
             * https://trustly.com/en/developer/documents for information about 
             * how the web page should be presented to the end user. 
             *
             * All of the parameters below are document in the API 
             * documentationf or the deposit call visit 
             * https://eu.developers.trustly.com/doc/reference/deposit for information on
             * the specifics of all of the parameters. A few tips below.
             *
             * EndUserID - Make sure this is something unique per enduser in 
             * your system. In an e-commerce system the easiest unique per user 
             * identifier here will be the email address, make sure it is 
             * normalized if this field comes from raw user input. For an 
             * E-Wallet type solution this could be something like the customer 
             * id or similar.
             *
             * Amount/Currency - This is the amount of founds and in the 
             * currecny you are requesting with this call. All notifications on 
             * this order will normally be done in this currency. If the 
             * enduser will deposit money in e different currency we will make 
             * an fx of funds and you will be notified in the correct currency.
             *
             * ShopperStatement - This information will be visible on the end 
             * users account ledger for this deposit. Note that we are severely 
             * limited by the banks on what information we can relay to the end 
             * user. Some banks will only allow numbers, some banks will give 
             * us 5 characets while some banks nothing at all etc. We will make 
             * a best effort to relay your informaiton, keeping it as simple as 
             * possible will increase the chance of it looking as you would 
             * like.
             *
             * IntegrationModule - This is a field identifying the version of 
             * the software generating the request. This is for internal 
             * troubleshooting only. 
             */
            $deposit = $api->deposit(
                "$base_url/php/example.php/notification",   /* NotificationURL */
                'john.doe@example.com',                     /* EndUserID */
                $messageid,                                 /* MessageID */
                'en_US',                                    /* Locale */
                $amount,                                    /* Amount */
                $currency,                                  /* Currency */
                'SE',                                       /* Country */
                NULL,                                       /* MobilePhone */
                'Sam',                                      /* FirstName */
                'Trautman',                                 /* LastName */
                NULL,                                       /* NationalIdentificationNumber */
                NULL,                                       /* ShopperStatement */
                $ip,                                        /* IP */
                "$base_url/success.html",                   /* SuccessURL */
                "$base_url/fail.html",                      /* FailURL */
                NULL,                                       /* TemplateURL */
                NULL,                                       /* URLTarget */
                NULL,                                       /* SuggestedMinAmount */
                NULL,                                       /* SuggestedMaxAmount */
                'trustly-client-php example/1.0'            /* IntegrationModule */
            );

        } catch(Trustly_ConnectionException $e) {
            /* A connection exception can be the result if we are unable to 
             * establish a secure connection to the Trustly servers (failed to 
             * connect or failed to verify the server certificate for instance 
             * */
            respond_json(200, Array('result' => 'error', 'error' => 'Trustly_ConnectionException ' . $a));
        } catch(Trustly_DataException $e) {
            /* A data exception will be thrown if we fail to properly sign the 
             * outgoing request, if the response does not seem related to our 
             * query or if the response data is not in the format we would 
             * expect */
            respond_json(200, Array('result' => 'error', 'error' => 'Trustly_DataException ' . $a));
        } catch(Exception $e) {
            respond_json(200, Array('result' => 'error', 'error' => 'Exception ' .$a));
        }

        if(isset($deposit)) {
            /* isSuccess() or isError() will reveal the outcome of the RPC call */
            if($deposit->isSuccess()) {
                /* Using the getData() method you can access the individual 
                 * fields in the response from Trustly. Without arguments this 
                 * will return all of the data */
                $orderid = $deposit->getData('orderid');

                save_order_data($orderid, Array(
                    'amount' => $amount,
                    'currency' => $currency,
                    'created' => @strftime('%F %T')
                ));
                respond_json(200, Array(
                    'result' => 'ok',
                    'url' => $deposit->getData('url'),
                    'orderid' => $orderid
                    ));
            } else {
                /* getErrorCode() and getErrorMessage() will reveal the problem 
                 * with the call. getErrorCode() will return an integer error 
                 * number identifying the problem at hand, use this for making 
                 * decisions on how to act. getErrorMessage() will return a 
                 * more descriptive text string with error information, this is 
                 * in a form for logging, not something to present to the end 
                 * user.
                 * */
                $errormessage = sprintf('Error: %s (%s)', $deposit->getErrorCode(), $deposit->getErrorMessage());
                respond_json(200, Array('result' => 'error', 'error' => $errormessage));
            }
        }
    }
}

function notification() {
    /* Sanity check, all notifications will be delivered via POST, so accept 
     * nothing else here */
    if($_SERVER['REQUEST_METHOD'] != 'POST') {
        return ;
    }

    /* Read all of the POST data in the incoming request */
    $post_data = file_get_contents('php://input');
    if(!$post_data) {
        return ;
    }

    $api = get_api();
    if(isset($api)) {
        try {
            /* This will process the input data and return a proper 
             * notification object (Trustly_Data_JSONRPCNotificationRequest) if 
             * the input data is valid. Never process a notification if you get 
             * an exception during this stage and never attempt to process it 
             * by not calling handleNotifiction() as you might process a forged 
             * request.
             *
             * This can fail in more then one way:
             * Trustly_SignatureException: 
             *  The incoming request is not properly cryptographically signed. 
             *  This can be a sign of either a fraud attempt (somebody other 
             *  then Trustly is sending you notifications for Trustly orders in 
             *  an attempt to aquire funds) or that Trustly has changed it's 
             *  crpytographic keys (should be an _extremely_ rare occasion). 
             *
             * Trustly_JSONRPCVersionException:
             *  The incoming request is formatted according to a different 
             *  JSONRPC version then this library was written for. This is 
             *  fatal as we do not know how to handle the incoming request in 
             *  this format.
             * */
            $notification = $api->handleNotification($post_data);
        } catch(Trustly_SignatureException $e) {
            error_log('Got incoming notification with bad signature');
            return ;
        } catch(Trustly_JSONRPCVersionException $e) {
            error_log('Got incoming notification with bad JSONRPC version');
            return ;
        }

        if(isset($notification)) {
            /* The method will reveal what type of incoming notification this 
             * is. Depending on the type of notification the contents will 
             * differ. Read more of the different notifications and exakt 
             * contents at https://trustly.com/en/developer/api */
            $method = $notification->getMethod();
            /* The orderid will always be present in the notifications. This 
             * and the 'messageid' parameter is used to connect the order to 
             * your call. OrderID being the Trustly identifier and the 
             * messageid being your identifier */
            $orderid = $notification->getData('orderid');
            /* This contains all the data of the notification. For most 
             * notitications the "interesting" data is within an 
             * "attributes" object in the data. */
            $data  = $notification->getData();


            $data['datestamp'] = @strftime('%F %T');
            save_order_data($orderid, Array(
                $method => $data,
            ));
            /* Once you have acted upon the data in the notification you should 
             * respond in the same HTTP request that you have received it and 
             * processed it. If you processed the request successfully (by 
             * successfully we mean in such a way that you processed the 
             * contents correctly, regardless if the data within the 
             * notification was to your liking or not) then you should respond 
             * to the request.
             *
             * The ONLY case where you should not respond to 
             * the request is when you failed to process the data. If you are 
             * unable to process the notification then either do not respond 
             * with anything or set the success parameter to FALSE in the call 
             * to notificationResponse();
             *
             * Trustly will continue to attempt to deliver this notification 
             * until you respond to it.
             * */
            $response = $api->notificationResponse($notification, TRUE);
            print $response->json();
        }
    }
}


/* Simple dispatcher to the correct function */
$path = $_SERVER['PATH_INFO'];
if($path == '/orders') {
    orders();
} elseif($path == '/clear_orders') {
    clear_orders();
} elseif($path == '/notification') {
    notification();
} elseif($path == '/deposit') {
    deposit();
} elseif($path == '/extensions') {
    check_extensions();
} else {
    _http_response_code(404);
}

exit(0);
