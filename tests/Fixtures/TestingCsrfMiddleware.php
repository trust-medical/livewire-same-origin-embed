<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

final class TestingCsrfMiddleware extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
