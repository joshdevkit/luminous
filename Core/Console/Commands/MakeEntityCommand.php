<?php

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Database\Schema\MigrationCreator;
use Core\Support\Str;

class MakeEntityCommand extends Command
{
    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Entity name is required.");
            $this->output->writeLine("Usage: php cli make:entity User [-m]");
            return 1;
        }

        $name = $args[0];
        $withMigration = in_array('-m', $args);

        // Create the entity file
        $filename = $this->createEntity($name);

        if ($filename) {
            $this->output->success("Created entity: {$filename}");
        } else {
            return 1; // Exit with failure because entity exists
        }
        // If -m flag is present, create a plural migration table
        if ($withMigration) {
            $creator = new MigrationCreator($this->getMigrationsPath());

            $table = Str::plural(Str::snake($name)); // 👈 now pluralized
            $migrationName = "create_{$table}_table";

            $migrationFile = $creator->create($migrationName);
            $this->output->writeLine("Created migration: {$migrationFile}");
        }

        return 0;
    }

    protected function createEntity(string $name): ?string
    {
        $path = base_path("app/Entities");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = "{$path}/{$name}.php";

        // Check if entity already exists
        if (file_exists($filename)) {
            $this->output->warning("Entity '{$name}' already exists. Skipping.");
            return null; // Prevent creation
        }

        $stub = $this->getStub($name);
        file_put_contents($filename, $stub);

        return $filename;
    }


    protected function getStub(string $name): string
    {
        $table = Str::plural(Str::snake($name));
        return <<<PHP
<?php

namespace App\Entities;

use Core\Database\Model;

class {$name} extends Model
{
    protected \$entities = [];
}

PHP;
    }
}
