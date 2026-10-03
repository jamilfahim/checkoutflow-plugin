<?php
/**
 * Native WooCommerce checkout integration.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\DefaultCheckout;

use EilmoCheckout\Licensing\LicenseManager;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\DefaultCheckoutStyle;
use EilmoCheckout\Admin\DefaultCheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Couriers\Ajax\LiveFraudAjax;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the saved field settings to WooCommerce Classic Checkout and the
 * Checkout Block without replacing WooCommerce checkout processing.
 */
final class DefaultCheckoutIntegration implements RegistrableInterface {

	/** Register hooks. */
	public function register(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}
		if ( ! $this->is_enabled() ) {
			return;
		}

		/* Bangladesh-first flow: one delivery address only. WooCommerce should use billing as shipping. */
		add_filter(
			'pre_option_woocommerce_ship_to_destination',
			array( $this, 'force_billing_shipping_destination' ),
			100,
			1
		);

		add_filter(
			'woocommerce_ship_to_different_address_checked',
			'__return_false',
			100
		);

		add_filter(
			'woocommerce_checkout_fields',
			array( $this, 'filter_checkout_fields' ),
			20
		);

		add_filter(
			'woocommerce_order_button_text',
			array( $this, 'filter_order_button_text' ),
			20
		);

		add_filter(
			'woocommerce_get_country_locale_base',
			array( $this, 'filter_block_locale_base' ),
			50,
			1
		);

		add_filter(
			'woocommerce_get_country_locale',
			array( $this, 'filter_block_country_locale' ),
			50,
			1
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_block_adapter' ),
			30
		);

		/*
		 * One server-rendered namespace identifies Default Checkout before the first
		 * paint. The visual class is opt-in and never depends on JavaScript, which
		 * prevents campaign/native style flashes during checkout initialization.
		 */
		add_filter( 'body_class', array( $this, 'filter_plugin_style_body_class' ) );
		if ( DefaultCheckoutSettings::plugin_style_enabled() ) {
			/* Enqueue before normal theme styles so child/theme CSS remains the final authority. */
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_plugin_checkout_style' ), 6 );
		}

		if ( DefaultCheckoutSettings::payment_options_enabled() || DefaultCheckoutSettings::live_fraud_enabled() ) {
			( new NativePaymentBridge() )->register();
		}

		if ( DefaultCheckoutSettings::delivery_enabled() ) {
			( new NativeDeliveryBridge() )->register();
		}

