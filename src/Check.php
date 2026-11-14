<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;

/**
 * The outcome of checking a result against one or more expected scripts.
 *
 * Reason format, stable for log parsing:
 *
 *     pass: Deva 0.95 >= 0.80 (dominant Deva, 412 letters)
 *     fail: Deva 0.12 < 0.80 (dominant Telu, 412 letters)
 *     too_short: 10 letters < 20 (Deva 1.00, dominant Deva)
 *
 * Several expected scripts are joined with "+" (Deva+Latn). A missing
 * dominant script prints as "none". Numbers have two to four decimals.
 */
final class Check
{
    private Verdict $verdict;
    private float $expectedShare;
    private ?Script $dominant;
    private Result $result;

    /** @var list<Script> */
    private array $expected;
    private float $minShare;
    private int $minLetters;

    /**
     * @param list<Script> $expected
     */
    private function __construct(Result $result, array $expected, float $minShare, int $minLetters)
    {
        $this->result = $result;
        $this->expected = $expected;
        $this->minShare = $minShare;
        $this->minLetters = $minLetters;
        $this->expectedShare = $result->shareOf(...$expected);
        $this->dominant = $result->dominant();
        if ($result->letters() < $minLetters) {
            $this->verdict = Verdict::TooShort;
        } elseif ($this->expectedShare >= $minShare) {
            $this->verdict = Verdict::Pass;
        } else {
            $this->verdict = Verdict::Fail;
        }
    }

    /**
     * Check a result. $expected holds Script cases or ISO codes.
     *
     * @param Script|array<Script|string> $expected
     *
     * @throws InvalidArgumentException on a bad threshold, an empty set or an unknown code.
     */
    public static function of(Result $result, Script|array $expected, float $minShare, int $minLetters = 20): self
    {
        Result::assertShare($minShare);
        Result::assertLetters($minLetters);

        return new self($result, self::normalise($expected), $minShare, $minLetters);
    }

    public function verdict(): Verdict
    {
        return $this->verdict;
    }

    /** True only for Verdict::Pass. */
    public function passed(): bool
    {
        return $this->verdict === Verdict::Pass;
    }

    /** Sum of the shares of the expected scripts. */
    public function expectedShare(): float
    {
        return $this->expectedShare;
    }

    /** Dominant script with default arguments (share 0.5, one letter). */
    public function dominant(): ?Script
    {
        return $this->dominant;
    }

    public function result(): Result
    {
        return $this->result;
    }

    /**
     * @return list<Script>
     */
    public function expected(): array
    {
        return $this->expected;
    }

    /** One line for logs. Holds codes and counts only, never the text. */
    public function reason(): string
    {
        $expected = implode('+', array_map(static fn (Script $s): string => $s->value, $this->expected));
        $share = self::number($this->expectedShare);
        $dominant = $this->dominant->value ?? 'none';
        $letters = $this->result->letters();

        return match ($this->verdict) {
            Verdict::Pass => sprintf(
                'pass: %s %s >= %s (dominant %s, %d letters)',
                $expected,
                $share,
                self::number($this->minShare),
                $dominant,
                $letters,
            ),
            Verdict::Fail => sprintf(
                'fail: %s %s < %s (dominant %s, %d letters)',
                $expected,
                $share,
                self::number($this->minShare),
                $dominant,
                $letters,
            ),
            Verdict::TooShort => sprintf(
                'too_short: %d letters < %d (%s %s, dominant %s)',
                $letters,
                $this->minLetters,
                $expected,
                $share,
                $dominant,
            ),
        };
    }

    /**
     * @return array{verdict: string, passed: bool, expected: list<string>, expected_share: float,
     *     min_share: float, min_letters: int, dominant: ?string, reason: string,
     *     result: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict->value,
            'passed' => $this->passed(),
            'expected' => array_map(static fn (Script $s): string => $s->value, $this->expected),
            'expected_share' => round($this->expectedShare, 4),
            'min_share' => $this->minShare,
            'min_letters' => $this->minLetters,
            'dominant' => $this->dominant?->value,
            'reason' => $this->reason(),
            'result' => $this->result->toArray(),
        ];
    }

    /**
     * @param Script|array<mixed> $expected Validated here, whatever the caller's types say.
     *
     * @return list<Script>
     */
    private static function normalise(Script|array $expected): array
    {
        if ($expected instanceof Script) {
            return [$expected];
        }
        if ($expected === []) {
            throw new InvalidArgumentException('The expected script set must not be empty.');
        }
        $out = [];
        foreach ($expected as $item) {
            if (is_string($item)) {
                $item = Script::fromCode($item);
            } elseif (!$item instanceof Script) {
                throw new InvalidArgumentException('Expected scripts must be Script cases or ISO 15924 codes.');
            }
            $out[$item->value] = $item;
        }

        return array_values($out);
    }

    /**
     * Two to four decimals: 0.8 is "0.80", 0.79987 is "0.7999".
     */
    private static function number(float $x): string
    {
        $s = rtrim(number_format($x, 4, '.', ''), '0');

        return strlen($s) - (int) strpos($s, '.') < 3 ? number_format($x, 2, '.', '') : $s;
    }
}
