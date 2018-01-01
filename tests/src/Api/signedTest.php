<?php

namespace Trustly\Tests\Unit\Api;

use PHPUnit_Framework_TestCase;
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
     */
    public function testDeposit()
    {
        // Prepare / Mock
        $notificationurl = '';
        $enduserid = '';
        $messageid = '';
        $testOpenSSLKey = file_get_contents(self::PRIVATE_KEY_PATH);

        $this->testObject->useMerchantPrivateKey($testOpenSSLKey);

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
