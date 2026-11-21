# Handoff: release v0.2.0

**Goal.** Ship the security fixes merged since the July release, and get npm/PyPI/Go metadata pointing at aktailtd.

**What changed.** Version bumped to 0.2.0 (minor: Webhook::verify plus hardening; User-Agent bumped).

**Decisions.** Repo history was rewritten on 2026-11-21 to remove personal author data; the originals are private `aktailtd/envello-php-archive`. Never push old history back.

**State.** PR open. After merge: tag `v0.2.0` on master. Publishing: tag only; not on Packagist yet.

**Verify.** not run locally (no PHP on the machine); only the User-Agent string changed. Run vendor/bin/phpunit before tagging. After tagging, check the Actions run and the registry page shows 0.2.0 with repository aktailtd/envello-php.

**Gotchas.** The July releases were published with tokens (no provenance); this is the first trusted-publishing release, so a failed publish most likely means the trusted publisher isn't configured yet. Re-run the workflow after fixing, no new tag needed.
