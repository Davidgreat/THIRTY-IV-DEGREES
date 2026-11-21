# 6.7.2 — digital card label cleanup

Install over 6.7.1 via Admin → Updates using `artifacts/thirtyvi-degrees-update-6.7.2.zip`. Back up first.

Removes the circled “THE RESTAURANT” header label. A source search found one instance of this header label, on the shared digital card page. The 36° brand/home link remains, with an accessible name. Other text referring to normal operational restaurant actions is unchanged.

The package replaces only `app/digital-card.php`. It preserves the uploaded logo, edited contact details and offers, preview codes, construction settings and all existing records. No migrations.

PHP lint and local page rendering passed. Confirmed the label is absent while the owner card and contact actions remain present. Verified the archive matches the runtime file.
