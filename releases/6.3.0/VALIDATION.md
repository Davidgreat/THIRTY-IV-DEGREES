# Validation — 6.3.0

Checks used a disposable local PHP 8.4/MariaDB 11.8 application with synthetic accounts, meals, categories and uploads. No production data or financial charges were used.

- 54 integration/browser checks passed: current-password verification, email uniqueness/confirmation, password validation, successful email/password login changes, revocation of other owner sessions, reset-token invalidation, hashed passwords, safe audit records, owner-only routes, categories and Store placement, inline category creation, gallery ordering, decimal prices, thumbnails/keyboard, 320/390/1440 pixel layouts, multiple uploaded images, disguised/unsafe-image rejection, failure preservation, cached-editor preservation, image removal and show-password controls. Zero browser JavaScript errors.
- 36 layout checks passed across security, categories and the meal editor in both themes at 320/390/1440 pixel widths; screenshot review completed.
- Existing HTTP functional suite: 22 checks passed, covering guest ordering, bookings, payment settings, CSRF and role authorization. Accumulated synthetic banner fixtures were disabled before running this suite so the existing three-banner limit did not block setup.
- Exact final ZIP installed through the admin updater on a 6.2.1 fixture: 27 checks passed. Existing owner credentials, Paystack settings, configuration, customer uploads, orders and bookings preserved; legacy owner session forced to reauthenticate; new pages and assets available; migration recorded once and duplicate installation rejected.
- Additive migration ran twice without error on an empty disposable database, creating both tables idempotently.
- PHP/JavaScript syntax and git whitespace checks passed. Final ZIP bytes were compared against runtime source. Gateway, verification, refunds, credential encryption, checkout endpoint and checkout submission controller match 6.2.1.

Authentication handling changes only owner session binding; existing guest/customer ordering and manager access checks passed. A new real Paystack transaction was not performed. The hosted domain was not inspected or deployed to; deployment and a normal hosted checkout remain the owner's final confirmation.

Build with `python3 release/build-630.py` against baseline commit `abb14fa`. The package includes changed runtime files plus `migrations/630_catalogue.sql`; credentials, configuration, storage and uploads are excluded.
