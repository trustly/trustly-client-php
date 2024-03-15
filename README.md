Trustly PHP Client
=====================

This is an example implementation of communication with the Trustly API using
PHP. It implements the standard Payments API as well as gives stubs for
executing calls against the API used by the backoffice.

For full documentation on the Trustly API internals visit our developer
website: https://eu.developers.trustly.com/. All information about software flows and
call patters can be found on that site. The documentation within this code will
only cover the code itself, not how you use the Trustly API.

This code is provided as-is, use it as inspiration, reference or drop it
directly into your own project and use it.

If you find problem in the code or want to extend it feel free to fork it and send us
a pull request.

This code should work with PHP 5 (>= 5.3.0). PHP modules needed are: bcmath,
openssl, curl, mbstring and json.

Overview
========

The code provided wrappers for calling the trustly API. Create an instance of
the API call with you merchant criterias and use the stubs in that class for
calling the API. The API will default to communicate with https://trustly.com,
override the `host` parameter for the constructor to communicate with
test.trustly.com instead.

When processing an incoming notification the `handleNotification()` method of the
API will help with parsing and verifying the message signature, use `notificationResponse()`
to build a proper response object

The examples below represent a very basic usage of the calls. A minimum of error
handling around this code would be to check for the following exceptions during
processing.

- `Trustly_ConnectionException`

  Thrown when unable to communicate with the Trustly API. This can be due to
  Internet or other forms of service errors.

- `Trustly_DataException`

  Thrown upon various problems with the API returned data. For instance when a
  responding message contains a different UUID then the sent message or when the
  response structure is incomplete.

- `Trustly_SignatureException`

  Issued when the authenticity of messages cannot be verified. If ever this
  exception is caught the data in the communication should be voided as it can be
  a forgery.

Example deposit call
--------------------

    require_once('Trustly.php');

    /* Change 'test.trustly.com' to 'trustly.com' below to use the live environment */
    $api = new Trustly_Api_Signed(
                    $trustly_rsa_private_key,
                    $trustly_username,
                    $trustly_password,
                    'test.trustly.com'
                );

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
                    'trustly-client-php example/1.0',           /* IntegrationModule */
                    FALSE,                                      /* HoldNotifications */
                    'john.doe@example.com',                     /* Email */
                    'SE',                                       /* ShippingAddressCountry */
                    '12345',                                    /* ShippingAddressPostalCode */
                    'ExampleCity',                              /* ShippingAddressCity */
                    '123 Main St',                              /* ShippingAddressLine1 */
                    'C/O Careholder',                           /* ShippingAddressLine2 */
                    NULL                                        /* ShippingAddress */
                );

    $iframe_url= $deposit->getData('url');

Example notification processing
-------------------------------

    $request = $api->handleNotification($notification_body);
        # FIXME Handle the incoming notification data here
    $notifyresponse = $api->notificationResponse($request, TRUE);

    echo $notifyresponse->json();

Example implementation
----------------------

In the example/ subdirectory is a simple implementation of a client that uses
the code to make a deposit call to Trustly and processes incoming
notifications. The code is well commented and contains information about what
to calls that needs to be made and some caveats while doing so.

The code is runnable on Linux/OSX with Apache v2.2/2.4. Use the
`example/example.sh` script to control the example environment. You need to
amend `example/www/php/example.php` and `example/example.private.pem` to
contain your processing account information before giving it a test spin.
