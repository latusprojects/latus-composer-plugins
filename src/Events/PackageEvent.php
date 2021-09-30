<?php

namespace Latus\ComposerPlugins\Events;

use Latus\Plugins\Models\Plugin;
use Latus\Plugins\Models\Theme;

abstract class PackageEvent
{
    public function __construct(
        protected Theme|Plugin $package
    )
    {
    }

    public function getPackage(): Theme|Plugin
    {
        return $this->package;
    }
}