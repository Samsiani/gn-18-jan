<?php
if (!defined('ABSPATH')) exit;

class CIG_Updater {
    public function __construct() {
        $autoload = CIG_PLUGIN_DIR . 'vendor/autoload.php';
        if (!file_exists($autoload)) return;
        require_once $autoload;

        $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/Samsiani/gn-industrial-custom-invoice-generator/',
            CIG_PLUGIN_FILE,
            'gn-industrial-custom-invoice-generator'
        );

        // Use GitHub Releases (looks for release assets named *.zip)
        $checker->getVcsApi()->enableReleaseAssets();
    }
}
