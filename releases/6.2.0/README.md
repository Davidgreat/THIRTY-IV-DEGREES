# 6.2.0 — The 36° Atelier experience

For a website running **6.1.2** with working Paystack payments.

**[Download the installable update ZIP](thirtyvi-degrees-update-6.2.0.zip)**. On GitHub choose **Download raw file ↓**, keep the ZIP unextracted, then upload through **Admin → Access & system → Website updates**. Back up files and database first. Clear hosting/CDN cache after installation, then refresh the browser. Do not upload the repository source ZIP.

## What changes

- A gold italic hero phrase rotates smoothly every 3.6 seconds through six shuffled phrases: gathering for, craving, celebrating, sharing, savouring, and coming home for. Each cycle includes all six. A pause button, hidden-tab pause and reduced-motion support keep movement optional.
- Warm ivory and deep green palettes, translucent glass panels, fine borders, soft shadows, pill buttons and photographic hover details extend across storefront, booking, checkout and dashboard components.
- Dark photographic banners retain readable pale text in both themes. Inputs and content panels follow the selected light/dark palette.
- A new editorial hospitality section and brief section entrances add personality without scroll interception or content hidden behind animation.
- Checkout removes the public software-version/mode label. On phones the order recap starts collapsed and delivery/contact details progress in short disclosures. Optional notes, gifting and scheduling remain available. Desktop disclosures start open; all fields remain available without JavaScript.
- Private-chef and catering bookings retain the existing two-step form, real availability check and chef-status indicator.

No migrations, configuration resets or payment service changes are included. The server order-creation code, physical checkout endpoint, submission controller, verification, encrypted credentials and refund modules match 6.1.2. Paystack credentials and activation are preserved. The internal endpoint probe continues to identify payment build 6.1.2; the installed update version is 6.2.0 in Website updates.

## Preview

Previews use synthetic test data; product images and promotions on your site remain your own.

- [Desktop homepage](previews/home-1440-dark.png)
- [Mobile homepage in light mode](previews/home-390-light.png)
- [Compact mobile checkout](previews/checkout-390-light.png)
- [Private-chef booking](previews/private-chef-390-dark.png)
- [Owner dashboard](previews/admin-1440-light.png)
- [Customer dashboard](previews/account-390-dark.png)

After installing, confirm hero rotation/pause, light-mode readability on bag and checkout, mobile contact progression, and your existing payment settings. See [VALIDATION.md](VALIDATION.md) for evidence and limits. The website itself and cloud environment were not published by this update.
