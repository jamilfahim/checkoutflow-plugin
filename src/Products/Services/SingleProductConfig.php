<?php
/**
 * Single Product configuration.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

use EilmoCheckout\Admin\ProductSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves global Single Product defaults with safe page-level overrides.
 */
final class SingleProductConfig {

	/**
	 * Get resolved Single Product configuration.
	 *
	 * Only known keys may be overridden by shortcode or Elementor settings.
	 *
	 * @param array<string, mixed> $overrides Page-level overrides.
	 *
	 * @return array<string, mixed>
	 */
	public function get( array $overrides = array() ): array {

		$all_defaults = ProductSettings::get_defaults();
		$defaults = isset( $all_defaults['single_product'] ) &&
			is_array( $all_defaults['single_product'] )
				? $all_defaults['single_product']
				: array();

		if ( empty( $defaults ) ) {
			return array();
		}
		$global   = ProductSettings::get_single_product();
		$allowed  = array_intersect_key(
			$overrides,
			$defaults
		);
		$attribute_style_resolver = new AttributeStyleResolver();
		$attribute_styles = $attribute_style_resolver->merge(
			$global['attribute_styles'] ?? array(),
			$allowed['attribute_styles'] ?? array()
		);

		unset( $allowed['attribute_styles'] );

		$settings = array_merge(
			$global,
			$allowed
		);
		$settings['attribute_styles'] = $attribute_styles;

		$sanitizer = new ProductSettings();
		$settings  = $sanitizer->sanitize_single_product(
			$settings,
			$defaults
		);

		/**
		 * Filters resolved Single Product presentation configuration.
		 *
		 * @param array<string, mixed> $settings  Resolved settings.
		 * @param array<string, mixed> $overrides Requested page overrides.
		 */
		$settings = apply_filters(
			'eilmo_cf/single_product_config',
			$settings,
			$overrides
		);

		return is_array( $settings )
			? $settings
			: ProductSettings::get_single_product();
	}
}
