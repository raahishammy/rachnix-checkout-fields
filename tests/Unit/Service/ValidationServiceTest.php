<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Unit\Service;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationError;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode as Code;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType as Rule;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\ValidationService;
use Rachnix\CheckoutFields\Tests\Unit\TestCase;

/**
 * Validation logic on already-sanitised values. Real WordPress sanitisers are
 * exercised in tests/Integration/HostileInputTest.
 */
final class ValidationServiceTest extends TestCase {

	private ValidationService $service;

	protected function setUp(): void {
		parent::setUp();
		// Stand-in for WordPress's is_email(); the real one runs in the integration tests.
		Functions\when( 'is_email' )->alias( static fn( string $email ) => false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false );

		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$this->service = new ValidationService( $registry, new PatternLibrary(), new Logger() );
	}

	public function test_empty_required_field_fails_and_optional_passes(): void {
		$this->assertCodes( array( Code::Required ), $this->field( 'text', new FieldSettings( required: true ) ), '' );
		$this->assertCodes( array(), $this->field( 'text' ), '' );
	}

	public function test_hidden_field_ignores_required(): void {
		$this->assertCodes( array(), $this->field( 'hidden', new FieldSettings( required: true ) ), '' );
	}

	public function test_required_checkbox_must_be_ticked(): void {
		$field = $this->field( 'checkbox', new FieldSettings( required: true ) );

		$this->assertCodes( array( Code::Required ), $field, '' );
		$this->assertCodes( array(), $field, '1' );
		$this->assertCodes( array( Code::InvalidChoice ), $field, 'yes' );
	}

