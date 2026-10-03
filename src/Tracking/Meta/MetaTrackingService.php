<?php
/**
 * Meta Tracking service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates authoritative Meta server-side Purchase tracking.
 *
 * Basic strategy:
 * - Purchase is eligible during the normal successful WooCommerce
 *   order flow.
 * - Browser Purchase may use the same stable event ID for deduplication.
 *
 * Advanced strategy:
 * - Browser Purchase is disabled by the frontend bridge.
 * - Purchase is sent only through CAPI when an order reaches one of
 *   the configured WooCommerce statuses.
 */
final class MetaTrackingService implements RegistrableInterface {

	/**
	 * Order attribution metadata.
	 */
	private const META_FBP =
		'_eilmo_cf_meta_fbp';

	private const META_FBC =
		'_eilmo_cf_meta_fbc';

	private const META_CLIENT_IP =
		'_eilmo_cf_meta_client_ip';

	private const META_CLIENT_USER_AGENT =
		'_eilmo_cf_meta_client_user_agent';

	private const META_EVENT_SOURCE_URL =
		'_eilmo_cf_meta_event_source_url';

	/**
	 * Purchase tracking metadata.
	 */
	private const META_PURCHASE_EVENT_ID =
		'_eilmo_cf_meta_purchase_event_id';

	private const META_PURCHASE_SENT =
		'_eilmo_cf_meta_purchase_sent';

	private const META_PURCHASE_SENT_AT =
		'_eilmo_cf_meta_purchase_sent_at';

	private const META_PURCHASE_RESPONSE =
		'_eilmo_cf_meta_purchase_response';

	private const META_PURCHASE_STRATEGY =
		'_eilmo_cf_meta_purchase_strategy';

	private const META_PURCHASE_TRIGGER_STATUS =
		'_eilmo_cf_meta_purchase_trigger_status';

	private const META_PURCHASE_LAST_ERROR =
		'_eilmo_cf_meta_purchase_last_error';

	private const META_PURCHASE_LAST_ERROR_AT =
		'_eilmo_cf_meta_purchase_last_error_at';

	/**
	 * Short in-flight lock to reduce duplicate concurrent CAPI sends.
	 */
	private const PURCHASE_LOCK_TTL =
		5 * MINUTE_IN_SECONDS;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * Capture browser attribution while the original
		 * checkout request still has cookies, IP, user
		 * agent and referrer available.
		 */
		add_action(
			'woocommerce_checkout_order_created',
			array(
				$this,
				'capture_order_context',
			),
			20
		);

		/*
		 * Eilmo manual payment/COD completion signal.
		 * Used only by Basic Purchase strategy.
		 */
		add_action(
			'eilmo_cf/orders/manual_processed',
			array(
				$this,
				'track_manual_purchase',
			),
			20,
			2
		);

		/*
		 * Gateway payment-complete signal.
		 * Used only by Basic Purchase strategy.
		 */
		add_action(
			'woocommerce_payment_complete',
			array(
				$this,
				'track_payment_complete',
			),
			20
		);

