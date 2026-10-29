<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use RuntimeException;

class NagadCryptographicService
{
    /**
     * Format raw or one-line PEM string into standard PEM structure with headers.
     */
    public function formatKey(string $key, bool $isPrivate): string
    {
        $header = $isPrivate ? '-----BEGIN RSA PRIVATE KEY-----' : '-----BEGIN PUBLIC KEY-----';
        $footer = $isPrivate ? '-----END RSA PRIVATE KEY-----' : '-----END PUBLIC KEY-----';

        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // Clean any whitespace/newlines and chunk into 64-character lines
        $clean = preg_replace('/\s+/', '', $key);
        $chunked = chunk_split((string) $clean, 64, "\n");

        return $header."\n".trim((string) $chunked)."\n".$footer;
    }

    /**
     * Encrypt sensitive data string using Nagad's RSA Public Key.
     */
    public function encryptWithPublicKey(string $data, string $publicKeyPem): string
    {
        $pem = $this->formatKey($publicKeyPem, false);
        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) {
            throw new RuntimeException('Invalid Nagad Public Key provided: '.openssl_error_string());
        }

        $encrypted = '';
        // 1024/2048 bit RSA chunk encryption (safe chunk limit 117 bytes for 1024-bit with PKCS1)
        $chunkSize = 117;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = substr($data, $i, $chunkSize);
            $subEncrypted = '';
            if (! openssl_public_encrypt($chunk, $subEncrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('OpenSSL Public Key Encryption failed: '.openssl_error_string());
            }
            $encrypted .= $subEncrypted;
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt data encrypted by Nagad using Merchant's RSA Private Key.
     */
    public function decryptWithPrivateKey(string $base64Cipher, string $privateKeyPem): string
    {
        $pem = $this->formatKey($privateKeyPem, true);
        $privKey = openssl_pkey_get_private($pem);
        if ($privKey === false) {
            throw new RuntimeException('Invalid Merchant Private Key provided: '.openssl_error_string());
        }

        $cipher = base64_decode($base64Cipher, true);
        if ($cipher === false) {
            throw new RuntimeException('Invalid base64 ciphertext provided for Nagad decryption.');
        }

        $decrypted = '';
        // 1024-bit key = 128 bytes chunk, 2048-bit key = 256 bytes chunk
        $details = openssl_pkey_get_details($privKey);
        $chunkSize = ($details['bits'] ?? 1024) / 8;
        $length = strlen($cipher);

        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = substr($cipher, $i, $chunkSize);
            $subDecrypted = '';
            if (! openssl_private_decrypt($chunk, $subDecrypted, $privKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('OpenSSL Private Key Decryption failed: '.openssl_error_string());
            }
            $decrypted .= $subDecrypted;
        }

        return $decrypted;
    }

    /**
     * Generate an SHA256 digital signature of data using Merchant's RSA Private Key.
     */
    public function generateSignature(string $data, string $privateKeyPem): string
    {
        $pem = $this->formatKey($privateKeyPem, true);
        $privKey = openssl_pkey_get_private($pem);
        if ($privKey === false) {
            throw new RuntimeException('Invalid Merchant Private Key for signing: '.openssl_error_string());
        }

        $signature = '';
        if (! openssl_sign($data, $signature, $privKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to generate OpenSSL signature: '.openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Verify an SHA256 digital signature using Nagad's RSA Public Key.
     */
    public function verifySignature(string $data, string $signatureBase64, string $publicKeyPem): bool
    {
        $pem = $this->formatKey($publicKeyPem, false);
        $pubKey = openssl_pkey_get_public($pem);
        if ($pubKey === false) {
            return false;
        }

        $rawSignature = base64_decode($signatureBase64, true);
        if ($rawSignature === false) {
            return false;
        }

        return openssl_verify($data, $rawSignature, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
