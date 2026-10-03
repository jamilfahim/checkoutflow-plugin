<?php
/**
 * Unified Special Discount CONDITION -> REWARD engine.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Discounts\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side source of truth for automatic Special Discount rewards.
 *
 * Combo Offers remain a separate selectable bundle system. This engine only
 * reads Special Discount rules and returns rewards to the existing pricing,
 * gift and delivery adapters.
 */
final class SpecialDiscountEngine {

	/** @var SpecialDiscountEligibility */
	private $eligibility;

	public function __construct( ?SpecialDiscountEligibility $eligibility = null ) {
		$this->eligibility = $eligibility ?: new SpecialDiscountEligibility();
	}

	/**
	 * Evaluate every eligible rule once and aggregate its rewards.
	 *
	 * @param array<string,mixed> $context Trusted checkout/cart context.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $context = array() ): array {
		$product_total = $this->amount( $context['product_total'] ?? 0 );
		$result = array(
			'enabled'                         => false,
			'matched'                         => false,
			'product_total'                   => $product_total,
			'automatic_discount'              => 0.0,
			'discounted_product_total'        => $product_total,
			'free_delivery'                   => false,
			'free_delivery_method_id'         => '',
			'free_delivery_hide_other_methods'=> false,
			'free_gift_rule_ids'              => array(),
			'applied_rule_ids'                => array(),
			'applied_rule_types'              => array(),
			'applied_rules'                   => array(),
		);

		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return $result;
		}
		$result['enabled'] = true;

		$allocated_discount = 0.0;
		$gift_keys = array();
		foreach ( $rules as $rule ) {
			if ( ! $this->is_rule_allowed_for_checkout( $rule, $context ) || ! $this->is_rule_in_schedule( $rule ) ) {
				continue;
			}

			/*
			 * Conditions may evaluate against the whole purchase while monetary
			 * rewards still respect the plugin's existing discount-compatibility
			 * line set. This is what lets a discounted Combo qualify for Free
			 * Delivery without silently making that Combo eligible for a second
			 * monetary discount.
			 */
			$eligibility_context = $context;
			if ( isset( $context['condition_context'] ) && is_array( $context['condition_context'] ) ) {
				$eligibility_context = array_merge( $context, $context['condition_context'] );
			}

			$evaluation = $this->eligibility->evaluate( $rule, $eligibility_context );
			if ( empty( $evaluation['eligible'] ) ) {
				continue;
			}

			$reward_type = $this->reward_type( $rule );
			$discount_scope_context = $this->eligibility->scope_context( $rule, $context );
			$scope_total = $this->amount( $discount_scope_context['product_total'] ?? $product_total );
			$benefit = 0.0;
			$raw_benefit = 0.0;
			if ( 'percentage_discount' === $reward_type ) {
				$rate = min( 100, $this->amount( $rule['pricing_value'] ?? $rule['value'] ?? 0 ) );
				$raw_benefit = $scope_total * ( $rate / 100 );
				$max = $this->amount( $rule['maximum_discount'] ?? 0 );
				if ( $max > 0 ) {
					$raw_benefit = min( $raw_benefit, $max );
				}
				$benefit = min( $this->amount( $raw_benefit ), max( 0, $product_total - $allocated_discount ) );
			} elseif ( 'fixed_discount' === $reward_type ) {
				$raw_benefit = $this->amount( $rule['pricing_value'] ?? $rule['value'] ?? 0 );
				$benefit = min( $raw_benefit, $scope_total, max( 0, $product_total - $allocated_discount ) );
			}

			if ( $benefit > 0 ) {
				$allocated_discount = $this->amount( $allocated_discount + $benefit );
			}

			if ( 'free_delivery' === $reward_type ) {
				$result['free_delivery'] = true;
				$method_id = sanitize_key( (string) ( $rule['free_delivery_method_id'] ?? $rule['delivery_method_id'] ?? '' ) );
				/* First specific method wins by priority; all-method reward stays empty. */
				if ( '' === $result['free_delivery_method_id'] && '' !== $method_id ) {
					$result['free_delivery_method_id'] = $method_id;
				}
				$result['free_delivery_hide_other_methods'] = $result['free_delivery_hide_other_methods'] || 'yes' === (string) ( $rule['free_delivery_hide_other_methods'] ?? $rule['hide_other_methods'] ?? 'no' );
			}

