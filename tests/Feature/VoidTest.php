<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Sipay\Helpers\Helper;
use Omnipay\Sipay\Message\VoidRequest;
use Omnipay\Sipay\Message\VoidResponse;
use Omnipay\Sipay\Tests\TestCase;

class VoidTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function test_void_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/VoidRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new VoidRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('INV-20230101-001', $data['invoice_id']);
        $this->assertEquals(0, $data['amount']);
        $this->assertEquals('app_id_123', $data['app_id']);
        $this->assertEquals('app_secret_456', $data['app_secret']);
        $this->assertEquals('$2y$10$test.merchant.key', $data['merchant_key']);
        $this->assertEquals('', $data['refund_transaction_id']);

        // A void is the refund endpoint's same-day operation: same payload
        // layout, amount forced to 0.
        $this->assertEquals(
            '0|INV-20230101-001|$2y$10$test.merchant.key',
            Helper::decryptHashKey($data['hash_key'], 'app_secret_456')
        );
    }

    public function test_void_request_validation_error()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/VoidRequest-ValidationError.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new VoidRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_void_response_success()
    {
        $httpResponse = $this->getMockHttpResponse('VoidResponseSuccess.txt');

        $response = new VoidResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('100', $response->getCode());
        $this->assertEquals('Cancel successful.', $response->getMessage());
    }

    public function test_void_response_api_error()
    {
        $httpResponse = $this->getMockHttpResponse('VoidResponseApiError.txt');

        $response = new VoidResponse($this->getMockRequest(), $httpResponse);

        $this->assertFalse($response->isSuccessful());
        $this->assertEquals('0', $response->getCode());
        $this->assertEquals('Cancel failed. Transaction not found.', $response->getMessage());
    }
}
