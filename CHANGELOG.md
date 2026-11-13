# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-13

### Added

- `ScriptShare` static entry points: `analyse`, `shares`, `dominant`,
  `check`, `isMostly`, `scriptsFor`, `checkLanguage`.
- `Analyser` and `Accumulator` for one-shot and chunked analysis. Any split
  of the input gives the same result as analysing it whole.
- `Result`: counts and shares per script (ISO 15924 codes), totals,
  `dominant()` with tie handling, `merge()`, `toArray()` and JSON.
- `Check` with three verdicts (`pass`, `fail`, `too_short`) and a stable,
  content-free `reason()` line.
- `Options`: mark mode (count or ignore), invalid UTF-8 mode (throw, or
  substitute maximal subparts), `skipLinks` (URLs, emails, mentions and
  hashtags, on by default) and `maxLetters`.
- 23 named scripts plus `Zzzz` for every other real script.
- BCP 47 language tag to expected script mapping.
- Typed exceptions under one marker interface.
- Golden corpus test, benchmark script, examples, and a one-file bootstrap
  for use without Composer.

[Unreleased]: https://github.com/IndianOpinion/script-share/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/IndianOpinion/script-share/releases/tag/v0.1.0
