<?php

declare(strict_types=1);

namespace AcfVersionedContentField\Fields;

/**
 * ACF field type that keeps a history of previous content.
 *
 * The stored value is an array holding the current version and a newest-first
 * list of previous versions. A new version is recorded only when the content
 * changes, and history is always rebuilt on the server: the form submits the
 * new content and an optional change note, plus a signed copy of the history
 * it was rendered with so rows keep their own history when a repeater is
 * reordered.
 *
 * @phpstan-type Version array{
 *     id: string,
 *     content: string,
 *     note: string,
 *     author_id: int,
 *     author_name: string,
 *     created_at: string
 * }
 * @phpstan-type History array{current: Version, versions: list<Version>}
 */
class VersionedContentField extends \acf_field
{
    public const TYPE = 'versioned_content';

    private const TEXT_DOMAIN = 'acf-versioned-content-field';
    private const PREVIEW_LENGTH = 240;

    private string $plugin_file;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;

        parent::__construct();
    }

    /**
     * Set up the field type. Called by the acf_field constructor.
     */
    public function initialize(): void
    {
        $this->name = self::TYPE;
        $this->label = __('Versioned Content', self::TEXT_DOMAIN);
        $this->category = 'content';
        $this->description = __('A textarea that keeps a history of previous content.', self::TEXT_DOMAIN);
        $this->defaults = [
            'max_versions' => 10,
            'return_format' => 'current',
            'rows' => 8,
            'enable_notes' => 1,
            'show_history' => 1,
        ];
    }

    /**
     * Render field settings in the field group editor.
     *
     * @param array<string, mixed> $field
     */
    public function render_field_settings(array $field): void
    {
        acf_render_field_setting($field, [
            'label' => __('Max Versions', self::TEXT_DOMAIN),
            'instructions' => __('Limit the number of previous versions kept. Use 0 for unlimited.', self::TEXT_DOMAIN),
            'type' => 'number',
            'name' => 'max_versions',
            'min' => 0,
            'step' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Return Format', self::TEXT_DOMAIN),
            'instructions' => __('Choose what value is returned by get_field().', self::TEXT_DOMAIN),
            'type' => 'select',
            'name' => 'return_format',
            'choices' => [
                'current' => __('Current content only', self::TEXT_DOMAIN),
                'all' => __('Current content + version history', self::TEXT_DOMAIN),
            ],
            'ui' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Rows', self::TEXT_DOMAIN),
            'instructions' => __('Textarea rows for the editor.', self::TEXT_DOMAIN),
            'type' => 'number',
            'name' => 'rows',
            'min' => 3,
            'step' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Enable Change Notes', self::TEXT_DOMAIN),
            'instructions' => __('Allow editors to add a change note for each version.', self::TEXT_DOMAIN),
            'type' => 'true_false',
            'name' => 'enable_notes',
            'ui' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Show Version History', self::TEXT_DOMAIN),
            'instructions' => __('Display previous versions in the editor UI.', self::TEXT_DOMAIN),
            'type' => 'true_false',
            'name' => 'show_history',
            'ui' => 1,
        ]);
    }

    /**
     * Render the field input.
     *
     * @param array<string, mixed> $field
     */
    public function render_field(array $field): void
    {
        $field = wp_parse_args($field, $this->defaults);
        $history = $this->normalize_value($field['value'] ?? null);
        $state = $this->encode_state($history);

        $name = (string) $field['name'];
        $note_id = ((string) ($field['id'] ?? $field['key'] ?? '')) . '-note';
        $current = $history['current'];
        $versions = $history['versions'];
        $total = count($versions);
        $last_saved = $this->format_version_meta($current);
        ?>
        <div class="acf-versioned-content-field">
            <div class="acf-versioned-content-editor">
                <label class="acf-versioned-content-label">
                    <strong><?php echo esc_html__('Current Content', self::TEXT_DOMAIN); ?></strong>
                    <?php if ($last_saved !== '') : ?>
                        <span class="acf-versioned-content-meta"><?php echo esc_html($last_saved); ?></span>
                    <?php endif; ?>
                </label>
                <textarea
                    class="acf-versioned-content-textarea"
                    name="<?php echo esc_attr($name); ?>[content]"
                    rows="<?php echo esc_attr((string) (int) $field['rows']); ?>"
                    data-role="current-content"
                ><?php echo esc_textarea($current['content']); ?></textarea>
                <input type="hidden" name="<?php echo esc_attr($name); ?>[state]" value="<?php echo esc_attr($state); ?>" />
                <input type="hidden" name="<?php echo esc_attr($name); ?>[signature]" value="<?php echo esc_attr($this->sign_state($state, $field)); ?>" />
            </div>

            <?php if ((bool) $field['enable_notes']) : ?>
                <div class="acf-versioned-content-note">
                    <label for="<?php echo esc_attr($note_id); ?>">
                        <?php echo esc_html__('Change Note', self::TEXT_DOMAIN); ?>
                    </label>
                    <input
                        type="text"
                        class="acf-versioned-content-note-input"
                        id="<?php echo esc_attr($note_id); ?>"
                        name="<?php echo esc_attr($name); ?>[note]"
                        value=""
                        placeholder="<?php echo esc_attr__('Describe what changed (optional)', self::TEXT_DOMAIN); ?>"
                        data-role="change-note"
                    />
                </div>
            <?php endif; ?>

            <?php if ((bool) $field['show_history']) : ?>
                <div class="acf-versioned-content-history">
                    <div class="acf-versioned-content-history-header">
                        <strong><?php echo esc_html__('Version History', self::TEXT_DOMAIN); ?></strong>
                        <span class="acf-versioned-content-history-count">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %d: number of previous versions */
                                _n('%d previous version', '%d previous versions', $total, self::TEXT_DOMAIN),
                                $total
                            ));
                            ?>
                        </span>
                    </div>
                    <?php if ($total === 0) : ?>
                        <p class="acf-versioned-content-empty">
                            <?php echo esc_html__('No previous versions yet.', self::TEXT_DOMAIN); ?>
                        </p>
                    <?php else : ?>
                        <ul class="acf-versioned-content-versions">
                            <?php foreach ($versions as $index => $version) : ?>
                                <li class="acf-versioned-content-version" data-version>
                                    <div class="acf-versioned-content-version-meta" data-role="version-meta" data-summary="<?php echo esc_attr($this->format_version_meta($version)); ?>">
                                        <span class="acf-versioned-content-version-title">
                                            <?php echo esc_html($this->format_version_label($version, $total - $index)); ?>
                                        </span>
                                        <?php if ($version['note'] !== '') : ?>
                                            <span class="acf-versioned-content-version-note">
                                                <?php echo esc_html($version['note']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="acf-versioned-content-version-actions">
                                        <button type="button" class="button button-small" data-action="restore-version">
                                            <?php echo esc_html__('Restore', self::TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                    <details class="acf-versioned-content-version-preview">
                                        <summary><?php echo esc_html__('Preview', self::TEXT_DOMAIN); ?></summary>
                                        <pre><?php echo esc_html($this->trim_preview($version['content'])); ?></pre>
                                    </details>
                                    <textarea hidden readonly data-role="version-content"><?php echo esc_textarea($version['content']); ?></textarea>
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
     * Enqueue scripts and styles for the input UI.
     */
    public function input_admin_enqueue_scripts(): void
    {
        $version = defined('ACF_VERSIONED_CONTENT_FIELD_VERSION') ? ACF_VERSIONED_CONTENT_FIELD_VERSION : false;
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
                'restoredNote' => __('Restored previous version', self::TEXT_DOMAIN),
            ],
        ]);

        wp_enqueue_script('acf-versioned-content-field-input');
        wp_enqueue_style('acf-versioned-content-field-input');
    }

    /**
     * Build the value to store, recording a new version when the content changes.
     *
     * Accepts the editor form submission, a plain string (e.g. from update_field()),
     * or a full history array (e.g. from the REST API). Only the content and note are
     * taken from the input; previous versions always come from the signed form state
     * or the stored value, so history can't be rewritten through this method.
     *
     * @param mixed $value Slashed input, as WordPress passes it to meta functions.
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return array<string, mixed>|string The history array, slashed for update_metadata(), or '' when empty.
     */
    public function update_value($value, $post_id, array $field)
    {
        $field = wp_parse_args($field, $this->defaults);
        $value = wp_unslash($value);

        $content = $this->sanitize_content($this->extract_content($value));
        $note = is_array($value) ? $this->sanitize_note($value['note'] ?? '') : '';

        $history = $this->submitted_history($value, $field) ?? $this->stored_history($post_id, $field);
        $current = $history['current'];

        if ($content !== $current['content']) {
            if ($current['content'] !== '') {
                $history['versions'] = [$current, ...$history['versions']];
            }
            $history['current'] = $this->new_version($content, $note);
        } elseif ($content !== '' && $current['id'] === '') {
            // Content saved before this field type was used (e.g. as a textarea) has no metadata yet.
            $history['current'] = $this->new_version($content, $note);
        }

        if ($history['current']['content'] === '' && $history['versions'] === []) {
            return '';
        }

        $max_versions = (int) $field['max_versions'];
        if ($max_versions > 0) {
            $history['versions'] = array_slice($history['versions'], 0, $max_versions);
        }

        return wp_slash($history);
    }

    /**
     * Format the stored value for templates.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return History|string|null
     */
    public function format_value($value, $post_id, array $field)
    {
        if ($value === '' || $value === null || $value === false) {
            return null;
        }

        $history = $this->normalize_value($value);

        if (($field['return_format'] ?? $this->defaults['return_format']) === 'all') {
            return $history;
        }

        return $history['current']['content'];
    }

    /**
     * Format the stored value for REST API responses so they match get_rest_schema().
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return History|string|null
     */
    public function format_value_for_rest($value, $post_id, array $field)
    {
        return $this->format_value($value, $post_id, $field);
    }

    /**
     * Validate the submitted value.
     *
     * @param bool|string $valid
     * @param mixed $value
     * @param array<string, mixed> $field
     * @param string $input
     * @return bool|string
     */
    public function validate_value($valid, $value, array $field, $input)
    {
        if ($valid !== true || empty($field['required'])) {
            return $valid;
        }

        if (trim($this->extract_content($value)) === '') {
            return __('Please enter content.', self::TEXT_DOMAIN);
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
        if (($field['return_format'] ?? $this->defaults['return_format']) !== 'all') {
            return [
                'type' => ['string', 'null'],
                'description' => __('Current version content.', self::TEXT_DOMAIN),
            ];
        }

        return [
            'type' => ['object', 'null'],
            'properties' => [
                'current' => $this->get_version_schema(__('Current version.', self::TEXT_DOMAIN)),
                'versions' => [
                    'type' => 'array',
                    'description' => __('Previous versions, newest first.', self::TEXT_DOMAIN),
                    'items' => $this->get_version_schema(__('Previous version.', self::TEXT_DOMAIN)),
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
     * Pull the new content out of any supported input shape.
     *
     * @param mixed $value
     */
    private function extract_content($value): string
    {
        if (is_array($value)) {
            $value = $value['content'] ?? $value['current']['content'] ?? '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * History the form was rendered with, if its signature checks out.
     *
     * @param mixed $value
     * @param array<string, mixed> $field
     * @return History|null
     */
    private function submitted_history($value, array $field): ?array
    {
        if (!is_array($value) || !is_string($value['state'] ?? null) || !is_string($value['signature'] ?? null)) {
            return null;
        }

        if (!hash_equals($this->sign_state($value['state'], $field), $value['signature'])) {
            return null;
        }

        $decoded = json_decode((string) base64_decode($value['state'], true), true);

        return is_array($decoded) ? $this->normalize_value($decoded) : null;
    }

    /**
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return History
     */
    private function stored_history($post_id, array $field): array
    {
        $stored = function_exists('acf_get_metadata_by_field')
            ? acf_get_metadata_by_field($post_id, $field)
            : acf_get_metadata($post_id, (string) $field['name']);

        return $this->normalize_value($stored);
    }

    /**
     * @param History $history
     */
    private function encode_state(array $history): string
    {
        $json = wp_json_encode($history);

        return $json === false ? '' : base64_encode($json);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function sign_state(string $state, array $field): string
    {
        return wp_hash(self::TYPE . '|' . ($field['key'] ?? '') . '|' . $state);
    }

    /**
     * Coerce a stored value into a complete history array.
     *
     * Plain strings are treated as current content with no metadata, which covers
     * values saved before the field was switched to this type.
     *
     * @param mixed $value
     * @return History
     */
    private function normalize_value($value): array
    {
        if (is_scalar($value) && !is_bool($value)) {
            $value = ['current' => ['content' => (string) $value]];
        }

        if (!is_array($value)) {
            $value = [];
        }

        $versions = [];
        foreach (is_array($value['versions'] ?? null) ? $value['versions'] : [] as $version) {
            $version = $this->normalize_version($version);
            if ($version['content'] !== '') {
                $versions[] = $version;
            }
        }

        return [
            'current' => $this->normalize_version($value['current'] ?? []),
            'versions' => $versions,
        ];
    }

    /**
     * @param mixed $version
     * @return Version
     */
    private function normalize_version($version): array
    {
        $version = is_array($version) ? $version : [];

        return [
            'id' => $this->to_string($version['id'] ?? ''),
            'content' => $this->to_string($version['content'] ?? ''),
            'note' => $this->to_string($version['note'] ?? ''),
            'author_id' => (int) $this->to_string($version['author_id'] ?? 0),
            'author_name' => $this->to_string($version['author_name'] ?? ''),
            'created_at' => $this->to_string($version['created_at'] ?? ''),
        ];
    }

    /**
     * @return Version
     */
    private function new_version(string $content, string $note): array
    {
        $author_id = get_current_user_id();
        $author = $author_id > 0 ? get_userdata($author_id) : false;

        return [
            'id' => wp_generate_uuid4(),
            'content' => $content,
            'note' => $note,
            'author_id' => $author_id,
            'author_name' => $author ? $author->display_name : '',
            'created_at' => current_time('mysql'),
        ];
    }

    /**
     * @param mixed $value
     */
    private function sanitize_content($value): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $this->to_string($value));

        return wp_kses_post($content);
    }

    /**
     * @param mixed $value
     */
    private function sanitize_note($value): string
    {
        return sanitize_text_field($this->to_string($value));
    }

    /**
     * @param mixed $value
     */
    private function to_string($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param Version $version
     */
    private function format_version_meta(array $version): string
    {
        $parts = [];

        if ($version['created_at'] !== '') {
            $date = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $version['created_at']);
            $parts[] = is_string($date) && $date !== '' ? $date : $version['created_at'];
        }

        if ($version['author_name'] !== '') {
            $parts[] = $version['author_name'];
        }

        return implode(' - ', $parts);
    }

    /**
     * @param Version $version
     */
    private function format_version_label(array $version, int $number): string
    {
        $label = sprintf(
            /* translators: %d: version number, counting up from the oldest kept version */
            __('Version %d', self::TEXT_DOMAIN),
            $number
        );

        $meta = $this->format_version_meta($version);

        return $meta === '' ? $label : $label . ' - ' . $meta;
    }

    private function trim_preview(string $content): string
    {
        $content = trim($content);

        if (mb_strlen($content) <= self::PREVIEW_LENGTH) {
            return $content;
        }

        return mb_substr($content, 0, self::PREVIEW_LENGTH) . '...';
    }
}
