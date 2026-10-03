<?php
/**
 * Product settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Products\Services\AttributeStyleResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Product Display and Product Gallery settings.
 *
 * Product settings keep their historical eilmo_cf_settings paths so existing
 * runtime readers, admin JavaScript, shortcodes and Elementor controls remain
 * backwards compatible. CheckoutSettings owns the single WordPress setting
 * registration and delegates Product sanitization to this class.
 */
final class ProductSettings {

	/**
	 * Product settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'eilmo_cf_settings';

	/**
	 * Product settings group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'eilmo_cf_settings_group';

	/**
	 * Get complete Product settings defaults.
	 *
	 * The historical nested keys are retained deliberately. This keeps the
	 * frontend transition small: only the option provider changes, while the
	 * existing product and gallery array paths remain stable.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {

		$defaults = array(
			'general'        => array(
				/* Retained for upgrades; product-linked galleries no longer need a master switch. */
				'product_gallery' => 'yes',
			),

			'single_product' => array(
				'visibility'           => 'visible',
				'layout'               => 'campaign',
				'media'                => 'gallery',
				'missing_image_behavior' => 'placeholder',
				'show_title'           => 'yes',
				'show_rating'          => 'yes',
				'show_price'           => 'yes',
				'show_description'     => 'yes',
				'show_stock'           => 'yes',
				'show_sale_badge'      => 'yes',
				'show_savings'         => 'no',
				'show_variations'      => 'yes',
				'variation_layout'     => 'sections',
				'variation_selection'  => 'single',
				'grid_show_price'      => 'yes',
				'grid_show_regular_price' => 'no',
				'grid_show_savings'    => 'no',
				'badge_attribute'      => '',
				'show_variation_badges' => 'yes',
				'default_selection'    => 'configured',
				'show_quantity'        => 'no',
				'summary_auto_add'     => 'yes',
				'show_selected_items'  => 'no',
				'selected_items_show_quantity' => 'yes',
				'show_summary_items'   => 'yes',
				'summary_items_show_quantity' => 'yes',
				'mobile_sticky_action' => 'yes',
				'attribute_styles'     => array(),
			),

			/*
			 * Shared labels mirror Single Product, but this independent namespace
			 * prevents either settings tab from changing the other mode.
			 */
			'multiple_products' => array(
				'product_selection'               => 'multiple',
				'default_product_selection'       => 'customer',
				'require_product_selection'       => 'yes',
				'allow_product_removal'            => 'yes',
				'show_product_number'              => 'yes',
				'media'                            => 'featured',
				'missing_image_behavior'           => 'placeholder',
				'show_title'                       => 'yes',
				'show_rating'                      => 'yes',
				'show_price'                       => 'yes',
				'show_description'                 => 'yes',
				'show_stock'                       => 'yes',
				'show_sale_badge'                  => 'yes',
				'show_savings'                     => 'no',
				'show_quantity'                    => 'yes',
				'select_on_quantity_change'        => 'yes',
				'use_woocommerce_quantity_limits'  => 'yes',
				'show_variations'                  => 'yes',
				'variation_layout'                 => 'grid',
				'variation_selection'              => 'single',
				'grid_show_price'                  => 'yes',
				'grid_show_regular_price'          => 'no',
				'grid_show_savings'                => 'no',
				'grid_show_stock'                  => 'no',
				'grid_show_quantity'               => 'yes',
				'badge_attribute'                  => '',
				'show_variation_badges'             => 'yes',
				'default_selection'                => 'customer',
				'summary_auto_add'                 => 'yes',
				'show_selected_items'              => 'yes',
				'selected_items_show_quantity'     => 'yes',
				'show_summary_items'               => 'yes',
				'summary_items_show_quantity'      => 'yes',
				'mobile_sticky_action'             => 'yes',
				'attribute_styles'                 => array(),
			),

			'checkout_display' => array(
				'products' => array(
					'show_thumbnail'            => 'yes',
					'show_title'                => 'yes',
					'show_description'          => 'yes',
					'show_price'                => 'yes',
					'show_quantity'             => 'no',
					'show_select_text'          => 'yes',
					'show_stock'                => 'yes',
					'show_sale_badge'           => 'yes',
					'title_link'                => 'yes',
					'title_link_target'         => 'same',
					'show_summary'              => 'yes',
					'group_columns_desktop'     => 3,
					'group_columns_tablet'      => 2,
					'group_columns_mobile'      => 2,
					'group_quantity_control'    => 'no',
					'group_image_ratio'         => '16-9',
					'group_image_fit'           => 'cover',
					'group_image_position'      => 'center',
					'variation_columns_desktop' => 3,
					'variation_columns_tablet'  => 2,
					'variation_columns_mobile'  => 2,
					'variation_badge_enabled'   => 'yes',
					'variation_badge_style'     => 'badge',
					'select_label'              => __(
						'Select',
						'eilmo-checkout-flow'
					),
					'selected_label'            => __(
						'Selected',
						'eilmo-checkout-flow'
					),
				),
			),

			'product_gallery' => array(
				'show_arrows'     => 'yes',
				'show_dots'       => 'yes',
				'show_thumbnails' => 'yes',
				'lightbox'        => 'yes',
				'loop'            => 'yes',
				'galleries'       => array(),
			),
		);

		/**
		 * Filters default Product settings.
		 *
		 * @param array<string, mixed> $defaults Default Product settings.
		 */
		$defaults = apply_filters(
			'eilmo_cf/product_settings_defaults',
			$defaults
		);

		return is_array( $defaults )
			? $defaults
			: array();
	}

	/**
	 * Get saved Product settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {

		$stored = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return self::merge_settings(
			self::get_defaults(),
			$stored
		);
	}

	/**
	 * Get Product Display settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_product_display(): array {

		$settings = self::get_settings();

		return isset( $settings['checkout_display']['products'] ) &&
			is_array( $settings['checkout_display']['products'] )
				? $settings['checkout_display']['products']
				: array();
	}

	/**
	 * Get Single Product defaults and presentation settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_single_product(): array {

		$settings = self::get_settings();

		return isset( $settings['single_product'] ) &&
			is_array( $settings['single_product'] )
				? $settings['single_product']
				: array();
	}

	/**
	 * Get Multiple Products defaults and presentation settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_multiple_products(): array {

		$settings = self::get_settings();

		return isset( $settings['multiple_products'] ) &&
			is_array( $settings['multiple_products'] )
				? $settings['multiple_products']
				: array();
	}

	/**
	 * Backwards-compatible alias for the global Checkout Style.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_product_style(): array {

		return CheckoutStyle::get();
	}

	/**
	 * Backwards-compatible alias for global Checkout Style variables.
	 *
	 * @param array<string, mixed>|null $style Optional style values.
	 *
	 * @return string
	 */
	public static function get_style_css_variables(
		?array $style = null
	): string {

		return CheckoutStyle::get_css_variables( $style );
	}

	/**
	 * Get Product Gallery settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_product_gallery(): array {

		$settings = self::get_settings();

		return isset( $settings['product_gallery'] ) &&
			is_array( $settings['product_gallery'] )
				? $settings['product_gallery']
				: array();
	}

	/**
	 * Determine whether Product Gallery is globally enabled.
	 *
	 * @return bool
	 */
	public static function is_gallery_enabled(): bool {

		return true;
	}

	/**
	 * Sanitize the complete Product settings payload.
	 *
	 * Tabs save independently. A section that was not submitted preserves its
	 * previously stored value.
	 *
	 * @param mixed $input Raw Product settings.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {

		$defaults = self::get_defaults();
		$stored   = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$sanitized = self::merge_settings(
			$defaults,
			$stored
		);

		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		if (
			isset( $stored['product_gallery']['galleries'] ) &&
			is_array( $stored['product_gallery']['galleries'] )
		) {
			$sanitized['product_gallery']['galleries'] =
				$stored['product_gallery']['galleries'];
		}

		if (
			array_key_exists( 'general', $input ) &&
			is_array( $input['general'] )
		) {
			$sanitized['general']['product_gallery'] =
				$this->sanitize_yes_no(
					$input['general']['product_gallery'] ??
						$defaults['general']['product_gallery'],
					$defaults['general']['product_gallery']
				);
		}

		if (
			isset( $input['checkout_display']['products'] ) &&
			is_array( $input['checkout_display']['products'] )
		) {
			$sanitized['checkout_display']['products'] =
				$this->sanitize_product_display(
					$input['checkout_display']['products'],
					$defaults['checkout_display']['products']
				);
		}

		if (
			array_key_exists( 'single_product', $input ) &&
			is_array( $input['single_product'] )
		) {
			$sanitized['single_product'] =
				$this->sanitize_single_product(
					$input['single_product'],
					$defaults['single_product']
				);
		}

		if (
			array_key_exists( 'multiple_products', $input ) &&
			is_array( $input['multiple_products'] )
		) {
			$sanitized['multiple_products'] =
				$this->sanitize_multiple_products(
					$input['multiple_products'],
					$defaults['multiple_products']
				);
		}

		if (
			array_key_exists( 'product_gallery', $input ) &&
			is_array( $input['product_gallery'] )
		) {
			$sanitized['product_gallery'] =
				$this->sanitize_product_gallery(
					$input['product_gallery'],
					$defaults['product_gallery']
				);
		}

		/**
		 * Filters sanitized Product settings before saving.
		 *
		 * @param array<string, mixed> $sanitized Sanitized Product settings.
		 * @param array<string, mixed> $input     Raw submitted settings.
		 * @param array<string, mixed> $stored    Previously stored settings.
		 */
		$sanitized = apply_filters(
			'eilmo_cf/product_settings_sanitized',
			$sanitized,
			$input,
			$stored
		);

		return is_array( $sanitized )
			? $sanitized
			: $stored;
	}

	/**
	 * Sanitize Product Display settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_product_display(
		array $input,
		array $defaults
	): array {

		$range = static function (
			$value,
			int $minimum,
			int $maximum,
			int $fallback
		): int {
			$value = is_numeric( $value )
				? (int) $value
				: $fallback;

			return max(
				$minimum,
				min( $maximum, $value )
			);
		};

		$text = static function ( $value, $fallback ): string {
			$value = sanitize_text_field( (string) $value );

			return '' !== $value
				? $value
				: sanitize_text_field( (string) $fallback );
		};

		return array(
			'show_thumbnail'            => $this->sanitize_yes_no( $input['show_thumbnail'] ?? $defaults['show_thumbnail'], $defaults['show_thumbnail'] ),
			'show_title'                => $this->sanitize_yes_no( $input['show_title'] ?? $defaults['show_title'], $defaults['show_title'] ),
			'show_description'          => $this->sanitize_yes_no( $input['show_description'] ?? $defaults['show_description'], $defaults['show_description'] ),
			'show_price'                => $this->sanitize_yes_no( $input['show_price'] ?? $defaults['show_price'], $defaults['show_price'] ),
			'show_quantity'             => $this->sanitize_yes_no( $input['show_quantity'] ?? $defaults['show_quantity'], $defaults['show_quantity'] ),
			'show_select_text'          => $this->sanitize_yes_no( $input['show_select_text'] ?? $defaults['show_select_text'], $defaults['show_select_text'] ),
			'show_stock'                => $this->sanitize_yes_no( $input['show_stock'] ?? $defaults['show_stock'], $defaults['show_stock'] ),
			'show_sale_badge'           => $this->sanitize_yes_no( $input['show_sale_badge'] ?? $defaults['show_sale_badge'], $defaults['show_sale_badge'] ),
			'title_link'                => $this->sanitize_yes_no( $input['title_link'] ?? $defaults['title_link'], $defaults['title_link'] ),
			'title_link_target'         => $this->sanitize_choice( $input['title_link_target'] ?? $defaults['title_link_target'], array( 'same', 'new' ), $defaults['title_link_target'] ),
			'show_summary'              => $this->sanitize_yes_no( $input['show_summary'] ?? $defaults['show_summary'], $defaults['show_summary'] ),
			'group_columns_desktop'     => $range( $input['group_columns_desktop'] ?? $defaults['group_columns_desktop'], 1, 6, 3 ),
			'group_columns_tablet'      => $range( $input['group_columns_tablet'] ?? $defaults['group_columns_tablet'], 1, 6, 2 ),
			'group_columns_mobile'      => $range( $input['group_columns_mobile'] ?? $defaults['group_columns_mobile'], 1, 4, 2 ),
			'group_quantity_control'    => $this->sanitize_yes_no( $input['group_quantity_control'] ?? $defaults['group_quantity_control'], $defaults['group_quantity_control'] ),
			'group_image_ratio'         => $this->sanitize_choice( $input['group_image_ratio'] ?? $defaults['group_image_ratio'], array( '1-1', '4-3', '3-2', '16-9' ), $defaults['group_image_ratio'] ),
			'group_image_fit'           => $this->sanitize_choice( $input['group_image_fit'] ?? $defaults['group_image_fit'], array( 'cover', 'contain' ), $defaults['group_image_fit'] ),
			'group_image_position'      => $this->sanitize_choice( $input['group_image_position'] ?? $defaults['group_image_position'], array( 'center', 'top', 'bottom', 'left', 'right' ), $defaults['group_image_position'] ),
			'variation_columns_desktop' => $range( $input['variation_columns_desktop'] ?? $defaults['variation_columns_desktop'], 1, 6, 3 ),
			'variation_columns_tablet'  => $range( $input['variation_columns_tablet'] ?? $defaults['variation_columns_tablet'], 1, 6, 2 ),
			'variation_columns_mobile'  => $range( $input['variation_columns_mobile'] ?? $defaults['variation_columns_mobile'], 1, 4, 2 ),
			'variation_badge_enabled'   => $this->sanitize_yes_no( $input['variation_badge_enabled'] ?? $defaults['variation_badge_enabled'], $defaults['variation_badge_enabled'] ),
			'variation_badge_style'     => $this->sanitize_choice( $input['variation_badge_style'] ?? $defaults['variation_badge_style'], array( 'badge', 'plain' ), $defaults['variation_badge_style'] ),
			'select_label'              => $text( $input['select_label'] ?? $defaults['select_label'], $defaults['select_label'] ),
			'selected_label'            => $text( $input['selected_label'] ?? $defaults['selected_label'], $defaults['selected_label'] ),
		);
	}

	/**
	 * Sanitize Single Product settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_single_product(
		array $input,
		array $defaults
	): array {

		$attribute_styles = ( new AttributeStyleResolver() )->sanitize(
			$input['attribute_styles'] ?? $defaults['attribute_styles'] ?? array()
		);

		$variation_layout = $this->sanitize_choice(
			$input['variation_layout'] ?? $defaults['variation_layout'],
			array( 'sections', 'grid' ),
			$defaults['variation_layout']
		);
		$variation_selection = $this->sanitize_choice(
			$input['variation_selection'] ?? $defaults['variation_selection'],
			array( 'single', 'multiple' ),
			$defaults['variation_selection']
		);

		/* Multiple exact variations are only meaningful in Compact Grid. */
		if ( 'grid' !== $variation_layout ) {
			$variation_selection = 'single';
		}

		return array(
			'visibility'           => $this->sanitize_choice( $input['visibility'] ?? $defaults['visibility'], array( 'visible', 'hidden' ), $defaults['visibility'] ),
			'layout'               => $this->sanitize_choice( $input['layout'] ?? $defaults['layout'], array( 'campaign', 'compact' ), $defaults['layout'] ),
			'media'                => $this->sanitize_choice( $input['media'] ?? $defaults['media'], array( 'gallery', 'featured', 'hidden' ), $defaults['media'] ),
			'missing_image_behavior' => $this->sanitize_choice( $input['missing_image_behavior'] ?? $defaults['missing_image_behavior'], array( 'placeholder', 'hide' ), $defaults['missing_image_behavior'] ),
			'show_title'           => $this->sanitize_yes_no( $input['show_title'] ?? $defaults['show_title'], $defaults['show_title'] ),
			'show_rating'          => $this->sanitize_yes_no( $input['show_rating'] ?? $defaults['show_rating'], $defaults['show_rating'] ),
			'show_price'           => $this->sanitize_yes_no( $input['show_price'] ?? $defaults['show_price'], $defaults['show_price'] ),
			'show_description'     => $this->sanitize_yes_no( $input['show_description'] ?? $defaults['show_description'], $defaults['show_description'] ),
			'show_stock'           => $this->sanitize_yes_no( $input['show_stock'] ?? $defaults['show_stock'], $defaults['show_stock'] ),
			'show_sale_badge'      => $this->sanitize_yes_no( $input['show_sale_badge'] ?? $defaults['show_sale_badge'], $defaults['show_sale_badge'] ),
			'show_savings'         => $this->sanitize_yes_no( $input['show_savings'] ?? $defaults['show_savings'], $defaults['show_savings'] ),
			'show_variations'      => $this->sanitize_yes_no( $input['show_variations'] ?? $defaults['show_variations'], $defaults['show_variations'] ),
			'variation_layout'     => $variation_layout,
			'variation_selection'  => $variation_selection,
			'grid_show_price'      => $this->sanitize_yes_no( $input['grid_show_price'] ?? $defaults['grid_show_price'], $defaults['grid_show_price'] ),
			'grid_show_regular_price' => $this->sanitize_yes_no( $input['grid_show_regular_price'] ?? $defaults['grid_show_regular_price'], $defaults['grid_show_regular_price'] ),
			'grid_show_savings'    => $this->sanitize_yes_no( $input['grid_show_savings'] ?? $defaults['grid_show_savings'], $defaults['grid_show_savings'] ),
			'badge_attribute'      => sanitize_key( (string) ( $input['badge_attribute'] ?? $defaults['badge_attribute'] ?? '' ) ),
			'show_variation_badges' => $this->sanitize_yes_no( $input['show_variation_badges'] ?? $defaults['show_variation_badges'], $defaults['show_variation_badges'] ),
			'default_selection'    => $this->sanitize_choice( $input['default_selection'] ?? $defaults['default_selection'], array( 'configured', 'first_available', 'customer' ), $defaults['default_selection'] ),
			'show_quantity'        => $this->sanitize_yes_no( $input['show_quantity'] ?? $defaults['show_quantity'], $defaults['show_quantity'] ),
			'summary_auto_add'     => $this->sanitize_yes_no( $input['summary_auto_add'] ?? $defaults['summary_auto_add'], $defaults['summary_auto_add'] ),
			'show_selected_items'  => $this->sanitize_yes_no( $input['show_selected_items'] ?? $defaults['show_selected_items'], $defaults['show_selected_items'] ),
			'selected_items_show_quantity' => $this->sanitize_yes_no( $input['selected_items_show_quantity'] ?? $defaults['selected_items_show_quantity'], $defaults['selected_items_show_quantity'] ),
			'show_summary_items'   => $this->sanitize_yes_no( $input['show_summary_items'] ?? $defaults['show_summary_items'], $defaults['show_summary_items'] ),
			'summary_items_show_quantity' => $this->sanitize_yes_no( $input['summary_items_show_quantity'] ?? $defaults['summary_items_show_quantity'], $defaults['summary_items_show_quantity'] ),
			'mobile_sticky_action' => $this->sanitize_yes_no( $input['mobile_sticky_action'] ?? $defaults['mobile_sticky_action'], $defaults['mobile_sticky_action'] ),
			'attribute_styles'     => $attribute_styles,
		);
	}

	/**
	 * Sanitize Multiple Products settings in their independent namespace.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_multiple_products(
		array $input,
		array $defaults
	): array {

		$attribute_styles = ( new AttributeStyleResolver() )->sanitize(
			$input['attribute_styles'] ?? $defaults['attribute_styles'] ?? array()
		);

		$variation_layout = $this->sanitize_choice(
			$input['variation_layout'] ?? $defaults['variation_layout'],
			array( 'sections', 'grid' ),
			$defaults['variation_layout']
		);

		$variation_selection = $this->sanitize_choice(
			$input['variation_selection'] ?? $defaults['variation_selection'],
			array( 'single', 'multiple' ),
			$defaults['variation_selection']
		);

		/* Multiple exact variations are only meaningful in Compact Grid. */
		if ( 'grid' !== $variation_layout ) {
			$variation_selection = 'single';
		}

		return array(
			'product_selection'              => $this->sanitize_choice( $input['product_selection'] ?? $defaults['product_selection'], array( 'single', 'multiple' ), $defaults['product_selection'] ),
			'default_product_selection'      => $this->sanitize_choice( $input['default_product_selection'] ?? $defaults['default_product_selection'], array( 'customer', 'first_available', 'all_available' ), $defaults['default_product_selection'] ),
			'require_product_selection'      => 'yes',
			'allow_product_removal'           => $this->sanitize_yes_no( $input['allow_product_removal'] ?? $defaults['allow_product_removal'], $defaults['allow_product_removal'] ),
			'show_product_number'             => $this->sanitize_yes_no( $input['show_product_number'] ?? $defaults['show_product_number'], $defaults['show_product_number'] ),
			'media'                           => $this->sanitize_choice( $input['media'] ?? $defaults['media'], array( 'gallery', 'featured', 'hidden' ), $defaults['media'] ),
			'missing_image_behavior'          => $this->sanitize_choice( $input['missing_image_behavior'] ?? $defaults['missing_image_behavior'], array( 'placeholder', 'hide' ), $defaults['missing_image_behavior'] ),
			'show_title'                      => $this->sanitize_yes_no( $input['show_title'] ?? $defaults['show_title'], $defaults['show_title'] ),
			'show_rating'                     => $this->sanitize_yes_no( $input['show_rating'] ?? $defaults['show_rating'], $defaults['show_rating'] ),
			'show_price'                      => $this->sanitize_yes_no( $input['show_price'] ?? $defaults['show_price'], $defaults['show_price'] ),
			'show_description'                => $this->sanitize_yes_no( $input['show_description'] ?? $defaults['show_description'], $defaults['show_description'] ),
			'show_stock'                      => $this->sanitize_yes_no( $input['show_stock'] ?? $defaults['show_stock'], $defaults['show_stock'] ),
			'show_sale_badge'                 => $this->sanitize_yes_no( $input['show_sale_badge'] ?? $defaults['show_sale_badge'], $defaults['show_sale_badge'] ),
			'show_savings'                    => $this->sanitize_yes_no( $input['show_savings'] ?? $defaults['show_savings'], $defaults['show_savings'] ),
			'show_quantity'                   => $this->sanitize_yes_no( $input['show_quantity'] ?? $defaults['show_quantity'], $defaults['show_quantity'] ),
			'select_on_quantity_change'       => 'yes',
			'use_woocommerce_quantity_limits' => 'yes',
			'show_variations'                 => $this->sanitize_yes_no( $input['show_variations'] ?? $defaults['show_variations'], $defaults['show_variations'] ),
			'variation_layout'                => $variation_layout,
			'variation_selection'             => $variation_selection,
			'grid_show_price'                 => $this->sanitize_yes_no( $input['grid_show_price'] ?? $defaults['grid_show_price'], $defaults['grid_show_price'] ),
			'grid_show_regular_price'         => $this->sanitize_yes_no( $input['grid_show_regular_price'] ?? $defaults['grid_show_regular_price'], $defaults['grid_show_regular_price'] ),
			'grid_show_savings'               => $this->sanitize_yes_no( $input['grid_show_savings'] ?? $defaults['grid_show_savings'], $defaults['grid_show_savings'] ),
			'grid_show_stock'                 => $this->sanitize_yes_no( $input['grid_show_stock'] ?? $defaults['grid_show_stock'], $defaults['grid_show_stock'] ),
			'grid_show_quantity'              => $this->sanitize_yes_no( $input['grid_show_quantity'] ?? $defaults['grid_show_quantity'], $defaults['grid_show_quantity'] ),
			'badge_attribute'                 => sanitize_key( (string) ( $input['badge_attribute'] ?? $defaults['badge_attribute'] ?? '' ) ),
			'show_variation_badges'            => $this->sanitize_yes_no( $input['show_variation_badges'] ?? $defaults['show_variation_badges'], $defaults['show_variation_badges'] ),
			'default_selection'               => $this->sanitize_choice( $input['default_selection'] ?? $defaults['default_selection'], array( 'configured', 'first_available', 'customer' ), $defaults['default_selection'] ),
			'summary_auto_add'                => $this->sanitize_yes_no( $input['summary_auto_add'] ?? $defaults['summary_auto_add'], $defaults['summary_auto_add'] ),
			'show_selected_items'             => $this->sanitize_yes_no( $input['show_selected_items'] ?? $defaults['show_selected_items'], $defaults['show_selected_items'] ),
			'selected_items_show_quantity'    => $this->sanitize_yes_no( $input['selected_items_show_quantity'] ?? $defaults['selected_items_show_quantity'], $defaults['selected_items_show_quantity'] ),
			'show_summary_items'              => $this->sanitize_yes_no( $input['show_summary_items'] ?? $defaults['show_summary_items'], $defaults['show_summary_items'] ),
			'summary_items_show_quantity'     => $this->sanitize_yes_no( $input['summary_items_show_quantity'] ?? $defaults['summary_items_show_quantity'], $defaults['summary_items_show_quantity'] ),
			'mobile_sticky_action'            => $this->sanitize_yes_no( $input['mobile_sticky_action'] ?? $defaults['mobile_sticky_action'], $defaults['mobile_sticky_action'] ),
			'attribute_styles'                => $attribute_styles,
		);
	}

	/**
	 * Backwards-compatible sanitizer alias for Checkout Style.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_product_style(
		array $input,
		array $defaults
	): array {

		return CheckoutStyle::sanitize( $input, $defaults );
	}

	/**
	 * Sanitize Product Gallery settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_product_gallery(
		array $input,
		array $defaults
	): array {

		$galleries = array();

		if (
			isset( $input['galleries'] ) &&
			is_array( $input['galleries'] )
		) {
			$galleries = $this->sanitize_product_gallery_library(
				$input['galleries']
			);
		}

		return array(
			'show_arrows'     => $this->sanitize_yes_no( $input['show_arrows'] ?? $defaults['show_arrows'], $defaults['show_arrows'] ),
			'show_dots'       => $this->sanitize_yes_no( $input['show_dots'] ?? $defaults['show_dots'], $defaults['show_dots'] ),
			'show_thumbnails' => $this->sanitize_yes_no( $input['show_thumbnails'] ?? $defaults['show_thumbnails'], $defaults['show_thumbnails'] ),
			'lightbox'        => $this->sanitize_yes_no( $input['lightbox'] ?? $defaults['lightbox'], $defaults['lightbox'] ),
			'loop'            => $this->sanitize_yes_no( $input['loop'] ?? $defaults['loop'], $defaults['loop'] ),
			'galleries'       => $galleries,
		);
	}

	/**
	 * Sanitize reusable Product Gallery library.
	 *
	 * Gallery IDs are generated from Gallery Name. Duplicate generated IDs
	 * receive a numeric suffix.
	 *
	 * @param array<mixed> $galleries Galleries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_product_gallery_library(
		array $galleries
	): array {

		$sanitized = array();
		$used_ids  = array();

		foreach ( $galleries as $index => $gallery ) {

			if ( ! is_array( $gallery ) ) {
				continue;
			}

			$name = sanitize_text_field(
				(string) ( $gallery['name'] ?? '' )
			);

			if ( '' === $name ) {
				continue;
			}

			$id = sanitize_key( sanitize_title( $name ) );

			if ( '' === $id ) {
				$id = sprintf(
					'product-gallery-%d',
					absint( $index ) + 1
				);
			}

			$id         = $this->make_unique_id( $id, $used_ids );
			$used_ids[] = $id;

			$product_id = absint( $gallery['product_id'] ?? 0 );

			/* Every reusable gallery must belong to one concrete product. */
			if ( $product_id <= 0 ) {
				continue;
			}

			$image_ids_raw = $gallery['image_ids'] ?? array();

			if ( is_string( $image_ids_raw ) ) {
				$image_ids_raw = preg_split(
					'/[\s,]+/',
					$image_ids_raw
				);
			}

			$image_ids = array();

			if ( is_array( $image_ids_raw ) ) {
				foreach ( $image_ids_raw as $image_id ) {
					$image_id = absint( $image_id );

					if ( $image_id <= 0 ) {
						continue;
					}

					if (
						'attachment' !== get_post_type( $image_id ) ||
						! wp_attachment_is_image( $image_id )
					) {
						continue;
					}

					$image_ids[] = $image_id;
				}
			}

			$image_ids = array_values(
				array_unique( $image_ids )
			);

			if ( empty( $image_ids ) ) {
				continue;
			}

			$sanitized[] = array(
				'id'         => $id,
				'name'       => $name,
				'scope'      => 'product',
				'product_id' => $product_id,
				'image_ids'  => $image_ids,
				'enabled'    => $this->sanitize_yes_no(
					$gallery['enabled'] ?? 'yes',
					'yes'
				),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize yes/no setting.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_yes_no(
		$value,
		string $fallback = 'no'
	): string {

		if ( 'yes' === $value ) {
			return 'yes';
		}

		if ( 'no' === $value ) {
			return 'no';
		}

		return 'yes' === $fallback
			? 'yes'
			: 'no';
	}

	/**
	 * Sanitize value against allowed choices.
	 *
	 * @param mixed         $value    Value.
	 * @param array<string> $allowed  Allowed values.
	 * @param string        $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_choice(
		$value,
		array $allowed,
		string $fallback
	): string {

		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true )
			? $value
			: $fallback;
	}

	/**
	 * Sanitize an integer within an inclusive range.
	 *
	 * @param mixed $value    Value.
	 * @param int   $minimum  Minimum.
	 * @param int   $maximum  Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function sanitize_range(
		$value,
		int $minimum,
		int $maximum,
		int $fallback
	): int {

		$value = is_numeric( $value )
			? (int) $value
			: $fallback;

		return max( $minimum, min( $maximum, $value ) );
	}

	/**
	 * Make a generated ID unique.
	 *
	 * @param string        $id       Requested ID.
	 * @param array<string> $used_ids Existing IDs.
	 *
	 * @return string
	 */
	private function make_unique_id(
		string $id,
		array $used_ids
	): string {

		if ( ! in_array( $id, $used_ids, true ) ) {
			return $id;
		}

		$base    = $id;
		$counter = 2;

		do {
			$id = sprintf(
				'%1$s-%2$d',
				$base,
				$counter
			);

			++$counter;
		} while ( in_array( $id, $used_ids, true ) );

		return $id;
	}

	/**
	 * Recursively merge associative settings.
	 *
	 * Indexed collections replace defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored.
	 *
	 * @return array<string, mixed>
	 */
	private static function merge_settings(
		array $defaults,
		array $stored
	): array {

		foreach ( $stored as $key => $value ) {

			if (
				isset( $defaults[ $key ] ) &&
				is_array( $defaults[ $key ] ) &&
				is_array( $value ) &&
				self::is_associative( $defaults[ $key ] ) &&
				self::is_associative( $value )
			) {
				$defaults[ $key ] = self::merge_settings(
					$defaults[ $key ],
					$value
				);

				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Determine whether an array is associative.
	 *
	 * @param array<mixed> $array Array.
	 *
	 * @return bool
	 */
	private static function is_associative(
		array $array
	): bool {

		if ( empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !== range(
			0,
			count( $array ) - 1
		);
	}
}
