# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | Yes |

## Reporting a vulnerability

Email **hello@indianopinion.org** with "script-share security" in the
subject. Please do not open a public issue for a security problem.

Include the version, the PHP and PCRE versions, and the smallest input that
shows the problem (as `\u{...}` escapes or hex bytes).

We acknowledge reports within 3 working days and aim to ship a fix, or a
clear plan, within 14 days. We will credit you in the release notes unless
you ask us not to.

## What counts

script-share is a pure function of its input. Things we treat as security
issues:

- input that makes analysis take super-linear time or memory (a regular
  expression that backtracks badly, for example);
- a failed PCRE call reported as a normal result instead of an exception;
- exception messages, `reason()` strings or `toArray()` output that echo
  any of the input text.
