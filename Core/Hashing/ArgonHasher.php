<?php

namespace Core\Hashing;

use Core\Contracts\Hashing\HasherContract;
use RuntimeException;

class ArgonHasher implements HasherContract
{
    /**
     * The default memory cost factor.
     *
     * @var int
     */
    protected int $memory = 65536;

    /**
     * The default time cost factor.
     *
     * @var int
     */
    protected int $time = 4;

    /**
     * The default threads factor.
     *
     * @var int
     */
    protected int $threads = 1;

    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected bool $verifyAlgorithm = false;

    /**
     * Create a new hasher instance.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->memory = $options['memory'] ?? $this->memory;
        $this->time = $options['time'] ?? $this->time;
        $this->threads = $options['threads'] ?? $this->threads;
        $this->verifyAlgorithm = $options['verify'] ?? $this->verifyAlgorithm;
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param array $options
     * @return string
     *
     * @throws RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, $this->algorithm(), [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     *
     * @throws RuntimeException
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if ($this->verifyAlgorithm && !$this->isHashed($hashedValue)) {
            throw new RuntimeException('This password does not use the Argon2 algorithm.');
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, $this->algorithm(), [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);
    }

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Get the algorithm that should be used for hashing.
     *
     * @return int
     */
    protected function algorithm(): int
    {
        return PASSWORD_ARGON2ID;
    }

    /**
     * Extract the memory cost value from the options array.
     *
     * @param array $options
     * @return int
     */
    protected function memory(array $options = []): int
    {
        return $options['memory'] ?? $this->memory;
    }

    /**
     * Extract the time cost value from the options array.
     *
     * @param array $options
     * @return int
     */
    protected function time(array $options = []): int
    {
        return $options['time'] ?? $this->time;
    }

    /**
     * Extract the threads value from the options array.
     *
     * @param array $options
     * @return int
     */
    protected function threads(array $options = []): int
    {
        return $options['threads'] ?? $this->threads;
    }

    /**
     * Verify that the given hash was created using Argon2.
     *
     * @param string $hashedValue
     * @return bool
     */
    protected function isHashed(string $hashedValue): bool
    {
        $info = $this->info($hashedValue);
        return in_array($info['algoName'], ['argon2i', 'argon2id']);
    }
}