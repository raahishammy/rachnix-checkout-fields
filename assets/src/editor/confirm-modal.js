/**
 * Asks before a destructive change (reset a section, delete a field, discard the draft).
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ConfirmModal( {
	title,
	message,
	confirmLabel,
	onConfirm,
	onClose,
} ) {
	return (
		<Modal title={ title } onRequestClose={ onClose } size="small">
			<p>{ message }</p>
			<div className="rxcf-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'rachnix-checkout-fields' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={ () => {
						onConfirm();
						onClose();
					} }
				>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
