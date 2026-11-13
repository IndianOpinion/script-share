<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;

/**
 * Static entry points with default options.
 */
final class ScriptShare
{
    private function __construct()
    {
    }

    public static function analyse(string $text, ?Options $options = null): Result
    {
        return (new Analyser($options))->analyse($text);
    }

    /**
     * @return array<string, float> ISO code => share, highest first
     */
    public static function shares(string $text): array
    {
        return self::analyse($text)->shares();
    }

    public static function dominant(string $text, float $minShare = 0.5, int $minLetters = 1): ?Script
    {
        return self::analyse($text)->dominant($minShare, $minLetters);
    }

    /**
     * @param Script|array<Script|string> $expected
     */
    public static function check(
        string|Result $text,
        Script|array $expected,
        float $minShare,
        int $minLetters = 20,
    ): Check {
        $result = $text instanceof Result ? $text : self::analyse($text);

        return Check::of($result, $expected, $minShare, $minLetters);
    }

    /**
     * True only for a pass; too_short is false.
     *
     * @param Script|array<Script|string> $expected
     */
    public static function isMostly(string $text, Script|array $expected, float $minShare, int $minLetters = 20): bool
    {
        return self::check($text, $expected, $minShare, $minLetters)->passed();
    }

    /**
     * @return list<Script>|null
     */
    public static function scriptsFor(string $languageTag): ?array
    {
        return LanguageMap::scriptsFor($languageTag);
    }

    /**
     * @throws InvalidArgumentException on an unknown language tag.
     */
    public static function checkLanguage(
        string|Result $text,
        string $languageTag,
        float $minShare,
        int $minLetters = 20,
    ): Check {
        $scripts = LanguageMap::scriptsFor($languageTag);
        if ($scripts === null) {
            throw new InvalidArgumentException('Unknown language tag: no expected script.');
        }

        return self::check($text, $scripts, $minShare, $minLetters);
    }
}
