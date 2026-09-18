<?php

namespace Omnipay\Sipay\Message;

use JsonException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Sipay\Constants\CardType;
use Psr\Http\Message\ResponseInterface;

class BinLookupResponse extends AbstractResponse
{
    protected $response;

    protected $request;

    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->request = $request;

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

    public function getData()
    {
        return $this->response;
    }

    /**
     * The POS rows the provider returned - one per installment option this card
     * may be charged in, for this amount, on this merchant's POS.
     *
     * A card that cannot be paid in installments comes back with a single row
     * whose `installments_number` is 1.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPosOptions(): array
    {
        return $this->response['data'] ?? [];
    }

    /**
     * How the card is funded: CREDIT_CARD or DEBIT_CARD.
     *
     * This is not the scheme. VISA and MASTER_CARD arrive separately, under
     * `card_scheme`.
     *
     * @see CardType
     */
    public function getCardType(): ?string
    {
        return $this->getPosOptions()[0]['card_type'] ?? null;
    }

    public function isCreditCard(): bool
    {
        return $this->getCardType() === CardType::CREDIT_CARD;
    }

    /**
     * A debit card cannot be charged in installments, so the provider offers it
     * nothing but single payment.
     */
    public function isDebitCard(): bool
    {
        return $this->getCardType() === CardType::DEBIT_CARD;
    }

    /**
     * Every installment count the issuer accepts for this card and amount, 1
     * being single payment.
     *
     * The merchant may offer fewer than these, never more: the issuer's own cap
     * is what the provider is reporting here.
     *
     * @return array<int, int>
     */
    public function getInstallmentNumbers(): array
    {
        $numbers = [];

        foreach ($this->getPosOptions() as $row) {

            if (isset($row['installments_number'])) {

                $numbers[] = (int) $row['installments_number'];

            }

        }

        return array_values(array_unique($numbers));
    }
}
