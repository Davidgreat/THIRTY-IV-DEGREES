# 6.4.0 — Microsoft email, test sending and clear confirmations

For a website running **6.3.0**.

**[Download the update ZIP](thirtyvi-degrees-update-6.4.0.zip)**. On GitHub choose **Download raw file ↓**, keep the ZIP unextracted and upload through **Admin → Access & system → Website updates**. Back up first, then clear hosting/CDN cache after installation.

## What the audit found

The previous SMTP client used password-based `AUTH LOGIN` over TLS, not Microsoft's OAuth2 modern authentication. The direct test-email endpoint existed but its button was missing from Email Delivery. Save confirmations were rendered outside the dashboard workspace.

This update adds Microsoft OAuth2 authorization-code sign-in with PKCE, delegated SMTP.Send/offline access, encrypted credentials and tokens, automatic refresh-token rotation, and SMTP `AUTH XOAUTH2` over verified TLS. Generic password SMTP remains supported. It adds a visible test-email form and places save/error feedback inside dashboard content, with keyboard focus and accessible status/alert roles. Delivery history starts collapsed to keep setup compact.

## Configure Microsoft email

Open **Customers & messages → Email delivery** (`/admin/email`). Choose **Microsoft modern authentication (OAuth2)**. Use the shortcut to fill the normal server settings, then verify them:

| Mailbox | SMTP hostname | Port | Encryption |
| --- | --- | --- | --- |
| Microsoft 365 work/school | smtp.office365.com | 587 | STARTTLS |
| Personal Outlook.com/Hotmail | smtp-mail.outlook.com | 587 | STARTTLS |

Enter the full mailbox address as SMTP username. Use the same From address unless Microsoft grants the mailbox Send As permission for another sender.

1. Register a **Web** application in Microsoft Entra with the intended supported account types. Add the **exact redirect URI displayed on Email Delivery**; on your normal site it should be `https://thirtyvidegrees.com/admin/email/microsoft/callback`. The displayed URI comes from your hosting `app_url`, not an untrusted request header. Correct an incorrect website URL in hosting configuration first.
2. Add delegated **Office 365 Exchange Online → SMTP.Send** permission. The platform requests `offline_access` during sign-in so the cron worker can renew authorization. Organizational policy may require administrator consent and Authenticated SMTP to be enabled for the mailbox/tenant. OAuth does not bypass those policies.
3. Create a client secret. In the platform enter the **Application (client) ID**, the **secret value** (not secret ID), and the appropriate tenant: your directory tenant GUID for a single-tenant work/school app; `organizations` for supported multitenant work/school apps; `consumers` for personal-only accounts; or `common` for an app explicitly registered to support both organizations and personal accounts.
4. Click **Save email settings**. The success message confirms storage/application of settings, not proven mailbox delivery.
5. Click **Connect Microsoft**, sign in as the configured mailbox, and approve the requested access. The platform then confirms authorization was saved. No Microsoft password is stored by this sign-in flow.
6. Enter a recipient and click **Send test email**. A successful result means the SMTP server accepted the message; check inbox and spam. Failure messages guide you toward connection, consent, Authenticated SMTP, sender/recipient or Send As issues.

A real Microsoft mailbox and app registration still need to be connected by the owner. No real account authorization was performed during development. Enter secrets only in your admin panel, never in chat or GitHub.

## Generic SMTP and automatic delivery

For cPanel mail or another password-SMTP provider, select **SMTP username & password**, use the provider's SMTP host/port/TLS settings and save the password. Leave secret inputs blank to retain existing saved values. Test sending is immediate and does not need cron.

Order, booking and recovery emails still use the queue. Keep the cPanel CLI cron worker configured, for example:

```text
/usr/local/bin/php /home/CPANEL_USER/public_html/app/cron-mail.php
```

Use the PHP binary/path appropriate for your hosting account. The existing worker refreshes Microsoft authorization automatically. Renew app secrets before expiry; revoked consent or expired/rejected credentials require reconnecting. Changing authentication-critical settings invalidates saved authorization. Disconnect removes platform tokens; revoke Microsoft consent separately if required.

Saving configuration or replacing authorization clears the prior test result so an old success cannot imply a new configuration was verified. Server acceptance is not guaranteed inbox delivery. Failed queue messages can be retried from the expanded delivery history after fixing the underlying issue.

## Preservation and references

No database migrations are included. Existing SMTP/Paystack settings, users, orders, bookings and uploads are preserved. Payment, verification, refunds and checkout-submission modules are unchanged. No hosted deployment or real email sending occurred.

[Mobile light preview](email-light.png) · [Mobile dark preview](email-dark.png) · [Validation](VALIDATION.md) · [Checksum](SHA256SUMS.txt)

Microsoft references:
- [OAuth for SMTP and other legacy mail protocols](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth)
- [Authenticated client SMTP submission controls](https://learn.microsoft.com/en-us/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission)

Tenant policy and account permissions remain Microsoft's responsibility; the platform implements the OAuth SMTP flow and reports the resulting test outcome.
