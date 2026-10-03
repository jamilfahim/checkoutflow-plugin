<?php
/**
 * Variation badge service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and resolves custom presentation badges for exact variations.
 */
final class VariationBadgeService {

	public const ENABLED_META = '_eilmo_cf_badge_enabled';
	public const TEXT_META    = '_eilmo_cf_badge_text';
	public const TYPE_META    = '_eilmo_cf_badge_type';

	/**
	 * Get variation badge presentation data.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 *
	 * @return array{enabled:string,text:string,type:string}
	 */
	public function get_badge( \WC_Product_Variation $variation ): array {

		$badge = array(
			'enabled' => 'yes' === (string) $variation->get_meta( self::ENABLED_META, true ) ? 'yes' : 'no',
			'text'    => $this->sanitize_text( $variation->get_meta( self::TEXT_META, true ) ),
			'type'    => $this->sanitize_type( $variation->get_meta( self::TYPE_META, true ) ),
		);

		if ( '' === $badge['text'] ) {
			$badge['enabled'] = 'no';
		}

		/**
		 * Filters resolved custom variation badge data.
		 *
		 * @param array<string, string> $badge     Badge data.
		 * @param \WC_Product_Variation $variation Variation.
		 */
		$badge = apply_filters(
			'eilmo_cf/variation_badge',
			$badge,
			$variation
		);

		if ( ! is_array( $badge ) ) {
			$badge = array();
		}

		$text = $this->sanitize_text( $badge['text'] ?? '' );

		return array(
			'enabled' => 'yes' === ( $badge['enabled'] ?? 'no' ) && '' !== $text ? 'yes' : 'no',
			'text'    => $text,
			'type'    => $this->sanitize_type( $badge['type'] ?? 'primary' ),
		);
	}

	/**
	 * Save sanitized badge data on a variation object.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 * @param mixed                 $enabled   Enabled value.
	 * @param mixed                 $text      Badge text.
	 * @param mixed                 $type      Badge type.
	 *
	 * @return void
	 */
	public function save_badge(
		\WC_Product_Variation $variation,
		$enabled,
		$text,
		$type
	): void {

		$text = $this->sanitize_text( $text );

		$variation->update_meta_data(
			self::ENABLED_META,
			'yes' === $enabled && '' !== $text ? 'yes' : 'no'
		);
		$variation->update_meta_data( self::TEXT_META, $text );
		$variation->update_meta_data( self::TYPE_META, $this->sanitize_type( $type ) );
	}

	/**
	 * Sanitize badge type.
	 *
	 * @param mixed $type Type.
	 *
	 * @return string
	 */
	public function sanitize_type( $type ): string {

		$type = sanitize_key( (string) $type );

		return in_array( $type, array( 'primary', 'success', 'warning' ), true )
			? $type
			: 'primary';
	}

	/**
	 * Sanitize short badge text.
	 *
	 * @param mixed $text Text.
	 *
	 * @return string
	 */
	private function sanitize_text( $text ): string {

		$text = sanitize_text_field( (string) $text );

		return function_exists( 'mb_substr' )
			? mb_substr( $text, 0, 80 )
			: substr( $text, 0, 80 );
	}
}
