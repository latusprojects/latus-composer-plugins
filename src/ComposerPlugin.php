<?php


namespace Latus\ComposerPlugins;

define('LATUS_COMPOSER_INSTALLER', true);

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Latus\ComposerPlugins\Installers\PluginInstaller;

class ComposerPlugin implements PluginInterface
{

    public function activate(Composer $composer, IOInterface $io)
    {
        $plugin_installer = new PluginInstaller($io, $composer);

        $composer->getInstallationManager()
            ->addInstaller($plugin_installer);

    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // TODO: Implement uninstall() method.
    }

}