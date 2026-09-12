# Consumer compatibility fixture

`composer.json` preserves the supplied application's manifest, including its original `^2.0` package constraint. It is a dependency fixture, not application source.

Run `php8.5 tools/check-consumer.php` from the repository root. The script creates `.cache/consumer/composer.json`, changes only this package to `^3.0`, and adds a local path repository identifying the candidate as 3.0.0. Composer runs in dry-run mode with scripts and plugins disabled. No tag is created and no application scripts run.

The PHP interpreter must have the application's required extensions installed. An unrelated package conflict is not evidence of a Twilio package regression. See `docs/VALIDATION.md` for the recorded outcome and `docs/APPLICATION_REVIEW_PROMPT.md` for the actual application review.
