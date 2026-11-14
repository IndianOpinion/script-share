---
name: Bug report
about: A count, share or verdict that is wrong, or an error you did not expect
labels: bug
---

**What happened**

**What you expected**

**Smallest input that shows it**

Please give the input as code points or a PHP string with `\u{...}` escapes
(for example `"क\u{200D}\u{0951}"`), not pasted text, so invisible characters
survive. Do not paste private or customer text.

```php
$result = \IndianOpinion\ScriptShare\ScriptShare::analyse("...");
var_dump($result->toArray());
```

**Environment**

- script-share version:
- PHP version (`php -v`):
- PCRE version (`php -r 'echo PCRE_VERSION;'`):
- Options used:
