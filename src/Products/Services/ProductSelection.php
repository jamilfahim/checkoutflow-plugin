<?php
/**
 * Product selection service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Handles checkout product selection rules.
 */
final class ProductSelection {

	/**
	 * Single selection mode.
	 *
	 * @var string
	 */
	public const SINGLE = 'single';

	/**
	 * Multiple selection mode.
	 *
	 * @var string
	 */
	public const MULTIPLE = 'multiple';

	/**
	 * Normalize selected checkout items.
	 *
	 * Expected item format:
	 *
	 * array(
	 *     array(
	 *         'product_id'   => 123,
	 *         'variation_id' => 0,
	 *         'quantity'     => 2,
	 *     ),
	 * )
	 *
	 * In single mode, only one item can have a quantity greater than zero.
	 * The selected item's quantity can still be greater than one.
	 *
	 * @param array<int, array<string, mixed>> $items          Checkout items.
	 * @param string                           $selection_mode Selection mode.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function normalize_items(
		array $items,
		string $selection_mode = self::SINGLE
	): array {

		$selection_mode = $this->sanitize_mode( $selection_mode );
		$normalized     = array();

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$normalized_item = $this->normalize_item( $item );

			if ( null === $normalized_item ) {
				continue;
			}

			$item_key = $this->get_item_key(
				$normalized_item['product_id'],
				$normalized_item['variation_id']
			);

			if ( isset( $normalized[ $item_key ] ) ) {
				$normalized[ $item_key ]['quantity'] += $normalized_item['quantity'];
				continue;
			}

			$normalized[ $item_key ] = $normalized_item;
		}

		$normalized = array_values( $normalized );

		if ( self::SINGLE === $selection_mode ) {
			$normalized = $this->enforce_single_selection( $normalized );
		}

		/**
		 * Filters normalized checkout product selections.
		 *
		 * @param array<int, array<string, int>> $normalized     Normalized items.
		 * @param string                         $selection_mode Selection mode.
		 */
		$normalized = apply_filters(
			'eilmo_cf/normalized_product_selection',
			$normalized,
			$selection_mode
		);

		return $this->validate_filtered_items( $normalized );
	}

	/**
	 * Get only selected items.
	 *
	 * Items with quantity zero are not considered selected.
	 *
	 * @param array<int, array<string, mixed>> $items          Checkout items.
	 * @param string                           $selection_mode Selection mode.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function get_selected_items(
		array $items,
		string $selection_mode = self::SINGLE
	): array {

		$items = $this->normalize_items(
			$items,
			$selection_mode
		);

		return array_values(
			array_filter(
				$items,
				static function ( array $item ): bool {
					return $item['quantity'] > 0;
				}
			)
		);
	}

	/**
	 * Determine whether at least one product is selected.
	 *
	 * @param array<int, array<string, mixed>> $items          Checkout items.
	 * @param string                           $selection_mode Selection mode.
	 *
	 * @return bool
	 */
	public function has_selection(
		array $items,
		string $selection_mode = self::SINGLE
	): bool {

		return ! empty(
			$this->get_selected_items(
				$items,
				$selection_mode
			)
		);
	}

	/**
	 * Count selected product/variation rows.
	 *
	 * This counts selected rows, not total item quantity.
	 *
	 * Example:
	 * Black x2 + White x3 = 2 selected items.
	 *
	 * @param array<int, array<string, mixed>> $items          Checkout items.
	 * @param string                           $selection_mode Selection mode.
	 *
	 * @return int
	 */
	public function get_selected_count(
		array $items,
		string $selection_mode = self::SINGLE
	): int {

		return count(
			$this->get_selected_items(
				$items,
				$selection_mode
			)
		);
	}

	/**
	 * Get total selected quantity.
	 *
	 * Example:
	 * Black x2 + White x3 = quantity 5.
	 *
	 * @param array<int, array<string, mixed>> $items          Checkout items.
	 * @param string                           $selection_mode Selection mode.
	 *
	 * @return int
	 */
	public function get_total_quantity(
		array $items,
		string $selection_mode = self::SINGLE
	): int {

		$total = 0;

		foreach (
			$this->get_selected_items(
				$items,
				$selection_mode
			) as $item
		) {
			$total += $item['quantity'];
		}

		return $total;
	}

	/**
	 * Sanitize selection mode.
	 *
	 * @param string $selection_mode Selection mode.
	 *
	 * @return string
	 */
	public function sanitize_mode( string $selection_mode ): string {

		$selection_mode = sanitize_key( $selection_mode );

		if ( self::MULTIPLE === $selection_mode ) {
			return self::MULTIPLE;
		}

		return self::SINGLE;
	}

	/**
	 * Normalize a checkout item.
	 *
	 * @param array<string, mixed> $item Checkout item.
	 *
	 * @return array<string, int>|null
	 */
	private function normalize_item( array $item ): ?array {

		$product_id = isset( $item['product_id'] )
			? absint( $item['product_id'] )
			: 0;

		$variation_id = isset( $item['variation_id'] )
			? absint( $item['variation_id'] )
			: 0;

		$quantity = isset( $item['quantity'] )
			? absint( $item['quantity'] )
			: 0;

		if ( $product_id <= 0 ) {
			return null;
		}

		return array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => $quantity,
		);
	}

	/**
	 * Enforce single-selection mode.
	 *
	 * Only the first item with a positive quantity remains selected.
	 * All other items are reset to zero.
	 *
	 * @param array<int, array<string, int>> $items Normalized items.
	 *
	 * @return array<int, array<string, int>>
	 */
	private function enforce_single_selection( array $items ): array {

		$selection_found = false;

		foreach ( $items as $index => $item ) {

			if ( $item['quantity'] <= 0 ) {
				continue;
			}

			if ( ! $selection_found ) {
				$selection_found = true;
				continue;
			}

			$items[ $index ]['quantity'] = 0;
		}

		return $items;
	}

	/**
	 * Generate a unique key for a product or variation.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 *
	 * @return string
	 */
	private function get_item_key(
		int $product_id,
		int $variation_id
	): string {

		if ( $variation_id > 0 ) {
			return 'variation:' . $variation_id;
		}

		return 'product:' . $product_id;
	}

	/**
	 * Validate items returned by filters.
	 *
	 * @param mixed $items Filtered items.
	 *
	 * @return array<int, array<string, int>>
	 */
	private function validate_filtered_items( $items ): array {

		if ( ! is_array( $items ) ) {
			return array();
		}

		$validated = array();

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$item = $this->normalize_item( $item );

			if ( null === $item ) {
				continue;
			}

			$validated[] = $item;
		}

		return $validated;
	}
}