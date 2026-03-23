<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Sipay\Message\BinLookupRequest;
use Omnipay\Sipay\Message\CompletePurchaseRequest;
use Omnipay\Sipay\Message\InstallmentRatesRequest;
use Omnipay\Sipay\Message\PurchaseRequest;
use Omnipay\Sipay\Message\RefundRequest;
use Omnipay\Sipay\Message\VoidRequest;
use Omnipay\Sipay\Tests\TestCase;

class GatewayTest extends TestCase
{
    public function test_gateway_name()
    {
        $this->assertEquals('Sipay', $this->gateway->getName());
    }

    public function test_gateway_default_parameters()
    {
        $defaults = $this->gateway->getDefaultParameters();

        $this->assertArrayHasKey('appId', $defaults);
        $this->assertArrayHasKey('appSecret', $defaults);
        $this->assertArrayHasKey('merchantKey', $defaults);
        $this->assertArrayHasKey('installment', $defaults);
        $this->assertArrayHasKey('secure', $defaults);
        $this->assertArrayHasKey('clientIp', $defaults);
    }

    public function test_gateway_setters_getters()
    {
        $this->gateway->setAppId('test_app_id');
        $this->assertEquals('test_app_id', $this->gateway->getAppId());

        $this->gateway->setAppSecret('test_app_secret');
        $this->assertEquals('test_app_secret', $this->gateway->getAppSecret());

        $this->gateway->setMerchantKey('test_merchant_key');
        $this->assertEquals('test_merchant_key', $this->gateway->getMerchantKey());

        $this->gateway->setInstallment(3);
        $this->assertEquals(3, $this->gateway->getInstallment());

        $this->gateway->setSecure(true);
        $this->assertTrue($this->gateway->getSecure());
    }

    public function test_purchase_returns_purchase_request()
    {
        $request = $this->gateway->purchase([]);

        $this->assertInstanceOf(PurchaseRequest::class, $request);
    }

    public function test_complete_purchase_returns_complete_purchase_request()
    {
        $request = $this->gateway->completePurchase([]);

        $this->assertInstanceOf(CompletePurchaseRequest::class, $request);
    }

    public function test_void_returns_void_request()
    {
        $request = $this->gateway->void([]);

        $this->assertInstanceOf(VoidRequest::class, $request);
    }

    public function test_refund_returns_refund_request()
    {
        $request = $this->gateway->refund([]);

        $this->assertInstanceOf(RefundRequest::class, $request);
    }

    public function test_bin_lookup_returns_bin_lookup_request()
    {
        $request = $this->gateway->binLookup([]);

        $this->assertInstanceOf(BinLookupRequest::class, $request);
    }

    public function test_installment_rates_returns_installment_rates_request()
    {
        $request = $this->gateway->installmentRates([]);

        $this->assertInstanceOf(InstallmentRatesRequest::class, $request);
    }
}
