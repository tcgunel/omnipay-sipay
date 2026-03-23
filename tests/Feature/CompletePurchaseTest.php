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
            'hash_key' => Helper::generateHashKey(
                '250.50',
                3,
                'TRY',
                '$2y$10$test.merchant.key',
                'INV-20230101-002',
                'app_secret_456'
            ),
        ]);

        $data = $request->getData();

        $this->assertEquals(100, $data['status_code']);
        $this->assertEquals('INV-20230101-002', $data['invoice_id']);
    }

    public function test_complete_purchase_response_success()
    {
        $hashKey = Helper::generateHashKey(
            '250.50',
            3,
            'TRY',
            '$2y$10$test.merchant.key',
            'INV-20230101-002',
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
        $hashKey = Helper::generateHashKey(
            '250.50',
            3,
            'TRY',
            '$2y$10$test.merchant.key',
            'INV-20230101-002',
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
}
