# Thirthy Vi Degrees v2

## Existing installations
The original v1 release has no admin ZIP updater. For this one-time upgrade, back up your entire cPanel site AND MySQL database, then upload the **contents of the v2 ZIP** over the existing application files. Preserve your existing `config.php`, database, and uploaded data. Do not run the installer again.

After upgrading, visit `/admin/updates` for future update ZIPs and `/admin/plugins` for plugin upload/enable/disable.

## Fresh installations
Upload the contents of the `thirtyvi-v2` folder to the domain document root and run `/install/`.

## Future updates
A valid update ZIP must contain `update.json` with `product` set to `thirtyvi-degrees` and `version`, and may contain `app/`, `public/`, `index.php`, and `router.php`. Updates back up replaced files to `storage/.backups`. No automatic database migration or rollback yet.

## Security
Only upload trusted PHP plugin/update packages. The installer executes code with your hosting account privileges. The update system requires PHP ZipArchive and writable app directories. Always back up your database separately.
