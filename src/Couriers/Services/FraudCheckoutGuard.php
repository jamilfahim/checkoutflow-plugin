<?php
/**
 * Authoritative live fraud checkout enforcement.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use EilmoCheckout\Admin\CourierSettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/** Verifies the signed live result and enforces its payment decision. */
final class FraudCheckoutGuard {

	/**
	 * Validate a final checkout payload.
	 *
	 * @param array<string,mixed> $payload Raw request.
	 * @param array<string,mixed> $validated Server-normalized checkout.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( array $payload, array $validated ) {

		if ( ! CourierSettings::live_fraud_is_enabled() ) {
			return array();
		}

		$customer = isset( $validated['customer'] ) && is_array( $validated['customer'] ) ? $validated['customer'] : array();
		$phone = ( new CourierSuccessService() )->normalize_phone( (string) ( $customer['billing_phone'] ?? '' ) );
		$fraud = isset( $payload['fraud_check'] ) && is_array( $payload['fraud_check'] ) ? $payload['fraud_check'] : array();
		$token = isset( $fraud['token'] ) && is_scalar( $fraud['token'] ) ? (string) $fraud['token'] : '';
		$result = ( new FraudDecisionToken() )->verify( $token, $phone );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$snapshot = isset( $result['snapshot'] ) && is_array( $result['snapshot'] ) ? $result['snapshot'] : array();
		$token_evaluation = isset( $result['evaluation'] ) && is_array( $result['evaluation'] ) ? $result['evaluation'] : array();
		$risk = new FraudRiskService();
		$evaluation = 'error' === ( $snapshot['status'] ?? '' )
			? $risk->evaluate_failure( (string) ( $snapshot['error_code'] ?? $token_evaluation['error_code'] ?? '' ) )
			: $risk->evaluate( $snapshot );
		foreach ( array( 'decision_source', 'local_total_orders', 'local_success_orders', 'local_cancel_orders', 'local_success_rate' ) as $meta_key ) {
			if ( array_key_exists( $meta_key, $token_evaluation ) ) {
				$evaluation[ $meta_key ] = $token_evaluation[ $meta_key ];
			}
		}
		$action = sanitize_key( (string) ( $evaluation['action'] ?? 'allow' ) );
		$allowed_payment_types = isset( $evaluation['allowed_payment_types'] ) && is_array( $evaluation['allowed_payment_types'] )
			? $evaluation['allowed_payment_types']
			: array();
		$advance = isset( $validated['advance_payment'] ) && is_array( $validated['advance_payment'] ) ? $validated['advance_payment'] : array();
		$payment_type = sanitize_key( (string) ( $advance['payment_type'] ?? '' ) );

		if ( 'block' === $action || empty( $allowed_payment_types ) ) {
			return new WP_Error( 'eilmo_cf_live_fraud_blocked', $this->message( 'block' ) );
		}

		if ( ! in_array( $payment_type, $allowed_payment_types, true ) ) {
			if ( 'advance' === $action ) {
				return new WP_Error( 'eilmo_cf_live_fraud_advance_required', $this->message( 'advance' ) );
			}
			if ( 'full' === $action ) {
				return new WP_Error( 'eilmo_cf_live_fraud_full_required', $this->message( 'full' ) );
			}

			return new WP_Error(
				'eilmo_cf_live_fraud_payment_unavailable',
				__( 'The selected payment option is not available for this phone number. Please choose an available option.', 'eilmo-checkout-flow' )
			);
		}

		$result['evaluation'] = $evaluation;
		return $result;
	}

	/** @return string */
	private function message( string $action ): string {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] ) ? $settings['live_fraud'] : array();
		$key = in_array( $action, array( 'advance', 'full', 'block' ), true ) ? $action . '_message' : 'allow_message';
		return sanitize_text_field( (string) ( $config[ $key ] ?? __( 'Phone verification could not be completed.', 'eilmo-checkout-flow' ) ) );
	}
}
