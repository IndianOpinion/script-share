<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Accumulator;
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * PRD 9: memory stays flat. Timing is reported by bench/bench.php, not asserted.
 */
final class PerformanceTest extends TestCase
{
    private const BUDGET = 4 * 1024 * 1024;

    public static function mixed(int $bytes): string
    {
        $unit = 'भारतीय रिज़र्व बैंक (RBI) ने बुधवार को रेपो दर 6.5% पर स्थिर रखी। Read more at https://example.com/a. ';

        return mb_strcut(str_repeat($unit, intdiv($bytes, strlen($unit)) + 1), 0, $bytes, 'UTF-8');
    }

    // 48. (9)
    #[RunInSeparateProcess]
    public function testOneMebibyteStaysWithinTheMemoryBudget(): void
    {
        $text = self::mixed(1024 * 1024);
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $before = memory_get_usage();
        $result = ScriptShare::analyse($text);
        $extra = memory_get_peak_usage() - $before;

        self::assertGreaterThan(250000, $result->letters());
        self::assertSame(Script::Deva, $result->dominant());
        self::assertLessThan(self::BUDGET, $extra, sprintf('extra peak memory %d bytes', $extra));
    }

    #[RunInSeparateProcess]
    public function testWorstCaseTokenDensityStaysWithinTheBudget(): void
    {
        $text = str_repeat('a.', 512 * 1024);
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $before = memory_get_usage();
        $result = ScriptShare::analyse($text);
        $extra = memory_get_peak_usage() - $before;

        self::assertSame(512 * 1024, $result->letters());
        self::assertLessThan(self::BUDGET, $extra, sprintf('extra peak memory %d bytes', $extra));
    }

    // 51. (4.9.1): doubling the input doubles the counts; windows bound the work per step.
    public function testWorkScalesWithInput(): void
    {
        $one = ScriptShare::analyse(str_repeat('नमस्ते दुनिया hello ', 2000));
        $two = ScriptShare::analyse(str_repeat('नमस्ते दुनिया hello ', 4000));
        self::assertSame(2 * $one->letters(), $two->letters());
        self::assertSame(2 * $one->codePoints(), $two->codePoints());
        self::assertGreaterThan(1, intdiv(strlen(str_repeat('नमस्ते दुनिया hello ', 4000)), Accumulator::DEFAULT_WINDOW));
    }
}
