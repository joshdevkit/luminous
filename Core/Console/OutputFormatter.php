<?php

namespace Core\Console;

class OutputFormatter
{
    // ANSI color codes
    protected array $colors = [
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m",
    ];

    /**
     * Write a plain line of text.
     */
    public function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Alias for line() to maintain compatibility.
     */
    public function writeLine(string $message = ''): void
    {
        $this->line($message);
    }

    public function success(string $message): void
    {
        $this->coloredLine("✓ {$message}", 'green');
    }

    public function error(string $message): void
    {
        $this->coloredLine("✗ ERROR: {$message}", 'red');
    }

    public function warning(string $message): void
    {
        $this->coloredLine("⚠️  WARNING: {$message}", 'yellow');
    }

    public function info(string $message): void
    {
        $this->coloredLine("ℹ️  {$message}", 'cyan');
    }

    public function comment(string $message): void
    {
        $this->coloredLine("# {$message}", 'magenta');
    }

    public function hr(string $char = '-', int $length = 60): void
    {
        echo str_repeat($char, $length) . "\n";
    }

    public function block(string $message, string $type = 'info'): void
    {
        $border = str_repeat('=', strlen($message) + 4);
        $prefix = match ($type) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '',
        };

        $color = match ($type) {
            'success' => 'green',
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'cyan',
            default => 'white',
        };

        $this->coloredLine("\n{$border}", $color);
        $this->coloredLine("{$prefix}  {$message}", $color);
        $this->coloredLine("{$border}\n", $color);
    }

    protected function coloredLine(string $message, string $color = 'white'): void
    {
        $colorCode = $this->colors[$color] ?? $this->colors['white'];
        $reset = $this->colors['reset'];
        echo "{$colorCode}{$message}{$reset}\n";
    }
}
