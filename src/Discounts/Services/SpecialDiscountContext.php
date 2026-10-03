<?php
/**
 * Signed per-checkout Special Discount assignment.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Discounts\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Protects the Special Discount scope selected by Elementor, shortcode or
 * Quick Checkout from being expanded by a frontend request.
 */
final class SpecialDiscountContext {

	/** Maximum rule IDs assigned to one checkout instance. */
	private const MAX_ALLOWED_IDS = 200;

	/**
	 * Create a signed checkout assignment.
	 *
	 * @param array<int|string, mixed> $allowed_ids Allowed rule IDs.
	 * @param string                   $instance_id Checkout instance ID.
	 * @param string                   $scope       all|selected|none.
	 *
	 * @return array<string, mixed>
	 */
	public function create(
		array $allowed_ids,
		string $instance_id,
		string $scope
	): array {
		$payload = array(
			'allowed_ids' => $this->normalize_ids( $allowed_ids ),
			'instance_id' => sanitize_key( $instance_id ),
			'scope'       => $this->normalize_scope( $scope ),
		);

		$payload['signature'] = $this->sign( $payload );

		return $payload;
	}

	/**
	 * Create a signed assignment from prepared checkout settings.
	 *
	 * @param array<string, mixed> $settings    Checkout settings.
	 * @param string               $instance_id Checkout instance ID.
	 *
	 * @return array<string, mixed>
	 */
	public function create_from_settings(
		array $settings,
		string $instance_id
	): array {
		$show = 'yes' === (string) ( $settings['show_special_offers'] ?? $settings['show_order_bumps'] ?? 'no' );
		$raw_ids = $settings['special_offer_ids'] ?? $settings['order_bump_ids'] ?? array();
		$allowed_ids = is_array( $raw_ids ) ? $this->normalize_ids( $raw_ids ) : array();

		$scope = ! $show
			? 'none'
			: ( ! empty( $allowed_ids ) ? 'selected' : 'all' );

		return $this->create( $allowed_ids, $instance_id, $scope );
	}

	/**
	 * Verify a submitted signed assignment.
	 *
	 * @param array<string, mixed> $context Submitted context.
	 *
	 * @return bool
	 */
	public function verify( array $context ): bool {
		$signature = strtolower( trim( (string) ( $context['signature'] ?? '' ) ) );

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return false;
		}

		$payload = array(
			'allowed_ids' => $this->get_allowed_ids( $context ),
			'instance_id' => sanitize_key( (string) ( $context['instance_id'] ?? '' ) ),
			'scope'       => $this->get_scope( $context ),
		);

		return hash_equals( $this->sign( $payload ), $signature );
	}

	/**
	 * Determine whether a rule belongs to this checkout.
	 *
	 * @param string               $rule_id Rule ID.
	 * @param array<string, mixed> $context Verified context.
	 *
	 * @return bool
	 */
	public function is_allowed( string $rule_id, array $context ): bool {
		if ( ! $this->verify( $context ) ) {
			return false;
		}

		$rule_id = sanitize_key( $rule_id );
		$scope = $this->get_scope( $context );

		if ( '' === $rule_id || 'none' === $scope ) {
			return false;
		}

		return 'all' === $scope || in_array( $rule_id, $this->get_allowed_ids( $context ), true );
	}

	/**
	 * Get normalized allowed IDs. Call verify() before trusting the result.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return array<int, string>
	 */
	public function get_allowed_ids( array $context ): array {
		return $this->normalize_ids(
			isset( $context['allowed_ids'] ) && is_array( $context['allowed_ids'] )
				? $context['allowed_ids']
				: array()
		);
	}

	/**
	 * Get normalized assignment scope.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	public function get_scope( array $context ): string {
		return $this->normalize_scope( (string) ( $context['scope'] ?? 'none' ) );
	}

	/**
	 * Sign the normalized context payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 *
	 * @return string
	 */
	private function sign( array $payload ): string {
		$normalized = array(
			'allowed_ids' => $this->get_allowed_ids( $payload ),
			'instance_id' => sanitize_key( (string) ( $payload['instance_id'] ?? '' ) ),
			'scope'       => $this->get_scope( $payload ),
		);
		$json = wp_json_encode( $normalized );

		return hash_hmac(
			'sha256',
			is_string( $json ) ? $json : '{}',
			wp_salt( 'auth' )
		);
	}

	/**
	 * Normalize rule IDs.
	 *
	 * @param array<int|string, mixed> $ids Rule IDs.
	 *
	 * @return array<int, string>
	 */
	public function normalize_ids( array $ids ): array {
		$normalized = array();

		foreach ( $ids as $id ) {
			if ( ! is_scalar( $id ) ) {
				continue;
			}

			$id = sanitize_key( (string) $id );
			if ( '' === $id || in_array( $id, $normalized, true ) ) {
				continue;
			}

			$normalized[] = $id;
			if ( count( $normalized ) >= self::MAX_ALLOWED_IDS ) {
				break;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize assignment scope.
	 *
	 * @param string $scope Scope.
	 *
	 * @return string
	 */
	private function normalize_scope( string $scope ): string {
		$scope = sanitize_key( $scope );

		return in_array( $scope, array( 'all', 'selected', 'none' ), true )
			? $scope
			: 'none';
	}
}
