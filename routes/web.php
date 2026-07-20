<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TrustMedical\SameOriginLivewireBridge\Http\Controllers\RenderController;
use TrustMedical\SameOriginLivewireBridge\Http\Controllers\SessionController;

Route::get('session', SessionController::class)
    ->name('session')
    ->middleware(config('livewire-bridge.session_middleware', ['throttle:livewire-bridge-session']));

Route::post('render', RenderController::class)
    ->name('render')
    ->middleware(config('livewire-bridge.render_middleware', []));
