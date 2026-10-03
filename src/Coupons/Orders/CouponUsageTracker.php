<?php
/**
 * Coupon usage tracker.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons\Orders;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Coupons\Services\CouponCalculator;
use EilmoCheckout\Coupons\Services\CustomerIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * Records Eilmo custom coupon usage on orders.
 *
 * Coupon usage is order-based instead of maintaining
 * a separate incrementing counter.
 *
 * This keeps usage idempotent:
 * one order can only represent one coupon usage.
 *
 * Usage is considered active according to the order
 * statuses used by the coupon validation layer.
 */
final class CouponUsageTracker implements RegistrableInterface {

	/**
	 * Coupon source order meta.
	 *
	 * @var string
	 */
	public const META_SOURCE =
		'_eilmo_cf_coupon_source';

	/**
	 * Coupon type order meta.
	 *
	 * @var string
	 */
	public const META_TYPE =
		'_eilmo_cf_coupon_type';

	/**
	 * Coupon discount order meta.
	 *
	 * @var string
	 */
	public const META_DISCOUNT =
		'_eilmo_cf_coupon_discount';

	/**
	 * Coupon free-delivery order meta.
	 *
	 * @var string
	 */
	public const META_FREE_DELIVERY =
		'_eilmo_cf_coupon_free_delivery';

	/**
	 * Customer identification mode.
	 *
	 * @var string
	 */
	public const META_IDENTIFICATION_MODE =
		'_eilmo_cf_coupon_identification_mode';

	/**
	 * Normalized customer email.
	 *
	 * @var string
	 */
	public const META_CUSTOMER_EMAIL =
		'_eilmo_cf_coupon_customer_email';

	/**
	 * Normalized customer phone.
	 *
	 * @var string
	 */
	public const META_CUSTOMER_PHONE =
		'_eilmo_cf_coupon_customer_phone';

	/**
	 * Register hooks.
	 *
	 * Final checkout/order service can record usage
	 * through:
	 *
	 * do_action(
	 *     'eilmo_cf/coupons/record_order_usage',
	 *     $order,
	 *     $coupon_data
	 * );
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'eilmo_cf/coupons/record_order_usage',
			array(
				$this,
				'handle_record_usage',
			),
			10,
			2
		);

		add_action(
			'eilmo_cf/coupons/clear_order_usage',
			array(
				$this,
				'handle_clear_usage',
			),
			10,
			1
		);
	}

	/**
	 * Handle coupon usage recording action.
	 *
	 * @param mixed                $order       Order or Order ID.
	 * @param array<string, mixed> $coupon_data Coupon data.
	 *
	 * @return void
	 */
	public function handle_record_usage(
		$order,
		array $coupon_data = array()
	): void {

		$this->record_usage(
			$order,
			$coupon_data
		);
	}

	/**
	 * Handle usage clearing action.
	 *
	 * @param mixed $order Order or Order ID.
	 *
	 * @return void
	 */
	public function handle_clear_usage(
		$order
	): void {

		$this->clear_usage(
			$order
		);
	}