		/* Native Live Fraud must remain usable even if the Orders Courier module is disabled. */
		if ( DefaultCheckoutSettings::live_fraud_enabled() && ! CourierSettings::is_enabled() ) {
			( new LiveFraudAjax() )->register();
		}
	}

	/**
	 * Add Default Checkout namespaces at server-render time.
	 *
	 * `eilmo-cf-native-checkout-page` is functional/context-only.
	 * `eilmo-cf-native-style` activates the optional Eilmo visual layer.
	 * Neither class is shared with Campaign Checkout.
	 */
	public function filter_plugin_style_body_class( array $classes ): array {
		if ( ! $this->is_checkout_request() ) {
			return $classes;
		}

		$classes[] = 'eilmo-cf-native-checkout-page';
		if ( DefaultCheckoutSettings::plugin_style_enabled() ) {
			$classes[] = 'eilmo-cf-native-style';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Load the optional Eilmo visual layer. It depends on WooCommerce core CSS
	 * and is enqueued early so the active theme/child-theme can override it
	 * naturally. The file is never enqueued while the setting is OFF.
	 */
	public function enqueue_plugin_checkout_style(): void {
		if ( ! $this->is_checkout_request() || ! DefaultCheckoutSettings::plugin_style_enabled() ) {
			return;
		}

		$relative = 'src/css/frontend/default-checkout-ui.css';
		$absolute = EILMO_CF_PATH . 'assets/' . $relative;
		if ( ! file_exists( $absolute ) ) {
			return;
		}

		wp_enqueue_style(
			'eilmo-cf-default-checkout-ui',
			EILMO_CF_ASSETS_URL . $relative,
			array( 'woocommerce-general' ),
			(string) filemtime( $absolute )
		);

		$style = DefaultCheckoutSettings::plugin_style();
		$css_variables = DefaultCheckoutStyle::get_css_variables( $style );
		if ( '' !== $css_variables ) {
			wp_add_inline_style(
				'eilmo-cf-default-checkout-ui',
				'body.eilmo-cf-native-style{' . $css_variables . '}'
			);
		}

	}

	/**
	 * Modify native Classic Checkout fields without changing their keys/names.
	 *
	 * @param array<string, mixed> $checkout_fields Checkout fields.
	 * @return array<string, mixed>
	 */
	public function filter_checkout_fields( array $checkout_fields ): array {
		foreach ( $this->field_settings() as $field_key => $override ) {
			if ( ! is_array( $override ) ) {
				continue;
			}

			$section = $this->field_section( (string) $field_key );
			if ( '' === $section || ! isset( $checkout_fields[ $section ][ $field_key ] ) ) {
				continue;
			}

			if ( 'yes' !== (string) ( $override['enabled'] ?? 'yes' ) ) {
				unset( $checkout_fields[ $section ][ $field_key ] );
				continue;
			}

			$field = is_array( $checkout_fields[ $section ][ $field_key ] )
				? $checkout_fields[ $section ][ $field_key ]
				: array();

			$label = sanitize_text_field( (string) ( $override['label'] ?? '' ) );
			if ( '' !== $label ) {
				$field['label'] = $label;
			}

			$placeholder = sanitize_text_field( (string) ( $override['placeholder'] ?? '' ) );
			if ( '' !== $placeholder ) {
				$field['placeholder'] = $placeholder;
			}

			$required = sanitize_key( (string) ( $override['required'] ?? 'inherit' ) );
			if ( in_array( $required, array( 'yes', 'no' ), true ) ) {
				$field['required'] = 'yes' === $required;
			}

			$width = sanitize_key( (string) ( $override['width'] ?? 'inherit' ) );
			if ( in_array( $width, array( 'full', 'half' ), true ) ) {
				$field['class'] = array_values(
					array_filter(
						isset( $field['class'] ) && is_array( $field['class'] )
							? $field['class']
							: array(),
						static function ( $class ): bool {
							return ! in_array(
								$class,
								array( 'form-row-first', 'form-row-last', 'form-row-wide' ),
								true
							);
						}
					)
				);

				$field['class'][] = 'full' === $width
					? 'form-row-wide'
					: $this->half_width_class( (string) $field_key );
			}

			$priority = absint( $override['priority'] ?? 0 );
			if ( $priority > 0 ) {
				$field['priority'] = $priority;
			}

			$checkout_fields[ $section ][ $field_key ] = $field;
		}

		/* Never expose a separate shipping-address form; billing is the delivery address. */
		$checkout_fields['shipping'] = array();

		return $checkout_fields;
	}

	/**
	 * Force WooCommerce to use the billing address as the shipping destination.
	 *
	 * @param mixed $pre Current pre-option value.
	 * @return string
	 */
	public function force_billing_shipping_destination( $pre ): string {
		return 'billing_only';
	}

	/**
	 * Apply settings to the Checkout Block base locale.
	 *
	 * @param array<string, mixed> $locale Base locale.
	 * @return array<string, mixed>
	 */
	public function filter_block_locale_base( array $locale ): array {
		return $this->apply_block_locale_settings( $locale );
	}

	/**
	 * Apply settings to every Checkout Block country locale.
	 *
	 * @param array<string, mixed> $locales Country locales.
	 * @return array<string, mixed>
	 */
	public function filter_block_country_locale( array $locales ): array {
		foreach ( $locales as $country => $locale ) {
			if ( is_array( $locale ) ) {
				$locales[ $country ] = $this->apply_block_locale_settings( $locale );
			}
		}

		return $locales;
	}

	/**
	 * Merge shared address rules into one WooCommerce locale.
	 *
	 * Checkout Block uses one country schema for billing and shipping. Shared
	 * rules are applied server-side; section-specific presentation remains in
	 * the block adapter.
	 *
	 * @param array<string, mixed> $locale Locale.
	 * @return array<string, mixed>
	 */
	private function apply_block_locale_settings( array $locale ): array {
		$fields = $this->field_settings();
		$address_keys = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
		);

		foreach ( $address_keys as $address_key ) {
			$billing = isset( $fields[ 'billing_' . $address_key ] ) && is_array( $fields[ 'billing_' . $address_key ] )
				? $fields[ 'billing_' . $address_key ]
				: array();
			$enabled = 'yes' === (string) ( $billing['enabled'] ?? 'yes' );
			$rule = array();

			/* Country remains available to Store API for tax/shipping calculations. */
			if ( 'country' !== $address_key && ! $enabled ) {
				$rule['hidden'] = true;
			}

			$required = sanitize_key( (string) ( $billing['required'] ?? 'inherit' ) );
			if ( in_array( $required, array( 'yes', 'no' ), true ) ) {
				$rule['required'] = 'yes' === $required;
			}

			foreach ( array( 'label', 'placeholder' ) as $property ) {
				$value = sanitize_text_field( (string) ( $billing[ $property ] ?? '' ) );
				if ( '' !== $value ) {
					$rule[ $property ] = $value;
				}
			}

			$priority = absint( $billing['priority'] ?? 0 );
			if ( $priority > 0 ) {
				$rule['priority'] = $priority;
			}

			if ( ! empty( $rule ) ) {
				$current = isset( $locale[ $address_key ] ) && is_array( $locale[ $address_key ] )
					? $locale[ $address_key ]
					: array();
				$locale[ $address_key ] = array_replace( $current, $rule );
			}
		}

		$phone = isset( $fields['billing_phone'] ) && is_array( $fields['billing_phone'] )
			? $fields['billing_phone']
			: array();
		$phone_rule = array();

		if ( 'yes' !== (string) ( $phone['enabled'] ?? 'yes' ) ) {
			$phone_rule['hidden']   = true;
			$phone_rule['required'] = false;
		} else {
			$required = sanitize_key( (string) ( $phone['required'] ?? 'inherit' ) );
			if ( in_array( $required, array( 'yes', 'no' ), true ) ) {
				$phone_rule['required'] = 'yes' === $required;
			}
		}

		foreach ( array( 'label', 'placeholder' ) as $property ) {
			$value = sanitize_text_field( (string) ( $phone[ $property ] ?? '' ) );
			if ( '' !== $value ) {
				$phone_rule[ $property ] = $value;
			}
		}

		$phone_priority = absint( $phone['priority'] ?? 0 );
		if ( $phone_priority > 0 ) {
			$phone_rule['priority'] = $phone_priority;
		}

		if ( ! empty( $phone_rule ) ) {
			$current_phone = isset( $locale['phone'] ) && is_array( $locale['phone'] )
				? $locale['phone']
				: array();
			$locale['phone'] = array_replace( $current_phone, $phone_rule );
		}

		return $locale;
	}

	/**
	 * Change the native Place order button text when configured.
	 *
	 * @param string $text Current text.
	 * @return string
	 */
	public function filter_order_button_text( string $text ): string {
		$settings = DefaultCheckoutSettings::get();
		$label = sanitize_text_field(
			(string) ( $settings['display']['order_button_label'] ?? '' )
		);

		return '' !== $label ? $label : $text;
	}

	/** Enqueue the field adapter only on Checkout Block pages. */
	public function enqueue_block_adapter(): void {
		if ( DefaultCheckoutSettings::payment_options_enabled() || DefaultCheckoutSettings::live_fraud_enabled() ) {
			return;
		}

		if ( ! $this->is_checkout_block_page() ) {
			return;
		}

		$style_path = 'src/css/frontend/default-checkout.css';
		$style_absolute = EILMO_CF_PATH . 'assets/' . $style_path;

		if ( file_exists( $style_absolute ) ) {
			wp_enqueue_style(
				'eilmo-cf-default-checkout',
				EILMO_CF_ASSETS_URL . $style_path,
				array(),
				(string) filemtime( $style_absolute )
			);
		}

		$script_path = 'src/js/frontend/default-checkout-block.js';
		$script_absolute = EILMO_CF_PATH . 'assets/' . $script_path;

		if ( ! file_exists( $script_absolute ) ) {
			return;
		}

		wp_enqueue_script(
			'eilmo-cf-default-checkout-block',
			EILMO_CF_ASSETS_URL . $script_path,
			array( 'wc-blocks-checkout', 'wp-dom-ready' ),
			(string) filemtime( $script_absolute ),
			true
		);

		$settings = DefaultCheckoutSettings::get();
		wp_localize_script(
			'eilmo-cf-default-checkout-block',
			'eilmoCFDefaultCheckoutBlock',
			array(
				'namespace'        => 'eilmo-checkout-flow',
				'fields'           => $this->client_field_settings(),
				'orderButtonLabel' => sanitize_text_field(
					(string) ( $settings['display']['order_button_label'] ?? '' )
				),
			)
		);
	}

	/**
	 * Get field settings.
	 *
	 * @return array<string, mixed>
	 */
	private function field_settings(): array {
		$settings = DefaultCheckoutSettings::get();
		$fields = isset( $settings['fields'] ) && is_array( $settings['fields'] )
			? $settings['fields']
			: array();

		if ( DefaultCheckoutSettings::live_fraud_enabled() ) {
			$current = isset( $fields['billing_phone'] ) && is_array( $fields['billing_phone'] )
				? $fields['billing_phone']
				: array();
			$fields['billing_phone'] = array_replace(
				$current,
				array(
					'enabled'  => 'yes',
					'required' => 'yes',
				)
			);
		}

		return $fields;
	}

	/**
	 * Build a client-safe field map.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function client_field_settings(): array {
		$client_fields = array();
		$fields = $this->field_settings();

		foreach ( DefaultCheckoutSettings::get_field_keys() as $field_key ) {
			$field = isset( $fields[ $field_key ] ) && is_array( $fields[ $field_key ] )
				? $fields[ $field_key ]
				: array();
			$required = sanitize_key( (string) ( $field['required'] ?? 'inherit' ) );
			$width = sanitize_key( (string) ( $field['width'] ?? 'inherit' ) );

			$client_fields[ $field_key ] = array(
				'enabled'     => 'yes' === (string) ( $field['enabled'] ?? 'yes' ) ? 'yes' : 'no',
				'label'       => sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
				'placeholder' => sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
				'required'    => in_array( $required, array( 'inherit', 'yes', 'no' ), true ) ? $required : 'inherit',
				'width'       => in_array( $width, array( 'inherit', 'full', 'half' ), true ) ? $width : 'inherit',
				'priority'    => min( 999, absint( $field['priority'] ?? 0 ) ),
			);
		}

		return $client_fields;
	}

	/** Resolve the native WooCommerce field section. */
	private function field_section( string $field_key ): string {
		if ( 0 === strpos( $field_key, 'billing_' ) ) {
			return 'billing';
		}

		return 'order_comments' === $field_key ? 'order' : '';
	}

	/** Deterministic half-width side, preserving normal first/last pairing. */
	private function half_width_class( string $field_key ): string {
		return false !== strpos( $field_key, 'last_name' ) ||
			false !== strpos( $field_key, 'email' ) ||
			false !== strpos( $field_key, 'state' ) ||
			false !== strpos( $field_key, 'postcode' )
				? 'form-row-last'
				: 'form-row-first';
	}

	/** Check feature master switch. */
	private function is_enabled(): bool {
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$general = isset( $stored['general'] ) && is_array( $stored['general'] )
			? $stored['general']
			: array();

		return 'yes' === (string) (
			$general['default_checkout_integration'] ??
			CheckoutSettings::get_defaults()['general']['default_checkout_integration'] ??
			'no'
		);
	}

	/** Check whether this is the live checkout frontend. */
	private function is_checkout_request(): bool {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return false;
		}
		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			return false;
		}
		return true;
	}

	/** Check whether the current checkout page contains Checkout Block. */
	private function is_checkout_block_page(): bool {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}

		if (
			function_exists( 'is_order_received_page' ) && is_order_received_page() ||
			function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page()
		) {
			return false;
		}

		global $post;

		return (bool) (
			$post &&
			function_exists( 'has_block' ) &&
			has_block( 'woocommerce/checkout', $post )
		);
	}
}
