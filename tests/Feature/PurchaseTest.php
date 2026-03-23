<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Sipay\Constants\TransactionType;
use Omnipay\Sipay\Message\PurchaseRequest;
use Omnipay\Sipay\Message\PurchaseResponse;
use Omnipay\Sipay\Tests\TestCase;

class PurchaseTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function test_purchase_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('Example User', $data['cc_holder_name']);
        $this->assertEquals('9792030394440796', $data['cc_no']);
        $this->assertEquals('12', $data['expiry_month']);
        $this->assertEquals('2099', $data['expiry_year']);
        $this->assertEquals('000', $data['cvv']);
        $this->assertEquals('TRY', $data['currency_code']);
        $this->assertEquals(1, $data['installments_number']);
        $this->assertEquals('INV-20230101-001', $data['invoice_id']);
        $this->assertEquals('100.00', $data['total']);
        $this->assertEquals('$2y$10$test.merchant.key', $data['merchant_key']);
        $this->assertEquals(TransactionType::AUTH, $data['transaction_type']);
        $this->assertNotEmpty($data['hash_key']);
        $this->assertArrayNotHasKey('return_url', $data);
        $this->assertArrayNotHasKey('cancel_url', $data);
    }

    /**
     * @throws \JsonException
     */
    public function test_purchase_3d_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest-3D.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('250.50', $data['total']);
        $this->assertEquals(3, $data['installments_number']);
        $this->assertEquals('TRY', $data['currency_code']);
        $this->assertNotEmpty($data['hash_key']);

        // 3D specific params
        $this->assertEquals('POST', $data['response_method']);
        $this->assertEquals('app', $data['payment_completed_by']);
        $this->assertEquals('192.168.1.1', $data['ip']);
        $this->assertEquals('https://example.com/payment-success', $data['return_url']);
        $this->assertEquals('https://example.com/payment-failure', $data['cancel_url']);
    }

    public function test_purchase_request_validation_error()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest-ValidationError.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_purchase_response_success()
    {
        $httpResponse = $this->getMockHttpResponse('PurchaseResponseSuccess.txt');

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertEquals('100', $response->getCode());
        $this->assertEquals('Success', $response->getMessage());
        $this->assertEquals('AUTH123456', $response->getTransactionId());
        $this->assertEquals('INV-20230101-001', $response->getTransactionReference());
    }

    public function test_purchase_response_api_error()
    {
        $httpResponse = $this->getMockHttpResponse('PurchaseResponseApiError.txt');

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertEquals('0', $response->getCode());
        $this->assertEquals('Transaction failed. Invalid card number.', $response->getMessage());
    }

    public function test_purchase_response_3d_redirect()
    {
        $httpResponse = $this->getMockHttpResponse('PurchaseResponse3D.txt');

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertNotNull($response->getRedirectHtml());
        $this->assertStringContainsString('<form', $response->getRedirectHtml());
    }
}
