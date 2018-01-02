<?php

namespace Trustly\Tests\Unit\Api;

use GuzzleHttp\Client;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use Trustly_Api_Signed;
use Trustly_Data_JSONRPCSignedResponse;

class signedTest extends PHPUnit_Framework_TestCase
{
    /**
     * @const The path to the private key.
     */
    const PRIVATE_KEY_PATH = __DIR__ . '/../../private.pem';

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
        $filename = self::PRIVATE_KEY_PATH;

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
        $key = file_get_contents(self::PRIVATE_KEY_PATH);

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
        $testOpenSSLKey = file_get_contents(self::PRIVATE_KEY_PATH);

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
        $guzzleResponseMock->expects($this->any())
            ->method('getStatusCode')
            ->willReturn('200');
        $guzzleResponseMock->expects($this->any())
            ->method('getUUID')
            ->willReturn('882772663636666363636');

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['query']);

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
     */
    public function testDepositUUIDMismatch()
    {
        // Prepare / Mock
        $notificationurl = '/notifications/';
        $enduserid = '883736';
        $messageid = '9999';
        $testOpenSSLKey = file_get_contents(self::PRIVATE_KEY_PATH);

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
        $guzzleResponseMock->expects($this->any())
            ->method('getStatusCode')
            ->willReturn('200');
        $guzzleResponseMock->expects($this->any())
            ->method('getUUID')
            ->willReturn('882772663636666363636');

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['query']);

                    return true;
                })
            )
            ->willReturn($guzzleResponseMock);

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
     */
    public function testDeposit()
    {
        // Prepare / Mock
        $notificationurl = '/notifications/';
        $enduserid = '883736';
        $messageid = '9999';
        $testOpenSSLKey = file_get_contents(self::PRIVATE_KEY_PATH);

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
                        "url": "...",
                        "orderid": "..."
                    },
                    "uuid": "..."
                }
            }');

        $guzzleResponseMock = $this->getMock(ResponseInterface::class);
        $guzzleResponseMock->expects($this->any())
            ->method('getBody')
            ->willReturn($streamBodyMock);
        $guzzleResponseMock->expects($this->any())
            ->method('getStatusCode')
            ->willReturn('200');
        $guzzleResponseMock->expects($this->any())
            ->method('getUUID')
            ->willReturn('882772663636666363636');

        $guzzleMock = $this->getMock(Client::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test.trustly.com/api/1',
                $this->callback(function (array $options) {
                    self::assertEquals(['Content-Type' => 'application/json; charset=utf-8'], $options['headers']);
                    self::assertTrue($options['verify']);
                    self::assertRegExp('#{"params":{"Data":{"NotificationURL":"\\\/notifications\\\/","EndUserID":"883736","MessageID":"9999","Username":"testUsername","Password":"testPassword"},"UUID":"[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}","Signature":".+"},"method":"Deposit","version":"1.1"}#', $options['query']);

                    return true;
                })
            )
            ->willReturn($guzzleResponseMock);

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
}
