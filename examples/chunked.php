<?php

/**
 * Analyse a stream in pieces, with a letter limit, and in substitute mode
 * so a broken byte does not stop the sweep.
 *
 * php examples/chunked.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use IndianOpinion\ScriptShare\Analyser;
use IndianOpinion\ScriptShare\InvalidMode;
use IndianOpinion\ScriptShare\Options;

$stream = fopen('php://memory', 'w+b');
if ($stream === false) {
    throw new RuntimeException('Cannot open a memory stream.');
}
fwrite($stream, str_repeat("தமிழ் ஒரு பழமையான மொழி. \xFF ", 2000));
rewind($stream);

$analyser = new Analyser(new Options(invalid: InvalidMode::Substitute, maxLetters: 5000));
$acc = $analyser->accumulator();
while (!feof($stream)) {
    $piece = fread($stream, 4096);
    if ($piece === false) {
        break;
    }
    $acc->add($piece);
}
fclose($stream);

$result = $acc->finish();
printf(
    "letters %d, invalid sequences %d, truncated %s, dominant %s\n",
    $result->letters(),
    $result->invalidSequences(),
    $result->truncated() ? 'yes' : 'no',
    $result->dominant()->value ?? 'none',
);
