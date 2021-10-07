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
use Latus\ComposerPlugins\Services\Interfaces\ThemeServiceInterface;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Theme;
use React\Promise\PromiseInterface;

class ThemeInstaller extends Installer
{
    public const MODULES_KEY = 'modules';

    protected ThemeServiceInterface $themeServiceInterface;

    protected function serviceInterface(): ThemeServiceInterface
    {
        if (!isset($this->{'themeServiceInterface'})) {
            $this->themeServiceInterface = App::make(ThemeServiceInterface::class);
        }

        return $this->themeServiceInterface;
    }

    /**
     * @inheritDoc
     */
    public function supports($packageType): bool
    {
        return in_array($packageType, [
            'latus-theme'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::install($repo, $package)->then(function () use ($repo, $package) {
            $repositoryId = $this->getRepositoryId($repo->getRepoName());

            $themeAlreadyExists = $this->serviceInterface()->find($package->getName()) ? true : false;

            $supports = $this->getComposerLatusExtra($package, self::MODULES_KEY) ?? [];

            $theme = $this->serviceInterface()->find($package->getName(), [
                'name' => $package->getName(),
                'status' => Theme::STATUS_ACTIVE,
                'repository_id' => $repositoryId,
                'current_version' => $package->getVersion(),
                'target_version' => $package->getVersion(),
                'supports' => $supports
            ]);

            if ($themeAlreadyExists) {
                $theme = $this->serviceInterface()->update($package->getName(), ['current_version' => $package->getVersion(), 'supports' => $supports]);
            }

            $installedListeners = $this->getListeners($package, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageInstalled::class]);

            $this->addListenersToCache(PackageInstalled::class, $theme, $installedListeners);

        })->otherwise(function () use ($repo, $package) {

            $repositoryId = $this->getRepositoryId($repo->getRepoName());

            $themeAlreadyExists = $this->serviceInterface()->find($package->getName()) ? true : false;

            $supports = $this->getComposerLatusExtra($package, self::MODULES_KEY) ?? [];

            $theme = $this->serviceInterface()->find($package->getName(), [
                'name' => $package->getName(),
                'status' => Theme::STATUS_FAILED_INSTALL,
                'repository_id' => $repositoryId,
                'current_version' => null,
                'target_version' => $package->getVersion(),
                'supports' => $supports
            ]);

            if ($themeAlreadyExists) {
                $this->serviceInterface()->update($package->getName(), ['supports' => $supports, 'status' => Theme::STATUS_FAILED_INSTALL]);
            }

            $installFailedListeners = $this->getListeners($package, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageInstallFailed::class]);

            $this->addListenersToCache(PackageInstallFailed::class, $theme, $installFailedListeners);

        });
    }

    /**
     * @inheritDoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::update($repo, $initial, $target)->then(function () use ($repo, $target) {
            $supports = $this->getComposerLatusExtra($target, self::MODULES_KEY) ?? [];

            $theme = $this->serviceInterface()->update($target->getName(), ['supports' => $supports, 'current_version' => $target->getVersion(), 'target_version' => $target->getVersion()]);

            $updatedListeners = $this->getListeners($target, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageUpdated::class]);

            $this->addListenersToCache(PackageUpdated::class, $theme, $updatedListeners);

        })->otherwise(function () use ($target) {

            $supports = $this->getComposerLatusExtra($target, self::MODULES_KEY) ?? [];

            $theme = $this->serviceInterface()->update($target->getName(), ['supports' => $supports, 'target_version' => $target->getVersion(), 'status' => Theme::STATUS_FAILED_UPDATE]);

            $updateFailedListeners = $this->getListeners($target, self::EVENT_CLASS_EVENT_TYPE_MAP[PackageUpdateFailed::class]);

            $this->addListenersToCache(PackageUpdateFailed::class, $theme, $updateFailedListeners);

        });
    }

    /**
     * @inheritDoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        return parent::uninstall($repo, $package)->then(function () use ($package) {

            $theme = $this->serviceInterface()->find($package->getName());

            if ($theme->status !== Theme::STATUS_INACTIVE) {
                $this->serviceInterface()->delete($package->getName());
            }

            $this->serviceInterface()->delete($package->getName());
            $this->addListenersToCache(PackageUninstalled::class, $package->getName());

        })->otherwise(function () use ($package) {
            $theme = $this->serviceInterface()->find($package->getName());

            if ($theme) {
                $this->serviceInterface()->update($theme, ['status' => Theme::STATUS_FAILED_UNINSTALL]);
            }

            $this->addListenersToCache(PackageUninstallFailed::class, $package->getName());
        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::themePath($package->getName());
    }
}