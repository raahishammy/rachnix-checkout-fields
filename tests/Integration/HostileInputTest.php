<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Rachnix\CheckoutFields\Domain\Field;
use Rachnix\CheckoutFields\Domain\FieldOption;
use Rachnix\CheckoutFields\Domain\FieldSettings;
use Rachnix\CheckoutFields\Domain\Section;
use Rachnix\CheckoutFields\Domain\SectionKey;
use Rachnix\CheckoutFields\Domain\ValidationErrorCode;
use Rachnix\CheckoutFields\Domain\ValidationRule;
use Rachnix\CheckoutFields\Domain\ValidationRuleType;
use Rachnix\CheckoutFields\FieldTypes\FieldTypeRegistry;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Service\PatternLibrary;
use Rachnix\CheckoutFields\Service\Sanitizer;
use Rachnix\CheckoutFields\Service\ValidationService;

/**
 * Step 3 gate: hostile input (scripts, SQL, huge strings, malformed data) through the
 * real WordPress sanitisers and the ValidationService, for every free field type.
 */
final class HostileInputTest extends IntegrationTestCase {

	private Sanitizer $sanitizer;

	private ValidationService $validator;

	protected function setUp(): void {
		$registry = new FieldTypeRegistry();
		$registry->register_free_types();
		$this->sanitizer = new Sanitizer( $registry );
		$this->validator = new ValidationService( $registry, new PatternLibrary(), new Logger() );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function types(): iterable {
		foreach ( array( 'text', 'textarea', 'email', 'phone', 'number', 'password', 'select', 'radio', 'checkbox', 'date', 'hidden' ) as $type ) {
			yield $type => array( $type );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payloads(): array {
		return array(
			'script tag'        => '<script>alert(document.cookie)</script>',
			'img onerror'       => '<img src=x onerror=alert(1)>',
			'svg onload'        => '<svg/onload=alert(1)>',
			'attribute escape'  => '" onfocus="alert(1)" autofocus="',
			'javascript url'    => 'javascript:alert(1)',
			'html entity trick' => '&lt;script&gt;alert(1)&lt;/script&gt;',
			'sql union'         => "' UNION SELECT user_pass FROM wp_users --",
			'sql drop'          => "Robert'); DROP TABLE wp_users;--",
			'sql comment'       => '1/**/OR/**/1=1',
			'null byte'         => "safe\0<script>",
			'invalid utf8'      => "caf\xC3\x28 \xFF\xFE",
			'percent octets'    => '%3Cscript%3Ealert(1)%3C%2Fscript%3E',
			'crlf injection'    => "value\r\nSet-Cookie: admin=1",
			'rtl override'      => "invoice\u{202E}fdp.exe",
			'php tag'           => '<?php system($_GET["c"]); ?>',
			'huge string'       => str_repeat( 'A', 1024 * 1024 ),
			'huge with tags'    => str_repeat( '<b>x</b>', 100000 ),
			'array'             => array( '<script>', 'x' ),
			'nested array'      => array( 'a' => array( 'b' => '<script>' ) ),
			'object'            => (object) array( 'x' => 1 ),
			'null'              => null,
			'bool'              => true,
			'float'             => 1.5e308,
		);
	}

	#[DataProvider( 'types' )]
	public function test_sanitised_output_is_safe_for_every_payload( string $type ): void {
		$field = $this->field( $type );

		foreach ( self::payloads() as $name => $payload ) {
			$clean = $this->sanitizer->sanitize( $field, $payload );

			$this->assertIsString( $clean, "{$type}/{$name}" );
			$this->assertStringNotContainsString( '<', $clean, "{$type}/{$name}: no tags survive" );
			$this->assertStringNotContainsString( "\0", $clean, "{$type}/{$name}: no null bytes" );
			$this->assertTrue( mb_check_encoding( $clean, 'UTF-8' ), "{$type}/{$name}: valid UTF-8" );
			if ( 'textarea' !== $type ) {
				$this->assertDoesNotMatchRegularExpression( '/[\r\n\t]/', $clean, "{$type}/{$name}: single line" );
			}
			if ( is_array( $payload ) || is_object( $payload ) || null === $payload ) {
				$this->assertSame( '', $clean, "{$type}/{$name}: non-scalar input is dropped" );
			}
		}
	}

	#[DataProvider( 'types' )]
	public function test_validation_never_errors_out_on_any_payload( string $type ): void {
		$field = $this->field( $type, required: true );

		foreach ( self::payloads() as $name => $payload ) {
			$clean  = (string) $this->sanitizer->sanitize( $field, $payload );
			$errors = $this->validator->validate( $field, $clean );

			$this->assertIsArray( $errors, "{$type}/{$name}" );
		}
	}

	#[DataProvider( 'types' )]
	public function test_huge_input_is_rejected_by_length_not_by_timeout( string $type ): void {
		if ( 'checkbox' === $type ) {
			$this->assertSame( '', $this->sanitizer->sanitize( $this->field( $type ), str_repeat( '1', 1024 * 1024 ) ) );
			return;
		}

		$field = $this->field( $type );
		$start = hrtime( true );
		$clean = (string) $this->sanitizer->sanitize( $field, str_repeat( '7', 1024 * 1024 ) );
		$codes = array_map( static fn( $e ) => $e->code, $this->validator->validate( $field, $clean ) );
		$ms    = ( hrtime( true ) - $start ) / 1e6;

		$this->assertSame( array( ValidationErrorCode::TooLong ), $codes, $type );
		$this->assertLessThan( 500, $ms, "{$type}: sanitise + validate of 1 MB took {$ms} ms" );
	}

	public function test_script_content_is_removed_not_just_the_tags(): void {
		$clean = $this->sanitizer->sanitize( $this->field( 'text' ), 'Hello <script>alert(1)</script>world' );

		$this->assertStringNotContainsString( 'alert', (string) $clean );
	}

	public function test_sql_is_kept_as_harmless_text(): void {
		// Sanitising is not escaping: SQL stays text, and the prepared queries store it as data.
		$this->assertSame( "Robert'); DROP TABLE wp_users;--", $this->sanitizer->sanitize( $this->field( 'text' ), "Robert'); DROP TABLE wp_users;--" ) );
	}

	public function test_textarea_keeps_line_breaks_but_drops_tags(): void {
		$this->assertSame( "Leave at door\nRing twice", $this->sanitizer->sanitize( $this->field( 'textarea' ), "Leave at <b>door</b>\nRing twice<script>x</script>" ) );
	}

	public function test_hostile_choice_values_are_rejected(): void {
		$field = $this->field( 'select' );

		foreach ( array( "am' OR '1'='1", 'am<script>', 'AM', ' am' ) as $value ) {
			$clean  = (string) $this->sanitizer->sanitize( $field, $value );
			$errors = $this->validator->validate( $field, $clean );
			$codes  = array_map( static fn( $e ) => $e->code, $errors );

			if ( 'am' === $clean ) {
				$this->assertSame( array(), $codes, "{$value} sanitises to a real option" );
			} else {
				$this->assertSame( array( ValidationErrorCode::InvalidChoice ), $codes, $value );
			}
		}
	}

	public function test_real_is_email_rejects_hostile_addresses(): void {
		$field = $this->field( 'email' );

		foreach ( array( "a@example.com\nBcc: b@evil.test", '"><script>@x.com', 'a@b', "a@example.com'--" ) as $value ) {
			$clean = (string) $this->sanitizer->sanitize( $field, $value );
			$codes = array_map( static fn( $e ) => $e->code, $this->validator->validate( $field, $clean ) );

			$this->assertNotSame( array(), $codes, "{$value} ({$clean})" );
		}
		$this->assertSame( array(), $this->validator->validate( $field, 'anne@example.com' ) );
	}

	public function test_pattern_rule_on_hostile_input(): void {
		$field = $this->field( 'text', validation: array( new ValidationRule( ValidationRuleType::Pattern, pattern_id: 'letters' ) ) );

		// Invalid UTF-8 is dropped whole by WordPress, never half-repaired.
		$this->assertSame( '', $this->sanitizer->sanitize( $field, "Anne\xC3\x28x1" ) );

		foreach ( array( "Anne' OR 1=1", str_repeat( 'a', 255 ) . '1', 'Anne;', 'Anne1' ) as $value ) {
			$clean = (string) $this->sanitizer->sanitize( $field, $value );
			$codes = array_map( static fn( $e ) => $e->code, $this->validator->validate( $field, $clean ) );

			$this->assertNotSame( array(), $codes, $value );
		}

		// The tag is stripped; what remains is a clean, valid name.
		$clean = (string) $this->sanitizer->sanitize( $field, 'Anne<script>' );
		$this->assertSame( 'Anne', $clean );
		$this->assertSame( array(), $this->validator->validate( $field, $clean ) );
	}

	/**
	 * @param string               $type       Type slug.
	 * @param bool                 $required   Required.
	 * @param list<ValidationRule> $validation Rules.
	 */
	private function field( string $type, bool $required = false, array $validation = array() ): Field {
		return new Field(
			wp_generate_uuid4(),
			SectionKey::core( Section::Billing ),
			'billing_extra',
			$type,
			settings: new FieldSettings(
				required: $required,
				options: array( new FieldOption( 'am', 'Morning' ), new FieldOption( 'pm', 'Evening' ) ),
				validation: $validation,
			)
		);
	}
}
