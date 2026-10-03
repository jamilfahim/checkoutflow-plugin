<?php
/**
 * Default WooCommerce checkout integration settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes native checkout integration settings.
 */
final class DefaultCheckoutSettings implements RegistrableInterface {

	/** Option name. */
	public const OPTION_NAME = 'eilmo_cf_default_checkout_settings';

	/** Option group. */
	public const OPTION_GROUP = 'eilmo_cf_default_checkout_settings_group';

	/** Schema version. */
	public const SCHEMA_VERSION = 11;

	/** Register hooks. */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/** Register settings. */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __(
					'Default WooCommerce checkout integration settings.',
					'eilmo-checkout-flow'
				),
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * Historical module keys remain untouched in the database for safe updates,
	 * but this settings model and its runtime no longer use them.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = array_replace_recursive( self::get_defaults(), $stored );

		/*
		 * 2.2.3.7 makes the campaign-matched Default Checkout presentation the
		 * default. Older releases saved the switch OFF by default, so installations
		 * created before schema 10 opt into the new default automatically. Merchants
		 * can still turn it OFF and save to return to raw WooCommerce/theme styling.
		 */
		$stored_schema = isset( $stored['schema_version'] ) ? (int) $stored['schema_version'] : 0;
		if ( $stored_schema < 10 ) {
			if ( ! isset( $merged['style'] ) || ! is_array( $merged['style'] ) ) {
				$merged['style'] = array();
			}
			$merged['style']['enabled'] = 'yes';
		}

		/* Default Checkout owns an independent visual theme. */
		$native_style = isset( $merged['style'] ) && is_array( $merged['style'] ) ? $merged['style'] : array();
		$stored_style = isset( $stored['style'] ) && is_array( $stored['style'] ) ? $stored['style'] : array();

		/*
		 * Schema 11 aligns untouched Default Checkout colours with Eilmo's
		 * canonical purple theme. Only the complete historical default palette is
		 * migrated; any merchant-customized value keeps the whole saved palette.
		 */
		if ( $stored_schema < 11 && ! empty( $stored_style ) ) {
			$old_defaults = array(
				'primary'         => '#96588a',
				'text'            => '#1e1e1e',
				'muted_text'      => '#6b7280',
				'background'      => '#ffffff',
				'soft_background' => '#f7f7f7',
				'border'          => '#dcdcde',
				'button_text'     => '#ffffff',
				'radius'          => 12,
				'parent_radius'   => 16,
				'gap'             => 16,
				'card_gap'        => 16,
			);
			$untouched = true;

			foreach ( $old_defaults as $key => $old_value ) {
				if ( ! array_key_exists( $key, $stored_style ) || strtolower( (string) $stored_style[ $key ] ) !== strtolower( (string) $old_value ) ) {
					$untouched = false;
					break;
				}
			}

			if ( $untouched ) {
				$enabled = (string) ( $native_style['enabled'] ?? 'yes' );
				$native_style = DefaultCheckoutStyle::get_defaults();
				$native_style['enabled'] = $enabled;
			}
		}
		/* Preserve the old two-colour native style as a one-time compatibility fallback. */
		if ( ! isset( $stored_style['primary'] ) && isset( $stored_style['accent_color'] ) ) {
			$legacy_primary = sanitize_hex_color( (string) $stored_style['accent_color'] );
			if ( $legacy_primary ) {
				$native_style['primary'] = $legacy_primary;
			}
		}
		if ( ! isset( $stored_style['background'] ) && isset( $stored_style['surface_color'] ) ) {
			$legacy_surface = sanitize_hex_color( (string) $stored_style['surface_color'] );
			if ( $legacy_surface ) {
				$native_style['background'] = $legacy_surface;
			}
		}
		$merged['style'] = array_replace_recursive(
			DefaultCheckoutStyle::get_defaults(),
			$native_style
		);

