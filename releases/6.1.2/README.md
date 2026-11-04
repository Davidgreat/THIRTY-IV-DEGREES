# 6.1.2 — Checkout submission repair

For a website running **6.1.1**. Back up files and database before updating.

Download [thirtyvi-degrees-update-6.1.2.zip](thirtyvi-degrees-update-6.1.2.zip). Keep it zipped and upload through **Admin → Access & system → Website updates**. Clear hosting/CDN cache afterwards. Do not upload the repository ZIP as an update.

This release gives checkout one submission controller and a physical PHP endpoint, avoiding redirects on the `/checkout` page route. The endpoint confirms order creation before the browser opens Paystack. Fresh session tokens, explicit errors and a saved-order recovery link replace silent reloads. Changing test/live mode while a checkout page is open requires refreshing before submission.

Paystack credentials and activation settings are preserved. No database migrations are included. Payment verification, refunds and credential encryption modules are unchanged. Existing website design remains in place.

## Check after installing

1. Checkout must display **Checkout 6.1.2** and the correct test/live mode.
2. Open `/public/checkout.php?probe=1` on your website. It should return JSON with `build: "6.1.2"`, rather than redirecting to checkout. This probe exposes no credentials or session token.
3. In test mode, add a meal, complete contact details, choose delivery with an address or pickup, then continue to Paystack.
4. If it fails, retain the visible error and checkout reference. The owner can open **Payments & reports → Checkout diagnostics** (`/admin/checkout-health`) to inspect missing database fields and recent request stages. Share the reference, stage and numeric error codes; never send keys, card details or customer information.

The hosted cause has not been confirmed. Local tests reproduce a hosting redirect that loses the checkout POST, and verify the new endpoint avoids it. Database or hosting failures have explicit diagnostics so remaining causes can be identified. These checks do not establish that live payments work on your hosting.

See [VALIDATION.md](VALIDATION.md) and [SHA256SUMS.txt](SHA256SUMS.txt).
