<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;

/**
 * Immutable analysis options. Use named arguments or the with*() methods.
 */
final class Options
{
    public readonly MarkMode $marks;
    public readonly InvalidMode $invalid;
    public readonly bool $skipLinks;
    public readonly ?int $maxLetters;

    /**
     * @param MarkMode    $marks      Count combining marks with their script, or ignore them.
     * @param InvalidMode $invalid    Throw on invalid UTF-8, or substitute.
     * @param bool        $skipLinks  Treat URLs, emails, @mentions and #hashtags as ignored.
     * @param int|null    $maxLetters Stop after this many counted letters (null: no limit).
     *
     * @throws InvalidArgumentException when $maxLetters is below 1.
     */
    public function __construct(
        MarkMode $marks = MarkMode::Count,
        InvalidMode $invalid = InvalidMode::Throw,
        bool $skipLinks = true,
        ?int $maxLetters = null,
    ) {
        if ($maxLetters !== null && $maxLetters < 1) {
            throw new InvalidArgumentException('maxLetters must be null or at least 1.');
        }
        $this->marks = $marks;
        $this->invalid = $invalid;
        $this->skipLinks = $skipLinks;
        $this->maxLetters = $maxLetters;
    }

    public function withMarks(MarkMode $marks): self
    {
        return new self($marks, $this->invalid, $this->skipLinks, $this->maxLetters);
    }

    public function withInvalid(InvalidMode $invalid): self
    {
        return new self($this->marks, $invalid, $this->skipLinks, $this->maxLetters);
    }

    public function withSkipLinks(bool $skipLinks): self
    {
        return new self($this->marks, $this->invalid, $skipLinks, $this->maxLetters);
    }

    public function withMaxLetters(?int $maxLetters): self
    {
        return new self($this->marks, $this->invalid, $this->skipLinks, $maxLetters);
    }
}
