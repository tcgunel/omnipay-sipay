<?php

namespace Omnipay\Sipay\Message;

use Omnipay\Common\Item;
use Omnipay\Sipay\Constants\TransactionType;
use Omnipay\Sipay\Helpers\Helper;

class PurchaseRequest extends RemoteAbstractRequest
{
    protected $endpoint = '/api/paySmart2D';

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @throws \Omnipay\Common\Exception\InvalidCreditCardException
     */
    public function getData()
    {
        $this->validateAll();

        $items = [];

        if ($this->getItems()) {
            foreach ($this->getItems()->all() as $item) {
                /** @var Item $item */
                $items[] = [
                    'name' => $item->getName(),
                    'price' => $item->getPrice(),
                    'quantity' => $item->getQuantity(),
                    'description' => $item->getDescription(),
                ];
            }
        }

        $data = [
            'cc_holder_name' => $this->get_card('getName'),
            'cc_no' => $this->get_card('getNumber'),
            'expiry_month' => $this->get_card('getExpiryMonth'),
            'expiry_year' => $this->get_card('getExpiryYear'),
            'cvv' => $this->get_card('getCvv'),
            'currency_code' => $this->getCurrency(),
            'installments_number' => $this->getInstallment() ?? 0,
            'invoice_id' => $this->getInvoiceId() ?? $this->getTransactionId(),
            'invoice_description' => $this->getInvoiceDescription() ?? '',
            'name' => $this->getFirstName() ?? $this->get_card('getFirstName'),
            'surname' => $this->getLastName() ?? $this->get_card('getLastName'),
            'total' => $this->getAmount(),
            'merchant_key' => $this->getMerchantKey(),
            'transaction_type' => TransactionType::AUTH,
            'items' => json_encode($items),
            'hash_key' => Helper::generateHashKey(
                $this->getAmount(),
                (int) ($this->getInstallment() ?? 0),
                $this->getCurrency(),
                $this->getMerchantKey(),
                $this->getInvoiceId() ?? $this->getTransactionId(),
                $this->getAppSecret()
            ),
        ];

        if ($this->getSecure()) {
            return $this->get3DData($data);
        }

        return $data;
    }

    /**
     * Convert to 3D parameters.
     */
    protected function get3DData(array $data): array
    {
        $data = array_merge($data, [
            'response_method' => 'POST',
            'payment_completed_by' => 'app',
            'ip' => $this->getClientIp() ?? '127.0.0.1',
            'cancel_url' => $this->getCancelUrl(),
            'return_url' => $this->getReturnUrl(),
        ]);

        return $data;
    }

    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @throws \Omnipay\Common\Exception\InvalidCreditCardException
     */
    protected function validateAll(): void
    {
        $this->validateSettings();

        $this->getCard()->validate();

        $this->validate('amount', 'currency');

        if ($this->getSecure()) {
            $this->validate('returnUrl', 'cancelUrl');
        }
    }

    public function sendData($data)
    {
        if ($this->getSecure()) {
            $this->endpoint = '/api/paySmart3D';
        }

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getBaseEndpoint() . $this->endpoint,
            $this->getHeaders(),
            json_encode($data)
        );

        return $this->createResponse($httpResponse);
    }

    protected function createResponse($data): PurchaseResponse
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
