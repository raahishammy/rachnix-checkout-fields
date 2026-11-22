/**
 * "Tools" tab: export, import (preview first; unsigned files need an extra confirmation),
 * and reset everything.
 */
import { useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import * as api from './api';
import ConfirmModal from './editor/confirm-modal';

const config = window.rxcfAdmin ?? {};

function errorText( problem ) {
	return [ problem?.message, ...Object.values( problem?.data?.params ?? {} ) ]
		.filter( Boolean )
		.join( ' ' );
}

function Summary( { preview } ) {
	const rows = [
		[ __( 'Added', 'rachnix-checkout-fields' ), preview.added ],
		[ __( 'Changed', 'rachnix-checkout-fields' ), preview.changed ],
		[ __( 'Removed', 'rachnix-checkout-fields' ), preview.removed ],
	];
	return (
		<dl className="rxcf-tools__summary">
			{ rows.map( ( [ label, keys ] ) => (
				<div key={ label }>
					<dt>{ label }</dt>
					<dd>
						{ keys.length
							? keys.join( ', ' )
							: __( 'Nothing', 'rachnix-checkout-fields' ) }
					</dd>
				</div>
			) ) }
		</dl>
	);
}

export default function Tools() {
	const [ notice, setNotice ] = useState( null );
	const [ file, setFile ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ accepted, setAccepted ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ confirmReset, setConfirmReset ] = useState( false );
	const [ deleteData, setDeleteData ] = useState(
		Boolean( config.deleteDataOnUninstall )
	);
	const [ savingDelete, setSavingDelete ] = useState( false );

	const [ systemInfo, setSystemInfo ] = useState( null );
	const [ copied, setCopied ] = useState( false );

	const onShowSystemInfo = async () => {
		setNotice( null );
		try {
			setSystemInfo( ( await api.getSystemInfo() ).text );
		} catch ( problem ) {
			setNotice( { status: 'error', text: errorText( problem ) } );
		}
	};

	const onCopySystemInfo = async () => {
		const box = document.getElementById( 'rxcf-system-info' );
		try {
			await window.navigator.clipboard.writeText( systemInfo );
		} catch {
			// No clipboard API on plain-HTTP admin screens: select the text for Ctrl+C instead.
			box?.select();
			return;
		}
		setCopied( true );
	};

	const onDeleteData = async ( enabled ) => {
		setNotice( null );
		// Flip at once so the switch answers the click; flip back if saving fails.
		const previous = deleteData;
		setDeleteData( enabled );
		setSavingDelete( true );
		try {
			const result = await api.saveUninstall( enabled );
			setDeleteData( Boolean( result.delete_data ) );
			config.deleteDataOnUninstall = Boolean( result.delete_data );
		} catch ( problem ) {
			setDeleteData( previous );
			setNotice( { status: 'error', text: errorText( problem ) } );
		}
		setSavingDelete( false );
	};

	const onExport = async () => {
		setNotice( null );
		try {
			const data = await api.exportFile();
			const blob = new window.Blob( [ JSON.stringify( data, null, 2 ) ], {
				type: 'application/json',
			} );
			const link = document.createElement( 'a' );
			link.href = window.URL.createObjectURL( blob );
			link.download = `rachnix-checkout-fields-${ new Date()
				.toISOString()
				.slice( 0, 10 ) }.json`;
			document.body.appendChild( link );
			link.click();
			link.remove();
			window.URL.revokeObjectURL( link.href );
		} catch ( problem ) {
			setNotice( { status: 'error', text: errorText( problem ) } );
		}
	};

	const onChooseFile = async ( event ) => {
		setNotice( null );
		setPreview( null );
		setAccepted( false );
		const chosen = event.target.files?.[ 0 ];
		if ( ! chosen ) {
			return;
		}
		if ( chosen.size > 1048576 ) {
			setNotice( {
				status: 'error',
				text: __(
					'The file is larger than 1 MB.',
					'rachnix-checkout-fields'
				),
			} );
			return;
		}
		try {
			const parsed = JSON.parse( await chosen.text() );
			setFile( parsed );
			setPreview( await api.importFile( parsed ) );
		} catch ( problem ) {
			setFile( null );
			setNotice( {
				status: 'error',
				text:
					problem instanceof SyntaxError
						? __(
								'This file is not valid JSON.',
								'rachnix-checkout-fields'
						  )
						: errorText( problem ),
			} );
		}
	};

	const onImport = async () => {
		setBusy( true );
		try {
			await api.importFile( file, true, ! preview.signed && accepted );
			setFile( null );
			setPreview( null );
			setNotice( {
				status: 'success',
				text: __(
					'The settings were imported and are live on the checkout.',
					'rachnix-checkout-fields'
				),
			} );
		} catch ( problem ) {
			setNotice( { status: 'error', text: errorText( problem ) } );
		} finally {
			setBusy( false );
		}
	};

	const onReset = async () => {
		try {
			const result = await api.resetAll();
			setNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %d: number of removed field settings. */
					__(
						'Everything is back to WooCommerce’s defaults (%d field settings removed). Values on past orders are kept.',
						'rachnix-checkout-fields'
					),
					result.deleted
				),
			} );
		} catch ( problem ) {
			setNotice( { status: 'error', text: errorText( problem ) } );
		}
	};

	return (
		<div className="rxcf-tools">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<section className="rxcf-card">
				<h2>{ __( 'Export', 'rachnix-checkout-fields' ) }</h2>
				<p>
					{ __(
						'Download all fields and the style as a file, to back them up or copy them to another store.',
						'rachnix-checkout-fields'
					) }
				</p>
				<Button variant="secondary" onClick={ onExport }>
					{ __( 'Download export file', 'rachnix-checkout-fields' ) }
				</Button>
			</section>

			<section className="rxcf-card">
				<h2>{ __( 'Import', 'rachnix-checkout-fields' ) }</h2>
				<p>
					{ __(
						'Replaces your current fields and style with the ones in the file. You see what changes before anything happens.',
						'rachnix-checkout-fields'
					) }
				</p>
				<div className="rxcf-tools__file">
					<label htmlFor="rxcf-import-file">
						{ __(
							'Choose an export file',
							'rachnix-checkout-fields'
						) }
					</label>
					<input
						id="rxcf-import-file"
						type="file"
						accept="application/json,.json"
						onChange={ onChooseFile }
					/>
				</div>
				{ preview && (
					<div className="rxcf-tools__preview">
						<Notice
							status={ preview.signed ? 'success' : 'warning' }
							isDismissible={ false }
						>
							{ preview.signed
								? __(
										'This file was exported from this store and has not been changed.',
										'rachnix-checkout-fields'
								  )
								: __(
										'This file comes from another store, or was changed after export. Check the changes below before importing.',
										'rachnix-checkout-fields'
								  ) }
						</Notice>
						<Summary preview={ preview } />
						{ ! preview.signed && (
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __(
									'I have checked the changes and want to import this file',
									'rachnix-checkout-fields'
								) }
								checked={ accepted }
								onChange={ setAccepted }
							/>
						) }
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={
								busy || ( ! preview.signed && ! accepted )
							}
							onClick={ onImport }
						>
							{ __( 'Import', 'rachnix-checkout-fields' ) }
						</Button>
					</div>
				) }
			</section>

			<section className="rxcf-card">
				<h2>
					{ __(
						'When the plugin is deleted',
						'rachnix-checkout-fields'
					) }
				</h2>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Delete all data when the plugin is deleted',
						'rachnix-checkout-fields'
					) }
					help={
						config.canDeletePlugins
							? __(
									'Off: deleting the plugin keeps your fields and settings, so reinstalling brings them back. On: they are removed too. Values on past orders are always kept.',
									'rachnix-checkout-fields'
							  )
							: __(
									'Only users who can delete plugins can change this.',
									'rachnix-checkout-fields'
							  )
					}
					checked={ deleteData }
					disabled={ ! config.canDeletePlugins || savingDelete }
					onChange={ onDeleteData }
				/>
			</section>

			<section className="rxcf-card">
				<h2>{ __( 'System info', 'rachnix-checkout-fields' ) }</h2>
				<p>
					{ __(
						'Versions and setup to paste into a bug report. It contains no site address, customer data or field labels.',
						'rachnix-checkout-fields'
					) }
				</p>
				{ systemInfo === null ? (
					<Button variant="secondary" onClick={ onShowSystemInfo }>
						{ __( 'Show system info', 'rachnix-checkout-fields' ) }
					</Button>
				) : (
					<>
						<TextareaControl
							__nextHasNoMarginBottom
							id="rxcf-system-info"
							className="rxcf-tools__system-info"
							label={ __(
								'System info',
								'rachnix-checkout-fields'
							) }
							hideLabelFromVision
							readOnly
							rows={ 12 }
							value={ systemInfo }
							onChange={ () => {} }
						/>
						<div className="rxcf-tools__actions">
							<Button
								variant="secondary"
								onClick={ onCopySystemInfo }
							>
								{ copied
									? __( 'Copied', 'rachnix-checkout-fields' )
									: __(
											'Copy system info',
											'rachnix-checkout-fields'
									  ) }
							</Button>
						</div>
					</>
				) }
			</section>

			<section className="rxcf-card is-danger">
				<h2>{ __( 'Reset everything', 'rachnix-checkout-fields' ) }</h2>
				<p>
					{ __(
						'Removes all custom fields and changes to WooCommerce’s fields, and restores the default style.',
						'rachnix-checkout-fields'
					) }
				</p>
				<Button
					variant="secondary"
					isDestructive
					onClick={ () => setConfirmReset( true ) }
				>
					{ __( 'Reset everything', 'rachnix-checkout-fields' ) }
				</Button>
			</section>

			{ confirmReset && (
				<ConfirmModal
					title={ __(
						'Reset everything?',
						'rachnix-checkout-fields'
					) }
					message={ __(
						'The checkout goes back to WooCommerce’s fields right away. Values on past orders are kept. Export first if you may want this setup again.',
						'rachnix-checkout-fields'
					) }
					confirmLabel={ __(
						'Reset everything',
						'rachnix-checkout-fields'
					) }
					onConfirm={ onReset }
					onClose={ () => setConfirmReset( false ) }
				/>
			) }
		</div>
	);
}
