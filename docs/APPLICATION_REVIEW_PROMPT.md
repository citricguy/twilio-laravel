# Application team review prompt

Review our application's upgrade from `citricguy/twilio-laravel:^2.0` to the proposed `^3.0` release. Do not deploy or perform live Twilio sends as part of this review.

1. Confirm PHP 8.4+ (our example application uses 8.5), required extensions, Laravel 13.23+, and Twilio SDK 8.12.1+. Resolve Composer dependencies and inspect the lockfile diff, including Symfony 8.1 and Pest 5 compatibility. Separate unrelated application dependency conflicts from package conflicts.
2. Find package usages, custom service/container bindings, local webhook middleware/controller replacements, event listeners, notifications, queued jobs and fake assertions. Read the package's upgrade guide and compare local logging replacements with the new behavior before removing them.
3. Verify sender precedence: explicit `from`, explicit `messagingServiceSid`, configured Messaging Service, configured `from`. Exercise SMS/MMS, call recording true/false, callback options and notification routing, including Stringable phone-number objects.
4. Confirm cancellation still works before enqueue and once per worker attempt. Review listener side effects and retry behavior now that the worker no longer emits duplicate sending events. Update fake cancellation expectations from `false` to the cancellation array and verify fakes respect `queue_messages`.
5. Verify signed form webhooks with whitespace/query encoding and trusted proxies, JSON body hashes, missing/invalid signatures, incoming messages and status callbacks. Confirm early voice progress callbacks take the status branch. Verify the first listener response is returned, including JSON/TwiML, and the default remains 202.
6. Use sentinel private values to confirm the package's diagnostic logs stay safe with debugging both enabled and disabled. Review our own listeners, exception reporting, independent SDK debug logging and queue payload storage separately. Keep `TWILIO_DEBUG=false` in production.
7. Confirm billing does not rely on estimated segment counts: sent events now prefer positive SDK counts, but zero/unavailable values still use estimates.
8. Run application tests, PHPStan, package discovery, configuration/route caching, serialized job/worker checks, and staging checks using fake transports. Document any required code/configuration changes with evidence. Prepare deployment and rollback steps, including cache rebuilding and worker restarts.

The package fixture checks dependency resolution and isolated package behavior. It cannot prove our application's custom code is compatible. Return a go/no-go recommendation, exact blockers, test results and a minimal change list.
