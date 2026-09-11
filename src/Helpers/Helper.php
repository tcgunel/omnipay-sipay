<?php

namespace Omnipay\Sipay\Helpers;

use Omnipay\Sipay\Exceptions\OmnipaySipayHashValidationException;

class Helper
{
    /**
     * Get authentication token from Sipay API.
     *
     * @param \Omnipay\Common\Http\ClientInterface $httpClient
     * @param string $endpoint
     * @param string $appId
     * @param string $appSecret
     * @return string
     * @throws \Exception
     */
    public static function getToken($httpClient, string $endpoint, string $appId, string $appSecret): string
    {
        $response = $httpClient->request(
            'POST',
            $endpoint . '/api/token',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json_encode([
                'app_id' => $appId,
                'app_secret' => $appSecret,
            ])
        );

        $body = (string) $response->getBody();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['status_code']) || (int) $data['status_code'] !== 100) {
            throw new \Exception('Sipay token request failed: ' . ($data['status_description'] ?? 'Unknown error'));
        }

        return $data['data']['token'];
    }

    /**
     * Generate hash key for Sipay requests.
     *
     * data = total + "|" + installment + "|" + currencyCode + "|" + merchantKey + "|" + invoiceId
     * iv = sha1(random).substr(0,16)
     * password = sha1(appSecret)
     * salt = sha1(random).substr(0,4)
     * saltWithPassword = sha256(password + salt)
     * encrypted = AES-256-CBC(data, saltWithPassword.substr(0,32), iv)
     * hashKey = iv + ":" + salt + ":" + base64(encrypted)  // replace "/" with "__"
     *
     * @param string $total
     * @param int $installment
     * @param string $currencyCode
     * @param string $merchantKey
     * @param string $invoiceId
     * @param string $appSecret
     * @return string
     */
    public static function generateHashKey(
        string $total,
        int $installment,
        string $currencyCode,
        string $merchantKey,
        string $invoiceId,
        string $appSecret
    ): string {
        $data = $total . '|' . $installment . '|' . $currencyCode . '|' . $merchantKey . '|' . $invoiceId;

        $iv = substr(sha1(random_bytes(16)), 0, 16);
        $password = sha1($appSecret);
        $salt = substr(sha1(random_bytes(16)), 0, 4);
        $saltWithPassword = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            substr($saltWithPassword, 0, 32),
            0,
            $iv
        );

        $hashKey = $iv . ':' . $salt . ':' . $encrypted;
        $hashKey = str_replace('/', '__', $hashKey);

        return $hashKey;
    }

    /**
     * Generate hash key with deterministic IV and salt (for testing).
     *
     * @param string $total
     * @param int $installment
     * @param string $currencyCode
     * @param string $merchantKey
     * @param string $invoiceId
     * @param string $appSecret
     * @param string $iv
     * @param string $salt
     * @return string
     */
    public static function generateHashKeyDeterministic(
        string $total,
        int $installment,
        string $currencyCode,
        string $merchantKey,
        string $invoiceId,
        string $appSecret,
        string $iv,
        string $salt
    ): string {
        $data = $total . '|' . $installment . '|' . $currencyCode . '|' . $merchantKey . '|' . $invoiceId;

        $password = sha1($appSecret);
        $saltWithPassword = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            substr($saltWithPassword, 0, 32),
            0,
            $iv
        );

        $hashKey = $iv . ':' . $salt . ':' . $encrypted;
        $hashKey = str_replace('/', '__', $hashKey);

        return $hashKey;
    }

    /**
     * Build a hash_key in the 3D CALLBACK shape
     *
     *   status|total|invoice_id|order_id|currency_code
     *
     * Sipay produces these; we only ever verify them. Exposed so that tests and
     * gateway fakes can produce a callback this package will accept.
     */
    public static function generateCallbackHashKey(
        string $status,
        string $total,
        string $invoiceId,
        string $orderId,
        string $currencyCode,
        string $appSecret
    ): string {
        $data = $status . '|' . $total . '|' . $invoiceId . '|' . $orderId . '|' . $currencyCode;

        $iv = substr(sha1(random_bytes(16)), 0, 16);
        $password = sha1($appSecret);
        $salt = substr(sha1(random_bytes(16)), 0, 4);
        $saltWithPassword = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt($data, 'aes-256-cbc', substr($saltWithPassword, 0, 32), 0, $iv);

        return str_replace('/', '__', $iv . ':' . $salt . ':' . $encrypted);
    }

    /**
     * Decrypt a hash_key into its pipe-separated payload.
     *
     * Shared by both hash shapes; it proves only that the key was produced with
     * this appSecret, never what the fields mean.
     *
     * @throws OmnipaySipayHashValidationException
     */
    public static function decryptHashKey(string $hashKey, string $appSecret): string
    {
        $hashKey = str_replace('__', '/', $hashKey);

        $parts = explode(':', $hashKey, 3);

        if (count($parts) !== 3) {
            throw new OmnipaySipayHashValidationException('Invalid hash key format.');
        }

        [$iv, $salt, $encrypted] = $parts;

        $password = sha1($appSecret);
        $saltWithPassword = hash('sha256', $password . $salt);

        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            substr($saltWithPassword, 0, 32),
            0,
            $iv
        );

        if ($decrypted === false) {
            throw new OmnipaySipayHashValidationException('Hash key decryption failed.');
        }

        return $decrypted;
    }

    /**
     * Validate the hash_key posted back by the 3D callback.
     *
     * This is a DIFFERENT payload from the one we send with a purchase. The
     * callback's hash decrypts to
     *
     *   status|total|invoice_id|order_id|currency_code
     *
     * e.g. "1|1412.00|6aa3d20689242|63924728630942896449049|TRY", where the
     * request's is total|installment|currency_code|merchant_key|invoice_id.
     * Reading the callback with the request's layout puts the currency code
     * where the invoice id is expected, so every callback failed validation -
     * and an approved, charged payment was reported back to the shop as a
     * decline.
     *
     * @return array{status: string, total: string, invoice_id: string, order_id: string, currency_code: string}
     *
     * @throws OmnipaySipayHashValidationException
     */
    public static function validateCallbackHashKey(string $hashKey, string $appSecret, string $invoiceId): array
    {
        $decrypted = self::decryptHashKey($hashKey, $appSecret);

        $parts = explode('|', $decrypted);

        if (count($parts) < 5) {
            throw new OmnipaySipayHashValidationException('Invalid callback hash key data format.');
        }

        [$status, $total, $decryptedInvoiceId, $orderId, $currencyCode] = $parts;

        // The whole point of the hash: the callback names the order it settles,
        // so another order's callback cannot be replayed onto this one.
        if ($decryptedInvoiceId !== $invoiceId) {
            throw new OmnipaySipayHashValidationException(
                'Hash key invoice_id mismatch. Expected: ' . $invoiceId . ', Got: ' . $decryptedInvoiceId
            );
        }

        return [
            'status' => $status,
            'total' => $total,
            'invoice_id' => $decryptedInvoiceId,
            'order_id' => $orderId,
            'currency_code' => $currencyCode,
        ];
    }

    /**
     * Validate a hash key built with generateHashKey(), i.e. the REQUEST shape
     *
     *   total|installment|currency_code|merchant_key|invoice_id
     *
     * The 3D callback posts a different payload - use validateCallbackHashKey()
     * for that.
     *
     * @param string $hashKey
     * @param string $appSecret
     * @param string $invoiceId
     * @return bool
     * @throws OmnipaySipayHashValidationException
     */
    public static function validateHashKey(string $hashKey, string $appSecret, string $invoiceId): bool
    {
        $decrypted = self::decryptHashKey($hashKey, $appSecret);

        $dataParts = explode('|', $decrypted);

        if (count($dataParts) < 5) {
            throw new OmnipaySipayHashValidationException('Invalid hash key data format.');
        }

        // invoice_id is the last part
        $decryptedInvoiceId = $dataParts[4];

        if ($decryptedInvoiceId !== $invoiceId) {
            throw new OmnipaySipayHashValidationException(
                'Hash key invoice_id mismatch. Expected: ' . $invoiceId . ', Got: ' . $decryptedInvoiceId
            );
        }

        return true;
    }
}
