<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare\Internal;

use IndianOpinion\ScriptShare\MarkMode;
use IndianOpinion\ScriptShare\Script;

/**
 * Builds the single tokenising pattern the scanner runs.
 *
 * Every alternative starts with a (*MARK:name) so the scanner knows which
 * kind of token matched without capture groups. Together the alternatives
 * cover every code point, so the matches are contiguous.
 *
 * @internal
 */
final class Pattern
{
    /** A URL or email address: skipped as one token. */
    public const LINK = 'U';

    /** A #hashtag or @mention: skipped as one token. */
    public const TAG = 'H';

    /** A run of whitespace. Resets the mark context. */
    public const SPACE = 'W';

    /** A run of Inherited combining marks. */
    public const INHERITED = 'N';

    /** Format characters and uncounted marks. Ignored, context kept. */
    public const BRIDGE = 'B';

    /** Anything else that is not counted. Resets the mark context. */
    public const IGNORED = 'I';

    /** Characters a link never ends with (they are left as punctuation). */
    private const TRAILING = '.,;:!?\'"()\[\]{}<>\x{AB}\x{BB}\x{2018}\x{2019}\x{201C}\x{201D}\x{2026}'
        . '\x{0964}\x{0965}\x{06D4}\x{3001}\x{3002}\x{FF0C}';

    private function __construct()
    {
    }

    /**
     * The Unicode Script property prefix for this PCRE build.
     *
     * PCRE2 10.40 changed \p{Devanagari} to mean Script_Extensions. The
     * library wants the Script property on every build, which is spelled
     * \p{sc:Devanagari} from 10.40 and \p{Devanagari} before it.
     */
    public static function scriptPrefix(): string
    {
        if (version_compare(explode(' ', PCRE_VERSION)[0], '10.40', '>=')) {
            return 'sc:';
        }

        return '';
    }

    /**
     * The tokenising pattern for one combination of options.
     */
    public static function tokens(MarkMode $marks, bool $skipLinks): string
    {
        $p = self::scriptPrefix();
        $sc = static fn (string $name): string => '\p{' . $p . $name . '}';
        $notSc = static fn (string $name): string => '\P{' . $p . $name . '}';
        $countMarks = $marks === MarkMode::Count;
        // Counted code points: letters, plus marks when they count. As a
        // negated class: not N, P, S, Z or C (or, ignoring marks, not non-L).
        $notCounted = $countMarks ? '\p{N}\p{P}\p{S}\p{Z}\p{C}' : '\P{L}';

        $alts = [];
        if ($skipLinks) {
            $end = '[^\s' . self::TRAILING . ']';
            $alts[] = '(*MARK:' . self::LINK . ')(?i:[a-z][a-z0-9+.\-]{0,31}:\/\/|www\.)\S*' . $end;
            $alts[] = '(*MARK:' . self::LINK . ')[A-Za-z0-9._%+\-]{1,64}@[^\s@]*[^\s@' . self::TRAILING . ']';
            $alts[] = '(*MARK:' . self::TAG . ')[@#][\p{L}\p{M}\p{N}_]++';
        }
        $alts[] = '(*MARK:' . self::SPACE . ')\s++';

        $named = '';
        foreach (Script::named() as $script) {
            $alts[] = '(*MARK:' . $script->value . ')[^' . $notSc($script->unicodeName()) . $notCounted . ']++';
            $named .= $sc($script->unicodeName());
        }
        $alts[] = '(*MARK:' . Script::Zzzz->value . ')[^' . $notCounted . $sc('Common') . $sc('Inherited')
            . $named . ']++';

        if ($countMarks) {
            $alts[] = '(*MARK:' . self::INHERITED . ')[^\P{M}' . $notSc('Inherited') . ']++';
            $alts[] = '(*MARK:' . self::BRIDGE . ')(?:\p{Cf}|[^\P{M}' . $notSc('Common') . '])++';
        } else {
            $alts[] = '(*MARK:' . self::BRIDGE . ')[\p{M}\p{Cf}]++';
        }

        $plain = $skipLinks ? '[^\p{L}\p{M}\p{Cf}\s@#]' : '[^\p{L}\p{M}\p{Cf}\s]';
        $alts[] = '(*MARK:' . self::IGNORED . ')(?:' . $plain
            . '|[^\P{L}' . $notSc('Common') . ']|[^\P{L}' . $notSc('Inherited') . '])++';
        // Catch-all: guarantees every code point is matched exactly once.
        $alts[] = '(*MARK:' . self::IGNORED . ')[\s\S]';

        return '/(?:' . implode('|', $alts) . ')/u';
    }

    /**
     * What continues a skipped token across a forced window cut.
     */
    public static function continuation(string $kind): string
    {
        return $kind === self::TAG ? '/^[\p{L}\p{M}\p{N}_]*+/u' : '/^\S*+/u';
    }

    /**
     * Leading combining marks of a run.
     */
    public static function leadingMarks(): string
    {
        return '/^\p{M}*+/u';
    }
}
