/**
 * Right column when no field is selected: the store's real checkout, showing the draft.
 * The frame is inert (view only), so nothing can be clicked or submitted in it.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { preview } from '../api';
import { stableStringify } from './draft';

const DELAY_MS = 800;

export default function Preview( { operations, usesBlockCheckout } ) {
	const [ url, setUrl ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const frame = useRef( null );
	const draftKey = stableStringify( operations );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		const timer = window.setTimeout( () => {
			preview( operations )
				.then( ( response ) => {
					if ( ! cancelled ) {
						setError( null );
						// A fresh URL each time, so the frame reloads with the new draft.
						setUrl( `${ response.url }&rxcf_t=${ Date.now() }` );
					}
				} )
				.catch( ( problem ) => {
					if ( ! cancelled ) {
						setLoading( false );
						setError(
							problem?.message ??
								__(
									'The preview could not be loaded.',
									'rachnix-checkout-fields'
								)
						);
					}
				} );
		}, DELAY_MS );
		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ draftKey ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		// React 18 does not know the boolean "inert" attribute; set it directly.
		frame.current?.setAttribute( 'inert', '' );
	}, [ url ] );

	return (
		<section
			className="rxcf-preview"
			aria-label={ __( 'Checkout preview', 'rachnix-checkout-fields' ) }
		>
			<div className="rxcf-preview__head">
				<h2>{ __( 'Preview', 'rachnix-checkout-fields' ) }</h2>
				{ loading && <Spinner /> }
			</div>
			{ usesBlockCheckout && (
				<p className="rxcf-preview__note">
					{ __(
						'Your checkout uses the Checkout block: add a product to your cart to see the fields here.',
						'rachnix-checkout-fields'
					) }
				</p>
			) }
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ url && (
				<iframe
					ref={ frame }
					className="rxcf-preview__frame"
					src={ url }
					title={ __(
						'Your checkout with the unpublished changes',
						'rachnix-checkout-fields'
					) }
					tabIndex={ -1 }
					onLoad={ () => setLoading( false ) }
				/>
			) }
		</section>
	);
}
