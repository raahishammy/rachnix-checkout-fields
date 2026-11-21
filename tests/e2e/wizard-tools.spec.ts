/**
 * Step 10 gates: the setup wizard finishes in 3 steps, and an exported file imports cleanly
 * after everything was reset (the "fresh site" case on the same store).
 */
import { test, expect, type Page } from '@playwright/test';
import { logInAsAdmin, wpEvalFile, type Store } from './helpers';

let store: Store;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	// `wizard`: the setup wizard has not run yet, as on a new install.
	store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic', 'wizard' );
} );

const ADMIN = 'admin.php?page=rxcf-checkout-fields';

async function checkout( page: Page ) {
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	await expect( page.locator( 'form.checkout' ) ).toBeVisible();
	return page.locator( 'form.checkout' );
}

test( 'the setup wizard finishes in 3 steps', async ( { page } ) => {
	await logInAsAdmin( page, store, ADMIN );

	await expect( page.getByText( 'Step 1 of 3' ) ).toBeVisible();
	await page.getByLabel( /Hide .Phone./ ).check();
	await page.getByRole( 'button', { name: 'Next' } ).click();

	await expect( page.getByText( 'Step 2 of 3' ) ).toBeVisible();
	await page.getByLabel( 'Field type' ).selectOption( 'text' );
	await page.getByLabel( 'Label', { exact: true } ).fill( 'Wizard field' );
	await page.getByRole( 'button', { name: 'Next' } ).click();

	await expect( page.getByText( 'Step 3 of 3' ) ).toBeVisible();
	await page.getByLabel( /Plugin style/ ).check();
	await page.getByRole( 'button', { name: 'Finish' } ).click();

	// The editor replaces the wizard, and it does not come back.
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeVisible();
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeVisible();
	await expect( page.getByText( 'Step 1 of 3' ) ).toHaveCount( 0 );

	const form = await checkout( page );
	await expect( form.getByText( 'Wizard field' ) ).toBeVisible();
	await expect( form.locator( '#billing_phone' ) ).toHaveCount( 0 );
	await expect( page.locator( 'body' ) ).toHaveClass( /rxcf-style-plugin/ );
} );

test( 'an export imports cleanly after resetting everything', async ( { page } ) => {
	await logInAsAdmin( page, store, ADMIN );
	await page.getByRole( 'tab', { name: 'Tools' } ).click();

	const downloading = page.waitForEvent( 'download' );
	await page.getByRole( 'button', { name: 'Download export file' } ).click();
	const exported = await ( await downloading ).path();

	const resetAll = async () => {
		await page.getByRole( 'button', { name: 'Reset everything' } ).click();
		await page.getByRole( 'dialog' ).getByRole( 'button', { name: 'Reset everything' } ).click();
		await expect( page.locator( '.components-notice' ).getByText( /Everything is back to/ ) ).toBeVisible();
	};
	await resetAll();

	let form = await checkout( page );
	await expect( form.getByText( 'Wizard field' ) ).toHaveCount( 0 );
	await expect( form.locator( '#billing_phone' ) ).toHaveCount( 1 );

	await page.goto( `${ store.home_url }wp-admin/${ ADMIN }`, { waitUntil: 'domcontentloaded' } );
	await page.getByRole( 'tab', { name: 'Tools' } ).click();
	await page.getByLabel( 'Choose an export file' ).setInputFiles( exported );

	// A file from this store: signed, so no extra confirmation; the diff lists what comes back.
	const preview = page.locator( '.rxcf-tools__preview' );
	await expect( preview.getByText( 'This file was exported from this store' ) ).toBeVisible();
	await expect( preview.locator( '.rxcf-tools__summary' ) ).toContainText( /text_[0-9a-f]{4}/ );
	await preview.getByRole( 'button', { name: 'Import', exact: true } ).click();
	await expect( page.locator( '.components-notice' ).getByText( 'The settings were imported' ) ).toBeVisible();

	form = await checkout( page );
	await expect( form.getByText( 'Wizard field' ) ).toBeVisible();
	await expect( form.locator( '#billing_phone' ) ).toHaveCount( 0 );
	await expect( page.locator( 'body' ) ).toHaveClass( /rxcf-style-plugin/ );

	// Leave the store as the other tests expect it: no fields, theme style.
	await page.goto( `${ store.home_url }wp-admin/${ ADMIN }`, { waitUntil: 'domcontentloaded' } );
	await page.getByRole( 'tab', { name: 'Tools' } ).click();
	await resetAll();
} );
