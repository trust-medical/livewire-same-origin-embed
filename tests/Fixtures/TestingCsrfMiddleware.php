<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

// Laravel 13 renamed ValidateCsrfToken to PreventRequestForgery; Laravel 12 only has the former.
class_alias(
    class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : ValidateCsrfToken::class,
    __NAMESPACE__.'\\CsrfMiddlewareBase',
);

final class TestingCsrfMiddleware extends CsrfMiddlewareBase
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
