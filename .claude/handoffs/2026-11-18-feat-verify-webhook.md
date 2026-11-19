# feat/verify-webhook

## Goal
T24: add a webhook signature verifier to the PHP SDK, matching the spec derived from `apps/api/src/modules/events/webhook-signing.ts` in the Envello repo.

## What changed
- `src/Webhook.php`: new `final class Envello\Webhook` with `static verify(string $secret, string $rawBody, ?string $header, int $toleranceSeconds = 300, ?int $now = null): bool`. HMAC-SHA256 over `<t>.<rawBody>`, `hash_equals` against every `v1` with no early exit.
- `tests/WebhookTest.php`: spec test vector, rotation (two v1, either secret), wrong secret, tampered body, t + 300 passes, t + 301 fails, t - 301 fails, default clock, 16 malformed/missing header cases, empty secret.
- `README.md`: "Verifying webhooks" section with a plain-PHP example (`php://input`, `$_SERVER['HTTP_ENVELLO_SIGNATURE']`). Removed the existing em dashes in the API surface section.
- `.github/workflows/ci.yml`: `php -l src/Webhook.php`, matching the existing per-file lint steps.

## Decisions
- Separate `Webhook` class rather than a method on `Envello`: verifying needs no API key or HTTP client, so callers shouldn't have to construct one.
- Malformed means: a part with no `=`, a duplicate `t`, `t` not a positive int (`filter_var` with `min_range 1`, which also rejects overflow and decimals), no `t`, or no `v1`. Unknown keys are ignored for forward compatibility.
- Empty secret returns false instead of throwing, keeping the never-throws contract.
- No CHANGELOG (repo has none), no version bump, no tag.

## State
Code and tests written. Tests NOT run locally: no `php`, `composer` or `docker` on this machine. The test vector HMACs were cross-checked with Python's `hmac` and match the spec. CI on the PR is the first real run. Branch pushed; no PR opened yet.

## Verify
```
composer install && composer test
```
Expect the existing suite plus all `WebhookTest` cases green.

## Gotchas
- The spec vector's `t` (1795000000) is 2026-11-18 11:06:40 UTC, so tests always pass `$now` explicitly. `testDefaultsUseCurrentTime` relies on the real clock being far from that moment, which holds from now on.
- Strict types: passing `false` from a failed `getenv` as `$secret` is a TypeError, hence the `(string)` cast in the README example.
- Local `master` tracks `aktai-old/master`; the pull and push here used `origin` explicitly.
