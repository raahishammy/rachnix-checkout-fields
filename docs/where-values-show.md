# Where the values show

Open a field and choose the **Show in** tab. Each place can be switched on or off per field.

| Place | Default | Notes |
| --- | --- | --- |
| Order screen (admin) | On | HPOS and legacy order storage |
| Thank-you page | On | |
| My Account order view | On | Only the customer who placed the order sees it |
| Emails to the store | On | Emails WooCommerce sends to the shop, such as New order |
| Emails to the customer | On | Emails WooCommerce sends to the customer, such as Processing order |
| Orders list column | Off | At most two fields, from either checkout |

Choices show their label rather than their stored value, checkboxes show Yes or No, and dates use your store's date format.

Hidden fields are never shown to the customer, whatever you choose. Password fields are masked everywhere except the admin order screen and the REST API.

## Checkout block

WooCommerce shows Checkout block values itself. The plugin applies your choices for the thank-you page, My Account and emails with WooCommerce 10.1 or later; with older versions only the thank-you page choice applies. WooCommerce always shows these values on the admin order screen.

## REST API

Orders from the WooCommerce REST API (`/wp-json/wc/v3/orders`) include an `rxcf_fields` list with each field's key, label, type, section, stored value and display value, for both checkouts. Checkout block values are also in WooCommerce's Store API, under `additional_fields`.

## If you remove a field

Values already saved on orders stay on those orders. They're part of your store's records.
