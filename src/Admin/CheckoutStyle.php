<?php
/**
 * Global checkout visual theme.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the single plugin-level theme shared by the complete Eilmo checkout.
 *
 * Component-specific overrides intentionally belong to Elementor. WhatsApp is
 * an integration and therefore keeps its visual settings in WhatsAppSettings.
 */
final class CheckoutStyle {

	/**
	 * Get global checkout theme defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {

		return array(
			'preset'             => 'premium_purple',
			'primary'            => '#6d28d9',
			'primary_hover'      => '#5b21b6',
			'primary_soft'       => '#f3e8ff',
			'secondary'          => '#7c3aed',
			'text'               => '#111827',
			'muted_text'         => '#64748b',
			'background'         => '#ffffff',
			'muted_background'   => '#f8fafc',
			'soft_background'    => '#f3f4f6',
			'border'             => '#e5e7eb',
			'border_strong'      => '#c4b5fd',
			'success'            => '#16a34a',
			'warning'            => '#d97706',
			'danger'             => '#dc2626',
			'badge_background'           => '#6d28d9',
			'badge_text'                 => '#ffffff',
			'payment_badge_background'   => '#6d28d9',
			'payment_badge_text'         => '#ffffff',
			'button_text'        => '#ffffff',
			'gradient_enabled'   => 'no',
			'gradient_end'       => '#7c3aed',
			'gradient_angle'     => 135,
			'radius'             => 12,
			'parent_radius'      => 16,
			'gap'                => 16,
			'card_gap'           => 16,
		);
	}

	/**
	 * Get the saved global theme with a legacy Product Style fallback.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {

		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$style = isset( $stored['checkout_style'] ) && is_array( $stored['checkout_style'] )
			? $stored['checkout_style']
			: ( isset( $stored['product_style'] ) && is_array( $stored['product_style'] )
				? $stored['product_style']
				: array() );

		$style = self::normalize_global_style( $style );

		return array_replace_recursive( self::get_defaults(), $style );
	}

	/**
	 * Normalize saved values for the global checkout theme.
	 *
	 * The Default Theme/Premium Purple preset is authoritative. Older releases
	 * accidentally stored a green palette while still labelling the preset as
	 * Premium Purple. Treating those stale values as merchant customization is
	 * what made Quick Checkout green even though its CSS fallbacks were purple.
	 *
	 * Genuine Custom presets are preserved. The one exception is the exact
	 * historical green default signature that older releases could accidentally
	 * save as Custom. A legacy no-preset fallback is also retained for old partial
	 * option arrays.
	 *
	 * @param array<string, mixed> $style Saved global style values.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalize_global_style( array $style ): array {

		$defaults = self::get_defaults();
		$has_explicit_preset = array_key_exists( 'preset', $style );
		$preset = $has_explicit_preset
			? sanitize_key( (string) $style['preset'] )
			: '';

		/*
		 * The complete historical green palette is a release artifact, not the
		 * current default. A few installations saved that exact palette while the
		 * preset flag had already become `custom`, which meant the older migration
		 * intentionally preserved it forever. Detect the full legacy signature
		 * before honoring the Custom flag. Requiring every core token to match makes
		 * this safe for genuine merchant-created green themes.
		 */
		$legacy_green = array(
			'primary'                  => '#274c3d',
			'primary_hover'            => '#355f4e',
			'primary_soft'             => '#f3f7f0',
			'secondary'                => '#355f4e',
			'text'                     => '#17392f',
			'muted_text'               => '#738078',
			'background'               => '#fffdfa',
			'muted_background'         => '#f7f4ec',
			'soft_background'          => '#e9efe2',
			'border'                   => '#dbe2d7',
			'border_strong'            => '#274c3d',
			'badge_background'         => '#274c3d',
			'badge_text'               => '#ffffff',
			'payment_badge_background' => '#7c3aed',
			'payment_badge_text'       => '#ffffff',
			'button_text'              => '#ffffff',
			'gradient_enabled'         => 'no',
			'gradient_end'             => '#355f4e',
		);

