<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidUtf8Exception;
use IndianOpinion\ScriptShare\Exception\RegexException;

/**
 * Counts letters per script. Stateless between calls; safe to reuse.
 */
final class Analyser
{
    private Options $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
    }

    public function options(): Options
    {
        return $this->options;
    }

    /**
     * @throws InvalidUtf8Exception in throw mode, on invalid UTF-8.
     * @throws RegexException       when PCRE fails (never reported as zero letters).
     */
    public function analyse(string $text): Result
    {
        $acc = new Accumulator($this->options);
        $acc->add($text);

        return $acc->finish();
    }

    /**
     * A fresh accumulator for chunked input, with this analyser's options.
     */
    public function accumulator(): Accumulator
    {
        return new Accumulator($this->options);
    }
}