			if ( 'free_gift' === $reward_type ) {
				$product_id = absint( $rule['product_id'] ?? 0 );
				$variation_id = absint( $rule['variation_id'] ?? 0 );
				$key = $product_id . ':' . $variation_id;
				if ( $product_id > 0 && ! isset( $gift_keys[ $key ] ) ) {
					$gift_keys[ $key ] = true;
					$result['free_gift_rule_ids'][] = sanitize_key( (string) ( $rule['id'] ?? '' ) );
				}
			}

			$rule_id = sanitize_key( (string) ( $rule['id'] ?? '' ) );
			$result['applied_rule_ids'][] = $rule_id;
			$result['applied_rule_types'][] = $reward_type;
			$result['applied_rules'][] = array(
				'id'                         => $rule_id,
				'title'                      => sanitize_text_field( (string) ( $rule['title'] ?? $rule['name'] ?? '' ) ),
				'rule_scope'                 => sanitize_key( (string) ( $evaluation['rule_scope'] ?? 'whole_cart' ) ),
				'scope_product_ids'          => isset( $evaluation['scope_product_ids'] ) && is_array( $evaluation['scope_product_ids'] ) ? array_values( array_map( 'absint', $evaluation['scope_product_ids'] ) ) : array(),
				'condition_type'             => sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) ),
				'condition_minimum'          => $this->amount( $rule['condition_minimum'] ?? $rule['minimum'] ?? 0 ),
				'condition_maximum'          => $this->amount( $rule['condition_maximum'] ?? $rule['maximum'] ?? 0 ),
				'condition_product_id'       => absint( $rule['condition_product_id'] ?? 0 ),
				'condition_product_quantity' => max( 1, absint( $rule['condition_product_quantity'] ?? 1 ) ),
				'condition_cart_quantity'    => max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) ),
				'reward_type'                => $reward_type,
				'reward_value'               => $this->amount( $rule['pricing_value'] ?? $rule['value'] ?? 0 ),
				'benefit_amount'              => $this->amount( $benefit ),
				'free_delivery'               => 'free_delivery' === $reward_type,
				'free_delivery_method_id'     => sanitize_key( (string) ( $rule['free_delivery_method_id'] ?? $rule['delivery_method_id'] ?? '' ) ),
				'gift_product_id'             => 'free_gift' === $reward_type ? absint( $rule['product_id'] ?? 0 ) : 0,
				'gift_variation_id'           => 'free_gift' === $reward_type ? absint( $rule['variation_id'] ?? 0 ) : 0,
				'gift_quantity'               => 'free_gift' === $reward_type ? max( 0, (float) ( $rule['quantity'] ?? 1 ) ) : 0,
			);
		}

		$result['automatic_discount'] = min( $allocated_discount, $product_total );
		$result['discounted_product_total'] = $this->amount( max( 0, $product_total - $result['automatic_discount'] ) );
		$result['applied_rule_ids'] = array_values( array_unique( array_filter( $result['applied_rule_ids'] ) ) );
		$result['applied_rule_types'] = array_values( array_unique( array_filter( $result['applied_rule_types'] ) ) );
		$result['free_gift_rule_ids'] = array_values( array_unique( array_filter( $result['free_gift_rule_ids'] ) ) );
		$result['matched'] = ! empty( $result['applied_rule_ids'] );

		return apply_filters( 'eilmo_cf/special_discounts/engine_result', $result, $context, $rules );
	}

	/**
	 * Return canonical Special Discount rules.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_rules(): array {
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$special = isset( $stored['order_bumps'] ) && is_array( $stored['order_bumps'] ) ? $stored['order_bumps'] : array();
		$rules = array();

		if ( 'yes' === ( $special['enabled'] ?? 'no' ) && isset( $special['offers'] ) && is_array( $special['offers'] ) ) {
			foreach ( $special['offers'] as $offer ) {
				if ( ! is_array( $offer ) || 'no' === ( $offer['enabled'] ?? 'yes' ) ) {
					continue;
				}
				$offer['id'] = sanitize_key( (string) ( $offer['id'] ?? '' ) );
				if ( '' === $offer['id'] ) {
					continue;
				}
				$offer['rule_scope'] = $this->normalize_scope( $offer['rule_scope'] ?? 'whole_cart' );
				$offer['scope_product_ids'] = isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $offer['scope_product_ids'] ) ) ) ) : array();
				$rules[] = $offer;
			}
		}

		/* Very old stores may still only have automatic_rules. Use them as fallback. */
		if ( empty( $rules ) ) {
			$legacy = isset( $stored['discounts']['automatic_rules'] ) && is_array( $stored['discounts']['automatic_rules'] ) ? $stored['discounts']['automatic_rules'] : array();
			foreach ( $legacy as $rule ) {
				if ( ! is_array( $rule ) || 'no' === ( $rule['enabled'] ?? 'yes' ) ) {
					continue;
				}
				$type = sanitize_key( (string) ( $rule['type'] ?? 'percentage' ) );
				$rule['offer_type'] = 'free_delivery' === $type ? 'free_delivery' : ( 'fixed' === $type ? 'fixed_discount' : 'percentage_discount' );
				$rule['title'] = sanitize_text_field( (string) ( $rule['name'] ?? '' ) );
				$rule['pricing_value'] = (float) ( $rule['value'] ?? 0 );
				$rule['condition_minimum'] = (float) ( $rule['minimum_amount'] ?? $rule['minimum'] ?? 0 );
				$rule['condition_maximum'] = (float) ( $rule['maximum_amount'] ?? $rule['maximum'] ?? 0 );
				$rule['rule_scope'] = 'whole_cart';
				$rule['_legacy_automatic_rule'] = true;
				$rules[] = $rule;
			}
		}


		usort(
			$rules,
			static function ( array $a, array $b ): int {
				return (int) ( $a['sort_order'] ?? $a['priority'] ?? 10 ) <=> (int) ( $b['sort_order'] ?? $b['priority'] ?? 10 );
			}
		);

		return apply_filters( 'eilmo_cf/special_discounts/rules', array_values( $rules ), $stored );
	}

	/** Is rule within start/end schedule? */
	private function is_rule_in_schedule( array $rule ): bool {
		$now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp', true ) : time();
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		foreach ( array( 'start_at' => 'start', 'end_at' => 'end' ) as $key => $kind ) {
			$value = trim( (string) ( $rule[ $key ] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			try {
				$date = new \DateTimeImmutable( $value, $timezone );
				$timestamp = $date->setTimezone( new \DateTimeZone( 'UTC' ) )->getTimestamp();
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( 'start' === $kind && $now < $timestamp ) {
				return false;
			}
			if ( 'end' === $kind && $now > $timestamp ) {
				return false;
			}
		}
		return true;
	}

	/** Respect signed per-checkout assignment for real Special Discount rules. */
	private function is_rule_allowed_for_checkout( array $rule, array $context ): bool {
		if ( ! array_key_exists( 'special_discount_scope', $context ) ) {
			return true;
		}
		$scope = sanitize_key( (string) $context['special_discount_scope'] );
		if ( 'none' === $scope ) {
			return false;
		}
		if ( 'all' === $scope ) {
			return true;
		}
		if ( 'selected' !== $scope ) {
			return false;
		}
		$ids = isset( $context['special_discount_ids'] ) && is_array( $context['special_discount_ids'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $context['special_discount_ids'] ) ) ) ) : array();
		return in_array( sanitize_key( (string) ( $rule['id'] ?? '' ) ), $ids, true );
	}

	/** @param array<string,mixed> $rule Rule. */
	private function reward_type( array $rule ): string {
		$type = sanitize_key( (string) ( $rule['offer_type'] ?? $rule['reward_type'] ?? '' ) );
		if ( in_array( $type, array( 'percentage_discount', 'fixed_discount', 'free_gift', 'free_delivery' ), true ) ) {
			return $type;
		}
		$legacy = sanitize_key( (string) ( $rule['type'] ?? '' ) );
		return 'fixed' === $legacy ? 'fixed_discount' : ( 'free_delivery' === $legacy ? 'free_delivery' : 'percentage_discount' );
	}

	/** @param mixed $scope Scope. */
	private function normalize_scope( $scope ): string {
		$scope = sanitize_key( (string) $scope );
		return in_array( $scope, array( 'current_product', 'selected_products', 'whole_cart' ), true ) ? $scope : 'whole_cart';
	}

	/** @param mixed $value Value. */
	private function amount( $value ): float {
		return is_numeric( $value ) ? max( 0, round( (float) $value, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) ) : 0.0;
	}
}
