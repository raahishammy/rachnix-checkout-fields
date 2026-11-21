/**
 * The editor's draft: a flat list of items, one per field the owner can change.
 *
 * - Custom fields: { kind: 'custom', id: uuid, ... }.
 * - WooCommerce's own fields: { kind: 'core', id: 'core:{section}:{key}', defaults, hasOverride, ... }.
 *
 * Everything here is pure (no React, no requests) so it can be unit tested. Publishing compares
 * the published items with the draft items and sends the difference as operations
 * (see FieldService::apply_batch() on the server).
 */

export const DISPLAY_DEFAULTS = {
	order_admin: true,
	my_account: true,
	thank_you: true,
	admin_email: true,
	customer_email: true,
	order_list: false,
};

export const coreId = ( section, key ) => `core:${ section }:${ key }`;

/**
 * Items from the API: GET /fields and GET /core-fields.
 *
 * @param {Array<Object>} fields     Stored fields (custom fields and core overrides).
 * @param {Array<Object>} coreFields WooCommerce defaults, each with its override or null.
 * @return {Array<Object>} Items.
 */
export function buildItems( fields, coreFields ) {
	const overrides = new Map();
	const items = [];

	for ( const field of fields ) {
		if ( field.is_core ) {
			overrides.set( `${ field.section }:${ field.key }`, field );
		} else {
			items.push( {
				id: field.uuid,
				kind: 'custom',
				uuid: field.uuid,
				section: field.section,
				key: field.key,
				type: field.type,
				label: field.label,
				placeholder: field.placeholder,
				settings: field.settings,
				priority: field.priority,
				enabled: field.enabled,
			} );
		}
	}

	for ( const core of coreFields ) {
		const override =
			core.override ??
			overrides.get( `${ core.section }:${ core.key }` ) ??
			null;
		items.push( {
			id: coreId( core.section, core.key ),
			kind: 'core',
			uuid: override?.uuid ?? null,
			hasOverride: Boolean( override ),
			section: core.section,
			key: core.key,
			type: 'core',
			defaults: {
				label: core.label,
				required: core.required,
				priority: core.priority,
				width: core.width,
			},
			label: override?.label ?? '',
			placeholder: override?.placeholder ?? '',
			settings: {
				required: override ? override.settings.required : core.required,
				width: override ? override.settings.width : core.width,
				css_classes: override?.settings.css_classes ?? [],
			},
			priority: override ? override.priority : core.priority,
			enabled: override ? override.enabled : true,
		} );
	}
	return items;
}

/**
 * Items of one section, in checkout order.
 *
 * @param {Array<Object>} items   Items.
 * @param {string}        section Section key.
 */
export function itemsInSection( items, section ) {
	return items
		.filter( ( item ) => item.section === section )
		.sort(
			( a, b ) => a.priority - b.priority || a.key.localeCompare( b.key )
		);
}

/**
 * Shallow merge of a change into one item; `settings` is merged one level deeper.
 *
 * @param {Array<Object>} items Items.
 * @param {string}        id    Item id.
 * @param {Object}        patch Changes.
 */
export function updateItem( items, id, patch ) {
	return items.map( ( item ) => {
		if ( item.id !== id ) {
			return item;
		}
		const next = { ...item, ...patch };
		if ( patch.settings ) {
			next.settings = { ...item.settings, ...patch.settings };
		}
		return next;
	} );
}

/**
 * Moves an item within its section; the section's priorities become 10, 20, 30, ...
 *
 * @param {Array<Object>} items   Items.
 * @param {string}        section Section key.
 * @param {string}        fromId  Moved item.
 * @param {string}        toId    Item whose place it takes.
 */
export function moveItem( items, section, fromId, toId ) {
	const ordered = itemsInSection( items, section ).map( ( item ) => item.id );
	const from = ordered.indexOf( fromId );
	const to = ordered.indexOf( toId );
	if ( from < 0 || to < 0 || from === to ) {
		return items;
	}
	ordered.splice( to, 0, ordered.splice( from, 1 )[ 0 ] );
	const priority = new Map(
		ordered.map( ( id, index ) => [ id, ( index + 1 ) * 10 ] )
	);
	return items.map( ( item ) =>
		priority.has( item.id )
			? { ...item, priority: priority.get( item.id ) }
			: item
	);
}

/**
 * A new custom field at the end of its section.
 *
 * @param {Array<Object>} items            Items.
 * @param {Object}        type             Field type from GET /field-types.
 * @param {Object}        args             New field.
 * @param {string}        args.uuid        Its id, chosen in the browser.
 * @param {string}        args.section     Section key.
 * @param {string}        args.label       Starting label.
 * @param {string}        args.optionLabel Label of the first choice (choice types).
 * @return {{ items: Array<Object>, id: string }} New items and the new field's id.
 */
export function addField( items, type, { uuid, section, label, optionLabel } ) {
	const last = itemsInSection( items, section ).at( -1 );
	const item = {
		id: uuid,
		kind: 'custom',
		uuid,
		isNew: true,
		section,
		key: uniqueKey( items, type.slug, uuid ),
		type: type.slug,
		label,
		placeholder: '',
		settings: {
			default: '',
			required: false,
			width: 'full',
			css_classes: [],
			options: type.supports_options
				? [ { value: 'option_1', label: optionLabel } ]
				: [],
			validation: [],
			display: { ...DISPLAY_DEFAULTS },
			extensions: {},
		},
		priority: ( last?.priority ?? 0 ) + 10,
		enabled: true,
	};
	return { items: [ ...items, item ], id: uuid };
}

