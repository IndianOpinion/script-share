<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\Exception\InvalidUtf8Exception;
use IndianOpinion\ScriptShare\Exception\RegexException;
use IndianOpinion\ScriptShare\Exception\ScriptShareException;
use IndianOpinion\ScriptShare\InvalidMode;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.8: invalid UTF-8 and PCRE failures.
 */
final class InvalidUtf8Test extends TestCase
{
    private static function substituting(): Analyser
    {
        return new Analyser(new Options(invalid: InvalidMode::Substitute));
    }

    private static function offsetOf(string $text): int
    {
        try {
            ScriptShare::analyse($text);
        } catch (InvalidUtf8Exception $e) {
            self::assertInstanceOf(\UnexpectedValueException::class, $e);
            self::assertInstanceOf(ScriptShareException::class, $e);
            self::assertStringNotContainsString('abc', $e->getMessage());

            return $e->byteOffset();
        }
        self::fail('expected InvalidUtf8Exception');
    }

    // 37. (4.8.1)
    public function testThrowModeReportsTheFirstBadOffset(): void
    {
        self::assertSame(3, self::offsetOf("abc\xC3"));
        self::assertSame(0, self::offsetOf("\x80abc"));
        self::assertSame(6, self::offsetOf("क\xE0\xA4\x95\xFFabc"));
    }

    public function testOffsetIsAbsoluteAcrossWindows(): void
    {
        $text = str_repeat('नमस्ते दुनिया ', 3000) . "\xFF";
        self::assertSame(strlen($text) - 1, self::offsetOf($text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'overlong' => ["\xC0\xAF"];
        yield 'surrogate' => ["\xED\xA0\x80"];
        yield 'above U+10FFFF' => ["\xF4\x90\x80\x80"];
        yield 'FF' => ["\xFF"];
        yield 'F5' => ["\xF5\x80\x80\x80"];
        yield 'stray continuation' => ["a\x80b"];
        yield 'truncated 4-byte' => ["\xF0\x9F\x98"];
    }

    // 38. (4.8.2)
    #[DataProvider('invalid')]
    public function testMalformedSequencesThrow(string $bytes): void
    {
        self::assertFalse(mb_check_encoding($bytes, 'UTF-8'));
        $this->expectException(InvalidUtf8Exception::class);
        ScriptShare::analyse($bytes);
    }

    // 39. (4.8.3)
    public function testSubstituteModeCountsMaximalSubparts(): void
    {
        $r = self::substituting()->analyse("ab\xFFcd");
        self::assertSame(4, $r->count(Script::Latn));
        self::assertSame(1, $r->invalidSequences());
        self::assertSame(5, $r->codePoints());
        self::assertSame(1, $r->ignored());

        $truncated = self::substituting()->analyse("\xE0\xA4");
        self::assertSame(1, $truncated->invalidSequences());
        self::assertSame(1, $truncated->codePoints());
        self::assertSame(0, $truncated->letters());

        $mark = self::substituting()->analyse("e\xFF\u{0301}");
        self::assertSame(1, $mark->letters());
        self::assertSame(2, $mark->ignored());
    }

    /**
     * Maximal subparts as in the Unicode Standard, chapter 3, table 3-8.
     */
    public function testMaximalSubpartsFollowTheStandard(): void
    {
        $r = self::substituting()->analyse("a\xF1\x80\x80\xE1\x80\xC2b\x80\xE0\x80c");
        // F1 80 80 | E1 80 | C2 | b | 80 | E0 | 80 | c  => 6 invalid, 3 letters... a and b and c.
        self::assertSame(6, $r->invalidSequences());
        self::assertSame(3, $r->letters());
        self::assertSame(9, $r->codePoints());
    }

    public function testSubstituteModeInLongText(): void
    {
        $unit = "नमस्ते\xFF ";
        $r = self::substituting()->analyse(str_repeat($unit, 5000));
        self::assertSame(5000, $r->invalidSequences());
        self::assertSame(30000, $r->count(Script::Deva));
        self::assertSame(40000, $r->codePoints());
    }

    // 40. (4.8.4)
    public function testPcreFailureThrowsInsteadOfReturningZero(): void
    {
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        try {
            ScriptShare::analyse('Hello world, नमस्ते');
            self::fail('expected RegexException');
        } catch (RegexException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertStringContainsString('limit', strtolower($e->getMessage()));
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
        self::assertSame(16, ScriptShare::analyse('Hello world, नमस्ते')->letters());
    }
}
