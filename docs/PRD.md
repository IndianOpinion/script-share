# script-share: product requirements

Status: draft for review. Version target: 0.1.0.
Package: `indianopinion/script-share`. Namespace: `IndianOpinion\ScriptShare`.

## 1. Problem

A news pipeline asks a language model to write in English, or in Hindi, or
in Tamil. Sometimes the model answers in another language. The usual cause
is a prompt that says "write in the language of the source", fed a Telugu
or Kannada source. The reply is fluent, well formed and wrong, and nothing
downstream notices until a reader does. The same failure shows up as a
translated headline over an untranslated body, and as one Indian script
bleeding into text meant to be in another.

The cheap test is to look at the writing system. Devanagari, Bengali,
Tamil, Telugu and the rest each have their own Unicode script, so the share
of letters in each script tells you, with no model and no network, whether
the reply is in the expected script.

The naive version of that test is easy to get wrong, and we got it wrong in
three ways before this library:

- Dividing by every character instead of by letters. Spaces, digits and
  punctuation dilute the share. Measured on a plain Hindi news sentence:
  the Devanagari share over all characters was 0.70, so the threshold had
  to be set absurdly low (0.15) and then misfired on mixed text.
- Counting only "letter" code points. Indic vowel signs and the virama are
  combining marks, not letters. Measured on the same sentence: 37% of the
  Devanagari code points were marks. Ignoring them makes every Indic text
  look about a third less Indic than it is next to Latin. On an English
  sentence quoting a short Hindi line, the Hindi share was 0.19 without
  marks and 0.27 with them.
- Treating a regex failure as zero. PHP's PCRE functions return `false` on
  invalid UTF-8. Cast to a number, that is 0 letters in every script, so a
  broken reply was reported as "English" and passed.

script-share does the counting once, correctly, and leaves the policy
(thresholds, what to do with short text) to the caller.

## 2. Goals and non-goals

Goals:

1. Report, for any string, how many letters fall in each writing system and
   what share of all letters that is.
2. Define "letter" precisely and the same way on every supported PHP build:
   which code points count, how combining marks count, and what is ignored.
3. Give two helpers on top: the dominant script, and a pass or fail check
   against an expected script with caller-supplied thresholds.
4. Map a language tag (`hi`, `ta`, `pa-Arab`) to its expected script or
   scripts, so a caller can go from "requested language" to a check.
5. Be fast and flat in memory on long strings, and accept text in chunks.
6. Never fail silently on invalid UTF-8.
7. Zero runtime dependencies beyond `ext-mbstring` and PCRE with Unicode
   support (both in every normal PHP build). No `ext-intl` requirement.

Non-goals:

- Language identification. The library reports scripts, not languages.
  Hindi, Marathi, Nepali and Sanskrit all use Devanagari and look the same
  to it. Romanised Hindi is Latin. Say so in the README.
- HTML or Markdown parsing. Input is plain text. The caller strips markup
  first (see rule 14.4).
- Unicode normalisation. The library counts what it is given.
- Transliteration, translation, or any network call.
- Grapheme cluster segmentation. Counting is by code point (see 4.2).
- A WordPress plugin. This is a plain Composer library.

## 3. Users and setting

- Engineers running LLM pipelines in PHP who need a guard after a model
  call: "did it answer in Tamil?". Typical inputs are a title (5 to 20
  words) and a body (a few hundred to a few thousand words).
- Ingest code that decides whether a feed item is in a language the site
  publishes, before spending a model call on it.
- QA scripts that sweep stored posts for script bleed.

It runs anywhere PHP 8.1+ runs: web requests, CLI, cron, queue workers. It
holds no state between calls, writes nothing and reads no files at runtime
except its own bundled data (if the builder chooses a data table, see 13.1).

## 4. Behaviour

### 4.1 What a letter is

4.1.1. A code point is a **letter** when its Unicode General_Category is
any of Lu, Ll, Lt, Lm, Lo (the `L` group) and its Unicode Script property is
a real script (not Common, not Inherited).

4.1.2. A code point is a **mark** when its General_Category is Mn, Mc or Me
(the `M` group).

