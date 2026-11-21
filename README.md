# Rachnix Checkout Field Editor for WooCommerce

Add, edit, hide and reorder WooCommerce checkout fields, on the classic checkout and the Checkout block. Fields look like your theme, and the plugin adds under 5 ms to a checkout page.

- Requires WordPress 6.5+, WooCommerce 9.9+, PHP 8.2+. HPOS and multisite compatible.
- Store owner and developer docs: [docs/](docs/README.md)
- WordPress.org listing text: [readme.txt](readme.txt)
- Report a vulnerability: [SECURITY.md](SECURITY.md)

## Development

```
composer install
npm ci
npm run build
npx wp-env start
```

| Check | Command |
| --- | --- |
| PHPCS, PHPStan, PHPUnit | `composer check` |
| Integration tests (in wp-env) | `npx wp-env run cli --env-cwd=wp-content/plugins/rachnix-checkout-fields php vendor/bin/phpunit -c phpunit-integration.xml.dist` |
| JavaScript lint and unit tests | `npm run lint:js` and `npm run test:js` |
| End-to-end tests (Playwright, wp-env) | `npx playwright test` |
| Shipped files, optionally zipped | `bash bin/build-dist.sh [--zip]` |

The end-to-end setup scripts in `tests/e2e/` reset the store's checkout fields and change store options. Run them only in wp-env, never on a real store.

## License

GPL-2.0-or-later
