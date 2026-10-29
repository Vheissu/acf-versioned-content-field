<?php

declare(strict_types=1);

use AcfVersionedContentField\Fields\VersionedContentField;
use AcfVersionedContentField\Plugin;
use Brain\Monkey\Functions;

describe('Plugin', function (): void {
    it('registers its hooks', function (): void {
        $plugin = new Plugin(__FILE__);
        $plugin->init();

        expect(has_action('init', [$plugin, 'loadTextdomain']))->toBe(0)
            ->and(has_action('acf/include_field_types', [$plugin, 'registerFieldType']))->toBe(10)
            ->and(has_action('admin_notices', [$plugin, 'renderMissingAcfNotice']))->toBe(10);
    });

    it('registers the field type with ACF', function (): void {
        Functions\expect('acf_register_field_type')
            ->once()
            ->with(Mockery::type(VersionedContentField::class));

        (new Plugin(__FILE__))->registerFieldType();
    });

    it('loads translations from the languages folder', function (): void {
        Functions\when('plugin_basename')->justReturn('acf-versioned-content-field/acf-versioned-content-field.php');
        Functions\expect('load_plugin_textdomain')
            ->once()
            ->with('acf-versioned-content-field', false, 'acf-versioned-content-field/languages');

        (new Plugin(__FILE__))->loadTextdomain();
    });

    it('shows a notice to administrators when ACF is missing', function (): void {
        Functions\when('current_user_can')->justReturn(true);

        ob_start();
        (new Plugin(__FILE__))->renderMissingAcfNotice();

        expect((string) ob_get_clean())->toContain('requires Advanced Custom Fields');
    });

    it('hides the notice from users who cannot manage plugins', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        ob_start();
        (new Plugin(__FILE__))->renderMissingAcfNotice();

        expect((string) ob_get_clean())->toBe('');
    });
});
