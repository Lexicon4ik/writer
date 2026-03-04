<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * AES-256-GCM encryption for sensitive data (tokens, API keys).
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12; // 96 bits for GCM
    private const TAG_LENGTH = 16;

    /**
     * Encrypt plaintext.
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64(nonce + ciphertext + tag)
     * @throws \RuntimeException if APP_KEY not set or encryption fails
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $nonce = random_bytes(self::NONCE_LENGTH);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($nonce . $ciphertext . $tag);
    }

    /**
     * Decrypt ciphertext.
     *
     * @param string $ciphertext Base64-encoded encrypted data
     * @return string Decrypted data
     * @throws \RuntimeException on decryption failure
     */
    public static function decrypt(string $ciphertext): string
    {
        $key = self::getKey();

        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid ciphertext: not valid base64');
        }

        $minLength = self::NONCE_LENGTH + self::TAG_LENGTH + 1;
        if (strlen($data) < $minLength) {
            throw new \RuntimeException('Invalid ciphertext format: too short');
        }

        $nonce = substr($data, 0, self::NONCE_LENGTH);
        $tag = substr($data, -self::TAG_LENGTH);
        $encrypted = substr($data, self::NONCE_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    /**
     * Safe decrypt with fallback for plain-text values.
     * Used for backward compatibility during migration from plain-text to encrypted.
     *
     * @param string $value Possibly encrypted value
     * @return string Decrypted value or original if not encrypted
     */
    public static function decryptSafe(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        try {
            return self::decrypt($value);
        } catch (\RuntimeException $e) {
            // Not encrypted (plain-text), return as-is
            return $value;
        }
    }

    /**
     * Generate a new APP_KEY.
     *
     * @return string Base64-encoded 32-byte key
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Get encryption key from environment.
     *
     * @return string 32-byte key
     * @throws \RuntimeException if APP_KEY not set
     */
    private static function getKey(): string
    {
        $key = $_ENV['APP_KEY'] ?? null;

        if (empty($key)) {
            throw new \RuntimeException('APP_KEY not set in .env');
        }

        // Handle base64: prefix
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
        } else {
            $decoded = base64_decode($key, true);
        }

        if ($decoded === false || strlen($decoded) !== 32) {
            throw new \RuntimeException('Invalid APP_KEY: must be 32 bytes base64-encoded');
        }

        return $decoded;
    }
}
