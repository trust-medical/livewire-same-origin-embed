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

    public function test_it_allows_nesting_up_to_the_maximum_depth(): void
    {
        $depth = JsonDuplicateKeyDetector::MAX_DEPTH;

        $this->assertFalse(JsonDuplicateKeyDetector::containsDuplicateKeys(
            str_repeat('[', $depth).'1'.str_repeat(']', $depth)
        ));
    }

    public function test_it_rejects_nesting_beyond_the_maximum_depth(): void
    {
        $depth = JsonDuplicateKeyDetector::MAX_DEPTH + 1;

        $this->assertTrue(JsonDuplicateKeyDetector::containsDuplicateKeys(
            str_repeat('[', $depth).'1'.str_repeat(']', $depth)
        ));
    }

    public function test_it_rejects_deeply_nested_bodies_without_exhausting_the_stack(): void
    {
        $this->assertTrue(JsonDuplicateKeyDetector::containsDuplicateKeys(
            str_repeat('[', 30000)
        ));
    }
}
