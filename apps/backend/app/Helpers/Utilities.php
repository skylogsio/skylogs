<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class Utilities
{
    public static function removeEmptyKeys(array &$array): void
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                self::removeEmptyKeys($value);
            }

            if ($key === '' || $key === null) {
                $newKey = 'empty';
                $array[$newKey] = $value;
                unset($array[$key]);
            }
        }
    }

    public static function ShortText($longString, $number = 100)
    {

        if (Str::length($longString) < $number + 3) {
            return $longString;
        }

        $maxLength = intval($number / 2);

        $firstPart = Str::substr($longString, 0, $maxLength);
        $lastPart = Str::substr($longString, -$maxLength);

        $shortenedString = $firstPart.' ... '.$lastPart;

        return $shortenedString;
    }

    public static function CheckPatternsString($pattern, $string)
    {
        $pattern = trim($pattern);

        if (empty($pattern)) {
            return true;
        }

        return self::evaluateExpression($pattern, $string);

    }

    private static function evaluateExpression($expression, $string)
    {
        $expression = trim($expression);

        while (preg_match('/\(([^()]+)\)/', $expression, $matches)) {
            $result = self::evaluateExpression($matches[1], $string);
            $expression = str_replace($matches[0], $result ? '1' : '0', $expression);
        }

        if (strpos($expression, '|') !== false) {
            $parts = preg_split('/\s*\|\s*/', $expression);
            foreach ($parts as $part) {
                if (self::evaluateExpression($part, $string)) {
                    return true;
                }
            }

            return false;
        }

        if (strpos($expression, '&') !== false) {
            $parts = preg_split('/\s*&\s*/', $expression);
            foreach ($parts as $part) {
                if (! self::evaluateExpression($part, $string)) {
                    return false;
                }
            }

            return true;
        }

        $expression = trim($expression);
        $isNegated = false;

        if (str_starts_with($expression, '!')) {
            $isNegated = true;
            $expression = trim(substr($expression, 1));
        }

        if ($expression === '1') {
            return ! $isNegated;
        }
        if ($expression === '0') {
            return $isNegated;
        }

        $matches = Str::is($expression, $string);

        return $isNegated ? ! $matches : $matches;
    }
}
