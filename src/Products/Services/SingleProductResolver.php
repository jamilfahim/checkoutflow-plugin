<?php
/**
 * Single Product resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves exactly one supported WooCommerce parent product.
 */
final class SingleProductResolver {

	/**
	 * Shared product resolver.
	 *
	 * @var ProductResolver
	 */
	private $product_resolver;

	/**
	 * Constructor.
	 *
	 * @param ProductResolver|null $product_resolver Product resolver.
	 */
	public function __construct(
		?ProductResolver $product_resolver = null
	) {

		$this->product_resolver = $product_resolver ?? new ProductResolver();
	}

	/**
	 * Resolve the first valid parent product only.
	 *
	 * A Variable product still counts as one product; its child variations are
	 * intentionally not treated as separate checkout products.
	 *
	 * @param array<int, int|string> $product_ids        Requested product IDs.
	 * @param int                    $fallback_product_id Optional current product.
	 *
	 * @return \WC_Product|null
	 */
	public function resolve(
		array $product_ids,
		int $fallback_product_id = 0
	): ?\WC_Product {

		$product_ids = $this->normalize_ids( $product_ids );

		if ( empty( $product_ids ) && $fallback_product_id > 0 ) {
			$product_ids[] = absint( $fallback_product_id );
		}

		$product = null;

		foreach ( $product_ids as $product_id ) {
			$product = $this->product_resolver->resolve_product( $product_id );

			if ( $product ) {
				break;
			}
		}

		/**
		 * Filters the one product used by Single Product mode.
		 *
		 * @param \WC_Product|null $product             Resolved product.
		 * @param array<int, int>  $product_ids         Normalized requested IDs.
		 * @param int              $fallback_product_id Fallback product ID.
		 */
		$product = apply_filters(
			'eilmo_cf/resolved_single_product',
			$product,
			$product_ids,
			$fallback_product_id
		);

		return $product instanceof \WC_Product &&
			$product->is_type( array( 'simple', 'variable' ) )
			? $product
			: null;
	}

	/**
	 * Normalize and de-duplicate requested parent product IDs.
	 *
	 * @param array<int, int|string> $product_ids Product IDs.
	 *
	 * @return array<int, int>
	 */
	private function normalize_ids( array $product_ids ): array {

		$normalized = array();

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );

			if ( $product_id > 0 ) {
				$normalized[] = $product_id;
			}
		}

		return array_values( array_unique( $normalized ) );
	}
}
