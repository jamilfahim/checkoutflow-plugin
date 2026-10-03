<?php
/**
 * Product resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves WooCommerce products.
 */
final class ProductResolver {

	/**
	 * Supported product types.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_TYPES = array(
		'simple',
		'variable',
	);

	/**
	 * Resolve a WooCommerce product by ID.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return \WC_Product|null
	 */
	public function resolve_product( int $product_id ): ?\WC_Product {

		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		if ( ! $this->is_supported( $product ) ) {
			return null;
		}

		if ( ! $this->is_visible( $product ) ) {
			return null;
		}

		/**
		 * Filters the resolved product.
		 *
		 * @param \WC_Product $product    Resolved WooCommerce product.
		 * @param int         $product_id Product ID.
		 */
		$product = apply_filters(
			'eilmo_cf/resolved_product',
			$product,
			$product_id
		);

		return $product instanceof \WC_Product
			? $product
			: null;
	}

	/**
	 * Resolve multiple WooCommerce products.
	 *
	 * Invalid and unsupported products are automatically skipped.
	 *
	 * @param array<int, int|string> $product_ids Product IDs.
	 *
	 * @return array<int, \WC_Product>
	 */
	public function resolve_products( array $product_ids ): array {

		$products = array();

		foreach ( $product_ids as $product_id ) {

			$product = $this->resolve_product(
				absint( $product_id )
			);

			if ( ! $product ) {
				continue;
			}

			$products[] = $product;
		}

		/**
		 * Filters resolved products.
		 *
		 * @param array<int, \WC_Product> $products    Resolved products.
		 * @param array<int, int|string>  $product_ids Original product IDs.
		 */
		return (array) apply_filters(
			'eilmo_cf/resolved_products',
			$products,
			$product_ids
		);
	}

	/**
	 * Determine whether a WooCommerce product type is supported.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 *
	 * @return bool
	 */
	public function is_supported( \WC_Product $product ): bool {

		$supported_types = self::SUPPORTED_TYPES;

		/**
		 * Filters supported WooCommerce product types.
		 *
		 * V1 supports simple and variable products.
		 *
		 * @param array<int, string> $supported_types Supported types.
		 * @param \WC_Product        $product         WooCommerce product.
		 */
		$supported_types = (array) apply_filters(
			'eilmo_cf/supported_product_types',
			$supported_types,
			$product
		);

		return in_array(
			$product->get_type(),
			$supported_types,
			true
		);
	}

	/**
	 * Determine whether a product can be displayed.
	 *
	 * This prevents trashed, draft or otherwise non-viewable products
	 * from appearing on the public checkout flow.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 *
	 * @return bool
	 */
	private function is_visible( \WC_Product $product ): bool {

		$product_id = $product->get_id();

		if ( $product_id <= 0 ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $product_id ) ) {
			return false;
		}

		/**
		 * Filters whether a resolved product is allowed to render.
		 *
		 * @param bool        $visible Product visibility.
		 * @param \WC_Product $product WooCommerce product.
		 */
		return (bool) apply_filters(
			'eilmo_cf/product_is_visible',
			true,
			$product
		);
	}
}