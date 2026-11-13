<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Exception\ScriptShareException;
use IndianOpinion\ScriptShare\Result;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.4 and 4.9.4: the result object.
 */
final class ResultTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function noLetters(): iterable
    {
        yield 'empty' => [''];
        yield 'digits' => ['123'];
        yield 'dots' => ['...'];
        yield 'whitespace' => [" \t\n\u{00A0}\u{3000}"];
        yield 'emoji' => ['😀🎉👍🏽'];
    }

    // 19. (4.4.3)
    #[DataProvider('noLetters')]
    public function testNoLettersMeansNoShares(string $text): void
    {
        $r = ScriptShare::analyse($text);
        self::assertSame(0, $r->letters());
        self::assertTrue($r->isEmpty());
        self::assertSame([], $r->shares());
        self::assertSame([], $r->counts());
        self::assertSame(0.0, $r->share(Script::Latn));
        self::assertSame(0.0, $r->shareOf(Script::Latn, Script::Deva));
        self::assertNull($r->dominant());
        self::assertNull($r->dominant(0.0, 0));
        self::assertNull(ScriptShare::dominant($text));
        self::assertSame(mb_strlen($text, 'UTF-8'), $r->ignored());
    }

    /**
     * @return iterable<int, array{string}>
     */
    public static function mixed(): iterable
    {
        yield ['Hello नमस्ते வணக்கம் 你好 שלום'];
        yield [Samples::HINDI_WITH_NAMES];
        yield [implode(' ', Samples::BY_SCRIPT)];
        yield ['abc अआइ ابت'];
        yield ['x'];
    }

    // 20. (4.4.4)
    #[DataProvider('mixed')]
    public function testSharesSumToOne(string $text): void
    {
        $r = ScriptShare::analyse($text);
        self::assertEqualsWithDelta(1.0, array_sum($r->shares()), 1e-9);
        self::assertSame($r->letters(), array_sum($r->counts()));
    }

    // 21. (4.4.5)
    public function testTiesFollowTableOrder(): void
    {
        $r = ScriptShare::analyse('abcd कखगघ');
        self::assertSame(['Deva', 'Latn'], array_keys($r->counts()));
        self::assertSame(['Deva', 'Latn'], array_keys($r->shares()));
        self::assertSame(['Deva', 'Latn'], array_keys(ScriptShare::analyse('कखगघ abcd')->counts()));

        $r = ScriptShare::analyse('ab кгд कखगघ');
        self::assertSame(['Deva', 'Cyrl', 'Latn'], array_keys($r->counts()));
    }

    // 22. (4.4.6)
    public function testToArrayRoundsButAccessorsDoNot(): void
    {
        $r = ScriptShare::analyse('abc क');
        self::assertSame(0.75, $r->share(Script::Latn));
        $r = ScriptShare::analyse('ab क');
        $array = $r->toArray();
        self::assertSame(['Latn' => 0.6667, 'Deva' => 0.3333], $array['shares']);
        self::assertSame(2 / 3, $r->share(Script::Latn));
        self::assertSame(2 / 3, $r->shares()['Latn']);
        self::assertSame($array, $r->jsonSerialize());
        self::assertSame(
            '{"code_points":4,"letters":3,"ignored":1,"invalid_sequences":0,"truncated":false,'
            . '"counts":{"Latn":2,"Deva":1},"shares":{"Latn":0.6667,"Deva":0.3333},"dominant":"Latn"}',
            json_encode($r, JSON_THROW_ON_ERROR),
        );
    }

    // 49. (4.4.1, 4.4.2)
    public function testResultReportsTotalsAndOnlyPresentScripts(): void
    {
        $r = ScriptShare::analyse('Hi नमस्ते, 2026!');
        self::assertSame(16, $r->codePoints());
        self::assertSame(8, $r->letters());
        self::assertSame(8, $r->ignored());
        self::assertSame(0, $r->invalidSequences());
        self::assertFalse($r->truncated());
        self::assertSame(['Deva' => 6, 'Latn' => 2], $r->counts());
        self::assertSame(['Deva' => 0.75, 'Latn' => 0.25], $r->shares());
        self::assertArrayNotHasKey('Taml', $r->counts());
        self::assertSame(0, $r->count(Script::Taml));
        self::assertSame(0.0, $r->share(Script::Taml));
        self::assertSame(1.0, $r->shareOf(Script::Deva, Script::Latn, Script::Deva));
    }

    // 44. (4.9.4)
    public function testMergeAddsCounts(): void
    {
        $a = ScriptShare::analyse('abc');
        $b = ScriptShare::analyse('कखग घ!');
        $m = $a->merge($b);
        self::assertSame(['Deva' => 4, 'Latn' => 3], $m->counts());
        self::assertSame(7, $m->letters());
        self::assertSame(3 + 6, $m->codePoints());
        self::assertSame(4 / 7, $m->share(Script::Deva));
        self::assertSame(['Latn' => 3], $a->counts(), 'merge does not mutate');
    }

    public function testMergeDoesNotCarryMarkContext(): void
    {
        $merged = ScriptShare::analyse('e')->merge(ScriptShare::analyse("\u{0301}"));
        $whole = ScriptShare::analyse("e\u{0301}");
        self::assertSame(1, $merged->letters());
        self::assertSame(2, $whole->letters());
    }

    public function testDominantRejectsBadArguments(): void
    {
        $r = ScriptShare::analyse('abc');
        foreach ([[-0.1, 1], [1.5, 1], [NAN, 1], [0.5, -1]] as [$share, $letters]) {
            try {
                $r->dominant($share, $letters);
                self::fail('expected an exception');
            } catch (InvalidArgumentException $e) {
                self::assertInstanceOf(ScriptShareException::class, $e);
            }
        }
    }

    public function testFromCountsValidates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Result::fromCounts(['Nope' => 1], 1, 0, false);
    }

    public function testFromCountsRejectsTooFewCodePoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Result::fromCounts(['Latn' => 5], 4, 0, false);
    }
}
