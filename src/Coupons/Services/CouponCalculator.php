<?php
/**
 * Coupon calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates and validates Eilmo custom coupons.
 */
final class CouponCalculator {

	/**
	 * Order meta key used to store applied custom coupon code.
	 *
	 * @var string
	 */
	public const ORDER_COUPON_META_KEY = '_eilmo_cf_coupon_code';

	/**
	 * Calculate coupon.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'product_total'              => 5000,
	 *     'discounted_product_total'   => 4550,
	 *     'automatic_discount'         => 450,
	 *     'existing_coupon_discount'   => 0,
	 *     'product_ids'                => array( 10, 20 ),
	 *     'category_ids'               => array( 5, 6 ),
	 *     'customer_id'                => 123,
	 *     'customer_email'             => 'customer@example.com',
	 *     'usage_count'                => 0,
	 *     'customer_usage_count'       => 0,
	 *     'is_new_customer'            => true,
	 * )
	 *
	 * @param string               $coupon_code Coupon code.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		string $coupon_code,
		array $context = array()
	): array {

		$settings =
			$this->get_settings();

		$code =
			$this->sanitize_coupon_code(
				$coupon_code
			);

		$base_amount =
			$this->get_base_amount(
				$context
			);

		$result =
			$this->get_default_result(
				$code,
				$base_amount
			);

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			$result['error_code'] =
				'coupons_disabled';

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		if ( '' === $code ) {

			$result['error_code'] =
				'empty_coupon';

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		$mode =
			sanitize_key(
				(string) (
					$settings['mode'] ??
						'woocommerce'
				)
			);

		$result['mode'] =
			$mode;

		/*
		 * WooCommerce-only mode.
		 *
		 * Native WooCommerce coupon handling will be
		 * delegated to WooCommerce later.
		 */
		if ( 'woocommerce' === $mode ) {

			$result['source'] =
				'woocommerce';

			$result['delegate_to_woocommerce'] =
				true;

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		$coupon =
			$this->find_custom_coupon(
				$settings,
				$code
			);

		/*
		 * Both mode:
		 *
		 * Custom Eilmo coupon gets first priority.
		 * If no custom code exists, allow WooCommerce
		 * to resolve the coupon.
		 */
		if ( null === $coupon ) {

			if ( 'both' === $mode ) {

				$result['source'] =
					'woocommerce';

				$result['delegate_to_woocommerce'] =
					true;

				return $this->filter_result(
					$result,
					$context,
					$settings
				);
			}

			$result['error_code'] =
				'invalid_coupon';

			$result['message'] =
				__(
					'Invalid coupon code.',
					'eilmo-checkout-flow'
				);

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		$result['handled'] =
			true;

		$result['source'] =
			'eilmo';

		$result['coupon_code'] =
			$code;

		$validation =
			$this->validate_coupon(
				$coupon,
				$base_amount,
				$context
			);

		if (
			! $validation['valid']
		) {

			$result['error_code'] =
				(string) (
					$validation['error_code'] ??
						'invalid_coupon'
				);

			$result['message'] =
				(string) (
					$validation['message'] ??
						''
				);

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		$result =
			$this->apply_coupon(
				$result,
				$coupon,
				$base_amount
			);

		return $this->filter_result(
			$result,
			$context,
			$settings
		);
	}

	/**
	 * Get default result.
	 *
	 * @param string $code        Coupon code.
	 * @param float  $base_amount Base amount.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_result(
		string $code,
		float $base_amount
	): array {

		return array(
			'handled'                  => false,
			'valid'                    => false,
			'applied'                  => false,
			'mode'                     => '',
			'source'                   => '',
			'delegate_to_woocommerce'  => false,

			'coupon_code'              => $code,
			'coupon_type'              => '',
			'description'              => '',

			'base_amount'              => $base_amount,
			'value'                    => 0.0,
			'minimum_spend'            => 0.0,
			'maximum_spend'            => 0.0,
			'maximum_discount'         => 0.0,

			'raw_discount'             => 0.0,
			'coupon_discount'          => 0.0,
			'discounted_total'         => $base_amount,

			'free_delivery'            => false,
			'is_capped'                => false,
			'is_clamped'               => false,

			'allow_stacking'           => false,
			'new_customer_only'        => false,

			'usage_limit'              => 0,
			'usage_limit_per_customer' => 0,

			'error_code'               => '',
			'message'                  => '',
		);
	}

	/**
	 * Validate custom coupon.
	 *
	 * @param array<string, mixed> $coupon      Coupon.
	 * @param float                $base_amount Base amount.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_coupon(
		array $coupon,
		float $base_amount,
		array $context
	): array {

		if (
			'yes' !== (
				$coupon['enabled'] ??
					'yes'
			)
		) {
			return $this->invalid(
				'coupon_disabled',
				__(
					'This coupon is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $this->date_is_valid(
				$coupon
			)
		) {
			return $this->invalid(
				'coupon_expired',
				__(
					'This coupon is not currently available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$minimum =
			$this->normalize_amount(
				$coupon['minimum_spend'] ??
					0
			);

		$maximum =
			$this->normalize_amount(
				$coupon['maximum_spend'] ??
					0
			);

		/*
		 * Coupon range:
		 *
		 * minimum <= amount < maximum
		 *
		 * Maximum 0 means unlimited.
		 */
		if ( $base_amount < $minimum ) {

			return $this->invalid(
				'minimum_spend_not_met',
				sprintf(
					/* translators: %s minimum amount. */
					__(
						'Minimum spend for this coupon is %s.',
						'eilmo-checkout-flow'
					),
					$this->format_price(
						$minimum
					)
				)
			);
		}

		if (
			$maximum > 0 &&
			$base_amount >= $maximum
		) {
			return $this->invalid(
				'maximum_spend_exceeded',
				sprintf(
					/* translators: %s maximum amount. */
					__(
						'This coupon is available for orders below %s.',
						'eilmo-checkout-flow'
					),
					$this->format_price(
						$maximum
					)
				)
			);
		}

		$usage_validation =
			$this->validate_usage_limits(
				$coupon,
				$context
			);

		if (
			! $usage_validation['valid']
		) {
			return $usage_validation;
		}

		if (
			'yes' === (
				$coupon['new_customer_only'] ??
					'no'
			) &&
			! $this->customer_is_new(
				$context
			)
		) {
			return $this->invalid(
				'new_customer_only',
				__(
					'This coupon is available for new customers only.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $this->products_are_valid(
				$coupon,
				$context
			)
		) {
			return $this->invalid(
				'product_restriction',
				__(
					'This coupon is not valid for the selected products.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $this->categories_are_valid(
				$coupon,
				$context
			)
		) {
			return $this->invalid(
				'category_restriction',
				__(
					'This coupon is not valid for the selected product categories.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Coupon-to-coupon stacking.
		 */
		$existing_coupon_discount =
			$this->normalize_amount(
				$context['existing_coupon_discount'] ??
					0
			);

		if (
			$existing_coupon_discount > 0 &&
			'yes' !== (
				$coupon['allow_stacking'] ??
					'no'
			)
		) {
			return $this->invalid(
				'coupon_stacking_not_allowed',
				__(
					'This coupon cannot be combined with another coupon.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'valid'      => true,
			'error_code' => '',
			'message'    => '',
		);
	}

	/**
	 * Apply coupon.
	 *
	 * @param array<string, mixed> $result      Result.
	 * @param array<string, mixed> $coupon      Coupon.
	 * @param float                $base_amount Base amount.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_coupon(
		array $result,
		array $coupon,
		float $base_amount
	): array {

		$type =
			sanitize_key(
				(string) (
					$coupon['type'] ??
						'percentage'
				)
			);

		$value =
			$this->normalize_amount(
				$coupon['value'] ??
					0
			);

		$maximum_discount =
			$this->normalize_amount(
				$coupon['maximum_discount'] ??
					0
			);

		$raw_discount =
			0.0;

		$discount =
			0.0;

		$free_delivery =
			false;

		$is_capped =
			false;

		$is_clamped =
			false;

		switch ( $type ) {

			case 'free_delivery':

				$free_delivery =
					true;
				break;

			case 'fixed':

				$raw_discount =
					$value;

				$discount =
					$value;
				break;

			case 'percentage':
			default:

				$value =
					min(
						100.0,
						$value
					);

				$raw_discount =
					$base_amount *
					(
						$value /
						100
					);

				$discount =
					$raw_discount;
				break;
		}

		$raw_discount =
			$this->normalize_amount(
				$raw_discount
			);

		$discount =
			$this->normalize_amount(
				$discount
			);

		if (
			$maximum_discount > 0 &&
			$discount > $maximum_discount
		) {
			$discount =
				$maximum_discount;

			$is_capped =
				true;
		}

		if ( $discount > $base_amount ) {

			$discount =
				$base_amount;

			$is_clamped =
				true;
		}

		$discount =
			$this->normalize_amount(
				$discount
			);

		$result['valid'] =
			true;

		$result['applied'] =
			true;

		$result['coupon_type'] =
			$type;

		$result['description'] =
			sanitize_textarea_field(
				(string) (
					$coupon['description'] ??
						''
				)
			);

		$result['value'] =
			$value;

		$result['minimum_spend'] =
			$this->normalize_amount(
				$coupon['minimum_spend'] ??
					0
			);

		$result['maximum_spend'] =
			$this->normalize_amount(
				$coupon['maximum_spend'] ??
					0
			);

		$result['maximum_discount'] =
			$maximum_discount;

		$result['raw_discount'] =
			$raw_discount;

		$result['coupon_discount'] =
			$discount;

		$result['discounted_total'] =
			$this->normalize_amount(
				max(
					0,
					$base_amount -
						$discount
				)
			);

		$result['free_delivery'] =
			$free_delivery;

		$result['is_capped'] =
			$is_capped;

		$result['is_clamped'] =
			$is_clamped;

		$result['allow_stacking'] =
			'yes' === (
				$coupon['allow_stacking'] ??
					'no'
			);

		$result['new_customer_only'] =
			'yes' === (
				$coupon['new_customer_only'] ??
					'no'
			);

		$result['usage_limit'] =
			absint(
				$coupon['usage_limit'] ??
					0
			);

		$result['usage_limit_per_customer'] =
			absint(
				$coupon['usage_limit_per_customer'] ??
					0
			);

		return $result;
	}

	/**
	 * Validate usage limits.
	 *
	 * @param array<string, mixed> $coupon  Coupon.
	 * @param array<string, mixed> $context Context.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_usage_limits(
		array $coupon,
		array $context
	): array {

		$usage_limit =
			absint(
				$coupon['usage_limit'] ??
					0
			);

		$per_customer_limit =
			absint(
				$coupon['usage_limit_per_customer'] ??
					0
			);

		$usage_count =
			array_key_exists(
				'usage_count',
				$context
			)
				? absint(
					$context['usage_count']
				)
				: 0;

		$customer_usage_count =
			array_key_exists(
				'customer_usage_count',
				$context
			)
				? absint(
					$context['customer_usage_count']
				)
				: 0;

		if (
			$usage_limit > 0 &&
			$usage_count >= $usage_limit
		) {
			return $this->invalid(
				'usage_limit_reached',
				__(
					'This coupon has reached its usage limit.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			$per_customer_limit > 0 &&
			$customer_usage_count >=
				$per_customer_limit
		) {
			return $this->invalid(
				'customer_usage_limit_reached',
				__(
					'You have already used this coupon the maximum number of times.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'valid'      => true,
			'error_code' => '',
			'message'    => '',
		);
	}

	/**
	 * Check coupon dates.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 *
	 * @return bool
	 */
	private function date_is_valid(
		array $coupon
	): bool {

		$today =
			current_time(
				'Y-m-d'
			);

		$start_date =
			sanitize_text_field(
				(string) (
					$coupon['start_date'] ??
						''
				)
			);

		$expiry_date =
			sanitize_text_field(
				(string) (
					$coupon['expiry_date'] ??
						''
				)
			);

		if (
			'' !== $start_date &&
			$today < $start_date
		) {
			return false;
		}

		if (
			'' !== $expiry_date &&
			$today > $expiry_date
		) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether customer is new.
	 *
	 * Context value takes precedence.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return bool
	 */
	private function customer_is_new(
		array $context
	): bool {

		if (
			array_key_exists(
				'is_new_customer',
				$context
			)
		) {
			return (bool)
				$context['is_new_customer'];
		}

		$customer_id =
			absint(
				$context['customer_id'] ??
					0
			);

		if (
			$customer_id > 0 &&
			function_exists(
				'wc_get_customer_order_count'
			)
		) {
			return (
				0 ===
				(int) wc_get_customer_order_count(
					$customer_id
				)
			);
		}

		/*
		 * Guest customer without previous-order
		 * information is treated as new here.
		 *
		 * Final checkout validation can provide
		 * is_new_customer explicitly.
		 */
		return true;
	}

	/**
	 * Validate product restrictions.
	 *
	 * @param array<string, mixed> $coupon  Coupon.
	 * @param array<string, mixed> $context Context.
	 *
	 * @return bool
	 */
	private function products_are_valid(
		array $coupon,
		array $context
	): bool {

		$product_ids =
			$this->sanitize_id_list(
				$context['product_ids'] ??
					array()
			);

		$included =
			$this->sanitize_id_list(
				$coupon['product_ids'] ??
					array()
			);

		$excluded =
			$this->sanitize_id_list(
				$coupon['excluded_product_ids'] ??
					array()
			);

		if (
			! empty( $excluded ) &&
			! empty(
				array_intersect(
					$product_ids,
					$excluded
				)
			)
		) {
			return false;
		}

		if (
			! empty( $included ) &&
			empty(
				array_intersect(
					$product_ids,
					$included
				)
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Validate category restrictions.
	 *
	 * @param array<string, mixed> $coupon  Coupon.
	 * @param array<string, mixed> $context Context.
	 *
	 * @return bool
	 */
	private function categories_are_valid(
		array $coupon,
		array $context
	): bool {

		$category_ids =
			$this->sanitize_id_list(
				$context['category_ids'] ??
					array()
			);

		$included =
			$this->sanitize_id_list(
				$coupon['category_ids'] ??
					array()
			);

		$excluded =
			$this->sanitize_id_list(
				$coupon['excluded_category_ids'] ??
					array()
			);

		if (
			! empty( $excluded ) &&
			! empty(
				array_intersect(
					$category_ids,
					$excluded
				)
			)
		) {
			return false;
		}

		if (
			! empty( $included ) &&
			empty(
				array_intersect(
					$category_ids,
					$included
				)
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Find custom coupon.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $code     Code.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_custom_coupon(
		array $settings,
		string $code
	): ?array {

		$coupons =
			isset(
				$settings['custom_coupons']
			) &&
			is_array(
				$settings['custom_coupons']
			)
				? $settings['custom_coupons']
				: array();

		foreach ( $coupons as $coupon ) {

			if ( ! is_array( $coupon ) ) {
				continue;
			}

			$coupon_code =
				$this->sanitize_coupon_code(
					$coupon['code'] ??
						''
				);

			if ( $code === $coupon_code ) {
				return $coupon;
			}
		}

		return null;
	}

	/**
	 * Resolve discount base amount.
	 *
	 * Coupon is applied after Automatic Discount.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return float
	 */
	private function get_base_amount(
		array $context
	): float {

		if (
			array_key_exists(
				'discounted_product_total',
				$context
			)
		) {
			return $this->normalize_amount(
				$context['discounted_product_total']
			);
		}

		return $this->normalize_amount(
			$context['product_total'] ??
				0
		);
	}

	/**
	 * Load coupon settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset(
				$defaults['coupons']
			) &&
			is_array(
				$defaults['coupons']
			)
				? $defaults['coupons']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset(
				$stored['coupons']
			) &&
			is_array(
				$stored['coupons']
			)
				? $stored['coupons']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		/*
		 * Custom coupons are indexed collections.
		 */
		$settings['custom_coupons'] =
			isset(
				$saved_settings['custom_coupons']
			) &&
			is_array(
				$saved_settings['custom_coupons']
			)
				? $saved_settings['custom_coupons']
				: (
					$default_settings['custom_coupons'] ??
						array()
				);

		/**
		 * Filters Coupon settings.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/coupons/settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Create invalid result.
	 *
	 * @param string $error_code Error code.
	 * @param string $message    Message.
	 *
	 * @return array<string, mixed>
	 */
	private function invalid(
		string $error_code,
		string $message
	): array {

		return array(
			'valid'      => false,
			'error_code' => $error_code,
			'message'    => $message,
		);
	}

	/**
	 * Format price.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function format_price(
		float $amount
	): string {

		if (
			function_exists(
				'wc_price'
			)
		) {
			return wp_strip_all_tags(
				wc_price(
					$amount
				)
			);
		}

		return number_format_i18n(
			$amount,
			2
		);
	}

	/**
	 * Sanitize coupon code.
	 *
	 * @param mixed $value Coupon code.
	 *
	 * @return string
	 */
	private function sanitize_coupon_code(
		$value
	): string {

		$value =
			trim(
				(string) $value
			);

		if ( '' === $value ) {
			return '';
		}

		if (
			function_exists(
				'wc_format_coupon_code'
			)
		) {
			$value =
				wc_format_coupon_code(
					$value
				);
		} else {
			$value =
				sanitize_text_field(
					$value
				);
		}

		return strtolower(
			trim(
				$value
			)
		);
	}

	/**
	 * Sanitize ID list.
	 *
	 * @param mixed $values Values.
	 *
	 * @return array<int>
	 */
	private function sanitize_id_list(
		$values
	): array {

		if ( is_string( $values ) ) {

			$values =
				preg_split(
					'/[\s,]+/',
					$values
				);
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$ids =
			array();

		foreach ( $values as $value ) {

			$id =
				absint(
					$value
				);

			if ( $id > 0 ) {
				$ids[] =
					$id;
			}
		}

		return array_values(
			array_unique(
				$ids
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

	/**
	 * Filter calculated result.
	 *
	 * @param array<string, mixed> $result   Result.
	 * @param array<string, mixed> $context  Context.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, mixed>
	 */
	private function filter_result(
		array $result,
		array $context,
		array $settings
	): array {

		/**
		 * Filters Coupon calculation result.
		 *
		 * @param array<string, mixed> $result   Result.
		 * @param array<string, mixed> $context  Context.
		 * @param array<string, mixed> $settings Settings.
		 */
		$result =
			apply_filters(
				'eilmo_cf/coupons/calculation',
				$result,
				$context,
				$settings
			);

		return is_array( $result )
			? $result
			: array();
	}
}