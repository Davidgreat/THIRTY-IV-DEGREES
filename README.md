# 36° restaurant and private-chef platform

PHP/MySQL source imported from the supplied cPanel application. The 6.0.0 update adds owner-managed test/live Paystack, payment/access fixes, account recovery, promotional-banner management and responsive glass dashboards.

- [Current 6.3.0 owner security and product galleries](releases/6.3.0/README.md)
- [6.2.1 password visibility update](releases/6.2.1/README.md)
- [6.2.0 visual experience update](releases/6.2.0/README.md)
- [6.1.2 checkout submission repair](releases/6.1.2/README.md)
- [Previous 6.1.1 checkout hotfix](releases/6.1.1/README.md)
- [6.1.0 compact redesign and previews](releases/6.1.0/README.md)
- [Previous 6.0.0 installation guide](release/INSTALL-6.0.0.md)
- [Audit, fixes and remaining integrations](release/AUDIT-6.0.0.md)
- [Validation evidence](release/VALIDATION.md)
- [Original application documentation](README-platform.md)

Hosting config.php, customer uploads, backups, production data and credentials are excluded from this repository and from the update ZIP. Keep the existing checkout for cloud tasks; no additional worktree is needed.

## Development

Use PHP 8.1+ with pdo_mysql, curl, mbstring, openssl, fileinfo and zip, plus MySQL/MariaDB. Local validation used PHP 8.4 and MariaDB 11.8 in the isolated Docker container `thirtyvi-dev`. The test copy is `/srv/site`; the original-updater fixture is `/srv/original`. Neither is the hosted site.

For a fresh **disposable test database** named `thirtyvi_dev`, import `schema.sql`, then `tests/dev-schema.sql`, then `release/migrations/600_payment_context.sql`. Configure a private local config.php with db_host/db_name/db_user/db_pass, a stable development-only app_key, and app_url. `php tests/seed.php` creates synthetic accounts for tests. The reconstructed development schema is not a production migration.

Run `php -S 127.0.0.1:8086 router.php` from the application directory. Start MySQL first. Use local HTTP requests for validation; do not assume these processes survive a new task. Test accounts are for disposable local fixtures only.

`tests/functional.py` uses Python's standard library and local HTTP. `tests/compact-experience.js` is the 6.1.0 responsive/interaction suite using Playwright/system Chromium; the earlier `tests/browser-smoke.js` documents the 6.0.0 smoke scope. `tests/payments.php` exercises the database and a local TLS Paystack mock; it requires tests/mock-gateway.php running with a dedicated trusted test certificate and HTTPS_PROXY pointing to that local fixture. Never point these destructive fixture tests at a production database. See release/VALIDATION.md for commands actually executed.

Build the update with `python3 release/build.py` while the original supplied baseline exists at `/workspace/platform-source/public_html`. The archive includes changed runtime files and additive migrations only. Review its contents and checksum before distribution.

Build the code-only 6.1.0 patch with `python3 release/build-610.py` while the exact 6.0.0 runtime baseline exists at `/workspace/baseline-600`. The package excludes payment service code and migrations.

Build the 6.1.2 checkout repair with `python3 release/build-612.py` against the recorded 6.1.1 Git baseline. See its release validation for synthetic endpoint and Apache redirect tests.

Build the 6.2.0 presentation update with `python3 release/build-620.py` against commit `d05db5f` (6.1.2).

Build the 6.2.1 authentication presentation patch with `python3 release/build-621.py` against commit `1d94f39` (6.2.0).

Build the 6.3.0 update with `python3 release/build-630.py` against commit `abb14fa` (6.2.1). This release includes an additive catalogue migration and requires owners to sign in again after installation.
