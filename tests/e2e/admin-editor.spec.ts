/**
 * Step 8 gate, automated: a first-time owner adds a field and sees it on the checkout in
 * under 2 minutes. (The spec's real gate is 5 people doing this; this test keeps the path working.)
 * Also: nothing is live before "Publish changes", undo, and the browser backup of the draft.
 */
import { test, expect, type Page } from '@playwright/test';
import { logInAsAdmin, wpEvalFile, type Store } from './helpers';

let store: Store;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );
} );

async function logIn( page: Page ) {
	// Leaving with unpublished changes asks first; accept, as the owner would after reading it.
	page.on( 'dialog', ( dialog ) => dialog.accept() );
	await logInAsAdmin( page, store, 'admin.php?page=rxcf-checkout-fields' );
}

// Notices are scoped to '.components-notice': WordPress also copies their text into a hidden
// screen-reader live region, so a page-wide getByText() would find it twice.

async function openEditor( page: Page ) {
	await page.goto( `${ store.home_url }wp-admin/admin.php?page=rxcf-checkout-fields`, { waitUntil: 'domcontentloaded' } );
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeVisible();
}

async function checkoutHasLabel( page: Page, label: string ) {
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	return page.locator( 'form.checkout' ).getByText( label, { exact: false } ).count();
}

test( 'a first-time owner adds a field and sees it on the checkout in under 2 minutes', async ( { page } ) => {
	const started = Date.now();
	await logIn( page );
	await openEditor( page );

	await page.getByRole( 'button', { name: 'Additional information' } ).click();
	await page.getByRole( 'button', { name: 'Add field' } ).click();
	await page.getByRole( 'button', { name: 'Text', exact: true } ).click();
	await page.getByLabel( 'Label', { exact: true } ).fill( 'Gift message' );

	// Our status bar (drag and drop adds its own role="status" live region for screen readers).
	await expect( page.locator( '.rxcf-editor__status' ) ).toContainText( 'unpublished change' );
	await page.getByRole( 'button', { name: 'Publish changes' } ).click();
	await expect( page.locator( '.components-notice' ).getByText( 'Your changes are live on the checkout.' ) ).toBeVisible();

	expect( await checkoutHasLabel( page, 'Gift message' ) ).toBeGreaterThan( 0 );
	expect( Date.now() - started, 'Under 2 minutes' ).toBeLessThan( 120_000 );
} );

test( 'nothing is live before publishing, and undo takes a change back', async ( { page } ) => {
	await logIn( page );
	await openEditor( page );

	await page.getByRole( 'button', { name: 'Additional information' } ).click();
	await page.getByRole( 'button', { name: 'Add field' } ).click();
	await page.getByRole( 'button', { name: 'Text', exact: true } ).click();
	await page.getByLabel( 'Label', { exact: true } ).fill( 'Draft only field' );

	expect( await checkoutHasLabel( page, 'Draft only field' ) ).toBe( 0 );

	await openEditor( page );
	await expect( page.locator( '.components-notice' ).getByText( 'Your unpublished changes from last time were restored.' ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Additional information' } ).click();
	await expect( page.getByText( 'Draft only field' ) ).toBeVisible();

	// The restored draft is one undo step: undoing it returns to what is published.
	await page.getByRole( 'button', { name: 'Undo' } ).click();
	await expect( page.getByText( 'Draft only field' ) ).toHaveCount( 0 );
	await expect( page.getByRole( 'button', { name: 'Undo' } ) ).toBeDisabled();
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeDisabled();
} );

test( 'a WooCommerce field can be hidden and the preview shows the draft', async ( { page } ) => {
	await logIn( page );
	await openEditor( page );

	// The section button also shows how many custom fields it has, e.g. "Billing 4".
	await page.getByRole( 'button', { name: /^Billing/ } ).click();
	await page.getByRole( 'button', { name: /^Last name/ } ).click();
	await page.getByRole( 'button', { name: 'More options' } ).click();
	await page.getByLabel( 'Show this field on the checkout' ).uncheck();
	await page.getByRole( 'button', { name: 'Close and show the preview' } ).click();

	const frame = page.frameLocator( '.rxcf-preview__frame' );
	await expect( frame.locator( 'form.checkout' ) ).toBeVisible( { timeout: 30_000 } );
	await expect( frame.locator( '#billing_last_name' ) ).toHaveCount( 0 );
	await expect( frame.getByText( 'Orders cannot be placed here' ) ).toBeVisible();

	// Still shown on the real checkout until published.
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	await expect( page.locator( '#billing_last_name' ) ).toBeVisible();
} );
