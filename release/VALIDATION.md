# Validation — release 6.0.0

Validated 4 November 2026 against synthetic local records. No commands targeted the hosted cPanel site. No actual customer data, production database or real Paystack credentials were used.

| Check | Final outcome |
|---|---|
| PHP syntax: all application PHP files and index.php | Passed |
| JavaScript syntax: studio-600.js and vi-motion-5718.js | Passed |
| Payment/account tests (`tests/payments.php`) | 34 passed |
| HTTP workflows (`tests/functional.py`) | 22 passed |
| Chromium/Playwright (`tests/browser-smoke.js`) | 60 checks passed, zero browser JavaScript errors |
| Original admin ZIP updater (`tests/update-install.php`) | 10 passed |
| Release archive inspection | 14 entries; manifest and safe paths verified; no config.php, uploads, backups, credentials or test fixtures |

Total: 126 behavioral/browser/installer checks passed, in addition to syntax and archive inspection. These counts are checks with different scopes, not 126 independent end-to-end customer journeys.

## Environment and executed commands

PHP 8.4, MariaDB 11.8, Debian trixie, PDO MySQL/cURL/OpenSSL/mbstring/fileinfo/ZipArchive, and system Chromium via Playwright. Test schema: original schema.sql plus tests/dev-schema.sql and the additive release migration, in disposable database `thirtyvi_dev`.

The application copy ran in Docker container `thirtyvi-dev` at `/srv/site` using `php -S 0.0.0.0:8086 router.php`. The untouched original platform was copied to `/srv/original` and served separately at 127.0.0.1:8087 for multipart update tests. Original-updater testing used the exact final ZIP, not a different build. Both copies use synthetic local configuration.

Commands executed:

```sh
# In the development container, from /srv/site:
find app -name '*.php' -exec php -l {} \;
php -l index.php

# A test-only CONNECT/TLS server implements Paystack responses on port 9080.
# Its dedicated certificate is trusted explicitly; client TLS verification stays enabled.
HTTPS_PROXY=http://127.0.0.1:9080 https_proxy=http://127.0.0.1:9080 \
  php -d curl.cainfo=/tmp/paystack-mock.pem tests/payments.php

# In the workspace repository:
python3 tests/functional.py
node tests/browser-smoke.js
node --check public/assets/studio-600.js
node --check public/assets/vi-motion-5718.js
python3 release/build.py

# In the container, with the original server running and ZIP staged in /tmp:
php /tmp/update-install.php
```

The mock certificate/key and gateway state are container-local test fixtures, not distributed in the update. Browser tests block external requests to expose dependency problems; local storefront imagery and meal-image fallbacks are therefore exercised. Screenshots force lazy images to load before capture. Mobile pages were checked at 390px and 768px, with no horizontal page overflow. Admin payment settings were also checked in light theme. The desktop viewport was 1440px.

## Diagnosed and resolved during testing

- Shared gateway functions initially depended on audit() being declared only in index.php, breaking CLI tests/reconciliation. Moved audit() into the shared core and reran payment/CLI tests successfully.
- Banner POST handlers redirected to a nonexistent page. Implemented and linked the page; HTTP create/display and browser navigation passed.
- Old mobile hero aspect-ratio rules forced a 1008px hero onto a 390px viewport after redesign. Removed conflicting layout behavior; both mobile widths passed on the final run.
- The original uploader does not invalidate OPcache, so the first post-update request can briefly serve old code. The installation test explicitly waited for its cache revalidation and then passed. Future updates gain explicit opcode invalidation in the updated installer.

## Not validated against external services

Real Paystack test/live API permissions, cards, refunds and externally delivered webhooks; real SMTP authentication/inbox delivery; cPanel Apache/PHP compatibility; cron execution; production schema/data; automated WhatsApp and Maps/tracking integrations. See INSTALL-6.0.0.md and AUDIT-6.0.0.md before deployment. A local TLS mock passing is not proof that these external systems are configured.
