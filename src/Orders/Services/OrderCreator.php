<?php
/**
 * Order creator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\AdvancePayment\Services\AdvanceCalculator;
use EilmoCheckout\ComboOffers\Services\ComboOfferCalculator;
use EilmoCheckout\Coupons\Services\CouponCalculator;
use EilmoCheckout\Coupons\Services\CustomerIdentity;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Delivery\Services\DeliveryCalculator;
use EilmoCheckout\Discounts\Services\AutomaticDiscountCalculator;
use EilmoCheckout\OrderBumps\Services\OrderBumpCalculator;
use EilmoCheckout\Payment\Services\PaymentProof;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates WooCommerce orders from validated
 * Eilmo Checkout Flow data.
 *
 * Important:
 *
 * - Frontend totals are never trusted.
 * - Product prices are loaded from WooCommerce.
 * - Combo Offer products and pricing are recalculated server-side.
 * - Order Bump products and pricing are recalculated server-side.
 * - Discounts are recalculated server-side.
 * - Coupons are revalidated server-side.
 * - Delivery is recalculated server-side.
 * - Cash on Delivery / Advance / Full payment is recalculated server-side.
 * - Payment gateway processing is NOT performed here.
 *
 * OrderAjax is responsible for the final payment step.
 */
final class OrderCreator {

	/**
	 * Eilmo order source meta.
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Product total meta.
	 */
	private const META_PRODUCT_TOTAL =
		'_eilmo_cf_product_total';

	/**
	 * Selected Combo Offers meta.
	 *
	 * Stores the authoritative Combo snapshot used
	 * to create this order.
	 */
	private const META_COMBO_OFFERS =
		'_eilmo_cf_combo_offers';

	/**
	 * Combo Offer discount meta.
	 */
	private const META_COMBO_DISCOUNT =
		'_eilmo_cf_combo_discount';

	/**
	 * Selected Order Bumps meta.
	 *
	 * Stores the authoritative Order Bump snapshot
	 * used to create this order.
	 */
	private const META_ORDER_BUMPS =
		'_eilmo_cf_order_bumps';

	/**
	 * Order Bump discount meta.
	 */
	private const META_ORDER_BUMP_DISCOUNT =
		'_eilmo_cf_order_bump_discount';

	/**
	 * Automatic discount meta.
	 */
	private const META_AUTOMATIC_DISCOUNT =
		'_eilmo_cf_automatic_discount';

	/**
	 * Applied Special Discount snapshots meta.
	 */
	private const META_APPLIED_SPECIAL_DISCOUNTS =
		'_eilmo_cf_applied_special_discounts';

	/**
	 * Coupon discount meta.
	 */
	private const META_COUPON_DISCOUNT =
		'_eilmo_cf_coupon_discount';

	/**
	 * Applied coupon code meta.
	 *
	 * This is stored only when the coupon produced an
	 * actual checkout benefit.
	 */
	private const META_COUPON_CODE =
		'_eilmo_cf_coupon_code';

	/**
	 * Applied coupon source meta.
	 *
	 * Internal values remain implementation keys:
	 *
	 * - eilmo       = Checkout Flow Coupon.
	 * - woocommerce = WooCommerce Coupon.
	 */
	private const META_COUPON_SOURCE =
		'_eilmo_cf_coupon_source';

	/**
	 * Applied coupon type meta.
	 */
	private const META_COUPON_TYPE =
		'_eilmo_cf_coupon_type';

	/**
	 * Coupon free-delivery benefit meta.
	 */
	private const META_COUPON_FREE_DELIVERY =
		'_eilmo_cf_coupon_free_delivery';

	/**
	 * Delivery method ID meta.
	 */
	private const META_DELIVERY_METHOD =
		'_eilmo_cf_delivery_method';

	/**
	 * Delivery charge meta.
	 */
	private const META_DELIVERY_CHARGE =
		'_eilmo_cf_delivery_charge';

	/**
	 * Full-payment discount meta.
	 */
	private const META_FULL_PAYMENT_DISCOUNT =
		'_eilmo_cf_full_payment_discount';

	/**
	 * Grand total meta.
	 */
	private const META_GRAND_TOTAL =
		'_eilmo_cf_grand_total';

	/**
	 * Payment type meta.
	 */
	private const META_PAYMENT_TYPE =
		'_eilmo_cf_payment_type';

	/**
	 * Advance amount meta.
	 */
	private const META_ADVANCE_AMOUNT =
		'_eilmo_cf_advance_amount';

	/**
	 * Advance rule ID meta.
	 */
	private const META_ADVANCE_RULE_ID =
		'_eilmo_cf_advance_rule_id';

	/**
	 * Advance rule type meta.
	 */
	private const META_ADVANCE_RULE_TYPE =
		'_eilmo_cf_advance_rule_type';

	/**
	 * Pay-now amount meta.
	 */
	private const META_PAY_NOW =
		'_eilmo_cf_pay_now';

	/**
	 * Remaining-due amount meta.
	 */
	private const META_REMAINING_DUE =
		'_eilmo_cf_remaining_due';

	/**
	 * Eilmo selected payment method.
	 */
	private const META_PAYMENT_METHOD =
		'_eilmo_cf_payment_method';

	/**
	 * Eilmo selected payment method key.
	 */
	private const META_PAYMENT_METHOD_KEY =
		'_eilmo_cf_payment_method_key';

	/**
	 * Payment source.
	 */
	private const META_PAYMENT_SOURCE =
		'_eilmo_cf_payment_source';

	/**
	 * WooCommerce gateway ID.
	 */
	private const META_PAYMENT_GATEWAY_ID =
		'_eilmo_cf_payment_gateway_id';

	/**
	 * Manual payment transaction ID.
	 */
	private const META_PAYMENT_TRANSACTION_ID =
		'_eilmo_cf_payment_transaction_id';

	/**
	 * Eilmo payment status.
	 */
	private const META_PAYMENT_STATUS =
		'_eilmo_cf_payment_status';

	/**
	 * Combo component marker item meta.
	 */
	private const ITEM_META_COMBO_COMPONENT =
		'_eilmo_cf_combo_component';

	/**
	 * Combo ID item meta.
	 */
	private const ITEM_META_COMBO_ID =
		'_eilmo_cf_combo_id';

	/**
	 * Combo title item meta.
	 */
	private const ITEM_META_COMBO_TITLE =
		'_eilmo_cf_combo_title';

	/**
	 * Per-line Combo discount meta.
	 */
	private const ITEM_META_COMBO_DISCOUNT =
		'_eilmo_cf_combo_discount_amount';

	/**
	 * Combo Automatic Discount compatibility meta.
	 */
	private const ITEM_META_COMBO_APPLY_AUTOMATIC =
		'_eilmo_cf_combo_apply_automatic_discount';

	/**
	 * Combo Coupon compatibility meta.
	 */
	private const ITEM_META_COMBO_APPLY_COUPON =
		'_eilmo_cf_combo_apply_coupon';

	/**
	 * Combo Full Payment Discount compatibility meta.
	 */
	private const ITEM_META_COMBO_APPLY_FULL_PAYMENT =
		'_eilmo_cf_combo_apply_full_payment_discount';

	/**
	 * Order Bump marker item meta.
	 */
	private const ITEM_META_ORDER_BUMP =
		'_eilmo_cf_order_bump';

	/**
	 * Order Bump ID item meta.
	 */
	private const ITEM_META_ORDER_BUMP_ID =
		'_eilmo_cf_order_bump_id';

	/**
	 * Order Bump title item meta.
	 */
	private const ITEM_META_ORDER_BUMP_TITLE =
		'_eilmo_cf_order_bump_title';

	/**
	 * Per-line Order Bump discount meta.
	 */
	private const ITEM_META_ORDER_BUMP_DISCOUNT =
		'_eilmo_cf_order_bump_discount_amount';

	/**
	 * Order Bump Automatic Discount compatibility meta.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC =
		'_eilmo_cf_order_bump_apply_automatic_discount';

	/**
	 * Order Bump Coupon compatibility meta.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_COUPON =
		'_eilmo_cf_order_bump_apply_coupon';

	/**
	 * Order Bump Full Payment Discount compatibility meta.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT =
		'_eilmo_cf_order_bump_apply_full_payment_discount';

	/**
	 * Per-line Automatic Discount meta.
	 */
	private const ITEM_META_AUTOMATIC_DISCOUNT =
		'_eilmo_cf_automatic_discount_amount';

	/**
	 * Per-line custom Coupon Discount meta.
	 */
	private const ITEM_META_COUPON_DISCOUNT =
		'_eilmo_cf_coupon_discount_amount';

	/**
	 * Per-line Full Payment Discount meta.
	 */
	private const ITEM_META_FULL_PAYMENT_DISCOUNT =
		'_eilmo_cf_full_payment_discount_amount';

	/**
	 * Create order.
	 *
	 * Expected input is the normalized result returned
	 * by OrderValidator::validate().
	 *
	 * @param array<string, mixed> $data Validated data.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function create(
		array $data
	) {

		if (
			! function_exists(
				'wc_create_order'
			)
		) {
			return new WP_Error(
				'woocommerce_unavailable',
				__(
					'WooCommerce is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$items =
			isset( $data['items'] ) &&
			is_array( $data['items'] )
				? $data['items']
				: array();

		$combo_offers =
			isset( $data['combo_offers'] ) &&
			is_array( $data['combo_offers'] )
				? $data['combo_offers']
				: array();

		$order_bumps =
			isset( $data['order_bumps'] ) &&
			is_array( $data['order_bumps'] )
				? $data['order_bumps']
				: array();

		if ( empty( $items ) ) {
			return new WP_Error(
				'empty_order',
				__(
					'No products were selected.',
					'eilmo-checkout-flow'
				)
			);
		}

		$customer =
			isset( $data['customer'] ) &&
			is_array( $data['customer'] )
				? $data['customer']
				: array();

		$payment =
			isset( $data['payment'] ) &&
			is_array( $data['payment'] )
				? $data['payment']
				: array();

		$advance_payment =
			isset( $data['advance_payment'] ) &&
			is_array( $data['advance_payment'] )
				? $data['advance_payment']
				: array();

		$fraud_check =
			isset( $data['fraud_check'] ) && is_array( $data['fraud_check'] )
				? $data['fraud_check']
				: array();

		$delivery =
			isset( $data['delivery'] ) &&
			is_array( $data['delivery'] )
				? $data['delivery']
				: array();

		$coupon_code =
			$this->normalize_coupon_code(
				$data['coupon_code'] ??
					''
			);

		$customer_id =
			get_current_user_id();

		$order =
			wc_create_order(
				array(
					'customer_id' =>
						$customer_id,

					'status' =>
						'pending',
				)
			);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if (
			! $order instanceof
				WC_Order
		) {
			return new WP_Error(
				'order_creation_failed',
				__(
					'The order could not be created.',
					'eilmo-checkout-flow'
				)
			);
		}

		try {

			$this->set_customer_data(
				$order,
				$customer
			);

			$this->add_products(
				$order,
				$items
			);

			$this->add_combo_products(
				$order,
				$combo_offers
			);

			$this->add_order_bump_products(
				$order,
				$order_bumps
			);

			/*
			 * Establish initial WooCommerce totals
			 * before applying Eilmo calculations.
			 */
			$order->calculate_totals(
				true
			);

			$product_context =
				$this->build_product_context(
					$order
				);

			$product_total =
				$this->normalize_amount(
					$product_context[
						'product_total'
					] ??
						0
				);

			if ( $product_total <= 0 ) {
				$this->throw_order_exception(
					__(
						'The selected products do not have a valid order total.',
						'eilmo-checkout-flow'
					)
				);
			}

			/*
			 * --------------------------------------------------
			 * Combo Offer pricing
			 * --------------------------------------------------
			 *
			 * Combo component products are real WooCommerce
			 * line items. Their regular subtotals remain intact,
			 * while the authoritative Combo discount is applied
			 * to those Combo line-item totals only.
			 */
			$combo_result =
				$this->apply_combo_pricing(
					$order,
					$combo_offers
				);

			$combo_regular_total =
				$this->normalize_amount(
					$combo_result[
						'regular_total'
					] ??
						0
				);

			$combo_discount =
				$this->normalize_amount(
					$combo_result[
						'discount'
					] ??
						0
				);

			$combo_total =
				$this->normalize_amount(
					$combo_result[
						'combo_total'
					] ??
						$combo_regular_total
				);

			if ( $combo_discount > 0 ) {
				$order->calculate_totals(
					true
				);
			}

			/*
			 * --------------------------------------------------
			 * Order Bump pricing
			 * --------------------------------------------------
			 *
			 * Order Bumps are stored as their own WooCommerce
			 * line items. Their configured pricing is applied only
			 * to those dedicated Order Bump lines.
			 */
			$order_bump_result =
				$this->apply_order_bump_pricing(
					$order,
					$order_bumps
				);

			$order_bump_regular_total =
				$this->normalize_amount(
					$order_bump_result[
						'regular_total'
					] ??
						0
				);

			$order_bump_discount =
				$this->normalize_amount(
					$order_bump_result[
						'discount'
					] ??
						0
				);

			$order_bump_total =
				$this->normalize_amount(
					$order_bump_result[
						'bump_total'
					] ??
						$order_bump_regular_total
				);

			if ( $order_bump_discount > 0 ) {
				$order->calculate_totals(
					true
				);
			}

			/*
			 * Automatic Discount and Coupon eligibility are now
			 * calculated from source-aware line-item collections.
			 *
			 * Normal product:
			 * - Automatic: yes.
			 * - Coupon: yes.
			 *
			 * Combo product:
			 * - Automatic: controlled by saved Combo settings.
			 * - Coupon: controlled by saved Combo settings.
			 *
			 * Order Bump:
			 * - Automatic: controlled by saved bump settings.
			 * - Coupon: controlled by saved bump settings.
			 */
			$discount_base_product_total =
				$this->get_current_product_total(
					$order
				);

			$automatic_discount_base_product_total =
				$this->get_automatic_discount_product_total(
					$order
				);

			$automatic_discount_context =
				$this->build_line_item_product_context(
					$this->get_automatic_discount_line_items( $order ),
					false
				);
			$automatic_discount_context['product_total'] =
				$automatic_discount_base_product_total;
			$automatic_discount_context['special_discount_scope'] =
				sanitize_key( (string) ( $data['special_discount_scope'] ?? 'all' ) );
			$automatic_discount_context['special_discount_ids'] =
				isset( $data['special_discount_ids'] ) && is_array( $data['special_discount_ids'] )
					? array_values( array_unique( array_filter( array_map( 'sanitize_key', $data['special_discount_ids'] ) ) ) )
					: array();
			$automatic_discount_context['current_checkout_product_ids'] =
				array_values( array_unique( array_filter( array_map( static function ( $item ) {
					return is_array( $item ) ? absint( $item['product_id'] ?? 0 ) : 0;
				}, isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() ) ) ) );

			/*
			 * Conditions see the complete purchased product context (normal + Combo),
			 * while monetary discount allocation still uses the historical automatic-
			 * discount compatibility line set above. Reward products themselves are
			 * excluded to avoid recursive Free Gift qualification.
			 */
			$special_discount_condition_context =
				$this->build_line_item_product_context(
					$this->get_special_discount_condition_line_items( $order ),
					false
				);
			$special_discount_condition_context['special_discount_scope'] =
				$automatic_discount_context['special_discount_scope'];
			$special_discount_condition_context['special_discount_ids'] =
				$automatic_discount_context['special_discount_ids'];
			$special_discount_condition_context['current_checkout_product_ids'] =
				$automatic_discount_context['current_checkout_product_ids'];
			$automatic_discount_context['condition_context'] =
				$special_discount_condition_context;

			$discount_product_context =
				$this->build_line_item_product_context(
					$this->get_coupon_discount_line_items(
						$order
					),
					false
				);

			$discount_product_context[
				'original_product_total'
			] =
				$product_total;

			$discount_product_context[
				'all_payable_product_total'
			] =
				$discount_base_product_total;

			$discount_product_context[
				'combo_regular_total'
			] =
				$combo_regular_total;

			$discount_product_context[
				'combo_discount'
			] =
				$combo_discount;

			$discount_product_context[
				'combo_total'
			] =
				$combo_total;

			$discount_product_context[
				'order_bump_regular_total'
			] =
				$order_bump_regular_total;

			$discount_product_context[
				'order_bump_discount'
			] =
				$order_bump_discount;

			$discount_product_context[
				'order_bump_total'
			] =
				$order_bump_total;

			$settings =
				$this->get_settings();

			$payment_type =
				$this->get_payment_type(
					$advance_payment
				);

			/*
			 * --------------------------------------------------
			 * Automatic Discount
			 * --------------------------------------------------
			 */
			$automatic_result =
				$this->calculate_automatic_discount(
					$automatic_discount_context
				);

			$automatic_discount =
				$this->normalize_amount(
					$automatic_result[
						'automatic_discount'
					] ??
						0
				);

			$automatic_discount =
				min(
					$automatic_discount,
					$automatic_discount_base_product_total
				);

			$automatic_free_delivery = ! empty( $automatic_result['free_delivery'] );
			/* Free Delivery is resolved only by the unified Special Discount engine. */
			$special_offer_free_delivery = false;

			/*
			 * --------------------------------------------------
			 * Coupon
			 * --------------------------------------------------
			 */
			$coupon_eligible_automatic_discount =
				$this->calculate_discount_overlap_amount(
					$this->get_automatic_discount_line_items(
						$order
					),
					$this->get_coupon_discount_line_items(
						$order
					),
					$automatic_discount
				);

			$coupon_result =
				$this->calculate_coupon(
					$coupon_code,
					$discount_product_context,
					$customer,
					$automatic_discount,
					$settings,
					$coupon_eligible_automatic_discount
				);

			if (
				is_wp_error(
					$coupon_result
				)
			) {
				$this->delete_order(
					$order
				);

				return $coupon_result;
			}

			$coupon_source =
				sanitize_key(
					(string) (
						$coupon_result[
							'source'
						] ??
							''
					)
				);

			$coupon_type =
				sanitize_key(
					(string) (
						$coupon_result[
							'coupon_type'
						] ??
							''
					)
				);

			$custom_coupon_discount =
				$this->normalize_amount(
					$coupon_result[
						'coupon_discount'
					] ??
						0
				);

			$coupon_free_delivery =
				! empty(
					$coupon_result[
						'free_delivery'
					]
				);

			/*
			 * Eilmo product-level discounts are applied
			 * directly to WooCommerce product-line totals.
			 *
			 * Subtotals remain untouched, therefore
			 * WooCommerce can calculate the order's
			 * discount_total correctly.
			 */
			if (
				$automatic_discount > 0
			) {
				$automatic_discount =
					$this->apply_automatic_discount(
						$order,
						$automatic_discount
					);
			}

			if (
				'eilmo' ===
					$coupon_source &&
				$custom_coupon_discount > 0
			) {
				$custom_coupon_discount =
					$this->apply_coupon_discount(
						$order,
						$custom_coupon_discount
					);
			}

			$order->calculate_totals(
				true
			);

			/*
			 * --------------------------------------------------
			 * Native WooCommerce coupon
			 * --------------------------------------------------
			 */
			$woocommerce_coupon_discount =
				0.0;

			if (
				'woocommerce' ===
					$coupon_source &&
				'' !==
					$coupon_code
			) {
				$woocommerce_coupon_discount =
					$this->apply_woocommerce_coupon(
						$order,
						$coupon_code
					);

				if (
					is_wp_error(
						$woocommerce_coupon_discount
					)
				) {
					$this->delete_order(
						$order
					);

					return $woocommerce_coupon_discount;
				}

				$woocommerce_coupon_discount =
					$this->normalize_amount(
						$woocommerce_coupon_discount
					);
			}

			$coupon_discount =
				'eilmo' ===
					$coupon_source
					? $custom_coupon_discount
					: $woocommerce_coupon_discount;

			$discounted_product_total =
				$this->get_current_product_total(
					$order
				);

			/*
			 * --------------------------------------------------
			 * Delivery
			 * --------------------------------------------------
			 */
			$free_delivery_reward =
				$coupon_free_delivery ||
				$automatic_free_delivery;

