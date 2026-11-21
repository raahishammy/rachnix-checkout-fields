# Styling

New fields use WooCommerce's own markup, so they look like the fields beside them. Choose how much the plugin adds on the **Style** tab.

| Mode | What it does | Use it when |
| --- | --- | --- |
| Follow my theme (default) | Adds layout only: widths, spacing between fields, error placement. Fonts, colours and borders come from your theme. | Almost every store |
| Plugin style | Adds simple input borders and padding, bold labels, a focus ring in the accent colour and even spacing | Your theme barely styles forms |
| Custom | Four controls: accent colour, corner radius (0–32 px), field spacing (0–64 px) and label position (above or beside the field) | Small brand tweaks without code |

On block themes the plugin reads your theme's colours (`theme.json`), so the default accent matches your theme.

## Why your theme always wins

The stylesheet is under 4 KB, loads only on the checkout page, and every rule has zero specificity (it is wrapped in `:where()`). Any rule in your theme or your own CSS overrides it, with no `!important` needed.

Custom mode is the one exception, on purpose: the accent, radius, spacing and label position you pick are applied with enough specificity to beat your theme, otherwise they wouldn't show.

## What Custom mode changes on the Checkout block

The Checkout block draws its own inputs and uses floating labels. Custom mode applies the corner radius, the focus colour and the accent to them. Label position applies to the classic checkout only.

## Custom CSS

Under **Advanced** there is a box for your own CSS, loaded after the plugin's stylesheet on the checkout only (up to 10,000 characters). Only users who can edit custom CSS in WordPress (the `edit_css` capability, normally administrators) can change it.

## Theme fixes

The plugin is tested on Storefront, Astra, Kadence, Blocksy, GeneratePress, Hello Elementor and Twenty Twenty-Five, on desktop and mobile. Where a theme needed a fix, it's included and applies only with that theme.
