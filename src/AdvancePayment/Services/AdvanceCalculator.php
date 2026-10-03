<?php
/**
 * Advance payment calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AdvancePayment\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates advance-payment requirements.
 */
final class AdvanceCalculator {

	/**
	 * Calculate advance payment.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'product_total'            => 2000,
	 *     'discounted_product_total' => 1800,
	 *     'delivery_charge'          => 100,
	 *     'grand_total'              => 1900,
	 *     'payment_type'             => 'advance',
	 * )
	 *
	 * @param array<string, mixed> $context Calculation context.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		array $context = array()
	): array {

		$settings =
			$this->get_settings();

		$grand_total =
			$this->normalize_amount(
				$context['grand_total'] ??
					0
			);

		$result =
			$this->get_default_result(
				$grand_total
			);

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return $result;
		}

		$basis =
			$this->get_basis_amount(
				$settings,
				$context
			);

		$payment_type =
			$this->get_payment_type(
				$settings,
				$context
			);

		$result['enabled'] =
			true;

		$result['payment_type'] =
			$payment_type;

		$result['calculation_basis'] =
			(string) (
				$settings['calculation_basis'] ??
					'grand_total'
			);

		$result['basis_amount'] =
			$basis;

		/*
		 * Full payment explicitly selected.
		 */
		if ( 'full' === $payment_type ) {

			$result['pay_now'] =
				$grand_total;

			$result['remaining_due'] =
				0.0;

			$result['is_full_payment'] =
				true;

			return $this->filter_result(
				$result,
				$context,
				$settings
			);
		}

		$rules =
			$this->get_rules(
				$settings
			);

		/*
		 * Resolve one applicable Advance Payment rule.
		 *
		 * Normal ranges:
		 *
		 * minimum <= amount < maximum
		 *
		 * Important:
		 * If the amount exceeds the maximum of the
		 * highest configured tier, that final tier
		 * continues indefinitely.
		 *
		 * Gaps between lower tiers remain intentional.
		 */
		$matched_rule =
			$this->find_matching_rule(
				$rules,
				$basis
			);

		if ( null !== $matched_rule ) {

			$result =
				$this->apply_rule(
					$result,
					$matched_rule,
					$basis,
					$grand_total
				);
		}

		/*
		 * No rule matched.
		 *
		 * This includes intentional gaps between
		 * configured ranges.
		 *
		 * Full payment becomes the safe fallback so
		 * the customer can never submit an undefined
		 * zero-payment order.
		 */
		if ( empty( $result['matched'] ) ) {

			$result['rule_id'] =
				'';

			$result['rule_type'] =
				'fallback_full_payment';

			$result['advance_amount'] =
				0.0;

			$result['pay_now'] =
				$grand_total;

			$result['remaining_due'] =
				0.0;

			$result['is_full_payment'] =
				true;

			$result['is_advance_payment'] =
				false;
		}