4.1.3. In the default mode (`marks: count`) a mark is counted as a letter
of a script when:
  a. its own Script property is a real script (for example a Devanagari
     vowel sign, virama or nukta, a Tamil pulli, a Bengali vowel sign). It
     counts towards that script, whatever precedes it; or
  b. its Script property is Inherited (for example U+0301 combining acute,
     U+0951 Devanagari stress sign udatta, which Unicode files as
     Inherited). It counts towards the script of the immediately preceding
     counted code point, provided only other marks or format characters
     (U+200C, U+200D) sit between them. Otherwise it is ignored.

4.1.4. In `marks: ignore` mode no mark is counted. This exists for callers
who want parity with tools that count `\p{L}` only. It is not the default
because of the 37% undercount in section 1.

4.1.5. A letter whose Script is Common or Inherited (for example
mathematical alphanumeric symbols U+1D400 onwards, or U+02BC modifier
letter apostrophe) is ignored. Styled "bold" Unicode text therefore counts
as no letters at all.

4.1.6. Fullwidth Latin letters (U+FF21 to U+FF5A) are Latin and count.

4.1.7. Digits are never letters, whatever their script. ASCII 0 to 9,
Devanagari digits U+0966 to U+096F, Tamil digits, Bengali digits and all
other `Nd`, `Nl` and `No` code points are ignored. A reply that is only
numbers has no letters.

4.1.8. Punctuation and symbols are never letters. That includes the danda
U+0964 and double danda U+0965 (shared by many Indic scripts), the Urdu
full stop U+06D4, quotation marks, currency signs such as U+20B9 rupee,
emoji and all `P`, `S`, `Z` and `C` categories.

4.1.9. Format characters (U+200C zero width non-joiner, U+200D zero width
joiner, U+FEFF byte order mark, U+00AD soft hyphen) are not letters. They do
not break the link between a base and a following Inherited mark.

4.1.10. Whitespace and control characters are ignored. U+0000 is valid
UTF-8 and is ignored, not an error.

4.1.11. Every code point that is not counted increments a single
`ignored` total, so `letters + ignored = codePoints` always holds.

### 4.2 Counting unit

4.2.1. The unit is the Unicode code point, not the byte and not the
grapheme cluster. "क्षि" is क, ्, ष, ि: two letters and two marks. It
counts as 4 in `count` mode and 2 in `ignore` mode.

4.2.2. No normalisation is applied. NFC and NFD forms of the same text can
give different counts (é is one letter in NFC, one letter plus one
Inherited mark in NFD). Shares for single-script text are identical in both
forms. Callers who need stable counts normalise first.

### 4.3 Scripts reported

4.3.1. Counted letters are attributed to one of these scripts, named by
their ISO 15924 code (the public value) and Unicode script name:

| Code | Unicode name | Typical languages |
|---|---|---|
| `Deva` | Devanagari | Hindi, Marathi, Nepali, Sanskrit, Konkani, Maithili, Dogri, Bodo |
| `Beng` | Bengali | Bengali, Assamese, Manipuri (one of its scripts) |
| `Guru` | Gurmukhi | Punjabi (India) |
| `Gujr` | Gujarati | Gujarati |
| `Orya` | Oriya | Odia |
| `Taml` | Tamil | Tamil |
| `Telu` | Telugu | Telugu |
| `Knda` | Kannada | Kannada, Tulu (commonly) |
| `Mlym` | Malayalam | Malayalam |
| `Sinh` | Sinhala | Sinhala |
| `Olck` | Ol_Chiki | Santali |
| `Mtei` | Meetei_Mayek | Manipuri |
| `Tibt` | Tibetan | Tibetan, Ladakhi, Sikkimese |
| `Arab` | Arabic | Urdu, Kashmiri, Sindhi, Arabic, Persian, Punjabi (Shahmukhi) |
| `Latn` | Latin | English and every romanised language |
| `Cyrl` | Cyrillic | Russian and others |
| `Grek` | Greek | Greek |
| `Hebr` | Hebrew | Hebrew |
| `Thai` | Thai | Thai |
| `Hani` | Han | Chinese, Japanese kanji |
| `Hira` | Hiragana | Japanese |
| `Kana` | Katakana | Japanese |
| `Hang` | Hangul | Korean |
| `Zzzz` | (other) | any letter in a real script not listed above |

