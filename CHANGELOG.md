# Changelog

## 1.1.0

### Changed

- History is now rebuilt on the server. The editor form no longer posts every previous version as hidden inputs, so edited form data can't change past versions, authors or dates.
- `update_field()` with a string now records a new version. Before, it cleared the field.
- REST API responses now match the schema (a string, or the history object) instead of returning the raw stored array.
- The field type is registered with `acf_register_field_type()`.
- The missing-ACF notice now checks late enough that ACF bundled in a theme counts, and only shows to users who can manage plugins.
- Removed the `Requires Plugins` header, which blocked activation on sites running ACF Pro.
- Version history labels count up from the oldest kept version, and dates use the site's date and time format.

### Fixed

- Backslashes in content and notes were stripped on save.
- Content with HTML entities such as `&amp;` could change when restored.
- Line ending differences between browsers created new versions for unchanged content.
- Translations now load before ACF registers the field, so field labels are translated.

### Development

- Tests cover saving, history, signatures, formatting, validation, REST schema, rendering and plugin setup.
- Added PHPStan (level 8) and PHPCS (PSR-12) config, and a GitHub Actions workflow.
- Dev dependencies now install on PHP 8.1 to 8.5.

## 1.0.0

- First release.
