# Privacy

Checkout fields often hold personal data. The plugin keeps it on the order, shows it only to the right people, and works with WordPress's privacy tools.

## Where customer data is stored

Only on orders (and, for Checkout block address and contact fields, on the customer's account), through WooCommerce. The plugin's own tables hold field definitions and the activity log, never what customers typed.

## Who can see it

- Store staff who can manage WooCommerce: on the order screen, in store emails, in the orders list and through the REST API.
- The customer who placed the order: on the thank-you page, in My Account and in their emails, for fields you show there.
- Nobody else. Hidden fields are never shown to customers, and password fields are masked except on the admin order screen.

## Export and erase personal data

The plugin hooks into **Tools > Export Personal Data** and **Tools > Erase Personal Data** in WordPress, through WooCommerce:

- **Export** includes every custom field value on the person's orders, and Checkout block values saved on their account.
- **Erase** removes those values from their orders and account, on both checkouts. If WooCommerce's setting to remove personal data from orders on request is off, orders are left unchanged, as WooCommerce does.

This helps you answer requests under laws such as the GDPR and India's DPDP Act.

## Logs

- The activity log records changes to field settings: who, when, what. It never records customer data. IP addresses are stored as a one-way hash with your site's salt, and entries are deleted after 90 days.
- Errors go to WooCommerce's logs (**WooCommerce > Status > Logs**) without personal data.

## Suggested text for your privacy policy

WordPress suggests privacy policy text under **Settings > Privacy**. If you add checkout fields that collect personal data, describe them in your policy, for example:

> At checkout we also ask for [your field, e.g. a delivery date / a second phone number]. We keep it with your order to [why, e.g. deliver your order on time] for as long as we keep the order.