4.3.2. Attribution uses the Unicode Script property of the code point, not
block ranges. A Devanagari block range happens to include digits, the danda
and marks; the Script property plus 4.1 handles each correctly. Code points
in Arabic Supplement, Arabic Extended-A and Arabic Presentation Forms count
as `Arab`. Latin Extended blocks count as `Latn`.

4.3.3. `Zzzz` collects every other real script (Armenian, Georgian,
Ethiopic, Thaana, and so on) so the shares still sum to 1.

### 4.4 Results

4.4.1. Analysing a string gives a result with: total code points, letters,
ignored, invalid sequences (0 unless substitution mode, see 4.8), a count
per script, and a share per script (count / letters).

4.4.2. Only scripts with at least one letter appear in the per-script
lists. Asking for any other script returns count 0 and share 0.0.

4.4.3. When `letters` is 0 (empty string, only digits, only punctuation,
only whitespace, only emoji), every share is 0.0, the share list is empty
and there is no dominant script. No division by zero, no NaN.

4.4.4. When `letters > 0`, the shares sum to 1.0 within 1e-9.

4.4.5. The per-script lists are ordered by count, highest first. Ties are
ordered by the table order in 4.3.1, so output is deterministic.

4.4.6. A result is immutable and can be turned into a plain array (for
logs and JSON) holding the counts and the shares rounded to four decimals.
The unrounded shares stay available through the accessors.

### 4.5 Mixed-script text

4.5.1. An English sentence quoting a Hindi line reports both scripts in
proportion to their letters. Nothing is rounded away.

4.5.2. A Hindi article naming brands, people or acronyms in Latin ("RBI",
"iPhone") reports a Latin share. A typical Hindi news paragraph lands
around 0.90 to 0.98 Devanagari. Thresholds must allow for it.

4.5.3. Romanised Hindi ("aap kaise hain") is 100% Latin. This is a stated
limit, not a bug.

4.5.4. URLs, email addresses and @handles are Latin letters and count as
Latin by default. With the `skipLinks` option on, substrings that match
`scheme://...` up to the next whitespace, `www.` up to the next whitespace,
an email address, or `@` followed by word characters, are counted as
ignored instead. Default is off so counts match the visible text.

4.5.5. Script bleed inside one word (a Tamil word with a stray Malayalam
vowel sign, a common model glitch) is visible: the stray mark is counted
under its own script by 4.1.3a.

4.5.6. Japanese text gives several scripts (Han, Hiragana, Katakana). The
check helper accepts a set of scripts for this (4.7.3).

### 4.6 Dominant script helper

4.6.1. Returns the script with the most letters, provided:
  a. `letters >= minLetters` (default 1), and
  b. its share is `>= minShare` (default 0.5), and
  c. it has strictly more letters than the runner-up.
Otherwise it returns null.

4.6.2. Exact ties for first place return null, never the first in some
list. (Our earlier code silently returned whichever script was tested
first.)

4.6.3. With `minShare` 0 the helper returns the plurality script, still
null on a tie or on no letters.

4.6.4. The dominant script of a string with no letters is null, never a
default such as Latin. (Our earlier code defaulted to English, so empty or
broken replies "passed" an English check.)

### 4.7 Threshold check helper

4.7.1. Inputs: text (or an existing result), one expected script or a set
of them, `minShare` (required, 0.0 to 1.0), `minLetters` (default 20).

4.7.2. The expected share is the sum of the shares of the expected
scripts.

4.7.3. A set of scripts lets callers allow combinations: `[Deva, Latn]` for
Hindi copy that legitimately carries English names, or
`[Hani, Hira, Kana]` for Japanese.

4.7.4. The verdict is one of three states:
  - `pass`: `letters >= minLetters` and expected share `>= minShare`;
  - `fail`: `letters >= minLetters` and expected share `< minShare`;
  - `too_short`: `letters < minLetters`. The share is still reported.
