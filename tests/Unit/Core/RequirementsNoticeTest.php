<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Core;

use Brain\Monkey\Functions;
use Rachnix\CheckoutFields\Core\Dependency;
use Rachnix\CheckoutFields\Core\RequirementsNotice;
use Rachnix\CheckoutFields\Core\UnmetRequirement;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class RequirementsNoticeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	public function test_register_hooks_into_site_and_network_notices(): void {
		$notice = new RequirementsNotice( array() );
		$notice->register();

		$this->assertNotFalse( has_action( 'admin_notices', array( $notice, 'render' ) ) );
		$this->assertNotFalse( has_action( 'network_admin_notices', array( $notice, 'render' ) ) );
	}

	public function test_renders_one_line_per_unmet_requirement(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		$notice = new RequirementsNotice(
			array(
				new UnmetRequirement( Dependency::WooCommerce, null ),
				new UnmetRequirement( Dependency::WordPress, '6.4.2' ),
			)
		);

		ob_start();
		$notice->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Install and activate WooCommerce 9.9 or newer.', $html );
		$this->assertStringContainsString( 'Update WordPress to version 6.5 or newer. This site runs version 6.4.2.', $html );
		$this->assertSame( 2, substr_count( $html, '<li>' ) );
	}

	public function test_renders_nothing_for_users_who_cannot_fix_it(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$notice = new RequirementsNotice( array( new UnmetRequirement( Dependency::WooCommerce, null ) ) );

		ob_start();
		$notice->render();

		$this->assertSame( '', ob_get_clean() );
	}
}
