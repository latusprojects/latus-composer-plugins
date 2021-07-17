<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Composer;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Latus\ComposerPlugins\ApplicationBootstrapper;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\Plugins\Repositories\Contracts\ComposerRepositoryRepository;
use Latus\Plugins\Services\ComposerRepositoryService;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected Application $app;

    public function __construct(
        IOInterface $io,
        Composer $composer,
        $type = 'library',
        Filesystem $filesystem = null,
        BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);

        $app_bootstrapper = new ApplicationBootstrapper();
        $this->app = $app_bootstrapper->bootstrapApplication();
        $container = Container::getInstance();
    }

    protected function getRepositoryId(string $repositoryName): int
    {
        $repository_model = (new ComposerRepositoryService(app(ComposerRepositoryRepository::class)))->findByName($repositoryName);
        return $repository_model->id;
    }

    protected function getPackageAndProxyNames(string $packageName): array
    {
        return [
            'package_proxy_name' => (str_contains($packageName, '-latus-proxied'))
                ? $packageName : null,
            'package_name' => str_replace('-latus-proxied', '', $packageName),
        ];
    }

}