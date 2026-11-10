<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Sipay\Helpers\Helper;
use Omnipay\Sipay\Message\CompletePurchaseRequest;
use Omnipay\Sipay\Message\CompletePurchaseResponse;
use Omnipay\Sipay\Tests\TestCase;

class CompletePurchaseTest extends TestCase
{
    public function test_complete_purchase_request()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');

        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        // Simulate the 3D callback POST data
        $this->getHttpRequest()->request->replace([
            'status_code' => 100,
            'status_description' => 'Success',
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'ORD-002',
            'hash_key' => Helper::generateCallbackHashKey(
                '1',
                '250.50',
                'INV-20230101-002',
                'ORD-002',
                'TRY',
                'app_secret_456'
            ),
        ]);

        $data = $request->getData();

        $this->assertEquals(100, $data['status_code']);
        $this->assertEquals('INV-20230101-002', $data['invoice_id']);
    }

    public function test_complete_purchase_response_success()
    {
        $hashKey = Helper::generateCallbackHashKey(
            '1',
            '250.50',
            'INV-20230101-002',
            'ORD-002',
            'TRY',
            'app_secret_456'
        );

        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');
        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($options);

        $data = [
            'status_code' => 100,
            'status_description' => 'Success',
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'ORD-002',
            'hash_key' => $hashKey,
        ];

        $response = new CompletePurchaseResponse($request, $data);

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isHashValid());
        $this->assertEquals('100', $response->getCode());
        $this->assertEquals('Success', $response->getMessage());
        $this->assertEquals('ORD-002', $response->getTransactionId());
        $this->assertEquals('INV-20230101-002', $response->getTransactionReference());
    }

    public function test_complete_purchase_response_invalid_hash()
    {
        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');
        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($options);

        $data = [
            'status_code' => 100,
            'status_description' => 'Success',
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'ORD-002',
            'hash_key' => 'invalid_hash_key_value',
        ];

        $response = new CompletePurchaseResponse($request, $data);

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isHashValid());
    }

    public function test_complete_purchase_response_failed_status()
    {
        $hashKey = Helper::generateCallbackHashKey(
            '1',
            '250.50',
            'INV-20230101-002',
            'ORD-002',
            'TRY',
            'app_secret_456'
        );

        $options = file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json');
        $options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($options);

        $data = [
            'status_code' => 0,
            'status_description' => 'Transaction failed.',
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'ORD-002',
            'hash_key' => $hashKey,
        ];

        $response = new CompletePurchaseResponse($request, $data);

        $this->assertFalse($response->isSuccessful());
    }

    /**
     * The regression that cost a real, charged payment: a callback hash carries
     *   status|total|invoice_id|order_id|currency_code
     * and was being read with the request's layout, which put the currency code
     * where the invoice id was expected. Every approved 3D payment came back as
     * a decline, and the caller voided the order the bank had already charged.
     */
    public function test_a_callback_hash_is_not_read_with_the_request_layout()
    {
        $callbackHash = Helper::generateCallbackHashKey(
            '1',
            '1412.00',
            '6aa3d20689242',
            '63924728630942896449049',
            'TRY',
            'app_secret_456'
        );

        $this->assertSame(
            '1|1412.00|6aa3d20689242|63924728630942896449049|TRY',
            Helper::decryptHashKey($callbackHash, 'app_secret_456')
        );

        $decoded = Helper::validateCallbackHashKey($callbackHash, 'app_secret_456', '6aa3d20689242');

        $this->assertSame('6aa3d20689242', $decoded['invoice_id']);
        $this->assertSame('1412.00', $decoded['total']);
        $this->assertSame('63924728630942896449049', $decoded['order_id']);
    }

    /**
     * The POST body is attacker-reachable and the hash is not, so where both
     * name the same field they have to agree.
     */
    public function test_a_posted_order_id_that_contradicts_the_hash_is_rejected()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($options);

        $response = new CompletePurchaseResponse($request, [
            'status_code' => 100,
            'status_description' => 'Success',
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'SOMEONE-ELSES-ORDER',
            'hash_key' => Helper::generateCallbackHashKey(
                '1',
                '250.50',
                'INV-20230101-002',
                'ORD-002',
                'TRY',
                'app_secret_456'
            ),
        ]);

        $this->assertFalse($response->isHashValid());
        $this->assertFalse($response->isSuccessful());
    }

    /**
     * A hash naming a different invoice must not settle this one.
     */
    public function test_another_orders_callback_cannot_be_replayed()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($options);

        $response = new CompletePurchaseResponse($request, [
            'status_code' => 100,
            'invoice_id' => 'INV-20230101-002',
            'order_id' => 'ORD-002',
            'hash_key' => Helper::generateCallbackHashKey(
                '1',
                '250.50',
                'SOME-OTHER-INVOICE',
                'ORD-002',
                'TRY',
                'app_secret_456'
            ),
        ]);

        $this->assertFalse($response->isHashValid());
        $this->assertNull($response->getCallbackData());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function expectingRequest(array $extra = []): CompletePurchaseRequest
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(array_merge($options, $extra));

        return $request;
    }

    /**
     * The genuine callback of a cheap order (INV-CHEAP, 1.00).
     *
     * @return array<string, mixed>
     */
    private function cheapCallback(): array
    {
        return [
            'status_code' => 100,
            'status_description' => 'Success',
            'invoice_id' => 'INV-CHEAP',
            'order_id' => 'ORD-CHEAP',
            'hash_key' => Helper::generateCallbackHashKey('1', '1.00', 'INV-CHEAP', 'ORD-CHEAP', 'TRY', 'app_secret_456'),
        ];
    }

    public function test_genuine_callback_matching_the_expected_invoice_and_amount_succeeds()
    {
        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-CHEAP', 'amount' => '1.00']),
            $this->cheapCallback()
        );

        $this->assertTrue($response->isHashValid());
        $this->assertTrue($response->isSuccessful());
    }

    public function test_a_cheap_orders_callback_cannot_settle_another_expected_invoice()
    {
        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-EXPENSIVE', 'amount' => '1.00']),
            $this->cheapCallback()
        );

        $this->assertFalse($response->isHashValid());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_the_expected_invoice_wins_even_when_posted_fields_are_stripped()
    {
        $data = $this->cheapCallback();
        unset($data['invoice_id'], $data['order_id']);

        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-EXPENSIVE', 'amount' => '1.00']),
            $data
        );

        $this->assertFalse($response->isSuccessful());
    }

    public function test_a_callback_for_a_different_amount_than_expected_is_rejected()
    {
        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-CHEAP', 'amount' => '1412.00']),
            $this->cheapCallback()
        );

        $this->assertFalse($response->isHashValid());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_amounts_are_compared_in_minor_units()
    {
        $data = $this->cheapCallback();
        $data['hash_key'] = Helper::generateCallbackHashKey('1', '1412.1', 'INV-CHEAP', 'ORD-CHEAP', 'TRY', 'app_secret_456');

        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-CHEAP', 'amount' => '1412.10']),
            $data
        );

        $this->assertTrue($response->isSuccessful());
    }

    public function test_a_missing_hash_key_is_not_successful()
    {
        $data = $this->cheapCallback();
        unset($data['hash_key']);

        $response = new CompletePurchaseResponse(
            $this->expectingRequest(['transactionId' => 'INV-CHEAP', 'amount' => '1.00']),
            $data
        );

        $this->assertFalse($response->isHashValid());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_a_missing_app_secret_is_not_successful()
    {
        $request = $this->expectingRequest(['transactionId' => 'INV-CHEAP', 'amount' => '1.00']);
        $request->setAppSecret('');

        $response = new CompletePurchaseResponse($request, $this->cheapCallback());

        $this->assertFalse($response->isSuccessful());
    }

    public function test_legacy_call_without_expectations_still_accepts_a_genuine_callback()
    {
        $data = $this->cheapCallback();
        $data['total'] = '1.00';

        $response = new CompletePurchaseResponse($this->expectingRequest(), $data);

        $this->assertTrue($response->isHashValid());
        $this->assertTrue($response->isSuccessful());
    }

    public function test_legacy_call_rejects_a_posted_total_that_contradicts_the_hash()
    {
        $data = $this->cheapCallback();
        $data['total'] = '1412.00';

        $response = new CompletePurchaseResponse($this->expectingRequest(), $data);

        $this->assertFalse($response->isSuccessful());
    }
}
