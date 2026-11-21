/**
 * Step 11 accessibility audit: axe-core (WCAG 2.2 A and AA rules) on everything we render,
 * plus keyboard-only reordering. Serious and critical findings fail the test; minor and
 * moderate ones are listed in the report's annotations.
 *
 * On the checkouts only our own fields are judged: the theme and WooCommerce are not ours to fix.
 */
import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { logInAsAdmin, wpEvalFile, type Store } from './helpers';

const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];
const ADMIN = 'admin.php?page=rxcf-checkout-fields';

test.describe.configure( { mode: 'serial' } );

type Filter = ( node: { html: string; target: unknown[] } ) => boolean;

async function audit( page: Page, include: string, where: string, ours: Filter = () => true ) {
	const results = await new AxeBuilder( { page } ).include( include ).withTags( TAGS ).analyze();
	const violations = results.violations
		.map( ( violation ) => ( { ...violation, nodes: violation.nodes.filter( ours ) } ) )
		.filter( ( violation ) => violation.nodes.length > 0 );

	for ( const violation of violations ) {
		test.info().annotations.push( {
			type: `axe ${ violation.impact }`,
			description: `${ where }: ${ violation.id } (${ violation.help }) at ${ violation.nodes.map( ( node ) => node.target.join( ' ' ) ).join( ', ' ) }`,
		} );
	}
	const blocking = violations
		.filter( ( violation ) => violation.impact === 'serious' || violation.impact === 'critical' )
		.map( ( violation ) => `${ violation.id }: ${ violation.help } → ${ violation.nodes.map( ( node ) => node.html ).join( ' | ' ) }` );
	expect( blocking, `${ where }: serious or critical accessibility problems` ).toEqual( [] );
}

test( 'setup wizard', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic', 'wizard' );
	await logInAsAdmin( page, store, ADMIN );

	await expect( page.getByText( 'Step 1 of 3' ) ).toBeVisible();
	await audit( page, '.rxcf-admin', 'Wizard step 1' );
	await page.getByRole( 'button', { name: 'Next' } ).click();
	await page.getByLabel( 'Field type' ).selectOption( 'text' );
	await audit( page, '.rxcf-admin', 'Wizard step 2' );
	await page.getByLabel( 'Label', { exact: true } ).fill( 'Gift message' );
	await page.getByRole( 'button', { name: 'Next' } ).click();
	await audit( page, '.rxcf-admin', 'Wizard step 3' );
	await page.getByRole( 'button', { name: 'Skip setup' } ).click();
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeVisible();
} );

test( 'editor, field panel, style and tools tabs', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );
	await logInAsAdmin( page, store, ADMIN );
	await expect( page.getByRole( 'button', { name: 'Publish changes' } ) ).toBeVisible();
	await audit( page, '.rxcf-admin', 'Fields tab' );

	await page.getByRole( 'button', { name: 'Additional information' } ).click();
	await page.getByRole( 'button', { name: 'Add field' } ).click();
	await audit( page, '.rxcf-admin', 'Field type picker' );
	await page.getByRole( 'button', { name: 'Text', exact: true } ).click();
	await expect( page.getByLabel( 'Label', { exact: true } ) ).toBeVisible();
	await audit( page, '.rxcf-admin', 'Field panel' );

	await page.getByRole( 'tab', { name: 'Style' } ).click();
	await audit( page, '.rxcf-admin', 'Style tab' );
	await page.getByRole( 'tab', { name: 'Tools' } ).click();
	await expect( page.getByRole( 'button', { name: 'Download export file' } ) ).toBeVisible();
	await audit( page, '.rxcf-admin', 'Tools tab' );
} );

test( 'fields can be reordered with the keyboard only', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );
	await logInAsAdmin( page, store, ADMIN );
	await page.getByRole( 'button', { name: /^Billing/ } ).click();

	const titles = page.locator( '.rxcf-fields__row .rxcf-fields__title' );
	const first = ( await titles.nth( 0 ).innerText() ).trim();
	const second = ( await titles.nth( 1 ).innerText() ).trim();

	// Wait for each stage: dnd-kit picks the item up, moves it over the next one, then drops it.
	// Its screen-reader live region (role status, id DndLiveRegion-*) announces every step.
	const announcer = page.locator( '[id^="DndLiveRegion"]' );
	await page.getByRole( 'button', { name: `Move ${ first }` } ).focus();
	await page.keyboard.press( 'Space' );
	await expect( page.locator( '.rxcf-fields__row.is-dragging' ) ).toHaveCount( 1 );
	await page.keyboard.press( 'ArrowDown' );
	await expect( announcer ).toContainText( 'moved over' );
	await page.keyboard.press( 'Space' );

	await expect( titles.nth( 0 ) ).toHaveText( second );
	await expect( titles.nth( 1 ) ).toHaveText( first );
	await expect( page.locator( '.rxcf-editor__status' ) ).toContainText( 'unpublished change' );
} );

test( 'classic checkout fields, with errors shown', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	await expect( page.locator( 'form.checkout' ) ).toBeVisible();
	await audit( page, '[id^="rxcf_"][id$="_field"]', 'Classic checkout' );

	await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
	await page.click( '#place_order' );
	await expect( page.locator( '.woocommerce-NoticeGroup-checkout' ) ).toBeVisible();
	await audit( page, '[id^="rxcf_"][id$="_field"]', 'Classic checkout with errors' );
} );

test( 'block checkout fields, with errors shown', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'blocks' );
	const ours: Filter = ( node ) => /rachnix/.test( node.html ) || /rachnix/.test( node.target.join( ' ' ) );

	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	await expect( page.getByLabel( /^VAT number/ ) ).toBeVisible();
	await audit( page, '[data-block-name="woocommerce/checkout"]', 'Block checkout', ours );

	await page.getByRole( 'button', { name: /place order/i } ).click();
	await expect( page.locator( '.wc-block-components-validation-error' ).first() ).toBeVisible();
	await audit( page, '[data-block-name="woocommerce/checkout"]', 'Block checkout with errors', ours );
} );
