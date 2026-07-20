<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Support;

final class JsonDuplicateKeyDetector
{
    public static function containsDuplicateKeys(string $json): bool
    {
        $length = strlen($json);
        $index = 0;

        self::skipWhitespace($json, $index, $length);

        if ($index >= $length) {
            return false;
        }

        return self::scanValue($json, $index, $length);
    }

    private static function scanValue(string $json, int &$index, int $length): bool
    {
        self::skipWhitespace($json, $index, $length);

        if ($index >= $length) {
            return false;
        }

        return match ($json[$index]) {
            '{' => self::scanObject($json, $index, $length),
            '[' => self::scanArray($json, $index, $length),
            '"' => self::scanString($json, $index, $length) === null ? false : false,
            default => self::scanPrimitive($json, $index, $length),
        };
    }

    private static function scanObject(string $json, int &$index, int $length): bool
    {
        $seen = [];
        $index++;
        self::skipWhitespace($json, $index, $length);

        if (($json[$index] ?? null) === '}') {
            $index++;

            return false;
        }

        while ($index < $length) {
            self::skipWhitespace($json, $index, $length);
            $key = self::scanString($json, $index, $length);

            if ($key === null) {
                return false;
            }

            if (isset($seen[$key])) {
                return true;
            }

            $seen[$key] = true;
            self::skipWhitespace($json, $index, $length);

            if (($json[$index] ?? null) !== ':') {
                return false;
            }

            $index++;

            if (self::scanValue($json, $index, $length)) {
                return true;
            }

            self::skipWhitespace($json, $index, $length);

            if (($json[$index] ?? null) === '}') {
                $index++;

                return false;
            }

            if (($json[$index] ?? null) !== ',') {
                return false;
            }

            $index++;
        }

        return false;
    }

    private static function scanArray(string $json, int &$index, int $length): bool
    {
        $index++;
        self::skipWhitespace($json, $index, $length);

        if (($json[$index] ?? null) === ']') {
            $index++;

            return false;
        }

        while ($index < $length) {
            if (self::scanValue($json, $index, $length)) {
                return true;
            }

            self::skipWhitespace($json, $index, $length);

            if (($json[$index] ?? null) === ']') {
                $index++;

                return false;
            }

            if (($json[$index] ?? null) !== ',') {
                return false;
            }

            $index++;
        }

        return false;
    }

    private static function scanString(string $json, int &$index, int $length): ?string
    {
        if (($json[$index] ?? null) !== '"') {
            return null;
        }

        $index++;
        $value = '';

        while ($index < $length) {
            $char = $json[$index];

            if ($char === '"') {
                $index++;

                return $value;
            }

            if ($char === '\\') {
                $index++;

                if ($index >= $length) {
                    return null;
                }

                $escaped = $json[$index];

                if ($escaped === 'u') {
                    $value .= '\u'.substr($json, $index + 1, 4);
                    $index += 5;

                    continue;
                }

                $value .= '\\'.$escaped;
                $index++;

                continue;
            }

            $value .= $char;
            $index++;
        }

        return null;
    }

    private static function scanPrimitive(string $json, int &$index, int $length): bool
    {
        while ($index < $length && ! in_array($json[$index], [',', '}', ']'], true)) {
            $index++;
        }

        return false;
    }

    private static function skipWhitespace(string $json, int &$index, int $length): void
    {
        while ($index < $length && ctype_space($json[$index])) {
            $index++;
        }
    }
}