	/**
	 * Record Eilmo coupon usage on an order.
	 *
	 * Expected coupon data:
	 *
	 * array(
	 *     'coupon_code'         => 'save10',
	 *     'source'              => 'eilmo',
	 *     'coupon_type'         => 'percentage',
	 *     'coupon_discount'     => 100,
	 *     'free_delivery'       => false,
	 *     'identification_mode' => 'email_or_phone',
	 *     'customer_email'      => 'customer@example.com',
	 *     'customer_phone'      => '01712345678',
	 *     'billing_country'     => 'BD',
	 * )
	 *
	 * @param mixed                $order       Order or Order ID.
	 * @param array<string, mixed> $coupon_data Coupon data.
	 *
	 * @return bool
	 */
	public function record_usage(
		$order,
		array $coupon_data
	): bool {

		$order =
			$this->resolve_order(
				$order
			);

		if ( ! $order ) {
			return false;
		}

		$code =
			$this->normalize_coupon_code(
				$coupon_data['coupon_code'] ??
					''
			);

		if ( '' === $code ) {
			return false;
		}

		$source =
			sanitize_key(
				(string) (
					$coupon_data['source'] ??
						'eilmo'
				)
			);

		/*
		 * This tracker is for Eilmo-managed coupons.
		 *
		 * WooCommerce native coupons remain managed
		 * through WooCommerce's own coupon system.
		 */
		if ( 'eilmo' !== $source ) {
			return false;
		}

		$identity_service =
			new CustomerIdentity();

		$identity =
			$identity_service->resolve(
				array(
					'customer_id' =>
						absint(
							$order->get_customer_id()
						),

					'customer_email' =>
						$coupon_data['customer_email'] ??
							$order->get_billing_email(),

					'customer_phone' =>
						$coupon_data['customer_phone'] ??
							$order->get_billing_phone(),

					'billing_country' =>
						$coupon_data['billing_country'] ??
							$order->get_billing_country(),
				)
			);

		$identification_mode =
			$identity_service->normalize_mode(
				$coupon_data['identification_mode'] ??
					CustomerIdentity::MODE_EMAIL_OR_PHONE
			);

		$discount =
			$this->normalize_amount(
				$coupon_data['coupon_discount'] ??
					0
			);

		$type =
			sanitize_key(
				(string) (
					$coupon_data['coupon_type'] ??
						''
				)
			);

		$free_delivery =
			! empty(
				$coupon_data['free_delivery']
			);

		$usage_data = array(
			'coupon_code' =>
				$code,

			'source' =>
				$source,

			'coupon_type' =>
				$type,

			'coupon_discount' =>
				$discount,

			'free_delivery' =>
				$free_delivery,

			'identification_mode' =>
				$identification_mode,

			'customer_email' =>
				(string) (
					$identity['customer_email'] ??
						''
				),

			'customer_phone' =>
				(string) (
					$identity['customer_phone'] ??
						''
				),
		);

		/**
		 * Filters coupon usage data before it is
		 * stored on the order.
		 *
		 * @param array<string, mixed> $usage_data Usage data.
		 * @param \WC_Order            $order      Order.
		 * @param array<string, mixed> $coupon_data Original data.
		 */
		$usage_data =
			apply_filters(
				'eilmo_cf/coupons/order_usage_data',
				$usage_data,
				$order,
				$coupon_data
			);

		if ( ! is_array( $usage_data ) ) {
			return false;
		}

		$order->update_meta_data(
			CouponCalculator::ORDER_COUPON_META_KEY,
			$code
		);

		$order->update_meta_data(
			self::META_SOURCE,
			$source
		);

		$order->update_meta_data(
			self::META_TYPE,
			$type
		);

		$order->update_meta_data(
			self::META_DISCOUNT,
			$discount
		);

		$order->update_meta_data(
			self::META_FREE_DELIVERY,
			$free_delivery
				? 'yes'
				: 'no'
		);

		$order->update_meta_data(
			self::META_IDENTIFICATION_MODE,
			$identification_mode
		);

		$order->update_meta_data(
			self::META_CUSTOMER_EMAIL,
			(string) (
				$usage_data['customer_email'] ??
					''
			)
		);

		$order->update_meta_data(
			self::META_CUSTOMER_PHONE,
			(string) (
				$usage_data['customer_phone'] ??
					''
			)
		);

		$order->save();

		/**
		 * Fires after Eilmo coupon usage is recorded.
		 *
		 * @param \WC_Order            $order      Order.
		 * @param array<string, mixed> $usage_data Usage data.
		 */
		do_action(
			'eilmo_cf/coupons/order_usage_recorded',
			$order,
			$usage_data
		);

		return true;
	}

