<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSameOrigin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || $origin === '') {
            if ((bool) config('livewire-bridge.require_origin_header', true)) {
                return $this->forbidden('origin_header_missing');
            }

            return $next($request);
        }

        $expected = $this->canonicalOrigin(
            (string) (config('livewire-bridge.allowed_origin') ?: config('app.url') ?: $request->getSchemeAndHttpHost())
        );

        if ($expected === null || $this->canonicalOrigin($origin) !== $expected) {
            return $this->forbidden('origin_mismatch');
        }

        return $next($request);
    }

    private function forbidden(string $code): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => 'The request origin is not allowed.',
            ],
        ], 403);
    }

    private function canonicalOrigin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return $scheme.'://'.$host.':'.$port;
    }
}
