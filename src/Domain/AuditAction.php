<?php
/**
 * Kinds of change recorded in the audit log.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Values of `rxcf_audit_log.action`.
 */
enum AuditAction: string {

	case Create = 'create';
	case Update = 'update';
	case Delete = 'delete';
	case Import = 'import';
	case Reset  = 'reset';
}
