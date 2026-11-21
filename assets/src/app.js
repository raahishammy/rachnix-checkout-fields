/**
 * Step 8a starter: loads the sections and fields and lists them, proving the build,
 * the loader and the REST API work together. Step 8b replaces this with the editor.
 */
import { useEffect, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getFields, getSections } from './api';

export default function App() {
	const [ state, setState ] = useState( {
		loading: true,
		error: null,
		sections: [],
		fields: [],
	} );

	useEffect( () => {
		Promise.all( [ getSections(), getFields() ] )
			.then( ( [ sections, fields ] ) =>
				setState( { loading: false, error: null, sections, fields } )
			)
			.catch( ( error ) =>
				setState( {
					loading: false,
					error:
						error?.message ??
						__( 'Could not load the fields.', 'rachnix-checkout-fields' ),
					sections: [],
					fields: [],
				} )
			);
	}, [] );

	if ( state.loading ) {
		return <Spinner />;
	}

	if ( state.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ state.error }
			</Notice>
		);
	}

	return (
		<div className="rxcf-admin">
			<h1>{ __( 'Checkout Fields', 'rachnix-checkout-fields' ) }</h1>
			{ state.sections.map( ( section ) => (
				<section key={ section.key } className="rxcf-admin__section">
					<h2>{ section.label }</h2>
					<ul>
						{ state.fields
							.filter( ( field ) => field.section === section.key )
							.map( ( field ) => (
								<li key={ field.uuid }>
									{ field.label || field.key }{ ' ' }
									<code>{ field.type }</code>
								</li>
							) ) }
					</ul>
				</section>
			) ) }
		</div>
	);
}
