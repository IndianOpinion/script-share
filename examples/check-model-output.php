<?php

/**
 * Guard after a model call: did the reply come back in the requested language?
 *
 * php examples/check-model-output.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use IndianOpinion\ScriptShare\ScriptShare;
use IndianOpinion\ScriptShare\Verdict;

$requested = 'hi';
$replies = [
    'good' => 'भारत की राजधानी नई दिल्ली है और यह एक बड़ा शहर है',
    'wrong language' => 'తెలుగు భాష చాలా మధురమైనది మరియు ప్రాచీనమైనది కూడా',
    'too short' => 'ठीक है',
];

foreach ($replies as $label => $reply) {
    $check = ScriptShare::checkLanguage($reply, $requested, 0.80, 20);
    // Model output: fail closed, so too_short is a rejection too.
    $accept = $check->verdict() === Verdict::Pass;
    printf("%-15s %-7s %s\n", $label, $accept ? 'accept' : 'reject', $check->reason());
}
