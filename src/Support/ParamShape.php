<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Support;

final class ParamShape
{
    /**
     * @param  array<mixed>  $value
     */
    public static function isAssociativeArray(array $value): bool
    {
        return $value === [] || array_is_list($value) === false;
    }

    public static function withinDepth(mixed $value, int $maxDepth, int $depth = 0): bool
    {
        if (! is_array($value)) {
            return true;
        }

        if ($depth >= $maxDepth) {
            return $value === [];
        }

        foreach ($value as $child) {
            if (! self::withinDepth($child, $maxDepth, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    public static function withinStringLength(mixed $value, int $maxLength): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) <= $maxLength;
        }

        if (! is_array($value)) {
            return true;
        }

        foreach ($value as $child) {
            if (! self::withinStringLength($child, $maxLength)) {
                return false;
            }
        }

        return true;
    }
}
