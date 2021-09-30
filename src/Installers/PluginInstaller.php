<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Latus\ComposerPlugins\Events\EventDispatcher;
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
            $this->pluginService = $this->getApp()->make(PluginService::class);
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

                $this->getEventDispatcher()->setPackage($plugin);
                $this->getEventDispatcher()->dispatchUninstalledEvent();

                if ($plugin->status !== Plugin::STATUS_DEACTIVATED) {
                    $this->getPluginService()->deletePlugin($plugin);
                }

            }

        })->otherwise(function () use ($packageName) {

            $plugin = $this->getPlugin($packageName);

            if ($plugin) {
                $this->getPluginService()->updatePlugin($plugin, ['status' => Plugin::STATUS_FAILED_UNINSTALL]);

                $this->getEventDispatcher()->setPackage($plugin);
                $this->getEventDispatcher()->dispatchUninstallFailedEvent();
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

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $packageName) {

            $plugin = $this->getPlugin($packageName);

            $this->getPluginService()->updatePlugin($plugin, ['current_version' => $target_version, 'target_version' => $target_version]);

            $this->getEventDispatcher()->setPackage($plugin);
            $this->getEventDispatcher()->dispatchUpdatedEvent();

        })->otherwise(function () use ($target_version, $packageName) {

            $plugin = $this->getPlugin($packageName);

            $this->getPluginService()->updatePlugin($plugin, ['target_version' => $target_version, 'status' => Plugin::STATUS_FAILED_UPDATE]);

            $this->getEventDispatcher()->setPackage($plugin);
            $this->getEventDispatcher()->dispatchUpdateFailedEvent();

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

        return parent::install($repo, $package)->then(function () use ($packageName, $package_version, $repoName) {

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
                $this->getPluginService()->activatePlugin($plugin);
            }

            $this->getEventDispatcher()->setPackage($plugin);
            $this->getEventDispatcher()->dispatchInstalledEvent();

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

            $this->getEventDispatcher()->setPackage($plugin);
            $this->getEventDispatcher()->dispatchInstallFailedEvent();
        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::pluginPath($package->getName());
    }

}