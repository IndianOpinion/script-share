<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.1 and 4.2: what a letter is, and the counting unit.
 */
final class LetterRulesTest extends TestCase
{
    private static function ignoring(): Analyser
    {
        return new Analyser(new Options(marks: MarkMode::Ignore));
    }

    // 1. (4.1.1)
    public function testLatinWordIsAllLatin(): void
    {
        $r = ScriptShare::analyse('Hello');
        self::assertSame(5, $r->letters());
        self::assertSame(['Latn' => 5], $r->counts());
        self::assertSame(1.0, $r->share(Script::Latn));
    }

    // 2. (4.1.2, 4.1.3a)
    public function testOwnScriptMarksCountInCountModeOnly(): void
    {
        self::assertSame(5, ScriptShare::analyse('किताब')->count(Script::Deva));
        self::assertSame(3, self::ignoring()->analyse('किताब')->count(Script::Deva));
    }

    // 3. (4.1.3b)
    public function testInheritedMarkJoinsThePrecedingLetter(): void
    {
        $latin = ScriptShare::analyse("e\u{0301}");
        self::assertSame(2, $latin->count(Script::Latn));
        self::assertSame(0, $latin->ignored());

        $deva = ScriptShare::analyse("क\u{0951}");
        self::assertSame(2, $deva->count(Script::Deva));
    }

    // 4. (4.1.3b)
    public function testInheritedMarkWithNoBaseIsIgnored(): void
    {
        $start = ScriptShare::analyse("\u{0301}abc");
        self::assertSame(3, $start->letters());
        self::assertSame(1, $start->ignored());

        $afterSpace = ScriptShare::analyse("abc \u{0301}");
        self::assertSame(3, $afterSpace->letters());
        self::assertSame(2, $afterSpace->ignored());

        $afterDigit = ScriptShare::analyse("abc1\u{0301}");
        self::assertSame(3, $afterDigit->letters());
    }

    // 5. (4.1.3b, 4.1.9)
    public function testFormatCharacterDoesNotBreakTheLink(): void
    {
        $r = ScriptShare::analyse("क\u{200D}\u{0951}");
        self::assertSame(2, $r->count(Script::Deva));
        self::assertSame(1, $r->ignored());

        $zwnj = ScriptShare::analyse("e\u{200C}\u{00AD}\u{FEFF}\u{0301}");
        self::assertSame(2, $zwnj->count(Script::Latn));
        self::assertSame(3, $zwnj->ignored());
    }

    // 6. (4.1.3a)
    public function testOwnScriptMarkCountsForItsScriptWhateverPrecedes(): void
    {
        $r = ScriptShare::analyse("a\u{093F}");
        self::assertSame(1, $r->count(Script::Latn));
        self::assertSame(1, $r->count(Script::Deva));
    }

    // 7. (4.1.4)
    public function testIgnoreModeMatchesLetterCategoryCount(): void
    {
        $tamil = 'தமிழ் ஒரு பழமையான மொழி, இது இந்தியாவிலும் இலங்கையிலும் பேசப்படுகிறது.';
        $expected = preg_match_all('/\p{L}/u', $tamil);
        self::assertSame($expected, self::ignoring()->analyse($tamil)->count(Script::Taml));
        self::assertSame($expected, self::ignoring()->analyse($tamil)->letters());
        self::assertGreaterThan($expected, ScriptShare::analyse($tamil)->letters());
    }

    // 8. (4.1.5)
    public function testCommonScriptLettersAreIgnored(): void
    {
        self::assertSame(0, ScriptShare::analyse('𝐇𝐞𝐥𝐥𝐨')->letters());
        self::assertSame(5, ScriptShare::analyse('𝐇𝐞𝐥𝐥𝐨')->ignored());
        $apostrophe = ScriptShare::analyse("don\u{02BC}t");
        self::assertSame(4, $apostrophe->letters());
        self::assertSame(1, $apostrophe->ignored());
    }

    // 9. (4.1.6)
    public function testFullwidthLatinIsLatin(): void
    {
        self::assertSame(['Latn' => 3], ScriptShare::analyse('ＡＢＣ')->counts());
    }

    // 10. (4.1.7)
    public function testDigitsAreNeverLetters(): void
    {
        $r = ScriptShare::analyse('२०२६ 2026 ௨௦');
        self::assertSame(0, $r->letters());
        self::assertSame(12, $r->ignored());
        self::assertSame(12, $r->codePoints());
    }

    // 11. (4.1.8)
    public function testPunctuationAndSymbolsAreNeverLetters(): void
    {
        $r = ScriptShare::analyse('।॥۔₹!?😀');
        self::assertSame(0, $r->letters());
        self::assertSame(7, $r->ignored());
    }

    // 12. (4.1.10)
    public function testNulIsValidAndIgnored(): void
    {
        $r = ScriptShare::analyse("ab\u{0000}cd\t\r\n");
        self::assertSame(4, $r->letters());
        self::assertSame(4, $r->ignored());
    }

    // 13. (4.1.11)
    public function testLettersPlusIgnoredEqualsCodePoints(): void
    {
        $pool = ['a', 'Z', 'é', "\u{0301}", 'क', 'ि', '्', "\u{0951}", 'த', 'ு', 'ب', 'Ж', 'ω', '中', 'あ', 'ア', '한',
            'Ա', ' ', "\n", '1', '२', '.', '।', '😀', "\u{200D}", "\u{200C}", '𝐇', "\u{0000}", '@', '#', '/', ':'];
        mt_srand(20261112);
        for ($i = 0; $i < 300; ++$i) {
            $s = '';
            $len = mt_rand(0, 60);
            for ($k = 0; $k < $len; ++$k) {
                $s .= $pool[mt_rand(0, count($pool) - 1)];
            }
            foreach ([new Options(skipLinks: true), new Options(marks: MarkMode::Ignore)] as $options) {
                $r = (new Analyser($options))->analyse($s);
                self::assertSame(mb_strlen($s, 'UTF-8'), $r->letters() + $r->ignored(), bin2hex($s));
                self::assertSame(mb_strlen($s, 'UTF-8'), $r->codePoints());
            }
        }
    }

    // 14. (4.2.1)
    public function testCountingUnitIsTheCodePoint(): void
    {
        self::assertSame(4, ScriptShare::analyse('क्षि')->letters());
        self::assertSame(2, self::ignoring()->analyse('क्षि')->letters());
    }

    // 15. (4.2.2)
    public function testNoNormalisation(): void
    {
        $nfc = ScriptShare::analyse("caf\u{00E9}");
        $nfd = ScriptShare::analyse("cafe\u{0301}");
        self::assertSame(4, $nfc->letters());
        self::assertSame(5, $nfd->letters());
        self::assertSame(1.0, $nfc->share(Script::Latn));
        self::assertSame(1.0, $nfd->share(Script::Latn));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function controlOnly(): iterable
    {
        yield 'controls' => ["\u{0001}\u{0002}\u{0007}\u{001B}\u{007F}"];
        yield 'binary-ish' => ["\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0E\x0F\x10"];
    }

    // 50. (4.8.5)
    #[DataProvider('controlOnly')]
    public function testControlCharactersOnlyGiveNoLetters(string $text): void
    {
        $r = ScriptShare::analyse($text);
        self::assertSame(0, $r->letters());
        self::assertSame(strlen($text), $r->ignored());
        self::assertTrue($r->isEmpty());
    }
}
