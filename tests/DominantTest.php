<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Tests;

use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use PHPUnit\Framework\TestCase;

/**
 * PRD 4.6: the dominant script helper.
 */
final class DominantTest extends TestCase
{
    // 28. (4.6.1)
    public function testDominantHonoursItsConditions(): void
    {
        self::assertSame(Script::Telu, ScriptShare::dominant(Samples::TELUGU));
        self::assertNull(ScriptShare::dominant('తెలుగు', 0.5, 50), 'below minLetters');
        // 4 Latin, 3 Deva, 3 Taml: Latin leads with 0.4.
        self::assertNull(ScriptShare::dominant('abcd कखग தகந', 0.5), 'top share under minShare');
        self::assertSame(Script::Latn, ScriptShare::dominant('abcd कखग தகந', 0.4));
    }

    // 29. (4.6.2)
    public function testExactTieHasNoWinner(): void
    {
        foreach ([0.0, 0.1, 0.5, 1.0] as $minShare) {
            self::assertNull(ScriptShare::dominant('abcd कखगघ', $minShare));
            self::assertNull(ScriptShare::dominant('कखगघ abcd', $minShare));
        }
    }

    // 30. (4.6.3)
    public function testZeroMinShareGivesThePlurality(): void
    {
        $text = 'abc कखगघ اب';
        self::assertNull(ScriptShare::dominant($text));
        self::assertSame(Script::Deva, ScriptShare::dominant($text, 0.0));
    }

    // 31. (4.6.4)
    public function testNoLettersHasNoDominantScript(): void
    {
        self::assertNull(ScriptShare::dominant(''));
        self::assertNull(ScriptShare::dominant('2026'));
        self::assertNull(ScriptShare::dominant('2026', 0.0, 0));
    }

    public function testFullShareMeetsAThresholdOfOne(): void
    {
        self::assertSame(Script::Latn, ScriptShare::dominant('abc', 1.0));
        self::assertNull(ScriptShare::dominant('abc क', 1.0));
    }
}
