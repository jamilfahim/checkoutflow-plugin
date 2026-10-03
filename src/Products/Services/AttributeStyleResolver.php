<?php
/**
 * Single Product attribute-style resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes global, shortcode and Elementor attribute presentation maps.
 */
final class AttributeStyleResolver {

	/**
	 * Normalize an attribute-style map.
	 *
	 * Supported string syntax:
	 * pa_color:image_grid:4:yes:no:no,pa_size:buttons
	 *
	 * @param mixed $input Raw map, shortcode string or Elementor repeater rows.
	 *
	 * @return array<string, array{style:string,columns:int,show_price:string,show_regular_price:string,show_savings:string}>
	 */
	public function sanitize( $input ): array {

		if ( is_string( $input ) ) {
			$input = $this->parse_string( $input );
		}

		if ( ! is_array( $input ) ) {
			return array();
		}

		$styles = array();

		foreach ( $input as $key => $row ) {
			$attribute = is_string( $key ) ? $key : '';
			$style     = '';
			$columns   = 4;
			$show_price = 'yes';
			$show_regular_price = 'no';
			$show_savings = 'no';

			if ( is_array( $row ) ) {
				$attribute = (string) ( $row['attribute'] ?? $attribute );
				$style     = (string) ( $row['style'] ?? '' );
				$columns   = $row['columns'] ?? 4;
				$show_price = (string) ( $row['show_price'] ?? 'yes' );
				$show_regular_price = (string) ( $row['show_regular_price'] ?? 'no' );
				$show_savings = (string) ( $row['show_savings'] ?? 'no' );
			} elseif ( is_string( $row ) ) {
				$style = $row;
			}

			$attribute = sanitize_title( $attribute );
			$style     = sanitize_key( $style );

			if (
				'' === $attribute ||
				! in_array( $style, array( 'buttons', 'text_grid', 'image_grid' ), true )
			) {
				continue;
			}

			$columns = is_numeric( $columns ) ? (int) $columns : 4;

			$styles[ $attribute ] = array(
				'style'        => $style,
				'columns'      => max( 2, min( 6, $columns ) ),
				'show_price'   => 'no' === sanitize_key( $show_price ) ? 'no' : 'yes',
				'show_regular_price' => 'yes' === sanitize_key( $show_regular_price ) ? 'yes' : 'no',
				'show_savings' => 'yes' === sanitize_key( $show_savings ) ? 'yes' : 'no',
			);
		}

		return $styles;
	}

	/**
	 * Merge page-level overrides over global defaults.
	 *
	 * @param mixed $global    Global map.
	 * @param mixed $overrides Page-level map.
	 *
	 * @return array<string, array{style:string,columns:int,show_price:string,show_regular_price:string,show_savings:string}>
	 */
	public function merge( $global, $overrides ): array {

		return array_replace(
			$this->sanitize( $global ),
			$this->sanitize( $overrides )
		);
	}

	/**
	 * Resolve one attribute's presentation.
	 *
	 * @param string $attribute Attribute slug.
	 * @param mixed  $styles    Resolved map.
	 *
	 * @return array{style:string,columns:int,show_price:string,show_regular_price:string,show_savings:string}
	 */
	public function resolve( string $attribute, $styles ): array {

		$attribute = sanitize_title( $attribute );
		$styles    = $this->sanitize( $styles );

		return $styles[ $attribute ] ?? array(
			'style'        => 'buttons',
			'columns'      => 4,
			'show_price'   => 'yes',
			'show_regular_price' => 'no',
			'show_savings' => 'no',
		);
	}

	/**
	 * Parse shortcode mapping syntax into repeater-shaped rows.
	 *
	 * @param string $input Shortcode value.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_string( string $input ): array {

		$rows   = array();
		$tokens = preg_split( '/[\r\n,;]+/', $input );

		if ( ! is_array( $tokens ) ) {
			return array();
		}

		foreach ( $tokens as $token ) {
			$parts = array_map( 'trim', explode( ':', trim( $token ) ) );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$rows[] = array(
				'attribute'    => $parts[0],
				'style'        => $parts[1],
				'columns'      => $parts[2] ?? 4,
				'show_price'   => $parts[3] ?? 'yes',
				'show_regular_price' => $parts[4] ?? 'no',
				'show_savings' => $parts[5] ?? 'no',
			);
		}

		return $rows;
	}
}
