<?php
/**
 * Independent visual theme for WooCommerce native/default checkout.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the Default Checkout visual system used only by Default Checkout.
 *
 * Important: these values are intentionally independent from CheckoutStyle,
 * which belongs to campaign/landing-page checkout. The style switch defaults ON; when explicitly disabled no values from this class are emitted on the frontend.
 */
final class DefaultCheckoutStyle {

	/**
	 * Independent defaults. These do not read campaign checkout settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'enabled'         => 'yes',
			'primary'         => '#6d28d9',
			'text'            => '#111827',
			'muted_text'      => '#64748b',
			'background'      => '#ffffff',
			'soft_background' => '#f3f4f6',
			'border'          => '#e5e7eb',
			'button_text'     => '#ffffff',
			'radius'          => 12,
			'parent_radius'   => 16,
			'gap'             => 16,
			'card_gap'        => 16,
		);
	}

	/**
	 * Sanitize one Default Checkout style payload.
	 *
	 * @param array<string, mixed> $input    Submitted values.
	 * @param array<string, mixed> $fallback Previously stored values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input, array $fallback = array() ): array {
		$defaults = array_replace_recursive( self::get_defaults(), $fallback );
		$colors   = array( 'primary', 'text', 'muted_text', 'background', 'soft_background', 'border', 'button_text' );

		$sanitized = array(
			'enabled'       => 'yes' === (string) ( $input['enabled'] ?? $defaults['enabled'] ) ? 'yes' : 'no',
			'radius'        => self::range( $input['radius'] ?? $defaults['radius'], 0, 40, (int) $defaults['radius'] ),
			'parent_radius' => self::range( $input['parent_radius'] ?? $defaults['parent_radius'], 0, 48, (int) $defaults['parent_radius'] ),
			'gap'           => self::range( $input['gap'] ?? $defaults['gap'], 0, 48, (int) $defaults['gap'] ),
			'card_gap'      => self::range( $input['card_gap'] ?? $defaults['card_gap'], 0, 48, (int) $defaults['card_gap'] ),
		);

		foreach ( $colors as $key ) {
			$value = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) );
			$sanitized[ $key ] = $value ?: (string) $defaults[ $key ];
		}

		return $sanitized;
	}

	/**
	 * Build Default Checkout-only CSS variables.
	 *
	 * @param array<string, mixed>|null $style Optional values.
	 * @return string
	 */
	public static function get_css_variables( ?array $style = null ): string {
		$defaults = self::get_defaults();
		$style    = array_replace_recursive( $defaults, is_array( $style ) ? $style : array() );

		$color = static function ( string $key ) use ( $style, $defaults ): string {
			$value = sanitize_hex_color( (string) ( $style[ $key ] ?? '' ) );
			return $value ?: (string) $defaults[ $key ];
		};

		$range = static function ( string $key, int $maximum ) use ( $style, $defaults ): int {
			$value = is_numeric( $style[ $key ] ?? null ) ? (int) $style[ $key ] : (int) $defaults[ $key ];
			return max( 0, min( $maximum, $value ) );
		};

		$primary    = $color( 'primary' );
		$background = $color( 'background' );
		$border     = $color( 'border' );

		$variables = array(
			'--eilmo-cf-native-theme-primary'         => $primary,
			'--eilmo-cf-native-theme-primary-hover'   => sprintf( 'color-mix(in srgb,%1$s 84%%,#000000)', $primary ),
			'--eilmo-cf-native-theme-primary-soft'    => sprintf( 'color-mix(in srgb,%1$s 8%%,%2$s)', $primary, $background ),
			'--eilmo-cf-native-theme-main-text'       => $color( 'text' ),
			'--eilmo-cf-native-theme-secondary-text'  => $color( 'muted_text' ),
			'--eilmo-cf-native-theme-card-background' => $background,
			'--eilmo-cf-native-theme-muted-surface'   => sprintf( 'color-mix(in srgb,%1$s 94%%,%2$s 6%%)', $background, $primary ),
			'--eilmo-cf-native-theme-soft-background' => $color( 'soft_background' ),
			'--eilmo-cf-native-theme-border'          => $border,
			'--eilmo-cf-native-theme-strong-border'   => sprintf( 'color-mix(in srgb,%1$s 56%%,%2$s 44%%)', $border, $primary ),
			'--eilmo-cf-native-theme-button-text'     => $color( 'button_text' ),
			'--eilmo-cf-native-radius'                => $range( 'radius', 40 ) . 'px',
			'--eilmo-cf-native-parent-radius'         => $range( 'parent_radius', 48 ) . 'px',
			'--eilmo-cf-native-gap'                   => $range( 'gap', 48 ) . 'px',
			'--eilmo-cf-native-card-gap'              => $range( 'card_gap', 48 ) . 'px',
		);

		$output = '';
		foreach ( $variables as $name => $value ) {
			$output .= $name . ':' . $value . ';';
		}
		return $output;
	}

	/** Clamp a numeric setting. */
	private static function range( $value, int $minimum, int $maximum, int $fallback ): int {
		$value = is_numeric( $value ) ? (int) $value : $fallback;
		return max( $minimum, min( $maximum, $value ) );
	}
}
