/**
 * Step 11 performance budget (spec, "Compatibility, testing and release checklist"):
 * - under 5 ms of PHP time added at checkout;
 * - admin assets only on our screen, no front-end JavaScript, checkout CSS only on the checkout.
 *
 * The time comes from the wp-env-only probe tests/perf/mu-plugins/rxcf-profiler.php: with the
 * cookie rxcf_profile=1 it times our main file and every callback we added, and returns the
 * result in the X-Rxcf-Profile header. The median of 10 warm requests is compared with the budget.
 */
import { test, expect, type Page, type Response } from '@playwright/test';
import { logInAsAdmin, wpEvalFile, type Store } from './helpers';

const BUDGET_MS = 5;
const RUNS = 10;
const PLUGIN_ASSETS = 'plugins/rachnix-checkout-fields/';

type Profile = { load_ms: number; hooks_ms: number; total_ms: number; calls: number };

test.describe.configure( { mode: 'serial' } );

function profileOf( response: Response | null ): Profile | null {
	const header = response?.headers()[ 'x-rxcf-profile' ];
	return header ? ( JSON.parse( header ) as Profile ) : null;
}

function median( values: number[] ): number {
	const sorted = [ ...values ].sort( ( a, b ) => a - b );
	const middle = Math.floor( sorted.length / 2 );
	return sorted.length % 2 ? sorted[ middle ] : ( sorted[ middle - 1 ] + sorted[ middle ] ) / 2;
}

/**
 * Loads the checkout RUNS times (after warm-up loads for OPcache and the field cache) and
 * returns the page profiles plus those of the AJAX / Store API requests the page makes.
 */
async function measureCheckout( page: Page, store: Store ) {
	await page.context().addCookies( [ { name: 'rxcf_profile', value: '1', url: store.home_url } ] );
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );

	const pages: Profile[] = [];
	const requests: Profile[] = [];
	const onResponse = ( response: Response ) => {
		const profile = profileOf( response );
		if ( profile && response.request().resourceType() !== 'document' ) {
			requests.push( profile );
		}
	};

	for ( let run = 0; run < 3; run++ ) {
		await page.goto( store.checkout_url, { waitUntil: 'networkidle' } );
	}
	page.on( 'response', onResponse );
	for ( let run = 0; run < RUNS; run++ ) {
		const profile = profileOf( await page.goto( store.checkout_url, { waitUntil: 'networkidle' } ) );
		expect( profile, 'The timing probe answered (is tests/perf/mu-plugins mapped in wp-env?)' ).not.toBeNull();
		pages.push( profile as Profile );
	}
	page.off( 'response', onResponse );
	await page.context().clearCookies( { name: 'rxcf_profile' } );

	const report = {
		page_median_ms: median( pages.map( ( p ) => p.total_ms ) ),
		page_load_median_ms: median( pages.map( ( p ) => p.load_ms ) ),
		page_calls: pages[ 0 ].calls,
		requests: requests.length,
		requests_median_ms: requests.length ? median( requests.map( ( p ) => p.total_ms ) ) : 0,
	};
	test.info().annotations.push( { type: 'timing', description: JSON.stringify( report ) } );
	console.log( 'RXCF timing', JSON.stringify( report ) ); // eslint-disable-line no-console
	return report;
}

test( 'classic checkout: under 5 ms added', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );
	const report = await measureCheckout( page, store );

	expect( report.page_median_ms ).toBeLessThan( BUDGET_MS );
	expect( report.requests, 'update_order_review was measured' ).toBeGreaterThan( 0 );
	expect( report.requests_median_ms ).toBeLessThan( BUDGET_MS );
} );

test( 'block checkout: under 5 ms added', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'blocks' );
	const report = await measureCheckout( page, store );

	expect( report.page_median_ms ).toBeLessThan( BUDGET_MS );
	expect( report.requests_median_ms ).toBeLessThan( BUDGET_MS );
} );

async function pluginAssets( page: Page ): Promise< string[] > {
	return page.locator( 'script[src], link[rel="stylesheet"][href]' ).evaluateAll(
		( nodes, needle ) =>
			nodes
				.map( ( node ) => node.getAttribute( 'src' ) ?? node.getAttribute( 'href' ) ?? '' )
				.filter( ( url ) => url.includes( needle ) ),
		PLUGIN_ASSETS
	);
}

test( 'assets load only where they are needed', async ( { page } ) => {
	const store = wpEvalFile< Store >( 'tests/e2e/setup.php', 'classic' );

	await page.goto( store.home_url );
	expect( await pluginAssets( page ), 'Nothing on other front-end pages' ).toEqual( [] );

	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	const checkout = await pluginAssets( page );
	expect( checkout.filter( ( url ) => url.includes( '.js' ) ), 'No JavaScript on the checkout' ).toEqual( [] );
	expect( checkout.some( ( url ) => url.includes( 'assets/css/checkout.css' ) ) ).toBe( true );

	await logInAsAdmin( page, store, 'admin.php?page=wc-orders' );
	expect( await pluginAssets( page ), 'Nothing on other admin screens' ).toEqual( [] );

	await page.goto( `${ store.home_url }wp-admin/admin.php?page=rxcf-checkout-fields`, { waitUntil: 'domcontentloaded' } );
	const admin = await pluginAssets( page );
	expect( admin.some( ( url ) => url.includes( 'assets/build/index.js' ) ) ).toBe( true );
	expect( admin.some( ( url ) => /assets\/build\/(style-)?index\.css/.test( url ) ), 'The admin stylesheet loads' ).toBe( true );
} );
