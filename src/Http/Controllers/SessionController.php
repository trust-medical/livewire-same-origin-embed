<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => $request->session()->token(),
            'config' => [
                'sessionExpiredMessage' => config('livewire-bridge.session_expired_message'),
                'confirmOnSessionExpired' => (bool) config('livewire-bridge.confirm_on_session_expired', true),
            ],
        ]);
    }
}
