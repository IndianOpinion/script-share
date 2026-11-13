<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

/**
 * How combining marks (General_Category M) are treated.
 */
enum MarkMode: string
{
    /** Marks count as letters of their script (the default). */
    case Count = 'count';

    /** No mark is counted. Parity with tools that count \p{L} only. */
    case Ignore = 'ignore';
}
