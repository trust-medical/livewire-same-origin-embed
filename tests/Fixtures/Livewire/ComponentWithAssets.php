<?php

declare(strict_types=1);

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

final class ComponentWithAssets extends Component
{
    public function render(): mixed
    {
        return view('livewire.component-with-assets');
    }
}
