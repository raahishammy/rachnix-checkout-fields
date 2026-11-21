/**
 * Right column when a field is selected: Basic, Rules and "Show in" tabs.
 * Every change goes to the draft through onChange( patch, key ); `key` groups typing into one
 * undo step. The server validates everything again when publishing.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	PanelBody,
	SelectControl,
	TabPanel,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { closeSmall } from '@wordpress/icons';
import { DISPLAY_DEFAULTS } from './draft';
import { itemTitle } from './field-list';

const NOT_HIDEABLE = [ 'billing_email', 'billing_country', 'shipping_country' ];
const ALWAYS_REQUIRED = [ 'billing_email' ];

const control = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

const widthOptions = () => [
	{ value: 'full', label: __( 'Full row', 'rachnix-checkout-fields' ) },
	{ value: 'half', label: __( 'Half row', 'rachnix-checkout-fields' ) },
];

/**
 * A space-separated list of CSS classes, committed when the input loses focus
 * (so typing a space between classes is possible).
 *
 * @param {Object}   props          Props.
 * @param {string[]} props.value    Classes.
 * @param {Function} props.onChange Receives the new list.
 */
function CssClassesControl( { value, onChange } ) {
	const [ text, setText ] = useState( value.join( ' ' ) );
	useEffect( () => setText( value.join( ' ' ) ), [ value ] );

	return (
		<TextControl
			{ ...control }
			label={ __( 'CSS classes', 'rachnix-checkout-fields' ) }
			help={ __(
				'For developers. Separate with spaces, for example: gift-note highlight',
				'rachnix-checkout-fields'
			) }
			value={ text }
			onChange={ setText }
			onBlur={ () =>
				onChange( text.split( /[\s,]+/ ).filter( Boolean ) )
			}
		/>
	);
}

function OptionsEditor( { options, onChange } ) {
	const set = ( index, patch ) =>
		onChange(
			options.map( ( option, i ) =>
				i === index ? { ...option, ...patch } : option
			)
		);

	return (
		<fieldset className="rxcf-options">
			<legend>{ __( 'Choices', 'rachnix-checkout-fields' ) }</legend>
			{ options.map( ( option, index ) => (
				<div className="rxcf-options__row" key={ index }>
					<TextControl
						{ ...control }
						label={ __(
							'Shown to customers',
							'rachnix-checkout-fields'
						) }
						value={ option.label }
						onChange={ ( label ) => set( index, { label } ) }
					/>
					<TextControl
						{ ...control }
						label={ __( 'Saved value', 'rachnix-checkout-fields' ) }
						value={ option.value }
						onChange={ ( value ) => set( index, { value } ) }
					/>
					<Button
						icon={ closeSmall }
						label={ __(
							'Remove this choice',
							'rachnix-checkout-fields'
						) }
						disabled={ options.length === 1 }
						onClick={ () =>
							onChange(
								options.filter( ( _, i ) => i !== index )
							)
						}
					/>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [
						...options,
						{
							value: `option_${ options.length + 1 }`,
							label: __(
								'New choice',
								'rachnix-checkout-fields'
							),
						},
					] )
				}
			>
				{ __( 'Add a choice', 'rachnix-checkout-fields' ) }
			</Button>
		</fieldset>
	);
}

function DefaultValueControl( { item, type, onChange } ) {
	const value = item.settings.default ?? '';
	const set = ( next ) =>
		onChange( { settings: { default: next } }, 'default' );

	if ( type.slug === 'checkbox' ) {
		return (
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Ticked by default', 'rachnix-checkout-fields' ) }
				checked={ value === '1' }
				onChange={ ( on ) => set( on ? '1' : '' ) }
			/>
		);
	}
	if ( type.supports_options ) {
		return (
			<SelectControl
				{ ...control }
				label={ __( 'Chosen by default', 'rachnix-checkout-fields' ) }
				value={ value }
				options={ [
					{
						value: '',
						label: __( 'Nothing', 'rachnix-checkout-fields' ),
					},
					...item.settings.options.map( ( option ) => ( {
						value: option.value,
						label: option.label,
					} ) ),
				] }
				onChange={ set }
			/>
		);
	}
	return (
		<TextControl
			{ ...control }
			label={ __( 'Default value', 'rachnix-checkout-fields' ) }
			help={
				type.slug === 'hidden'
					? __(
							'Saved with every order, for example a campaign code.',
							'rachnix-checkout-fields'
					  )
					: __(
							'Pre-filled for the customer. Leave empty for none.',
							'rachnix-checkout-fields'
					  )
			}
			value={ value }
			onChange={ set }
		/>
	);
}

function BasicTab( { item, type, onChange } ) {
	const core = item.kind === 'core';
	const settings = item.settings;

	return (
		<div className="rxcf-panel__tab">
			<TextControl
				{ ...control }
				label={ __( 'Label', 'rachnix-checkout-fields' ) }
				placeholder={ core ? item.defaults.label : undefined }
				help={
					core
						? __(
								'Leave empty to keep WooCommerce’s label.',
								'rachnix-checkout-fields'
						  )
						: undefined
				}
				value={ item.label }
				onChange={ ( label ) => onChange( { label }, 'label' ) }
			/>
			{ ( core || type?.supports_placeholder ) && (
				<TextControl
					{ ...control }
					label={ __( 'Placeholder', 'rachnix-checkout-fields' ) }
					help={ __(
						'Grey example text inside the empty field, for example: GB123456789',
						'rachnix-checkout-fields'
					) }
					value={ item.placeholder }
					onChange={ ( placeholder ) =>
						onChange( { placeholder }, 'placeholder' )
					}
				/>
			) }
			{ ( core || type?.can_be_required ) && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Customers must fill this in',
						'rachnix-checkout-fields'
					) }
					disabled={ core && ALWAYS_REQUIRED.includes( item.key ) }
					checked={ Boolean( settings.required ) }
					onChange={ ( required ) =>
						onChange( { settings: { required } } )
					}
				/>
			) }
			<SelectControl
				{ ...control }
				label={ __( 'Width', 'rachnix-checkout-fields' ) }
				value={ settings.width }
				options={ widthOptions() }
				onChange={ ( width ) => onChange( { settings: { width } } ) }
			/>
			{ ! core && type?.supports_options && (
				<OptionsEditor
					options={ settings.options }
					onChange={ ( options ) =>
						onChange( { settings: { options } }, 'options' )
					}
				/>
			) }
			{ ! core && type && (
				<DefaultValueControl
					item={ item }
					type={ type }
					onChange={ onChange }
				/>
			) }
			<PanelBody
				title={ __( 'More options', 'rachnix-checkout-fields' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Show this field on the checkout',
						'rachnix-checkout-fields'
					) }
					help={
						core && NOT_HIDEABLE.includes( item.key )
							? __(
									'The checkout needs this field, so it cannot be hidden.',
									'rachnix-checkout-fields'
							  )
							: undefined
					}
					disabled={ core && NOT_HIDEABLE.includes( item.key ) }
					checked={ item.enabled }
					onChange={ ( enabled ) => onChange( { enabled } ) }
				/>
				{ ! core && (
					<TextControl
						{ ...control }
						label={ __( 'Field key', 'rachnix-checkout-fields' ) }
						help={
							item.isNew
								? __(
										'Used in order data and exports. Lowercase letters, digits and underscores, for example: gift_message',
										'rachnix-checkout-fields'
								  )
								: __(
										'Fixed once published, so saved orders keep their values.',
										'rachnix-checkout-fields'
								  )
						}
						disabled={ ! item.isNew }
						value={ item.key }
						onChange={ ( key ) => onChange( { key }, 'key' ) }
					/>
				) }
				<CssClassesControl
					value={ settings.css_classes ?? [] }
					onChange={ ( classes ) =>
						onChange( { settings: { css_classes: classes } } )
					}
				/>
			</PanelBody>
		</div>
	);
}

