<?php

namespace Core\Database\Traits;

use Core\Facades\Hash;
use Core\Support\Carbon;

trait HasCasting
{
    /**
     * Check if an attribute has a cast type defined
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->getCasts());
    }


    /**
     * Cast an attribute to its defined type
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return $value;
        }

        // Don't cast null values (except for specific types)
        if (is_null($value) && !in_array($casts[$key], ['array', 'json', 'collection'])) {
            return $value;
        }

        $castType = $casts[$key];

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => $this->castToBoolean($value),
            'array', 'json' => $this->castToArray($value),
            'object' => $this->castToObject($value),
            'collection' => $this->castToCollection($value),
            'date' => $this->asDate($value),
            'datetime' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            'hashed', 'password' => $this->castToHashed($value),
            default => $value,
        };
    }


    /**
     * Determine if the given attribute should be cast when setting
     */
    protected function shouldCastOnSet(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $casts = $this->getCasts();

        // Always cast these types when setting
        $alwaysCastOnSet = ['hashed', 'password', 'array', 'json', 'collection'];

        return in_array($casts[$key], $alwaysCastOnSet);
    }


    /**
     * Cast value to boolean (handles various truthy/falsy values)
     */
    protected function castToBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // Handle string representations
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Cast value to array
     */
    protected function castToArray(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return (array) $value;
    }

    /**
     * Cast value to object
     */
    protected function castToObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);
            return is_object($decoded) ? $decoded : (object) [];
        }

        return (object) $value;
    }

    /**
     * Cast value to collection (array)
     */
    protected function castToCollection(mixed $value): array
    {
        return $this->castToArray($value);
    }

    /**
     * Cast value to hashed password using Hash facade
     * Only hashes if the value is not already hashed
     */
    protected function castToHashed(mixed $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // Check if already hashed using Hash facade
        if (is_string($value) && Hash::isHashed($value)) {
            return $value;
        }

        // Hash the password using Hash facade (respects config)
        return Hash::make($value);
    }

    /**
     * Check if a value is already a bcrypt hash
     * 
     * @deprecated Use Hash::isHashed() instead
     */
    protected function isAlreadyHashed(string $value): bool
    {
        return Hash::isHashed($value);
    }

    /**
     * Cast value to date string (Y-m-d)
     */
    protected function asDate(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        $format = 'Y-m-d';

        if ($value instanceof Carbon) {
            return $value->format($format);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format($format);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value)->format($format);
        }

        if (is_string($value)) {
            return Carbon::parse($value)->format($format);
        }

        return '';
    }

    /**
     * Cast value to datetime string
     */
    protected function asDateTime(mixed $value): Carbon
    {
        $timezone = config('app.timezone', 'UTC');
        if ($value instanceof Carbon) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, $timezone);
        }

        if (is_string($value)) {
            return Carbon::parse($value, $timezone);
        }

        return Carbon::now($timezone); // fallback
    }


    /**
     * Cast value to timestamp
     */
    protected function asTimestamp(mixed $value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->getTimestamp();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            return Carbon::parse($value)->getTimestamp();
        }

        return null;
    }

    /**
     * Get the casts array
     */
    public function getCasts(): array
    {
        return $this->casts ?? [];
    }

    /**
     * Determine if the given attribute should be cast when getting
     */
    protected function shouldCastOnGet(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $casts = $this->getCasts();

        // Don't cast hashed/password when getting (they're already hashed)
        $skipOnGet = ['hashed', 'password'];

        return !in_array($casts[$key], $skipOnGet);
    }
}
