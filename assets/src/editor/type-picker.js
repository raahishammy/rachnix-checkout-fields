/**
 * "Add field": every field type with its icon and one-line description.
 */
import { Button, Dashicon, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function TypePicker( {
	types,
	usesBlockCheckout,
	onPick,
	onClose,
} ) {
	return (
		<Modal
			title={ __( 'Add a field', 'rachnix-checkout-fields' ) }
			onRequestClose={ onClose }
			className="rxcf-type-picker"
		>
			<ul className="rxcf-type-picker__grid">
				{ types.map( ( type ) => (
					<li key={ type.slug }>
						<Button
							className="rxcf-type-picker__type"
							aria-label={ type.label }
							aria-describedby={ `rxcf-type-${ type.slug }` }
							onClick={ () => onPick( type ) }
						>
							<Dashicon icon={ type.icon } />
							<span className="rxcf-type-picker__label">
								{ type.label }
							</span>
							<span
								id={ `rxcf-type-${ type.slug }` }
								className="rxcf-type-picker__description"
							>
								{ type.description }
							</span>
							{ usesBlockCheckout && ! type.block_checkout && (
								<span className="rxcf-badge is-warning">
									{ __(
										'Not shown on the block checkout',
										'rachnix-checkout-fields'
									) }
								</span>
							) }
						</Button>
					</li>
				) ) }
			</ul>
		</Modal>
	);
}
