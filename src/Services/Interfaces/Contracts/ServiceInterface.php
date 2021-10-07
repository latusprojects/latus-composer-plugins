<?php

namespace Latus\ComposerPlugins\Services\Interfaces\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ServiceInterface
{
    public function find(string $packageName, array $createAttributes = null): Model|null;

    public function update(string $packageName, array $attributes): Model;

    public function delete(string $packageName);
}