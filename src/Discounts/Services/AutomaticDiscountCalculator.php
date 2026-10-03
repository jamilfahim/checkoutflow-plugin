<?php
/**
 * Automatic discount calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Discounts\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates automatic discounts.
 */
final class AutomaticDiscountCalculator {

	/**
	 * Calculate automatic discount.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'product_total' => 5000,
	 * )
	 *
	 * @param array<string, mixed> $context Calculation context.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		array $context = array()
	): array {
		$product_total = $this->normalize_amount( $context['product_total'] ?? 0 );
		$result = $this->get_default_result( $product_total );

		/*
		 * SpecialDiscountEngine is now the single CONDITION -> REWARD source of
		 * truth. This historical calculator remains as a compatibility adapter so
		 * existing totals/order code and public filters do not need to change.
		 */
		$engine = new SpecialDiscountEngine();
		$engine_result = $engine->evaluate( $context );
		if ( ! is_array( $engine_result ) ) {
			return $this->filter_result( $result, $context, $this->get_settings() );
		}

		$result['enabled'] = ! empty( $engine_result['enabled'] );
		$result['matched'] = ! empty( $engine_result['matched'] );
		$result['automatic_discount'] = $this->normalize_amount( $engine_result['automatic_discount'] ?? 0 );
		$result['raw_discount'] = $result['automatic_discount'];
		$result['discounted_product_total'] = $this->normalize_amount( $engine_result['discounted_product_total'] ?? max( 0, $product_total - $result['automatic_discount'] ) );
		$result['free_delivery'] = ! empty( $engine_result['free_delivery'] );
		$result['free_delivery_method_id'] = sanitize_key( (string) ( $engine_result['free_delivery_method_id'] ?? '' ) );
		$result['free_delivery_hide_other_methods'] = ! empty( $engine_result['free_delivery_hide_other_methods'] );
		$result['free_gift_rule_ids'] = isset( $engine_result['free_gift_rule_ids'] ) && is_array( $engine_result['free_gift_rule_ids'] ) ? array_values( $engine_result['free_gift_rule_ids'] ) : array();
		$result['applied_rule_ids'] = isset( $engine_result['applied_rule_ids'] ) && is_array( $engine_result['applied_rule_ids'] ) ? array_values( $engine_result['applied_rule_ids'] ) : array();
		$result['applied_rule_types'] = isset( $engine_result['applied_rule_types'] ) && is_array( $engine_result['applied_rule_types'] ) ? array_values( $engine_result['applied_rule_types'] ) : array();
		$result['applied_rules'] = isset( $engine_result['applied_rules'] ) && is_array( $engine_result['applied_rules'] ) ? array_values( $engine_result['applied_rules'] ) : array();

		if ( ! empty( $result['applied_rules'] ) && is_array( $result['applied_rules'][0] ) ) {
			$primary = $result['applied_rules'][0];
			$result['rule_id'] = sanitize_key( (string) ( $primary['id'] ?? '' ) );
			$result['rule_name'] = sanitize_text_field( (string) ( $primary['title'] ?? '' ) );
			$result['rule_type'] = sanitize_key( (string) ( $primary['reward_type'] ?? '' ) );
			$result['value'] = $this->normalize_amount( $primary['reward_value'] ?? 0 );
		}

