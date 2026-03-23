<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Sipay\Exceptions\OmnipaySipayHashValidationException;
use Omnipay\Sipay\Helpers\Helper;

class CompletePurchaseResponse extends AbstractResponse
{
	protected $response;

	protected $request;

	protected bool $hashValid = false;

	public function __construct(RequestInterface $request, $data)
	{
		parent::__construct($request, $data);

		$this->request = $request;

		$this->response = $data;

		// Validate hash_key if present
		if (isset($data['hash_key']) && $request instanceof CompletePurchaseRequest) {
			try {
				$this->hashValid = Helper::validateHashKey(
					$data['hash_key'],
					$request->getAppSecret(),
					$data['invoice_id'] ?? ''
				);
			} catch (OmnipaySipayHashValidationException $e) {
				$this->hashValid = false;
			}
		}
	}

	public function isSuccessful(): bool
	{
		return isset($this->response['status_code'])
			&& (int) $this->response['status_code'] === 100
			&& $this->hashValid;
	}

	public function getMessage(): ?string
	{
		return $this->response['status_description'] ?? null;
	}

	public function getCode(): ?string
	{
		return isset($this->response['status_code'])
			? (string) $this->response['status_code']
			: null;
	}

	public function getTransactionId(): ?string
	{
		return $this->response['order_id'] ?? null;
	}

	public function getTransactionReference(): ?string
	{
		return $this->response['invoice_id'] ?? null;
	}

	public function getData()
	{
		return $this->response;
	}

	public function isHashValid(): bool
	{
		return $this->hashValid;
	}
}
