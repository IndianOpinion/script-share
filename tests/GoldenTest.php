<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Options;
use PHPUnit\Framework\TestCase;

/**
 * PRD 12, test 53: a fixed corpus gives the committed counts on every PHP
 * build in the CI matrix (8.1, 8.2, 8.3). Regenerate with bin/golden.php.
 */
final class GoldenTest extends TestCase
{
    public function testCorpusMatchesCommittedCounts(): void
    {
        $dir = __DIR__ . '/fixtures';
        $lines = explode("\n", rtrim((string) file_get_contents($dir . '/golden-corpus.txt'), "\n"));
        /** @var list<array<string, array{code_points: int, letters: int, counts: array<string, int>}>> $expected */
        $expected = json_decode((string) file_get_contents($dir . '/golden-expected.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThanOrEqual(300, count($lines));
        self::assertCount(count($lines), $expected);

        $modes = ['count' => new Analyser(), 'ignore' => new Analyser(new Options(marks: MarkMode::Ignore))];
        foreach ($lines as $k => $line) {
            foreach ($modes as $mode => $analyser) {
                $r = $analyser->analyse($line);
                $actual = ['code_points' => $r->codePoints(), 'letters' => $r->letters(), 'counts' => $r->counts()];
                self::assertSame($expected[$k][$mode], $actual, sprintf('line %d, %s mode', $k + 1, $mode));
            }
        }
    }

    /**
     * The single-script sample lines hold no Common or Inherited letters, so
     * an independent count of \p{L} and \p{M} must agree with the corpus.
     */
    public function testSampleLinesAgreeWithAnIndependentCount(): void
    {
        foreach (Samples::BY_SCRIPT as $code => $text) {
            $r = (new Analyser())->analyse($text);
            self::assertSame(preg_match_all('/[\p{L}\p{M}]/u', $text), $r->letters(), $code);
            self::assertSame(preg_match_all('/\p{L}/u', $text), (new Analyser(new Options(marks: MarkMode::Ignore)))->analyse($text)->letters(), $code);
        }
    }
}
