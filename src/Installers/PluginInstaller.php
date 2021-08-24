<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Composer;
use Composer\Installer\BinaryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Repositories\Contracts\PluginRepository;
use Latus\Plugins\Services\PluginService;
use React\Promise\PromiseInterface;

class PluginInstaller extends Installer
{

    protected PluginService $pluginService;

    public function __construct(
        IOInterface     $io,
        Composer        $composer,
                        $type = 'library',
        Filesystem      $filesystem = null,
        BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);

        $this->bootstrapInstaller(function () {
            $this->pluginService = new PluginService($this->app->make(PluginRepository::class));
        });
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
        $plugin = $this->pluginService->findByName($packageName);
        return $plugin;
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {

        $plugin = $this->getPlugin($package->getName());

        return parent::uninstall($repo, $package)->then(function () use ($plugin) {

            if ($plugin->status === Plugin::STATUS_DEACTIVATED) {
                return;
            }
            $this->pluginService->deletePlugin($plugin);

        })->otherwise(function () use ($plugin) {

            $this->pluginService->updatePlugin($plugin, ['status' => Plugin::STATUS_FAILED_UNINSTALL]);

        });

    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): PromiseInterface
    {

        $plugin = $this->getPlugin($target->getName());

        $target_version = $target->getVersion();

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $plugin) {

            $this->pluginService->updatePlugin($plugin, ['current_version' => $target_version, 'target_version' => $target_version]);

        })->otherwise(function () use ($target_version, $plugin) {

            $this->pluginService->updatePlugin($plugin, ['target_version' => $target_version, 'status' => Plugin::STATUS_FAILED_UPDATE]);

        });
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {

        $package_version = $package->getVersion();

        $package_name = $package->getName();

        $repository_id = $this->getRepositoryId($repo->getRepoName());

        $plugin = $this->getPlugin($package_name);

        return parent::install($repo, $package)->then(function () use ($package_name, $package_version, $repository_id, $plugin) {

            if ($plugin) {
                $this->pluginService->activatePlugin($plugin);
                return;
            }

            $this->pluginService->createPlugin([
                'name' => $package_name,
                'status' => Plugin::STATUS_ACTIVATED,
                'repository_id' => $repository_id,
                'current_version' => $package_version,
                'target_version' => $package_version,
            ]);

        })->otherwise(function () use ($package_name, $package_version, $repository_id, $plugin) {

            if ($plugin) {
                $this->pluginService->updatePlugin($plugin, ['status' => Plugin::STATUS_FAILED_INSTALL]);
                return;
            }

            $this->pluginService->createPlugin([
                'name' => $package_name,
                'status' => Plugin::STATUS_FAILED_INSTALL,
                'repository_id' => $repository_id,
                'current_version' => null,
                'target_version' => $package_version,
            ]);

        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::pluginPath($package->getName());
    }

}