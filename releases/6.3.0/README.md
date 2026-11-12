# 6.3.0 — Owner security, categories and product galleries

For a website running **6.2.1**.

**[Download the update ZIP](thirtyvi-degrees-update-6.3.0.zip)**. On GitHub click **Download raw file ↓**. Keep it zipped and upload through **Admin → Access & system → Website updates**. Take a full files-and-database backup first, then clear hosting/CDN cache after installation.

**After installing, sign in again with your existing owner email and password.** This update requires fresh owner authentication once. Customer and manager sessions are unaffected by that requirement.

## Change your own admin login details

Open **Access & system → My account & security** (`/admin/security`).

- To change your email, enter the new address twice and confirm your current password.
- To change your password, enter the current password, then the new password twice. Use at least 10 characters; very long passwords may need shortening.
- Show-password checkboxes work on each password field.
- Successful changes sign out your owner sessions, including other devices when they next make a request. Sign in with the new details.
- Duplicate emails, incorrect current passwords, mismatched confirmations and short new passwords are rejected. Old recovery links are invalidated. Audit records contain the action, not passwords.

This changes only the signed-in owner's account. It does not edit another owner's account, the public business email, SMTP settings, Paystack details, orders or bookings. No email-delivery setup is required to save these changes; use an inbox you control for future recovery.

## Create and manage categories

Open **Menu & stock → Menu categories** (`/admin/categories`) to create or rename categories. Enable **Also show in 36° Store** when appropriate. Existing Store category placements are preserved until you change them. Categories remain available in the food menu.

You can also open **Create a new category** inside **Add/Edit Meal**. Enter a name and save the meal: the category is created and assigned in the same transaction. Leave it blank to use the selected existing category. Duplicate categories are rejected.

## Add multiple product photos

In **Add/Edit Meal**, keep a cover photo for menu cards and open **Additional product photos** for up to seven extra views.

- Upload several JPG, PNG or WebP files together, or enter image URLs/media-library paths.
- Each uploaded file must be a valid image of up to 6 MB. Large batches may need separate saves to fit your hosting upload limit.
- Add descriptions, change display order, or mark an existing view for removal, then save.
- Buyers tap thumbnails or use keyboard arrows to switch the main image. Without JavaScript, each thumbnail remains a link to its full image.
- Removing a gallery view does not delete its media file or affect other products. An older cached editor submission preserves the existing gallery.

Decimal prices are supported. Long product names and galleries fit phone screens.

## Database and previews

The package adds `product_gallery` and `category_channels` tables. It does not alter existing user/product columns or payment tables. Because it includes a migration, the updater's automatic code-only rollback is unavailable for this release; use your full backup if restoration is necessary.

[Owner security](previews/security-light.png) · [Meal editor](previews/product-light.png) · [Categories](previews/categories-light.png) · [Buyer gallery](previews/gallery-mobile.png)

Previews use synthetic local accounts and products. See [VALIDATION.md](VALIDATION.md) and [SHA256SUMS.txt](SHA256SUMS.txt). The live website was not modified.
