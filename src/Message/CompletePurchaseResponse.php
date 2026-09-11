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

    /**
     * @var array{status: string, total: string, invoice_id: string, order_id: string, currency_code: string}|null
     */
    protected ?array $callback = null;

    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->request = $request;

        $this->response = $data;

        // The callback's hash_key is the only part of this POST we can trust: it
        // is encrypted with the appSecret and names the invoice it settles.
        if (isset($data['hash_key']) && $request instanceof CompletePurchaseRequest) {
            try {
                $this->callback = Helper::validateCallbackHashKey(
                    $data['hash_key'],
                    $request->getAppSecret(),
                    $data['invoice_id'] ?? ''
                );

                $this->hashValid = $this->amountMatchesHash($data);

            } catch (OmnipaySipayHashValidationException $e) {
                $this->hashValid = false;
                $this->callback = null;
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

    /**
     * The hash-bound view of the transaction, or null when validation failed.
     *
     * @return array{status: string, total: string, invoice_id: string, order_id: string, currency_code: string}|null
     */
    public function getCallbackData(): ?array
    {
        return $this->callback;
    }

    /**
     * The POST is attacker-reachable; the hash is not. Where the two describe
     * the same field they have to agree, otherwise a shopper could settle a
     * 1412.00 order by posting back a 1.00 one.
     *
     * @param  array<string, mixed>  $data
     */
    private function amountMatchesHash(array $data): bool
    {
        foreach (['total' => 'total', 'order_id' => 'order_id'] as $posted => $hashed) {

            if (! isset($data[$posted]) || (string) $data[$posted] === '') {
                continue;
            }

            if ((string) $data[$posted] !== (string) $this->callback[$hashed]) {
                return false;
            }

        }

        return true;
    }
}
