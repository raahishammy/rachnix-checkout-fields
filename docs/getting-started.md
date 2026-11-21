# Getting started

## What you need

- WordPress 6.5 or later
- WooCommerce 9.9 or later, active
- PHP 8.2 or later

If something is too old, the plugin shows a notice saying what to update and doesn't load, so your checkout stays as it was.

## Install

1. Go to **Plugins > Add New Plugin**, search for "Rachnix Checkout Field Editor", then click **Install Now** and **Activate**.
2. Go to **WooCommerce > Checkout Fields**.

## The setup wizard

On your first visit a three-step wizard opens. Every step is optional, and **Skip setup** takes you straight to the editor.

1. **Your checkout.** On the classic checkout, tick the WooCommerce fields you don't need: Company name, Address line 2 or Phone. If your store uses the Checkout block, the wizard tells you to hide them in the block's settings instead.
2. **Your first field.** Pick a type, type a label such as "Gift message", and choose where it goes.
3. **A style.** "Follow my theme" suits almost every store.

**Finish** publishes all of it at once.

## The editor

The editor has three columns:

- **Sections** on the left: Billing details, Shipping details and Additional information.
- **Fields** in the middle, in checkout order. Drag a field by its handle to move it. With the keyboard, focus the handle, press Space, use the arrow keys, then press Space again.
- **Preview** on the right: your real checkout with your theme, showing your draft. Clicking a field shows its settings in this column instead.

## Add a field

1. Choose a section and click **Add field**.
2. Pick a type. Each type has a one-line description.
3. Type the label customers will see. The other settings are optional:
   - **Basic**: placeholder, required, width (full or half).
   - **Rules**: checks on what customers type, such as a length limit or a format.
   - **Show in**: where the value appears after the order (see [Where the values show](where-values-show.md)).
4. Check the preview, then click **Publish changes**.

Nothing reaches your live checkout until you publish. Until then, **Undo** steps back through your changes, and your draft is kept in the browser if you close the tab.

## Edit or hide a WooCommerce field

Click a WooCommerce field in the list to change its label, placeholder, required setting, width or position, or to hide it. **Reset section** puts every field in the section back to WooCommerce's defaults.

On the Checkout block, WooCommerce's own fields are controlled by the block; see [Classic checkout and Checkout block](classic-and-block-checkout.md).
