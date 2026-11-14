<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Exception;

/**
 * A PCRE call failed (backtrack limit, JIT stack limit, and so on).
 *
 * The library never turns a failed match into zero counts.
 */
final class RegexException extends \RuntimeException implements ScriptShareException
{
}
