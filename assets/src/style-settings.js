/**
 * "Style" tab: how checkout fields look (step 7 settings). Saved directly, not part of the draft.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	PanelBody,
	RadioControl,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getSettings, saveSettings } from './api';

export default function StyleSettings() {
	const [ style, setStyle ] = useState( null );
	const [ canEditCss, setCanEditCss ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		getSettings()
			.then( ( settings ) => {
				setStyle( settings.style );
				setCanEditCss( settings.can_edit_css );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error?.message } )
			);
	}, [] );

	if ( ! style ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const set = ( patch ) => setStyle( { ...style, ...patch } );

	const onSave = async () => {
		setSaving( true );
		setNotice( null );
		const { custom_css: customCss, ...rest } = style;
		try {
			const saved = await saveSettings(
				canEditCss ? { ...rest, custom_css: customCss } : rest
			);
			setStyle( saved.style );
			setNotice( {
				status: 'success',
				text: __( 'Style saved.', 'rachnix-checkout-fields' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				text: error?.message,
				details: Object.values( error?.data?.params ?? {} ),
			} );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="rxcf-style">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
					{ notice.details?.length > 0 && (
						<ul>
							{ notice.details.map( ( detail, index ) => (
								<li key={ index }>{ detail }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }
			<RadioControl
				label={ __( 'How fields look', 'rachnix-checkout-fields' ) }
				selected={ style.mode }
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
					{
						value: 'custom',
						label: __(
							'Custom: choose colour, corners, spacing and labels',
							'rachnix-checkout-fields'
						),
					},
				] }
				onChange={ ( mode ) => set( { mode } ) }
			/>
			{ style.mode === 'custom' && (
				<div className="rxcf-style__custom">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="color"
						label={ __(
							'Accent colour',
							'rachnix-checkout-fields'
						) }
						help={ __(
							'Used for the focus outline and ticked boxes.',
							'rachnix-checkout-fields'
						) }
						value={ style.accent ?? '#2271b1' }
						onChange={ ( accent ) => set( { accent } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Corner radius (px)',
							'rachnix-checkout-fields'
						) }
						min={ 0 }
						max={ 32 }
						value={ style.radius }
						onChange={ ( radius ) =>
							set( { radius: radius ?? 0 } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Space between fields (px)',
							'rachnix-checkout-fields'
						) }
						min={ 0 }
						max={ 64 }
						value={ style.spacing }
						onChange={ ( spacing ) =>
							set( { spacing: spacing ?? 0 } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Labels', 'rachnix-checkout-fields' ) }
						value={ style.label_position }
						options={ [
							{
								value: 'above',
								label: __(
									'Above the field',
									'rachnix-checkout-fields'
								),
							},
							{
								value: 'side',
								label: __(
									'Beside the field on wide screens (classic checkout)',
									'rachnix-checkout-fields'
								),
							},
						] }
						onChange={ ( position ) =>
							set( { label_position: position } )
						}
					/>
				</div>
			) }
			<PanelBody
				title={ __( 'Advanced', 'rachnix-checkout-fields' ) }
				initialOpen={ false }
			>
				{ canEditCss ? (
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Custom CSS', 'rachnix-checkout-fields' ) }
						help={ __(
							'For developers. Loaded on the checkout page only.',
							'rachnix-checkout-fields'
						) }
						value={ style.custom_css }
						rows={ 8 }
						onChange={ ( css ) => set( { custom_css: css } ) }
					/>
				) : (
					<p>
						{ __(
							'Only users who can edit CSS on this site can add custom CSS.',
							'rachnix-checkout-fields'
						) }
					</p>
				) }
			</PanelBody>
			<Button
				variant="primary"
				isBusy={ saving }
				disabled={ saving }
				onClick={ onSave }
			>
				{ __( 'Save style', 'rachnix-checkout-fields' ) }
			</Button>
		</div>
	);
}
