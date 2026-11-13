<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Internal;

use IndianOpinion\ScriptShare\Exception\RegexException;
use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Options;
use IndianOpinion\ScriptShare\Result;

/**
 * Counts letters in valid UTF-8 text, one window at a time.
 *
 * Holds the only state that crosses a window boundary: the script of the
 * last counted code point (for Inherited marks), a skipped token that may
 * continue, and the letter limit.
 *
 * @internal
 */
final class Scanner
{
    private string $pattern;
    private bool $countMarks;
    private ?int $limit;

    /** @var array<string, int> */
    private array $counts = [];
    private int $letters = 0;
    private int $codePoints = 0;
    private int $invalid = 0;
    private bool $truncated = false;
    private bool $stopped = false;

    /** Script code of the last counted code point, while marks may still attach to it. */
    private ?string $context = null;

    /** Kind of skipped token cut by a forced window boundary, or ''. */
    private string $carry = '';

    public function __construct(Options $options, string $pattern)
    {
        $this->pattern = $pattern;
        $this->countMarks = $options->marks === MarkMode::Count;
        $this->limit = $options->maxLetters;
    }

    /**
     * Has the letter limit stopped the scan?
     */
    public function stopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Count one piece of valid UTF-8.
     *
     * @param bool $forcedCut The piece ends at a window cut that is not after whitespace.
     *
     * @throws RegexException when PCRE fails.
     */
    public function scan(string $text, bool $forcedCut): void
    {
        if ($this->stopped || $text === '') {
            return;
        }
        if ($this->carry !== '') {
            $text = $this->continueSkipped($text);
            if ($text === '') {
                return;
            }
        }

        $found = preg_match_all($this->pattern, $text, $m);
        if ($found === false) {
            throw self::regexError();
        }
        /** @var list<string> $tokens */
        $tokens = $m[0];
        /** @var array<int, string> $kinds */
        $kinds = $m['MARK'] ?? [];

        if ($this->limit === null) {
            $this->countAll($tokens, $kinds);
            $this->codePoints += mb_strlen($text, 'UTF-8');
        } else {
            $this->countLimited($tokens, $kinds, $this->limit);
        }

        if ($forcedCut && !$this->stopped) {
            $this->carry = self::openToken($kinds, count($tokens));
        }
    }

    /**
     * Which skipped token, if any, is still open at a forced cut.
     *
     * A link runs to the next whitespace (only trailing punctuation, which
     * is ignored anyway, is left out of it), so a link anywhere after the
     * last whitespace token is still open. A tag ends at its first
     * non-word character, so it is open only as the very last token.
     *
     * @param array<int, string> $kinds
     */
    private static function openToken(array $kinds, int $n): string
    {
        if ($n > 0 && ($kinds[$n - 1] ?? '') === Pattern::TAG) {
            return Pattern::TAG;
        }
        for ($i = $n - 1; $i >= 0; --$i) {
            $kind = $kinds[$i] ?? '';
            if ($kind === Pattern::LINK) {
                return Pattern::LINK;
            }
            if ($kind === Pattern::SPACE) {
                break;
            }
        }

        return '';
    }

    /**
     * Record one maximal invalid subpart (substitute mode).
     */
    public function invalid(): void
    {
        if ($this->stopped) {
            return;
        }
        ++$this->codePoints;
        ++$this->invalid;
        $this->context = null;
        $this->carry = '';
    }

    public function result(): Result
    {
        return Result::fromCounts($this->counts, $this->codePoints, $this->invalid, $this->truncated);
    }

    /**
     * @param list<string>       $tokens
     * @param array<int, string> $kinds
     */
    private function countAll(array $tokens, array $kinds): void
    {
        $context = $this->context;
        foreach ($tokens as $i => $token) {
            $kind = $kinds[$i] ?? Pattern::IGNORED;
            if (strlen($kind) === 4) {
                $len = mb_strlen($token, 'UTF-8');
                $this->counts[$kind] = ($this->counts[$kind] ?? 0) + $len;
                $this->letters += $len;
                $context = $kind;
            } elseif ($kind === Pattern::INHERITED) {
                if ($context !== null) {
                    $len = mb_strlen($token, 'UTF-8');
                    $this->counts[$context] = ($this->counts[$context] ?? 0) + $len;
                    $this->letters += $len;
                }
            } elseif ($kind !== Pattern::BRIDGE) {
                $context = null;
            }
        }
        $this->context = $context;
    }

    /**
     * The same as countAll(), stopping at the first letter past the limit.
     * Marks still attached to the last counted base are counted.
     *
     * @param list<string>       $tokens
     * @param array<int, string> $kinds
     */
    private function countLimited(array $tokens, array $kinds, int $limit): void
    {
        foreach ($tokens as $i => $token) {
            $kind = $kinds[$i] ?? Pattern::IGNORED;
            $len = mb_strlen($token, 'UTF-8');
            if (strlen($kind) === 4) {
                if ($this->letters < $limit) {
                    $room = $limit - $this->letters;
                    if ($len <= $room) {
                        $this->add($kind, $len);
                        $this->codePoints += $len;
                        $this->context = $kind;
                        continue;
                    }
                    $this->add($kind, $room);
                    $rest = mb_substr($token, $room, null, 'UTF-8');
                    $taken = $room;
                } elseif ($this->context !== null) {
                    $rest = $token;
                    $taken = 0;
                } else {
                    $this->stop();

                    return;
                }
                // At the limit: only marks attached to the last base still count.
                $marks = $this->leadingMarks($rest);
                $this->add($kind, $marks);
                $this->codePoints += $taken + $marks;
                if ($taken + $marks < $len) {
                    $this->stop();

                    return;
                }
                $this->context = $kind;
            } elseif ($kind === Pattern::INHERITED) {
                if ($this->context !== null) {
                    $this->add($this->context, $len);
                }
                $this->codePoints += $len;
            } else {
                if ($kind !== Pattern::BRIDGE) {
                    $this->context = null;
                }
                $this->codePoints += $len;
            }
        }
    }

    private function add(string $code, int $n): void
    {
        if ($n === 0) {
            return;
        }
        $this->counts[$code] = ($this->counts[$code] ?? 0) + $n;
        $this->letters += $n;
    }

    private function stop(): void
    {
        $this->stopped = true;
        $this->truncated = true;
    }

    private function leadingMarks(string $text): int
    {
        if (!$this->countMarks || $text === '') {
            return 0;
        }
        if (preg_match(Pattern::leadingMarks(), $text, $m) === false) {
            throw self::regexError();
        }

        return mb_strlen($m[0] ?? '', 'UTF-8');
    }

    /**
     * Consume the rest of a link or tag cut by a forced window boundary.
     */
    private function continueSkipped(string $text): string
    {
        if (preg_match(Pattern::continuation($this->carry), $text, $m) === false) {
            throw self::regexError();
        }
        $skipped = $m[0] ?? '';
        $this->codePoints += mb_strlen($skipped, 'UTF-8');
        $this->context = null;
        if (strlen($skipped) < strlen($text)) {
            $this->carry = '';
        }

        return (string) substr($text, strlen($skipped));
    }

    private static function regexError(): RegexException
    {
        return new RegexException('PCRE match failed: ' . preg_last_error_msg() . '.', preg_last_error());
    }
}
