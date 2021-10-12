<?php


namespace Latus\ComposerPlugins\Installers;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Latus\ComposerPlugins\Contracts\Installer as InstallerContract;
use Latus\ComposerPlugins\Events\PackageActivated;
use Latus\ComposerPlugins\Events\PackageDeactivated;
use Latus\ComposerPlugins\Events\PackageInstalled;
use Latus\ComposerPlugins\Events\PackageInstallFailed;
use Latus\ComposerPlugins\Events\PackageUninstalled;
use Latus\ComposerPlugins\Events\PackageUninstallFailed;
use Latus\ComposerPlugins\Events\PackageUpdated;
use Latus\ComposerPlugins\Events\PackageUpdateFailed;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Services\ComposerRepositoryService;
use Latus\Settings\Services\SettingService;
use React\Promise\PromiseInterface;

abstract class Installer extends LibraryInstaller implements InstallerContract
{


    public const EVENT_CLASS_EVENT_TYPE_MAP = [
        PackageInstalled::class => 'installed',
        PackageUpdated::class => 'updated',
        PackageUninstalled::class => 'uninstalled',
        PackageInstallFailed::class => 'install-failed',
        PackageUpdateFailed::class => 'update-failed',
        PackageUninstallFailed::class => 'uninstall-failed',
        PackageDeactivated::class => 'deactivated',
        PackageActivated::class => 'activated'
    ];

    public const PACKAGE_EVENTS_KEY = 'package-events';

    protected ComposerRepositoryService $composerRepositoryService;
    protected SettingService $settingService;

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

    protected function addListenersToCache(string $eventClass, Theme|Plugin|string $package, array $listenerClasses = [])
    {
        $cache = $this->getCachedListeners();

        if (!isset($cache[$eventClass])) {
            $cache[$eventClass] = [];
        }

        foreach ($listenerClasses as $listenerClass) {
            $cache[$eventClass][] = [
                'event_class' => 'latus-package-installed.' . (is_string($package) ? $package : $package->name),
                'listener_class' => $listenerClass,
                'package_class' => is_string($package) ? $package : get_class($package),
                'package_id' => is_string($package) ? $package : $package->id
            ];
        }

        $this->storeCachedListeners($cache);
    }

    protected function getComposerLatusExtra(PackageInterface $package, string $key, string $subKey = null): string|array|null
    {
        if (!isset($package->getExtra()['latus']) || !isset($package->getExtra()['latus'][$key])) {
            return null;
        }

        if ($subKey && !isset($package->getExtra()['latus'][$key][$subKey])) {
            return null;
        }

        return $subKey ? $package->getExtra()['latus'][$key][$subKey] : $package->getExtra()['latus'][$key];
    }

    protected function getListeners(PackageInterface $package, string $eventType): array
    {
        $packageListeners = $this->getComposerLatusExtra($package, 'package-events', $eventType);

        return $packageListeners ?? [];
    }

    protected function getCachedListeners(): array
    {
        $filePath = Paths::basePath('bootstrap/cache/latus-package-events.php');

        if (!stream_resolve_include_path($filePath)) {
            $this->storeCachedListeners([]);
        }

        return include $filePath;
    }

    protected function storeCachedListeners(array $listeners)
    {
        $filePath = Paths::basePath('bootstrap/cache/latus-package-events.php');

        $fileContents =
            "<?php \n" .
            "return " . var_export($listeners, true) . ";";

        File::put($filePath, $fileContents);
    }

}