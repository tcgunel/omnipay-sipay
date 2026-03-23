<?php

namespace Omnipay\Sipay;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Sipay\Message\BinLookupRequest;
use Omnipay\Sipay\Message\CompletePurchaseRequest;
use Omnipay\Sipay\Message\InstallmentRatesRequest;
use Omnipay\Sipay\Message\PurchaseRequest;
use Omnipay\Sipay\Message\RefundRequest;
use Omnipay\Sipay\Message\VoidRequest;
use Omnipay\Sipay\Traits\PurchaseGettersSetters;

/**
 * Sipay Gateway
 * (c) Tolga Can Günel
 * 2015, mobius.studio
 * http://www.github.com/tcgunel/omnipay-sipay
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface capture(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface fetchTransaction(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface createCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = [])
 */
class Gateway extends AbstractGateway
{
	use PurchaseGettersSetters;

	public function getName(): string
	{
		return 'Sipay';
	}

	public function getDefaultParameters()
	{
		return [
			'provider'    => 'sipay',
			'clientIp'    => '127.0.0.1',
			'appId'       => '',
			'appSecret'   => '',
			'merchantKey' => '',
			'installment' => 0,
			'secure'      => false,
		];
	}

	public function purchase(array $options = []): AbstractRequest
	{
		return $this->createRequest(PurchaseRequest::class, $options);
	}

	public function completePurchase(array $options = []): AbstractRequest
	{
		return $this->createRequest(CompletePurchaseRequest::class, $options);
	}

	public function void(array $options = []): AbstractRequest
	{
		return $this->createRequest(VoidRequest::class, $options);
	}

	public function refund(array $options = []): AbstractRequest
	{
		return $this->createRequest(RefundRequest::class, $options);
	}

	public function binLookup(array $options = []): AbstractRequest
	{
		return $this->createRequest(BinLookupRequest::class, $options);
	}

	public function installmentRates(array $options = []): AbstractRequest
	{
		return $this->createRequest(InstallmentRatesRequest::class, $options);
	}
}
