<?php

declare(strict_types=1);

use AcfVersionedContentField\Fields\VersionedContentField;
use AcfVersionedContentField\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (!class_exists('acf_field')) {
        eval('class acf_field {
            public $name;
            public $label;
            public $category;
            public $defaults = [];
            public function __construct() {}
        }');
    }
});

describe('VersionedContentField', function (): void {
    it('sets field name, label, and category', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->name)->toBe('versioned_content')
            ->and($field->label)->toBe('Versioned Content')
            ->and($field->category)->toBe('content');
    });

    it('returns empty string when no content or versions', function (): void {
        $field = new VersionedContentField(__FILE__);

        expect($field->update_value([], 1, []))->toBe('');
    });

    it('creates current version metadata on first save', function (): void {
        $field = new VersionedContentField(__FILE__);

        $value = [
            'current' => ['content' => 'Hello world'],
            'current_original' => 'Hello world',
        ];

        $result = $field->update_value($value, 1, []);

        expect($result)->toBeArray()
            ->and($result['current']['content'])->toBe('Hello world')
            ->and($result['current']['id'])->toBe('uuid-1234')
            ->and($result['current']['author_id'])->toBe(42)
            ->and($result['current']['author_name'])->toBe('Test User')
            ->and($result['current']['created_at'])->toBe('2026-01-08 12:00:00');
    });

    it('adds a previous version when content changes', function (): void {
        $field = new VersionedContentField(__FILE__);

        $value = [
            'current' => [
                'id' => 'old-id',
                'content' => 'New content',
                'note' => 'Old note',
                'author_id' => 7,
                'author_name' => 'Editor',
                'created_at' => '2026-01-07 10:00:00',
            ],
            'current_original' => 'Old content',
            'note' => 'New change',
        ];

        $result = $field->update_value($value, 1, []);

        expect($result['current']['content'])->toBe('New content')
            ->and($result['versions'])->toHaveCount(1)
            ->and($result['versions'][0]['content'])->toBe('Old content')
            ->and($result['versions'][0]['id'])->toBe('old-id')
            ->and($result['current']['note'])->toBe('New change');
    });

    it('trims versions based on max_versions', function (): void {
        $field = new VersionedContentField(__FILE__);

        $value = [
            'current' => ['content' => 'Same'],
            'current_original' => 'Same',
            'versions' => [
                ['content' => 'Version 1'],
                ['content' => 'Version 2'],
            ],
        ];

        $result = $field->update_value($value, 1, ['max_versions' => 1]);

        expect($result['versions'])->toHaveCount(1)
            ->and($result['versions'][0]['content'])->toBe('Version 1');
    });

    it('formats value based on return format', function (): void {
        $field = new VersionedContentField(__FILE__);

        $value = [
            'current' => ['content' => 'Formatted'],
            'versions' => [],
        ];

        expect($field->format_value($value, 1, ['return_format' => 'current']))
            ->toBe('Formatted');

        $formatted = $field->format_value($value, 1, ['return_format' => 'all']);
        expect($formatted)->toBeArray()
            ->and($formatted['current']['content'])->toBe('Formatted');
    });
});
