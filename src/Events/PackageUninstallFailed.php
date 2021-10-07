<?php

namespace Latus\ComposerPlugins\Events;

class PackageUninstallFailed
{
    public function __construct(
        protected string $packageName
    )
    {
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }
}