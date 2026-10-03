<?php
/** Ratio-based Steadfast profile and risk regression. Run with php tests/steadfast-profile-regression.php. */
namespace EilmoCheckout\Admin {
	final class CourierSettings {
		public static function get_settings(): array {
			return array( 'steadfast' => array( 'api_key' => 'test-key', 'secret_key' => 'test-secret', 'base_url' => 'https://example.test/api/v1' ), 'live_fraud' => array(
				'trusted_rate' => 95, 'advance_rate' => 90, 'block_rate' => 40,
				'minimum_orders_for_block' => 5,
				'payment_rules' => array(
				'trusted' => array( 'full' ), 'review' => array( 'full' ),
				'high' => array( 'full' ), 'critical' => array( 'full' ),
				'unknown' => array( 'full' ),
				),
			) );
		}
	}
	final class CheckoutSettings {
		public const OPTION_NAME = 'checkout';
		public static function get_defaults(): array { return array( 'advance_payment' => array( 'enabled' => 'no' ) ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function sanitize_key( $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
	function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
	function absint( $value ): int { return abs( (int) $value ); }
	function get_option( $key, $default = false ) { return $default; }
	function wp_json_encode( $value ): string { return json_encode( $value ); }
	function __( $message, $domain = '' ): string { return $message; }
	function untrailingslashit( $value ): string { return rtrim( $value, '/' ); }
	function esc_url_raw( $value ): string { return $value; }
	class WP_Error {
		private $code;
		public function __construct( $code, $message = '' ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
	function wp_safe_remote_get( $url, $args ) {
		$GLOBALS['last_steadfast_url'] = $url;
		return $GLOBALS['steadfast_response'];
	}
	function wp_remote_retrieve_response_code( $response ): int { return $response['code']; }
	function wp_remote_retrieve_body( $response ): string { return $response['body']; }
	require dirname( __DIR__ ) . '/src/Couriers/Services/SteadfastProfileService.php';
	require dirname( __DIR__ ) . '/src/Couriers/Services/FraudRiskService.php';
	require dirname( __DIR__ ) . '/src/Couriers/Services/CourierSuccessService.php';
	$profile = \EilmoCheckout\Couriers\Services\SteadfastProfileService::normalize( array(
		'delivery_ratio' => 92, 'cancellation_ratio' => 7, 'volume_band' => 'high',
		'total_reports' => 2, 'fraud_categories' => array( 'no_response' => 2 ),
		'total_delivered' => 999,
	) );
	if ( 92.0 !== $profile['delivery_ratio'] || 7.0 !== $profile['cancellation_ratio'] || isset( $profile['total'] ) || 2 !== $profile['fraud_categories']['no_response'] ) {
		throw new \RuntimeException( 'Steadfast profile used old counts or changed independent ratios.' );
	}
	$risk = ( new \EilmoCheckout\Couriers\Services\FraudRiskService() )->evaluate( array( 'stats' => array( 'steadfast' => $profile ) ) );
	if ( 'review' !== $risk['band'] || 92.0 !== $risk['success_rate'] || 0 !== $risk['total_orders'] ) {
		throw new \RuntimeException( 'Steadfast risk did not use the published delivery ratio.' );
	}
	$empty = \EilmoCheckout\Couriers\Services\SteadfastProfileService::normalize( array( 'delivery_ratio' => 0, 'cancellation_ratio' => 0, 'volume_band' => 'none', 'total_reports' => 0 ) );
	if ( $empty['available'] || 'unknown' !== ( new \EilmoCheckout\Couriers\Services\FraudRiskService() )->evaluate( array( 'stats' => array( 'steadfast' => $empty ) ) )['band'] ) {
		throw new \RuntimeException( 'No finished Steadfast history was treated as available.' );
	}
	$GLOBALS['steadfast_response'] = array( 'code' => 200, 'body' => json_encode( array( 'delivery_ratio' => 92, 'cancellation_ratio' => 7, 'volume_band' => 'high', 'total_reports' => 2, 'fraud_categories' => array( 'no_response' => 2 ) ) ) );
	$method = new \ReflectionMethod( \EilmoCheckout\Couriers\Services\CourierSuccessService::class, 'request_steadfast_api' );
	$result = $method->invoke( new \EilmoCheckout\Couriers\Services\CourierSuccessService(), '01712345678' );
	if ( 'https://example.test/api/v1/fraud_check/score/01712345678' !== $GLOBALS['last_steadfast_url'] || 92.0 !== $result['stats']['steadfast']['ratio'] ) {
		throw new \RuntimeException( 'Steadfast request missed the score endpoint or failed normalization.' );
	}
	$GLOBALS['steadfast_response'] = array( 'code' => 429, 'body' => '' );
	$limited = $method->invoke( new \EilmoCheckout\Couriers\Services\CourierSuccessService(), '01712345678' );
	if ( ! $limited instanceof WP_Error || 'eilmo_cf_courier_rate_limited' !== $limited->get_error_code() ) {
		throw new \RuntimeException( 'Steadfast rate limit was not handled.' );
	}
	$bd = ( new \EilmoCheckout\Couriers\Services\FraudRiskService() )->evaluate( array( 'stats' => array( 'redx' => array( 'total' => 10, 'success' => 10, 'cancel' => 0 ) ) ) );
	if ( 10 !== $bd['total_orders'] || 'trusted' !== $bd['band'] || 100.0 !== $bd['success_rate'] ) {
		throw new \RuntimeException( 'Count-based BD Courier risk changed.' );
	}
	$service = new \EilmoCheckout\Couriers\Services\CourierSuccessService();
	$extract_bd = new \ReflectionMethod( $service, 'extract_bd_courier_stats' );
	$normalize_bd = new \ReflectionMethod( $service, 'normalize_provider' );
	$sanitize_bd = new \ReflectionMethod( $service, 'sanitize_normalized_provider_stat' );
	$rate_only = $normalize_bd->invoke( $service, array(
		'total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0,
		'success_ratio' => 100, 'rate_only' => true,
		'parcel_range' => '6–20', 'volume_band' => 'medium',
	), 'Steadfast' );
	$round_trip = $sanitize_bd->invoke( $service, $rate_only, 'Steadfast' );
	if ( empty( $round_trip['rate_only'] ) || '6–20' !== $round_trip['parcel_range'] || 'medium' !== $round_trip['volume_band'] || 100.0 !== (float) $round_trip['ratio'] || 0 !== $round_trip['total'] ) {
		throw new \RuntimeException( 'BD Courier rate-only Steadfast range was lost or turned into parcel counts.' );
	}
	$extracted = $extract_bd->invoke( $service, array( 'steadfast' => array(
		'total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0,
		'success_ratio' => 100, 'rate_only' => true,
		'parcel_range' => '6–20', 'volume_band' => 'medium',
	) ) );
	if ( '6–20' !== ( $extracted['steadfast']['parcel_range'] ?? '' ) ) {
		throw new \RuntimeException( 'BD Courier did not extract the live Steadfast volume range.' );
	}
	$rate_only_risk = ( new \EilmoCheckout\Couriers\Services\FraudRiskService() )->evaluate( array( 'stats' => array( 'steadfast' => array(
		'delivery_ratio' => $round_trip['ratio'], 'volume_band' => $round_trip['volume_band'],
	) ) ) );
	if ( 'trusted' !== $rate_only_risk['band'] ) {
		throw new \RuntimeException( 'BD Courier Steadfast 100% display band was not trusted.' );
	}
	$low_rate_risk = ( new \EilmoCheckout\Couriers\Services\FraudRiskService() )->evaluate( array( 'stats' => array( 'steadfast' => array(
		'delivery_ratio' => 25, 'volume_band' => 'medium',
	) ) ) );
	if ( 'critical' !== $low_rate_risk['band'] ) {
		throw new \RuntimeException( 'BD Courier Steadfast display band did not change with the rate.' );
	}
	$count_based = $normalize_bd->invoke( $service, array( 'total_parcel' => 16, 'success_parcel' => 15, 'cancelled_parcel' => 1, 'success_ratio' => 93.75 ), 'Steadfast' );
	if ( isset( $count_based['rate_only'] ) || 16 !== $count_based['total'] || 15 !== $count_based['success'] ) {
		throw new \RuntimeException( 'BD Courier count-based history changed.' );
	}
	echo "Steadfast profile and ratio risk regression passed.\n";
}
