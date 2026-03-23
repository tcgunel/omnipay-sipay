<?php

namespace Omnipay\Sipay\Tests\Feature;

use InvalidArgumentException;
use Omnipay\Sipay\Constants\Provider;
use Omnipay\Sipay\Message\PurchaseRequest;
use Omnipay\Sipay\Tests\TestCase;

class ProviderTest extends TestCase
{
    // ---------------------------------------------------------------
    // Provider constants class
    // ---------------------------------------------------------------

    public function test_provider_constants_has_all_providers()
    {
        $expected = ['sipay', 'halkode', 'iqmoney', 'parolapara', 'paybull', 'qnbpay', 'vepara'];

        $this->assertEquals($expected, Provider::all());
    }

    public function test_provider_is_valid_returns_true_for_known_providers()
    {
        $this->assertTrue(Provider::isValid('sipay'));
        $this->assertTrue(Provider::isValid('halkode'));
        $this->assertTrue(Provider::isValid('SIPAY')); // case insensitive
        $this->assertTrue(Provider::isValid('Paybull'));
    }

    public function test_provider_is_valid_returns_false_for_unknown()
    {
        $this->assertFalse(Provider::isValid('nonexistent'));
    }

    /**
     * @dataProvider providerUrlDataProvider
     */
    public function test_provider_get_base_url(string $provider, bool $testMode, string $expectedUrl)
    {
        $this->assertEquals($expectedUrl, Provider::getBaseUrl($provider, $testMode));
    }

    public function providerUrlDataProvider(): array
    {
        return [
            // sipay
            ['sipay', true, 'https://provisioning.sipay.com.tr/ccpayment'],
            ['sipay', false, 'https://app.sipay.com.tr/ccpayment'],
            // halkode
            ['halkode', true, 'https://testapp.halkode.com.tr/ccpayment'],
            ['halkode', false, 'https://app.halkode.com.tr/ccpayment'],
            // iqmoney
            ['iqmoney', true, 'https://provisioning.iqmoneytr.com/ccpayment'],
            ['iqmoney', false, 'https://app.iqmoneytr.com/ccpayment'],
            // parolapara
            ['parolapara', true, 'https://testccpayment.parolapara.com/ccpayment'],
            ['parolapara', false, 'https://ccpayment.parolapara.com/ccpayment'],
            // paybull
            ['paybull', true, 'https://test.paybull.com/ccpayment'],
            ['paybull', false, 'https://app.paybull.com/ccpayment'],
            // qnbpay
            ['qnbpay', true, 'https://test.qnbpay.com.tr/ccpayment'],
            ['qnbpay', false, 'https://portal.qnbpay.com.tr/ccpayment'],
            // vepara
            ['vepara', true, 'https://test.vepara.com.tr/ccpayment'],
            ['vepara', false, 'https://app.vepara.com.tr/ccpayment'],
            // case insensitive
            ['SIPAY', true, 'https://provisioning.sipay.com.tr/ccpayment'],
            ['Paybull', false, 'https://app.paybull.com/ccpayment'],
        ];
    }

    public function test_provider_get_base_url_throws_for_invalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid provider 'nonexistent'");

        Provider::getBaseUrl('nonexistent', true);
    }

    // ---------------------------------------------------------------
    // Gateway provider parameter
    // ---------------------------------------------------------------

    public function test_gateway_default_provider_is_sipay()
    {
        $defaults = $this->gateway->getDefaultParameters();

        $this->assertArrayHasKey('provider', $defaults);
        $this->assertEquals('sipay', $defaults['provider']);
    }

    public function test_gateway_get_set_provider()
    {
        $this->gateway->setProvider('halkode');
        $this->assertEquals('halkode', $this->gateway->getProvider());

        $this->gateway->setProvider('vepara');
        $this->assertEquals('vepara', $this->gateway->getProvider());
    }

    public function test_gateway_provider_defaults_to_sipay()
    {
        // Without explicitly setting provider, it should default to sipay
        $this->assertEquals('sipay', $this->gateway->getProvider());
    }

    // ---------------------------------------------------------------
    // Request base endpoint resolves via provider
    // ---------------------------------------------------------------

    public function test_request_uses_sipay_test_endpoint_by_default()
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'provider' => 'sipay',
            'testMode' => true,
        ]);

        $reflection = new \ReflectionMethod($request, 'getBaseEndpoint');
        $reflection->setAccessible(true);

        $this->assertEquals('https://provisioning.sipay.com.tr/ccpayment', $reflection->invoke($request));
    }

    public function test_request_uses_sipay_live_endpoint_by_default()
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'provider' => 'sipay',
            'testMode' => false,
        ]);

        $reflection = new \ReflectionMethod($request, 'getBaseEndpoint');
        $reflection->setAccessible(true);

        $this->assertEquals('https://app.sipay.com.tr/ccpayment', $reflection->invoke($request));
    }

    /**
     * @dataProvider providerEndpointDataProvider
     */
    public function test_request_resolves_correct_endpoint_per_provider(
        string $provider,
        bool $testMode,
        string $expectedBaseUrl
    ) {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'provider' => $provider,
            'testMode' => $testMode,
        ]);

        $reflection = new \ReflectionMethod($request, 'getBaseEndpoint');
        $reflection->setAccessible(true);

        $this->assertEquals($expectedBaseUrl, $reflection->invoke($request));
    }

    public function providerEndpointDataProvider(): array
    {
        return [
            ['halkode', true, 'https://testapp.halkode.com.tr/ccpayment'],
            ['halkode', false, 'https://app.halkode.com.tr/ccpayment'],
            ['paybull', true, 'https://test.paybull.com/ccpayment'],
            ['paybull', false, 'https://app.paybull.com/ccpayment'],
            ['qnbpay', true, 'https://test.qnbpay.com.tr/ccpayment'],
            ['qnbpay', false, 'https://portal.qnbpay.com.tr/ccpayment'],
            ['vepara', true, 'https://test.vepara.com.tr/ccpayment'],
            ['vepara', false, 'https://app.vepara.com.tr/ccpayment'],
            ['iqmoney', true, 'https://provisioning.iqmoneytr.com/ccpayment'],
            ['iqmoney', false, 'https://app.iqmoneytr.com/ccpayment'],
            ['parolapara', true, 'https://testccpayment.parolapara.com/ccpayment'],
            ['parolapara', false, 'https://ccpayment.parolapara.com/ccpayment'],
        ];
    }

    public function test_request_without_provider_defaults_to_sipay()
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'testMode' => true,
        ]);

        $reflection = new \ReflectionMethod($request, 'getBaseEndpoint');
        $reflection->setAccessible(true);

        // Even without setting provider, it should default to sipay
        $this->assertEquals('https://provisioning.sipay.com.tr/ccpayment', $reflection->invoke($request));
    }

    public function test_gateway_passes_provider_to_request()
    {
        $this->gateway->setProvider('paybull');
        $this->gateway->setTestMode(true);

        $request = $this->gateway->purchase([]);

        $this->assertEquals('paybull', $request->getProvider());
    }
}
