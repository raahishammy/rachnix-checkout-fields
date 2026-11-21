/**
 * WooCommerce > Checkout Fields: first-run wizard, then the editor, style and tools tabs.
 */
import { useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Editor from './editor/editor';
import StyleSettings from './style-settings';
import Tools from './tools';
import Wizard from './wizard';

const config = window.rxcfAdmin ?? {};

export default function App() {
	const [ wizardDone, setWizardDone ] = useState(
		config.wizardCompleted !== false
	);

	return (
		<div className="rxcf-admin">
			<h1 className="rxcf-admin__title">
				{ __( 'Checkout Fields', 'rachnix-checkout-fields' ) }
			</h1>
			{ wizardDone ? (
				<TabPanel
					className="rxcf-admin__tabs"
					tabs={ [
						{
							name: 'fields',
							title: __( 'Fields', 'rachnix-checkout-fields' ),
						},
						{
							name: 'style',
							title: __( 'Style', 'rachnix-checkout-fields' ),
						},
						{
							name: 'tools',
							title: __( 'Tools', 'rachnix-checkout-fields' ),
						},
					] }
				>
					{ ( tab ) => {
						if ( tab.name === 'style' ) {
							return <StyleSettings />;
						}
						if ( tab.name === 'tools' ) {
							return <Tools />;
						}
						return <Editor />;
					} }
				</TabPanel>
			) : (
				<Wizard onDone={ () => setWizardDone( true ) } />
			) }
		</div>
	);
}