		return $this->filter_result(
			$result,
			$context,
			$settings
		);
	}

	/**
	 * Get default calculator result.
	 *
	 * @param float $grand_total Grand total.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_result(
		float $grand_total
	): array {

		return array(
			'enabled'            => false,
			'valid'              => true,
			'matched'            => false,
			'rule_id'            => '',
			'rule_type'          => '',
			'payment_type'       => 'full',
			'calculation_basis'   => 'grand_total',
			'basis_amount'        => $grand_total,
			'advance_amount'      => 0.0,
			'pay_now'             => $grand_total,
			'remaining_due'       => 0.0,
			'is_full_payment'     => true,
			'is_advance_payment'  => false,
			'minimum_pay_amount'  => 0.0,
			'maximum_pay_amount'  => 0.0,
			'message'             => '',
		);
	}

	/**
	 * Resolve selected payment type.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $context  Context.
	 *
	 * @return string
	 */
	private function get_payment_type(
		array $settings,
		array $context
	): string {

		$allow_full_payment =
			'yes' === (
				$settings['allow_full_payment'] ??
					'yes'
			);

		$requested =
			sanitize_key(
				(string) (
					$context['payment_type'] ??
						$settings['default_payment_type'] ??
						'advance'
				)
			);

		if (
			'full' === $requested &&
			$allow_full_payment
		) {
			return 'full';
		}

		return 'advance';
	}

	/**
	 * Resolve calculation basis.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $context  Context.
	 *
	 * @return float
	 */
	private function get_basis_amount(
		array $settings,
		array $context
	): float {

		$basis =
			sanitize_key(
				(string) (
					$settings['calculation_basis'] ??
						'grand_total'
				)
			);

		switch ( $basis ) {

			case 'product_total':
				$amount =
					$context['product_total'] ??
						0;
				break;

			case 'discounted_product_total':
				$amount =
					$context['discounted_product_total'] ??
						$context['product_total'] ??
						0;
				break;

			case 'grand_total':
			default:
				$amount =
					$context['grand_total'] ??
						0;
				break;
		}

		return $this->normalize_amount(
			$amount
		);
	}

	/**
	 * Get enabled Advance Payment rules.
	 *
	 * Rules are sorted by Priority ascending.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_rules(
		array $settings
	): array {

		$rules =
			isset(
				$settings['rules']
			) &&
			is_array(
				$settings['rules']
			)
				? $settings['rules']
				: array();

		$rules =
			array_values(
				array_filter(
					$rules,
					function (
						$rule
					): bool {

						return (
							is_array( $rule ) &&
							$this->is_enabled(
								$rule['enabled'] ??
									'yes'
							)
						);
					}
				)
			);

		usort(
			$rules,
			static function (
				array $first,
				array $second
			): int {

				$first_priority =
					(int) (
						$first['priority'] ??
							10
					);

				$second_priority =
					(int) (
						$second['priority'] ??
							10
					);

				return (
					$first_priority <=>
					$second_priority
				);
			}
		);

		return $rules;
	}

	/**
	 * Find matching Advance Payment rule.
	 *
	 * Standard range logic:
	 *
	 * minimum <= amount < maximum
	 *
	 * Maximum 0 means unlimited.
	 *
	 * If no normal range matches and the amount reaches
	 * or exceeds the maximum of the highest configured
	 * tier, the highest tier continues indefinitely.
	 *
	 * Example:
	 *
	 * 500 - 2000  => 20%
	 * 3000 - 5000 => 10%
	 *
	 * 1500  => 20%
	 * 2500  => no match
	 * 4000  => 10%
	 * 5000  => 10%
	 * 10000 => 10%
	 *
	 * @param array<int, array<string, mixed>> $rules  Rules.
	 * @param float                            $amount Amount.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_matching_rule(
		array $rules,
		float $amount
	): ?array {

		if ( empty( $rules ) ) {
			return null;
		}

		$matched_rule =
			null;

		/*
		 * Preserve existing Priority and Stop Processing
		 * behavior for normal range matches.
		 *
		 * If multiple overlapping rules match:
		 * - Rules are already sorted by Priority.
		 * - The latest matching rule becomes active.
		 * - Stop Processing immediately locks the match.
		 */
		foreach ( $rules as $rule ) {

			if (
				! $this->rule_matches(
					$rule,
					$amount
				)
			) {
				continue;
			}

			$matched_rule =
				$rule;

			if (
				$this->is_enabled(
					$rule['stop_processing'] ??
						'no'
				)
			) {
				break;
			}
		}

		/*
		 * Normal range matched.
		 */
		if ( null !== $matched_rule ) {
			return $matched_rule;
		}

		/*
		 * No normal range matched.
		 *
		 * Find the final/highest tier by the greatest
		 * Minimum Amount.
		 */
		$final_rule =
			$this->get_final_rule(
				$rules
			);

		if ( null === $final_rule ) {
			return null;
		}

		$minimum =
			$this->normalize_amount(
				$final_rule['minimum_amount'] ??
					0
			);

		$maximum =
			$this->normalize_amount(
				$final_rule['maximum_amount'] ??
					0
			);

		/*
		 * Open-ended final tier.
		 *
		 * rule_matches() normally catches this already,
		 * but retaining the explicit condition keeps the
		 * rule semantics clear.
		 */
		if (
			$maximum <= 0 &&
			$amount >= $minimum
		) {
			return $final_rule;
		}

		/*
		 * Final-tier continuation.
		 *
		 * Once customer reaches or exceeds the maximum
		 * of the highest tier, keep applying that tier.
		 */
		if (
			$maximum > 0 &&
			$amount >= $maximum
		) {
			return $final_rule;
		}

		return null;
	}

	/**
	 * Get highest configured Advance Payment tier.
	 *
	 * The rule having the largest Minimum Amount is
	 * considered the final tier.
	 *
	 * If multiple rules have the same Minimum Amount,
	 * the lower Priority number wins.
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
					$this->normalize_amount(
						$first['minimum_amount'] ??
							0
					);

				$second_minimum =
					$this->normalize_amount(
						$second['minimum_amount'] ??
							0
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
					(int) (
						$first['priority'] ??
							10
					);

				$second_priority =
					(int) (
						$second['priority'] ??
							10
					);

				return (
					$first_priority <=>
					$second_priority
				);
			}
		);

		return $candidates[0] ??
			null;
	}

	/**
	 * Determine whether rule matches amount.
	 *
	 * Range rules use:
	 *
	 * minimum <= amount
	 * amount < maximum
	 *
	 * Maximum 0 means unlimited.
	 *
	 * This prevents overlapping boundaries such as:
	 *
	 * 1000 - 2000
	 * 2000 - 3000
	 *
	 * An amount of exactly 2000 matches the second rule.
	 *
	 * The final-tier open-ended continuation is handled
	 * separately by find_matching_rule().
	 *
	 * @param array<string, mixed> $rule   Rule.
	 * @param float                $amount Amount.
	 *
	 * @return bool
	 */
	private function rule_matches(
		array $rule,
		float $amount
	): bool {

		$minimum =
			$this->normalize_amount(
				$rule['minimum_amount'] ??
					0
			);

		$maximum =
			$this->normalize_amount(
				$rule['maximum_amount'] ??
					0
			);

		if ( $amount < $minimum ) {
			return false;
		}

		if (
			$maximum > 0 &&
			$amount >= $maximum
		) {
			return false;
		}

		return true;
	}

	/**
	 * Apply matched Advance Payment rule.
	 *
	 * @param array<string, mixed> $result      Current result.
	 * @param array<string, mixed> $rule        Rule.
	 * @param float                $basis       Basis amount.
	 * @param float                $grand_total Grand total.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_rule(
		array $result,
		array $rule,
		float $basis,
		float $grand_total
	): array {

		$type =
			sanitize_key(
				(string) (
					$rule['type'] ??
						'fixed'
				)
			);

		$value =
			$this->normalize_amount(
				$rule['value'] ??
					0
			);

		$minimum_pay =
			$this->normalize_amount(
				$rule['minimum_pay_amount'] ??
					0
			);

		$maximum_pay =
			$this->normalize_amount(
				$rule['maximum_pay_amount'] ??
					0
			);

		$advance =
			0.0;

		switch ( $type ) {

			case 'percentage':

				/*
				 * Percentage Advance cannot exceed 100%.
				 */
				$value =
					min(
						100.0,
						$value
					);

				$advance =
					$basis *
					(
						$value /
						100
					);
				break;

			case 'full_payment':

				$advance =
					$grand_total;
				break;

			case 'no_advance':

				$advance =
					0.0;
				break;

			case 'fixed':
			default:

				$advance =
					$value;
				break;
		}

		$advance =
			$this->normalize_amount(
				$advance
			);

		/*
		 * Minimum / Maximum Pay Amount apply only to
		 * normal fixed and percentage Advance rules.
		 *
		 * They must not alter explicit Full Payment or
		 * No Advance behavior.
		 */
		if (
			in_array(
				$type,
				array(
					'fixed',
					'percentage',
				),
				true
			)
		) {

			if (
				$minimum_pay > 0 &&
				$advance < $minimum_pay
			) {
				$advance =
					$minimum_pay;
			}

			if (
				$maximum_pay > 0 &&
				$advance > $maximum_pay
			) {
				$advance =
					$maximum_pay;
			}
		}

		$advance =
			min(
				$advance,
				$grand_total
			);

		$advance =
			$this->normalize_amount(
				$advance
			);

		$is_full_payment =
			'full_payment' === $type ||
			(
				$grand_total > 0 &&
				$advance >=
					$grand_total
			);

		if ( 'no_advance' === $type ) {

			/*
			 * No Advance means no upfront partial payment.
			 */
			$pay_now =
				0.0;

			$remaining_due =
				$grand_total;

		} else {

			$pay_now =
				$is_full_payment
					? $grand_total
					: $advance;

			$remaining_due =
				max(
					0,
					$grand_total -
						$pay_now
				);
		}

		$result['matched'] =
			true;

		$result['rule_id'] =
			sanitize_key(
				(string) (
					$rule['id'] ??
						''
				)
			);

		$result['rule_type'] =
			$type;

		$result['advance_amount'] =
			$advance;

		$result['pay_now'] =
			$this->normalize_amount(
				$pay_now
			);

		$result['remaining_due'] =
			$this->normalize_amount(
				$remaining_due
			);

		$result['minimum_pay_amount'] =
			$minimum_pay;

		$result['maximum_pay_amount'] =
			$maximum_pay;

		$result['is_full_payment'] =
			$is_full_payment;

		$result['is_advance_payment'] =
			! $is_full_payment &&
			'no_advance' !== $type;

		return $result;
	}

	/**
	 * Load Advance Payment settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset(
				$defaults['advance_payment']
			) &&
			is_array(
				$defaults['advance_payment']
			)
				? $defaults['advance_payment']
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
				$stored['advance_payment']
			) &&
			is_array(
				$stored['advance_payment']
			)
				? $stored['advance_payment']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		/*
		 * Rules are indexed collections and must remain
		 * exactly as configured by the administrator.
		 */
		$settings['rules'] =
			isset(
				$saved_settings['rules']
			) &&
			is_array(
				$saved_settings['rules']
			)
				? $saved_settings['rules']
				: (
					$default_settings['rules'] ??
						array()
				);

		/**
		 * Filters Advance Payment settings.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/advance_payment/settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Filter calculated result.
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
		 * Filters Advance Payment calculation.
		 *
		 * @param array<string, mixed> $result   Result.
		 * @param array<string, mixed> $context  Context.
		 * @param array<string, mixed> $settings Settings.
		 */
		$result =
			apply_filters(
				'eilmo_cf/advance_payment/calculation',
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