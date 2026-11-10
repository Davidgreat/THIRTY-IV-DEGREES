# Validation — 6.2.0

All execution used a disposable local PHP 8.4 / MariaDB 11.8 installation and synthetic records. No production data or actual financial charges were used.

- Responsive browser suite: 165 checks passed across 320, 390, 768 and 1440 pixel widths, both themes, eight public routes, shuffled phrase cycling, pause/reduced-motion, compact booking, and removal of checkout build copy. The later checkout disclosure change is covered separately below.
- Settled-style screenshot review: 69 checks passed across public pages, checkout, owner workspace/payment settings and customer account in both themes at phone and desktop widths. Theme transitions were allowed to finish before colour assertions/screenshots. After adding checkout disclosures, focused checkout review passed another 13 checks.
- Compact checkout browser handoff: 14 checks passed, covering phone/desktop in test and live configurations against the local TLS mock. Each case saved exactly one order and separately navigated to a synthetic hosted checkout. This is not a real live Paystack transaction.
- Existing HTTP functional suite: 22 checks passed for guest ordering, booking, payment settings, CSRF and authorization.
- Payment regression: 34 checks passed against a local TLS gateway with certificate verification: encrypted credentials, mode/amount/reference matching, idempotency, stock and signed webhooks.
- Final 40,188-byte ZIP installed through the dashboard uploader on a 6.1.2 fixture: 22 checks passed, preserving payment settings, config, uploads, orders and bookings. Code-only restore point created; duplicate update rejected; new assets installed.
- PHP/JavaScript syntax and git whitespace checks passed. Archive contents were compared byte-for-byte with the runtime source. Server processing before the checkout page renderer and payment/endpoint/controller files match commit d05db5f.

The hosted site was not inspected or modified. This environment's domain policy does not include the public website. Screenshots demonstrate the local fixture, not the customer's actual inventory or hosting. Download and install the exact release ZIP; deployment and a normal hosted checkout check remain the owner's final verification.