		$core_signature = array(
			'primary',
			'primary_hover',
			'primary_soft',
			'secondary',
			'text',
			'muted_text',
			'background',
			'muted_background',
			'soft_background',
			'border',
			'border_strong',
			'badge_background',
			'button_text',
			'gradient_end',
		);

		$is_legacy_green = true;
		foreach ( $core_signature as $key ) {
			if (
				! array_key_exists( $key, $style ) ||
				strtolower( (string) $style[ $key ] ) !== strtolower( (string) $legacy_green[ $key ] )
			) {
				$is_legacy_green = false;
				break;
			}
		}

		if ( $is_legacy_green ) {
			foreach ( $legacy_green as $key => $old_value ) {
				if (
					array_key_exists( $key, $style ) &&
					array_key_exists( $key, $defaults ) &&
					strtolower( (string) $style[ $key ] ) === strtolower( (string) $old_value )
				) {
					$style[ $key ] = $defaults[ $key ];
				}
			}

			$style['preset'] = 'premium_purple';
			$preset = 'premium_purple';
		}

		/*
		 * A saved Default Theme must always resolve to the canonical plugin
		 * defaults. The settings UI automatically switches edited themes to
		 * Custom, so stale values under premium_purple are legacy data rather
		 * than an intentional merchant palette.
		 */
		if ( 'premium_purple' === $preset ) {
			return array_replace_recursive( $style, $defaults );
		}

		/* Never rewrite an explicitly custom merchant theme. */
		if ( $has_explicit_preset ) {
			return $style;
		}

		/*
		 * Very old installations can have no preset key and only a subset of the
		 * historical defaults. Keep the previous conservative partial migration:
		 * every present legacy token must match and the primary/text signature must
		 * both be present before any values are changed.
		 */
		$matches = 0;
		foreach ( $legacy_green as $key => $old_value ) {
			if ( ! array_key_exists( $key, $style ) ) {
				continue;
			}

			if ( strtolower( (string) $style[ $key ] ) !== strtolower( (string) $old_value ) ) {
				return $style;
			}

			++$matches;
		}

		if (
			$matches >= 3 &&
			isset( $style['primary'], $style['text'] ) &&
			'#274c3d' === strtolower( (string) $style['primary'] ) &&
			'#17392f' === strtolower( (string) $style['text'] )
		) {
			foreach ( array_keys( $legacy_green ) as $key ) {
				if ( array_key_exists( $key, $style ) && array_key_exists( $key, $defaults ) ) {
					$style[ $key ] = $defaults[ $key ];
				}
			}
		}

