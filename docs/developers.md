# Hooks and extending the plugin

The plugin is built so that add-ons, including Rachnix's own Pro add-on, extend it only through the hooks on this page. Anything not listed here is internal and may change in any release.

Namespace: `Rachnix\CheckoutFields`. All hooks start with `rxcf_`.

## Actions

### `rxcf_loaded`

Fires once the plugin has booted and its requirements are met.

```php
add_action( 'rxcf_loaded', function ( string $version ) {
	// Safe to use the plugin's hooks from here.
} );
```

### `rxcf_register_field_types`

Fires the first time field types are looked up. Register your own types here.

```php
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;

add_action( 'rxcf_register_field_types', function ( FieldTypeRegistry $registry ) {
	$registry->register( new My_Colour_Field() );
} );
```

A type implements `Rachnix\CheckoutFields\Contracts\FieldTypeInterface`. Extending `Rachnix\CheckoutFields\FieldTypes\AbstractFieldType` gives you sensible defaults. You provide the slug, the label, the one-line description, a Dashicons icon, which settings it supports, its validation rules, `sanitize()`, `value_errors()` and `format_value()`. See the built-in types in `src/FieldTypes/` for examples.

## Filters

### Classic checkout

| Filter | Arguments | Use |
| --- | --- | --- |
| `rxcf_classic_form_type` | `string $form_type`, `string $slug` | The `woocommerce_form_field()` type for a field type added by an add-on (default `text`) |
| `rxcf_classic_field_args` | `array $args`, `Field $field` | The `woocommerce_form_field()` arguments of a custom field, e.g. to add attributes or classes |

### Checkout block

| Filter | Arguments | Use |
| --- | --- | --- |
| `rxcf_register_block_checkout_fields` | `bool $register` | Whether to register fields with the Checkout block. Defaults to true only when the checkout uses the block. |
| `rxcf_block_field_options` | `array $options`, `Field $field` | The options passed to `woocommerce_register_additional_checkout_field()` for a field |
| `rxcf_block_checkout_renders_type` | `bool $renders`, `string $slug` | Return true if your add-on renders this field type on the Checkout block itself. The editor then stops marking it "Not shown on the block checkout". |

### Fields and sections

| Filter | Arguments | Use |
| --- | --- | --- |
| `rxcf_field_extensions` | `array $stored`, `mixed $submitted`, `string $type` | Validate and keep your own per-field settings (sent as `settings.extensions`). Return the array to store. Without an add-on, the stored value is kept and what was submitted is ignored. |
| `rxcf_custom_section_exists` | `bool $exists`, `SectionKey $section` | Whether a custom section (`custom:{uuid}`) exists. The free plugin knows none. |
| `rxcf_rest_sections` | `array $sections` | The sections listed in the editor, for adding custom sections |

## Reading values from an order

Use the WooCommerce REST API: orders include `rxcf_fields`, with each field's `key`, `label`, `type`, `section`, `value`, `display` and `source` (`classic` or `blocks`).

In PHP, classic checkout values are order meta `_rxcf_{key}`:

```php
$order = wc_get_order( $order_id );
$gift  = $order->get_meta( '_rxcf_gift_message' );
```

Checkout block values are stored by WooCommerce under the field id `rachnix/{key}`. Read them with WooCommerce's API:

```php
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

$fields = Package::container()->get( CheckoutFields::class );
$value  = $fields->get_field_from_object( 'rachnix/gift_message', $order, 'other' );
```

## Capability

Everything in the editor and its REST routes (namespace `rxcf/v1`) needs the `rxcf_manage_fields` capability. It's given at run time to every user with `manage_woocommerce`, unless a role explicitly denies it.

```php
// Stop shop managers using the field editor.
get_role( 'shop_manager' )->add_cap( 'rxcf_manage_fields', false );
```

## Data

| What | Where |
| --- | --- |
| Field definitions | Table `{prefix}rxcf_fields` |
| Sections (for add-ons) | Table `{prefix}rxcf_sections` |
| Activity log, 90 days | Table `{prefix}rxcf_audit_log` |
| Settings | Option `rxcf_settings` |
| Schema version | Option `rxcf_db_version` |

Don't write to these tables directly; field definitions are validated and cached by the plugin.
