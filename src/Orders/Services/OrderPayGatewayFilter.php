<?php
/**
 * Order Pay gateway filter.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Services;

use EilmoCheckout\Contracts\RegistrableInterface;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Restricts WooCommerce Order Pay to the payment
 * gateway selected during Eilmo checkout.
 */
final class OrderPayGatewayFilter implements RegistrableInterface {

	/**
	 * Eilmo order marker.
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Payment source.
	 */
	private const META_PAYMENT_SOURCE =
		'_eilmo_cf_payment_source';

	/**
	 * WooCommerce gateway ID.
	 */
	private const META_GATEWAY_ID =
		'_eilmo_cf_payment_gateway_id';

	/**
	 * Eilmo order source value.
	 */
	private const CREATED_VIA =
		'eilmo-checkout-flow';

	/**
	 * WooCommerce gateway payment source.
	 */
	private const PAYMENT_SOURCE =
		'woocommerce_gateway';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * Run late so normal WooCommerce and gateway
		 * availability checks happen first.
		 *
		 * Eilmo then restricts the final list to the
		 * gateway originally selected by the customer.
		 */
		add_filter(
			'woocommerce_available_payment_gateways',
			array(
				$this,
				'filter_gateways',
			),
			9999,
			1
		);

		/*
		 * Keep WooCommerce session selection aligned
		 * with the Eilmo order before the payment form
		 * is rendered.
		 */
		add_action(
			'wp',
			array(
				$this,
				'sync_chosen_gateway',
			),
			50
		);
	}

	/**
	 * Restrict available gateways on Order Pay.
	 *
	 * Normal checkout, My Account and other
	 * WooCommerce pages are left untouched.
	 *
	 * @param array<string, object> $gateways Available gateways.
	 *
	 * @return array<string, object>
	 */
	public function filter_gateways(
		array $gateways
	): array {

		if (
			! $this->is_order_pay_request()
		) {
			return $gateways;
		}

		$order =
			$this->get_current_order();

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_gateway_order(
				$order
			)
		) {
			return $gateways;
		}

		$gateway_id =
			$this->get_selected_gateway_id(
				$order
			);

		/**
		 * Filters the WooCommerce gateway ID allowed
		 * on an Eilmo Order Pay page.
		 *
		 * @param string   $gateway_id Gateway ID.
		 * @param WC_Order $order      Order.
		 */
		$gateway_id =
			sanitize_key(
				(string) apply_filters(
					'eilmo_cf/orders/order_pay_gateway_id',
					$gateway_id,
					$order
				)
			);

		/*
		 * Defensive fallback.
		 *
		 * An Eilmo WooCommerce gateway order should
		 * always have a gateway ID, but do not break
		 * payment if old order metadata is incomplete.
		 */
		if (
			'' ===
				$gateway_id
		) {
			return $gateways;
		}

		/*
		 * IMPORTANT:
		 *
		 * Do not fall back to another gateway when the
		 * selected gateway is unavailable.
		 *
		 * Switching gateways here could make the stored
		 * Eilmo payment method and the actual payment
		 * method inconsistent.
		 */
		if (
			! isset(
				$gateways[
					$gateway_id
				]
			)
		) {
			return array();
		}

		$gateway =
			$gateways[
				$gateway_id
			];

		if (
			! is_object(
				$gateway
			)
		) {
			return array();
		}

		return array(
			$gateway_id =>
				$gateway,
		);
	}

	/**
	 * Synchronize WooCommerce session gateway.
	 *
	 * @return void
	 */
	public function sync_chosen_gateway(): void {

		if (
			! $this->is_order_pay_request()
		) {
			return;
		}

		$order =
			$this->get_current_order();

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_gateway_order(
				$order
			)
		) {
			return;
		}

		$gateway_id =
			$this->get_selected_gateway_id(
				$order
			);

		if (
			'' ===
				$gateway_id
		) {
			return;
		}

		if (
			! function_exists(
				'WC'
			) ||
			! WC() ||
			! WC()->session
		) {
			return;
		}

		$current =
			sanitize_key(
				(string) WC()
					->session
					->get(
						'chosen_payment_method'
					)
			);

		if (
			$current ===
				$gateway_id
		) {
			return;
		}

		WC()
			->session
			->set(
				'chosen_payment_method',
				$gateway_id
			);

		if (
			method_exists(
				WC()->session,
				'save_data'
			)
		) {
			WC()
				->session
				->save_data();
		}
	}

	/**
	 * Determine whether current request is the
	 * WooCommerce Order Pay endpoint.
	 *
	 * @return bool
	 */
	private function is_order_pay_request(): bool {

		if (
			function_exists(
				'is_checkout_pay_page'
			) &&
			is_checkout_pay_page()
		) {
			return true;
		}

		if (
			function_exists(
				'is_wc_endpoint_url'
			) &&
			is_wc_endpoint_url(
				'order-pay'
			)
		) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve current Order Pay order.
	 *
	 * @return WC_Order|null
	 */
	private function get_current_order(): ?WC_Order {

		$order_id =
			absint(
				get_query_var(
					'order-pay'
				)
			);

		/*
		 * Defensive endpoint fallback.
		 */
		if (
			$order_id <= 0 &&
			isset(
				$GLOBALS['wp']
			) &&
			is_object(
				$GLOBALS['wp']
			) &&
			isset(
				$GLOBALS['wp']
					->query_vars[
						'order-pay'
					]
			)
		) {
			$order_id =
				absint(
					$GLOBALS['wp']
						->query_vars[
							'order-pay'
						]
				);
		}

		if (
			$order_id <= 0
		) {
			return null;
		}

		$order =
			wc_get_order(
				$order_id
			);

		if (
			! $order instanceof
				WC_Order
		) {
			return null;
		}

		/*
		 * When the standard order key exists in the
		 * request, make sure it belongs to this order.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce Order Pay navigation parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
		if ( isset( $_GET['key'] ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce Order Pay navigation parameter.
			$request_key =
				wc_clean(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						(string) $_GET[
							'key'
						]
					)
				);

			if (
				'' !==
					$request_key &&
				! hash_equals(
					(string) $order
						->get_order_key(),
					(string) $request_key
				)
			) {
				return null;
			}
		}

		return $order;
	}

	/**
	 * Determine whether order is an Eilmo order
	 * using a native WooCommerce gateway.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function is_eilmo_gateway_order(
		WC_Order $order
	): bool {

		$created_via =
			sanitize_text_field(
				(string) $order
					->get_meta(
						self::META_CREATED_VIA,
						true
					)
			);

		if (
			self::CREATED_VIA !==
				$created_via
		) {
			return false;
		}

		$payment_source =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_SOURCE,
						true
					)
			);

		return (
			self::PAYMENT_SOURCE ===
				$payment_source
		);
	}

	/**
	 * Get gateway selected during Eilmo checkout.
	 *
	 * Eilmo gateway metadata is preferred.
	 * WooCommerce order payment method is retained
	 * as a defensive fallback for older orders.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_selected_gateway_id(
		WC_Order $order
	): string {

		$gateway_id =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_GATEWAY_ID,
						true
					)
			);

		if (
			'' !==
				$gateway_id
		) {
			return $gateway_id;
		}

		return sanitize_key(
			(string) $order
				->get_payment_method()
		);
	}
}