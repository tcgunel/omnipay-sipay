<?php

namespace Omnipay\Sipay\Traits;

trait PurchaseGettersSetters
{
	public function getProvider()
	{
		return $this->getParameter('provider');
	}

	public function setProvider($value)
	{
		return $this->setParameter('provider', strtolower($value));
	}

	public function getAppId()
	{
		return $this->getParameter('appId');
	}

	public function setAppId($value)
	{
		return $this->setParameter('appId', $value);
	}

	public function getAppSecret()
	{
		return $this->getParameter('appSecret');
	}

	public function setAppSecret($value)
	{
		return $this->setParameter('appSecret', $value);
	}

	public function getMerchantKey()
	{
		return $this->getParameter('merchantKey');
	}

	public function setMerchantKey($value)
	{
		return $this->setParameter('merchantKey', $value);
	}

	public function getInstallment()
	{
		return $this->getParameter('installment');
	}

	public function setInstallment($value)
	{
		return $this->setParameter('installment', $value);
	}

	public function getInvoiceId()
	{
		return $this->getParameter('invoiceId');
	}

	public function setInvoiceId($value)
	{
		return $this->setParameter('invoiceId', $value);
	}

	public function getInvoiceDescription()
	{
		return $this->getParameter('invoiceDescription');
	}

	public function setInvoiceDescription($value)
	{
		return $this->setParameter('invoiceDescription', $value);
	}

	public function getFirstName()
	{
		return $this->getParameter('firstName');
	}

	public function setFirstName($value)
	{
		return $this->setParameter('firstName', $value);
	}

	public function getLastName()
	{
		return $this->getParameter('lastName');
	}

	public function setLastName($value)
	{
		return $this->setParameter('lastName', $value);
	}

	public function getHashKey()
	{
		return $this->getParameter('hashKey');
	}

	public function setHashKey($value)
	{
		return $this->setParameter('hashKey', $value);
	}

	public function getClientIp()
	{
		return $this->getParameter('clientIp');
	}

	public function setClientIp($value)
	{
		return $this->setParameter('clientIp', $value);
	}

	public function getSecure()
	{
		return $this->getParameter('secure');
	}

	public function setSecure($value)
	{
		return $this->setParameter('secure', $value);
	}

	public function getEndpoint()
	{
		return $this->endpoint;
	}
}
