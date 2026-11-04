# 36° platform audit and release notes

Scope: the active PHP application and assets supplied in public_html.zip. Old storage backups, the renamed HTML export and nested historical ZIP are reference artifacts, not active application versions. No access to the hosted cPanel site, actual database, Paystack account, SMTP account or operational cron jobs was supplied. This is a tested update candidate; it is not a claim that production integrations have been activated or every possible defect has been eliminated.

## Fixed in this update

- Added discoverable, owner-only Paystack Setup navigation with separate encrypted test/live secret keys, public keys, an activation switch, mode-specific format validation, read-only connection checks, and webhook/callback instructions. Preserved the legacy integration fallback.
- Prevented a guest payment authorization bypass caused by comparing two null user IDs. An unrelated guest can no longer start another guest's payment using its numeric ID.
- Serialized checkout initialization per order/booking and reused the pending checkout reference. Network timeouts remain uncertain rather than creating a second charge; a missing initialization can be resumed with its original reference. Definitive initialization rejections can be retried.
- Stored encrypted credential context with each new transaction so verification and refunds use the transaction's original mode/key after a mode switch or key rotation. Callback and webhook verification validate reference, amount, currency, successful status, and the expected Paystack domain.
- Restricted checkout redirects to HTTPS checkout.paystack.com. Webhooks require valid HMAC-SHA512 signatures; verification/reconciliation failures return a retryable response rather than a false acknowledgment.
- Made finalization idempotent, locked the parent record, prevented duplicate stock deductions and duplicate payment hooks, and avoided storing full card/customer gateway responses. Test payments no longer consume live inventory. Test orders and transaction environments are labelled.
- Prevented new charges for cancelled, completed, paid and refunded requests. The payment button reflects this and disappears while payments are paused. Verification and refunds continue while new payments are paused.
- Corrected fully paid confirmed bookings retaining the wrong payment state; payment no longer regresses their operational status. Booking dates and chef service times receive strict calendar/hour validation.
- Added account password recovery using hashed, expiring, single-use reset tokens and the existing transactional mail queue. Reset requests do not disclose whether an account exists.
- Replaced session-only request throttling with atomic database counters keyed by action/client address, so replacing a session cookie does not reset the limit. Hardened session cookies and added basic response security headers.
- Restored the missing promotional-banner admin page and linked its existing save/delete actions. Clarified SMTP's accepted encryption setting (`starttls`, not `tls`).
- Added warm glass admin/customer dashboards, real database metrics, tool search, responsive cards/forms, clearer payment status, keyboard focus and skip navigation, copyable webhook URLs, and accessible mobile navigation. Retired conflicting legacy homepage styles and hidden-by-animation content. Added reduced-motion behavior and local fallback meal imagery. Existing saved hero slides remain intact.
- Updated the file installer to invalidate PHP opcode cache after replacing scripts. Corrected the hard-coded footer year.

## Validated locally

- PHP 8.4 / MariaDB 11.8 with the application-required PHP extensions.
- 34 gateway/account tests: encrypted storage, mode validation, checkout URL restrictions, mismatched transaction rejection, initialization/retry, server verification, idempotency, test/live inventory behavior, signed/invalid webhook handling, booking state and password-reset single use. Gateway HTTP calls used a local TLS mock with a trusted test certificate and synthetic keys. No real charge was attempted.
- 22 HTTP workflow checks: owner settings save/activation/pause, secret non-disclosure, CSRF, banner creation/public display, guest cart and order creation, unrelated guest denial, customer booking, invalid calendar/time rejection, account records, manager/customer restrictions and unsigned webhook rejection.
- Browser smoke/responsive suite: public and admin pages, customer dashboard, JavaScript errors and horizontal-overflow checks at 390px and 768px. Final run results are recorded in VALIDATION.md.
- Original dashboard updater: multipart upload installs the archive, records version/migration, preserves configuration/uploads/orders, opens the new setup page, rejects reinstallation and reports migration-aware rollback limits. PHP's existing cache revalidation delay was observed and allowed explicitly.

## Configuration needed on the hosted site

| Component | Implemented behavior | What remains to verify |
|---|---|---|
| Paystack | Hosted checkout, server verification, signed webhooks, refunds, mode-specific admin configuration | Enter your keys, confirm HTTPS/app_url, set both webhooks, run real test-mode checkout and a small authorized live payment/refund |
| Email | SMTP client, transactional queue, templates, retries and password recovery | Enter SMTP settings, test TLS/authentication and recipient delivery, configure the mail cron |
| Payment reconciliation | CLI retries for pending charges and submitted refunds | Configure cron; inspect actual Paystack pending/uncertain transactions |
| WhatsApp | Working manual Click-to-Chat and message templates | Set the business number. Automated WhatsApp API delivery is NOT implemented; it requires a selected provider/business account and approved templates |
| Maps/delivery | Saved customer/recipient addresses and flat delivery fee | Optional Maps keys in Connections are storage-only. Geocoding, route pricing, delivery zones and GPS/rider tracking are NOT implemented |
| Chef bookings | Inquiry/quotation/deposit flow, advisory availability and serialized confirmation checks | This is not an automated reservation engine or calendar-provider synchronization; operations still confirm a booking |
| Media | Upload/selection and local banner assets | Replace sample product photography and confirm ownership/permissions of all brand media |

## Remaining scope and operational limits

- Automated WhatsApp delivery, Maps/rider tracking, external calendar sync, SMS and provider-independent integrations cannot be verified or activated without provider details/accounts. Dormant optional API fields are explicitly labelled as storage-only rather than advertised as functioning connectors.
- Existing schema.sql is an early starter schema and omits tables used by the current application. The development fixture reconstructs those tables for tests; it is NOT a production migration. The update only creates its three new tables and does not guess at or overwrite your live schema. Test against your actual staging database before production.
- Email receipt/real SMTP delivery, live Paystack permissions, real webhook reachability, cPanel Apache rewrite/security rules and actual cron execution remain untested here. The built-in PHP router is not an Apache production test.
- The existing application's integration encryption is tied to app_key or db_pass. New secrets and per-transaction snapshots are encrypted with that mechanism; changing those root values can make existing secrets unreadable. Use proper private backups and a separate key-migration plan.
- An unknown gateway result is deliberately held for review if it cannot be safely resumed. Historical payments without saved context require their original legacy secret. An uncertain refund submission still requires Paystack dashboard review before a human resubmits anything.
- Dashboard order-value totals include test/legacy modes and are labelled accordingly; these are operational figures, not net accounting reports (partial refunds and deposits require reconciliation).
- Stock holds are time-limited and cannot guarantee physical inventory at late payment completion; insufficient stock is audited for manual resolution. Test orders remain visible for audit and must not be fulfilled.
- Existing sessions are not globally revoked when a password changes. Hosting-level controls, trusted reverse-proxy client-IP configuration, comprehensive penetration testing, email verification and formal accessibility/legal review remain separate work.
- The source archive includes an old nested application ZIP, renamed HTML export, installer, config example and many historical backups. The update does not delete these hosting files. Remove obsolete public downloads and the installer using cPanel after preserving a full backup; confirm storage/app/plugin access is blocked by Apache and directory listing is disabled.
- This is a trusted-code ZIP updater; owner access is intentionally powerful. Installation is not atomic across files and database DDL, and migration rollback requires the full files/database backup.
