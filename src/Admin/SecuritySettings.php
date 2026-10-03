<?php
/**
 * Security settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Checkout Security settings.
 *
 * Security configuration is stored separately from the main
 * Checkout Flow settings because Security has its own dedicated
 * admin screen and runtime services.
 *
 * This class handles:
 * - Security settings registration.
 * - Default Security settings schema.
 * - Legacy General-settings migration/fallbacks.
 * - Checkout access settings.
 * - Individual protection feature settings.
 * - Customer-facing Security messages.
 * - Advanced Security bypass settings.
 */
final class SecuritySettings implements RegistrableInterface {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'eilmo_cf_security_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'eilmo_cf_security_settings_group';

	/**
	 * Legacy/main Checkout Flow option name.
	 *
	 * Security settings previously lived partly inside
	 * the General settings section of this option.
	 *
	 * @var string
	 */
	private const LEGACY_OPTION_NAME = 'eilmo_cf_settings';

	/**
	 * Settings schema version.
	 *
	 * Version 4 consolidates the idempotency
	 * "already processing" customer message into the
	 * Duplicate Order message and removes the redundant
	 * order_processing message setting.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 4;

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
				'maybe_migrate_legacy_settings',
			),
			5
		);

		add_action(
			'admin_init',
			array(
				$this,
				'register_settings',
			)
		);
	}

	/**
	 * Register Security settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __(
					'Checkout Flow security settings.',
					'eilmo-checkout-flow'
				),
				'sanitize_callback' => array(
					$this,
					'sanitize',
				),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Get complete default Security settings.
	 *
	 * All individual protection features default to enabled.
	 * The Security master switch itself defaults to disabled.
	 * Therefore, when Security is enabled for the first time,
	 * all protections are ready unless the admin explicitly
	 * disables a specific feature.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {

		$defaults = array(

			'schema_version' =>
				self::SCHEMA_VERSION,

			/*
			 * Security master switch.
			 *
			 * This becomes the Security source of truth.
			 * Legacy General.checkout_security is imported for
			 * existing installations during migration/fallback.
			 */
			'enabled' => 'no',

			/*
			 * Checkout access.
			 *
			 * These values previously lived in General settings.
			 */
			'access' => array(
				'checkout_access'          => 'everyone',
				'custom_login_url_enabled' => 'no',
				'custom_login_url'         => '',
				'login_button_text'        => __(
					'Log In to Order',
					'eilmo-checkout-flow'
				),
				'after_login'              => 'checkout',
				'after_login_url'          => '',
			),

			/*
			 * Individual Security protections.
			 */
			'protection' => array(

				/*
				 * Order rate limiting.
				 *
				 * Counts newly created successful orders within
				 * the configured time window.
				 */
				'rate_limit' => array(
					'enabled'        => 'yes',
					'maximum_orders' => 3,
					'window_minutes' => 10,
					'block_minutes'  => 60,
				),

				/*
				 * Duplicate-order protection.
				 */
				'duplicate_order' => array(
					'enabled'                  => 'yes',
					'window_minutes'           => 10,
					'compare_customer'         => 'yes',
					'compare_products'         => 'yes',
					'compare_quantities'       => 'yes',
					'compare_total'            => 'yes',
					'allow_different_products' => 'yes',
				),

				/*
				 * Invisible bot-detection field.
				 */
				'honeypot' => array(
					'enabled' => 'yes',
				),

				/*
				 * Checkout security token validation.
				 */
				'security_token' => array(
					'enabled' => 'yes',
				),

				/*
				 * Reject checkout submissions completed
				 * unrealistically fast.
				 */
				'minimum_checkout_time' => array(
					'enabled' => 'yes',
					'seconds' => 3,
				),

				/*
				 * Minimum waiting time after a successful order
				 * before the same customer can place another order.
				 *
				 * Smart identity priority:
				 * logged-in customer ID -> phone -> email -> IP.
				 */
				'order_cooldown' => array(
					'enabled'     => 'yes',
					'duration'    => 2,
					'unit'        => 'minutes',
					'identify_by' => 'smart',
				),

				/*
				 * Customer blacklist.
				 */
				'blacklist' => array(
					'enabled'         => 'yes',
					'normalize_phone' => 'yes',
				),

				/*
				 * Security activity logging.
				 */
				'activity_logging' => array(
					'enabled'          => 'yes',
					'auto_cleanup'     => 'yes',
					'retention_days'   => 30,
					'log_rate_limit'   => 'yes',
					'log_duplicate'    => 'yes',
					'log_bot'          => 'yes',
					'log_cooldown'     => 'yes',
					'log_blacklist'    => 'yes',
				),
			),

