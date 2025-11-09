<?php

namespace Core\Support;

class Str
{
    /**
     * The cache of snake-cased words.
     */
    protected static array $snakeCache = [];

    /**
     * The cache of camel-cased words.
     */
    protected static array $camelCache = [];

    /**
     * The cache of studly-cased words.
     */
    protected static array $studlyCache = [];

    /**
     * Convert a string to snake_case
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Convert a string to camelCase
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Convert a string to StudlyCase (PascalCase)
     */
    public static function studly(string $value): string
    {
        if (isset(static::$studlyCache[$value])) {
            return static::$studlyCache[$value];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$value] = str_replace(' ', '', $value);
    }

    /**
     * Convert a string to kebab-case
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    /**
     * Pluralize a word
     */
    public static function plural(string $word): string
    {
        // Very basic rules
        if (preg_match('/(s|x|z|ch|sh)$/i', $word)) {
            return $word . 'es';
        }

        if (preg_match('/y$/i', $word) && !preg_match('/[aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        return $word . 's';
    }


    /**
     * Singularize a word
     */
    public static function singular(string $value): string
    {
        $irregular = [
            'people' => 'person',
            'men' => 'man',
            'women' => 'woman',
            'children' => 'child',
            'teeth' => 'tooth',
            'feet' => 'foot',
            'mice' => 'mouse',
            'geese' => 'goose',
            'oxen' => 'ox',
        ];

        $lower = strtolower($value);

        if (isset($irregular[$lower])) {
            return static::matchCase($irregular[$lower], $value);
        }

        $rules = [
            '/([^aeiou])ies$/i' => '\1y',
            '/(x|ch|ss|sh|z)es$/i' => '\1',
            '/ves$/i' => 'fe',
            '/ses$/i' => 'sis',
            '/([ti])a$/i' => '\1um',
            '/(analy|ba|diagno|parenthe|progno|synop|the)ses$/i' => '\1sis',
            '/(m)en$/i' => '\1an',
            '/(ss)$/i' => '\1',
            '/s$/i' => '',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * Match the case of the target word
     */
    protected static function matchCase(string $value, string $comparison): string
    {
        $functions = ['mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords'];

        foreach ($functions as $function) {
            if ($function($comparison) === $comparison) {
                return $function($value);
            }
        }

        return $value;
    }

    /**
     * Determine if a string contains a given substring
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a string starts with a given substring
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a string ends with a given substring
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limit the number of characters in a string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /**
     * Limit the number of words in a string
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Generate a random string
     */
    public static function random(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Convert string to title case
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert string to lowercase
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert string to uppercase
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Replace the first occurrence of a value in a string
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a value in a string
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Slugify a string
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        // Convert to lowercase
        $value = static::lower($value);

        // Replace spaces and undersCores with separator
        $value = preg_replace('/[\s_]+/u', $separator, $value);

        // Remove all non-alphanumeric characters except separator
        $value = preg_replace('/[^a-z0-9' . preg_quote($separator) . ']/u', '', $value);

        // Remove duplicate separators
        $value = preg_replace('/' . preg_quote($separator) . '+/u', $separator, $value);

        // Trim separators from ends
        return trim($value, $separator);
    }

    /**
     * Get the portion of a string before a given value
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Get the portion of a string after a given value
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }
}
