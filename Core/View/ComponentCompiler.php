<?php

declare(strict_types=1);

namespace Core\View;

/**
 * Compiles component tags into PHP code.
 * Supports both self-closing and paired component tags.
 */
class ComponentCompiler
{
    protected string $componentNamespace = 'App\\View\\Components\\';
    protected string $componentPath = '';

    public function __construct(string $componentPath = '')
    {
        $this->componentPath = $componentPath ?: base_path('app/View/Components');
    }

    /**
     * Compile component tags in the given content.
     */
    public function compile(string $contents): string
    {
        // Compile self-closing components: <x-alert />
        $contents = $this->compileSelfClosingComponents($contents);

        // Compile opening and closing component tags: <x-alert>...</x-alert>
        $contents = $this->compileOpeningComponents($contents);
        $contents = $this->compileClosingComponents($contents);

        return $contents;
    }

    /**
     * Compile self-closing component tags.
     */
    protected function compileSelfClosingComponents(string $contents): string
    {
        $pattern = '/<\s*x-([a-zA-Z0-9\-]+)\s*([^>]*?)\/>/';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $matches[2];

            return $this->compileComponentTag($component, $attributes, true);
        }, $contents);
    }

    /**
     * Compile opening component tags.
     */
    protected function compileOpeningComponents(string $contents): string
    {
        $pattern = '/<\s*x-([a-zA-Z0-9\-]+)\s*([^>]*?)>/';

        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = $matches[2];

            return $this->compileComponentTag($component, $attributes, false);
        }, $contents);
    }

    /**
     * Compile closing component tags.
     */
    protected function compileClosingComponents(string $contents): string
    {
        $pattern = '/<\/\s*x-([a-zA-Z0-9\-]+)\s*>/';

        return preg_replace_callback($pattern, function ($matches) {
            return "<?php echo \$__componentInstance->render(); unset(\$__componentInstance); ?>";
        }, $contents);
    }

    /**
     * Compile a single component tag into PHP code.
     */
    protected function compileComponentTag(string $component, string $attributesString, bool $selfClosing): string
    {
        $class = $this->componentClass($component);
        $attributes = $this->parseAttributes($attributesString);

        $attributesArray = $this->buildAttributesArray($attributes);

        if ($selfClosing) {
            return "<?php \$__componentInstance = new \\{$class}({$attributesArray}); "
                . "\$__componentInstance->setSlot(''); "
                . "echo \$__componentInstance->render(); "
                . "unset(\$__componentInstance); ?>";
        }

        // For paired tags, capture content between opening and closing tags
        return "<?php \$__componentInstance = new \\{$class}({$attributesArray}); ob_start(); ?>";
    }

    /**
     * Convert component name to class name.
     * Examples: app-layout-guest => AppLayoutGuest
     */
    protected function componentClass(string $component): string
    {
        $parts = explode('-', $component);
        $className = implode('', array_map('ucfirst', $parts));

        return $this->componentNamespace . $className;
    }

    /**
     * Parse attributes string into array.
     */
    protected function parseAttributes(string $attributesString): array
    {
        $attributes = [];
        $attributesString = trim($attributesString);

        if (empty($attributesString)) {
            return $attributes;
        }

        // Match attribute="value" or :attribute="$variable"
        preg_match_all('/([a-zA-Z0-9\-_:]+)\s*=\s*(["\'])([^\2]*?)\2/', $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = $match[3];

            // Handle dynamic attributes (prefixed with :)
            if (strpos($name, ':') === 0) {
                $name = substr($name, 1);
                $attributes[$name] = ['dynamic' => true, 'value' => $value];
            } else {
                $attributes[$name] = ['dynamic' => false, 'value' => $value];
            }
        }

        return $attributes;
    }

    /**
     * Build PHP array string for attributes.
     */
    protected function buildAttributesArray(array $attributes): string
    {
        if (empty($attributes)) {
            return '[]';
        }

        $parts = [];
        foreach ($attributes as $name => $data) {
            $key = var_export($name, true);
            if ($data['dynamic']) {
                // Dynamic attribute: use the variable directly
                $parts[] = "{$key} => {$data['value']}";
            } else {
                // Static attribute: export as string
                $value = var_export($data['value'], true);
                $parts[] = "{$key} => {$value}";
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }
}