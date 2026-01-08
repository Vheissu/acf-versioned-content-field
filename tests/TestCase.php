<?php

declare(strict_types=1);

namespace AcfVersionedContentField\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case with WordPress mocking support.
 */
class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function mockWordPressFunctions(): void
    {
        Monkey\Functions\stubs([
            '__' => static fn(string $text, string $domain = 'default'): string => $text,
            'esc_html__' => static fn(string $text, string $domain = 'default'): string => $text,
            'esc_html' => static fn(string $text): string => $text,
            'esc_attr' => static fn(string $text): string => $text,
            'esc_textarea' => static fn(string $text): string => $text,
            'wp_parse_args' => static fn(array $args, array $defaults): array => array_merge($defaults, $args),
            'wp_unslash' => static fn(string $value): string => $value,
            'sanitize_text_field' => static fn(string $text): string => trim(strip_tags($text)),
            'wp_kses_post' => static fn(string $text): string => $text,
            'current_time' => static fn(string $type): string => '2026-01-08 12:00:00',
            'get_current_user_id' => static fn(): int => 42,
            'get_userdata' => static function (int $user_id): ?object {
                return (object) ['display_name' => 'Test User'];
            },
            'wp_generate_uuid4' => static fn(): string => 'uuid-1234',
        ]);
    }
}
