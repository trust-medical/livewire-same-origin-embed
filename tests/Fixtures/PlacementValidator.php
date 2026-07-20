<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use TrustMedical\SameOriginLivewireBridge\Exceptions\BridgeException;
use TrustMedical\SameOriginLivewireBridge\Validation\ComponentParamsValidator;

final class PlacementValidator implements ComponentParamsValidator
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function validate(string $componentAlias, array $params): array
    {
        $placement = $params['placement'] ?? '';

        if (! in_array($placement, ['price-page', 'menu-page'], true)) {
            throw new BridgeException('params_validation_failed', 422);
        }

        $params['placement'] = strtoupper((string) $placement);

        return $params;
    }
}
