<?php
/**
 * Checkout flow shortcode.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Shortcodes;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Products\Services\AttributeStyleResolver;
use EilmoCheckout\Rendering\CheckoutRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the checkout flow shortcode.
 */
final class CheckoutFlowShortcode implements RegistrableInterface {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	private const TAG =
		'eilmo_checkout';

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {

		add_shortcode(
			self::TAG,
			array(
				$this,
				'render',
			)
		);
	}

	/**
	 * Render the checkout flow shortcode.
	 *
	 * Basic examples:
	 *
	 * [eilmo_checkout]
	 *
	 * [eilmo_checkout product_mode="single" product_id="123"]
	 *
	 * [eilmo_checkout product_mode="multiple" product_ids="123,456,789"]
	 *
	 * Per-checkout Single Product attribute styles:
	 *
	 * [eilmo_checkout product_id="123" variation_selection="multiple" attribute_styles="pa_color:image_grid:4,pa_size:buttons"]
	 *
	 * Combo Offers are intentionally not rendered inside shortcode checkout
	 * forms. Add them with the Elementor Checkout Flow – Combo Button widget.
	 * Special Discounts are global automatic CONDITION -> REWARD rules.
	 * Combo/special assignment attributes are not part of the checkout
	 * shortcode. Enabled Special Discount rules apply automatically.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 *
	 * @return string
	 */

	public function render(
		$atts = array()
	): string {

		if (
			! is_array(
				$atts
			)
		) {
			$atts =
				array();
		}

		/*
		 * Keep track of attributes the author actually supplied.
		 * Presentation defaults now live in Plugin Settings; legacy
		 * presentation attributes remain supported only when they are
		 * explicitly present in an existing shortcode.
		 */
		$raw_atts = $atts;

		$atts =
			shortcode_atts(
				array(
                    'checkout_layout' => '',
                    'checkout_language' => '',
					/*
					 * -----------------------------------------
					 * Checkout / products.
					 * -----------------------------------------
					 */
					'id' =>
						0,

					'product_id' =>
						0,

					'product_ids' =>
						'',

					'product_mode' =>
						'auto',

					'selection' =>
						'single',

					'product_layout' =>
						'cards',

					'variation_selection' =>
						'',

					'variation_layout' =>
						'',

					'grid_show_price' =>
						'',

					'grid_show_regular_price' =>
						'',

					'grid_show_savings' =>
						'',

					'badge_attribute' =>
						'',

					'show_selected_items' =>
						'',

					'selected_items_show_quantity' =>
						'',

					'show_summary_items' =>
						'',

					'summary_items_show_quantity' =>
						'',

					'attribute_styles' =>
						'',

					'product_group_title' =>
						'',

					'group_columns_desktop' =>
						3,

					'group_columns_tablet' =>
						2,

					'group_columns_mobile' =>
						2,

					'group_gallery_id' =>
						'',

					'group_quantity_control' =>
						'no',

					'group_image_ratio' =>
						'16-9',

					'group_image_fit' =>
						'cover',

					'group_image_position' =>
						'center',

					/*
					 * -----------------------------------------
					 * Product Gallery.
					 * -----------------------------------------
					 */
					'gallery_id' =>
						'',

					'gallery_ids' =>
						'',

					/*
					 * -----------------------------------------
					 * Product presentation.
					 * -----------------------------------------
					 */
					'show_thumbnail' =>
						'yes',

					'show_title' =>
						'yes',

					'show_description' =>
						'yes',

					'show_price' =>
						'yes',

					'show_quantity' =>
						'yes',

					'show_stock' =>
						'yes',

					'show_sale_badge' =>
						'yes',

					'show_summary' =>
						'yes',

					'title_link' =>
						'yes',

					'title_link_target' =>
						'same',
				),
				$atts,
				self::TAG
			);

		/*
		 * ---------------------------------------------
		 * Products
		 * ---------------------------------------------
		 */
		$product_ids =
			$this->sanitize_product_ids(
				$atts[
					'product_ids'
				],
				$atts[
					'product_id'
				]
			);

		$product_mode = $this->sanitize_product_mode(
			$atts['product_mode']
		);

		/* Single Product mode may never own more than one parent product. */
		if ( 'single' === $product_mode && count( $product_ids ) > 1 ) {
			$product_ids = array_slice( $product_ids, 0, 1 );
		}

		/*
		 * ---------------------------------------------
		 * Product Gallery
		 * ---------------------------------------------
		 */
		$gallery_ids =
			$this->sanitize_gallery_ids(
				array(
					$atts['gallery_id'],
					$atts['gallery_ids'],
				)
			);


		$single_product_overrides = array();

		if ( array_key_exists( 'variation_layout', $raw_atts ) ) {
			$variation_layout = sanitize_key( (string) $atts['variation_layout'] );

			if ( in_array( $variation_layout, array( 'sections', 'grid' ), true ) ) {
				$single_product_overrides['variation_layout'] = $variation_layout;
			}
		}

		if ( array_key_exists( 'variation_selection', $raw_atts ) ) {
			$variation_selection = sanitize_key( (string) $atts['variation_selection'] );

			if ( in_array( $variation_selection, array( 'single', 'multiple' ), true ) ) {
				$single_product_overrides['variation_selection'] = $variation_selection;
			}
		}

		foreach ( array( 'grid_show_price', 'grid_show_regular_price', 'grid_show_savings', 'show_selected_items', 'selected_items_show_quantity', 'show_summary_items', 'summary_items_show_quantity' ) as $grid_control ) {
			if ( ! array_key_exists( $grid_control, $raw_atts ) ) {
				continue;
			}

			$value = sanitize_key( (string) $atts[ $grid_control ] );

			if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
				$single_product_overrides[ $grid_control ] = $value;
			}
		}

