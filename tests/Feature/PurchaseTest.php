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
     * QNBPay's API is .NET and binds expiry_month/expiry_year to System.String.
     * Omnipay's CreditCard hands back ints, which JSON-encode as bare numbers
     * and fail model binding there with "The JSON value could not be converted
     * to System.String" - and then, confusingly, "The request field is
     * required." for the whole body.
     *
     * @throws \JsonException
     */
    public function test_expiry_date_is_sent_as_a_zero_padded_string()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/PurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $options['card']['expiryMonth'] = 1;
        $options['card']['expiryYear'] = 2028;

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertSame('01', $data['expiry_month']);
        $this->assertSame('2028', $data['expiry_year']);
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

    /**
     * What paySmart3D actually answers with: not JSON at all, but a complete
     * self-submitting HTML page. Before this was recognised the caller saw a
     * non-redirect response, voided the order and dumped the bank's page into
     * the checkout as if it were an error message.
     */
    public function test_raw_html_3d_page_is_a_redirect()
    {
        $httpResponse = $this->getMockHttpResponse('PurchaseResponse3D-Html.txt');

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('acs.bank.com', $response->getRedirectHtml());
        $this->assertStringContainsString(
            'acs.bank.com',
            $response->getRedirectResponse()->getContent()
        );
    }

    /**
     * Sipay wraps refusals in the same shape, posting the reason back to the
     * merchant's own cancel_url. That is still a redirect: the order has to
     * survive long enough for the failure page to read status_description off
     * the POST.
     */
    public function test_raw_html_refusal_is_also_a_redirect()
    {
        $httpResponse = $this->getMockHttpResponse('PurchaseResponse3D-HtmlError.txt');

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('payment-failure', $response->getRedirectResponse()->getContent());
    }

    /**
     * A plain-text body with no form is not a redirect - falling back to the
     * parent would try to build one out of an empty URL.
     */
    public function test_non_html_body_is_not_a_redirect()
    {
        $body = 'Service Unavailable';

        $raw = "HTTP/1.1 503 Service Unavailable\r\nContent-Type: text/plain\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;

        file_put_contents($file = sys_get_temp_dir() . '/SipayPlainText.txt', $raw);

        $httpResponse = \GuzzleHttp\Psr7\Message::parseResponse(file_get_contents($file));

        $response = new PurchaseResponse($this->getMockRequest(), $httpResponse);

        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectHtml());

        unlink($file);
    }
}
