/**
 * Step 5 gate: a real order on the classic (shortcode) checkout, using every free field type.
 * Runs against wp-env (see .github/workflows/ci.yml, job "e2e").
 */
import { test, expect, type Page } from '@playwright/test';
import { expectValuesForStore, wpEvalFile, type Store } from './helpers';

let store: Store;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	store = wpEvalFile<Store>( 'tests/e2e/setup.php', 'classic' );
} );

async function openCheckout( page: Page ) {
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	await expect( page.locator( 'form.checkout' ) ).toBeVisible();
}

async function fillWooCommerceFields( page: Page ) {
	await page.fill( '#billing_first_name', 'Anne' );
	await page.fill( '#billing_last_name', 'Tester' );
	await page.locator( '#billing_country' ).selectOption( 'US', { force: true } );
	await page.fill( '#billing_address_1', '1 Test Street' );
	await page.fill( '#billing_city', 'San Francisco' );
	await page.locator( '#billing_state' ).selectOption( 'CA', { force: true } );
	await page.fill( '#billing_postcode', '94103' );
	await page.fill( '#billing_phone', '4155550100' );
	await page.fill( '#billing_email', 'anne@example.com' );
}

async function fillCustomFields( page: Page, overrides: Record< string, string > = {} ) {
	const text: Record< string, string > = {
		rxcf_rx_vat: 'GB123456',
		rxcf_rx_alt_email: 'other@example.com',
		rxcf_rx_mobile: '+44 20 7946 0958',
		rxcf_rx_note: 'Leave at door\nRing twice',
		rxcf_rx_floor: '3',
		rxcf_rx_pin: '7319',
		rxcf_rx_date: '2026-12-24',
		...overrides,
	};
	for ( const [ name, value ] of Object.entries( text ) ) {
		await page.fill( `[name="${ name }"]`, value );
	}
	await page.locator( '[name="rxcf_rx_slot"]' ).selectOption( 'pm' );
	await page.check( '[name="rxcf_rx_wrap"][value="box"]' );
	await page.check( '[name="rxcf_rx_gift"]' );
}

async function placeOrder( page: Page ) {
	await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
	const cod = page.locator( '#payment_method_cod' );
	if ( await cod.count() ) {
		await cod.check( { force: true } );
	}
	await page.click( '#place_order' );
}

test( 'core field edits are applied', async ( { page } ) => {
	await openCheckout( page );

	await expect( page.locator( 'label[for="billing_first_name"]' ) ).toContainText( 'Given name' );
	await expect( page.locator( '#billing_company' ) ).toHaveCount( 0 );
} );

test( 'every field type renders', async ( { page } ) => {
	await openCheckout( page );

	const expected: Record< string, string > = {
		rxcf_rx_vat: 'text',
		rxcf_rx_alt_email: 'email',
		rxcf_rx_mobile: 'tel',
		rxcf_rx_source: 'hidden',
		rxcf_rx_floor: 'number',
		rxcf_rx_pin: 'password',
		rxcf_rx_gift: 'checkbox',
		rxcf_rx_date: 'date',
	};
	for ( const [ name, type ] of Object.entries( expected ) ) {
		await expect( page.locator( `input[name="${ name }"]` ) ).toHaveAttribute( 'type', type );
	}
	await expect( page.locator( 'textarea[name="rxcf_rx_note"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'select[name="rxcf_rx_slot"] option' ) ).toHaveCount( 3 );
	await expect( page.locator( 'input[name="rxcf_rx_wrap"][type="radio"]' ) ).toHaveCount( 2 );
} );

test( 'invalid values stop the order with clear messages', async ( { page } ) => {
	await openCheckout( page );
	await fillWooCommerceFields( page );
	await fillCustomFields( page, { rxcf_rx_floor: '99', rxcf_rx_alt_email: 'not-an-email' } );
	await page.locator( '[name="rxcf_rx_slot"]' ).selectOption( '' );

	await placeOrder( page );

	const notices = page.locator( '.woocommerce-NoticeGroup-checkout' );
	await expect( notices ).toContainText( 'Floor must be between 0 and 50.' );
	await expect( notices ).toContainText( 'Other email is not a valid email address.' );
	await expect( notices ).toContainText( 'Delivery slot' );
	await expect( page ).not.toHaveURL( /order-received/ );
} );

test( 'an order with every field type is placed and every value is saved', async ( { page } ) => {
	await openCheckout( page );
	await fillWooCommerceFields( page );
	await fillCustomFields( page );

	await placeOrder( page );
	await page.waitForURL( /order-received/, { timeout: 60_000 } );

	const orderId = page.url().match( /order-received[=/](\d+)/ )?.[ 1 ];
	expect( orderId, page.url() ).toBeTruthy();

	const meta = wpEvalFile< Record< string, string > >( 'tests/e2e/order-meta.php', orderId as string );
	expect( meta ).toEqual( {
		_rxcf_rx_alt_email: 'other@example.com',
		_rxcf_rx_date: '2026-12-24',
		_rxcf_rx_floor: '3',
		_rxcf_rx_gift: '1',
		_rxcf_rx_mobile: '+44 20 7946 0958',
		_rxcf_rx_note: 'Leave at door\nRing twice',
		_rxcf_rx_pin: '7319',
		_rxcf_rx_slot: 'pm',
		_rxcf_rx_source: 'e2e',
		_rxcf_rx_vat: 'GB123456',
		_rxcf_rx_wrap: 'box',
	} );

	// Step 9: where the values show.
	const thankYou = page.locator( '.rxcf-fields-table' );
	await expect( thankYou ).toContainText( 'GB123456' );
	await expect( thankYou ).toContainText( 'Pm', { useInnerText: true } ); // Choice label, not its value.
	await expect( thankYou ).toContainText( '••••' ); // Password-type value masked for customers.
	await expect( thankYou.locator( 'tr', { hasText: 'Gift card PIN' } ) ).toContainText( '••••' );
	await expect( thankYou ).not.toContainText( '7319' ); // The real PIN never reaches the customer page.
	await expect( thankYou ).not.toContainText( 'e2e' ); // Hidden field: store only.

	await expectValuesForStore( page, store, orderId as string, [ 'GB123456', 'Leave at door', '7319' ] );
} );
