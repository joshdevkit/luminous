<?php

namespace Core\Framework;

use RuntimeException;

class AppKeyValidator
{
    /**
     * Validate the application key from configuration.
     *
     * @param string|null $key
     * @param string $cipher
     * @throws RuntimeException
     */
    public static function validate(?string $key, string $cipher = 'AES-256-CBC'): void
    {
        if (empty($key)) {
            throw new RuntimeException(
                'No application  key has been specified. ' .
                'Please run: php dev key:generate'
            );
        }

        $key = static::parseKey($key);

        if (static::keyIsInvalid($key, $cipher)) {
            throw new RuntimeException(
                'The application key is invalid. ' .
                'The key must be ' . static::getRequiredKeyLength($cipher) . ' bytes long. ' .
                'Please run: php dev key:generate'
            );
        }
    }

    /**
     * Parse the encryption key.
     *
     * @param string $key
     * @return string
     */
    protected static function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }

    /**
     * Check if the key is invalid for the given cipher.
     *
     * @param string $key
     * @param string $cipher
     * @return bool
     */
    protected static function keyIsInvalid(string $key, string $cipher): bool
    {
        return mb_strlen($key, '8bit') !== static::getRequiredKeyLength($cipher);
    }

    /**
     * Get the required key length for the cipher.
     *
     * @param string $cipher
     * @return int
     */
    protected static function getRequiredKeyLength(string $cipher): int
    {
        return match (strtoupper($cipher)) {
            'AES-128-CBC' => 16,
            'AES-256-CBC' => 32,
            default => throw new RuntimeException("Unsupported cipher: {$cipher}")
        };
    }

    /**
     * Check if a key is set and valid without throwing exception.
     *
     * @param string|null $key
     * @param string $cipher
     * @return bool
     */
    public static function isValid(?string $key, string $cipher = 'AES-256-CBC'): bool
    {
        try {
            static::validate($key, $cipher);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
