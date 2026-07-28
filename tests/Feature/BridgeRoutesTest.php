<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class BridgeRoutesTest extends TestCase
{
    public function test_session_route_returns_csrf_token_and_cookie(): void
    {
        $response = $this->getJson('/livewire-bridge/session');

        $response->assertOk()
            ->assertJsonStructure(['csrf_token', 'config' => ['sessionExpiredMessage', 'confirmOnSessionExpired']])
            ->assertJsonPath('config.sessionExpiredMessage', config('livewire-bridge.session_expired_message'))
            ->assertJsonPath('config.confirmOnSessionExpired', true);
        $response->assertCookie(config('session.cookie'));
    }

    public function test_session_route_reflects_custom_session_expired_config(): void
    {
        config()->set('livewire-bridge.session_expired_message', 'セッションの有効期限が切れました。再読み込みしますか?');
        config()->set('livewire-bridge.confirm_on_session_expired', false);

        $this->getJson('/livewire-bridge/session')
            ->assertOk()
            ->assertJsonPath('config.sessionExpiredMessage', 'セッションの有効期限が切れました。再読み込みしますか?')
            ->assertJsonPath('config.confirmOnSessionExpired', false);
    }

    public function test_render_route_requires_csrf_token(): void
    {
        $this->postJson('/livewire-bridge/render', [
            'components' => [$this->bridgeComponent()],
        ], ['Origin' => 'https://example.test'])
            ->assertStatus(419);
    }

    public function test_render_route_rejects_invalid_csrf_token(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'invalid-token',
            ])
            ->assertStatus(419);
    }

    public function test_render_route_succeeds_with_valid_csrf_and_origin(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertOk()
            ->assertJsonPath('components.0.key', 'reservation-price')
            ->assertJsonPath('components.0.component', 'reservation')
            ->assertJsonStructure(['components', 'assets', 'livewireScript', 'config'])
            ->assertSee('data-testid=\"reservation-form\"', false)
            ->assertSee('/livewire-', false);
    }

    public function test_origin_header_is_required_by_default(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], ['X-CSRF-TOKEN' => 'valid-token'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'origin_header_missing');
    }

    public function test_origin_header_requirement_can_be_disabled(): void
    {
        config()->set('livewire-bridge.require_origin_header', false);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], ['X-CSRF-TOKEN' => 'valid-token'])
            ->assertOk();
    }

    public function test_different_origin_is_rejected(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://evil.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'origin_mismatch');
    }

    public function test_origin_validation_fails_closed_when_no_allowed_origin_is_configured(): void
    {
        config()->set('livewire-bridge.allowed_origin', null);
        config()->set('app.url', '');

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'origin_not_configured');
    }

    public function test_origin_comparison_ignores_app_url_path_and_includes_default_port(): void
    {
        config()->set('livewire-bridge.allowed_origin', 'https://example.test/path');

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertOk();
    }

    public function test_unregistered_component_is_rejected(): void
    {
        $component = $this->bridgeComponent(['component' => 'missing']);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$component],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'component_not_registered');
    }

    public function test_arbitrary_class_name_is_rejected(): void
    {
        $component = $this->bridgeComponent(['component' => 'Tests\\Fixtures\\Livewire\\ReservationForm']);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$component],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request_validation_failed');
    }

    public function test_invalid_params_are_rejected(): void
    {
        $component = $this->bridgeComponent(['params' => ['placement' => ['too' => ['deep' => ['value']]]]]);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$component],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable();
    }

    public function test_disallowed_params_are_rejected(): void
    {
        $component = $this->bridgeComponent(['params' => ['placement' => 'price-page', 'admin' => true]]);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$component],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'params_validation_failed');
    }

    public function test_component_count_limit_is_enforced(): void
    {
        config()->set('livewire-bridge.max_components_per_request', 1);

        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [
                    $this->bridgeComponent(['key' => 'one']),
                    $this->bridgeComponent(['key' => 'two']),
                ],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable();
    }

    public function test_duplicate_component_keys_are_rejected(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [
                    $this->bridgeComponent(),
                    $this->bridgeComponent(),
                ],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertUnprocessable();
    }

    public function test_multiple_components_can_be_rendered_together(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [
                    $this->bridgeComponent(),
                    [
                        'component' => 'questionnaire',
                        'key' => 'questionnaire-price',
                        'params' => ['placement' => 'price-page'],
                    ],
                ],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'components')
            ->assertSee('Questionnaire PRICE-PAGE', false);
    }

    public function test_response_includes_component_assets(): void
    {
        $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [[
                    'component' => 'with-assets',
                    'key' => 'with-assets',
                    'params' => [],
                ]],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'assets' => ["<script src=\"/assets/example.js\" defer></script>\n<link rel=\"stylesheet\" href=\"/assets/example.css\">\n    "],
            ]);
    }

    public function test_cors_headers_are_not_added(): void
    {
        $response = $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [$this->bridgeComponent()],
            ], [
                'Origin' => 'https://example.test',
                'X-CSRF-TOKEN' => 'valid-token',
            ]);

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{component: string, key: string, params: array<string, mixed>}
     */
    private function bridgeComponent(array $overrides = []): array
    {
        return array_merge([
            'component' => 'reservation',
            'key' => 'reservation-price',
            'params' => ['placement' => 'price-page'],
        ], $overrides);
    }
}
