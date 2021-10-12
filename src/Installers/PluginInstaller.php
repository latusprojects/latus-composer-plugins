<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Illuminate\Support\Facades\App;
use Latus\ComposerPlugins\Events\PackageActivated;
use Latus\ComposerPlugins\Events\PackageDeactivated;
use Latus\ComposerPlugins\Events\PackageInstalled;
use Latus\ComposerPlugins\Events\PackageInstallFailed;
use Latus\ComposerPlugins\Events\PackageUninstalled;
use Latus\ComposerPlugins\Events\PackageUninstallFailed;
use Latus\ComposerPlugins\Events\PackageUpdated;
use Latus\ComposerPlugins\Events\PackageUpdateFailed;
use Latus\ComposerPlugins\Services\Interfaces\PluginServiceInterface;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Plugin;
use React\Promise\PromiseInterface;

class PluginInstaller extends Installer
{

    protected PluginServiceInterface $pluginServiceInterface;

    protected function serviceInterface(): PluginServiceInterface
    {
        if (!isset($this->{'pluginServiceInterface'})) {
            $this->pluginServiceInterface = App::make(PluginServiceInterface::class);
        }

        return $this->pluginServiceInterface;
    }

    /**
     * @inheritDoc
     */
    public function supports($packageType): bool
    {
        return in_array($packageType, [
            'latus-plugin'
        ]);
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::uninstall($repo, $package)->then(function () use ($package) {

            $plugin = $this->serviceInterface()->find($package->getName());

            if ($plugin->status === Plugin::STATUS_DEACTIVATED) {
                $this->addListenersToCache(PackageDeactivated::class, $plugin);
                return;
            }

            $this->serviceInterface()->delete($package->getName());
            $this->addListenersToCache(PackageUninstalled::class, $package->getName());

        })->otherwise(function () use ($package) {

            $plugin = $this->serviceInterface()->find($package->getName());

            if ($plugin) {
                $this->serviceInterface()->update($plugin, ['status' => Plugin::STATUS_FAILED_UNINSTALL]);
            }

            $this->addListenersToCache(PackageUninstallFailed::class, $package->getName());
        });
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::update($repo, $initial, $target)->then(function () use ($target) {

            $plugin = $this->serviceInterface()->update($target->getName(), ['current_version' => $target->getVersion(), 'target_version' => $target->getVersion()]);

            $updatedListeners = $this->getListeners($target, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageUpdated::class]);

            $this->addListenersToCache(PackageUpdated::class, $plugin, $updatedListeners);

        })->otherwise(function () use ($target) {
            $plugin = $this->serviceInterface()->update($target->getName(), ['target_version' => $target->getVersion(), 'status' => Plugin::STATUS_FAILED_UPDATE]);

            $updateFailedListeners = $this->getListeners($target, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageUpdateFailed::class]);

            $this->addListenersToCache(PackageUpdateFailed::class, $plugin, $updateFailedListeners);
        });
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::install($repo, $package)->then(function () use ($package, $repo) {

            $repositoryId = $this->getRepositoryId($repo->getRepoName());

            $pluginAlreadyExists = $this->serviceInterface()->find($package->getName()) ? true : false;

            $plugin = $this->serviceInterface()->find($package->getName(), [
                'name' => $package->getName(),
                'status' => Plugin::STATUS_ACTIVATED,
                'repository_id' => $repositoryId,
                'current_version' => $package->getVersion(),
                'target_version' => $package->getVersion(),
            ]);

            if (!$pluginAlreadyExists) {
                $activatedListeners = $this->getListeners($package, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageActivated::class]);

                $this->addListenersToCache(PackageActivated::class, $plugin, $activatedListeners);
                return;
            }

            $this->serviceInterface()->update($package->getName(), [
                'current_version' => $package->getVersion(),
                'status' => Plugin::STATUS_ACTIVATED,
            ]);

            $installedListeners = $this->getListeners($package, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageInstalled::class]);

            $this->addListenersToCache(PackageInstalled::class, $plugin, $installedListeners);

        })->otherwise(function () use ($package, $repo) {

            $repositoryId = $this->getRepositoryId($repo->getRepoName());

            $pluginAlreadyExists = (bool)$this->serviceInterface()->find($package->getName());

            $plugin = $this->serviceInterface()->find($package->getName(), [
                'name' => $package->getName(),
                'status' => Plugin::STATUS_FAILED_INSTALL,
                'repository_id' => $repositoryId,
                'current_version' => $package->getVersion(),
                'target_version' => $package->getVersion(),
            ]);

            if ($pluginAlreadyExists) {
                $this->serviceInterface()->update($package->getName(), [
                    'current_version' => $package->getVersion(),
                    'status' => Plugin::STATUS_FAILED_INSTALL,
                ]);
            }

            $installFailedListeners = $this->getListeners($package, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageInstallFailed::class]);

            $this->addListenersToCache(PackageInstallFailed::class, $plugin, $installFailedListeners);
        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::pluginPath($package->getName());
    }

}