		if ( array_key_exists( 'badge_attribute', $raw_atts ) ) {
			$single_product_overrides['badge_attribute'] = sanitize_key( (string) $atts['badge_attribute'] );
		}

		if ( array_key_exists( 'attribute_styles', $raw_atts ) ) {
			$single_product_overrides['attribute_styles'] =
				( new AttributeStyleResolver() )->sanitize( $atts['attribute_styles'] );
		}

		/*
		 * ---------------------------------------------
		 * Sanitized renderer settings.
		 * ---------------------------------------------
		 */
		$settings =
			array(
				/*
				 * Checkout / products.
				 */
				'id' =>
					absint(
						$atts[
							'id'
						]
					),

				'product_id' =>
					! empty(
						$product_ids
					)
						? $product_ids[0]
						: 0,

				'product_ids' =>
					$product_ids,

				'product_mode' =>
					$product_mode,

				'selection' =>
					$this->sanitize_selection_mode(
						$atts[
							'selection'
						]
					),

				'product_layout' =>
					$this->sanitize_product_layout(
						$atts[
							'product_layout'
						]
					),

				'single_product' =>
					$single_product_overrides,

				'product_group_title' =>
					sanitize_text_field(
						(string) $atts[
							'product_group_title'
						]
					),

				'group_columns_desktop' =>
					$this->sanitize_integer_range(
						$atts['group_columns_desktop'],
						1,
						6,
						3
					),

				'group_columns_tablet' =>
					$this->sanitize_integer_range(
						$atts['group_columns_tablet'],
						1,
						6,
						2
					),

				'group_columns_mobile' =>
					$this->sanitize_integer_range(
						$atts['group_columns_mobile'],
						1,
						4,
						2
					),

				'group_gallery_id' =>
					sanitize_key( (string) $atts['group_gallery_id'] ),

				'group_quantity_control' =>
					$this->sanitize_yes_no( $atts['group_quantity_control'] ),

				'group_image_ratio' =>
					in_array(
						(string) $atts['group_image_ratio'],
						array( '1-1', '4-3', '3-2', '16-9' ),
						true
					)
						? (string) $atts['group_image_ratio']
						: '16-9',

				'group_image_fit' =>
					'contain' === (string) $atts['group_image_fit']
						? 'contain'
						: 'cover',

				'group_image_position' =>
					in_array(
						(string) $atts['group_image_position'],
						array( 'center', 'top', 'bottom', 'left', 'right' ),
						true
					)
						? (string) $atts['group_image_position']
						: 'center',

				'gallery_ids' =>
					$gallery_ids,

				/*
				 * Combo Offers are no longer rendered inside checkout. Legacy shortcode
				 * attributes are accepted above for backward compatibility but ignored
				 * for presentation. Use the Elementor Combo Button widget instead.
				 */
				'show_combo_offers' => 'no',
				'combo_offer_ids' => array(),
				'combo_selection_mode' => 'multiple',
				'combo_title' => '',
				'combo_show_description' => 'no',
				'selected_combo_offer_ids' => array(),

			/* Special Discounts remain automatic global rules. */
			'show_special_offers' => 'yes',
			'special_offer_ids' => array(),

				/* Internal compatibility aliases. */
			'show_order_bumps' => 'yes',
			'order_bump_ids' => array(),

				/*
				 * Product presentation.
				 */
				'show_thumbnail' =>
					$this->sanitize_yes_no(
						$atts[
							'show_thumbnail'
						]
					),

				'show_title' =>
					$this->sanitize_yes_no(
						$atts[
							'show_title'
						]
					),

				'show_description' =>
					$this->sanitize_yes_no(
						$atts[
							'show_description'
						]
					),

				'show_price' =>
					$this->sanitize_yes_no(
						$atts[
							'show_price'
						]
					),

				'show_quantity' =>
					$this->sanitize_yes_no(
						$atts[
							'show_quantity'
						]
					),

				'show_stock' =>
					$this->sanitize_yes_no(
						$atts[
							'show_stock'
						]
					),

				'show_sale_badge' =>
					$this->sanitize_yes_no(
						$atts[
							'show_sale_badge'
						]
					),

				'show_summary' =>
					$this->sanitize_yes_no(
						$atts[
							'show_summary'
						]
					),

				'title_link' =>
					$this->sanitize_yes_no(
						$atts[
							'title_link'
						]
					),

				'title_link_target' =>
					$this->sanitize_link_target(
						$atts[
							'title_link_target'
						]
					),
			);