		/*
		 * One generic status transition hook supports both
		 * WooCommerce core and registered custom statuses.
		 *
		 * Basic strategy uses successful placed/paid states
		 * as a fallback. Advanced strategy uses only the
		 * statuses configured by the merchant.
		 */
		add_action(
			'woocommerce_order_status_changed',
			array(
				$this,
				'track_order_status_change',
			),
			20,
			4
		);
	}

	/**
	 * Capture attribution context on the WooCommerce order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public function capture_order_context(
		WC_Order $order
	): void {

		if (
			! MetaTrackingSettings::is_enabled()
		) {
			return;
		}

		$changed =
			false;

		$fbp =
			$this->get_cookie(
				'_fbp'
			);

		if (
			'' !== $fbp &&
			'' ===
				(string) $order->get_meta(
					self::META_FBP,
					true
				)
		) {
			$order->update_meta_data(
				self::META_FBP,
				$fbp
			);

			$changed =
				true;
		}

		$fbc =
			$this->get_cookie(
				'_fbc'
			);

		if ( '' === $fbc ) {
			$fbc =
				$this->build_fbc_from_referrer();
		}

		if (
			'' !== $fbc &&
			'' ===
				(string) $order->get_meta(
					self::META_FBC,
					true
				)
		) {
			$order->update_meta_data(
				self::META_FBC,
				$fbc
			);

			$changed =
				true;
		}

		$client_ip =
			$this->get_client_ip(
				$order
			);

		if (
			'' !== $client_ip &&
			'' ===
				(string) $order->get_meta(
					self::META_CLIENT_IP,
					true
				)
		) {
			$order->update_meta_data(
				self::META_CLIENT_IP,
				$client_ip
			);

			$changed =
				true;
		}

		$user_agent =
			$this->get_client_user_agent(
				$order
			);

		if (
			'' !== $user_agent &&
			'' ===
				(string) $order->get_meta(
					self::META_CLIENT_USER_AGENT,
					true
				)
		) {
			$order->update_meta_data(
				self::META_CLIENT_USER_AGENT,
				$user_agent
			);

			$changed =
				true;
		}

		$source_url =
			$this->get_source_url();

		if (
			'' !== $source_url &&
			'' ===
				(string) $order->get_meta(
					self::META_EVENT_SOURCE_URL,
					true
				)
		) {
			$order->update_meta_data(
				self::META_EVENT_SOURCE_URL,
				$source_url
			);

			$changed =
				true;
		}

		if ( $changed ) {
			$order->save();
		}
	}

	/**
	 * Track Eilmo manual/COD Purchase in Basic mode.
	 *
	 * Advanced mode intentionally ignores this signal and waits
	 * for a configured WooCommerce order status instead.
	 *
	 * @param WC_Order             $order  Order.
	 * @param array<string,mixed> $result Processing result.
	 *
	 * @return void
	 */
	public function track_manual_purchase(
		WC_Order $order,
		array $result = array()
	): void {

		unset( $result );

		if (
			! MetaTrackingSettings::uses_basic_purchase_tracking()
		) {
			return;
		}

		$this->maybe_send_purchase(
			$order,
			$this->normalize_status(
				(string) $order->get_status()
			),
			'manual_processed'
		);
	}

	/**
	 * Track a successfully paid WooCommerce order in Basic mode.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function track_payment_complete(
		$order_id
	): void {

		if (
			! MetaTrackingSettings::uses_basic_purchase_tracking()
		) {
			return;
		}

		$order =
			wc_get_order(
				absint(
					$order_id
				)
			);

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->maybe_send_purchase(
			$order,
			$this->normalize_status(
				(string) $order->get_status()
			),
			'payment_complete'
		);
	}

	/**
	 * Track Purchase from a WooCommerce status transition.
	 *
	 * Advanced mode sends only when the new status matches one of
	 * the merchant-selected trigger statuses. Basic mode retains
	 * the standard successful placed/paid status fallback.
	 *
	 * @param int           $order_id Order ID.
	 * @param string        $from     Previous status.
	 * @param string        $to       New status.
	 * @param WC_Order|null $order    Order object.
	 *
	 * @return void
	 */
	public function track_order_status_change(
		$order_id,
		$from,
		$to,
		$order = null
	): void {

		unset( $from );

		if ( ! $order instanceof WC_Order ) {
			$order =
				wc_get_order(
					absint(
						$order_id
					)
				);
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$status =
			$this->normalize_status(
				(string) $to
			);

		if ( '' === $status ) {
			return;
		}

		if (
			MetaTrackingSettings::uses_advanced_purchase_tracking()
		) {
			if (
				! MetaTrackingSettings::is_purchase_trigger_status(
					$status
				)
			) {
				return;
			}

			$this->maybe_send_purchase(
				$order,
				$status,
				'order_status'
			);

			return;
		}

		$eligibility =
			new MetaOrderEligibility();

		if (
			! $eligibility->is_purchase_state_eligible(
				$order
			)
		) {
			return;
		}

		$this->maybe_send_purchase(
			$order,
			$status,
			'order_status'
		);
	}

	/**
	 * Send Purchase once for one WooCommerce order.
	 *
	 * A failed API call is not marked as sent, allowing a later
	 * eligible hook/status transition to retry the same stable event ID.
	 *
	 * @param WC_Order $order          Order.
	 * @param string   $trigger_status Status at the trigger moment.
	 * @param string   $trigger_source Trigger source identifier.
	 *
	 * @return void
	 */
	private function maybe_send_purchase(
		WC_Order $order,
		string $trigger_status = '',
		string $trigger_source = ''
	): void {

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			) ||
			! MetaTrackingSettings::event_is_enabled(
				'purchase'
			)
		) {
			return;
		}

		if (
			'yes' ===
				(string) $order->get_meta(
					self::META_PURCHASE_SENT,
					true
				)
		) {
			return;
		}

		$current_status =
			$this->normalize_status(
				(string) $order->get_status()
			);

		if (
			$this->is_non_purchase_status(
				$current_status
			)
		) {
			return;
		}

		$eligibility =
			new MetaOrderEligibility();

		/*
		 * Never let admin-created, imported or unrelated
		 * programmatic orders become advertising Purchase
		 * conversions by default.
		 */
		if (
			! $eligibility->is_website_checkout_order(
				$order
			)
		) {
			return;
		}

		$strategy =
			MetaTrackingSettings::get_purchase_strategy();

		$trigger_status =
			$this->normalize_status(
				$trigger_status
			);

		$trigger_source =
			sanitize_key(
				$trigger_source
			);

		if (
			MetaTrackingSettings::PURCHASE_STRATEGY_ADVANCED ===
			$strategy
		) {

			/*
			 * Advanced Purchase must come from a selected
			 * status. Manual/payment-complete hooks never
			 * bypass this rule.
			 */
			if (
				'' === $trigger_status ||
				! MetaTrackingSettings::is_purchase_trigger_status(
					$trigger_status
				)
			) {
				return;
			}
		} else {

			/*
			 * Basic payment_complete/manual_processed are
			 * authoritative successful-order signals.
			 *
			 * Status fallback calls still require a normal
			 * eligible Purchase state.
			 */
			if (
				! in_array(
					$trigger_source,
					array(
						'payment_complete',
						'manual_processed',
					),
					true
				) &&
				! $eligibility->is_purchase_state_eligible(
					$order
				)
			) {
				return;
			}
		}

		$allowed =
			(bool) apply_filters(
				'eilmo_cf/meta_tracking/allowed',
				true,
				'Purchase',
				$order
			);

		if ( ! $allowed ) {
			return;
		}

		$allowed_order =
			(bool) apply_filters(
				'eilmo_cf/meta_tracking/track_order',
				true,
				$order
			);

		if ( ! $allowed_order ) {
			return;
		}

		$event_id =
			$this->get_purchase_event_id(
				$order
			);

		if ( '' === $event_id ) {
			return;
		}

		$lock_key =
			$this->get_purchase_lock_key(
				$order,
				$event_id
			);

		if (
			false !==
				get_transient(
					$lock_key
				)
		) {
			return;
		}

		set_transient(
			$lock_key,
			'processing',
			self::PURCHASE_LOCK_TTL
		);

		/*
		 * Eilmo manual checkout is still inside the original
		 * customer request, so this is the last safe opportunity
		 * to persist fbp/fbc if the custom order pipeline did not
		 * fire the native WooCommerce order-created hook.
		 *
		 * Advanced status tracking deliberately never recaptures
		 * here because its trigger may run from wp-admin or a
		 * fulfillment process days later.
		 */
		if (
			MetaTrackingSettings::PURCHASE_STRATEGY_BASIC ===
				$strategy &&
			'manual_processed' === $trigger_source
		) {
			$this->capture_order_context(
				$order
			);
		}

		$builder =
			new MetaEventBuilder();

		$event =
			$builder->build_purchase(
				$order,
				$event_id
			);

		if ( empty( $event ) ) {
			delete_transient(
				$lock_key
			);

			$this->save_error(
				$order,
				new WP_Error(
					'meta_purchase_build_failed',
					__(
						'Meta Purchase event could not be built.',
						'eilmo-checkout-flow'
					)
				)
			);

			return;
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

			$this->save_error(
				$order,
				$response
			);

			/**
			 * Fires when Meta Purchase sending fails.
			 *
			 * @param WP_Error $response API error.
			 * @param WC_Order $order    Order.
			 * @param string   $event_id Event ID.
			 */
			do_action(
				'eilmo_cf/meta_tracking/purchase_failed',
				$response,
				$order,
				$event_id
			);

			return;
		}

		$stored_response =
			array(
				'events_received' =>
					absint(
						$response['events_received'] ??
							0
					),

				'fbtrace_id' =>
					sanitize_text_field(
						(string) (
							$response['fbtrace_id'] ??
								''
						)
					),
			);

		$order->update_meta_data(
			self::META_PURCHASE_SENT,
			'yes'
		);

		$order->update_meta_data(
			self::META_PURCHASE_SENT_AT,
			current_time(
				'mysql',
				true
			)
		);

		$order->update_meta_data(
			self::META_PURCHASE_STRATEGY,
			$strategy
		);

		$order->update_meta_data(
			self::META_PURCHASE_TRIGGER_STATUS,
			'' !== $trigger_status
				? $trigger_status
				: $current_status
		);

		$order->update_meta_data(
			self::META_PURCHASE_RESPONSE,
			wp_json_encode(
				$stored_response
			)
		);

		$order->delete_meta_data(
			self::META_PURCHASE_LAST_ERROR
		);

		$order->delete_meta_data(
			self::META_PURCHASE_LAST_ERROR_AT
		);

		$order->save();

		delete_transient(
			$lock_key
		);

		/**
		 * Fires after Meta Purchase is accepted.
		 *
		 * @param array<string,mixed> $response API response.
		 * @param WC_Order            $order    Order.
		 * @param string              $event_id Event ID.
		 */
		do_action(
			'eilmo_cf/meta_tracking/purchase_sent',
			$response,
			$order,
			$event_id
		);
	}

	/**
	 * Get or create the stable Purchase event ID.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_purchase_event_id(
		WC_Order $order
	): string {

		$event_id =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_PURCHASE_EVENT_ID,
					true
				)
			);

		if ( '' !== $event_id ) {
			return $event_id;
		}

		$order_id =
			absint(
				$order->get_id()
			);

		if ( $order_id <= 0 ) {
			return '';
		}

		$event_id =
			'eilmo_purchase_' .
			$order_id;

		$order->update_meta_data(
			self::META_PURCHASE_EVENT_ID,
			$event_id
		);

		$order->save();

		return $event_id;
	}

	/**
	 * Get in-flight Purchase lock key.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $event_id Event ID.
	 *
	 * @return string
	 */
	private function get_purchase_lock_key(
		WC_Order $order,
		string $event_id
	): string {

		return 'eilmo_cf_meta_purchase_' .
			md5(
				(string) $order->get_id() .
					'|' .
					$event_id
			);
	}

	/**
	 * Determine whether a state can never represent a Purchase.
	 *
	 * These states are blocked even if accidentally selected
	 * in Advanced settings.
	 *
	 * @param string $status Order status.
	 *
	 * @return bool
	 */
	private function is_non_purchase_status(
		string $status
	): bool {

		return in_array(
			$this->normalize_status(
				$status
			),
			array(
				'cancelled',
				'failed',
				'refunded',
				'trash',
			),
			true
		);
	}

	/**
	 * Normalize WooCommerce order status.
	 *
	 * @param string $status Order status.
	 *
	 * @return string
	 */
	private function normalize_status(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if (
			0 ===
				strpos(
					$status,
					'wc-'
				)
		) {
			$status =
				substr(
					$status,
					3
				);
		}

		return sanitize_key(
			$status
		);
	}

	/**
	 * Save a retryable Meta error.
	 *
	 * @param WC_Order $order Order.
	 * @param WP_Error $error Error.
	 *
	 * @return void
	 */
	private function save_error(
		WC_Order $order,
		WP_Error $error
	): void {

		$payload =
			array(
				'code' =>
					sanitize_key(
						$error->get_error_code()
					),

				'message' =>
					sanitize_text_field(
						$error->get_error_message()
					),
			);

		$order->update_meta_data(
			self::META_PURCHASE_LAST_ERROR,
			wp_json_encode(
				$payload
			)
		);

		$order->update_meta_data(
			self::META_PURCHASE_LAST_ERROR_AT,
			current_time(
				'mysql',
				true
			)
		);

		$order->save();
	}

	/**
	 * Get a tracking cookie.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return string
	 */
	private function get_cookie(
		string $name
	): string {

		if (
			! isset(
				$_COOKIE[ $name ]
			)
		) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				(string) $_COOKIE[ $name ]
			)
		);
	}

	/**
	 * Build fbc from fbclid found in the referring URL.
	 *
	 * @return string
	 */
	private function build_fbc_from_referrer(): string {

		$referrer =
			wp_get_referer();

		if ( ! is_string( $referrer ) ) {
			return '';
		}

		$query =
			wp_parse_url(
				$referrer,
				PHP_URL_QUERY
			);

		if (
			! is_string( $query ) ||
			'' === $query
		) {
			return '';
		}

		$args =
			array();

		parse_str(
			$query,
			$args
		);

		$fbclid =
			sanitize_text_field(
				(string) (
					$args['fbclid'] ??
						''
				)
			);

		if ( '' === $fbclid ) {
			return '';
		}

		return sprintf(
			'fb.1.%1$d.%2$s',
			time() * 1000,
			$fbclid
		);
	}

	/**
	 * Get client IP.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_client_ip(
		WC_Order $order
	): string {

		$ip =
			'';

		if (
			method_exists(
				$order,
				'get_customer_ip_address'
			)
		) {
			$ip =
				sanitize_text_field(
					(string) $order
						->get_customer_ip_address()
				);
		}

		if (
			'' === $ip &&
			class_exists(
				'\WC_Geolocation'
			)
		) {
			$ip =
				sanitize_text_field(
					(string) \WC_Geolocation
						::get_ip_address()
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

	/**
	 * Get client user agent.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_client_user_agent(
		WC_Order $order
	): string {

		if (
			method_exists(
				$order,
				'get_customer_user_agent'
			)
		) {
			$user_agent =
				sanitize_text_field(
					(string) $order
						->get_customer_user_agent()
				);

			if ( '' !== $user_agent ) {
				return $user_agent;
			}
		}

		if (
			! isset(
				$_SERVER['HTTP_USER_AGENT']
			)
		) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				(string) $_SERVER['HTTP_USER_AGENT']
			)
		);
	}

	/**
	 * Get original source/referrer URL.
	 *
	 * @return string
	 */
	private function get_source_url(): string {

		$referrer =
			wp_get_referer();

		if (
			is_string( $referrer ) &&
			'' !== $referrer
		) {
			return esc_url_raw(
				$referrer
			);
		}

		return esc_url_raw(
			home_url( '/' )
		);
	}
}