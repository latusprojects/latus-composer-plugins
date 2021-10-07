<?php

namespace Latus\ComposerPlugins\Services\Interfaces;

use Latus\Plugins\Models\Theme;
use Latus\Plugins\Services\ThemeService;

class ThemeServiceInterface extends ServiceInterface
{
    public function __construct(
        protected ThemeService $themeService
    )
    {
    }

    public function find(string $packageName, array $createAttributes = null): Theme|null
    {
        /**
         * @var Theme $theme
         */
        $theme = $this->themeService->findByName($packageName)
            ?? ($createAttributes
                ? $this->themeService->createTheme($createAttributes)
                : null);

        return $theme;
    }

    public function update(string $packageName, array $attributes): Theme
    {
        $this->themeService->updateTheme($this->find($packageName), $attributes);

        return $this->find($packageName);
    }

    public function delete(string $packageName)
    {
        $this->themeService->deleteTheme($this->find($packageName));
    }
}