		/**
		 * Filters checkout shortcode settings before rendering.
		 *
		 * This filter can be used by extensions and future
		 * modules to add additional checkout configuration
		 * without changing shortcode rendering logic.
		 *
		 * @param array<string, mixed> $settings Sanitized shortcode settings.
		 * @param array<string, mixed> $atts     Original shortcode attributes.
		 */
		/*
		 * Global Plugin Settings control presentation. Checkout-instance content attributes (layout, selection, titles and assignments) remain in the renderer settings, while legacy presentation attributes are omitted unless explicitly supplied. CheckoutRenderer still enforces current global presentation values.
		 */
        $settings['checkout_layout'] = sanitize_key( $atts['checkout_layout'] );
        $settings['checkout_language'] = sanitize_key( $atts['checkout_language'] );
		$legacy_presentation_attributes = array(
			'group_columns_desktop',
			'group_columns_tablet',
			'group_columns_mobile',
			'group_image_ratio',
			'group_image_fit',
			'group_image_position',
			'show_thumbnail',
			'show_title',
			'show_description',
			'show_price',
			'show_quantity',
			'show_stock',
			'show_sale_badge',
			'show_summary',
			'title_link',
			'title_link_target',
		);

		foreach ( $legacy_presentation_attributes as $attribute ) {
			if ( ! array_key_exists( $attribute, $raw_atts ) ) {
				unset( $settings[ $attribute ] );
			}
		}

		$settings =
			apply_filters(
				'eilmo_cf/shortcode_settings',
				$settings,
				$atts
			);

		if (
			! is_array(
				$settings
			)
		) {
			return '';
		}

		$renderer =
			new CheckoutRenderer();

