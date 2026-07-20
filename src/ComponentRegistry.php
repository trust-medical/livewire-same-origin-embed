<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge;

use Illuminate\Contracts\Container\Container;
use TrustMedical\SameOriginLivewireBridge\Exceptions\BridgeException;
use TrustMedical\SameOriginLivewireBridge\Validation\ComponentParamsValidator;

final class ComponentRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return array{component: class-string|string, allowed_params?: list<string>, validator?: class-string<ComponentParamsValidator>}|null
     */
    public function registration(string $alias): ?array
    {
        $registration = config("livewire-bridge.components.$alias");

        if (! is_array($registration) || ! isset($registration['component']) || ! is_string($registration['component'])) {
            return null;
        }

        /** @var array{component: class-string|string, allowed_params?: list<string>, validator?: class-string<ComponentParamsValidator>} $registration */
        return $registration;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function validatedParams(string $alias, array $params): array
    {
        $registration = $this->registration($alias);

        if ($registration === null) {
            throw new BridgeException('component_not_registered', 404);
        }

        $allowedParams = $registration['allowed_params'] ?? [];
        $extraKeys = array_values(array_diff(array_keys($params), $allowedParams));

        if ($extraKeys !== []) {
            throw new BridgeException('params_validation_failed', 422);
        }

        if (! isset($registration['validator'])) {
            return $params;
        }

        $validator = $this->container->make($registration['validator']);

        if (! $validator instanceof ComponentParamsValidator) {
            throw new BridgeException('params_validator_invalid', 500);
        }

        return $validator->validate($alias, $params);
    }
}
