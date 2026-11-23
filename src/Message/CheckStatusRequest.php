<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Sipay\Helpers\Helper;

/**
 * POST /api/checkstatus: a sale's current state, addressed by the merchant's
 * own invoice_id.
 */
class CheckStatusRequest extends RemoteAbstractRequest
{
    protected $endpoint = '/api/checkstatus';

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        $this->validateSettings();

        $this->validate('transactionId');

        $invoiceId = $this->getInvoiceId() ?? $this->getTransactionId();

        return [
            'merchant_key' => $this->getMerchantKey(),
            'invoice_id' => $invoiceId,
            'include_pending_status' => true,
            'show_refund_info' => 'Y',
            'hash_key' => Helper::generateCheckStatusHashKey(
                (string) $invoiceId,
                $this->getMerchantKey(),
                $this->getAppSecret()
            ),
        ];
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

    protected function createResponse($data): CheckStatusResponse
    {
        return $this->response = new CheckStatusResponse($this, $data);
    }
}
