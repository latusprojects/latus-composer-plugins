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
use Latus\Plugins\Composer\ProxyPackage;
use Latus\Plugins\Repositories\Contracts\ComposerRepositoryRepository;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Providers\SettingsServiceProvider;
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
                UIServiceProvider::class
            ]);

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

    protected function getRepositoryId(string $repositoryName): int
    {
        $repository_model = (new ComposerRepositoryService(app(ComposerRepositoryRepository::class)))->findByName($repositoryName);
        return $repository_model->id;
    }

    protected function getPackageAndProxyNames(string $packageName): array
    {
        return [
            'package_proxy_name' => (str_contains($packageName, ProxyPackage::PREFIX))
                ? $packageName : null,
            'package_name' => str_replace(ProxyPackage::PREFIX, '', $packageName),
        ];
    }

}