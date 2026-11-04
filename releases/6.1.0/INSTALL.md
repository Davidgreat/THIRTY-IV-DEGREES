# Install 36° Compact Experience — 6.1.0

For the website already running the 6.0.0 update. This is a code-only update: no database migrations, credential resets, destructive queries or changes to payment gateway logic.

1. Take a full cPanel file/database backup. If available, install on a staging copy first.
2. Download **thirtyvi-degrees-update-6.1.0.zip** from this GitHub folder using **Download raw file**. Do not use GitHub's “Download repository ZIP”, and do not extract the update archive.
3. Open your existing website admin dashboard → **Site Updates**, upload this exact ZIP and select **Install Update**.
4. Clear any hosting/CDN page cache and refresh the website. The new stylesheet has a new filename/version, so a newly loaded page will not use the oversized 6.0.0 layout.
5. Open **All tools & quick guide** in the new admin navigation. Your existing tools are grouped under six sections; existing page URLs still work.
6. Check the homepage, menu, store, private-chef/catering inquiry form, customer account and checkout on a real phone and desktop. Paystack Setup should show your existing saved keys, activation and selected environment. You do not need to enter them again.
7. To test the chef status: **Website & content → Brand & service settings**, pause inquiries, then open the homepage/private-chef page in another tab. The signal becomes red and the inquiry form is disabled. Resume inquiries to return it to green. Public status refreshes about every 20 seconds while the tab is visible and when returning to it. Colours also have explicit text; reduced-motion users receive a static signal.

## What changes

- Compact homepage hero, interactive meal/chef/catering selector, smaller two-column mobile meal cards, short banner rails and a smaller header/footer.
- Private-chef/catering inquiries use two short steps: event essentials, then contact details. Optional preferences are folded away. Going back preserves typed information. Without JavaScript the full form remains usable.
- Six admin menu groups, descriptions of every tool, searchable navigation and an “All tools & quick guide” page. The overview focuses on daily priorities; phones switch between kitchen and booking queues.
- Customer account tabs for orders, bookings and saved favourites, with earlier orders folded away. Reordering and review submission remain available.
- Green pulsing chef-active signal and red pulsing chef-paused signal. This is a visual pulse, not an audible alarm. Paused inquiries cannot be submitted; the existing server-side availability checks still apply.
- Paystack's inactive credential section is collapsed to shorten the page. Switching environment reveals the matching section. Saving behavior and encrypted credentials are unchanged.

## Admin menu map

| Group | Tools |
|---|---|
| Orders & bookings | Order management, chef/catering inquiries, availability calendar |
| Menu & stock | Products, categories, meal choices and stock control |
| Payments & reports | Transactions, Paystack setup, refunds and sales reports |
| Website & content | Brand/service settings, slideshow, banners, media and information pages |
| Customers & messages | Customer accounts, WhatsApp, email delivery/templates and reviews |
| Access & system | Staff permissions, other connections, plugins and website updates |

Managers see only the existing operational tools permitted by their role. Owner-only pages remain protected by the server.

## Existing features and settings

The package does not include or alter config.php, customer uploads, the database, Paystack service code, payment verification/refund code, encryption helpers, cron workers or your saved settings. Existing custom homepage slides, banners, products, stock, orders, bookings, gift checkout, scheduling, favourites, tracking, communications, reviews and plugin/update routes are retained. Missing remote photographs use an existing local image as a browser fallback; product records are not overwritten.

## Rollback

This release creates a code-only restore point in your existing updater. If necessary, use **Access & system → Website updates** to restore the pre-6.1.0 code. The restore-point list belongs to your hosting account; confirm the selected point is “before 6.1.0”. Restore does not change orders, bookings, payments or saved credentials. Keep the full cPanel backup as the independent recovery option.

The live public website could not be inspected from this workspace because its outbound proxy denies the domain. Validation used the exact 6.0.0 code you installed and synthetic local records; your hosting, content and browser caches can still affect production appearance.
