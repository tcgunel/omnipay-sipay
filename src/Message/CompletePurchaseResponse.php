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

        $this->hashValid = $this->verifyHash($request, is_array($data) ? $data : []);
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
     * The callback's hash_key is the only part of this POST we can trust: it is
     * encrypted with the appSecret and names the invoice and amount it settles.
     *
     * When the caller passes transactionId (its own invoice id) and/or amount,
     * those are authoritative and the hash has to match them exactly; posted
     * values are never used in their place. Without them we fall back to the
     * legacy behaviour and bind the hash to the posted fields.
     *
     * @param  array<string, mixed>  $data
     */
    private function verifyHash(RequestInterface $request, array $data): bool
    {
        if (! $request instanceof CompletePurchaseRequest) {
            return false;
        }

        $hashKey = $data['hash_key'] ?? null;
        $appSecret = $request->getAppSecret();

        if (! is_string($hashKey) || $hashKey === '' || ! is_string($appSecret) || $appSecret === '') {
            return false;
        }

        $expectedInvoiceId = $request->getTransactionId();
        $callerSuppliedInvoice = $expectedInvoiceId !== null && (string) $expectedInvoiceId !== '';

        if (! $callerSuppliedInvoice) {
            $expectedInvoiceId = $data['invoice_id'] ?? null;

            if (! is_scalar($expectedInvoiceId) || (string) $expectedInvoiceId === '') {
                return false;
            }
        }

        try {
            $this->callback = Helper::validateCallbackHashKey($hashKey, $appSecret, (string) $expectedInvoiceId);
        } catch (OmnipaySipayHashValidationException $e) {
            $this->callback = null;

            return false;
        }

        if (! $this->fieldsMatchHash($request, $data)) {
            $this->callback = null;

            return false;
        }

        return true;
    }

    /**
     * The POST is attacker-reachable; the hash is not. Every hash field the POST
     * also carries has to agree with it, and a caller-supplied amount has to
     * equal the hashed total exactly (compared in integer minor units).
     *
     * @param  array<string, mixed>  $data
     */
    private function fieldsMatchHash(CompletePurchaseRequest $request, array $data): bool
    {
        $hash = (array) $this->callback;

        try {
            $decimals = $request->getCurrencyDecimalPlaces();
            $rawAmount = $request->getParameters()['amount'] ?? null;
            $expectedAmount = $rawAmount !== null && $rawAmount !== '' ? $request->getAmountInteger() : null;
        } catch (\Throwable $e) {
            return false;
        }

        $hashTotal = self::toMinorUnits((string) $hash['total'], $decimals);

        if ($hashTotal === null) {
            return false;
        }

        if ($expectedAmount !== null) {
            if ($hashTotal !== $expectedAmount) {
                return false;
            }
        } elseif ($this->isPosted($data, 'total')) {
            if (self::toMinorUnits((string) $data['total'], $decimals) !== $hashTotal) {
                return false;
            }
        }

        // With a caller-supplied invoice id the hash was already checked against
        // it; a posted invoice_id must still not contradict the hash.
        foreach (['invoice_id', 'order_id', 'currency_code'] as $field) {
            if ($this->isPosted($data, $field) && (string) $data[$field] !== (string) $hash[$field]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isPosted(array $data, string $field): bool
    {
        return isset($data[$field]) && is_scalar($data[$field]) && (string) $data[$field] !== '';
    }

    /**
     * "1412.00" => 141200 (for 2 decimals). Null for anything that is not a
     * plain non-negative decimal with at most $decimals fraction digits.
     */
    private static function toMinorUnits(string $value, int $decimals): ?int
    {
        $value = trim($value);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $m)) {
            return null;
        }

        $fraction = rtrim($m[2] ?? '', '0');

        if (strlen($fraction) > $decimals) {
            return null;
        }

        return (int) ($m[1] . str_pad($fraction, $decimals, '0'));
    }
}
