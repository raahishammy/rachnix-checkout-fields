# Hosting and security headers

Before each major release we scan a test store with OWASP ZAP. The plugin's own code had no high-risk findings. The scan does report medium and low warnings that come from the server, WordPress and WooCommerce, not from this plugin. They apply to most WordPress stores, so here is what they mean and what your host can do.

These are settings for your web server, CDN or a security plugin, not for this plugin. Test each one on a staging copy first: a header that is too strict can break payment gateways, the admin or the checkout.

| Warning | What it means | What to do |
| --- | --- | --- |
| Content Security Policy header not set | Browsers aren't told which scripts and frames are allowed, which makes cross-site scripting harder to exploit | Start with `Content-Security-Policy-Report-Only` and a report address, watch what your payment gateway, analytics and theme need, then enforce it. A strict policy usually breaks the WordPress admin, so apply it to the storefront first. |
| Missing anti-clickjacking header | Other sites could load your pages in a frame | `Content-Security-Policy: frame-ancestors 'self'` or `X-Frame-Options: SAMEORIGIN`. **Use `SAMEORIGIN`, not `DENY`:** the plugin's live preview loads your checkout in a frame on your own admin screen. |
| Cookie without HttpOnly or SameSite | Scripts can read a cookie, or it is sent with cross-site requests | WordPress's login cookies already use HttpOnly. The warnings are usually about WooCommerce's cart and session cookies, which WooCommerce's own scripts need to read. Leave those as they are; check any other cookie with the plugin that sets it. |
| Strict-Transport-Security header not set | Browsers may try plain HTTP first | Serve the whole store over HTTPS, then add `Strict-Transport-Security: max-age=31536000; includeSubDomains`. |
| X-Content-Type-Options header missing | Browsers may guess file types | `X-Content-Type-Options: nosniff` |
| Server leaks version information (`X-Powered-By`, `Server`) | Shows your PHP and server versions | Turn off `expose_php` in php.ini, and hide the server version (`ServerTokens Prod` on Apache, `server_tokens off` on nginx). |
| PHP version with known vulnerabilities | The PHP version (read from `X-Powered-By`) has known security fixes after it | Ask your host for a supported PHP version. The plugin needs PHP 8.2 or later. |

## Example: nginx

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
server_tokens off;
```

## Example: Apache (.htaccess)

```apache
<IfModule mod_headers.c>
	Header always set X-Frame-Options "SAMEORIGIN"
	Header always set X-Content-Type-Options "nosniff"
	Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
	Header always set Referrer-Policy "strict-origin-when-cross-origin"
	Header unset X-Powered-By
</IfModule>
```

## Good practice for every store

- Keep WordPress, WooCommerce, PHP, your theme and every plugin up to date.
- Turn on two-factor login for every account that can manage the store.
- Give staff the lowest role that does the job: Shop manager rather than Administrator.
- Back up the database and files daily, and test a restore now and then.

To report a security problem in this plugin, see the [security policy](../SECURITY.md).
