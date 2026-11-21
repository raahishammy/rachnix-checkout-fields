# Field types and validation

## The eleven types

| Type | What customers see | Example use | Classic checkout | Checkout block |
| --- | --- | --- | --- | --- |
| Text | One line of text | Company VAT number | Yes | Yes |
| Text area | Several lines | Delivery instructions | Yes | No |
| Email | An email address, format checked | Second email for invoices | Yes | Yes (as text, checked on the server) |
| Phone | A phone number | Second contact number | Yes | Yes (as text, checked on the server) |
| Number | A number, optional minimum and maximum | Number of guests | Yes | No |
| Password | Text hidden while typing | Gift card PIN | Yes | No |
| Drop-down | One choice from a list | Delivery time slot | Yes | Yes |
| Radio buttons | One choice, all shown at once | Gift message style | Yes | No |
| Checkbox | A single tick box | Gift wrap this order | Yes | Yes |
| Date | A date from a calendar | Delivery date | Yes | Yes, with WooCommerce 11.2 or later |
| Hidden | Nothing; saved with the order | Campaign code | Yes | No |

Drop-downs and radio buttons can have up to 100 choices. Each choice has a label for customers and a value saved on the order.

Password values are masked everywhere except the admin order screen and the REST API. Hidden fields are shown only to the store, never to the customer.

## Required

Any type except Hidden can be required. Customers then can't place the order without filling it in, and they see a clear message next to the field.

## Rules

Under **Rules** you can add up to ten checks. Which checks a type offers depends on the type:

| Rule | What it checks |
| --- | --- |
| Email | A valid email address (always on for Email fields) |
| Phone | A valid phone number (always on for Phone fields) |
| Number range | A minimum and/or maximum |
| Length | A minimum and/or maximum number of characters |
| Format | One of the built-in formats below |

### Built-in formats

The plugin only uses this checked list of formats, never a pattern typed in by hand. That keeps checkout validation fast and safe.

| Format | Accepts, for example |
| --- | --- |
| Letters only | Anne-Marie O'Neil |
| Letters and numbers | Order42 |
| Digits only | 123456 |
| Code (letters, digits, - and _) | SUMMER-24 |
| UK postcode | SW1A 1AA |
| US ZIP code | 12345-6789 |
| Indian PIN code | 560001 |
| EU VAT number | DE123456789 |
| IBAN | DE89370400440532013000 |

## Limits

Labels and placeholders can be up to 255 characters. Field keys (the internal name, set automatically) use lowercase letters, digits and underscores, and are unique across all sections.
