<?php

/**
 * Plugin Name: Advanced Custom Fields: Versioned Content Field
 * Plugin URI: https://github.com/Vheissu/acf-versioned-content-field
 * Description: Adds a versioned content field type to Advanced Custom Fields.
 * Version: 1.1.0
 * Author: Dwayne Charrington
 * Author URI: https://ilikekillnerds.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-versioned-content-field
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package AcfVersionedContentField
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ACF_VERSIONED_CONTENT_FIELD_VERSION', '1.1.0');
define('ACF_VERSIONED_CONTENT_FIELD_FILE', __FILE__);
define('ACF_VERSIONED_CONTENT_FIELD_PATH', plugin_dir_path(__FILE__));
define('ACF_VERSIONED_CONTENT_FIELD_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'AcfVersionedContentField\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $file = ACF_VERSIONED_CONTENT_FIELD_PATH . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

(new AcfVersionedContentField\Plugin(ACF_VERSIONED_CONTENT_FIELD_FILE))->init();
