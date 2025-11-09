<?php

namespace Core\Routing;

use Core\Contracts\Routing\ArraySerializable;
use Core\Contracts\Routing\ModelAdapterInterface;
use Core\Support\ErrorBag;
use Core\Http\Response;
use Core\Routing\Traits\RegeneratesSession;
use InvalidArgumentException;
use ReflectionClass;

abstract class BaseForm implements ArraySerializable
{
    use RegeneratesSession;

    protected array $data;
    protected ErrorBag $errors;
    protected array $hidden = [];

    // Add these properties to avoid deprecated warnings
    public ?string $_token = null;
    public ?string $_method = null;

    public function __construct(array $data = [])
    {
        $this->errors = new ErrorBag();

        $this->data = $data;

        // Store CSRF token and method if present
        $this->_token = $data['_token'] ?? null;
        $this->_method = $data['_method'] ?? null;

        // Check for CSRF token in header for JSON requests
        if (!$this->_token && function_exists('request')) {
            $this->_token = request()->getHeader('X-CSRF-TOKEN');
        }

        $this->assignProperties($data);
    }

    /**
     * Check if the request is JSON
     */
    public function isJsonRequest(): bool
    {
        if (!function_exists('request')) {
            return false;
        }

        $request = request();

        // Check Content-Type header
        $contentType = $request->getHeader('Content-Type') ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            return true;
        }

        // Check Accept header
        $accept = $request->getHeader('Accept') ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        // Check if request expects JSON response
        if (method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the request is AJAX
     */
    public function isAjaxRequest(): bool
    {
        if (!function_exists('request')) {
            return false;
        }

        $request = request();
        $xmlHttpRequest = $request->getHeader('X-Requested-With') ?? '';

        return strtolower($xmlHttpRequest) === 'xmlhttprequest';
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrf(): bool
    {
        // DON'T bypass if function doesn't exist - throw exception instead
        if (!function_exists('csrf_token')) {
            throw new \RuntimeException(
                'CSRF protection is not configured. The csrf_token() helper function is not available.'
            );
        }

        $sessionToken = csrf_token();

        if (empty($this->_token)) {
            $this->errors->add('_token', 'CSRF token is missing.');
            return false;
        }

        if (!hash_equals($sessionToken, $this->_token)) {
            $this->errors->add('_token', 'CSRF token mismatch.');
            return false;
        }

        return true;
    }

    /**
     * Validate HTTP method spoofing
     */
    public function validateMethod(string $expectedMethod): bool
    {
        $method = request()->getMethod() ?? 'GET';

        // If _method is present, use it (for PUT, PATCH, DELETE)
        if ($this->_method) {
            $method = strtoupper($this->_method);
        }

        $expectedMethod = strtoupper($expectedMethod);

        if ($method !== $expectedMethod) {
            $this->errors->add('_method', "Method mismatch. Expected {$expectedMethod}, got {$method}.");
            return false;
        }

        return true;
    }


    /**
     * Sync property values back to data array
     */
    protected function syncDataFromProperties(): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $prop) {
            $name = $prop->getName();
            if (
                $name !== 'data' && $name !== 'errors' && $name !== 'hidden'
                && $name !== '_token' && $name !== '_method' && !$prop->isStatic()
            ) {
                $this->data[$name] = $this->{$name} ?? null;
            }
        }
    }

    /**
     * Define validation rules for the form
     * Override this in your form classes
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Custom error messages
     * Override this in your form classes
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Custom attribute names for error messages
     * Override this in your form classes
     */
    protected function attributes(): array
    {
        return [];
    }

    /**
     * Validate the form data (includes CSRF by default)
     */
    public function validate(bool $checkCsrf = true): bool
    {
        if ($checkCsrf && !$this->validateCsrf()) {
            return false;
        }

        $rules = $this->rules();
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $field => $ruleString) {
            $value = property_exists($this, $field) ? $this->{$field} : $this->get($field);
            $ruleList = is_string($ruleString) ? explode('|', $ruleString) : $ruleString;

            foreach ($ruleList as $rule) {
                $this->validateRule($field, $value, $rule);

                // Stop further rules if this field already has an error
                if ($this->errors->has($field)) {
                    break;
                }
            }
        }

