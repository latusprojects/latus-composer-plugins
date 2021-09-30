<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Services\ThemeService;
use React\Promise\PromiseInterface;

class ThemeInstaller extends Installer
{

    protected ThemeService $themeService;

    protected function getThemeService(): ThemeService
    {
        if (!isset($this->{'themeService'})) {
            $this->themeService = $this->getApp()->make(ThemeService::class);
        }

        return $this->themeService;
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

    protected function getTheme(string $packageName): Theme|null
    {
        /**
         * @var Theme|null $theme
         */
        $theme = $this->getThemeService()->findByName($packageName);
        return $theme;
    }

    /**
     * @inheritDoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        if (!$this->isRunningInLaravel()) {
            return \React\Promise\resolve();
        }

        $packageName = $package->getName();

        $repoName = $repo->getRepoName();

        $package_version = $package->getVersion();

        $supports = isset($package->getExtra()['latus']['modules']) ? $package->getExtra()['latus']['modules'] : [];

        return parent::install($repo, $package)->then(function () use ($packageName, $package_version, $supports, $repoName) {

            $repositoryId = $this->getRepositoryId($repoName);

            $theme = $this->getTheme($packageName);

            if (!$theme) {
                $theme = $this->getThemeService()->createTheme([
                    'name' => $packageName,
                    'status' => Theme::STATUS_ACTIVE,
                    'repository_id' => $repositoryId,
                    'current_version' => $package_version,
                    'target_version' => $package_version,
                    'supports' => $supports
                ]);
            } else {
                $this->getThemeService()->updateTheme($theme, ['current_version' => $package_version, 'supports' => $supports]);
            }

            $this->getEventDispatcher()->setPackage($theme);
            $this->getEventDispatcher()->dispatchInstalledEvent();

        })->otherwise(function () use ($packageName, $package_version, $supports, $repoName) {

            $repositoryId = $this->getRepositoryId($repoName);

            $theme = $this->getTheme($packageName);

            if (!$theme) {
                $theme = $this->getThemeService()->createTheme([
                    'name' => $packageName,
                    'status' => Theme::STATUS_FAILED_INSTALL,
                    'repository_id' => $repositoryId,
                    'current_version' => null,
                    'target_version' => $package_version,
                    'supports' => $supports
                ]);
            } else {
                $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'status' => Theme::STATUS_FAILED_INSTALL]);
            }

            $this->getEventDispatcher()->setPackage($theme);
            $this->getEventDispatcher()->dispatchInstallFailedEvent();

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

        $packageName = $initial->getName();

        $target_version = $target->getVersion();

        $supports = isset($target->getExtra()['latus']['modules']) ? $target->getExtra()['latus']['modules'] : [];

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $supports, $packageName) {

            $theme = $this->getTheme($packageName);

            $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'current_version' => $target_version, 'target_version' => $target_version]);

            $this->getEventDispatcher()->setPackage($theme);
            $this->getEventDispatcher()->dispatchUpdatedEvent();

        })->otherwise(function () use ($target_version, $supports, $packageName) {

            $theme = $this->getTheme($packageName);

            $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'target_version' => $target_version, 'status' => Theme::STATUS_FAILED_UPDATE]);

            $this->getEventDispatcher()->setPackage($theme);
            $this->getEventDispatcher()->dispatchUpdateFailedEvent();

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

        $packageName = $package->getName();

        return parent::uninstall($repo, $package)->then(function () use ($packageName) {

            $theme = $this->getTheme($packageName);

            if ($theme) {

                $this->getEventDispatcher()->setPackage($theme);
                $this->getEventDispatcher()->dispatchUninstalledEvent();

                if ($theme->status !== Theme::STATUS_INACTIVE) {
                    $this->getThemeService()->deleteTheme($theme);
                }
            }

        })->otherwise(function () use ($packageName) {

            $theme = $this->getTheme($packageName);

            if ($theme) {

                $this->getThemeService()->updateTheme($theme, ['status' => Theme::STATUS_FAILED_UNINSTALL]);

                $this->getEventDispatcher()->setPackage($theme);
                $this->getEventDispatcher()->dispatchUninstallFailedEvent();

            }

        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::themePath($package->getName());
    }
}