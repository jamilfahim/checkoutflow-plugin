<?php
/**
 * Special Discount eligibility service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Discounts\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Shared CONDITION evaluator used by every Special Discount reward.
 *
 * The service is deliberately presentation-free. It accepts a trusted cart
 * context and returns only eligibility/scoped totals so PHP remains the source
 * of truth for rewards, delivery and order creation.
 */
final class SpecialDiscountEligibility {

	/**
	 * Evaluate one rule against its configured scope.
	 *
	 * Supported scope values:
	 * - current_product
	 * - selected_products
	 * - whole_cart
	 *
	 * Supported condition aliases intentionally include historical values so
	 * saved rules keep working after the unified engine migration.
	 *
	 * @param array<string, mixed> $rule    Rule.
	 * @param array<string, mixed> $context Trusted context.
	 *
	 * @return array<string, mixed>
	 */
	public function evaluate( array $rule, array $context ): array {
		$scope_context = $this->scope_context( $rule, $context );
		$type = sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) );
		$total = $this->amount( $scope_context['product_total'] ?? 0 );
		$cart_quantity = $this->quantity( $scope_context['cart_quantity'] ?? 0 );
		$product_quantities = isset( $scope_context['product_quantities'] ) && is_array( $scope_context['product_quantities'] )
			? $scope_context['product_quantities']
			: array();

		$result = array(
			'eligible'             => true,
			'condition_type'       => $type,
			'rule_scope'           => $this->normalize_scope( $rule['rule_scope'] ?? $rule['scope'] ?? 'whole_cart' ),
			'scope_product_ids'    => $scope_context['scope_product_ids'] ?? array(),
			'scope_total'          => $total,
			'scope_quantity'       => $cart_quantity,
			'remaining_amount'     => 0.0,
			'remaining_quantity'   => 0.0,
			'required_product_id'  => 0,
		);

		if ( in_array( $type, array( 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) ) {
			$minimum = $this->amount( $rule['condition_minimum'] ?? $rule['minimum'] ?? $rule['condition_value'] ?? 0 );
			$maximum = $this->amount( $rule['condition_maximum'] ?? $rule['maximum'] ?? 0 );
			$result['eligible'] = $total >= $minimum && ( $maximum <= 0 || $total <= $maximum );
			$result['remaining_amount'] = max( 0, $minimum - $total );
		} elseif ( in_array( $type, array( 'specific_product', 'product_exists' ), true ) ) {
			$product_id = absint( $rule['condition_product_id'] ?? 0 );
			$required = max( 1, $this->quantity( $rule['condition_product_quantity'] ?? $rule['condition_value'] ?? 1 ) );
			$current = $this->quantity( $product_quantities[ $product_id ] ?? 0 );
			$result['eligible'] = $product_id > 0 && $current >= $required;
			$result['remaining_quantity'] = max( 0, $required - $current );
			$result['required_product_id'] = $product_id;
		} elseif ( in_array( $type, array( 'cart_quantity', 'minimum_quantity', 'buy_x_quantity' ), true ) ) {
			$required = max( 1, $this->quantity( $rule['condition_cart_quantity'] ?? $rule['condition_value'] ?? 1 ) );
			$result['eligible'] = $cart_quantity >= $required;
			$result['remaining_quantity'] = max( 0, $required - $cart_quantity );
		} elseif ( 'current_product' === $type ) {
			$result['eligible'] = ! empty( $scope_context['scope_product_ids'] ) && $cart_quantity > 0;
		}

		/* A selected/current scope with no applicable product never qualifies. */
		if (
			in_array( $result['rule_scope'], array( 'current_product', 'selected_products' ), true ) &&
			( empty( $scope_context['scope_product_ids'] ) || $cart_quantity <= 0 )
		) {
			$result['eligible'] = false;
		}

		return $result;
	}

	/**
	 * Build the portion of cart context visible to one rule's scope.
	 *
	 * @param array<string, mixed> $rule    Rule.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public function scope_context( array $rule, array $context ): array {
		$scope = $this->normalize_scope( $rule['rule_scope'] ?? $rule['scope'] ?? 'whole_cart' );
		$product_quantities = isset( $context['product_quantities'] ) && is_array( $context['product_quantities'] )
			? $context['product_quantities']
			: array();
		$product_totals = isset( $context['product_totals'] ) && is_array( $context['product_totals'] )
			? $context['product_totals']
			: array();

		if ( 'whole_cart' === $scope ) {
			return array(
				'product_total'      => $this->amount( $context['product_total'] ?? 0 ),
				'cart_quantity'      => $this->quantity( $context['cart_quantity'] ?? 0 ),
				'product_quantities' => $product_quantities,
				'product_totals'     => $product_totals,
				'scope_product_ids'  => isset( $context['product_ids'] ) && is_array( $context['product_ids'] ) ? $context['product_ids'] : array_keys( $product_quantities ),
			);
		}

		if ( 'current_product' === $scope ) {
			$ids = isset( $context['current_checkout_product_ids'] ) && is_array( $context['current_checkout_product_ids'] )
				? $this->normalize_ids( $context['current_checkout_product_ids'] )
				: array();
		} else {
			$ids = isset( $rule['scope_product_ids'] ) && is_array( $rule['scope_product_ids'] )
				? $this->normalize_ids( $rule['scope_product_ids'] )
				: array();

			/* Legacy single-product condition doubles as selected-product scope. */
			if ( empty( $ids ) && ! empty( $rule['condition_product_id'] ) ) {
				$ids = array( absint( $rule['condition_product_id'] ) );
			}
		}

		$scoped_quantities = array();
		$scoped_totals = array();
		$total = 0.0;
		$quantity = 0.0;
		foreach ( $ids as $id ) {
			$current_quantity = $this->quantity( $product_quantities[ $id ] ?? 0 );
			$current_total = $this->amount( $product_totals[ $id ] ?? 0 );
			if ( $current_quantity > 0 ) {
				$scoped_quantities[ $id ] = $current_quantity;
				$quantity += $current_quantity;
			}
			if ( $current_total > 0 ) {
				$scoped_totals[ $id ] = $current_total;
				$total += $current_total;
			}
		}

		return array(
			'product_total'      => $this->amount( $total ),
			'cart_quantity'      => $this->quantity( $quantity ),
			'product_quantities' => $scoped_quantities,
			'product_totals'     => $scoped_totals,
			'scope_product_ids'  => $ids,
		);
	}

	/**
	 * Build a trusted context from validated checkout and Combo items.
	 *
	 * @param array<int, array<string, mixed>> $items       Normal products.
	 * @param array<string, mixed>             $combo_data Validated Combo result.
	 * @return array<string, mixed>
	 */
	public function build_context( array $items, array $combo_data = array() ): array {
		$total = 0.0;
		$cart_quantity = 0.0;
		$product_quantities = array();
		$product_totals = array();
		$product_ids = array();

		$all_items = $items;
		if ( isset( $combo_data['items'] ) && is_array( $combo_data['items'] ) ) {
			$all_items = array_merge( $all_items, $combo_data['items'] );
		}

		foreach ( $all_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product_id = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			$quantity = $this->quantity( $item['quantity'] ?? 0 );
			if ( $product_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			$product_ids[] = $product_id;
			$product_quantities[ $product_id ] = $this->quantity( $product_quantities[ $product_id ] ?? 0 ) + $quantity;
			if ( $variation_id > 0 ) {
				$product_ids[] = $variation_id;
				$product_quantities[ $variation_id ] = $this->quantity( $product_quantities[ $variation_id ] ?? 0 ) + $quantity;
			}
			$cart_quantity += $quantity;

			$line_total = 0.0;
			if ( isset( $item['line_total'] ) && is_numeric( $item['line_total'] ) ) {
				$line_total = $this->amount( $item['line_total'] );
			} elseif ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
				if ( $product ) {
					$line_total = $this->amount( $product->get_price() ) * $quantity;
				}
			}
			$total += $line_total;
			$product_totals[ $product_id ] = $this->amount( $product_totals[ $product_id ] ?? 0 ) + $line_total;
			if ( $variation_id > 0 ) {
				$product_totals[ $variation_id ] = $this->amount( $product_totals[ $variation_id ] ?? 0 ) + $line_total;
			}
		}

		/* Use the authoritative payable Combo total instead of component prices. */
		if ( isset( $combo_data['items'] ) && is_array( $combo_data['items'] ) ) {
			$combo_components_total = 0.0;
			foreach ( $combo_data['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( isset( $item['line_total'] ) && is_numeric( $item['line_total'] ) ) {
					$combo_components_total += $this->amount( $item['line_total'] );
				} elseif ( function_exists( 'wc_get_product' ) ) {
					$product_id = absint( $item['product_id'] ?? 0 );
					$variation_id = absint( $item['variation_id'] ?? 0 );
					$quantity = $this->quantity( $item['quantity'] ?? 0 );
					$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
					if ( $product ) {
						$combo_components_total += $this->amount( $product->get_price() ) * $quantity;
					}
				}
			}
			$total = max( 0, $total - $combo_components_total ) + $this->amount( $combo_data['combo_total'] ?? 0 );
		}

		return array(
			'product_total'       => $this->amount( $total ),
			'cart_quantity'       => $this->quantity( $cart_quantity ),
			'product_quantities'  => $product_quantities,
			'product_totals'      => $product_totals,
			'product_ids'         => array_values( array_unique( array_filter( $product_ids ) ) ),
		);
	}

	/** @param mixed $scope Scope. */
	private function normalize_scope( $scope ): string {
		$scope = sanitize_key( (string) $scope );
		return in_array( $scope, array( 'current_product', 'selected_products', 'whole_cart' ), true ) ? $scope : 'whole_cart';
	}

	/** @param array<mixed> $ids IDs. @return array<int,int> */
	private function normalize_ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/** @param mixed $value Value. */
	private function amount( $value ): float {
		return is_numeric( $value ) ? max( 0, round( (float) $value, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) ) : 0.0;
	}

	/** @param mixed $value Value. */
	private function quantity( $value ): float {
		return is_numeric( $value ) ? max( 0, (float) $value ) : 0.0;
	}
}
