<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SameOriginLivewireBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livewire-bridge.php', 'livewire-bridge');
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerRoutes();
        $this->registerPublishes();
    }

    private function registerRoutes(): void
    {
        if (! config('livewire-bridge.enabled', true)) {
            return;
        }

        Route::middleware(config('livewire-bridge.middleware', ['web']))
            ->prefix(trim((string) config('livewire-bridge.route_prefix', 'livewire-bridge'), '/'))
            ->as((string) config('livewire-bridge.route_name_prefix', 'livewire-bridge.'))
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerPublishes(): void
    {
        $config = [__DIR__.'/../config/livewire-bridge.php' => config_path('livewire-bridge.php')];
        $assets = [
            __DIR__.'/../dist' => public_path('vendor/livewire-bridge'),
            __DIR__.'/../public/vendor/livewire-bridge' => public_path('vendor/livewire-bridge'),
        ];

        $this->publishes($config, 'livewire-bridge-config');
        $this->publishes($assets, 'livewire-bridge-assets');
        $this->publishes($config + $assets, 'livewire-bridge');
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('livewire-bridge-session', function (Request $request): Limit {
            return Limit::perMinute((int) config('livewire-bridge.rate_limits.session_per_minute', 60))
                ->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('livewire-bridge-render', function (Request $request): Limit {
            return Limit::perMinute((int) config('livewire-bridge.rate_limits.render_per_minute', 30))
                ->by($request->ip() ?: 'unknown');
        });
    }
}
