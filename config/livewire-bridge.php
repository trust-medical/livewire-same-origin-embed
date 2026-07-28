<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use TrustMedical\SameOriginLivewireBridge\Http\Middleware\EnsureSameOrigin;

return [
    'enabled' => true,

    'route_prefix' => 'livewire-bridge',
    'route_name_prefix' => 'livewire-bridge.',

    'middleware' => [
        'web',
    ],

    'session_middleware' => [
        'throttle:livewire-bridge-session',
    ],

    'render_middleware' => [
        ValidateCsrfToken::class,
        'throttle:livewire-bridge-render',
        EnsureSameOrigin::class,
    ],

    'require_origin_header' => true,
    'allowed_origin' => env('APP_URL'),

    'selector' => 'livewire-bridge, [data-livewire-bridge]',

    'components' => [
        // 'reservation' => [
        //     'component' => App\Livewire\Embedded\ReservationForm::class,
        //     'allowed_params' => ['placement', 'campaign'],
        //     'validator' => App\LivewireBridge\ReservationParamsValidator::class,
        // ],
    ],

    'max_components_per_request' => 10,
    'max_body_bytes' => 65536,
    'max_params_depth' => 3,
    'max_param_string_length' => 1000,
    'component_key_pattern' => '/\A[a-zA-Z0-9][a-zA-Z0-9:_-]{0,127}\z/',
    'component_alias_pattern' => '/\A[a-z0-9][a-z0-9_-]{0,63}\z/',

    'allow_external_assets' => false,
    'fail_if_livewire_already_loaded' => true,
    'legacy_wire_extender_markup' => false,

    'session_timeout_ms' => 10000,
    'render_timeout_ms' => 15000,
    'retry_count' => 1,
    'retry_delay_ms' => 250,

    'asset_path' => 'vendor/livewire-bridge/livewire-bridge.css',
    'client_path' => 'vendor/livewire-bridge/livewire-bridge.js',

    'generic_error_message' => 'The embedded form could not be loaded.',

    'session_expired_message' => "This page has expired.\nWould you like to refresh the page?",
    'confirm_on_session_expired' => true,

    'rate_limits' => [
        'session_per_minute' => 60,
        'render_per_minute' => 30,
    ],
];
