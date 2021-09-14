<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Composer;
use Composer\Installer\BinaryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Repositories\Contracts\ThemeRepository;
use Latus\Plugins\Services\ThemeService;
use React\Promise\PromiseInterface;

class ThemeInstaller extends Installer
{

    protected ThemeService $themeService;

    protected function getThemeService(): ThemeService
    {
        if (!isset($this->{'themeService'})) {
            $this->themeService = $this->app->make(ThemeService::class);
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

        $package_name = $package->getName();

        $package_version = $package->getVersion();

        $repository_id = $this->getRepositoryId($repo->getRepoName());

        $theme = $this->getTheme($package_name);

        $supports = isset($package->getExtra()['latus']['modules']) ? $package->getExtra()['latus']['modules'] : [];

        return parent::install($repo, $package)->then(function () use ($package_name, $package_version, $repository_id, $supports, $theme) {

            if ($theme) {
                $this->getThemeService()->updateTheme($theme, ['current_version' => $package_version, 'supports' => $supports]);
                return;
            }

            $this->getThemeService()->createTheme([
                'name' => $package_name,
                'status' => Theme::STATUS_ACTIVE,
                'repository_id' => $repository_id,
                'current_version' => $package_version,
                'target_version' => $package_version,
                'supports' => $supports
            ]);

        })->otherwise(function () use ($package_name, $package_version, $repository_id, $supports, $theme) {

            if ($theme) {
                $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'status' => Theme::STATUS_FAILED_INSTALL]);
                return;
            }

            $this->getThemeService()->createTheme([
                'name' => $package_name,
                'status' => Theme::STATUS_FAILED_INSTALL,
                'repository_id' => $repository_id,
                'current_version' => null,
                'target_version' => $package_version,
                'supports' => $supports
            ]);

        });
    }

    /**
     * @inheritDoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): PromiseInterface
    {
        $package_name = $initial->getName();

        $theme = $this->getTheme($package_name);

        $target_version = $target->getVersion();

        $supports = isset($target->getExtra()['latus']['modules']) ? $target->getExtra()['latus']['modules'] : [];

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $supports, $theme) {

            $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'current_version' => $target_version, 'target_version' => $target_version]);

        })->otherwise(function () use ($target_version, $supports, $theme) {

            $this->getThemeService()->updateTheme($theme, ['supports' => $supports, 'target_version' => $target_version, 'status' => Theme::STATUS_FAILED_UPDATE]);

        });
    }

    /**
     * @inheritDoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {

        $theme = $this->getTheme($package->getName());

        return parent::uninstall($repo, $package)->then(function () use ($theme) {

            if ($theme->status === Theme::STATUS_INACTIVE) {
                return;
            }
            $this->getThemeService()->deleteTheme($theme);

        })->otherwise(function () use ($theme) {

            $this->getThemeService()->updateTheme($theme, ['status' => Theme::STATUS_FAILED_UNINSTALL]);

        });
    }

    public function getInstallPath(PackageInterface $package): string
    {
        return Paths::themePath($package->getName());
    }
}