			$delivery_result =
				$this->resolve_delivery(
					(string) (
						$delivery[
							'method_id'
						] ??
							''
					),
					$discounted_product_total,
					array(
						'product_total' =>
							$this->normalize_amount( $special_discount_condition_context['product_total'] ?? $discounted_product_total ),

						'combo_regular_total' =>
							$combo_regular_total,

						'combo_discount' =>
							$combo_discount,

						'combo_total' =>
							$combo_total,

						'order_bump_regular_total' =>
							$order_bump_regular_total,

						'order_bump_discount' =>
							$order_bump_discount,

						'order_bump_total' =>
							$order_bump_total,

						'discounted_product_total' =>
							$discounted_product_total,

						'automatic_discount' =>
							$automatic_discount,

						'coupon_discount' =>
							$coupon_discount,

						'coupon_code' =>
							$coupon_code,

						'coupon_source' =>
							$coupon_source,

					'coupon_free_delivery' =>
						$coupon_free_delivery,

					'automatic_free_delivery' =>
						$automatic_free_delivery,

					'special_offer_free_delivery' =>
						false,

					'free_delivery' =>
						$free_delivery_reward,

					'payment_type' =>
						$payment_type,

					'special_discount_scope' =>
						$automatic_discount_context['special_discount_scope'],

					'special_discount_ids' =>
						$automatic_discount_context['special_discount_ids'],

					'current_checkout_product_ids' =>
						$automatic_discount_context['current_checkout_product_ids'],

					'product_quantities' =>
						$special_discount_condition_context['product_quantities'] ?? array(),

					'product_totals' =>
						$special_discount_condition_context['product_totals'] ?? array(),

					'cart_quantity' =>
						$special_discount_condition_context['cart_quantity'] ?? 0,

						'customer' =>
							$customer,
					)
				);

			if (
				is_wp_error(
					$delivery_result
				)
			) {
				$this->delete_order(
					$order
				);

				return $delivery_result;
			}

			$delivery_charge =
				$this->normalize_amount(
					$delivery_result[
						'charge'
					] ??
						0
				);

			$delivery_base_charge =
				$this->normalize_amount(
					$delivery_result[
						'base_charge'
					] ??
						$delivery_charge
				);


			$applied_special_discounts =
				$this->build_applied_special_discount_snapshots(
					$automatic_result,
					$order_bump_result,
					$delivery_base_charge
				);

			/*
			 * A coupon is considered applied only when it produced
			 * a real checkout benefit:
			 *
			 * - A positive product discount, or
			 * - A free-delivery reduction from a positive base charge.
			 *
			 * This keeps the admin Coupon Details section hidden for
			 * empty, invalid or no-effect coupon states.
			 */
			$coupon_free_delivery_applied =
				$coupon_free_delivery &&
				$delivery_base_charge >
					$delivery_charge;

			$coupon_applied =
				'' !== $coupon_code &&
				(
					$coupon_discount > 0 ||
					$coupon_free_delivery_applied
				);

			if (
				! empty(
					$delivery_result[
						'method_id'
					]
				)
			) {
				$this->add_delivery_item(
					$order,
					$delivery_result,
					$delivery_charge
				);
			}

			$order->calculate_totals(
				true
			);

			/*
			 * --------------------------------------------------
			 * Full Payment Discount
			 * --------------------------------------------------
			 *
			 * Normal lines are always eligible.
			 *
			 * Combo and Order Bump lines are included only when
			 * their saved Full Payment Discount compatibility
			 * allows it.
			 */
			$base_grand_total =
				$this->normalize_amount(
					$order->get_total()
				);

			$discounted_product_total =
				$this->get_current_product_total(
					$order
				);

			$full_payment_product_total =
				$this->get_full_payment_regular_product_total(
					$order
				);

			$full_payment_discounted_product_total =
				$this->get_full_payment_product_total(
					$order
				);

			$full_payment_ineligible_promotional_total =
				$this->get_full_payment_ineligible_promotional_total(
					$order
				);

			$full_payment_grand_total =
				$this->normalize_amount(
					max(
						0,
						$base_grand_total -
							$full_payment_ineligible_promotional_total
					)
				);

			$full_payment_discount =
				$this->calculate_full_payment_discount(
					$settings,
					$payment_type,
					$full_payment_product_total,
					$full_payment_discounted_product_total,
					$full_payment_grand_total
				);

			if (
				$full_payment_discount > 0
			) {
				$full_payment_discount =
					$this->apply_full_payment_discount(
						$order,
						$full_payment_discount
					);

				$order->calculate_totals(
					true
				);
			}

			$grand_total =
				$this->normalize_amount(
					$order->get_total()
				);

			$discounted_product_total =
				$this->get_current_product_total(
					$order
				);

			/*
			 * --------------------------------------------------
			 * Cash on Delivery / Advance / Full Payment
			 * --------------------------------------------------
			 */
			$advance_result =
				$this->calculate_advance_payment(
					$product_total,
					$discounted_product_total,
					$delivery_charge,
					$grand_total,
					$payment_type
				);

			$pay_now =
				$this->normalize_amount(
					$advance_result[
						'pay_now'
					] ??
						$grand_total
				);

			$pay_now =
				min(
					$pay_now,
					$grand_total
				);

			$remaining_due =
				$this->normalize_amount(
					max(
						0,
						$grand_total -
							$pay_now
					)
				);

			$advance_amount =
				$this->normalize_amount(
					$advance_result[
						'advance_amount'
					] ??
						0
				);

			/*
			 * --------------------------------------------------
			 * Payment Method
			 * --------------------------------------------------
			 */
			$payment_data =
				$this->configure_payment_method(
					$order,
					$payment,
					$settings,
					$payment_type
				);

			if (
				is_wp_error(
					$payment_data
				)
			) {
				$this->delete_order(
					$order
				);

				return $payment_data;
			}

			/*
			 * --------------------------------------------------
			 * Order metadata
			 * --------------------------------------------------
			 */
			$this->save_order_meta(
				$order,
				array(
					'product_total' =>
						$product_total,

					'combo_offers' =>
						isset(
							$combo_result[
								'offers'
							]
						) &&
						is_array(
							$combo_result[
								'offers'
							]
						)
							? $combo_result[
								'offers'
							]
							: array(),

					'combo_discount' =>
						$combo_discount,

					'order_bumps' =>
						isset(
							$order_bump_result[
								'offers'
							]
						) &&
						is_array(
							$order_bump_result[
								'offers'
							]
						)
							? $order_bump_result[
								'offers'
							]
							: array(),

					'order_bump_discount' =>
						$order_bump_discount,

					'automatic_discount' =>
						$automatic_discount,

					'applied_special_discounts' =>
						$applied_special_discounts,

					'coupon_discount' =>
						$coupon_discount,

					'coupon_applied' =>
						$coupon_applied,

					'coupon_code' =>
						$coupon_applied
							? $coupon_code
							: '',

					'coupon_source' =>
						$coupon_applied
							? $coupon_source
							: '',

					'coupon_type' =>
						$coupon_applied
							? $coupon_type
							: '',

					'coupon_free_delivery' =>
						$coupon_applied &&
						$coupon_free_delivery_applied,

					'delivery_method' =>
						(string) (
							$delivery_result[
								'method_id'
							] ??
								''
						),

					'delivery_charge' =>
						$delivery_charge,

					'full_payment_discount' =>
						$full_payment_discount,

					'grand_total' =>
						$grand_total,

					'payment_type' =>
						$payment_type,

					'advance_amount' =>
						$advance_amount,

					'advance_rule_id' =>
						sanitize_key(
							(string) (
								$advance_result[
									'rule_id'
								] ??
									''
							)
						),

					'advance_rule_type' =>
						sanitize_key(
							(string) (
								$advance_result[
									'rule_type'
								] ??
									''
							)
						),

					'pay_now' =>
						$pay_now,

					'remaining_due' =>
						$remaining_due,

					'payment_method' =>
						(string) (
							$payment_data[
								'method'
							] ??
								''
						),

					'payment_method_key' =>
						(string) (
							$payment_data[
								'method_key'
							] ??
								''
						),

					'payment_source' =>
						(string) (
							$payment_data[
								'source'
							] ??
								''
						),

					'payment_gateway_id' =>
						(string) (
							$payment_data[
								'gateway_id'
							] ??
								''
						),

					'payment_transaction_id' =>
						(string) (
							$payment_data[
								'transaction_id'
							] ??
								''
						),

					'payment_proof_token' =>
						(string) ( $payment_data['proof_token'] ?? '' ),
				)
			);

			$order->update_meta_data(
				self::META_PAYMENT_STATUS,
				'unpaid'
			);

			if ( ! empty( $fraud_check ) ) {
				( new CourierSuccessService() )->attach_live_result(
					$order,
					$fraud_check
				);
			}

			$order->set_status(
				'pending'
			);

			$proof_token = sanitize_text_field( (string) ( $payment_data['proof_token'] ?? '' ) );
			$proof_method = sanitize_key( (string) ( $payment_data['method_key'] ?? '' ) );
			if ( '' !== $proof_token && ! PaymentProof::assign_to_order( $order, $proof_token, $proof_method ) ) {
				$this->throw_order_exception(
					__( 'The payment screenshot expired before the order was created. Please upload it again.', 'eilmo-checkout-flow' )
				);
			}

			$order->save();

			if (
				'eilmo' ===
					$coupon_source &&
				'' !==
					$coupon_code
			) {
				$this->record_coupon_usage(
					$order,
					$coupon_result,
					$customer
				);
			}

			$result =
				array(
					'order' =>
						$order,

					'order_id' =>
						$order->get_id(),

					'order_key' =>
						$order->get_order_key(),

					'payment_type' =>
						$payment_type,

					'is_cash_on_delivery' =>
						'cash_on_delivery' ===
							$payment_type,

					'payment_method' =>
						(string) (
							$payment_data[
								'method'
							] ??
								''
						),

					'payment_method_key' =>
						(string) (
							$payment_data[
								'method_key'
							] ??
								''
						),

					'payment_source' =>
						(string) (
							$payment_data[
								'source'
							] ??
								''
						),

					'gateway_id' =>
						(string) (
							$payment_data[
								'gateway_id'
							] ??
								''
						),

					'is_partial_payment' =>
						$pay_now > 0 &&
						$pay_now <
							$grand_total,

					'requires_payment' =>
						$pay_now > 0,

					'requires_gateway' =>
						'woocommerce_gateway' ===
							(
								$payment_data[
									'source'
								] ??
									''
							) &&
						$pay_now > 0,

					'combo_offers' =>
						isset(
							$combo_result[
								'offers'
							]
						) &&
						is_array(
							$combo_result[
								'offers'
							]
						)
							? $combo_result[
								'offers'
							]
							: array(),

					'order_bumps' =>
						isset(
							$order_bump_result[
								'offers'
							]
						) &&
						is_array(
							$order_bump_result[
								'offers'
							]
						)
							? $order_bump_result[
								'offers'
							]
							: array(),

					'totals' =>
						array(
							'product_total' =>
								$product_total,

							'combo_regular_total' =>
								$combo_regular_total,

							'combo_discount' =>
								$combo_discount,

							'combo_total' =>
								$combo_total,

							'order_bump_regular_total' =>
								$order_bump_regular_total,

							'order_bump_discount' =>
								$order_bump_discount,

							'order_bump_total' =>
								$order_bump_total,

							'automatic_discount' =>
								$automatic_discount,

							'coupon_discount' =>
								$coupon_discount,

							'delivery_charge' =>
								$delivery_charge,

							'full_payment_discount' =>
								$full_payment_discount,

							'grand_total' =>
								$grand_total,

							'advance_amount' =>
								$advance_amount,

							'pay_now' =>
								$pay_now,

							'remaining_due' =>
								$remaining_due,
						),

					'coupon' =>
						array(
							'applied' =>
								$coupon_applied,

							'code' =>
								$coupon_applied
									? $coupon_code
									: '',

							'source' =>
								$coupon_applied
									? $coupon_source
									: '',

							'type' =>
								$coupon_applied
									? $coupon_type
									: '',

							'discount' =>
								$coupon_applied
									? $coupon_discount
									: 0.0,

							'free_delivery' =>
								$coupon_applied &&
								$coupon_free_delivery_applied,
						),

					'delivery' =>
						$delivery_result,

					'advance' =>
						$advance_result,
				);

			$result =
				apply_filters(
					'eilmo_cf/orders/created_result',
					$result,
					$order,
					$data
				);

			do_action(
				'eilmo_cf/orders/created',
				$order,
				$result,
				$data
			);

