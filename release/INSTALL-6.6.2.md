# 36° 6.6.2 — cron health and wallet receipt Unicode repair

Install over 6.6.1 through Admin → Updates using `artifacts/thirtyvi-degrees-update-6.6.2.zip`. Take a full hosting files/database backup first. This release converts `email_queue` to utf8mb4 Unicode; the built-in code-only restore cannot reverse a database migration.

## Confirmed receipt defect

The reported diagnostic `receipt · PDOException · 22007 / 1366` identifies an email storage character-encoding rejection. It was reproduced with a Latin-1 email queue and the naira currency symbol in the wallet receipt. This migration lets the queue store ₦ and other Unicode characters. Existing email content is preserved. Wallet balances and payment credentials are not changed.

After installing, leave the existing mail cron running. It repairs up to 20 missing verified top-up receipts before sending its mail batch. You can also use the receipt repair button on the new page. Existing queued/sent receipts are not duplicated, and no new charge is made.

## Test hosting cron

Open **Admin → Access & system → Cron health & test**, or use the link in **Email delivery**. The direct route is `/admin/cron`.

1. Keep all five hosting schedule fields `*` (every minute). Keep the valid CLI PHP path and full `app/cron-mail.php` path. The user's screenshot schedule is correct and command syntax appears correct; actual executable/path access can only be confirmed by a run.
2. Enter a test recipient and click **Start scheduled cron & email test**.
3. Leave the page open for up to three minutes. It refreshes status every five seconds while visible.
4. **Test confirmed** means the CLI worker saw this particular test request. **Email: sent** means the SMTP server accepted the queued test email. Inbox placement remains separate.

The button only queues a test and records a request. It never executes shell commands or pretends a browser action is a cron run. Manually draining the mail queue does not confirm the scheduled-run test. The page records actual CLI PHP version, binary path, required extension availability, last start/finish, worker build, mail/wallet outcomes and errors. If a job fails before reaching the application database, the page will report no observed run; inspect the hosting cron error output.

The owner-only page also shows recent top-up references alongside their platform receipt status, attempts, recipient and SMTP acceptance timestamp. **Queue missing verified top-up receipts** repairs missing queue entries without changing money. Failed messages retain the existing retry workflow under Email delivery.

## Validation

- 24 local integration checks: owner access and CSRF, public CLI endpoint blocked, real CLI heartbeat, web/manual activity cannot falsely pass the test, queued test accepted by local SMTP, stale test detection, receipt repair without duplicate email or fund changes, automatic status polling and compact mobile layout.
- 41 exact-ZIP updater checks passed on an isolated 6.6.1 fixture, including preservation of configuration, credentials, uploads, orders and bookings, and Unicode migration.
- 9 legacy-encoding checks reproduced the exact 22007/1366 error, recovered the original receipt after migration, preserved the naira amount and existing queue content, prevented duplicate receipts, preserved wallet balance and submitted the recovered receipt through CLI to local SMTP.

All tests used fake payment keys and a local SMTP fixture. No live customer emails or financial charges were made from this workspace.
