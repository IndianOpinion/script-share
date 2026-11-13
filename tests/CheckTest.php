<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use IndianOpinion\ScriptShare\Verdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.7: the threshold check helper.
 */
final class CheckTest extends TestCase
{
    // 27. (4.5.6, 4.7.3)
    public function testJapaneseNeedsASetOfScripts(): void
    {
        $text = '日本語は、ひらがなとカタカナと漢字で書きます。東京タワーへ行きました。';
        $check = ScriptShare::check($text, [Script::Hani, Script::Hira, Script::Kana], 0.95);
        self::assertSame(Verdict::Pass, $check->verdict());
        self::assertEqualsWithDelta(1.0, $check->expectedShare(), 1e-9);
        self::assertFalse(ScriptShare::check($text, Script::Hani, 0.95)->passed());
        self::assertTrue(ScriptShare::checkLanguage($text, 'ja', 0.95)->passed());
    }

    // 32. (4.7.4)
    public function testThreeVerdicts(): void
    {
        $telugu = ScriptShare::check(Samples::TELUGU, Script::Deva, 0.8);
        self::assertSame(Verdict::Fail, $telugu->verdict());
        self::assertSame(Script::Telu, $telugu->dominant());
        self::assertSame(0.0, $telugu->expectedShare());

        $hindi = ScriptShare::check(Samples::HINDI, Script::Deva, 0.8);
        self::assertSame(Verdict::Pass, $hindi->verdict());
        self::assertTrue($hindi->passed());

        $short = ScriptShare::check('नमस्ते भारत', Script::Deva, 0.8);
        self::assertSame(10, $short->result()->letters());
        self::assertSame(Verdict::TooShort, $short->verdict());
        self::assertSame(1.0, $short->expectedShare());
        self::assertFalse($short->passed());
    }

    public function testBoundaryIsInclusive(): void
    {
        // 3 Deva of 4 letters: exactly 0.75.
        self::assertTrue(ScriptShare::check('कखग a', Script::Deva, 0.75, 1)->passed());
        self::assertFalse(ScriptShare::check('कखग a', Script::Deva, 0.76, 1)->passed());
        self::assertTrue(ScriptShare::check('कखग a', Script::Deva, 0.75, 4)->passed());
        self::assertSame(Verdict::TooShort, ScriptShare::check('कखग a', Script::Deva, 0.75, 5)->verdict());
    }

    // 33. (4.7.2, 4.7.3)
    public function testExpectedSetSumsShares(): void
    {
        self::assertFalse(ScriptShare::check(Samples::HINDI_WITH_NAMES, Script::Deva, 0.99)->passed());
        self::assertTrue(ScriptShare::check(Samples::HINDI_WITH_NAMES, [Script::Deva, Script::Latn], 0.99)->passed());
        self::assertTrue(ScriptShare::check(Samples::HINDI_WITH_NAMES, ['deva', 'Latn'], 0.99)->passed());
    }

    public function testDuplicatesInTheSetCountOnce(): void
    {
        $check = ScriptShare::check('कखग a', [Script::Deva, Script::Deva, 'Deva'], 0.8, 1);
        self::assertSame(0.75, $check->expectedShare());
        self::assertSame([Script::Deva], $check->expected());
    }

    // 34. (4.7.5)
    public function testReasonFormat(): void
    {
        self::assertSame(
            'fail: Deva 0.00 < 0.80 (dominant Telu, 43 letters)',
            ScriptShare::check(Samples::TELUGU, Script::Deva, 0.8)->reason(),
        );
        self::assertSame(
            'pass: Deva 1.00 >= 0.80 (dominant Deva, 38 letters)',
            ScriptShare::check(Samples::HINDI, Script::Deva, 0.8)->reason(),
        );
        self::assertSame(
            'too_short: 10 letters < 20 (Deva 1.00, dominant Deva)',
            ScriptShare::check('नमस्ते भारत', Script::Deva, 0.8)->reason(),
        );
        self::assertSame(
            'too_short: 0 letters < 20 (Deva+Latn 0.00, dominant none)',
            ScriptShare::check('2026', [Script::Deva, Script::Latn], 0.8)->reason(),
        );
        self::assertSame(
            'fail: Latn 0.6667 < 0.95 (dominant Latn, 3 letters)',
            ScriptShare::check('ab क', Script::Latn, 0.95, 1)->reason(),
        );
    }

    public function testToArrayIsFlatAndSafe(): void
    {
        $array = ScriptShare::check(Samples::TELUGU, Script::Deva, 0.8)->toArray();
        self::assertSame('fail', $array['verdict']);
        self::assertFalse($array['passed']);
        self::assertSame(['Deva'], $array['expected']);
        self::assertSame(0.0, $array['expected_share']);
        self::assertSame('Telu', $array['dominant']);
        self::assertSame(43, $array['result']['letters']);
        self::assertStringNotContainsString('తె', json_encode($array, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return iterable<string, array{float, int, mixed}>
     */
    public static function badArguments(): iterable
    {
        yield 'share below 0' => [-0.1, 20, Script::Deva];
        yield 'share above 1' => [1.1, 20, Script::Deva];
        yield 'NAN' => [NAN, 20, Script::Deva];
        yield 'INF' => [INF, 20, Script::Deva];
        yield '-INF' => [-INF, 20, Script::Deva];
        yield 'negative letters' => [0.5, -1, Script::Deva];
        yield 'empty set' => [0.5, 20, []];
        yield 'unknown code' => [0.5, 20, ['Xxxx']];
        yield 'not a script' => [0.5, 20, [42]];
    }

    // 35. (4.7.6)
    #[DataProvider('badArguments')]
    public function testInvalidArgumentsThrow(float $share, int $letters, mixed $expected): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScriptShare::check('abc', $expected, $share, $letters);
    }

    // 36. (4.7.7)
    public function testIsMostlyIsFalseWhenTooShort(): void
    {
        self::assertFalse(ScriptShare::isMostly('नमस्ते भारत', Script::Deva, 0.8));
        self::assertTrue(ScriptShare::isMostly('नमस्ते भारत', Script::Deva, 0.8, 10));
        self::assertTrue(ScriptShare::isMostly(Samples::HINDI, Script::Deva, 0.8));
        self::assertFalse(ScriptShare::isMostly(Samples::TELUGU, Script::Deva, 0.8));
    }

    public function testCheckAcceptsAnExistingResult(): void
    {
        $result = ScriptShare::analyse(Samples::HINDI);
        self::assertSame($result, ScriptShare::check($result, Script::Deva, 0.8)->result());
        self::assertTrue(ScriptShare::checkLanguage($result, 'hi-IN', 0.8)->passed());
        self::assertFalse(ScriptShare::checkLanguage($result, 'te', 0.8)->passed());
    }
}
