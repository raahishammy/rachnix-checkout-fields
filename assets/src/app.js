/**
 * WooCommerce > Checkout Fields: the field editor and the style settings.
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Editor from './editor/editor';
import StyleSettings from './style-settings';

export default function App() {
	return (
		<div className="rxcf-admin">
			<h1 className="rxcf-admin__title">
				{ __( 'Checkout Fields', 'rachnix-checkout-fields' ) }
			</h1>
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
				] }
			>
				{ ( tab ) =>
					tab.name === 'style' ? <StyleSettings /> : <Editor />
				}
			</TabPanel>
		</div>
	);
}
