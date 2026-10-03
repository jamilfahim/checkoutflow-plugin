<?php
/**
 * Global checkout display settings helper.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Provides normalized, backwards-compatible display settings.
 */
final class CheckoutDisplaySettings {

	/**
	 * Get complete checkout display settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = CheckoutSettings::get_defaults();
		$display_defaults = isset( $defaults['checkout_display'] ) && is_array( $defaults['checkout_display'] )
			? $defaults['checkout_display']
			: array();

		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored_display = isset( $stored['checkout_display'] ) && is_array( $stored['checkout_display'] )
			? $stored['checkout_display']
			: array();

		return array_replace_recursive( $display_defaults, $stored_display );
	}

	/**
	 * Get one display subsection.
	 *
	 * @param string $section Section key.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_section( string $section ): array {
		$display = self::get();
		return isset( $display[ $section ] ) && is_array( $display[ $section ] )
			? $display[ $section ]
			: array();
	}
}
