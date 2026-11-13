<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Accumulator;
use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Exception\InvalidUtf8Exception;
use IndianOpinion\ScriptShare\Exception\LogicException;
use IndianOpinion\ScriptShare\InvalidMode;
use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Result;
use IndianOpinion\ScriptShare\Script;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.9: letter limits, chunked input and windows.
 */
final class ChunkedTest extends TestCase
{
    private const HINDI = 'भारत की राजधानी नई दिल्ली है। यह शहर यमुना नदी के किनारे बसा है और इसका इतिहास बहुत पुराना है।';

    // 41. (4.9.2)
    public function testMaxLettersTruncates(): void
    {
        $analyser = new Analyser(new Options(maxLetters: 10));
        $long = $analyser->analyse(self::HINDI);
        self::assertTrue($long->truncated());
        self::assertGreaterThanOrEqual(10, $long->letters());
        self::assertLessThanOrEqual(13, $long->letters());
        self::assertSame($long->codePoints(), $long->letters() + $long->ignored());

        $short = $analyser->analyse('नमस्ते');
        self::assertFalse($short->truncated());
        self::assertSame(6, $short->letters());
    }

    public function testMaxLettersKeepsTrailingMarks(): void
    {
        // न म स ् त े : the limit falls on स, the virama is still counted, त stops it.
        $r = (new Analyser(new Options(maxLetters: 3)))->analyse('नमस्ते');
        self::assertSame(4, $r->letters());
        self::assertTrue($r->truncated());
        self::assertSame(4, $r->codePoints());

        // Inherited marks after the limit attach too.
        $r = (new Analyser(new Options(maxLetters: 1)))->analyse("e\u{0301}\u{0300}x");
        self::assertSame(3, $r->letters());
        self::assertTrue($r->truncated());
    }

    public function testMaxLettersSkipsAPoorLead(): void
    {
        $text = 'https://example.com/' . str_repeat('a', 500) . ' 1 2 3 4 5 6 7 8 9 ' . self::HINDI;
        $r = (new Analyser(new Options(skipLinks: true, maxLetters: 20)))->analyse($text);
        self::assertSame(Script::Deva, $r->dominant(0.99));
        self::assertTrue($r->truncated());
    }

    public function testExactlyAtTheLimitIsNotTruncated(): void
    {
        $r = (new Analyser(new Options(maxLetters: 5)))->analyse('Hello. 123');
        self::assertSame(5, $r->letters());
        self::assertFalse($r->truncated());
        self::assertSame(10, $r->codePoints());
    }