		return $this->filter_result( $result, $context, $this->get_settings() );
	}

	/**
	 * Limit automatic rules to the Special Discounts assigned to this checkout.
	 *
	 * Context-free internal integrations retain the historical all-rules
	 * behavior. Checkout requests always supply a verified scope and allowlist.
	 *
	 * @param array<int, array<string, mixed>> $rules   Enabled rules.
	 * @param array<string, mixed>             $context Calculation context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_rules_by_checkout_assignment(
		array $rules,
		array $context
	): array {
		if ( ! array_key_exists( 'special_discount_scope', $context ) ) {
			return $rules;
		}

		$scope = sanitize_key( (string) $context['special_discount_scope'] );
		if ( 'none' === $scope ) {
			return array();
		}

		if ( 'all' === $scope ) {
			return $rules;
		}

		if ( 'selected' !== $scope ) {
			return array();
		}

		$raw_ids = isset( $context['special_discount_ids'] ) && is_array( $context['special_discount_ids'] )
			? $context['special_discount_ids']
			: array();
		$allowed_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $raw_ids )
				)
			)
		);

		if ( empty( $allowed_ids ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$rules,
				static function ( array $rule ) use ( $allowed_ids ): bool {
					return in_array(
						sanitize_key( (string) ( $rule['id'] ?? '' ) ),
						$allowed_ids,
						true
					);
				}
			)
		);
	}

	/**
	 * Get default result.
	 *
	 * @param float $product_total Product total.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_result(
		float $product_total
	): array {

		return array(
			'enabled'                  => false,
			'valid'                    => true,
			'matched'                  => false,
			'disabled_reason'          => '',
			'rule_id'                  => '',
			'rule_name'                => '',
			'rule_type'                => '',
			'product_total'            => $product_total,
			'minimum_amount'           => 0.0,
			'maximum_amount'           => 0.0,
			'value'                    => 0.0,
			'maximum_discount'         => 0.0,
			'raw_discount'             => 0.0,
			'automatic_discount'       => 0.0,
			'discounted_product_total' => $product_total,
			'free_delivery'            => false,
			'is_capped'                => false,
			'is_clamped'               => false,
			'priority'                 => 10,
			'stop_processing'          => false,
			'applied_rule_ids'          => array(),
			'applied_rule_types'        => array(),
			'applied_rules'             => array(),
		);
	}

	/**
	 * Find every independently satisfied Special Discount rule.
	 *
	 * The legacy calculator selected one tier. Unified Special Discounts are
	 * independent rewards, so Free Delivery and one or more monetary rewards
	 * can be active together. Final-tier continuation remains available when no
	 * regular amount range matches.
	 *
	 * @param array<int, array<string, mixed>> $rules   Rules.
	 * @param float                            $amount  Amount.
	 * @param array<string, mixed>              $context Product context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function find_matching_rules(
		array $rules,
		float $amount,
		array $context = array()
	): array {
		$matches = array();

		foreach ( $rules as $rule ) {
			if ( $this->rule_matches( $rule, $amount, $context ) ) {
				$matches[] = $rule;
			}
		}

		if ( ! empty( $matches ) ) {
			usort(
				$matches,
				function ( array $first, array $second ): int {
					$priority_compare = $this->get_priority( $first ) <=> $this->get_priority( $second );
					if ( 0 !== $priority_compare ) {
						return $priority_compare;
					}

					$minimum_compare = $this->get_rule_minimum( $second ) <=> $this->get_rule_minimum( $first );
					if ( 0 !== $minimum_compare ) {
						return $minimum_compare;
					}

					return (int) ( $first['_eilmo_index'] ?? 0 ) <=> (int) ( $second['_eilmo_index'] ?? 0 );
				}
			);

			return array_values( $matches );
		}

		$continued_rule = $this->find_matching_rule( $rules, $amount, $context );

		return null === $continued_rule ? array() : array( $continued_rule );
	}

	/**
	 * Apply all matched rules and aggregate their rewards.
	 *
	 * @param array<string, mixed>              $result        Base result.
	 * @param array<int, array<string, mixed>> $rules         Matched rules.
	 * @param float                             $product_total Product total.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_rules(
		array $result,
		array $rules,
		float $product_total
	): array {
		$discount = 0.0;
		$raw_discount = 0.0;
		$free_delivery = false;
		$applied_rule_ids = array();
		$applied_rule_types = array();
		$applied_rules = array();
		$primary_result = null;

		foreach ( $rules as $rule ) {
			$rule_result = $this->apply_rule(
				$this->get_default_result( $product_total ),
				$rule,
				$product_total
			);

			if ( null === $primary_result ) {
				$primary_result = $rule_result;
			}

			$rule_discount = $this->normalize_amount(
				$rule_result['automatic_discount'] ?? 0
			);
			$applied_rule_discount = $this->normalize_amount(
				min(
					$rule_discount,
					max( 0, $product_total - $discount )
				)
			);
			$discount = $this->normalize_amount( $discount + $applied_rule_discount );
			$raw_discount += (float) ( $rule_result['raw_discount'] ?? 0 );
			$free_delivery = $free_delivery || ! empty( $rule_result['free_delivery'] );
			$applied_rule_ids[] = sanitize_key( (string) ( $rule_result['rule_id'] ?? '' ) );
			$applied_rule_types[] = sanitize_key( (string) ( $rule_result['rule_type'] ?? '' ) );
			$applied_rules[] = $this->build_applied_rule_snapshot(
				$rule,
				$rule_result,
				$applied_rule_discount
			);
		}

		if ( is_array( $primary_result ) ) {
			$result = array_merge( $result, $primary_result );
		}

		$discount = $this->normalize_amount( min( $discount, $product_total ) );
		$result['matched'] = true;
		$result['raw_discount'] = $this->normalize_amount( $raw_discount );
		$result['automatic_discount'] = $discount;
		$result['discounted_product_total'] = $this->normalize_amount( max( 0, $product_total - $discount ) );
		$result['free_delivery'] = $free_delivery;
		$result['is_clamped'] = $discount < $this->normalize_amount( $raw_discount );
		$result['applied_rule_ids'] = array_values( array_filter( array_unique( $applied_rule_ids ) ) );
		$result['applied_rule_types'] = array_values( array_filter( array_unique( $applied_rule_types ) ) );
		$result['applied_rules'] = array_values( $applied_rules );

		return $result;
	}

	/**
	 * Build an immutable snapshot of one matched Special Discount rule.
	 *
	 * @param array<string, mixed> $rule             Configured rule.
	 * @param array<string, mixed> $rule_result      Calculated reward.
	 * @param float                $applied_discount Actual allocated discount.
	 *
	 * @return array<string, mixed>
	 */
	private function build_applied_rule_snapshot(
		array $rule,
		array $rule_result,
		float $applied_discount
	): array {
		return array(
			'id' => sanitize_key( (string) ( $rule_result['rule_id'] ?? $rule['id'] ?? '' ) ),
			'title' => sanitize_text_field( (string) ( $rule_result['rule_name'] ?? $rule['name'] ?? '' ) ),
			'condition_type' => sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) ),
			'condition_minimum' => $this->normalize_amount( $rule['minimum_amount'] ?? $rule['minimum'] ?? 0 ),
			'condition_maximum' => $this->normalize_amount( $rule['maximum_amount'] ?? $rule['maximum'] ?? 0 ),
			'condition_product_id' => absint( $rule['condition_product_id'] ?? 0 ),
			'condition_product_quantity' => max( 1, absint( $rule['condition_product_quantity'] ?? 1 ) ),
			'condition_cart_quantity' => max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) ),
			'reward_type' => sanitize_key( (string) ( $rule_result['rule_type'] ?? $rule['type'] ?? '' ) ),
			'reward_value' => $this->normalize_amount( $rule_result['value'] ?? $rule['value'] ?? 0 ),
			'benefit_amount' => $applied_discount,
			'free_delivery' => ! empty( $rule_result['free_delivery'] ),
		);
	}

	/**
	 * Get enabled Automatic Discount rules.
	 *
	 * Both the current canonical keys and legacy/admin
	 * field keys are supported:
	 *
	 * minimum_amount / maximum_amount
	 * minimum        / maximum
	 *
	 * Internally every rule is normalized to the
	 * canonical *_amount structure.
	 *
	 * @param array<string, mixed> $settings Discount settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_rules(
		array $settings
	): array {

		$rules =
			isset(
				$settings['automatic_rules']
			) &&
			is_array(
				$settings['automatic_rules']
			)
				? $settings['automatic_rules']
				: array();

		$enabled_rules =
			array();

		foreach ( $rules as $index => $rule ) {

			if ( ! is_array( $rule ) ) {
				continue;
			}

			if (
				! $this->is_enabled(
					$rule['enabled'] ??
						'yes'
				)
			) {
				continue;
			}

			if ( ! $this->is_rule_in_schedule( $rule ) ) {
				continue;
			}

			/*
			 * Normalize old and current key formats.
			 */
			$minimum =
				$this->normalize_amount(
					$rule['minimum_amount'] ??
					$rule['minimum'] ??
						0
				);

			$maximum =
				$this->normalize_amount(
					$rule['maximum_amount'] ??
					$rule['maximum'] ??
						0
				);

			/*
			 * Invalid upper range should never produce
			 * unpredictable matching.
			 *
			 * 0 remains the special "unlimited" value.
			 */
			if (
				$maximum > 0 &&
				$maximum < $minimum
			) {
				$maximum =
					$minimum;
			}

			$rule['minimum_amount'] =
				$minimum;

			$rule['maximum_amount'] =
				$maximum;

			$rule['maximum_discount'] =
				$this->normalize_amount(
					$rule['maximum_discount'] ??
						0
				);

			$rule['value'] =
				$this->normalize_amount(
					$rule['value'] ??
						0
				);

			$rule['priority'] =
				$this->get_priority(
					$rule
				);

			/*
			 * Keep original order for deterministic
			 * tie-breaking.
			 */
			$rule['_eilmo_index'] =
				(int) $index;

			$enabled_rules[] =
				$rule;
		}

		return $enabled_rules;
	}

	/**
	 * Check a rule schedule in the WordPress site timezone.
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return bool
	 */
	private function is_rule_in_schedule( array $rule ): bool {
		$timezone = function_exists( 'wp_timezone' )
			? wp_timezone()
			: new \DateTimeZone( 'UTC' );
		$now = time();

		foreach ( array( 'start_at', 'end_at' ) as $key ) {
			$value = sanitize_text_field( (string) ( $rule[ $key ] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			try {
				$timestamp = ( new \DateTimeImmutable( $value, $timezone ) )->getTimestamp();
			} catch ( \Exception $exception ) {
				continue;
			}

			if ( ( 'start_at' === $key && $now < $timestamp ) || ( 'end_at' === $key && $now >= $timestamp ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find matching Automatic Discount rule.
	 *
	 * Normal ranges:
	 *
	 * minimum <= amount < maximum
	 *
	 * Maximum 0 means unlimited.
	 *
	 * If no normal range matches and the amount reaches
	 * or exceeds the maximum of the highest configured
	 * tier, that final tier continues indefinitely.
	 *
	 * Gaps between lower tiers remain intentional.
	 *
	 * Example:
	 *
	 * 500 - 2000  => 7%
	 * 3000 - 5000 => 9%
	 *
	 * 1500  => 7%
	 * 2500  => no discount
	 * 4000  => 9%
	 * 5000  => 9%
	 * 10000 => 9%
	 *
	 * @param array<int, array<string, mixed>> $rules  Rules.
	 * @param float                            $amount Amount.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_matching_rule(
		array $rules,
		float $amount,
		array $context = array()
	): ?array {

		if ( empty( $rules ) ) {
			return null;
		}

		$matches =
			array();

		foreach ( $rules as $rule ) {

			if (
				$this->rule_matches(
					$rule,
					$amount,
					$context
				)
			) {
				$matches[] =
					$rule;
			}
		}

		/*
		 * Normal matching.
		 *
		 * Priority:
		 * Lower number wins.
		 *
		 * If Priority is equal, the more specific /
		 * higher Minimum Amount wins.
		 */
		if ( ! empty( $matches ) ) {

			usort(
				$matches,
				function (
					array $first,
					array $second
				): int {

					$first_priority =
						$this->get_priority(
							$first
						);

					$second_priority =
						$this->get_priority(
							$second
						);

					if (
						$first_priority !==
							$second_priority
					) {
						return (
							$first_priority <=>
							$second_priority
						);
					}

					$first_minimum =
						$this->get_rule_minimum(
							$first
						);

					$second_minimum =
						$this->get_rule_minimum(
							$second
						);

					if (
						$first_minimum !==
							$second_minimum
					) {
						return (
							$second_minimum <=>
							$first_minimum
						);
					}

					return (
						(int) (
							$first['_eilmo_index'] ??
								0
						)
						<=>
						(int) (
							$second['_eilmo_index'] ??
								0
						)
					);
				}
			);

			return $matches[0];
		}

		/*
		 * No standard range matched.
		 *
		 * Apply final-tier continuation.
		 */
		$amount_rules = array_values(
			array_filter(
				$rules,
				static function ( array $rule ): bool {
					$condition = sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) );
					return in_array( $condition, array( '', 'order_amount' ), true );
				}
			)
		);

		$final_rule =
			$this->get_final_rule(
				$amount_rules
			);

		if ( null === $final_rule ) {
			return null;
		}

		$minimum =
			$this->get_rule_minimum(
				$final_rule
			);

		$maximum =
			$this->get_rule_maximum(
				$final_rule
			);

		/*
		 * Open-ended final tier.
		 */
		if (
			$maximum <= 0 &&
			$amount >= $minimum
		) {
			return $final_rule;
		}

		/*
		 * Final tier maximum crossed.
		 *
		 * Keep the final discount tier active.
		 */
		if (
			$maximum > 0 &&
			$amount > $maximum
		) {
			return $final_rule;
		}

		return null;
	}

	/**
	 * Get final/highest Automatic Discount tier.
	 *
	 * Highest Minimum Amount is considered the
	 * final tier.
	 *
	 * @param array<int, array<string, mixed>> $rules Rules.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_final_rule(
		array $rules
	): ?array {

		if ( empty( $rules ) ) {
			return null;
		}

		$candidates =
			$rules;

		usort(
			$candidates,
			function (
				array $first,
				array $second
			): int {

				$first_minimum =
					$this->get_rule_minimum(
						$first
					);

				$second_minimum =
					$this->get_rule_minimum(
						$second
					);

				if (
					$first_minimum !==
						$second_minimum
				) {
					return (
						$second_minimum <=>
						$first_minimum
					);
				}

				$first_priority =
					$this->get_priority(
						$first
					);

				$second_priority =
					$this->get_priority(
						$second
					);

				if (
					$first_priority !==
						$second_priority
				) {
					return (
						$first_priority <=>
						$second_priority
					);
				}

				return (
					(int) (
						$first['_eilmo_index'] ??
							0
					)
					<=>
					(int) (
						$second['_eilmo_index'] ??
							0
					)
				);
			}
		);

		return $candidates[0] ??
			null;
	}

	/**
	 * Determine whether rule matches amount.
	 *
	 * Half-open range:
	 *
	 * minimum <= amount < maximum
	 *
	 * Maximum 0 means unlimited.
	 *
	 * @param array<string, mixed> $rule   Rule.
	 * @param float                $amount Amount.
	 *
	 * @return bool
	 */
	private function rule_matches(
		array $rule,
		float $amount,
		array $context = array()
	): bool {
		$condition_type = sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) );
		if ( ! in_array( $condition_type, array( '', 'order_amount' ), true ) ) {
			$context['product_total'] = $amount;
			$evaluation = ( new SpecialDiscountEligibility() )->evaluate( $rule, $context );
			return ! empty( $evaluation['eligible'] );
		}

		$minimum =
			$this->get_rule_minimum(
				$rule
			);

		$maximum =
			$this->get_rule_maximum(
				$rule
			);

		if ( $amount < $minimum ) {
			return false;
		}

		if (
			$maximum > 0 &&
			$amount > $maximum
		) {
			return false;
		}

		return true;
	}

	/**
	 * Apply matched Automatic Discount rule.
	 *
	 * Supported rule types:
	 *
	 * percentage
	 * fixed
	 * free_delivery
	 *
	 * @param array<string, mixed> $result        Current result.
	 * @param array<string, mixed> $rule          Rule.
	 * @param float                $product_total Product total.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_rule(
		array $result,
		array $rule,
		float $product_total
	): array {

		$type =
			sanitize_key(
				(string) (
					$rule['type'] ??
						'percentage'
				)
			);

		if (
			! in_array(
				$type,
				array(
					'percentage',
					'fixed',
					'free_delivery',
				),
				true
			)
		) {
			$type =
				'percentage';
		}

		$value =
			$this->normalize_amount(
				$rule['value'] ??
					0
			);

		$minimum =
			$this->get_rule_minimum(
				$rule
			);

		$maximum =
			$this->get_rule_maximum(
				$rule
			);

		$maximum_discount =
			$this->normalize_amount(
				$rule['maximum_discount'] ??
					0
			);

		$raw_discount =
			0.0;

		$discount =
			0.0;

		$is_capped =
			false;

		$is_clamped =
			false;

		$free_delivery =
			false;

		switch ( $type ) {

			case 'free_delivery':

				$free_delivery =
					true;

				break;

			case 'fixed':

				$raw_discount =
					$value;

				$discount =
					$value;

				break;

			case 'percentage':
			default:

				/*
				 * Percentage discount cannot exceed 100%.
				 */
				$value =
					min(
						100.0,
						$value
					);

				$raw_discount =
					$product_total *
					(
						$value /
						100
					);

				$discount =
					$raw_discount;

				break;
		}

		$raw_discount =
			$this->normalize_amount(
				$raw_discount
			);

		$discount =
			$this->normalize_amount(
				$discount
			);

		/*
		 * Maximum Discount cap.
		 */
		if (
			$maximum_discount > 0 &&
			$discount > $maximum_discount
		) {
			$discount =
				$maximum_discount;

			$is_capped =
				true;
		}

		/*
		 * Discount can never exceed Product Total.
		 */
		if ( $discount > $product_total ) {

			$discount =
				$product_total;

			$is_clamped =
				true;
		}

		$discount =
			$this->normalize_amount(
				$discount
			);

		$result['matched'] =
			true;

		$result['rule_id'] =
			sanitize_key(
				(string) (
					$rule['id'] ??
						''
				)
			);

		$result['rule_name'] =
			sanitize_text_field(
				(string) (
					$rule['name'] ??
					$rule['rule_name'] ??
						''
				)
			);

		$result['rule_type'] =
			$type;

		$result['minimum_amount'] =
			$minimum;

		$result['maximum_amount'] =
			$maximum;

		$result['value'] =
			$value;

		$result['maximum_discount'] =
			$maximum_discount;

		$result['raw_discount'] =
			$raw_discount;

		$result['automatic_discount'] =
			$discount;

		$result['discounted_product_total'] =
			$this->normalize_amount(
				max(
					0,
					$product_total -
						$discount
				)
			);

		$result['free_delivery'] =
			$free_delivery;

		$result['is_capped'] =
			$is_capped;

		$result['is_clamped'] =
			$is_clamped;

		$result['priority'] =
			$this->get_priority(
				$rule
			);

		$result['stop_processing'] =
			$this->is_enabled(
				$rule['stop_processing'] ??
					'no'
			);

		return $result;
	}

	/**
	 * Get normalized rule minimum.
	 *
	 * Supports:
	 *
	 * minimum_amount
	 * minimum
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return float
	 */
	private function get_rule_minimum(
		array $rule
	): float {

		return $this->normalize_amount(
			$rule['minimum_amount'] ??
			$rule['minimum'] ??
				0
		);
	}

	/**
	 * Get normalized rule maximum.
	 *
	 * Supports:
	 *
	 * maximum_amount
	 * maximum
	 *
	 * Zero means no upper limit.
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return float
	 */
	private function get_rule_maximum(
		array $rule
	): float {

		return $this->normalize_amount(
			$rule['maximum_amount'] ??
			$rule['maximum'] ??
				0
		);
	}

	/**
	 * Determine whether Full Payment Discount is enabled.
	 *
	 * Full Payment Discount owns the discount experience
	 * when enabled, so Automatic Discounts do not apply.
	 *
	 * @param array<string, mixed> $settings Discount settings.
	 *
	 * @return bool
	 */
	private function full_payment_discount_enabled(
		array $settings
	): bool {

		$full_payment =
			isset(
				$settings['full_payment']
			) &&
			is_array(
				$settings['full_payment']
			)
				? $settings['full_payment']
				: array();

		return (
			'yes' === (
				$full_payment['enabled'] ??
					'no'
			)
		);
	}

	/**
	 * Get rule priority.
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return int
	 */
	private function get_priority(
		array $rule
	): int {

		$priority =
			(int) (
				$rule['priority'] ??
					10
			);

		return max(
			0,
			$priority
		);
	}

	/**
	 * Load Discount settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset(
				$defaults['discounts']
			) &&
			is_array(
				$defaults['discounts']
			)
				? $defaults['discounts']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset(
				$stored['discounts']
			) &&
			is_array(
				$stored['discounts']
			)
				? $stored['discounts']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		/*
		 * Automatic Rules are an indexed collection.
		 *
		 * Preserve exactly as saved.
		 */
		$settings['automatic_rules'] =
			isset(
				$saved_settings['automatic_rules']
			) &&
			is_array(
				$saved_settings['automatic_rules']
			)
				? $saved_settings['automatic_rules']
				: (
					$default_settings['automatic_rules'] ??
						array()
				);

		/**
		 * Filters Automatic Discount settings.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/discounts/automatic/settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Filter calculation result.
	 *
	 * @param array<string, mixed> $result   Result.
	 * @param array<string, mixed> $context  Context.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, mixed>
	 */
	private function filter_result(
		array $result,
		array $context,
		array $settings
	): array {

		/**
		 * Filters Automatic Discount calculation.
		 *
		 * @param array<string, mixed> $result   Result.
		 * @param array<string, mixed> $context  Context.
		 * @param array<string, mixed> $settings Settings.
		 */
		$result =
			apply_filters(
				'eilmo_cf/discounts/automatic/calculation',
				$result,
				$context,
				$settings
			);

		return is_array( $result )
			? $result
			: array();
	}

	/**
	 * Normalize enabled/boolean-like value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private function is_enabled(
		$value
	): bool {

		return in_array(
			$value,
			array(
				true,
				1,
				'1',
				'yes',
				'true',
			),
			true
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

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$amount =
			max(
				0,
				(float) $amount
			);

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			$amount,
			$decimals
		);
	}
}
