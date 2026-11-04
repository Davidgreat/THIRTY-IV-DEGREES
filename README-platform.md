# THIRTY VI DEGREES — cPanel PHP/MySQL Starter

A working, extensible single-brand restaurant web application with an installation wizard, customer accounts, meal ordering, private chef and catering inquiries, Paystack transaction verification, content management, and a protected admin panel.

## Requirements
- Apache with `mod_rewrite`, PHP 8.1+ (8.2+ recommended), extensions `pdo_mysql`, `curl`, `mbstring`, `openssl`, and MySQL/MariaDB.
- HTTPS on production. A dedicated empty MySQL database and database user.

## Install on cPanel
1. Back up your domain's current files and database.
2. Upload the **contents** of this ZIP into your domain's `public_html` or document root. Do not upload the ZIP's enclosing folder unless you intend to use a subdirectory.
3. In cPanel > MySQL Databases, create an empty database, database user and grant it all privileges on that database.
4. Visit `https://thirtyvidegrees.com/install/` and enter your database details and admin account information.
5. Log in at `/login`; admin dashboard is `/admin`.
6. Edit `config.php` on the server to set `paystack_secret` to your **test** secret key and `app_url` to `https://thirtyvidegrees.com`. Never paste live keys into a public repository.
7. Test a real Paystack test-mode payment. Configure Paystack webhook URL `https://thirtyvidegrees.com/payment/webhook`.
8. Replace sample meal images, product descriptions, prices, legal pages and business details in the admin panel before going live.
9. Remove the `/install` directory after installation. Ensure `.htaccess` is honored. Disable directory listings.
10. Switch to Paystack live credentials only after completing business verification and full acceptance/security testing.

## Local development
- Create a MySQL database manually.
- Run `php -S localhost:8000 router.php` from project root.
- Open `http://localhost:8000/install/`.
- On local environments, Paystack callback requires a publicly reachable HTTPS address or suitable testing tunnel.

## Extending with plugins
A plugin is a directory in `/plugins` with `plugin.json` and `bootstrap.php`. See `/plugins/example-greeter`. The manifest must have `enabled: true` to execute. Hooks are dispatched after order creation, booking creation and successful payment verification. Install **trusted** plugins only: plugin PHP runs with application permissions. Do not expose plugin files to the web; Apache blocks direct access.

## Included features
- Responsive premium homepage and product catalog
- Category filters, meal detail, special instructions, cart and checkout
- Orders with delivery/pickup and customer order details
- Private chef/catering inquiries and basic slot conflict check
- Admin order status, products, booking quotations, customers, payments, reviews, page content, settings
- Registration/login with hashed passwords, CSRF tokens, prepared SQL statements, session regeneration
- Paystack hosted checkout, backend verification and signed webhooks
- Audit records and basic plugin lifecycle events

## Important limitations / production checklist
This is a **functional MVP foundation**, not a fully audited enterprise release. The following require additional implementation or validation before commercial launch:
- Reliable transactional email/SMS/WhatsApp delivery, password-reset email, customer email verification.
- Booking holds and database-level concurrency protection; the current availability check is advisory for inquiries, not a guaranteed real-time reservation engine.
- Comprehensive order status transition rules, refunds, tax calculation, dynamic delivery zones, stock deduction, coupon codes, product variants, scheduled orders and rider tracking.
- Staff role provisioning, fine-grained permissions, media upload pipeline, detailed inventory, full analytics and marketing tools.
- User-facing payment retry reconciliation and more complete deposit accounting / notifications.
- Web application firewall, stronger server-side rate limiting, secure cookie/session settings, CAPTCHA, audit review, backup/restore tests, security penetration testing and accessibility review.
- Demo content and stock photography are placeholders and must be replaced with brand-owned/appropriately licensed assets.
- The simple plugin hook system is not a sandbox. Treat every installed plugin as trusted server code and review it carefully.

**Never test unknown or nulled plugins on production hosting.**
