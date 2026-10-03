<?php
/**
 * Order Bump resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\OrderBumps\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Discounts\Services\SpecialDiscountEligibility;
use EilmoCheckout\Discounts\Services\SpecialDiscountEngine;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves configured Order Bump offers.
 *
 * Important:
 *
 * Frontend submits only Order Bump IDs.
 *
 * Product IDs, variation IDs, quantities and
 * pricing configuration are always resolved again
 * from the server-side Eilmo settings.
 */
final class OrderBumpResolver {

	/**
	 * Resolve selected Order Bump IDs.
	 *
	 * @param mixed                $selected_ids Selected IDs.
	 * @param array<string, mixed> $context      Trusted cart context.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function resolve_selected(
		$selected_ids,
		array $context = array()
	) {

		$settings =
			$this->get_settings();

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return array(
				'selected_ids' =>
					array(),

				'offers' =>
					array(),
			);
		}

		$selected_ids =
			$this->normalize_selected_ids(
				$selected_ids
			);
		$customer_selected_ids = $selected_ids;

		$library = $this->get_offer_library( $settings );
		$eligibility = new SpecialDiscountEligibility();

		/*
		 * Free Gift auto-application is decided by the same unified engine used
		 * for monetary discounts and Free Delivery. This resolver remains only
		 * the authoritative WooCommerce product/stock adapter for the gift line.
		 */
		$engine_result = ( new SpecialDiscountEngine() )->evaluate( $context );
		foreach ( (array) ( $engine_result['free_gift_rule_ids'] ?? array() ) as $gift_rule_id ) {
			$gift_rule_id = sanitize_key( (string) $gift_rule_id );
			if ( '' !== $gift_rule_id && isset( $library[ $gift_rule_id ] ) && ! in_array( $gift_rule_id, $selected_ids, true ) ) {
				$selected_ids[] = $gift_rule_id;
			}
		}

		if ( empty( $selected_ids ) ) {
			return array(
				'selected_ids' =>
					array(),

				'offers' =>
					array(),
			);
		}

		$selection_mode =
			'single' === (
				$settings['selection_mode'] ??
					'multiple'
			)
				? 'single'
				: 'multiple';

		if (
			'single' ===
				$selection_mode &&
			count(
				$customer_selected_ids
			) > 1
		) {
			return new WP_Error(
				'invalid_order_bump_selection',
				__(
					'Only one selectable Special Discount can be selected.',
					'eilmo-checkout-flow'
				)
			);
		}

		$resolved =
			array();
		$resolved_ids =
			array();

		foreach (
			$selected_ids as
				$selected_id
		) {

			if (
				! isset(
					$library[
						$selected_id
					]
				)
			) {
				/*
				 * Special Discounts are optional additions. If an offer was
				 * removed after the page loaded, discard the stale selection
				 * and continue with the normal order instead of blocking it.
				 * The signed checkout context is still verified before this
				 * resolver runs, so a discarded ID can never grant a benefit.
				 */
				continue;
			}

			$offer =
				$this->resolve_offer(
					$library[
						$selected_id
					]
				);

			if (
				is_wp_error(
					$offer
				)
			) {
				/*
				 * Expired, not-yet-started, unavailable and misconfigured
				 * optional offers all fall back to authoritative normal pricing.
				 */
				continue;
			}

			$evaluation = $eligibility->evaluate( $library[ $selected_id ], $context );
			if ( empty( $evaluation['eligible'] ) ) {
				continue;
			}

			$resolved[] =
				$offer;
			$resolved_ids[] =
				$selected_id;
		}

