/**
 * Unit tests for the draft logic: what "Publish changes" sends must be exactly the edits.
 */
import {
	addField,
	buildItems,
	isCoreDefault,
	itemsInSection,
	moveItem,
	operations,
	removeItem,
	resetSection,
	stableStringify,
	updateItem,
} from './draft';

const UUID_A = '11111111-1111-4111-8111-111111111111';
const UUID_B = '22222222-2222-4222-8222-222222222222';
const UUID_C = '33333333-3333-4333-8333-333333333333';

const settings = ( extra = {} ) => ( {
	default: '',
	required: false,
	width: 'full',
	css_classes: [],
	options: [],
	validation: [],
	display: {},
	extensions: {},
	...extra,
} );

const customField = ( uuid, key, priority, extra = {} ) => ( {
	uuid,
	section: 'order',
	key,
	type: 'text',
	label: key,
	placeholder: '',
	settings: settings(),
	priority,
	enabled: true,
	is_core: false,
	...extra,
} );

const coreFields = [
	{
		section: 'billing',
		key: 'billing_company',
		label: 'Company name',
		required: false,
		priority: 30,
		width: 'full',
		override: null,
	},
	{
		section: 'billing',
		key: 'billing_phone',
		label: 'Phone',
		required: true,
		priority: 100,
		width: 'full',
		override: null,
	},
];

const textType = { slug: 'text', supports_options: false };
const selectType = { slug: 'select', supports_options: true };

describe( 'buildItems', () => {
	it( 'lists custom fields and every WooCommerce field', () => {
		const items = buildItems(
			[ customField( UUID_A, 'gift_note', 10 ) ],
			coreFields
		);

		expect( items.map( ( item ) => item.id ) ).toEqual( [
			UUID_A,
			'core:billing:billing_company',
			'core:billing:billing_phone',
		] );
		expect( items[ 1 ] ).toMatchObject( {
			kind: 'core',
			hasOverride: false,
			label: '',
			enabled: true,
			priority: 30,
		} );
	} );

	it( 'applies stored core overrides', () => {
		const override = customField( UUID_B, 'billing_company', 5, {
			section: 'billing',
			type: 'core',
			is_core: true,
			label: 'Business',
			enabled: false,
			settings: { required: false, width: 'half', css_classes: [] },
		} );
		const core = buildItems( [ override ], coreFields ).find(
			( item ) => item.key === 'billing_company'
		);

		expect( core ).toMatchObject( {
			hasOverride: true,
			uuid: UUID_B,
			label: 'Business',
			enabled: false,
			priority: 5,
		} );
		expect( core.settings.width ).toBe( 'half' );
	} );
} );

describe( 'operations', () => {
	const published = buildItems(
		[ customField( UUID_A, 'gift_note', 10 ) ],
		coreFields
	);

	it( 'is empty when nothing changed', () => {
		expect( operations( published, published ) ).toEqual( [] );
	} );

	it( 'creates a new field with its client uuid', () => {
		const { items } = addField( published, textType, {
			uuid: UUID_C,
			section: 'order',
			label: 'Floor',
		} );
		const ops = operations( published, items );

		expect( ops ).toHaveLength( 1 );
		expect( ops[ 0 ] ).toMatchObject( {
			op: 'create',
			field: { uuid: UUID_C, type: 'text', label: 'Floor', priority: 20 },
		} );
		expect( ops[ 0 ].field.key ).toMatch( /^text_[0-9a-f]{4}$/ );
	} );

	it( 'updates only what changed', () => {
		const draft = updateItem( published, UUID_A, {
			label: 'Gift message',
			settings: { required: true },
		} );

		expect( operations( published, draft ) ).toEqual( [
			{
				op: 'update',
				uuid: UUID_A,
				changes: {
					label: 'Gift message',
					settings: settings( { required: true } ),
				},
			},
		] );
	} );

	it( 'deletes removed custom fields first', () => {
		const added = addField( published, textType, {
			uuid: UUID_C,
			section: 'order',
			label: 'Floor',
		} ).items;
		const draft = removeItem( added, UUID_A );

		expect( operations( published, draft ).map( ( op ) => op.op ) ).toEqual(
			[ 'delete', 'create' ]
		);
	} );

	it( 'customises a WooCommerce field from its defaults', () => {
		const draft = updateItem( published, 'core:billing:billing_company', {
			enabled: false,
		} );

		expect( operations( published, draft ) ).toEqual( [
			{
				op: 'core',
				section: 'billing',
				key: 'billing_company',
				changes: { enabled: false },
			},
		] );
	} );

	it( 'restoring a customised WooCommerce field deletes its override', () => {
		const override = customField( UUID_B, 'billing_company', 30, {
			section: 'billing',
			type: 'core',
			is_core: true,
			label: 'Business',
			settings: { required: false, width: 'full', css_classes: [] },
		} );
		const withOverride = buildItems( [ override ], coreFields );
		const draft = removeItem(
			withOverride,
			'core:billing:billing_company'
		);

		expect( operations( withOverride, draft ) ).toEqual( [
			{ op: 'delete', uuid: UUID_B },
		] );
	} );

	it( 'moving a field renumbers its section and publishes the new priorities', () => {
		const two = addField( published, textType, {
			uuid: UUID_C,
			section: 'order',
			label: 'Floor',
		} ).items;
		const base = buildItems(
			[
				customField( UUID_A, 'gift_note', 10 ),
				customField( UUID_C, 'floor', 20 ),
			],
			coreFields
		);
		const moved = moveItem( base, 'order', UUID_C, UUID_A );

		expect(
			itemsInSection( moved, 'order' ).map( ( item ) => item.key )
		).toEqual( [ 'floor', 'gift_note' ] );
		expect( operations( base, moved ) ).toEqual( [
			{ op: 'update', uuid: UUID_A, changes: { priority: 20 } },
			{ op: 'update', uuid: UUID_C, changes: { priority: 10 } },
		] );
		expect( two ).toHaveLength( 4 );
	} );

	it( 'resetting a section removes custom fields and restores WooCommerce ones', () => {
		const draft = resetSection( published, 'order' );

		expect( operations( published, draft ) ).toEqual( [
			{ op: 'delete', uuid: UUID_A },
		] );
	} );
} );

describe( 'helpers', () => {
	it( 'gives new choice fields one starting option', () => {
		const { items, id } = addField( [], selectType, {
			uuid: UUID_A,
			section: 'order',
			label: 'Slot',
			optionLabel: 'Option 1',
		} );

		expect(
			items.find( ( item ) => item.id === id ).settings.options
		).toEqual( [ { value: 'option_1', label: 'Option 1' } ] );
	} );

	it( 'generates field keys that do not collide', () => {
		const first = addField( [], textType, {
			uuid: '44441111-0000-4000-8000-000000000000',
			section: 'order',
			label: 'A',
		} ).items;
		const second = addField( first, textType, {
			uuid: '44442222-0000-4000-8000-000000000000',
			section: 'order',
			label: 'B',
		} ).items;

		expect( new Set( second.map( ( item ) => item.key ) ).size ).toBe( 2 );
	} );

	it( 'knows when a WooCommerce field is untouched', () => {
		const core = buildItems( [], coreFields )[ 0 ];

		expect( isCoreDefault( core ) ).toBe( true );
		expect( isCoreDefault( { ...core, label: 'X' } ) ).toBe( false );
	} );

	it( 'compares objects regardless of key order', () => {
		expect( stableStringify( { b: 1, a: [ { d: 2, c: 3 } ] } ) ).toBe(
			stableStringify( { a: [ { c: 3, d: 2 } ], b: 1 } )
		);
	} );
} );
