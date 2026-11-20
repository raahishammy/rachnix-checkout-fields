<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\FieldTypes;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Contracts\FieldTypeInterface;
use Rachnix\CheckoutFields\Domain\Patterns;
use Rachnix\CheckoutFields\Domain\ValidationRuleType as Rule;
use Rachnix\CheckoutFields\FieldTypes;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

/**
 * One row per free field type: the behaviour each type promises.
 */
final class FieldTypesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	/**
	 * @return iterable<string, array{class-string<FieldTypeInterface>, string, bool, bool, bool, list<Rule>}>
	 */
	public static function free_types(): iterable {
		// class, slug, options, placeholder, can be required, allowed rules.
		yield 'text'     => array( FieldTypes\TextField::class, 'text', false, true, true, array( Rule::Length, Rule::Pattern ) );
		yield 'textarea' => array( FieldTypes\TextareaField::class, 'textarea', false, true, true, array( Rule::Length, Rule::Pattern ) );
		yield 'email'    => array( FieldTypes\EmailField::class, 'email', false, true, true, array( Rule::Length ) );
		yield 'phone'    => array( FieldTypes\PhoneField::class, 'phone', false, true, true, array( Rule::Length, Rule::Pattern ) );
		yield 'number'   => array( FieldTypes\NumberField::class, 'number', false, true, true, array( Rule::NumberRange ) );
		yield 'password' => array( FieldTypes\PasswordField::class, 'password', false, true, true, array( Rule::Length, Rule::Pattern ) );
		yield 'select'   => array( FieldTypes\SelectField::class, 'select', true, true, true, array() );
		yield 'radio'    => array( FieldTypes\RadioField::class, 'radio', true, false, true, array() );
		yield 'checkbox' => array( FieldTypes\CheckboxField::class, 'checkbox', false, false, true, array() );
		yield 'date'     => array( FieldTypes\DateField::class, 'date', false, false, true, array() );
		yield 'hidden'   => array( FieldTypes\HiddenField::class, 'hidden', false, false, false, array() );
	}

	/**
	 * @param class-string<FieldTypeInterface> $class_name Type class.
	 * @param string                           $slug       Expected slug.
	 * @param bool                             $options    Supports options.
	 * @param bool                             $placeholder Supports a placeholder.
	 * @param bool                             $required   Can be required.
	 * @param list<Rule>                       $rules      Allowed rules.
	 */
	#[DataProvider( 'free_types' )]
	public function test_type_capabilities( string $class_name, string $slug, bool $options, bool $placeholder, bool $required, array $rules ): void {
		$type = new $class_name();

		$this->assertSame( $slug, $type->slug() );
		$this->assertSame( $options, $type->supports_options(), 'supports_options' );
		$this->assertSame( $placeholder, $type->supports_placeholder(), 'supports_placeholder' );
		$this->assertSame( $required, $type->can_be_required(), 'can_be_required' );
		$this->assertSame( $rules, $type->allowed_rules(), 'allowed_rules' );
	}

	/**
	 * @param class-string<FieldTypeInterface> $class_name Type class.
	 */
	#[DataProvider( 'free_types' )]
	public function test_type_has_picker_metadata( string $class_name ): void {
		$type = new $class_name();

		$this->assertMatchesRegularExpression( Patterns::TYPE_SLUG, $type->slug() );
		$this->assertNotSame( '', $type->label() );
		$this->assertStringEndsWith( '.', $type->description(), 'Description is one full sentence.' );
		$this->assertMatchesRegularExpression( '/^[a-z][a-z-]*$/', $type->icon(), 'Dashicons name without prefix.' );
	}

	public function test_length_caps_and_built_in_rules(): void {
		$expected = array(
			'text'     => array( 255, array() ),
			'textarea' => array( 5000, array() ),
			'email'    => array( 254, array( Rule::Email ) ),
			'phone'    => array( 255, array( Rule::Phone ) ),
			'number'   => array( 255, array() ),
			'password' => array( 255, array() ),
			'select'   => array( 255, array() ),
			'radio'    => array( 255, array() ),
			'checkbox' => array( 255, array() ),
			'date'     => array( 10, array() ),
			'hidden'   => array( 255, array() ),
		);

		foreach ( FieldTypes\FieldTypeRegistry::FREE_TYPES as $class_name ) {
			$type            = new $class_name();
			[ $max, $rules ] = $expected[ $type->slug() ];

			$this->assertSame( $max, $type->max_length(), $type->slug() );
			$this->assertSame( $rules, array_map( static fn( $r ) => $r->type, $type->built_in_rules() ), $type->slug() );
		}
	}

	public function test_checkbox_sanitises_to_one_or_empty(): void {
		$checkbox = new FieldTypes\CheckboxField();

		foreach ( array( '1', 1, true, 'on', 'yes', 'true' ) as $ticked ) {
			$this->assertSame( '1', $checkbox->sanitize( $ticked ) );
		}
		foreach ( array( '', '0', 0, false, null, 'no', array( '1' ), '<script>', ' 1', 'ON' ) as $not_ticked ) {
			$this->assertSame( '', $checkbox->sanitize( $not_ticked ) );
		}
	}

	public function test_every_free_type_is_covered_here(): void {
		$tested = array_map( static fn( array $row ): string => $row[0], iterator_to_array( self::free_types(), false ) );

		$this->assertSame( FieldTypes\FieldTypeRegistry::FREE_TYPES, $tested );
	}
}
