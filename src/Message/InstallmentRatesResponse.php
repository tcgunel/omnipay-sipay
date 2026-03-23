<?php

namespace Omnipay\Sipay\Message;

use JsonException;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class InstallmentRatesResponse extends AbstractResponse
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
}
