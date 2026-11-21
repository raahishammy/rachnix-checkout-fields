<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Checkout\CheckoutStyles;
use Rachnix\CheckoutFields\Repository\AuditLogRepository;
use Rachnix\CheckoutFields\Repository\SettingsRepository;
use Rachnix\CheckoutFields\Rest\SettingsController;
use Rachnix\CheckoutFields\Security\Logger;
use Rachnix\CheckoutFields\Security\RequestContext;
use Rachnix\CheckoutFields\Service\SettingsService;

/**
 * Style settings through the REST API (rxcftest/v1, rxcftest_settings option) and
 * how CheckoutStyles turns them into body classes and inline CSS.
 */
final class StylesTest extends IntegrationTestCase {

	private const OPTION = 'rxcftest_settings';

	private static int $manager = 0;

	private static int $admin = 0;

	private ?\Closure $routes = null;

	private SettingsRepository $settings;

	public static function setUpBeforeClass(): void {
		self::reset_test_schema();
		self::migrator()->migrate();
		self::$manager = self::create_test_user( 'shop_manager' );
		self::$admin   = self::create_test_user( 'administrator' );
	}

	public static function tearDownAfterClass(): void {
		wp_set_current_user( 0 );
		self::delete_test_user( self::$manager );
		self::delete_test_user( self::$admin );
		delete_option( self::OPTION );
		self::reset_test_schema();
	}

	protected function setUp(): void {
		delete_option( self::OPTION );
		$this->settings = new SettingsRepository( self::OPTION );
		$controller     = new SettingsController(
			new SettingsService( $this->settings, new AuditLogRepository( self::db(), self::tables(), new RequestContext(), new Logger() ) ),
			new Logger(),
			'rxcftest/v1'
		);

		$this->routes = static function () use ( $controller ): void {
			$controller->register_routes();
		};
		add_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh REST server per test.
		rest_get_server();
		wp_set_current_user( self::$manager );
	}

	protected function tearDown(): void {
		remove_action( 'rest_api_init', $this->routes );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reset for other tests.
		wp_set_current_user( 0 );
	}

	public function test_defaults_and_partial_update(): void {
		$this->assertSame( 'theme', $this->call( 'GET' )->get_data()['style']['mode'] );

		$response = $this->call(
			'POST',
			array(
				'mode'   => 'custom',
				'accent' => '#ABC',
				'radius' => 10,
			)
		);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$style = $response->get_data()['style'];
		$this->assertSame( 'custom', $style['mode'] );
		$this->assertSame( '#aabbcc', $style['accent'], 'Short hex expanded, lowercased.' );
		$this->assertSame( 10, $style['radius'] );
		$this->assertSame( 16, $style['spacing'], 'Untouched values keep their defaults.' );

		$this->assertNull( $this->call( 'POST', array( 'accent' => '' ) )->get_data()['style']['accent'], 'Empty resets to the theme colour.' );
	}

	public function test_invalid_values_are_rejected(): void {
		foreach ( array(
			'mode'           => 'neon',
			'accent'         => 'red',
			'radius'         => 99,
			'spacing'        => -1,
			'label_position' => 'under',
		) as $key => $value ) {
			$this->assertSame( 400, $this->call( 'POST', array( $key => $value ) )->get_status(), $key );
		}
		$this->assertSame( 400, $this->call( 'POST', array( 'unknown' => 1 ) )->get_status() );
	}

	public function test_custom_css_needs_edit_css(): void {
		$this->assertFalse( current_user_can( 'edit_css' ), 'Shop managers cannot edit CSS by default.' );
		$refused = $this->call( 'POST', array( 'custom_css' => '.x{color:red}' ) );
		$this->assertSame( 400, $refused->get_status() );
		$this->assertArrayHasKey( 'style.custom_css', $refused->as_error()->get_error_data()['params'] );

		wp_set_current_user( self::$admin );
		if ( ! current_user_can( 'edit_css' ) ) {
			$this->markTestSkipped( 'Administrators cannot edit CSS on this site (multisite or DISALLOW_UNFILTERED_HTML).' );
		}
		$saved = $this->call( 'POST', array( 'custom_css' => ".x{color:red}</style><script>alert(1)</script>\n.y>.z{margin:0}" ) );
		$this->assertSame( 200, $saved->get_status() );
		$css = $saved->get_data()['style']['custom_css'];
		$this->assertStringNotContainsString( '<', $css );
		$this->assertStringContainsString( '.y>.z{margin:0}', $css, 'Child combinators survive.' );
	}

	public function test_styles_follow_the_mode(): void {
		$styles = new CheckoutStyles( $this->settings, WP_PLUGIN_DIR . '/rachnix-checkout-fields/rachnix-checkout-fields.php', static fn(): bool => true );

		$theme = 'rxcf-theme-' . sanitize_html_class( get_template() );
		$this->assertSame( array( 'x', 'rxcf-style-theme', $theme ), $styles->body_class( array( 'x' ) ) );
		$this->assertSame( '', $styles->inline_css(), 'Follow theme: no inline CSS.' );

		$this->call(
			'POST',
			array(
				'mode'           => 'custom',
				'accent'         => '#1a73e8',
				'radius'         => 6,
				'spacing'        => 20,
				'label_position' => 'side',
			)
		);
		$this->assertSame( array( 'rxcf-style-custom', $theme, 'rxcf-labels-side' ), $styles->body_class( array() ) );
		$this->assertSame( ':root{--rxcf-radius:6px;--rxcf-gap:20px;--rxcf-accent:#1a73e8}', $styles->inline_css() );

		$this->call( 'POST', array( 'mode' => 'plugin' ) );
		$this->assertSame( array( 'rxcf-style-plugin', $theme ), $styles->body_class( array() ) );
	}

	public function test_nothing_is_loaded_outside_the_checkout(): void {
		$styles = new CheckoutStyles( $this->settings, WP_PLUGIN_DIR . '/rachnix-checkout-fields/rachnix-checkout-fields.php', static fn(): bool => false );

		$styles->enqueue();
		$this->assertFalse( wp_style_is( CheckoutStyles::HANDLE, 'enqueued' ) );
		$this->assertSame( array( 'x' ), $styles->body_class( array( 'x' ) ) );
	}

	public function test_stylesheet_is_enqueued_on_the_checkout(): void {
		$styles = new CheckoutStyles( $this->settings, WP_PLUGIN_DIR . '/rachnix-checkout-fields/rachnix-checkout-fields.php', static fn(): bool => true );

		$styles->enqueue();
		$this->assertTrue( wp_style_is( CheckoutStyles::HANDLE, 'enqueued' ) );
		$this->assertStringEndsWith( '/rachnix-checkout-fields/assets/css/checkout.css', wp_styles()->registered[ CheckoutStyles::HANDLE ]->src );
		wp_dequeue_style( CheckoutStyles::HANDLE );
		wp_deregister_style( CheckoutStyles::HANDLE );
	}

	/**
	 * @param string               $method Method.
	 * @param array<string, mixed> $style  Style changes (POST).
	 */
	private function call( string $method, array $style = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rxcftest/v1/settings' );
		if ( 'POST' === $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( array( 'style' => $style ) ) );
		}
		return rest_do_request( $request );
	}
}
