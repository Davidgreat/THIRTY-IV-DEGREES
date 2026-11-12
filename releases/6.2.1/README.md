# 6.2.1 — Show or hide passwords

For websites running **6.2.0**.

**[Download the installable ZIP](thirtyvi-degrees-update-6.2.1.zip)**. On GitHub click **Download raw file ↓**, keep the ZIP unextracted, and upload it through **Admin → Access & system → Website updates**. Back up first and clear hosting/CDN cache after installing.

Login, registration and password reset now have a **Show password** checkbox. Check it to reveal the characters; uncheck it to mask them. The password starts hidden and is masked again on submission or when leaving/returning to the page. The control supports keyboard use and a 44-pixel label tap target. It is hidden when JavaScript is unavailable, leaving the normal masked field working.

Authentication fields now include password-manager/autofill hints. Registration and password reset show the existing minimum of 10 characters and validate that requirement in the browser. Existing login passwords are not subject to a new minimum-length rule.

No migrations or changes to authentication processing, payment processing, Paystack configuration, saved accounts or existing site features are included. Passwords are not stored by the visibility script or written to browser storage.

## Validation

- 60 Chromium checks passed: default masking, reveal/hide without value loss, keyboard toggle, autofill hints, short-new-password validation, light/dark layouts at 320/390/1440 pixels, checkbox tap target, successful login and registration while revealed, back-navigation masking and JavaScript-disabled fallback. Zero browser JavaScript errors.
- Exact release ZIP installed through the existing admin updater on a synthetic 6.2.0 fixture: 22 checks passed, preserving Paystack settings, hosting config, uploads, orders and bookings. Restore point created; duplicate install rejected.
- PHP and JavaScript syntax checks and git whitespace validation passed. Package bytes were compared with current source.

Tests ran on an isolated local PHP/MariaDB installation with synthetic accounts. The hosted website was not changed or tested. Payment regression tests were not repeated because payment code is unchanged.

[Mobile light-mode preview](login-light.png) · [Checksum](SHA256SUMS.txt)
