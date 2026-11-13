# script-share

script-share reports what share of a string's letters fall in each writing
system (Devanagari, Bengali, Tamil, Telugu, Latin and 19 more), with no model
and no network. Use it to catch a language model answering in the wrong
language, a translated title over an untranslated body, or one Indian script
bleeding into another.

Measured: in a plain Hindi news sentence 37% of the Devanagari code points
are combining marks, so a naive `\p{L}` count makes Hindi look a third less
Hindi than it is. script-share counts marks with their script, and analyses
1 MiB of mixed Hindi and English in about 70 ms with under 0.4 MiB of extra
memory (PHP 8.3 with PCRE JIT on, the PHP default; see Limitations for JIT
off).

This project came out of the work on indianopinion.org and is written from
scratch; it is independent of and not affiliated with any third-party plugin
it integrates with.

## Install

With Composer:

```sh
composer require indianopinion/script-share
```

Without Composer, copy the repository (or just `src/`) and load one file:

```php
require __DIR__ . '/script-share/src/bootstrap.php';
```

Requirements: PHP 8.1 or newer, `ext-mbstring`, and PCRE with Unicode
support (both are in every normal PHP build). No `ext-intl`. No runtime
dependencies.

## Quick start

```php
use IndianOpinion\ScriptShare\Script;
use IndianOpinion\ScriptShare\ScriptShare;
use IndianOpinion\ScriptShare\Verdict;

$result = ScriptShare::analyse('RBI ने बुधवार को रेपो दर में कोई बदलाव नहीं किया।');
$result->letters();             // 38
$result->shares();              // ['Deva' => 0.921..., 'Latn' => 0.078...]
$result->dominant();            // Script::Deva

// After a model call: was the reply in Hindi?
$check = ScriptShare::checkLanguage($reply, 'hi', 0.80);
if ($check->verdict() !== Verdict::Pass) {   // fail closed: too_short is a reject too
    error_log($check->reason());             // fail: Deva 0.00 < 0.80 (dominant Telu, 43 letters)
}

// Hindi copy that may name English brands and people:
ScriptShare::isMostly($body, [Script::Deva, Script::Latn], 0.95);
```

More in [`examples/`](examples): `basic.php`, `check-model-output.php`,
`chunked.php`. Each runs with plain `php examples/<file>.php`.

## Configuration

`Options` is immutable. Pass it to `new Analyser($options)` or to
`ScriptShare::analyse($text, $options)`.

| Option | Type | Default | Meaning |
|---|---|---|---|
| `marks` | `MarkMode::Count` or `MarkMode::Ignore` | `Count` | Count combining marks (vowel signs, virama, nukta) as letters of their script. `Ignore` gives parity with tools that count `\p{L}` only. |
| `invalid` | `InvalidMode::Throw` or `InvalidMode::Substitute` | `Throw` | Throw `InvalidUtf8Exception` (with the byte offset) on invalid UTF-8, or count each maximal invalid subpart as one ignored code point. |
| `skipLinks` | bool | `false` | Treat URLs, `www.` links, email addresses, `@mentions` and `#hashtags` as ignored, not as Latin letters. Off by default so counts match the visible text. |
| `maxLetters` | ?int (1 or more) | `null` | Stop after this many counted letters and mark the result `truncated()`. |

```php
$options = new Options(skipLinks: true, maxLetters: 2000);
$options = $options->withMarks(MarkMode::Ignore);   // with*() returns a copy
```

Helper defaults:

| Helper | Default | Note |
|---|---|---|
| `dominant($minShare, $minLetters)` | 0.5, 1 | Null on a tie, on no letters, or under either threshold. |
| `check($text, $expected, $minShare, $minLetters)` | none, 20 | `$minShare` is required on purpose. |

Thresholds that worked in production (guidance, not code defaults):

- Model output that must be in one Indic language: 0.80 of the expected
  script, `minLetters` 20, `too_short` treated as a fail.
- English output: 0.90 Latin, which tolerates a quoted line.
- Indic output that may carry English names: `[script, Latn]` at 0.95, or the
  script alone at 0.70. A typical Hindi news paragraph is 0.90 to 0.98
  Devanagari.
- Turn `skipLinks` on for model output and scraped text, where a long URL
  or a run of handles is Latin that says nothing about the language of the
  prose, and always with `maxLetters`, so a lead made of a URL does not use
  up the sample. Note that it skips a `#हिंदी` hashtag too.
- Ingest gating: check the title and the lead separately. Treat `too_short`
  as a pass (fail open) so a short headline does not drop an item. Let the
  body overrule an English headline only when most of the body's letters are
  in the other script, not when it merely leads a plurality.

## How it works

**What counts.** A code point is counted when it is a letter (`L`) or, in
the default mode, a combining mark (`M`), and its Unicode Script property is
a real script. Digits, punctuation (including the danda), symbols, emoji,
whitespace, controls and format characters are never letters. Letters whose
script is Common or Inherited (mathematical bold, the modifier apostrophe)
are ignored, so styled Unicode text counts as no letters. An Inherited mark
(U+0301, U+0951) counts for the script of the code point before it, if only
marks or format characters (ZWJ, ZWNJ) sit between them.

**Shares** are counts divided by counted letters, never by all characters.
`letters() + ignored() === codePoints()` always holds.

**Scripts** are reported by ISO 15924 code: `Deva Beng Guru Gujr Orya Taml
Telu Knda Mlym Sinh Olck Mtei Tibt Arab Latn Cyrl Grek Hebr Thai Hani Hira
Kana Hang`, plus `Zzzz` for any other real script (Armenian, Georgian,
Ethiopic and so on). Lists are ordered by count, ties in that table order.

