<?php
/**
 * Product stock service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce product stock information and validation.
 */
final class StockService {

	/**
	 * Stock status: in stock.
	 *
	 * @var string
	 */
	public const IN_STOCK = 'instock';

	/**
	 * Stock status: out of stock.
	 *
	 * @var string
	 */
	public const OUT_OF_STOCK = 'outofstock';

	/**
	 * Stock status: on backorder.
	 *
	 * @var string
	 */
	public const ON_BACKORDER = 'onbackorder';

	/**
	 * Determine whether a product can be purchased.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function can_purchase( \WC_Product $product ): bool {

		$can_purchase = $product->is_purchasable()
			&& $product->is_in_stock();

		/**
		 * Filters whether a checkout product can be purchased.
		 *
		 * @param bool        $can_purchase Whether product can be purchased.
		 * @param \WC_Product $product      WooCommerce product.
		 */
		return (bool) apply_filters(
			'eilmo_cf/stock_can_purchase',
			$can_purchase,
			$product
		);
	}

	/**
	 * Determine whether stock management is enabled.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function manages_stock( \WC_Product $product ): bool {

		return $product->managing_stock();
	}

	/**
	 * Get WooCommerce stock status.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return string
	 */
	public function get_status( \WC_Product $product ): string {

		$status = $product->get_stock_status();

		if (
			! in_array(
				$status,
				array(
					self::IN_STOCK,
					self::OUT_OF_STOCK,
					self::ON_BACKORDER,
				),
				true
			)
		) {
			return self::OUT_OF_STOCK;
		}

		return $status;
	}

	/**
	 * Determine whether the product is in stock.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function is_in_stock( \WC_Product $product ): bool {

		return $product->is_in_stock();
	}

	/**
	 * Determine whether the product is out of stock.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function is_out_of_stock( \WC_Product $product ): bool {

		return self::OUT_OF_STOCK === $this->get_status( $product );
	}

	/**
	 * Determine whether the product is on backorder.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function is_on_backorder( \WC_Product $product ): bool {

		return self::ON_BACKORDER === $this->get_status( $product );
	}

	/**
	 * Determine whether a requested quantity would be backordered.
	 *
	 * @param \WC_Product $product  WooCommerce product or variation.
	 * @param int         $quantity Requested quantity.
	 *
	 * @return bool
	 */
	public function requires_backorder(
		\WC_Product $product,
		int $quantity = 1
	): bool {

		$quantity = max( 0, $quantity );

		if ( 0 === $quantity ) {
			return false;
		}

		return $product->is_on_backorder( $quantity );
	}

	/**
	 * Get remaining managed stock quantity.
	 *
	 * Null means WooCommerce is not managing stock for the product.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return int|null
	 */
	public function get_remaining_stock( \WC_Product $product ): ?int {

		if ( ! $this->manages_stock( $product ) ) {
			return null;
		}

		$quantity = $product->get_stock_quantity();

		if ( null === $quantity ) {
			return null;
		}

		return max( 0, (int) $quantity );
	}

	/**
	 * Get WooCommerce low-stock threshold.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return int
	 */
	public function get_low_stock_threshold( \WC_Product $product ): int {

		if ( function_exists( 'wc_get_low_stock_amount' ) ) {
			return max(
				0,
				(int) wc_get_low_stock_amount( $product )
			);
		}

		return 0;
	}

	/**
	 * Determine whether the product has low stock.
	 *
	 * A product must be using managed stock and have a positive
	 * remaining quantity to be considered low stock.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return bool
	 */
	public function is_low_stock( \WC_Product $product ): bool {

		$remaining = $this->get_remaining_stock( $product );

		if ( null === $remaining || $remaining <= 0 ) {
			return false;
		}

		$threshold = $this->get_low_stock_threshold( $product );

		if ( $threshold <= 0 ) {
			return false;
		}

		$is_low_stock = $remaining <= $threshold;

		/**
		 * Filters whether a product should be considered low stock.
		 *
		 * @param bool        $is_low_stock Whether stock is low.
		 * @param int         $remaining    Remaining stock.
		 * @param int         $threshold    Low-stock threshold.
		 * @param \WC_Product $product      WooCommerce product.
		 */
		return (bool) apply_filters(
			'eilmo_cf/is_low_stock',
			$is_low_stock,
			$remaining,
			$threshold,
			$product
		);
	}

	/**
	 * Get the maximum quantity allowed for checkout.
	 *
	 * A return value of -1 means there is no explicit maximum.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return int
	 */
	public function get_max_quantity( \WC_Product $product ): int {

		if ( ! $this->can_purchase( $product ) ) {
			return 0;
		}

		if ( $product->is_sold_individually() ) {
			return 1;
		}

		$maximum = (int) $product->get_max_purchase_quantity();

		if ( $maximum < 0 ) {
			return -1;
		}

		return max( 0, $maximum );
	}

	/**
	 * Validate requested product quantity.
	 *
	 * Quantity zero is valid because it represents an unselected
	 * product in Eilmo Checkout Flow.
	 *
	 * @param \WC_Product $product  WooCommerce product or variation.
	 * @param int         $quantity Requested quantity.
	 *
	 * @return bool
	 */
	public function is_valid_quantity(
		\WC_Product $product,
		int $quantity
	): bool {

		if ( $quantity < 0 ) {
			return false;
		}

		if ( 0 === $quantity ) {
			return true;
		}

		if ( ! $this->can_purchase( $product ) ) {
			return false;
		}

		if ( $product->is_sold_individually() && $quantity > 1 ) {
			return false;
		}

		$maximum = $this->get_max_quantity( $product );

		if ( $maximum >= 0 && $quantity > $maximum ) {
			return false;
		}

		if ( ! $product->has_enough_stock( $quantity ) ) {
			return false;
		}

		/**
		 * Filters product quantity validation.
		 *
		 * @param bool        $is_valid Whether quantity is valid.
		 * @param \WC_Product $product  WooCommerce product.
		 * @param int         $quantity Requested quantity.
		 */
		return (bool) apply_filters(
			'eilmo_cf/is_valid_stock_quantity',
			true,
			$product,
			$quantity
		);
	}

	/**
	 * Normalize a requested quantity.
	 *
	 * Invalid negative values become zero and quantities exceeding
	 * the maximum allowed value are reduced to the maximum.
	 *
	 * @param \WC_Product $product  WooCommerce product or variation.
	 * @param int         $quantity Requested quantity.
	 *
	 * @return int
	 */
	public function normalize_quantity(
		\WC_Product $product,
		int $quantity
	): int {

		$quantity = max( 0, $quantity );

		if ( 0 === $quantity ) {
			return 0;
		}

		if ( ! $this->can_purchase( $product ) ) {
			return 0;
		}

		if ( $product->is_sold_individually() ) {
			return 1;
		}

		$maximum = $this->get_max_quantity( $product );

		if ( $maximum >= 0 ) {
			$quantity = min( $quantity, $maximum );
		}

		return $quantity;
	}

	/**
	 * Get normalized stock information for frontend rendering.
	 *
	 * @param \WC_Product $product WooCommerce product or variation.
	 *
	 * @return array<string, mixed>
	 */
	public function get_stock_data( \WC_Product $product ): array {

		$data = array(
			'status'          => $this->get_status( $product ),
			'in_stock'        => $this->is_in_stock( $product ),
			'out_of_stock'    => $this->is_out_of_stock( $product ),
			'on_backorder'    => $this->is_on_backorder( $product ),
			'low_stock'       => $this->is_low_stock( $product ),
			'manages_stock'   => $this->manages_stock( $product ),
			'remaining_stock' => $this->get_remaining_stock( $product ),
			'max_quantity'    => $this->get_max_quantity( $product ),
			'can_purchase'    => $this->can_purchase( $product ),
		);

		/**
		 * Filters normalized checkout stock data.
		 *
		 * @param array<string, mixed> $data    Stock data.
		 * @param \WC_Product          $product WooCommerce product.
		 */
		return (array) apply_filters(
			'eilmo_cf/stock_data',
			$data,
			$product
		);
	}
}