# Upgrading from 2.x to 3.x

3.0.0 is a major release. There is no 2.x backport of this work. Applications constrained to `^2.0` will not receive it automatically.

## Requirements

- PHP 8.4 or 8.5 with curl, mbstring and the extensions required by Laravel.
- Laravel 13.23 or newer within 13.x. The package now explicitly requires the full framework because its existing jobs/events use Foundation traits.
- Twilio SDK 8.12.1 or newer within 8.x.

After the release, change the package requirement to `^3.0` and update with dependencies:

```sh
composer require citricguy/twilio-laravel:^3.0 --with-all-dependencies
composer check-platform-reqs
```

Inspect the lockfile diff. Existing `^13.0` application constraints allow Laravel 13.23+, but an old lockfile may need its Laravel dependencies updated. Do not force-publish configuration over application customizations.

## Behavior changes to review

| Area | 3.x behavior | Application action |
| --- | --- | --- |
| Logging | Safe counts, fixed labels and booleans replace payloads, addresses, URLs, signatures, option arrays, cancellation reasons and exception messages. | Update log consumers that relied on those fields. Compare any local middleware/controller replacements before removing them. |
| Sender selection | Explicit `from`, explicit `messagingServiceSid`, configured Messaging Service, configured `from`, in that order. | A previously ignored per-message Messaging Service now takes effect. When both explicit fields are present, `from` wins. |
| Sending events | Once before enqueue, once per worker attempt; direct sends fire once. | Remove assumptions that the worker invokes a listener twice. Listeners can still run again on a queue retry. |
| Fake cancellation | All six entry points run cancellation checks and return `['status' => 'cancelled', 'to' => ..., 'reason' => ...]`. | Replace `false` assertions with cancellation-array assertions. |
| Fake dispatch mode | `sendMessage`/`makeCall` respect `queue_messages`; explicit queue/now methods retain their meaning. | Check tests that previously expected all automatic fake sends to be queued. Fake success values remain arrays. |
| Webhook responses | First Symfony-compatible response wins, including Laravel JSON responses. Default remains 202 JSON. | Review listeners whose JSON response was previously ignored. |
| Voice callbacks | `CallbackSource=call-progress-events` identifies status callbacks even when ringing/in-progress. | Review listener branches using inbound/status helpers. |
| Segments | Sent events prefer positive SDK `numSegments`; unavailable/zero values retain the previous rough estimate. | Do not use estimates for billing; reconcile final provider data. |
| Signatures | Original form values and query encoding/order are verified; `bodySHA256` checks the raw body. | Ensure trusted proxy settings reconstruct the exact public URL. Malformed/nested signature input is rejected. |

Public facade/service methods, builder methods, event field names, default webhook route, channel names, queue job properties, cancellation reasons in results, and exception propagation remain available. Both webhook validation configuration keys retain their precedence: a boolean `validate_webhook` wins, otherwise a boolean `validate_webhook_signature`, otherwise validation is enabled. Event payloads are not redacted by the logging change.

## Deployment and rollback

1. Resolve and test the update on a branch. Review application logging replacements, event listeners, notifications and fake assertions.
2. Exercise signed message and voice callbacks in staging; test synchronous sends and queued cancellation with a fake HTTP transport.
3. Deploy application changes and the lockfile together. Rebuild configuration and route caches using the application's normal deployment process, then restart queue workers so they load the new code.
4. Monitor signature rejection rates and application delivery processing. Keep `TWILIO_DEBUG=false` in production.
5. If necessary, restore the previous application revision and lockfile, rebuild caches and restart workers. Account for changed listener behavior during mixed-version deployment. The package adds no database migrations.

Queued jobs still carry the same public payload properties, but applications should verify their own serialized jobs and listeners. SDK exceptions, application event handlers, queue payloads and independent SDK diagnostics can contain private data; the package logging guarantee applies to its own log calls.

### Sending-listener options

Synchronous `TwilioMessageSending` and `TwilioCallSending` listeners can modify `$event->options`. The service now uses the modified options for direct SDK requests, queued job serialization, and subsequent queued/sent event metadata. Worker listeners can modify them again for that attempt. The standard fake retains the same modified options in its records and events. Remove custom fake overrides that dispatch an additional sending event solely to supply this behavior. Queued listeners cannot synchronously change a request already being sent. This contract covers options, not rewriting recipient, message, or TwiML URL event fields.
