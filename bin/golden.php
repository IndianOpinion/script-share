<?php

/**
 * Regenerates the golden corpus and its expected counts.
 *
 *     php bin/golden.php           # check: exit 1 if the committed files differ
 *     php bin/golden.php --write   # rewrite tests/fixtures/golden-*.{txt,json}
 *
 * Only regenerate after a deliberate behaviour change, and say why in the
 * pull request. The corpus uses code points from Unicode 14.0 or older, so
 * the expected counts must be identical on every PHP build in the matrix.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../tests/Samples.php';

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Tests\Samples;

$lines = array_values(Samples::BY_SCRIPT);
$samples = array_values(Samples::BY_SCRIPT);
$n = count($samples);
for ($i = 0; $i < $n; ++$i) {
    for ($j = $i + 1; $j < $n; ++$j) {
        $lines[] = $samples[$i] . ' ' . $samples[$j];
    }
}
$lines = array_merge($lines, [
    "cafe\u{0301} caf\u{00E9} e\u{200D}\u{0301} \u{0301}start",
    "क\u{200D}\u{0951} क्षि किताब २०२६ ।॥",
    "தமி\u{0D3F}ழ் த\u{0D3F}\u{0301}",
    '𝐇𝐞𝐥𝐥𝐨 ＡＢＣ don' . "\u{02BC}" . 't ﭐﻻ ƀǀ',
    Samples::HINDI_WITH_NAMES,
    'See https://example.com/a-b?c=d, (www.example.org). Mail desk@example.com #खबर @desk_1',
    '日本語は、ひらがなとカタカナと漢字で書きます。',
    "\u{0000}\u{0001} 2026 ௨௦ ₹ 😀",
]);

$fixtures = __DIR__ . '/../tests/fixtures';
$corpus = implode("\n", $lines) . "\n";

$modes = ['count' => new Analyser(), 'ignore' => new Analyser(new Options(marks: MarkMode::Ignore))];
$expected = [];
foreach ($lines as $k => $line) {
    $row = [];
    foreach ($modes as $mode => $analyser) {
        $r = $analyser->analyse($line);
        $row[$mode] = ['code_points' => $r->codePoints(), 'letters' => $r->letters(), 'counts' => $r->counts()];
    }
    $expected[] = $row;
}
$json = json_encode($expected, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

if (in_array('--write', $argv, true)) {
    file_put_contents($fixtures . '/golden-corpus.txt', $corpus);
    file_put_contents($fixtures . '/golden-expected.json', $json);
    printf("Wrote %d lines.\n", count($lines));
    exit(0);
}

$same = is_file($fixtures . "/golden-corpus.txt") && file_get_contents($fixtures . "/golden-corpus.txt") === $corpus
    && is_file($fixtures . "/golden-expected.json") && file_get_contents($fixtures . "/golden-expected.json") === $json;
echo $same ? "Golden files are up to date.\n" : "Golden files differ. Run with --write after checking why.\n";
exit($same ? 0 : 1);
