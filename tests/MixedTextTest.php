<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.5: mixed-script text, links, hashtags and mentions.
 */
final class MixedTextTest extends TestCase
{
    private static function counting(): Analyser
    {
        return new Analyser(new Options(skipLinks: false));
    }

    // 23. (4.5.1)
    public function testEnglishQuotingHindi(): void
    {
        $r = ScriptShare::analyse('He said "नमस्ते भारत" and left.');
        self::assertSame(['Latn' => 13, 'Deva' => 10], $r->counts());
        self::assertSame(10 / 23, $r->share(Script::Deva));
    }

    public function testHindiNewsWithLatinNames(): void
    {
        $r = ScriptShare::analyse(Samples::HINDI_WITH_NAMES);
        self::assertSame(3, $r->count(Script::Latn));
        self::assertGreaterThan(0.9, $r->share(Script::Deva));
        self::assertLessThan(1.0, $r->share(Script::Deva));
    }

    // 24. (4.5.3)
    public function testRomanisedHindiIsLatin(): void
    {
        self::assertSame(['Latn' => 1.0], ScriptShare::shares('aap kaise hain'));
    }

    // 25. (4.5.4)
    public function testSkipLinksKeepsAHindiSentenceHindi(): void
    {
        $text = 'पूरी खबर यहाँ पढ़ें https://www.example.com/news/2026/11/12/a-very-long-article-slug-in-english?utm_source=feed';
        $on = ScriptShare::analyse($text);
        self::assertSame(1.0, $on->share(Script::Deva));
        self::assertSame(['Deva'], array_keys($on->counts()));

        $off = self::counting()->analyse($text);
        self::assertGreaterThan(40, $off->count(Script::Latn));
        self::assertSame($on->count(Script::Deva), $off->count(Script::Deva));
        self::assertSame($on->codePoints(), $off->codePoints());
    }

    /**
     * Ruling 6: a link runs to the next whitespace, trailing punctuation
     * stripped; ruling 3: hashtags and mentions are skipped too.
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function skipped(): iterable
    {
        // text, Latin letters with skipLinks on, Latin letters with it off
        yield 'scheme' => ['see https://example.com/abc now', 6, 24];
        yield 'www' => ['see www.example.com now', 6, 19];
        yield 'other scheme' => ['ftp://files.example.org/x', 0, 19];
        yield 'upper case scheme' => ['HTTPS://EXAMPLE.COM', 0, 15];
        yield 'trailing punctuation' => ['(see https://example.com/abc).', 3, 21];
        yield 'link then danda' => ['https://example.com/abc।', 0, 18];
        yield 'email' => ['write to news.desk+tips@example.co.uk today', 12, 35];
        yield 'loose email' => ['x@y', 0, 2];
        yield 'mention' => ['thanks @news_desk1 for this', 13, 21];
        yield 'hashtag' => ['#Budget2026 is trending', 10, 16];
        yield 'bare at' => ['a @ b', 2, 2];
        yield 'scheme only' => ['http:// abc', 7, 7];
    }

    #[DataProvider('skipped')]
    public function testLinksTagsAndEmails(string $text, int $on, int $off): void
    {
        self::assertSame($on, ScriptShare::analyse($text)->count(Script::Latn), 'skipLinks on');
        self::assertSame($off, self::counting()->analyse($text)->count(Script::Latn), 'skipLinks off');
    }

    public function testHindiHashtagIsSkippedByDefault(): void
    {
        $r = ScriptShare::analyse('आज #भारत में');
        self::assertSame(5, $r->count(Script::Deva));
        self::assertSame(9, self::counting()->analyse('आज #भारत में')->count(Script::Deva));
    }

    public function testInheritedMarkAfterLinkIsIgnored(): void
    {
        $r = ScriptShare::analyse("https://example.com/a\u{0301}");
        self::assertSame(0, $r->letters());
    }

    public function testDefaultOptions(): void
    {
        $o = new Options();
        self::assertTrue($o->skipLinks);
        self::assertNull($o->maxLetters);
        self::assertFalse($o->withSkipLinks(false)->skipLinks);
        self::assertTrue($o->skipLinks, 'with*() returns a copy');
    }
}
