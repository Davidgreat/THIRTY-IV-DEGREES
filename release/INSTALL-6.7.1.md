# 36° 6.7.1 — under construction and private preview

Install over 6.7.0 through Admin → Updates using `artifacts/thirtyvi-degrees-update-6.7.1.zip`. Back up first. No database migrations are included. **Construction mode starts enabled immediately after installation** unless an owner has already saved this setting.

## Public access

- The main website and its storefront/account/checkout endpoints show a standalone under-construction page to unapproved visitors. It links to the owner digital card and admin sign-in.
- The card remains public at **https://thirtyvidegrees.com/digital-card**, including its contact download.
- Login, logout and password recovery remain available. Existing authentication and admin permissions are retained.
- Signed-in admins/managers can browse the website normally. Customer login alone does not bypass construction mode.
- Existing Paystack webhooks and payment verification callbacks remain available for reconciliation. The closure does not initiate or cancel a charge.

## Owner controls

Sign in at `/login`, then open **Admin → Access & system → Under construction & preview** (`/admin/construction`).

- To launch: uncheck **Keep the main website under construction** and save. The public site goes live immediately.
- To close again: check the same setting and save.
- Edit the holding-page headline and message here.
- Set a custom preview code of 8–72 bytes, or click **Generate new preview code**. A generated code is shown once, with a copy button. No shared/default code is shipped in the public repository.

## Preview access

Visit **https://thirtyvidegrees.com/preview**, or expand “Have a private preview code?” on the holding page. Submit the code in the form. It opens the full site in that browser for eight hours and does not grant admin rights or sign in a customer.

The code is case-sensitive. It is submitted by POST rather than placed in a share URL. Only a password hash is saved in settings. Preview grants are session-scoped, expire and can be ended explicitly. Rotating the code or saving construction settings revokes earlier grants. Code guessing is rate-limited and all access-changing forms require CSRF tokens.

Private previews have a visible banner on normal storefront pages. `/preview` always provides preview access controls while construction mode is active. Construction and private preview responses are non-cacheable and non-indexable; the publicly available digital card retains its own metadata. The holding page uses HTTP 503 with Retry-After for temporary closure.

## Validation

Local tests cover automatic activation, deep-route and physical checkout isolation, public card/contact download, admin login/recovery/dashboard access, owner-only settings and CSRF, password-hashed codes, wrong-code rejection, rate limiting, browser isolation, expiry, explicit exit, rotation/revocation, launch/close controls and the payment route exceptions. The holding page was checked at 320, 390, 768 and 1440 pixels. The exact ZIP is also tested through the admin uploader, with configuration, existing credentials and records preserved.

No live site changes, real charges or emails were made from this workspace. Install the update to apply it to hosting.
