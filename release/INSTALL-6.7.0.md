# ThirtyVi Degrees — owner digital card 6.7.0

Install over 6.6.2. Back up first, then upload `artifacts/thirtyvi-degrees-update-6.7.0.zip` through Admin → Updates. Do not upload GitHub's full source archive. This update has no database migrations and preserves payment/email settings, customer data and uploads.

## Open and edit

After installation, share **https://thirtyvidegrees.com/digital-card**. A “Meet the chef · Digital card” link appears in the website footer.

Edit from **Admin → Website & content → Owner digital card** (`/admin/digital-card`). Only the owner administrator can edit it. Changes save with a visible confirmation and apply to the public page. Preview opens in a separate tab.

Editable information includes owner name, professional title, brand, PNG/JPG/WebP logo, tagline, introduction, closing invitation, availability text, both phone numbers and labels, email, website, WhatsApp message, email subject and social profile URLs. Offers can be added, reordered, removed and rewritten, up to ten. Empty optional phone/social fields stay hidden. Unpublishing disables both the page and downloadable contact and hides the footer link.

## Supplied information

- Tosin Olateru, Chef, ThirtyVi Degrees.
- Nigeria: +234 806 752 1752; United Kingdom: +44 754 346 4282. Both have call and WhatsApp actions.
- admin@thirtyvidegrees.com; https://thirtyvidegrees.com/.
- Instagram starts at https://www.instagram.com/thirtysixdegrees/ based on the supplied handle. Confirm the intended profile in the editor. Other platforms are optional and start empty.
- Grill & BBQ Services, Decadent Desserts, Exquisite Pastries, Private Chef Services, Bespoke Lifestyle & Retail Concierge Services, with the supplied descriptions.

The default logo is the site's existing official logo. Replace it in the card editor if the owner wants the alternative 36° artwork from the printed card. The website brand settings and card identity are separate.

## Experience

A standalone dark green and gold card includes a prominent logo, contact actions, social icons, expandable service offers, service-specific WhatsApp enquiries, native device sharing with copy-link fallback, and an international-format vCard contact download. Reduced-motion preferences are respected. No third-party script or external font is required, and no form collects visitor data.

Contact actions open the visitor's WhatsApp, dialler or email app; the site does not silently send a message or place a call. The vCard route is `/digital-card/contact.vcf`.

## Verification

Public/editor tests cover mobile and desktop layouts, contact destinations and prefilled text, downloadable contact and line folding, logo loading/uploads, service editing/reordering, sharing, unsafe URL/file rejection, escaped content, CSRF, owner-only access and unpublishing. The exact ZIP passed 44 isolated updater/preservation checks. Local browser checks do not prove that the supplied phone numbers or social account are owned by the business; no real calls/messages were made.
