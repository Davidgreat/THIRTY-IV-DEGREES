# 36° Payment & Studio Update — 6.0.0

This is an admin-dashboard UPDATE, not a fresh installation archive. It targets the PHP application supplied in public_html.zip (the source assets identify the previous release as 5.8.3; the root VERSION file is stale). It does not contain hosting credentials, production records, uploaded customer media or backup copies.

## Install

1. Take a complete cPanel files AND MySQL database backup. This update adds three tables; the existing updater cannot automatically roll database migrations back.
2. Install on a staging copy of your current site first. Match production PHP/extensions and use a copy of its database. The supplied ZIP did not contain a production database dump, so local testing used a reconstructed development schema and synthetic records.
3. Sign in as the owner administrator, open **Site Updates** (`/admin/updates`), upload `thirtyvi-degrees-update-6.0.0.zip`, and click **Install Update**. Do not extract it over the website or run `/install/`.
4. Refresh after installation. Your original updater can briefly serve old PHP pages from OPcache; wait a few seconds and refresh. If they persist, use cPanel's PHP restart/reset mechanism. This release invalidates replaced scripts for subsequent updates.
5. Open **Paystack Setup** (`/admin/paystack`). Paste the test public and secret keys into their TEST fields, select Test, enable payments, and save. Blank credential fields retain their previous values. Secret keys are never redisplayed.
6. Click **Check Test connection**. Then place and pay for an actual Paystack test order; a successful connection check alone is not a completed transaction test. Verify order details, admin records, signed webhook delivery and the customer receipt queue. Test transactions do not deduct real inventory. Mark test orders cancelled after testing; do not fulfil them.
7. In the Paystack dashboard, set the webhook URL in both environments to the endpoint shown on the setup page (`https://YOUR-DOMAIN/payment/webhook`). The browser callback is `https://YOUR-DOMAIN/payment/verify`. Confirm the existing `app_url` in config.php is your correct HTTPS domain.
8. After Paystack approves your business and the staging payment journey passes, enter the LIVE public/secret keys, select Live, enable payments, and save. Check the live connection and perform a small, authorized live transaction and refund before taking customer payments.

## Hosting requirements

PHP 8.1+; PDO MySQL, cURL, OpenSSL, mbstring, fileinfo and ZipArchive; MySQL/MariaDB with InnoDB; writable app/public and protected storage directories. The database account needs CREATE privileges for the additive migration. HTTPS and outbound HTTPS to `api.paystack.co` are required. Keep TLS verification enabled.

Existing legacy `paystack_secret`/`paystack_public` configuration is retained as a compatibility fallback. Pre-update transactions have no saved gateway context and need their original legacy secret until reconciled. Do not change the application's `app_key` or database password without a credential re-encryption plan: existing integration encryption derives from that configuration. The new per-payment encrypted credential snapshot allows post-update transactions/refunds to survive switching modes and rotating saved keys.

## Email and background jobs

Configure SMTP in **Email Delivery**, select `starttls` (usually port 587) or `ssl` (usually port 465), and send a test. Configure cPanel Cron Jobs using your actual cPanel account path and PHP CLI executable, for example:

```cron
* * * * * /usr/local/bin/php /home/CPANEL_USER/public_html/app/cron-mail.php
*/5 * * * * /usr/local/bin/php /home/CPANEL_USER/public_html/app/cron-reconcile.php
```

These paths are examples; confirm them on your host. Password-reset emails and transaction notifications are queued and require working SMTP plus the mail worker. The reconciliation worker retries pending payments and submitted refunds; it never initiates a refund.

## Recovery

Use your full cPanel files/database backup for rollback. The admin updater intentionally marks this release as involving database migrations and does not offer automatic code-only rollback. Never delete payment/order records to clear an uncertain gateway outcome. An interrupted checkout must be reconciled before another charge. See AUDIT-6.0.0.md for deployment dependencies and remaining connector limitations.
