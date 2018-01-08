<?php

namespace Trustly\Api;

// To test the behaviour of the code, we need to control the behaviour of these methods.

/**
 * @override Returns what is set by the test.
 *
 * @param mixed $serial_data
 * @param mixed $raw_signature
 * @param mixed $public_key
 */
function openssl_verify($serial_data, $raw_signature, $public_key)
{
    return \Trustly\Tests\Unit\Api\signedTest::$openssl_verified;
}

/**
 * @override Returns concatenated params.
 *
 * @param mixed $min
 * @param mixed $max
 */
function mt_rand($min, $max)
{
    return \Trustly\Tests\Unit\Api\signedTest::$uuid_fraction;
}

namespace Trustly\Tests\Unit\Api;

use GuzzleHttp\Client;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use Trustly\Api\Trustly_Api_Signed;
use Trustly_Data_JSONRPCSignedResponse;

class signedTest extends PHPUnit_Framework_TestCase
{
    /**
     * Used to mock the behaviour of the openssl verified method in the api class.
     */
    public static $openssl_verified = false;

    /**
     * Used to compute the uuid for signed requests, controlled by tests.
     */
    public static $uuid_fraction;

    /**
     * @const The path to the private key.
     */
    const PRIVATE_KEY_PATH = '/../../private.pem';

    /**
     * @const An invalid private key path used for testing.
     */
    const INVALID_PRIVATE_KEY_PATH = 'invalid.pem';

    /**
     * @var SignedInterface The object to be tested.
     */
    private $testObject;

    /**
     * @var ReflectionClass The reflection class.
     */
    private $reflection;

    /**
     * @var array The test object dependencies.
     */
    private $dependencies = [];

    /**
     * Set up the testing object.
     */
    public function setUp()
    {
        $merchant_privatekey = null;
        $username = 'testUsername';
        $password = 'testPassword';
        $host = 'test.trustly.com';
        $port = 443;
        $is_https = true;

        $this->dependencies = [
            'merchantPrivateKey' => $merchant_privatekey,
            'username' => $username,
            'password' => $password,
            'host' => $host,
            'port' => $port,
            'isHttps' => $is_https
        ];

        $this->reflection = new ReflectionClass(Trustly_Api_Signed::class);
        $this->testObject = $this->reflection->newInstanceArgs($this->dependencies);
    }

    /**
     * testConstruct Test that construct executes as expected.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Cannot load merchant private key file file.key
     */
    public function testConstructPrivateKeyFileException()
    {
        $this->dependencies['merchantPrivateKey'] = 'file.key';

        new Trustly_Api_Signed(
            $this->dependencies['merchantPrivateKey'],
            $this->dependencies['username'],
            $this->dependencies['password']
        );
    }

    /**
     * testConstruct Test that construct executes as expected.
     */
    public function testConstructPrivateKeyString()
    {
        $this->dependencies['merchantPrivateKey'] = "asdfasdfasasdfsdf\nasdfasdfsf";

        $result = new Trustly_Api_Signed(
            $this->dependencies['merchantPrivateKey'],
            $this->dependencies['username'],
            $this->dependencies['password']
        );

        self::assertInstanceOf(Trustly_Api_Signed::class, $result);
    }

    /**
     * testLoadMerchantPrivateKey Test that loadMerchantPrivateKey executes as expected.
     */
    public function testLoadMerchantPrivateKey()
    {
        // Prepare / Mock
        $filename = $this->getPrivateKeyPath();

        // Execute
        $result = $this->testObject->loadMerchantPrivateKey($filename);

        // Assert Result
        self::assertTrue($result);
    }

    /**
     * testLoadMerchantPrivateKey Test that loadMerchantPrivateKey executes as expected.
     */
    public function testLoadMerchantPrivateKeyNonExistant()
    {
        // Prepare / Mock
        $filename = self::INVALID_PRIVATE_KEY_PATH;

        // Execute
        $result = $this->testObject->loadMerchantPrivateKey($filename);

        // Assert Result
        self::assertFalse($result);
    }

    /**
     * testUseMerchantPrivateKey Test that userMerchantPrivateKey executes as expected.
     */
    public function testUseMerchantPrivateKey()
    {
        // Prepare / Mock
        $key = file_get_contents($this->getPrivateKeyPath());

        // Execute
        $result = $this->testObject->useMerchantPrivateKey($key);

        // Assert Result
        self::assertTrue($result);
    }

    /**
     * testUseMerchantPrivateKey Test that userMerchantPrivateKey executes as expected.
     */
    public function testUseMerchantPrivateKeyFalst()
    {
        // Prepare / Mock
        $key = false;

        // Execute
        $result = $this->testObject->useMerchantPrivateKey($key);

        // Assert Result
        self::assertFalse($result);
    }

    /**
     * testDeposit Test that deposit executes as expected.
     *
     * @expectedException Trustly_SignatureException
     */
    public function testDepositSignitureException()
    {
        // Prepare / Mock
        $notificationurl = '';
        $enduserid = '';
        $messageid = '';

        // Execute
        $this->testObject->deposit(
            $notificationurl,
            $enduserid,
            $messageid
        );
    }

