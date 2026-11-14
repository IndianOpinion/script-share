<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

/**
 * Maps a BCP 47 language tag to the scripts its text is expected in.
 *
 * Defaults follow CLDR likely subtags for the languages listed. A script
 * subtag always wins. Region, variant and extension subtags are ignored.
 */
final class LanguageMap
{
    /**
     * Language subtag => expected scripts (ISO 15924 codes, in order).
     *
     * @var array<string, list<string>>
     */
    private const DEFAULTS = [
        // Devanagari
        'hi' => ['Deva'], 'mr' => ['Deva'], 'ne' => ['Deva'], 'sa' => ['Deva'], 'kok' => ['Deva'],
        'mai' => ['Deva'], 'doi' => ['Deva'], 'brx' => ['Deva'], 'bho' => ['Deva'], 'new' => ['Deva'],
        // Other Indic and South Asian scripts
        'bn' => ['Beng'], 'as' => ['Beng'], 'pa' => ['Guru'], 'gu' => ['Gujr'], 'or' => ['Orya'],
        'ta' => ['Taml'], 'te' => ['Telu'], 'kn' => ['Knda'], 'tcy' => ['Knda'], 'ml' => ['Mlym'],
        'si' => ['Sinh'], 'sat' => ['Olck'], 'mni' => ['Mtei', 'Beng'], 'bo' => ['Tibt'], 'dz' => ['Tibt'],
        // Arabic
        'ur' => ['Arab'], 'ks' => ['Arab'], 'sd' => ['Arab'], 'ar' => ['Arab'], 'fa' => ['Arab'],
        'ps' => ['Arab'], 'ug' => ['Arab'], 'ckb' => ['Arab'],
        // Cyrillic
        'ru' => ['Cyrl'], 'uk' => ['Cyrl'], 'be' => ['Cyrl'], 'bg' => ['Cyrl'], 'mk' => ['Cyrl'],
        'sr' => ['Cyrl'], 'kk' => ['Cyrl'], 'ky' => ['Cyrl'], 'mn' => ['Cyrl'], 'tg' => ['Cyrl'],
        'tt' => ['Cyrl'], 'ba' => ['Cyrl'], 'cv' => ['Cyrl'], 'ce' => ['Cyrl'], 'os' => ['Cyrl'],
        // Greek, Hebrew, Thai, East Asian
        'el' => ['Grek'], 'he' => ['Hebr'], 'iw' => ['Hebr'], 'yi' => ['Hebr'], 'th' => ['Thai'],
        'zh' => ['Hani'], 'ja' => ['Hani', 'Hira', 'Kana'], 'ko' => ['Hang', 'Hani'],
    ];

    /**
     * Languages whose CLDR likely script is Latin.
     *
     * @var list<string>
     */
    private const LATIN = [
        'af', 'ak', 'an', 'ast', 'ay', 'az', 'bm', 'br', 'bs', 'ca', 'ceb', 'ch', 'co', 'cs', 'cy', 'da',
        'de', 'dsb', 'ee', 'en', 'eo', 'es', 'et', 'eu', 'ff', 'fi', 'fil', 'fj', 'fo', 'fr', 'fur', 'fy',
        'ga', 'gd', 'gl', 'gn', 'gsw', 'gv', 'ha', 'haw', 'hmn', 'hr', 'hsb', 'ht', 'hu', 'id', 'ig', 'ilo',
        'is', 'it', 'jv', 'kea', 'kg', 'ki', 'kl', 'ku', 'kw', 'la', 'lb', 'lg', 'li', 'ln', 'lt', 'lu',
        'lv', 'mg', 'mh', 'mi', 'ms', 'mt', 'nb', 'nd', 'nl', 'nn', 'no', 'nr', 'nso', 'ny', 'oc', 'om',
        'pap', 'pl', 'pt', 'qu', 'rm', 'rn', 'ro', 'rw', 'sc', 'scn', 'se', 'sg', 'sk', 'sl', 'sm', 'sn',
        'so', 'sq', 'ss', 'st', 'su', 'sv', 'sw', 'tk', 'tl', 'tn', 'to', 'tpi', 'tr', 'ts', 'tw', 'ty',
        'uz', 've', 'vi', 'wa', 'wo', 'xh', 'yo', 'za', 'zu',
    ];

    /**
     * Script subtags that are not one of the Script cases but map onto them.
     *
     * @var array<string, list<string>>
     */
    private const SCRIPT_ALIASES = [
        'hans' => ['Hani'], 'hant' => ['Hani'], 'jpan' => ['Hani', 'Hira', 'Kana'],
        'kore' => ['Hang', 'Hani'], 'hrkt' => ['Hira', 'Kana'], 'aran' => ['Arab'],
        'latf' => ['Latn'], 'latg' => ['Latn'],
    ];

    private function __construct()
    {
    }

    /**
     * The expected scripts for a language tag, or null when the language or
     * its script subtag is not known. Never guesses Latin.
     *
     * @return list<Script>|null
     */
    public static function scriptsFor(string $tag): ?array
    {
        $parts = explode('-', strtolower(str_replace('_', '-', trim($tag))));
        $language = $parts[0];
        if (preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            return null;
        }

        foreach (array_slice($parts, 1) as $i => $subtag) {
            if ($i < 3 && preg_match('/^[a-z]{3}$/', $subtag) === 1) {
                continue; // extended language subtag
            }
            if (preg_match('/^[a-z]{4}$/', $subtag) === 1) {
                return self::forScriptSubtag($subtag);
            }
            break; // region or later: the script subtag can no longer appear
        }

        if (isset(self::DEFAULTS[$language])) {
            return self::scripts(self::DEFAULTS[$language]);
        }
        if (in_array($language, self::LATIN, true)) {
            return [Script::Latn];
        }

        return null;
    }

    /**
     * @return list<Script>|null
     */
    private static function forScriptSubtag(string $subtag): ?array
    {
        if (isset(self::SCRIPT_ALIASES[$subtag])) {
            return self::scripts(self::SCRIPT_ALIASES[$subtag]);
        }
        $script = Script::tryFrom(ucfirst($subtag));

        return $script === null || $script === Script::Zzzz ? null : [$script];
    }

    /**
     * @param list<string> $codes
     *
     * @return list<Script>
     */
    private static function scripts(array $codes): array
    {
        return array_map(static fn (string $code): Script => Script::from($code), $codes);
    }
}