		return $renderer->render(
			$settings
		);
	}

	/**
	 * Sanitize product IDs.
	 *
	 * Supports:
	 *
	 * product_id="123"
	 *
	 * product_ids="123,456,789"
	 *
	 * When both are supplied, all valid IDs are
	 * merged and duplicates are removed.
	 *
	 * @param mixed $product_ids Multiple product IDs.
	 * @param mixed $product_id  Single product ID.
	 *
	 * @return array<int, int>
	 */
	private function sanitize_product_ids(
		$product_ids,
		$product_id
	): array {

		$ids =
			array();

		$single_product_id =
			absint(
				$product_id
			);

		if (
			$single_product_id >
				0
		) {
			$ids[] =
				$single_product_id;
		}

		if (
			is_string(
				$product_ids
			) &&
			'' !==
				trim(
					$product_ids
				)
		) {
			$product_ids =
				preg_split(
					'/[\s,]+/',
					$product_ids
				);
		}

		if (
			is_array(
				$product_ids
			)
		) {
			foreach (
				$product_ids as
					$id
			) {
				$id =
					absint(
						$id
					);

				if (
					$id >
						0
				) {
					$ids[] =
						$id;
				}
			}
		}

		$ids =
			array_values(
				array_unique(
					$ids
				)
			);

		/**
		 * Filters selected product IDs.
		 *
		 * @param array<int, int> $ids Product IDs.
		 */
		$ids =
			apply_filters(
				'eilmo_cf/shortcode_product_ids',
				$ids
			);

		if (
			! is_array(
				$ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach (
			$ids as
				$id
		) {

			$id =
				absint(
					$id
				);

			if (
				$id <=
					0
			) {
				continue;
			}

			$normalized[] =
				$id;
		}

		return array_values(
			array_unique(
				$normalized
			)
		);
	}

	/**
	 * Sanitize Product Gallery IDs.
	 *
	 * Supports strings, arrays and nested arrays.
	 *
	 * @param mixed $gallery_ids Gallery IDs.
	 *
	 * @return array<int, string>
	 */
	private function sanitize_gallery_ids(
		$gallery_ids
	): array {

		$values = array();

		$collect = static function ( $value ) use ( &$values, &$collect ): void {
			if ( is_array( $value ) ) {
				foreach ( $value as $nested_value ) {
					$collect( $nested_value );
				}
				return;
			}

			if ( ! is_scalar( $value ) ) {
				return;
			}

			$parts = preg_split(
				'/[\s,]+/',
				trim( (string) $value )
			);

			if ( ! is_array( $parts ) ) {
				return;
			}

			foreach ( $parts as $part ) {
				$part = sanitize_key( (string) $part );

				if ( '' !== $part ) {
					$values[] = $part;
				}
			}
		};

		$collect( $gallery_ids );

		$values = array_values(
			array_unique( $values )
		);

		$values = apply_filters(
			'eilmo_cf/shortcode_gallery_ids',
			$values
		);

		if ( ! is_array( $values ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $values as $value ) {
			$value = sanitize_key( (string) $value );

			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}

		return array_values(
			array_unique( $normalized )
		);
	}

	/**
	 * Sanitize the checkout product mode.
	 *
	 * auto preserves inference for shortcodes created before product_mode was
	 * introduced. New shortcodes should explicitly use single or multiple.
	 *
	 * @param mixed $mode Product mode.
	 *
	 * @return string
	 */
	private function sanitize_product_mode(
		$mode
	): string {

		$mode = sanitize_key( (string) $mode );

		return in_array( $mode, array( 'single', 'multiple' ), true )
			? $mode
			: 'auto';
	}

	/**
	 * Sanitize product selection mode.
	 *
	 * @param mixed $selection Selection mode.
	 *
	 * @return string
	 */
	private function sanitize_selection_mode(
		$selection
	): string {

		$selection =
			sanitize_key(
				(string) $selection
			);

		$allowed =
			array(
				'single',
				'multiple',
			);

		if (
			! in_array(
				$selection,
				$allowed,
				true
			)
		) {
			return 'single';
		}

		return $selection;
	}

	/**
	 * Sanitize product presentation layout.
	 *
	 * cards   = Existing separate product cards.
	 * grouped = One parent card containing a responsive product grid.
	 *
	 * @param mixed $layout Product layout.
	 *
	 * @return string
	 */
	private function sanitize_product_layout(
		$layout
	): string {

		$layout =
			sanitize_key(
				(string) $layout
			);

		return 'grouped' === $layout
			? 'grouped'
			: 'cards';
	}

	/**
	 * Sanitize an integer range.
	 *
	 * @param mixed $value   Value.
	 * @param int   $minimum Minimum.
	 * @param int   $maximum Maximum.
	 * @param int   $default Default.
	 *
	 * @return int
	 */
	private function sanitize_integer_range(
		$value,
		int $minimum,
		int $maximum,
		int $default
	): int {

		$value = absint( $value );

		if (
			$value < $minimum ||
			$value > $maximum
		) {
			return $default;
		}

		return $value;
	}

	/**
	 * Sanitize Combo Offer selection mode.
	 *
	 * single:
	 * Customer can select one Combo Offer.
	 *
	 * multiple:
	 * Customer can select multiple Combo Offers.
	 *
	 * @param mixed $selection Selection mode.
	 *
	 * @return string
	 */
	private function sanitize_combo_selection_mode(
		$selection
	): string {

		$selection =
			sanitize_key(
				(string) $selection
			);

		$allowed =
			array(
				'single',
				'multiple',
			);

		if (
			! in_array(
				$selection,
				$allowed,
				true
			)
		) {
			return 'multiple';
		}

		return $selection;
	}

	/**
	 * Sanitize product title link target.
	 *
	 * same = current browser tab.
	 * new  = new browser tab.
	 *
	 * @param mixed $target Link target.
	 *
	 * @return string
	 */
	private function sanitize_link_target(
		$target
	): string {

		$target =
			sanitize_key(
				(string) $target
			);

		if (
			'new' ===
				$target
		) {
			return 'new';
		}

		return 'same';
	}

	/**
	 * Sanitize yes/no option.
	 *
	 * @param mixed $value Option value.
	 *
	 * @return string
	 */
	private function sanitize_yes_no(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return (
			'no' ===
				$value
		)
			? 'no'
			: 'yes';
	}
}
