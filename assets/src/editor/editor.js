/**
 * The checkout field editor: sections | fields | preview or field settings.
 *
 * The draft lives in the browser (with a backup in browser storage). Nothing reaches the
 * live checkout until "Publish changes" sends the difference to POST /publish, which
 * applies it in one transaction.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useState,
} from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { undo as undoIcon } from '@wordpress/icons';
import * as api from '../api';
import {
	addField,
	buildItems,
	itemsInSection,
	moveItem,
	operations,
	removeItem,
	resetSection,
	updateItem,
} from './draft';
import {
	clearBackup,
	fingerprint,
	historyReducer,
	loadBackup,
	saveBackup,
	uuid4,
} from './support';
import ConfirmModal from './confirm-modal';
import FieldList, { itemTitle } from './field-list';
import FieldPanel from './field-panel';
import Preview from './preview';
import SectionList from './section-list';
import TypePicker from './type-picker';

const config = window.rxcfAdmin ?? {};

export default function Editor() {
	const [ loaded, setLoaded ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ state, dispatch ] = useReducer( historyReducer, {
		items: [],
		past: [],
		lastKey: null,
	} );
	const [ section, setSection ] = useState( 'billing' );
	const [ selected, setSelected ] = useState( null );
	const [ picking, setPicking ] = useState( false );
	const [ confirm, setConfirm ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ publishing, setPublishing ] = useState( false );

	const load = useCallback( async ( restore ) => {
		const [ sections, fields, coreFields, meta ] = await Promise.all( [
			api.getSections(),
			api.getFields(),
			api.getCoreFields(),
			api.getFieldTypes(),
		] );
		const published = buildItems( fields, coreFields );
		const signature = fingerprint( published );
		setLoaded( {
			sections,
			types: meta.types,
			patterns: meta.patterns,
			published,
			signature,
		} );
		dispatch( { type: 'reset', items: published } );

		if ( ! restore ) {
			return;
		}
		const backup = loadBackup( signature );
		if ( backup.items ) {
			dispatch( { type: 'change', items: backup.items } );
			setNotice( {
				status: 'info',
				text: __(
					'Your unpublished changes from last time were restored.',
					'rachnix-checkout-fields'
				),
			} );
		} else if ( backup.stale ) {
			setNotice( {
				status: 'warning',
				text: __(
					'Your saved unpublished changes were discarded, because the fields were changed somewhere else since.',
					'rachnix-checkout-fields'
				),
			} );
		}
	}, [] );

	useEffect( () => {
		load( true ).catch( ( error ) =>
			setLoadError(
				error?.message ??
					__(
						'Could not load the fields.',
						'rachnix-checkout-fields'
					)
			)
		);
	}, [ load ] );

	const ops = useMemo(
		() => ( loaded ? operations( loaded.published, state.items ) : [] ),
		[ loaded, state.items ]
	);
	const dirty = ops.length > 0;

	// Keep a backup of the draft, and warn before leaving with unpublished changes.
	useEffect( () => {
		if ( ! loaded ) {
			return;
		}
		if ( dirty ) {
			saveBackup( loaded.signature, state.items );
		} else {
			clearBackup();
		}
	}, [ loaded, dirty, state.items ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}
	if ( ! loaded ) {
		return <Spinner />;
	}

	const change = ( items, key ) => dispatch( { type: 'change', items, key } );
	const types = new Map(
		loaded.types.map( ( type ) => [ type.slug, type ] )
	);
	const sectionLabel =
		loaded.sections.find( ( entry ) => entry.key === section )?.label ??
		section;
	const selectedItem =
		state.items.find( ( item ) => item.id === selected ) ?? null;

	const onAdd = ( type ) => {
		const result = addField( state.items, type, {
			uuid: uuid4(),
			section,
			label: type.label,
			optionLabel: __( 'Option 1', 'rachnix-checkout-fields' ),
		} );
		change( result.items );
		setSelected( result.id );
		setPicking( false );
	};

	const onPublish = async () => {
		setPublishing( true );
		setNotice( null );
		try {
			await api.publish( ops );
			clearBackup();
			await load( false );
			setSelected( null );
			setNotice( {
				status: 'success',
				text: __(
					'Your changes are live on the checkout.',
					'rachnix-checkout-fields'
				),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				text:
					error?.message ??
					__(
						'Your changes could not be published.',
						'rachnix-checkout-fields'
					),
				details: Object.values( error?.data?.params ?? {} ),
			} );
		} finally {
			setPublishing( false );
		}
	};

	return (
		<div className="rxcf-editor">
			<div className="rxcf-editor__bar">
				<span className="rxcf-editor__status" role="status">
					{ dirty
						? sprintf(
								/* translators: %d: number of unpublished changes. */
								_n(
									'%d unpublished change. Customers do not see it yet.',
									'%d unpublished changes. Customers do not see them yet.',
									ops.length,
									'rachnix-checkout-fields'
								),
								ops.length
						  )
						: __(
								'Everything you see is live on the checkout.',
								'rachnix-checkout-fields'
						  ) }
				</span>
				<Button
					icon={ undoIcon }
					label={ __( 'Undo', 'rachnix-checkout-fields' ) }
					disabled={ ! state.past.length }
					onClick={ () => dispatch( { type: 'undo' } ) }
				/>
				<Button
					variant="tertiary"
					disabled={ ! dirty }
					onClick={ () =>
						setConfirm( {
							title: __(
								'Discard changes?',
								'rachnix-checkout-fields'
							),
							message: __(
								'All unpublished changes are removed. The live checkout stays as it is.',
								'rachnix-checkout-fields'
							),
							confirmLabel: __(
								'Discard changes',
								'rachnix-checkout-fields'
							),
							onConfirm: () => {
								dispatch( {
									type: 'reset',
									items: loaded.published,
								} );
								clearBackup();
								setSelected( null );
							},
						} )
					}
				>
					{ __( 'Discard changes', 'rachnix-checkout-fields' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ publishing }
					disabled={ ! dirty || publishing }
					onClick={ onPublish }
				>
					{ __( 'Publish changes', 'rachnix-checkout-fields' ) }
				</Button>
			</div>

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

			<div className="rxcf-editor__columns">
				<SectionList
					sections={ loaded.sections }
					items={ state.items }
					current={ section }
					onSelect={ ( key ) => {
						setSection( key );
						setSelected( null );
					} }
					onReset={ ( key ) =>
						setConfirm( {
							title: __(
								'Reset this section?',
								'rachnix-checkout-fields'
							),
							message: sprintf(
								/* translators: %s: section name, e.g. Billing. */
								__(
									'%s goes back to WooCommerce’s fields. Your custom fields in it are removed when you publish.',
									'rachnix-checkout-fields'
								),
								sectionLabel
							),
							confirmLabel: __(
								'Reset section',
								'rachnix-checkout-fields'
							),
							onConfirm: () => {
								change( resetSection( state.items, key ) );
								setSelected( null );
							},
						} )
					}
				/>
				<FieldList
					title={ sectionLabel }
					items={ itemsInSection( state.items, section ) }
					types={ types }
					selected={ selected }
					usesBlockCheckout={ config.usesBlockCheckout }
					onSelect={ setSelected }
					onMove={ ( from, to ) =>
						change( moveItem( state.items, section, from, to ) )
					}
					onAdd={ () => setPicking( true ) }
				/>
				<div className="rxcf-editor__side">
					{ selectedItem ? (
						<FieldPanel
							item={ selectedItem }
							type={ types.get( selectedItem.type ) }
							patterns={ loaded.patterns }
							onChange={ ( patch, key ) =>
								change(
									updateItem(
										state.items,
										selectedItem.id,
										patch
									),
									key
										? `${ selectedItem.id }:${ key }`
										: undefined
								)
							}
							onRemove={ () =>
								setConfirm( {
									title:
										selectedItem.kind === 'core'
											? __(
													'Restore WooCommerce’s settings?',
													'rachnix-checkout-fields'
											  )
											: __(
													'Delete this field?',
													'rachnix-checkout-fields'
											  ),
									message: sprintf(
										/* translators: %s: field label. */
										__(
											'“%s” changes when you publish. Values already saved on orders are kept.',
											'rachnix-checkout-fields'
										),
										itemTitle( selectedItem )
									),
									confirmLabel:
										selectedItem.kind === 'core'
											? __(
													'Restore',
													'rachnix-checkout-fields'
											  )
											: __(
													'Delete field',
													'rachnix-checkout-fields'
											  ),
									onConfirm: () => {
										change(
											removeItem(
												state.items,
												selectedItem.id
											)
										);
										setSelected( null );
									},
								} )
							}
							onClose={ () => setSelected( null ) }
						/>
					) : (
						<Preview
							operations={ ops }
							usesBlockCheckout={ config.usesBlockCheckout }
						/>
					) }
				</div>
			</div>

			{ picking && (
				<TypePicker
					types={ loaded.types }
					usesBlockCheckout={ config.usesBlockCheckout }
					onPick={ onAdd }
					onClose={ () => setPicking( false ) }
				/>
			) }
			{ confirm && (
				<ConfirmModal
					{ ...confirm }
					onClose={ () => setConfirm( null ) }
				/>
			) }
		</div>
	);
}