		return array(
			'selected_ids' =>
				array_values(
					$resolved_ids
				),

			'offers' =>
				array_values(
					$resolved
				),
		);
	}

	/**
	 * Get valid frontend offers.
	 *
	 * Invalid or unavailable WooCommerce products
	 * are silently excluded from frontend rendering.
	 *
	 * @param array<int, string> $allowed_ids Optional allowed IDs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_available_offers(
		array $allowed_ids = array()
	): array {

		$settings =
			$this->get_settings();

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return array();
		}

		$allowed_ids =
			$this->normalize_selected_ids(
				$allowed_ids
			);

		$offers =
			array();

		foreach (
			$this->get_offer_library(
				$settings
			) as
				$offer_id =>
				$offer
		) {
			$offer_type = sanitize_key( (string) ( $offer['offer_type'] ?? '' ) );
			$apply_behavior = sanitize_key( (string) ( $offer['apply_behavior'] ?? 'customer_selectable' ) );
			if (
				in_array( $offer_type, array( 'percentage_discount', 'fixed_discount' ), true ) ||
				( 'free_delivery' === $offer_type && 'auto_apply' === $apply_behavior )
			) {
				continue;
			}

			if (
				! empty(
					$allowed_ids
				) &&
				! in_array(
					$offer_id,
					$allowed_ids,
					true
				)
			) {
				continue;
			}

			$resolved =
				$this->resolve_offer(
					$offer
				);

			if (
				is_wp_error(
					$resolved
				)
			) {
				continue;
			}

			$offers[] =
				$resolved;
		}

		usort(
			$offers,
			static function (
				array $first,
				array $second
			): int {

				return (
					(int) (
						$first['sort_order'] ??
							10
					)
				) <=>
				(
					(int) (
						$second['sort_order'] ??
							10
					)
				);
			}
		);

		return array_values(
			$offers
		);
	}

	/**
	 * Get Order Bump settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {

		$defaults =
			array(
				'enabled' =>
					'no',

				'title' =>
					__(
						'Special Discounts',
						'eilmo-checkout-flow'
					),

				'selection_mode' =>
					'multiple',

				'offers' =>
					array(),
			);

		if (
			! class_exists(
				CheckoutSettings::class
			)
		) {
			return $defaults;
		}

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$order_bumps =
			isset(
				$stored[
					'order_bumps'
				]
			) &&
			is_array(
				$stored[
					'order_bumps'
				]
			)
				? $stored[
					'order_bumps'
				]
				: array();

		return array_replace_recursive(
			$defaults,
			$order_bumps
		);
	}

	/**
	 * Get offer library indexed by ID.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_offer_library(
		array $settings
	): array {

		$offers =
			isset(
				$settings[
					'offers'
				]
			) &&
			is_array(
				$settings[
					'offers'
				]
			)
				? $settings[
					'offers'
				]
				: array();

		$library =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			if (
				'yes' !== (
					$offer['enabled'] ??
						'yes'
				)
			) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$offer['id'] ??
							''
					)
				);

			if ( '' === $id ) {
				continue;
			}

			$offer['id'] =
				$id;

			$library[
				$id
			] =
				$offer;
		}

		return $library;
	}

	/**
	 * Resolve one configured offer.
	 *
	 * @param array<string, mixed> $offer Offer.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function resolve_offer(
		array $offer
	) {

		$id =
			sanitize_key(
				(string) (
					$offer['id'] ??
						''
				)
			);

		if ( '' === $id ) {
			return new WP_Error(
				'invalid_order_bump',
				__(
					'The selected Special Discount is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		$offer_type = sanitize_key( (string) ( $offer['offer_type'] ?? 'single_product' ) );
		if (
			! in_array(
				$offer_type,
				array( 'single_product', 'free_gift', 'gift_box', 'buy_one_get_one', 'free_delivery' ),
				true
			)
		) {
			$offer_type = 'single_product';
		}
		$is_free_gift = 'free_gift' === $offer_type;

		$availability = $this->validate_offer_schedule( $offer );
		if ( is_wp_error( $availability ) ) {
			return $availability;
		}

		$common = array(
			'id' => $id,
			'enabled' => 'yes',
			'offer_type' => $offer_type,
			'title' => sanitize_text_field( (string) ( $offer['title'] ?? '' ) ),
			'description' => wp_kses_post( (string) ( $offer['description'] ?? '' ) ),
			'badge' => sanitize_text_field( (string) ( $offer['badge'] ?? '' ) ),
			'label' => sanitize_text_field( (string) ( $offer['label'] ?? 'LIMITED TIME OFFER' ) ),
			'before_apply_text' => sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) ),
			'button_text' => sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) ),
			'applied_text' => sanitize_text_field( (string) ( $offer['applied_text'] ?? 'Added' ) ),
			'reward_title' => sanitize_text_field( (string) ( $offer['reward_title'] ?? '' ) ),
			'start_at' => sanitize_text_field( (string) ( $offer['start_at'] ?? '' ) ),
			'end_at' => sanitize_text_field( (string) ( $offer['end_at'] ?? '' ) ),
			'end_timestamp' => $this->get_offer_timestamp( (string) ( $offer['end_at'] ?? '' ) ),
			'timer_enabled' => 'no' === ( $offer['timer_enabled'] ?? 'yes' ) ? 'no' : 'yes',
			'image_source' => in_array( (string) ( $offer['image_source'] ?? 'product' ), array( 'preset', 'product', 'custom', 'hidden' ), true ) ? (string) ( $offer['image_source'] ?? 'product' ) : 'product',
			'image_media_id' => absint( $offer['image_media_id'] ?? 0 ),
			'icon_preset' => in_array( sanitize_key( (string) ( $offer['icon_preset'] ?? 'auto' ) ), array( 'auto', 'discount', 'delivery', 'gift', 'product' ), true ) ? sanitize_key( (string) ( $offer['icon_preset'] ?? 'auto' ) ) : 'auto',
			'sort_order' => absint( $offer['sort_order'] ?? 10 ),
			'apply_automatic_discount' => 'yes' === ( $offer['apply_automatic_discount'] ?? 'no' ) ? 'yes' : 'no',
			'apply_coupon' => 'yes' === ( $offer['apply_coupon'] ?? 'no' ) ? 'yes' : 'no',
			'apply_full_payment_discount' => 'yes' === ( $offer['apply_full_payment_discount'] ?? 'yes' ) ? 'yes' : 'no',
			'condition_type' => sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) ),
			'condition_minimum' => (float) ( $offer['condition_minimum'] ?? 0 ),
			'condition_maximum' => (float) ( $offer['condition_maximum'] ?? 0 ),
			'condition_product_id' => absint( $offer['condition_product_id'] ?? 0 ),
			'condition_product_quantity' => max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ),
			'condition_cart_quantity' => max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ),
			'apply_behavior' => sanitize_key( (string) ( $offer['apply_behavior'] ?? 'customer_selectable' ) ),
			'ineligible_action' => sanitize_key( (string) ( $offer['ineligible_action'] ?? 'show_locked' ) ),
			'locked_text' => sanitize_text_field( (string) ( $offer['locked_text'] ?? '' ) ),
		);

		/*
		 * Free Delivery is a zero-value reward, not a product.
		 * It therefore never creates a WooCommerce line item and
		 * can only zero the delivery charge once.
		 */
		if ( 'free_delivery' === $offer_type ) {
			return array_merge(
				$common,
				array(
					'product_id' => 0,
					'variation_id' => 0,
					'quantity' => 0.0,
					'pricing_type' => 'regular_price',
					'pricing_value' => 0.0,
					'free_delivery' => true,
				)
			);
		}

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
					1
			);

		if (
			$product_id <= 0 ||
			$quantity <= 0
		) {
			return new WP_Error(
				'invalid_order_bump_product',
				__(
					'The Special Offer product is invalid.',
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
			return new WP_Error(
				'order_bump_product_unavailable',
				__(
					'The Special Offer product is no longer available.',
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
			return new WP_Error(
				'invalid_order_bump_variation',
				__(
					'The Special Offer product variation is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * A variable parent product cannot be purchased
		 * without selecting an exact variation.
		 */
		if (
			$variation_id <= 0 &&
			$product->is_type(
				'variable'
			)
		) {
			return new WP_Error(
				'order_bump_variation_required',
				__(
					'An exact variation must be selected for the Special Offer product.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $is_free_gift &&
			method_exists(
				$product,
				'is_purchasable'
			) &&
			! $product->is_purchasable()
		) {
			return new WP_Error(
				'order_bump_not_purchasable',
				__(
					'The selected Special Offer product cannot currently be purchased.',
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
			return new WP_Error(
				'order_bump_out_of_stock',
				__(
					'The selected Special Offer product is out of stock.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			method_exists(
				$product,
				'managing_stock'
			) &&
			$product->managing_stock() &&
			! $product->backorders_allowed() &&
			method_exists(
				$product,
				'has_enough_stock'
			) &&
			! $product->has_enough_stock(
				$quantity
			)
		) {
			return new WP_Error(
				'order_bump_insufficient_stock',
				__(
					'There is not enough stock for the selected Special Offer.',
					'eilmo-checkout-flow'
				)
			);
		}

		$pricing_type =
			sanitize_key(
				(string) (
					$offer[
						'pricing_type'
					] ??
						'regular_price'
				)
			);

		if (
			! in_array(
				$pricing_type,
				array(
					'regular_price',
					'fixed_price',
					'percentage_discount',
					'fixed_discount',
				),
				true
			)
		) {
			$pricing_type =
				'regular_price';
		}

		$pricing_value =
			$this->normalize_amount(
				$offer[
					'pricing_value'
				] ??
					0
			);

		if (
			'percentage_discount' ===
				$pricing_type
		) {
			$pricing_value =
				min(
					100,
					$pricing_value
				);
		}

		return array_merge(
			$common,
			array(
			'id' =>
				$id,

			'enabled' =>
				'yes',

			'title' =>
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							$product->get_name()
					)
				),

			'description' =>
				wp_kses_post(
					(string) (
						$offer[
							'description'
						] ??
							''
					)
				),

			'badge' =>
				sanitize_text_field(
					(string) (
						$offer[
							'badge'
						] ??
							''
					)
				),

			'product_id' =>
				$product_id,

			'variation_id' =>
				$variation_id,

			'quantity' =>
				$quantity,

			'pricing_type' =>
				$pricing_type,

			'pricing_value' =>
				$pricing_value,

			'sort_order' =>
				absint(
					$offer[
						'sort_order'
					] ??
						10
				),

			/*
			 * Discount isolation flags.
			 *
			 * Default behavior intentionally protects the
			 * Order Bump from receiving Automatic Discount
			 * on top of its own promotional price.
			 */
			'apply_automatic_discount' =>
				'yes' === (
					$offer[
						'apply_automatic_discount'
					] ??
						'no'
				)
					? 'yes'
					: 'no',

			'apply_coupon' =>
				'yes' === (
					$offer[
						'apply_coupon'
					] ??
						'no'
				)
					? 'yes'
					: 'no',

			'apply_full_payment_discount' =>
				'yes' === (
					$offer[
						'apply_full_payment_discount'
					] ??
						'yes'
				)
					? 'yes'
					: 'no',

			'free_delivery' =>
				false,
			)
		);
	}

	/**
	 * Validate one offer against the WordPress site timezone.
	 *
	 * Legacy offers without an end time remain visible until the
	 * merchant saves them again; every newly created admin row has
	 * a required end time.
	 *
	 * @param array<string, mixed> $offer Offer.
	 *
	 * @return true|WP_Error
	 */
	private function validate_offer_schedule(
		array $offer
	) {
		$now = time();
		$start = $this->get_offer_timestamp( (string) ( $offer['start_at'] ?? '' ) );
		$end = $this->get_offer_timestamp( (string) ( $offer['end_at'] ?? '' ) );

		if ( $start > 0 && $now < $start ) {
			return new WP_Error( 'special_offer_not_started', __( 'This Special Discount has not started yet.', 'eilmo-checkout-flow' ) );
		}

		if ( $end > 0 && $now >= $end ) {
			return new WP_Error( 'special_offer_expired', __( 'This Special Discount has expired.', 'eilmo-checkout-flow' ) );
		}

		return true;
	}

	/**
	 * Convert an admin datetime-local value in the WordPress site
	 * timezone to an absolute Unix timestamp.
	 *
	 * @param string $value Datetime-local value.
	 *
	 * @return int
	 */
	private function get_offer_timestamp(
		string $value
	): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}

		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $value, $timezone );
			return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : 0;
		} catch ( \Exception $exception ) {
			return 0;
		}
	}

	/**
	 * Normalize selected IDs.
	 *
	 * @param mixed $selected_ids IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_selected_ids(
		$selected_ids
	): array {

		if ( ! is_array( $selected_ids ) ) {

			if (
				is_string(
					$selected_ids
				) &&
				'' !== trim(
					$selected_ids
				)
			) {
				$selected_ids =
					array(
						$selected_ids,
					);
			} else {
				return array();
			}
		}

		$normalized =
			array();

		foreach (
			$selected_ids as
				$selected_id
		) {

			$selected_id =
				sanitize_key(
					(string) $selected_id
				);

			if ( '' === $selected_id ) {
				continue;
			}

			$normalized[] =
				$selected_id;
		}

		return array_values(
			array_unique(
				$normalized
			)
		);
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
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		return max(
			0,
			(float) $quantity
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

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount =
				wc_format_decimal(
					$amount,
					$decimals
				);
		}

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}
