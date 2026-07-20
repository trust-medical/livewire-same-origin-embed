<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Validation;

interface ComponentParamsValidator
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function validate(string $componentAlias, array $params): array;
}
