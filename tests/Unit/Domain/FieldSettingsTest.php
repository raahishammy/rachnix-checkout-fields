<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Domain;

use Rachnix\CheckoutFields\Domain\DisplayFlags;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\Domain\Width;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

final class FieldSettingsTest extends TestCase {

	public function test_defaults(): void {
		$settings = FieldSettings::from_array( array() );

		$this->assertSame( '', $settings->default_value );
		$this->assertFalse( $settings->required );
		$this->assertSame( Width::Full, $settings->width );
		$this->assertSame( array(), $settings->options );
		$this->assertTrue( $settings->display->customer_email );
		$this->assertFalse( $settings->display->order_list );
	}

	public function test_round_trips_through_json(): void {
		$settings = new FieldSettings(
			default_value: 'Morning',
			required: true,
			width: Width::Half,
			css_classes: array( 'rx-slot' ),
			options: array( new FieldOption( 'am', 'Morning' ), new FieldOption( 'pm', 'Evening' ) ),
			validation: array( new ValidationRule( ValidationRuleType::Length, 2.0, 40.0 ) ),
			display: new DisplayFlags( customer_email: false, order_list: true ),
			extensions: array( 'pro' => array( 'conditions' => array( array( 'field' => 'billing_country' ) ) ) ),
		);

		$decoded = json_decode( (string) json_encode( $settings->to_array() ), true );
		$this->assertIsArray( $decoded );

		$this->assertEquals( $settings, FieldSettings::from_array( $decoded ) );
	}

	public function test_damaged_data_falls_back_instead_of_failing(): void {
		$settings = FieldSettings::from_array(
			array(
				'default'     => array( 'nested' ),
				'required'    => 'yes',
				'width'       => 'triple',
				'css_classes' => array( 'ok', '"><script>', 42, 'ok' ),
				'options'     => array( array( 'value' => '' ), 'junk', array( 'value' => 'a' ) ),
				'validation'  => array(
					array( 'rule' => 'eval' ),
					array(
						'rule'    => 'pattern',
						'pattern' => '/(a+)+$/',
					),
				),
				'display'     => array( 'admin_email' => 'no' ),
				'extensions'  => 'not-an-array',
				'unknown_key' => true,
			)
		);

		$this->assertSame( '', $settings->default_value );
		$this->assertFalse( $settings->required, 'Only a real true marks a field required.' );
		$this->assertSame( Width::Full, $settings->width );
		$this->assertSame( array( 'ok' ), $settings->css_classes );
		$this->assertEquals( array( new FieldOption( 'a', 'a' ) ), $settings->options );
		$this->assertCount( 1, $settings->validation );
		$this->assertNull( $settings->validation[0]->pattern_id, 'Raw regex is never accepted, only curated ids.' );
		$this->assertTrue( $settings->display->admin_email );
		$this->assertSame( array(), $settings->extensions );
		$this->assertArrayNotHasKey( 'unknown_key', $settings->to_array() );
	}

	public function test_validation_rule_omits_unused_parameters(): void {
		$this->assertSame(
			array(
				'rule'    => 'pattern',
				'pattern' => 'postcode_uk',
			),
			( new ValidationRule( ValidationRuleType::Pattern, pattern_id: 'postcode_uk' ) )->to_array()
		);
	}
}
