<?php

namespace Core\Database\Schema;

class MigrationCreator
{
    protected string $migrationsPath;

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    public function create(string $name): string
    {
        if ($this->exists($name)) {
            throw new \Exception("Migration already exists: {$name}");
        }

        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_{$name}.php";
        $path = "{$this->migrationsPath}/{$filename}";

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $className = $this->getClassName($name);
        $stub = $this->getStub($name);

        $content = str_replace('{{ClassName}}', $className, $stub);

        file_put_contents($path, $content);

        return $filename;
    }

    public function exists(string $name): bool
    {
        $pattern = "{$this->migrationsPath}/*_{$name}.php";
        return !empty(glob($pattern));
    }

    protected function getClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    protected function getStub(string $name): string
    {
        if (preg_match('/create_(\w+)_table/', $name, $matches)) {
            return $this->getCreateTableStub($matches[1]);
        }

        if (preg_match('/^(add|drop|modify)_(\w+)_(to|from|in)_(\w+)/', $name, $matches)) {
            return $this->getAlterTableStub($matches[4]);
        }

        return $this->getBlankStub();
    }

    protected function getCreateTableStub(string $table): string
    {
        return <<<PHP
<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class {{ClassName}} extends Migration
{
    public function up(): void
    {
        \$this->schema->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        \$this->schema->drop('{$table}');
    }
}

PHP;
    }

    protected function getAlterTableStub(string $table): string
    {
        return <<<PHP
<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class {{ClassName}} extends Migration
{
    public function up(): void
    {
        \$this->schema->alter('{$table}', function (Blueprint \$table) {
            // Add your column modifications here
        });
    }

    public function down(): void
    {
        \$this->schema->alter('{$table}', function (Blueprint \$table) {
            // Reverse your modifications here
        });
    }
}

PHP;
    }

    protected function getBlankStub(): string
    {
        return <<<PHP
<?php

use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class {{ClassName}} extends Migration
{
    public function up(): void
    {
        // Write your migration logic here
    }

    public function down(): void
    {
        // Reverse your migration logic here
    }
}

PHP;
    }
}
