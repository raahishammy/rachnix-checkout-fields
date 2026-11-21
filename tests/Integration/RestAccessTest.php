<?php
/**
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Tests\Integration;

use Rachnix\CheckoutFields\Rest\AbstractController;
use Rachnix\CheckoutFields\Security\Capabilities;

/**
 * Step 4 gate: every real `rxcf/v1` route refuses a logged-out visitor and a shop
 * customer. Refused requests never reach a callback, so this is safe against the
 * plugin's real tables; the test also checks that nothing was written.
 */
final class RestAccessTest extends IntegrationTestCase {

	private const SAMPLE_UUID = '3f2a8c1e-5b7d-4e9f-a0c2-1d3e5f7a9b0c';

	/**
	 * A valid body for each writing route, so WordPress's argument validation passes
	 * and the permission check is what refuses the request. Every route must be listed.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const BODIES = array(
		'POST /rxcf/v1/fields'                      => array(
			'section' => 'billing',
			'key'     => 'billing_access_test',
			'type'    => 'text',
		),
		'POST /rxcf/v1/fields/{uuid}'               => array( 'label' => 'x' ),
		'PUT /rxcf/v1/fields/{uuid}'                => array( 'label' => 'x' ),
		'PATCH /rxcf/v1/fields/{uuid}'              => array( 'label' => 'x' ),
		'DELETE /rxcf/v1/fields/{uuid}'             => array(),
		'GET /rxcf/v1/fields'                       => array(),
		'GET /rxcf/v1/fields/{uuid}'                => array(),
		'POST /rxcf/v1/fields/reorder'              => array(
			'section' => 'billing',
			'order'   => array(),
		),
		'GET /rxcf/v1/field-types'                  => array(),
		'GET /rxcf/v1/sections'                     => array(),
		'POST /rxcf/v1/sections/{section}/reset'    => array( 'confirm' => true ),
		'GET /rxcf/v1/core-fields'                  => array(),
		'POST /rxcf/v1/core-fields/{section}/{key}' => array( 'enabled' => false ),
		'GET /rxcf/v1/settings'                     => array(),
		'POST /rxcf/v1/settings'                    => array( 'style' => array( 'mode' => 'plugin' ) ),
	);

	private static int $customer = 0;

	private static int $manager = 0;

	public static function setUpBeforeClass(): void {
		self::$customer = self::create_test_user( 'customer' );
		self::$manager  = self::create_test_user( 'shop_manager' );
	}

	public static function tearDownAfterClass(): void {
		wp_set_current_user( 0 );
		self::delete_test_user( self::$customer );
		self::delete_test_user( self::$manager );
	}

	protected function tearDown(): void {
		global $wp_rest_auth_cookie;
		$wp_rest_auth_cookie = null;
		unset( $_SERVER['HTTP_X_WP_NONCE'] );
		wp_set_current_user( 0 );
	}

	public function test_every_route_is_covered_by_this_test(): void {
		$this->assertEqualsCanonicalizing( array_keys( self::BODIES ), array_keys( $this->endpoints() ), 'A route was added or removed: update BODIES.' );
	}

	public function test_every_endpoint_has_a_real_permission_callback(): void {
		foreach ( rest_get_server()->get_routes( AbstractController::NAMESPACE ) as $route => $handlers ) {
			if ( '/' . AbstractController::NAMESPACE === $route ) {
				continue; // WordPress's own namespace index: lists route schemas, public for every plugin, no data.
			}
			foreach ( $handlers as $handler ) {
				$callback = $handler['permission_callback'] ?? null;
				$this->assertIsCallable( $callback, $route );
				$this->assertNotSame( '__return_true', $callback, $route );
			}
		}
	}

	public function test_logged_out_visitor_is_refused_everywhere(): void {
		wp_set_current_user( 0 );
		$this->assert_all_refused( 401 );
	}

	public function test_shop_customer_is_refused_everywhere(): void {
		wp_set_current_user( self::$customer );
		$this->assertFalse( current_user_can( Capabilities::MANAGE_FIELDS ) );
		$this->assert_all_refused( 403 );
	}

	public function test_shop_manager_has_the_capability(): void {
		$this->assertTrue( user_can( self::$manager, Capabilities::MANAGE_FIELDS ) );
		$this->assertFalse( user_can( self::$customer, Capabilities::MANAGE_FIELDS ) );
	}

	public function test_cookie_login_without_nonce_counts_as_logged_out(): void {
		global $wp_rest_auth_cookie;
		wp_set_current_user( self::$manager );
		$wp_rest_auth_cookie = true;

		rest_cookie_check_errors( null );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 401, rest_do_request( new \WP_REST_Request( 'GET', '/rxcf/v1/fields' ) )->get_status() );
	}

	public function test_cookie_login_with_nonce_is_accepted(): void {
		global $wp_rest_auth_cookie;
		wp_set_current_user( self::$manager );
		$wp_rest_auth_cookie        = true;
		$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );

		$this->assertTrue( rest_cookie_check_errors( null ) );

		$this->assertSame( self::$manager, get_current_user_id() );
		$this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/rxcf/v1/field-types' ) )->get_status() );
	}

	/**
	 * @param int $status Expected status for every endpoint.
	 */
	private function assert_all_refused( int $status ): void {
		$before = $this->real_row_counts();

		foreach ( $this->endpoints() as $name => [ $method, $path ] ) {
			$request = new \WP_REST_Request( $method, $path );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( self::BODIES[ $name ] ) );

			$response = rest_do_request( $request );

			$this->assertSame( $status, $response->get_status(), "{$name}: " . wp_json_encode( $response->get_data() ) );
		}

		$this->assertSame( $before, $this->real_row_counts(), 'A refused request changed data.' );
	}

	/**
	 * Every method of every registered route, as "METHOD /route-with-placeholders" => [method, concrete path].
	 *
	 * @return array<string, array{string, string}>
	 */
	private function endpoints(): array {
		$endpoints = array();
		foreach ( rest_get_server()->get_routes( AbstractController::NAMESPACE ) as $route => $handlers ) {
			if ( '/' . AbstractController::NAMESPACE === $route ) {
				continue; // Namespace index, generated by WordPress.
			}
			$readable = (string) preg_replace( '#\(\?P<(\w+)>(?:[^()]|\([^()]*\))*\)#', '{$1}', $route );
			$concrete = str_replace( array( '{uuid}', '{section}', '{key}' ), array( self::SAMPLE_UUID, 'billing', 'billing_company' ), $readable );
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( $handler['methods'] ) as $method ) {
					$endpoints[ "{$method} {$readable}" ] = array( $method, $concrete );
				}
			}
		}
		return $endpoints;
	}

	/**
	 * @return array<string, int>
	 */
	private function real_row_counts(): array {
		global $wpdb;
		$counts = array();
		foreach ( array( 'fields', 'audit_log' ) as $table ) {
			$counts[ $table ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rxcf_' . $table ) );
		}
		return $counts;
	}
}
