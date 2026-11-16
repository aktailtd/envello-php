# fix/https-base-url-pinned-actions

## Goal
T18: stop the SDK sending the API key over plain http. T19: pin GitHub Actions to commit SHAs. Lead 9: block CR/LF header injection through `$idempotencyKey`.

## What changed
- `src/Envello.php`: the constructor rejects any base URL that isn't `https`, or `http` on `localhost` / `127.0.0.1` / `[::1]`, with `EnvelloException("The API URL must use https (http is only allowed for localhost).")`. Unparseable URLs and URLs without a host are rejected the same way. Path is kept, trailing slash stripped as before. `sendEmail` throws `EnvelloException` if `$idempotencyKey` contains `\r` or `\n`, before any header is built.
- `tests/EnvelloTest.php`, `phpunit.xml.dist`: first test suite in this repo. URL cases (remote http, `localhost.evil.com`, `localhost@evil.com`, ftp, garbage, empty, no host, bad IPv6, uppercase, path) and CRLF / LF / CR keys.
- `composer.json`: `phpunit/phpunit ^10.5` in `require-dev`, `autoload-dev`, `composer test` script.
- `.gitignore`: `vendor/`, `composer.lock`, PHPUnit cache.
- `.github/workflows/ci.yml`: actions pinned to SHAs; added `composer install` and `composer test`.
- `README.md`: documents the URL rule.

## Decisions
- Both checks throw `EnvelloException`, the SDK's own type. The empty `apiKey` check still throws `InvalidArgumentException`; left alone as unrelated.
- PHPUnit 10.5 because CI runs PHP 8.1 and PHPUnit 11 needs 8.2.
- `composer.lock` not committed: this is a library, consumers resolve their own deps.
- Same major versions kept, pinned to the latest release in each. Floating `v4` / `v2` resolved to these SHAs on 2026-11-16.

| Action | Commit | Tag |
| --- | --- | --- |
| actions/checkout | 11d5960a326750d5838078e36cf38b85af677262 | v4.4.0 |
| shivammathur/setup-php | eb7c497e18156a6bbabfef1d3a82760b9eda3962 | 2.40.0 (annotated tag, dereferenced) |

## State
Code done. Tests not run locally (no `php` / `composer` on the dev machine); CI on the PR is the first run.

## Verify
```
composer install && composer test   # 17 tests expected to pass
```
Or check the CI run on the PR.

## Gotchas
- `parse_url` keeps IPv6 brackets and case, so the loopback list holds `[::1]` and both scheme and host are lowercased.
- The CR/LF test points at `http://127.0.0.1:9`. If the guard regressed, the test fails on the message (cURL error), not by hanging.
- No `id-token: write` in this repo's workflow; nothing to scope.
