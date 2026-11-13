# script-share architecture

This note explains how the library is built and records every decision,
including the answers to the open questions in `docs/PRD.md` section 13.

## Modules

| File | Role |
|---|---|
| `src/ScriptShare.php` | Static entry points with default options. |
| `src/Analyser.php` | Holds `Options`; `analyse()` is one `add()` plus `finish()` on a fresh accumulator. |
| `src/Accumulator.php` | Byte layer: buffering, windows, UTF-8 validation and substitution. |
| `src/Internal/Scanner.php` | Token layer: runs the pattern over valid UTF-8 and counts. Carries mark context, the letter limit and open links across windows. |
| `src/Internal/Pattern.php` | Builds the one tokenising pattern for a mark mode and `skipLinks`. |
| `src/Internal/Utf8.php` | Byte-level UTF-8 rules (Unicode table 3-7), maximal subparts, safe cut points. |
| `src/Result.php` | Immutable counts, shares, `dominant()`, `merge()`, `toArray()`. |
| `src/Check.php` | Threshold verdict and the `reason()` line. |
| `src/LanguageMap.php` | BCP 47 tag to expected scripts. |
| `src/Script.php`, `MarkMode.php`, `InvalidMode.php`, `Verdict.php` | Enums. |
| `src/Exception/*` | Typed exceptions under the `ScriptShareException` marker. |
| `src/bootstrap.php` | Autoloader for use without Composer. |
| `bin/golden.php` | Regenerates or checks the golden corpus. |
| `bench/bench.php` | Prints timings and peak memory. |

`Internal\*` classes and `Result::fromCounts()` are marked `@internal`; they
may change in any release.

## Data flow

```
add(chunk) ─▶ buffer ─▶ window of 16 KiB, cut after its last ASCII whitespace
                         (or, with none, at a code point boundary: a "forced cut")
                │
                ├─ mb_check_encoding() ok ─▶ Scanner::scan(window)
                └─ not ok ─▶ byte walk: valid pieces ─▶ scan(); bad subpart ─▶
                             throw InvalidUtf8Exception(offset) | Scanner::invalid()

Scanner::scan(text):
  preg_match_all(PATTERN, text) ─▶ tokens + (*MARK) kinds
  for each token:
    script run   ─▶ counts[script] += code points; context = script
    Inherited    ─▶ counts[context] += code points if context, else ignored
    bridge       ─▶ ignored (format characters, Common marks); context kept
    anything else ─▶ ignored; context = none
  codePoints += mb_strlen(text)

finish() ─▶ last window ─▶ Result::fromCounts(counts, codePoints, invalid, truncated)
```

Windows are taken only when 16 KiB are buffered (or at `finish()`), so where
a window ends depends only on the concatenated bytes, never on how the input
was split into `add()` calls. Cutting after whitespace is neutral: no token,
link or mark attachment crosses whitespace. That is why chunked analysis
equals whole analysis exactly, which the tests check at every byte offset.

## Decisions

### D1. Script lookup: PCRE script properties (PRD 13.1)

Ruling: use PCRE `\p{Script}` classes, document that the Unicode version
follows the PCRE build, and keep a golden-file test that runs on 8.1 to 8.3.

- The pattern uses `\p{sc:Devanagari}` on PCRE2 10.40 and later and
  `\p{Devanagari}` before it. PCRE2 10.40 changed the bare form to mean
  Script_Extensions, under which U+0951 is Devanagari and the Japanese
  long-vowel mark is Hiragana. The PRD defines attribution by the Script
  property, so the library asks for `sc:` where it exists. Without this,
  results differ between PHP builds.
- Checked against the public PCRE2 ChangeLog: 10.40 item 17 added the
  `sc:` and `scx:` qualifiers and changed a bare script name from Script to
  Script_Extensions, so the guard is exactly right.
- Unicode version: whatever the PCRE library has. PCRE2 10.39 to 10.42 carry
  Unicode 14, 10.43 and 10.44 Unicode 15, 10.45 to 10.47 Unicode 16, 10.48
  Unicode 17. PHP bundles 10.39 (8.1), 10.40 (8.2) and 10.42 (8.3). The
  Unicode 14 guarantee needs PCRE2 10.39 or later. The README says so.
- `tests/GoldenTest.php` checks 308 lines (every script alone, every pair
  of scripts, and edge cases) against committed counts in both mark modes.
  The corpus uses only code points from Unicode 14.0 or older. A second test
  cross-checks the single-script lines against an independent `\p{L}` and
  `\p{M}` count.
- Reason: less code, nothing to regenerate, and the golden test turns any
  build difference into a red CI run rather than a silent change.

### D2. Scanning: one pass over code points (PRD 13.2)

Ruling: a single pass over code points that meets the 1 MiB budget, measured
and recorded.

One compiled pattern with one alternative per token kind: links (when
skipped), whitespace, one run class per named script, `Zzzz`, Inherited
marks, bridge characters, ignored characters, and a one-character
catch-all. Each alternative starts with `(*MARK:kind)`, so
`preg_match_all()` returns just two flat arrays (token text and kind) with
no capture groups. The alternatives cover every code point, so tokens are
contiguous and each code point is read once. Script runs use possessive
quantifiers over negated classes such as `[^\P{sc:Tamil}\p{N}\p{P}\p{S}\p{Z}\p{C}]`
(Tamil and a letter or mark), so nothing backtracks. Link alternatives are
bounded (`{0,31}` scheme, `{1,64}` email local part) to stay linear.

The pattern runs over 16 KiB windows, which bounds the match arrays. The
mb_str_split route was rejected for memory (tens of bytes per code point);
`preg_match` with an offset loop was rejected for per-call overhead.

Measured with `php bench/bench.php` on PHP 8.3.6, PCRE2 10.42, one core of
a shared two-core VM, opcache JIT off, default options, best of several
runs:

| Input | PCRE JIT on (PHP default) | PCRE JIT off | Extra peak memory |
|---|---|---|---|
| 1 KiB mixed Hindi and English | 0.09 ms | 0.2 ms | 174 KiB |
| 1 MiB mixed Hindi and English | 70 ms | 200 ms | 388 KiB |
| 1 MiB, `skipLinks` on | 68 ms | 230 ms | 351 KiB |
| 8 MiB mixed Hindi and English | 600 ms (8.7 times 1 MiB) | 1.7 s | 375 KiB |
| 1 MiB of `a.` (one token per byte, worst case) | 450 ms | 1.7 s | 1055 KiB |

The VM is noisy: single runs vary by up to two times, so read the best of
several. All budgets in PRD section 9 are met with PCRE JIT on. With
`pcre.jit=0` the 1 MiB case misses the 150 ms budget and the 1 KiB case sits
at the 0.2 ms line, so the README states the budgets as holding with PCRE
JIT on. Memory and linear scaling hold either way.
The worst-case row is not a budget case but shows memory stays flat.
`tests/PerformanceTest.php` asserts the 4 MiB memory budget for the 1 MiB
mixed input and for the worst case, in a separate process.

### D3. `skipLinks` covers hashtags and mentions, off by default (PRD 13.3)

Ruling: `skipLinks` also skips hashtags and mentions, and stays off by
default as PRD 4.5.4, 5 and 7 state, so counts match the visible text. The
build first turned it on by default; review reverted that, because the PRD
fixes the default (only spelling may change), and on by default would also
drop Hindi hashtags (`आज #भारत में` counts 5 Devanagari letters, not 9),
which the PRD's own answer to 13.3 rejects. With the option opt-in, skipping
tags with links is a fair answer for callers who ask for it: in model output
and scraped text, URLs, handles and tags say little about the language of
the prose. The README recommends `skipLinks: true` for model output and with
`maxLetters`, and notes that Hindi hashtags are skipped too.

### D4. `Zzzz` for unlisted scripts (PRD 13.4)

Ruling: `Zzzz` for unknown scripts; every recognised script gets its ISO
15924 code. The `Script` enum has the 23 listed scripts, each backed by its
ISO 15924 code, plus `Zzzz`. Any letter of a real script not in the list
counts as `Zzzz`. Reason: a closed enum keeps the API typed and the sort
order defined. `Script::Zzzz->unicodeName()` is `Unknown` (the Unicode
value for that code) and `label()` is `Other`. A language tag whose script
subtag is unlisted (`hy-Armn`) or `Zzzz` maps to null, since checking
against "any other script" would be meaningless.

### D5. No per-script thresholds (PRD 13.5)

Ruling: no per-script thresholds; one default of 0.5 with a per-call
override. `dominant()` defaults to `minShare` 0.5, overridable per call.
`check()`, `isMostly()` and `checkLanguage()` take `minShare` as a required
argument (PRD 7: no default on purpose), and there is no table of
per-script or per-family values. Production guidance lives in the README.

### D6. Link and email rule (PRD 13.6)

Ruling: a link runs to the next whitespace with trailing punctuation
stripped, and emails are matched loosely.

- Link: `scheme://` (a letter, then up to 31 of letters, digits, `+ . -`,
  case-insensitive) or `www.`, then everything to the next whitespace, then
  back off over trailing `. , ; : ! ? ' " ( ) [ ] { } < >`, guillemets,
  curly quotes, `…`, `।`, `॥`, `۔`, `、`, `。`, `，`. The link needs at
  least one character after the prefix.
