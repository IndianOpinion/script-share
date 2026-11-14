# Contributing

Thank you for helping. Small, focused pull requests are easiest to review.

## Setup

```sh
git clone https://github.com/IndianOpinion/script-share.git
cd script-share
composer install
```

You need PHP 8.1 or newer with `mbstring`. Write code that runs on 8.1: no
readonly classes, no DNF types, no `true` type, no constants in traits.

## Tests and checks

```sh
composer check      # phpcs, phpstan (level 8), phpunit
php bin/golden.php  # the golden corpus is up to date
php bench/bench.php # timings; for information, never a gate
```

Every behaviour in `docs/PRD.md` section 4 has a test. A change in
behaviour needs a new or changed test, and a line in `CHANGELOG.md` under
Unreleased.

The golden corpus (`tests/fixtures/golden-*`) pins exact counts on every
PHP version in CI. If your change moves those counts on purpose, run
`php bin/golden.php --write` and explain the change in the pull request.
Only use code points from Unicode 14.0 or older in the corpus.

## Style

- PSR-12, checked by `phpcs`. `composer cs-fix` fixes most of it.
- `declare(strict_types=1);` in every PHP file.
- Typed exceptions that implement `ScriptShareException`. Never `@`, never
  silent failure.
- Exception messages and log strings never contain the input text.
- Prose: short sentences, plain words, British spelling. No em dashes.

## Pull requests

- Branch from `main`, open the PR against `main`.
- Fill in the template. CI must be green.
- A sign-off line (DCO) is not required.
- By contributing you agree your work is released under the MIT licence.
