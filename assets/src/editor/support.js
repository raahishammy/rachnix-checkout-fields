/**
 * Small browser helpers for the editor: ids, the draft backup, and undo history.
 */
import { stableStringify } from './draft';

/**
 * A v4 UUID. crypto.randomUUID() only exists on HTTPS (or localhost); getRandomValues()
 * works everywhere, so plain-HTTP stores can use the editor too.
 */
export function uuid4() {
	if ( typeof window.crypto?.randomUUID === 'function' ) {
		return window.crypto.randomUUID();
	}
	const bytes = window.crypto.getRandomValues( new Uint8Array( 16 ) );
	// Version 4 (0100xxxx) and RFC 4122 variant (10xxxxxx), written without bitwise operators.
	bytes[ 6 ] = ( bytes[ 6 ] % 16 ) + 0x40;
	bytes[ 8 ] = ( bytes[ 8 ] % 64 ) + 0x80;
	const hex = Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	return `${ hex.slice( 0, 8 ) }-${ hex.slice( 8, 12 ) }-${ hex.slice(
		12,
		16
	) }-${ hex.slice( 16, 20 ) }-${ hex.slice( 20 ) }`;
}

const BACKUP_KEY = 'rxcf-draft-v1';

/**
 * A short fingerprint of the published items: a backup only applies to the version it was made from.
 *
 * @param {Array<Object>} items Published items.
 */
export function fingerprint( items ) {
	const text = stableStringify( items );
	// djb2 kept to 32 bits with plain arithmetic (hash * 33 stays far below 2^53).
	let hash = 5381;
	for ( let i = 0; i < text.length; i++ ) {
		hash = ( hash * 33 + text.charCodeAt( i ) ) % 4294967296;
	}
	return `${ text.length }-${ hash.toString( 36 ) }`;
}

/**
 * Browser storage can be missing or blocked (private windows, strict settings); every
 * call is wrapped so the editor still works, only without a backup.
 *
 * @param {string}        published Fingerprint of the published items.
 * @param {Array<Object>} items     Draft items.
 */
export function saveBackup( published, items ) {
	try {
		window.localStorage.setItem(
			BACKUP_KEY,
			JSON.stringify( { published, items } )
		);
	} catch ( error ) {
		// No backup possible; the draft still lives in memory.
	}
}

/**
 * @param {string} published Fingerprint of the published items.
 * @return {{items: Array<Object>|null, stale: boolean}} The backup, if it matches.
 */
export function loadBackup( published ) {
	try {
		const raw = window.localStorage.getItem( BACKUP_KEY );
		if ( ! raw ) {
			return { items: null, stale: false };
		}
		const backup = JSON.parse( raw );
		if (
			backup?.published !== published ||
			! Array.isArray( backup.items )
		) {
			clearBackup();
			return { items: null, stale: true };
		}
		return { items: backup.items, stale: false };
	} catch ( error ) {
		return { items: null, stale: false };
	}
}

export function clearBackup() {
	try {
		window.localStorage.removeItem( BACKUP_KEY );
	} catch ( error ) {
		// Nothing to clear.
	}
}

const HISTORY_LIMIT = 50;

/**
 * Undo history reducer: { items, past, lastKey }. "change" keeps the previous items for undo.
 * Consecutive changes with the same `key` (e.g. typing into one label) form one undo step.
 *
 * @param {Object} state  Current state.
 * @param {Object} action { type: 'change'|'undo'|'reset', items?, key? }.
 */
export function historyReducer( state, action ) {
	switch ( action.type ) {
		case 'change':
			if ( action.items === state.items ) {
				return state;
			}
			if ( action.key && action.key === state.lastKey ) {
				return { ...state, items: action.items };
			}
			return {
				items: action.items,
				past: [ ...state.past, state.items ].slice( -HISTORY_LIMIT ),
				lastKey: action.key ?? null,
			};
		case 'undo':
			if ( ! state.past.length ) {
				return state;
			}
			return {
				items: state.past[ state.past.length - 1 ],
				past: state.past.slice( 0, -1 ),
				lastKey: null,
			};
		case 'reset':
			return { items: action.items, past: [], lastKey: null };
		default:
			return state;
	}
}
