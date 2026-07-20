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
        ]);
    }
}
