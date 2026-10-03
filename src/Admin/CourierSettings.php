<?php
/**
 * Courier settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Courier settings.
 *
 * The Courier feature master switch lives in the main
 * General settings.
 *
 * All detailed Courier configuration is stored
 * separately in this option.
 */
final class CourierSettings implements RegistrableInterface {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME =
		'eilmo_cf_courier_settings';

	/**
	 * Option group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP =
		'eilmo_cf_courier_settings_group';

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
						'Eilmo Courier settings.',
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
	 * Get default Courier settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {

		return array(

			/*
			 * Courier features.
			 */
			'features' => array(

				'courier_success' =>
					'yes',

				'courier_one_click' =>
					'yes',
			),

			/*
			 * Courier Success API.
			 */
			'ratio_api' => array(

				/*
				 * Courier history source used by both the Orders-screen
				 * success ratio and, when enabled, Live Checkout Fraud.
				 *
				 * steadfast = default/free Steadfast fraud-check endpoint.
				 * bdcourier = optional multi-courier BD Courier endpoint.
				 */
				'provider' =>
					'steadfast',

				'api_key' =>
					'',

				/* When BD Courier is selected and unavailable, automatically retry with Steadfast. */
				'fallback_to_steadfast' =>
					'yes',

				/* Show this store's customer order history inside courier history hover cards. */
				'show_store_history' =>
					'yes',

				'cache_minutes' =>
					360,
			),

			/*
			 * Advanced live checkout fraud assessment.
			 *
			 * Disabled by default so existing installations keep the
			 * post-order Courier Success workflow until explicitly enabled.
			 */
			'live_fraud' => array(

				'enabled' =>
					'no',

				/* Prefer this store's own resolved orders before spending API quota. */
				'data_strategy' =>
					'local_first',

				'minimum_local_orders' =>
					3,

				/* Successful phone-level courier snapshots remain reusable. */
				'cache_days' =>
					30,

				'refresh_expired_cache' =>
					'yes',

				'use_stale_on_failure' =>
					'yes',

				'api_error_cooldown_minutes' =>
					30,

				'manual_order_check' =>
					'yes',

				'show_cache_age' =>
					'yes',

				'trusted_rate' =>
					95,

				'advance_rate' =>
					90,

				'block_rate' =>
					40,

				'minimum_orders_for_block' =>
					5,

				'unknown_action' =>
					'allow',

				'middle_action' =>
					'allow',

				'high_risk_action' =>
					'advance',

				'critical_action' =>
					'block',

				/* Kept for backwards compatibility; failure payments drive the action. */
				'api_failure_action' =>
					'allow',

				/*
				 * always      = render every globally enabled option and disable
				 *               options rejected by the fraud decision.
				 * conditional = hide the section until verification, then render
				 *               only options accepted by the fraud decision.
				 */
				'payment_display_mode' =>
					'always',

				'payment_rules' => array(
					'trusted' => array( 'cash_on_delivery', 'advance', 'full' ),
					'review' => array( 'cash_on_delivery', 'advance', 'full' ),
					'high' => array( 'advance', 'full' ),
					'critical' => array(),
					'unknown' => array( 'advance', 'full' ),
					'unavailable' => array( 'cash_on_delivery', 'advance', 'full' ),
				),

				'checking_message' =>
					__( 'Checking delivery history…', 'eilmo-checkout-flow' ),

				'allow_message' =>
					__( 'Phone verification completed.', 'eilmo-checkout-flow' ),

				'advance_message' =>
					__( 'An advance payment is required for this order.', 'eilmo-checkout-flow' ),

				'full_message' =>
					__( 'Full payment is required for this order.', 'eilmo-checkout-flow' ),

				'block_message' =>
					__( 'We cannot accept this order with the provided phone number.', 'eilmo-checkout-flow' ),

				'unknown_message' =>
					__( 'No delivery history found.', 'eilmo-checkout-flow' ),

				'unavailable_message' =>
					__( 'Live verification is temporarily unavailable. You can still place your order using an available payment option.', 'eilmo-checkout-flow' ),

				'local_message' =>
					__( 'Your previous purchase history has been checked.', 'eilmo-checkout-flow' ),

				'stale_message' =>
					__( 'Saved courier history is being used because a fresh check is unavailable.', 'eilmo-checkout-flow' ),
			),

