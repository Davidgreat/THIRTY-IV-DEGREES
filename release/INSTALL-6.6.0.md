# 36° update 6.6.0

Requires the installed 6.5.0 update. Download `artifacts/thirtyvi-degrees-update-6.6.0.zip` from this branch, then upload that ZIP through Admin → Updates. Do not upload GitHub's whole source-code archive.

Take a full cPanel backup of files and database first. This update adds customer, wallet and chat tables. The automatic code-only restore feature intentionally cannot reverse database migrations. Configuration, payment/email credentials, orders and uploaded media are preserved.

Existing customers sign in again once after installation. The new account security stamp also signs out other customer sessions when a password or login email changes.

## Where to find things

- Customers: My Account opens the standalone dining dashboard. It includes order details and history, booking details, saved dishes, default delivery addresses, account settings, support requests, wallet and concierge chat.
- Owner: Customer experience → Customer chat. Use **Pause customer chat** or **Enable customer chat**. Pausing stops new messages, reactions and typing updates for both customers and staff; conversation history remains readable. Saving displays confirmation.
- Owner: Staff access → enable **Use customer chat** for individual managers. Managers cannot change chat availability.
- Owner: Customer requests contains return, cancellation and refund review requests and team replies. Requests do not automatically refund money. Approved refunds still use the existing owner refund workflow.
- Owner: Payments & reports → Customer wallets shows balances, ledger entries and top-up verification controls.

## Payments and wallet

Keep Paystack enabled with the intended test/live keys. Top-ups open Paystack's hosted payment page; the server verifies reference, amount, currency and environment before crediting the ledger. Configure Paystack's existing webhook URL `/payment/webhook` as before. The customer can also check a pending top-up from their wallet history.

Test and live balances are separate. Test funds never become live funds. Wallet checkout uses server-calculated order totals and booking deposit/balance amounts. A request with an existing card checkout cannot silently switch to wallet. Wallet refunds return to the original payment's test/live wallet. This version does not provide withdrawals.

Keep the existing cPanel mail cron running. Welcome messages, payment notifications and wallet receipts use the delivery queue. “Sent” means the SMTP server accepted the message; it does not prove inbox delivery. For the welcome email already marked sent, check Spam, the business mailbox for bounces and cPanel SPF/DKIM. Account settings provides a rate-limited welcome resend.

## Chat operation

Chat checks for messages about every three seconds while open and visible, using ordinary cPanel PHP hosting. It includes dated messages, emoji selection, reactions, seen receipts, unread badges and expiring typing indicators. It does not send browser push notifications or provide attachments/voice calls. Staff must open the chat to reply.

## Validation

The exact ZIP installed through the 6.5.0 admin uploader in a separate fixture: 38 checks passed, including migration creation and preservation of configuration, media, orders, bookings and payment/email settings. Existing payment regression suite: 34 passed. Customer/wallet integration: 44 passed. Chat integration: 37 passed, including owner pause and manager permissions. Independent parallel PHP workers could not overspend one wallet; booking deposit/balance and existing card conflict checks passed. Responsive customer pages were checked at 320, 390, 768 and 1440 pixels in both themes. All transactions and mail tests used synthetic local fixtures; no live charges or customer emails were sent.
