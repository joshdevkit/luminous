<?php

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeFactoryCommand extends Command
{
    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Factory name is required.");
            $this->output->writeLine("Usage: php dev make:factory User");
            return 1;
        }

        $name = $args[0];
        
        // Ensure it ends with Factory
        if (!str_ends_with($name, 'Factory')) {
            $name .= 'Factory';
        }

        $path = $this->basePath . '/database/generators/' . $name . '.php';

        // Create directory if it doesn't exist
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            $this->output->error("Factory already exists: {$name}");
            return 1;
        }

        // Extract model name from factory name (remove Factory suffix)
        $modelName = str_replace('Factory', '', $name);
        
        $stub = $this->getStub($modelName);
        
        if (file_put_contents($path, $stub) === false) {
            $this->output->error("Failed to create factory: {$name}");
            return 1;
        }

        $this->output->success("Created factory: {$name}");
        $this->output->info("Location: database/generators/{$name}.php");
        
        return 0;
    }

    protected function getStub(string $modelName): string
    {
        return <<<PHP
<?php

namespace Database\Generators;

use Core\Database\Factory;
use App\Entities\\{$modelName};

/**
 * @extends Factory<\\App\\Entities\\{$modelName}>
 */
class {$modelName}Factory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            // Define your model attributes here using \$this->faker
            // Example:
            // 'name' => \$this->faker->name(),
            // 'email' => \$this->faker->unique()->safeEmail(),
            // 'description' => \$this->faker->paragraph(),
            // 'price' => \$this->faker->randomFloat(2, 10, 1000),
            // 'status' => \$this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Define a state for the factory.
     * 
     * Example:
     * public function active(): static
     * {
     *     return \$this->state([
     *         'status' => 'active',
     *     ]);
     * }
     * 
     * Or with closure:
     * public function withRandomPrice(): static
     * {
     *     return \$this->state(function () {
     *         return [
     *             'price' => \$this->faker->randomFloat(2, 100, 1000),
     *         ];
     *     });
     * }
     */
}

PHP;
    }
}