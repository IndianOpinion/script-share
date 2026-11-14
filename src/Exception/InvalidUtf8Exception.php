<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Exception;

/**
 * The input is not valid UTF-8. Carries the byte offset of the first bad
 * sequence and nothing of the input itself.
 */
final class InvalidUtf8Exception extends \UnexpectedValueException implements ScriptShareException
{
    private int $byteOffset;

    public function __construct(int $byteOffset)
    {
        parent::__construct(sprintf('Input is not valid UTF-8: first bad sequence at byte offset %d.', $byteOffset));
        $this->byteOffset = $byteOffset;
    }

    public function byteOffset(): int
    {
        return $this->byteOffset;
    }
}
