<?php

declare(strict_types=1);

use AcfVersionedContentField\Fields\VersionedContentField;

const FIELD = ['key' => 'field_abc123', 'name' => 'body'];
const POST_ID = 1;

/**
 * Save a value the way ACF does: slashed in, slashed out.
 *
 * @param array<string, mixed> $settings
 * @return mixed The value as it would be stored, after update_metadata() unslashes it.
 */
function save(mixed $value, array $settings = []): mixed
{
    $field = new VersionedContentField(__FILE__);

    return wp_unslash($field->update_value(wp_slash($value), POST_ID, array_merge(FIELD, $settings)));
}

/**
 * Render the field and return the HTML.
 *
 * @param array<string, mixed> $settings
 */
function render(mixed $value, array $settings = []): string
{
    $field = new VersionedContentField(__FILE__);

    ob_start();
    $field->render_field(array_merge(FIELD, ['id' => 'acf-field_abc123', 'name' => 'acf[field_abc123]', 'value' => $value], $settings));

    return (string) ob_get_clean();
}

/**
 * Pull the hidden state and signature inputs out of rendered HTML, as the browser would submit them.
 *
 * @return array{state: string, signature: string}
 */
function form_state(string $html): array
{
    preg_match('/\[state\]" value="([^"]*)"/', $html, $state);
    preg_match('/\[signature\]" value="([^"]*)"/', $html, $signature);

    return [
        'state' => html_entity_decode($state[1] ?? '', ENT_QUOTES),
        'signature' => html_entity_decode($signature[1] ?? '', ENT_QUOTES),
    ];
}

/**
 * @return array<string, mixed>
 */
function version(string $content, string $id, array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'content' => $content,
        'note' => '',
        'author_id' => 7,
        'author_name' => 'Editor',
        'created_at' => '2026-01-07 10:00:00',
    ], $extra);
}

/**
 * @param list<array<string, mixed>> $versions
 * @return array<string, mixed>
 */
function history(array $current, array $versions = []): array
{
    return ['current' => $current, 'versions' => $versions];
}

describe('field type', function (): void {
    it('sets name, label, category and defaults', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->name)->toBe('versioned_content')
            ->and($field->label)->toBe('Versioned Content')
            ->and($field->category)->toBe('content')
            ->and($field->defaults)->toMatchArray([
                'max_versions' => 10,
                'return_format' => 'current',
                'rows' => 8,
            ]);
    });
});

