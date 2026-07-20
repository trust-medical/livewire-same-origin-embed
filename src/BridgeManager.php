<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge;

use Illuminate\Support\Facades\Blade;
use Throwable;
use TrustMedical\SameOriginLivewireBridge\Exceptions\BridgeException;
use TrustMedical\SameOriginLivewireBridge\Support\LivewireAssets;
use TrustMedical\SameOriginLivewireBridge\Support\LivewireScriptRenderer;

final class BridgeManager
{
    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly LivewireAssets $assets,
        private readonly LivewireScriptRenderer $scripts,
    ) {}

    /**
     * @param  list<array{component: string, key: string, params: array<string, mixed>}>  $requestedComponents
     * @return array{components: list<array{key: string, component: string, html: string}>, assets: list<string>, livewireScript: string, config: array<string, mixed>}
     */
    public function render(array $requestedComponents): array
    {
        $rendered = [];

        foreach ($requestedComponents as $requestedComponent) {
            $alias = $requestedComponent['component'];
            $registration = $this->components->registration($alias);

            if ($registration === null) {
                throw new BridgeException('component_not_registered', 404);
            }

            $params = $this->components->validatedParams($alias, $requestedComponent['params']);

            try {
                $html = Blade::render('@livewire($component, $params, key($key))', [
                    'component' => $registration['component'],
                    'params' => $params,
                    'key' => $requestedComponent['key'],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                throw new BridgeException('component_render_failed', 500);
            }

            $rendered[] = [
                'key' => $requestedComponent['key'],
                'component' => $alias,
                'html' => $html,
            ];
        }

        return [
            'components' => $rendered,
            'assets' => $this->assets->renderedAssets(),
            'livewireScript' => $this->scripts->render(),
            'config' => [
                'allowExternalAssets' => (bool) config('livewire-bridge.allow_external_assets', false),
                'failIfLivewireAlreadyLoaded' => (bool) config('livewire-bridge.fail_if_livewire_already_loaded', true),
            ],
        ];
    }
}
