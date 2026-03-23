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
     * Validate hash key from a 3D response by decrypting and checking invoice_id.
     *
     * @param string $hashKey
     * @param string $appSecret
     * @param string $invoiceId
     * @return bool
     * @throws OmnipaySipayHashValidationException
     */
    public static function validateHashKey(string $hashKey, string $appSecret, string $invoiceId): bool
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
