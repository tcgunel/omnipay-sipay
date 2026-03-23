<?php

namespace Omnipay\Sipay\Message;

class InstallmentRatesRequest extends RemoteAbstractRequest
{
    protected $endpoint = '/api/commissions';

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        $this->validateAll();

        $data = [
            'currency_code' => $this->getCurrency(),
        ];

        return $data;
    }

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    protected function validateAll(): void
    {
        $this->validateSettings();

        $this->validate('currency');
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

    protected function createResponse($data): InstallmentRatesResponse
    {
        return $this->response = new InstallmentRatesResponse($this, $data);
    }
}
