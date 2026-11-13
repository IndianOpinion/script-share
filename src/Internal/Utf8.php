<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Internal;

/**
 * Byte-level UTF-8 helpers, following Unicode table 3-7 (well-formed byte
 * sequences) and the "substitution of maximal subparts" practice.
 *
 * Only used on windows that mb_check_encoding() has rejected, or to find a
 * safe place to cut, so the byte loop never runs over valid text in bulk.
 *
 * @internal
 */
final class Utf8
{
    private function __construct()
    {
    }

    /**
     * Inspect the sequence starting at $i.
     *
     * Returns [true, length] for a well-formed sequence, or [false, length]
     * for a maximal invalid subpart (always at least one byte).
     *
     * @return array{bool, int}
     */
    public static function inspect(string $s, int $i, int $n): array
    {
        $b = ord($s[$i]);
        if ($b < 0x80) {
            return [true, 1];
        }
        if ($b >= 0xC2 && $b <= 0xDF) {
            $need = 2;
            $lo = 0x80;
            $hi = 0xBF;
        } elseif ($b === 0xE0) {
            $need = 3;
            $lo = 0xA0;
            $hi = 0xBF;
        } elseif ($b === 0xED) {
            $need = 3;
            $lo = 0x80;
            $hi = 0x9F;
        } elseif ($b >= 0xE1 && $b <= 0xEF) {
            $need = 3;
            $lo = 0x80;
            $hi = 0xBF;
        } elseif ($b === 0xF0) {
            $need = 4;
            $lo = 0x90;
            $hi = 0xBF;
        } elseif ($b === 0xF4) {
            $need = 4;
            $lo = 0x80;
            $hi = 0x8F;
        } elseif ($b >= 0xF1 && $b <= 0xF3) {
            $need = 4;
            $lo = 0x80;
            $hi = 0xBF;
        } else {
            // 80..C1 and F5..FF never start a sequence.
            return [false, 1];
        }

        for ($k = 1; $k < $need; ++$k) {
            if ($i + $k >= $n) {
                return [false, $k];
            }
            $c = ord($s[$i + $k]);
            if ($c < $lo || $c > $hi) {
                return [false, $k];
            }
            $lo = 0x80;
            $hi = 0xBF;
        }

        return [true, $need];
    }

    /**
     * The largest cut point <= $limit that does not split a sequence which
     * the bytes before the cut have started. Invalid bytes are never held
     * back for longer than one call.
     */
    public static function safeCut(string $s, int $limit): int
    {
        for ($back = 1; $back <= 3 && $back <= $limit; ++$back) {
            $b = ord($s[$limit - $back]);
            if ($b < 0x80) {
                return $limit;
            }
            if ($b >= 0xC0) {
                $need = $b >= 0xF0 ? 4 : ($b >= 0xE0 ? 3 : 2);

                return $back < $need ? $limit - $back : $limit;
            }
        }

        return $limit;
    }

    /**
     * Offset just past the last ASCII whitespace byte in $s, or 0.
     */
    public static function afterLastSpace(string $s): int
    {
        $best = -1;
        foreach ([' ', "\n", "\t", "\r", "\f", "\v"] as $ws) {
            $pos = strrpos($s, $ws);
            if ($pos !== false && $pos > $best) {
                $best = $pos;
            }
        }

        return $best + 1;
    }
}
