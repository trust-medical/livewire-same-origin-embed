<?php

declare(strict_types=1);

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

final class QuestionnaireForm extends Component
{
    public string $placement = '';

    public function mount(string $placement = ''): void
    {
        $this->placement = $placement;
    }

    public function render(): mixed
    {
        return view('livewire.questionnaire-form');
    }
}
