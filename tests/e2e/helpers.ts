/**
 * Shared helpers for the end-to-end tests.
 */
import { execFileSync } from 'node:child_process';

const PLUGIN = 'wp-content/plugins/rachnix-checkout-fields';

/** What tests/e2e/setup.php reports. */
export type Store = {
	product_id: number;
	home_url: string;
	checkout_url: string;
	mode: 'classic' | 'blocks';
	date_supported: boolean;
};

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
