# 6.5.0 — Email automation audit and repair

For a website running **6.4.0**.

**[Download the update ZIP](thirtyvi-degrees-update-6.5.0.zip)**. On GitHub select **Download raw file ↓**, keep the ZIP unextracted, and upload through **Admin → Access & system → Website updates**. Back up first, then clear hosting/CDN cache.

## Required live-site step: enable the queue worker

A successful SMTP test sends directly. Account, recovery, order and booking notifications are queued and need the worker. No cPanel cron setup was confirmed during the audit; the hosted cron configuration was not accessible from this workspace.

After installing:

1. Open **Admin → Customers & messages → Email delivery → Automatic email delivery**.
2. Copy the **Command** displayed there. It uses the actual installed application path and the detected CLI PHP location.
3. Open **cPanel → Cron Jobs → Add New Cron Job**. Select **Once per minute** (minute/hour/day/month/weekday all `*`). Paste the copied command into the **Command** box and save. If your host uses a different CLI PHP binary, use its PHP 8.1+ executable path.
4. Wait about one minute and return to Email delivery. It should show **Cron worker recently observed** with a fresh completed-run timestamp. This confirms an actual worker run, not just that SMTP settings were saved.
5. Request a **new** reset link using the email you use to sign in. The public business email or SMTP sender address is not automatically a registered account. Old reset links may already have expired.

You do not need to replace working SMTP credentials. For immediate recovery before cron is configured, the owner can click **Send up to 5 pending emails now**. Recovery links are prioritized. This manual action does not pretend cron is enabled and does not replace the one-minute job.

The ZIP cannot create a cPanel Cron Job by itself. Installation alone does not schedule background email delivery.

## Audit findings and fixes

| Area | Finding / resulting behavior |
| --- | --- |
| Delivery | Direct SMTP tests worked independently of the automation queue. A new health panel exposes scheduler timestamps, ready/retry/failed counts, oldest ready message, last batch, and the exact cron command. |
| Registration | A welcome-email trigger was missing. New registrations now queue one welcome email. Existing accounts are not bulk-mailed. |
| Password recovery | Reset messages were queued. The worker now prioritizes them and rejects links that expired, were replaced, or no longer match the account/recipient. Expired jobs cannot be retried from the panel; request a new link. The public unknown-account response stays generic. |
| Account security | Successful resets and owner password changes send security alerts. Owner login-email changes notify the previous and new addresses without including any password. |
| Orders | Placement receipts remain separate from verified-payment confirmations. Each real status transition can notify, including a state revisited later. Saving an unchanged status sends no duplicate. Completion/cancellation have clear templates. |
| Bookings | Inquiry, quotation, confirmation and cancellation notify. Changing a quotation/deposit while status stays the same now notifies; saving identical values does not. |
| Refunds | Only a provider-confirmed processed refund transition queues a completion email. A pending/processing refund or ordinary cancellation does not claim money was returned. |
| Templates | New lifecycle templates appear under **Email templates**. Password-reset templates must retain `{link}`. Quotation templates support `{quote}` and `{deposit}`. Existing saved template overrides are retained. |

The worker records cron heartbeats even when the queue is empty. Manual batches and cron use a shared lock; per-message claims and event keys retain duplicate protection. SMTP failures remain queued for scheduled retries. A `sent` status means the SMTP server accepted the message, not guaranteed inbox delivery.

No database migrations are included. SMTP/OAuth/Paystack credentials, configuration, users, orders, bookings and uploads are preserved. Gateway initialization, payment verification, checkout submission and Microsoft OAuth modules are unchanged. The refund reconciliation file only adds a notification after the existing confirmed transition; refund amounts/calculations and gateway requests are unchanged.

[Automation-health preview](automation-health.png) · [Validation](VALIDATION.md) · [Checksum](SHA256SUMS.txt)

No live cPanel configuration or real email/financial transaction was performed by this update. After setting up cron, verify one fresh password recovery and an ordinary customer flow on the hosted platform.
