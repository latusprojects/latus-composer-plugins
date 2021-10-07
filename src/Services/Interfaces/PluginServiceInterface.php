<?php

namespace Latus\ComposerPlugins\Services\Interfaces;

use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Services\PluginService;

class PluginServiceInterface extends ServiceInterface
{
    public function __construct(
        protected PluginService $pluginService
    )
    {
    }

    public function find(string $packageName, array $createAttributes = null): Plugin|null
    {
        /**
         * @var Plugin $plugin
         */
        $plugin = $this->pluginService->findByName($packageName)
            ?? ($createAttributes
                ? $this->pluginService->createPlugin($createAttributes)
                : null);

        return $plugin;
    }

    public function update(string $packageName, array $attributes): Plugin
    {
        $this->pluginService->updatePlugin($this->find($packageName), $attributes);

        return $this->find($packageName);
    }

    public function delete(string $packageName)
    {
        $this->pluginService->deletePlugin($this->find($packageName));
    }

    public function deactivate(string $packageName)
    {
        $this->pluginService->deactivatePlugin($this->find($packageName));
    }

    public function activate(string $packageName)
    {
        $this->pluginService->activatePlugin($this->find($packageName));
    }
}