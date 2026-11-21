/**
 * First-run setup in three steps: your checkout, a first field, a style.
 * "Finish" publishes everything at once; "Skip" goes straight to the editor.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import * as api from './api';
import { addField, operations } from './editor/draft';
import { uuid4 } from './editor/support';

const config = window.rxcfAdmin ?? {};

/** WooCommerce fields many stores do not need, offered for hiding in step 1. */
const OPTIONAL_CORE = [
	'billing_company',
	'billing_address_2',
	'billing_phone',
];

const STEPS = 3;

export default function Wizard( { onDone } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ step, setStep ] = useState( 1 );
	const [ hide, setHide ] = useState( [] );
	const [ field, setField ] = useState( {
		type: '',
		label: '',
		section: 'order',
	} );
	const [ mode, setMode ] = useState( 'theme' );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		Promise.all( [ api.getCoreFields(), api.getFieldTypes() ] )
			.then( ( [ coreFields, meta ] ) =>
				setData( { coreFields, types: meta.types } )
			)
			.catch( ( problem ) => setError( problem?.message ) );
	}, [] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! data ) {
		return <Spinner />;
	}

	const blocks = Boolean( config.usesBlockCheckout );
	const optional = data.coreFields.filter(
		( core ) =>
			core.section === 'billing' && OPTIONAL_CORE.includes( core.key )
	);
	const types = blocks
		? data.types.filter( ( type ) => type.block_checkout )
		: data.types;

	const skip = async () => {
		await api.completeWizard();
		onDone();
	};

	const finish = async () => {
		setSaving( true );
		setError( null );
		const ops = hide.map( ( key ) => ( {
			op: 'core',
			section: 'billing',
			key,
			changes: { enabled: false },
		} ) );
		const type = data.types.find( ( entry ) => entry.slug === field.type );
		if ( type && field.label.trim() ) {
			const { items } = addField( [], type, {
				uuid: uuid4(),
				section: field.section,
				label: field.label.trim(),
				optionLabel: __( 'Option 1', 'rachnix-checkout-fields' ),
			} );
			ops.push( ...operations( [], items ) );
		}
		try {
			if ( ops.length ) {
				await api.publish( ops );
			}
			await api.saveSettings( { mode } );
			await api.completeWizard();
			onDone();
		} catch ( problem ) {
			setError(
				[
					problem?.message,
					...Object.values( problem?.data?.params ?? {} ),
				]
					.filter( Boolean )
					.join( ' ' )
			);
			setSaving( false );
		}
	};

	return (
		<div className="rxcf-wizard">
			<div className="rxcf-wizard__head">
				<h2>
					{ __(
						'Set up your checkout fields',
						'rachnix-checkout-fields'
					) }
				</h2>
				<p className="rxcf-wizard__progress">
					{ sprintf(
						/* translators: 1: current step, 2: number of steps. */
						__( 'Step %1$d of %2$d', 'rachnix-checkout-fields' ),
						step,
						STEPS
					) }
				</p>
				<div className="rxcf-wizard__track" aria-hidden="true">
					<span style={ { width: `${ ( step / STEPS ) * 100 }%` } } />
				</div>
			</div>

			{ step === 1 && (
				<section
					aria-label={ __(
						'Your checkout',
						'rachnix-checkout-fields'
					) }
				>
					<h3>
						{ __( 'Your checkout', 'rachnix-checkout-fields' ) }
					</h3>
					<p>
						{ blocks
							? __(
									'Your store uses the Checkout block. To hide WooCommerce’s own fields there, use the Checkout block’s settings in the editor.',
									'rachnix-checkout-fields'
							  )
							: __(
									'Your store uses the classic checkout. Hide the WooCommerce fields you do not need:',
									'rachnix-checkout-fields'
							  ) }
					</p>
					{ ! blocks &&
						optional.map( ( core ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ core.key }
								label={ sprintf(
									/* translators: %s: field label, e.g. Company name. */
									__(
										'Hide “%s”',
										'rachnix-checkout-fields'
									),
									core.label
								) }
								checked={ hide.includes( core.key ) }
								onChange={ ( on ) =>
									setHide(
										on
											? [ ...hide, core.key ]
											: hide.filter(
													( key ) => key !== core.key
											  )
									)
								}
							/>
						) ) }
				</section>
			) }

			{ step === 2 && (
				<section
					aria-label={ __(
						'Your first field',
						'rachnix-checkout-fields'
					) }
				>
					<h3>
						{ __(
							'Add your first field (optional)',
							'rachnix-checkout-fields'
						) }
					</h3>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Field type', 'rachnix-checkout-fields' ) }
						value={ field.type }
						options={ [
							{
								value: '',
								label: __(
									'No field for now',
									'rachnix-checkout-fields'
								),
							},
							...types.map( ( type ) => ( {
								value: type.slug,
								label: `${ type.label } – ${ type.description }`,
							} ) ),
						] }
						onChange={ ( type ) => setField( { ...field, type } ) }
					/>
					{ field.type && (
						<>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Label',
									'rachnix-checkout-fields'
								) }
								help={ __(
									'What customers see, for example: Gift message',
									'rachnix-checkout-fields'
								) }
								value={ field.label }
								onChange={ ( label ) =>
									setField( { ...field, label } )
								}
							/>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Where',
									'rachnix-checkout-fields'
								) }
								value={ field.section }
								options={ [
									{
										value: 'billing',
										label: __(
											'Billing details',
											'rachnix-checkout-fields'
										),
									},
									{
										value: 'order',
										label: __(
											'Additional information',
											'rachnix-checkout-fields'
										),
									},
								] }
								onChange={ ( section ) =>
									setField( { ...field, section } )
								}
							/>
						</>
					) }
				</section>
			) }

			{ step === 3 && (
				<section
					aria-label={ __( 'Style', 'rachnix-checkout-fields' ) }
				>
					<h3>
						{ __( 'Choose a style', 'rachnix-checkout-fields' ) }
					</h3>
					<RadioControl
						label={ __(
							'How fields look',
							'rachnix-checkout-fields'
						) }
						hideLabelFromVision
						selected={ mode }
						options={ [
							{
								value: 'theme',
								label: __(
									'Follow my theme (recommended)',
									'rachnix-checkout-fields'
								),
							},
							{
								value: 'plugin',
								label: __(
									'Plugin style, for themes that barely style forms',
									'rachnix-checkout-fields'
								),
							},
						] }
						onChange={ setMode }
					/>
					<p>
						{ __(
							'You can change this, and choose your own colours, on the Style tab later.',
							'rachnix-checkout-fields'
						) }
					</p>
				</section>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<div className="rxcf-wizard__actions">
				<Button variant="link" onClick={ skip } disabled={ saving }>
					{ __( 'Skip setup', 'rachnix-checkout-fields' ) }
				</Button>
				{ step > 1 && (
					<Button
						variant="secondary"
						disabled={ saving }
						onClick={ () => setStep( step - 1 ) }
					>
						{ __( 'Back', 'rachnix-checkout-fields' ) }
					</Button>
				) }
				{ step < STEPS ? (
					<Button
						variant="primary"
						disabled={
							step === 2 && field.type && ! field.label.trim()
						}
						onClick={ () => setStep( step + 1 ) }
					>
						{ __( 'Next', 'rachnix-checkout-fields' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						isBusy={ saving }
						disabled={ saving }
						onClick={ finish }
					>
						{ __( 'Finish', 'rachnix-checkout-fields' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
