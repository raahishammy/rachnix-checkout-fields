<?php
/**
 * Warns when the block checkout cannot show some fields.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Admin;

use Rachnix\CheckoutFields\Checkout\Blocks\BlockFieldSupport;
use Rachnix\CheckoutFields\Checkout\CheckoutDetector;
use Rachnix\CheckoutFields\Contracts\FieldRepositoryInterface;
use Rachnix\CheckoutFields\Contracts\Hookable;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * When the store uses the Checkout block, lists every enabled field it will not show,
 * and says that edits to WooCommerce's own fields apply to the classic checkout only.
 * Shown on the dashboard, the Plugins screen and WooCommerce screens, so nothing fails silently.
 */
final class BlockCheckoutNotice implements Hookable {

	/**
	 * @param FieldRepositoryInterface $fields   Field definitions.
	 * @param FieldTypeRegistry        $types    Field types.
	 * @param BlockFieldSupport        $support  Block type support.
	 * @param CheckoutDetector         $detector Which checkout is in use.
	 */
	public function __construct(
		private readonly FieldRepositoryInterface $fields,
		private readonly FieldTypeRegistry $types,
		private readonly BlockFieldSupport $support,
		private readonly CheckoutDetector $detector,
	) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_FIELDS ) || ! $this->on_relevant_screen() ) {
			return;
		}
		echo $this->html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes every value.
	}

	/**
	 * The notice markup, or '' when there is nothing to say. Separate from render() for tests.
	 */
	public function html(): string {
		if ( ! $this->detector->uses_block_checkout() ) {
			return '';
		}

		$hidden = $this->fields_not_shown();
		$core   = $this->has_core_overrides();
		if ( array() === $hidden && ! $core ) {
			return '';
		}

		$items = '';
		foreach ( $hidden as $field ) {
			$type   = $this->types->has( $field->type ) ? $this->types->get( $field->type )->label() : $field->type;
			$items .= sprintf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: 1: field label, 2: field type name, e.g. "Text area". */
						__( '%1$s (%2$s)', 'rachnix-checkout-fields' ),
						'' !== $field->label ? $field->label : $field->key,
						$type
					)
				)
			);
		}

		$html = '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Rachnix Checkout Field Editor: your checkout uses the Checkout block.', 'rachnix-checkout-fields' ) . '</strong></p>';
		if ( '' !== $items ) {
			$html .= '<p>' . esc_html__( 'These fields are not shown on the block checkout, so customers cannot fill them in. Change their type, or switch the checkout page to the classic checkout ([woocommerce_checkout] shortcode):', 'rachnix-checkout-fields' ) . '</p>';
			$html .= '<ul style="list-style:disc;padding-left:2em">' . $items . '</ul>';
		}
		if ( $core ) {
			$html .= '<p>' . esc_html__( 'Your changes to WooCommerce\'s own fields (hide, rename, reorder) apply to the classic checkout only. On the block checkout, use the settings of the Checkout block in the editor.', 'rachnix-checkout-fields' ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * @return list<Field>
	 */
	private function fields_not_shown(): array {
		$hidden = array();
		foreach ( $this->fields->all()->enabled() as $field ) {
			if ( ! $field->is_core && ! $field->section->is_custom() && ! $this->support->is_shown( $field->type ) ) {
				$hidden[] = $field;
			}
		}
		return $hidden;
	}

	private function has_core_overrides(): bool {
		foreach ( $this->fields->all() as $field ) {
			if ( $field->is_core ) {
				return true;
			}
		}
		return false;
	}

	private function on_relevant_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return false;
		}
		$woocommerce = function_exists( 'wc_get_screen_ids' ) ? wc_get_screen_ids() : array();
		return in_array( $screen->id, array_merge( array( 'dashboard', 'plugins' ), $woocommerce ), true );
	}
}
