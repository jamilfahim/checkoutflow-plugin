<?php
/**
 * Meta Tracking settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Meta Tracking settings.
 *
 * The Meta Tracking master switch lives in the main
 * General settings. Detailed Meta configuration is
 * stored separately so secrets never need to live in
 * the main Checkout Flow option.
 */
final class MetaTrackingSettings implements RegistrableInterface {

	/**
	 * Option name.
	 */
	public const OPTION_NAME =
		'eilmo_cf_meta_tracking_settings';

	/**
	 * Option group.
	 */
	public const OPTION_GROUP =
		'eilmo_cf_meta_tracking_settings_group';

	/**
	 * Basic Purchase tracking strategy.
	 *
	 * Purchase is tracked from the normal successful
	 * order / Thank You flow.
	 */
	public const PURCHASE_STRATEGY_BASIC =
		'basic';

	/**
	 * Advanced Purchase tracking strategy.
	 *
	 * Purchase is tracked server-side when an order
	 * reaches one of the selected WooCommerce statuses.
	 */
	public const PURCHASE_STRATEGY_ADVANCED =
		'advanced';

	/**
	 * Order statuses that must never trigger Purchase.
	 *
	 * These are excluded from:
	 *
	 * - Advanced Purchase settings UI.
	 * - Settings sanitization.
	 * - Runtime trigger status resolution.
	 *
	 * Custom statuses remain supported unless explicitly
	 * filtered here.
	 *
	 * @var array<int,string>
	 */
	private const NON_PURCHASE_STATUSES =
		array(
			'cancelled',
			'failed',
			'refunded',
			'trash',
			'checkout-draft',
		);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_init',
			array(
				$this,
				'register_settings',
			)
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type' =>
					'array',

				'description' =>
					__(
						'Eilmo Meta Tracking settings.',
						'eilmo-checkout-flow'
					),

				'sanitize_callback' =>
					array(
						$this,
						'sanitize',
					),

				'default' =>
					self::get_defaults(),

				'show_in_rest' =>
					false,
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {

		return array(
			'features' =>
				array(
					'browser_pixel' =>
						'yes',

					'conversions_api' =>
						'yes',
				),

			'connection' =>
				array(
					'pixel_id' =>
						'',

					'access_token' =>
						'',

					'test_event_code' =>
						'',
				),

			'events' =>
				array(
					'page_view' =>
						'yes',

					'view_content' =>
						'yes',

					'add_to_cart' =>
						'yes',

					'initiate_checkout' =>
						'yes',

					'purchase' =>
						'yes',
				),

			'purchase_tracking' =>
				array(
					'strategy' =>
						self::PURCHASE_STRATEGY_ADVANCED,

					'trigger_statuses' =>
						array(
							'completed',
						),
				),

			'advanced' =>
				array(
					'debug_mode' =>
						'no',
				),
		);
	}

	/**
	 * Get stored settings merged with defaults.
	 *
	 * Numeric arrays such as Purchase trigger statuses
	 * are restored explicitly so array_replace_recursive()
	 * cannot accidentally preserve default numeric items.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {

		$stored =
			get_option(
				self::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$settings =
			array_replace_recursive(
				self::get_defaults(),
				$stored
			);

		if (
			isset(
				$stored['purchase_tracking']['trigger_statuses']
			) &&
			is_array(
				$stored['purchase_tracking']['trigger_statuses']
			)
		) {
			$settings['purchase_tracking']['trigger_statuses'] =
				array_values(
					$stored['purchase_tracking']['trigger_statuses']
				);
		}

		return $settings;
	}

	/**
	 * Determine whether Meta Tracking master switch is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$default_general =
			isset( $defaults['general'] ) &&
			is_array( $defaults['general'] )
				? $defaults['general']
				: array();

		$stored_general =
			isset( $stored['general'] ) &&
			is_array( $stored['general'] )
				? $stored['general']
				: array();

		$general =
			array_replace(
				$default_general,
				$stored_general
			);

		return 'yes' ===
			(
				$general['meta_tracking'] ??
					'no'
			);
	}

	/**
	 * Determine whether a Meta tracking feature is enabled.
	 *
	 * @param string $feature Feature key.
	 *
	 * @return bool
	 */
	public static function feature_is_enabled(
		string $feature
	): bool {

		if ( ! self::is_enabled() ) {
			return false;
		}

		$feature =
			sanitize_key(
				$feature
			);

		if (
			! in_array(
				$feature,
				array(
					'browser_pixel',
					'conversions_api',
				),
				true
			)
		) {
			return false;
		}

		$settings =
			self::get_settings();

		$features =
			isset( $settings['features'] ) &&
			is_array( $settings['features'] )
				? $settings['features']
				: array();

		return 'yes' ===
			(
				$features[ $feature ] ??
					'no'
			);
	}

