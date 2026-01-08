<?php

declare(strict_types=1);

namespace AcfVersionedContentField\Fields;

/**
 * ACF Versioned Content field type.
 */
class VersionedContentField extends \acf_field
{
    private string $plugin_file;

    /**
     * @var array<string, mixed>
     */
    public $settings = [];

    /**
     * @var array<string, mixed>
     */
    public $defaults = [];

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;

        $this->name = 'versioned_content';
        $this->label = __('Versioned Content', 'acf-versioned-content-field');
        $this->category = 'content';
        $this->defaults = [
            'max_versions' => 10,
            'return_format' => 'current',
            'rows' => 8,
            'enable_notes' => true,
            'show_history' => true,
        ];

        parent::__construct();
    }

    /**
     * Render field settings in the field group editor.
     *
     * @param array<string, mixed> $field
     */
    public function render_field_settings(array $field): void
    {
        acf_render_field_setting($field, [
            'label' => __('Max Versions', 'acf-versioned-content-field'),
            'instructions' => __('Limit the number of previous versions kept. Use 0 for unlimited.', 'acf-versioned-content-field'),
            'type' => 'number',
            'name' => 'max_versions',
            'min' => 0,
            'step' => 1,
            'default_value' => $this->defaults['max_versions'],
        ]);

        acf_render_field_setting($field, [
            'label' => __('Return Format', 'acf-versioned-content-field'),
            'instructions' => __('Choose what value is returned by get_field().', 'acf-versioned-content-field'),
            'type' => 'select',
            'name' => 'return_format',
            'choices' => [
                'current' => __('Current content only', 'acf-versioned-content-field'),
                'all' => __('Current content + version history', 'acf-versioned-content-field'),
            ],
            'default_value' => $this->defaults['return_format'],
            'ui' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Rows', 'acf-versioned-content-field'),
            'instructions' => __('Textarea rows for the editor.', 'acf-versioned-content-field'),
            'type' => 'number',
            'name' => 'rows',
            'min' => 3,
            'step' => 1,
            'default_value' => $this->defaults['rows'],
        ]);

        acf_render_field_setting($field, [
            'label' => __('Enable Change Notes', 'acf-versioned-content-field'),
            'instructions' => __('Allow editors to add a change note for each version.', 'acf-versioned-content-field'),
            'type' => 'true_false',
            'name' => 'enable_notes',
            'ui' => 1,
            'default_value' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Show Version History', 'acf-versioned-content-field'),
            'instructions' => __('Display previous versions in the editor UI.', 'acf-versioned-content-field'),
            'type' => 'true_false',
            'name' => 'show_history',
            'ui' => 1,
            'default_value' => 1,
        ]);
    }

    /**
     * Render field input.
     *
     * @param array<string, mixed> $field
     */
    public function render_field(array $field): void
    {
        $field = wp_parse_args($field, $this->defaults);
        $value = $this->normalize_value($field['value'] ?? null);

        $current = $value['current'];
        $versions = $value['versions'];
        $rows = (int) $field['rows'];
        $enable_notes = (bool) $field['enable_notes'];
        $show_history = (bool) $field['show_history'];

        $field_name = esc_attr($field['name']);
        $current_content = $current['content'];
        $current_note = $current['note'];

        $last_saved = $this->format_version_meta($current);
        ?>
        <div class="acf-versioned-content-field" data-field-key="<?php echo esc_attr((string) ($field['key'] ?? '')); ?>">
            <div class="acf-versioned-content-editor">
                <label class="acf-versioned-content-label">
                    <strong><?php echo esc_html__('Current Content', 'acf-versioned-content-field'); ?></strong>
                    <?php if ($last_saved !== ''): ?>
                        <span class="acf-versioned-content-meta"><?php echo esc_html($last_saved); ?></span>
                    <?php endif; ?>
                </label>
                <textarea
                    class="acf-versioned-content-textarea"
                    name="<?php echo $field_name; ?>[current][content]"
                    rows="<?php echo esc_attr((string) $rows); ?>"
                    data-role="current-content"
                ><?php echo esc_textarea($current_content); ?></textarea>
                <input type="hidden" name="<?php echo $field_name; ?>[current][id]" value="<?php echo esc_attr($current['id']); ?>" />
                <input type="hidden" name="<?php echo $field_name; ?>[current][created_at]" value="<?php echo esc_attr($current['created_at']); ?>" />
                <input type="hidden" name="<?php echo $field_name; ?>[current][author_id]" value="<?php echo esc_attr((string) $current['author_id']); ?>" />
                <input type="hidden" name="<?php echo $field_name; ?>[current][author_name]" value="<?php echo esc_attr($current['author_name']); ?>" />
                <input type="hidden" name="<?php echo $field_name; ?>[current][note]" value="<?php echo esc_attr($current_note); ?>" />
                <input type="hidden" name="<?php echo $field_name; ?>[current_original]" value="<?php echo esc_attr($current_content); ?>" />
            </div>

            <?php if ($enable_notes): ?>
                <div class="acf-versioned-content-note">
                    <label for="<?php echo esc_attr($field['id'] ?? $field['key']); ?>-note">
                        <?php echo esc_html__('Change Note', 'acf-versioned-content-field'); ?>
                    </label>
                    <input
                        type="text"
                        class="acf-versioned-content-note-input"
                        id="<?php echo esc_attr($field['id'] ?? $field['key']); ?>-note"
                        name="<?php echo $field_name; ?>[note]"
                        value=""
                        placeholder="<?php echo esc_attr__('Describe what changed (optional)', 'acf-versioned-content-field'); ?>"
                        data-role="change-note"
                    />
                </div>
            <?php endif; ?>

            <?php if ($show_history): ?>
                <div class="acf-versioned-content-history" data-max-versions="<?php echo esc_attr((string) $field['max_versions']); ?>">
                    <div class="acf-versioned-content-history-header">
                        <strong><?php echo esc_html__('Version History', 'acf-versioned-content-field'); ?></strong>
                        <span class="acf-versioned-content-history-count">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: number of versions */
                                    __('%d previous versions', 'acf-versioned-content-field'),
                                    count($versions)
                                )
                            );
                            ?>
                        </span>
                    </div>
                    <?php if (empty($versions)): ?>
                        <p class="acf-versioned-content-empty">
                            <?php echo esc_html__('No previous versions yet.', 'acf-versioned-content-field'); ?>
                        </p>
                    <?php else: ?>
                        <ul class="acf-versioned-content-versions">
                            <?php foreach ($versions as $index => $version): ?>
                                <li class="acf-versioned-content-version" data-version>
                                    <div class="acf-versioned-content-version-meta" data-role="version-meta" data-summary="<?php echo esc_attr($this->format_version_meta($version)); ?>">
                                        <span class="acf-versioned-content-version-title">
                                            <?php echo esc_html($this->format_version_label($version, $index)); ?>
                                        </span>
                                        <?php if (!empty($version['note'])): ?>
                                            <span class="acf-versioned-content-version-note">
                                                <?php echo esc_html($version['note']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="acf-versioned-content-version-actions">
                                        <button type="button" class="button button-small" data-action="restore-version">
                                            <?php echo esc_html__('Restore', 'acf-versioned-content-field'); ?>
                                        </button>
                                    </div>
                                    <details class="acf-versioned-content-version-preview">
                                        <summary><?php echo esc_html__('Preview', 'acf-versioned-content-field'); ?></summary>
                                        <pre><?php echo esc_html($this->trim_preview($version['content'])); ?></pre>
                                    </details>
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][id]" value="<?php echo esc_attr($version['id']); ?>" />
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][content]" value="<?php echo esc_attr($version['content']); ?>" data-role="version-content" />
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][note]" value="<?php echo esc_attr($version['note']); ?>" />
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][author_id]" value="<?php echo esc_attr((string) $version['author_id']); ?>" />
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][author_name]" value="<?php echo esc_attr($version['author_name']); ?>" />
                                    <input type="hidden" name="<?php echo $field_name; ?>[versions][<?php echo esc_attr((string) $index); ?>][created_at]" value="<?php echo esc_attr($version['created_at']); ?>" />
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue scripts/styles for the input UI.
     */
    public function input_admin_enqueue_scripts(): void
    {
        $version = defined('ACF_VERSIONED_CONTENT_FIELD_VERSION') ? ACF_VERSIONED_CONTENT_FIELD_VERSION : '1.0.0';
        $url = plugin_dir_url($this->plugin_file);

        wp_register_script(
            'acf-versioned-content-field-input',
            $url . 'assets/js/input.js',
            ['acf-input', 'jquery'],
            $version,
            true
        );

        wp_register_style(
            'acf-versioned-content-field-input',
            $url . 'assets/css/input.css',
            ['acf-input'],
            $version
        );

        wp_localize_script('acf-versioned-content-field-input', 'acfVersionedContentField', [
            'i18n' => [
                'restoredNote' => __('Restored previous version', 'acf-versioned-content-field'),
            ],
        ]);

        wp_enqueue_script('acf-versioned-content-field-input');
        wp_enqueue_style('acf-versioned-content-field-input');
    }

    /**
     * Prepare value for saving.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return array<string, mixed>|string
     */
    public function update_value($value, $post_id, array $field)
    {
        $field = wp_parse_args($field, $this->defaults);
        $raw = is_array($value) ? $value : [];

        $current_input = $this->sanitize_content($raw['current']['content'] ?? '');
        $current_original = $this->sanitize_content($raw['current_original'] ?? ($raw['current']['content'] ?? ''));
        $note = $this->sanitize_note($raw['note'] ?? '');

        $current = $this->sanitize_version($raw['current'] ?? []);
        $current['content'] = $current_original;

        $versions = $this->sanitize_versions($raw['versions'] ?? []);

        if ($current_input === '' && $current_original === '' && empty($versions)) {
            return '';
        }

        $current_changed = $current_input !== $current_original;

        if ($current_changed) {
            if ($current_original !== '') {
                $versions = $this->prepend_version($current, $versions);
            }
            $current = $this->build_version_entry($current_input, $note);
        } else {
            if ($current_input !== '' && $current['id'] === '') {
                $current = $this->build_version_entry(
                    $current_input,
                    $note,
                    $current['author_id'] > 0 ? $current['author_id'] : null,
                    $current['created_at'] !== '' ? $current['created_at'] : null,
                    $current['id'] !== '' ? $current['id'] : null,
                    $current['author_name'] !== '' ? $current['author_name'] : null
                );
            } else {
                $current['content'] = $current_input;
            }
        }

        $versions = $this->trim_versions($versions, (int) $field['max_versions']);

        return [
            'current' => $current,
            'versions' => array_values($versions),
        ];
    }

    /**
     * Format value for frontend.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return mixed
     */
    public function format_value($value, $post_id, array $field)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $normalized = $this->normalize_value($value);
        $return_format = $field['return_format'] ?? $this->defaults['return_format'];

        if ($return_format === 'current') {
            return $normalized['current']['content'] ?? '';
        }

        return $normalized;
    }

    /**
     * Validate the field value.
     *
     * @param bool|string $valid
     * @param mixed $value
     * @param array<string, mixed> $field
     * @param string $input
     * @return bool|string
     */
    public function validate_value($valid, $value, array $field, string $input)
    {
        if ($valid !== true) {
            return $valid;
        }

        if (!empty($field['required'])) {
            $content = '';
            if (is_array($value)) {
                $content = (string) ($value['current']['content'] ?? '');
            } elseif (is_string($value)) {
                $content = $value;
            }

            if (trim($content) === '') {
                return __('Please enter content.', 'acf-versioned-content-field');
            }
        }

        return $valid;
    }

    /**
     * Schema for REST API responses.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function get_rest_schema(array $field): array
    {
        $return_format = $field['return_format'] ?? $this->defaults['return_format'];

        if ($return_format === 'current') {
            return [
                'type' => 'string',
                'description' => __('Current version content.', 'acf-versioned-content-field'),
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'current' => $this->get_version_schema(__('Current version.', 'acf-versioned-content-field')),
                'versions' => [
                    'type' => 'array',
                    'items' => $this->get_version_schema(__('Historical version.', 'acf-versioned-content-field')),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get_version_schema(string $description): array
    {
        return [
            'type' => 'object',
            'description' => $description,
            'properties' => [
                'id' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'author_id' => ['type' => 'integer'],
                'author_name' => ['type' => 'string'],
                'created_at' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalize_value($value): array
    {
        if (is_string($value)) {
            $current = $this->empty_version();
            $current['content'] = $this->sanitize_content($value);

            return [
                'current' => $current,
                'versions' => [],
            ];
        }

        if (!is_array($value)) {
            return [
                'current' => $this->empty_version(),
                'versions' => [],
            ];
        }

        $current = $this->sanitize_version($value['current'] ?? []);
        $current['content'] = $this->sanitize_content($value['current']['content'] ?? $current['content']);

        return [
            'current' => $current,
            'versions' => $this->sanitize_versions($value['versions'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $version
     * @return array<string, mixed>
     */
    private function sanitize_version(array $version): array
    {
        return [
            'id' => $this->sanitize_id($version['id'] ?? ''),
            'content' => $this->sanitize_content($version['content'] ?? ''),
            'note' => $this->sanitize_note($version['note'] ?? ''),
            'author_id' => (int) ($version['author_id'] ?? 0),
            'author_name' => $this->sanitize_note($version['author_name'] ?? ''),
            'created_at' => $this->sanitize_note($version['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<int|string, mixed> $versions
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_versions($versions): array
    {
        if (!is_array($versions)) {
            return [];
        }

        $sanitized = [];
        foreach ($versions as $version) {
            if (!is_array($version)) {
                continue;
            }
            $entry = $this->sanitize_version($version);
            if ($entry['content'] === '') {
                continue;
            }
            $sanitized[] = $entry;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $version
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function prepend_version(array $version, array $versions): array
    {
        $entry = $this->sanitize_version($version);
        if ($entry['content'] === '') {
            return $versions;
        }

        array_unshift($versions, $entry);
        return $versions;
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     * @return array<int, array<string, mixed>>
     */
    private function trim_versions(array $versions, int $max): array
    {
        if ($max <= 0) {
            return $versions;
        }

        return array_slice($versions, 0, $max);
    }

    /**
     * @return array<string, mixed>
     */
    private function build_version_entry(
        string $content,
        string $note,
        ?int $author_id = null,
        ?string $created_at = null,
        ?string $id = null,
        ?string $author_name = null
    ): array {
        $author_id = $author_id ?? (int) get_current_user_id();
        $author_name = $author_name ?? $this->get_author_name($author_id);

        return [
            'id' => $id ?? $this->generate_id(),
            'content' => $this->sanitize_content($content),
            'note' => $this->sanitize_note($note),
            'author_id' => $author_id,
            'author_name' => $this->sanitize_note($author_name),
            'created_at' => $created_at ?? current_time('mysql'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_version(): array
    {
        return [
            'id' => '',
            'content' => '',
            'note' => '',
            'author_id' => 0,
            'author_name' => '',
            'created_at' => '',
        ];
    }

    private function sanitize_content($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = wp_unslash($value);
        return wp_kses_post($value);
    }

    private function sanitize_note($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash($value));
    }

    private function sanitize_id($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_text_field($value);
    }

    private function generate_id(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        return uniqid('v_', true);
    }

    private function get_author_name(int $author_id): string
    {
        if ($author_id <= 0) {
            return '';
        }

        $user = get_userdata($author_id);
        if ($user && isset($user->display_name)) {
            return (string) $user->display_name;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $version
     */
    private function format_version_meta(array $version): string
    {
        $parts = [];

        if (!empty($version['created_at'])) {
            $parts[] = $version['created_at'];
        }

        if (!empty($version['author_name'])) {
            $parts[] = $version['author_name'];
        }

        return implode(' - ', $parts);
    }

    /**
     * @param array<string, mixed> $version
     */
    private function format_version_label(array $version, int $index): string
    {
        $label = sprintf(
            /* translators: %d: version index */
            __('Version %d', 'acf-versioned-content-field'),
            $index + 1
        );

        if (!empty($version['created_at'])) {
            $label .= ' - ' . $version['created_at'];
        }

        if (!empty($version['author_name'])) {
            $label .= ' - ' . $version['author_name'];
        }

        return $label;
    }

    private function trim_preview(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $excerpt = substr($content, 0, 240);
        if (strlen($content) > 240) {
            $excerpt .= '...';
        }

        return $excerpt;
    }
}
