# Translations

`rachnix-checkout-fields.pot` is the translation template. Regenerate it after changing any
translatable text (it needs wp-env running and a fresh `npm run build`):

```
npm run build
npm run make-pot
```

CI also generates it on every E2E run (artifact `translation-template`).

Translations for WordPress.org installs arrive as language packs and load automatically.
`.mo` files placed in this folder are loaded too (`I18n\TextDomain`).
