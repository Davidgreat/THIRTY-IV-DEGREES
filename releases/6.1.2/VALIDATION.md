# Local validation — 6.1.2

All checks used synthetic accounts and orders in an isolated PHP 8.4 / MariaDB 11.8 installation. Gateway requests used a local TLS mock with certificate verification. No real charges or production database changes were made.

- Dedicated endpoint browser suite: 29 checks passed against the PHP server.
- Apache 2.4 reverse-proxy suite: 34 checks passed with an actual HTTP 302 rule on POST `/checkout`. Covers mobile/desktop, delivery/pickup, test/live mock initialization, stale CSRF, expired session, gateway denial, database rollback, explicit transport errors, lost-response recovery without a second order/payment, mode changes and owner-only diagnostics. Zero browser JavaScript errors.
- Payment regression: 34 checks passed, including verification identity/amount/mode, encrypted credentials, idempotency, stock and signed webhooks.
- Existing HTTP functional suite: 22 checks passed, including guest checkout, authorization and payment settings.
- Exact ZIP installed through the existing dashboard updater onto a 6.1.1 fixture: 20 checks passed. Settings, configuration, uploads, orders and bookings preserved; restore point created and duplicate install rejected.
- PHP and JavaScript syntax checks and git diff whitespace checks passed.

The public hosting domain is unavailable under this workspace's network policy. Actual cPanel redirects, its database structure and production Paystack requests have not been inspected. The Apache rule is a controlled reproduction of one possible failure class, not proof of the hosted root cause. Endpoint build markers and owner-only stage/schema diagnostics support the next hosted check.

Build: `python3 release/build-612.py` compares runtime files against commit `684a707` (6.1.1), excluding uploads, config, storage and migrations. The builder rejects changes to gateway, verification, refund and credential encryption modules. Test scripts are only for disposable local fixtures.
