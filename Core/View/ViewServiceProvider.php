<?php

namespace Core\View;

use Core\Contracts\View\ViewFactoryInterface;
use Core\Providers\ServiceProvider;
use Core\View\Engines\PhpEngine;
use Core\View\Engines\TemplateEngine;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerViewFinder();
        $this->registerEngines();
        $this->registerViewFactory();
    }

    protected function registerViewFinder(): void
    {
        $this->app->singleton('view.finder', function ($c) {
            $app = $c->get('app');
            $paths = [$app->basePath('resources/views')];
            
            return new ViewFinder($paths);
        });
    }

    protected function registerEngines(): void
    {
        // Register PHP Engine
        $this->app->singleton('view.engine.php', function () {
            return new PhpEngine();
        });

        // Register Template Engine
        $this->app->singleton('view.engine.template', function ($c) {
            $app = $c->get('app');
            $cachePath = $app->basePath('storage/views');
            
            return new TemplateEngine($cachePath);
        });
    }

    protected function registerViewFactory(): void
    {
        $this->app->singleton('view', function ($c) {
            $finder = $c->get('view.finder');
            $engine = $c->get('view.engine.template'); // Use template engine by default
            
            return new ViewFactory($finder, $engine);
        });

        $this->app->singleton(ViewFactoryInterface::class, function ($c) {
            return $c->get('view');
        });
    }

    public function boot(): void
    {
        // Share common data with all views
        $view = $this->app->get('view');
        $view->share('app_name', config('app.name', 'Framework'));
        $view->share('app_url', config('app.url', 'http://localhost'));
    }
}