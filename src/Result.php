<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;

/**
 * The outcome of one analysis. Immutable.
 *
 * Invariants: letters() + ignored() === codePoints(); when letters() > 0
 * the shares sum to 1.0 (within float rounding); when letters() is 0 every
 * share is 0.0 and there is no dominant script.
 */
final class Result implements \JsonSerializable
{
    /** @var array<string, int> ISO code => count, highest first, ties in Script order. */
    private array $counts;
    private int $letters;
    private int $codePoints;
    private int $invalidSequences;
    private bool $truncated;

    /**
     * @param array<string, int> $counts
     */
    private function __construct(array $counts, int $letters, int $codePoints, int $invalidSequences, bool $truncated)
    {
        $this->counts = $counts;
        $this->letters = $letters;
        $this->codePoints = $codePoints;
        $this->invalidSequences = $invalidSequences;
        $this->truncated = $truncated;
    }

    /**
     * Build a result from raw counts. Used by the scanner and by merge().
     *
     * @internal
     *
     * @param array<string, int> $counts ISO code => letters (zero entries are dropped)
     *
     * @throws InvalidArgumentException on an unknown code or inconsistent totals.
     */
    public static function fromCounts(array $counts, int $codePoints, int $invalidSequences, bool $truncated): self
    {
        $order = self::order();
        $clean = [];
        $letters = 0;
        foreach ($counts as $code => $n) {
            if (!isset($order[$code]) || $n < 0) {
                throw new InvalidArgumentException('Counts must be keyed by a known script code and not negative.');
            }
            if ($n > 0) {
                $clean[$code] = $n;
                $letters += $n;
            }
        }
        if ($invalidSequences < 0 || $codePoints < $letters + $invalidSequences) {
            throw new InvalidArgumentException('codePoints must cover letters and invalid sequences.');
        }
        uksort($clean, static function (string $a, string $b) use ($clean, $order): int {
            return [$clean[$b], $order[$a]] <=> [$clean[$a], $order[$b]];
        });

        return new self($clean, $letters, $codePoints, $invalidSequences, $truncated);
    }

    /** Code points read, including ignored ones and invalid sequences. */
    public function codePoints(): int
    {
        return $this->codePoints;
    }

    /** Counted letters (marks included in count mode). */
    public function letters(): int
    {
        return $this->letters;
    }

    /** Code points read but not counted. */
    public function ignored(): int
    {
        return $this->codePoints - $this->letters;
    }

    /** Maximal invalid subparts seen (substitute mode only). */
    public function invalidSequences(): int
    {
        return $this->invalidSequences;
    }

    /** Did maxLetters stop the analysis with letters left uncounted? */
    public function truncated(): bool
    {
        return $this->truncated;
    }

    /** True when no letter was counted. */
    public function isEmpty(): bool
    {
        return $this->letters === 0;
    }

    public function count(Script $script): int
    {
        return $this->counts[$script->value] ?? 0;
    }

    public function share(Script $script): float
    {
        if ($this->letters === 0) {
            return 0.0;
        }

        return (float) $this->count($script) / $this->letters;
    }

    /**
     * Sum of the shares of the given scripts. Each script counts once.
     */
    public function shareOf(Script ...$scripts): float
    {
        if ($this->letters === 0) {
            return 0.0;
        }
        $sum = 0;
        $seen = [];
        foreach ($scripts as $script) {
            if (!isset($seen[$script->value])) {
                $seen[$script->value] = true;
                $sum += $this->count($script);
            }
        }

        return (float) $sum / $this->letters;
    }

    /**
     * Letters per script, keyed by ISO code, highest first.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * Unrounded share per script, keyed by ISO code, highest first.
     *
     * @return array<string, float>
     */
    public function shares(): array
    {
        $shares = [];
        foreach ($this->counts as $code => $n) {
            $shares[$code] = (float) $n / $this->letters;
        }

        return $shares;
    }

    /**
     * The script with the most letters, or null when there are too few
     * letters, the top share is below $minShare, or first place is tied.
     *
     * @throws InvalidArgumentException when $minShare is outside 0..1 or $minLetters is negative.
     */
    public function dominant(float $minShare = 0.5, int $minLetters = 1): ?Script
    {
        self::assertShare($minShare);
        self::assertLetters($minLetters);
        if ($this->letters === 0 || $this->letters < $minLetters) {
            return null;
        }
        $top = array_key_first($this->counts);
        $values = array_values($this->counts);
        if ($top === null || (isset($values[1]) && $values[1] === $values[0])) {
            return null;
        }
        if ($values[0] / $this->letters < $minShare) {
            return null;
        }

        return Script::from($top);
    }

    /**
     * Add two results for independent texts. Mark context does not carry
     * across, so this is not always equal to analysing a concatenation.
     */
    public function merge(Result $other): Result
    {
        $counts = $this->counts;
        foreach ($other->counts as $code => $n) {
            $counts[$code] = ($counts[$code] ?? 0) + $n;
        }

        return self::fromCounts(
            $counts,
            $this->codePoints + $other->codePoints,
            $this->invalidSequences + $other->invalidSequences,
            $this->truncated || $other->truncated,
        );
    }

    /**
     * Plain data for logs and JSON. Shares are rounded to four decimals.
     *
     * @return array{code_points: int, letters: int, ignored: int, invalid_sequences: int, truncated: bool,
     *     counts: array<string, int>, shares: array<string, float>, dominant: ?string}
     */
    public function toArray(): array
    {
        $shares = [];
        foreach ($this->shares() as $code => $share) {
            $shares[$code] = round($share, 4);
        }

        return [
            'code_points' => $this->codePoints,
            'letters' => $this->letters,
            'ignored' => $this->ignored(),
            'invalid_sequences' => $this->invalidSequences,
            'truncated' => $this->truncated,
            'counts' => $this->counts,
            'shares' => $shares,
            'dominant' => $this->dominant()?->value,
        ];
    }

    /**
     * @return array{code_points: int, letters: int, ignored: int, invalid_sequences: int, truncated: bool,
     *     counts: array<string, int>, shares: array<string, float>, dominant: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @internal
     *
     * @throws InvalidArgumentException
     */
    public static function assertShare(float $share): void
    {
        if (is_nan($share) || $share < 0.0 || $share > 1.0) {
            throw new InvalidArgumentException('minShare must be a number from 0.0 to 1.0.');
        }
    }

    /**
     * @internal
     *
     * @throws InvalidArgumentException
     */
    public static function assertLetters(int $letters): void
    {
        if ($letters < 0) {
            throw new InvalidArgumentException('minLetters must not be negative.');
        }
    }

    /**
     * @return array<string, int> ISO code => position in the Script enum
     */
    private static function order(): array
    {
        $order = [];
        foreach (Script::cases() as $i => $script) {
            $order[$script->value] = $i;
        }

        return $order;
    }
}
