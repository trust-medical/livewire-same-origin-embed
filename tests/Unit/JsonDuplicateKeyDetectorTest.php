<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TrustMedical\SameOriginLivewireBridge\Support\JsonDuplicateKeyDetector;

final class JsonDuplicateKeyDetectorTest extends TestCase
{
    public function test_it_detects_duplicate_keys_in_nested_objects(): void
    {
        $this->assertTrue(JsonDuplicateKeyDetector::containsDuplicateKeys(
            '{"components":[{"params":{"placement":"a","placement":"b"}}]}'
        ));
    }

    public function test_it_allows_distinct_keys(): void
    {
        $this->assertFalse(JsonDuplicateKeyDetector::containsDuplicateKeys(
            '{"components":[{"params":{"placement":"a","campaign":"b"}}]}'
        ));
    }
}
