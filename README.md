# Omnipay: Sipay

**Sipay (CCPayment) driver for the Omnipay PHP payment processing library**

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway payment
processing library for PHP. This package implements Sipay support for Omnipay.

## Installation

```bash
composer require tcgunel/omnipay-sipay
```

## Requirements

- PHP >= 8.0
- ext-json
- ext-openssl

## Configuration

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Sipay');

$gateway->setAppId('your_app_id');           // app_id from Sipay
$gateway->setAppSecret('your_app_secret');   // app_secret from Sipay
$gateway->setMerchantKey('your_merchant_key'); // merchant_key from Sipay
$gateway->setTestMode(true);                 // Use test endpoint
```

## Supported Methods

### Purchase (Non-3D)

```php
$response = $gateway->purchase([
    'amount'       => '100.00',
    'currency'     => 'TRY',
    'card'         => $cardData,
    'installment'  => 1,
    'transactionId' => 'INV-001',
    'items'        => [
        ['name' => 'Product', 'price' => 100, 'quantity' => 1, 'description' => 'Desc'],
    ],
])->send();

if ($response->isSuccessful()) {
    echo $response->getTransactionId(); // auth_code
}
```

### Purchase (3D Secure)

```php
$response = $gateway->purchase([
    'amount'       => '100.00',
    'currency'     => 'TRY',
    'card'         => $cardData,
    'installment'  => 1,
    'transactionId' => 'INV-001',
    'secure'       => true,
    'returnUrl'    => 'https://example.com/success',
    'cancelUrl'    => 'https://example.com/cancel',
    'clientIp'     => '127.0.0.1',
    'items'        => [
        ['name' => 'Product', 'price' => 100, 'quantity' => 1, 'description' => 'Desc'],
    ],
])->send();

if ($response->isRedirect()) {
    echo $response->getRedirectHtml(); // Render this HTML
}
```

### Complete Purchase (3D Callback)

```php
$response = $gateway->completePurchase()->send();

if ($response->isSuccessful()) {
    echo $response->getTransactionId();
}
```

### Void (Cancel)

```php
$response = $gateway->void([
    'transactionId' => 'INV-001',
    'currency'      => 'TRY',
])->send();

if ($response->isSuccessful()) {
    echo 'Transaction cancelled.';
}
```

### Refund

```php
$response = $gateway->refund([
    'transactionId' => 'INV-001',
    'amount'        => '50.00',
    'currency'      => 'TRY',
])->send();

if ($response->isSuccessful()) {
    echo 'Refund successful.';
}
```

### BIN Lookup (Installment Query)

```php
$response = $gateway->binLookup([
    'card'     => ['number' => '979203XXXXXXXXXX'],
    'amount'   => '100.00',
    'currency' => 'TRY',
])->send();

if ($response->isSuccessful()) {
    $data = $response->getData();
    // $data['data'] contains POS and installment info
}
```

### Installment Rates (All Commissions)

```php
$response = $gateway->installmentRates([
    'currency' => 'TRY',
])->send();

if ($response->isSuccessful()) {
    $data = $response->getData();
    // $data['data'] contains all commission rates
}
```

## Endpoints

| Environment | Base URL |
|-------------|----------|
| Test | `https://provisioning.sipay.com.tr/ccpayment` |
| Live | `https://app.sipay.com.tr/ccpayment` |

## Authentication

The gateway uses token-based authentication. A Bearer token is automatically obtained before each API request using your `app_id` and `app_secret`.

## Hash Key

For purchase, refund, and void operations, a hash key is generated using AES-256-CBC encryption with the following data:

```
data = total|installment|currencyCode|merchantKey|invoiceId
```

The hash key is automatically generated and validated by the library.

## Running Tests

```bash
vendor/bin/phpunit
```

## License

MIT
