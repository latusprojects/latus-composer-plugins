<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Composer;
use Composer\InstalledVersions;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\Helpers\Paths;
use Latus\Laravel\Application;
use Latus\Laravel\Bootstrapper;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Services\SettingService;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected Application|null $app = null;
    protected ComposerRepositoryService $composerRepositoryService;
    protected SettingService $settingService;

    public function __construct(
        IOInterface     $io,
        Composer        $composer,
                        $type = 'library',
        Filesystem      $filesystem = null,
        BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);

        $this->bootstrapInstaller(function () {
            $bootstrapper = new Bootstrapper(Paths::basePath());

            require_once Paths::basePath('vendor/autoload.php');

            $bootstrapper->build();

            $this->app = $bootstrapper->finish();

        });
    }

    /**
     * @return ComposerRepositoryService
     */
    public function getComposerRepositoryService(): ComposerRepositoryService
    {
        if (!isset($this->{'composerRepositoryService'})) {
            $this->composerRepositoryService = $this->app->make(ComposerRepositoryService::class);
        }

        return $this->composerRepositoryService;
    }

    /**
     * @return SettingService
     */
    public function getSettingService(): SettingService
    {
        if (!isset($this->{'settingService'})) {
            $this->settingService = $this->app->make(SettingService::class);
        }

        return $this->settingService;
    }

    protected function bootstrapInstaller(\Closure $closure)
    {
        if (InstalledVersions::isInstalled('latusprojects/latus-composer-plugins') && file_exists(Paths::basePath('artisan'))) {
            $closure();
        }
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