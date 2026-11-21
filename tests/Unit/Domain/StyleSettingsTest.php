<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Domain;

use Rachnix\CheckoutFields\Domain\LabelPosition;
use Rachnix\CheckoutFields\Domain\StyleMode;
use Rachnix\CheckoutFields\Domain\StyleSettings;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class StyleSettingsTest extends TestCase {

	public function test_defaults_follow_the_theme(): void {
		$style = StyleSettings::from_array( array() );

		$this->assertSame( StyleMode::Theme, $style->mode );
		$this->assertNull( $style->accent );
		$this->assertSame( LabelPosition::Above, $style->label_position );
		$this->assertSame( '', $style->custom_css );
	}

	public function test_round_trip(): void {
		$style = new StyleSettings( StyleMode::Custom, '#1a73e8', 8, 24, LabelPosition::Side, '.x{color:red}' );

		$this->assertEquals( $style, StyleSettings::from_array( $style->to_array() ) );
	}

	public function test_damaged_values_fall_back(): void {
		$style = StyleSettings::from_array(
			array(
				'mode'           => 'neon',
				'accent'         => 'red;}body{display:none',
				'radius'         => 999,
				'spacing'        => -5,
				'label_position' => 'under',
				'custom_css'     => '</style><script>alert(1)</script>',
			)
		);

		$this->assertSame( StyleMode::Theme, $style->mode );
		$this->assertNull( $style->accent, 'Only #rrggbb is accepted.' );
		$this->assertSame( StyleSettings::MAX_RADIUS, $style->radius, 'Clamped.' );
		$this->assertSame( 0, $style->spacing, 'Clamped.' );
		$this->assertSame( LabelPosition::Above, $style->label_position );
		$this->assertSame( '', $style->custom_css, 'Stored CSS containing "<" is never used.' );
	}

	public function test_non_integer_sizes_use_defaults(): void {
		$style = StyleSettings::from_array(
			array(
				'radius'  => '8px',
				'spacing' => 1.5,
			)
		);

		$this->assertSame( 4, $style->radius );
		$this->assertSame( 16, $style->spacing );
	}
}