The library does not decide whether `too_short` passes. The caller does,
and the README says to fail closed on model output and open on ingest.

4.7.5. The verdict carries the expected share, the dominant script (by
4.6 with default arguments), the full result, and a short reason string for
logs, for example `fail: Deva 0.12 < 0.80 (dominant Telu, 412 letters)`.

4.7.6. `minShare` outside 0.0 to 1.0, NaN or infinite, `minLetters`
negative, an empty expected set, or an unknown script code throws an
invalid argument exception. It never clamps.

4.7.7. A boolean shortcut returns true only for `pass`.

### 4.8 Invalid UTF-8

4.8.1. Default mode (`invalid: throw`): if the input is not valid UTF-8,
throw an invalid UTF-8 exception carrying the byte offset of the first bad
sequence. No partial result.

4.8.2. Invalid means anything PHP's `mb_check_encoding($s, 'UTF-8')`
rejects: stray continuation bytes, truncated sequences, overlong forms,
encoded surrogates U+D800 to U+DFFF, values above U+10FFFF, bytes F5 to FF.

4.8.3. Substitute mode (`invalid: substitute`): each maximal invalid
subpart (as defined by the Unicode "substitution of maximal subparts"
practice) counts as one code point that is ignored, and increments
`invalidSequences`. Valid text around it is counted normally. An Inherited
mark straight after an invalid sequence is ignored.

4.8.4. The library never returns a result with silently zero letters
because a PCRE call failed. Any PCRE error (`preg_last_error()` not
`PREG_NO_ERROR`), including backtrack or JIT stack limits, throws a runtime
exception with the PCRE error message.

4.8.5. Input that is valid UTF-8 but not text (binary that happens to
validate) is analysed like text. Callers who care check letters or the
ignored ratio.

### 4.9 Long strings and chunked input

4.9.1. Analysis is a single forward pass. Cost is linear in input length.

4.9.2. `maxLetters` option (default null, meaning no limit): stop after
that many counted letters (in `count` mode, counted marks are letters for
this purpose) and mark the result as truncated. This replaces
"look at the first N characters": a lead made of a long URL or a table of
numbers no longer starves the sample. Truncation never splits a code point,
and a trailing run of marks belonging to the last counted base is still
counted (so the limit may be exceeded by those marks).

4.9.3. Chunked analysis: an accumulator accepts strings in order and
yields the same result as analysing their concatenation. A UTF-8 sequence
split across two chunks is joined, not reported invalid. An Inherited mark
at the start of a chunk attaches to the last base of the previous chunk.
A sequence still incomplete when the accumulator is finished is invalid
(throw or substitute per mode).

4.9.4. Two results can be merged (counts add). Merging is for independent
texts. It does not carry Inherited-mark context across, so merge of
analyses is not exactly equal to analysis of a concatenation at a boundary
that starts with an Inherited mark. The README says so.

### 4.10 Language to script mapping

4.10.1. A helper maps a BCP 47 language tag to its expected set of
scripts. Case and `_` versus `-` do not matter.

4.10.2. An explicit script subtag wins: `pa-Arab` is `Arab`, `sd-Deva` is
`Deva`, `sr-Latn` is `Latn`, `hi-Latn` is `Latn`.

4.10.3. Without a subtag, the default script is used: `hi mr ne sa kok mai
doi brx` to `Deva`; `bn as` to `Beng`; `pa` to `Guru`; `gu` to `Gujr`;
`or` to `Orya`; `ta` to `Taml`; `te` to `Telu`; `kn tcy` to `Knda`; `ml` to
`Mlym`; `si` to `Sinh`; `sat` to `Olck`; `mni` to `Mtei` and `Beng`; `ur ks
sd ar fa` to `Arab`; `en fr de es pt it nl` and every other language whose
default script is Latin to `Latn` (the builder takes the list from CLDR
likely-subtags data and records it in ARCHITECTURE.md); `ru uk` to `Cyrl`; `el` to `Grek`; `he` to `Hebr`;
`th` to `Thai`; `zh` to `Hani`; `ja` to `Hani Hira Kana`; `ko` to `Hang` and
`Hani`.

