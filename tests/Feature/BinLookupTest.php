<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
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
		$this->assertEquals('VISA', $data['data'][0]['card_type']);
	}

	public function test_bin_lookup_response_api_error()
	{
		$httpResponse = $this->getMockHttpResponse('BinLookupResponseApiError.txt');

		$response = new BinLookupResponse($this->getMockRequest(), $httpResponse);

		$this->assertFalse($response->isSuccessful());
		$this->assertEquals('0', $response->getCode());
		$this->assertEquals('BIN not found.', $response->getMessage());
	}
}