- Email: 1 to 64 of `A-Z a-z 0-9 . _ % + -`, `@`, then to the next
  whitespace with the same trailing rule. No dot is required after `@`. The
  ASCII-only local part stops Hindi words written straight before an
  address from being swallowed.
- Mention or hashtag: `@` or `#` followed by one or more letters, marks,
  digits or `_`.
- A skipped token resets mark context, so a mark after a link is ignored.
- Trailing punctuation is ignored whether or not it is inside a link, so
  where exactly the link ends never changes a count; the rule only fixes
  which characters are reported as part of the link.

### D7. Window size and forced cuts

16 KiB windows, cut after the last ASCII whitespace byte. Whitespace runs
are separate tokens, so a cut there is always a token boundary with no mark
context. When a window has no ASCII whitespace at all, it is cut at a code
point boundary and the scanner carries the mark context and any open link
(a link anywhere after the window's last whitespace) or tag (the last token)
into the next window. With `skipLinks` on, a forced cut first moves back
to the start of the ASCII run of link characters (letters, digits,
`. _ % + - : / @ #`) that ends at it, up to 256 bytes, so a scheme, `www.`,
email local part, `@` or `#` is never split from what follows it. The one
remaining difference from an unsplit scan is a link that starts at a forced
cut inside such a run of more than 256 bytes, within a run of over 16 KiB
with no whitespace. The README lists it under limitations.

### D8. Invalid UTF-8

`mb_check_encoding()` validates each window (fast, in C). Only a window it
rejects is walked byte by byte, using Unicode table 3-7 for well-formed
sequences and the "maximal subpart" practice for substitution. The offset
in `InvalidUtf8Exception` is absolute in the whole input. In throw mode the
whole input is validated even after `maxLetters` stops counting, so a
truncated analysis never hides invalid input.

### D9. `maxLetters` and `truncated()`

Counting stops at the first letter beyond the limit that is not a mark
attached to the last counted base (own-script marks in the same run, and
Inherited marks, with only marks or format characters between).
`truncated()` is true exactly when such a letter was left uncounted, so
text with exactly `maxLetters` letters is not truncated. `codePoints()`
counts only what was read, keeping `letters + ignored = codePoints`.

### D10. API adjustments (PRD 5 allows spelling changes)

- `Options` uses public readonly properties plus the `with*()` methods.
- `Check::of()` is the public constructor; `Check::expected()` is added.
- `Script::named()` lists the 23 named scripts.
- `check()` accepts ISO code strings as well as `Script` cases in the
  expected set, and drops duplicates so a script is never counted twice.
- `Accumulator::__construct()` takes an optional window size (minimum 16),
  for tests; keep the default.
- `toArray()` keys are snake_case: `code_points`, `letters`, `ignored`,
  `invalid_sequences`, `truncated`, `counts`, `shares`, `dominant`. An
  empty `counts` or `shares` encodes as `[]` in JSON.
- `finish()` twice throws `LogicException`, like `add()` after `finish()`.

### D11. Language map (PRD 4.10)

Defaults as listed in PRD 4.10.3, plus a few CLDR defaults that follow the
same rule (for example `bho new` to `Deva`, `bo dz` to `Tibt`, `ps ug ckb`
to `Arab`, `be bg mk sr kk ky mn tg tt ba cv ce os` to `Cyrl`, `iw yi` to
`Hebr`). Languages whose CLDR likely script is Latin map to `Latn`; the list
is `LanguageMap::LATIN` (about 120 languages, from CLDR likely subtags).
Script subtag aliases: `Hans Hant` to `Hani`, `Jpan` to `Hani Hira Kana`,
`Kore` to `Hang Hani`, `Hrkt` to `Hira Kana`, `Aran` to `Arab`, `Latf Latg`
to `Latn`. Extended language subtags (`zh-yue`) are skipped. Region subtags
are ignored, as the PRD says, so `pa-PK` is Gurmukhi.

### D12. Test seam for PCRE failures (PRD 4.8.4)

No stub matcher. The test sets `pcre.jit=0` and `pcre.backtrack_limit=1`,
which makes the real `preg_match_all()` fail, and checks that a
`RegexException` is thrown, then restores both settings.

## Test map

Every acceptance test in PRD section 12 has a test, numbered in a comment
above it: `LetterRulesTest` (1 to 15, 50), `ScriptAttributionTest` (16 to
18, 26), `ResultTest` (19 to 22, 44, 49), `MixedTextTest` (23 to 25 and the
link rules), `CheckTest` (27, 32 to 36), `DominantTest` (28 to 31),
`InvalidUtf8Test` (37 to 40), `ChunkedTest` (41 to 43), `LanguageMapTest`
(45 to 47, 52), `PerformanceTest` (48, 51), `GoldenTest` (53).
