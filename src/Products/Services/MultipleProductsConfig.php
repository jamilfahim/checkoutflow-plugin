<?php
/**
 * Multiple Products configuration.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

use EilmoCheckout\Admin\ProductSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves global Multiple Products defaults with safe checkout overrides.
 */
final class MultipleProductsConfig {

	/**
	 * Get resolved Multiple Products configuration.
	 *
	 * Only keys defined inside the independent multiple_products namespace may
	 * be overridden by shortcode, Elementor, or another checkout integration.
	 *
	 * @param array<string, mixed> $overrides Checkout-level overrides.
	 *
	 * @return array<string, mixed>
	 */
	public function get( array $overrides = array() ): array {

		$all_defaults = ProductSettings::get_defaults();
		$defaults = isset( $all_defaults['multiple_products'] ) &&
			is_array( $all_defaults['multiple_products'] )
				? $all_defaults['multiple_products']
				: array();

		if ( empty( $defaults ) ) {
			return array();
		}

		$global = ProductSettings::get_multiple_products();
		$allowed = array_intersect_key(
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
		$settings = $sanitizer->sanitize_multiple_products(
			$settings,
			$defaults
		);

		/**
		 * Filters resolved Multiple Products presentation configuration.
		 *
		 * @param array<string, mixed> $settings  Resolved settings.
		 * @param array<string, mixed> $overrides Requested checkout overrides.
		 */
		$settings = apply_filters(
			'eilmo_cf/multiple_products_config',
			$settings,
			$overrides
		);

		return is_array( $settings )
			? $settings
			: ProductSettings::get_multiple_products();
	}
}
