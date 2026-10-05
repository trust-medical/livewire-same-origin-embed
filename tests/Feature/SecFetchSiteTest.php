<?php

declare(strict_types=1);

namespace Tests\Feature;

use Generator;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Laravel 13's PreventRequestForgery trusts `Sec-Fetch-Site: same-origin` without a CSRF token and can
 * optionally reject other values. Laravel 12 ignores the header. The bridge must work on both.
 */
final class SecFetchSiteTest extends TestCase
{
    /**
     * @return Generator<string, array{string|null}>
     */
    public static function secFetchSiteValues(): Generator
    {
        yield 'same-origin' => ['same-origin'];
        yield 'same-site' => ['same-site'];
        yield 'cross-site' => ['cross-site'];
        yield 'none' => ['none'];
        yield 'header absent (older browsers)' => [null];
    }

    #[DataProvider('secFetchSiteValues')]
    public function test_render_succeeds_with_valid_csrf_token_for_every_sec_fetch_site_value(?string $secFetchSite): void
    {
        $this->render($this->headers($secFetchSite, 'valid-token'))
            ->assertOk()
            ->assertJsonPath('components.0.component', 'reservation');
    }

    public function test_same_site_and_cross_site_still_need_a_csrf_token(): void
    {
        foreach (['same-site', 'cross-site', 'none', null] as $secFetchSite) {
            $this->render($this->headers($secFetchSite, null))->assertStatus(419);
        }
    }

    public function test_same_origin_without_token_depends_on_laravel_version(): void
    {
        $response = $this->render($this->headers('same-origin', null));

        if (class_exists(PreventRequestForgery::class)) {
            // Laravel 13: a same-origin fetch is trusted by the framework; the bridge still enforces Origin.
            $response->assertOk();
        } else {
            $response->assertStatus(419);
        }
    }

    #[DataProvider('secFetchSiteValues')]
    public function test_invalid_token_is_rejected_regardless_of_sec_fetch_site_except_same_origin_on_laravel_13(?string $secFetchSite): void
    {
        $response = $this->render($this->headers($secFetchSite, 'invalid-token'));

        if ($secFetchSite === 'same-origin' && class_exists(PreventRequestForgery::class)) {
            $response->assertOk();
        } else {
            $response->assertStatus(419);
        }
    }

    public function test_origin_header_is_still_enforced_when_sec_fetch_site_is_same_origin(): void
    {
        $this->render($this->headers('same-origin', 'valid-token', 'https://evil.test'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'origin_mismatch');

        $this->render(['Sec-Fetch-Site' => 'same-origin', 'X-CSRF-TOKEN' => 'valid-token'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'origin_header_missing');
    }

    public function test_same_origin_still_works_when_the_app_enables_origin_only_mode(): void
    {
        if (! class_exists(PreventRequestForgery::class)) {
            $this->markTestSkipped('PreventRequestForgery::useOriginOnly() exists on Laravel 13+ only.');
        }

        PreventRequestForgery::useOriginOnly();

        $this->render($this->headers('same-origin', 'valid-token'))->assertOk();
    }

    public function test_origin_only_mode_rejects_non_same_origin_requests(): void
    {
        if (! class_exists(PreventRequestForgery::class)) {
            $this->markTestSkipped('PreventRequestForgery::useOriginOnly() exists on Laravel 13+ only.');
        }

        PreventRequestForgery::useOriginOnly();

        foreach (['same-site', 'cross-site'] as $secFetchSite) {
            $this->render($this->headers($secFetchSite, 'valid-token'))->assertForbidden();
        }
    }

    public function test_same_site_can_be_allowed_by_the_app_without_changing_bridge_behaviour(): void
    {
        if (! class_exists(PreventRequestForgery::class)) {
            $this->markTestSkipped('PreventRequestForgery::allowSameSite() exists on Laravel 13+ only.');
        }

        PreventRequestForgery::allowSameSite();

        $this->render($this->headers('same-site', null))->assertOk();
        // EnsureSameOrigin remains the bridge's own same-origin gate.
        $this->render($this->headers('same-site', null, 'https://evil.test'))->assertForbidden();
    }

    public function test_session_route_is_not_affected_by_sec_fetch_site(): void
    {
        foreach (['same-origin', 'same-site', 'cross-site', 'none'] as $secFetchSite) {
            $this->getJson('/livewire-bridge/session', ['Sec-Fetch-Site' => $secFetchSite])
                ->assertOk()
                ->assertJsonStructure(['csrf_token']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $secFetchSite, ?string $token, string $origin = 'https://example.test'): array
    {
        return array_filter([
            'Origin' => $origin,
            'Sec-Fetch-Site' => $secFetchSite,
            'X-CSRF-TOKEN' => $token,
        ], static fn (?string $value): bool => $value !== null);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function render(array $headers): TestResponse
    {
        return $this->withSession(['_token' => 'valid-token'])
            ->postJson('/livewire-bridge/render', [
                'components' => [[
                    'component' => 'reservation',
                    'key' => 'reservation-price',
                    'params' => ['placement' => 'price-page'],
                ]],
            ], $headers);
    }
}
