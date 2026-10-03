<?php
/**
 * Combo offer repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ComboOffers\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Provides normalized access to the global
 * Combo Offer library.
 */
final class ComboOfferRepository {

	/**
	 * Settings section.
	 */
	private const SECTION =
		'combo_offers';

	/**
	 * Global offer collection key.
	 */
	private const OFFERS_KEY =
		'offers';

	/**
	 * Get all combo offers.
	 *
	 * @param bool $enabled_only Return enabled offers only.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all(
		bool $enabled_only = true
	): array {

		$section =
			$this->get_settings();

		if (
			'yes' !== (
				$section[
					'enabled'
				] ??
					'no'
			)
		) {
			return array();
		}

		$offers =
			isset(
				$section[
					self::OFFERS_KEY
				]
			) &&
			is_array(
				$section[
					self::OFFERS_KEY
				]
			)
				? $section[
					self::OFFERS_KEY
				]
				: array();

		$normalized =
			array();

		foreach (
			$offers as
				$offer
		) {
			if (
				! is_array(
					$offer
				)
			) {
				continue;
			}

			$offer =
				$this->normalize_offer(
					$offer
				);

			if (
				'' ===
					$offer['id']
			) {
				continue;
			}

			if (
				$enabled_only &&
				'yes' !==
					$offer['enabled']
			) {
				continue;
			}

			$normalized[] =
				$offer;
		}

		usort(
			$normalized,
			static function (
				array $first,
				array $second
			): int {

				return (
					(int) (
						$first[
							'sort_order'
						] ??
							0
					)
				) <=> (
					(int) (
						$second[
							'sort_order'
						] ??
							0
					)
				);
			}
		);

		/**
		 * Filters normalized global Combo Offer library.
		 *
		 * @param array<int, array<string,mixed>> $normalized   Offers.
		 * @param bool                            $enabled_only Enabled only.
		 */
		$normalized =
			apply_filters(
				'eilmo_cf/combo_offers/library',
				$normalized,
				$enabled_only
			);