	/**
	 * Determine whether one event is enabled.
	 *
	 * @param string $event Event key.
	 *
	 * @return bool
	 */
	public static function event_is_enabled(
		string $event
	): bool {

		if ( ! self::is_enabled() ) {
			return false;
		}

		$event =
			sanitize_key(
				$event
			);

		if (
			! in_array(
				$event,
				array(
					'page_view',
					'view_content',
					'add_to_cart',
					'initiate_checkout',
					'purchase',
				),
				true
			)
		) {
			return false;
		}

		$settings =
			self::get_settings();

		$events =
			isset( $settings['events'] ) &&
			is_array( $settings['events'] )
				? $settings['events']
				: array();

		return 'yes' ===
			(
				$events[ $event ] ??
					'no'
			);
	}

	/**
	 * Get Purchase tracking strategy.
	 *
	 * @return string
	 */
	public static function get_purchase_strategy(): string {

		$settings =
			self::get_settings();

		$purchase_tracking =
			isset( $settings['purchase_tracking'] ) &&
			is_array( $settings['purchase_tracking'] )
				? $settings['purchase_tracking']
				: array();

		$strategy =
			sanitize_key(
				(string) (
					$purchase_tracking['strategy'] ??
						self::PURCHASE_STRATEGY_ADVANCED
				)
			);

		if (
			self::PURCHASE_STRATEGY_ADVANCED ===
			$strategy
		) {
			return self::PURCHASE_STRATEGY_ADVANCED;
		}

		return self::PURCHASE_STRATEGY_BASIC;
	}

	/**
	 * Determine whether Basic Purchase tracking is active.
	 *
	 * @return bool
	 */
	public static function uses_basic_purchase_tracking(): bool {

		return self::PURCHASE_STRATEGY_BASIC ===
			self::get_purchase_strategy();
	}

	/**
	 * Determine whether Advanced status-based Purchase
	 * tracking is active.
	 *
	 * @return bool
	 */
	public static function uses_advanced_purchase_tracking(): bool {

		return self::PURCHASE_STRATEGY_ADVANCED ===
			self::get_purchase_strategy();
	}

