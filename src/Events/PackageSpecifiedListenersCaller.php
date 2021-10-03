<?php

namespace Latus\ComposerPlugins\Events;

use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;

class PackageSpecifiedListenersCaller
{
    public const EVENT_INSTALLED = 'installed';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_UNINSTALL = 'uninstall';

    public function __construct(
        protected Theme|Plugin $package,
        protected array        $listeners
    )
    {
    }

    protected function listeners(string $event): array
    {
        $listeners = match ($event) {
            self::EVENT_INSTALLED => $this->listeners['installed'] ?? [],
            self::EVENT_UPDATED => $this->listeners['updated'] ?? [],
            self::EVENT_UNINSTALL => $this->listeners['uninstall'] ?? [],
            default => [],
        };

        return is_array($listeners) ? $listeners : [$listeners];
    }

    public function onUninstall()
    {
        $event = app()->make(PackageUninstalled::class, ['package' => $this->package]);

        foreach ($this->listeners(self::EVENT_UNINSTALL) as $listenerCallable) {
            try {
                app()->call($listenerCallable, ['event' => $event]);
            } catch (\BadMethodCallException) {

            }
        }
    }

    public function afterInstall()
    {
        $event = app()->make(PackageInstalled::class, ['package' => $this->package]);

        foreach ($this->listeners(self::EVENT_INSTALLED) as $listenerCallable) {
            try {
                app()->call($listenerCallable, ['event' => $event]);
            } catch (\BadMethodCallException) {

            }
        }
    }

    public function afterUpdate()
    {
        $event = app()->make(PackageUpdated::class, ['package' => $this->package]);

        foreach ($this->listeners(self::EVENT_UPDATED) as $listenerCallable) {
            try {
                app()->call($listenerCallable, ['event' => $event]);
            } catch (\BadMethodCallException) {

            }
        }
    }
}