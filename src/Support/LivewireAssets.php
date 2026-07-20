<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Support;

use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;

final class LivewireAssets
{
    /**
     * Livewire v4 exposes @assets output through this feature class during render/update payload creation.
     * The dependency is isolated here so Livewire internals can be checked with a focused compatibility test.
     *
     * @return list<string>
     */
    public function renderedAssets(): array
    {
        return array_values(array_filter(
            SupportScriptsAndAssets::getAssets(),
            static fn (mixed $asset): bool => is_string($asset) && $asset !== ''
        ));
    }
}
