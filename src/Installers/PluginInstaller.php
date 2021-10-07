<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Illuminate\Support\Facades\App;
use Latus\ComposerPlugins\Events\PackageInstalled;
use Latus\ComposerPlugins\Events\PackageInstallFailed;
use Latus\ComposerPlugins\Events\PackageUninstalled;
use Latus\ComposerPlugins\Events\PackageUninstallFailed;
use Latus\ComposerPlugins\Events\PackageUpdated;
use Latus\ComposerPlugins\Events\PackageUpdateFailed;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Services\PluginService;
use React\Promise\PromiseInterface;

class PluginInstaller extends Installer
{

    protected PluginService $pluginService;

    protected function getPluginService(): PluginService
    {
        if (!isset($this->{'pluginService'})) {
            $this->pluginService = App::make(PluginService::class);
        }

        return $this->pluginService;
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

    protected function getPlugin(string $packageName): Plugin|null
    {
        /**
         * @var Plugin|null $plugin
         */
        $plugin = $this->getPluginService()->findByName($packageName);
        return $plugin;
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        $packageName = $package->getName();

        return parent::uninstall($repo, $package)->then(function () use ($packageName) {

            $plugin = $this->getPlugin($packageName);

            if ($plugin) {

                $this->addListenersToCache(PackageUninstalled::class, [], $plugin);

                if ($plugin->status !== Plugin::STATUS_DEACTIVATED) {
                    $this->getPluginService()->deletePlugin($plugin);
                }

            }

        })->otherwise(function () use ($packageName) {

            $plugin = $this->getPlugin($packageName);

            if ($plugin) {
                $this->getPluginService()->updatePlugin($plugin, ['status' => Plugin::STATUS_FAILED_UNINSTALL]);

                $this->addListenersToCache(PackageUninstallFailed::class, [], $plugin);
            }
        });

    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        $packageName = $target->getName();

        $target_version = $target->getVersion();

        $packageListeners = isset($target->getExtra()['latus']['package-events']) ? $target->getExtra()['latus']['package-events'] : [];

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $packageName, $packageListeners) {

            $plugin = $this->getPlugin($packageName);

            $this->getPluginService()->updatePlugin($plugin, ['current_version' => $target_version, 'target_version' => $target_version]);

            $this->addListenersToCache(PackageUpdated::class, $packageListeners['updated'] ?? [], $plugin);

        })->otherwise(function () use ($target_version, $packageName) {

            $plugin = $this->getPlugin($packageName);

            $this->getPluginService()->updatePlugin($plugin, ['target_version' => $target_version, 'status' => Plugin::STATUS_FAILED_UPDATE]);

            $this->addListenersToCache(PackageUpdateFailed::class, [], $plugin);

        });
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        $package_version = $package->getVersion();

        $packageName = $package->getName();

        $repoName = $repo->getRepoName();

        $packageListeners = isset($package->getExtra()['latus']['package-events']) ? $package->getExtra()['latus']['package-events'] : [];

        return parent::install($repo, $package)->then(function () use ($packageName, $package_version, $repoName, $packageListeners) {

            $repositoryId = $this->getRepositoryId($repoName);

            $plugin = $this->getPlugin($packageName);

            if (!$plugin) {
                /**
                 * @var Plugin $plugin
                 */
                $plugin = $this->getPluginService()->createPlugin([
                    'name' => $packageName,
                    'status' => Plugin::STATUS_ACTIVATED,
                    'repository_id' => $repositoryId,
                    'current_version' => $package_version,
                    'target_version' => $package_version,
                ]);
            } else {
                $this->getPluginService()->updatePlugin($plugin, [
                    'current_version' => $package_version
                ]);

                $this->getPluginService()->activatePlugin($plugin);
            }

            $this->addListenersToCache(PackageInstalled::class, $packageListeners['installed'] ?? [], $plugin);

        })->otherwise(function () use ($packageName, $package_version, $repoName) {

            $repositoryId = $this->getRepositoryId($repoName);

            $plugin = $this->getPlugin($packageName);

            if (!$plugin) {
                /**
                 * @var Plugin $plugin
                 */
                $plugin = $this->getPluginService()->createPlugin([
                    'name' => $packageName,
                    'status' => Plugin::STATUS_FAILED_INSTALL,
                    'repository_id' => $repositoryId,
                    'current_version' => null,
                    'target_version' => $package_version,
                ]);
            } else {
                $this->getPluginService()->updatePlugin($plugin, ['status' => Plugin::STATUS_FAILED_INSTALL]);
            }

            $this->addListenersToCache(PackageInstallFailed::class, [], $plugin);
        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::pluginPath($package->getName());
    }

}