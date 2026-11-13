<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

/**
 * What happens when the input is not valid UTF-8.
 */
enum InvalidMode: string
{
    /** Throw InvalidUtf8Exception with the offset of the first bad byte (the default). */
    case Throw = 'throw';

    /** Count each maximal invalid subpart as one ignored code point. */
    case Substitute = 'substitute';
}
