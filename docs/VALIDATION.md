# Validation of the proposed 3.0.0 release

Checked locally on September 12, 2026, starting from clean `master` at `39af102` / `v2.1.1`, matching `https://github.com/citricguy/twilio-laravel.git`. Implementation is on `codex/twilio-modernization`. No release tag or remote push was made.

## Package checks

| Check | Result |
| --- | --- |
| PHP 8.4.25, latest dependencies | 295 tests pass |
| PHP 8.5.10, latest dependencies | 295 tests pass |
| PHP 8.4.25, lowest-compatible dependencies | 295 tests pass |
| Source line coverage, PHP 8.5/Xdebug | 98.0%; CI requires 95% |
| PHPStan level 9 | Pass; only two existing dynamic notification methods are excluded, each scoped to one file and occurrence |
| Pint, Composer validation/platform requirements, actionlint | Pass |
| Composer security audit, latest and lowest package installs | No advisories |
| Standalone Laravel consumer smoke test | Pass; package discovery, cached boot, notification, serialized worker, signed/invalid webhooks |
| Supplied full application manifest | Resolves with the local 3.0.0 candidate; 251 packages selected |

Latest dependencies used Laravel 13.31.0, Twilio SDK 8.12.1, Testbench 11.2.0, Pest 5.1.4, PHPStan 2.2.13 and Symfony HttpFoundation 8.1.6. The lowest-compatible install used Laravel 13.23.0, SDK 8.12.1, Testbench 11.2.0, Pest 5.1.0, PHPStan 2.2.2 and Symfony HttpFoundation 7.4.13.

The initial installed development dependencies had 37 advisories across 11 packages. Fresh package resolution and audits are clean. This library intentionally does not commit its lockfile; future CI runs resolve then-current allowed dependencies and audit them again.

## Application compatibility

The manifest in `tests/Fixtures/consumer/composer.json` is the supplied example. `php8.5 tools/check-consumer.php` changes its Twilio package constraint from `^2.0` to `^3.0` in an ignored fixture, adding a local path repository with candidate version 3.0.0. All other application requirements and scripts are retained; scripts/plugins are disabled during the dry run.

Resolution selected Laravel 13.31.0, Filament 5.8.1, Pest 5.1.4, Symfony HTTP Client/Postmark Mailer 8.1.6 and this candidate. No unrelated dependency conflict remained after supplying the required PHP extensions. This is a solver check: it does not install or audit the full application's selected dependency set, execute its scripts or test unavailable application code.

`bash tools/smoke-consumer.sh` uses PHP 8.5 on PATH to create a separate temporary Laravel 13 application with Symfony 8.1 and the local candidate. It installs/audits production dependencies, discovers the provider, caches configuration/routes, and verifies notifications, a serialized queued job and signed webhooks through a recording Twilio transport. The temporary application is removed afterward. No live Twilio sends occur.

## Reproduction and limits

```sh
composer update
composer validate --strict
composer check-platform-reqs
composer audit
vendor/bin/pest --parallel --processes=2 --fail-on-risky --fail-on-warning
composer test:analyse
composer test:lint
XDEBUG_MODE=coverage composer test:coverage
# On PHP 8.5 with the application's required extensions:
php8.5 tools/check-consumer.php
bash tools/smoke-consumer.sh
```

Use a separate checkout/install for `composer update --prefer-lowest --prefer-stable`. CI follows that isolation. Locally, PHP 8.5's missing mbstring/curl/zip/SQLite/intl extensions were extracted into a temporary directory and loaded through `PHP_INI_SCAN_DIR`, without changing system PHP. CI provisions extensions with setup-php.

GitHub workflow YAML was validated locally with actionlint 1.7.12. Remote GitHub runs have not been triggered for this branch, and repository rules/settings were not changed. The actual application still needs the review in [APPLICATION_REVIEW_PROMPT.md](APPLICATION_REVIEW_PROMPT.md), particularly its custom listeners, logging replacements, notification routing and deployment process.

## Consumer-review follow-up

FSRevs identified that sending-listener option mutations were discarded. Fixed direct SMS/voice, enqueue serialization, worker sending and fake records/events. Added eight option-propagation regressions using the real SDK with in-memory HTTP transport or standard fake, plus two HTTP response tests for unhandled voice progress (202) and inbound TwiML (200). Rechecked all 295 tests / 1,054 assertions on PHP 8.4 and 8.5 latest and PHP 8.4 lowest-compatible dependencies; coverage remains 98.0%. PHPStan level 9 and Pint pass. Application code and deployed runtimes remain untested here.