		return $style;
	}

	/**
	 * Sanitize the global theme.
	 *
	 * @param array<string, mixed> $input    Submitted values.
	 * @param array<string, mixed> $fallback Previously saved values.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input, array $fallback = array() ): array {

		$defaults = array_replace_recursive( self::get_defaults(), $fallback );
		$colors   = array(
			'primary', 'primary_hover', 'primary_soft', 'secondary', 'text',
			'muted_text', 'background', 'muted_background', 'soft_background',
			'border', 'border_strong', 'success', 'warning', 'danger',
			'badge_background', 'badge_text', 'payment_badge_background', 'payment_badge_text',
			'button_text', 'gradient_end',
		);

		$preset = sanitize_key( (string) ( $input['preset'] ?? $defaults['preset'] ) );
		if ( ! in_array( $preset, array( 'premium_purple', 'custom' ), true ) ) {
			$preset = (string) $defaults['preset'];
		}

		$sanitized = array(
			'preset'           => $preset,
			'gradient_enabled' => 'yes' === (string) ( $input['gradient_enabled'] ?? $defaults['gradient_enabled'] ) ? 'yes' : 'no',
			'gradient_angle'   => self::range( $input['gradient_angle'] ?? $defaults['gradient_angle'], 0, 360, (int) $defaults['gradient_angle'] ),
			'radius'           => self::range( $input['radius'] ?? $defaults['radius'], 0, 40, (int) $defaults['radius'] ),
			'parent_radius'    => self::range( $input['parent_radius'] ?? $defaults['parent_radius'], 0, 48, (int) $defaults['parent_radius'] ),
			'gap'              => self::range( $input['gap'] ?? $defaults['gap'], 0, 48, (int) $defaults['gap'] ),
			'card_gap'         => self::range( $input['card_gap'] ?? $defaults['card_gap'], 0, 48, (int) $defaults['card_gap'] ),
		);

		foreach ( $colors as $color ) {
			$value = sanitize_hex_color( (string) ( $input[ $color ] ?? '' ) );
			$sanitized[ $color ] = $value ?: (string) $defaults[ $color ];
		}

		return $sanitized;
	}

	/**
	 * Build root-level CSS variables for the complete checkout.
	 *
	 * @param array<string, mixed>|null $style        Optional theme values.
	 * @param string                    $theme_prefix CSS custom-property prefix for colour tokens.
	 *
	 * @return string
	 */
	public static function get_css_variables( ?array $style = null, string $theme_prefix = '--eilmo-cf-theme-' ): string {

		$defaults = self::get_defaults();
		$style    = array_replace_recursive( $defaults, is_array( $style ) ? $style : self::get() );

		if ( ! preg_match( '/^--[a-z0-9-]+-$/', $theme_prefix ) ) {
			$theme_prefix = '--eilmo-cf-theme-';
		}

		$color = static function ( string $key ) use ( $style, $defaults ): string {
			$value = sanitize_hex_color( (string) ( $style[ $key ] ?? '' ) );

			return $value ?: (string) $defaults[ $key ];
		};

		$range = static function ( string $key, int $maximum ) use ( $style, $defaults ): int {
			$value = is_numeric( $style[ $key ] ?? null ) ? (int) $style[ $key ] : (int) $defaults[ $key ];

			return max( 0, min( $maximum, $value ) );
		};

		/*
		 * Keep the global theme intentionally small. Merchants choose a handful
		 * of base colors; all supporting shades are derived from them. Historical
		 * stored values remain readable for backward compatibility but no longer
		 * need to be edited one-by-one. Elementor can still override any component.
		 */
		$primary = $color( 'primary' );
		$card_background = $color( 'background' );
		$page_background = $color( 'soft_background' );
		$border = $color( 'border' );
		$button_text = $color( 'button_text' );
		$primary_hover = sprintf( 'color-mix(in srgb,%1$s 84%%,#000000)', $primary );
		$primary_soft = sprintf( 'color-mix(in srgb,%1$s 8%%,%2$s)', $primary, $card_background );
		$muted_surface = sprintf( 'color-mix(in srgb,%1$s 94%%,%2$s 6%%)', $card_background, $primary );
		$strong_border = sprintf( 'color-mix(in srgb,%1$s 56%%,%2$s 44%%)', $border, $primary );

		$variables = array(
			/* Canonical theme variables: names match the Global Checkout Style UI. */
			$theme_prefix . 'primary'            => $primary,
			$theme_prefix . 'primary-hover'      => $primary_hover,
			$theme_prefix . 'primary-soft'       => $primary_soft,
			$theme_prefix . 'main-text'          => $color( 'text' ),
			$theme_prefix . 'secondary-text'     => $color( 'muted_text' ),
			$theme_prefix . 'card-background'    => $card_background,
			$theme_prefix . 'muted-surface'      => $muted_surface,
			$theme_prefix . 'soft-background'    => $page_background,
			$theme_prefix . 'border'             => $border,
			$theme_prefix . 'strong-border'      => $strong_border,
			$theme_prefix . 'success'            => $color( 'success' ),
			$theme_prefix . 'warning'            => $color( 'warning' ),
			$theme_prefix . 'error'              => $color( 'danger' ),
			$theme_prefix . 'button-text'        => $button_text,

			/* Layout tokens remain independent from the colour theme. */
			'--eilmo-cf-radius'             => $range( 'radius', 40 ) . 'px',
			'--eilmo-cf-parent-radius'      => $range( 'parent_radius', 48 ) . 'px',
			'--eilmo-cf-gap'                => $range( 'gap', 48 ) . 'px',
			'--eilmo-cf-card-gap'           => $range( 'card_gap', 48 ) . 'px',
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
