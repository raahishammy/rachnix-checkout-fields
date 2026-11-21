/**
 * Entry point of the checkout field editor (WooCommerce > Checkout Fields).
 * Built by @wordpress/scripts into assets/build; WordPress packages are loaded from core.
 */
import { createRoot } from '@wordpress/element';
import App from './app';
import './style.scss';

const root = document.getElementById( 'rxcf-admin' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
