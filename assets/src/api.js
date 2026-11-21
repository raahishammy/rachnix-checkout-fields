/**
 * The plugin's REST API. The wp_rest nonce is added by wp-api-fetch in the admin.
 */
import apiFetch from '@wordpress/api-fetch';

const namespace = window.rxcfAdmin?.namespace ?? 'rxcf/v1';
const path = ( route ) => `/${ namespace }${ route }`;

export const getFields = () => apiFetch( { path: path( '/fields' ) } );

export const getSections = () => apiFetch( { path: path( '/sections' ) } );

export const getCoreFields = () => apiFetch( { path: path( '/core-fields' ) } );

export const getFieldTypes = () => apiFetch( { path: path( '/field-types' ) } );

export const getSettings = () => apiFetch( { path: path( '/settings' ) } );

export const exportFile = () => apiFetch( { path: path( '/export' ) } );

/**
 * Previews (apply false) or imports (apply true) an export file.
 *
 * @param {Object}  file            Parsed export file.
 * @param {boolean} apply           Import instead of previewing.
 * @param {boolean} confirmUnsigned The owner accepted an unsigned file.
 */
export const importFile = ( file, apply = false, confirmUnsigned = false ) =>
	apiFetch( {
		path: path( '/import' ),
		method: 'POST',
		data: { file, apply, confirm_unsigned: confirmUnsigned },
	} );

export const resetAll = () =>
	apiFetch( {
		path: path( '/reset-all' ),
		method: 'POST',
		data: { confirm: true },
	} );

export const completeWizard = () =>
	apiFetch( { path: path( '/wizard/complete' ), method: 'POST' } );

/**
 * @param {Object} style Changed style settings.
 */
export const saveSettings = ( style ) =>
	apiFetch( {
		path: path( '/settings' ),
		method: 'POST',
		data: { style },
	} );

/**
 * Applies the draft (a list of operations) in one transaction.
 *
 * @param {Array<Object>} operations Operations, see FieldService::apply_batch().
 */
export const publish = ( operations ) =>
	apiFetch( {
		path: path( '/publish' ),
		method: 'POST',
		data: { operations },
	} );

/**
 * Validates the draft and returns { url } of the checkout showing it.
 *
 * @param {Array<Object>} operations Operations, see FieldService::apply_batch().
 */
export const preview = ( operations ) =>
	apiFetch( {
		path: path( '/preview' ),
		method: 'POST',
		data: { operations },
	} );
