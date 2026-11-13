<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;
use IndianOpinion\ScriptShare\Exception\InvalidUtf8Exception;
use IndianOpinion\ScriptShare\Exception\LogicException;
use IndianOpinion\ScriptShare\Exception\RegexException;
use IndianOpinion\ScriptShare\Internal\Pattern;
use IndianOpinion\ScriptShare\Internal\Scanner;
use IndianOpinion\ScriptShare\Internal\Utf8;

/**
 * Analyses text that arrives in pieces. The result equals analysing the
 * concatenation of every piece.
 *
 * Input is cut into windows of a fixed size. A window ends after its last
 * ASCII whitespace byte, where no token, link or mark attachment can span,
 * so cutting there changes nothing. Window boundaries depend only on the
 * concatenated bytes, never on how they were split into add() calls.
 */
final class Accumulator
{
    public const DEFAULT_WINDOW = 16384;

    private Options $options;
    private Scanner $scanner;
    private int $window;
    private string $buffer = '';

    /** Absolute byte offset of $buffer[0] in the whole input. */
    private int $base = 0;
    private bool $finished = false;

    /**
     * @param int $windowBytes Window size in bytes. Exposed for tests; keep the default.
     *
     * @throws InvalidArgumentException when $windowBytes is below 16.
     */
    public function __construct(?Options $options = null, int $windowBytes = self::DEFAULT_WINDOW)
    {
        if ($windowBytes < 16) {
            throw new InvalidArgumentException('windowBytes must be at least 16.');
        }
        $this->options = $options ?? new Options();
        $this->window = $windowBytes;
        $this->scanner = new Scanner(
            $this->options,
            Pattern::tokens($this->options->marks, $this->options->skipLinks),
        );
    }

    /**
     * Add the next piece. A UTF-8 sequence may be split across pieces.
     *
     * @throws LogicException       after finish().
     * @throws RegexException       when PCRE fails.
     * @throws InvalidUtf8Exception in throw mode, once an invalid sequence is reached (at finish() at the latest).
     */
    public function add(string $chunk): void
    {
        if ($this->finished) {
            throw new LogicException('add() called after finish().');
        }
        if ($chunk === '') {
            return;
        }
        $this->buffer .= $chunk;
        $this->drain(false);
    }

    /**
     * Finish and return the result. A sequence still incomplete here is invalid.
     *
     * @throws LogicException       when called twice.
     * @throws InvalidUtf8Exception in throw mode.
     * @throws RegexException       when PCRE fails.
     */
    public function finish(): Result
    {
        if ($this->finished) {
            throw new LogicException('finish() called twice.');
        }
        $this->finished = true;
        $this->drain(true);

        return $this->scanner->result();
    }

    private function drain(bool $final): void
    {
        $offset = 0;
        $length = strlen($this->buffer);
        while ($offset < $length && ($final || $length - $offset >= $this->window)) {
            if ($length - $offset <= $this->window && $final) {
                $this->processWindow(substr($this->buffer, $offset), $this->base + $offset, false);
                $offset = $length;
                break;
            }
            $win = substr($this->buffer, $offset, $this->window);
            $cut = Utf8::afterLastSpace($win);
            $forced = $cut === 0;
            if ($forced) {
                $cut = Utf8::safeCut($win, $this->window);
            }
            $this->processWindow(substr($win, 0, $cut), $this->base + $offset, $forced);
            $offset += $cut;
        }
        if ($offset > 0) {
            $this->buffer = (string) substr($this->buffer, $offset);
            $this->base += $offset;
        }
    }

    /**
     * Validate one window and hand its valid pieces to the scanner.
     */
    private function processWindow(string $win, int $at, bool $forced): void
    {
        if (mb_check_encoding($win, 'UTF-8')) {
            $this->scanner->scan($win, $forced);

            return;
        }

        $throw = $this->options->invalid === InvalidMode::Throw;
        $n = strlen($win);
        $i = 0;
        $start = 0;
        while ($i < $n) {
            $ascii = strspn($win, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"
                . "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F"
                . " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`"
                . "abcdefghijklmnopqrstuvwxyz{|}~\x7F", $i);
            if ($ascii > 0) {
                $i += $ascii;
                continue;
            }
            [$valid, $len] = Utf8::inspect($win, $i, $n);
            if ($valid) {
                $i += $len;
                continue;
            }
            if ($throw) {
                throw new InvalidUtf8Exception($at + $i);
            }
            $this->scanner->scan(substr($win, $start, $i - $start), false);
            $this->scanner->invalid();
            $i += $len;
            $start = $i;
        }
        $this->scanner->scan(substr($win, $start), $forced);
    }
}
