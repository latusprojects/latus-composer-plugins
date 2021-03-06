<?php

namespace Latus\ComposerPlugins\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Latus\ComposerPlugins\Events\PackageInstalled;
use Latus\ComposerPlugins\Events\PackageUninstalled;
use Latus\ComposerPlugins\Events\PackageUpdated;
use Latus\Helpers\Paths;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;
use Latus\Plugins\Services\PluginService;
use Latus\Plugins\Services\ThemeService;

class DispatchPackageEvents
{
    public const EVENT_INSTALLED = PackageInstalled::class;
    public const EVENT_UPDATED = PackageUpdated::class;
    public const EVENT_UNINSTALL = PackageUninstalled::class;

    public function __construct(
        protected ThemeService  $themeService,
        protected PluginService $pluginService,
    )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->hasDispatchableEvents()) {
            $this->dispatchEvents(self::EVENT_INSTALLED);
            $this->dispatchEvents(self::EVENT_UPDATED);
            $this->dispatchEvents(self::EVENT_UNINSTALL);

            $this->clearCachedEventsAndListeners();
        }

        return $next($request);
    }

    protected function hasDispatchableEvents(): bool
    {
        if (!stream_resolve_include_path($this->getFilePath()) || empty($this->getCachedEventsAndListeners())) {
            return false;
        }

        return true;
    }

    protected function dispatchEvents(string $eventClass)
    {
        $listeners = $this->getCachedEventsAndListeners()[$eventClass] ?? null;
        if (!$listeners) {
            return;
        }

        foreach ($listeners as $listenerData) {
            $packageSpecificEventClass = $listenerData['event_class'] ?? $eventClass;
            $listenerClass = $listenerData['listener_class'];
            $packageClass = $listenerData['package_class'];
            $packageId = $listenerData['package_id'];

            if ($packageSpecificEventClass !== $eventClass) {
                app()->bind($packageSpecificEventClass, $eventClass);
            }

            Event::listen($packageSpecificEventClass, $listenerClass);

            $eventObj = app($eventClass, ['package' => $this->getPackage($packageClass, $packageId)]);

            Event::dispatch($packageSpecificEventClass, ['event' => $eventObj]);
            Event::dispatch($eventClass, ['event' => $eventObj]);
        }
    }

    protected function getCachedEventsAndListeners(): array
    {
        if (!stream_resolve_include_path($this->getFilePath())) {
            return [];
        }

        return include $this->getFilePath();
    }

    protected function getPackage(string $packageClass, int $packageId): Theme|Model
    {
        return match ($packageClass) {
            Plugin::class => $this->pluginService->find($packageId),
            Theme::class => $this->themeService->find($packageId)
        };
    }

    protected function clearCachedEventsAndListeners()
    {
        File::delete($this->getFilePath());
    }

    protected function getFilePath(): string
    {
        return Paths::basePath('bootstrap/cache/latus-package-events.php');
    }
}