		return is_array(
			$normalized
		)
			? $normalized
			: array();
	}

	/**
	 * Get one combo offer.
	 *
	 * @param string $offer_id     Offer ID.
	 * @param bool   $enabled_only Require enabled offer.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get(
		string $offer_id,
		bool $enabled_only = true
	): ?array {

		$offer_id =
			sanitize_key(
				$offer_id
			);

		if (
			'' ===
				$offer_id
		) {
			return null;
		}

		foreach (
			$this->get_all(
				$enabled_only
			) as
				$offer
		) {
			if (
				$offer_id ===
					(
						$offer[
							'id'
						] ??
							''
					)
			) {
				return $offer;
			}
		}

		return null;
	}

	/**
	 * Get selected offers.
	 *
	 * Requested ID order is preserved.
	 *
	 * @param array<int, mixed> $offer_ids    Offer IDs.
	 * @param bool              $enabled_only Require enabled offers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_many(
		array $offer_ids,
		bool $enabled_only = true
	): array {

		$offer_ids =
			$this->normalize_ids(
				$offer_ids
			);

		if (
			empty(
				$offer_ids
			)
		) {
			return array();
		}

		$library =
			$this->get_all(
				$enabled_only
			);

		$indexed =
			array();

		foreach (
			$library as
				$offer
		) {
			$id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if (
				'' ===
					$id
			) {
				continue;
			}

			$indexed[
				$id
			] =
				$offer;
		}

		$result =
			array();

		foreach (
			$offer_ids as
				$offer_id
		) {
			if (
				isset(
					$indexed[
						$offer_id
					]
				)
			) {
				$result[] =
					$indexed[
						$offer_id
					];
			}
		}

		return $result;
	}

	/**
	 * Determine whether an enabled combo exists.
	 *
	 * @param string $offer_id Offer ID.
	 *
	 * @return bool
	 */
	public function exists(
		string $offer_id
	): bool {

		return null !==
			$this->get(
				$offer_id,
				true
			);
	}

	/**
	 * Normalize combo offer.
	 *
	 * Expected structure:
	 *
	 * [
	 *     'id'           => 'combo_1',
	 *     'enabled'      => 'yes',
	 *     'title'        => 'T-Shirt + Cap',
	 *     'description'  => '',
	 *     'badge'        => 'Best Deal',
	 *     'pricing_type' => 'fixed_price',
	 *     'pricing_value'=> 1250,
	 *     'items'        => [
	 *         [
	 *             'product_id'   => 10,
	 *             'variation_id' => 0,
	 *             'quantity'     => 1,
	 *         ],
	 *     ],
	 *     'sort_order'   => 10,
	 * ]
	 *
	 * @param array<string, mixed> $offer Offer.
	 *
	 * @return array<string, mixed>
	 */
	public function normalize_offer(
		array $offer
	): array {

		$pricing_type =
			sanitize_key(
				(string) (
					$offer[
						'pricing_type'
					] ??
						'fixed_price'
				)
			);

		if (
			! in_array(
				$pricing_type,
				array(
					'fixed_price',
					'percentage_discount',
					'fixed_discount',
				),
				true
			)
		) {
			$pricing_type =
				'fixed_price';
		}

		$items =
			isset(
				$offer[
					'items'
				]
			) &&
			is_array(
				$offer[
					'items'
				]
			)
				? $offer[
					'items'
				]
				: array();

		$normalized_items =
			array();

		foreach (
			$items as
				$item
		) {
			if (
				! is_array(
					$item
				)
			) {
				continue;
			}

			$product_id =
				absint(
					$item[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				$this->normalize_quantity(
					$item[
						'quantity'
					] ??
						1
				);

			if (
				$product_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			$normalized_items[] =
				array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						$quantity,

					'image_source' =>
						in_array(
							sanitize_key( (string) ( $item['image_source'] ?? 'product' ) ),
							array( 'product', 'custom', 'hidden' ),
							true
						)
							? sanitize_key( (string) ( $item['image_source'] ?? 'product' ) )
							: 'product',

					'image_id' =>
						absint( $item['image_id'] ?? 0 ),
				);
		}

		return array(
			'id' =>
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				),

			'enabled' =>
				'yes' === (
					$offer[
						'enabled'
					] ??
						'no'
				)
					? 'yes'
					: 'no',

			'title' =>
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							''
					)
				),

			'description' =>
				wp_kses_post(
					(string) (
						$offer[
							'description'
						] ??
							''
					)
				),

			'badge' =>
				sanitize_text_field(
					(string) (
						$offer[
							'badge'
						] ??
							''
					)
				),

			'eyebrow' =>
				sanitize_text_field(
					(string) ( $offer['eyebrow'] ?? 'COMBO OFFER' )
				),

			'icon_type' =>
				in_array(
					sanitize_key( (string) ( $offer['icon_type'] ?? 'preset' ) ),
					array( 'preset', 'custom', 'none' ),
					true
				)
					? sanitize_key( (string) ( $offer['icon_type'] ?? 'preset' ) )
					: 'preset',

			'icon_preset' =>
				in_array(
					sanitize_key( (string) ( $offer['icon_preset'] ?? 'flame' ) ),
					array( 'flame', 'gift', 'star', 'bolt' ),
					true
				)
					? sanitize_key( (string) ( $offer['icon_preset'] ?? 'flame' ) )
					: 'flame',

			'icon_media_id' =>
				absint( $offer['icon_media_id'] ?? 0 ),

			'pricing_type' =>
				$pricing_type,

			'pricing_value' =>
				$this->normalize_amount(
					$offer[
						'pricing_value'
					] ??
						0
				),

			'items' =>
				$normalized_items,

			'sort_order' =>
				absint(
					$offer[
						'sort_order'
					] ??
						0
				),

			'apply_automatic_discount' =>
				'yes' === (string) ( $offer['apply_automatic_discount'] ?? 'no' )
					? 'yes'
					: 'no',

			'apply_coupon' =>
				'no' === (string) ( $offer['apply_coupon'] ?? 'yes' )
					? 'no'
					: 'yes',

			'apply_full_payment_discount' =>
				'no' === (string) ( $offer['apply_full_payment_discount'] ?? 'yes' )
					? 'no'
					: 'yes',
		);
	}

	/**
	 * Normalize combo IDs.
	 *
	 * @param array<int, mixed> $offer_ids IDs.
	 *
	 * @return array<int, string>
	 */
	public function normalize_ids(
		array $offer_ids
	): array {

		$result =
			array();

		foreach (
			$offer_ids as
				$offer_id
		) {
			$offer_id =
				sanitize_key(
					(string) $offer_id
				);

			if (
				'' ===
					$offer_id
			) {
				continue;
			}

			$result[] =
				$offer_id;
		}

		return array_values(
			array_unique(
				$result
			)
		);
	}

	/**
	 * Get combo settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$default_section =
			isset(
				$defaults[
					self::SECTION
				]
			) &&
			is_array(
				$defaults[
					self::SECTION
				]
			)
				? $defaults[
					self::SECTION
				]
				: array();

		$stored_section =
			isset(
				$stored[
					self::SECTION
				]
			) &&
			is_array(
				$stored[
					self::SECTION
				]
			)
				? $stored[
					self::SECTION
				]
				: array();

		$settings =
			array_replace_recursive(
				$default_section,
				$stored_section
			);

		/*
		 * Combo offers are an indexed collection.
		 * Stored values must replace defaults instead
		 * of recursively merging by index.
		 */
		if (
			array_key_exists(
				self::OFFERS_KEY,
				$stored_section
			) &&
			is_array(
				$stored_section[
					self::OFFERS_KEY
				]
			)
		) {
			$settings[
				self::OFFERS_KEY
			] =
				$stored_section[
					self::OFFERS_KEY
				];
		}

		return $settings;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
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
