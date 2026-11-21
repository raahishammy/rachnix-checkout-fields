/**
 * Step 7 gate: checkout screenshots on one theme (set RXCF_THEME), desktop and mobile,
 * classic and block checkout, plus the Plugin and Custom style modes.
 *
 * Automated checks catch the breaks a person would see: horizontal scrolling, fields cut
 * off at the screen edge, fields squeezed too narrow, and fields overlapping each other.
 * Screenshots go to test-results/themes/<theme>/ for review (uploaded by CI).
 *
 * Runs only when RXCF_THEME is set (CI job "themes"); the theme must already be active.
 */
import { test, expect, type Page } from '@playwright/test';
import { wpEvalFile, type Store } from './helpers';

const THEME = process.env.RXCF_THEME ?? '';

const VIEWPORTS = {
	desktop: { width: 1280, height: 900 },
	mobile: { width: 390, height: 844 },
} as const;

test.skip( ! THEME, 'Set RXCF_THEME to run the theme screenshots.' );
test.describe.configure( { mode: 'serial' } );

async function openCheckout( page: Page, store: Store ) {
	await page.goto( `${ store.home_url }?add-to-cart=${ store.product_id }` );
	await page.goto( store.checkout_url );
	if ( 'blocks' === store.mode ) {
		await expect( page.locator( '[data-block-name="woocommerce/checkout"]' ) ).toBeVisible();
		await expect( page.getByLabel( 'Email address' ) ).toBeVisible();
	} else {
		await expect( page.locator( 'form.checkout' ) ).toBeVisible();
		await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
	}
}

/** Returns a description of every layout break on the page. */
async function layoutProblems( page: Page ): Promise< string[] > {
	return page.evaluate( () => {
		const problems: string[] = [];
		const width = window.innerWidth;

		if ( document.documentElement.scrollWidth > width + 1 ) {
			problems.push( `page scrolls sideways (${ document.documentElement.scrollWidth }px wide in a ${ width }px window)` );
		}

		const controls = Array.from(
			document.querySelectorAll< HTMLElement >(
				'form.checkout :is(input:not([type=hidden]),select,textarea), .wc-block-checkout :is(input:not([type=hidden]),select,textarea)'
			)
		)
			.filter( ( el ) => ! el.classList.contains( 'select2-hidden-accessible' ) )
			// Deliberately hidden controls, e.g. the block checkout parks "Apartment, suite"
			// off-screen (for autofill) until "+ Add apartment" is clicked.
			.filter( ( el ) => ! el.closest( '[aria-hidden="true"]' ) )
			.filter( ( el ) => {
				const style = getComputedStyle( el );
				const box = el.getBoundingClientRect();
				const entirelyOffScreen = box.right <= 0 || box.left >= width;
				return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 1 && box.height > 1 && ! entirelyOffScreen;
			} )
			.map( ( el ) => ( {
				name: el.getAttribute( 'name' ) || el.id || el.tagName.toLowerCase(),
				box: el.getBoundingClientRect(),
				textLike: ! [ 'checkbox', 'radio' ].includes( ( el as HTMLInputElement ).type ),
			} ) );

		for ( const c of controls ) {
			// Partly visible but running past the edge. (A control pushed wholly off-screen by a
			// real break makes the page scroll sideways, which the first check reports.)
			if ( c.box.left < -1 || c.box.right > width + 1 ) {
				problems.push( `${ c.name } is cut off at the screen edge (spans ${ Math.round( c.box.left ) }px to ${ Math.round( c.box.right ) }px in a ${ width }px window)` );
			}
			if ( c.textLike && c.box.width < 60 ) {
				problems.push( `${ c.name } is only ${ Math.round( c.box.width ) }px wide` );
			}
		}

		for ( let i = 0; i < controls.length; i++ ) {
			for ( let j = i + 1; j < controls.length; j++ ) {
				const a = controls[ i ].box;
				const b = controls[ j ].box;
				const overlapX = Math.min( a.right, b.right ) - Math.max( a.left, b.left );
				const overlapY = Math.min( a.bottom, b.bottom ) - Math.max( a.top, b.top );
				if ( overlapX > 2 && overlapY > 2 ) {
					problems.push( `${ controls[ i ].name } overlaps ${ controls[ j ].name }` );
				}
			}
		}
		if ( problems.length ) {
			// Diagnosis: the outermost element wider than the window, and how the page clips it.
			const wide = Array.from( document.querySelectorAll< HTMLElement >( 'body *' ) ).find( ( el ) => {
				const box = el.getBoundingClientRect();
				return ( box.right > width + 1 || box.left < -1 ) && box.width > 0 && box.height > 0;
			} );
			const describe = ( el: Element | null ) => ( el ? `${ el.tagName.toLowerCase() }${ el.id ? '#' + el.id : '' }.${ Array.from( el.classList ).join( '.' ) }` : 'none' );
			problems.push(
				`diagnosis: first element past the edge is ${ describe( wide ?? null ) } (${ wide ? Math.round( wide.getBoundingClientRect().width ) : 0 }px wide, parent ${ describe( wide?.parentElement ?? null ) }); ` +
					`html overflow-x ${ getComputedStyle( document.documentElement ).overflowX }, body overflow-x ${ getComputedStyle( document.body ).overflowX }`
			);
		}
		return problems;
	} );
}

async function check( page: Page, store: Store, label: string ) {
	await openCheckout( page, store );
	await page.screenshot( { path: `test-results/themes/${ THEME }/${ label }.png`, fullPage: true } );
	expect( await layoutProblems( page ), `${ THEME } / ${ label }` ).toEqual( [] );
}

for ( const checkout of [ 'classic', 'blocks' ] as const ) {
	test.describe( `${ checkout } checkout`, () => {
		let store: Store;

		test.beforeAll( () => {
			store = wpEvalFile< Store >( 'tests/e2e/setup.php', checkout );
			wpEvalFile( 'tests/e2e/style.php', 'theme' );
		} );

		for ( const [ viewport, size ] of Object.entries( VIEWPORTS ) ) {
			test( `${ viewport }, follow theme`, async ( { page } ) => {
				await page.setViewportSize( size );
				await check( page, store, `${ checkout }-${ viewport }-theme` );

				if ( 'classic' === checkout ) {
					// Every visible custom field rendered as a usable control.
					for ( const name of [ 'rx_vat', 'rx_alt_email', 'rx_mobile', 'rx_note', 'rx_floor', 'rx_pin', 'rx_slot', 'rx_date' ] ) {
						await expect( page.locator( `[name="rxcf_${ name }"]` ) ).toBeVisible();
					}
				}
			} );
		}

		if ( 'classic' === checkout ) {
			for ( const mode of [ 'plugin', 'custom' ] as const ) {
				test( `desktop and mobile, ${ mode } style`, async ( { page } ) => {
					wpEvalFile( 'tests/e2e/style.php', mode );
					for ( const [ viewport, size ] of Object.entries( VIEWPORTS ) ) {
						await page.setViewportSize( size );
						await check( page, store, `${ checkout }-${ viewport }-${ mode }` );
					}
					await expect( page.locator( 'body' ) ).toHaveClass( new RegExp( `rxcf-style-${ mode }` ) );
					wpEvalFile( 'tests/e2e/style.php', 'theme' );
				} );
			}
		}
	} );
}
