# Export, import, reset and uninstall

All of these are on the **Tools** tab.

## Export

**Download export file** saves your setup as a JSON file: every custom field, every change to WooCommerce's fields, and your style settings. It contains no customer or order data.

The file is signed with a key unique to your site, so the plugin can tell whether a file came from this store unchanged.

## Import

1. Click **Choose an export file** and pick a file.
2. You see what the import will do before anything changes:
   - **Added**: fields in the file that aren't on your site.
   - **Changed**: fields on both, with different settings.
   - **Removed**: custom fields on your site that aren't in the file. WooCommerce fields not in the file go back to their defaults.
3. Click **Import**.

An import replaces your whole setup with the file's. It's applied all at once: if anything in the file is invalid, nothing changes and you see what's wrong.

A file exported from another site, or edited by hand, isn't signed by this site. You then have to tick a box confirming you trust the file before you can import it.

Limits: files up to 1 MB and 500 fields. Custom CSS in a file is only imported if you're allowed to edit custom CSS (`edit_css`).

## Reset everything

**Reset everything** removes all custom fields and all changes to WooCommerce's fields, and restores the default style. It asks for confirmation first. Values saved on past orders are kept.

Export first if you might want the setup again.

To reset one section only, use **Reset section** in the editor.

## When the plugin is deleted

**Delete all data when the plugin is deleted** is off by default.

- **Off**: deleting the plugin from the Plugins screen keeps your fields and settings, so reinstalling brings them back.
- **On**: deleting the plugin also removes its database tables, its settings, its cached data, its scheduled clean-up, and its capability from every role.

Either way, values on past orders are kept, because they're part of your store's records. Deactivating the plugin never removes anything.

Only users who can delete plugins can change this setting. On multisite, each site's own setting decides what happens on that site.

## System info

**Show system info** lists the versions of the plugin, WordPress, WooCommerce, PHP and the database, your theme, which checkout you use, your active plugins, and the keys and types of your fields. **Copy system info** copies it, ready to paste into a support topic or bug report.

It contains no site address, no customer or user data, and none of the labels or choices you typed, so it is safe to post publicly.

## Who changed what

Every change to fields and settings is written to an activity log (who, when, what changed), kept for 90 days. It never contains customer data, and IP addresses are stored only as a one-way hash.
