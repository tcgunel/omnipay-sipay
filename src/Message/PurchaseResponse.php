<?php

namespace Omnipay\Sipay\Message;

use JsonException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    protected $response;

    protected $request;

    protected bool $is3D = false;

    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->request = $request;

        $this->response = $data;

        if ($data instanceof ResponseInterface) {

            $body = (string) $data->getBody();

            try {

                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                $this->response = $decoded;

                // 3D response returns HTML for redirect
                if (isset($decoded['data']) && is_string($decoded['data']) && str_contains($decoded['data'], '<form')) {
                    $this->is3D = true;
                }

            } catch (JsonException $e) {

                // If it's raw HTML (3D redirect), store as-is
                $this->response = [
                    'status_code' => 0,
                    'status_description' => $body,
                ];

            }
        }
    }

    public function isSuccessful(): bool
    {
        if ($this->is3D) {
            return false;
        }

        return isset($this->response['status_code'])
            && (int) $this->response['status_code'] === 100;
    }

    public function isRedirect(): bool
    {
        return $this->is3D;
    }

    public function getRedirectUrl()
    {
        return null;
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function getRedirectData()
    {
        return [];
    }

    /**
     * For 3D, the response contains HTML to render directly.
     */
    public function getRedirectHtml(): ?string
    {
        if ($this->is3D && isset($this->response['data'])) {
            return $this->response['data'];
        }

        return null;
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
        return $this->response['data']['auth_code'] ?? null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->response['data']['invoice_id'] ?? null;
    }

    public function getData()
    {
        return $this->response;
    }
}