    public function testMaxLettersMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(maxLetters: 0);
    }

    // 42. (4.9.3)
    public function testEverySplitPointGivesTheSameResult(): void
    {
        $text = self::HINDI . " e\u{0301} क\u{200D}\u{0951} https://example.com/x #टैग";
        foreach ([new Options(skipLinks: true), new Options(marks: MarkMode::Ignore)] as $options) {
            $whole = (new Analyser($options))->analyse($text);
            for ($i = 0; $i <= strlen($text); ++$i) {
                $acc = (new Analyser($options))->accumulator();
                $acc->add(substr($text, 0, $i));
                $acc->add(substr($text, $i));
                self::assertEquals($whole, $acc->finish(), "split at byte $i");
            }
        }
    }

    public function testByteByByteFeedGivesTheSameResult(): void
    {
        $text = str_repeat(self::HINDI . ' Hello e' . "\u{0301}" . ' ', 30);
        $whole = (new Analyser())->analyse($text);
        $acc = new Accumulator(null, 64);
        foreach (str_split($text) as $byte) {
            $acc->add($byte);
        }
        self::assertEquals($whole, $acc->finish());
    }

    public function testSplitBeforeAnInheritedMarkAttachesIt(): void
    {
        $acc = (new Analyser())->accumulator();
        $acc->add('e');
        $acc->add("\u{0301}");
        $r = $acc->finish();
        self::assertSame(2, $r->count(Script::Latn));
    }

    public function testUnfinishedSequenceAtFinish(): void
    {
        $acc = (new Analyser())->accumulator();
        $acc->add("abc\xE0\xA4");
        try {
            $acc->finish();
            self::fail('expected InvalidUtf8Exception');
        } catch (InvalidUtf8Exception $e) {
            self::assertSame(3, $e->byteOffset());
        }

        $acc = (new Analyser(new Options(invalid: InvalidMode::Substitute)))->accumulator();
        $acc->add("abc\xE0");
        $acc->add("\xA4");
        $r = $acc->finish();
        self::assertSame(3, $r->letters());
        self::assertSame(1, $r->invalidSequences());
    }

    // 43. (4.9.3)
    public function testAddAfterFinishThrows(): void
    {
        $acc = (new Analyser())->accumulator();
        $acc->add('abc');
        $acc->finish();
        $this->expectException(LogicException::class);
        $acc->add('more');
    }

    public function testFinishTwiceThrows(): void
    {
        $acc = (new Analyser())->accumulator();
        $acc->finish();
        $this->expectException(LogicException::class);
        $acc->finish();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function windowed(): iterable
    {
        yield 'spaced Hindi' => [str_repeat(self::HINDI . ' ', 20)];
        yield 'one long word' => [str_repeat('नमस्ते', 200)];
        yield 'marks across cuts' => [str_repeat("e\u{0301}\u{0300}क\u{200D}\u{0951}", 300)];
        yield 'long link' => ['पढ़ें https://example.com/' . str_repeat('abcdefgh', 100) . ' अब'];
        yield 'long hashtag' => ['#' . str_repeat('टैग', 200) . ' ok'];
    }

    /**
     * Windows of 16 bytes force cuts inside words, marks and links. The
     * counts must not change for any of them.
     */
    #[DataProvider('windowed')]
    public function testSmallWindowsChangeNothing(string $text): void
    {
        foreach ([new Options(), new Options(skipLinks: true)] as $options) {
            $whole = (new Analyser($options))->analyse($text);
            foreach ([16, 17, 31, 64] as $window) {
                $acc = new Accumulator($options, $window);
                foreach (str_split($text, 7) as $piece) {
                    $acc->add($piece);
                }
                $r = $acc->finish();
                $label = "window $window, skipLinks " . ($options->skipLinks ? 'on' : 'off');
                self::assertSame($whole->counts(), $r->counts(), $label);
                self::assertSame($whole->codePoints(), $r->codePoints(), $label);
            }
        }
    }

    /**
     * A run with no whitespace longer than a window is cut at a code point.
     * The cut moves back before an ASCII link, email, mention or hashtag
     * prefix, so a prefix that would have straddled it is still skipped.
     */
    public function testForcedCutKeepsLinkPrefixesWhole(): void
    {
        $analyser = new Analyser(new Options(skipLinks: true));
        foreach (['https://example.com/abc', 'www.example.com', 'user@example.com', '#tag', '@handle'] as $link) {
            for ($pad = 1; $pad <= 40; ++$pad) {
                $lead = Accumulator::DEFAULT_WINDOW - $pad;
                $text = str_repeat('|', $lead % 3) . str_repeat('क', intdiv($lead, 3)) . $link . ' ख';
                $r = $analyser->analyse($text);
                self::assertSame(0, $r->count(Script::Latn), "$link at cut minus $pad");
                self::assertSame(intdiv($lead, 3) + 1, $r->count(Script::Deva), "$link at cut minus $pad");
            }
        }
    }

    public function testWindowTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Accumulator(null, 8);
    }

    public function testMaxLettersAcrossChunks(): void
    {
        $options = new Options(maxLetters: 10);
        $whole = (new Analyser($options))->analyse(self::HINDI);
        $acc = new Accumulator($options, 16);
        foreach (str_split(self::HINDI, 5) as $piece) {
            $acc->add($piece);
        }
        self::assertEquals($whole, $acc->finish());
    }

    public function testThrowModeStillValidatesAfterTruncation(): void
    {
        $this->expectException(InvalidUtf8Exception::class);
        (new Analyser(new Options(maxLetters: 1)))->analyse("abc \xFF");
    }

    public function testResultIsAValueObject(): void
    {
        self::assertInstanceOf(Result::class, (new Analyser())->accumulator()->finish());
    }
}