4.10.4. An unknown language returns null. It does not guess Latin.

4.10.5. Region subtags are ignored (`en-IN`, `hi-IN`).

## 5. Public interface

Names below are the proposed public API. The builder may adjust spelling
but not shape, and records any change in ARCHITECTURE.md.

`enum Script: string` with the cases in 4.3.1, backed by the ISO 15924
code. Methods: `unicodeName(): string`, `label(): string` (English name),
`static fromCode(string $code): self` (case-insensitive, throws on unknown).

`final class Options` (immutable, named constructor arguments):

| Option | Type | Default | Meaning |
|---|---|---|---|
| `marks` | `MarkMode` enum: `Count`, `Ignore` | `Count` | 4.1.3, 4.1.4 |
| `invalid` | `InvalidMode` enum: `Throw`, `Substitute` | `Throw` | 4.8 |
| `skipLinks` | bool | false | 4.5.4 |
| `maxLetters` | ?int (>= 1) | null | 4.9.2 |

Methods `withMarks()`, `withInvalid()`, `withSkipLinks()`,
`withMaxLetters()` return copies.

`final class Analyser`:
- `__construct(?Options $options = null)`
- `analyse(string $text): Result`
- `accumulator(): Accumulator`

`final class Accumulator`: `add(string $chunk): void`, `finish(): Result`.
`add` after `finish` throws a logic exception.

`final class Result` (immutable, `JsonSerializable`):
- `codePoints(): int`, `letters(): int`, `ignored(): int`,
  `invalidSequences(): int`, `truncated(): bool`
- `count(Script $s): int`, `share(Script $s): float`
- `shareOf(Script ...$s): float` (sum)
- `counts(): array<string,int>` and `shares(): array<string,float>`, keyed
  by ISO code, ordered per 4.4.5
- `dominant(float $minShare = 0.5, int $minLetters = 1): ?Script`
- `isEmpty(): bool` (letters is 0)
- `merge(Result $other): Result`
- `toArray(): array` and `jsonSerialize()`

`enum Verdict: string` with `Pass = 'pass'`, `Fail = 'fail'`,
`TooShort = 'too_short'`.

`final class Check` (the check outcome): `verdict(): Verdict`,
`passed(): bool`, `expectedShare(): float`, `dominant(): ?Script`,
`result(): Result`, `reason(): string`, `toArray(): array`.

`final class ScriptShare` (static façade, default options):
- `analyse(string $text, ?Options $o = null): Result`
- `shares(string $text): array<string,float>`
- `dominant(string $text, float $minShare = 0.5, int $minLetters = 1): ?Script`
- `check(string|Result $text, Script|array $expected, float $minShare, int $minLetters = 20): Check`
- `isMostly(string $text, Script|array $expected, float $minShare, int $minLetters = 20): bool`
- `scriptsFor(string $languageTag): ?array` (list of `Script`, 4.10)
- `checkLanguage(string|Result $text, string $languageTag, float $minShare, int $minLetters = 20): Check`
  (throws invalid argument on an unknown tag)

Exceptions, all implementing a marker interface `ScriptShareException`:
- `InvalidUtf8Exception` (extends `\UnexpectedValueException`), with
  `byteOffset(): int`
- `InvalidArgumentException` (extends `\InvalidArgumentException`)
- `RegexException` (extends `\RuntimeException`) for 4.8.4
- `LogicException` for misuse of the accumulator

A bootstrap file `src/bootstrap.php` (or `autoload.php`) lets non-Composer
users load everything with one `require`.

## 6. Data

Nothing is stored. No files, caches, globals, options or static mutable
state. If the builder ships a generated Unicode script table (13.1), it is
a read-only PHP file under `src/` or `data/`, built by a script in
`bin/` from a pinned Unicode version, and committed. No uninstall step.

## 7. Configuration and sensible defaults

