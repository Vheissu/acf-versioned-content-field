<?php

declare(strict_types=1);

namespace AcfVersionedContentField;

use AcfVersionedContentField\Fields\VersionedContentField;

/**
 * Wires the plugin into WordPress and ACF.
 */
final class Plugin
{
    private string $plugin_file;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public function init(): void
    {
        // Before ACF registers field types on init (priority 5), so labels are translated.
        add_action('init', [$this, 'loadTextdomain'], 0);
        add_action('acf/include_field_types', [$this, 'registerFieldType']);
        add_action('admin_notices', [$this, 'renderMissingAcfNotice']);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'acf-versioned-content-field',
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    public function registerFieldType(): void
    {
        acf_register_field_type(new VersionedContentField($this->plugin_file));
    }

    /**
     * Warn administrators when ACF isn't loaded. Checked late so ACF bundled in a theme still counts.
     */
    public function renderMissingAcfNotice(): void
    {
        if (class_exists('ACF') || !current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'ACF Versioned Content Field requires Advanced Custom Fields to be installed and activated.',
                'acf-versioned-content-field'
            )
        );
    }
}
