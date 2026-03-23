<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Sipay\Helpers\Helper;

class VoidRequest extends RemoteAbstractRequest
{
    protected $endpoint = '/api/refund';

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        $this->validateAll();

        $invoiceId = $this->getInvoiceId() ?? $this->getTransactionId();

        $data = [
            'invoice_id' => $invoiceId,
            'amount' => 0,
            'app_id' => $this->getAppId(),
            'app_secret' => $this->getAppSecret(),
            'merchant_key' => $this->getMerchantKey(),
            'hash_key' => Helper::generateHashKey(
                '0',
                0,
                $this->getCurrency() ?? 'TRY',
                $this->getMerchantKey(),
                $invoiceId,
                $this->getAppSecret()
            ),
        ];

        return $data;
    }

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    protected function validateAll(): void
    {
        $this->validateSettings();

        $this->validate('transactionId');
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

    protected function createResponse($data): VoidResponse
    {
        return $this->response = new VoidResponse($this, $data);
    }
}
