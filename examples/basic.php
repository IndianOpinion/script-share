<?php

/**
 * php examples/basic.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use IndianOpinion\ScriptShare\ScriptShare;

$text = 'RBI ने बुधवार को रेपो दर में कोई बदलाव नहीं किया।';
$result = ScriptShare::analyse($text);

printf("letters: %d, ignored: %d\n", $result->letters(), $result->ignored());
foreach ($result->shares() as $code => $share) {
    printf("  %s %.4f\n", $code, $share);
}
printf("dominant: %s\n", $result->dominant()->value ?? 'none');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
