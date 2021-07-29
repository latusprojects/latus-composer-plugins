<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Composer;
use Composer\Installer\BinaryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Repositories\Contracts\ThemeRepository;
use Latus\Plugins\Services\ThemeService;
use React\Promise\PromiseInterface;

class ThemeInstaller extends Installer
{

    protected ThemeService $themeService;

    public function __construct(
        IOInterface $io,
        Composer $composer,
        $type = 'library',
        Filesystem $filesystem = null,
        BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);

        $this->bootstrapInstaller(function () {
            $this->themeService = new ThemeService($this->app->make(ThemeRepository::class));
        });
    }

    /**
     * @inheritDoc
     */
    public function supports($packageType): bool
    {
        return in_array($packageType, [
            'latus-theme',
            'latus-proxy-theme'
        ]);
    }

    protected function getTheme(string $packageName): Theme|null
    {
        /**
         * @var Theme|null $theme
         */
        $theme = $this->themeService->findByName($packageName);
        return $theme;
    }

    /**
     * @inheritDoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {

        $package_names = $this->getPackageAndProxyNames($package->getName());

        $package_version = $package->getVersion();

        $repository_id = $this->getRepositoryId($repo->getRepoName());

        $theme = $this->getTheme($package_names['package_name']);

        $supports = isset($package->getExtra()['latus']['modules']) ? $package->getExtra()['latus']['modules'] : [];

        return parent::install($repo, $package)->then(function () use ($package_names, $package_version, $repository_id, $supports, $theme) {

            if ($theme) {
                $this->themeService->updateTheme($theme, ['supports' => $supports]);
                return;
            }

            $this->themeService->createTheme([
                'name' => $package_names['package_name'],
                'proxy_name' => $package_names['package_proxy_name'],
                'status' => Theme::STATUS_ACTIVE,
                'repository_id' => $repository_id,
                'current_version' => $package_version,
                'target_version' => $package_version,
                'supports' => $supports
            ]);

        })->otherwise(function () use ($package_names, $package_version, $repository_id, $supports, $theme) {

            if ($theme) {
                $this->themeService->updateTheme($theme, ['supports' => $supports, 'status' => Theme::STATUS_FAILED_INSTALL]);
                return;
            }

            $this->themeService->createTheme([
                'name' => $package_names['package_name'],
                'proxy_name' => $package_names['package_proxy_name'],
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
        $package_names = $this->getPackageAndProxyNames($initial->getName());

        $theme = $this->getTheme($package_names['package_name']);

        $target_version = $target->getVersion();

        $supports = isset($target->getExtra()['latus']['modules']) ? $target->getExtra()['latus']['modules'] : [];

        return parent::update($repo, $initial, $target)->then(function () use ($target_version, $supports, $theme) {

            $this->themeService->updateTheme($theme, ['supports' => $supports, 'current_version' => $target_version, 'target_version' => $target_version]);

        })->otherwise(function () use ($target_version, $supports, $theme) {

            $this->themeService->updateTheme($theme, ['supports' => $supports, 'target_version' => $target_version, 'status' => Theme::STATUS_FAILED_UPDATE]);

        });
    }

    /**
     * @inheritDoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): PromiseInterface
    {
        $package_names = $this->getPackageAndProxyNames($package->getName());

        $theme = $this->getTheme($package_names['package_name']);

        return parent::uninstall($repo, $package)->then(function () use ($theme) {

            if ($theme->status === Theme::STATUS_INACTIVE) {
                return;
            }
            $this->themeService->deleteTheme($theme);

        })->otherwise(function () use ($theme) {

            $this->themeService->updateTheme($theme, ['status' => Theme::STATUS_FAILED_UNINSTALL]);

        });
    }
}