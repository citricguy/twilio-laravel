# Changelog

## Unreleased — 3.0.0

### Requirements

- PHP 8.4+, Laravel 13.23+, Twilio SDK 8.12.1+; Pest 5/Testbench 11 development tooling.
- Explicit framework dependency for the Foundation traits already used by jobs and events.

### Security

- Resolve issue #1 by removing sensitive request/provider/customer data from package logs, including cancellation and exception paths.
- Validate original form values and raw query strings; validate JSON `bodySHA256`; reject malformed nested signature input.

### Fixes

- Honor explicit Messaging Service SIDs with documented sender precedence.
- Dispatch the sending event once per worker attempt.
- Align fake dispatch/cancellation with the service while retaining fake success records.
- Return JSON and Symfony listener responses; classify early voice progress callbacks correctly.
- Prefer provider-reported positive segment counts, retaining estimates only as a fallback.

### Maintenance

- PHP 8.4/8.5 and lowest-dependency CI, PHPStan level 9, 95% coverage gate, dependency audits/review, workflow validation and Dependabot.
- Upgrade guidance and a repeatable consumer-manifest compatibility check.

See [the upgrade guide](docs/UPGRADING.md) for application impact. No 2.x backport is included; no release has been tagged.

### Sending-listener options

Synchronous `TwilioMessageSending` and `TwilioCallSending` listeners can modify `$event->options`. The service now uses the modified options for direct SDK requests, queued job serialization, and subsequent queued/sent event metadata. Worker listeners can modify them again for that attempt. The standard fake retains the same modified options in its records and events. Remove custom fake overrides that dispatch an additional sending event solely to supply this behavior. Queued listeners cannot synchronously change a request already being sent. This contract covers options, not rewriting recipient, message, or TwiML URL event fields.
