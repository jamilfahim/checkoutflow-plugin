<?php
/**
 * Meta order eligibility.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a WooCommerce order came from a
 * customer-facing website checkout and is eligible for
 * Purchase tracking.
 *
 * Admin-created, REST-imported and other programmatic
 * orders are excluded by default.
 */
final class MetaOrderEligibility {

	/**
	 * Eilmo order source meta.
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Eilmo customer checkout source value.
	 */
	private const CREATED_VIA_EILMO =
		'eilmo-checkout-flow';

	/**
	 * Determine whether an order came from a website checkout.
	 *
	 * Supported by default:
	 *
	 * - Eilmo Checkout Flow.
	 * - WooCommerce classic checkout.
	 * - WooCommerce Checkout Block / Store API.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function is_website_checkout_order(
		WC_Order $order
	): bool {

		$eilmo_created_via =
			sanitize_key(
				(string) $order->get_meta(
					self::META_CREATED_VIA,
					true
				)
			);

		$woocommerce_created_via =
			sanitize_key(
				(string) $order->get_created_via()
			);

		$is_website_order =
			self::CREATED_VIA_EILMO ===
				$eilmo_created_via ||
			in_array(
				$woocommerce_created_via,
				array(
					'checkout',
					'store-api',
				),
				true
			);

		/**
		 * Filters whether an order is considered a website
		 * checkout order for Meta tracking.
		 *
		 * Use this to support another trusted customer-facing
		 * checkout integration without enabling admin/REST orders.
		 *
		 * @param bool     $is_website_order Current decision.
		 * @param WC_Order $order            Order.
		 */
		return (bool) apply_filters(
			'eilmo_cf/meta_tracking/is_website_order',
			$is_website_order,
			$order
		);
	}

	/**
	 * Determine whether the current order state represents
	 * a placed/confirmed website order for Purchase tracking.
	 *
	 * Pending is intentionally excluded because online
	 * gateways may still be waiting for payment.
	 *
	 * On-hold is included for legitimate customer checkout
	 * flows such as COD, BACS, cheque and Eilmo manual payment.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function is_purchase_state_eligible(
		WC_Order $order
	): bool {

		$status =
			sanitize_key(
				(string) $order->get_status()
			);

		$is_eligible =
			in_array(
				$status,
				array(
					'on-hold',
					'processing',
					'completed',
				),
				true
			);

		/**
		 * Filters whether an order status is eligible for a
		 * Meta Purchase event.
		 *
		 * @param bool     $is_eligible Current decision.
		 * @param WC_Order $order       Order.
		 */
		return (bool) apply_filters(
			'eilmo_cf/meta_tracking/purchase_state_eligible',
			$is_eligible,
			$order
		);
	}
}