- Marks counted. Invalid UTF-8 throws. Links counted. No letter limit.
- `dominant`: `minShare` 0.5, `minLetters` 1.
- `check`: `minShare` has no default on purpose; `minLetters` 20.
- README guidance (not code defaults), from production:
  - model output that must be in one Indic language: 0.80 of the expected
    script, `minLetters` 20, `too_short` treated as fail;
  - English output: 0.90 Latin, which tolerates a quoted line;
  - Indic output that may carry English names: expected set
    `[script, Latn]` at 0.95, or the script alone at 0.70;
  - ingest gating: check title and lead separately (rule 14.6).

## 8. Security and privacy

- Pure function of its input. No I/O, network, shell, `eval`, or `@`.
- Regular expressions must be linear (no nested quantifiers on
  user-controlled input). PCRE limit errors throw (4.8.4) rather than
  returning a wrong answer.
- Input may be untrusted model output or scraped text. The library never
  echoes it into exceptions beyond the byte offset; reasons and arrays hold
  counts and codes only, so logs do not leak content.
- No dependency on locale (`setlocale`) or `mbstring.func_overload`.

## 9. Performance budget

Measured on PHP 8.3, one core, JIT off:

- 1 KiB of text: under 0.2 ms.
- 1 MiB of mixed Devanagari and Latin: under 150 ms.
- Extra peak memory for 1 MiB input under 4 MiB. In particular the builder
  must not split the whole input into an array of one-character strings
  (that costs tens of bytes per code point). Process in bounded chunks or
  with offset-based matching.
- Linear scaling: 8 MiB takes no more than 10 times 1 MiB.
- One pass. No per-script rescans of the text. (Our earlier code ran one
  regex per script plus a length count: eleven passes.)

A `bench/` script prints timings; CI does not fail on it.

## 10. Compatibility

- PHP 8.1, 8.2, 8.3 (CI matrix). No 8.2+ syntax.
- Requires `ext-mbstring` and PCRE with UTF-8 support (`/u`).
- Unicode version: the PCRE library bundled with PHP differs by version
  (8.1 builds can be on Unicode 14, 8.3 on 15). Results must be identical
  across the matrix for every code point assigned in Unicode 14.0. Newer
  code points may differ by PHP build unless the builder ships its own
  table (13.1). The README states which Unicode version applies.
- No WordPress, framework or extension dependency. Works in CLI and FPM.

## 11. Observability

- `Result::toArray()` and `Check::toArray()` give flat, JSON-ready data:
  counts, rounded shares, letters, ignored, invalid, truncated, verdict,
  dominant, reason. That is what a caller logs.
- `Check::reason()` is one line, stable in format, safe to log.
- The library itself logs nothing.

## 12. Acceptance tests

Each line is one PHPUnit test (data providers allowed). Section numbers map
to section 4.

1. (4.1.1) "Hello" gives 5 letters, all `Latn`, share 1.0.
2. (4.1.2, 4.1.3a) "किताब" in `count` mode gives 5 `Deva` letters; in
   `ignore` mode 3.
3. (4.1.3b) "e" plus U+0301 counts 2 `Latn`; "क" plus U+0951 counts 2
   `Deva`.
4. (4.1.3b) U+0301 at the start of a string, and after a space, is
   ignored.
5. (4.1.3b, 4.1.9) "क" plus U+200D plus U+0951 counts the mark as `Deva`.
6. (4.1.3a) A Devanagari vowel sign after a Latin letter counts `Deva`.
7. (4.1.4) A Tamil sentence in `ignore` mode equals the `\p{L}` count of
   that sentence.
8. (4.1.5) Mathematical bold "𝐇𝐞𝐥𝐥𝐨" gives 0 letters.
9. (4.1.6) Fullwidth "ＡＢＣ" gives 3 `Latn`.
10. (4.1.7) "२०२६ 2026 ௨௦" gives 0 letters and 12 code points ignored.
11. (4.1.8) "।॥۔₹!?😀" gives 0 letters.
12. (4.1.10) A string containing U+0000 is valid and the NUL is ignored.
13. (4.1.11) For a random sample of mixed strings, letters plus ignored
    equals `mb_strlen`.
