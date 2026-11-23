<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Sipay\Gateway;
use Omnipay\Sipay\Helpers\Helper;
use Omnipay\Sipay\Message\CheckStatusRequest;
use Omnipay\Sipay\Message\CheckStatusResponse;
use Omnipay\Sipay\Tests\TestCase;

class CheckStatusTest extends TestCase
{
    public function test_fetch_transaction_returns_a_check_status_request()
    {
        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());

        $this->assertInstanceOf(CheckStatusRequest::class, $gateway->fetchTransaction(['transactionId' => 'INV-1']));
    }

    public function test_check_status_request()
    {
        $request = new CheckStatusRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'appId' => 'app_id_123',
            'appSecret' => 'app_secret_456',
            'merchantKey' => '$2y$10$test.merchant.key',
            'transactionId' => 'INV-20230101-001',
        ]);

        $data = $request->getData();

        $this->assertSame('INV-20230101-001', $data['invoice_id']);
        $this->assertSame('$2y$10$test.merchant.key', $data['merchant_key']);
        $this->assertTrue($data['include_pending_status']);
        $this->assertSame('Y', $data['show_refund_info']);
        $this->assertArrayNotHasKey('app_secret', $data);

        // invoice_id|merchant_key, the layout QNBpay accepted live.
        $this->assertSame(
            'INV-20230101-001|$2y$10$test.merchant.key',
            Helper::decryptHashKey($data['hash_key'], 'app_secret_456')
        );
    }

    public function test_check_status_request_needs_the_invoice()
    {
        $request = new CheckStatusRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'appId' => 'app_id_123',
            'appSecret' => 'app_secret_456',
            'merchantKey' => '$2y$10$test.merchant.key',
        ]);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    /**
     * QNBpay\'s live answer for a sale its team refunded by hand after a 101.
     */
    public function test_a_refunded_sale_reports_the_total_refunded()
    {
        $response = new CheckStatusResponse($this->getMockRequest(), $this->getMockHttpResponse('CheckStatusResponseRefunded.txt'));

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('REFUNDED', $response->getTransactionStatus());
        $this->assertTrue($response->isRefunded());
        $this->assertSame(1891.73, $response->getTotalRefundedAmount());
        $this->assertSame(1891.73, $response->getTransactionAmount());
        $this->assertSame('63931046657184002596401', $response->getTransactionReference());
    }

    public function test_a_completed_sale_has_nothing_refunded()
    {
        $response = new CheckStatusResponse($this->getMockRequest(), $this->getMockHttpResponse('CheckStatusResponseCompleted.txt'));

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('COMPLETED', $response->getTransactionStatus());
        $this->assertFalse($response->isRefunded());
        $this->assertSame(0.0, $response->getTotalRefundedAmount());
    }

    public function test_a_failed_lookup_is_not_successful()
    {
        $response = new CheckStatusResponse($this->getMockRequest(), $this->getMockHttpResponse('CheckStatusResponseApiError.txt'));

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('68', $response->getCode());
        $this->assertSame('Invalid hash key', $response->getMessage());
        $this->assertNull($response->getTransactionStatus());
    }
}
