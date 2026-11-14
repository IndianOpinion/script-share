<?php

/**
 * Prints timings and peak memory for the performance budget in docs/PRD.md
 * section 9. CI runs it for information only; it never fails a build.
 *
 *     php bench/bench.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\Options;

$unit = 'भारतीय रिज़र्व बैंक (RBI) ने बुधवार को रेपो दर 6.5% पर स्थिर रखी। Read more at https://example.com/a. ';

$make = static function (int $bytes) use ($unit): string {
    return mb_strcut(str_repeat($unit, intdiv($bytes, strlen($unit)) + 1), 0, $bytes, 'UTF-8');
};

$analyser = new Analyser();

/**
 * @return array{float, int} best time in ms over $runs, and extra peak bytes
 */
$measure = static function (string $text, int $runs, ?Analyser $with = null) use ($analyser): array {
    $with ??= $analyser;
    $best = INF;
    $extra = 0;
    for ($i = 0; $i < $runs; ++$i) {
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $before = memory_get_usage();
        $t = hrtime(true);
        $with->analyse($text);
        $best = min($best, (hrtime(true) - $t) / 1e6);
        $extra = max($extra, memory_get_peak_usage() - $before);
    }

    return [$best, $extra];
};

printf("PHP %s, PCRE %s, JIT %s\n", PHP_VERSION, PCRE_VERSION, ini_get('pcre.jit') === '1' ? 'on' : 'off');
printf("%-10s %12s %16s\n", 'input', 'best ms', 'extra peak KiB');
$times = [];
foreach (['1 KiB' => 1024, '1 MiB' => 1 << 20, '8 MiB' => 8 << 20] as $label => $bytes) {
    [$ms, $extra] = $measure($make($bytes), $bytes > (1 << 20) ? 3 : 10);
    $times[$label] = $ms;
    printf("%-10s %12.3f %16d\n", $label, $ms, intdiv($extra, 1024));
}
[$ms, $extra] = $measure($make(1 << 20), 10, new Analyser(new Options(skipLinks: true)));
printf("%-10s %12.3f %16d  (skipLinks on)\n", '1 MiB', $ms, intdiv($extra, 1024));
printf("8 MiB / 1 MiB time ratio: %.2f (budget: 10 or less)\n", $times['8 MiB'] / $times['1 MiB']);
[$ms, $extra] = $measure(str_repeat('a.', 512 * 1024), 3);
printf("worst-case token density, 1 MiB of 'a.': %.3f ms, %d KiB extra\n", $ms, intdiv($extra, 1024));
