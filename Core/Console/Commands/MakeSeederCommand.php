<?php

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeSeederCommand extends Command
{
    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Seeder name is required.");
            $this->output->writeLine("Usage: php dev make:seeder UserSeeder");
            return 1;
        }

        $name = $args[0];
        
        // Ensure it ends with Seeder
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = $this->basePath . '/database/dummy/' . $name . '.php';

        // Create directory if it doesn't exist
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->output->error("Seeder already exists: {$name}");
            return 1;
        }

        $stub = $this->getStub($name);
        
        if (file_put_contents($path, $stub) === false) {
            $this->output->error("Failed to create seeder: {$name}");
            return 1;
        }

        $this->output->success("Created seeder: {$name}");
        $this->output->info("Location: database/dummy/{$name}.php");
        
        return 0;
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

namespace Database\Dummy;

use Core\Database\Generators\Seeder;

class {$name} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \$this->info("Seeding {$name}...");
        
        // Use factories to create records
        // Example:
        // UserFactory::new()->count(10)->create();
        // \$this->success("Created 10 users");
        
        // Or call other seeders
        // \$this->call([
        //     AnotherSeeder::class,
        // ]);
    }
}

PHP;
    }
}