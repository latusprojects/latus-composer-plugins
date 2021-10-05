<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Illuminate\Support\Facades\App;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\ComposerPlugins\Events\EventDispatcher;
use Latus\ComposerPlugins\Events\PackageSpecifiedListenersCaller;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Services\SettingService;
use React\Promise\PromiseInterface;

abstract class Installer extends LibraryInstaller implements InstallerContract
{
    protected ComposerRepositoryService $composerRepositoryService;
    protected SettingService $settingService;
    protected EventDispatcher $eventDispatcher;

    protected function getEventDispatcher(): EventDispatcher
    {
        if (!isset($this->{'eventDispatcher'})) {
            $this->eventDispatcher = App::make(EventDispatcher::class);
        }

        return $this->eventDispatcher;
    }

    /**
     * @return ComposerRepositoryService
     */
    public function getComposerRepositoryService(): ComposerRepositoryService
    {
        if (!isset($this->{'composerRepositoryService'})) {
            $this->composerRepositoryService = App::make(ComposerRepositoryService::class);
        }

        return $this->composerRepositoryService;
    }

    /**
     * @return SettingService
     */
    public function getSettingService(): SettingService
    {
        if (!isset($this->{'settingService'})) {
            $this->settingService = App::make(SettingService::class);
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

    protected function callPackageListeners(string $event, Theme|Plugin $package, array $packageListeners)
    {
        if (empty($packageListeners)) {
            return;
        }

        $listenerCaller = new PackageSpecifiedListenersCaller($package, $packageListeners);

        match ($event) {
            PackageSpecifiedListenersCaller::EVENT_INSTALLED => $listenerCaller->afterInstall(),
            PackageSpecifiedListenersCaller::EVENT_UPDATED => $listenerCaller->afterUpdate(),
            PackageSpecifiedListenersCaller::EVENT_UNINSTALL => $listenerCaller->onUninstall(),
        };
    }

}