<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NoCsrfBypassTest extends TestCase
{
    public function test_csrf_bypass_trait_is_not_present(): void
    {
        $this->assertFalse(class_exists('TrustMedical\\SameOriginLivewireBridge\\Http\\Middlewares\\IgnoreForWireExtender'));
        $this->assertFalse(trait_exists('TrustMedical\\SameOriginLivewireBridge\\Http\\Middlewares\\IgnoreForWireExtender'));
    }
}
