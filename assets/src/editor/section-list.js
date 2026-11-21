/**
 * Left column: the checkout sections.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SectionList( {
	sections,
	items,
	current,
	onSelect,
	onReset,
} ) {
	return (
		<nav
			className="rxcf-sections"
			aria-label={ __( 'Checkout sections', 'rachnix-checkout-fields' ) }
		>
			<ul>
				{ sections.map( ( section ) => {
					const custom = items.filter(
						( item ) =>
							item.section === section.key &&
							item.kind === 'custom'
					).length;
					const isCurrent = section.key === current;
					return (
						<li key={ section.key }>
							<Button
								className="rxcf-sections__item"
								isPressed={ isCurrent }
								aria-current={ isCurrent ? 'true' : undefined }
								onClick={ () => onSelect( section.key ) }
							>
								<span>{ section.label }</span>
								{ custom > 0 && (
									<span className="rxcf-badge">
										{ custom }
									</span>
								) }
							</Button>
						</li>
					);
				} ) }
			</ul>
			<Button
				variant="link"
				isDestructive
				onClick={ () => onReset( current ) }
			>
				{ __( 'Reset this section', 'rachnix-checkout-fields' ) }
			</Button>
		</nav>
	);
}
