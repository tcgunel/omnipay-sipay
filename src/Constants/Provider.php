<?php

namespace Omnipay\Sipay\Constants;

use InvalidArgumentException;

class Provider
{
	const SIPAY = 'sipay';
	const HALKODE = 'halkode';
	const IQMONEY = 'iqmoney';
	const PAROLAPARA = 'parolapara';
	const PAYBULL = 'paybull';
	const QNBPAY = 'qnbpay';
	const VEPARA = 'vepara';

	const PROVIDERS = [
		self::SIPAY => [
			'test' => 'https://provisioning.sipay.com.tr/ccpayment',
			'live' => 'https://app.sipay.com.tr/ccpayment',
		],
		self::HALKODE => [
			'test' => 'https://testapp.halkode.com.tr/ccpayment',
			'live' => 'https://app.halkode.com.tr/ccpayment',
		],
		self::IQMONEY => [
			'test' => 'https://provisioning.iqmoneytr.com/ccpayment',
			'live' => 'https://app.iqmoneytr.com/ccpayment',
		],
		self::PAROLAPARA => [
			'test' => 'https://testccpayment.parolapara.com/ccpayment',
			'live' => 'https://ccpayment.parolapara.com/ccpayment',
		],
		self::PAYBULL => [
			'test' => 'https://test.paybull.com/ccpayment',
			'live' => 'https://app.paybull.com/ccpayment',
		],
		self::QNBPAY => [
			'test' => 'https://test.qnbpay.com.tr/ccpayment',
			'live' => 'https://portal.qnbpay.com.tr/ccpayment',
		],
		self::VEPARA => [
			'test' => 'https://test.vepara.com.tr/ccpayment',
			'live' => 'https://app.vepara.com.tr/ccpayment',
		],
	];

	/**
	 * Get the base URL for a provider and mode.
	 *
	 * @param string $provider
	 * @param bool $testMode
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function getBaseUrl(string $provider, bool $testMode): string
	{
		$provider = strtolower($provider);

		if (!isset(self::PROVIDERS[$provider])) {
			throw new InvalidArgumentException(
				"Invalid provider '{$provider}'. Valid providers: " . implode(', ', array_keys(self::PROVIDERS))
			);
		}

		return self::PROVIDERS[$provider][$testMode ? 'test' : 'live'];
	}

	/**
	 * Check if a provider name is valid.
	 *
	 * @param string $provider
	 * @return bool
	 */
	public static function isValid(string $provider): bool
	{
		return isset(self::PROVIDERS[strtolower($provider)]);
	}

	/**
	 * Get all valid provider names.
	 *
	 * @return array
	 */
	public static function all(): array
	{
		return array_keys(self::PROVIDERS);
	}
}
