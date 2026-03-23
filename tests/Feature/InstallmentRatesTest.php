<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Sipay\Message\InstallmentRatesRequest;
use Omnipay\Sipay\Message\InstallmentRatesResponse;
use Omnipay\Sipay\Tests\TestCase;

class InstallmentRatesTest extends TestCase
{
	/**
	 * @throws \JsonException
	 */
	public function test_installment_rates_request()
	{
		$options = file_get_contents(__DIR__ . '/../Mock/InstallmentRatesRequest.json');

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new InstallmentRatesRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertEquals('TRY', $data['currency_code']);
	}

	public function test_installment_rates_request_validation_error()
	{
		$options = file_get_contents(__DIR__ . '/../Mock/InstallmentRatesRequest-ValidationError.json');

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new InstallmentRatesRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$this->expectException(InvalidRequestException::class);

		$request->getData();
	}

	public function test_installment_rates_response_success()
	{
		$httpResponse = $this->getMockHttpResponse('InstallmentRatesResponseSuccess.txt');

		$response = new InstallmentRatesResponse($this->getMockRequest(), $httpResponse);

		$this->assertTrue($response->isSuccessful());
		$this->assertEquals('100', $response->getCode());
		$this->assertEquals('Success', $response->getMessage());

		$data = $response->getData();
		$this->assertCount(3, $data['data']);
		$this->assertEquals(1, $data['data'][0]['installments_number']);
		$this->assertEquals(2, $data['data'][1]['installments_number']);
		$this->assertEquals(3, $data['data'][2]['installments_number']);
	}

	public function test_installment_rates_response_api_error()
	{
		$httpResponse = $this->getMockHttpResponse('InstallmentRatesResponseApiError.txt');

		$response = new InstallmentRatesResponse($this->getMockRequest(), $httpResponse);

		$this->assertFalse($response->isSuccessful());
		$this->assertEquals('0', $response->getCode());
		$this->assertEquals('Invalid currency code.', $response->getMessage());
	}
}
