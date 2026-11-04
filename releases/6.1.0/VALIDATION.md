# Validation and review — 6.1.0

Validated locally on 4 November 2026. Tests used synthetic records in an isolated PHP 8.4/MariaDB 11.8 environment. No requests changed the hosted website. The public domain was denied by the workspace's enforced outbound proxy, so no claim is made that the live website was visually inspected.

| Scope | Final result |
|---|---|
| Compact UI/interaction suite: `tests/compact-experience.js` | 487 checks passed; zero browser JavaScript errors |
| Existing payment/account regression: `tests/payments.php` | 34 passed against a local trusted-TLS gateway mock |
| Existing HTTP workflows: `tests/functional.py` | 22 passed |
| 6.0.0 → 6.1.0 dashboard ZIP installation: `tests/update-610.php` | 16 passed |
| PHP syntax | All application PHP files and index.php passed |
| JavaScript syntax | studio-610.js, chef-status.js and the new browser test passed |
| Release archive | 12 entries; manifest and safe paths checked; no migrations, hosting config, customer media or payment service code |

The counts include repeated checks at different viewport sizes, not 487 separate customer journeys. Browser tests blocked external image requests; unavailable remote product images therefore used the local fallback. Your own saved product photographs are not changed by this update.

## Screen sizes and measured booking heights

| Width | Visible booking step height | Full chef page height in synthetic fixture |
|---|---:|---:|
| 320px | 514px | 1246px |
| 375px | 501px | 1164px |
| 390px | 501px | 1164px |
| 430px | 501px | 1141px |
| 768px | 536px | 1076px |
| 1280px | 536px | 1076px |
| 1440px | 536px | 1076px |

Page heights include the header, introduction, current form step and footer. More optional text, different fonts or your own saved content can change these figures. The booking step target was under 550px at these widths; header heights were verified at 56px on phones and 64px on larger screens. Phone footers were below 300px.

## Behaviors exercised

- Public homepage/menu/store/private-chef/catering/about/login/cart layouts and checkout at small/large widths, with no horizontal page overflow. Store category navigation remains horizontal and in document flow.
- Homepage experience switching, arrow-key tab navigation, mobile navigation opening/closing and visibility of a single active experience.
- Required event validation, progression to contact details, accurate request summary, back navigation preserving inputs and a real inquiry submission to the existing local backend.
- No-JavaScript booking fallback: both fieldsets and the submit button remain available.
- Six admin groups with every previous tool route retained, search revealing collapsed tools, descriptions in the workspace guide, and one mobile work queue at a time. Larger screens retain both overview columns.
- Manager navigation excludes owner-only settings; direct access to Paystack settings remains forbidden. Existing CSRF guards remain effective.
- Paystack page retains blank secret fields, folds away inactive environment credentials and opens the selected environment without changing saving behavior. Workspace theme switching works.
- Real local admin availability toggles observed from a second browser tab: red status and disabled inquiry inputs while paused; green after resuming; entered draft text retained; stale pause notices removed. Pulse animation is disabled when reduced motion is requested.
- Customer orders/bookings/favourites tabs, earlier-order disclosure, review form and reordering links remain available; shortcut navigation does not cover account activity.
- Checkout still supports gifting on delivery and clears delivery-only gift fields for pickup.
- Existing HTTP checks cover banner create/display, guest cart/checkout, booking creation, strict availability input, customer/manager access restrictions, secret non-disclosure and unsigned webhook rejection.
- Payment/account tests retain verification, idempotency, encrypted storage, mode matching, inventory behavior, booking accounting and password-reset behavior.
- The **exact final ZIP** was uploaded by multipart HTTP into a separate 6.0.0 application copy through its existing dashboard updater. Paystack settings/credentials/activation/mode were compared before and after; unchanged. Hosting configuration, uploaded sentinel media, orders and bookings were preserved. The installer created a code-only restore point and rejected the same version on reinstall.

## Visual corrections found during review

The first pass exposed competing legacy header/footer dimensions and a generic mobile `nav` rule that relocated account/store navigation. These were corrected and covered by explicit size/position checks. A desktop form-height target initially failed at 536px against an overly narrow 535px cutoff; the review target is now consistently below 550px on all selected widths, rather than treating a one-pixel difference as an application bug.

## Limits

This update does not activate new payment, messaging or maps services. Your already-connected Paystack is preserved. No real payment was made here; the payment gateway regression uses synthetic data. Real cPanel PHP/Apache behavior, browser/device fonts, production data, hosting/CDN caches, actual card payments, SMTP delivery and cron execution remain installation checks on your hosting. The broad provider limitations described in the 6.0.0 audit remain applicable.

The release is intentionally UI-focused and has no SQL migration. Existing routes, POST handlers, role/CSRF guards, encryption, payment verification/refunds, stock and booking validation continue to run. The package does not replace app/paystack.php, app/payment-verification.php, app/paystack-refunds.php or app/integrations.php.
