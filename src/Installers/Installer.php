<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\ComposerPlugins\Events\EventDispatcher;
use Latus\Helpers\Paths;
use Latus\Laravel\Application;
use Latus\Laravel\Bootstrapper;
use Latus\Plugins\Providers\PluginsServiceProvider;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Providers\SettingsServiceProvider;
use Latus\Settings\Services\SettingService;
use React\Promise\PromiseInterface;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected Application $app;
    protected ComposerRepositoryService $composerRepositoryService;
    protected SettingService $settingService;
    protected EventDispatcher $eventDispatcher;

    protected function bootApp()
    {
        $bootstrapper = new Bootstrapper(Paths::basePath());

        $bootstrapper->addBaseProviders([
            PluginsServiceProvider::class,
            SettingsServiceProvider::class
        ]);

        require_once Paths::basePath('vendor/autoload.php');

        $bootstrapper->build();

        $this->app = $bootstrapper->finish();
    }

    protected function getApp(): Application
    {
        if (!isset($this->{'app'})) {
            $this->bootApp();
        }

        return $this->app;
    }

    protected function getEventDispatcher(): EventDispatcher
    {
        if (!isset($this->{'eventDispatcher'})) {
            $this->eventDispatcher = $this->getApp()->make(EventDispatcher::class);
        }

        return $this->eventDispatcher;
    }

    /**
     * @return ComposerRepositoryService
     */
    public function getComposerRepositoryService(): ComposerRepositoryService
    {
        if (!isset($this->{'composerRepositoryService'})) {
            $this->composerRepositoryService = $this->getApp()->make(ComposerRepositoryService::class);
        }

        return $this->composerRepositoryService;
    }

    /**
     * @return SettingService
     */
    public function getSettingService(): SettingService
    {
        if (!isset($this->{'settingService'})) {
            $this->settingService = $this->getApp()->make(SettingService::class);
        }

        return $this->settingService;
    }

    protected function getRepositoryId(string $repositoryName): int
    {
        $repository_model = $this->getComposerRepositoryService()->findByName($repositoryName);

        if (!$repository_model) {
            $mainRepositoryName = $this->getSettingService()->findByKey('main_repository_name')->value;
            $repository_model = $this->getComposerRepositoryService()->findByName($mainRepositoryName);
        }

        return $repository_model->id;
    }

    public function cleanup($type, PackageInterface $package, PackageInterface $prevPackage = null): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }
        return parent::cleanup($type, $package, $prevPackage);
    }

    public function download(PackageInterface $package, PackageInterface $prevPackage = null): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }
        return parent::download($package, $prevPackage);
    }

    public function prepare($type, PackageInterface $package, PackageInterface $prevPackage = null): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }
        return parent::prepare($type, $package, $prevPackage);
    }

    protected function isRunningInLaravel(): bool
    {
        return defined('LARAVEL_START');
    }

}