**One pass.** A single PCRE pattern tokenises the text into runs (one
alternative per script, plus links, whitespace, Inherited marks and ignored
characters), in windows of 16 KiB cut after whitespace. There is no
per-script rescan, and memory stays flat whatever the input size.

**Links.** With `skipLinks` on, a link starts at `scheme://` or `www.` and
runs to the next whitespace, with trailing punctuation (`. , ; : ! ? ' " )
] } >`, closing quotes, the danda) left out. An email is matched loosely:
up to 64 of `A-Z a-z 0-9 . _ % + -`, an `@`, then to the next whitespace.
`@` or `#` followed by letters, marks, digits or `_` is a mention or hashtag.

**Chunks.** `Analyser::accumulator()` takes text in pieces. A UTF-8 sequence
or a base and its mark may be split across pieces; the result equals
analysing the whole string. `Result::merge()` is for independent texts and
does not carry mark context across.

**Unicode version.** Script data comes from the PCRE library PHP was built
with, so the Unicode version follows your PCRE build: PCRE2 10.39 to 10.42
are Unicode 14, 10.43 and 10.44 are Unicode 15, 10.45 to 10.47 are Unicode
16, and 10.48 is Unicode 17. The PCRE2 bundled with PHP is 10.39 in 8.1,
10.40 in 8.2 and 10.42 in 8.3; distribution builds often link the system
library instead. On PCRE2 10.40 and later the pattern asks for the Script
property explicitly (`\p{sc:Devanagari}`), because from 10.40 a bare
`\p{Devanagari}` means Script_Extensions. Results are identical on any
PCRE2 10.39 or later for every code point assigned in Unicode 14.0; a golden
corpus test checks this in CI. Older PCRE2 releases carry older Unicode data.
Check yours with `php -r 'echo PCRE_VERSION;'`.

### Logging

`Result::toArray()` and `Check::toArray()` give flat, JSON-ready data, and
`Check::reason()` is one stable line:

```
pass: Deva 0.95 >= 0.80 (dominant Deva, 412 letters)
fail: Deva 0.12 < 0.80 (dominant Telu, 412 letters)
too_short: 10 letters < 20 (Deva 1.00, dominant Deva)
```

None of these, and no exception message, contains the input text.

### Errors

Every exception implements `IndianOpinion\ScriptShare\Exception\ScriptShareException`.

| Exception | When |
|---|---|
| `InvalidUtf8Exception` | Invalid UTF-8 in throw mode. `byteOffset()` gives the first bad byte. |
| `InvalidArgumentException` | A threshold outside 0 to 1, NaN, a negative `minLetters`, an empty or unknown expected script, an unknown language in `checkLanguage`. Never clamped. |
| `RegexException` | A PCRE call failed (backtrack or JIT stack limit). Never reported as zero letters. |
| `LogicException` | `add()` or `finish()` after `finish()`. |

## Limitations

- It reports scripts, not languages. Hindi, Marathi, Nepali and Sanskrit all
  look like Devanagari. Romanised Hindi ("aap kaise hain") is 100% Latin.
- Input is plain text. Strip HTML or Markdown first, and strip it from the
  whole text, not just one side of a ratio.
- No normalisation. NFC and NFD give different counts (shares for
  single-script text are the same). Normalise first if you need stable counts.
- Counting is by code point, not grapheme cluster: "क्षि" is 4 in count mode.
- Japanese uses three scripts, and the long-vowel mark (ー) is Common and so
  ignored. Check Japanese against `[Hani, Hira, Kana]` (or the tag `ja`).
- Region subtags are ignored: `pa-PK` maps to Gurmukhi. Use `pa-Arab` for
  Shahmukhi.
- Text is scanned in 16 KiB windows cut after whitespace. In a run of over
  16 KiB with no ASCII whitespace at all the cut falls inside the run; with
  `skipLinks` on it moves back before any ASCII link, email, mention or
  hashtag prefix that ends at it. Only a link that starts inside such a run
  of more than 256 ASCII letters, digits and `. _ % + - : / @ #` can still
  be split and counted as letters.
- The speed budgets (1 KiB under 0.2 ms, 1 MiB under 150 ms) hold with PCRE
  JIT on, the PHP default. With `pcre.jit=0` the 1 MiB case takes about
  200 ms and the 1 KiB case about 0.2 ms. Memory stays flat either way.

## FAQ

**Why are vowel signs counted as letters?** Because they are a third of
Indic text. Without them, an English sentence quoting a short Hindi line
shows Hindi at 0.19 instead of 0.27, and every Indic threshold must be set
oddly low.

**Why is there no default `minShare` for `check`?** Every caller's tolerance
for quoted English or a brand name differs. The library reports; you decide.

**What should I do with `too_short`?** Fail closed on model output (a
two-word reply is not a good translation), open on ingest.

**Why does an empty string have no dominant script, not Latin?** "No letters"
is its own answer. Defaulting to English let broken replies pass an English
check.

**Can I get a code for Armenian or Georgian?** Not in 0.1. They are grouped
as `Zzzz` so the API stays a closed enum. Open an issue if you need one.

## Development

```sh
composer install
composer check        # phpcs, phpstan level 8, phpunit
php bin/golden.php    # golden corpus up to date
php bench/bench.php   # timings
```

See [CONTRIBUTING.md](CONTRIBUTING.md) and
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Licence

MIT. See [LICENSE](LICENSE).
