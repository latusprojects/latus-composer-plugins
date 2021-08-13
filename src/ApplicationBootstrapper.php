<?php

namespace Latus\ComposerPlugins;

use Illuminate\Foundation\Application;
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

        $app->bootstrapWith([
            \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
            \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
            \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
            \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
            \Illuminate\Foundation\Bootstrap\BootProviders::class,

        ]);

        $app->boot();

        $app->registerConfiguredProviders();

        $app->loadDeferredProviders();

        $this->app = $app;

        return $this->app;
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