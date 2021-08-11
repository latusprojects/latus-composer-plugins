<?php


namespace Latus\ComposerPlugins;

if (!defined('LATUS_COMPOSER_INSTALLER')) {
    define('LATUS_COMPOSER_INSTALLER', true);
}

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Illuminate\Support\Facades\File;
use Latus\ComposerPlugins\Installers\PluginInstaller;
use Latus\ComposerPlugins\Installers\ThemeInstaller;
use Latus\Helpers\Paths;

class ComposerPlugin implements PluginInterface
{

    public function activate(Composer $composer, IOInterface $io)
    {

        /**
         * Verify that latus-packages were installed in a laravel-root
         */
        if (File::exists(Paths::basePath('artisan'))) {
            $plugin_installer = new PluginInstaller($io, $composer);

            $composer->getInstallationManager()
                ->addInstaller($plugin_installer);

            $themeInstaller = new ThemeInstaller($io, $composer);

            $composer->getInstallationManager()
                ->addInstaller($themeInstaller);
        }

    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // TODO: Implement uninstall() method.
    }

}