        return !$this->errors->hasAny();
    }

    /**
     * Validate a single rule
     */
    protected function validateRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $method = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    /**
     * Validation Rules
     */
    protected function validateRequired(string $field, mixed $value, array $params = []): void
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->addError($field, 'required');
        }
    }

    protected function validateEmail(string $field, mixed $value, array $params = []): void
    {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
        }
    }

    protected function validateMin(string $field, mixed $value, array $params = []): void
    {
        $min = (int) ($params[0] ?? 0);

        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, 'min', ['min' => $min]);
        }
    }

    protected function validateMax(string $field, mixed $value, array $params = []): void
    {
        $max = (int) ($params[0] ?? 0);

        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, 'max', ['max' => $max]);
        }
    }

    protected function validateConfirmed(string $field, mixed $value, array $params = []): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = property_exists($this, $confirmField)
            ? $this->{$confirmField}
            : $this->get($confirmField);

        if ($value !== $confirmValue) {
            $this->addError($field, 'confirmed');
        }
    }

    protected function validateUnique(string $field, mixed $value, array $params = []): void
    {
        if (empty($value)) {
            return;
        }

        if (empty($params[0])) {
            return;
        }

        $table = $params[0];
        $column = $params[1] ?? $field;
        $ignoreId = $params[2] ?? null;
        $ignoreColumn = $params[3] ?? 'id';

        if (function_exists('table')) {
            $query = table($table)->where($column, $value);

            if ($ignoreId) {
                $query->where($ignoreColumn, '!=', $ignoreId);
            }

            if ($query->count() > 0) {
                $this->addError($field, 'unique');
            }
            return;
        }

        if (class_exists($table)) {
            $query = $table::query()->where($column, $value);

            if ($ignoreId) {
                $query->where($ignoreColumn, '!=', $ignoreId);
            }

            if ($query->count() > 0) {
                $this->addError($field, 'unique');
            }
        }
    }

    protected function validateExists(string $field, mixed $value, array $params = []): void
    {
        if (empty($params[0])) {
            return;
        }
        $tableName = $params[0];
        $column = $params[1] ?? $field;
        $query = table($tableName)->where($column, $value);
        if ($query->count() == 0) {
            $this->addError($field, 'exists');
        }
    }

    protected function validateNumeric(string $field, mixed $value, array $params = []): void
    {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, 'numeric');
        }
    }

    protected function validateUrl(string $field, mixed $value, array $params = []): void
    {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
        }
    }

    protected function validateIn(string $field, mixed $value, array $params = []): void
    {
        if (!empty($value) && !in_array($value, $params)) {
            $this->addError($field, 'in', ['values' => implode(', ', $params)]);
        }
    }

    protected function validateArray(string $field, mixed $value, array $params = []): void
    {
        if (!empty($value) && !is_array($value)) {
            $this->addError($field, 'array');
        }
    }

    protected function validateFile(string $field, mixed $value, array $params = []): void
    {
        if (empty($value)) {
            return;
        }

        // Check if it's a valid uploaded file
        if (!is_array($value) || !isset($value['tmp_name']) || !isset($value['error'])) {
            $this->addError($field, 'file');
            return;
        }

        // Check for upload errors
        if ($value['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'file');
        }
    }

    protected function validateImage(string $field, mixed $value, array $params = []): void
    {
        if (empty($value)) {
            return;
        }

        // Check if it's a valid uploaded file
        if (!is_array($value) || !isset($value['tmp_name']) || !isset($value['error'])) {
            $this->addError($field, 'image');
            return;
        }

        // Check for upload errors
        if ($value['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'image');
            return;
        }

        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $value['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->addError($field, 'image');
        }
    }

    protected function validateMimes(string $field, mixed $value, array $params = []): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        // Check if it's a valid uploaded file
        if (!is_array($value) || !isset($value['tmp_name']) || !isset($value['error'])) {
            $this->addError($field, 'mimes', ['values' => implode(', ', $params)]);
            return;
        }

        // Check for upload errors
        if ($value['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'mimes', ['values' => implode(', ', $params)]);
            return;
        }

        // Get actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $value['tmp_name']);
        finfo_close($finfo);

        // Map extensions to MIME types
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];

        $allowedMimes = [];
        foreach ($params as $ext) {
            if (isset($mimeMap[$ext])) {
                $allowedMimes[] = $mimeMap[$ext];
            }
        }

        if (!in_array($mimeType, $allowedMimes)) {
            $this->addError($field, 'mimes', ['values' => implode(', ', $params)]);
        }
    }

    protected function validateMaxFileSize(string $field, mixed $value, array $params = []): void
    {
        if (empty($value) || empty($params[0])) {
            return;
        }

        // Check if it's a valid uploaded file
        if (!is_array($value) || !isset($value['size'])) {
            return;
        }

        $maxSize = (int) $params[0]; // Size in kilobytes
        $fileSizeKb = $value['size'] / 1024;

        if ($fileSizeKb > $maxSize) {
            $this->addError($field, 'max_file_size', ['max' => $maxSize]);
        }
    }

    protected function validateJson(string $field, mixed $value, array $params = []): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, 'json');
            return;
        }

        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field, 'json');
        }
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $rule, array $replacements = []): void
    {
        $messages = $this->messages();
        $attributes = $this->attributes();

        $attribute = $attributes[$field] ?? str_replace('_', ' ', $field);
        $message = $messages["{$field}.{$rule}"]
            ?? $messages[$rule]
            ?? $this->getDefaultMessage($rule);

        $message = str_replace(':attribute', $attribute, $message);

        foreach ($replacements as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }

        $this->errors->add($field, $message);
    }

    /**
     * Get default error message for a rule
     */
    protected function getDefaultMessage(string $rule): string
    {
        return match ($rule) {
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The :attribute does not exist.',
            'numeric' => 'The :attribute must be a number.',
            'url' => 'The :attribute must be a valid URL.',
            'in' => 'The selected :attribute is invalid.',
            'array' => 'The :attribute must be an array.',
            'file' => 'The :attribute must be a valid file.',
            'image' => 'The :attribute must be a valid image (jpeg, png, gif, webp, svg).',
            'mimes' => 'The :attribute must be a file of type: :values.',
            'max_file_size' => 'The :attribute may not be larger than :max kilobytes.',
            'json' => 'The :attribute must be a valid JSON string.',
            default => 'The :attribute is invalid.',
        };
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return $this->errors->hasAny();
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Get validation errors
     */
    public function errors(): ErrorBag
    {
        return $this->errors;
    }

    /**
     * Validate and redirect back with errors if fails
     */
    public function validateOrFail(bool $checkCsrf = true): void
    {
        if (!$this->validate($checkCsrf)) {
            // Return JSON response for JSON/AJAX requests
            if ($this->isJsonRequest() || $this->isAjaxRequest()) {
                $response = Response::json([
                    'success' => false,
                    'errors' => $this->errors->all(),
                    'message' => 'Validation failed.'
                ], 422);
                $response->send();
                exit;
            }

            // Return HTML response for regular requests
            $response = Response::back()->withErrors($this->errors->all());
            $response->send();
            // dd($response);
            exit;
        }
    }

    /**
     * Assigns known properties only.
     */
    protected function assignProperties(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && $key !== '_token' && $key !== '_method') {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Returns only defined public/protected form properties, excluding hidden fields.
     */
    public function toArray(): array
    {
        $output = [];
        $reflection = new ReflectionClass($this);

        // Internal properties that should never be included
        $internalProps = ['data', 'errors', 'hidden', '_token', '_method'];

        foreach ($reflection->getProperties() as $prop) {
            $name = $prop->getName();

            // Skip internal properties, static properties, and hidden fields
            if (in_array($name, $internalProps) || $prop->isStatic() || in_array($name, $this->hidden)) {
                continue;
            }

            $output[$name] = $this->{$name} ?? null;
        }

        return $output;
    }

    /**
     * Maps this form to a model instance using a model adapter.
     */
    public function toModel(object|string $model, ?ModelAdapterInterface $adapter = null): object
    {
        // Validate before creating model
        $this->validateOrFail();

        $adapter ??= new class implements ModelAdapterInterface {
            public function fill(object $model, array $data): object
            {
                if (!method_exists($model, 'fill')) {
                    throw new InvalidArgumentException(sprintf(
                        'Model [%s] must implement fill() method.',
                        get_class($model)
                    ));
                }

                $model->fill($data);
                return $model;
            }

            public function create(string $class, array $data): object
            {
                if (!class_exists($class)) {
                    throw new InvalidArgumentException("Model class [{$class}] not found.");
                }

                return new $class($data);
            }
        };

        // Get data without hidden fields
        $data = $this->toArray();

        return match (true) {
            is_object($model) => $adapter->fill($model, $data),
            is_string($model) => $adapter->create($model, $data),
            default => throw new InvalidArgumentException('Invalid model type for toModel().')
        };
    }

    /**
     * Helpers for filtering data.
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get all data excluding hidden fields
     */
    public function validated(): array
    {
        return $this->toArray();
    }

    /**
     * Get the hidden fields array
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set hidden fields
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Add a field to hidden
     */
    public function addHidden(string $field): static
    {
        if (!in_array($field, $this->hidden)) {
            $this->hidden[] = $field;
        }
        return $this;
    }

    /**
     * Remove a field from hidden
     */
    public function removeHidden(string $field): static
    {
        $this->hidden = array_filter($this->hidden, fn($h) => $h !== $field);
        return $this;
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken(): ?string
    {
        return $this->_token;
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): ?string
    {
        return $this->_method;
    }
}
