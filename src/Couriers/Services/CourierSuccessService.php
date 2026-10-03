<?php
/**
 * Courier success service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use EilmoCheckout\Admin\CourierSettings;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the selected courier-history source and stores permanent/reusable snapshots.
 */
final class CourierSuccessService {

	/**
	 * BD Courier API endpoint.
	 */
	private const BD_COURIER_API_URL =
		'https://api.bdcourier.com/courier-check';

	/**
	 * Async lookup hook.
	 */
	private const LOOKUP_HOOK =
		'eilmo_cf_courier_success_lookup';

	/**
	 * Action Scheduler group.
	 */
	private const ACTION_GROUP =
		'eilmo-checkout-flow';

	/**
	 * Delay before checking a newly created order.
	 *
	 * This gives WooCommerce enough time to finish saving billing data.
	 */
	private const LOOKUP_DELAY = 20;

	/**
	 * BD Courier documented daily request limit.
	 */
	private const DAILY_REQUEST_LIMIT = 150;

	/**
	 * Permanent order meta: whether this order is allowed to use
	 * Courier Success API quota.
	 *
	 * The value is captured once when the order is created:
	 *
	 * yes = Courier Success was enabled at creation time.
	 * no  = Courier Success was disabled at creation time.
	 *
	 * Missing meta is treated as not eligible. This protects historical
	 * orders created before this eligibility system from consuming quota.
	 */
	private const META_ELIGIBLE =
		'_eilmo_cf_courier_success_eligible';

	/**
	 * Permanent order meta: successful courier-history snapshot.
	 */
	private const META_SNAPSHOT =
		'_eilmo_cf_courier_success_snapshot';

	/**
	 * Permanent order meta: phone used for the lookup.
	 */
	private const META_PHONE =
		'_eilmo_cf_courier_success_phone';

	/**
	 * Permanent order meta: successful check timestamp.
	 */
	private const META_CHECKED_AT =
		'_eilmo_cf_courier_success_checked_at';

	/**
	 * Permanent order meta: automatic API attempt timestamp.
	 *
	 * Once this exists, the order will not automatically call the selected history provider again.
	 */
	private const META_ATTEMPTED_AT =
		'_eilmo_cf_courier_success_attempted_at';

	/**
	 * Permanent order meta: last automatic lookup error.
	 */
	private const META_ERROR =
		'_eilmo_cf_courier_success_error';

	/** Permanent order meta: signed live-check decision snapshot. */
	private const META_LIVE_FRAUD =
		'_eilmo_cf_live_fraud_decision';

	/**
	 * Register automatic snapshot hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'woocommerce_new_order',
			array(
				$this,
				'schedule_order_lookup',
			),
			100,
			1
		);

		add_action(
			self::LOOKUP_HOOK,
			array(
				$this,
				'capture_order_by_id',
			),
			10,
			1
		);
	}

	/**
	 * Schedule a background courier-history lookup for a new order.
	 *
	 * The external API is never called directly from the checkout request.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function schedule_order_lookup(
		int $order_id
	): void {

		/* Live mode owns the one allowed lookup during checkout. */
		if ( CourierSettings::live_fraud_is_enabled() ) {
			return;
		}

		if ( $order_id <= 0 ) {
			return;
		}

		$order =
			wc_get_order(
				$order_id
			);

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		/*
		 * Capture eligibility before deciding whether to schedule anything.
		 *
		 * This decision is permanent for the order and is never recalculated
		 * from the current feature setting later.
		 */
		if (
			! $this->capture_order_eligibility(
				$order
			)
		) {
			return;
		}

		if ( $this->order_has_lookup_result( $order ) ) {
			return;
		}

		$args =
			array(
				$order_id,
			);

		$timestamp =
			time() +
			self::LOOKUP_DELAY;

		/*
		 * WooCommerce includes Action Scheduler. Prefer it so the API
		 * lookup is truly background work and does not slow checkout.
		 */
		if (
			function_exists( 'as_schedule_single_action' ) &&
			function_exists( 'as_has_scheduled_action' )
		) {
			$scheduled =
				as_has_scheduled_action(
					self::LOOKUP_HOOK,
					$args,
					self::ACTION_GROUP
				);

			if ( ! $scheduled ) {
				as_schedule_single_action(
					$timestamp,
					self::LOOKUP_HOOK,
					$args,
					self::ACTION_GROUP
				);
			}

			return;
		}

