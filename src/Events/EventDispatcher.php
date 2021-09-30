<?php

namespace Latus\ComposerPlugins\Events;


use Illuminate\Support\Facades\Event;
use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;

class EventDispatcher
{
    protected Theme|Plugin $package;

    public function setPackage(Theme|Plugin $package)
    {
        $this->package = $package;
    }

    public function dispatchUninstallFailedEvent()
    {
        $packageSpecificEventName = 'latus.package.uninstallFailed.' . $this->package->name;

        $this->dispatchEvents(PackageUninstallFailed::class, $packageSpecificEventName);
    }

    public function dispatchUninstalledEvent()
    {
        $packageSpecificEventName = 'latus.package.uninstalled.' . $this->package->name;

        $this->dispatchEvents(PackageUninstalled::class, $packageSpecificEventName);
    }

    public function dispatchInstallFailedEvent()
    {
        $packageSpecificEventName = 'latus.package.installFailed.' . $this->package->name;

        $this->dispatchEvents(PackageInstallFailed::class, $packageSpecificEventName);
    }

    public function dispatchInstalledEvent()
    {
        $packageSpecificEventName = 'latus.package.installed.' . $this->package->name;

        $this->dispatchEvents(PackageInstalled::class, $packageSpecificEventName);
    }

    public function dispatchUpdateFailedEvent()
    {
        $packageSpecificEventName = 'latus.package.updateFailed.' . $this->package->name;

        $this->dispatchEvents(PackageUpdateFailed::class, $packageSpecificEventName);
    }

    public function dispatchUpdatedEvent()
    {
        $packageSpecificEventName = 'latus.package.installed.' . $this->package->name;

        $this->dispatchEvents(PackageUpdated::class, $packageSpecificEventName);
    }

    protected function dispatchEvents(string $eventClass, string $packageSpecificEventName)
    {
        app()->bind($packageSpecificEventName, $eventClass);

        $payload = ['package' => $this->package];

        Event::dispatch($packageSpecificEventName, $payload);
        Event::dispatch($eventClass, $payload);
    }
}