/**
 * Shared helpers for the end-to-end tests.
 */
import { execFileSync } from 'node:child_process';
import { expect, type Page } from '@playwright/test';

const PLUGIN = 'wp-content/plugins/rachnix-checkout-fields';

/** What tests/e2e/setup.php reports. */
export type Store = {
	product_id: number;
	home_url: string;
	checkout_url: string;
	mode: 'classic' | 'blocks';
	date_supported: boolean;
};

/**
 * Logs in as wp-env's administrator (admin / password), straight to `target` (an admin path).
 * Going via the dashboard is slow in CI (WooCommerce's dashboard widgets make remote requests),
 * and only the HTML is awaited, not every asset.
 *
 * @param page   Page.
 * @param store  Store from setup.php.
 * @param target Admin path to land on, e.g. "admin.php?page=wc-orders".
 */
export async function logInAsAdmin( page: Page, store: Store, target = 'admin.php?page=wc-orders' ) {
	const destination = `${ store.home_url }wp-admin/${ target }`;
	await page.goto( `${ store.home_url }wp-login.php?redirect_to=${ encodeURIComponent( destination ) }` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [
		page.waitForURL( /wp-admin/, { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
}

/**
 * Checks the places a placed order's values show for the store: the order screen (HPOS) and
 * the orders list column (the VAT field has "Column in the orders list" on in setup.php).
 *
 * @param page    Page.
 * @param store   Store from setup.php.
 * @param orderId Order id.
 * @param values  Texts that must appear on the order screen.
 */
export async function expectValuesForStore( page: Page, store: Store, orderId: string, values: string[] ) {
	await logInAsAdmin( page, store );

	await page.goto( `${ store.home_url }wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`, { waitUntil: 'domcontentloaded' } );
	const screen = await page.content();
	for ( const value of values ) {
		expect( screen, `order screen shows ${ value }` ).toContain( value );
	}

	await page.goto( `${ store.home_url }wp-admin/admin.php?page=wc-orders`, { waitUntil: 'domcontentloaded' } );
	await expect( page.locator( 'th#rxcf_rx_vat, th.column-rxcf_rx_vat' ).first() ).toContainText( 'VAT number' );
	await expect( page.locator( `tr#order-${ orderId } td.column-rxcf_rx_vat, tr:has(a[href*="id=${ orderId }"]) td.column-rxcf_rx_vat` ).first() ).toContainText( 'GB123456' );
}

/** Runs a PHP script inside wp-env and returns its `RXCF_E2E {json}` result line. */
export function wpEvalFile< T >( file: string, ...args: string[] ): T {
	const output = execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', 'eval-file', `${ PLUGIN }/${ file }`, ...args ], {
		encoding: 'utf8',
		shell: process.platform === 'win32',
	} );
	const line = output.split( /\r?\n/ ).find( ( l ) => l.startsWith( 'RXCF_E2E ' ) );
	if ( ! line ) {
		throw new Error( `No result from ${ file }:\n${ output }` );
	}
	return JSON.parse( line.slice( 'RXCF_E2E '.length ) ) as T;
}
