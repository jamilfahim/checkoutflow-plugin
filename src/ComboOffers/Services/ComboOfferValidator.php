<?php
/**
 * Combo Offer validator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ComboOffers\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use WC_Product;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates selected Combo Offers server-side.
 *
 * Frontend Combo IDs, selection mode and prices are
 * never trusted.
 */
final class ComboOfferValidator {

	/**
	 * Maximum selected Combo Offers.
	 */
	private const MAX_SELECTED_OFFERS = 100;

	/**
	 * Repository.
	 *
	 * @var ComboOfferRepository
	 */
	private $repository;

	/**
	 * Calculator.
	 *
	 * @var ComboOfferCalculator
	 */
	private $calculator;

	/**
	 * Signed context service.
	 *
	 * @var ComboOfferContext
	 */
	private $context_service;

	/**
	 * Constructor.
	 *
	 * @param ComboOfferRepository|null $repository      Repository.
	 * @param ComboOfferCalculator|null $calculator      Calculator.
	 * @param ComboOfferContext|null    $context_service Context service.
	 */
	public function __construct(
		?ComboOfferRepository $repository = null,
		?ComboOfferCalculator $calculator = null,
		?ComboOfferContext $context_service = null
	) {

		$this->repository =
			$repository
				? $repository
				: new ComboOfferRepository();

		$this->calculator =
			$calculator
				? $calculator
				: new ComboOfferCalculator();

		$this->context_service =
			$context_service
				? $context_service
				: new ComboOfferContext();
	}

	/**
	 * Validate Combo Offer checkout payload.
	 *
	 * @param mixed $payload Combo payload.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function validate(
		$payload
	) {

		if (
			null === $payload ||
			'' === $payload
		) {
			return $this->get_empty_result();
		}

		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'invalid_combo_request',
				__(
					'Invalid Combo Offer data was received.',
					'eilmo-checkout-flow'
				)
			);
		}

		$selected_ids =
			$this->normalize_ids(
				$payload['selected_ids'] ??
					array()
			);

		/*
		 * Combo selection is optional.
		 */
		if ( empty( $selected_ids ) ) {
			return $this->get_empty_result();
		}

