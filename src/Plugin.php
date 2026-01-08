<?php

declare(strict_types=1);

namespace AcfVersionedContentField;

use AcfVersionedContentField\Fields\VersionedContentField;

/**
 * Main plugin class.
 */
final class Plugin
{
    private const VERSION = '1.0.0';
    private const MIN_PHP_VERSION = '8.1';

    private static ?self $instance = null;
    private string $plugin_file;

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public static function getInstance(string $plugin_file = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    public function init(): void
    {
        if (!$this->checkRequirements()) {
            return;
        }

        $this->loadTextdomain();

        add_action('acf/include_field_types', [$this, 'registerFieldType']);
    }

    private function checkRequirements(): bool
    {
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function (): void {
                $message = sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    __('ACF Versioned Content Field requires PHP %1$s or higher. You are running PHP %2$s.', 'acf-versioned-content-field'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                );
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
            });

            return false;
        }

        if (!$this->isAcfActive()) {
            add_action('admin_notices', function (): void {
                $message = __('ACF Versioned Content Field requires Advanced Custom Fields to be installed and activated.', 'acf-versioned-content-field');
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
            });

            return false;
        }

        return true;
    }

    private function isAcfActive(): bool
    {
        return class_exists('ACF') || class_exists('acf');
    }

    private function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'acf-versioned-content-field',
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    public function registerFieldType(): void
    {
        new VersionedContentField($this->plugin_file);
    }

    public static function activate(): void
    {
        // Placeholder for activation tasks.
    }

    public static function deactivate(): void
    {
        // Placeholder for deactivation tasks.
    }

    public static function getVersion(): string
    {
        return self::VERSION;
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
