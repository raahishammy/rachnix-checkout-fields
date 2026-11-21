# Translation

## The plugin itself

Everything the plugin shows is translation ready, with the text domain `rachnix-checkout-fields`. Translations from translate.wordpress.org load automatically. Right-to-left languages are supported, in the editor and on the checkout.

To translate it yourself, use the template `languages/rachnix-checkout-fields.pot` with a tool such as Poedit, or the Loco Translate plugin.

## Your own texts: WPML and Polylang

The labels, placeholders and choices you type in the editor are in your store's main language. With WPML (and WPML String Translation) or Polylang active, the plugin registers each of them as a string you can translate:

- WPML: **WPML > String Translation**, domain `rachnix-checkout-fields`.
- Polylang: **Languages > Translations**, group "Rachnix Checkout Fields".

Strings are named after the section, the field key and the text, for example `billing.gift_message.label`, `order.delivery_slot.option.morning`.

Translated texts are used on the checkout, in validation messages, on the thank-you page, in My Account, in emails and on the order screen.

A string appears in the list after the field is published and an administrator has opened any admin page.
