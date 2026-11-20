# 36° wallet recovery hotfix 6.6.1

Install over 6.6.0 using Admin → Updates. Download `artifacts/thirtyvi-degrees-update-6.6.1.zip`; do not upload GitHub's whole source archive. Take a full cPanel backup first. No database migrations or credential changes are included.

## Recover an already-debited top-up

Do not pay again. After installing, open My Account → Wallet and use **Check status** beside the original WAL- reference. The server verifies that existing transaction with Paystack and credits it once if Paystack confirms the original reference, amount, NGN currency and live/test mode. The wallet page also checks pending payments automatically while open. The owner can verify from Admin → Customer wallets.

“Live” beside a top-up is its original payment environment, not confirmation of wallet credit. Test and live balances stay separate, even if the owner later switches modes.

If verification still fails, open Admin → Customer wallets → Latest verification diagnostic and share its reference, stage, type and error codes with the developer. Do not share keys or passwords. These codes deliberately exclude raw SQL, provider responses and credentials. A receipt diagnostic is historical and does not mean that credited funds were reversed.

## What changed

- Receipt enqueueing runs after the verified wallet transaction commits. A receipt failure cannot undo funds. Missing receipts retry separately.
- Wallet verification no longer requires PHP's optional `ctype_digit` function.
- The existing `app/cron-mail.php` cron also reconciles pending top-ups in bounded, round-robin batches. It makes verification calls, never new charges. Mail infrastructure failures do not prevent that reconciliation.
- While the wallet is open and visible, an authenticated, CSRF-protected check runs after three seconds and then about once per minute. A confirmed credit refreshes the displayed balance automatically.
- Failed credit application rolls back safely and produces a wallet-specific recovery message and protected diagnostic instead of the generic “change could not be saved” message.
- Original reference, amount, currency, historical key and test/live checks remain enforced. The ledger key and row locks prevent duplicate credit across callbacks, webhooks, cron and manual checks.

## Evidence and limits

6.6.0's rollback was reproduced with a synthetic receipt-queue rejection; a missing optional ctype extension also reproduced the hidden error. These are confirmed code defects, not a diagnosis of the specific live hosting error without its PHP log. No live transaction was queried or manually credited from this workspace.

Local validation: 11 wallet recovery/failure tests passed, 44 customer/wallet regression checks passed, 4 browser recovery checks passed (including a synthetic live-mode payment and mobile layout), and 38 exact-ZIP installer/preservation checks passed. All provider traffic used a local TLS fixture and fake keys; no real charges or emails were made.