	/**
	 * Get selected Advanced Purchase trigger statuses.
	 *
	 * Statuses are returned without the WooCommerce
	 * "wc-" prefix.
	 *
	 * Legacy invalid statuses are filtered here even if
	 * they were saved before the current validation rules.
	 *
	 * @return array<int,string>
	 */
	public static function get_purchase_trigger_statuses(): array {

		$settings =
			self::get_settings();

		$purchase_tracking =
			isset( $settings['purchase_tracking'] ) &&
			is_array( $settings['purchase_tracking'] )
				? $settings['purchase_tracking']
				: array();

		$statuses =
			isset(
				$purchase_tracking['trigger_statuses']
			) &&
			is_array(
				$purchase_tracking['trigger_statuses']
			)
				? $purchase_tracking['trigger_statuses']
				: array(
					'completed',
				);

		$allowed_statuses =
			array_keys(
				self::get_order_status_options()
			);

		$normalized =
			array();

		foreach ( $statuses as $status ) {

			$status =
				self::normalize_order_status(
					(string) $status
				);

			if (
				'' === $status ||
				self::is_non_purchase_status(
					$status
				)
			) {
				continue;
			}

			if (
				! empty( $allowed_statuses ) &&
				! in_array(
					$status,
					$allowed_statuses,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$status;
		}

		$normalized =
			array_values(
				array_unique(
					$normalized
				)
			);

		if ( empty( $normalized ) ) {
			return array(
				'completed',
			);
		}

		return $normalized;
	}

	/**
	 * Determine whether one WooCommerce order status is
	 * configured to trigger an Advanced Purchase event.
	 *
	 * @param string $status Order status.
	 *
	 * @return bool
	 */
	public static function is_purchase_trigger_status(
		string $status
	): bool {

		if (
			! self::event_is_enabled(
				'purchase'
			) ||
			! self::uses_advanced_purchase_tracking()
		) {
			return false;
		}

		$status =
			self::normalize_order_status(
				$status
			);

		if (
			'' === $status ||
			self::is_non_purchase_status(
				$status
			)
		) {
			return false;
		}

		return in_array(
			$status,
			self::get_purchase_trigger_statuses(),
			true
		);
	}

	/**
	 * Determine whether Advanced Purchase tracking has
	 * everything required to send server-side Purchase.
	 *
	 * Advanced status tracking cannot rely on a customer
	 * browser because the status may be changed later by
	 * an administrator, fulfillment process or webhook.
	 *
	 * @return bool
	 */
	public static function advanced_purchase_is_ready(): bool {

		if (
			! self::event_is_enabled(
				'purchase'
			) ||
			! self::uses_advanced_purchase_tracking() ||
			! self::feature_is_enabled(
				'conversions_api'
			)
		) {
			return false;
		}

		$settings =
			self::get_settings();

		$connection =
			isset( $settings['connection'] ) &&
			is_array( $settings['connection'] )
				? $settings['connection']
				: array();

		$pixel_id =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$connection['pixel_id'] ??
						''
				)
			);

		if ( ! is_string( $pixel_id ) ) {
			$pixel_id =
				'';
		}

		$access_token =
			trim(
				(string) (
					$connection['access_token'] ??
						''
				)
			);

		return (
			'' !== $pixel_id &&
			'' !== $access_token &&
			! empty(
				self::get_purchase_trigger_statuses()
			)
		);
	}

	/**
	 * Get WooCommerce order statuses available for the
	 * Advanced Purchase trigger selector.
	 *
	 * Registered/custom WooCommerce statuses are included
	 * automatically when WooCommerce exposes them through
	 * wc_get_order_statuses().
	 *
	 * Negative/terminal states that cannot represent a
	 * Purchase are excluded.
	 *
	 * Keys are returned without the "wc-" prefix.
	 *
	 * @return array<string,string>
	 */
	public static function get_order_status_options(): array {

		$options =
			array();

		if (
			function_exists(
				'wc_get_order_statuses'
			)
		) {
			$statuses =
				wc_get_order_statuses();

			if ( is_array( $statuses ) ) {

				foreach (
					$statuses as
						$status_key =>
						$status_label
				) {
					$status =
						self::normalize_order_status(
							(string) $status_key
						);

					if (
						'' === $status ||
						self::is_non_purchase_status(
							$status
						)
					) {
						continue;
					}

					$options[ $status ] =
						wp_strip_all_tags(
							(string) $status_label
						);
				}
			}
		}

		/*
		 * Safe fallback for environments where WooCommerce
		 * status helpers are not available during an early
		 * settings request.
		 *
		 * Only potentially valid Purchase states are exposed.
		 */
		if ( empty( $options ) ) {
			$options =
				array(
					'pending' =>
						__(
							'Pending payment',
							'eilmo-checkout-flow'
						),

					'processing' =>
						__(
							'Processing',
							'eilmo-checkout-flow'
						),

					'on-hold' =>
						__(
							'On hold',
							'eilmo-checkout-flow'
						),

					'completed' =>
						__(
							'Completed',
							'eilmo-checkout-flow'
						),
				);
		}

		/**
		 * Filters statuses available for Advanced Purchase tracking.
		 *
		 * Invalid terminal states are removed again after this
		 * filter so integrations cannot accidentally re-enable them.
		 *
		 * @param array<string,string> $options Status options.
		 */
		$options =
			apply_filters(
				'eilmo_cf/meta_tracking/purchase_status_options',
				$options
			);

		if ( ! is_array( $options ) ) {
			$options =
				array();
		}

		$clean_options =
			array();

		foreach (
			$options as
				$status =>
				$label
		) {
			$status =
				self::normalize_order_status(
					(string) $status
				);

			if (
				'' === $status ||
				self::is_non_purchase_status(
					$status
				)
			) {
				continue;
			}

			$clean_options[ $status ] =
				wp_strip_all_tags(
					(string) $label
				);
		}

		if ( empty( $clean_options ) ) {
			$clean_options['completed'] =
				__(
					'Completed',
					'eilmo-checkout-flow'
				);
		}

		return $clean_options;
	}

