<?php

namespace Omnipay\Sipay\Message;

use JsonException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The checkstatus answer. Successful means the lookup worked (status 100),
 * not that the sale was paid: read getTransactionStatus() for that.
 */
class CheckStatusResponse extends AbstractResponse
{
    protected $response;

    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->response = $data;

        if ($data instanceof ResponseInterface) {
            $body = (string) $data->getBody();

            try {
                $this->response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->response = [
                    'status_code' => 0,
                    'status_description' => $body,
                ];
            }
        }
    }

    public function isSuccessful(): bool
    {
        return isset($this->response['status_code'])
            && (int) $this->response['status_code'] === 100;
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

    /**
     * Upper-cased: QNBpay answers "REFUNDED" where the docs show "Completed".
     */
    public function getTransactionStatus(): ?string
    {
        $status = $this->response['transaction_status'] ?? null;

        return is_string($status) && $status !== '' ? strtoupper($status) : null;
    }

    public function isRefunded(): bool
    {
        return in_array($this->getTransactionStatus(), ['REFUNDED', 'PARTIAL REFUND', 'PARTIALLY_REFUNDED', 'PARTIALLY_REFUND'], true);
    }

    /**
     * Everything refunded so far, including refunds the provider's team
     * completed by hand after a 101 answer.
     */
    public function getTotalRefundedAmount(): float
    {
        return round((float) ($this->response['total_refunded_amount'] ?? 0), 2);
    }

    public function getTransactionAmount(): ?float
    {
        return isset($this->response['transaction_amount'])
            ? round((float) $this->response['transaction_amount'], 2)
            : null;
    }

    public function getTransactionReference(): ?string
    {
        $reference = $this->response['order_id'] ?? null;

        return $reference !== null && $reference !== '' ? (string) $reference : null;
    }

    public function getData()
    {
        return $this->response;
    }
}
