<?php

namespace Trustly\Tests\Unit\Data;

use PHPUnit_Framework_TestCase;
use ReflectionClass;
use Trustly_Data_JSONRPCSignedResponse;

class jsonrpcsignedresponseTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var jsonrpcsignedresponseInterface The object to be tested.
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
        $this->dependencies = [
            'responseBody' => '{
               "version": "1.1",
               "result": {
                   "signature": "asdf-sig",
                   "method": "deposit",
                   "data": {
                       "url": "http://test-url.com",
                       "orderid": "3344323"
                   },
                   "uuid": "8s7d878as7df78"
               }
           }'
        ];

        $this->reflection = new ReflectionClass(Trustly_Data_JSONRPCSignedResponse::class);
        $this->testObject = $this->reflection->newInstanceArgs($this->dependencies);
    }

    /**
     * @expectedException Trustly_DataException
     */
    public function testNoResponse()
    {
        new Trustly_Data_JSONRPCSignedResponse('{}');
    }

    /**
     * Test that the response contains error details.
     */
    public function testResponseWithError()
    {
        $result = new Trustly_Data_JSONRPCSignedResponse('{"version":"1.1","error":{"error":{"data":{"code":616,"message":"ERROR_INVALID_CREDENTIALS"},"signature":"TSlH2N+lwVz38wUG9I12gWut0E3DgwxJxUgBnu0ct9mjN0PG1tCGKQ5Q0nW8QwSHwVi9fx+KOvVzi6gAWmw9VzcFIIHdw1TSTZXDyiwsvpHorsR/A3e2NBUrLE2AWkZTbh8ZM51AFTa/epiwS4nLVlft2q231SNb2Y56wmzLLY/fM603CL7ncZ2PMMMiEvpYMktFNacpQ9stU/UBYyJAH5vC3wfwLkrNGehRcb5ia+7FDqxCAXe1uuVmTq3rvbOZH+AfXUS2TSkxHjgDSLx2h1l/2ulj3x14Qu8+QL8JxNIOLQZSrR+lF4wEgAkZ4IFLkLHIspx/gB2zKOpdpc4/zA==","uuid":"8956f9db-451f-470b-9f96-7ab4c6257d88","method":"Deposit"},"message":"ERROR_INVALID_CREDENTIALS","name":"JSONRPCError","code":616}}');

        self::assertTrue($result->isError());

        self::assertEquals(616, $result->getData('code'));
        self::assertEquals('ERROR_INVALID_CREDENTIALS', $result->getData('message'));
    }

    /**
     * testGetData Test that getData executes as expected.
     */
    public function testGetDataWithoutParams()
    {
        // Execute
        $result = $this->testObject->getData();

        // Assert Result
        self::assertTrue(is_array($result));

        self::assertArrayHasKey('url', $result);
        self::assertArrayHasKey('orderid', $result);

        self::assertEquals('http://test-url.com', $result['url']);
        self::assertEquals(3344323, $result['orderid']);
    }

    /**
     * testGetDataWithParam Test that getDataWithParam executes as expected.
     */
    public function testGetDataWithParamFound()
    {
        // Execute
        $result = $this->testObject->getData('orderid');

        // Assert Result
        self::assertEquals(3344323, $result);
    }

    /**
     * testGetDataWithParamButNotFound Test that getDataWithParamButNotFound executes as expected.
     */
    public function testGetDataWithParamButNotFound()
    {
        // Execute
        $result = $this->testObject->getData('random');

        // Assert Result
        self::assertNull($result);
    }

    /**
     * testGetDataNoResponse Test that getDataNoResponse executes as expected.
     */
    public function testGetDataNoResponse()
    {
        // Prepare / Mock
        $reflectionProperty = $this->reflection->getProperty('response_result');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, []);

        // Execute
        $result = $this->testObject->getData('orderid');

        // Assert Result
        self::assertNull($result);
    }

    /**
     * Test that the getErrorCode works as expected.
     */
    public function testGetErrorCode()
    {
        $reflectionProperty = $this->reflection->getProperty('response_result');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, [
            'data' => [
                'code' => 616
            ]
        ]);

        $reflectionProperty = $this->reflection->getProperty('payload');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, [
            'error' => true
        ]);

        $code = $this->testObject->getErrorCode();

        self::assertEquals(616, $code);
    }

    /**
     * Test that the getErrorCode works as expected.
     */
    public function testGetErrorCodeNoError()
    {
        $reflectionProperty = $this->reflection->getProperty('response_result');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, []);

        $code = $this->testObject->getErrorCode();

        self::assertNull($code);
    }

    /**
     * Test that the getErrorMessage works as expected.
     */
    public function testGetErrorMessage()
    {
        $reflectionProperty = $this->reflection->getProperty('response_result');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, [
            'data' => [
                'message' => 'ERROR_INVALID_CREDENTIALS'
            ]
        ]);

        $reflectionProperty = $this->reflection->getProperty('payload');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, [
            'error' => true
        ]);

        $message = $this->testObject->getErrorMessage();

        self::assertEquals('ERROR_INVALID_CREDENTIALS', $message);
    }

    /**
     * Test that the get error message returns no errors when no response is set.
     */
    public function testGetErrorMessageNoError()
    {
        $reflectionProperty = $this->reflection->getProperty('response_result');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->testObject, []);

        $message = $this->testObject->getErrorMessage();

        self::assertNull($message);
    }
}
