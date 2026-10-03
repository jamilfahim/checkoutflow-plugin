<?php
/**
 * Delivery calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Delivery\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Discounts\Services\SpecialDiscountEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Handles delivery method availability and charge calculation.
 *
 * The browser is never trusted for delivery charges.
 * Frontend sends only the selected delivery method ID.
 *
 * This service resolves the method and calculates the
 * final delivery charge from server-side settings.
 */
final class DeliveryCalculator {

	/**
	 * Calculate delivery.
	 *
	 * @param string               $selected_method_id Selected method ID.
	 * @param float                $order_total        Current order total.
	 * @param array<string, mixed> $context            Calculation context.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		string $selected_method_id = '',
		float $order_total = 0.0,
		array $context = array()
	): array {

		$settings = $this->get_settings();

		if ( ! $this->is_enabled( $settings ) ) {
			return $this->get_disabled_result();
		}

		$order_total = $this->normalize_amount(
			$order_total
		);

		$methods = $this->get_available_methods(
			$order_total,
			$context
		);

		if ( empty( $methods ) ) {
			return $this->get_error_result(
				'no_delivery_methods',
				__(
					'No delivery methods are currently available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$selected_method_id = sanitize_key(
			$selected_method_id
		);

		/*
		 * If no method was explicitly selected,
		 * resolve the configured default.
		 */
		if ( '' === $selected_method_id ) {
			$selected_method_id =
				$this->get_default_method_id(
					$methods,
					$settings
				);
		}

		/*
		 * Delivery may be optional.
		 */
		if (
			'' === $selected_method_id &&
			'no' === (
				$settings['required'] ??
				'yes'
			)
		) {
			return $this->get_optional_result();
		}

		if ( '' === $selected_method_id ) {
			return $this->get_error_result(
				'delivery_method_required',
				__(
					'Please select a delivery method.',
					'eilmo-checkout-flow'
				)
			);
		}

		$method = $this->find_method(
			$selected_method_id,
			$methods
		);

