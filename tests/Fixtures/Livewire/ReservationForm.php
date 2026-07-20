<?php

declare(strict_types=1);

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

final class ReservationForm extends Component
{
    public string $placement = '';

    public string $name = '';

    public bool $submitted = false;

    /**
     * @param  array<string, mixed>|null  $campaign
     */
    public function mount(string $placement = '', mixed $campaign = null): void
    {
        $this->placement = $placement;
    }

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'min:2'],
        ]);

        $this->submitted = true;
        $this->dispatch('reservation-form:submitted', placement: $this->placement);
    }

    public function render(): mixed
    {
        return view('livewire.reservation-form');
    }
}
