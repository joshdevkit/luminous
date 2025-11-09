<?php

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeAlterCommand extends Command
{
    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Please provide a table name.");
            $this->output->writeLine("Usage: php dev make:alter <table_name> [--operation=add|modify|drop]");
            return 1;
        }

        $tableName = $args[0];
        $operation = $this->getOption($args, '--operation') ?? 'add';
        
        // Create migration name based on operation
        $migrationName = $this->createMigrationName($tableName, $operation);
        $className = $this->createClassName($migrationName);
        $timestamp = date('Ymd_His'); 
        $filename = "{$timestamp}_{$migrationName}.php";
        
        $migrationsPath = $this->getMigrationsPath();
        
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0755, true);
        }

        $filepath = $migrationsPath . '/' . $filename;

        if (file_exists($filepath)) {
            $this->output->error("Migration already exists: {$filename}");
            return 1;
        }

        $template = $this->getTemplate($className, $tableName, $operation);
        
        if (file_put_contents($filepath, $template) === false) {
            $this->output->error("Failed to create migration file.");
            return 1;
        }

        $this->output->success("Created migration: {$filename}");
        $this->output->info("File location: {$filepath}");
        
        return 0;
    }

    protected function createMigrationName(string $tableName, string $operation): string
    {
        return match($operation) {
            'add' => "add_columns_to_{$tableName}_table",
            'modify' => "modify_{$tableName}_table",
            'drop' => "drop_columns_from_{$tableName}_table",
            'rename' => "rename_columns_in_{$tableName}_table",
            default => "alter_{$tableName}_table"
        };
    }

    protected function createClassName(string $migrationName): string
    {
        return str_replace('_', '', ucwords($migrationName, '_'));
    }

    protected function getOption(array $args, string $option): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $option . '=')) {
                return substr($arg, strlen($option) + 1);
            }
        }
        return null;
    }

    protected function getTemplate(string $className, string $tableName, string $operation): string
    {
        $content = $this->getOperationTemplate($tableName, $operation);
        
        return <<<PHP
<?php


use Core\Database\Schema\Migration;
use Core\Database\Schema\Blueprint;

class {$className} extends Migration
{
    public function up(): void
    {
{$content['up']}
    }

    public function down(): void
    {
{$content['down']}
    }
}

PHP;
    }

    protected function getOperationTemplate(string $tableName, string $operation): array
    {
        return match($operation) {
            'add' => $this->getAddTemplate($tableName),
            'modify' => $this->getModifyTemplate($tableName),
            'drop' => $this->getDropTemplate($tableName),
            'rename' => $this->getRenameTemplate($tableName),
            default => $this->getGeneralTemplate($tableName)
        };
    }

    protected function getAddTemplate(string $tableName): array
    {
        return [
            'up' => <<<PHP
        // Add new columns to {$tableName}
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->string('column_name')->nullable();
            // Add more columns here
        });

        // Add indexes (optional)
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->index('column_name');
            // \$table->unique('column_name');
        });
PHP,
            'down' => <<<PHP
        // Drop indexes
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->dropIndex('{$tableName}_column_name_index');
        });

        // Drop columns
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn(['column_name']);
        });
PHP
        ];
    }

    protected function getModifyTemplate(string $tableName): array
    {
        return [
            'up' => <<<PHP
        // Modify existing columns in {$tableName}
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->modifyColumn('column_name', 'VARCHAR', ['length' => 255]);
            // Modify more columns here
        });
PHP,
            'down' => <<<PHP
        // Revert column modifications
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->modifyColumn('column_name', 'VARCHAR', ['length' => 100]);
            // Revert more columns here
        });
PHP
        ];
    }

    protected function getDropTemplate(string $tableName): array
    {
        return [
            'up' => <<<PHP
        // Drop columns from {$tableName}
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn(['column_name']);
            // Drop more columns here
        });
PHP,
            'down' => <<<PHP
        // Re-add dropped columns
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->string('column_name')->nullable();
            // Re-add more columns here
        });
PHP
        ];
    }

    protected function getRenameTemplate(string $tableName): array
    {
        return [
            'up' => <<<PHP
        // Rename columns in {$tableName}
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->renameColumn('old_name', 'new_name');
            // Rename more columns here
        });
PHP,
            'down' => <<<PHP
        // Revert column renames
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            \$table->renameColumn('new_name', 'old_name');
            // Revert more columns here
        });
PHP
        ];
    }

    protected function getGeneralTemplate(string $tableName): array
    {
        return [
            'up' => <<<PHP
        // Alter {$tableName} table
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            // Add your alterations here:
            
            // Add columns
            // \$table->string('new_column')->nullable();
            
            // Modify columns
            // \$table->modifyColumn('existing_column', 'VARCHAR', ['length' => 255]);
            
            // Rename columns
            // \$table->renameColumn('old_name', 'new_name');
            
            // Add indexes
            // \$table->index('column_name');
            // \$table->unique('column_name');
            
            // Add foreign keys
            // \$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
PHP,
            'down' => <<<PHP
        // Revert alterations to {$tableName}
        \$this->schema->alter('{$tableName}', function (Blueprint \$table) {
            // Reverse your alterations here:
            
            // Drop foreign keys
            // \$table->dropForeign('{$tableName}_user_id_index');
            
            // Drop indexes
            // \$table->dropIndex('{$tableName}_column_name_index');
            // \$table->dropUnique('{$tableName}_column_name_unique');
            
            // Drop columns
            // \$table->dropColumn(['new_column']);
            
            // Revert renames
            // \$table->renameColumn('new_name', 'old_name');
        });
PHP
        ];
    }
}