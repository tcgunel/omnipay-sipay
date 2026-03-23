<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Sipay\Helpers\Helper;

class CompletePurchaseRequest extends RemoteAbstractRequest
{
	/**
	 * @throws \Omnipay\Common\Exception\InvalidRequestException
	 */
	public function getData()
	{
		$this->validateAll();

		// The data comes from the 3D POST callback
		$data = $this->httpRequest->request->all();

		return $data;
	}

	/**
	 * @throws \Omnipay\Common\Exception\InvalidRequestException
	 */
	protected function validateAll(): void
	{
		$this->validateSettings();
	}

	public function sendData($data)
	{
		return $this->createResponse($data);
	}

	protected function createResponse($data): CompletePurchaseResponse
	{
		return $this->response = new CompletePurchaseResponse($this, $data);
	}
}
