<?php

declare(strict_types=1);

namespace Core\View;

/**
 * Base class for template components.
 * Components can accept props and render content with slots.
 */
abstract class Component
{
    protected array $attributes = [];
    protected string $slot = '';

    /**
     * Create a new component instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Set the slot content for this component.
     */
    public function setSlot(string $content): void
    {
        $this->slot = $content;
    }

    /**
     * Get the view / template path that should be used.
     */
    abstract public function render(): string;

    /**
     * Get data that should be passed to the view.
     */
    public function data(): array
    {
        return [
            'slot' => $this->slot,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Get an attribute value with optional default.
     */
    protected function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if an attribute exists.
     */
    protected function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}