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
use Latus\Permissions\Providers\LatusPermissionsServiceProvider;
use Latus\Plugins\Providers\PluginsServiceProvider;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Providers\SettingsServiceProvider;
use Latus\Settings\Services\SettingService;
use Latus\UI\Providers\UIServiceProvider;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected Application|null $app = null;

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

            $bootstrapper->addBaseProviders([
                SettingsServiceProvider::class,
                LatusPermissionsServiceProvider::class,
                UIServiceProvider::class,
                PluginsServiceProvider::class
            ]);

            require_once Paths::basePath('vendor/autoload.php');

            $bootstrapper->build();

            $this->app = $bootstrapper->finish();

        });
    }

    protected function bootstrapInstaller(\Closure $closure)
    {
        if (InstalledVersions::isInstalled('latusprojects/latus-composer-plugins') && file_exists(Paths::basePath('artisan'))) {
            $closure();
        }
    }

    //TODO: Refactor this, as its does not match the actual program-pattern
    protected function getRepositoryId(string $repositoryName): int
    {
        $repository_model = app(ComposerRepositoryService::class)->findByName($repositoryName);

        if (!$repository_model) {
            $mainRepositoryName = app(SettingService::class)->findByKey('main_repository_name')->value;
            $repository_model = app(ComposerRepositoryService::class)->findByName($mainRepositoryName);
        }

        return $repository_model->id;
    }

}