describe('update_value', function (): void {
    it('returns an empty string when there is no content or history', function (): void {
        expect(save(['content' => '', 'note' => '']))->toBe('')
            ->and(save(''))->toBe('')
            ->and(save(null))->toBe('');
    });

    it('creates the current version with metadata on first save', function (): void {
        $result = save(['content' => 'Hello world', 'note' => 'First draft']);

        expect($result['current'])->toBe([
            'id' => 'uuid-1',
            'content' => 'Hello world',
            'note' => 'First draft',
            'author_id' => 42,
            'author_name' => 'Test User',
            'created_at' => '2026-01-08 12:00:00',
        ])->and($result['versions'])->toBe([]);
    });

    it('moves the previous content into history when content changes', function (): void {
        $this->meta['1:body'] = history(version('Old content', 'old-id', ['note' => 'Old note']));

        $result = save(['content' => 'New content', 'note' => 'New change']);

        expect($result['current']['content'])->toBe('New content')
            ->and($result['current']['note'])->toBe('New change')
            ->and($result['current']['id'])->toBe('uuid-1')
            ->and($result['versions'])->toBe([version('Old content', 'old-id', ['note' => 'Old note'])]);
    });

    it('keeps history newest first', function (): void {
        $this->meta['1:body'] = history(version('Second', 'v2'), [version('First', 'v1')]);

        $result = save(['content' => 'Third']);

        expect(array_column($result['versions'], 'content'))->toBe(['Second', 'First']);
    });

    it('does not add a version when the content is unchanged', function (): void {
        $stored = history(version('Same', 'same-id'), [version('Older', 'v1')]);
        $this->meta['1:body'] = $stored;

        expect(save(['content' => 'Same', 'note' => 'Ignored']))->toBe($stored);
    });

    it('treats different line endings as the same content', function (): void {
        $this->meta['1:body'] = history(version("Line one\nLine two", 'v1'));

        $result = save(['content' => "Line one\r\nLine two"]);

        expect($result['current']['id'])->toBe('v1')
            ->and($result['versions'])->toBe([]);
    });

    it('records a version when update_field() is given a string', function (): void {
        $this->meta['1:body'] = history(version('Old content', 'old-id'));

        $result = save('New content');

        expect($result['current']['content'])->toBe('New content')
            ->and($result['current']['note'])->toBe('')
            ->and($result['versions'][0]['id'])->toBe('old-id');
    });

    it('adds metadata to a plain string saved before the field type was used', function (): void {
        $this->meta['1:body'] = 'Legacy content';

        $result = save(['content' => 'Legacy content']);

        expect($result['current']['content'])->toBe('Legacy content')
            ->and($result['current']['id'])->toBe('uuid-1')
            ->and($result['versions'])->toBe([]);
    });

    it('keeps the previous content as a version when the content is cleared', function (): void {
        $this->meta['1:body'] = history(version('Something', 'v1'));

        $result = save(['content' => '']);

        expect($result['current']['content'])->toBe('')
            ->and($result['versions'])->toBe([version('Something', 'v1')]);
    });

    it('trims history to max_versions', function (): void {
        $this->meta['1:body'] = history(version('Current', 'c'), [version('One', 'v1'), version('Two', 'v2')]);

        $result = save(['content' => 'New'], ['max_versions' => 2]);

        expect(array_column($result['versions'], 'content'))->toBe(['Current', 'One']);
    });

    it('keeps every version when max_versions is 0', function (): void {
        $versions = array_map(fn(int $i): array => version("Version {$i}", "v{$i}"), range(1, 15));
        $this->meta['1:body'] = history(version('Current', 'c'), $versions);

        $result = save(['content' => 'New'], ['max_versions' => 0]);

        expect($result['versions'])->toHaveCount(16);
    });

    it('sanitizes content and notes', function (): void {
        $result = save(['content' => 'Hi<script>alert(1)</script>', 'note' => '<b>Bold</b> note']);

        expect($result['current']['content'])->toBe('Hi')
            ->and($result['current']['note'])->toBe('Bold note');
    });

    it('preserves backslashes through the slashed save path', function (): void {
        $result = save(['content' => 'C:\\Users\\editor', 'note' => 'Path\\note']);

        expect($result['current']['content'])->toBe('C:\\Users\\editor')
            ->and($result['current']['note'])->toBe('Path\\note');
    });

    it('ignores history supplied in the input', function (): void {
        $this->meta['1:body'] = history(version('Stored', 'stored-id'));

        $result = save(history(
            version('Submitted', 'forged-id', ['author_id' => 1, 'author_name' => 'Admin']),
            [version('Forged history', 'forged-old')]
        ));

        expect($result['current']['content'])->toBe('Submitted')
            ->and($result['current']['id'])->toBe('uuid-1')
            ->and($result['current']['author_id'])->toBe(42)
            ->and(array_column($result['versions'], 'id'))->toBe(['stored-id']);
    });

    it('uses the signed form state over stored meta', function (): void {
        // The row was rendered with this history, then moved to another repeater index.
        $state = form_state(render(history(version('Row A', 'a1'), [version('Row A older', 'a0')])));
        $this->meta['1:body'] = history(version('Row B', 'b1'));

        $result = save(['content' => 'Row A edited'] + $state);

        expect(array_column($result['versions'], 'id'))->toBe(['a1', 'a0']);
    });

    it('falls back to stored meta when the form state signature is wrong', function (): void {
        $forged = base64_encode((string) json_encode(history(version('Forged', 'forged'))));
        $this->meta['1:body'] = history(version('Stored', 'stored-id'));

        $result = save(['content' => 'New', 'state' => $forged, 'signature' => 'bad']);

        expect(array_column($result['versions'], 'id'))->toBe(['stored-id']);
    });

    it('rejects form state signed for a different field', function (): void {
        $state = form_state(render(history(version('Other field', 'other'))));
        $this->meta['1:body'] = history(version('Stored', 'stored-id'));

        $result = save(['content' => 'New'] + $state, ['key' => 'field_other']);

        expect(array_column($result['versions'], 'id'))->toBe(['stored-id']);
    });

    it('keeps content with HTML entities intact through the form state', function (): void {
        $current = version('Fish &amp; chips & "quotes"', 'v1');
        $state = form_state(render(history($current)));

        $result = save(['content' => 'Changed'] + $state);

        expect($result['versions'])->toBe([$current]);
    });
});