    /**
     * testDeposit Test that deposit executes as expected.
     *
     * @expectedException Trustly_SignatureException
     */
    public function testDepositSignatureInvalid()
    {
        // Prepare / Mock
        $notificationurl = '/notifications/';
        $enduserid = '883736';
        $messageid = '9999';
        $testOpenSSLKey = file_get_contents($this->getPrivateKeyPath());

        $this->testObject->useMerchantPrivateKey($testOpenSSLKey);

        $streamBodyMock = $this->getMock(StreamInterface::class);
        $streamBodyMock->expects($this->any())
            ->method('getContents')
            ->willReturn('{
           "version": "1.1",
           "error": {
               "error": {
                   "signature": "...",
                   "data": {
                       "code": 620,
                       "message": "ERROR_UNKNOWN"
                   },
                   "method": "...",
                   "uuid": "..."
               },
               "name": "JSONRPCError",
               "code": 620,
               "message": "ERROR_UNKNOWN"
           }
          }');

        $guzzleResponseMock = $this->getMock(ResponseInterface::class);
        $guzzleResponseMock->expects($this->any())
            ->method('getBody')
            ->willReturn($streamBodyMock);

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['body']);

                    return true;
                })
            )
            ->willReturn($guzzleResponseMock);

        $this->testObject->setGuzzle($guzzleMock);

        // Execute
        $this->testObject->deposit(
            $notificationurl,
            $enduserid,
            $messageid
        );
    }

    /**
     * testDeposit Test that deposit executes as expected.
     *
     * @expectedException Trustly_DataException
     * @expectedExceptionMessage UUID mismatch
     */
    public function testDepositUUIDMismatch()
    {
        // Prepare / Mock
        $notificationurl = '/notifications/';
        $enduserid = '883736';
        $messageid = '9999';
        $testOpenSSLKey = file_get_contents($this->getPrivateKeyPath());

        $this->testObject->useMerchantPrivateKey($testOpenSSLKey);

        $streamBodyMock = $this->getMock(StreamInterface::class);
        $streamBodyMock->expects($this->any())
            ->method('getContents')
            ->willReturn('{
                "version": "1.1",
                "result": {
                    "signature": "valid-signature",
                    "method": "POST",
                    "data": {
                        "url": "http://test-url.com",
                        "orderid": "3888273"
                    },
                    "uuid": "99283828398928392982832"
                }
            }');

        $guzzleResponseMock = $this->getMock(ResponseInterface::class);
        $guzzleResponseMock->expects($this->any())
            ->method('getBody')
            ->willReturn($streamBodyMock);

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['body']);

                    return true;
                })
            )
            ->willReturn($guzzleResponseMock);

        self::$openssl_verified = true;

        $this->testObject->setGuzzle($guzzleMock);

        // Execute
        $result = $this->testObject->deposit(
            $notificationurl,
            $enduserid,
            $messageid
        );

        // Assert Result
        self::assertInstanceOf(Trustly_Data_JSONRPCSignedResponse::class, $result);

        self::assertTrue(is_integer($result->getData('code')));
        self::assertTrue(is_string($result->getData('message')));
    }

    /**
     * testDeposit Test that deposit executes as expected.
     *
     * @group testing
     */
    public function testDeposit()
    {
        // Prepare / Mock
        $notificationurl = '/notifications/';
        $enduserid = '883736';
        $messageid = '9999';
        $testOpenSSLKey = file_get_contents($this->getPrivateKeyPath());

        $this->testObject->useMerchantPrivateKey($testOpenSSLKey);

        $streamBodyMock = $this->getMock(StreamInterface::class);
        $streamBodyMock->expects($this->any())
            ->method('getContents')
            ->willReturn('{
                "version": "1.1",
                "result": {
                    "signature": "...",
                    "method": "...",
                    "data": {
                        "url": "http://test-url.com",
                        "orderid": "34233",
                        "message": "This is a test message"
                    },
                    "uuid": "0d050d05-0d05-4d05-8d05-0d050d050d05"
                }
            }');

        $guzzleResponseMock = $this->getMock(ResponseInterface::class);
        $guzzleResponseMock->expects($this->any())
            ->method('getBody')
            ->willReturn($streamBodyMock);

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['body']);

                    return true;
                })
            )
            ->willReturn($guzzleResponseMock);

        self::$openssl_verified = true;

        // This generates the uuid: 0d050d05-0d05-4d05-8d05-0d050d050d05, use this in the response.
        self::$uuid_fraction = 3333;

        $this->testObject->setGuzzle($guzzleMock);

        // Execute
        $result = $this->testObject->deposit(
            $notificationurl,
            $enduserid,
            $messageid
        );

        // Assert Result
        self::assertInstanceOf(Trustly_Data_JSONRPCSignedResponse::class, $result);
        self::assertEquals('This is a test message', $result->getData('message'));
        self::assertEquals('34233', $result->getData('orderid'));
        self::assertEquals('http://test-url.com', $result->getData('url'));
    }

    /**
     * PHP 5.5 does not support static scalar expressions so back porting.
     *
     * @return string
     */
    private function getPrivateKeyPath()
    {
        return __DIR__ . self::PRIVATE_KEY_PATH;
    }
}
