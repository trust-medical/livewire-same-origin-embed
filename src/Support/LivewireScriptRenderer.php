<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Support;

use Illuminate\Support\Facades\Blade;

final class LivewireScriptRenderer
{
    public function render(): string
    {
        return Blade::render('@livewireScripts');
    }
}
