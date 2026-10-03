<?php
/**
 * Abandoned Checkout tracker.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AbandonedCheckout;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend Abandoned Checkout tracking.
 */
final class AbandonedCheckoutTracker implements RegistrableInterface {

	/**
	 * Tracking AJAX action.
	 */
	public const AJAX_ACTION =
		'eilmo_cf_track_abandoned_checkout';

	/**
	 * Successful checkout cleanup AJAX action.
	 */
	public const COMPLETE_AJAX_ACTION =
		'eilmo_cf_complete_abandoned_checkout';

	/**
	 * AJAX nonce action.
	 */
	public const NONCE_ACTION =
		'eilmo_cf_abandoned_checkout_tracking';

	/**
	 * Token length.
	 */
	private const TOKEN_LENGTH =
		64;

	/**
	 * Maximum snapshot items.
	 */
	private const MAX_ITEMS =
		100;

	/**
	 * Completed-session transient prefix.
	 *
	 * Prevents a late/in-flight tracking request from
	 * recreating a row after the order succeeded.
	 */
	private const COMPLETED_TRANSIENT_PREFIX =
		'eilmo_cf_abandoned_completed_';

	/**
	 * Completed-session protection lifetime.
	 */
	private const COMPLETED_TRANSIENT_TTL =
		10 * MINUTE_IN_SECONDS;

	/**
	 * Browser cookie used only by native WooCommerce checkout tracking.
	 */
	private const NATIVE_COOKIE_NAME =
		'eilmo_cf_abandoned_native_token';

