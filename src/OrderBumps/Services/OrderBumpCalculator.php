<?php
/**
 * Order Bump calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\OrderBumps\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates authoritative Order Bump pricing.
 *
 * Supported pricing types:
 *
 * - regular_price
 * - fixed_price
 * - percentage_discount
 * - fixed_discount
 *
 * Important:
 *
 * This calculator never loads frontend prices.
 * The regular total supplied to this class must
 * come from WooCommerce product data.
 */
final class OrderBumpCalculator {

	/**
	 * Calculate Order Bump pricing.
	 *
	 * @param array<string, mixed> $offer         Order Bump offer.
	 * @param float                $regular_total WooCommerce regular total.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		array $offer,
		float $regular_total
	): array {

		$regular_total =
			$this->normalize_amount(
				$regular_total
			);

		if ( $regular_total < 0 ) {
			$regular_total = 0.0;
		}

		$pricing_type =
			$this->normalize_pricing_type(
				$offer['pricing_type'] ??
					'regular_price'
			);

		$pricing_value =
			$this->normalize_amount(
				$offer['pricing_value'] ??
					0
			);

		$discount =
			0.0;

		$bump_total =
			$regular_total;

		switch ( $pricing_type ) {

			case 'fixed_price':

				$bump_total =
					min(
						$regular_total,
						$pricing_value
					);

				$discount =
					max(
						0,
						$regular_total -
							$bump_total
					);

				break;

			case 'percentage_discount':

				$percentage =
					min(
						100,
						max(
							0,
							$pricing_value
						)
					);

				$discount =
					$this->normalize_amount(
						$regular_total *
						(
							$percentage /
							100
						)
					);

				$bump_total =
					max(
						0,
						$regular_total -
							$discount
					);

				break;

			case 'fixed_discount':

				$discount =
					min(
						$regular_total,
						$pricing_value
					);

				$bump_total =
					max(
						0,
						$regular_total -
							$discount
					);

				break;

			case 'regular_price':
			default:

				$discount =
					0.0;

				$bump_total =
					$regular_total;

				break;
		}

		$discount =
			$this->normalize_amount(
				min(
					$regular_total,
					max(
						0,
						$discount
					)
				)
			);

		$bump_total =
			$this->normalize_amount(
				max(
					0,
					$bump_total
				)
			);

		return array(
			'valid' =>
				true,

			'pricing_type' =>
				$pricing_type,

			'pricing_value' =>
				$pricing_value,

			'regular_total' =>
				$regular_total,

			'discount' =>
				$discount,

			'bump_total' =>
				$bump_total,
		);
	}

	/**
	 * Normalize pricing type.
	 *
	 * @param mixed $value Pricing type.
	 *
	 * @return string
	 */
	private function normalize_pricing_type(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		$allowed =
			array(
				'regular_price',
				'fixed_price',
				'percentage_discount',
				'fixed_discount',
			);

		return in_array(
			$value,
			$allowed,
			true
		)
			? $value
			: 'regular_price';
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

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount =
				wc_format_decimal(
					$amount,
					$decimals
				);
		}

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}