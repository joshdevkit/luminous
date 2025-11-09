<?php

namespace Core\Console\Commands;

use Core\Console\OutputFormatter;

class MakeControllerCommand
{
    protected OutputFormatter $output;

    public function __construct($app, string $basePath, OutputFormatter $output)
    {
        $this->output = $output;
    }

    public function execute(array $args): int
    {
        if (empty($args[0])) {
            $this->output->error("Controller name is required!");
            return 1;
        }

        $input = $args[0];

        // Split path into folder + class name
        $pathParts = explode('/', $input);
        $className = array_pop($pathParts);

        $folderPath = implode('/', $pathParts);
        $namespacePath = implode('\\', $pathParts);

        // Use app_path() from your global helper
        $directory = app_path('Controllers' . ($folderPath ? '/' . $folderPath : ''));

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = "{$directory}/{$className}.php";

        if (file_exists($filePath)) {
            $this->output->error("Controller already exists: {$filePath}");
            return 1;
        }

        $namespace = 'App\\Controllers' . ($namespacePath ? '\\' . $namespacePath : '');

$stub = <<<PHP
<?php

namespace {$namespace};

use Core\Routing\Controller;

class {$className} extends Controller
{
   //
}
PHP;

        file_put_contents($filePath, $stub);

        $this->output->success("Controller created: {$filePath}");
        return 0;
    }
}