		/* Separate shipping-address customization was removed in schema 5. */
		if ( isset( $merged['fields'] ) && is_array( $merged['fields'] ) ) {
			foreach ( array_keys( $merged['fields'] ) as $field_key ) {
				if ( 0 === strpos( (string) $field_key, 'shipping_' ) ) {
					unset( $merged['fields'][ $field_key ] );
				}
			}
		}

		return $merged;
	}

	/**
	 * Get default settings.
	 *
	 * Blank labels/placeholders and inherited field properties deliberately
	 * preserve WooCommerce's country-aware defaults until explicitly changed.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		$fields = array();

		foreach ( self::get_field_keys() as $field_key ) {
			$fields[ $field_key ] = array(
				'enabled'     => 'yes',
				'label'       => '',
				'placeholder' => '',
				'required'    => 'inherit',
				'width'       => 'inherit',
				'priority'    => 0,
			);
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'advanced'       => array(
				'payment_options_enabled' => 'no',
				'live_fraud_enabled'      => 'no',
				'delivery_enabled'         => 'no',
			),
			'display'        => array(
				'order_button_label' => '',
			),
			'style'          => DefaultCheckoutStyle::get_defaults(),
			'purchase_flow'  => array(
				'hide_add_to_cart' => 'no',
				'direct_checkout'  => 'yes',
			),
			'fields'         => $fields,
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$sanitized = array_replace_recursive( self::get_defaults(), $stored );

		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		if ( isset( $input['advanced'] ) && is_array( $input['advanced'] ) ) {
			$live_fraud = $this->yes_no( $input['advanced']['live_fraud_enabled'] ?? 'no' );
			$payment_options = $this->yes_no( $input['advanced']['payment_options_enabled'] ?? 'no' );
			$delivery_enabled = $this->yes_no( $input['advanced']['delivery_enabled'] ?? 'no' );

			/* Live Fraud always needs the Eilmo Cash / Advance / Full selector. */
			if ( 'yes' === $live_fraud ) {
				$payment_options = 'yes';
			}

			$sanitized['advanced'] = array(
				'payment_options_enabled' => $payment_options,
				'live_fraud_enabled'      => $live_fraud,
				'delivery_enabled'         => $delivery_enabled,
			);
		}

		if ( isset( $input['display'] ) && is_array( $input['display'] ) ) {
			$sanitized['display'] = array(
				'order_button_label' => sanitize_text_field(
					(string) ( $input['display']['order_button_label'] ?? '' )
				),
			);
		}

		if ( isset( $input['style'] ) && is_array( $input['style'] ) ) {
			$current_style = isset( $sanitized['style'] ) && is_array( $sanitized['style'] )
				? $sanitized['style']
				: DefaultCheckoutStyle::get_defaults();
			$sanitized['style'] = DefaultCheckoutStyle::sanitize( $input['style'], $current_style );
		}

		if ( isset( $input['purchase_flow'] ) && is_array( $input['purchase_flow'] ) ) {
			$sanitized['purchase_flow'] = array(
				'hide_add_to_cart' => $this->yes_no(
					$input['purchase_flow']['hide_add_to_cart'] ?? 'no'
				),
				'direct_checkout'  => $this->yes_no(
					$input['purchase_flow']['direct_checkout'] ?? 'no'
				),
			);
		}

		if ( isset( $input['fields'] ) && is_array( $input['fields'] ) ) {
			$fields = array();

			foreach ( self::get_field_keys() as $field_key ) {
				$current = isset( $sanitized['fields'][ $field_key ] ) &&
					is_array( $sanitized['fields'][ $field_key ] )
						? $sanitized['fields'][ $field_key ]
						: array();

				$field_input = isset( $input['fields'][ $field_key ] ) &&
					is_array( $input['fields'][ $field_key ] )
						? $input['fields'][ $field_key ]
						: array();

				$fields[ $field_key ] = $this->sanitize_field( $field_input, $current );
			}

			$sanitized['fields'] = $fields;
		}

		if ( isset( $sanitized['fields'] ) && is_array( $sanitized['fields'] ) ) {
			foreach ( array_keys( $sanitized['fields'] ) as $field_key ) {
				if ( 0 === strpos( (string) $field_key, 'shipping_' ) ) {
					unset( $sanitized['fields'][ $field_key ] );
				}
			}
		}

		$sanitized['schema_version'] = self::SCHEMA_VERSION;

		return $sanitized;
	}

	/** Whether the Eilmo Payment Options bridge is enabled for native checkout. */
	public static function payment_options_enabled(): bool {
		$settings = self::get();
		$advanced = isset( $settings['advanced'] ) && is_array( $settings['advanced'] ) ? $settings['advanced'] : array();
		return 'yes' === (string) ( $advanced['payment_options_enabled'] ?? 'no' ) ||
			'yes' === (string) ( $advanced['live_fraud_enabled'] ?? 'no' );
	}

	/** Whether the existing Eilmo Delivery System is enabled for native checkout. */
	public static function delivery_enabled(): bool {
		$settings = self::get();
		$advanced = isset( $settings['advanced'] ) && is_array( $settings['advanced'] ) ? $settings['advanced'] : array();
		return 'yes' === (string) ( $advanced['delivery_enabled'] ?? 'no' );
	}

	/** Whether checkout-time Live Fraud is enabled for native checkout. */
	public static function live_fraud_enabled(): bool {
		$settings = self::get();
		$advanced = isset( $settings['advanced'] ) && is_array( $settings['advanced'] ) ? $settings['advanced'] : array();
		return 'yes' === (string) ( $advanced['live_fraud_enabled'] ?? 'no' );
	}

	/** Whether the Default Checkout Integration master feature is enabled. */
	public static function integration_enabled(): bool {
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$general = is_array( $stored ) && isset( $stored['general'] ) && is_array( $stored['general'] ) ? $stored['general'] : array();
		$defaults = CheckoutSettings::get_defaults();
		return 'yes' === (string) ( $general['default_checkout_integration'] ?? $defaults['general']['default_checkout_integration'] ?? 'no' );
	}

	/** Whether plugin-owned checkout colours should override the active theme. */
	public static function plugin_style_enabled(): bool {
		$settings = self::get();
		$style = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();
		return 'yes' === (string) ( $style['enabled'] ?? 'yes' );
	}

	/**
	 * Get the independent Default Checkout visual theme.
	 *
	 * @return array<string,mixed>
	 */
	public static function plugin_style(): array {
		$settings = self::get();
		$style = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();
		return array_replace_recursive( DefaultCheckoutStyle::get_defaults(), $style );
	}

	/**
	 * Supported native checkout field keys.
	 *
	 * @return array<int, string>
	 */
	public static function get_field_keys(): array {
		return array(
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_phone',
			'billing_email',
			'order_comments',
		);
	}

	/**
	 * Sanitize one field.
	 *
	 * @param array<string, mixed> $input   Input.
	 * @param array<string, mixed> $current Current values.
	 * @return array<string, mixed>
	 */
	private function sanitize_field( array $input, array $current ): array {
		$required = sanitize_key(
			(string) ( $input['required'] ?? $current['required'] ?? 'inherit' )
		);

		if ( ! in_array( $required, array( 'inherit', 'yes', 'no' ), true ) ) {
			$required = 'inherit';
		}

		$width = sanitize_key(
			(string) ( $input['width'] ?? $current['width'] ?? 'inherit' )
		);

		if ( ! in_array( $width, array( 'inherit', 'full', 'half' ), true ) ) {
			$width = 'inherit';
		}

		return array(
			'enabled'     => $this->yes_no( $input['enabled'] ?? $current['enabled'] ?? 'yes' ),
			'label'       => sanitize_text_field( (string) ( $input['label'] ?? '' ) ),
			'placeholder' => sanitize_text_field( (string) ( $input['placeholder'] ?? '' ) ),
			'required'    => $required,
			'width'       => $width,
			'priority'    => min( 999, absint( $input['priority'] ?? 0 ) ),
		);
	}

	/** Sanitize yes/no. */
	private function yes_no( $value ): string {
		return 'yes' === (string) $value ? 'yes' : 'no';
	}
}