			/*
			 * Customer-facing Security messages.
			 *
			 * Supported by the Security runtime:
			 * {remaining_time}
			 */
			'messages' => array(
				'cooldown' => __(
					'You recently placed an order. Please wait {remaining_time} before placing another order.',
					'eilmo-checkout-flow'
				),
				'duplicate_order' => __(
					'A similar order was recently submitted or is still being processed. Please wait before placing the same order again.',
					'eilmo-checkout-flow'
				),
				'rate_limit' => __(
					'Too many orders were placed within the allowed time window. Please try again in {remaining_time}.',
					'eilmo-checkout-flow'
				),
				'bot_protection' => __(
					'We could not verify this checkout request. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				),
				'blacklist' => __(
					'This checkout request cannot be processed. Please contact us if you need assistance.',
					'eilmo-checkout-flow'
				),
				'checkout_token_invalid' => __(
					'The checkout request token is invalid. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				),
				'checkout_request_failed' => __(
					'The checkout request could not be finalized. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				),
			),

			/*
			 * Advanced bypass settings.
			 *
			 * Useful while administrators/shop managers test
			 * the store repeatedly from the same browser/IP.
			 */
			'advanced' => array(
				'bypass_administrators' => 'yes',
				'bypass_shop_managers'  => 'yes',
			),
		);

		/**
		 * Filters default Security settings.
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		$defaults = apply_filters(
			'eilmo_cf/security_settings_defaults',
			$defaults
		);

		return is_array( $defaults )
			? $defaults
			: array();
	}

	/**
	 * Get complete saved Security settings.
	 *
	 * Stored settings are recursively merged with defaults.
	 * Legacy General values are used only when the equivalent
	 * Security setting has not yet been saved.
	 *
	 * This makes migration safe for both the Eilmo custom
	 * Checkout Flow and native/default WooCommerce checkout
	 * while runtime services are switched to this source.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {

		$stored = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_replace_recursive(
			self::get_defaults(),
			$stored
		);

		if (
			isset( $settings['messages'] ) &&
			is_array( $settings['messages'] )
		) {
			unset(
				$settings['messages']['order_processing']
			);
		}

		return self::apply_legacy_fallbacks(
			$settings,
			$stored
		);
	}

	/**
	 * Whether Checkout Security is enabled.
	 *
	 * Runtime services for both native WooCommerce checkout
	 * and Eilmo Checkout Flow should use this method.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {

		$settings = self::get_settings();

		return 'yes' === (
			$settings['enabled'] ??
			'no'
		);
	}

	/**
	 * Whether a specific Security protection is enabled.
	 *
	 * The master Security switch must also be enabled.
	 *
	 * @param string $protection Protection key.
	 *
	 * @return bool
	 */
	public static function is_protection_enabled(
		string $protection
	): bool {

		if ( ! self::is_enabled() ) {
			return false;
		}

		$settings = self::get_settings();

		if (
			empty( $settings['protection'] ) ||
			! is_array( $settings['protection'] ) ||
			empty( $settings['protection'][ $protection ] ) ||
			! is_array( $settings['protection'][ $protection ] )
		) {
			return false;
		}

		return 'yes' === (
			$settings['protection'][ $protection ]['enabled'] ??
			'no'
		);
	}

	/**
	 * Get a customer-facing Security / checkout message.
	 *
	 * This can also be used by order-pipeline services that
	 * must remain active independently of the Security master
	 * switch, such as checkout idempotency.
	 *
	 * @param string $key      Message key.
	 * @param string $fallback Fallback message.
	 *
	 * @return string
	 */
	public static function get_message(
		string $key,
		string $fallback = ''
	): string {

		$key =
			sanitize_key(
				$key
			);

		$settings =
			self::get_settings();

		$message =
			isset(
				$settings['messages'][ $key ]
			)
				? sanitize_textarea_field(
					(string) $settings['messages'][ $key ]
				)
				: '';

		if ( '' !== $message ) {
			return $message;
		}

		return sanitize_textarea_field(
			$fallback
		);
	}

	/**
	 * Get configured Order Cooldown duration in seconds.
	 *
	 * @return int
	 */
	public static function get_order_cooldown_seconds(): int {

		$settings = self::get_settings();

		$cooldown = array();

		if (
			isset( $settings['protection']['order_cooldown'] ) &&
			is_array( $settings['protection']['order_cooldown'] )
		) {
			$cooldown =
				$settings['protection']['order_cooldown'];
		}

		$duration = max(
			1,
			absint(
				$cooldown['duration'] ??
				2
			)
		);

		$unit = sanitize_key(
			(string) (
				$cooldown['unit'] ??
				'minutes'
			)
		);

		switch ( $unit ) {
			case 'seconds':
				return $duration;

			case 'hours':
				return $duration * HOUR_IN_SECONDS;

			case 'minutes':
			default:
				return $duration * MINUTE_IN_SECONDS;
		}
	}

	/**
	 * Migrate legacy Security-related General settings.
	 *
	 * Migration runs only when the stored Security schema is
	 * older than the current schema. Existing Security values
	 * always win over legacy General values.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_settings(): void {

		$stored = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$current_version = absint(
			$stored['schema_version'] ??
			0
		);

		if ( $current_version >= self::SCHEMA_VERSION ) {
			return;
		}

		$migrated = array_replace_recursive(
			self::get_defaults(),
			$stored
		);

		$migrated = self::apply_legacy_fallbacks(
			$migrated,
			$stored
		);

		if (
			isset( $migrated['messages'] ) &&
			is_array( $migrated['messages'] )
		) {
			unset(
				$migrated['messages']['order_processing']
			);
		}

		$migrated['schema_version'] =
			self::SCHEMA_VERSION;

		update_option(
			self::OPTION_NAME,
			$migrated
		);
	}

	/**
	 * Sanitize complete Security settings payload.
	 *
	 * Security settings may be saved section-by-section.
	 * Sections that are not submitted preserve their saved values.
	 *
	 * @param mixed $input Raw settings.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize(
		$input
	): array {

		$defaults = self::get_defaults();

		$stored = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/*
		 * Invalid payload must never wipe saved settings.
		 */
		if ( ! is_array( $input ) ) {
			return array_replace_recursive(
				$defaults,
				$stored
			);
		}

		$sanitized = array_replace_recursive(
			$defaults,
			$stored
		);

		$sanitized['schema_version'] =
			self::SCHEMA_VERSION;

		if (
			isset( $sanitized['messages'] ) &&
			is_array( $sanitized['messages'] )
		) {
			unset(
				$sanitized['messages']['order_processing']
			);
		}

		if ( array_key_exists( 'enabled', $input ) ) {
			$sanitized['enabled'] =
				$this->sanitize_yes_no(
					$input['enabled'],
					(string) (
						$sanitized['enabled'] ??
						'no'
					)
				);
		}

		if (
			array_key_exists( 'access', $input ) &&
			is_array( $input['access'] )
		) {
			$sanitized['access'] =
				$this->sanitize_access(
					$input['access'],
					$this->get_array(
						$sanitized,
						'access'
					)
				);
		}

		if (
			array_key_exists( 'protection', $input ) &&
			is_array( $input['protection'] )
		) {
			$sanitized['protection'] =
				$this->sanitize_protection(
					$input['protection'],
					$this->get_array(
						$sanitized,
						'protection'
					)
				);
		}

		if (
			array_key_exists( 'messages', $input ) &&
			is_array( $input['messages'] )
		) {
			$sanitized['messages'] =
				$this->sanitize_messages(
					$input['messages'],
					$this->get_array(
						$sanitized,
						'messages'
					)
				);
		}

		if (
			array_key_exists( 'advanced', $input ) &&
			is_array( $input['advanced'] )
		) {
			$sanitized['advanced'] =
				$this->sanitize_advanced(
					$input['advanced'],
					$this->get_array(
						$sanitized,
						'advanced'
					)
				);
		}

		/**
		 * Filters sanitized Security settings before saving.
		 *
		 * @param array<string, mixed> $sanitized Sanitized settings.
		 * @param array<string, mixed> $input     Raw submitted settings.
		 * @param array<string, mixed> $stored    Previously stored settings.
		 */
		$sanitized = apply_filters(
			'eilmo_cf/security_settings_sanitized',
			$sanitized,
			$input,
			$stored
		);

		return is_array( $sanitized )
			? $sanitized
			: $stored;
	}

	/**
	 * Sanitize Checkout Access settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $fallback Existing/default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_access(
		array $input,
		array $fallback
	): array {

		$checkout_access = sanitize_key(
			(string) (
				$input['checkout_access'] ??
				$fallback['checkout_access'] ??
				'everyone'
			)
		);

		$allowed_access_modes = array(
			'everyone',
			'guest_only',
			'logged_in_only',
		);

		if (
			! in_array(
				$checkout_access,
				$allowed_access_modes,
				true
			)
		) {
			$checkout_access = 'everyone';
		}

		$after_login = sanitize_key(
			(string) (
				$input['after_login'] ??
				$fallback['after_login'] ??
				'checkout'
			)
		);

		if (
			! in_array(
				$after_login,
				array(
					'checkout',
					'custom_url',
				),
				true
			)
		) {
			$after_login = 'checkout';
		}

		$login_button_text = sanitize_text_field(
			(string) (
				$input['login_button_text'] ??
				$fallback['login_button_text'] ??
				__(
					'Log In to Order',
					'eilmo-checkout-flow'
				)
			)
		);

		if ( '' === $login_button_text ) {
			$login_button_text = __(
				'Log In to Order',
				'eilmo-checkout-flow'
			);
		}

		return array(
			'checkout_access' =>
				$checkout_access,

			'custom_login_url_enabled' =>
				$this->sanitize_yes_no(
					$input['custom_login_url_enabled'] ??
						$fallback['custom_login_url_enabled'] ??
						'no',
					(string) (
						$fallback['custom_login_url_enabled'] ??
						'no'
					)
				),

			'custom_login_url' =>
				esc_url_raw(
					trim(
						(string) (
							$input['custom_login_url'] ??
							$fallback['custom_login_url'] ??
							''
						)
					)
				),

			'login_button_text' =>
				$login_button_text,

			'after_login' =>
				$after_login,

			'after_login_url' =>
				esc_url_raw(
					trim(
						(string) (
							$input['after_login_url'] ??
							$fallback['after_login_url'] ??
							''
						)
					)
				),
		);
	}

	/**
	 * Sanitize checkout protection settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $fallback Existing/default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_protection(
		array $input,
		array $fallback
	): array {

		$sanitized = $fallback;

		$sections = array(
			'rate_limit',
			'duplicate_order',
			'honeypot',
			'security_token',
			'minimum_checkout_time',
			'order_cooldown',
			'blacklist',
			'activity_logging',
		);

		foreach ( $sections as $section ) {
			if (
				! array_key_exists( $section, $input ) ||
				! is_array( $input[ $section ] )
			) {
				continue;
			}

			$section_input = $input[ $section ];
			$section_fallback = $this->get_array(
				$fallback,
				$section
			);

			switch ( $section ) {
				case 'rate_limit':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'maximum_orders' =>
							$this->sanitize_positive_integer(
								$section_input['maximum_orders'] ??
									$section_fallback['maximum_orders'] ??
									3,
								(int) (
									$section_fallback['maximum_orders'] ??
									3
								)
							),
						'window_minutes' =>
							$this->sanitize_positive_integer(
								$section_input['window_minutes'] ??
									$section_fallback['window_minutes'] ??
									10,
								(int) (
									$section_fallback['window_minutes'] ??
									10
								)
							),
						'block_minutes' =>
							$this->sanitize_positive_integer(
								$section_input['block_minutes'] ??
									$section_fallback['block_minutes'] ??
									60,
								(int) (
									$section_fallback['block_minutes'] ??
									60
								)
							),
					);
					break;

				case 'duplicate_order':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'window_minutes' =>
							$this->sanitize_positive_integer(
								$section_input['window_minutes'] ??
									$section_fallback['window_minutes'] ??
									10,
								(int) (
									$section_fallback['window_minutes'] ??
									10
								)
							),
						'compare_customer' =>
							$this->sanitize_yes_no(
								$section_input['compare_customer'] ??
									$section_fallback['compare_customer'] ??
									'yes',
								(string) (
									$section_fallback['compare_customer'] ??
									'yes'
								)
							),
						'compare_products' =>
							$this->sanitize_yes_no(
								$section_input['compare_products'] ??
									$section_fallback['compare_products'] ??
									'yes',
								(string) (
									$section_fallback['compare_products'] ??
									'yes'
								)
							),
						'compare_quantities' =>
							$this->sanitize_yes_no(
								$section_input['compare_quantities'] ??
									$section_fallback['compare_quantities'] ??
									'yes',
								(string) (
									$section_fallback['compare_quantities'] ??
									'yes'
								)
							),
						'compare_total' =>
							$this->sanitize_yes_no(
								$section_input['compare_total'] ??
									$section_fallback['compare_total'] ??
									'yes',
								(string) (
									$section_fallback['compare_total'] ??
									'yes'
								)
							),
						'allow_different_products' =>
							$this->sanitize_yes_no(
								$section_input['allow_different_products'] ??
									$section_fallback['allow_different_products'] ??
									'yes',
								(string) (
									$section_fallback['allow_different_products'] ??
									'yes'
								)
							),
					);
					break;

				case 'honeypot':
				case 'security_token':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
					);
					break;

				case 'minimum_checkout_time':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'seconds' =>
							$this->sanitize_positive_integer(
								$section_input['seconds'] ??
									$section_fallback['seconds'] ??
									3,
								(int) (
									$section_fallback['seconds'] ??
									3
								)
							),
					);
					break;

				case 'order_cooldown':
					$unit = sanitize_key(
						(string) (
							$section_input['unit'] ??
							$section_fallback['unit'] ??
							'minutes'
						)
					);

					if (
						! in_array(
							$unit,
							array(
								'seconds',
								'minutes',
								'hours',
							),
							true
						)
					) {
						$unit = 'minutes';
					}

					$identify_by = sanitize_key(
						(string) (
							$section_input['identify_by'] ??
							$section_fallback['identify_by'] ??
							'smart'
						)
					);

					if (
						! in_array(
							$identify_by,
							array(
								'smart',
								'customer_id',
								'phone',
								'email',
								'ip',
							),
							true
						)
					) {
						$identify_by = 'smart';
					}

					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'duration' =>
							$this->sanitize_positive_integer(
								$section_input['duration'] ??
									$section_fallback['duration'] ??
									2,
								(int) (
									$section_fallback['duration'] ??
									2
								)
							),
						'unit'        => $unit,
						'identify_by' => $identify_by,
					);
					break;

				case 'blacklist':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'normalize_phone' =>
							$this->sanitize_yes_no(
								$section_input['normalize_phone'] ??
									$section_fallback['normalize_phone'] ??
									'yes',
								(string) (
									$section_fallback['normalize_phone'] ??
									'yes'
								)
							),
					);
					break;

				case 'activity_logging':
					$sanitized[ $section ] = array(
						'enabled' =>
							$this->sanitize_yes_no(
								$section_input['enabled'] ??
									$section_fallback['enabled'] ??
									'yes',
								(string) (
									$section_fallback['enabled'] ??
									'yes'
								)
							),
						'auto_cleanup' =>
							$this->sanitize_yes_no(
								$section_input['auto_cleanup'] ??
									$section_fallback['auto_cleanup'] ??
									'yes',
								(string) (
									$section_fallback['auto_cleanup'] ??
									'yes'
								)
							),
						'retention_days' =>
							$this->sanitize_positive_integer(
								$section_input['retention_days'] ??
									$section_fallback['retention_days'] ??
									30,
								(int) (
									$section_fallback['retention_days'] ??
									30
								)
							),
						'log_rate_limit' =>
							$this->sanitize_yes_no(
								$section_input['log_rate_limit'] ??
									$section_fallback['log_rate_limit'] ??
									'yes',
								(string) (
									$section_fallback['log_rate_limit'] ??
									'yes'
								)
							),
						'log_duplicate' =>
							$this->sanitize_yes_no(
								$section_input['log_duplicate'] ??
									$section_fallback['log_duplicate'] ??
									'yes',
								(string) (
									$section_fallback['log_duplicate'] ??
									'yes'
								)
							),
						'log_bot' =>
							$this->sanitize_yes_no(
								$section_input['log_bot'] ??
									$section_fallback['log_bot'] ??
									'yes',
								(string) (
									$section_fallback['log_bot'] ??
									'yes'
								)
							),
						'log_cooldown' =>
							$this->sanitize_yes_no(
								$section_input['log_cooldown'] ??
									$section_fallback['log_cooldown'] ??
									'yes',
								(string) (
									$section_fallback['log_cooldown'] ??
									'yes'
								)
							),
						'log_blacklist' =>
							$this->sanitize_yes_no(
								$section_input['log_blacklist'] ??
									$section_fallback['log_blacklist'] ??
									'yes',
								(string) (
									$section_fallback['log_blacklist'] ??
									'yes'
								)
							),
					);
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize Security customer messages.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $fallback Existing/default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_messages(
		array $input,
		array $fallback
	): array {

		$keys = array(
			'cooldown',
			'duplicate_order',
			'rate_limit',
			'bot_protection',
			'blacklist',
			'checkout_token_invalid',
			'checkout_request_failed',
		);

		$messages = $fallback;

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = sanitize_textarea_field(
				(string) $input[ $key ]
			);

			if ( '' === $value ) {
				$value = (string) (
					$fallback[ $key ] ??
					''
				);
			}

			$messages[ $key ] = $value;
		}

		return $messages;
	}

	/**
	 * Sanitize advanced Security settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $fallback Existing/default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_advanced(
		array $input,
		array $fallback
	): array {

		return array(
			'bypass_administrators' =>
				$this->sanitize_yes_no(
					$input['bypass_administrators'] ??
						$fallback['bypass_administrators'] ??
						'yes',
					(string) (
						$fallback['bypass_administrators'] ??
						'yes'
					)
				),

			'bypass_shop_managers' =>
				$this->sanitize_yes_no(
					$input['bypass_shop_managers'] ??
						$fallback['bypass_shop_managers'] ??
						'yes',
					(string) (
						$fallback['bypass_shop_managers'] ??
						'yes'
					)
				),
		);
	}

	/**
	 * Apply legacy General-setting fallbacks.
	 *
	 * Existing dedicated Security values always win.
	 * Legacy values are read only when the equivalent
	 * Security value has never been stored.
	 *
	 * @param array<string, mixed> $settings Merged Security settings.
	 * @param array<string, mixed> $stored   Raw stored Security settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function apply_legacy_fallbacks(
		array $settings,
		array $stored
	): array {

		$legacy = get_option(
			self::LEGACY_OPTION_NAME,
			array()
		);

		if (
			! is_array( $legacy ) ||
			empty( $legacy['general'] ) ||
			! is_array( $legacy['general'] )
		) {
			return $settings;
		}

		$general = $legacy['general'];

		if (
			! array_key_exists( 'enabled', $stored ) &&
			array_key_exists( 'checkout_security', $general )
		) {
			$settings['enabled'] =
				'yes' === $general['checkout_security']
					? 'yes'
					: 'no';
		}

		if (
			! self::nested_key_exists(
				$stored,
				array(
					'protection',
					'blacklist',
					'enabled',
				)
			) &&
			array_key_exists( 'customer_blacklist', $general )
		) {
			$settings['protection']['blacklist']['enabled'] =
				'yes' === $general['customer_blacklist']
					? 'yes'
					: 'no';
		}

		if (
			! self::nested_key_exists(
				$stored,
				array(
					'access',
					'checkout_access',
				)
			) &&
			array_key_exists( 'checkout_access', $general )
		) {
			$legacy_access = sanitize_key(
				(string) $general['checkout_access']
			);

			if (
				in_array(
					$legacy_access,
					array(
						'everyone',
						'guest_only',
						'logged_in_only',
					),
					true
				)
			) {
				$settings['access']['checkout_access'] =
					$legacy_access;
			}
		}

		if (
			! self::nested_key_exists(
				$stored,
				array(
					'access',
					'custom_login_url_enabled',
				)
			) &&
			array_key_exists(
				'custom_login_url_enabled',
				$general
			)
		) {
			$settings['access']['custom_login_url_enabled'] =
				'yes' === $general['custom_login_url_enabled']
					? 'yes'
					: 'no';
		}

		if (
			! self::nested_key_exists(
				$stored,
				array(
					'access',
					'custom_login_url',
				)
			) &&
			array_key_exists( 'custom_login_url', $general )
		) {
			$settings['access']['custom_login_url'] =
				esc_url_raw(
					trim(
						(string) $general['custom_login_url']
					)
				);
		}

		return $settings;
	}

	/**
	 * Check whether a nested key exists.
	 *
	 * Unlike isset(), this treats a stored null value as an
	 * existing key, which is safer for migration detection.
	 *
	 * @param array<string, mixed> $array Source array.
	 * @param array<int, string>   $path  Nested key path.
	 *
	 * @return bool
	 */
	private static function nested_key_exists(
		array $array,
		array $path
	): bool {

		$current = $array;

		foreach ( $path as $key ) {
			if (
				! is_array( $current ) ||
				! array_key_exists( $key, $current )
			) {
				return false;
			}

			$current = $current[ $key ];
		}

		return true;
	}

	/**
	 * Get array from parent array.
	 *
	 * @param array<string, mixed> $input Input.
	 * @param string               $key   Key.
	 *
	 * @return array<string, mixed>
	 */
	private function get_array(
		array $input,
		string $key
	): array {

		if (
			! isset( $input[ $key ] ) ||
			! is_array( $input[ $key ] )
		) {
			return array();
		}

		return $input[ $key ];
	}

	/**
	 * Sanitize yes/no value.
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
	 * Sanitize positive integer.
	 *
	 * Values below 1 fall back to the configured default.
	 *
	 * @param mixed $value    Value.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function sanitize_positive_integer(
		$value,
		int $fallback
	): int {

		$value = absint(
			$value
		);

		if ( $value < 1 ) {
			return max(
				1,
				$fallback
			);
		}

		return $value;
	}
}
