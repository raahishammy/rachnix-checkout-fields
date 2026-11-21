/**
 * Step 6 gate: the same store and fields as the classic test, on the Checkout block.
 *
 * The block checkout shows text, email, phone, select, checkbox and (WooCommerce 11.2+)
 * date fields. Textarea, number, password, radio and hidden fields are not shown there; the
 * admin notice lists them (covered by tests/Integration/BlocksCheckoutTest.php).
 */
import { test, expect, type Page } from '@playwright/test';
import { expectValuesForStore, wpEvalFile, type Store } from './helpers';

let store: Store;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'blocks' );
} );

async function openCheckout( page: Page ) {
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	// The block root; ".wc-block-checkout" also matches its inner layout element.
	await expect( page.locator( '[data-block-name="woocommerce/checkout"]' ) ).toBeVisible();
	await expect( page.getByLabel( 'Email address' ) ).toBeVisible();
}

async function fillWooCommerceFields( page: Page ) {
	await page.getByLabel( 'Email address' ).fill( 'anne@example.com' );
	await page.getByLabel( 'First name' ).fill( 'Anne' );
	await page.getByLabel( 'Last name' ).fill( 'Tester' );
	await page.getByLabel( 'Address', { exact: true } ).fill( '1 Test Street' );
	await page.getByLabel( 'City' ).fill( 'San Francisco' );
	await page.getByLabel( 'State' ).selectOption( 'CA' );
	await page.getByLabel( /^ZIP/ ).fill( '94103' );
}

async function fillCustomFields( page: Page, overrides: { email?: string } = {} ) {
	await page.getByLabel( /^VAT number/ ).fill( 'GB123456' );
	await page.getByLabel( /^Other email/ ).fill( overrides.email ?? 'other@example.com' );
	await page.getByLabel( /^Second phone/ ).fill( '+44 20 7946 0958' );
	await page.getByLabel( /^Delivery slot/ ).selectOption( 'pm' );
	await page.getByLabel( /^This is a gift/ ).check();
	if ( store.date_supported ) {
		await page.getByLabel( /^Delivery date/ ).fill( '2026-12-24' );
	}
}

async function placeOrder( page: Page ) {
	await page.getByRole( 'button', { name: /place order/i } ).click();
}

test( 'supported field types are shown, unsupported ones are not', async ( { page } ) => {
	await openCheckout( page );

	for ( const label of [ /^VAT number/, /^Other email/, /^Second phone/, /^Delivery slot/, /^This is a gift/ ] ) {
		await expect( page.getByLabel( label ) ).toHaveCount( 1 );
	}
	await expect( page.getByLabel( /^Delivery date/ ) ).toHaveCount( store.date_supported ? 1 : 0 );

	for ( const label of [ /^Delivery note/, /^Floor/, /^Gift card PIN/, /^Wrapping/ ] ) {
		await expect( page.getByLabel( label ) ).toHaveCount( 0 );
	}
} );

test( 'invalid values stop the order with clear messages', async ( { page } ) => {
	await openCheckout( page );
	await fillWooCommerceFields( page );
	await fillCustomFields( page, { email: 'not-an-email' } );

	await placeOrder( page );

	// The block may show the error both in the notice banner and under the field.
	await expect( page.getByText( 'Other email is not a valid email address.' ).first() ).toBeVisible();
	await expect( page ).not.toHaveURL( /order-received/ );
} );

test( 'an order is placed and every shown field is saved by WooCommerce', async ( { page } ) => {
	await openCheckout( page );
	await fillWooCommerceFields( page );
	await fillCustomFields( page );

	await placeOrder( page );
	await page.waitForURL( /order-received/, { timeout: 60_000 } );

	const orderId = page.url().match( /order-received[=/](\d+)/ )?.[ 1 ];
	expect( orderId, page.url() ).toBeTruthy();

	const meta = wpEvalFile< Record< string, unknown > >( 'tests/e2e/order-meta.php', orderId as string );

	expect( meta ).toMatchObject( {
		'_wc_billing/rachnix/rx_vat': 'GB123456',
		'_wc_billing/rachnix/rx_alt_email': 'other@example.com',
		'_wc_billing/rachnix/rx_mobile': '+44 20 7946 0958',
		'_wc_other/rachnix/rx_slot': 'pm',
	} );
	expect( [ '1', 1, true ] ).toContain( meta[ '_wc_other/rachnix/rx_gift' ] );
	if ( store.date_supported ) {
		expect( meta[ '_wc_other/rachnix/rx_date' ] ).toBe( '2026-12-24' );
	}
	expect( Object.keys( meta ).filter( ( key ) => key.startsWith( '_rxcf_' ) ), 'The classic checkout code stayed out of it.' ).toEqual( [] );

	// Step 9: WooCommerce shows block checkout values itself, following our "Show in" switches.
	const confirmation = page.locator( 'main' );
	await expect( confirmation ).toContainText( 'GB123456' );
	await expect( confirmation ).toContainText( 'Pm' ); // Choice label, not its value.

	await expectValuesForStore( page, store, orderId as string, [ 'GB123456' ] );
} );