/**
 * Custom fields are removed; WooCommerce fields go back to WooCommerce's settings.
 *
 * @param {Array<Object>} items Items.
 * @param {string}        id    Item id.
 */
export function removeItem( items, id ) {
	return items.flatMap( ( item ) => {
		if ( item.id !== id ) {
			return [ item ];
		}
		return item.kind === 'core' ? [ restoreCore( item ) ] : [];
	} );
}

/**
 * Removes a section's custom fields and restores its WooCommerce fields.
 *
 * @param {Array<Object>} items   Items.
 * @param {string}        section Section key.
 */
export function resetSection( items, section ) {
	return items.flatMap( ( item ) => {
		if ( item.section !== section ) {
			return [ item ];
		}
		return item.kind === 'core' ? [ restoreCore( item ) ] : [];
	} );
}

/**
 * The operations that turn `published` into `draft`, in a safe order: deletes first,
 * so a key or position freed by a deleted field can be reused by another.
 *
 * @param {Array<Object>} published Items as published.
 * @param {Array<Object>} draft     Items as edited.
 * @return {Array<Object>} Operations for POST /publish and /preview.
 */
export function operations( published, draft ) {
	const before = new Map( published.map( ( item ) => [ item.id, item ] ) );
	const draftIds = new Set( draft.map( ( item ) => item.id ) );
	const deletes = [];
	const others = [];

	for ( const item of published ) {
		if ( item.kind === 'custom' && ! draftIds.has( item.id ) ) {
			deletes.push( { op: 'delete', uuid: item.uuid } );
		}
	}

	for ( const item of draft ) {
		const old = before.get( item.id );

		if ( item.kind === 'custom' ) {
			if ( ! old ) {
				others.push( { op: 'create', field: fieldData( item ) } );
			} else {
				const changes = customChanges( old, item );
				if ( Object.keys( changes ).length ) {
					others.push( { op: 'update', uuid: item.uuid, changes } );
				}
			}
			continue;
		}

		if ( isCoreDefault( item ) ) {
			if ( old?.hasOverride ) {
				deletes.push( { op: 'delete', uuid: old.uuid } );
			}
			continue;
		}
		const changes = coreChanges( old?.hasOverride ? old : null, item );
		if ( Object.keys( changes ).length ) {
			others.push( {
				op: 'core',
				section: item.section,
				key: item.key,
				changes,
			} );
		}
	}

	return [ ...deletes, ...others ];
}

/**
 * Whether a WooCommerce field is exactly as WooCommerce defines it.
 *
 * @param {Object} item Core item.
 */
export function isCoreDefault( item ) {
	return (
		item.label === '' &&
		item.placeholder === '' &&
		item.enabled &&
		item.priority === item.defaults.priority &&
		item.settings.required === item.defaults.required &&
		item.settings.width === item.defaults.width &&
		item.settings.css_classes.length === 0
	);
}

/**
 * JSON with sorted object keys, so equal values always compare equal.
 *
 * @param {*} value Value.
 */
export function stableStringify( value ) {
	if ( Array.isArray( value ) ) {
		return `[${ value.map( stableStringify ).join( ',' ) }]`;
	}
	if ( value && typeof value === 'object' ) {
		return `{${ Object.keys( value )
			.sort()
			.map(
				( key ) =>
					`${ JSON.stringify( key ) }:${ stableStringify(
						value[ key ]
					) }`
			)
			.join( ',' ) }}`;
	}
	return JSON.stringify( value ) ?? 'null';
}

function restoreCore( item ) {
	return {
		...item,
		label: '',
		placeholder: '',
		enabled: true,
		priority: item.defaults.priority,
		settings: {
			required: item.defaults.required,
			width: item.defaults.width,
			css_classes: [],
		},
	};
}

function uniqueKey( items, slug, uuid ) {
	const taken = new Set( items.map( ( item ) => item.key ) );
	const hex = uuid.replace( /[^0-9a-f]/g, '' );
	for ( let length = 4; length <= hex.length; length += 2 ) {
		const key = `${ slug }_${ hex.slice( 0, length ) }`;
		if ( ! taken.has( key ) ) {
			return key;
		}
	}
	return `${ slug }_${ hex }`;
}

function fieldData( item ) {
	return {
		uuid: item.uuid,
		section: item.section,
		key: item.key,
		type: item.type,
		label: item.label,
		placeholder: item.placeholder,
		priority: item.priority,
		enabled: item.enabled,
		settings: item.settings,
	};
}

function customChanges( old, item ) {
	const changes = {};
	for ( const prop of [
		'key',
		'label',
		'placeholder',
		'priority',
		'enabled',
	] ) {
		if ( old[ prop ] !== item[ prop ] ) {
			changes[ prop ] = item[ prop ];
		}
	}
	if (
		stableStringify( old.settings ) !== stableStringify( item.settings )
	) {
		changes.settings = item.settings;
	}
	return changes;
}

function coreChanges( old, item ) {
	// Without a stored override, every non-default value is sent; the server starts from
	// WooCommerce's defaults. With one, only what differs from the stored override.
	const base = old ?? restoreCore( item );
	const changes = {};
	for ( const prop of [ 'label', 'placeholder', 'priority', 'enabled' ] ) {
		if ( base[ prop ] !== item[ prop ] ) {
			changes[ prop ] = item[ prop ];
		}
	}
	if (
		stableStringify( base.settings ) !== stableStringify( item.settings )
	) {
		changes.settings = item.settings;
	}
	return changes;
}