	/**
	 * Sanitize complete settings.
	 *
	 * Blank secret fields preserve the previously saved
	 * secret so the admin can save unrelated settings
	 * without re-entering the access token.
	 *
	 * @param mixed $input Raw settings.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize(
		$input
	): array {

		$defaults =
			self::get_defaults();

		$stored =
			get_option(
				self::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		if ( ! is_array( $input ) ) {
			return self::get_settings();
		}

		$features =
			isset( $input['features'] ) &&
			is_array( $input['features'] )
				? $input['features']
				: array();

		$connection =
			isset( $input['connection'] ) &&
			is_array( $input['connection'] )
				? $input['connection']
				: array();

		$events =
			isset( $input['events'] ) &&
			is_array( $input['events'] )
				? $input['events']
				: array();

		$purchase_tracking =
			isset( $input['purchase_tracking'] ) &&
			is_array( $input['purchase_tracking'] )
				? $input['purchase_tracking']
				: array();

		$advanced =
			isset( $input['advanced'] ) &&
			is_array( $input['advanced'] )
				? $input['advanced']
				: array();

		$stored_connection =
			isset( $stored['connection'] ) &&
			is_array( $stored['connection'] )
				? $stored['connection']
				: array();

		$pixel_id =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$connection['pixel_id'] ??
						''
				)
			);

		if ( ! is_string( $pixel_id ) ) {
			$pixel_id =
				'';
		}

		$sanitized_features =
			array(
				'browser_pixel' =>
					$this->sanitize_yes_no(
						$features['browser_pixel'] ??
							$defaults['features']['browser_pixel'],
						(string) $defaults['features']['browser_pixel']
					),

				'conversions_api' =>
					$this->sanitize_yes_no(
						$features['conversions_api'] ??
							$defaults['features']['conversions_api'],
						(string) $defaults['features']['conversions_api']
					),
			);

		$sanitized_events =
			array(
				'page_view' =>
					$this->sanitize_yes_no(
						$events['page_view'] ??
							$defaults['events']['page_view'],
						(string) $defaults['events']['page_view']
					),

				'view_content' =>
					$this->sanitize_yes_no(
						$events['view_content'] ??
							$defaults['events']['view_content'],
						(string) $defaults['events']['view_content']
					),

				'add_to_cart' =>
					$this->sanitize_yes_no(
						$events['add_to_cart'] ??
							$defaults['events']['add_to_cart'],
						(string) $defaults['events']['add_to_cart']
					),

				'initiate_checkout' =>
					$this->sanitize_yes_no(
						$events['initiate_checkout'] ??
							$defaults['events']['initiate_checkout'],
						(string) $defaults['events']['initiate_checkout']
					),

				'purchase' =>
					$this->sanitize_yes_no(
						$events['purchase'] ??
							$defaults['events']['purchase'],
						(string) $defaults['events']['purchase']
					),
			);

		$purchase_strategy =
			$this->sanitize_purchase_strategy(
				$purchase_tracking['strategy'] ??
					$defaults['purchase_tracking']['strategy']
			);

		$purchase_statuses =
			$this->sanitize_purchase_trigger_statuses(
				$purchase_tracking['trigger_statuses'] ??
					array()
			);

		/*
		 * Advanced Purchase tracking must always have at
		 * least one valid status. The Purchase event switch
		 * should be used when Purchase tracking needs to be
		 * completely disabled.
		 */
		if (
			self::PURCHASE_STRATEGY_ADVANCED ===
				$purchase_strategy &&
			empty( $purchase_statuses )
		) {
			$purchase_statuses =
				array(
					'completed',
				);
		}

