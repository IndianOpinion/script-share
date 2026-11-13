<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.3 and 4.5.5: which script a letter is attributed to.
 */
final class ScriptAttributionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function samples(): iterable
    {
        foreach (Samples::BY_SCRIPT as $code => $text) {
            yield $code => [$code, $text];
        }
    }

    // 16. (4.3.1)
    #[DataProvider('samples')]
    public function testEachScriptIsAttributedToItsCode(string $code, string $text): void
    {
        $r = ScriptShare::analyse($text);
        $script = Script::from($code);
        self::assertGreaterThan(0, $r->letters());
        self::assertSame($r->letters(), $r->count($script), json_encode($r->counts(), JSON_THROW_ON_ERROR));
        self::assertSame(1.0, $r->share($script));
    }

    public function testEveryScriptHasASample(): void
    {
        self::assertCount(24, Script::cases());
        foreach (Script::cases() as $script) {
            self::assertArrayHasKey($script->value, Samples::BY_SCRIPT);
        }
    }

    public function testTibetanTshegIsPunctuation(): void
    {
        $r = ScriptShare::analyse('བོད་ཡིག');
        self::assertSame(1, $r->ignored());
        self::assertSame(6, $r->count(Script::Tibt));
    }

    // 17. (4.3.2)
    public function testExtensionBlocksBelongToTheirScript(): void
    {
        self::assertSame(['Arab' => 2], ScriptShare::analyse("\u{FB50}\u{FEFB}")->counts());
        self::assertSame(['Arab' => 1], ScriptShare::analyse("\u{0750}")->counts());
        self::assertSame(['Latn' => 2], ScriptShare::analyse("\u{0180}\u{01C0}")->counts());
        self::assertSame(['Latn' => 1], ScriptShare::analyse("\u{1E9E}")->counts());
    }

    public function testDevanagariBlockDigitsAndDandaAreNotLetters(): void
    {
        $r = ScriptShare::analyse('क।१');
        self::assertSame(['Deva' => 1], $r->counts());
        self::assertSame(2, $r->ignored());
    }

    // 18. (4.3.3)
    public function testUnlistedScriptsCountAsOther(): void
    {
        $r = ScriptShare::analyse('Հայերեն ქართული');
        self::assertSame(['Zzzz' => 14], $r->counts());
        self::assertSame(['Zzzz' => 5, 'Latn' => 3], ScriptShare::analyse('ኢትዮጵያ abc')->counts());
    }

    // 26. (4.5.5)
    public function testStrayVowelSignFromAnotherScriptIsVisible(): void
    {
        $r = ScriptShare::analyse("தமி\u{0D3F}ழ்");
        self::assertSame(1, $r->count(Script::Mlym));
        self::assertSame(5, $r->count(Script::Taml));
    }

    public function testInheritedMarkAfterStrayMarkFollowsTheStrayMark(): void
    {
        $r = ScriptShare::analyse("த\u{0D3F}\u{0301}");
        self::assertSame(2, $r->count(Script::Mlym));
    }

    public function testScriptEnumHelpers(): void
    {
        self::assertSame(Script::Deva, Script::fromCode('deva'));
        self::assertSame(Script::Latn, Script::fromCode('LATN'));
        self::assertSame('Devanagari', Script::Deva->unicodeName());
        self::assertSame('Odia', Script::Orya->label());
        self::assertSame('Oriya', Script::Orya->unicodeName());
        self::assertSame('Other', Script::Zzzz->label());
        self::assertNotContains(Script::Zzzz, Script::named());
        $this->expectException(InvalidArgumentException::class);
        Script::fromCode('Xxxx');
    }
}
