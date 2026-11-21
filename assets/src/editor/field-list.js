/**
 * Middle column: the section's fields, in checkout order, reorderable by mouse or keyboard
 * (focus the handle, press Space, use the arrow keys, press Space to drop).
 */
import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dragHandle, plus } from '@wordpress/icons';

export function itemTitle( item ) {
	if ( item.kind === 'core' ) {
		return item.label || item.defaults.label;
	}
	return item.label || item.key;
}

function badgesFor( item, type, usesBlockCheckout ) {
	// Tone sets the badge colour: neutral, danger, success or warning.
	const badges = [];
	if ( ! item.enabled ) {
		badges.push( {
			label: __( 'Hidden', 'rachnix-checkout-fields' ),
			tone: 'neutral',
		} );
	}
	if ( item.settings?.required ) {
		badges.push( {
			label: __( 'Required', 'rachnix-checkout-fields' ),
			tone: 'danger',
		} );
	}
	if ( item.isNew ) {
		badges.push( {
			label: __( 'New', 'rachnix-checkout-fields' ),
			tone: 'success',
		} );
	}
	if (
		usesBlockCheckout &&
		item.kind === 'custom' &&
		type &&
		! type.block_checkout
	) {
		badges.push( {
			label: __(
				'Not shown on the block checkout',
				'rachnix-checkout-fields'
			),
			tone: 'warning',
		} );
	}
	return badges;
}

function Row( { item, type, selected, usesBlockCheckout, onSelect } ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: item.id } );
	const title = itemTitle( item );
	const typeLabel =
		item.kind === 'core'
			? __( 'WooCommerce field', 'rachnix-checkout-fields' )
			: type?.label ?? item.type;

	return (
		<li
			ref={ setNodeRef }
			style={ {
				transform: CSS.Transform.toString( transform ),
				transition,
			} }
			className={ [
				'rxcf-fields__row',
				selected ? 'is-selected' : '',
				isDragging ? 'is-dragging' : '',
				item.enabled ? '' : 'is-hidden',
			].join( ' ' ) }
		>
			<Button
				ref={ setActivatorNodeRef }
				className="rxcf-fields__handle"
				icon={ dragHandle }
				label={ sprintf(
					/* translators: %s: field label. */
					__( 'Move %s', 'rachnix-checkout-fields' ),
					title
				) }
				{ ...attributes }
				{ ...listeners }
			/>
			<Button
				className="rxcf-fields__open"
				aria-pressed={ selected }
				onClick={ () => onSelect( item.id ) }
			>
				<span className="rxcf-fields__title">{ title }</span>
				<span className="rxcf-fields__type">{ typeLabel }</span>
				{ badgesFor( item, type, usesBlockCheckout ).map( ( badge ) => (
					<span
						key={ badge.label }
						className={ `rxcf-badge is-${ badge.tone }` }
					>
						{ badge.label }
					</span>
				) ) }
			</Button>
		</li>
	);
}

export default function FieldList( {
	title,
	items,
	types,
	selected,
	usesBlockCheckout,
	onSelect,
	onMove,
	onAdd,
} ) {
	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 4 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const onDragEnd = ( { active, over } ) => {
		if ( over && active.id !== over.id ) {
			onMove( active.id, over.id );
		}
	};

	return (
		<div className="rxcf-fields">
			<div className="rxcf-fields__head">
				<h2>{ title }</h2>
				<Button variant="secondary" icon={ plus } onClick={ onAdd }>
					{ __( 'Add field', 'rachnix-checkout-fields' ) }
				</Button>
			</div>
			{ items.length === 0 ? (
				<p>
					{ __(
						'No fields in this section yet.',
						'rachnix-checkout-fields'
					) }
				</p>
			) : (
				<DndContext
					sensors={ sensors }
					collisionDetection={ closestCenter }
					onDragEnd={ onDragEnd }
					accessibility={ {
						screenReaderInstructions: {
							draggable: __(
								'To move a field, press Space or Enter, move it with the arrow keys, then press Space or Enter again to drop it. Press Escape to cancel.',
								'rachnix-checkout-fields'
							),
						},
					} }
				>
					<SortableContext
						items={ items.map( ( item ) => item.id ) }
						strategy={ verticalListSortingStrategy }
					>
						<ul className="rxcf-fields__list">
							{ items.map( ( item ) => (
								<Row
									key={ item.id }
									item={ item }
									type={ types.get( item.type ) }
									selected={ item.id === selected }
									usesBlockCheckout={ usesBlockCheckout }
									onSelect={ onSelect }
								/>
							) ) }
						</ul>
					</SortableContext>
				</DndContext>
			) }
		</div>
	);
}