	/**
	 * Register tracker hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_ajax_' .
				self::AJAX_ACTION,
			array(
				$this,
				'handle_track',
			)
		);

		add_action(
			'wp_ajax_nopriv_' .
				self::AJAX_ACTION,
			array(
				$this,
				'handle_track',
			)
		);

		add_action(
			'wp_ajax_' .
				self::COMPLETE_AJAX_ACTION,
			array(
				$this,
				'handle_complete',
			)
		);

		add_action(
			'wp_ajax_nopriv_' .
				self::COMPLETE_AJAX_ACTION,
			array(
				$this,
				'handle_complete',
			)
		);


		/* Native WooCommerce checkout cleanup. */
		add_action(
			'woocommerce_checkout_order_created',
			array( $this, 'handle_native_order_completed' ),
			20
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			array( $this, 'handle_native_order_completed' ),
			20
		);
	}

	/**
	 * Handle frontend tracking request.
	 *
	 * @return void
	 */
	public function handle_track(): void {

		if (
			! $this->is_enabled()
		) {
			wp_send_json_success(
				array(
					'tracked' =>
						false,

					'disabled' =>
						true,
				)
			);
		}

		$this->verify_nonce();

		$checkout =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'checkout'
				]
			) &&
			is_array(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'checkout'
				]
			)
				? wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
					$_POST[
						'checkout'
					]
				)
				: array();

		$data =
			$this->sanitize_checkout_data(
				$checkout
			);

		if (
			! $this->is_meaningful(
				$data
			)
		) {
			wp_send_json_success(
				array(
					'tracked' =>
						false,
				)
			);
		}

		$tracking_token =
			$this->get_request_tracking_token();

		$generated_token =
			false;

		if (
			'' === $tracking_token
		) {
			$tracking_token =
				$this->generate_token();

			$generated_token =
				true;
		}

		$session_key =
			$this->hash_token(
				$tracking_token
			);

		if (
			'' === $session_key
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Invalid checkout tracking token.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * The order may already have completed while a
		 * delayed tracking request was still waiting.
		 */
		if (
			$this->is_completed_session(
				$session_key
			)
		) {
			wp_send_json_success(
				array(
					'tracked' =>
						false,

					'completed' =>
						true,
				)
			);
		}

		try {

			$repository =
				new AbandonedCheckoutRepository();

			$result =
				$repository->save(
					$session_key,
					$data
				);

			if (
				is_wp_error(
					$result
				)
			) {
				wp_send_json_error(
					array(
						'message' =>
							$result->get_error_message(),
					),
					500
				);
			}

			/*
			 * Race protection.
			 *
			 * The completion request may have arrived
			 * while repository->save() was executing.
			 *
			 * Re-check the completed marker and remove
			 * the row immediately if necessary.
			 */
			if (
				$this->is_completed_session(
					$session_key
				)
			) {
				$repository->delete_by_session(
					$session_key
				);

				wp_send_json_success(
					array(
						'tracked' =>
							false,

						'completed' =>
							true,
					)
				);
			}

			$checkout_id =
				absint(
					$result
				);

			if (
				$checkout_id <= 0
			) {
				wp_send_json_error(
					array(
						'message' =>
							__(
								'Checkout activity could not be saved.',
								'eilmo-checkout-flow'
							),
					),
					500
				);
			}

			do_action(
				'eilmo_cf/abandoned_checkout/tracked',
				$checkout_id,
				$data
			);

			$response =
				array(
					'tracked' =>
						true,

					'checkout_id' =>
						$checkout_id,
				);

			if (
				$generated_token
			) {
				$response[
					'tracking_token'
				] =
					$tracking_token;
			}

			wp_send_json_success(
				$response
			);

		} catch ( Throwable $throwable ) {

			do_action(
				'eilmo_cf/abandoned_checkout/tracking_failed',
				$throwable,
				$data
			);

			wp_send_json_error(
				array(
					'message' =>
						__(
							'Checkout activity could not be saved.',
							'eilmo-checkout-flow'
						),
				),
				500
			);
		}
	}

	/**
	 * Handle successful checkout cleanup.
	 *
	 * checkout.js creates the WooCommerce order.
	 *
	 * This action only removes the matching Eilmo
	 * Abandoned Checkout tracking record.
	 *
	 * It never creates, modifies or links WooCommerce
	 * orders.
	 *
	 * @return void
	 */
	public function handle_complete(): void {

		$this->verify_nonce();

		$tracking_token =
			$this->get_request_tracking_token();

		if (
			'' === $tracking_token
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Invalid checkout tracking token.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$session_key =
			$this->hash_token(
				$tracking_token
			);

		if (
			'' === $session_key
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Invalid checkout tracking token.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * Set this before deleting the row.
		 *
		 * Any delayed tracking request using the same
		 * token will now refuse to save/recreate it.
		 */
		$this->mark_session_completed(
			$session_key
		);

		try {

			$repository =
				new AbandonedCheckoutRepository();

			$deleted =
				$repository->delete_by_session(
					$session_key
				);

			if ( ! $deleted ) {
				wp_send_json_error(
					array(
						'message' =>
							__(
								'Checkout tracking record could not be removed.',
								'eilmo-checkout-flow'
							),
					),
					500
				);
			}

			/**
			 * Fires after a successful checkout removes
			 * its abandoned tracking record.
			 *
			 * Raw tracking token is intentionally not
			 * exposed.
			 *
			 * @param string $session_key Hashed session key.
			 */
			do_action(
				'eilmo_cf/abandoned_checkout/completed',
				$session_key
			);

			wp_send_json_success(
				array(
					'completed' =>
						true,
				)
			);

		} catch ( Throwable $throwable ) {

			do_action(
				'eilmo_cf/abandoned_checkout/completion_failed',
				$throwable,
				$session_key
			);

			wp_send_json_error(
				array(
					'message' =>
						__(
							'Checkout tracking record could not be removed.',
							'eilmo-checkout-flow'
						),
				),
				500
			);
		}
	}

	/**
	 * Verify tracking nonce.
	 *
	 * @return void
	 */
	private function verify_nonce(): void {

		if (
			check_ajax_referer(
				self::NONCE_ACTION,
				'nonce',
				false
			)
		) {
			return;
		}

		wp_send_json_error(
			array(
				'message' =>
					__(
						'Invalid tracking request.',
						'eilmo-checkout-flow'
					),
			),
			403
		);
	}

	/**
	 * Get raw tracking token from request.
	 *
	 * @return string
	 */
	private function get_request_tracking_token(): string {

		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'tracking_token'
				]
			)
		) {
			return '';
		}

		return $this->normalize_token(
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				(string) $_POST[
					'tracking_token'
				]
			)
		);
	}

	/**
	 * Remove a native WooCommerce checkout tracking record after a
	 * successful Classic or Checkout Block order is created.
	 *
	 * @param mixed $order WooCommerce order object.
	 *
	 * @return void
	 */
	public function handle_native_order_completed( $order ): void {

		$token = isset( $_COOKIE[ self::NATIVE_COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::NATIVE_COOKIE_NAME ] ) )
			: '';

		$session_key = $this->hash_token( $token );

		if ( '' === $session_key ) {
			return;
		}

		$this->mark_session_completed( $session_key );

		try {
			( new AbandonedCheckoutRepository() )->delete_by_session( $session_key );
		} catch ( Throwable $throwable ) {
			// Native checkout completion must never fail because cleanup failed.
		}

		unset( $_COOKIE[ self::NATIVE_COOKIE_NAME ] );

		if ( ! headers_sent() ) {
			setcookie( self::NATIVE_COOKIE_NAME, '', time() - HOUR_IN_SECONDS, '/' );
		}
	}

	/**
	 * Sanitize frontend checkout data.
	 *
	 * @param array<string,mixed> $data Raw data.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_checkout_data(
		array $data
	): array {

		$courier_service =
			new CourierSuccessService();

		$billing_phone =
			$courier_service->normalize_phone(
				sanitize_text_field(
					(string) (
						$data[
							'billing_phone'
						] ??
							''
					)
				)
			);

		$raw_snapshot =
			$data[
				'cart_snapshot'
			] ??
				array();

		if (
			is_string(
				$raw_snapshot
			) &&
			'' !== trim(
				$raw_snapshot
			)
		) {
			$decoded =
				json_decode(
					$raw_snapshot,
					true
				);

			$raw_snapshot =
				is_array(
					$decoded
				)
					? $decoded
					: array();
		}

		if (
			! is_array(
				$raw_snapshot
			)
		) {
			$raw_snapshot =
				array();
		}

		$checkout_source = sanitize_key( (string) ( $data['checkout_source'] ?? AbandonedCheckoutRepository::SOURCE_EILMO ) );

		if ( ! in_array( $checkout_source, array( AbandonedCheckoutRepository::SOURCE_EILMO, AbandonedCheckoutRepository::SOURCE_CLASSIC, AbandonedCheckoutRepository::SOURCE_BLOCK ), true ) ) {
			$checkout_source = AbandonedCheckoutRepository::SOURCE_EILMO;
		}

		return array(
			'checkout_source' =>
				$checkout_source,

			'customer_id' =>
				is_user_logged_in()
					? get_current_user_id()
					: 0,

			'billing_first_name' =>
				sanitize_text_field(
					(string) (
						$data[
							'billing_first_name'
						] ??
							''
					)
				),

			'billing_last_name' =>
				sanitize_text_field(
					(string) (
						$data[
							'billing_last_name'
						] ??
							''
					)
				),

			'billing_phone' =>
				$billing_phone,

			'billing_email' =>
				sanitize_email(
					(string) (
						$data[
							'billing_email'
						] ??
							''
					)
				),

			'billing_address_1' =>
				sanitize_text_field(
					(string) ( $data['billing_address_1'] ?? '' )
				),

			'billing_address_2' => '',

			'billing_city' =>
				sanitize_text_field(
					(string) ( $data['billing_city'] ?? '' )
				),

			'billing_state' => '',

			'billing_postcode' => '',

			'billing_country' => '',

			'cart_snapshot' =>
				$this->sanitize_cart_snapshot(
					$raw_snapshot
				),

			'delivery_method' =>
				sanitize_key(
					(string) (
						$data[
							'delivery_method'
						] ??
							''
					)
				),

			'payment_method' =>
				sanitize_key(
					(string) (
						$data[
							'payment_method'
						] ??
							''
					)
				),

			'currency' =>
				function_exists(
					'get_woocommerce_currency'
				)
					? sanitize_key(
						(string) get_woocommerce_currency()
					)
					: '',

			'subtotal' =>
				$this->sanitize_amount(
					$data[
						'subtotal'
					] ??
						0
				),

			'total' =>
				$this->sanitize_amount(
					$data[
						'total'
					] ??
						0
				),
		);
	}

	/**
	 * Sanitize cart snapshot.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_cart_snapshot(
		array $snapshot
	): array {

		return array(
			'items' =>
				$this->sanitize_items(
					$snapshot[
						'items'
					] ??
						array()
				),

			'combo_offers' =>
				$this->sanitize_identifier_list(
					$snapshot[
						'combo_offers'
					] ??
						array()
				),

			'order_bumps' =>
				$this->sanitize_identifier_list(
					$snapshot[
						'order_bumps'
					] ??
						array()
				),
		);
	}

	/**
	 * Sanitize selected products.
	 *
	 * @param mixed $items Items.
	 *
	 * @return array<int,array<string,int>>
	 */
	private function sanitize_items(
		$items
	): array {

		if (
			! is_array(
				$items
			)
		) {
			return array();
		}

		$clean =
			array();

		foreach (
			array_slice(
				$items,
				0,
				self::MAX_ITEMS
			) as $item
		) {

			if (
				! is_array(
					$item
				)
			) {
				continue;
			}

			$product_id =
				absint(
					$item[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				min(
					999,
					absint(
						$item[
							'quantity'
						] ??
							0
					)
				);

			if (
				$product_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			$clean[] =
				array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						$quantity,
				);
		}

		return $clean;
	}

	/**
	 * Sanitize reusable identifiers.
	 *
	 * @param mixed $values Values.
	 *
	 * @return array<int,string>
	 */
	private function sanitize_identifier_list(
		$values
	): array {

		if (
			! is_array(
				$values
			)
		) {
			return array();
		}

		$clean =
			array();

		foreach (
			array_slice(
				$values,
				0,
				self::MAX_ITEMS
			) as $value
		) {

			$value =
				sanitize_key(
					(string) $value
				);

			if ( '' === $value ) {
				continue;
			}

			$clean[
				$value
			] =
				$value;
		}

		return array_values(
			$clean
		);
	}

	/**
	 * Determine whether checkout has meaningful data.
	 *
	 * @param array<string,mixed> $data Data.
	 *
	 * @return bool
	 */
	private function is_meaningful(
		array $data
	): bool {

		/* Start tracking only after a complete Bangladesh mobile number. */
		return '' !== (string) ( $data['billing_phone'] ?? '' );
	}

	/**
	 * Generate tracking token.
	 *
	 * @return string
	 */
	private function generate_token(): string {

		try {

			return bin2hex(
				random_bytes(
					32
				)
			);

		} catch ( Throwable $throwable ) {

			do_action(
				'eilmo_cf/abandoned_checkout/token_generation_failed',
				$throwable
			);

			return hash(
				'sha256',
				wp_generate_uuid4() .
				'|' .
				microtime(
					true
				) .
				'|' .
				wp_rand() .
				'|' .
				wp_salt(
					'nonce'
				)
			);
		}
	}

	/**
	 * Normalize raw tracking token.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	private function normalize_token(
		string $token
	): string {

		$token =
			strtolower(
				trim(
					$token
				)
			);

		if (
			self::TOKEN_LENGTH !==
				strlen(
					$token
				) ||
			1 !== preg_match(
				'/^[a-f0-9]{64}$/',
				$token
			)
		) {
			return '';
		}

		return $token;
	}

	/**
	 * Hash tracking token.
	 *
	 * @param string $token Raw token.
	 *
	 * @return string
	 */
	private function hash_token(
		string $token
	): string {

		$token =
			$this->normalize_token(
				$token
			);

		if ( '' === $token ) {
			return '';
		}

		return hash_hmac(
			'sha256',
			$token,
			wp_salt(
				'auth'
			)
		);
	}

	/**
	 * Get completed transient key.
	 *
	 * @param string $session_key Hashed session.
	 *
	 * @return string
	 */
	private function get_completed_transient_key(
		string $session_key
	): string {

		return self::COMPLETED_TRANSIENT_PREFIX .
			$session_key;
	}

	/**
	 * Mark session as successfully completed.
	 *
	 * @param string $session_key Hashed session.
	 *
	 * @return void
	 */
	private function mark_session_completed(
		string $session_key
	): void {

		set_transient(
			$this->get_completed_transient_key(
				$session_key
			),
			1,
			self::COMPLETED_TRANSIENT_TTL
		);
	}

	/**
	 * Determine whether session already completed.
	 *
	 * @param string $session_key Hashed session.
	 *
	 * @return bool
	 */
	private function is_completed_session(
		string $session_key
	): bool {

		return false !==
			get_transient(
				$this->get_completed_transient_key(
					$session_key
				)
			);
	}

	/**
	 * Sanitize informational amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return string
	 */
	private function sanitize_amount(
		$amount
	): string {

		if (
			! is_numeric(
				$amount
			)
		) {
			return '0';
		}

		$amount =
			max(
				0,
				(float) $amount
			);

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			return wc_format_decimal(
				$amount,
				8
			);
		}

		return number_format(
			$amount,
			8,
			'.',
			''
		);
	}

	/**
	 * Determine whether feature is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		$settings =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$settings
			)
		) {
			return false;
		}

		$general =
			isset(
				$settings[
					'general'
				]
			) &&
			is_array(
				$settings[
					'general'
				]
			)
				? $settings[
					'general'
				]
				: array();

		return (
			'yes' ===
				(
					$general[
						'abandoned_checkout'
					] ??
						'no'
				)
		);
	}
}
