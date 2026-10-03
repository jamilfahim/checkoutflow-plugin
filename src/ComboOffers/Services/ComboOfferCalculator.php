<?php
/**
 * Combo offer calculator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ComboOffers\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates Combo Offer pricing.
 */
final class ComboOfferCalculator {

	/**
	 * Calculate Combo Offer pricing.
	 *
	 * Supported types:
	 *
	 * fixed_price
	 * percentage_discount
	 * fixed_discount
	 *
	 * @param array<string, mixed> $offer       Combo offer.
	 * @param float                $items_total Current regular total of combo items.
	 *
	 * @return array<string, mixed>
	 */
	public function calculate(
		array $offer,
		float $items_total
	): array {

		$items_total =
			$this->normalize_amount(
				$items_total
			);

		$pricing_type =
			sanitize_key(
				(string) (
					$offer[
						'pricing_type'
					] ??
						'fixed_price'
				)
			);

		$pricing_value =
			$this->normalize_amount(
				$offer[
					'pricing_value'
				] ??
					0
			);

		$result =
			array(
				'valid' =>
					false,

				'offer_id' =>
					sanitize_key(
						(string) (
							$offer[
								'id'
							] ??
								''
						)
					),

				'pricing_type' =>
					$pricing_type,

				'pricing_value' =>
					$pricing_value,

				'regular_total' =>
					$items_total,

				'discount' =>
					0.0,

				'combo_total' =>
					$items_total,

				'saving' =>
					0.0,
			);

		if (
			$items_total <= 0 ||
			$pricing_value <= 0
		) {
			return $this->filter_result(
				$result,
				$offer
			);
		}

		$combo_total =
			$items_total;

		$discount =
			0.0;

		switch (
			$pricing_type
		) {

			case 'fixed_price':

				/*
				 * A Combo Offer is a discount mechanism.
				 *
				 * A configured fixed combo price higher
				 * than the regular item total must not
				 * increase the customer price.
				 */
				$combo_total =
					min(
						$items_total,
						$pricing_value
					);

				$discount =
					max(
						0,
						$items_total -
							$combo_total
					);

				break;

			case 'percentage_discount':

				$percentage =
					min(
						100,
						$pricing_value
					);

				$discount =
					$this->normalize_amount(
						$items_total *
						(
							$percentage /
							100
						)
					);

				$discount =
					min(
						$discount,
						$items_total
					);

				$combo_total =
					$this->normalize_amount(
						max(
							0,
							$items_total -
								$discount
						)
					);

				break;

			case 'fixed_discount':

				$discount =
					min(
						$pricing_value,
						$items_total
					);

				$combo_total =
					$this->normalize_amount(
						max(
							0,
							$items_total -
								$discount
						)
					);

				break;

			default:

				return $this->filter_result(
					$result,
					$offer
				);
		}

		$discount =
			$this->normalize_amount(
				$discount
			);

		$combo_total =
			$this->normalize_amount(
				$combo_total
			);

		$result[
			'valid'
		] =
			true;

		$result[
			'discount'
		] =
			$discount;

		$result[
			'combo_total'
		] =
			$combo_total;

		$result[
			'saving'
		] =
			$discount;

		return $this->filter_result(
			$result,
			$offer
		);
	}

	/**
	 * Filter calculation result.
	 *
	 * @param array<string, mixed> $result Result.
	 * @param array<string, mixed> $offer  Offer.
	 *
	 * @return array<string, mixed>
	 */
	private function filter_result(
		array $result,
		array $offer
	): array {

		/**
		 * Filters Combo Offer calculation.
		 *
		 * Frontend values must never be treated as
		 * authoritative on order creation.
		 *
		 * @param array<string,mixed> $result Result.
		 * @param array<string,mixed> $offer  Offer.
		 */
		$result =
			apply_filters(
				'eilmo_cf/combo_offers/calculation',
				$result,
				$offer
			);

		return is_array(
			$result
		)
			? $result
			: array();
	}

	/**
	 * Normalize amount.
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

		if (
			! is_numeric(
				$amount
			)
		) {
			return 0.0;
		}

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}