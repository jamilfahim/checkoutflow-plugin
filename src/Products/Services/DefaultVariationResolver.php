<?php
/**
 * Default variation resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves a safe default variation without bypassing WooCommerce rules.
 */
final class DefaultVariationResolver {

	/**
	 * Parent product meta containing the Eilmo default variation ID.
	 */
	public const META_KEY = '_eilmo_cf_default_variation_id';

	/**
	 * Variation resolver.
	 *
	 * @var VariationResolver
	 */
	private $variation_resolver;

	/**
	 * Constructor.
	 *
	 * @param VariationResolver|null $variation_resolver Variation resolver.
	 */
	public function __construct(
		?VariationResolver $variation_resolver = null
	) {

		$this->variation_resolver = $variation_resolver ?? new VariationResolver();
	}

	/**
	 * Resolve a selectable default variation.
	 *
	 * @param \WC_Product_Variable $product Variable parent product.
	 * @param string               $mode    configured|first_available|customer.
	 *
	 * @return \WC_Product_Variation|null
	 */
	public function resolve(
		\WC_Product_Variable $product,
		string $mode = 'configured'
	): ?\WC_Product_Variation {

		$mode = sanitize_key( $mode );

		if ( 'customer' === $mode ) {
			return null;
		}

		$variation = $this->resolve_eilmo_default( $product );

		if ( ! $variation ) {
			$variation = $this->resolve_woocommerce_default( $product );
		}

		if ( ! $variation && 'first_available' === $mode ) {
			$variation = $this->resolve_first_available( $product );
		}

		/**
		 * Filters the variation preselected by Single Product mode.
		 *
		 * @param \WC_Product_Variation|null $variation Default variation.
		 * @param \WC_Product_Variable       $product   Parent product.
		 * @param string                     $mode      Selection mode.
		 */
		$variation = apply_filters(
			'eilmo_cf/default_single_variation',
			$variation,
			$product,
			$mode
		);

		return $variation instanceof \WC_Product_Variation &&
			$this->is_selectable( $variation )
				? $variation
				: null;
	}

	/**
	 * Resolve the variation selected in Eilmo product metadata.
	 *
	 * @param \WC_Product_Variable $product Parent product.
	 *
	 * @return \WC_Product_Variation|null
	 */
	private function resolve_eilmo_default(
		\WC_Product_Variable $product
	): ?\WC_Product_Variation {

		$variation_id = absint( $product->get_meta( self::META_KEY, true ) );

		if ( $variation_id <= 0 ) {
			return null;
		}

		$variation = $this->variation_resolver->resolve_variation(
			$variation_id,
			$product->get_id()
		);

		return $variation && $this->is_selectable( $variation )
			? $variation
			: null;
	}

	/**
	 * Resolve WooCommerce default form attributes.
	 *
	 * @param \WC_Product_Variable $product Parent product.
	 *
	 * @return \WC_Product_Variation|null
	 */
	private function resolve_woocommerce_default(
		\WC_Product_Variable $product
	): ?\WC_Product_Variation {

		$defaults = $product->get_default_attributes();

		if ( empty( $defaults ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $defaults as $attribute => $value ) {
			$attributes[ 'attribute_' . sanitize_title( $attribute ) ] =
				(string) $value;
		}

		$data_store = \WC_Data_Store::load( 'product' );

		if ( ! is_callable( array( $data_store, 'find_matching_product_variation' ) ) ) {
			return null;
		}

		$variation_id = absint(
			$data_store->find_matching_product_variation(
				$product,
				$attributes
			)
		);

		$variation = $this->variation_resolver->resolve_variation(
			$variation_id,
			$product->get_id()
		);

		return $variation && $this->is_selectable( $variation )
			? $variation
			: null;
	}

	/**
	 * Resolve the first visible, purchasable and in-stock variation.
	 *
	 * @param \WC_Product_Variable $product Parent product.
	 *
	 * @return \WC_Product_Variation|null
	 */
	private function resolve_first_available(
		\WC_Product_Variable $product
	): ?\WC_Product_Variation {

		foreach ( $this->variation_resolver->get_variations( $product ) as $variation ) {
			if ( $this->is_selectable( $variation ) ) {
				return $variation;
			}
		}

		return null;
	}

	/**
	 * Determine whether a variation may be preselected for ordering.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 *
	 * @return bool
	 */
	private function is_selectable(
		\WC_Product_Variation $variation
	): bool {

		return $variation->is_purchasable() &&
			$variation->is_in_stock();
	}
}
