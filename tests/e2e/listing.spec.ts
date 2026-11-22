/**
 * WordPress.org listing assets: screenshot-1.png to screenshot-8.png (matching the captions
 * in readme.txt), banner-772x250.png, banner-1544x500.png, icon-128x128.png, icon-256x256.png
 * and icon.svg, written to test-results/wordpress-org/.
 *
 * Runs only when RXCF_LISTING is set (CI job "listing-assets", from "Run workflow").
 * Resets the wp-env store and renames it: never point it at a real store.
 */
import { readFileSync, mkdirSync, copyFileSync } from 'node:fs';
import { join } from 'node:path';
import { test, expect, type Page } from '@playwright/test';
import { logInAsAdmin, wpEvalFile, type Store } from './helpers';

const OUT = join( 'test-results', 'wordpress-org' );
const ADMIN = 'admin.php?page=rxcf-checkout-fields';
const THEME = process.env.RXCF_LISTING_THEME ?? 'storefront';

test.skip( ! process.env.RXCF_LISTING, 'Set RXCF_LISTING to make the listing assets.' );
test.describe.configure( { mode: 'serial' } );
test.use( { viewport: { width: 1440, height: 900 } } );

const file = ( name: string ) => join( OUT, name );

function prepare( ...setupArgs: string[] ): Store {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', ...setupArgs );
	wpEvalFile( 'tests/e2e/listing-setup.php', THEME );
	return store;
}

async function openEditor( page: Page, store: Store ) {
	await logInAsAdmin( page, store, ADMIN );
	await expect( page.locator( '.rxcf-admin' ) ).toBeVisible();
	// Hide WordPress's notices and the plugin's own Checkout block notice: not part of the product shot.
	await page.addStyleTag( { content: '.notice, .update-nag, #wpfooter { display: none !important; }' } );
}

test.beforeAll( () => {
	mkdirSync( OUT, { recursive: true } );
} );

/** Waits for the preview's checkout form; on failure, says what the frame showed instead. */
async function waitForPreview( page: Page ) {
	const frame = page.frameLocator( '.rxcf-preview__frame' );
	try {
		await expect( frame.locator( 'form.checkout' ) ).toBeVisible( { timeout: 60_000 } );
	} catch ( error ) {
		const src = await page.locator( '.rxcf-preview__frame' ).getAttribute( 'src' ).catch( () => null );
		const shown = await frame.locator( 'body' ).innerText( { timeout: 2_000 } ).catch( () => '(no frame body)' );
		const notices = await page.locator( '.rxcf-preview .components-notice' ).allInnerTexts();
		throw new Error( `Preview did not show the checkout.\nFrame src: ${ src }\nPreview notices: ${ notices.join( ' | ' ) || 'none' }\nFrame text: ${ shown.slice( 0, 800 ) }\n${ String( error ) }` );
	}
	return frame;
}

test( 'screenshots of the editor, type picker, panel, Style and Tools', async ( { page } ) => {
	const store = prepare( 'classic' );
	// A product in the cart: the preview then shows a real order summary.
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await openEditor( page, store );

	// 1. The editor with the live preview.
	await page.getByRole( 'button', { name: /^Billing/ } ).click();
	const frame = await waitForPreview( page );
	await expect( frame.getByText( 'VAT number' ).first() ).toBeVisible();
	await page.waitForTimeout( 1000 ); // Let the theme's fonts and the preview settle.
	await page.screenshot( { path: file( 'screenshot-1.png' ) } );

	// 2. The type picker.
	await page.getByRole( 'button', { name: 'Add field' } ).click();
	const picker = page.getByRole( 'dialog' );
	await expect( picker ).toBeVisible();
	await page.screenshot( { path: file( 'screenshot-2.png' ) } );
	await page.keyboard.press( 'Escape' );
	await expect( picker ).toHaveCount( 0 );

	// 3. A field's settings.
	await page.locator( '.rxcf-fields__open', { hasText: 'VAT number' } ).click();
	await expect( page.getByRole( 'textbox', { name: 'Label' } ) ).toHaveValue( 'VAT number' );
	await page.screenshot( { path: file( 'screenshot-3.png' ) } );

	// 6. The Style tab, with Custom chosen so its controls show (not saved).
	await page.getByRole( 'tab', { name: 'Style' } ).click();
	const custom = page.getByRole( 'radio', { name: /Custom/ } );
	await expect( custom ).toBeVisible();
	await custom.check();
	await page.screenshot( { path: file( 'screenshot-6.png' ) } );

	// 7. Tools.
	await page.getByRole( 'tab', { name: 'Tools' } ).click();
	await expect( page.getByRole( 'button', { name: 'Download export file' } ) ).toBeVisible();
	await page.screenshot( { path: file( 'screenshot-7.png' ), fullPage: true } );
} );

test( 'screenshots of both checkouts', async ( { page } ) => {
	// 4. Classic checkout.
	let store = prepare( 'classic' );
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	const form = page.locator( 'form.checkout' );
	await expect( form ).toBeVisible();
	await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
	await form.screenshot( { path: file( 'screenshot-4.png' ) } );

	// 5. Checkout block.
	store = prepare( 'blocks' );
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	const block = page.locator( '[data-block-name="woocommerce/checkout"]' );
	await expect( block ).toBeVisible();
	await expect( page.getByLabel( 'Email address' ) ).toBeVisible();
	await expect( block.getByText( 'VAT number' ).first() ).toBeVisible();
	await page.waitForTimeout( 1000 );
	await block.screenshot( { path: file( 'screenshot-5.png' ) } );
} );

test( 'screenshot of the setup wizard', async ( { page } ) => {
	const store = prepare( 'classic', 'wizard' );
	await openEditor( page, store );
	await expect( page.getByText( 'Step 1 of 3' ) ).toBeVisible();
	await page.screenshot( { path: file( 'screenshot-8.png' ) } );

	// Leave wp-env as the other tests expect it.
	wpEvalFile( 'tests/e2e/setup.php', 'classic' );
} );

test( 'banner and icon', async ( { page } ) => {
	const banner = readFileSync( join( 'tests', 'listing', 'banner.html' ), 'utf8' );
	for ( const [ width, height, scale ] of [ [ 1544, 500, 1 ], [ 772, 250, 0.5 ] ] ) {
		await page.setViewportSize( { width, height } );
		await page.setContent( banner.replace( '<body>', `<body style="zoom:${ scale }">` ) );
		await page.screenshot( { path: file( `banner-${ width }x${ height }.png` ) } );
	}

	const icon = readFileSync( join( 'tests', 'listing', 'icon.svg' ), 'utf8' );
	for ( const size of [ 128, 256 ] ) {
		await page.setViewportSize( { width: size, height: size } );
		await page.setContent( `<style>html,body{margin:0;background:transparent}svg{display:block;width:${ size }px;height:${ size }px}</style>${ icon }` );
		await page.locator( 'svg' ).screenshot( { path: file( `icon-${ size }x${ size }.png` ), omitBackground: true } );
	}
	copyFileSync( join( 'tests', 'listing', 'icon.svg' ), file( 'icon.svg' ) );
} );