			/*
			 * Steadfast Courier.
			 */
			'steadfast' => array(

				'base_url' =>
					'https://portal.packzy.com/api/v1',

				'api_key' =>
					'',

				'secret_key' =>
					'',
			),

			/*
			 * Pathao Courier.
			 */
			'pathao' => array(

				/*
				 * Supported:
				 *
				 * live
				 * staging
				 */
				'environment' =>
					'live',

				'client_id' =>
					'',

				'client_secret' =>
					'',

				/*
				 * Pathao webhook secret.
				 *
				 * This must exactly match the Secret configured in
				 * Pathao Merchant Panel > Developer API > Webhook
				 * Integration.
				 */
				'webhook_secret' =>
					'',

				'store_id' =>
					0,

				/*
				 * 48 = Normal Delivery.
				 * 12 = On Demand.
				 * 24 = Express Delivery.
				 */
				'delivery_type' =>
					48,

				/*
				 * 1 = Document.
				 * 2 = Parcel.
				 * 3 = Fragile.
				 */
				'item_type' =>
					2,

				/*
				 * Fallback weight in kilograms.
				 */
				'item_weight' =>
					0.5,
			),
		);
	}

	/**
	 * Get stored settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {

		$stored =
			get_option(
				self::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		$settings = array_replace_recursive(
			self::get_defaults(),
			$stored
		);

		/* Shorten the original v1.9.47 default without overwriting a genuinely
		 * customized merchant message. */
		$legacy_unknown_message = 'No previous delivery history was found. Please continue with an available payment option.';
		if (
			isset( $settings['live_fraud']['unknown_message'] ) &&
			$legacy_unknown_message === (string) $settings['live_fraud']['unknown_message']
		) {
			$settings['live_fraud']['unknown_message'] = (string) self::get_defaults()['live_fraud']['unknown_message'];
		}

		/*
		 * Numeric arrays are merged item-by-item by array_replace_recursive().
		 * A deliberately empty per-band allowlist must replace its default
		 * completely because an empty list means "block this band".
		 */
		$stored_rules = isset( $stored['live_fraud']['payment_rules'] ) && is_array( $stored['live_fraud']['payment_rules'] )
			? $stored['live_fraud']['payment_rules']
			: array();
		foreach ( array( 'trusted', 'review', 'high', 'critical', 'unknown', 'unavailable' ) as $band ) {
			if ( array_key_exists( $band, $stored_rules ) ) {
				$settings['live_fraud']['payment_rules'][ $band ] = is_array( $stored_rules[ $band ] )
					? array_values( $stored_rules[ $band ] )
					: array();
			}
		}

		return $settings;
	}

	/**
	 * Determine whether the global Courier feature is enabled.
	 *
	 * The master switch is stored in:
	 *
	 * eilmo_cf_settings[general][courier]
	 *
	 * Courier is enabled by default.
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

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		$default_general =
			isset(
				$defaults[
					'general'
				]
			) &&
			is_array(
				$defaults[
					'general'
				]
			)
				? $defaults[
					'general'
				]
				: array();

		$stored_general =
			isset(
				$stored[
					'general'
				]
			) &&
			is_array(
				$stored[
					'general'
				]
			)
				? $stored[
					'general'
				]
				: array();

		$general =
			array_replace(
				$default_general,
				$stored_general
			);

		return 'yes' ===
			(
				$general[
					'courier'
				] ??
					'yes'
			);
	}

	/**
	 * Determine whether one Courier sub-feature is enabled.
	 *
	 * @param string $feature Feature key.
	 *
	 * @return bool
	 */
	public static function feature_is_enabled(
		string $feature
	): bool {

		if (
			! self::is_enabled()
		) {
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
					'courier_success',
					'courier_one_click',
				),
				true
			)
		) {
			return false;
		}

		$settings =
			self::get_settings();

		$features =
			isset(
				$settings[
					'features'
				]
			) &&
			is_array(
				$settings[
					'features'
				]
			)
				? $settings[
					'features'
				]
				: array();

		return 'yes' ===
			(
				$features[
					$feature
				] ??
					'yes'
			);
	}

	/**
	 * Determine whether checkout-time fraud assessment is enabled.
	 *
	 * @return bool
	 */
	public static function live_fraud_is_enabled(): bool {

		/*
		 * Live fraud is an independent checkout feature. It shares the
		 * Courier API credentials, but it must not silently switch off when
		 * the Orders-screen Courier Success column is disabled.
		 */
		$native_enabled = DefaultCheckoutSettings::integration_enabled() &&
			DefaultCheckoutSettings::live_fraud_enabled();

		if ( $native_enabled ) {
			return true;
		}

		if ( ! self::is_enabled() ) {
			return false;
		}

		$settings = self::get_settings();
		$live_fraud = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();

		return 'yes' === ( $live_fraud['enabled'] ?? 'no' );
	}

	/**
	 * Get the selected Courier Success / external fraud-history provider.
	 *
	 * This setting is intentionally independent from the Live Checkout Fraud
	 * toggle. Orders-screen Courier Performance continues to use the selected
	 * source even when live checkout checking is disabled.
	 *
	 * @return string steadfast|bdcourier
	 */
	public static function get_success_provider(): string {

		$settings = self::get_settings();
		$ratio_api = isset( $settings['ratio_api'] ) && is_array( $settings['ratio_api'] )
			? $settings['ratio_api']
			: array();
		$provider = sanitize_key( (string) ( $ratio_api['provider'] ?? 'steadfast' ) );

		return in_array( $provider, array( 'steadfast', 'bdcourier' ), true )
			? $provider
			: 'steadfast';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Submitted settings.
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

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		if (
			! is_array(
				$input
			)
		) {
			return array_replace_recursive(
				$defaults,
				$stored
			);
		}

		$features =
			$this->get_array(
				$input,
				'features'
			);

		$ratio_api =
			$this->get_array(
				$input,
				'ratio_api'
			);

		$live_fraud =
			$this->get_array(
				$input,
				'live_fraud'
			);

		$steadfast =
			$this->get_array(
				$input,
				'steadfast'
			);

		$pathao =
			$this->get_array(
				$input,
				'pathao'
			);

		$stored_ratio_api =
			isset(
				$stored[
					'ratio_api'
				]
			) &&
			is_array(
				$stored[
					'ratio_api'
				]
			)
				? $stored[
					'ratio_api'
				]
				: array();

		$stored_steadfast =
			isset(
				$stored[
					'steadfast'
				]
			) &&
			is_array(
				$stored[
					'steadfast'
				]
			)
				? $stored[
					'steadfast'
				]
				: array();

		$stored_pathao =
			isset(
				$stored[
					'pathao'
				]
			) &&
			is_array(
				$stored[
					'pathao'
				]
			)
				? $stored[
					'pathao'
				]
				: array();

		/*
		 * Pathao environment.
		 */
		$environment =
			sanitize_key(
				(string) (
					$pathao[
						'environment'
					] ??
						$defaults[
							'pathao'
						][
							'environment'
						]
				)
			);

		if (
			! in_array(
				$environment,
				array(
					'live',
					'staging',
				),
				true
			)
		) {
			$environment =
				(string) $defaults[
					'pathao'
				][
					'environment'
				];
		}

		/*
		 * Pathao delivery type.
		 */
		$delivery_type =
			absint(
				$pathao[
					'delivery_type'
				] ??
					$defaults[
						'pathao'
					][
						'delivery_type'
					]
			);

		if (
			! in_array(
				$delivery_type,
				array(
					12,
					24,
					48,
				),
				true
			)
		) {
			$delivery_type =
				(int) $defaults[
					'pathao'
				][
					'delivery_type'
				];
		}

		/*
		 * Pathao item type.
		 */
		$item_type =
			absint(
				$pathao[
					'item_type'
				] ??
					$defaults[
						'pathao'
					][
						'item_type'
					]
			);

		if (
			! in_array(
				$item_type,
				array(
					1,
					2,
					3,
				),
				true
			)
		) {
			$item_type =
				(int) $defaults[
					'pathao'
				][
					'item_type'
				];
		}

		/*
		 * Pathao fallback weight.
		 */
		$item_weight =
			isset(
				$pathao[
					'item_weight'
				]
			) &&
			is_numeric(
				$pathao[
					'item_weight'
				]
			)
				? (float) $pathao[
					'item_weight'
				]
				: (float) $defaults[
					'pathao'
				][
					'item_weight'
				];

		$item_weight =
			max(
				0.1,
				$item_weight
			);

		/*
		 * Courier Success / fraud-history provider.
		 */
		$success_provider = sanitize_key(
			(string) ( $ratio_api['provider'] ?? $defaults['ratio_api']['provider'] )
		);

		if ( ! in_array( $success_provider, array( 'steadfast', 'bdcourier' ), true ) ) {
			$success_provider = 'steadfast';
		}

		/*
		 * Courier Success cache duration.
		 */
		$cache_minutes =
			absint(
				$ratio_api[
					'cache_minutes'
				] ??
					$defaults[
						'ratio_api'
					][
						'cache_minutes'
					]
			);

		$trusted_rate = $this->sanitize_percentage(
			$live_fraud['trusted_rate'] ?? $defaults['live_fraud']['trusted_rate'],
			(float) $defaults['live_fraud']['trusted_rate']
		);

		$advance_rate = $this->sanitize_percentage(
			$live_fraud['advance_rate'] ?? $defaults['live_fraud']['advance_rate'],
			(float) $defaults['live_fraud']['advance_rate']
		);

		$block_rate = $this->sanitize_percentage(
			$live_fraud['block_rate'] ?? $defaults['live_fraud']['block_rate'],
			(float) $defaults['live_fraud']['block_rate']
		);

		/* Keep the bands ordered even if the option is edited manually. */
		$advance_rate = min( $trusted_rate, $advance_rate );
		$block_rate = min( $advance_rate, $block_rate );

		$cache_minutes =
			max(
				5,
				min(
					1440,
					$cache_minutes
				)
			);

		return array(

			'features' => array(

				'courier_success' =>
					$this->sanitize_yes_no(
						$features[
							'courier_success'
						] ??
							null,
						(string) $defaults[
							'features'
						][
							'courier_success'
						]
					),

				'courier_one_click' =>
					$this->sanitize_yes_no(
						$features[
							'courier_one_click'
						] ??
							null,
						(string) $defaults[
							'features'
						][
							'courier_one_click'
						]
					),
			),

			'ratio_api' => array(

				'provider' =>
					$success_provider,

				'api_key' =>
					$this->sanitize_secret(
						$ratio_api[
							'api_key'
						] ??
							'',
						$stored_ratio_api[
							'api_key'
						] ??
							''
					),

				'cache_minutes' =>
					$cache_minutes,

				'fallback_to_steadfast' =>
					$this->sanitize_yes_no(
						$ratio_api['fallback_to_steadfast'] ?? null,
						(string) $defaults['ratio_api']['fallback_to_steadfast']
					),

				'show_store_history' =>
					$this->sanitize_yes_no(
						$ratio_api['show_store_history'] ?? null,
						(string) $defaults['ratio_api']['show_store_history']
					),
			),

			'live_fraud' => array(

				'enabled' => $this->sanitize_yes_no(
					$live_fraud['enabled'] ?? null,
					(string) $defaults['live_fraud']['enabled']
				),

				'data_strategy' => $this->sanitize_data_strategy(
					$live_fraud['data_strategy'] ?? $defaults['live_fraud']['data_strategy']
				),
				'minimum_local_orders' => max(
					1,
					min( 100, absint( $live_fraud['minimum_local_orders'] ?? $defaults['live_fraud']['minimum_local_orders'] ) )
				),
				'cache_days' => max(
					1,
					min( 365, absint( $live_fraud['cache_days'] ?? $defaults['live_fraud']['cache_days'] ) )
				),
				'refresh_expired_cache' => $this->sanitize_yes_no(
					$live_fraud['refresh_expired_cache'] ?? null,
					(string) $defaults['live_fraud']['refresh_expired_cache']
				),
				'use_stale_on_failure' => $this->sanitize_yes_no(
					$live_fraud['use_stale_on_failure'] ?? null,
					(string) $defaults['live_fraud']['use_stale_on_failure']
				),
				'api_error_cooldown_minutes' => max(
					1,
					min( 1440, absint( $live_fraud['api_error_cooldown_minutes'] ?? $defaults['live_fraud']['api_error_cooldown_minutes'] ) )
				),
				'manual_order_check' => $this->sanitize_yes_no(
					$live_fraud['manual_order_check'] ?? null,
					(string) $defaults['live_fraud']['manual_order_check']
				),
				'show_cache_age' => $this->sanitize_yes_no(
					$live_fraud['show_cache_age'] ?? null,
					(string) $defaults['live_fraud']['show_cache_age']
				),

				'trusted_rate' => $trusted_rate,
				'advance_rate' => $advance_rate,
				'block_rate' => $block_rate,
				'minimum_orders_for_block' => max(
					1,
					min( 100, absint( $live_fraud['minimum_orders_for_block'] ?? 5 ) )
				),
				'unknown_action' => $this->sanitize_fraud_action( $live_fraud['unknown_action'] ?? 'allow', 'allow' ),
				'middle_action' => $this->sanitize_fraud_action( $live_fraud['middle_action'] ?? 'allow', 'allow' ),
				'high_risk_action' => $this->sanitize_fraud_action( $live_fraud['high_risk_action'] ?? 'advance', 'advance' ),
				'critical_action' => $this->sanitize_fraud_action( $live_fraud['critical_action'] ?? 'block', 'block' ),
				'api_failure_action' => 'allow',
				'payment_display_mode' => $this->sanitize_payment_display_mode( $live_fraud['payment_display_mode'] ?? 'always' ),
				'payment_rules' => $this->sanitize_payment_rules(
					isset( $live_fraud['payment_rules'] ) && is_array( $live_fraud['payment_rules'] )
						? $live_fraud['payment_rules']
						: array(),
					$defaults['live_fraud']['payment_rules']
				),
				'checking_message' => $this->sanitize_message( $live_fraud['checking_message'] ?? '', (string) $defaults['live_fraud']['checking_message'] ),
				'allow_message' => $this->sanitize_message( $live_fraud['allow_message'] ?? '', (string) $defaults['live_fraud']['allow_message'] ),
				'advance_message' => $this->sanitize_message( $live_fraud['advance_message'] ?? '', (string) $defaults['live_fraud']['advance_message'] ),
				'full_message' => $this->sanitize_message( $live_fraud['full_message'] ?? '', (string) $defaults['live_fraud']['full_message'] ),
				'block_message' => $this->sanitize_message( $live_fraud['block_message'] ?? '', (string) $defaults['live_fraud']['block_message'] ),
				'unknown_message' => $this->sanitize_message( $live_fraud['unknown_message'] ?? '', (string) $defaults['live_fraud']['unknown_message'] ),
				'unavailable_message' => $this->sanitize_message( $live_fraud['unavailable_message'] ?? '', (string) $defaults['live_fraud']['unavailable_message'] ),
				'local_message' => $this->sanitize_message( $live_fraud['local_message'] ?? '', (string) $defaults['live_fraud']['local_message'] ),
				'stale_message' => $this->sanitize_message( $live_fraud['stale_message'] ?? '', (string) $defaults['live_fraud']['stale_message'] ),
			),

			'steadfast' => array(

				'base_url' =>
					$this->sanitize_url(
						$steadfast[
							'base_url'
						] ??
							$defaults[
								'steadfast'
							][
								'base_url'
							],
						(string) $defaults[
							'steadfast'
						][
							'base_url'
						]
					),

				'api_key' =>
					$this->sanitize_secret(
						$steadfast[
							'api_key'
						] ??
							'',
						$stored_steadfast[
							'api_key'
						] ??
							''
					),

				'secret_key' =>
					$this->sanitize_secret(
						$steadfast[
							'secret_key'
						] ??
							'',
						$stored_steadfast[
							'secret_key'
						] ??
							''
					),
			),

			'pathao' => array(

				'environment' =>
					$environment,

				'client_id' =>
					$this->sanitize_secret(
						$pathao[
							'client_id'
						] ??
							'',
						$stored_pathao[
							'client_id'
						] ??
							''
					),

				'client_secret' =>
					$this->sanitize_secret(
						$pathao[
							'client_secret'
						] ??
							'',
						$stored_pathao[
							'client_secret'
						] ??
							''
					),

				'webhook_secret' =>
					$this->sanitize_secret(
						$pathao[
							'webhook_secret'
						] ??
							'',
						$stored_pathao[
							'webhook_secret'
						] ??
							''
					),

				'store_id' =>
					absint(
						$pathao[
							'store_id'
						] ??
							$defaults[
								'pathao'
							][
								'store_id'
							]
					),

				'delivery_type' =>
					$delivery_type,

				'item_type' =>
					$item_type,

				'item_weight' =>
					round(
						$item_weight,
						2
					),
			),
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

		if (
			'yes' ===
				$value
		) {
			return 'yes';
		}

		if (
			'no' ===
				$value
		) {
			return 'no';
		}

		return (
			'yes' ===
				$fallback
		)
			? 'yes'
			: 'no';
	}

	/**
	 * Sanitize a percentage.
	 *
	 * @param mixed $value Value.
	 * @param float $fallback Fallback.
	 * @return float
	 */
	private function sanitize_percentage( $value, float $fallback ): float {

		$value = is_numeric( $value ) ? (float) $value : $fallback;
		return round( max( 0, min( 100, $value ) ), 2 );
	}

	/**
	 * Sanitize a live fraud decision action.
	 *
	 * @param mixed  $value Value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function sanitize_fraud_action( $value, string $fallback ): string {

		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'allow', 'advance', 'full', 'block' ), true )
			? $value
			: $fallback;
	}

	/** Sanitize the live fraud data-source strategy. */
	private function sanitize_data_strategy( $value ): string {

		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'local_first', 'courier_first', 'combined', 'local_only', 'courier_only' ), true )
			? $value
			: 'local_first';
	}

	/** Sanitize the fraud payment-card presentation mode. */
	private function sanitize_payment_display_mode( $value ): string {

		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'always', 'conditional' ), true ) ? $value : 'always';
	}

	/**
	 * Sanitize per-risk-band payment allowlists.
	 *
	 * Empty lists are valid for risk bands that intentionally block. API
	 * failure is different: at least one fallback payment must remain available
	 * so exhausted courier quota can never strand a genuine customer.
	 *
	 * @param array<string,mixed> $rules Submitted rules.
	 * @param array<string,mixed> $defaults Default rules.
	 * @return array<string,array<int,string>>
	 */
	private function sanitize_payment_rules( array $rules, array $defaults ): array {

		$clean = array();
		foreach ( array( 'trusted', 'review', 'high', 'critical', 'unknown', 'unavailable' ) as $band ) {
			$submitted = array_key_exists( $band, $rules ) ? $rules[ $band ] : ( $defaults[ $band ] ?? array() );
			$submitted = is_array( $submitted ) ? $submitted : array();
			$clean[ $band ] = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $payment_type ): string {
								return is_scalar( $payment_type ) ? sanitize_key( (string) $payment_type ) : '';
							},
							$submitted
						),
						static function ( string $payment_type ): bool {
							return in_array( $payment_type, array( 'cash_on_delivery', 'advance', 'full' ), true );
						}
					)
				)
			);
		}

		if ( empty( $clean['unavailable'] ) ) {
			$fallback = isset( $defaults['unavailable'] ) && is_array( $defaults['unavailable'] )
				? $defaults['unavailable']
				: array( 'cash_on_delivery', 'advance', 'full' );
			$clean['unavailable'] = array_values(
				array_intersect(
					array( 'cash_on_delivery', 'advance', 'full' ),
					$fallback
				)
			);
		}

		return $clean;
	}

	/**
	 * Sanitize a customer-facing message with a safe fallback.
	 *
	 * @param mixed  $value Value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function sanitize_message( $value, string $fallback ): string {

		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Sanitize secret/credential field.
	 *
	 * Empty submitted values preserve an existing saved
	 * credential.
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
			is_scalar(
				$value
			)
				? trim(
					sanitize_text_field(
						(string) $value
					)
				)
				: '';

		if (
			'' !==
				$value
		) {
			return $value;
		}

		$stored =
			is_scalar(
				$stored
			)
				? trim(
					sanitize_text_field(
						(string) $stored
					)
				)
				: '';

		return $stored;
	}

	/**
	 * Sanitize URL with fallback.
	 *
	 * @param mixed  $value    URL.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_url(
		$value,
		string $fallback
	): string {

		$value =
			is_scalar(
				$value
			)
				? trim(
					(string) $value
				)
				: '';

		$url =
			esc_url_raw(
				$value
			);

		if (
			'' !==
				$url
		) {
			return untrailingslashit(
				$url
			);
		}

		$fallback =
			esc_url_raw(
				$fallback
			);

		return untrailingslashit(
			$fallback
		);
	}

	/**
	 * Get nested array.
	 *
	 * @param array<string,mixed> $source Source.
	 * @param string              $key    Key.
	 *
	 * @return array<string,mixed>
	 */
	private function get_array(
		array $source,
		string $key
	): array {

		if (
			! isset(
				$source[
					$key
				]
			) ||
			! is_array(
				$source[
					$key
				]
			)
		) {
			return array();
		}

		return $source[
			$key
		];
	}
}
