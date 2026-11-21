/**
 * Unit tests for the editor helpers.
 */
import {
	clearBackup,
	fingerprint,
	historyReducer,
	loadBackup,
	saveBackup,
	uuid4,
} from './support';

describe( 'uuid4', () => {
	it( 'makes valid, distinct v4 UUIDs', () => {
		const pattern =
			/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
		const a = uuid4();
		const b = uuid4();

		expect( a ).toMatch( pattern );
		expect( b ).toMatch( pattern );
		expect( a ).not.toBe( b );
	} );

	it( 'works without crypto.randomUUID (plain-HTTP sites)', () => {
		const original = window.crypto.randomUUID;
		Object.defineProperty( window.crypto, 'randomUUID', {
			value: undefined,
			configurable: true,
		} );
		try {
			for ( let i = 0; i < 50; i++ ) {
				expect( uuid4() ).toMatch(
					/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/
				);
			}
		} finally {
			Object.defineProperty( window.crypto, 'randomUUID', {
				value: original,
				configurable: true,
			} );
		}
	} );
} );

describe( 'draft backup', () => {
	afterEach( () => clearBackup() );

	it( 'restores a backup made from the same published version', () => {
		saveBackup( 'v1', [ { id: 'a' } ] );

		expect( loadBackup( 'v1' ) ).toEqual( {
			items: [ { id: 'a' } ],
			stale: false,
		} );
	} );

	it( 'discards a backup when the published fields changed since', () => {
		saveBackup( 'v1', [ { id: 'a' } ] );

		expect( loadBackup( 'v2' ) ).toEqual( { items: null, stale: true } );
		expect( loadBackup( 'v1' ).items ).toBeNull();
	} );

	it( 'fingerprints ignore key order', () => {
		expect( fingerprint( [ { a: 1, b: 2 } ] ) ).toBe(
			fingerprint( [ { b: 2, a: 1 } ] )
		);
		expect( fingerprint( [ { a: 1 } ] ) ).not.toBe(
			fingerprint( [ { a: 2 } ] )
		);
	} );
} );

describe( 'historyReducer', () => {
	it( 'undoes changes one at a time and stops at the start', () => {
		let state = historyReducer(
			{ items: [], past: [] },
			{ type: 'reset', items: [ 1 ] }
		);
		state = historyReducer( state, { type: 'change', items: [ 1, 2 ] } );
		state = historyReducer( state, { type: 'change', items: [ 1, 2, 3 ] } );

		state = historyReducer( state, { type: 'undo' } );
		expect( state.items ).toEqual( [ 1, 2 ] );
		state = historyReducer( state, { type: 'undo' } );
		expect( state.items ).toEqual( [ 1 ] );
		state = historyReducer( state, { type: 'undo' } );
		expect( state.items ).toEqual( [ 1 ] );
	} );

	it( 'publishing (reset) clears the history', () => {
		const state = historyReducer(
			{ items: [ 1 ], past: [ [] ], lastKey: 'x' },
			{ type: 'reset', items: [ 9 ] }
		);

		expect( state ).toEqual( { items: [ 9 ], past: [], lastKey: null } );
	} );

	it( 'typing into one setting is a single undo step', () => {
		let state = { items: [ 'a' ], past: [], lastKey: null };
		state = historyReducer( state, {
			type: 'change',
			items: [ 'G' ],
			key: 'f1:label',
		} );
		state = historyReducer( state, {
			type: 'change',
			items: [ 'Gi' ],
			key: 'f1:label',
		} );
		state = historyReducer( state, {
			type: 'change',
			items: [ 'Gift' ],
			key: 'f1:label',
		} );
		state = historyReducer( state, {
			type: 'change',
			items: [ 'Gift!' ],
			key: 'f1:placeholder',
		} );

		expect( state.past ).toEqual( [ [ 'a' ], [ 'Gift' ] ] );
		expect( historyReducer( state, { type: 'undo' } ).items ).toEqual( [
			'Gift',
		] );
	} );
} );
