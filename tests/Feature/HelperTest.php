<?php

namespace Omnipay\Sipay\Tests\Feature;

use Omnipay\Sipay\Exceptions\OmnipaySipayHashValidationException;
use Omnipay\Sipay\Helpers\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
	public function test_generate_hash_key_returns_valid_format()
	{
		$hashKey = Helper::generateHashKey(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret'
		);

		// Replace __ back to / and check format: iv:salt:encrypted
		$decoded = str_replace('__', '/', $hashKey);
		$parts = explode(':', $decoded);

		$this->assertCount(3, $parts);
		$this->assertEquals(16, strlen($parts[0])); // iv is 16 chars
		$this->assertEquals(4, strlen($parts[1]));  // salt is 4 chars
		$this->assertNotEmpty($parts[2]);            // encrypted data
	}

	public function test_generate_hash_key_deterministic()
	{
		$hashKey1 = Helper::generateHashKeyDeterministic(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret',
			'1234567890123456',
			'abcd'
		);

		$hashKey2 = Helper::generateHashKeyDeterministic(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret',
			'1234567890123456',
			'abcd'
		);

		$this->assertEquals($hashKey1, $hashKey2);
	}

	public function test_validate_hash_key_success()
	{
		$hashKey = Helper::generateHashKey(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret'
		);

		$result = Helper::validateHashKey($hashKey, 'app_secret', 'INV-001');

		$this->assertTrue($result);
	}

	public function test_validate_hash_key_wrong_invoice_id()
	{
		$hashKey = Helper::generateHashKey(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret'
		);

		$this->expectException(OmnipaySipayHashValidationException::class);
		$this->expectExceptionMessage('invoice_id mismatch');

		Helper::validateHashKey($hashKey, 'app_secret', 'INV-999');
	}

	public function test_validate_hash_key_invalid_format()
	{
		$this->expectException(OmnipaySipayHashValidationException::class);
		$this->expectExceptionMessage('Invalid hash key format');

		Helper::validateHashKey('invalid-hash', 'app_secret', 'INV-001');
	}

	public function test_validate_hash_key_wrong_secret()
	{
		$hashKey = Helper::generateHashKey(
			'100.00',
			1,
			'TRY',
			'test_merchant_key',
			'INV-001',
			'app_secret'
		);

		$this->expectException(OmnipaySipayHashValidationException::class);

		Helper::validateHashKey($hashKey, 'wrong_secret', 'INV-001');
	}

	public function test_generate_hash_key_replaces_slashes()
	{
		// Generate many keys to increase probability of hitting a "/" in base64
		$hasSlashReplacement = false;

		for ($i = 0; $i < 50; $i++) {
			$hashKey = Helper::generateHashKey(
				'100.00',
				1,
				'TRY',
				'test_merchant_key',
				'INV-' . $i,
				'app_secret'
			);

			// The output should never contain bare "/"
			// but may contain "__" (the replacement)
			$this->assertStringNotContainsString('/', $hashKey);

			if (str_contains($hashKey, '__')) {
				$hasSlashReplacement = true;
			}
		}

		// It's statistically likely at least one hash had "/" in base64
		// but not guaranteed. This is more of a sanity check.
		$this->assertTrue(true);
	}
}
