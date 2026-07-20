<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RequestValidationTest extends TestCase
{
    public function test_duplicate_json_keys_are_rejected(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->call(
                'POST',
                '/livewire-bridge/render',
                [],
                [],
                [],
                [
                    'HTTP_ORIGIN' => 'https://example.test',
                    'HTTP_X_CSRF_TOKEN' => 'valid-token',
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                '{"components":[{"component":"reservation","key":"one","params":{"placement":"price-page","placement":"menu-page"}}]}'
            )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'duplicate_json_key');
    }

    public function test_request_body_size_is_limited(): void
    {
        config()->set('livewire-bridge.max_body_bytes', 10);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [[
                    'component' => 'reservation',
                    'key' => 'one',
                    'params' => [],
                ]],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'body_too_large');
    }

    public function test_params_must_be_json_object(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [[
                    'component' => 'reservation',
                    'key' => 'one',
                    'params' => ['not-object'],
                ]],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable();
    }
}
