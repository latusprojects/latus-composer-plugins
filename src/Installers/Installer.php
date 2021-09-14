<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Installer\LibraryInstaller;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\Helpers\Paths;
use Latus\Laravel\Application;
use Latus\Laravel\Bootstrapper;
use Latus\Plugins\Providers\PluginsServiceProvider;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Providers\SettingsServiceProvider;
use Latus\Settings\Services\SettingService;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected Application $app;
    protected ComposerRepositoryService $composerRepositoryService;
    protected SettingService $settingService;

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

}