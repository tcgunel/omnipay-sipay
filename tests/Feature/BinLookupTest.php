<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Sipay\Constants\CardType;
use Omnipay\Sipay\Message\BinLookupRequest;
use Omnipay\Sipay\Message\BinLookupResponse;
use Omnipay\Sipay\Tests\TestCase;

class BinLookupTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function test_bin_lookup_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/BinLookupRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new BinLookupRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertEquals('979203', $data['credit_card']);
        $this->assertEquals('100.00', $data['amount']);
        $this->assertEquals('TRY', $data['currency_code']);
        $this->assertEquals('$2y$10$test.merchant.key', $data['merchant_key']);
    }

    public function test_bin_lookup_request_validation_error()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/BinLookupRequest-ValidationError.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new BinLookupRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_bin_lookup_response_success()
    {
        $httpResponse = $this->getMockHttpResponse('BinLookupResponseSuccess.txt');

        $response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('100', $response->getCode());
        $this->assertEquals('Success', $response->getMessage());

        $data = $response->getData();
        $this->assertNotEmpty($data['data']);

        // card_type is how the card is funded. The scheme has its own field -
        // an earlier fixture here claimed "VISA" for card_type, which the live
        // API never returns.
        $this->assertEquals('CREDIT_CARD', $data['data'][0]['card_type']);
        $this->assertEquals('MASTER_CARD', $data['data'][0]['card_scheme']);
    }

    public function test_a_credit_card_reports_every_installment_the_issuer_allows()
    {
        $httpResponse = $this->getMockHttpResponse('BinLookupResponseSuccess.txt');

        $response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

        $this->assertSame(CardType::CREDIT_CARD, $response->getCardType());
        $this->assertTrue($response->isCreditCard());
        $this->assertFalse($response->isDebitCard());

        // This issuer caps the card at 4; a merchant offering 12 may still only
        // charge these.
        $this->assertSame([1, 2, 3, 4], $response->getInstallmentNumbers());
        $this->assertCount(4, $response->getPosOptions());
    }

    public function test_a_debit_card_is_offered_single_payment_only()
    {
        $httpResponse = $this->getMockHttpResponse('BinLookupResponseDebitCard.txt');

        $response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(CardType::DEBIT_CARD, $response->getCardType());
        $this->assertTrue($response->isDebitCard());
        $this->assertFalse($response->isCreditCard());
        $this->assertSame([1], $response->getInstallmentNumbers());
    }

    public function test_bin_lookup_response_api_error()
    {
        $httpResponse = $this->getMockHttpResponse('BinLookupResponseApiError.txt');

        $response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

        $this->assertFalse($response->isSuccessful());
        $this->assertEquals('0', $response->getCode());
        $this->assertEquals('BIN not found.', $response->getMessage());
    }

    /**
     * A lookup that failed knows nothing about the card, and must not answer as
     * though it did.
     */
    public function test_the_card_accessors_are_empty_when_the_lookup_failed()
    {
        $httpResponse = $this->getMockHttpResponse('BinLookupResponseApiError.txt');

        $response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

        $this->assertSame([], $response->getPosOptions());
        $this->assertNull($response->getCardType());
        $this->assertFalse($response->isCreditCard());
        $this->assertFalse($response->isDebitCard());
        $this->assertSame([], $response->getInstallmentNumbers());
    }
}
