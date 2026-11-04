# 6.1.1 — Checkout payment hotfix

For the website already running **6.1.0**.

**[Download thirtyvi-degrees-update-6.1.1.zip](thirtyvi-degrees-update-6.1.1.zip)**. Open the ZIP on GitHub, click **Download raw file ↓**, and upload it through **Admin → Access & system → Website updates**. Keep the ZIP unextracted. Back up the website first; clear hosting/CDN page cache after installation.

## What changes

- A valid checkout now saves the order and opens hosted Paystack checkout directly when online payments are enabled. Customers no longer have to discover a second Pay button on the order-review screen.
- Invalid browser fields produce a visible error summary instead of silently blocking the button. Inputs inside a closed schedule disclosure are revealed.
- Server validation returns to an explicit error summary and retains contact, fulfilment, recipient, address, note and scheduling details. Correct the indicated field and continue.
- Checkout posts explicitly to `/checkout` and is marked private/no-store. Browser back-navigation restores submission buttons.
- Duplicate submissions with an emptied cart recover the existing saved order instead of beginning another checkout.
- A failed gateway initialization returns to the saved order with a readable, sanitized error and payment retry option, not to checkout/cart. Audit records identify the order and HTTP status without keys or payment details.
- A paused payment service clearly says it will save a request rather than promising a payment redirect.
- Schedule validation runs once; the earliest selectable scheduling minute is rounded forward so it is not already behind the required two-hour lead time.

This is a focused checkout patch. It does not redesign the website, reset Paystack keys, change activation/mode, modify existing orders, or run database migrations. Verification, refunds and integration encryption files are unchanged. Saved checkout drafts are cleared when an order is created or the user signs out.

## After installation

1. Check that your existing live mode and activation remain selected in Paystack Setup. You do not need to re-enter keys.
2. Fill in contact details and either a delivery address or pickup selection. For a gift, enter the recipient's details.
3. Select **Pay securely / Continue to Paystack**. A valid checkout should open Paystack.
4. If it cannot proceed, read the visible field/gateway error. Do not share secret keys or card details. A gateway failure leaves a saved order that can be retried without placing another order.

Your exact hosting-side trigger could not be confirmed remotely: the workspace cannot access the public domain, and no production logs were supplied. The silent-return paths and extra payment-review handoff were inspected and corrected locally. These changes also provide actionable feedback if a hosting or Paystack account issue remains.

See [VALIDATION.md](VALIDATION.md) for local evidence and limits; [SHA256SUMS.txt](SHA256SUMS.txt) contains the archive checksum.