	public function test_length_cap_is_checked_before_anything_else(): void {
		$field  = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::Pattern, pattern_id: 'digits' ) ) ) );
		$errors = $this->service->validate( $field, str_repeat( 'x', 256 ) );

		$this->assertCount( 1, $errors );
		$this->assertSame( Code::TooLong, $errors[0]->code );
		$this->assertSame( 255.0, $errors[0]->max );
		$this->assertCodes( array(), $this->field( 'text' ), str_repeat( 'é', 255 ), 'Cap counts characters, not bytes.' );
		$this->assertCodes( array( Code::TooLong ), $this->field( 'textarea' ), str_repeat( 'x', 5001 ) );
	}

	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function emails(): iterable {
		yield 'plain'           => array( 'anne@example.com', true );
		yield 'plus and subdomain' => array( 'anne+orders@mail.example.co.uk', true );
		yield 'no at'           => array( 'anne.example.com', false );
		yield 'two ats'         => array( 'a@b@example.com', false );
		yield 'header injection' => array( "anne@example.com\r\nBcc: x@evil.test", false );
		yield 'script'          => array( 'anne@example.com<script>', false );
	}

	#[DataProvider( 'emails' )]
	public function test_email_type_always_checks_the_format( string $email, bool $valid ): void {
		$this->assertCodes( $valid ? array() : array( Code::InvalidEmail ), $this->field( 'email' ), $email );
	}

	/**
	 * @return iterable<string, array{string, bool}>
	 */
	public static function phones(): iterable {
		yield 'international' => array( '+44 20 7946 0958', true );
		yield 'india'         => array( '+91 98765-43210', true );
		yield 'us brackets'   => array( '(555) 123-4567', true );
		yield 'too few digits' => array( '12345', false );
		yield 'too many digits' => array( '+1234567890123456', false );
		yield 'letters'       => array( '555-CALL-NOW', false );
		yield 'plus in middle' => array( '555+1234567', false );
		yield 'sql'           => array( "1' OR '1'='1", false );
	}

	#[DataProvider( 'phones' )]
	public function test_phone_type_always_checks_the_format( string $phone, bool $valid ): void {
		$this->assertCodes( $valid ? array() : array( Code::InvalidPhone ), $this->field( 'phone' ), $phone );
	}

	public function test_length_rule(): void {
		$field = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::Length, 3.0, 5.0 ) ) ) );

		$this->assertCodes( array( Code::TooShort ), $field, 'ab' );
		$this->assertCodes( array(), $field, 'abc' );
		$this->assertCodes( array(), $field, 'ééééé' );
		$this->assertCodes( array( Code::TooLong ), $field, 'abcdef' );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function not_numbers(): iterable {
		// phpcs:ignore PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound -- hostile input on purpose.
		foreach ( array( '1e5', '0x1A', 'INF', 'NAN', '1,5', '1.', '.5', '--1', '+1', '1 000', '١٢٣', '9999999999999999' ) as $value ) {
			yield $value => array( $value );
		}
	}

	#[DataProvider( 'not_numbers' )]
	public function test_number_type_accepts_plain_decimals_only( string $value ): void {
		$this->assertCodes( array( Code::InvalidNumber ), $this->field( 'number' ), $value );
	}

	public function test_number_range_rule(): void {
		$field = $this->field( 'number', new FieldSettings( validation: array( new ValidationRule( Rule::NumberRange, 1.0, 10.0 ) ) ) );

		$this->assertCodes( array(), $field, '1' );
		$this->assertCodes( array(), $field, '9.99' );
		$this->assertCodes( array( Code::OutOfRange ), $field, '0.5' );
		$this->assertCodes( array( Code::OutOfRange ), $field, '-3' );
		$this->assertCodes( array( Code::OutOfRange ), $field, '10.01' );
	}

	public function test_date_type(): void {
		$field = $this->field( 'date' );

		$this->assertCodes( array(), $field, '2026-12-24' );
		$this->assertCodes( array(), $field, '2028-02-29' );
		foreach ( array( '2026-02-30', '2026-13-01', '24/12/2026', '2026-1-1', 'tomorrow', '2026-12-2x' ) as $bad ) {
			$this->assertCodes( array( Code::InvalidDate ), $field, $bad, $bad );
		}
		$this->assertCodes( array( Code::TooLong ), $field, '2026-12-24x', 'Over 10 characters is cut off by the cap first.' );
	}

	public function test_choice_types_only_accept_their_options(): void {
		$settings = new FieldSettings( options: array( new FieldOption( 'am', 'Morning' ), new FieldOption( 'pm', 'Evening' ) ) );

		foreach ( array( 'select', 'radio' ) as $type ) {
			$field = $this->field( $type, $settings );
			$this->assertCodes( array(), $field, 'pm', $type );
			$this->assertCodes( array( Code::InvalidChoice ), $field, 'Morning', "{$type}: label is not a value" );
			$this->assertCodes( array( Code::InvalidChoice ), $field, 'AM', "{$type}: case sensitive" );
			$this->assertCodes( array( Code::InvalidChoice ), $field, "am' OR 1=1", $type );
		}
	}

	public function test_pattern_rule_uses_the_curated_library(): void {
		$field  = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::Pattern, pattern_id: 'pin_in' ) ) ) );
		$errors = $this->service->validate( $field, '012345' );

		$this->assertCodes( array(), $field, '560001' );
		$this->assertSame( Code::PatternMismatch, $errors[0]->code );
		$this->assertSame( '560001', $errors[0]->hint );
	}

	public function test_unknown_pattern_id_is_ignored(): void {
		$field = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::Pattern, pattern_id: 'no_such_pattern' ) ) ) );

		$this->assertCodes( array(), $field, 'anything' );
	}

	public function test_regex_engine_error_rejects_the_value(): void {
		$field = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::Pattern, pattern_id: 'letters' ) ) ) );

		// Invalid UTF-8 makes a /u pattern fail to run; the value must not slip through.
		$this->assertCodes( array( Code::PatternMismatch ), $field, "Anne\xC3\x28" );
	}

	public function test_rules_the_type_does_not_allow_are_ignored(): void {
		$field = $this->field( 'text', new FieldSettings( validation: array( new ValidationRule( Rule::NumberRange, 1.0, 2.0 ) ) ) );

		$this->assertCodes( array(), $field, 'hello' );
	}

	public function test_all_rule_errors_are_reported(): void {
		$field = $this->field(
			'text',
			new FieldSettings(
				validation: array(
					new ValidationRule( Rule::Length, 6.0 ),
					new ValidationRule( Rule::Pattern, pattern_id: 'digits' ),
				)
			)
		);

		$this->assertCodes( array( Code::TooShort, Code::PatternMismatch ), $field, 'abc' );
	}

	public function test_unregistered_type_is_not_validated(): void {
		$this->assertCodes( array(), $this->field( 'color', new FieldSettings( required: true ) ), '' );
	}

	public function test_error_messages_name_the_field(): void {
		Functions\stubTranslationFunctions();

		$this->assertSame( 'Gift note is a required field.', ( new ValidationError( 'gift', Code::Required ) )->message( 'Gift note' ) );
		$this->assertSame( 'Floor must be between 0 and 2.5.', ( new ValidationError( 'floor', Code::OutOfRange, 0.0, 2.5 ) )->message( 'Floor' ) );
		$this->assertSame( 'Floor must be 3 or more.', ( new ValidationError( 'floor', Code::OutOfRange, 3.0 ) )->message( 'Floor' ) );
		$this->assertSame( 'PIN is not in the expected format, for example 560001.', ( new ValidationError( 'pin', Code::PatternMismatch, hint: '560001' ) )->message( 'PIN' ) );
		$this->assertSame( 'Note must be at most 5 characters long.', ( new ValidationError( 'note', Code::TooLong, max: 5.0 ) )->message( 'Note' ) );
	}

	/**
	 * @param string        $type     Type slug.
	 * @param FieldSettings $settings Settings.
	 */
	private function field( string $type, FieldSettings $settings = new FieldSettings() ): Field {
		return new Field( '3f2a8c1e-5b7d-4e9f-a0c2-1d3e5f7a9b0c', SectionKey::core( Section::Billing ), 'billing_extra', $type, settings: $settings );
	}

	/**
	 * @param list<Code> $expected Expected error codes, in order.
	 * @param Field      $field    Field.
	 * @param string     $value    Sanitised value.
	 * @param string     $message  Assertion message.
	 */
	private function assertCodes( array $expected, Field $field, string $value, string $message = '' ): void {
		$codes = array_map( static fn( ValidationError $e ) => $e->code, $this->service->validate( $field, $value ) );
		$this->assertSame( $expected, $codes, $message );
	}
}