const RULE_LABELS = () => ( {
	length: __( 'Length', 'rachnix-checkout-fields' ),
	number_range: __( 'Number range', 'rachnix-checkout-fields' ),
	pattern: __( 'Format', 'rachnix-checkout-fields' ),
} );

const toNumber = ( text ) => ( text === '' ? undefined : Number( text ) );

function RulesTab( { item, type, patterns, onChange } ) {
	const rules = item.settings.validation ?? [];
	const labels = RULE_LABELS();
	const available = type.allowed_rules.filter(
		( rule ) => ! rules.some( ( existing ) => existing.rule === rule )
	);
	const [ adding, setAdding ] = useState( available[ 0 ] ?? '' );
	useEffect( () => setAdding( available[ 0 ] ?? '' ), [ available.join() ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const set = ( index, patch ) => {
		const next = rules.map( ( rule, i ) => {
			if ( i !== index ) {
				return rule;
			}
			const merged = { ...rule, ...patch };
			Object.keys( merged ).forEach(
				( key ) => merged[ key ] === undefined && delete merged[ key ]
			);
			return merged;
		} );
		onChange( { settings: { validation: next } }, `rule-${ index }` );
	};

	if ( ! type.allowed_rules.length ) {
		return (
			<p className="rxcf-panel__tab">
				{ __(
					'This field type has no extra rules. Its format is always checked.',
					'rachnix-checkout-fields'
				) }
			</p>
		);
	}

	return (
		<div className="rxcf-panel__tab">
			{ rules.map( ( rule, index ) => (
				<fieldset className="rxcf-rule" key={ rule.rule }>
					<legend>{ labels[ rule.rule ] ?? rule.rule }</legend>
					{ rule.rule === 'pattern' ? (
						<SelectControl
							{ ...control }
							label={ __(
								'Allowed format',
								'rachnix-checkout-fields'
							) }
							value={ rule.pattern ?? '' }
							options={ [
								{
									value: '',
									label: __(
										'Choose a format',
										'rachnix-checkout-fields'
									),
								},
								...patterns.map( ( pattern ) => ( {
									value: pattern.id,
									label: `${ pattern.label } (${ pattern.example })`,
								} ) ),
							] }
							onChange={ ( pattern ) =>
								set( index, { pattern } )
							}
						/>
					) : (
						<div className="rxcf-rule__range">
							<TextControl
								{ ...control }
								type="number"
								label={
									rule.rule === 'length'
										? __(
												'Fewest characters',
												'rachnix-checkout-fields'
										  )
										: __(
												'Lowest number',
												'rachnix-checkout-fields'
										  )
								}
								value={ rule.min ?? '' }
								onChange={ ( min ) =>
									set( index, { min: toNumber( min ) } )
								}
							/>
							<TextControl
								{ ...control }
								type="number"
								label={
									rule.rule === 'length'
										? __(
												'Most characters',
												'rachnix-checkout-fields'
										  )
										: __(
												'Highest number',
												'rachnix-checkout-fields'
										  )
								}
								value={ rule.max ?? '' }
								onChange={ ( max ) =>
									set( index, { max: toNumber( max ) } )
								}
							/>
						</div>
					) }
					<Button
						variant="link"
						isDestructive
						onClick={ () =>
							onChange( {
								settings: {
									validation: rules.filter(
										( _, i ) => i !== index
									),
								},
							} )
						}
					>
						{ __( 'Remove rule', 'rachnix-checkout-fields' ) }
					</Button>
				</fieldset>
			) ) }
			{ available.length > 0 && (
				<div className="rxcf-rule__add">
					<SelectControl
						{ ...control }
						label={ __( 'Add a rule', 'rachnix-checkout-fields' ) }
						value={ adding }
						options={ available.map( ( rule ) => ( {
							value: rule,
							label: labels[ rule ] ?? rule,
						} ) ) }
						onChange={ setAdding }
					/>
					<Button
						variant="secondary"
						onClick={ () =>
							onChange( {
								settings: {
									validation: [ ...rules, { rule: adding } ],
								},
							} )
						}
					>
						{ __( 'Add', 'rachnix-checkout-fields' ) }
					</Button>
				</div>
			) }
		</div>
	);
}

const DISPLAY_LABELS = () => ( {
	order_admin: __( 'Order screen in the admin', 'rachnix-checkout-fields' ),
	my_account: __(
		'Customer’s My Account order page',
		'rachnix-checkout-fields'
	),
	thank_you: __( 'Thank-you page', 'rachnix-checkout-fields' ),
	admin_email: __( 'Emails to the store', 'rachnix-checkout-fields' ),
	customer_email: __( 'Emails to the customer', 'rachnix-checkout-fields' ),
	order_list: __(
		'Column in the orders list (up to 2 fields)',
		'rachnix-checkout-fields'
	),
} );

function ShowInTab( { item, onChange } ) {
	const display = { ...DISPLAY_DEFAULTS, ...( item.settings.display ?? {} ) };
	const labels = DISPLAY_LABELS();

	return (
		<div className="rxcf-panel__tab">
			{ Object.keys( DISPLAY_DEFAULTS ).map( ( key ) => (
				<ToggleControl
					__nextHasNoMarginBottom
					key={ key }
					label={ labels[ key ] }
					checked={ display[ key ] }
					onChange={ ( on ) =>
						onChange( {
							settings: { display: { ...display, [ key ]: on } },
						} )
					}
				/>
			) ) }
		</div>
	);
}

export default function FieldPanel( {
	item,
	type,
	patterns,
	onChange,
	onRemove,
	onClose,
} ) {
	const core = item.kind === 'core';
	const tabs = [
		{ name: 'basic', title: __( 'Basic', 'rachnix-checkout-fields' ) },
	];
	if ( ! core ) {
		tabs.push(
			{ name: 'rules', title: __( 'Rules', 'rachnix-checkout-fields' ) },
			{ name: 'show', title: __( 'Show in', 'rachnix-checkout-fields' ) }
		);
	}

	return (
		<section
			className="rxcf-panel"
			aria-label={ __( 'Field settings', 'rachnix-checkout-fields' ) }
		>
			<div className="rxcf-panel__head">
				<h2>{ itemTitle( item ) }</h2>
				<Button
					icon={ closeSmall }
					label={ __(
						'Close and show the preview',
						'rachnix-checkout-fields'
					) }
					onClick={ onClose }
				/>
			</div>
			<TabPanel key={ item.id } tabs={ tabs }>
				{ ( tab ) => {
					if ( tab.name === 'rules' ) {
						return (
							<RulesTab
								item={ item }
								type={ type }
								patterns={ patterns }
								onChange={ onChange }
							/>
						);
					}
					if ( tab.name === 'show' ) {
						return (
							<ShowInTab item={ item } onChange={ onChange } />
						);
					}
					return (
						<BasicTab
							item={ item }
							type={ type }
							onChange={ onChange }
						/>
					);
				} }
			</TabPanel>
			<div className="rxcf-panel__foot">
				<Button variant="secondary" isDestructive onClick={ onRemove }>
					{ core
						? __(
								'Restore WooCommerce’s settings',
								'rachnix-checkout-fields'
						  )
						: __( 'Delete field', 'rachnix-checkout-fields' ) }
				</Button>
			</div>
		</section>
	);
}
