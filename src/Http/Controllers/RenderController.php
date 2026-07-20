<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use TrustMedical\SameOriginLivewireBridge\BridgeManager;
use TrustMedical\SameOriginLivewireBridge\Exceptions\BridgeException;
use TrustMedical\SameOriginLivewireBridge\Http\Requests\RenderComponentsRequest;

final class RenderController
{
    public function __invoke(RenderComponentsRequest $request, BridgeManager $bridge): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $payload = $bridge->render($request->components());

            Log::info('Livewire bridge render completed', [
                'component_aliases' => $request->componentAliases(),
                'component_count' => count($request->components()),
                'status' => 200,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json($payload);
        } catch (BridgeException $exception) {
            Log::warning('Livewire bridge render rejected', [
                'component_aliases' => $request->componentAliases(),
                'component_count' => count($request->components()),
                'status' => $exception->statusCode,
                'error_code' => $exception->errorCode,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $this->safeMessage($exception->errorCode),
                ],
            ], $exception->statusCode);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'error' => [
                    'code' => 'http_error',
                    'message' => $this->safeMessage('http_error'),
                ],
            ], $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Livewire bridge render failed', [
                'component_aliases' => $request->componentAliases(),
                'component_count' => count($request->components()),
                'status' => 500,
                'error_code' => 'render_failed',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'render_failed',
                    'message' => $this->safeMessage('render_failed'),
                ],
            ], 500);
        }
    }

    private function safeMessage(string $code): string
    {
        return match ($code) {
            'component_not_registered' => 'The requested component is not available.',
            'params_validation_failed' => 'The component parameters are invalid.',
            default => (string) config('livewire-bridge.generic_error_message', 'The embedded form could not be loaded.'),
        };
    }
}
