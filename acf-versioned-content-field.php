<?php

declare(strict_types=1);

/**
 * Plugin Name: Advanced Custom Fields: Versioned Content Field
 * Plugin URI: https://github.com/Vheissu/acf-versioned-content-field
 * Description: Adds a versioned content field type to Advanced Custom Fields.
 * Version: 1.0.0
 * Author: Dwayne Charrington
 * Author URI: https://ilikekillnerds.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-versioned-content-field
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: advanced-custom-fields
 *
 * @package AcfVersionedContentField
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ACF_VERSIONED_CONTENT_FIELD_VERSION', '1.0.0');
define('ACF_VERSIONED_CONTENT_FIELD_FILE', __FILE__);
define('ACF_VERSIONED_CONTENT_FIELD_PATH', plugin_dir_path(__FILE__));
define('ACF_VERSIONED_CONTENT_FIELD_URL', plugin_dir_url(__FILE__));

$autoloader = ACF_VERSIONED_CONTENT_FIELD_PATH . 'vendor/autoload.php';

if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'AcfVersionedContentField\\';
        $base_dir = ACF_VERSIONED_CONTENT_FIELD_PATH . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

register_activation_hook(__FILE__, [AcfVersionedContentField\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [AcfVersionedContentField\Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    $plugin = AcfVersionedContentField\Plugin::getInstance(ACF_VERSIONED_CONTENT_FIELD_FILE);
    $plugin->init();
}, 15);
