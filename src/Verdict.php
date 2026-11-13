<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

/**
 * The outcome of a threshold check.
 */
enum Verdict: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case TooShort = 'too_short';
}
