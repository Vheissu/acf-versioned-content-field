<?php

declare(strict_types=1);

namespace AcfVersionedContentField\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case with WordPress and ACF functions stubbed by Brain Monkey.
 */
class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Fake metadata store read by acf_get_metadata_by_field(), keyed by "{post_id}:{field_name}".
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    private int $uuid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->meta = [];
        $this->uuid = 0;

        Monkey\Functions\stubTranslationFunctions();
        Monkey\Functions\stubEscapeFunctions();
        $this->stubWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function stubWordPressFunctions(): void
    {
        $slash = static function ($value) use (&$slash) {
            return is_array($value) ? array_map($slash, $value) : (is_string($value) ? addslashes($value) : $value);
        };
        $unslash = static function ($value) use (&$unslash) {
            return is_array($value) ? array_map($unslash, $value) : (is_string($value) ? stripslashes($value) : $value);
        };

        Monkey\Functions\stubs([
            'wp_parse_args' => static fn(array $args, array $defaults): array => array_merge($defaults, $args),
            'wp_slash' => $slash,
            'wp_unslash' => $unslash,
            'wp_json_encode' => static fn($data) => json_encode($data),
            'wp_hash' => static fn(string $data): string => hash_hmac('md5', $data, 'test-salt'),
            'wp_kses_post' => static fn(string $text): string => (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $text),
            'sanitize_text_field' => static fn(string $text): string => trim(strip_tags($text)),
            'current_time' => static fn(string $type): string => '2026-01-08 12:00:00',
            'get_current_user_id' => static fn(): int => 42,
            'get_userdata' => static fn(int $user_id): object => (object) ['display_name' => 'Test User'],
            'wp_generate_uuid4' => fn(): string => 'uuid-' . ++$this->uuid,
            'get_option' => static fn(string $name): string => ['date_format' => 'Y-m-d', 'time_format' => 'H:i'][$name] ?? '',
            'mysql2date' => static fn(string $format, string $date): string => date($format, (int) strtotime($date)),
            'acf_get_metadata_by_field' => fn($post_id, array $field) => $this->meta[$post_id . ':' . $field['name']] ?? null,
        ]);
    }
}
