=== Rachnix Checkout Field Editor for WooCommerce ===
Contributors: rachnix
Tags: checkout fields, checkout field editor, custom fields, block checkout, woocommerce checkout
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0-beta.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add, edit, hide and reorder WooCommerce checkout fields, on the classic checkout and the Checkout block. Looks like your theme, adds no bloat.

== Description ==

Rachnix Checkout Field Editor lets you shape your WooCommerce checkout without code. Add the fields you need, hide the ones you don't, change labels and order, and see the result in a live preview of your own checkout before customers do.

It works with both WooCommerce checkouts: the classic (shortcode) checkout and the newer Checkout block. Your fields take on your theme's look automatically.

= Edit WooCommerce's own fields =

* Change the label, placeholder, required setting, width (full or half) and order of every billing, shipping and order field.
* Hide fields many stores don't need, such as Company, Address line 2 or Phone.
* Restore any section to WooCommerce's defaults with one click.

= Add your own fields =

Unlimited custom fields in eleven types:

* **Text**, **Text area**, **Email**, **Phone**, **Number** and **Password**
* **Drop-down**, **Radio buttons** and **Checkbox**
* **Date**
* **Hidden**, saved with the order but not shown to the customer, such as a campaign code

Each field can be required and can check what customers type: a valid email or phone number, a number range, a minimum or maximum length, or a format from a safe built-in list (letters only, digits only, a code, a UK postcode, a US ZIP code, an Indian PIN code, an EU VAT number, an IBAN).

= Built for the classic checkout and the Checkout block =

On the classic checkout every field type is shown.

The Checkout block shows text, email, phone, drop-down, checkbox and date fields (date needs WooCommerce 11.2 or later). The editor marks any field the Checkout block can't show, and a notice lists them, so nothing fails silently.

= An editor that's easy to understand =

* Three columns: sections, the section's fields, and a live preview of your real checkout with your theme.
* Drag and drop to reorder, with the mouse or the keyboard alone.
* Changes stay a draft, with undo, until you press "Publish changes".
* A three-step setup wizard for your first visit.
* It uses WordPress's own components and follows your admin colour scheme.

= See the values where you need them =

Choose per field where its value appears:

* The order screen in the admin (HPOS and legacy order storage)
* The thank-you page and the customer's My Account order view
* Emails to the store, emails to the customer, or both
* A column in the orders list (up to two fields)
* The WooCommerce REST API, for integrations

= Looks like your theme =

New fields use WooCommerce's own markup and inherit your theme's fonts, colours and borders. The plugin's stylesheet is under 4 KB, has zero specificity so your theme always wins, and loads only on the checkout. If your theme barely styles forms, switch to the plugin style, or pick your own accent colour, corner radius, spacing and label position.

= Fast and careful =

* Adds under 5 ms to a checkout page in our tests, and no JavaScript on the storefront.
* Signed export and import of your whole setup as a JSON file.
* Works with WordPress's privacy tools: field values are included in personal data exports and erasures.
* Translation ready, right-to-left ready, and store owner texts (labels, placeholders, choices) can be translated with WPML or Polylang.
* Multisite and HPOS compatible.

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New Plugin**, search for "Rachnix Checkout Field Editor" and click **Install Now**, then **Activate**. WooCommerce 9.9 or later must be active.
2. Go to **WooCommerce > Checkout Fields**. The setup wizard opens on your first visit; you can skip it.
3. Pick a section on the left, add or edit fields, check the preview, and press **Publish changes**.

== Frequently Asked Questions ==

= Does it work with the Checkout block? =

Yes. Text, email, phone, drop-down, checkbox and date fields are shown on the Checkout block through WooCommerce's own Additional Checkout Fields API. The other types (text area, number, password, radio buttons, hidden) are shown on the classic checkout only; the editor marks them when your store uses the Checkout block.

= Why can't I hide or rename WooCommerce's fields on the Checkout block? =

On the Checkout block, WooCommerce's own fields are controlled by the block itself. Open the checkout page in the editor and use the Checkout block's settings to hide Company, Address line 2 or Phone. Edits to WooCommerce's fields in this plugin apply to the classic checkout.

= Some fields can't be hidden. Why? =

The billing email and the billing and shipping country are always shown, and the billing email is always required. WooCommerce needs them to create the order and calculate tax and shipping.

= Where are the values saved? =

On the order, through WooCommerce, so they work with HPOS and stay with your orders even if you remove this plugin. Classic checkout values are saved as order meta named `_rxcf_{field key}`; Checkout block values are saved by WooCommerce itself.

= Will my theme's styles be overridden? =

No. Every rule in the plugin's stylesheet has zero specificity, so any rule in your theme wins. The Custom style mode is the exception, on purpose: it applies the accent, radius, spacing and label position you choose.

= Can I move my setup to another site? =

Yes. **Tools > Download export file** saves your fields and style as a signed JSON file. On the other site, choose the file under **Tools > Import**. You see what will be added, changed and removed before anything is applied. A file from another site, or one edited by hand, needs an extra confirmation.

= What happens to my data when I delete the plugin? =

By default your fields and settings are kept, so reinstalling brings them back. To remove them too, turn on **Tools > Delete all data when the plugin is deleted** before deleting the plugin. Values on past orders are always kept, because they are part of your store's records.

= Is it translated? =

The plugin is translation ready, with a .pot file. Labels, placeholders and choices you type are registered with WPML or Polylang, so you can translate them there.

= Who can use the editor? =

Users who can manage WooCommerce (administrators and shop managers). A role editor can take the "rxcf_manage_fields" capability away from a role.

= How do I report a security issue? =

Please don't post it in the support forum. See the security policy at https://github.com/raahishammy/rachnix-checkout-fields/security/policy for how to report it privately.

== Screenshots ==

1. The editor: sections, the fields in checkout order, and a live preview of your own checkout.
2. Add a field: every field type with a one-line description.
3. Field settings: label, placeholder, required and width, with validation rules and display options in their own tabs.
4. Custom fields on the classic checkout, styled by the theme.
5. Custom fields on the Checkout block.
6. The Style tab: follow your theme, use the plugin style, or choose your own accent, radius and spacing.
7. Tools: signed export and import, system info for bug reports, delete-on-uninstall, and reset everything.
8. The setup wizard.

== Changelog ==

= 0.1.0-beta.1 =
* First build, for the private beta.
