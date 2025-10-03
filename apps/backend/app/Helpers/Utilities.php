<?php

namespace App\Helpers;

use Config;
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

        $shortenedString = $firstPart . ' ... ' . $lastPart;

        return $shortenedString;
    }

    static function CheckPatternsString($pattern, $string)
    {
        $pattern = trim($pattern);

        $isNot = Str::contains($pattern, "!");
        if ($isNot) {
            $pattern = Str::replace("!", "", $pattern);
            $pattern = trim($pattern);
        }

        $finalPatterns = [];
        $patternArrays = explode("|", $pattern);

        foreach ($patternArrays as $onePattern) {
            $onePattern = trim($onePattern);

            if (!empty($onePattern)) {
                $finalPatterns[] = $onePattern;
            }
        }



        foreach ($finalPatterns as $onePattern) {
            if (self::CheckPattern($onePattern, $string)) {
                return !$isNot;
            }
        }


        return $isNot;

    }

    static function CheckPattern($pattern, $string)
    {
        // Escape special characters in the pattern
        $pattern = preg_quote($pattern, '/');

        // Replace * with a regex equivalent
        $pattern = str_replace('\*', '.*', $pattern);

        // Add regex delimiters and flags for case insensitivity
        $pattern = '/^' . $pattern . '$/i';

        // Check if the string matches the pattern
        return preg_match($pattern, $string);
    }

    public static function parseLogicStringToArrayN($string) {
        $string = preg_replace('/\s+/', '', $string); // Remove whitespace
        $length = strlen($string);
        $stack = [];
        $output = [];
        $current = &$output;

        $currentKey = '';
        $currentValue = '';

        $readingKey = true;

        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];
            switch ($char) {
                case '(':
                    $current[] = [];
                    array_push($stack, $current);
                    $current = &$current[count($current) - 1];
                    break;
                case ')':
                    if ($currentKey !== '' && $currentValue !== '') {
                        $current[$currentKey] = $currentValue;
                        $currentKey = '';
                        $currentValue = '';
                    }
                    $current = &$stack[count($stack) - 1];
                    array_pop($stack);
                    break;
                case 'and':
                case 'AND':
                    $readingKey = false;
                    $current[] = 'AND';
                    break;
                case 'or':
                case 'OR':
                    $readingKey = true;
                    $current[] = 'OR';
                    break;
                case ':':
                    $readingKey = false;
                    break;
                default:
                    if ($readingKey) {
                        $currentKey .= $char;
                    } else {
                        $currentValue .= $char;
                    }
            }
        }

        return $output;
    }
    public static   function parseLogicStringToArray($alert,$query) {

//        if ($query['token']['type'] == "LITERAL")

        $string = preg_replace('/\s+/', '', $string); // Remove whitespace
        $length = strlen($string);
        $stack = [];
        $output = [];
        $current = &$output;

        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];
            switch ($char) {
                case '(':
                    $current[] = [];
                    array_push($stack, $current);
                    $current = &$current[count($current) - 1];
                    break;
                case ')':
                    $current = &$stack[count($stack) - 1];
                    array_pop($stack);
                    break;
                case 'and':
                case 'AND':
                    $current[] = 'AND';
                    break;
                case 'or':
                case 'OR':
                    $current[] = 'OR';
                    break;
                default:
                    $current[] = $char;
            }
        }

        return $output;
    }

}