		return array(
			'features' =>
				$sanitized_features,

			'connection' =>
				array(
					'pixel_id' =>
						$pixel_id,

					'access_token' =>
						$this->sanitize_secret(
							$connection['access_token'] ??
								'',
							$stored_connection['access_token'] ??
								''
						),

					'test_event_code' =>
						sanitize_text_field(
							(string) (
								$connection['test_event_code'] ??
									''
							)
						),
				),

			'events' =>
				$sanitized_events,

			'purchase_tracking' =>
				array(
					'strategy' =>
						$purchase_strategy,

					'trigger_statuses' =>
						$purchase_statuses,
				),

			'advanced' =>
				array(
					'debug_mode' =>
						$this->sanitize_yes_no(
							$advanced['debug_mode'] ??
								$defaults['advanced']['debug_mode'],
							(string) $defaults['advanced']['debug_mode']
						),
				),
		);
	}

	/**
	 * Sanitize Purchase tracking strategy.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function sanitize_purchase_strategy(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		if (
			self::PURCHASE_STRATEGY_ADVANCED ===
			$value
		) {
			return self::PURCHASE_STRATEGY_ADVANCED;
		}

		return self::PURCHASE_STRATEGY_BASIC;
	}

	/**
	 * Sanitize Advanced Purchase trigger statuses.
	 *
	 * Only currently registered WooCommerce statuses are
	 * accepted. Invalid terminal states are always rejected.
	 *
	 * @param mixed $value Status values.
	 *
	 * @return array<int,string>
	 */
	private function sanitize_purchase_trigger_statuses(
		$value
	): array {

		if ( is_string( $value ) ) {
			$value =
				array(
					$value,
				);
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed =
			array_keys(
				self::get_order_status_options()
			);

		$statuses =
			array();

		foreach ( $value as $status ) {

			$status =
				self::normalize_order_status(
					(string) $status
				);

			if (
				'' === $status ||
				self::is_non_purchase_status(
					$status
				)
			) {
				continue;
			}

			if (
				! empty( $allowed ) &&
				! in_array(
					$status,
					$allowed,
					true
				)
			) {
				continue;
			}

			$statuses[] =
				$status;
		}

		return array_values(
			array_unique(
				$statuses
			)
		);
	}

	/**
	 * Determine whether an order status must never be used
	 * as a Meta Purchase conversion trigger.
	 *
	 * @param string $status Order status.
	 *
	 * @return bool
	 */
	private static function is_non_purchase_status(
		string $status
	): bool {

		$status =
			self::normalize_order_status(
				$status
			);

		if ( '' === $status ) {
			return true;
		}

		return in_array(
			$status,
			self::NON_PURCHASE_STATUSES,
			true
		);
	}

	/**
	 * Normalize a WooCommerce order status.
	 *
	 * Both "wc-completed" and "completed" become
	 * "completed".
	 *
	 * @param string $status Order status.
	 *
	 * @return string
	 */
	private static function normalize_order_status(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if (
			0 === strpos(
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
	 * Sanitize yes/no.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_yes_no(
		$value,
		string $fallback = 'no'
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		if ( 'yes' === $value ) {
			return 'yes';
		}

		if ( 'no' === $value ) {
			return 'no';
		}

		return 'yes' === $fallback
			? 'yes'
			: 'no';
	}

	/**
	 * Sanitize a secret while preserving the stored value
	 * when the submitted password field is blank.
	 *
	 * @param mixed $value  Submitted value.
	 * @param mixed $stored Stored value.
	 *
	 * @return string
	 */
	private function sanitize_secret(
		$value,
		$stored
	): string {

		$value =
			trim(
				(string) $value
			);

		if ( '' === $value ) {
			return sanitize_text_field(
				(string) $stored
			);
		}

		return sanitize_text_field(
			$value
		);
	}
}
