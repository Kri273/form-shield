<?php

declare(strict_types=1);

class IpProtection
{
    private string $key;

    public function __construct(string $secret)
    {
        if ($secret === '') {
            throw new RuntimeException(
                'IP encryption secret is missing.'
            );
        }

        $this->key = hash(
            'sha256',
            $secret,
            true
        );
    }

    public function encrypt(string $ip): string
    {
        $iv = random_bytes(
            openssl_cipher_iv_length('aes-256-cbc')
        );

        $encrypted = openssl_encrypt(
            $ip,
            'aes-256-cbc',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException(
                'Could not encrypt IP address.'
            );
        }

        return base64_encode(
            $iv . $encrypted
        );
    }

    public function decrypt(string $encrypted): ?string
    {
        $decoded = base64_decode(
            $encrypted,
            true
        );

        if ($decoded === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(
            'aes-256-cbc'
        );

        if (strlen($decoded) <= $ivLength) {
            return null;
        }

        $iv = substr(
            $decoded,
            0,
            $ivLength
        );

        $ciphertext = substr(
            $decoded,
            $ivLength
        );

        $ip = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $ip !== false
            ? $ip
            : null;
    }
}