<?php

namespace Latus\ComposerPlugins;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Latus\Plugins\Providers\PluginsServiceProvider;
use Latus\Repositories\RepositoriesServiceProvider;
use Latus\Settings\SettingsServiceProvider;
use Latus\Helpers\Paths;

class ApplicationBootstrapper
{
    protected Application|null $app = null;
    protected string|null $base_path = null;

    public function __construct(Application $app = null)
    {
        if ($app) {
            $this->app = $app;
        }
    }

    public function bootstrapApplication(string $basePath = null): Application
    {
        if ($this->app) {
            return $this->app;
        }

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        if ($basePath) {
            $this->base_path = $basePath;
        }

        $app = new Application($this->getBasePath());

        $this->app = $app;

        $this->requireLaravelHelperFunctions();

        $this->bindAppSingletons();

        $this->app->make(Kernel::class)->bootstrap();

        $this->registerProviders();

        return $this->app;
    }

    protected function requireLaravelHelperFunctions()
    {

        $foundation_helpers = $this->getBasePath(
            'vendor' . DIRECTORY_SEPARATOR .
            'laravel' . DIRECTORY_SEPARATOR .
            'framework' . DIRECTORY_SEPARATOR .
            'src' . DIRECTORY_SEPARATOR .
            'Illuminate' . DIRECTORY_SEPARATOR .
            'Foundation' . DIRECTORY_SEPARATOR .
            'helpers.php'
        );

        require $foundation_helpers;

        $support_helpers = $this->getBasePath(
            'vendor' . DIRECTORY_SEPARATOR .
            'laravel' . DIRECTORY_SEPARATOR .
            'framework' . DIRECTORY_SEPARATOR .
            'src' . DIRECTORY_SEPARATOR .
            'Illuminate' . DIRECTORY_SEPARATOR .
            'Support' . DIRECTORY_SEPARATOR .
            'helpers.php'
        );

        require $support_helpers;

        $collection_helpers = $this->getBasePath(
            'vendor' . DIRECTORY_SEPARATOR .
            'laravel' . DIRECTORY_SEPARATOR .
            'framework' . DIRECTORY_SEPARATOR .
            'src' . DIRECTORY_SEPARATOR .
            'Illuminate' . DIRECTORY_SEPARATOR .
            'Collections' . DIRECTORY_SEPARATOR .
            'helpers.php'
        );

        require $collection_helpers;

    }

    protected function registerProviders()
    {
        $this->app->register(PluginsServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(RepositoriesServiceProvider::class);
    }

    protected function bindAppSingletons()
    {
        $this->app->singleton(
            HttpKernelContract::class,
            HttpKernel::class
        );

        $this->app->singleton(
            ConsoleKernelContract::class,
            ConsoleKernel::class
        );

        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class
        );
    }

    public function getBasePath(string $path = ''): string
    {
        if ($this->base_path) {
            return $this->base_path . $path;
        }

        return $this->base_path = Paths::basePath($path);
    }

    public function getApp(): Application
    {
        return $this->app;
    }

}