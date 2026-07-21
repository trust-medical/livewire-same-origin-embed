<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use TrustMedical\SameOriginLivewireBridge\Http\Controllers\RenderController;
use TrustMedical\SameOriginLivewireBridge\Http\Controllers\SessionController;
use TrustMedical\SameOriginLivewireBridge\Http\Middleware\EnsureSameOrigin;

Route::get('session', SessionController::class)
    ->name('session')
    ->middleware(config('livewire-bridge.session_middleware', ['throttle:livewire-bridge-session']));

Route::post('render', RenderController::class)
    ->name('render')
    ->middleware(config('livewire-bridge.render_middleware', [
        ValidateCsrfToken::class,
        'throttle:livewire-bridge-render',
        EnsureSameOrigin::class,
    ]));
