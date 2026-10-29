# Advanced Custom Fields: Versioned Content Field

Adds a **Versioned Content** field type to Advanced Custom Fields (ACF). Editors write in a single textarea. Each time the content changes, the previous content is kept as a version with an optional change note, a timestamp and the author. Older versions can be previewed and restored from the editor.

## Features

- Records a new version only when the content changes
- Optional change note for each version
- Preview and restore previous versions in the editor
- Limit how many versions are kept
- Return just the current content or the full history from `get_field()`
- Works with `update_field()` and the ACF REST API, which also record versions

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Advanced Custom Fields 6.0+ (free or Pro)

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate **Advanced Custom Fields: Versioned Content Field** on the Plugins screen.
3. Add a field to a field group and choose **Versioned Content** (under Content).

The plugin needs no Composer install to run. Composer is only used for development.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| Max Versions | `10` | Number of previous versions to keep. `0` keeps all of them. The oldest are dropped first. |
| Return Format | Current content only | `current` returns a string. `all` returns the current version and its history. |
| Rows | `8` | Height of the editor textarea. |
| Enable Change Notes | On | Shows a change note input under the textarea. |
| Show Version History | On | Lists previous versions with Preview and Restore controls. |

## Usage

### Templates

```php
// Return format "current": the latest content as a string.
echo wp_kses_post(get_field('my_versioned_content'));

// Return format "all": the current version and its history.
$value = get_field('my_versioned_content');

foreach ($value['versions'] as $version) {
    printf('%s by %s', $version['created_at'], $version['author_name']);
}

// The raw stored value, whatever the return format.
$raw = get_field('my_versioned_content', false, false);
```

`get_field()` returns `null` when the field has never been saved.

### Saving from code

`update_field()` takes the new content as a string. If it differs from the current content, the current version moves into history, just like a save from the editor:

```php
update_field('my_versioned_content', 'New content', $post_id);
```

Passing an array with a `content` key (and optionally a `note`) works too. Any history included in the value you pass is ignored, so code and REST requests can add versions but can't rewrite existing ones. To import history, write the meta directly with `update_post_meta()` using the structure below.

### Stored value

Versions are listed newest first. `created_at` is in site time (`current_time('mysql')`).

```php
[
    'current' => [
        'id' => '0b6c1e8e-...',
        'content' => 'Latest content',
        'note' => 'What changed',
        'author_id' => 123,
        'author_name' => 'Editor Name',
        'created_at' => '2026-01-08 12:00:00',
    ],
    'versions' => [
        [
            'id' => '6f1d2a4c-...',
            'content' => 'Previous content',
            'note' => 'Earlier note',
            'author_id' => 123,
            'author_name' => 'Editor Name',
            'created_at' => '2026-01-07 10:00:00',
        ],
    ],
]
```

Values saved before a field was switched to Versioned Content (for example, a plain textarea) are read as the current content. Metadata is added the next time the field is saved.

### REST API

When the field group has **Show in REST API** enabled, the field returns the same value as `get_field()`: a string for `current` or the object above for `all`. Writing a string to the field records a new version.

## How it works

Content is sanitized with `wp_kses_post()` and change notes with `sanitize_text_field()`. Line endings are normalized, so saving the same text from a different browser doesn't create a version.

History is built on the server. The editor form submits only the new content, the change note and a signed copy of the history it was rendered with. The signature uses `wp_hash()` and is tied to the field key, so edited form data can't change past versions. The signed copy keeps history attached to the right row when repeater rows are reordered. If the signature is missing or invalid, the stored value is used instead.

Restore copies an old version into the textarea and fills in a change note. Nothing changes until the post is saved, and the restore is recorded as a new version.

## Limitations

- History is stored with the field's meta value, so it is kept in the same place as other ACF data (post, term, user or options meta). Long content with many versions makes that meta value large. Use **Max Versions** to cap it.
- ACF Blocks store field data in block attributes, and versioning hasn't been tested there.
- If two people edit the same post at once, the last save wins, as with other ACF fields.

## Development

```bash
composer install
composer test      # Pest unit tests
composer phpstan   # Static analysis
composer phpcs     # Coding standards (PSR-12)
composer check     # All of the above
```

Tests use [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) to stub WordPress and ACF functions, so they run without a WordPress install. CI runs them on PHP 8.2 to 8.5 and checks syntax on PHP 8.1. The test tools no longer install on PHP 8.1.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
