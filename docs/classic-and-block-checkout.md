# Classic checkout and Checkout block

WooCommerce has two checkouts:

- The **classic checkout**: a checkout page containing the `[woocommerce_checkout]` shortcode.
- The **Checkout block**: a checkout page (or, on block themes, the checkout template) built with the Checkout block. New stores get this one by default.

The plugin finds out which one your checkout page uses and tells the editor. If you switch, it notices on the next page load.

## Custom fields

| | Classic checkout | Checkout block |
| --- | --- | --- |
| Field types shown | All eleven | Text, email, phone, drop-down, checkbox, date (WooCommerce 11.2+) |
| Billing fields | In the billing form | In both address forms (billing and shipping) |
| Shipping fields | In the shipping form | In both address forms (billing and shipping) |
| Additional information | In the order notes area | In the "Additional order information" area |
| Values saved by | This plugin, as order meta `_rxcf_{key}` | WooCommerce's Additional Checkout Fields API |

The Checkout block only renders the field types WooCommerce supports there. When your store uses the Checkout block, the editor marks other types "Not shown on the block checkout", and an admin notice lists any such field, so nothing goes missing silently.

On the Checkout block, WooCommerce shows address fields in both the billing and the shipping address, and also on the customer's My Account address forms. That is how WooCommerce's API works.

## WooCommerce's own fields

On the **classic checkout** you can change every WooCommerce field: label, placeholder, required, width, position, or hide it.

On the **Checkout block**, WooCommerce's fields are controlled by the block itself. To hide Company, Address line 2 or Phone, edit the checkout page and select the Checkout block; the options are in its settings sidebar. Changes to WooCommerce fields in this plugin apply to the classic checkout only, and the editor says so.

These are always shown, on both checkouts, because WooCommerce needs them: the billing email (always required), and the billing and shipping country.

## Things to know

- WooCommerce changes some address labels and required settings per country (for example "State" vs. "County", or whether a postcode is needed). On the classic checkout this can override your edits to those fields when a customer picks a country.
- The preview of the Checkout block needs a product in your cart. With an empty cart, WooCommerce shows its empty-cart message instead of the form.
