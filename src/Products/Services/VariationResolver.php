<?php
/**
 * Variation resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves WooCommerce product variations.
 */
final class VariationResolver {

	/**
	 * Get valid variations for a variable product.
	 *
	 * Out-of-stock variations are intentionally kept so the
	 * checkout UI can display their stock status.
	 *
	 * Disabled variations and variations without a price
	 * are excluded using WooCommerce's native visibility logic.
	 *
	 * @param \WC_Product_Variable $product Variable product.
	 *
	 * @return array<int, \WC_Product_Variation>
	 */
	public function get_variations( \WC_Product_Variable $product ): array {

		$variation_ids = $product->get_children();

		if ( empty( $variation_ids ) ) {
			return array();
		}

		$variations = array();

		foreach ( $variation_ids as $variation_id ) {

			$variation = $this->resolve_variation(
				absint( $variation_id ),
				$product->get_id()
			);

			if ( ! $variation ) {
				continue;
			}

			$variations[] = $variation;
		}

		/**
		 * Filters resolved product variations.
		 *
		 * @param array<int, \WC_Product_Variation> $variations Resolved variations.
		 * @param \WC_Product_Variable               $product    Parent product.
		 */
		$variations = apply_filters(
			'eilmo_cf/resolved_variations',
			$variations,
			$product
		);

		if ( ! is_array( $variations ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$variations,
				static function ( $variation ): bool {

					return $variation instanceof \WC_Product_Variation;
				}
			)
		);
	}

	/**
	 * Resolve a single variation.
	 *
	 * @param int $variation_id      Variation ID.
	 * @param int $parent_product_id Parent variable product ID.
	 *
	 * @return \WC_Product_Variation|null
	 */
	public function resolve_variation(
		int $variation_id,
		int $parent_product_id = 0
	): ?\WC_Product_Variation {

		$variation_id = absint( $variation_id );

		if ( $variation_id <= 0 ) {
			return null;
		}

		$variation = wc_get_product( $variation_id );

		if ( ! $variation instanceof \WC_Product_Variation ) {
			return null;
		}

		if ( ! $variation->exists() ) {
			return null;
		}

		if (
			$parent_product_id > 0 &&
			$variation->get_parent_id() !== $parent_product_id
		) {
			return null;
		}

		/*
		 * WooCommerce considers disabled variations and variations
		 * with an empty price invisible.
		 *
		 * Out-of-stock variations are NOT removed here because
		 * Eilmo Checkout Flow needs to display their stock status.
		 */
		if ( ! $variation->variation_is_visible() ) {
			return null;
		}

		/**
		 * Filters whether this variation can appear in
		 * Eilmo Checkout Flow.
		 *
		 * @param bool                  $allowed   Whether variation is allowed.
		 * @param \WC_Product_Variation $variation Variation object.
		 */
		$allowed = (bool) apply_filters(
			'eilmo_cf/variation_is_allowed',
			true,
			$variation
		);

		if ( ! $allowed ) {
			return null;
		}

		/**
		 * Filters the resolved variation.
		 *
		 * @param \WC_Product_Variation $variation         Variation object.
		 * @param int                   $variation_id      Variation ID.
		 * @param int                   $parent_product_id Parent product ID.
		 */
		$variation = apply_filters(
			'eilmo_cf/resolved_variation',
			$variation,
			$variation_id,
			$parent_product_id
		);

		return $variation instanceof \WC_Product_Variation
			? $variation
			: null;
	}
}