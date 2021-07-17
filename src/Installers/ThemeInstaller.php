<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use InvalidArgumentException;
use React\Promise\PromiseInterface;

class ThemeInstaller extends Installer
{

    /**
     * @inheritDoc
     */
    public function supports($packageType)
    {
        // TODO: Implement supports() method.
    }

    /**
     * @inheritDoc
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // TODO: Implement isInstalled() method.
    }

    /**
     * @inheritDoc
     */
    public function download(PackageInterface $package, PackageInterface $prevPackage = null)
    {
        // TODO: Implement download() method.
    }

    /**
     * @inheritDoc
     */
    public function prepare($type, PackageInterface $package, PackageInterface $prevPackage = null)
    {
        // TODO: Implement prepare() method.
    }

    /**
     * @inheritDoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // TODO: Implement install() method.
    }

    /**
     * @inheritDoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        // TODO: Implement update() method.
    }

    /**
     * @inheritDoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // TODO: Implement uninstall() method.
    }

    /**
     * @inheritDoc
     */
    public function cleanup($type, PackageInterface $package, PackageInterface $prevPackage = null)
    {
        // TODO: Implement cleanup() method.
    }

    /**
     * @inheritDoc
     */
    public function getInstallPath(PackageInterface $package)
    {
        // TODO: Implement getInstallPath() method.
    }
}