14. (4.2.1) "क्षि" gives 4 in `count` mode, 2 in `ignore` mode.
15. (4.2.2) NFC and NFD of "café" both give `Latn` share 1.0; counts
    are 4 and 5.
16. (4.3.1) One pangram or common word per listed script is attributed to
    the right code (data provider over all 23 scripts).
17. (4.3.2) An Arabic Presentation Forms letter counts `Arab`; a Latin
    Extended-B letter counts `Latn`.
18. (4.3.3) Armenian and Georgian letters count `Zzzz`.
19. (4.4.3) Empty string, "123", "...", whitespace only and emoji only
    all give letters 0, empty shares, share 0.0 for any script, no
    dominant, no warning or notice.
20. (4.4.4) Shares sum to 1.0 within 1e-9 over a provider of mixed strings.
21. (4.4.5) Equal counts of `Latn` and `Deva` list `Deva` first (table
    order); order is stable across runs.
22. (4.4.6) `toArray()` rounds to four decimals; accessors do not.
23. (4.5.1) An English sentence quoting a ten-letter Hindi line reports
    both scripts with the exact expected counts.
24. (4.5.3) "aap kaise hain" is 100% `Latn`.
25. (4.5.4) With `skipLinks` on, a Hindi sentence with a long URL keeps
    `Deva` share 1.0; with it off the URL counts as `Latn`.
26. (4.5.5) A Tamil word with one stray Malayalam vowel sign shows a
    `Mlym` count of 1.
27. (4.5.6, 4.7.3) A Japanese sentence passes `check` against
    `[Hani, Hira, Kana]` at 0.95.
28. (4.6.1) `dominant` returns `Telu` on Telugu text; null below
    `minLetters`; null when the top share is under `minShare`.
29. (4.6.2) A 50/50 `Latn` and `Deva` string gives null at any
    `minShare`, including 0.
30. (4.6.3) `minShare` 0 returns the plurality script of a three-way mix.
31. (4.6.4) `dominant('')` and `dominant('2026')` are null.
32. (4.7.4) A Telugu reply checked against `Deva` at 0.8 gives `fail` with
    dominant `Telu`; a Hindi reply gives `pass`; a 10-letter Hindi reply
    gives `too_short` with its share reported.
33. (4.7.2, 4.7.3) Hindi text with English names fails `Deva` at 0.99
    and passes `[Deva, Latn]` at 0.99.
34. (4.7.5) `reason()` matches the documented format for pass, fail and
    too_short.
35. (4.7.6) `minShare` of -0.1, 1.1, NAN, INF, `minLetters` of -1, an empty
    expected array, and the code "Xxxx" each throw the library's invalid
    argument exception.
36. (4.7.7) `isMostly` is false for `too_short`.
37. (4.8.1) "abc\xC3" throws `InvalidUtf8Exception` with offset 3;
    "\x80abc" with offset 0.
38. (4.8.2) Overlong "\xC0\xAF", surrogate "\xED\xA0\x80", "\xF4\x90\x80\x80"
    and "\xFF" each throw.
39. (4.8.3) In substitute mode "ab\xFFcd" gives 4 `Latn` letters and 1
    invalid sequence; "\xE0\xA4" truncated gives 1 invalid sequence;
    "e\xFF" plus U+0301 leaves the mark ignored.
40. (4.8.4) A forced PCRE failure (test seam or a stubbed matcher) throws
    `RegexException`, never returns zero counts.
41. (4.9.2) `maxLetters` 10 on a long Hindi text gives 10 or slightly more
    letters (trailing marks), `truncated()` true; a short text gives
    `truncated()` false.
42. (4.9.3) Feeding a Hindi string to the accumulator split at every byte
    offset gives a result equal to `analyse` of the whole; a split before
    an Inherited mark attaches it correctly; an unfinished sequence at
    `finish` throws (or substitutes in that mode).
43. (4.9.3) `add` after `finish` throws `LogicException`.
44. (4.9.4) `merge` of two results adds counts and recomputes shares.
45. (4.10.2) `scriptsFor('pa-Arab')` is `[Arab]`, `hi-Latn` is `[Latn]`.
46. (4.10.3) Every mapping in 4.10.3 holds (data provider); `HI_in` and
    `hi-IN` give `[Deva]`.
