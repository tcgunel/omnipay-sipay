<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Common\Exception\InvalidCreditCardException;

class BinLookupRequest extends RemoteAbstractRequest
{
    protected $endpoint = '/api/getpos';

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @throws InvalidCreditCardException
     */
    public function getData()
    {
        $this->validateAll();

        $data = [
            'credit_card' => substr($this->getCard()->getNumber(), 0, 6),
            'amount' => $this->getAmount(),
            'currency_code' => $this->getCurrency(),
            'merchant_key' => $this->getMerchantKey(),
        ];

        return $data;
    }

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @throws InvalidCreditCardException
     */
    protected function validateAll(): void
    {
        $this->validateSettings();

        if (!is_null($this->getCard()->getNumber()) && !preg_match('/^\d{8,19}$/', $this->getCard()->getNumber())) {
            throw new InvalidCreditCardException('Card number should have at least 8 to maximum of 19 digits');
        }

        $this->validate('amount', 'currency');
    }

    public function sendData($data)
    {
        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getBaseEndpoint() . $this->endpoint,
            $this->getHeaders(),
            json_encode($data)
        );

        return $this->createResponse($httpResponse);
    }

    protected function createResponse($data): BinLookupResponse
    {
        return $this->response = new BinLookupResponse($this, $data);
    }
}
