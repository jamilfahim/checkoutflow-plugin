<?php
/**
 * Meta browser-to-server event controller.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Receives first-party browser event signals and mirrors
 * supported events through Meta Conversions API.
 */
final class MetaServerEventController implements RegistrableInterface {

	/**
	 * AJAX action.
	 */
	public const AJAX_ACTION =
		'eilmo_cf_meta_server_event';

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION =
		'eilmo_cf_meta_server_event';

	/**
	 * Successful event lock lifetime.
	 */
	private const EVENT_LOCK_TTL =
		DAY_IN_SECONDS;

	/**
	 * In-flight lock lifetime.
	 */
	private const PROCESSING_LOCK_TTL =
		MINUTE_IN_SECONDS;

	/**
	 * Soft per-IP request ceiling per minute.
	 */
	private const RATE_LIMIT_PER_MINUTE =
		120;

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_ajax_' .
				self::AJAX_ACTION,
			array(
				$this,
				'handle',
			)
		);

		add_action(
			'wp_ajax_nopriv_' .
				self::AJAX_ACTION,
			array(
				$this,
				'handle',
			)
		);
	}

	/**
	 * Handle browser-originated server event.
	 *
	 * @return void
	 */
	public function handle(): void {

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Meta Conversions API is disabled.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		if ( ! $this->verify_nonce() ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Invalid Meta tracking request.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		if ( ! $this->within_rate_limit() ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Too many tracking requests.',
							'eilmo-checkout-flow'
						),
				),
				429
			);
		}

		$event_name =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['event_name'] )
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['event_name']
					)
				)
				: '';

		$event_id =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['event_id'] )
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['event_id']
					)
				)
				: '';

		$event_key =
			$this->get_event_key(
				$event_name
			);

		if (
			'' === $event_key ||
			! MetaTrackingSettings::event_is_enabled(
				$event_key
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Unsupported Meta tracking event.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$event_id =
			$this->normalize_event_id(
				$event_id
			);

		if ( '' === $event_id ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Meta event ID is missing.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$allowed =
			(bool) apply_filters(
				'eilmo_cf/meta_tracking/server_allowed',
				true,
				$event_name
			);

		if ( ! $allowed ) {
			wp_send_json_success(
				array(
					'skipped' =>
						true,
				)
			);
		}

		$lock_key =
			$this->get_event_lock_key(
				$event_name,
				$event_id
			);

		$existing_lock =
			get_transient(
				$lock_key
			);

		if ( false !== $existing_lock ) {
			wp_send_json_success(
				array(
					'deduplicated' =>
						true,

					'event_id' =>
						$event_id,
				)
			);
		}

		set_transient(
			$lock_key,
			'processing',
			self::PROCESSING_LOCK_TTL
		);

		$raw_data =
			$this->decode_json_post_field(
				'custom_data'
			);

		$customer =
			$this->decode_json_post_field(
				'customer'
			);

		$source_url =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['source_url'] )
				? esc_url_raw(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['source_url']
					)
				)
				: '';

		$builder =
			new MetaServerEventBuilder();

		$event =
			$builder->build(
				$event_name,
				$event_id,
				$raw_data,
				$customer,
				$source_url
			);

		if ( empty( $event ) ) {
			delete_transient(
				$lock_key
			);

			wp_send_json_error(
				array(
					'message' =>
						__(
							'Unable to build Meta server event.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$api =
			new MetaConversionsApi();

		$response =
			$api->send_event(
				$event
			);

		if ( is_wp_error( $response ) ) {
			delete_transient(
				$lock_key
			);

			/**
			 * Fires when a browser-originated CAPI event fails.
			 *
			 * @param WP_Error $response   Error.
			 * @param string   $event_name Event name.
			 * @param string   $event_id   Event ID.
			 */
			do_action(
				'eilmo_cf/meta_tracking/server_event_failed',
				$response,
				$event_name,
				$event_id
			);

			wp_send_json_error(
				array(
					'message' =>
						$response->get_error_message(),
				),
				502
			);
		}

		set_transient(
			$lock_key,
			'sent',
			self::EVENT_LOCK_TTL
		);

		/**
		 * Fires after a browser-originated CAPI event succeeds.
		 *
		 * @param array<string,mixed> $response   Response.
		 * @param string              $event_name Event name.
		 * @param string              $event_id   Event ID.
		 */
		do_action(
			'eilmo_cf/meta_tracking/server_event_sent',
			$response,
			$event_name,
			$event_id
		);

		wp_send_json_success(
			array(
				'event_id' =>
					$event_id,

				'events_received' =>
					absint(
						$response['events_received'] ??
							0
					),
			)
		);
	}

	/**
	 * Verify AJAX nonce.
	 *
	 * @return bool
	 */
	private function verify_nonce(): bool {

		$nonce =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['nonce'] )
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['nonce']
					)
				)
				: '';

		return '' !== $nonce &&
			false !==
				wp_verify_nonce(
					$nonce,
					self::NONCE_ACTION
				);
	}

	/**
	 * Get supported settings event key.
	 *
	 * @param string $event_name Event name.
	 *
	 * @return string
	 */
	private function get_event_key(
		string $event_name
	): string {

		$map =
			array(
				'ViewContent' =>
					'view_content',

				'AddToCart' =>
					'add_to_cart',

				'InitiateCheckout' =>
					'initiate_checkout',
			);

		return $map[ $event_name ] ??
			'';
	}

	/**
	 * Normalize event ID.
	 *
	 * @param string $event_id Event ID.
	 *
	 * @return string
	 */
	private function normalize_event_id(
		string $event_id
	): string {

		$event_id =
			preg_replace(
				'/[^a-zA-Z0-9_.:-]/',
				'',
				trim(
					$event_id
				)
			);

		if ( ! is_string( $event_id ) ) {
			return '';
		}

		return substr(
			$event_id,
			0,
			128
		);
	}

	/**
	 * Decode one JSON POST field.
	 *
	 * @param string $key Field key.
	 *
	 * @return array<string,mixed>
	 */
	private function decode_json_post_field(
		string $key
	): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		if ( ! isset( $_POST[ $key ] ) ) {
			return array();
		}

		$raw =
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				(string) $_POST[ $key ]
			);

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$decoded =
			json_decode(
				$raw,
				true
			);

		return is_array( $decoded )
			? $decoded
			: array();
	}

	/**
	 * Get event lock transient key.
	 *
	 * @param string $event_name Event name.
	 * @param string $event_id   Event ID.
	 *
	 * @return string
	 */
	private function get_event_lock_key(
		string $event_name,
		string $event_id
	): string {

		return 'eilmo_cf_meta_evt_' .
			md5(
				$event_name .
				'|' .
				$event_id
			);
	}

	/**
	 * Soft request rate limit.
	 *
	 * @return bool
	 */
	private function within_rate_limit(): bool {

		$ip =
			$this->get_client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$key =
			'eilmo_cf_meta_rate_' .
			md5(
				$ip .
				'|' .
				gmdate( 'YmdHi' )
			);

		$count =
			absint(
				get_transient(
					$key
				)
			);

		if (
			$count >=
				self::RATE_LIMIT_PER_MINUTE
		) {
			return false;
		}

		set_transient(
			$key,
			$count + 1,
			2 * MINUTE_IN_SECONDS
		);

		return true;
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {

		$ip =
			'';

		if ( class_exists( '\\WC_Geolocation' ) ) {
			$ip =
				sanitize_text_field(
					(string) \WC_Geolocation
						::get_ip_address()
				);
		}

		if (
			'' === $ip &&
			isset( $_SERVER['REMOTE_ADDR'] )
		) {
			$ip =
				sanitize_text_field(
					wp_unslash(
						(string) $_SERVER['REMOTE_ADDR']
					)
				);
		}

		return false !==
			filter_var(
				$ip,
				FILTER_VALIDATE_IP
			)
				? $ip
				: '';
	}
}