47. (4.10.4) `scriptsFor('xx')` is null and `checkLanguage` with `xx`
    throws.
48. (9) A 1 MiB generated mixed string is analysed without exceeding the
    memory budget (asserted with `memory_get_peak_usage` deltas, generous
    margin); timing is reported by the bench script, not asserted.
49. (4.4.1, 4.4.2) A result for mixed text reports totals, per-script counts
    and shares; scripts with no letters are absent from the lists.
50. (4.8.5) Valid UTF-8 made of control characters only is analysed without
    error and gives 0 letters.
51. (4.9.1) Doubling the input length roughly doubles the work (covered by
    the memory test and the bench script, not a timing assertion).
52. (4.10.1, 4.10.5) `scriptsFor('en-IN')`, `scriptsFor('EN')` and
    `scriptsFor('hi_IN')` give the same answers as the bare tags.
53. (10) The golden file test: a fixed corpus of a few hundred lines in all
    listed scripts produces committed expected counts identical on 8.1,
    8.2 and 8.3.

## 13. Open questions (the builder answers in ARCHITECTURE.md)

1. Script lookup: PCRE script properties (`\p{Devanagari}` and friends)
   at runtime, or a generated range table from Unicode `Scripts.txt` and
   `DerivedGeneralCategory.txt`? The table gives the same answers on every
   PHP build and makes test 49 trivially stable; PCRE is less code. Pick
   one and justify it, with the Unicode version.
2. Scanning strategy that meets section 9: one `preg_match_all` with a
   combined pattern over bounded chunks, `preg_match` with an offset loop,
   or `mb_ord` over `mb_str_split` in chunks. Report the measured numbers.
3. Should `skipLinks` also skip hashtags? Default answer: no, a Hindi
   hashtag is Hindi.
4. Is `Zzzz` the right public code for "other", or should unlisted scripts
   be reported under their own ISO code as strings? The enum keeps the API
   closed; strings keep it complete.
5. Should `checkLanguage` default `minShare` per script family? Current
   answer: no default, the caller passes it.
6. Exact `skipLinks` matching (4.5.4): where a link ends when it is followed
   by closing punctuation such as `)` or `.`, and how strict the email
   pattern is. Pick the simplest linear rule, state it, and test it.

## 14. Lessons from production (rules)

14.1. Divide by letters, never by characters. Whitespace, digits and
punctuation dilute the share and force a threshold so low it misfires.

14.2. Count combining marks with their script. Indic text is around a
third marks; `\p{L}` alone makes Indic look less Indic than it is.

14.3. Use the Script property, not block ranges. A block range lumps in
digits and the danda, and misses the letters a script has in its
supplement and extension blocks.

14.4. Strip markup before counting, and strip it for the numerator and the
denominator alike. Our earlier code stripped tags for the total only, so
tag names and attributes were counted on one side and not the other.

14.5. A failed regex is an error, not zero. `preg_match_all` returns false
on invalid UTF-8, and `false / n` is 0. That turned broken input into a
pass.

14.6. Check the title and the body separately. A short English headline
over an English report that embeds a Hindi tweet or caption is English.
Let the body overrule such a headline only when most of the body's
letters are in the other script, not when the other script merely leads
a plurality count.

14.7. "No letters" is its own answer. Never default to Latin or English
when nothing is found.

14.8. Ties have no winner. Returning whichever script is tested first makes
the answer depend on list order.

14.9. Report scripts, map languages separately. Devanagari is Hindi and
Marathi and Nepali; Urdu, Kashmiri and Shahmukhi Punjabi share Arabic
script. A code labelled `hi` for "Devanagari" will mislead the next reader.

14.10. Sample by letters, not by characters. "The first 1,500
characters" of a lead can be a URL and a table of figures.

14.11. Keep thresholds out of the library. Every caller's tolerance for
quoted English or a romanised brand name differs; the library reports and
the caller decides, including what `too_short` means.