		if ( null === $method ) {
			return $this->get_error_result(
				'invalid_delivery_method',
				__(
					'The selected delivery method is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$base_charge = $this->normalize_amount(
			$method['base_charge'] ??
				$method['charge'] ??
				0
		);

		$charge = $this->normalize_amount(
			$method['charge'] ??
				0
		);

		$is_free = $charge <= 0.0 &&
			$base_charge > 0.0;

		$result = array(
			'enabled' => true,
			'valid'   => true,

			'method_id' => (string) (
				$method['id'] ??
				''
			),

			'label' => (string) (
				$method['label'] ??
				''
			),

			'description' => (string) (
				$method['description'] ??
				''
			),

			'base_charge' => $base_charge,
			'charge'      => $charge,

			'is_free' => $is_free,

			'free_delivery_applied' =>
				! empty(
					$method['free_delivery_applied']
				),

			'order_total' => $order_total,

			'error_code' => '',
			'message'    => '',
		);

		/**
		 * Filters final delivery calculation.
		 *
		 * Server-side integrations may adjust the result.
		 *
		 * @param array<string, mixed> $result  Calculation result.
		 * @param array<string, mixed> $method  Delivery method.
		 * @param float                $order_total Order total.
		 * @param array<string, mixed> $context Calculation context.
		 */
		$result = apply_filters(
			'eilmo_cf/delivery/calculation',
			$result,
			$method,
			$order_total,
			$context
		);

		return is_array( $result )
			? $result
			: $this->get_error_result(
				'invalid_delivery_calculation',
				__(
					'Unable to calculate the delivery charge.',
					'eilmo-checkout-flow'
				)
			);
	}

	/**
	 * Get available delivery methods.
	 *
	 * Free-delivery configuration is applied here so
	 * frontend receives only server-approved charges.
	 *
	 * @param float                $order_total Order total.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_available_methods(
		float $order_total = 0.0,
		array $context = array()
	): array {

		$settings = $this->get_settings();

		if ( ! $this->is_enabled( $settings ) ) {
			return array();
		}

		$order_total = $this->normalize_amount(
			$order_total
		);

		$stored_methods = isset(
			$settings['methods']
		) && is_array( $settings['methods'] )
			? $settings['methods']
			: array();

		$methods = array();

		foreach ( $stored_methods as $method ) {

			if ( ! is_array( $method ) ) {
				continue;
			}

			if (
				'yes' !== (
					$method['enabled'] ??
					'no'
				)
			) {
				continue;
			}

			$id = sanitize_key(
				(string) (
					$method['id'] ??
					''
				)
			);

			$label = sanitize_text_field(
				(string) (
					$method['label'] ??
					''
				)
			);

			if (
				'' === $id ||
				'' === $label
			) {
				continue;
			}

			$charge = $this->normalize_amount(
				$method['charge'] ??
					0
			);

			$methods[] = array(
				'id'          => $id,
				'label'       => $label,
				'label_bn'    => sanitize_text_field( (string) ( $method['label_bn'] ?? '' ) ),
				'description' => wp_kses_post(
					(string) (
						$method['description'] ??
							''
					)
				),
				'description_bn' => wp_kses_post( (string) ( $method['description_bn'] ?? '' ) ),

				/*
				 * Keep the configured charge separately.
				 * Rules/free-delivery may change `charge`.
				 */
				'base_charge' => $charge,
				'charge'      => $charge,

				'enabled' => 'yes',

				'sort_order' => absint(
					$method['sort_order'] ??
						0
				),

				'free_delivery_applied' => false,
			);
		}

		$methods = $this->sort_methods(
			$methods
		);

		/*
		 * Free Delivery eligibility is resolved by the unified Special Discount
		 * engine. Delivery remains authoritative only for methods/rates and for
		 * applying an already-resolved reward to those methods.
		 */
		$reward_context = $context;
		if ( ! isset( $reward_context['product_total'] ) ) {
			$reward_context['product_total'] = $order_total;
		}

		$free_delivery_reward = array(
			'free_delivery' => ! empty( $context['coupon_free_delivery'] ),
			'free_delivery_method_id' => '',
			'free_delivery_hide_other_methods' => false,
		);

		if ( empty( $free_delivery_reward['free_delivery'] ) && class_exists( SpecialDiscountEngine::class ) ) {
			$engine_result = ( new SpecialDiscountEngine() )->evaluate( $reward_context );
			if ( is_array( $engine_result ) && ! empty( $engine_result['free_delivery'] ) ) {
				$free_delivery_reward = $engine_result;
			}
		}

		$methods = $this->apply_resolved_free_delivery(
			$methods,
			$free_delivery_reward
		);

		/*
		 * Full Payment can independently unlock free delivery. This is evaluated
		 * after coupon / Special Discount rewards so the most permissive result
		 * wins without duplicating delivery-rule logic. All currently available
		 * Eilmo delivery methods become free while Full Payment is selected.
		 */
		if ( $this->full_payment_free_delivery_active( $context ) ) {
			$methods = $this->apply_resolved_free_delivery(
				$methods,
				array(
					'free_delivery'                    => true,
					'free_delivery_method_id'          => '',
					'free_delivery_hide_other_methods' => false,
				)
			);
		}

		/**
		 * Filters available delivery methods.
		 *
		 * This filter remains available for server-side
		 * delivery-method customization.
		 *
		 * @param array<int, array<string, mixed>> $methods     Methods.
		 * @param float                            $order_total Order total.
		 * @param array<string, mixed>             $context     Context.
		 * @param array<string, mixed>             $settings    Settings.
		 */
		$methods = apply_filters(
			'eilmo_cf/delivery/available_methods',
			$methods,
			$order_total,
			$context,
			$settings
		);

		if ( ! is_array( $methods ) ) {
			return array();
		}

		return array_values(
			$methods
		);
	}

	/**
	 * Get configured default delivery method.
	 *
	 * If no explicit default is configured, the first
	 * available method becomes the default.
	 *
	 * @param float                $order_total Order total.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return string
	 */
	public function get_default_method(
		float $order_total = 0.0,
		array $context = array()
	): string {

		$settings = $this->get_settings();

		$methods = $this->get_available_methods(
			$order_total,
			$context
		);

		return $this->get_default_method_id(
			$methods,
			$settings
		);
	}

	/**
	 * Get one available method.
	 *
	 * @param string               $method_id   Method ID.
	 * @param float                $order_total Order total.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_method(
		string $method_id,
		float $order_total = 0.0,
		array $context = array()
	): ?array {

		$method_id = sanitize_key(
			$method_id
		);

		if ( '' === $method_id ) {
			return null;
		}

		$methods = $this->get_available_methods(
			$order_total,
			$context
		);

		return $this->find_method(
			$method_id,
			$methods
		);
	}

	/**
	 * Determine whether delivery is enabled.
	 *
	 * @return bool
	 */
	public function delivery_is_enabled(): bool {

		return $this->is_enabled(
			$this->get_settings()
		);
	}

	/**
	 * Determine whether delivery selection is required.
	 *
	 * @return bool
	 */
	public function delivery_is_required(): bool {

		$settings = $this->get_settings();

		return $this->is_enabled( $settings ) &&
			'yes' === (
				$settings['required'] ??
				'yes'
			);
	}

	/**
	 * Apply an already-resolved Free Delivery reward to configured methods.
	 *
	 * No condition/threshold logic lives here. SpecialDiscountEngine is the
	 * only source of truth for campaign Free Delivery eligibility.
	 *
	 * @param array<int,array<string,mixed>> $methods Methods.
	 * @param array<string,mixed>            $reward Resolved reward.
	 * @return array<int,array<string,mixed>>
	 */
	private function apply_resolved_free_delivery( array $methods, array $reward ): array {
		if ( empty( $reward['free_delivery'] ) ) {
			return $methods;
		}

		$method_id = sanitize_key( (string) ( $reward['free_delivery_method_id'] ?? '' ) );
		$hide_other = ! empty( $reward['free_delivery_hide_other_methods'] );
		$found = false;

		foreach ( $methods as &$method ) {
			if ( ! is_array( $method ) ) {
				continue;
			}
			$current_id = sanitize_key( (string) ( $method['id'] ?? '' ) );
			if ( '' !== $method_id && $current_id !== $method_id ) {
				continue;
			}
			if ( ! isset( $method['base_charge'] ) ) {
				$method['base_charge'] = $this->normalize_amount( $method['charge'] ?? 0 );
			}
			$method['charge'] = 0.0;
			$method['free_delivery_applied'] = true;
			$found = true;
		}
		unset( $method );

		if ( ! $found || '' === $method_id || ! $hide_other ) {
			return $methods;
		}

		return array_values(
			array_filter(
				$methods,
				static function ( array $method ) use ( $method_id ): bool {
					return $method_id === sanitize_key( (string) ( $method['id'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * Resolve default delivery method ID.
	 *
	 * @param array<int, array<string, mixed>> $methods  Available methods.
	 * @param array<string, mixed>             $settings Delivery settings.
	 *
	 * @return string
	 */
	private function get_default_method_id(
		array $methods,
		array $settings
	): string {

		if ( empty( $methods ) ) {
			return '';
		}

		$configured_default = sanitize_key(
			(string) (
				$settings['default_method'] ??
					''
			)
		);

		if ( '' !== $configured_default ) {

			$method = $this->find_method(
				$configured_default,
				$methods
			);

			if ( null !== $method ) {
				return $configured_default;
			}
		}

		/*
		 * Automatic / First Available.
		 */
		$first = reset(
			$methods
		);

		if (
			! is_array( $first ) ||
			empty( $first['id'] )
		) {
			return '';
		}

		return sanitize_key(
			(string) $first['id']
		);
	}

	/**
	 * Find delivery method by ID.
	 *
	 * @param string                            $method_id Method ID.
	 * @param array<int, array<string, mixed>> $methods   Methods.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_method(
		string $method_id,
		array $methods
	): ?array {

		foreach ( $methods as $method ) {

			if (
				! is_array( $method ) ||
				! isset( $method['id'] )
			) {
				continue;
			}

			if (
				$method_id ===
				(string) $method['id']
			) {
				return $method;
			}
		}

		return null;
	}

	/**
	 * Sort methods by configured sort order.
	 *
	 * @param array<int, array<string, mixed>> $methods Methods.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_methods(
		array $methods
	): array {

		usort(
			$methods,
			static function (
				array $first,
				array $second
			): int {

				$first_order = isset(
					$first['sort_order']
				)
					? (int) $first['sort_order']
					: 0;

				$second_order = isset(
					$second['sort_order']
				)
					? (int) $second['sort_order']
					: 0;

				return $first_order
					<=> $second_order;
			}
		);

		return array_values(
			$methods
		);
	}

	/**
	 * Whether Full Payment currently unlocks free delivery.
	 *
	 * @param array<string,mixed> $context Calculation context.
	 * @return bool
	 */
	private function full_payment_free_delivery_active( array $context ): bool {
		if ( 'full' !== sanitize_key( (string) ( $context['payment_type'] ?? '' ) ) ) {
			return false;
		}

		$defaults = CheckoutSettings::get_defaults();
		$stored   = get_option( CheckoutSettings::OPTION_NAME, array() );
		$merged   = array_replace_recursive( $defaults, is_array( $stored ) ? $stored : array() );
		$full     = isset( $merged['discounts']['full_payment'] ) && is_array( $merged['discounts']['full_payment'] )
			? $merged['discounts']['full_payment']
			: array();

		return 'yes' === (string) ( $full['free_delivery'] ?? 'no' );
	}

	/**
	 * Load delivery settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults = CheckoutSettings::get_defaults();

		$default_delivery = isset(
			$defaults['delivery']
		) && is_array( $defaults['delivery'] )
			? $defaults['delivery']
			: array();

		$stored = get_option(
			CheckoutSettings::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$delivery = isset(
			$stored['delivery']
		) && is_array( $stored['delivery'] )
			? $stored['delivery']
			: array();

		$settings = array_replace_recursive(
			$default_delivery,
			$delivery
		);

		/*
		 * Collections must not be recursively merged.
		 */
		$settings['methods'] = isset(
			$delivery['methods']
		) && is_array( $delivery['methods'] )
			? $delivery['methods']
			: (
				$default_delivery['methods'] ??
				array()
			);

		/**
		 * Filters delivery settings used by the calculator.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings = apply_filters(
			'eilmo_cf/delivery/settings',
			$settings
		);

		return is_array( $settings )
			? $settings
			: $default_delivery;
	}

	/**
	 * Determine whether delivery is enabled.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return bool
	 */
	private function is_enabled(
		array $settings
	): bool {

		return 'yes' === (
			$settings['enabled'] ??
				'no'
		);
	}

	/**
	 * Normalize monetary value.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return float
	 */
	private function normalize_amount(
		$amount
	): float {

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount = wc_format_decimal(
				$amount,
				false
			);
		}

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$amount = max(
			0.0,
			(float) $amount
		);

		$decimals = function_exists(
			'wc_get_price_decimals'
		)
			? wc_get_price_decimals()
			: 2;

		return round(
			$amount,
			$decimals
		);
	}

	/**
	 * Get result when delivery is disabled.
	 *
	 * @return array<string, mixed>
	 */
	private function get_disabled_result(): array {

		return array(
			'enabled' => false,
			'valid'   => true,

			'method_id'  => '',
			'label'      => '',
			'description' => '',

			'base_charge' => 0.0,
			'charge'      => 0.0,

			'is_free'               => false,
			'free_delivery_applied' => false,

			'order_total' => 0.0,

			'error_code' => '',
			'message'    => '',
		);
	}

	/**
	 * Get result when delivery is optional and
	 * no method has been selected.
	 *
	 * @return array<string, mixed>
	 */
	private function get_optional_result(): array {

		return array(
			'enabled' => true,
			'valid'   => true,

			'method_id'  => '',
			'label'      => '',
			'description' => '',

			'base_charge' => 0.0,
			'charge'      => 0.0,

			'is_free'               => false,
			'free_delivery_applied' => false,

			'order_total' => 0.0,

			'error_code' => '',
			'message'    => '',
		);
	}

	/**
	 * Get calculation error result.
	 *
	 * @param string $error_code Error code.
	 * @param string $message    Message.
	 *
	 * @return array<string, mixed>
	 */
	private function get_error_result(
		string $error_code,
		string $message
	): array {

		return array(
			'enabled' => true,
			'valid'   => false,

			'method_id'  => '',
			'label'      => '',
			'description' => '',

			'base_charge' => 0.0,
			'charge'      => 0.0,

			'is_free'               => false,
			'free_delivery_applied' => false,

			'order_total' => 0.0,

			'error_code' => sanitize_key(
				$error_code
			),

			'message' => sanitize_text_field(
				$message
			),
		);
	}
}