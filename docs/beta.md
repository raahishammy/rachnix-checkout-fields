# Beta testing guide

Thank you for testing Rachnix Checkout Field Editor before its public release. This page tells you how to install the beta, what to try, and how to tell us what you found.

## Before you start

- **Use a staging copy of your store if you can.** Most hosts create one in a click. The beta is well tested, but it's still a beta, and it changes your checkout.
- On a live store, test at a quiet time, and place a test order after each change.
- Turn off other checkout field plugins while you test: two plugins editing the same fields will confuse each other.
- The plugin sends nothing anywhere. There's no tracking and no "phone home".

## Install

1. Download `rachnix-checkout-fields-<version>.zip` from the link you were sent (a GitHub pre-release).
2. In your admin, go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and click **Install Now**, then **Activate**.
3. Go to **WooCommerce > Checkout Fields**.

To update to a newer beta, upload the new zip the same way and choose **Replace current with uploaded**. Your fields are kept.

## What to try

Please try whatever your store really needs first. That's the most useful test. Then, if you have time:

**First impressions**
- [ ] Without reading any docs, add a field and see it on your checkout. Roughly how long did it take?

**Your checkout**
- [ ] Add two or three fields your store would use, of different types.
- [ ] Hide or rename a WooCommerce field you don't need (classic checkout), or check the editor explains what to do (Checkout block).
- [ ] Reorder fields by dragging.
- [ ] Make a field required, then try to place an order without filling it in.
- [ ] Check the fields look right with your theme, on a phone too.

**After the order**
- [ ] Place a real test order with your usual payment method (Razorpay, Stripe, PayPal, cash on delivery...).
- [ ] Find the values on the order screen, in the new order email, in the customer's email, on the thank-you page and in My Account.

**Tools**
- [ ] Export your setup, then import it again (on a staging site if you can).

## Tell us what you found

Every report helps, including "it just worked".

- **Bug:** on GitHub, open a [bug report](https://github.com/raahishammy/rachnix-checkout-fields/issues/new?template=bug_report.yml). No GitHub account? Send the same details to [beta contact, to be added].
- **Feedback:** the [beta feedback form](https://github.com/raahishammy/rachnix-checkout-fields/issues/new?template=feedback.yml): what you used it for, what was confusing, what's missing.
- **Security problem:** never in a public issue. See the [security policy](../SECURITY.md).

With every report, please include the **system info**: **WooCommerce > Checkout Fields > Tools > Show system info > Copy system info**. It lists versions and settings only, with no site address or customer data. Please don't send customer names, addresses, emails or phone numbers, and crop them out of screenshots.

### How bad is it?

| Level | Example |
| --- | --- |
| Critical | Customers can't place orders; values are lost; an error page |
| Major | A feature doesn't work and there's no way around it |
| Minor | It works, but something looks wrong or is confusing |

We'll answer critical bugs within a day and aim to send a fixed beta within 72 hours.

## Removing the beta

Deactivate and delete the plugin from **Plugins**. By default your field setup is kept in case you reinstall; to remove it too, first turn on **Tools > Delete all data when the plugin is deleted**. Values on your orders are always kept.
