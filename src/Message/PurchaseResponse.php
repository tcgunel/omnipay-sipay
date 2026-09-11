<?php

namespace Omnipay\Sipay\Message;

use JsonException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    protected $response;

    protected $request;

    protected bool $is3D = false;

    protected ?string $html = null;

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

                // paySmart3D answers with a whole HTML page rather than JSON, for
                // both outcomes: on success the form posts the shopper to the
                // bank's ACS, on refusal it posts the error straight back to the
                // merchant's cancel_url. Either way it is a redirect - the caller
                // must render it and must not treat it as a finished transaction.
                $this->response = [
                    'status_code' => 0,
                    'status_description' => $body,
                ];

                $this->html = $body;

                $this->is3D = str_contains($body, '<form');

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
        if (! $this->is3D) {
            return null;
        }

        if ($this->html !== null) {
            return $this->html;
        }

        return $this->response['data'] ?? null;
    }

    /**
     * Sipay hands back a complete, self-submitting HTML document, so there is no
     * URL or field set to rebuild a form from - the parent's implementation would
     * fail validateRedirect() on the empty redirect URL. Serve the page as it came.
     */
    public function getRedirectResponse()
    {
        $html = $this->getRedirectHtml();

        if ($html === null) {
            return parent::getRedirectResponse();
        }

        return new HttpResponse($html);
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