		/*
		 * Fallback for unusual WooCommerce environments where
		 * Action Scheduler is unavailable.
		 */
		if (
			! wp_next_scheduled(
				self::LOOKUP_HOOK,
				$args
			)
		) {
			wp_schedule_single_event(
				$timestamp,
				self::LOOKUP_HOOK,
				$args
			);
		}
	}

	/**
	 * Determine whether this order is allowed to consume Courier Success
	 * API quota.
	 *
	 * Only an explicit permanent "yes" marker is eligible. Missing meta
	 * is deliberately treated as false so historical orders cannot start
	 * consuming API requests after the feature is enabled.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function is_order_eligible(
		WC_Order $order
	): bool {

		return 'yes' ===
			sanitize_key(
				(string) $order->get_meta(
					self::META_ELIGIBLE,
					true
				)
			);
	}

	/**
	 * Permanently capture Courier Success eligibility for a newly
	 * created order.
	 *
	 * Once a valid yes/no marker exists it is never overwritten, so
	 * toggling Courier Success later cannot change historical eligibility.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool True when the order is eligible.
	 */
	private function capture_order_eligibility(
		WC_Order $order
	): bool {

		$stored =
			sanitize_key(
				(string) $order->get_meta(
					self::META_ELIGIBLE,
					true
				)
			);

		if ( 'yes' === $stored ) {
			return true;
		}

		if ( 'no' === $stored ) {
			return false;
		}

		$eligible =
			CourierSettings::is_enabled() &&
			CourierSettings::feature_is_enabled(
				'courier_success'
			);

		$order->update_meta_data(
			self::META_ELIGIBLE,
			$eligible
				? 'yes'
				: 'no'
		);

		$order->save_meta_data();

		return $eligible;
	}

	/**
	 * Run the background lookup for one order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function capture_order_by_id(
		int $order_id
	): void {

		if (
			CourierSettings::live_fraud_is_enabled() ||
			! CourierSettings::is_enabled() ||
			! CourierSettings::feature_is_enabled(
				'courier_success'
			)
		) {
			return;
		}

		$order =
			wc_get_order(
				$order_id
			);

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->capture_order(
			$order
		);
	}

	/**
	 * Fetch and permanently save one order's courier-history snapshot.
	 *
	 * Automatic processing performs at most one external API attempt per order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function capture_order(
		WC_Order $order
	) {

		if ( CourierSettings::live_fraud_is_enabled() ) {
			return array();
		}

		$snapshot = $this->get_snapshot( $order );

		/* Keep the permanent per-order snapshot authoritative when it belongs to
		 * the currently selected provider. A provider switch never mixes old BD
		 * Courier data into a Steadfast check (or the reverse). */
		if ( ! empty( $snapshot ) && $this->snapshot_matches_selected_provider( $snapshot ) ) {
			return $snapshot;
		}

		if ( ! $this->is_order_eligible( $order ) ) {
			return array();
		}

		if (
			! CourierSettings::is_enabled() ||
			! CourierSettings::feature_is_enabled( 'courier_success' )
		) {
			return array();
		}

		/* Existing order-level one-attempt protection is preserved. */
		if ( '' !== (string) $order->get_meta( self::META_ATTEMPTED_AT, true ) ) {
			$error = $this->get_saved_error( $order );

			return is_wp_error( $error )
				? $error
				: new WP_Error(
					'eilmo_cf_courier_already_attempted',
					__( 'Courier history was already checked for this order.', 'eilmo-checkout-flow' )
				);
		}

		$phone = $this->normalize_phone( (string) $order->get_billing_phone() );
		if ( '' === $phone ) {
			return new WP_Error(
				'eilmo_cf_courier_invalid_phone',
				__( 'Please provide a valid Bangladesh mobile number.', 'eilmo-checkout-flow' )
			);
		}

		/* Preserve the existing database-first architecture: reuse a fresh shared
		 * phone snapshot before considering any external courier request. */
		$cached = $this->get_live_cached_snapshot( $phone, false );
		if ( ! empty( $cached ) ) {
			$this->save_manual_snapshot( $order, $cached );
			return $cached;
		}

		$preflight = $this->provider_preflight();
		if ( is_wp_error( $preflight ) ) {
			if ( 'eilmo_cf_courier_daily_limit_reached' === $preflight->get_error_code() ) {
				$this->save_error( $order, $preflight );
			}
			return $preflight;
		}

		/* Mark before HTTP exactly as the existing one-attempt flow did. */
		$attempted_at = time();
		$order->update_meta_data( self::META_ATTEMPTED_AT, $attempted_at );
		$order->update_meta_data( self::META_PHONE, $phone );
		$order->delete_meta_data( self::META_ERROR );
		$order->save_meta_data();

		$result = $this->request_api( $phone );
		if ( is_wp_error( $result ) ) {
			$this->save_error( $order, $result );
			return $result;
		}

		$selected_provider = $this->get_selected_provider();
		$actual_provider = sanitize_key( (string) ( $result['provider'] ?? $selected_provider ) );
		$is_fallback = 'bdcourier' === $selected_provider && 'steadfast' === $actual_provider;
		$source = $is_fallback ? 'bdcourier_fallback_steadfast' : $selected_provider;
		$snapshot = array(
			'version' => 3,
			'source' => $source,
			'phone' => $phone,
			'checked_at' => time(),
			'status' => 'success',
			'cache_status' => 'live',
			'stats' => $result['stats'],
			'response' => $result['response'],
			'provider' => $actual_provider,
			'fallback_from' => sanitize_key( (string) ( $result['fallback_from'] ?? '' ) ),
			'fallback_reason' => sanitize_key( (string) ( $result['fallback_reason'] ?? '' ) ),
		);

		$this->save_manual_snapshot( $order, $snapshot );
		$this->save_live_cached_snapshot( $this->live_cache_key( $phone, $selected_provider ), $snapshot );
		delete_transient( $this->live_cache_key( $phone, $selected_provider ) . '_error' );

		return $snapshot;
	}


	/**
	 * Look up one phone during checkout, with a durable shared phone cache.
	 *
	 * Successful data is retained so an expired record can still be used if the
	 * provider quota is unavailable. Provider failures use only a short cooldown
	 * and never replace or re-date the last successful data.
	 *
	 * @param string $phone Customer phone.
	 * @return array<string,mixed>|WP_Error
	 */
	public function lookup_live_phone( string $phone, bool $force_refresh = false ) {

		$phone = $this->normalize_phone( $phone );
		if ( '' === $phone ) {
			return new WP_Error(
				'eilmo_cf_courier_invalid_phone',
				__( 'Please provide a valid Bangladesh mobile number.', 'eilmo-checkout-flow' )
			);
		}

		$provider = $this->get_selected_provider();
		$key = $this->live_cache_key( $phone, $provider );
		$cached = $this->get_live_cached_snapshot( $phone, true );
		$config = $this->get_live_fraud_config();
		if ( ! $force_refresh && ! empty( $cached ) ) {
			if ( 'fresh' === ( $cached['cache_status'] ?? '' ) ) {
				return $cached;
			}
			if ( 'yes' !== ( $config['refresh_expired_cache'] ?? 'yes' ) ) {
				return $cached;
			}
		}

		$error_cache = get_transient( $key . '_error' );
		if ( ! $force_refresh && is_array( $error_cache ) ) {
			return new WP_Error(
				sanitize_key( (string) ( $error_cache['code'] ?? 'eilmo_cf_courier_api_error' ) ),
				sanitize_text_field( (string) ( $error_cache['message'] ?? __( 'Unable to load courier success history.', 'eilmo-checkout-flow' ) ) )
			);
		}

		$preflight = $this->provider_preflight();
		if ( is_wp_error( $preflight ) ) {
			return $preflight;
		}

		/* Atomic option lock prevents simultaneous requests for one phone/source. */
		$lock_key = $this->acquire_live_lock( $key );
		if ( '' === $lock_key ) {
			return new WP_Error(
				'eilmo_cf_live_fraud_check_in_progress',
				__( 'Phone verification is already in progress. Please wait a moment.', 'eilmo-checkout-flow' )
			);
		}

		$result = $this->request_api( $phone );
		delete_option( $lock_key );

		if ( is_wp_error( $result ) ) {
			set_transient(
				$key . '_error',
				array(
					'code' => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				max( 1, min( 1440, absint( $config['api_error_cooldown_minutes'] ?? 30 ) ) ) * MINUTE_IN_SECONDS
			);
			return $result;
		}

		$actual_provider = sanitize_key( (string) ( $result['provider'] ?? $provider ) );
		$is_fallback = 'bdcourier' === $provider && 'steadfast' === $actual_provider;
		$snapshot = array(
			'version' => 3,
			'source' => $is_fallback ? 'bdcourier_fallback_steadfast_live_checkout' : $provider . '_live_checkout',
			'phone' => $phone,
			'checked_at' => time(),
			'status' => 'success',
			'cache_status' => 'live',
			'stats' => $result['stats'],
			'provider' => $actual_provider,
			'fallback_from' => sanitize_key( (string) ( $result['fallback_from'] ?? '' ) ),
			'fallback_reason' => sanitize_key( (string) ( $result['fallback_reason'] ?? '' ) ),
		);
		$this->save_live_cached_snapshot( $key, $snapshot );
		delete_transient( $key . '_error' );

		return $snapshot;
	}


	/**
	 * Read a successful phone cache without making an API request.
	 *
	 * @param string $phone Customer phone.
	 * @param bool   $include_stale Return expired data too.
	 * @return array<string,mixed>
	 */
	public function get_live_cached_snapshot( string $phone, bool $include_stale = true ): array {

		$phone = $this->normalize_phone( $phone );
		if ( '' === $phone ) {
			return array();
		}

		$provider = $this->get_selected_provider();
		$key = $this->live_cache_key( $phone, $provider );
		$stored = get_option( $key, array() );
		$snapshot = is_array( $stored ) && isset( $stored['snapshot'] ) && is_array( $stored['snapshot'] )
			? $stored['snapshot']
			: array();

		/* One-time compatibility with the previous provider-agnostic BD Courier
		 * cache. It is migrated only while BD Courier is selected, so old data can
		 * never masquerade as a Steadfast response. */
		if ( empty( $snapshot ) && 'bdcourier' === $provider ) {
			$legacy_key = $this->legacy_live_cache_key( $phone );
			$legacy_stored = get_option( $legacy_key, array() );
			if ( is_array( $legacy_stored ) && isset( $legacy_stored['snapshot'] ) && is_array( $legacy_stored['snapshot'] ) ) {
				$snapshot = $legacy_stored['snapshot'];
			}
			if ( empty( $snapshot ) ) {
				$legacy = get_transient( $legacy_key );
				if ( is_array( $legacy ) && isset( $legacy['snapshot'] ) && is_array( $legacy['snapshot'] ) ) {
					$snapshot = $legacy['snapshot'];
				}
			}
			if ( ! empty( $snapshot ) ) {
				$this->save_live_cached_snapshot( $key, $snapshot );
			}
		}

		/* Compatibility with a transient stored under the new provider key. */
		if ( empty( $snapshot ) ) {
			$legacy = get_transient( $key );
			if ( is_array( $legacy ) && isset( $legacy['snapshot'] ) && is_array( $legacy['snapshot'] ) ) {
				$snapshot = $legacy['snapshot'];
				$this->save_live_cached_snapshot( $key, $snapshot );
			}
		}

		if ( empty( $snapshot ) || ! $this->snapshot_matches_selected_provider( $snapshot ) || ! $this->snapshot_has_current_steadfast_profile( $snapshot ) ) {
			return array();
		}

		$checked_at = max( 0, absint( $snapshot['checked_at'] ?? 0 ) );
		$age_seconds = $checked_at > 0 ? max( 0, time() - $checked_at ) : PHP_INT_MAX;
		$cache_days = max( 1, min( 365, absint( $this->get_live_fraud_config()['cache_days'] ?? 30 ) ) );
		$fresh = $age_seconds <= $cache_days * DAY_IN_SECONDS;
		if ( ! $fresh && ! $include_stale ) {
			return array();
		}

		$snapshot['cache_status'] = $fresh ? 'fresh' : 'stale';
		$snapshot['cache_age_days'] = $checked_at > 0 ? round( $age_seconds / DAY_IN_SECONDS, 1 ) : 0;
		$snapshot['stats'] = $this->filter_stats_for_selected_provider(
			isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] ) ? $snapshot['stats'] : array()
		);

		return $snapshot;
	}


	/** Determine whether a saved snapshot is older than the configured TTL. */
	public function snapshot_is_stale( array $snapshot ): bool {

		$checked_at = max( 0, absint( $snapshot['checked_at'] ?? 0 ) );
		$cache_days = max( 1, min( 365, absint( $this->get_live_fraud_config()['cache_days'] ?? 30 ) ) );
		return $checked_at <= 0 || time() - $checked_at > $cache_days * DAY_IN_SECONDS;
	}

	/** Determine whether a snapshot contains actual courier provider data. */
	public function snapshot_has_courier_data( array $snapshot ): bool {

		if ( empty( $snapshot ) || ! $this->snapshot_matches_selected_provider( $snapshot ) || ! $this->snapshot_has_current_steadfast_profile( $snapshot ) ) {
			return false;
		}

		$stats = isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] ) ? $snapshot['stats'] : array();
		$stats = $this->filter_stats_for_selected_provider( $stats );

		foreach ( $stats as $provider ) {
			if ( is_array( $provider ) && $this->is_normalized_provider_stat( $provider ) ) {
				return true;
			}
		}

		return false;
	}


	/** Permanently attach an administrator-requested courier snapshot. */
	public function save_manual_snapshot( WC_Order $order, array $snapshot ): void {

		$phone = $this->normalize_phone( (string) ( $snapshot['phone'] ?? $order->get_billing_phone() ) );
		$checked_at = max( 1, absint( $snapshot['checked_at'] ?? time() ) );
		$order->update_meta_data( self::META_ELIGIBLE, 'yes' );
		$order->update_meta_data( self::META_PHONE, $phone );
		$order->update_meta_data( self::META_SNAPSHOT, $snapshot );
		$order->update_meta_data( self::META_CHECKED_AT, $checked_at );
		$order->update_meta_data( self::META_ATTEMPTED_AT, $checked_at );
		$order->delete_meta_data( self::META_ERROR );
		$order->save_meta_data();
	}

	/** Save a successful phone cache with autoload disabled. */
	private function save_live_cached_snapshot( string $key, array $snapshot ): void {

		$value = array( 'snapshot' => $snapshot );
		if ( false === get_option( $key, false ) ) {
			add_option( $key, $value, '', 'no' );
			return;
		}
		update_option( $key, $value, false );
	}

	/**
	 * Permanently attach the checkout-time result to a newly created order.
	 *
	 * @param WC_Order             $order Order.
	 * @param array<string,mixed> $result Verified token result.
	 * @return void
	 */
	public function attach_live_result( WC_Order $order, array $result ): void {

		$phone = $this->normalize_phone( (string) ( $result['phone'] ?? '' ) );
		$snapshot = isset( $result['snapshot'] ) && is_array( $result['snapshot'] )
			? $result['snapshot']
			: array();
		$evaluation = isset( $result['evaluation'] ) && is_array( $result['evaluation'] )
			? $result['evaluation']
			: array();
		$checked_at = absint( $snapshot['checked_at'] ?? $result['issued_at'] ?? time() );

		$order->update_meta_data( self::META_ELIGIBLE, 'yes' );
		$order->update_meta_data( self::META_PHONE, $phone );
		$order->update_meta_data( self::META_ATTEMPTED_AT, $checked_at );
		$order->update_meta_data( self::META_LIVE_FRAUD, $evaluation );

		$steadfast_profile = $snapshot['stats']['steadfast'] ?? null;
		if ( is_array( $steadfast_profile ) && array_key_exists( 'delivery_ratio', $steadfast_profile ) ) {
			$order->add_order_note( sprintf(
				/* translators: 1: decision, 2: delivered ratio, 3: volume band. */
				__( 'Eilmo Smart Risk Check: %1$s · %2$s%% delivered · customer volume %3$s. Checkout snapshot saved.', 'eilmo-checkout-flow' ),
				ucfirst( sanitize_key( (string) ( $evaluation['action'] ?? 'allow' ) ) ),
				number_format_i18n( (float) ( $steadfast_profile['delivery_ratio'] ?? 0 ), 2 ),
				sanitize_key( (string) ( $steadfast_profile['volume_band'] ?? 'none' ) )
			) );
		} else {
		$order->add_order_note(
			sprintf(
				/* translators: 1: decision, 2: success rate, 3: history records, 4: confidence, 5: risk score, 6: decision source. */
				__( 'Eilmo Smart Risk Check: %1$s · success %2$s%% · %3$d history records · %4$s confidence · risk score %5$d/100 · source %6$s. Checkout snapshot saved.', 'eilmo-checkout-flow' ),
				ucfirst( sanitize_key( (string) ( $evaluation['action'] ?? 'allow' ) ) ),
				number_format_i18n( (float) ( $evaluation['success_rate'] ?? 0 ), 2 ),
				absint( $evaluation['total_orders'] ?? 0 ),
				sanitize_key( (string) ( $evaluation['confidence'] ?? 'none' ) ),
				absint( $evaluation['risk_score'] ?? 0 ),
				sanitize_key( (string) ( $evaluation['decision_source'] ?? 'unknown' ) )
			)
		);
		}

		if ( 'success' === ( $snapshot['status'] ?? '' ) && isset( $snapshot['stats'] ) ) {
			$order->update_meta_data( self::META_SNAPSHOT, $snapshot );
			$order->update_meta_data( self::META_CHECKED_AT, $checked_at );
			$order->delete_meta_data( self::META_ERROR );
		} else {
			$order->delete_meta_data( self::META_SNAPSHOT );
			$order->update_meta_data(
				self::META_ERROR,
				array(
					'code' => sanitize_key( (string) ( $snapshot['error_code'] ?? $evaluation['error_code'] ?? 'live_lookup_unavailable' ) ),
					'message' => __( 'Live checkout courier verification was unavailable.', 'eilmo-checkout-flow' ),
					'time' => $checked_at,
					'provider' => $this->get_selected_provider(),
				)
			);
		}
	}

	/** @return string */
	private function live_cache_key( string $phone, string $provider = '' ): string {

		$provider = sanitize_key( $provider );
		if ( ! in_array( $provider, array( 'steadfast', 'bdcourier' ), true ) ) {
			$provider = $this->get_selected_provider();
		}

		return 'eilmo_cf_live_fraud_' . $provider . '_' . substr( hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) ), 0, 40 );
	}

	/** Previous v2 provider-agnostic cache key, retained only for BD migration. */
	private function legacy_live_cache_key( string $phone ): string {

		return 'eilmo_cf_live_fraud_' . substr( hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) ), 0, 40 );
	}


	/**
	 * Acquire an atomic, short-lived per-phone request lock.
	 *
	 * @param string $cache_key Cache key.
	 * @return string Option name, or empty when another request owns it.
	 */
	private function acquire_live_lock( string $cache_key ): string {

		$lock_key = $cache_key . '_lock';
		$now = time();
		if ( add_option( $lock_key, $now, '', 'no' ) ) {
			return $lock_key;
		}

		$created = absint( get_option( $lock_key, 0 ) );
		if ( $created > 0 && $created < $now - 30 ) {
			delete_option( $lock_key );
			if ( add_option( $lock_key, $now, '', 'no' ) ) {
				return $lock_key;
			}
		}

		return '';
	}

	/**
	 * Get saved stats for the Orders screen.
	 *
	 * IMPORTANT: This method never calls an external courier API.
	 * It reads only saved WordPress/WooCommerce database state: the order
	 * snapshot/error plus the durable per-phone/provider cache.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,array<string,mixed>>|WP_Error
	 */
	public function get_saved_stats(
		WC_Order $order
	) {

		/*
		 * Read the currently selected provider from the database only.
		 *
		 * The order-level snapshot contains only the provider that was selected
		 * when that order was last checked. When the merchant switches between
		 * Steadfast and BD Courier we must not make the already-saved result look
		 * like "No history". The durable per-phone/provider cache is therefore
		 * used as a second database source. This method never calls an external
		 * courier API.
		 */
		$snapshot = $this->get_saved_snapshot_for_selected_provider( $order );

		if (
			isset( $snapshot['stats'] ) &&
			is_array( $snapshot['stats'] )
		) {
			return $this->filter_stats_for_selected_provider( $snapshot['stats'] );
		}

		/* Orders without an explicit creation-time eligibility marker never
		 * trigger a historical lookup. */
		if ( ! $this->is_order_eligible( $order ) ) {
			return $this->empty_stats();
		}

		$error = $this->get_saved_error( $order );
		if ( is_wp_error( $error ) && $this->saved_error_matches_selected_provider( $order ) ) {
			return $error;
		}

		return $this->empty_stats();
	}


	/**
	 * Get the saved snapshot for the provider selected right now without ever
	 * calling a courier API.
	 *
	 * Prefer the permanent order snapshot when it belongs to the selected
	 * provider. If the merchant has switched providers, fall back to the
	 * durable shared phone/provider cache. The shared cache is intentionally
	 * read with stale records included so previously checked data continues to
	 * display until the merchant explicitly chooses Refresh Courier Data.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string,mixed>
	 */
	public function get_saved_snapshot_for_selected_provider( WC_Order $order ): array {

		$order_snapshot = $this->get_snapshot( $order );
		if ( ! empty( $order_snapshot ) && $this->snapshot_matches_selected_provider( $order_snapshot ) && $this->snapshot_has_current_steadfast_profile( $order_snapshot ) ) {
			return $order_snapshot;
		}

		$phone = $this->normalize_phone( (string) $order->get_billing_phone() );
		if ( '' === $phone ) {
			return array();
		}

		/* Database read only. get_live_cached_snapshot() never performs HTTP. */
		return $this->get_live_cached_snapshot( $phone, true );
	}


	/**
	 * Get the complete saved order snapshot.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	public function get_snapshot(
		WC_Order $order
	): array {

		$snapshot =
			$order->get_meta(
				self::META_SNAPSHOT,
				true
			);

		return is_array( $snapshot )
			? $snapshot
			: array();
	}

	/**
	 * Normalize a Bangladesh mobile number to 01XXXXXXXXX.
	 *
	 * @param string $phone Phone number.
	 *
	 * @return string
	 */
	public function normalize_phone(
		string $phone
	): string {

		$digits =
			preg_replace(
				'/\D+/',
				'',
				$phone
			);

		if ( ! is_string( $digits ) ) {
			return '';
		}

		if (
			13 === strlen( $digits ) &&
			0 === strpos( $digits, '880' )
		) {
			$digits =
				'0' .
				substr(
					$digits,
					3
				);
		}

		if (
			10 === strlen( $digits ) &&
			'1' === substr( $digits, 0, 1 )
		) {
			$digits =
				'0' . $digits;
		}

		return 1 === preg_match(
			'/^01[3-9][0-9]{8}$/',
			$digits
		)
			? $digits
			: '';
	}

	/**
	 * Perform one request using the currently selected external history source.
	 *
	 * @param string $phone Normalized phone.
	 * @return array{stats:array<string,array<string,mixed>>,response:array<string,mixed>}|WP_Error
	 */
	private function request_api( string $phone ) {

		$selected = $this->get_selected_provider();

		if ( 'steadfast' === $selected ) {
			$preflight = $this->provider_preflight_for( 'steadfast' );
			if ( is_wp_error( $preflight ) ) {
				return $preflight;
			}

			$result = $this->request_steadfast_api( $phone );
			if ( ! is_wp_error( $result ) ) {
				$result['provider'] = 'steadfast';
			}

			return $result;
		}

		$bd_preflight = $this->provider_preflight_for( 'bdcourier' );
		if ( ! is_wp_error( $bd_preflight ) ) {
			$config = $this->get_ratio_config();
			$api_key = trim( (string) ( $config['api_key'] ?? '' ) );
			$this->increment_daily_request_count();
			$result = $this->request_bd_courier_api( $phone, $api_key );
			if ( ! is_wp_error( $result ) ) {
				$result['provider'] = 'bdcourier';
				return $result;
			}
			$bd_error = $result;
		} else {
			$bd_error = $bd_preflight;
		}

		if ( ! $this->bdcourier_fallback_to_steadfast_enabled() ) {
			return $bd_error;
		}

		$steadfast_preflight = $this->provider_preflight_for( 'steadfast' );
		if ( is_wp_error( $steadfast_preflight ) ) {
			return $bd_error;
		}

		$fallback = $this->request_steadfast_api( $phone );
		if ( is_wp_error( $fallback ) ) {
			return $fallback;
		}

		$fallback['provider'] = 'steadfast';
		$fallback['fallback_from'] = 'bdcourier';
		$fallback['fallback_reason'] = sanitize_key( (string) $bd_error->get_error_code() );

		return $fallback;
	}

	/**
	 * Call Steadfast's fraud-check endpoint using the already configured
	 * Steadfast API Key + Secret Key.
	 *
	 * @param string $phone Normalized phone.
	 * @return array{stats:array<string,array<string,mixed>>,response:array<string,mixed>}|WP_Error
	 */
	private function request_steadfast_api( string $phone ) {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['steadfast'] ) && is_array( $settings['steadfast'] )
			? $settings['steadfast']
			: array();
		$api_key = trim( (string) ( $config['api_key'] ?? '' ) );
		$secret_key = trim( (string) ( $config['secret_key'] ?? '' ) );
		$base_url = untrailingslashit( esc_url_raw( (string) ( $config['base_url'] ?? 'https://portal.packzy.com/api/v1' ) ) );
		if ( '' === $base_url ) {
			$base_url = 'https://portal.packzy.com/api/v1';
		}

		$response = wp_safe_remote_get(
			$base_url . '/fraud_check/score/' . rawurlencode( $phone ),
			array(
				'timeout' => 20,
				'redirection' => 2,
				'headers' => array(
					'Api-Key' => $api_key,
					'Secret-Key' => $secret_key,
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'eilmo_cf_courier_api_request_failed', $response->get_error_message() );
		}

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( 429 === $status_code ) {
			return new WP_Error( 'eilmo_cf_courier_rate_limited', __( 'Steadfast rate limit reached. Please try again later.', 'eilmo-checkout-flow' ) );
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'eilmo_cf_courier_invalid_response',
				__( 'Steadfast returned an invalid fraud-check response.', 'eilmo-checkout-flow' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'eilmo_cf_courier_api_error',
				$this->get_response_message( $decoded, __( 'Unable to load Steadfast delivery history.', 'eilmo-checkout-flow' ) )
			);
		}

		$data = isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : $decoded;
		if ( 429 === absint( $data['status'] ?? 0 ) ) {
			return new WP_Error( 'eilmo_cf_courier_rate_limited', __( 'Steadfast rate limit reached. Please try again later.', 'eilmo-checkout-flow' ) );
		}
		if ( ! isset( $data['delivery_ratio'], $data['cancellation_ratio'], $data['volume_band'] ) || ! is_numeric( $data['delivery_ratio'] ) || ! is_numeric( $data['cancellation_ratio'] ) || ! in_array( sanitize_key( (string) $data['volume_band'] ), array( 'none', 'low', 'medium', 'high', 'very_high' ), true ) ) {
			return new WP_Error( 'eilmo_cf_courier_invalid_response', __( 'Steadfast returned an invalid score response.', 'eilmo-checkout-flow' ) );
		}

		return array(
			'stats' => array(
				'steadfast' => SteadfastProfileService::normalize( $decoded ),
			),
			'response' => $decoded,
		);
	}

	/**
	 * Perform one official BD Courier API request.
	 *
	 * @param string $phone   Normalized phone.
	 * @param string $api_key BD Courier API key.
	 *
	 * @return array{stats:array<string,array<string,mixed>>,response:array<string,mixed>}|WP_Error
	 */
	private function request_bd_courier_api(
		string $phone,
		string $api_key
	) {

		$response =
			wp_safe_remote_post(
				self::BD_COURIER_API_URL,
				array(
					'timeout' =>
						20,

					'redirection' =>
						2,

					'headers' =>
						array(
							'Accept' =>
								'application/json',

							'Content-Type' =>
								'application/json',

							'Authorization' =>
								'Bearer ' . $api_key,
						),

					'body' =>
						wp_json_encode(
							array(
								'phone' =>
									$phone,
							),
							JSON_UNESCAPED_UNICODE |
							JSON_UNESCAPED_SLASHES
						),

					'data_format' =>
						'body',
				)
			);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'eilmo_cf_courier_api_request_failed',
				$response->get_error_message()
			);
		}

		$status_code =
			absint(
				wp_remote_retrieve_response_code(
					$response
				)
			);

		$body =
			(string) wp_remote_retrieve_body(
				$response
			);

		$decoded =
			json_decode(
				$body,
				true
			);

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'eilmo_cf_courier_invalid_response',
				__(
					'BD Courier returned an invalid response.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			$status_code < 200 ||
			$status_code >= 300
		) {
			return new WP_Error(
				'eilmo_cf_courier_api_error',
				$this->get_response_message(
					$decoded,
					__(
						'Unable to load courier success history.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		$response_status =
			sanitize_key(
				(string) (
					$decoded['status'] ??
						''
				)
			);

		if (
			(
				'' !== $response_status &&
				'success' !== $response_status
			) ||
			(
				isset( $decoded['success'] ) &&
				false === $decoded['success']
			)
		) {
			return new WP_Error(
				'eilmo_cf_courier_api_error',
				$this->get_response_message(
					$decoded,
					__(
						'Unable to load courier success history.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		$data =
			isset( $decoded['data'] ) &&
			is_array( $decoded['data'] )
				? $decoded['data']
				: array();

		$stats = $this->extract_bd_courier_stats( $data );

		if ( empty( $stats ) ) {
			return new WP_Error(
				'eilmo_cf_courier_provider_data_missing',
				__(
					'BD Courier did not return usable courier history.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'stats' => $stats,
			'response' => $decoded,
		);
	}


	/**
	 * Extract every courier provider history returned by BD Courier.
	 *
	 * The API has used both associative provider objects (for example
	 * data.steadfast) and list/nested payloads. We therefore discover provider
	 * objects by their parcel-stat fields instead of hard-coding courier names.
	 * Generic aggregate/summary objects are intentionally skipped to prevent
	 * double-counting when individual courier rows are also present.
	 *
	 * @param array<string,mixed> $data BD Courier data payload.
	 * @return array<string,array<string,mixed>>
	 */
	private function extract_bd_courier_stats( array $data ): array {

		$stats = array();
		$this->walk_bd_courier_data( $data, '', $stats );

		return $stats;
	}

	/**
	 * Recursively collect one BD Courier provider payload.
	 *
	 * @param array<string|int,mixed>                 $node  Current node.
	 * @param string                                  $hint  Provider-name hint.
	 * @param array<string,array<string,mixed>>       $stats Collected stats.
	 * @return void
	 */
	private function walk_bd_courier_data( array $node, string $hint, array &$stats ): void {

		$is_provider =
			array_key_exists( 'total_parcel', $node ) ||
			array_key_exists( 'success_parcel', $node ) ||
			array_key_exists( 'cancelled_parcel', $node ) ||
			array_key_exists( 'success_ratio', $node );

		if ( $is_provider ) {
			$explicit_name = (string) (
				$node['courier_name'] ??
				$node['courier'] ??
				$node['provider_name'] ??
				$node['provider'] ??
				''
			);
			$hint_key = sanitize_key( $hint );
			$generic_hint = '' === $hint_key || in_array( $hint_key, array( 'couriers', 'providers', 'items', 'results', 'data' ), true );
			if ( '' === trim( $explicit_name ) && $generic_hint && isset( $node['name'] ) ) {
				$explicit_name = (string) $node['name'];
			}
			$raw_name = '' !== trim( $explicit_name ) ? $explicit_name : $hint;
			$key = sanitize_key( $raw_name );

			if ( '' === $key ) {
				return;
			}

			/* Do not treat an API-wide total/summary as another courier. */
			if (
				'' === trim( $explicit_name ) &&
				in_array( $key, array( 'summary', 'total', 'overall', 'combined', 'aggregate', 'all', 'data' ), true )
			) {
				return;
			}

			$label = '' !== trim( $explicit_name )
				? sanitize_text_field( $explicit_name )
				: $this->provider_label_from_key( $key );
			$stats[ $key ] = $this->normalize_provider( $node, $label );
			return;
		}

		foreach ( $node as $key => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$child_hint = is_string( $key ) ? $key : $hint;
			$this->walk_bd_courier_data( $child, $child_hint, $stats );
		}
	}

	/** Build a readable provider label when the API only returns an object key. */
	private function provider_label_from_key( string $key ): string {

		$key = sanitize_key( $key );
		$known = array(
			'steadfast' => 'Steadfast',
			'pathao' => 'Pathao',
			'redx' => 'REDX',
			'paperfly' => 'Paperfly',
			'ecourier' => 'eCourier',
		);

		if ( isset( $known[ $key ] ) ) {
			return $known[ $key ];
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
	}

	/**
	 * Normalize one documented BD Courier provider payload.
	 *
	 * @param array<string,mixed> $data Provider data.
	 *
	 * @return array<string,mixed>
	 */
	private function normalize_provider(
		array $data,
		string $label = ''
	): array {

		if ( empty( $data ) ) {
			return $this->empty_provider();
		}

		$label = sanitize_text_field( $label );

		$total =
			$this->number(
				$data['total_parcel'] ??
					0
			);

		$success =
			$this->number(
				$data['success_parcel'] ??
					0
			);

		$cancel =
			$this->number(
				$data['cancelled_parcel'] ??
					0
			);

		$ratio =
			$this->number(
				$data['success_ratio'] ??
					0
			);

		if (
			$total <= 0 &&
			(
				$success > 0 ||
				$cancel > 0
			)
		) {
			$total =
				$success +
				$cancel;
		}

		if (
			$ratio <= 0 &&
			$total > 0 &&
			$success > 0
		) {
			$ratio =
				(
					$success /
					$total
				) *
				100;
		}

		$total =
			max(
				0,
				(int) round( $total )
			);

		$success =
			max(
				0,
				(int) round( $success )
			);

		$cancel =
			max(
				0,
				(int) round( $cancel )
			);

		$ratio =
			max(
				0.0,
				min(
					100.0,
					round(
						$ratio,
						2
					)
				)
			);

		$rate_only = $this->bd_courier_rate_only_fields( $data, 'success_ratio' );

		return array_merge( array(
			'label' => $label,

			'available' =>
				$total > 0,

			'total' =>
				$total,

			'success' =>
				$success,

			'cancel' =>
				$cancel,

			'ratio' =>
				$ratio,

			'state' =>
				$total > 0 ? 'available' : 'no_history',
		), $rate_only );
	}

	/** Preserve BD Courier's rate-only volume data without inventing parcel counts. */
	private function bd_courier_rate_only_fields( array $provider, string $ratio_key ): array {
		if ( ! filter_var( $provider['rate_only'] ?? false, FILTER_VALIDATE_BOOLEAN ) || ! is_numeric( $provider[ $ratio_key ] ?? null ) ) {
			return array();
		}

		$range = sanitize_text_field( (string) ( $provider['parcel_range'] ?? '' ) );
		$band = sanitize_key( (string) ( $provider['volume_band'] ?? '' ) );
		if ( ! preg_match( '/^\d{1,6}\s*(?:[-–]\s*\d{1,6}|\+)$/u', $range ) || ! in_array( $band, array( 'low', 'medium', 'high', 'very_high' ), true ) ) {
			return array();
		}

		return array(
			'rate_only' => true,
			'parcel_range' => $range,
			'volume_band' => $band,
		);
	}

	/**
	 * Empty provider stats.
	 *
	 * @return array<string,mixed>
	 */
	private function empty_provider( string $state = 'no_history' ): array {

		$state = sanitize_key( $state );
		if ( ! in_array( $state, array( 'no_history', 'not_selected' ), true ) ) {
			$state = 'no_history';
		}

		return array(
			'label' => '',
			'available' => false,
			'total' => 0,
			'success' => 0,
			'cancel' => 0,
			'ratio' => 0.0,
			'state' => $state,
		);
	}


	/**
	 * Empty stats for an order with no saved snapshot.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function empty_stats(): array {

		if ( 'steadfast' === $this->get_selected_provider() ) {
			$steadfast = $this->empty_provider();
			$steadfast['label'] = 'Steadfast';
			return array( 'steadfast' => $steadfast );
		}

		return array();
	}


	/**
	 * Convert an API numeric value to float.
	 *
	 * @param mixed $value Value.
	 *
	 * @return float
	 */
	private function number(
		$value
	): float {

		if ( is_string( $value ) ) {
			$value =
				str_replace(
					array(
						'%',
						',',
					),
					'',
					trim( $value )
				);
		}

		return is_numeric( $value )
			? (float) $value
			: 0.0;
	}

	/** Get the selected external courier-history provider. */
	private function get_selected_provider(): string {

		return CourierSettings::get_success_provider();
	}

	/** Validate credentials/quota without changing the existing request flow. */
	private function provider_preflight() {

		$provider = $this->get_selected_provider();
		$preflight = $this->provider_preflight_for( $provider );

		if ( ! is_wp_error( $preflight ) || 'bdcourier' !== $provider || ! $this->bdcourier_fallback_to_steadfast_enabled() ) {
			return $preflight;
		}

		/* Treat a configured Steadfast fallback as an available provider chain. */
		$fallback = $this->provider_preflight_for( 'steadfast' );
		return is_wp_error( $fallback ) ? $preflight : true;
	}

	/** Validate one concrete provider without applying fallback rules. */
	private function provider_preflight_for( string $provider ) {

		if ( 'steadfast' === $provider ) {
			$settings = CourierSettings::get_settings();
			$config = isset( $settings['steadfast'] ) && is_array( $settings['steadfast'] )
				? $settings['steadfast']
				: array();
			if ( '' === trim( (string) ( $config['api_key'] ?? '' ) ) || '' === trim( (string) ( $config['secret_key'] ?? '' ) ) ) {
				return new WP_Error(
					'eilmo_cf_courier_api_key_missing',
					__( 'Steadfast API Key and Secret Key are not configured.', 'eilmo-checkout-flow' )
				);
			}
			return true;
		}

		$config = $this->get_ratio_config();
		if ( '' === trim( (string) ( $config['api_key'] ?? '' ) ) ) {
			return new WP_Error(
				'eilmo_cf_courier_api_key_missing',
				__( 'BD Courier API token is not configured.', 'eilmo-checkout-flow' )
			);
		}
		if ( ! $this->can_make_daily_request() ) {
			return new WP_Error(
				'eilmo_cf_courier_daily_limit_reached',
				__( 'BD Courier daily API request limit has been reached.', 'eilmo-checkout-flow' )
			);
		}

		return true;
	}

	/** Whether BD Courier may transparently retry through Steadfast. */
	private function bdcourier_fallback_to_steadfast_enabled(): bool {

		if ( 'bdcourier' !== $this->get_selected_provider() ) {
			return false;
		}

		$config = $this->get_ratio_config();
		return 'yes' === ( $config['fallback_to_steadfast'] ?? 'yes' );
	}

	/** Whether a saved snapshot belongs to the source selected right now. */
	private function snapshot_matches_selected_provider( array $snapshot ): bool {

		if ( empty( $snapshot ) ) {
			return false;
		}
		$source = sanitize_key( (string) ( $snapshot['source'] ?? '' ) );
		$provider = $this->get_selected_provider();
		if ( 'steadfast' === $provider ) {
			return 0 === strpos( $source, 'steadfast' );
		}

		return 0 === strpos( $source, 'bdcourier' );
	}

	/** Normalize provider rows according to the selected source. */
	private function filter_stats_for_selected_provider( array $stats ): array {

		if ( 'steadfast' === $this->get_selected_provider() ) {
			$steadfast = isset( $stats['steadfast'] ) && is_array( $stats['steadfast'] )
				? $stats['steadfast']
				: $this->empty_provider();
			$steadfast['label'] = 'Steadfast';

			return array( 'steadfast' => $this->sanitize_normalized_provider_stat( $steadfast, 'Steadfast' ) );
		}

		$output = array();
		foreach ( $stats as $key => $provider ) {
			if ( ! is_string( $key ) || ! is_array( $provider ) || ! $this->is_normalized_provider_stat( $provider ) ) {
				continue;
			}

			$provider_key = sanitize_key( $key );
			if ( '' === $provider_key || 'website' === $provider_key ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $provider['label'] ?? '' ) );
			if ( '' === $label ) {
				$label = $this->provider_label_from_key( $provider_key );
			}
			$output[ $provider_key ] = $this->sanitize_normalized_provider_stat( $provider, $label );
		}

		return $output;
	}

	/** Whether an array is one normalized courier-provider statistics row. */
	private function is_normalized_provider_stat( array $provider ): bool {
		if ( 'steadfast' === ( $provider['provider'] ?? '' ) && array_key_exists( 'delivery_ratio', $provider ) ) {
			return true;
		}

		return
			array_key_exists( 'total', $provider ) ||
			array_key_exists( 'success', $provider ) ||
			array_key_exists( 'cancel', $provider ) ||
			array_key_exists( 'ratio', $provider ) ||
			array_key_exists( 'available', $provider );
	}

	/** Old Steadfast count snapshots cannot represent the new ratio endpoint. */
	private function snapshot_has_current_steadfast_profile( array $snapshot ): bool {
		if ( 'steadfast' !== $this->get_selected_provider() ) {
			return true;
		}
		$profile = $snapshot['stats']['steadfast'] ?? null;
		return is_array( $profile ) && array_key_exists( 'delivery_ratio', $profile );
	}

	/** @return array<string,mixed> */
	private function sanitize_normalized_provider_stat( array $provider, string $label ): array {
		if ( 'steadfast' === ( $provider['provider'] ?? '' ) && array_key_exists( 'delivery_ratio', $provider ) ) {
			return SteadfastProfileService::normalize( $provider );
		}

		$total = max( 0, absint( $provider['total'] ?? 0 ) );
		$success = max( 0, absint( $provider['success'] ?? 0 ) );
		$cancel = max( 0, absint( $provider['cancel'] ?? 0 ) );
		if ( $total <= 0 && ( $success > 0 || $cancel > 0 ) ) {
			$total = $success + $cancel;
		}
		$ratio = $total > 0
			? round( max( 0, min( 100, ( $success / $total ) * 100 ) ), 2 )
			: max( 0, min( 100, (float) ( $provider['ratio'] ?? 0 ) ) );
		$state = sanitize_key( (string) ( $provider['state'] ?? ( $total > 0 ? 'available' : 'no_history' ) ) );
		if ( ! in_array( $state, array( 'available', 'no_history', 'not_selected' ), true ) ) {
			$state = $total > 0 ? 'available' : 'no_history';
		}

		$rate_only = $this->bd_courier_rate_only_fields( $provider, 'ratio' );

		return array_merge( array(
			'label' => sanitize_text_field( $label ),
			'available' => $total > 0,
			'total' => $total,
			'success' => $success,
			'cancel' => $cancel,
			'ratio' => $ratio,
			'state' => $state,
		), $rate_only );
	}

	/**
	 * Get Courier ratio API configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function get_ratio_config(): array {

		$settings =
			CourierSettings::get_settings();

		return isset( $settings['ratio_api'] ) &&
		is_array( $settings['ratio_api'] )
			? $settings['ratio_api']
			: array();
	}

	/** Get Advanced Live Fraud configuration. */
	private function get_live_fraud_config(): array {

		$settings = CourierSettings::get_settings();
		return isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();
	}

	/**
	 * Determine whether an order already has a successful or attempted lookup.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function order_has_lookup_result(
		WC_Order $order
	): bool {

		if ( ! empty( $this->get_snapshot( $order ) ) ) {
			return true;
		}

		if ( is_wp_error( $this->get_saved_error( $order ) ) ) {
			return true;
		}

		return '' !==
			(string) $order->get_meta(
				self::META_ATTEMPTED_AT,
				true
			);
	}

	/**
	 * Save an API error for display without automatically retrying the order.
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

		$order->update_meta_data(
			self::META_ERROR,
			array(
				'code' =>
					$error->get_error_code(),

				'message' =>
					$error->get_error_message(),

				'time' =>
					time(),

				'provider' =>
					$this->get_selected_provider(),
			)
		);

		$order->save_meta_data();
	}

	/** Whether the saved automatic error belongs to the current source. */
	private function saved_error_matches_selected_provider( WC_Order $order ): bool {

		$error = $order->get_meta( self::META_ERROR, true );
		if ( ! is_array( $error ) ) {
			return false;
		}

		$provider = sanitize_key( (string) ( $error['provider'] ?? '' ) );
		if ( '' === $provider ) {
			/* Errors written before provider selection existed came from BD Courier. */
			$provider = 'bdcourier';
		}

		return $provider === $this->get_selected_provider();
	}

	/**
	 * Read a previously saved automatic lookup error.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return WP_Error|null
	 */
	private function get_saved_error(
		WC_Order $order
	) {

		$error =
			$order->get_meta(
				self::META_ERROR,
				true
			);

		if ( ! is_array( $error ) ) {
			return null;
		}

		$code =
			sanitize_key(
				(string) (
					$error['code'] ??
						''
				)
			);

		$message =
			sanitize_text_field(
				(string) (
					$error['message'] ??
						''
				)
			);

		if (
			'' === $code ||
			'' === $message
		) {
			return null;
		}

		return new WP_Error(
			$code,
			$message
		);
	}

	/**
	 * Check local daily request guard.
	 *
	 * @return bool
	 */
	private function can_make_daily_request(): bool {

		return $this->get_daily_request_count() <
			self::DAILY_REQUEST_LIMIT;
	}

	/**
	 * Get locally counted BD Courier requests for the current day.
	 *
	 * @return int
	 */
	private function get_daily_request_count(): int {

		return max(
			0,
			(int) get_transient(
				$this->get_daily_counter_key()
			)
		);
	}

	/**
	 * Increment local daily API request counter.
	 *
	 * @return void
	 */
	private function increment_daily_request_count(): void {

		$count =
			$this->get_daily_request_count() +
			1;

		set_transient(
			$this->get_daily_counter_key(),
			$count,
			2 * DAY_IN_SECONDS
		);
	}

	/**
	 * Get daily request counter transient key.
	 *
	 * @return string
	 */
	private function get_daily_counter_key(): string {

		return 'eilmo_cf_bdcourier_daily_' .
			wp_date( 'Ymd' );
	}

	/**
	 * Get the best API error message from a response.
	 *
	 * @param array<string,mixed> $response Response.
	 * @param string              $fallback Fallback message.
	 *
	 * @return string
	 */
	private function get_response_message(
		array $response,
		string $fallback
	): string {

		$candidates =
			array(
				$response['message'] ?? null,
				$response['error'] ?? null,
				$response['data']['message'] ?? null,
				$response['data']['error'] ?? null,
			);

		foreach ( $candidates as $candidate ) {
			if (
				is_string( $candidate ) &&
				'' !== trim( $candidate )
			) {
				return sanitize_text_field(
					$candidate
				);
			}
		}

		return $fallback;
	}
}