		if (
			count( $selected_ids ) >
				self::MAX_SELECTED_OFFERS
		) {
			return new WP_Error(
				'too_many_combo_offers',
				__(
					'Too many Combo Offers were selected.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $this->combo_library_is_enabled()
		) {
			return new WP_Error(
				'combo_offers_disabled',
				__(
					'Combo Offers are currently unavailable.',
					'eilmo-checkout-flow'
				)
			);
		}

		$context =
			isset(
				$payload['context']
			) &&
			is_array(
				$payload['context']
			)
				? $payload['context']
				: array();

		/*
		 * Signed checkout context is mandatory whenever
		 * one or more Combo Offers are selected.
		 */
		if (
			empty( $context ) ||
			! $this->context_service->verify(
				$context
			)
		) {
			return new WP_Error(
				'invalid_combo_context',
				__(
					'The Combo Offer selection could not be verified. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		$allowed_ids =
			$this->context_service->get_allowed_ids(
				$context
			);

		if ( empty( $allowed_ids ) ) {
			return new WP_Error(
				'invalid_combo_context',
				__(
					'The Combo Offer selection could not be verified. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Selected Combo IDs must belong to the signed
		 * allowed list for this exact checkout instance.
		 */
		foreach ( $selected_ids as $offer_id ) {

			if (
				! in_array(
					$offer_id,
					$allowed_ids,
					true
				)
			) {
				return new WP_Error(
					'combo_offer_not_allowed',
					__(
						'One of the selected Combo Offers is not available for this checkout.',
						'eilmo-checkout-flow'
					)
				);
			}
		}

		/*
		 * Do not trust payload.selection_mode.
		 *
		 * The selection mode stored inside the signed
		 * context is authoritative.
		 */
		$selection_mode =
			$this->context_service->get_selection_mode(
				$context
			);

		if (
			'single' === $selection_mode &&
			count( $selected_ids ) > 1
		) {
			return new WP_Error(
				'combo_single_selection_required',
				__(
					'Please select only one Combo Offer.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Reload selected Combos from trusted settings.
		 *
		 * Frontend is never authoritative.
		 */
		$offers =
			$this->repository->get_many(
				$selected_ids,
				true
			);

		if (
			! is_array( $offers ) ||
			count( $offers ) !==
				count( $selected_ids )
		) {
			return new WP_Error(
				'combo_offer_unavailable',
				__(
					'One of the selected Combo Offers is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$offers_by_id =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$offer_id =
				sanitize_key(
					(string) (
						$offer['id'] ??
							''
					)
				);

			if ( '' === $offer_id ) {
				continue;
			}

			$offers_by_id[
				$offer_id
			] =
				$offer;
		}

		$validated_offers =
			array();

		$component_items =
			array();

		$regular_total =
			0.0;

		$combo_discount =
			0.0;

		$combo_total =
			0.0;

		/*
		 * Preserve customer-selected order.
		 */
		foreach ( $selected_ids as $offer_id ) {

			if (
				! isset(
					$offers_by_id[
						$offer_id
					]
				) ||
				! is_array(
					$offers_by_id[
						$offer_id
					]
				)
			) {
				return new WP_Error(
					'combo_offer_unavailable',
					__(
						'One of the selected Combo Offers is no longer available.',
						'eilmo-checkout-flow'
					)
				);
			}

			$validated_offer =
				$this->validate_offer(
					$offers_by_id[
						$offer_id
					]
				);

			if (
				is_wp_error(
					$validated_offer
				)
			) {
				return $validated_offer;
			}

			$validated_offers[] =
				$validated_offer;

			$regular_total +=
				(float) (
					$validated_offer[
						'regular_total'
					] ??
						0
				);

			$combo_discount +=
				(float) (
					$validated_offer[
						'discount'
					] ??
						0
				);

			$combo_total +=
				(float) (
					$validated_offer[
						'combo_total'
					] ??
						0
				);

			if (
				isset(
					$validated_offer[
						'items'
					]
				) &&
				is_array(
					$validated_offer[
						'items'
					]
				)
			) {
				foreach (
					$validated_offer[
						'items'
					] as $item
				) {
					if (
						is_array(
							$item
						)
					) {
						$component_items[] =
							$item;
					}
				}
			}
		}

		$result = array(
			'selected_ids' =>
				$selected_ids,

			'selection_mode' =>
				$selection_mode,

			'instance_id' =>
				$this->context_service->get_instance_id(
					$context
				),

			'allowed_ids' =>
				$allowed_ids,

			'offers' =>
				$validated_offers,

			'items' =>
				$component_items,

			'regular_total' =>
				$this->normalize_amount(
					$regular_total
				),

			'discount' =>
				$this->normalize_amount(
					$combo_discount
				),

			'combo_total' =>
				$this->normalize_amount(
					$combo_total
				),
		);

		/**
		 * Filters validated Combo Offer result.
		 *
		 * Frontend prices must never become authoritative
		 * through this filter.
		 *
		 * @param array<string,mixed> $result  Result.
		 * @param array<string,mixed> $payload Raw payload.
		 */
		$result =
			apply_filters(
				'eilmo_cf/combo_offers/validated',
				$result,
				$payload
			);

		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'invalid_combo_result',
				__(
					'Combo Offer validation failed. Please try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		return $result;
	}

	/**
	 * Validate one Combo Offer.
	 *
	 * @param array<string,mixed> $offer Offer.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_offer(
		array $offer
	) {

		$offer_id =
			sanitize_key(
				(string) (
					$offer['id'] ??
						''
				)
			);

		if ( '' === $offer_id ) {
			return new WP_Error(
				'invalid_combo_offer',
				__(
					'An invalid Combo Offer was selected.',
					'eilmo-checkout-flow'
				)
			);
		}

		$items =
			isset(
				$offer['items']
			) &&
			is_array(
				$offer['items']
			)
				? $offer['items']
				: array();

		if ( empty( $items ) ) {
			return new WP_Error(
				'empty_combo_offer',
				__(
					'The selected Combo Offer does not contain any products.',
					'eilmo-checkout-flow'
				)
			);
		}

		$validated_items =
			array();

		$regular_total =
			0.0;

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				return new WP_Error(
					'invalid_combo_product',
					__(
						'A product in the selected Combo Offer is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}

			$validated_item =
				$this->validate_item(
					$item,
					$offer_id
				);

			if (
				is_wp_error(
					$validated_item
				)
			) {
				return $validated_item;
			}

			$validated_items[] =
				$validated_item;

			$regular_total +=
				(float) (
					$validated_item[
						'line_total'
					] ??
						0
				);
		}

		$regular_total =
			$this->normalize_amount(
				$regular_total
			);

		if ( $regular_total <= 0 ) {
			return new WP_Error(
				'invalid_combo_price',
				__(
					'The selected Combo Offer does not have a valid price.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Discount compatibility is loaded only from the
		 * trusted saved Combo Offer configuration.
		 *
		 * Preserve the historical Combo behavior by
		 * default:
		 *
		 * - Automatic Discount: no.
		 * - Coupon Discount: yes.
		 * - Full Payment Discount: yes.
		 */
		$apply_automatic_discount =
			'yes' ===
				(
					$offer[
						'apply_automatic_discount'
					] ??
						'no'
				)
					? 'yes'
					: 'no';

		$apply_coupon =
			'no' ===
				(
					$offer[
						'apply_coupon'
					] ??
						'yes'
				)
					? 'no'
					: 'yes';

		$apply_full_payment_discount =
			'no' ===
				(
					$offer[
						'apply_full_payment_discount'
					] ??
						'yes'
				)
					? 'no'
					: 'yes';

		/*
		 * Recalculate Combo price server-side.
		 */
		$calculation =
			$this->calculator->calculate(
				$offer,
				$regular_total
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
			return new WP_Error(
				'invalid_combo_calculation',
				__(
					'The selected Combo Offer could not be calculated.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'id' =>
				$offer_id,

			'title' =>
				sanitize_text_field(
					(string) (
						$offer['title'] ??
							''
					)
				),

			'pricing_type' =>
				sanitize_key(
					(string) (
						$calculation[
							'pricing_type'
						] ??
							$offer[
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

			'items' =>
				$validated_items,

			'regular_total' =>
				$this->normalize_amount(
					$calculation[
						'regular_total'
					] ??
						$regular_total
				),

			'discount' =>
				$this->normalize_amount(
					$calculation[
						'discount'
					] ??
						0
				),

			'combo_total' =>
				$this->normalize_amount(
					$calculation[
						'combo_total'
					] ??
						$regular_total
				),

			'apply_automatic_discount' =>
				$apply_automatic_discount,

			'apply_coupon' =>
				$apply_coupon,

			'apply_full_payment_discount' =>
				$apply_full_payment_discount,
		);
	}

	/**
	 * Validate one Combo component product.
	 *
	 * @param array<string,mixed> $item     Item.
	 * @param string              $offer_id Combo ID.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_item(
		array $item,
		string $offer_id
	) {

		if (
			! function_exists(
				'wc_get_product'
			)
		) {
			return new WP_Error(
				'woocommerce_unavailable',
				__(
					'WooCommerce product validation is currently unavailable.',
					'eilmo-checkout-flow'
				)
			);
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
					1
			);

		if (
			$product_id <= 0 ||
			$quantity <= 0
		) {
			return new WP_Error(
				'invalid_combo_product',
				__(
					'A product in the selected Combo Offer is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		$lookup_id =
			$variation_id > 0
				? $variation_id
				: $product_id;

		$product =
			wc_get_product(
				$lookup_id
			);

		if (
			! $product instanceof
				WC_Product
		) {
			return new WP_Error(
				'combo_product_not_found',
				__(
					'A product in the selected Combo Offer no longer exists.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Variation must belong to configured parent.
		 */
		if (
			$variation_id > 0 &&
			absint(
				$product->get_parent_id()
			) !==
				$product_id
		) {
			return new WP_Error(
				'invalid_combo_variation',
				__(
					'A variation in the selected Combo Offer is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Variable parents cannot be purchased without
		 * selecting an exact variation.
		 */
		if (
			0 === $variation_id &&
			$product->is_type(
				'variable'
			)
		) {
			return new WP_Error(
				'combo_variation_required',
				__(
					'A variable product in the selected Combo Offer requires a specific variation.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $product->is_purchasable()
		) {
			return new WP_Error(
				'combo_product_not_purchasable',
				__(
					'A product in the selected Combo Offer can no longer be purchased.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $product->is_in_stock()
		) {
			return new WP_Error(
				'combo_product_out_of_stock',
				__(
					'A product in the selected Combo Offer is out of stock.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			$product->managing_stock() &&
			! $product->backorders_allowed() &&
			! $product->has_enough_stock(
				$quantity
			)
		) {
			return new WP_Error(
				'combo_product_insufficient_stock',
				__(
					'There is not enough stock for a product in the selected Combo Offer.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Server-authoritative current WooCommerce price.
		 */
		$unit_price =
			$this->normalize_amount(
				$product->get_price()
			);

		$line_total =
			$this->normalize_amount(
				$unit_price *
					$quantity
			);

		if ( $line_total <= 0 ) {
			return new WP_Error(
				'invalid_combo_product_price',
				__(
					'A product in the selected Combo Offer does not have a valid price.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'combo_id' =>
				$offer_id,

			'product_id' =>
				$product_id,

			'variation_id' =>
				$variation_id,

			'quantity' =>
				$quantity,

			'product_name' =>
				sanitize_text_field(
					(string) $product->get_name()
				),

			'unit_price' =>
				$unit_price,

			'line_total' =>
				$line_total,
		);
	}

	/**
	 * Determine whether global Combo Offer library is enabled.
	 *
	 * @return bool
	 */
	private function combo_library_is_enabled(): bool {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$default_enabled =
			(string) (
				$defaults[
					'combo_offers'
				][
					'enabled'
				] ??
					'no'
			);

		if (
			! is_array(
				$stored
			) ||
			! isset(
				$stored[
					'combo_offers'
				]
			) ||
			! is_array(
				$stored[
					'combo_offers'
				]
			)
		) {
			return (
				'yes' ===
					$default_enabled
			);
		}

		return (
			'yes' ===
				(string) (
					$stored[
						'combo_offers'
					][
						'enabled'
					] ??
						$default_enabled
				)
		);
	}

	/**
	 * Normalize Combo IDs.
	 *
	 * @param mixed $ids IDs.
	 *
	 * @return array<int,string>
	 */
	private function normalize_ids(
		$ids
	): array {

		if ( is_string( $ids ) ) {
			$ids =
				preg_split(
					'/[\s,]+/',
					$ids
				);
		}

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$normalized =
			array();

		foreach ( $ids as $id ) {

			if ( ! is_scalar( $id ) ) {
				continue;
			}

			$id =
				sanitize_key(
					(string) $id
				);

			if (
				'' === $id ||
				in_array(
					$id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$id;
		}

		return $normalized;
	}

	/**
	 * Normalize WooCommerce stock quantity.
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

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
	}

	/**
	 * Normalize money amount.
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

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			max(
				0.0,
				(float) $amount
			),
			$decimals
		);
	}

	/**
	 * Empty Combo result.
	 *
	 * @return array<string,mixed>
	 */
	private function get_empty_result(): array {

		return array(
			'selected_ids' =>
				array(),

			'selection_mode' =>
				'multiple',

			'instance_id' =>
				'',

			'allowed_ids' =>
				array(),

			'offers' =>
				array(),

			'items' =>
				array(),

			'regular_total' =>
				0.0,

			'discount' =>
				0.0,

			'combo_total' =>
				0.0,
		);
	}
}