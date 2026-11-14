<?php

declare(strict_types=1);

namespace IndianOpinion\ScriptShare;

use IndianOpinion\ScriptShare\Exception\InvalidArgumentException;

/**
 * A writing system, backed by its ISO 15924 code.
 *
 * The case order is the tie-break order used everywhere results are sorted.
 */
enum Script: string
{
    case Deva = 'Deva';
    case Beng = 'Beng';
    case Guru = 'Guru';
    case Gujr = 'Gujr';
    case Orya = 'Orya';
    case Taml = 'Taml';
    case Telu = 'Telu';
    case Knda = 'Knda';
    case Mlym = 'Mlym';
    case Sinh = 'Sinh';
    case Olck = 'Olck';
    case Mtei = 'Mtei';
    case Tibt = 'Tibt';
    case Arab = 'Arab';
    case Latn = 'Latn';
    case Cyrl = 'Cyrl';
    case Grek = 'Grek';
    case Hebr = 'Hebr';
    case Thai = 'Thai';
    case Hani = 'Hani';
    case Hira = 'Hira';
    case Kana = 'Kana';
    case Hang = 'Hang';
    case Zzzz = 'Zzzz';

    /**
     * The Unicode Script property value name (as used in Scripts.txt and in
     * PCRE's \p{...}). Zzzz is Unicode's "Unknown".
     */
    public function unicodeName(): string
    {
        return match ($this) {
            self::Deva => 'Devanagari',
            self::Beng => 'Bengali',
            self::Guru => 'Gurmukhi',
            self::Gujr => 'Gujarati',
            self::Orya => 'Oriya',
            self::Taml => 'Tamil',
            self::Telu => 'Telugu',
            self::Knda => 'Kannada',
            self::Mlym => 'Malayalam',
            self::Sinh => 'Sinhala',
            self::Olck => 'Ol_Chiki',
            self::Mtei => 'Meetei_Mayek',
            self::Tibt => 'Tibetan',
            self::Arab => 'Arabic',
            self::Latn => 'Latin',
            self::Cyrl => 'Cyrillic',
            self::Grek => 'Greek',
            self::Hebr => 'Hebrew',
            self::Thai => 'Thai',
            self::Hani => 'Han',
            self::Hira => 'Hiragana',
            self::Kana => 'Katakana',
            self::Hang => 'Hangul',
            self::Zzzz => 'Unknown',
        };
    }

    /**
     * A plain English name for logs and user interfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::Orya => 'Odia',
            self::Olck => 'Ol Chiki',
            self::Mtei => 'Meetei Mayek',
            self::Zzzz => 'Other',
            default => $this->unicodeName(),
        };
    }

    /**
     * Look a script up by its ISO 15924 code, ignoring case.
     *
     * @throws InvalidArgumentException when the code is not one of the cases.
     */
    public static function fromCode(string $code): self
    {
        $normal = ucfirst(strtolower($code));
        $script = self::tryFrom($normal);
        if ($script === null) {
            throw new InvalidArgumentException('Unknown script code: expected an ISO 15924 code such as Deva.');
        }

        return $script;
    }

    /**
     * Every script except Zzzz, in table order.
     *
     * @return list<self>
     */
    public static function named(): array
    {
        $named = [];
        foreach (self::cases() as $script) {
            if ($script !== self::Zzzz) {
                $named[] = $script;
            }
        }

        return $named;
    }
}