			return is_array( $result )
				? $result
				: array(
					'order' =>
						$order,

					'order_id' =>
						$order->get_id(),
				);

		} catch ( \Throwable $throwable ) {

			$this->delete_order(
				$order
			);

			do_action(
				'eilmo_cf/orders/creation_failed',
				$throwable,
				$data
			);

			return new WP_Error(
				'order_creation_failed',
				$throwable->getMessage()
					? sanitize_text_field(
						$throwable->getMessage()
					)
					: __(
						'The order could not be created. Please try again.',
						'eilmo-checkout-flow'
					)
			);
		}
	}

	/**
	 * Set WooCommerce customer information.
	 *
	 * @param WC_Order             $order    Order.
	 * @param array<string, mixed> $customer Customer.
	 *
	 * @return void
	 */
	private function set_customer_data(
		WC_Order $order,
		array $customer
	): void {

		$billing =
			array(
				'first_name' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_first_name'
							] ??
								''
						)
					),

				'last_name' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_last_name'
							] ??
								''
						)
					),

				'company' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_company'
							] ??
								''
						)
					),

				'address_1' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_address_1'
							] ??
								''
						)
					),

				'address_2' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_address_2'
							] ??
								''
						)
					),

				'city' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_city'
							] ??
								''
						)
					),

				'state' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_state'
							] ??
								''
						)
					),

				'postcode' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_postcode'
							] ??
								''
						)
					),

				'country' =>
					strtoupper(
						sanitize_key(
							(string) (
								$customer[
									'billing_country'
								] ??
									''
							)
						)
					),

				'email' =>
					sanitize_email(
						(string) (
							$customer[
								'billing_email'
							] ??
								''
						)
					),

				'phone' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_phone'
							] ??
								''
						)
					),
			);

		$order->set_billing_address(
			$billing
		);

		$order_comments =
			sanitize_textarea_field(
				(string) (
					$customer[
						'order_comments'
					] ??
						''
				)
			);

		if (
			'' !==
				$order_comments
		) {
			$order->set_customer_note(
				$order_comments
			);
		}
	}

	/**
	 * Prepare validated normal products for line-item creation.
	 *
	 * OrderValidator normally supplies this exact shape. This
	 * creator-level normalization is a final fail-closed boundary
	 * because validated-payload filters may run between validation
	 * and order creation.
	 *
	 * Duplicate Simple products or exact variations are grouped so
	 * one purchasable identity creates one WooCommerce line item.
	 *
	 * @param array<int, array<mixed>> $items Items.
	 *
	 * @return array<int, array<string, int|float>>
	 *
	 * @throws \RuntimeException Invalid normal product data.
	 */
	private function prepare_normal_items(
		array $items
	): array {

		$grouped =
			array();

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				$this->throw_order_exception(
					__(
						'One of the selected product entries is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$product_id =
				$this->normalize_product_identity(
					$item[
						'product_id'
					] ?? null,
					false
				);

			$variation_id =
				$this->normalize_product_identity(
					$item[
						'variation_id'
					] ?? 0,
					true
				);

			$quantity =
				$this->normalize_quantity(
					$item[
						'quantity'
					] ?? null
				);

			if (
				$product_id <= 0 ||
				$variation_id < 0 ||
				$quantity <= 0
			) {
				$this->throw_order_exception(
					__(
						'One of the selected product entries is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$key =
				sprintf(
					'%1$d:%2$d',
					$product_id,
					$variation_id
				);

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] =
					array(
						'product_id' =>
							$product_id,

						'variation_id' =>
							$variation_id,

						'quantity' =>
							0.0,
					);
			}

			$combined_quantity =
				(float) $grouped[ $key ][
					'quantity'
				] + $quantity;

			if (
				! is_finite(
					$combined_quantity
				) ||
				$combined_quantity <= 0
			) {
				$this->throw_order_exception(
					__(
						'One of the selected product quantities is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$grouped[ $key ][
				'quantity'
			] =
				$combined_quantity;
		}

		if ( empty( $grouped ) ) {
			$this->throw_order_exception(
				__(
					'No valid products could be added to the order.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array_values(
			$grouped
		);
	}

	/**
	 * Add selected products to order.
	 *
	 * @param WC_Order                 $order Order.
	 * @param array<int, array<mixed>> $items Items.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException Product failure.
	 */
	private function add_products(
		WC_Order $order,
		array $items
	): void {

		$items =
			$this->prepare_normal_items(
				$items
			);

		foreach ( $items as $item ) {

			$product_id =
				(int) (
					$item[
						'product_id'
					] ??
						0
				);

			$variation_id =
				(int) (
					$item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				$this->normalize_quantity(
					$item[
						'quantity'
					] ??
						0
				);

			$product =
				wc_get_product(
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if ( ! $product ) {
				$this->throw_order_exception(
					__(
						'One of the selected products is no longer available.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				$variation_id > 0 &&
				(
					! $product->is_type(
						'variation'
					) ||
					(int) $product->get_parent_id() !==
						$product_id
				)
			) {
				$this->throw_order_exception(
					__(
						'One of the selected product variations is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				0 === $variation_id &&
				$product->is_type(
					'variable'
				)
			) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'Please select an option for %s.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			if (
				! $product->is_purchasable()
			) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s cannot currently be purchased.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			if ( ! $product->is_in_stock() ) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s is currently out of stock.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			if (
				$product->is_sold_individually() &&
				$quantity > 1
			) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'Only one %s can be purchased per order.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			if (
				$product->managing_stock() &&
				! $product->has_enough_stock(
					$quantity
				)
			) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'There is not enough stock available for %s.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			$item_id =
				$order->add_product(
					$product,
					$quantity
				);

			if ( ! $item_id ) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s could not be added to the order.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			$order_item =
				$order->get_item(
					$item_id
				);

			if (
				! is_object( $order_item ) ||
				! method_exists(
					$order_item,
					'get_product_id'
				) ||
				! method_exists(
					$order_item,
					'get_variation_id'
				) ||
				(int) $order_item->get_product_id() !==
					$product_id ||
				(int) $order_item->get_variation_id() !==
					$variation_id
			) {
				$this->throw_order_exception(
					__(
						'A selected product could not be prepared correctly for the order.',
						'eilmo-checkout-flow'
					)
				);
			}
		}

		if (
			0 ===
				count(
					$order->get_items(
						'line_item'
					)
				)
		) {
			$this->throw_order_exception(
				__(
					'No valid products could be added to the order.',
					'eilmo-checkout-flow'
				)
			);
		}
	}

	/**
	 * Add validated Combo Offer component products.
	 *
	 * Combo Offers remain a distinct Eilmo checkout
	 * entity, but every component is stored as a real
	 * WooCommerce line item for stock, reporting and
	 * order compatibility.
	 *
	 * Discount compatibility is always resolved again
	 * from the current saved Combo Offer configuration.
	 * Frontend or stale validated values are never the
	 * final authority for these flags.
	 *
	 * @param WC_Order             $order        Order.
	 * @param array<string, mixed> $combo_offers Validated Combo data.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException Combo product failure.
	 */
	private function add_combo_products(
		WC_Order $order,
		array $combo_offers
	): void {

		$offers =
			isset(
				$combo_offers[
					'offers'
				]
			) &&
			is_array(
				$combo_offers[
					'offers'
				]
			)
				? $combo_offers[
					'offers'
				]
				: array();

		if ( empty( $offers ) ) {
			return;
		}

		$settings =
			$this->get_settings();

		$added =
			0;

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$combo_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			$combo_title =
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							$combo_id
					)
				);

			if ( '' === $combo_id ) {
				$this->throw_order_exception(
					__(
						'One of the selected Combo Offers is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$apply_automatic_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			$apply_coupon =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_coupon',
					true,
					$offer
				);

			$apply_full_payment_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_full_payment_discount',
					true,
					$offer
				);

			$items =
				isset(
					$offer[
						'items'
					]
				) &&
				is_array(
					$offer[
						'items'
					]
				)
					? $offer[
						'items'
					]
					: array();

			if ( empty( $items ) ) {
				$this->throw_order_exception(
					__(
						'One of the selected Combo Offers does not contain any products.',
						'eilmo-checkout-flow'
					)
				);
			}

			foreach ( $items as $item ) {

				if ( ! is_array( $item ) ) {
					continue;
				}

				$product_id =
					absint(
						$item[
							'product_id'
						] ??
							0
					);

				$variation_id =
					absint(
						$item[
							'variation_id'
						] ??
							0
					);

				$quantity =
					$this->normalize_quantity(
						$item[
							'quantity'
						] ??
							0
					);

				if (
					$product_id <= 0 ||
					$quantity <= 0
				) {
					$this->throw_order_exception(
						__(
							'A product in the selected Combo Offer is invalid.',
							'eilmo-checkout-flow'
						)
					);
				}

				$product =
					wc_get_product(
						$variation_id > 0
							? $variation_id
							: $product_id
					);

				if ( ! $product ) {
					$this->throw_order_exception(
						__(
							'A product in the selected Combo Offer is no longer available.',
							'eilmo-checkout-flow'
						)
					);
				}

				if (
					$variation_id > 0 &&
					absint(
						$product->get_parent_id()
					) !==
						$product_id
				) {
					$this->throw_order_exception(
						__(
							'A variation in the selected Combo Offer is invalid.',
							'eilmo-checkout-flow'
						)
					);
				}

				$item_id =
					$order->add_product(
						$product,
						$quantity
					);

				if ( ! $item_id ) {
					$this->throw_order_exception(
						sprintf(
							/* translators: %s: Product name. */
							__(
								'%s could not be added from the selected Combo Offer.',
								'eilmo-checkout-flow'
							),
							$product->get_name()
						)
					);
				}

				$order_item =
					$order->get_item(
						$item_id
					);

				if (
					! is_object(
						$order_item
					) ||
					! method_exists(
						$order_item,
						'add_meta_data'
					)
				) {
					$this->throw_order_exception(
						__(
							'A Combo Offer product could not be prepared for the order.',
							'eilmo-checkout-flow'
						)
					);
				}

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_COMPONENT,
					'yes',
					true
				);

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_ID,
					$combo_id,
					true
				);

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_TITLE,
					$combo_title,
					true
				);

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_APPLY_AUTOMATIC,
					$apply_automatic_discount,
					true
				);

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_APPLY_COUPON,
					$apply_coupon,
					true
				);

				$order_item->add_meta_data(
					self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,
					$apply_full_payment_discount,
					true
				);

				$order_item->save();

				++$added;
			}
		}

		if (
			! empty( $offers ) &&
			$added <= 0
		) {
			$this->throw_order_exception(
				__(
					'No valid Combo Offer products could be added to the order.',
					'eilmo-checkout-flow'
				)
			);
		}
	}

	/**
	 * Add validated Order Bump products.
	 *
	 * Each Order Bump is added as a dedicated WooCommerce
	 * line item. The same product may therefore exist in
	 * the same order as:
	 *
	 * - A normal product.
	 * - A Combo component.
	 * - An Order Bump.
	 *
	 * Those occurrences never reuse or merge line items.
	 *
	 * Discount compatibility is resolved again from the
	 * current saved Order Bump configuration before the
	 * line item is created.
	 *
	 * @param WC_Order             $order       Order.
	 * @param array<string, mixed> $order_bumps Validated Order Bump data.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException Order Bump product failure.
	 */
	private function add_order_bump_products(
		WC_Order $order,
		array $order_bumps
	): void {

		$offers =
			isset(
				$order_bumps[
					'offers'
				]
			) &&
			is_array(
				$order_bumps[
					'offers'
				]
			)
				? $order_bumps[
					'offers'
				]
				: array();

		if ( empty( $offers ) ) {
			return;
		}

		$settings =
			$this->get_settings();

		$added =
			0;

		$requires_product =
			false;

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$offer_type = sanitize_key( (string) ( $offer['offer_type'] ?? 'single_product' ) );

			/* Free Delivery is a reward flag, not a product line. */
			if ( 'free_delivery' === $offer_type ) {
				continue;
			}

			$requires_product = true;

			$bump_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			$bump_title =
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							$bump_id
					)
				);

			$product_id =
				absint(
					$offer[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$offer[
						'variation_id'
					] ??
						0
				);

			$quantity =
				$this->normalize_quantity(
					$offer[
						'quantity'
					] ??
						0
				);

			if (
				'' === $bump_id ||
				$product_id <= 0 ||
				$quantity <= 0
			) {
				$this->throw_order_exception(
					__(
						'One of the selected Special Discounts is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$product =
				wc_get_product(
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if ( ! $product ) {
				$this->throw_order_exception(
					__(
						'A product in the selected Special Offer is no longer available.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				$variation_id > 0 &&
				absint(
					$product->get_parent_id()
				) !==
					$product_id
			) {
				$this->throw_order_exception(
					__(
						'A variation in the selected Special Offer is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				0 === $variation_id &&
				method_exists(
					$product,
					'is_type'
				) &&
				$product->is_type(
					'variable'
				)
			) {
				$this->throw_order_exception(
					__(
						'An exact variation is required for one of the selected Special Discounts.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				'free_gift' !== $offer_type &&
				method_exists(
					$product,
					'is_purchasable'
				) &&
				! $product->is_purchasable()
			) {
				$this->throw_order_exception(
					__(
						'A product in the selected Special Offer cannot currently be purchased.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				method_exists(
					$product,
					'is_in_stock'
				) &&
				! $product->is_in_stock()
			) {
				$this->throw_order_exception(
					__(
						'A product in the selected Special Offer is out of stock.',
						'eilmo-checkout-flow'
					)
				);
			}

			if (
				method_exists(
					$product,
					'has_enough_stock'
				) &&
				method_exists(
					$product,
					'backorders_allowed'
				) &&
				! $product->backorders_allowed() &&
				! $product->has_enough_stock(
					$quantity
				)
			) {
				$this->throw_order_exception(
					__(
						'There is not enough stock for a product in the selected Special Offer.',
						'eilmo-checkout-flow'
					)
				);
			}

			$item_id =
				$order->add_product(
					$product,
					$quantity
				);

			if ( ! $item_id ) {
				$this->throw_order_exception(
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s could not be added from the selected Special Offer.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);
			}

			$order_item =
				$order->get_item(
					$item_id
				);

			if (
				! is_object(
					$order_item
				) ||
				! method_exists(
					$order_item,
					'add_meta_data'
				)
			) {
				$this->throw_order_exception(
					__(
						'A Special Offer product could not be prepared for the order.',
						'eilmo-checkout-flow'
					)
				);
			}

			$apply_automatic_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			$apply_coupon =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_coupon',
					false,
					$offer
				);

			$apply_full_payment_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_full_payment_discount',
					true,
					$offer
				);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP,
				'yes',
				true
			);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP_ID,
				$bump_id,
				true
			);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP_TITLE,
				$bump_title,
				true
			);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC,
				$apply_automatic_discount,
				true
			);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
				$apply_coupon,
				true
			);

			$order_item->add_meta_data(
				self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,
				$apply_full_payment_discount,
				true
			);

			$order_item->save();

			++$added;
		}

		if (
			$requires_product &&
			$added <= 0
		) {
			$this->throw_order_exception(
				__(
					'No valid Special Offer products could be added to the order.',
					'eilmo-checkout-flow'
				)
			);
		}
	}

	/**
	 * Apply authoritative Combo Offer pricing.
	 *
	 * @param WC_Order             $order        Order.
	 * @param array<string, mixed> $combo_offers Combo data.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \RuntimeException Combo pricing failure.
	 */
	private function apply_combo_pricing(
		WC_Order $order,
		array $combo_offers
	): array {

		$empty =
			array(
				'selected_ids' =>
					array(),

				'offers' =>
					array(),

				'regular_total' =>
					0.0,

				'discount' =>
					0.0,

				'combo_total' =>
					0.0,
			);

		$offers =
			isset(
				$combo_offers[
					'offers'
				]
			) &&
			is_array(
				$combo_offers[
					'offers'
				]
			)
				? $combo_offers[
					'offers'
				]
				: array();

		if ( empty( $offers ) ) {
			return $empty;
		}

		if (
			! class_exists(
				ComboOfferCalculator::class
			)
		) {
			$this->throw_order_exception(
				__(
					'Combo Offer pricing is currently unavailable.',
					'eilmo-checkout-flow'
				)
			);
		}

		$calculator =
			new ComboOfferCalculator();

		$settings =
			$this->get_settings();

		$selected_ids =
			array();

		$resolved_offers =
			array();

		$regular_total =
			0.0;

		$total_discount =
			0.0;

		$total_combo_price =
			0.0;

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$combo_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if ( '' === $combo_id ) {
				$this->throw_order_exception(
					__(
						'One of the selected Combo Offers is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$line_items =
				$this->get_combo_line_items(
					$order,
					$combo_id
				);

			if ( empty( $line_items ) ) {
				$this->throw_order_exception(
					__(
						'A selected Combo Offer could not be matched to its order products.',
						'eilmo-checkout-flow'
					)
				);
			}

			$offer_regular_total =
				0.0;

			foreach ( $line_items as $line_item ) {
				$offer_regular_total +=
					max(
						0,
						(float) $line_item
							->get_total()
					);
			}

			$offer_regular_total =
				$this->normalize_amount(
					$offer_regular_total
				);

			if ( $offer_regular_total <= 0 ) {
				$this->throw_order_exception(
					__(
						'One of the selected Combo Offers does not have a valid price.',
						'eilmo-checkout-flow'
					)
				);
			}

			$calculation =
				$calculator->calculate(
					$offer,
					$offer_regular_total
				);

			if (
				! is_array(
					$calculation
				) ||
				empty(
					$calculation[
						'valid'
					]
				)
			) {
				$this->throw_order_exception(
					__(
						'One of the selected Combo Offers could not be calculated.',
						'eilmo-checkout-flow'
					)
				);
			}

			$requested_discount =
				$this->normalize_amount(
					$calculation[
						'discount'
					] ??
						0
				);

			$applied_discount =
				$this->apply_discount_to_items(
					$line_items,
					$requested_discount,
					self::ITEM_META_COMBO_DISCOUNT
				);

			$offer_combo_total =
				$this->normalize_amount(
					max(
						0,
						$offer_regular_total -
							$applied_discount
					)
				);

			$apply_automatic_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			$apply_coupon =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_coupon',
					true,
					$offer
				);

			$apply_full_payment_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'combo_offers',
					$combo_id,
					'apply_full_payment_discount',
					true,
					$offer
				);

			/*
			 * Keep the line-item compatibility snapshot in sync
			 * with the current saved Combo Offer settings.
			 */
			foreach ( $line_items as $line_item ) {

				if (
					! is_object(
						$line_item
					) ||
					! method_exists(
						$line_item,
						'update_meta_data'
					)
				) {
					continue;
				}

				$line_item->update_meta_data(
					self::ITEM_META_COMBO_APPLY_AUTOMATIC,
					$apply_automatic_discount
				);

				$line_item->update_meta_data(
					self::ITEM_META_COMBO_APPLY_COUPON,
					$apply_coupon
				);

				$line_item->update_meta_data(
					self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,
					$apply_full_payment_discount
				);

				if (
					method_exists(
						$line_item,
						'save'
					)
				) {
					$line_item->save();
				}
			}

			$selected_ids[] =
				$combo_id;

			$regular_total =
				$this->normalize_amount(
					$regular_total +
						$offer_regular_total
				);

			$total_discount =
				$this->normalize_amount(
					$total_discount +
						$applied_discount
				);

			$total_combo_price =
				$this->normalize_amount(
					$total_combo_price +
						$offer_combo_total
				);

			$resolved_offers[] =
				array(
					'id' =>
						$combo_id,

					'title' =>
						sanitize_text_field(
							(string) (
								$offer[
									'title'
								] ??
									$combo_id
							)
						),

					'pricing_type' =>
						sanitize_key(
							(string) (
								$calculation[
									'pricing_type'
								] ??
									''
							)
						),

					'pricing_value' =>
						$this->normalize_amount(
							$calculation[
								'pricing_value'
							] ??
								0
						),

					'regular_total' =>
						$offer_regular_total,

					'discount' =>
						$applied_discount,

					'combo_total' =>
						$offer_combo_total,

					'apply_automatic_discount' =>
						$apply_automatic_discount,

					'apply_coupon' =>
						$apply_coupon,

					'apply_full_payment_discount' =>
						$apply_full_payment_discount,

					'items' =>
						$this->build_combo_item_snapshot(
							$line_items
						),
				);
		}

		return array(
			'selected_ids' =>
				array_values(
					array_unique(
						$selected_ids
					)
				),

			'offers' =>
				$resolved_offers,

			'regular_total' =>
				$regular_total,

			'discount' =>
				$total_discount,

			'combo_total' =>
				$total_combo_price,
		);
	}

	/**
	 * Apply authoritative Order Bump pricing.
	 *
	 * @param WC_Order             $order       Order.
	 * @param array<string, mixed> $order_bumps Order Bump data.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \RuntimeException Order Bump pricing failure.
	 */
	private function apply_order_bump_pricing(
		WC_Order $order,
		array $order_bumps
	): array {

		$empty =
			array(
				'selected_ids' =>
					array(),

				'offers' =>
					array(),

				'regular_total' =>
					0.0,

				'discount' =>
					0.0,

				'bump_total' =>
					0.0,
			);

		$offers =
			isset(
				$order_bumps[
					'offers'
				]
			) &&
			is_array(
				$order_bumps[
					'offers'
				]
			)
				? $order_bumps[
					'offers'
				]
				: array();

		if ( empty( $offers ) ) {
			return $empty;
		}

		if (
			! class_exists(
				OrderBumpCalculator::class
			)
		) {
			$this->throw_order_exception(
				__(
					'Special Offer pricing is currently unavailable.',
					'eilmo-checkout-flow'
				)
			);
		}

		$calculator =
			new OrderBumpCalculator();

		$settings =
			$this->get_settings();

		$selected_ids =
			array();

		$resolved_offers =
			array();

		$regular_total =
			0.0;

		$total_discount =
			0.0;

		$total_bump_price =
			0.0;

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$bump_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if ( '' === $bump_id ) {
				$this->throw_order_exception(
					__(
						'One of the selected Special Discounts is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			if ( 'free_delivery' === sanitize_key( (string) ( $offer['offer_type'] ?? '' ) ) ) {
				$selected_ids[] = $bump_id;
				$resolved_offers[] = array(
					'id' => $bump_id,
					'title' => sanitize_text_field( (string) ( $offer['title'] ?? $bump_id ) ),
					'offer_type' => 'free_delivery',
					'free_delivery' => true,
					'condition_type' => sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) ),
					'condition_minimum' => $this->normalize_amount( $offer['condition_minimum'] ?? 0 ),
					'condition_maximum' => $this->normalize_amount( $offer['condition_maximum'] ?? 0 ),
					'condition_product_id' => absint( $offer['condition_product_id'] ?? 0 ),
					'condition_product_quantity' => max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ),
					'condition_cart_quantity' => max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ),
					'product_id' => 0,
					'variation_id' => 0,
					'quantity' => 0.0,
					'pricing_type' => 'regular_price',
					'pricing_value' => 0.0,
					'regular_total' => 0.0,
					'discount' => 0.0,
					'bump_total' => 0.0,
					'apply_automatic_discount' => 'no',
					'apply_coupon' => 'no',
					'apply_full_payment_discount' => 'no',
					'items' => array(),
				);
				continue;
			}

			$line_items =
				$this->get_order_bump_line_items(
					$order,
					$bump_id
				);

			if ( empty( $line_items ) ) {
				$this->throw_order_exception(
					__(
						'A selected Special Offer could not be matched to its order product.',
						'eilmo-checkout-flow'
					)
				);
			}

			$offer_regular_total =
				$this->sum_line_item_amounts(
					$line_items,
					false
				);

			if ( $offer_regular_total <= 0 ) {
				$this->throw_order_exception(
					__(
						'One of the selected Special Discounts does not have a valid price.',
						'eilmo-checkout-flow'
					)
				);
			}

			$calculation =
				$calculator->calculate(
					$offer,
					$offer_regular_total
				);

			if (
				! is_array(
					$calculation
				) ||
				empty(
					$calculation[
						'valid'
					]
				)
			) {
				$this->throw_order_exception(
					__(
						'One of the selected Special Discounts could not be calculated.',
						'eilmo-checkout-flow'
					)
				);
			}

			$requested_discount =
				$this->normalize_amount(
					$calculation[
						'discount'
					] ??
						0
				);

			$applied_discount =
				$this->apply_discount_to_items(
					$line_items,
					$requested_discount,
					self::ITEM_META_ORDER_BUMP_DISCOUNT
				);

			$offer_bump_total =
				$this->normalize_amount(
					max(
						0,
						$offer_regular_total -
							$applied_discount
					)
				);

			$apply_automatic_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			$apply_coupon =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_coupon',
					false,
					$offer
				);

			$apply_full_payment_discount =
				$this->get_saved_offer_compatibility_flag(
					$settings,
					'order_bumps',
					$bump_id,
					'apply_full_payment_discount',
					true,
					$offer
				);

			foreach ( $line_items as $line_item ) {

				if (
					! is_object(
						$line_item
					) ||
					! method_exists(
						$line_item,
						'update_meta_data'
					)
				) {
					continue;
				}

				$line_item->update_meta_data(
					self::ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC,
					$apply_automatic_discount
				);

				$line_item->update_meta_data(
					self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
					$apply_coupon
				);

				$line_item->update_meta_data(
					self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,
					$apply_full_payment_discount
				);

				if (
					method_exists(
						$line_item,
						'save'
					)
				) {
					$line_item->save();
				}
			}

			$selected_ids[] =
				$bump_id;

			$regular_total =
				$this->normalize_amount(
					$regular_total +
						$offer_regular_total
				);

			$total_discount =
				$this->normalize_amount(
					$total_discount +
						$applied_discount
				);

			$total_bump_price =
				$this->normalize_amount(
					$total_bump_price +
						$offer_bump_total
				);

			$resolved_offers[] =
				array(
					'id' =>
						$bump_id,

					'title' =>
						sanitize_text_field(
							(string) (
								$offer[
									'title'
								] ??
									$bump_id
							)
						),

					'offer_type' =>
						sanitize_key(
							(string) (
								$offer['offer_type'] ??
									'single_product'
							)
						),

					'condition_type' =>
						sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) ),

					'condition_minimum' =>
						$this->normalize_amount( $offer['condition_minimum'] ?? 0 ),

					'condition_maximum' =>
						$this->normalize_amount( $offer['condition_maximum'] ?? 0 ),

					'condition_product_id' =>
						absint( $offer['condition_product_id'] ?? 0 ),

					'condition_product_quantity' =>
						max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ),

					'condition_cart_quantity' =>
						max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ),

					'product_id' =>
						absint(
							$offer[
								'product_id'
							] ??
								0
						),

					'variation_id' =>
						absint(
							$offer[
								'variation_id'
							] ??
								0
						),

					'quantity' =>
						$this->normalize_quantity(
							$offer[
								'quantity'
							] ??
								0
						),

					'pricing_type' =>
						sanitize_key(
							(string) (
								$calculation[
									'pricing_type'
								] ??
									''
							)
						),

					'pricing_value' =>
						$this->normalize_amount(
							$calculation[
								'pricing_value'
							] ??
								0
						),

					'regular_total' =>
						$offer_regular_total,

					'discount' =>
						$applied_discount,

					'bump_total' =>
						$offer_bump_total,

					'apply_automatic_discount' =>
						$apply_automatic_discount,

					'apply_coupon' =>
						$apply_coupon,

					'apply_full_payment_discount' =>
						$apply_full_payment_discount,

					'items' =>
						$this->build_order_bump_item_snapshot(
							$line_items
						),
				);
		}

		return array(
			'selected_ids' =>
				array_values(
					array_unique(
						$selected_ids
					)
				),

			'offers' =>
				$resolved_offers,

			'regular_total' =>
				$regular_total,

			'discount' =>
				$total_discount,

			'bump_total' =>
				$total_bump_price,
		);
	}

	/**
	 * Get Order Bump line items.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $bump_id Order Bump ID.
	 *
	 * @return array<int, object>
	 */
	private function get_order_bump_line_items(
		WC_Order $order,
		string $bump_id
	): array {

		$bump_id =
			sanitize_key(
				$bump_id
			);

		if ( '' === $bump_id ) {
			return array();
		}

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object(
					$item
				) ||
				! method_exists(
					$item,
					'get_meta'
				)
			) {
				continue;
			}

			$item_bump_id =
				sanitize_key(
					(string) $item
						->get_meta(
							self::ITEM_META_ORDER_BUMP_ID,
							true
						)
				);

			if (
				$bump_id ===
					$item_bump_id
			) {
				$items[] =
					$item;
			}
		}

		return array_values(
			$items
		);
	}

	/**
	 * Get line items for Combo.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $combo_id Combo ID.
	 *
	 * @return array<int, object>
	 */
	private function get_combo_line_items(
		WC_Order $order,
		string $combo_id
	): array {

		$combo_id =
			sanitize_key(
				$combo_id
			);

		if ( '' === $combo_id ) {
			return array();
		}

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object(
					$item
				) ||
				! method_exists(
					$item,
					'get_meta'
				)
			) {
				continue;
			}

			$item_combo_id =
				sanitize_key(
					(string) $item
						->get_meta(
							self::ITEM_META_COMBO_ID,
							true
						)
				);

			if (
				$combo_id ===
					$item_combo_id
			) {
				$items[] =
					$item;
			}
		}

		return $items;
	}

	/**
	 * Apply discount across specified items.
	 *
	 * @param array<int, object> $items    Items.
	 * @param float              $amount   Discount.
	 * @param string             $meta_key Optional per-line discount meta key.
	 *
	 * @return float
	 */
	private function apply_discount_to_items(
		array $items,
		float $amount,
		string $meta_key = ''
	): float {

		$amount =
			$this->normalize_amount(
				$amount
			);

		if (
			$amount <= 0 ||
			empty( $items )
		) {
			return 0.0;
		}

		$eligible_items =
			array_values(
				array_filter(
					$items,
					static function (
						$item
					): bool {

						return (
							is_object( $item ) &&
							method_exists(
								$item,
								'get_total'
							) &&
							method_exists(
								$item,
								'set_total'
							) &&
							(float) $item
								->get_total() > 0
						);
					}
				)
			);

		if ( empty( $eligible_items ) ) {
			return 0.0;
		}

		$current_total =
			0.0;

		foreach ( $eligible_items as $item ) {
			$current_total +=
				max(
					0,
					(float) $item
						->get_total()
				);
		}

		$current_total =
			$this->normalize_amount(
				$current_total
			);

		if ( $current_total <= 0 ) {
			return 0.0;
		}

		$target_discount =
			min(
				$amount,
				$current_total
			);

		$remaining_discount =
			$target_discount;

		$item_count =
			count(
				$eligible_items
			);

		foreach (
			$eligible_items as
			$index => $item
		) {

			$item_total =
				$this->normalize_amount(
					$item->get_total()
				);

			if ( $item_total <= 0 ) {
				continue;
			}

			$is_last =
				$index ===
					(
						$item_count -
							1
					);

			if ( $is_last ) {
				$item_discount =
					min(
						$item_total,
						$remaining_discount
					);
			} else {
				$item_discount =
					$this->normalize_amount(
						$target_discount *
							(
								$item_total /
									$current_total
							)
					);

				$item_discount =
					min(
						$item_total,
						$item_discount,
						$remaining_discount
					);
			}

			if ( $item_discount <= 0 ) {
				continue;
			}

			$item->set_total(
				$this->normalize_amount(
					max(
						0,
						$item_total -
							$item_discount
					)
				)
			);

			/*
			 * Preserve the source-specific Eilmo discount amount
			 * for this line item. WooCommerce's native line discount
			 * combines all reductions into subtotal minus total.
			 */
			if (
				'' !== $meta_key &&
				method_exists(
					$item,
					'get_meta'
				) &&
				method_exists(
					$item,
					'update_meta_data'
				)
			) {
				$existing_discount =
					$this->normalize_amount(
						$item->get_meta(
							$meta_key,
							true
						)
					);

				$item->update_meta_data(
					$meta_key,
					$this->normalize_amount(
						$existing_discount +
							$item_discount
					)
				);
			}

			if (
				method_exists(
					$item,
					'save'
				)
			) {
				$item->save();
			}

			$remaining_discount =
				$this->normalize_amount(
					max(
						0,
						$remaining_discount -
							$item_discount
					)
				);

			if (
				$remaining_discount <= 0
			) {
				break;
			}
		}

		return $this->normalize_amount(
			$target_discount -
				$remaining_discount
		);
	}

	/**
	 * Build Combo product snapshot.
	 *
	 * @param array<int, object> $line_items Items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_combo_item_snapshot(
		array $line_items
	): array {

		$items =
			array();

		foreach ( $line_items as $line_item ) {

			if (
				! is_object(
					$line_item
				) ||
				! method_exists(
					$line_item,
					'get_product_id'
				)
			) {
				continue;
			}

			$quantity =
				method_exists(
					$line_item,
					'get_quantity'
				)
					? $this->normalize_quantity(
						$line_item
							->get_quantity()
					)
					: 0.0;

			$items[] =
				array(
					'product_id' =>
						absint(
							$line_item
								->get_product_id()
						),

					'variation_id' =>
						method_exists(
							$line_item,
							'get_variation_id'
						)
							? absint(
								$line_item
									->get_variation_id()
							)
							: 0,

					'quantity' =>
						$quantity,
				);
		}

		return $items;
	}

	/**
	 * Build Order Bump product snapshot.
	 *
	 * @param array<int, object> $line_items Items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_order_bump_item_snapshot(
		array $line_items
	): array {

		$items =
			array();

		foreach ( $line_items as $line_item ) {

			if (
				! is_object(
					$line_item
				) ||
				! method_exists(
					$line_item,
					'get_product_id'
				)
			) {
				continue;
			}

			$quantity =
				method_exists(
					$line_item,
					'get_quantity'
				)
					? $this->normalize_quantity(
						$line_item
							->get_quantity()
					)
					: 0.0;

			$items[] =
				array(
					'product_id' =>
						absint(
							$line_item
								->get_product_id()
						),

					'variation_id' =>
						method_exists(
							$line_item,
							'get_variation_id'
						)
							? absint(
								$line_item
									->get_variation_id()
							)
							: 0,

					'quantity' =>
						$quantity,
				);
		}

		return $items;
	}

	/**
	 * Build authoritative product context.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string, mixed>
	 */
	private function build_product_context(
		WC_Order $order
	): array {

		$product_total =
			0.0;

		$product_ids =
			array();

		$category_ids =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {

			if (
				! is_object( $item ) ||
				! method_exists(
					$item,
					'get_product'
				)
			) {
				continue;
			}

			$product =
				$item->get_product();

			if ( ! $product ) {
				continue;
			}

			$product_total +=
				(float) $item
					->get_subtotal();

			$product_id =
				absint(
					$item->get_product_id()
				);

			$variation_id =
				absint(
					$item->get_variation_id()
				);

			if ( $product_id > 0 ) {
				$product_ids[] =
					$product_id;
			}

			if ( $variation_id > 0 ) {
				$product_ids[] =
					$variation_id;
			}

			$category_product_id =
				$product_id > 0
					? $product_id
					: $product->get_id();

			$terms =
				wp_get_post_terms(
					$category_product_id,
					'product_cat',
					array(
						'fields' =>
							'ids',
					)
				);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_id ) {
				$category_ids[] =
					absint(
						$term_id
					);
			}
		}

		return array(
			'product_total' =>
				$this->normalize_amount(
					$product_total
				),

			'product_ids' =>
				array_values(
					array_unique(
						array_filter(
							$product_ids
						)
					)
				),

			'category_ids' =>
				array_values(
					array_unique(
						array_filter(
							$category_ids
						)
					)
				),
		);
	}

	/**
	 * Build product context from specified line items.
	 *
	 * @param array<int, object> $items        Line items.
	 * @param bool               $use_subtotal Use regular subtotal instead of current total.
	 *
	 * @return array<string, mixed>
	 */
	private function build_line_item_product_context(
		array $items,
		bool $use_subtotal = false
	): array {

		$product_total =
			0.0;

		$product_ids =
			array();

		$category_ids =
			array();

		$product_quantities = array();
		$product_totals = array();
		$cart_quantity = 0.0;

		foreach ( $items as $item ) {

			if (
				! is_object( $item ) ||
				! method_exists(
					$item,
					'get_product'
				)
			) {
				continue;
			}

			$product =
				$item->get_product();

			if ( ! $product ) {
				continue;
			}

			$line_amount = 0.0;
			if ( $use_subtotal && method_exists( $item, 'get_subtotal' ) ) {
				$line_amount = max( 0, (float) $item->get_subtotal() );
			} elseif ( method_exists( $item, 'get_total' ) ) {
				$line_amount = max( 0, (float) $item->get_total() );
			}
			$product_total += $line_amount;

			$product_id =
				method_exists(
					$item,
					'get_product_id'
				)
					? absint(
						$item->get_product_id()
					)
					: 0;

			$variation_id =
				method_exists(
					$item,
					'get_variation_id'
				)
					? absint(
						$item->get_variation_id()
					)
					: 0;

			if ( $product_id > 0 ) {
				$product_ids[] =
					$product_id;
			}

			if ( $variation_id > 0 ) {
				$product_ids[] =
					$variation_id;
			}

			$quantity = method_exists( $item, 'get_quantity' )
				? max( 0, (float) $item->get_quantity() )
				: 0.0;
			$cart_quantity += $quantity;
			if ( $product_id > 0 ) {
				$product_quantities[ $product_id ] =
					(float) ( $product_quantities[ $product_id ] ?? 0 ) + $quantity;
			}
			if ( $variation_id > 0 ) {
				$product_quantities[ $variation_id ] =
					(float) ( $product_quantities[ $variation_id ] ?? 0 ) + $quantity;
			}
			if ( $product_id > 0 ) {
				$product_totals[ $product_id ] = $this->normalize_amount( (float) ( $product_totals[ $product_id ] ?? 0 ) + $line_amount );
			}
			if ( $variation_id > 0 ) {
				$product_totals[ $variation_id ] = $this->normalize_amount( (float) ( $product_totals[ $variation_id ] ?? 0 ) + $line_amount );
			}

			$category_product_id =
				$product_id > 0
					? $product_id
					: $product->get_id();

			$terms =
				wp_get_post_terms(
					$category_product_id,
					'product_cat',
					array(
						'fields' =>
							'ids',
					)
				);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_id ) {
				$category_ids[] =
					absint(
						$term_id
					);
			}
		}

		return array(
			'product_total' =>
				$this->normalize_amount(
					$product_total
				),

			'product_ids' =>
				array_values(
					array_unique(
						array_filter(
							$product_ids
						)
					)
				),

			'category_ids' =>
				array_values(
					array_unique(
						array_filter(
							$category_ids
						)
					)
				),

			'cart_quantity' =>
				$cart_quantity,

			'product_quantities' =>
				$product_quantities,

			'product_totals' =>
				$product_totals,
		);
	}

	/**
	 * Calculate Automatic Discount.
	 *
	 * @param array<string, mixed> $context Product eligibility context.
	 *
	 * @return array<string, mixed>
	 */
	private function calculate_automatic_discount(
		array $context
	): array {
		$product_total = $this->normalize_amount( $context['product_total'] ?? 0 );

		if (
			$product_total <= 0 ||
			! class_exists(
				AutomaticDiscountCalculator::class
			)
		) {
			return array(
				'matched' =>
					false,

				'automatic_discount' =>
					0.0,
			);
		}

		$calculator =
			new AutomaticDiscountCalculator();

		$context['product_total'] = $product_total;
		$result = $calculator->calculate( $context );

		if (
			! is_array( $result ) ||
			empty(
				$result[
					'matched'
				]
			)
		) {
			return array(
				'matched' =>
					false,

				'automatic_discount' =>
					0.0,
			);
		}

		$result[
			'automatic_discount'
		] =
			$this->normalize_amount(
				$result[
					'automatic_discount'
				] ??
					0
			);

		return $result;
	}

	/**
	 * Calculate and validate coupon.
	 *
	 * @param string               $code               Coupon code.
	 * @param array<string, mixed> $product_context    Product context.
	 * @param array<string, mixed> $customer           Customer.
	 * @param float                $automatic_discount Automatic discount.
	 * @param array<string, mixed> $settings                           Settings.
	 * @param float                $coupon_eligible_automatic_discount Automatic discount overlapping Coupon-eligible lines.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function calculate_coupon(
		string $code,
		array $product_context,
		array $customer,
		float $automatic_discount,
		array $settings,
		float $coupon_eligible_automatic_discount = -1.0
	) {

		if ( '' === $code ) {
			return $this->get_empty_coupon_result();
		}

		$coupons =
			isset(
				$settings[
					'coupons'
				]
			) &&
			is_array(
				$settings[
					'coupons'
				]
			)
				? $settings[
					'coupons'
				]
				: array();

		if (
			'yes' !==
				(
					$coupons[
						'enabled'
					] ??
						'yes'
				)
		) {
			return new WP_Error(
				'coupons_disabled',
				__(
					'Coupons are currently disabled.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! class_exists(
				CouponCalculator::class
			)
		) {
			return new WP_Error(
				'coupon_service_unavailable',
				__(
					'Coupon validation is currently unavailable.',
					'eilmo-checkout-flow'
				)
			);
		}

		$product_total =
			$this->normalize_amount(
				$product_context[
					'product_total'
				] ??
					0
			);

		$discounted_product_total =
			$product_total;

		$stacking_automatic_discount =
			$coupon_eligible_automatic_discount >= 0
				? $this->normalize_amount(
					$coupon_eligible_automatic_discount
				)
				: $automatic_discount;

		if (
			$this->coupon_stacking_allowed(
				$settings
			) &&
			$stacking_automatic_discount > 0
		) {
			$discounted_product_total =
				$this->normalize_amount(
					max(
						0,
						$product_total -
							$stacking_automatic_discount
					)
				);
		}

		$customer_context =
			$this->build_coupon_customer_context(
				$code,
				$customer,
				$coupons
			);

		$context =
			array_merge(
				$product_context,
				$customer_context,
				array(
					'product_total' =>
						$product_total,

					'discounted_product_total' =>
						$discounted_product_total,

					'automatic_discount' =>
						$automatic_discount,

					'existing_coupon_discount' =>
						0.0,
				)
			);

		$context =
			apply_filters(
				'eilmo_cf/coupons/order_context',
				$context,
				$code
			);

		if ( ! is_array( $context ) ) {
			$context =
				array();
		}

		$calculator =
			new CouponCalculator();

		$result =
			$calculator->calculate(
				$code,
				$context
			);

		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'invalid_coupon',
				__(
					'This coupon could not be applied.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! empty(
				$result[
					'delegate_to_woocommerce'
				]
			)
		) {
			$coupon =
				class_exists(
					'WC_Coupon'
				)
					? new \WC_Coupon(
						$code
					)
					: null;

			if (
				! $coupon ||
				! $coupon->get_id()
			) {
				return new WP_Error(
					'invalid_coupon',
					__(
						'Invalid coupon code.',
						'eilmo-checkout-flow'
					)
				);
			}

			return array(
				'valid' =>
					true,

				'applied' =>
					false,

				'source' =>
					'woocommerce',

				'delegate_to_woocommerce' =>
					true,

				'coupon_code' =>
					$code,

				'coupon_type' =>
					sanitize_key(
						(string) $coupon
							->get_discount_type()
					),

				'coupon_discount' =>
					0.0,

				'free_delivery' =>
					(bool) $coupon
						->get_free_shipping(),
			);
		}

		if (
			empty(
				$result[
					'valid'
				]
			) ||
			empty(
				$result[
					'applied'
				]
			)
		) {
			return new WP_Error(
				sanitize_key(
					(string) (
						$result[
							'error_code'
						] ??
							'invalid_coupon'
					)
				),
				sanitize_text_field(
					(string) (
						$result[
							'message'
						] ??
							__(
								'This coupon could not be applied.',
								'eilmo-checkout-flow'
							)
					)
				)
			);
		}

		$result[
			'source'
		] =
			'eilmo';

		$result[
			'coupon_discount'
		] =
			$this->normalize_amount(
				$result[
					'coupon_discount'
				] ??
					0
			);

		return $result;
	}

	/**
	 * Empty coupon result.
	 *
	 * @return array<string, mixed>
	 */
	private function get_empty_coupon_result(): array {

		return array(
			'valid' =>
				true,

			'applied' =>
				false,

			'source' =>
				'',

			'coupon_code' =>
				'',

			'coupon_type' =>
				'',

			'coupon_discount' =>
				0.0,

			'free_delivery' =>
				false,
		);
	}

	/**
	 * Build final coupon customer context.
	 *
	 * @param string               $code     Coupon.
	 * @param array<string, mixed> $customer Customer.
	 * @param array<string, mixed> $settings Coupon settings.
	 *
	 * @return array<string, mixed>
	 */
	private function build_coupon_customer_context(
		string $code,
		array $customer,
		array $settings
	): array {

		$identity =
			$this->resolve_customer_identity(
				$customer
			);

		$customer_id =
			absint(
				$identity[
					'customer_id'
				] ??
					get_current_user_id()
			);

		$customer_email =
			sanitize_email(
				(string) (
					$identity[
						'customer_email'
					] ??
						''
				)
			);

		$customer_phone =
			sanitize_text_field(
				(string) (
					$identity[
						'customer_phone'
					] ??
						''
				)
			);

		$billing_country =
			strtoupper(
				sanitize_key(
					(string) (
						$identity[
							'billing_country'
						] ??
							''
					)
				)
			);

		$mode =
			sanitize_key(
				(string) (
					$settings[
						'customer_identification'
					] ??
						'email_or_phone'
				)
			);

		if (
			! in_array(
				$mode,
				array(
					'email',
					'phone',
					'email_or_phone',
				),
				true
			)
		) {
			$mode =
				'email_or_phone';
		}

		$identity_known =
			$customer_id > 0 ||
			'' !==
				$customer_email ||
			'' !==
				$customer_phone;

		return array(
			'customer_id' =>
				$customer_id,

			'customer_email' =>
				$customer_email,

			'customer_phone' =>
				$customer_phone,

			'billing_country' =>
				$billing_country,

			'identification_mode' =>
				$mode,

			'identity_known' =>
				$identity_known,

			'is_new_customer' =>
				$identity_known
					? $this->is_new_customer(
						$identity,
						$mode
					)
					: false,

			'usage_count' =>
				$this->get_coupon_usage_count(
					$code
				),

			'customer_usage_count' =>
				$identity_known
					? $this->get_customer_coupon_usage_count(
						$code,
						$identity,
						$mode
					)
					: 0,
		);
	}

	/**
	 * Resolve customer identity.
	 *
	 * @param array<string, mixed> $customer Customer.
	 *
	 * @return array<string, mixed>
	 */
	private function resolve_customer_identity(
		array $customer
	): array {

		$context =
			array(
				'customer_id' =>
					get_current_user_id(),

				'customer_email' =>
					sanitize_email(
						(string) (
							$customer[
								'billing_email'
							] ??
								''
						)
					),

				'customer_phone' =>
					sanitize_text_field(
						(string) (
							$customer[
								'billing_phone'
							] ??
								''
						)
					),

				'billing_country' =>
					strtoupper(
						sanitize_key(
							(string) (
								$customer[
									'billing_country'
								] ??
									''
							)
						)
					),
			);

		if (
			class_exists(
				CustomerIdentity::class
			)
		) {
			$resolver =
				new CustomerIdentity();

			$identity =
				$resolver->resolve(
					$context
				);

			if ( is_array( $identity ) ) {
				return $identity;
			}
		}

		return array_merge(
			$context,
			array(
				'identity_known' =>
					$context[
						'customer_id'
					] > 0 ||
					'' !==
						$context[
							'customer_email'
						] ||
					'' !==
						$context[
							'customer_phone'
						],
			)
		);
	}

	/**
	 * Determine whether customer is new.
	 *
	 * @param array<string, mixed> $identity Identity.
	 * @param string               $mode     Identification mode.
	 *
	 * @return bool
	 */
	private function is_new_customer(
		array $identity,
		string $mode
	): bool {

		if (
			! function_exists(
				'wc_get_orders'
			)
		) {
			return false;
		}

		$orders =
			wc_get_orders(
				array(
					'limit' =>
						-1,

					'status' =>
						$this->get_usage_statuses(),

					'return' =>
						'objects',
				)
			);

		if ( ! is_array( $orders ) ) {
			return false;
		}

		foreach ( $orders as $order ) {

			if (
				$order instanceof
					WC_Order &&
				$this->order_matches_identity(
					$order,
					$identity,
					$mode
				)
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Total custom coupon usage.
	 *
	 * @param string $code Coupon.
	 *
	 * @return int
	 */
	private function get_coupon_usage_count(
		string $code
	): int {

		if (
			'' === $code ||
			! function_exists(
				'wc_get_orders'
			)
		) {
			return 0;
		}

		$orders =
			wc_get_orders(
				array(
					'limit' =>
						-1,

					'return' =>
						'ids',

					'status' =>
						$this->get_usage_statuses(),

					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Coupon usage compatibility requires lookup by the plugin-owned coupon metadata key.
					'meta_query' =>
						array(
							array(
								'key' =>
									$this->get_coupon_meta_key(),

								'value' =>
									$code,

								'compare' =>
									'=',
							),
						),
				)
			);

		return is_array( $orders )
			? count( $orders )
			: 0;
	}

	/**
	 * Per-customer coupon usage count.
	 *
	 * @param string               $code     Coupon.
	 * @param array<string, mixed> $identity Identity.
	 * @param string               $mode     Mode.
	 *
	 * @return int
	 */
	private function get_customer_coupon_usage_count(
		string $code,
		array $identity,
		string $mode
	): int {

		if (
			'' === $code ||
			! function_exists(
				'wc_get_orders'
			)
		) {
			return 0;
		}

		$orders =
			wc_get_orders(
				array(
					'limit' =>
						-1,

					'return' =>
						'objects',

					'status' =>
						$this->get_usage_statuses(),

					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Coupon usage compatibility requires lookup by the plugin-owned coupon metadata key.
					'meta_query' =>
						array(
							array(
								'key' =>
									$this->get_coupon_meta_key(),

								'value' =>
									$code,

								'compare' =>
									'=',
							),
						),
				)
			);

		if ( ! is_array( $orders ) ) {
			return 0;
		}

		$count =
			0;

		foreach ( $orders as $order ) {

			if (
				$order instanceof
					WC_Order &&
				$this->order_matches_identity(
					$order,
					$identity,
					$mode
				)
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Match order to customer identity.
	 *
	 * @param WC_Order             $order    Order.
	 * @param array<string, mixed> $identity Identity.
	 * @param string               $mode     Mode.
	 *
	 * @return bool
	 */
	private function order_matches_identity(
		WC_Order $order,
		array $identity,
		string $mode
	): bool {

		$customer_id =
			absint(
				$identity[
					'customer_id'
				] ??
					0
			);

		if (
			$customer_id > 0 &&
			$customer_id ===
				(int) $order
					->get_customer_id()
		) {
			return true;
		}

		$email =
			strtolower(
				sanitize_email(
					(string) (
						$identity[
							'customer_email'
						] ??
							''
					)
				)
			);

		$order_email =
			strtolower(
				sanitize_email(
					(string) $order
						->get_billing_email()
				)
			);

		if (
			in_array(
				$mode,
				array(
					'email',
					'email_or_phone',
				),
				true
			) &&
			'' !== $email &&
			$email ===
				$order_email
		) {
			return true;
		}

		$phone =
			$this->normalize_phone(
				(string) (
					$identity[
						'customer_phone'
					] ??
						''
				),
				(string) (
					$identity[
						'billing_country'
					] ??
						''
				)
			);

		$order_phone =
			$this->normalize_phone(
				(string) $order
					->get_billing_phone(),
				(string) $order
					->get_billing_country()
			);

		if (
			in_array(
				$mode,
				array(
					'phone',
					'email_or_phone',
				),
				true
			) &&
			'' !== $phone &&
			$phone ===
				$order_phone
		) {
			return true;
		}

		return false;
	}

	/**
	 * Normalize phone.
	 *
	 * @param string $phone   Phone.
	 * @param string $country Country.
	 *
	 * @return string
	 */
	private function normalize_phone(
		string $phone,
		string $country = ''
	): string {

		$phone =
			preg_replace(
				'/\D+/',
				'',
				$phone
			);

		$phone =
			is_string( $phone )
				? $phone
				: '';

		if (
			0 === strpos(
				$phone,
				'00'
			)
		) {
			$phone =
				substr(
					$phone,
					2
				);
		}

		$country =
			strtoupper(
				sanitize_key(
					$country
				)
			);

		if (
			'BD' === $country ||
			0 === strpos(
				$phone,
				'880'
			) ||
			(
				11 === strlen(
					$phone
				) &&
				0 === strpos(
					$phone,
					'01'
				)
			)
		) {
			if (
				11 === strlen(
					$phone
				) &&
				0 === strpos(
					$phone,
					'01'
				)
			) {
				$phone =
					'88' .
						$phone;
			}
		}

		return (string) apply_filters(
			'eilmo_cf/coupons/normalized_phone',
			$phone,
			$country
		);
	}

	/**
	 * Coupon statuses.
	 *
	 * @return array<int, string>
	 */
	private function get_usage_statuses(): array {

		return array(
			'wc-pending',
			'wc-on-hold',
			'wc-processing',
			'wc-completed',
		);
	}

	/**
	 * Coupon order meta key.
	 *
	 * @return string
	 */
	private function get_coupon_meta_key(): string {

		$constant =
			CouponCalculator::class .
				'::ORDER_COUPON_META_KEY';

		if ( defined( $constant ) ) {
			return (string) constant(
				$constant
			);
		}

		return '_eilmo_cf_coupon_code';
	}

	/**
	 * Global coupon stacking.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return bool
	 */
	private function coupon_stacking_allowed(
		array $settings
	): bool {

		$discounts =
			isset(
				$settings[
					'discounts'
				]
			) &&
			is_array(
				$settings[
					'discounts'
				]
			)
				? $settings[
					'discounts'
				]
				: array();

		return (
			'yes' ===
				(
					$discounts[
						'allow_coupon_stacking'
					] ??
						'no'
				)
		);
	}

	/**
	 * Apply Automatic Discount to eligible products.
	 *
	 * Combo Offer components are always excluded.
	 * Order Bumps are included only when the saved
	 * Order Bump configuration explicitly allows it.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Discount.
	 *
	 * @return float
	 */
	private function apply_automatic_discount(
		WC_Order $order,
		float $amount
	): float {

		$items =
			$this->get_automatic_discount_line_items(
				$order
			);

		return $this->apply_discount_to_items(
			$items,
			$amount,
			self::ITEM_META_AUTOMATIC_DISCOUNT
		);
	}

	/**
	 * Apply custom Eilmo Coupon Discount to eligible
	 * products.
	 *
	 * Normal lines are eligible. Combo and Order Bump
	 * lines are included only when their saved compatibility
	 * allows Coupon Discount.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Discount.
	 *
	 * @return float
	 */
	private function apply_coupon_discount(
		WC_Order $order,
		float $amount
	): float {

		return $this->apply_discount_to_items(
			$this->get_coupon_discount_line_items(
				$order
			),
			$amount,
			self::ITEM_META_COUPON_DISCOUNT
		);
	}

	/**
	 * Apply product discount.
	 *
	 * @param WC_Order $order    Order.
	 * @param float    $amount   Discount.
	 * @param string   $meta_key Optional per-line discount meta key.
	 *
	 * @return float
	 */
	private function apply_product_discount(
		WC_Order $order,
		float $amount,
		string $meta_key = ''
	): float {

		$items =
			$order->get_items(
				'line_item'
			);

		return $this->apply_discount_to_items(
			is_array( $items )
				? array_values( $items )
				: array(),
			$amount,
			$meta_key
		);
	}

	/**
	 * Apply WooCommerce native coupon.
	 *
	 * WooCommerce performs its own coupon validation and
	 * product targeting. After WooCommerce calculates the
	 * coupon, any Order Bump line that explicitly disables
	 * Coupon stacking is restored to its pre-coupon total.
	 *
	 * This keeps two occurrences of the same WooCommerce
	 * product independent: a normal line can receive the
	 * coupon while an Order Bump line using the same product
	 * can remain excluded.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $code  Coupon.
	 *
	 * @return float|WP_Error
	 */
    private function apply_woocommerce_coupon(
        WC_Order $order,
        string $code
    ) {

        if (
            ! class_exists(
                'WC_Coupon'
            )
        ) {
            return new WP_Error(
                'woocommerce_coupon_unavailable',
                __(
                    'WooCommerce coupons are not available.',
                    'eilmo-checkout-flow'
                )
            );
        }

        $normalized_code =
            $this->normalize_coupon_code(
                $code
            );

        if ( '' === $normalized_code ) {
            return new WP_Error(
                'invalid_coupon',
                __(
                    'Invalid coupon code.',
                    'eilmo-checkout-flow'
                )
            );
        }

        $before =
            $this->normalize_amount(
                $order->get_discount_total()
            );

        $before_tax =
            $this->normalize_amount(
                method_exists(
                    $order,
                    'get_discount_tax'
                )
                    ? $order->get_discount_tax()
                    : 0
            );

        /*
        * --------------------------------------------------
        * Promotional native WooCommerce Coupon isolation
        * --------------------------------------------------
        *
        * Combo and Order Bump lines with apply_coupon="no"
        * must not participate in native WooCommerce coupon
        * allocation.
        *
        * Filtering the actual WC_Discounts item collection is
        * important because the same WooCommerce product can exist
        * simultaneously as:
        *
        * - A normal product line.
        * - A Combo line.
        * - An Order Bump line.
        *
        * Product-ID-only coupon filters cannot distinguish those
        * purchase contexts. The WC_Discounts item object contains
        * the exact WC_Order_Item_Product, so we can exclude only
        * the disallowed promotional occurrence while leaving the
        * normal line eligible.
        */
        $excluded_items =
            $this->get_coupon_excluded_line_items(
                $order
            );

        $excluded_item_ids =
            array();

        $excluded_totals =
            array();

        foreach ( $excluded_items as $item ) {

            if (
                ! is_object( $item ) ||
                ! method_exists(
                    $item,
                    'get_id'
                )
            ) {
                continue;
            }

            $item_id =
                absint(
                    $item->get_id()
                );

            if ( $item_id <= 0 ) {
                continue;
            }

            $excluded_item_ids[
                $item_id
            ] =
                true;

            if (
                method_exists(
                    $item,
                    'get_total'
                )
            ) {
                $excluded_totals[
                    $item_id
                ] =
                    $this->normalize_amount(
                        $item->get_total()
                    );
            }
        }

        $filter_invoked =
            false;

        $coupon_items_filter =
            function (
                $items_to_apply,
                $coupon,
                $discounts
            ) use (
                &$filter_invoked,
                $excluded_item_ids,
                $normalized_code
            ) {
                unset( $discounts );

                if (
                    ! is_array(
                        $items_to_apply
                    ) ||
                    ! is_object(
                        $coupon
                    ) ||
                    ! method_exists(
                        $coupon,
                        'get_code'
                    )
                ) {
                    return $items_to_apply;
                }

                $coupon_code =
                    $this->normalize_coupon_code(
                        (string) $coupon->get_code()
                    );

                if (
                    $normalized_code !==
                        $coupon_code
                ) {
                    return $items_to_apply;
                }

                $filter_invoked =
                    true;

                if (
                    empty(
                        $excluded_item_ids
                    )
                ) {
                    return $items_to_apply;
                }

                $filtered_items =
                    array();

                foreach (
                    $items_to_apply as
                        $discount_item
                ) {
                    if (
                        ! is_object(
                            $discount_item
                        ) ||
                        ! isset(
                            $discount_item->object
                        ) ||
                        ! is_object(
                            $discount_item->object
                        ) ||
                        ! method_exists(
                            $discount_item->object,
                            'get_id'
                        )
                    ) {
                        $filtered_items[] =
                            $discount_item;

                        continue;
                    }

                    $order_item_id =
                        absint(
                            $discount_item
                                ->object
                                ->get_id()
                        );

                    if (
                        $order_item_id > 0 &&
                        isset(
                            $excluded_item_ids[
                                $order_item_id
                            ]
                        )
                    ) {
                        continue;
                    }

                    $filtered_items[] =
                        $discount_item;
                }

                return array_values(
                    $filtered_items
                );
            };

        /*
        * WooCommerce 8.8+ exposes the exact coupon item list
        * through woocommerce_coupon_get_items_to_apply.
        *
        * The filter is attached only for this synchronous
        * order->apply_coupon() call and is always removed.
        */
        add_filter(
            'woocommerce_coupon_get_items_to_apply',
            $coupon_items_filter,
            PHP_INT_MAX,
            3
        );

        try {

            $result =
                $order->apply_coupon(
                    $normalized_code
                );

        } finally {

            remove_filter(
                'woocommerce_coupon_get_items_to_apply',
                $coupon_items_filter,
                PHP_INT_MAX
            );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        /*
        * Backward-compatibility fallback.
        *
        * Older WooCommerce versions do not execute the
        * woocommerce_coupon_get_items_to_apply filter. In that
        * case preserve the previous restoration behavior so an
        * excluded Combo / Order Bump line still does not retain
        * a coupon reduction.
        */
        if (
            ! $filter_invoked &&
            ! empty(
                $excluded_totals
            )
        ) {
            foreach ( $excluded_items as $item ) {

                if (
                    ! is_object( $item ) ||
                    ! method_exists(
                        $item,
                        'get_id'
                    ) ||
                    ! method_exists(
                        $item,
                        'set_total'
                    )
                ) {
                    continue;
                }

                $item_id =
                    absint(
                        $item->get_id()
                    );

                if (
                    $item_id <= 0 ||
                    ! array_key_exists(
                        $item_id,
                        $excluded_totals
                    )
                ) {
                    continue;
                }

                $item->set_total(
                    $excluded_totals[
                        $item_id
                    ]
                );

                if (
                    method_exists(
                        $item,
                        'save'
                    )
                ) {
                    $item->save();
                }
            }

            $order->calculate_totals(
                true
            );
        }

        $after =
            $this->normalize_amount(
                $order->get_discount_total()
            );

        $after_tax =
            $this->normalize_amount(
                method_exists(
                    $order,
                    'get_discount_tax'
                )
                    ? $order->get_discount_tax()
                    : 0
            );

        $applied_discount =
            $this->normalize_amount(
                max(
                    0,
                    $after -
                        $before
                )
            );

        $applied_discount_tax =
            $this->normalize_amount(
                max(
                    0,
                    $after_tax -
                        $before_tax
                )
            );

        /*
        * In the modern filtered path WooCommerce already owns
        * the correct per-item and coupon-item allocation.
        *
        * Only the old-version fallback requires manual coupon
        * item synchronization after restoring excluded lines.
        */
        if (
            ! $filter_invoked &&
            ! empty(
                $excluded_totals
            )
        ) {
            $this->sync_woocommerce_coupon_item_discount(
                $order,
                $normalized_code,
                $applied_discount,
                $applied_discount_tax
            );
        }

        return $applied_discount;
    }

	/**
	 * Synchronize WooCommerce coupon order-item amounts
	 * after excluded Order Bump lines are restored.
	 *
	 * @param WC_Order $order        Order.
	 * @param string   $code         Coupon code.
	 * @param float    $discount     Discount.
	 * @param float    $discount_tax Discount tax.
	 *
	 * @return void
	 */
	private function sync_woocommerce_coupon_item_discount(
		WC_Order $order,
		string $code,
		float $discount,
		float $discount_tax
	): void {

		$normalized_code =
			$this->normalize_coupon_code(
				$code
			);

		if ( '' === $normalized_code ) {
			return;
		}

		foreach (
			$order->get_items(
				'coupon'
			) as $item
		) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$item_code =
				method_exists(
					$item,
					'get_code'
				)
					? (string) $item->get_code()
					: (
						method_exists(
							$item,
							'get_name'
						)
							? (string) $item->get_name()
							: ''
					);

			if (
				$normalized_code !==
					$this->normalize_coupon_code(
						$item_code
					)
			) {
				continue;
			}

			if (
				method_exists(
					$item,
					'set_discount'
				)
			) {
				$item->set_discount(
					$this->normalize_amount(
						$discount
					)
				);
			}

			if (
				method_exists(
					$item,
					'set_discount_tax'
				)
			) {
				$item->set_discount_tax(
					$this->normalize_amount(
						$discount_tax
					)
				);
			}

			if (
				method_exists(
					$item,
					'save'
				)
			) {
				$item->save();
			}

			break;
		}
	}

	/**
	 * Resolve authoritative delivery method.
	 *
	 * @param string               $selected_method Selected method.
	 * @param float                $order_total     Order total.
	 * @param array<string, mixed> $context         Context.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function resolve_delivery(
		string $selected_method,
		float $order_total,
		array $context
	) {

		$selected_method =
			sanitize_key(
				$selected_method
			);

		$settings =
			$this->get_section_settings(
				'delivery'
			);

		if (
			'yes' !==
				(
					$settings[
						'enabled'
					] ??
						'no'
				)
		) {
			return array(
				'method_id' =>
					'',

				'label' =>
					'',

				'charge' =>
					0.0,

				'base_charge' =>
					0.0,

				'free_delivery_applied' =>
					false,
			);
		}

		$methods =
			array();

		if (
			class_exists(
				DeliveryCalculator::class
			)
		) {
			$calculator =
				new DeliveryCalculator();

			$methods =
				$calculator
					->get_available_methods(
						$order_total,
						$context
					);

			if ( ! is_array( $methods ) ) {
				$methods =
					array();
			}
		}

		if ( empty( $methods ) ) {

			$configured =
				isset(
					$settings[
						'methods'
					]
				) &&
				is_array(
					$settings[
						'methods'
					]
				)
					? $settings[
						'methods'
					]
					: array();

			foreach (
				$configured as $method
			) {
				if (
					! is_array( $method ) ||
					'yes' !==
						(
							$method[
								'enabled'
							] ??
								'yes'
						)
				) {
					continue;
				}

				$methods[] =
					$method;
			}
		}

		if (
			'' === $selected_method &&
			class_exists(
				DeliveryCalculator::class
			)
		) {
			$calculator =
				isset( $calculator )
					? $calculator
					: new DeliveryCalculator();

			$selected_method =
				sanitize_key(
					(string) $calculator
						->get_default_method(
							$order_total,
							$context
						)
				);
		}

		if (
			'' === $selected_method &&
			! empty( $methods )
		) {
			$selected_method =
				sanitize_key(
					(string) (
						$methods[0][
							'id'
						] ??
							''
					)
				);
		}

		foreach ( $methods as $method ) {

			if ( ! is_array( $method ) ) {
				continue;
			}

			$method_id =
				sanitize_key(
					(string) (
						$method[
							'id'
						] ??
							''
					)
				);

			if (
				'' === $method_id ||
				$method_id !==
					$selected_method
			) {
				continue;
			}

			$charge =
				$this->normalize_amount(
					$method[
						'charge'
					] ??
						0
				);

			$base_charge =
				$this->normalize_amount(
					$method[
						'base_charge'
					] ??
						$charge
				);

			$free_delivery =
				! empty( $context['coupon_free_delivery'] ) ||
				! empty( $method['free_delivery_applied'] );

			if ( ! empty( $context['coupon_free_delivery'] ) ) {
				$charge = 0.0;
			}

			return array(
				'method_id' =>
					$method_id,

				'label' =>
					sanitize_text_field(
						(string) (
							$method[
								'label'
							] ??
							$method[
								'title'
							] ??
								$method_id
						)
					),

				'charge' =>
					$charge,

				'base_charge' =>
					$base_charge,

				'free_delivery_applied' =>
					$free_delivery ||
					! empty(
						$method[
							'free_delivery_applied'
						]
					),
			);
		}

		return new WP_Error(
			'invalid_delivery_method',
			__(
				'The selected delivery method is no longer available.',
				'eilmo-checkout-flow'
			)
		);
	}

	/**
	 * Add WooCommerce shipping item.
	 *
	 * @param WC_Order             $order           Order.
	 * @param array<string, mixed> $delivery        Delivery.
	 * @param float                $delivery_charge Charge.
	 *
	 * @return void
	 */
	private function add_delivery_item(
		WC_Order $order,
		array $delivery,
		float $delivery_charge
	): void {

		if (
			! class_exists(
				'WC_Order_Item_Shipping'
			)
		) {
			return;
		}

		$method_id =
			sanitize_key(
				(string) (
					$delivery[
						'method_id'
					] ??
						''
				)
			);

		if ( '' === $method_id ) {
			return;
		}

		$label =
			sanitize_text_field(
				(string) (
					$delivery[
						'label'
					] ??
						$method_id
				)
			);

		$shipping =
			new \WC_Order_Item_Shipping();

		$shipping->set_method_title(
			$label
		);

		$shipping->set_method_id(
			'eilmo_cf_' .
				$method_id
		);

		$shipping->set_total(
			$this->normalize_amount(
				$delivery_charge
			)
		);

		$shipping->add_meta_data(
			'_eilmo_cf_delivery_method_id',
			$method_id,
			true
		);

		$shipping->add_meta_data(
			'_eilmo_cf_delivery_base_charge',
			$this->normalize_amount(
				$delivery[
					'base_charge'
				] ??
					$delivery_charge
			),
			true
		);

		$shipping->add_meta_data(
			'_eilmo_cf_free_delivery_applied',
			! empty(
				$delivery[
					'free_delivery_applied'
				]
			)
				? 'yes'
				: 'no',
			true
		);

		$order->add_item(
			$shipping
		);
	}

	/**
	 * Calculate full-payment discount.
	 *
	 * @param array<string, mixed> $settings                 Settings.
	 * @param string               $payment_type             Payment type.
	 * @param float                $product_total            Product total.
	 * @param float                $discounted_product_total Discounted total.
	 * @param float                $grand_total              Grand total.
	 *
	 * @return float
	 */
	private function calculate_full_payment_discount(
		array $settings,
		string $payment_type,
		float $product_total,
		float $discounted_product_total,
		float $grand_total
	): float {

		if (
			'full' !==
				$payment_type
		) {
			return 0.0;
		}

		$discounts =
			isset(
				$settings[
					'discounts'
				]
			) &&
			is_array(
				$settings[
					'discounts'
				]
			)
				? $settings[
					'discounts'
				]
				: array();

		$full_payment =
			isset(
				$discounts[
					'full_payment'
				]
			) &&
			is_array(
				$discounts[
					'full_payment'
				]
			)
				? $discounts[
					'full_payment'
				]
				: array();

		if (
			'yes' !==
				(
					$full_payment[
						'enabled'
					] ??
						'no'
				)
		) {
			return 0.0;
		}

		$type =
			sanitize_key(
				(string) (
					$full_payment[
						'type'
					] ??
						'percentage'
				)
			);

		if (
			! in_array(
				$type,
				array(
					'percentage',
					'fixed',
				),
				true
			)
		) {
			$type =
				'percentage';
		}

		$value =
			$this->normalize_amount(
				$full_payment[
					'value'
				] ??
					0
			);

		if ( $value <= 0 ) {
			return 0.0;
		}

		if (
			'percentage' ===
				$type
		) {
			$value =
				min(
					100,
					$value
				);
		}

		$basis =
			sanitize_key(
				(string) (
					$full_payment[
						'basis'
					] ??
						'discounted_product_total'
				)
			);

		switch ( $basis ) {

			case 'product_total':
				$basis_amount =
					$product_total;
				break;

			case 'grand_total':
				$basis_amount =
					$grand_total;
				break;

			case 'discounted_product_total':
			default:
				$basis_amount =
					$discounted_product_total;
				break;
		}

		$basis_amount =
			$this->normalize_amount(
				$basis_amount
			);

		$minimum_amount =
			$this->normalize_amount(
				$full_payment[
					'minimum_amount'
				] ??
					0
			);

		if (
			$basis_amount <= 0 ||
			(
				$minimum_amount > 0 &&
				$basis_amount <
					$minimum_amount
			)
		) {
			return 0.0;
		}

		$discount =
			'fixed' ===
				$type
				? $value
				: (
					$basis_amount *
						(
							$value /
								100
						)
				);

		$discount =
			$this->normalize_amount(
				$discount
			);

		$maximum_discount =
			$this->normalize_amount(
				$full_payment[
					'maximum_discount'
				] ??
					0
			);

		if (
			$maximum_discount > 0 &&
			$discount >
				$maximum_discount
		) {
			$discount =
				$maximum_discount;
		}

		return $this->normalize_amount(
			min(
				$discount,
				$basis_amount,
				$grand_total
			)
		);
	}

	/**
	 * Apply Full Payment Discount to eligible products and,
	 * when required by the selected basis, shipping.
	 *
	 * Normal lines remain eligible. Combo and Order Bump
	 * lines are eligible only when their own saved compatibility
	 * allows Full Payment Discount stacking.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Amount.
	 *
	 * @return float
	 */
	private function apply_full_payment_discount(
		WC_Order $order,
		float $amount
	): float {

		$amount =
			$this->normalize_amount(
				$amount
			);

		if ( $amount <= 0 ) {
			return 0.0;
		}

		$product_discount =
			$this->apply_discount_to_items(
				$this->get_full_payment_discount_line_items(
					$order
				),
				$amount,
				self::ITEM_META_FULL_PAYMENT_DISCOUNT
			);

		$remaining =
			$this->normalize_amount(
				max(
					0,
					$amount -
						$product_discount
				)
			);

		$shipping_discount =
			0.0;

		if ( $remaining > 0 ) {
			$shipping_discount =
				$this->apply_shipping_discount(
					$order,
					$remaining
				);
		}

		return $this->normalize_amount(
			$product_discount +
				$shipping_discount
		);
	}

	/**
	 * Apply order-level discount.
	 *
	 * @param WC_Order $order    Order.
	 * @param float    $amount   Amount.
	 * @param string   $meta_key Optional product-line discount meta key.
	 *
	 * @return float
	 */
	private function apply_order_discount(
		WC_Order $order,
		float $amount,
		string $meta_key = ''
	): float {

		$amount =
			$this->normalize_amount(
				$amount
			);

		if ( $amount <= 0 ) {
			return 0.0;
		}

		$product_discount =
			$this->apply_product_discount(
				$order,
				$amount,
				$meta_key
			);

		$remaining =
			$this->normalize_amount(
				max(
					0,
					$amount -
						$product_discount
				)
			);

		$shipping_discount =
			0.0;

		if ( $remaining > 0 ) {
			$shipping_discount =
				$this->apply_shipping_discount(
					$order,
					$remaining
				);
		}

		return $this->normalize_amount(
			$product_discount +
				$shipping_discount
		);
	}

	/**
	 * Apply discount against shipping.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Amount.
	 *
	 * @return float
	 */
	private function apply_shipping_discount(
		WC_Order $order,
		float $amount
	): float {

		$amount =
			$this->normalize_amount(
				$amount
			);

		$remaining =
			$amount;

		$applied =
			0.0;

		foreach (
			$order->get_items(
				'shipping'
			) as $item
		) {

			$current =
				$this->normalize_amount(
					$item->get_total()
				);

			if (
				$current <= 0 ||
				$remaining <= 0
			) {
				continue;
			}

			$discount =
				min(
					$current,
					$remaining
				);

			$item->set_total(
				$this->normalize_amount(
					max(
						0,
						$current -
							$discount
					)
				)
			);

			$item->save();

			$applied =
				$this->normalize_amount(
					$applied +
						$discount
				);

			$remaining =
				$this->normalize_amount(
					max(
						0,
						$remaining -
							$discount
					)
				);
		}

		return $applied;
	}

	/**
	 * Calculate final advance payment.
	 *
	 * @param float  $product_total            Product total.
	 * @param float  $discounted_product_total Discounted product total.
	 * @param float  $delivery_charge          Delivery charge.
	 * @param float  $grand_total              Grand total.
	 * @param string $payment_type             Payment type.
	 *
	 * @return array<string, mixed>
	 */
	private function calculate_advance_payment(
		float $product_total,
		float $discounted_product_total,
		float $delivery_charge,
		float $grand_total,
		string $payment_type
	): array {

		/*
		 * Cash on Delivery is a top-level Payment Option.
		 *
		 * Nothing is payable during checkout. The complete
		 * authoritative grand total remains due on delivery.
		 *
		 * Do this before touching AdvanceCalculator so COD
		 * can never inherit an Advance / Full calculation.
		 */
		if (
			'cash_on_delivery' ===
				$payment_type
		) {
			return array(
				'enabled' =>
					true,

				'matched' =>
					true,

				'rule_id' =>
					'',

				'rule_type' =>
					'cash_on_delivery',

				'payment_type' =>
					'cash_on_delivery',

				'advance_amount' =>
					0.0,

				'pay_now' =>
					0.0,

				'remaining_due' =>
					$grand_total,

				'is_cash_on_delivery' =>
					true,

				'is_advance_payment' =>
					false,

				'is_full_payment' =>
					false,
			);
		}

		if (
			! class_exists(
				AdvanceCalculator::class
			)
		) {
			/*
			 * Full Payment remains safely deterministic even
			 * when the Advance calculator is unavailable.
			 *
			 * Preserve the historical fallback for Advance:
			 * the full order amount becomes payable instead
			 * of accepting a client-supplied partial amount.
			 */
			return array(
				'enabled' =>
					false,

				'matched' =>
					false,

				'rule_id' =>
					'',

				'rule_type' =>
					'',

				'payment_type' =>
					'full',

				'advance_amount' =>
					0.0,

				'pay_now' =>
					$grand_total,

				'remaining_due' =>
					0.0,

				'is_cash_on_delivery' =>
					false,

				'is_advance_payment' =>
					false,

				'is_full_payment' =>
					true,
			);
		}

		$calculator =
			new AdvanceCalculator();

		$result =
			$calculator->calculate(
				array(
					'product_total' =>
						$product_total,

					'discounted_product_total' =>
						$discounted_product_total,

					'delivery_charge' =>
						$delivery_charge,

					'grand_total' =>
						$grand_total,

					'payment_type' =>
						$payment_type,
				)
			);

		if (
			! is_array(
				$result
			)
		) {
			$result =
				array();
		}

		$result[
			'payment_type'
		] =
			$payment_type;

		$result[
			'is_cash_on_delivery'
		] =
			false;

		$result[
			'is_full_payment'
		] =
			'full' ===
				$payment_type;

		$result[
			'is_advance_payment'
		] =
			'advance' ===
				$payment_type;

		return $result;
	}

	/**
	 * Configure payment method.
	 *
	 * @param WC_Order             $order    Order.
	 * @param array<string, mixed> $payment  Payment data.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function configure_payment_method(
		WC_Order $order,
		array $payment,
		array $settings,
		string $payment_type
	) {

		$payment_type =
			sanitize_key(
				$payment_type
			);

		$method =
			sanitize_text_field(
				(string) (
					$payment[
						'method'
					] ??
						''
				)
			);

		$method_key =
			sanitize_key(
				(string) (
					$payment[
						'method_key'
					] ??
						''
				)
			);

		$source =
			sanitize_key(
				(string) (
					$payment[
						'source'
					] ??
						''
				)
			);

		$gateway_id =
			sanitize_key(
				(string) (
					$payment[
						'gateway_id'
					] ??
						''
				)
			);

		$transaction_id =
			sanitize_text_field(
				(string) (
					$payment[
						'transaction_id'
					] ??
						''
				)
			);

		/*
		 * OrderValidator has already authenticated this temporary token against
		 * the selected manual method. Preserve it while rebuilding the canonical
		 * payment array so screenshot-only proof can be attached to the order.
		 */
		$proof_token =
			sanitize_text_field(
				(string) (
					$payment[
						'proof_token'
					] ??
						''
				)
			);

		/*
		 * --------------------------------------------------
		 * Cash on Delivery
		 * --------------------------------------------------
		 *
		 * COD is selected as a Payment Option, not as a
		 * Payment Method. Never trust a browser-supplied
		 * method for this branch.
		 *
		 * OrderValidator already normalizes the same values.
		 * Rebuilding them here keeps OrderCreator safe if a
		 * filter or another integration modifies validated data.
		 */
		if (
			'cash_on_delivery' ===
				$payment_type
		) {
			$advance_settings =
				isset(
					$settings[
						'advance_payment'
					]
				) &&
				is_array(
					$settings[
						'advance_payment'
					]
				)
					? $settings[
						'advance_payment'
					]
					: array();

			if (
				'yes' !== (
					$advance_settings[
						'enabled'
					] ??
						'no'
				) ||
				'yes' !== (
					$advance_settings[
						'allow_cash_on_delivery'
					] ??
						'no'
				)
			) {
				return new WP_Error(
					'cash_on_delivery_unavailable',
					__(
						'Cash on Delivery is no longer available.',
						'eilmo-checkout-flow'
					)
				);
			}

			$gateway = $this->get_woocommerce_gateway( 'cod' );
			if ( ! $gateway ) {
				return new WP_Error( 'cash_on_delivery_unavailable', __( 'Cash on Delivery is no longer available.', 'eilmo-checkout-flow' ) );
			}
			$method = 'wc__cod';
			$method_key = 'cod';
			$source = 'woocommerce_gateway';
			$gateway_id = 'cod';
			$transaction_id = '';
			$title = preg_replace( '#<small\b[^>]*>.*?</small>#is', '', (string) $gateway->get_title() );
			$title = sanitize_text_field( (string) $title );
			$order->set_payment_method( $gateway );
			$order->set_payment_method_title( $title );
			return array(
				'method' => $method,
				'method_key' => $method_key,
				'source' => $source,
				'gateway_id' => $gateway_id,
				'transaction_id' => '',
				'proof_token' => '',
				'title' => $title,
			);
		}

		/*
		 * Advance / Full must never use COD.
		 */
		if (
			'eilmo_cash_on_delivery' ===
				$method ||
			'cash_on_delivery' ===
				$method_key ||
			'cod' ===
				$method_key ||
			'cod' ===
				$gateway_id ||
			'wc__cod' ===
				$method
		) {
			return new WP_Error(
				'cod_payment_method_not_allowed',
				__(
					'Cash on Delivery cannot be used with Advance or Full Payment.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			in_array( $source, array( 'woocommerce_gateway', 'eilmo_gateway' ), true )
		) {
			if (
				'' === $gateway_id ||
				'cod' === $gateway_id
			) {
				return new WP_Error(
					'invalid_payment_gateway',
					__(
						'The selected payment gateway is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$gateway =
				$this->get_woocommerce_gateway(
					$gateway_id
				);

			if ( ! $gateway ) {
				return new WP_Error(
					'payment_gateway_unavailable',
					__(
						'The selected payment gateway is no longer available.',
						'eilmo-checkout-flow'
					)
				);
			}

			$order->set_payment_method(
				$gateway
			);
			$title = preg_replace( '#<small\b[^>]*>.*?</small>#is', '', (string) $gateway->get_title() );
			$title = sanitize_text_field( (string) $title );
			$order->set_payment_method_title( $title );

			return array(
				'method' =>
					$method,

				'method_key' =>
					( 'eilmo_gateway' === $source ? $method_key : $gateway_id ),

				'source' =>
					$source,

				'gateway_id' =>
					$gateway_id,

				'transaction_id' =>
					( 'eilmo_gateway' === $source ? $transaction_id : '' ),

				'proof_token' =>
					( 'eilmo_gateway' === $source ? $proof_token : '' ),

				'title' =>
					$title,
			);
		}

		$title =
			$this->get_manual_payment_title(
				$method,
				$method_key,
				$settings
			);

		if ( '' === $title ) {
			return new WP_Error(
				'payment_method_unavailable',
				__(
					'The selected payment method is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$order->set_payment_method(
			$method
		);

		$order->set_payment_method_title(
			$title
		);

		return array(
			'method' =>
				$method,

			'method_key' =>
				$method_key,

			'source' =>
				'eilmo_manual',

			'gateway_id' =>
				'',

			'transaction_id' =>
				$transaction_id,

			'proof_token' =>
				$proof_token,

			'title' =>
				$title,
		);
	}

	/**
	 * Get WooCommerce gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return object|null
	 */
	private function get_woocommerce_gateway(
		string $gateway_id
	) {

		if (
			'' === $gateway_id ||
			! function_exists(
				'WC'
			) ||
			! WC() ||
			! WC()->payment_gateways()
		) {
			return null;
		}

		try {

			$gateways =
				WC()
					->payment_gateways()
					->get_available_payment_gateways();

		} catch ( \Throwable $throwable ) {

			return null;
		}

		if (
			! is_array(
				$gateways
			) ||
			! isset(
				$gateways[
					$gateway_id
				]
			)
		) {
			return null;
		}

		return is_object(
			$gateways[
				$gateway_id
			]
		)
			? $gateways[
				$gateway_id
			]
			: null;
	}

	/**
	 * Get manual payment title.
	 *
	 * @param string               $method     Method.
	 * @param string               $method_key Method key.
	 * @param array<string, mixed> $settings   Settings.
	 *
	 * @return string
	 */
	private function get_manual_payment_title(
		string $method,
		string $method_key,
		array $settings
	): string {

		$payment_settings =
			isset(
				$settings[
					'payment_methods'
				]
			) &&
			is_array(
				$settings[
					'payment_methods'
				]
			)
				? $settings[
					'payment_methods'
				]
				: array();

		if (
			'eilmo_cash_on_delivery' ===
				$method
		) {
			$advance_settings =
				isset(
					$settings[
						'advance_payment'
					]
				) &&
				is_array(
					$settings[
						'advance_payment'
					]
				)
					? $settings[
						'advance_payment'
					]
					: array();

			if (
				'yes' !== (
					$advance_settings[
						'enabled'
					] ??
						'no'
				) ||
				'yes' !== (
					$advance_settings[
						'allow_cash_on_delivery'
					] ??
						'no'
				)
			) {
				return '';
			}

			$cash =
				isset(
					$payment_settings[
						'cash_on_delivery'
					]
				) &&
				is_array(
					$payment_settings[
						'cash_on_delivery'
					]
				)
					? $payment_settings[
						'cash_on_delivery'
					]
					: array();

			return sanitize_text_field(
				(string) (
					$cash[
						'title'
					] ??
						__(
							'Cash on Delivery',
							'eilmo-checkout-flow'
						)
				)
			);
		}

		if (
			'eilmo_bank_transfer' ===
				$method
		) {
			$bank =
				isset(
					$payment_settings[
						'bank_transfer'
					]
				) &&
				is_array(
					$payment_settings[
						'bank_transfer'
					]
				)
					? $payment_settings[
						'bank_transfer'
					]
					: array();

			if (
				'yes' !==
					(
						$bank[
							'enabled'
						] ??
							'no'
					)
			) {
				return '';
			}

			return sanitize_text_field(
				(string) (
					$bank[
						'title'
					] ??
						__(
							'Bank Transfer',
							'eilmo-checkout-flow'
						)
				)
			);
		}

		if (
			0 ===
				strpos(
					$method,
					'eilmo_custom__'
				)
		) {
			$custom_methods =
				isset(
					$payment_settings[
						'custom_methods'
					]
				) &&
				is_array(
					$payment_settings[
						'custom_methods'
					]
				)
					? $payment_settings[
						'custom_methods'
					]
					: array();

			foreach (
				$custom_methods as
					$custom_method
			) {
				if (
					! is_array(
						$custom_method
					)
				) {
					continue;
				}

				$id =
					sanitize_key(
						(string) (
							$custom_method[
								'id'
							] ??
								''
						)
					);

				if (
					$id !==
						$method_key ||
					'yes' !==
						(
							$custom_method[
								'enabled'
							] ??
								'no'
						)
				) {
					continue;
				}

				return sanitize_text_field(
					(string) (
						$custom_method[
							'title'
						] ??
							$id
					)
				);
			}
		}

		return '';
	}

	/**
	 * Build one immutable backend record for every fulfilled Special Discount.
	 *
	 * @param array<string, mixed> $automatic_result   Automatic reward result.
	 * @param array<string, mixed> $order_bump_result  Product reward result.
	 * @param float                $delivery_base_charge Delivery value before reward.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_applied_special_discount_snapshots(
		array $automatic_result,
		array $order_bump_result,
		float $delivery_base_charge
	): array {
		$snapshots = array();

		foreach ( (array) ( $automatic_result['applied_rules'] ?? array() ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$rule_id = sanitize_key( (string) ( $rule['id'] ?? '' ) );
			if ( '' === $rule_id ) {
				continue;
			}

			$reward_type = sanitize_key( (string) ( $rule['reward_type'] ?? '' ) );
			if ( 'fixed' === $reward_type ) {
				$reward_type = 'fixed_discount';
			} elseif ( 'percentage' === $reward_type ) {
				$reward_type = 'percentage_discount';
			}

			$free_delivery = ! empty( $rule['free_delivery'] ) || 'free_delivery' === $reward_type;
			$snapshots[ $rule_id ] = array(
				'id' => $rule_id,
				'title' => sanitize_text_field( (string) ( $rule['title'] ?? $rule_id ) ),
				'rule_scope' => sanitize_key( (string) ( $rule['rule_scope'] ?? 'whole_cart' ) ),
				'scope_product_ids' => isset( $rule['scope_product_ids'] ) && is_array( $rule['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $rule['scope_product_ids'] ) ) ) ) : array(),
				'condition_type' => sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) ),
				'condition_minimum' => $this->normalize_amount( $rule['condition_minimum'] ?? 0 ),
				'condition_maximum' => $this->normalize_amount( $rule['condition_maximum'] ?? 0 ),
				'condition_product_id' => absint( $rule['condition_product_id'] ?? 0 ),
				'condition_product_name' => $this->get_product_snapshot_name( absint( $rule['condition_product_id'] ?? 0 ) ),
				'condition_product_quantity' => max( 1, absint( $rule['condition_product_quantity'] ?? 1 ) ),
				'condition_cart_quantity' => max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) ),
				'reward_type' => $reward_type,
				'reward_value' => $this->normalize_amount( $rule['reward_value'] ?? 0 ),
				'benefit_amount' => $free_delivery
					? $this->normalize_amount( $delivery_base_charge )
					: $this->normalize_amount( $rule['benefit_amount'] ?? 0 ),
				'product_id' => absint( $rule['gift_variation_id'] ?? 0 ) ?: absint( $rule['gift_product_id'] ?? 0 ),
				'product_name' => $this->get_product_snapshot_name( absint( $rule['gift_variation_id'] ?? 0 ) ?: absint( $rule['gift_product_id'] ?? 0 ) ),
				'quantity' => $this->normalize_quantity( $rule['gift_quantity'] ?? 0 ),
				'free_delivery' => $free_delivery,
				'free_delivery_method_id' => sanitize_key( (string) ( $rule['free_delivery_method_id'] ?? '' ) ),
			);
		}

		foreach ( (array) ( $order_bump_result['offers'] ?? array() ) as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}

			$rule_id = sanitize_key( (string) ( $offer['id'] ?? '' ) );
			if ( '' === $rule_id ) {
				continue;
			}

			$reward_type = sanitize_key( (string) ( $offer['offer_type'] ?? 'single_product' ) );
			$free_delivery = ! empty( $offer['free_delivery'] ) || 'free_delivery' === $reward_type;
			$product_id = absint( $offer['variation_id'] ?? 0 ) ?: absint( $offer['product_id'] ?? 0 );
			$benefit_amount = $this->normalize_amount( $offer['discount'] ?? 0 );
			if ( $free_delivery ) {
				$benefit_amount = $this->normalize_amount( $delivery_base_charge );
			} elseif ( 'free_gift' === $reward_type && $benefit_amount <= 0 ) {
				$benefit_amount = $this->normalize_amount( $offer['regular_total'] ?? 0 );
			}

			$snapshots[ $rule_id ] = array(
				'id' => $rule_id,
				'title' => sanitize_text_field( (string) ( $offer['title'] ?? $rule_id ) ),
				'rule_scope' => sanitize_key( (string) ( $offer['rule_scope'] ?? 'whole_cart' ) ),
				'scope_product_ids' => isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $offer['scope_product_ids'] ) ) ) ) : array(),
				'condition_type' => sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) ),
				'condition_minimum' => $this->normalize_amount( $offer['condition_minimum'] ?? 0 ),
				'condition_maximum' => $this->normalize_amount( $offer['condition_maximum'] ?? 0 ),
				'condition_product_id' => absint( $offer['condition_product_id'] ?? 0 ),
				'condition_product_name' => $this->get_product_snapshot_name( absint( $offer['condition_product_id'] ?? 0 ) ),
				'condition_product_quantity' => max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ),
				'condition_cart_quantity' => max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ),
				'reward_type' => $reward_type,
				'reward_value' => $this->normalize_amount( $offer['pricing_value'] ?? 0 ),
				'benefit_amount' => $benefit_amount,
				'product_id' => $product_id,
				'product_name' => $this->get_product_snapshot_name( $product_id ),
				'quantity' => $this->normalize_quantity( $offer['quantity'] ?? 0 ),
				'free_delivery' => $free_delivery,
				'free_delivery_method_id' => sanitize_key( (string) ( $offer['free_delivery_method_id'] ?? '' ) ),
			);
		}

		return array_values( $snapshots );
	}

	/**
	 * Resolve a product name while the order snapshot is being created.
	 *
	 * @param int $product_id Product or variation ID.
	 *
	 * @return string
	 */
	private function get_product_snapshot_name(
		int $product_id
	): string {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		return $product && method_exists( $product, 'get_name' )
			? sanitize_text_field( (string) $product->get_name() )
			: '';
	}

	/**
	 * Save Eilmo order metadata.
	 *
	 * @param WC_Order             $order Order.
	 * @param array<string, mixed> $data  Data.
	 *
	 * @return void
	 */
	private function save_order_meta(
		WC_Order $order,
		array $data
	): void {

		$order->update_meta_data(
			self::META_CREATED_VIA,
			'eilmo-checkout-flow'
		);

		$order->update_meta_data(
			self::META_PRODUCT_TOTAL,
			$this->normalize_amount(
				$data[
					'product_total'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_COMBO_OFFERS,
			$this->sanitize_combo_order_meta(
				$data[
					'combo_offers'
				] ??
					array()
			)
		);

		$order->update_meta_data(
			self::META_COMBO_DISCOUNT,
			$this->normalize_amount(
				$data[
					'combo_discount'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_ORDER_BUMPS,
			$this->sanitize_order_bump_order_meta(
				$data[
					'order_bumps'
				] ??
					array()
			)
		);

		$order->update_meta_data(
			self::META_ORDER_BUMP_DISCOUNT,
			$this->normalize_amount(
				$data[
					'order_bump_discount'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_AUTOMATIC_DISCOUNT,
			$this->normalize_amount(
				$data[
					'automatic_discount'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_APPLIED_SPECIAL_DISCOUNTS,
			$this->sanitize_applied_special_discount_order_meta(
				$data['applied_special_discounts'] ?? array()
			)
		);

		$order->update_meta_data(
			self::META_COUPON_DISCOUNT,
			$this->normalize_amount(
				$data[
					'coupon_discount'
				] ??
					0
			)
		);

		$coupon_applied =
			! empty(
				$data[
					'coupon_applied'
				]
			);

		if ( $coupon_applied ) {

			$order->update_meta_data(
				self::META_COUPON_CODE,
				$this->normalize_coupon_code(
					$data[
						'coupon_code'
					] ??
						''
				)
			);

			$order->update_meta_data(
				self::META_COUPON_SOURCE,
				sanitize_key(
					(string) (
						$data[
							'coupon_source'
						] ??
							''
					)
				)
			);

			$order->update_meta_data(
				self::META_COUPON_TYPE,
				sanitize_key(
					(string) (
						$data[
							'coupon_type'
						] ??
							''
					)
				)
			);

			$order->update_meta_data(
				self::META_COUPON_FREE_DELIVERY,
				! empty(
					$data[
						'coupon_free_delivery'
					]
				)
					? 'yes'
					: 'no'
			);

		} else {

			/*
			 * Keep coupon-identifying metadata absent when no coupon
			 * produced an actual benefit. The admin renderer uses this
			 * absence to keep Coupon Details completely hidden.
			 */
			$order->delete_meta_data(
				self::META_COUPON_CODE
			);

			$order->delete_meta_data(
				self::META_COUPON_SOURCE
			);

			$order->delete_meta_data(
				self::META_COUPON_TYPE
			);

			$order->delete_meta_data(
				self::META_COUPON_FREE_DELIVERY
			);
		}

		$order->update_meta_data(
			self::META_DELIVERY_METHOD,
			sanitize_key(
				(string) (
					$data[
						'delivery_method'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_DELIVERY_CHARGE,
			$this->normalize_amount(
				$data[
					'delivery_charge'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_FULL_PAYMENT_DISCOUNT,
			$this->normalize_amount(
				$data[
					'full_payment_discount'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_GRAND_TOTAL,
			$this->normalize_amount(
				$data[
					'grand_total'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_TYPE,
			sanitize_key(
				(string) (
					$data[
						'payment_type'
					] ??
						'full'
				)
			)
		);

		$order->update_meta_data(
			self::META_ADVANCE_AMOUNT,
			$this->normalize_amount(
				$data[
					'advance_amount'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_ADVANCE_RULE_ID,
			sanitize_key(
				(string) (
					$data[
						'advance_rule_id'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_ADVANCE_RULE_TYPE,
			sanitize_key(
				(string) (
					$data[
						'advance_rule_type'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_PAY_NOW,
			$this->normalize_amount(
				$data[
					'pay_now'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_REMAINING_DUE,
			$this->normalize_amount(
				$data[
					'remaining_due'
				] ??
					0
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_METHOD,
			sanitize_text_field(
				(string) (
					$data[
						'payment_method'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_METHOD_KEY,
			sanitize_key(
				(string) (
					$data[
						'payment_method_key'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_SOURCE,
			sanitize_key(
				(string) (
					$data[
						'payment_source'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_GATEWAY_ID,
			sanitize_key(
				(string) (
					$data[
						'payment_gateway_id'
					] ??
						''
				)
			)
		);

		$order->update_meta_data(
			self::META_PAYMENT_TRANSACTION_ID,
			sanitize_text_field(
				(string) (
					$data[
						'payment_transaction_id'
					] ??
						''
				)
			)
		);
	}

	/**
	 * Sanitize Combo order meta.
	 *
	 * @param mixed $offers Offers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_combo_order_meta(
		$offers
	): array {

		if ( ! is_array( $offers ) ) {
			return array();
		}

		$sanitized =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$combo_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if ( '' === $combo_id ) {
				continue;
			}

			$items =
				array();

			if (
				isset(
					$offer[
						'items'
					]
				) &&
				is_array(
					$offer[
						'items'
					]
				)
			) {
				foreach (
					$offer[
						'items'
					] as $item
				) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$product_id =
						absint(
							$item[
								'product_id'
							] ??
								0
						);

					$variation_id =
						absint(
							$item[
								'variation_id'
							] ??
								0
						);

					$quantity =
						$this->normalize_quantity(
							$item[
								'quantity'
							] ??
								0
						);

					if (
						$product_id <= 0 ||
						$quantity <= 0
					) {
						continue;
					}

					$items[] =
						array(
							'product_id' =>
								$product_id,

							'variation_id' =>
								$variation_id,

							'quantity' =>
								$quantity,
						);
				}
			}

			$sanitized[] =
				array(
					'id' =>
						$combo_id,

					'title' =>
						sanitize_text_field(
							(string) (
								$offer[
									'title'
								] ??
									''
							)
						),

					'pricing_type' =>
						sanitize_key(
							(string) (
								$offer[
									'pricing_type'
								] ??
									''
							)
						),

					'pricing_value' =>
						$this->normalize_amount(
							$offer[
								'pricing_value'
							] ??
								0
						),

					'regular_total' =>
						$this->normalize_amount(
							$offer[
								'regular_total'
							] ??
								0
						),

					'discount' =>
						$this->normalize_amount(
							$offer[
								'discount'
							] ??
								0
						),

					'combo_total' =>
						$this->normalize_amount(
							$offer[
								'combo_total'
							] ??
								0
						),

					'apply_automatic_discount' =>
						$this->normalize_yes_no(
							$offer[
								'apply_automatic_discount'
							] ??
								'no',
							false
						),

					'apply_coupon' =>
						$this->normalize_yes_no(
							$offer[
								'apply_coupon'
							] ??
								'yes',
							true
						),

					'apply_full_payment_discount' =>
						$this->normalize_yes_no(
							$offer[
								'apply_full_payment_discount'
							] ??
								'yes',
							true
						),

					'items' =>
						$items,
				);
		}

		return $sanitized;
	}

	/**
	 * Sanitize fulfilled Special Discount snapshots for durable order storage.
	 *
	 * @param mixed $rules Applied rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_applied_special_discount_order_meta(
		$rules
	): array {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$rule_id = sanitize_key( (string) ( $rule['id'] ?? '' ) );
			if ( '' === $rule_id ) {
				continue;
			}

			$sanitized[] = array(
				'id' => $rule_id,
				'title' => sanitize_text_field( (string) ( $rule['title'] ?? $rule_id ) ),
				'rule_scope' => sanitize_key( (string) ( $rule['rule_scope'] ?? 'whole_cart' ) ),
				'scope_product_ids' => isset( $rule['scope_product_ids'] ) && is_array( $rule['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $rule['scope_product_ids'] ) ) ) ) : array(),
				'condition_type' => sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) ),
				'condition_minimum' => $this->normalize_amount( $rule['condition_minimum'] ?? 0 ),
				'condition_maximum' => $this->normalize_amount( $rule['condition_maximum'] ?? 0 ),
				'condition_product_id' => absint( $rule['condition_product_id'] ?? 0 ),
				'condition_product_name' => sanitize_text_field( (string) ( $rule['condition_product_name'] ?? '' ) ),
				'condition_product_quantity' => max( 1, absint( $rule['condition_product_quantity'] ?? 1 ) ),
				'condition_cart_quantity' => max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) ),
				'reward_type' => sanitize_key( (string) ( $rule['reward_type'] ?? '' ) ),
				'reward_value' => $this->normalize_amount( $rule['reward_value'] ?? 0 ),
				'benefit_amount' => $this->normalize_amount( $rule['benefit_amount'] ?? 0 ),
				'product_id' => absint( $rule['product_id'] ?? 0 ),
				'product_name' => sanitize_text_field( (string) ( $rule['product_name'] ?? '' ) ),
				'quantity' => $this->normalize_quantity( $rule['quantity'] ?? 0 ),
				'free_delivery' => ! empty( $rule['free_delivery'] ),
				'free_delivery_method_id' => sanitize_key( (string) ( $rule['free_delivery_method_id'] ?? '' ) ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize Order Bump order meta.
	 *
	 * @param mixed $offers Offers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_order_bump_order_meta(
		$offers
	): array {

		if ( ! is_array( $offers ) ) {
			return array();
		}

		$sanitized =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$bump_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if ( '' === $bump_id ) {
				continue;
			}

			$items =
				array();

			if (
				isset(
					$offer[
						'items'
					]
				) &&
				is_array(
					$offer[
						'items'
					]
				)
			) {
				foreach (
					$offer[
						'items'
					] as $item
				) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$product_id =
						absint(
							$item[
								'product_id'
							] ??
								0
						);

					$variation_id =
						absint(
							$item[
								'variation_id'
							] ??
								0
						);

					$quantity =
						$this->normalize_quantity(
							$item[
								'quantity'
							] ??
								0
						);

					if (
						$product_id <= 0 ||
						$quantity <= 0
					) {
						continue;
					}

					$items[] =
						array(
							'product_id' =>
								$product_id,

							'variation_id' =>
								$variation_id,

							'quantity' =>
								$quantity,
						);
				}
			}

			$sanitized[] =
				array(
					'id' =>
						$bump_id,

					'title' =>
						sanitize_text_field(
							(string) (
								$offer[
									'title'
								] ??
									''
							)
						),

					'product_id' =>
						absint(
							$offer[
								'product_id'
							] ??
								0
						),

					'variation_id' =>
						absint(
							$offer[
								'variation_id'
							] ??
								0
						),

					'quantity' =>
						$this->normalize_quantity(
							$offer[
								'quantity'
							] ??
								0
						),

					'pricing_type' =>
						sanitize_key(
							(string) (
								$offer[
									'pricing_type'
								] ??
									''
							)
						),

					'pricing_value' =>
						$this->normalize_amount(
							$offer[
								'pricing_value'
							] ??
								0
						),

					'regular_total' =>
						$this->normalize_amount(
							$offer[
								'regular_total'
							] ??
								0
						),

					'discount' =>
						$this->normalize_amount(
							$offer[
								'discount'
							] ??
								0
						),

					'bump_total' =>
						$this->normalize_amount(
							$offer[
								'bump_total'
							] ??
								0
						),

					'apply_automatic_discount' =>
						$this->normalize_yes_no(
							$offer[
								'apply_automatic_discount'
							] ??
								'no',
							false
						),

					'apply_coupon' =>
						$this->normalize_yes_no(
							$offer[
								'apply_coupon'
							] ??
								'no',
							false
						),

					'apply_full_payment_discount' =>
						$this->normalize_yes_no(
							$offer[
								'apply_full_payment_discount'
							] ??
								'yes',
							true
						),

					'items' =>
						$items,
				);
		}

		return $sanitized;
	}

	/**
	 * Record custom coupon usage.
	 *
	 * @param WC_Order             $order         Order.
	 * @param array<string, mixed> $coupon_result Coupon result.
	 * @param array<string, mixed> $customer      Customer.
	 *
	 * @return void
	 */
	private function record_coupon_usage(
		WC_Order $order,
		array $coupon_result,
		array $customer
	): void {

		$settings =
			$this->get_section_settings(
				'coupons'
			);

		$identity =
			$this->resolve_customer_identity(
				$customer
			);

		$coupon_data =
			array(
				'coupon_code' =>
					sanitize_text_field(
						(string) (
							$coupon_result[
								'coupon_code'
							] ??
								''
						)
					),

				'source' =>
					'eilmo',

				'coupon_type' =>
					sanitize_key(
						(string) (
							$coupon_result[
								'coupon_type'
							] ??
								''
						)
					),

				'coupon_discount' =>
					$this->normalize_amount(
						$coupon_result[
							'coupon_discount'
						] ??
							0
					),

				'free_delivery' =>
					! empty(
						$coupon_result[
							'free_delivery'
						]
					),

				'identification_mode' =>
					sanitize_key(
						(string) (
							$settings[
								'customer_identification'
							] ??
								'email_or_phone'
						)
					),

				'customer_id' =>
					absint(
						$identity[
							'customer_id'
						] ??
							0
					),

				'customer_email' =>
					sanitize_email(
						(string) (
							$identity[
								'customer_email'
							] ??
								''
						)
					),

				'customer_phone' =>
					sanitize_text_field(
						(string) (
							$identity[
								'customer_phone'
							] ??
								''
						)
					),

				'billing_country' =>
					strtoupper(
						sanitize_key(
							(string) (
								$identity[
									'billing_country'
								] ??
									''
							)
						)
					),
			);

		do_action(
			'eilmo_cf/coupons/record_order_usage',
			$order,
			$coupon_data
		);
	}

	/**
	 * Determine whether line item is a Combo component.
	 *
	 * @param object $item Order item.
	 *
	 * @return bool
	 */
	private function is_combo_line_item(
		$item
	): bool {

		return (
			is_object( $item ) &&
			method_exists(
				$item,
				'get_meta'
			) &&
			'yes' ===
				(string) $item->get_meta(
					self::ITEM_META_COMBO_COMPONENT,
					true
				)
		);
	}

	/**
	 * Determine whether line item is an Order Bump.
	 *
	 * @param object $item Order item.
	 *
	 * @return bool
	 */
	private function is_order_bump_line_item(
		$item
	): bool {

		return (
			is_object( $item ) &&
			method_exists(
				$item,
				'get_meta'
			) &&
			'yes' ===
				(string) $item->get_meta(
					self::ITEM_META_ORDER_BUMP,
					true
				)
		);
	}

	/**
	 * Normalize yes / no value.
	 *
	 * Missing or empty values preserve the historical
	 * default supplied by the caller.
	 *
	 * @param mixed $value   Value.
	 * @param bool  $default Default.
	 *
	 * @return string
	 */
	private function normalize_yes_no(
		$value,
		bool $default
	): string {

		if ( is_bool( $value ) ) {
			return $value
				? 'yes'
				: 'no';
		}

		if (
			is_int( $value ) ||
			is_float( $value )
		) {
			return 1 === (int) $value
				? 'yes'
				: 'no';
		}

		$value =
			strtolower(
				trim(
					(string) $value
				)
			);

		if ( '' === $value ) {
			return $default
				? 'yes'
				: 'no';
		}

		if (
			in_array(
				$value,
				array(
					'yes',
					'1',
					'true',
					'on',
				),
				true
			)
		) {
			return 'yes';
		}

		if (
			in_array(
				$value,
				array(
					'no',
					'0',
					'false',
					'off',
				),
				true
			)
		) {
			return 'no';
		}

		return $default
			? 'yes'
			: 'no';
	}

	/**
	 * Resolve one saved promotional-offer compatibility
	 * flag.
	 *
	 * Current saved settings are authoritative. The
	 * validated offer snapshot is used only as a fallback
	 * if the exact saved offer cannot be found.
	 *
	 * @param array<string, mixed> $settings       Settings.
	 * @param string               $section        combo_offers|order_bumps.
	 * @param string               $offer_id       Offer ID.
	 * @param string               $flag           Compatibility flag.
	 * @param bool                 $default        Historical default.
	 * @param array<string, mixed> $fallback_offer Validated offer.
	 *
	 * @return string
	 */
	private function get_saved_offer_compatibility_flag(
		array $settings,
		string $section,
		string $offer_id,
		string $flag,
		bool $default,
		array $fallback_offer = array()
	): string {

		$section =
			sanitize_key(
				$section
			);

		$offer_id =
			sanitize_key(
				$offer_id
			);

		$flag =
			sanitize_key(
				$flag
			);

		$offers =
			isset(
				$settings[
					$section
				][
					'offers'
				]
			) &&
			is_array(
				$settings[
					$section
				][
					'offers'
				]
			)
				? $settings[
					$section
				][
					'offers'
				]
				: array();

		if (
			'' !== $offer_id &&
			'' !== $flag
		) {
			foreach ( $offers as $saved_offer ) {

				if ( ! is_array( $saved_offer ) ) {
					continue;
				}

				$saved_offer_id =
					sanitize_key(
						(string) (
							$saved_offer[
								'id'
							] ??
								''
						)
					);

				if ( $saved_offer_id !== $offer_id ) {
					continue;
				}

				return $this->normalize_yes_no(
					$saved_offer[
						$flag
					] ??
						(
							$default
								? 'yes'
								: 'no'
						),
					$default
				);
			}
		}

		return $this->normalize_yes_no(
			$fallback_offer[
				$flag
			] ??
				(
					$default
						? 'yes'
						: 'no'
				),
			$default
		);
	}

	/**
	 * Determine whether a Combo line allows a specified
	 * compatibility flag.
	 *
	 * @param object $item     Order item.
	 * @param string $meta_key Compatibility meta key.
	 * @param bool   $default  Default for missing meta.
	 *
	 * @return bool
	 */
	private function combo_allows(
		$item,
		string $meta_key,
		bool $default = false
	): bool {

		if (
			! $this->is_combo_line_item(
				$item
			)
		) {
			return $default;
		}

		if (
			! method_exists(
				$item,
				'get_meta'
			)
		) {
			return $default;
		}

		$value =
			(string) $item->get_meta(
				$meta_key,
				true
			);

		if ( 'yes' === $value ) {
			return true;
		}

		if ( 'no' === $value ) {
			return false;
		}

		return $default;
	}

	/**
	 * Determine whether an Order Bump line allows a
	 * specified compatibility flag.
	 *
	 * Non-Order-Bump lines always return the provided
	 * default value.
	 *
	 * @param object $item     Order item.
	 * @param string $meta_key Compatibility meta key.
	 * @param bool   $default  Default for missing meta.
	 *
	 * @return bool
	 */
	private function order_bump_allows(
		$item,
		string $meta_key,
		bool $default = false
	): bool {

		if (
			! $this->is_order_bump_line_item(
				$item
			)
		) {
			return $default;
		}

		if (
			! method_exists(
				$item,
				'get_meta'
			)
		) {
			return $default;
		}

		$value =
			(string) $item->get_meta(
				$meta_key,
				true
			);

		if ( 'yes' === $value ) {
			return true;
		}

		if ( 'no' === $value ) {
			return false;
		}

		return $default;
	}

	/**
	 * Get line items eligible for Automatic Discount.
	 *
	 * - Normal lines: eligible.
	 * - Combo lines: controlled by Combo compatibility,
	 *   default no.
	 * - Order Bump lines: controlled by Order Bump
	 *   compatibility, default no.
	 *
	 * Exact WooCommerce line items are evaluated so the
	 * same product may remain independent across normal,
	 * Combo and Order Bump contexts.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, object>
	 */
	private function get_automatic_discount_line_items(
		WC_Order $order
	): array {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object( $item ) ||
				! method_exists(
					$item,
					'get_meta'
				)
			) {
				continue;
			}

			if (
				$this->is_combo_line_item(
					$item
				)
			) {
				if (
					! $this->combo_allows(
						$item,
						self::ITEM_META_COMBO_APPLY_AUTOMATIC,
						false
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			if (
				$this->is_order_bump_line_item(
					$item
				)
			) {
				if (
					! $this->order_bump_allows(
						$item,
						self::ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC,
						false
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			/*
			 * Normal product line.
			 */
			$items[] =
				$item;
		}

		return array_values(
			$items
		);
	}

	/**
	 * Get the purchase lines visible to Special Discount CONDITIONS.
	 *
	 * Normal products and Combo components participate. Special Discount reward
	 * lines (historically stored as Order Bumps) are excluded so a Free Gift can
	 * never make another rule eligible by itself.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, object>
	 */
	private function get_special_discount_condition_line_items(
		WC_Order $order
	): array {
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}
			if ( $this->is_order_bump_line_item( $item ) ) {
				continue;
			}
			$items[] = $item;
		}
		return array_values( $items );
	}

	/**
	 * Current Automatic Discount eligible product total.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_automatic_discount_product_total(
		WC_Order $order
	): float {

		return $this->sum_line_item_amounts(
			$this->get_automatic_discount_line_items(
				$order
			),
			false
		);
	}

	/**
	 * Get line items eligible for Checkout Flow custom
	 * Coupon Discount.
	 *
	 * - Normal lines: eligible.
	 * - Combo lines: controlled by apply_coupon,
	 *   historical default yes.
	 * - Order Bump lines: controlled by apply_coupon,
	 *   historical default no.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, object>
	 */
	private function get_coupon_discount_line_items(
		WC_Order $order
	): array {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object( $item ) ||
				! method_exists(
					$item,
					'get_meta'
				)
			) {
				continue;
			}

			if (
				$this->is_combo_line_item(
					$item
				)
			) {
				if (
					! $this->combo_allows(
						$item,
						self::ITEM_META_COMBO_APPLY_COUPON,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			if (
				$this->is_order_bump_line_item(
					$item
				)
			) {
				if (
					! $this->order_bump_allows(
						$item,
						self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
						false
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			/*
			 * Normal product line.
			 */
			$items[] =
				$item;
		}

		return array_values(
			$items
		);
	}

	/**
	 * Get promotional lines explicitly excluded from
	 * Coupon Discount.
	 *
	 * This collection is used by the native WooCommerce
	 * coupon path so exact order-item occurrences can be
	 * filtered independently even when they share the same
	 * WooCommerce product ID.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, object>
	 */
	private function get_coupon_excluded_line_items(
		WC_Order $order
	): array {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object(
					$item
				)
			) {
				continue;
			}

			if (
				$this->is_combo_line_item(
					$item
				)
			) {
				if (
					$this->combo_allows(
						$item,
						self::ITEM_META_COMBO_APPLY_COUPON,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			if (
				$this->is_order_bump_line_item(
					$item
				)
			) {
				if (
					$this->order_bump_allows(
						$item,
						self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
						false
					)
				) {
					continue;
				}

				$items[] =
					$item;
			}
		}

		return array_values(
			$items
		);
	}

	/**
	 * Get line items eligible for Full Payment Discount.
	 *
	 * - Normal lines: eligible.
	 * - Combo lines: controlled by
	 *   apply_full_payment_discount, default yes.
	 * - Order Bump lines: controlled by
	 *   apply_full_payment_discount, default yes.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, object>
	 */
	private function get_full_payment_discount_line_items(
		WC_Order $order
	): array {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object( $item ) ||
				! method_exists(
					$item,
					'get_meta'
				)
			) {
				continue;
			}

			if (
				$this->is_combo_line_item(
					$item
				)
			) {
				if (
					! $this->combo_allows(
						$item,
						self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			if (
				$this->is_order_bump_line_item(
					$item
				)
			) {
				if (
					! $this->order_bump_allows(
						$item,
						self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			/*
			 * Normal product line.
			 */
			$items[] =
				$item;
		}

		return array_values(
			$items
		);
	}

	/**
	 * Full Payment Discount eligible regular product total.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_full_payment_regular_product_total(
		WC_Order $order
	): float {

		return $this->sum_line_item_amounts(
			$this->get_full_payment_discount_line_items(
				$order
			),
			true
		);
	}

	/**
	 * Full Payment Discount eligible current product total.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_full_payment_product_total(
		WC_Order $order
	): float {

		return $this->sum_line_item_amounts(
			$this->get_full_payment_discount_line_items(
				$order
			),
			false
		);
	}

	/**
	 * Current total of promotional lines excluded from
	 * Full Payment Discount.
	 *
	 * Both Combo and Order Bump contexts are evaluated
	 * independently from normal product lines.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_full_payment_ineligible_promotional_total(
		WC_Order $order
	): float {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			if (
				! is_object(
					$item
				)
			) {
				continue;
			}

			if (
				$this->is_combo_line_item(
					$item
				)
			) {
				if (
					$this->combo_allows(
						$item,
						self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;

				continue;
			}

			if (
				$this->is_order_bump_line_item(
					$item
				)
			) {
				if (
					$this->order_bump_allows(
						$item,
						self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,
						true
					)
				) {
					continue;
				}

				$items[] =
					$item;
			}
		}

		return $this->sum_line_item_amounts(
			$items,
			false
		);
	}

	/**
	 * Sum current totals or regular subtotals for line
	 * items.
	 *
	 * @param array<int, object> $items        Items.
	 * @param bool               $use_subtotal Use subtotal.
	 *
	 * @return float
	 */
	private function sum_line_item_amounts(
		array $items,
		bool $use_subtotal = false
	): float {

		$total =
			0.0;

		foreach ( $items as $item ) {

			if ( ! is_object( $item ) ) {
				continue;
			}

			if (
				$use_subtotal &&
				method_exists(
					$item,
					'get_subtotal'
				)
			) {
				$total +=
					max(
						0,
						(float) $item
							->get_subtotal()
					);
				continue;
			}

			if (
				method_exists(
					$item,
					'get_total'
				)
			) {
				$total +=
					max(
						0,
						(float) $item
							->get_total()
					);
			}
		}

		return $this->normalize_amount(
			$total
		);
	}

	/**
	 * Calculate how much of a proportional discount would
	 * land on a subset of the source items.
	 *
	 * This mirrors apply_discount_to_items() closely enough
	 * for Coupon stacking calculations before the Automatic
	 * Discount is physically written to line totals.
	 *
	 * @param array<int, object> $source_items Source items.
	 * @param array<int, object> $subset_items Subset items.
	 * @param float              $amount       Discount.
	 *
	 * @return float
	 */
	private function calculate_discount_overlap_amount(
		array $source_items,
		array $subset_items,
		float $amount
	): float {

		$amount =
			$this->normalize_amount(
				$amount
			);

		if (
			$amount <= 0 ||
			empty( $source_items ) ||
			empty( $subset_items )
		) {
			return 0.0;
		}

		$eligible_source_items =
			array_values(
				array_filter(
					$source_items,
					static function (
						$item
					): bool {

						return (
							is_object( $item ) &&
							method_exists(
								$item,
								'get_total'
							) &&
							(float) $item
								->get_total() > 0
						);
					}
				)
			);

		if ( empty( $eligible_source_items ) ) {
			return 0.0;
		}

		$subset_keys =
			array();

		foreach ( $subset_items as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$key =
				method_exists(
					$item,
					'get_id'
				) &&
				$item->get_id() > 0
					? 'id:' .
						(string) $item->get_id()
					: 'object:' .
						spl_object_hash(
							$item
						);

			$subset_keys[
				$key
			] =
				true;
		}

		$current_total =
			$this->sum_line_item_amounts(
				$eligible_source_items,
				false
			);

		if ( $current_total <= 0 ) {
			return 0.0;
		}

		$target_discount =
			min(
				$amount,
				$current_total
			);

		$remaining_discount =
			$target_discount;

		$item_count =
			count(
				$eligible_source_items
			);

		$overlap =
			0.0;

		foreach (
			$eligible_source_items as
			$index => $item
		) {
			$item_total =
				$this->normalize_amount(
					$item->get_total()
				);

			if ( $item_total <= 0 ) {
				continue;
			}

			$is_last =
				$index ===
					(
						$item_count -
							1
					);

			if ( $is_last ) {
				$item_discount =
					min(
						$item_total,
						$remaining_discount
					);
			} else {
				$item_discount =
					$this->normalize_amount(
						$target_discount *
							(
								$item_total /
									$current_total
							)
					);

				$item_discount =
					min(
						$item_total,
						$item_discount,
						$remaining_discount
					);
			}

			$key =
				method_exists(
					$item,
					'get_id'
				) &&
				$item->get_id() > 0
					? 'id:' .
						(string) $item->get_id()
					: 'object:' .
						spl_object_hash(
							$item
						);

			if (
				isset(
					$subset_keys[
						$key
					]
				)
			) {
				$overlap =
					$this->normalize_amount(
						$overlap +
							$item_discount
					);
			}

			$remaining_discount =
				$this->normalize_amount(
					max(
						0,
						$remaining_discount -
							$item_discount
					)
				);

			if ( $remaining_discount <= 0 ) {
				break;
			}
		}

		return $this->normalize_amount(
			$overlap
		);
	}

	/**
	 * Current product total.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_current_product_total(
		WC_Order $order
	): float {

		$total =
			0.0;

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {
			$total +=
				max(
					0,
					(float) $item
						->get_total()
				);
		}

		return $this->normalize_amount(
			$total
		);
	}

	/**
	 * Get payment type.
	 *
	 * @param array<string, mixed> $advance_payment Advance payment.
	 *
	 * @return string
	 */
	private function get_payment_type(
		array $advance_payment
	): string {

		$type =
			sanitize_key(
				(string) (
					$advance_payment[
						'payment_type'
					] ??
						'full'
				)
			);

		return in_array(
			$type,
			array(
				'cash_on_delivery',
				'advance',
				'full',
			),
			true
		)
			? $type
			: 'full';
	}

	/**
	 * Get complete plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

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

		$settings =
			array_replace_recursive(
				$defaults,
				$stored
			);

		$indexed_paths =
			array(
				array(
					'combo_offers',
					'offers',
				),

			array(
				'order_bumps',
				'offers',
			),

				array(
					'delivery',
					'methods',
				),

				array(
					'advance_payment',
					'rules',
				),

				array(
					'discounts',
					'automatic_rules',
				),

				array(
					'coupons',
					'custom_coupons',
				),

				array(
					'payment_methods',
					'custom_methods',
				),

			);

		foreach (
			$indexed_paths as $path
		) {
			$section =
				$path[0];

			$key =
				$path[1];

			if (
				isset(
					$stored[
						$section
					]
				) &&
				is_array(
					$stored[
						$section
					]
				) &&
				array_key_exists(
					$key,
					$stored[
						$section
					]
				) &&
				is_array(
					$stored[
						$section
					][
						$key
					]
				)
			) {
				$settings[
					$section
				][
					$key
				] =
					$stored[
						$section
					][
						$key
					];
			}
		}

		return $settings;
	}

	/**
	 * Get settings section.
	 *
	 * @param string $section Section.
	 *
	 * @return array<string, mixed>
	 */
	private function get_section_settings(
		string $section
	): array {

		$settings =
			$this->get_settings();

		return isset(
			$settings[
				$section
			]
		) &&
		is_array(
			$settings[
				$section
			]
		)
			? $settings[
				$section
			]
			: array();
	}

	/**
	 * Delete incomplete order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	private function delete_order(
		WC_Order $order
	): void {

		try {

			if (
				$order->get_id() > 0
			) {
				$order->delete(
					true
				);
			}

		} catch ( \Throwable $throwable ) {
			/*
			 * Do not replace original error.
			 */
		}
	}

	/**
	 * Normalize coupon.
	 *
	 * @param mixed $code Code.
	 *
	 * @return string
	 */
	private function normalize_coupon_code(
		$code
	): string {

		$code =
			trim(
				(string) $code
			);

		if ( '' === $code ) {
			return '';
		}

		if (
			function_exists(
				'wc_format_coupon_code'
			)
		) {
			return wc_format_coupon_code(
				$code
			);
		}

		return strtolower(
			sanitize_text_field(
				$code
			)
		);
	}

	/**
	 * Normalize a product identity without changing its meaning.
	 *
	 * Negative or malformed values must not be converted into a
	 * different valid WooCommerce product ID.
	 *
	 * @param mixed $value      Value.
	 * @param bool  $allow_zero Whether zero is valid.
	 *
	 * @return int Negative one indicates an invalid value.
	 */
	private function normalize_product_identity(
		$value,
		bool $allow_zero
	): int {

		if ( is_int( $value ) ) {
			$integer =
				$value;
		} elseif (
			is_string(
				$value
			) &&
			1 === preg_match(
				'/^[0-9]+$/',
				trim( $value )
			)
		) {
			$integer =
				(int) trim(
					$value
				);
		} else {
			return -1;
		}

		if (
			$integer < 0 ||
			(
				! $allow_zero &&
				0 === $integer
			)
		) {
			return -1;
		}

		return $integer;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			! is_int( $quantity ) &&
			! is_float( $quantity ) &&
			! is_string( $quantity )
		) {
			return 0.0;
		}

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		$quantity =
			(float) $quantity;

		if (
			! is_finite(
				$quantity
			) ||
			$quantity <= 0
		) {
			return 0.0;
		}

		return $quantity;
	}

	/**
	 * Throw an order-creation exception.
	 *
	 * Exception messages are transport data. The AJAX/Store API boundary is
	 * responsible for choosing and escaping the final client-facing response.
	 *
	 * @param string $message Exception message.
	 *
	 * @return void
	 * @throws \RuntimeException Always.
	 */
	private function throw_order_exception(
		string $message
	): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This is exception data, not HTML output; the response boundary escapes it.
		throw new \RuntimeException( $message );
	}

	/**
	 * Normalize monetary amount.
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
			$amount =
				wc_format_decimal(
					$amount,
					false
				);
		}

		if (
			! is_numeric(
				$amount
			)
		) {
			return 0.0;
		}

		$amount =
			max(
				0,
				(float) $amount
			);

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			$amount,
			$decimals
		);
	}
}
