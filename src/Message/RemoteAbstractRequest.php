<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Sipay\Constants\Provider;
use Omnipay\Sipay\Helpers\Helper;
use Omnipay\Sipay\Traits\PurchaseGettersSetters;

abstract class RemoteAbstractRequest extends AbstractRequest
{
	use PurchaseGettersSetters;

	protected $endpoint = '';

	protected $token = null;

	/**
	 * @throws InvalidRequestException
	 */
	protected function validateSettings(): void
	{
		$this->validate('appId', 'appSecret', 'merchantKey');
	}

	protected function get_card($key)
	{
		return $this->getCard() ? $this->getCard()->$key() : null;
	}

	/**
	 * Resolve the base endpoint depending on provider and test mode.
	 */
	protected function getBaseEndpoint(): string
	{
		$provider = $this->getProvider() ?? Provider::SIPAY;

		return Provider::getBaseUrl($provider, (bool) $this->getTestMode());
	}

	/**
	 * Obtain a Bearer token from the Sipay API.
	 *
	 * @throws \Exception
	 */
	protected function fetchToken(): string
	{
		if ($this->token !== null) {
			return $this->token;
		}

		$this->token = Helper::getToken(
			$this->httpClient,
			$this->getBaseEndpoint(),
			$this->getAppId(),
			$this->getAppSecret()
		);

		return $this->token;
	}

	/**
	 * Return common headers with Bearer auth.
	 *
	 * @throws \Exception
	 */
	protected function getHeaders(): array
	{
		return [
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->fetchToken(),
		];
	}

	abstract protected function createResponse($data);
}
