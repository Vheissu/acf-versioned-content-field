# Advanced Custom Fields: Versioned Content Field

## Description

Adds a Versioned Content field type to Advanced Custom Fields (ACF). Editors work in a single textarea while previous versions are retained only when the content changes. Each version can include a change note, timestamp, and author metadata, and you can restore older versions in the editor.

## Features

- Automatic version history whenever content changes
- Optional change notes per version
- Restore previous versions in the admin UI
- Configurable max version count and return format
- Pest unit tests with WordPress function mocking

## Installation

1. Upload this plugin to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress Plugins screen
3. Create a new ACF field and choose **Versioned Content**

## Usage

### Template example

```php
// Return only the current content (default)
$current = get_field('my_versioned_content');

// Return current + history
$history = get_field('my_versioned_content'); // with return format = "all"

// Or bypass formatting to inspect raw stored data
$raw = get_field('my_versioned_content', false, false);
```

### Stored value structure (return format: all)

```php
[
    'current' => [
        'id' => 'uuid',
        'content' => 'Latest content',
        'note' => 'What changed',
        'author_id' => 123,
        'author_name' => 'Editor Name',
        'created_at' => '2026-01-08 12:00:00',
    ],
    'versions' => [
        [
            'id' => 'uuid',
            'content' => 'Previous content',
            'note' => 'Earlier note',
            'author_id' => 123,
            'author_name' => 'Editor Name',
            'created_at' => '2026-01-07 10:00:00',
        ],
    ],
]
```

## Field settings

- **Max Versions**: limit retained history (0 = unlimited)
- **Return Format**: current only or full history
- **Rows**: textarea rows
- **Enable Change Notes**: toggle change note input
- **Show Version History**: toggle history list in editor

## Running tests

```bash
composer install
composer test
```