describe('format_value', function (): void {
    it('returns null for empty values', function (mixed $value): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->format_value($value, POST_ID, FIELD))->toBeNull();
    })->with(['empty string' => [''], 'null' => [null], 'false' => [false]]);

    it('returns the current content by default', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->format_value(history(version('Formatted', 'v1')), POST_ID, FIELD))->toBe('Formatted');
    });

    it('returns the full history when return_format is all', function (): void {
        $field = new VersionedContentField(__FILE__);
        $value = history(version('Current', 'c'), [version('Old', 'o')]);

        expect($field->format_value($value, POST_ID, FIELD + ['return_format' => 'all']))->toBe($value);
    });

    it('fills in missing keys and drops empty versions', function (): void {
        $field = new VersionedContentField(__FILE__);
        $value = ['current' => ['content' => 'Partial'], 'versions' => [['content' => ''], 'junk', ['content' => 'Kept']]];

        $result = $field->format_value($value, POST_ID, FIELD + ['return_format' => 'all']);

        expect($result['current'])->toBe(version('Partial', '', ['author_id' => 0, 'author_name' => '', 'created_at' => '']))
            ->and(array_column($result['versions'], 'content'))->toBe(['Kept']);
    });

    it('handles a legacy plain string value', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->format_value('Legacy', POST_ID, FIELD))->toBe('Legacy');
    });

    it('formats REST values the same way', function (): void {
        $field = new VersionedContentField(__FILE__);
        $value = history(version('Current', 'c'));

        expect($field->format_value_for_rest($value, POST_ID, FIELD))->toBe('Current')
            ->and($field->format_value_for_rest($value, POST_ID, FIELD + ['return_format' => 'all']))->toBe($value);
    });
});

describe('validate_value', function (): void {
    it('requires content when the field is required', function (mixed $value): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->validate_value(true, $value, FIELD + ['required' => 1], 'acf[field_abc123]'))
            ->toBe('Please enter content.');
    })->with(['empty form' => [['content' => '  ']], 'empty string' => [''], 'null' => [null]]);

    it('accepts content when the field is required', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->validate_value(true, ['content' => 'Text'], FIELD + ['required' => 1], 'acf[field_abc123]'))
            ->toBeTrue();
    });

    it('allows empty content when the field is optional', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->validate_value(true, ['content' => ''], FIELD, 'acf[field_abc123]'))->toBeTrue();
    });

    it('keeps an earlier validation error', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->validate_value('Earlier error', ['content' => ''], FIELD + ['required' => 1], 'acf[field_abc123]'))
            ->toBe('Earlier error');
    });
});

describe('get_rest_schema', function (): void {
    it('describes a string for the current return format', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->get_rest_schema(FIELD)['type'])->toBe(['string', 'null']);
    });

    it('describes the history object for the all return format', function (): void {
        $field = new VersionedContentField(__FILE__);
        $schema = $field->get_rest_schema(FIELD + ['return_format' => 'all']);

        expect($schema['type'])->toBe(['object', 'null'])
            ->and(array_keys($schema['properties']))->toBe(['current', 'versions'])
            ->and(array_keys($schema['properties']['current']['properties']))
            ->toBe(['id', 'content', 'note', 'author_id', 'author_name', 'created_at']);
    });
});

describe('render_field', function (): void {
    it('renders the current content escaped in the textarea', function (): void {
        $html = render(history(version('<b>Hi</b> & bye', 'v1')));

        expect($html)->toContain('name="acf[field_abc123][content]"')
            ->and($html)->toContain('&lt;b&gt;Hi&lt;/b&gt; &amp; bye</textarea>')
            ->and($html)->toContain('2026-01-07 10:00 - Editor');
    });

    it('lists previous versions with restore buttons', function (): void {
        $html = render(history(version('Current', 'c'), [
            version('Newer', 'v2', ['note' => 'Fixed typo']),
            version('Older', 'v1'),
        ]));

        expect($html)->toContain('2 previous versions')
            ->and($html)->toContain('Version 2 - 2026-01-07 10:00 - Editor')
            ->and($html)->toContain('Version 1 - 2026-01-07 10:00 - Editor')
            ->and($html)->toContain('Fixed typo')
            ->and(substr_count($html, 'data-action="restore-version"'))->toBe(2)
            ->and($html)->toContain('data-role="version-content">Newer</textarea>');
    });

    it('does not submit previous versions with the form', function (): void {
        $html = render(history(version('Current', 'c'), [version('Older', 'v1')]));

        expect($html)->not->toContain('[versions]')
            ->and($html)->not->toMatch('/<textarea hidden[^>]*name=/');
    });

    it('shows an empty state when there is no history', function (): void {
        $html = render(null);

        expect($html)->toContain('No previous versions yet.')
            ->and($html)->toContain('0 previous versions');
    });

    it('uses the singular label for one version', function (): void {
        expect(render(history(version('Current', 'c'), [version('Older', 'v1')])))->toContain('1 previous version');
    });

    it('hides the note input and history when disabled', function (): void {
        $html = render(history(version('Current', 'c'), [version('Older', 'v1')]), [
            'enable_notes' => 0,
            'show_history' => 0,
        ]);

        expect($html)->not->toContain('data-role="change-note"')
            ->and($html)->not->toContain('acf-versioned-content-history');
    });

    it('shortens long previews', function (): void {
        $html = render(history(version('Current', 'c'), [version(str_repeat('é', 300), 'v1')]));

        expect($html)->toContain('<pre>' . str_repeat('é', 240) . '...</pre>');
    });
});