	/**
	 * Remove Eilmo coupon usage metadata.
	 *
	 * Normally this should only be used before an
	 * order is finalized when the coupon itself is
	 * removed from the order.
	 *
	 * Cancelled / failed orders do NOT need this.
	 * CouponAjax excludes those statuses from usage
	 * counting automatically.
	 *
	 * @param mixed $order Order or Order ID.
	 *
	 * @return bool
	 */
	public function clear_usage(
		$order
	): bool {

		$order =
			$this->resolve_order(
				$order
			);

		if ( ! $order ) {
			return false;
		}

		$meta_keys = array(
			CouponCalculator::ORDER_COUPON_META_KEY,
			self::META_SOURCE,
			self::META_TYPE,
			self::META_DISCOUNT,
			self::META_FREE_DELIVERY,
			self::META_IDENTIFICATION_MODE,
			self::META_CUSTOMER_EMAIL,
			self::META_CUSTOMER_PHONE,
		);

		foreach ( $meta_keys as $meta_key ) {
			$order->delete_meta_data(
				$meta_key
			);
		}

		$order->save();

		/**
		 * Fires after Eilmo coupon usage metadata
		 * is removed from an order.
		 *
		 * @param \WC_Order $order Order.
		 */
		do_action(
			'eilmo_cf/coupons/order_usage_cleared',
			$order
		);

		return true;
	}

	/**
	 * Determine whether an order has an Eilmo
	 * custom coupon recorded.
	 *
	 * @param mixed $order Order or Order ID.
	 *
	 * @return bool
	 */
	public function has_usage(
		$order
	): bool {

		$order =
			$this->resolve_order(
				$order
			);

		if ( ! $order ) {
			return false;
		}

		$code =
			$this->normalize_coupon_code(
				$order->get_meta(
					CouponCalculator::ORDER_COUPON_META_KEY,
					true
				)
			);

		return '' !== $code;
	}

	/**
	 * Get recorded coupon usage.
	 *
	 * @param mixed $order Order or Order ID.
	 *
	 * @return array<string, mixed>
	 */
	public function get_usage(
		$order
	): array {

		$order =
			$this->resolve_order(
				$order
			);

		if ( ! $order ) {
			return array();
		}

		$code =
			$this->normalize_coupon_code(
				$order->get_meta(
					CouponCalculator::ORDER_COUPON_META_KEY,
					true
				)
			);

		if ( '' === $code ) {
			return array();
		}

		return array(
			'coupon_code' =>
				$code,

			'source' =>
				sanitize_key(
					(string) $order->get_meta(
						self::META_SOURCE,
						true
					)
				),

			'coupon_type' =>
				sanitize_key(
					(string) $order->get_meta(
						self::META_TYPE,
						true
					)
				),

			'coupon_discount' =>
				$this->normalize_amount(
					$order->get_meta(
						self::META_DISCOUNT,
						true
					)
				),

			'free_delivery' =>
				'yes' ===
				$order->get_meta(
					self::META_FREE_DELIVERY,
					true
				),

			'identification_mode' =>
				sanitize_key(
					(string) $order->get_meta(
						self::META_IDENTIFICATION_MODE,
						true
					)
				),

			'customer_email' =>
				sanitize_email(
					(string) $order->get_meta(
						self::META_CUSTOMER_EMAIL,
						true
					)
				),

			'customer_phone' =>
				sanitize_text_field(
					(string) $order->get_meta(
						self::META_CUSTOMER_PHONE,
						true
					)
				),
		);
	}

	/**
	 * Resolve WooCommerce order.
	 *
	 * @param mixed $order Order or Order ID.
	 *
	 * @return \WC_Order|null
	 */
	private function resolve_order(
		$order
	) {

		if (
			$order instanceof
				\WC_Order
		) {
			return $order;
		}

		$order_id =
			absint(
				$order
			);

		if (
			$order_id <= 0 ||
			! function_exists(
				'wc_get_order'
			)
		) {
			return null;
		}

		$order =
			wc_get_order(
				$order_id
			);

		return $order instanceof
			\WC_Order
				? $order
				: null;
	}

	/**
	 * Normalize coupon code.
	 *
	 * @param mixed $code Coupon code.
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
			$code =
				wc_format_coupon_code(
					$code
				);
		} else {
			$code =
				sanitize_text_field(
					$code
				);
		}

		return strtolower(
			trim(
				$code
			)
		);
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

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}
