<?php
/**
 * Signed live fraud decision transport.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents checkout clients from changing courier history or decisions.
 */
final class FraudDecisionToken {

	private const VERSION = 1;
	private const MAX_TOKEN_BYTES = 16384;

	/**
	 * Create a signed decision token.
	 *
	 * @param string              $phone Normalized phone.
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param array<string,mixed> $evaluation Evaluation.
	 * @param int                 $ttl Token TTL.
	 * @return string
	 */
	public function create( string $phone, array $snapshot, array $evaluation, int $ttl = 1800 ): string {

		$now = time();
		$payload = array(
			'version' => self::VERSION,
			'phone' => $phone,
			'issued_at' => $now,
			'expires_at' => $now + max( 300, min( DAY_IN_SECONDS, $ttl ) ),
			'snapshot' => $this->sanitize_snapshot( $snapshot ),
			'evaluation' => $this->sanitize_evaluation( $evaluation ),
		);
		$encoded = $this->base64url_encode( (string) wp_json_encode( $payload ) );
		$signature = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );

		return $encoded . '.' . $signature;
	}

	/**
	 * Verify and normalize a decision token.
	 *
	 * @param string $token Token.
	 * @param string $phone Expected normalized phone.
	 * @return array<string,mixed>|WP_Error
	 */
	public function verify( string $token, string $phone ) {

		$token = trim( $token );
		if ( '' === $token || strlen( $token ) > self::MAX_TOKEN_BYTES ) {
			return $this->error();
		}

		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return $this->error();
		}

		$expected = hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return $this->error();
		}

		$json = $this->base64url_decode( $parts[0] );
		$payload = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( ! is_array( $payload ) ) {
			return $this->error();
		}

		if (
			self::VERSION !== absint( $payload['version'] ?? 0 ) ||
			$phone !== (string) ( $payload['phone'] ?? '' ) ||
			time() > absint( $payload['expires_at'] ?? 0 ) ||
			absint( $payload['issued_at'] ?? 0 ) > time() + 60
		) {
			return $this->error();
		}

		$snapshot = isset( $payload['snapshot'] ) && is_array( $payload['snapshot'] )
			? $this->sanitize_snapshot( $payload['snapshot'] )
			: array();
		$evaluation = isset( $payload['evaluation'] ) && is_array( $payload['evaluation'] )
			? $this->sanitize_evaluation( $payload['evaluation'] )
			: array();

		if ( empty( $evaluation['action'] ) ) {
			return $this->error();
		}

		return array(
			'phone' => $phone,
			'issued_at' => absint( $payload['issued_at'] ),
			'expires_at' => absint( $payload['expires_at'] ),
			'snapshot' => $snapshot,
			'evaluation' => $evaluation,
		);
	}

	/** @return array<string,mixed> */
	private function sanitize_snapshot( array $snapshot ): array {

		$stats = isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] ) ? $snapshot['stats'] : array();
		$clean_stats = array();
		$count = 0;
		foreach ( $stats as $raw_key => $provider ) {
			if ( $count >= 25 || ! is_string( $raw_key ) || ! is_array( $provider ) ) {
				continue;
			}

			$key = sanitize_key( $raw_key );
			if ( '' === $key ) {
				continue;
			}

			$is_stat =
				array_key_exists( 'total', $provider ) ||
				array_key_exists( 'success', $provider ) ||
				array_key_exists( 'cancel', $provider ) ||
				array_key_exists( 'ratio', $provider ) ||
				array_key_exists( 'available', $provider );
			if ( ! $is_stat ) {
				continue;
			}
			if ( 'steadfast' === $key && array_key_exists( 'delivery_ratio', $provider ) ) {
				$clean_stats[ $key ] = SteadfastProfileService::normalize( $provider );
				++$count;
				continue;
			}

			$state = sanitize_key( (string) ( $provider['state'] ?? ( ! empty( $provider['available'] ) ? 'available' : 'no_history' ) ) );
			if ( ! in_array( $state, array( 'available', 'no_history', 'not_selected' ), true ) ) {
				$state = ! empty( $provider['available'] ) ? 'available' : 'no_history';
			}
			$clean_stats[ $key ] = array(
				'label' => sanitize_text_field( (string) ( $provider['label'] ?? '' ) ),
				'available' => ! empty( $provider['available'] ),
				'total' => max( 0, absint( $provider['total'] ?? 0 ) ),
				'success' => max( 0, absint( $provider['success'] ?? 0 ) ),
				'cancel' => max( 0, absint( $provider['cancel'] ?? 0 ) ),
				'ratio' => max( 0, min( 100, (float) ( $provider['ratio'] ?? 0 ) ) ),
				'state' => $state,
			);
			++$count;
		}

		return array(
			'version' => 3,
			'source' => sanitize_key( (string) ( $snapshot['source'] ?? 'bdcourier_live_checkout' ) ),
			'decision_source' => sanitize_key( (string) ( $snapshot['decision_source'] ?? '' ) ),
			'phone' => sanitize_text_field( (string) ( $snapshot['phone'] ?? '' ) ),
			'checked_at' => absint( $snapshot['checked_at'] ?? time() ),
			'status' => sanitize_key( (string) ( $snapshot['status'] ?? 'success' ) ),
			'error_code' => sanitize_key( (string) ( $snapshot['error_code'] ?? '' ) ),
			'provider_error' => sanitize_key( (string) ( $snapshot['provider_error'] ?? '' ) ),
			'cache_status' => sanitize_key( (string) ( $snapshot['cache_status'] ?? '' ) ),
			'cache_age_days' => max( 0, (float) ( $snapshot['cache_age_days'] ?? 0 ) ),
			'stats' => $clean_stats,
		);
	}

	/** @return array<string,mixed> */
	private function sanitize_evaluation( array $evaluation ): array {

		$action = sanitize_key( (string) ( $evaluation['action'] ?? '' ) );
		if ( ! in_array( $action, array( 'allow', 'advance', 'full', 'block' ), true ) ) {
			$action = '';
		}
		$payment_types = isset( $evaluation['allowed_payment_types'] ) && is_array( $evaluation['allowed_payment_types'] )
			? array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $type ): string {
								return is_scalar( $type ) ? sanitize_key( (string) $type ) : '';
							},
							$evaluation['allowed_payment_types']
						),
						static function ( string $type ): bool {
							return in_array( $type, array( 'cash_on_delivery', 'advance', 'full' ), true );
						}
					)
				)
			)
			: array();

		return array(
			'version' => 1,
			'action' => $action,
			'band' => sanitize_key( (string) ( $evaluation['band'] ?? '' ) ),
			'total_orders' => max( 0, absint( $evaluation['total_orders'] ?? 0 ) ),
			'success_orders' => max( 0, absint( $evaluation['success_orders'] ?? 0 ) ),
			'cancel_orders' => max( 0, absint( $evaluation['cancel_orders'] ?? 0 ) ),
			'success_rate' => max( 0, min( 100, (float) ( $evaluation['success_rate'] ?? 0 ) ) ),
			'risk_score' => array_key_exists( 'risk_score', $evaluation ) && null === $evaluation['risk_score'] ? null : max( 0, min( 100, absint( $evaluation['risk_score'] ?? 0 ) ) ),
			'confidence' => sanitize_key( (string) ( $evaluation['confidence'] ?? 'none' ) ),
			'error_code' => sanitize_key( (string) ( $evaluation['error_code'] ?? '' ) ),
			'decision_source' => sanitize_key( (string) ( $evaluation['decision_source'] ?? '' ) ),
			'local_total_orders' => max( 0, absint( $evaluation['local_total_orders'] ?? 0 ) ),
			'local_success_orders' => max( 0, absint( $evaluation['local_success_orders'] ?? 0 ) ),
			'local_cancel_orders' => max( 0, absint( $evaluation['local_cancel_orders'] ?? 0 ) ),
			'local_success_rate' => max( 0, min( 100, (float) ( $evaluation['local_success_rate'] ?? 0 ) ) ),
			'allowed_payment_types' => $payment_types,
			'payment_display_mode' => 'conditional' === sanitize_key( (string) ( $evaluation['payment_display_mode'] ?? 'always' ) ) ? 'conditional' : 'always',
			'policy_hash' => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) ( $evaluation['policy_hash'] ?? '' ) ) ),
		);
	}

	private function base64url_encode( string $value ): string {

		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/** @return string|false */
	private function base64url_decode( string $value ) {

		$padding = strlen( $value ) % 4;
		if ( $padding > 0 ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}

	private function error(): WP_Error {

		return new WP_Error(
			'eilmo_cf_live_fraud_invalid_token',
			__( 'Phone verification expired or is invalid. Please verify the phone number again.', 'eilmo-checkout-flow' )
		);
	}
}
