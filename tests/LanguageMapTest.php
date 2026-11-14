<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.10: language tag to expected scripts.
 */
final class LanguageMapTest extends TestCase
{
    /**
     * @param list<Script>|null $scripts
     *
     * @return list<string>|null
     */
    private static function codes(?array $scripts): ?array
    {
        return $scripts === null ? null : array_map(static fn (Script $s): string => $s->value, $scripts);
    }

    // 45. (4.10.2)
    public function testScriptSubtagWins(): void
    {
        self::assertSame(['Arab'], self::codes(ScriptShare::scriptsFor('pa-Arab')));
        self::assertSame(['Deva'], self::codes(ScriptShare::scriptsFor('sd-Deva')));
        self::assertSame(['Latn'], self::codes(ScriptShare::scriptsFor('sr-Latn')));
        self::assertSame(['Latn'], self::codes(ScriptShare::scriptsFor('hi-Latn')));
        self::assertSame(['Latn'], self::codes(ScriptShare::scriptsFor('hi-latn-IN')));
        self::assertSame(['Hani'], self::codes(ScriptShare::scriptsFor('zh-Hant-TW')));
        self::assertSame(['Hani'], self::codes(ScriptShare::scriptsFor('zh-yue-Hans')));
        self::assertSame(['Hani', 'Hira', 'Kana'], self::codes(ScriptShare::scriptsFor('ja-Jpan')));
        self::assertNull(ScriptShare::scriptsFor('hy-Armn'), 'unlisted script subtag');
        self::assertNull(ScriptShare::scriptsFor('hi-Zzzz'));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function defaults(): iterable
    {
        foreach (['hi', 'mr', 'ne', 'sa', 'kok', 'mai', 'doi', 'brx'] as $tag) {
            yield $tag => [$tag, ['Deva']];
        }
        foreach (['bn', 'as'] as $tag) {
            yield $tag => [$tag, ['Beng']];
        }
        yield 'pa' => ['pa', ['Guru']];
        yield 'gu' => ['gu', ['Gujr']];
        yield 'or' => ['or', ['Orya']];
        yield 'ta' => ['ta', ['Taml']];
        yield 'te' => ['te', ['Telu']];
        yield 'kn' => ['kn', ['Knda']];
        yield 'tcy' => ['tcy', ['Knda']];
        yield 'ml' => ['ml', ['Mlym']];
        yield 'si' => ['si', ['Sinh']];
        yield 'sat' => ['sat', ['Olck']];
        yield 'mni' => ['mni', ['Mtei', 'Beng']];
        foreach (['ur', 'ks', 'sd', 'ar', 'fa'] as $tag) {
            yield $tag => [$tag, ['Arab']];
        }
        foreach (['en', 'fr', 'de', 'es', 'pt', 'it', 'nl', 'sw', 'vi', 'tr', 'pl'] as $tag) {
            yield $tag => [$tag, ['Latn']];
        }
        foreach (['ru', 'uk'] as $tag) {
            yield $tag => [$tag, ['Cyrl']];
        }
        yield 'el' => ['el', ['Grek']];
        yield 'he' => ['he', ['Hebr']];
        yield 'th' => ['th', ['Thai']];
        yield 'zh' => ['zh', ['Hani']];
        yield 'ja' => ['ja', ['Hani', 'Hira', 'Kana']];
        yield 'ko' => ['ko', ['Hang', 'Hani']];
        yield 'HI_in' => ['HI_in', ['Deva']];
        yield 'hi-IN' => ['hi-IN', ['Deva']];
    }

    /**
     * @param list<string> $expected
     */
    // 46. (4.10.3)
    #[DataProvider('defaults')]
    public function testDefaultScripts(string $tag, array $expected): void
    {
        self::assertSame($expected, self::codes(ScriptShare::scriptsFor($tag)));
    }

    // 47. (4.10.4)
    public function testUnknownLanguageIsNull(): void
    {
        self::assertNull(ScriptShare::scriptsFor('xx'));
        self::assertNull(ScriptShare::scriptsFor(''));
        self::assertNull(ScriptShare::scriptsFor('123'));
        self::assertNull(ScriptShare::scriptsFor('english'));
        $this->expectException(InvalidArgumentException::class);
        ScriptShare::checkLanguage('Hello world', 'xx', 0.9);
    }

    // 52. (4.10.1, 4.10.5)
    public function testCaseSeparatorAndRegionDoNotMatter(): void
    {
        $en = ScriptShare::scriptsFor('en');
        self::assertSame($en, ScriptShare::scriptsFor('en-IN'));
        self::assertSame($en, ScriptShare::scriptsFor('EN'));
        self::assertSame($en, ScriptShare::scriptsFor('en_GB'));
        self::assertSame(ScriptShare::scriptsFor('hi'), ScriptShare::scriptsFor('hi_IN'));
        self::assertSame(ScriptShare::scriptsFor('pa'), ScriptShare::scriptsFor('pa-PK'), 'regions are ignored');
    }
}
