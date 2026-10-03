<?php
/**
 * Live checkout fraud AJAX endpoint.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Ajax;

use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Couriers\Services\FraudAssessmentService;
use EilmoCheckout\Couriers\Services\FraudDecisionToken;

defined( 'ABSPATH' ) || exit;

/** Handles the single checkout-time courier history request. */
final class LiveFraudAjax implements RegistrableInterface {

	public const ACTION = 'eilmo_cf_live_fraud_check';
	public const NONCE_ACTION = 'eilmo_cf_frontend';

	/** Register AJAX hooks. */
	public function register(): void {

		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/** Process one verification request. */
	public function handle(): void {

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed. Please refresh and try again.', 'eilmo-checkout-flow' ) ), 403 );
		}

		if ( ! CourierSettings::live_fraud_is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Live phone verification is not enabled.', 'eilmo-checkout-flow' ) ), 400 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The scalar is length-limited and normalized by CourierSuccessService immediately below.
		$raw_phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';
		$raw_phone = is_scalar( $raw_phone ) ? (string) $raw_phone : '';
		if ( strlen( $raw_phone ) > 40 ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid Bangladesh mobile number.', 'eilmo-checkout-flow' ) ), 400 );
		}

		$success_service = new CourierSuccessService();
		$phone = $success_service->normalize_phone( $raw_phone );
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid Bangladesh mobile number.', 'eilmo-checkout-flow' ) ), 400 );
		}

		if ( ! $this->within_rate_limit( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many verification requests. Please wait and try again.', 'eilmo-checkout-flow' ) ), 429 );
		}

		$assessment = ( new FraudAssessmentService() )->assess( $phone );
		$snapshot = isset( $assessment['snapshot'] ) && is_array( $assessment['snapshot'] )
			? $assessment['snapshot']
			: array();
		$evaluation = isset( $assessment['evaluation'] ) && is_array( $assessment['evaluation'] )
			? $assessment['evaluation']
			: array();

		$settings = CourierSettings::get_settings();
		$ratio = isset( $settings['ratio_api'] ) && is_array( $settings['ratio_api'] ) ? $settings['ratio_api'] : array();
		$token = ( new FraudDecisionToken() )->create(
			$phone,
			$snapshot,
			$evaluation,
			max( 5, min( 1440, absint( $ratio['cache_minutes'] ?? 360 ) ) ) * MINUTE_IN_SECONDS
		);
		$action = sanitize_key( (string) ( $evaluation['action'] ?? 'allow' ) );

		wp_send_json_success(
			array(
				'decision' => $action,
				'band' => sanitize_key( (string) ( $evaluation['band'] ?? '' ) ),
				'allowedPaymentTypes' => isset( $evaluation['allowed_payment_types'] ) && is_array( $evaluation['allowed_payment_types'] )
					? array_values( $evaluation['allowed_payment_types'] )
					: array(),
				'paymentDisplayMode' => 'conditional' === ( $evaluation['payment_display_mode'] ?? 'always' ) ? 'conditional' : 'always',
				'token' => $token,
				'source' => sanitize_key( (string) ( $evaluation['decision_source'] ?? $snapshot['decision_source'] ?? '' ) ),
				'cacheStatus' => sanitize_key( (string) ( $snapshot['cache_status'] ?? '' ) ),
				'message' => $this->message( $action, $evaluation, $snapshot ),
			)
		);
	}

	/** @return bool */
	private function within_rate_limit( string $phone ): bool {

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'eilmo_cf_live_fraud_rate_' . substr( hash_hmac( 'sha256', $ip . '|' . $phone, wp_salt( 'nonce' ) ), 0, 40 );
		$count = absint( get_transient( $key ) );
		if ( $count >= 12 ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/** @return string */
	private function message( string $action, array $evaluation, array $snapshot ): string {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] ) ? $settings['live_fraud'] : array();
		$band = sanitize_key( (string) ( $evaluation['band'] ?? '' ) );
		$source = sanitize_key( (string) ( $evaluation['decision_source'] ?? $snapshot['decision_source'] ?? '' ) );
		$cache_status = sanitize_key( (string) ( $snapshot['cache_status'] ?? '' ) );
		if ( 'unavailable' === $band || 'api_unavailable' === $source ) {
			return sanitize_text_field( (string) ( $config['unavailable_message'] ?? __( 'Live verification is temporarily unavailable. You can still place your order using an available payment option.', 'eilmo-checkout-flow' ) ) );
		}
		if ( 'unknown' === $band ) {
			return sanitize_text_field( (string) ( $config['unknown_message'] ?? __( 'No delivery history found.', 'eilmo-checkout-flow' ) ) );
		}
		if ( 'stale' === $cache_status || 'stale_cache' === $source ) {
			return sanitize_text_field( (string) ( $config['stale_message'] ?? __( 'Saved courier history is being used because a fresh check is unavailable.', 'eilmo-checkout-flow' ) ) );
		}
		if ( in_array( $source, array( 'local_history', 'local_fallback' ), true ) ) {
			return sanitize_text_field( (string) ( $config['local_message'] ?? __( 'Your previous purchase history has been checked.', 'eilmo-checkout-flow' ) ) );
		}
		$key = in_array( $action, array( 'allow', 'advance', 'full', 'block' ), true ) ? $action . '_message' : 'allow_message';
		return sanitize_text_field( (string) ( $config[ $key ] ?? __( 'Verification completed.', 'eilmo-checkout-flow' ) ) );
	}
}
