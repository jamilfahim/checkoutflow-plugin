<?php
/**
 * Single Product frontend renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\ProductGallery\Rendering\ProductGalleryRenderer;
use EilmoCheckout\Products\Services\AttributeStyleResolver;
use EilmoCheckout\Products\Services\DefaultVariationResolver;
use EilmoCheckout\Products\Services\SingleProductConfig;
use EilmoCheckout\Products\Services\VariationBadgeService;
use EilmoCheckout\Products\Services\VariationResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one Simple or Variable parent product for campaign checkouts.
 *
 * Variable children remain orderable WooCommerce variations, but presentation
 * is consolidated into one product card and compact option controls.
 */
final class SingleProductRenderer {

	/** @var SingleProductConfig */
	private $config;

	/** @var VariationResolver */
	private $variation_resolver;

	/** @var DefaultVariationResolver */
	private $default_variation_resolver;

	/** @var VariationBadgeService */
	private $variation_badge_service;

	/** @var AttributeStyleResolver */
	private $attribute_style_resolver;

	/**
	 * Constructor.
	 *
	 * @param SingleProductConfig|null       $config                     Configuration.
	 * @param VariationResolver|null         $variation_resolver         Variation resolver.
	 * @param DefaultVariationResolver|null  $default_variation_resolver Default resolver.
	 * @param VariationBadgeService|null     $variation_badge_service    Badge service.
	 * @param AttributeStyleResolver|null    $attribute_style_resolver   Attribute styles.
	 */
	public function __construct(
		?SingleProductConfig $config = null,
		?VariationResolver $variation_resolver = null,
		?DefaultVariationResolver $default_variation_resolver = null,
		?VariationBadgeService $variation_badge_service = null,
		?AttributeStyleResolver $attribute_style_resolver = null
	) {

		$this->config = $config ?? new SingleProductConfig();
		$this->variation_resolver = $variation_resolver ?? new VariationResolver();
		$this->default_variation_resolver =
			$default_variation_resolver ?? new DefaultVariationResolver( $this->variation_resolver );
		$this->variation_badge_service =
			$variation_badge_service ?? new VariationBadgeService();
		$this->attribute_style_resolver =
			$attribute_style_resolver ?? new AttributeStyleResolver();
	}

	/**
	 * Render one parent product.
	 *
	 * @param \WC_Product          $product  Parent product.
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
	public function render(
		\WC_Product $product,
		array $settings = array()
	): string {

		if ( ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return '';
		}

		$overrides = isset( $settings['single_product'] ) &&
			is_array( $settings['single_product'] )
				? $settings['single_product']
				: array();

		$single_product = $this->config->get( $overrides );

		/* Lightweight checkout-selector controls intentionally live in Elementor,
		 * not in the removed Products admin screen. Keep them outside the legacy
		 * ProductSettings schema so old saved settings remain untouched. */
		$single_product['show_checkout_selector'] =
			'no' === (string) ( $overrides['show_checkout_selector'] ?? 'yes' ) ? 'no' : 'yes';
		$single_product['max_visible_variations'] = max(
			1,
			min( 12, absint( $overrides['max_visible_variations'] ?? 4 ) )
		);
		foreach ( array( 'show_package_title', 'show_package_helper', 'show_selected_quantity', 'show_variation_descriptions', 'show_description', 'show_checkout_price', 'show_checkout_regular_price', 'show_checkout_savings' ) as $visibility_key ) {
			if ( array_key_exists( $visibility_key, $overrides ) ) {
				$single_product[ $visibility_key ] = 'no' === (string) $overrides[ $visibility_key ] ? 'no' : 'yes';
			}
		}

		if ( empty( $single_product ) ) {
			return '';
		}

		$settings['single_product'] = $single_product;
		$variations                = array();
		$variation_rows            = array();
		$attribute_groups          = array();
		$default_variation_id      = 0;

		if ( $product instanceof \WC_Product_Variable ) {
			$variations = $this->variation_resolver->get_variations( $product );

			if ( empty( $variations ) ) {
				return '';
			}

			$default_variation = $this->default_variation_resolver->resolve(
				$product,
				(string) ( $single_product['default_selection'] ?? 'configured' )
			);

			/*
			 * A single available child does not need a customer decision. When the
			 * controls are intentionally hidden, also resolve a safe fallback so the
			 * checkout never displays an order form that cannot select a product.
			 */
			if (
				! $default_variation &&
				(
					1 === count( $variations ) ||
					'no' === (string) ( $single_product['show_variations'] ?? 'yes' )
				)
			) {
				$default_variation = $this->default_variation_resolver->resolve(
					$product,
					'first_available'
				);
			}

			$default_variation_id = $default_variation
				? $default_variation->get_id()
				: 0;

			$variation_rows   = $this->prepare_variation_rows( $product, $variations );
			$attribute_groups = $this->prepare_attribute_groups(
				$product,
				$variation_rows,
				$single_product
			);
		}

		$product_gallery_html = '';

		if ( 'gallery' === (string) ( $single_product['media'] ?? 'gallery' ) ) {
			$product_gallery_html = $this->render_product_gallery(
				$product->get_id(),
				$settings
			);
		}

		$media_images = $this->prepare_media_images(
			$product,
			(string) ( $single_product['media'] ?? 'gallery' ),
			(string) ( $single_product['missing_image_behavior'] ?? 'placeholder' )
		);

		$template = EILMO_CF_PATH . 'templates/products/single-product.php';

		/**
		 * Filters the Single Product campaign template path.
		 *
		 * @param string               $template       Template path.
		 * @param \WC_Product          $product        Parent product.
		 * @param array<string, mixed> $settings       Checkout settings.
		 * @param array<string, mixed> $single_product Resolved configuration.
		 */
		$template = (string) apply_filters(
			'eilmo_cf/single_product_template',
			$template,
			$product,
			$settings,
			$single_product
		);

		if ( ! file_exists( $template ) ) {
			return '';
		}

		/**
		 * Fires before the historical individual-product boundary.
		 *
		 * @param \WC_Product          $product  Product.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		do_action( 'eilmo_cf/before_product', $product, $settings );

		ob_start();
		require $template;
		$output = ob_get_clean();

		/**
		 * Fires after the historical individual-product boundary.
		 *
		 * @param \WC_Product          $product  Product.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		do_action( 'eilmo_cf/after_product', $product, $settings );

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters the new consolidated Single Product HTML.
		 *
		 * @param string               $output         Rendered HTML.
		 * @param \WC_Product          $product        Product.
		 * @param array<string, mixed> $settings       Checkout settings.
		 * @param array<string, mixed> $single_product Configuration.
		 */
		return (string) apply_filters(
			'eilmo_cf/single_product_html',
			$output,
			$product,
			$settings,
			$single_product
		);
	}

	/**
	 * Prepare exact variation rows for markup and safe frontend matching.
	 *
	 * @param \WC_Product_Variable              $product    Parent product.
	 * @param array<int, \WC_Product_Variation> $variations Variations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_variation_rows(
		\WC_Product_Variable $product,
		array $variations
	): array {

		$rows = array();

		foreach ( $variations as $variation ) {
			if ( ! $variation instanceof \WC_Product_Variation ) {
				continue;
			}

			$attributes = array();

			foreach ( $variation->get_attributes() as $attribute => $value ) {
				$attributes[ sanitize_title( (string) $attribute ) ] = (string) $value;
			}

			$image_id = absint(
				$variation->get_image_id() ?: $product->get_image_id()
			);
			$badge    = $this->variation_badge_service->get_badge( $variation );
			$can_purchase = $variation->is_purchasable() && $variation->is_in_stock();
			$maximum_quantity = $variation->get_max_purchase_quantity();
			$current_price = (float) $variation->get_price();
			$regular_price = (float) $variation->get_regular_price();
			$saving_amount = $regular_price > $current_price
				? max( 0.0, $regular_price - $current_price )
				: 0.0;

			if ( $variation->is_sold_individually() ) {
				$maximum_quantity = 1;
			}

			$rows[] = array(
				'variation'       => $variation,
				'id'              => $variation->get_id(),
				'label'           => $this->get_variation_label( $variation ),
				'description'     => wp_strip_all_tags( (string) $variation->get_description() ),
				'attributes'      => $attributes,
				'price'           => wc_format_decimal( $variation->get_price() ),
				'price_html'      => wp_kses_post( $variation->get_price_html() ),
				'current_price_html' => wp_kses_post( wc_price( $current_price ) ),
				'regular_price_html' => $regular_price > $current_price
					? wp_kses_post( wc_price( $regular_price ) )
					: '',
				'saving_amount'   => wc_format_decimal( $saving_amount ),
				'saving_html'     => $saving_amount > 0
					? wp_kses_post(
						sprintf(
							/* translators: %s: Formatted amount saved. */
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Save %s' ),
							wc_price( $saving_amount )
						)
					)
					: '',
				'image'           => $this->get_image_data( $image_id ),
				'summary_image'   => $this->get_summary_image_url( $image_id ),
				'stock_html'      => wp_kses_post( wc_get_stock_html( $variation ) ),
				'stock_status'    => $variation->get_stock_status(),
				'stock_quantity'  => $variation->get_stock_quantity(),
				'maximum_quantity'=> $maximum_quantity,
				'can_purchase'    => $can_purchase,
				'on_sale'         => $variation->is_on_sale(),
				'badge'           => $badge,
			);
		}

		return $rows;
	}

	/**
	 * Prepare the parent product's attribute/option groups.
	 *
	 * @param \WC_Product_Variable          $product        Parent product.
	 * @param array<int, array<string,mixed>> $variation_rows Prepared variations.
	 * @param array<string,mixed>             $settings       Single Product settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_attribute_groups(
		\WC_Product_Variable $product,
		array $variation_rows,
		array $settings
	): array {

		$groups = array();

		foreach ( $product->get_variation_attributes() as $attribute => $options ) {
			$attribute_key = sanitize_title( (string) $attribute );
			$option_rows   = array();
			$presentation = $this->attribute_style_resolver->resolve(
				$attribute_key,
				$settings['attribute_styles'] ?? array()
			);

			foreach ( (array) $options as $option ) {
				$option = (string) $option;
				$pricing = $this->get_attribute_option_pricing(
					$attribute_key,
					$option,
					$variation_rows
				);

				$option_rows[] = array(
					'value'       => $option,
					'label'       => $this->get_option_label( (string) $attribute, $option ),
					'image'       => $this->get_attribute_option_image(
						$attribute_key,
						$option,
						$variation_rows
					),
					'price_html'  => (string) $pricing['price_html'],
					'description' => (string) ( $pricing['description'] ?? '' ),
					'regular_price_html' => (string) $pricing['regular_price_html'],
					'saving_html' => (string) $pricing['saving_html'],
					'badge'       => isset( $pricing['badge'] ) && is_array( $pricing['badge'] ) ? $pricing['badge'] : array(),
				);
			}

			if ( empty( $option_rows ) ) {
				continue;
			}

			$groups[] = array(
				'key'          => $attribute_key,
				'label'        => wc_attribute_label( (string) $attribute, $product ),
				'options'      => $option_rows,
				'style'        => (string) $presentation['style'],
				'columns'      => (int) $presentation['columns'],
				'show_price'   => (string) $presentation['show_price'],
				'show_regular_price' => (string) $presentation['show_regular_price'],
				'show_savings' => (string) $presentation['show_savings'],
				'show_badge'   => 'yes' === (string) ( $settings['show_variation_badges'] ?? 'yes' ) &&
					$attribute_key === sanitize_key( (string) ( $settings['badge_attribute'] ?? '' ) ) ? 'yes' : 'no',
			);
		}

		return $groups;
	}

	/**
	 * Resolve fallback exact price/saving copy for one attribute option.
	 *
	 * Browser-side contextual pricing replaces this fallback as soon as the
	 * current attribute selection is known. Ranges are deliberately avoided.
	 *
	 * @param string                           $attribute      Attribute key.
	 * @param string                           $option         Option value.
	 * @param array<int, array<string, mixed>> $variation_rows Variation rows.
	 *
	 * @return array{price_html:string,description:string,regular_price_html:string,saving_html:string,badge:array<string,mixed>}
	 */
	private function get_attribute_option_pricing(
		string $attribute,
		string $option,
		array $variation_rows
	): array {

		foreach ( $variation_rows as $row ) {
			$attributes = isset( $row['attributes'] ) && is_array( $row['attributes'] )
				? $row['attributes']
				: array();
			$row_value = (string) ( $attributes[ $attribute ] ?? '' );

			if (
				empty( $row['can_purchase'] ) ||
				( '' !== $row_value && $row_value !== $option )
			) {
				continue;
			}

			return array(
				'price_html'         => (string) ( $row['current_price_html'] ?? '' ),
				'description'        => (string) ( $row['description'] ?? '' ),
				'regular_price_html' => (string) ( $row['regular_price_html'] ?? '' ),
				'saving_html'        => (string) ( $row['saving_html'] ?? '' ),
				'badge'              => isset( $row['badge'] ) && is_array( $row['badge'] ) ? $row['badge'] : array(),
			);
		}

		return array(
			'price_html'         => '',
			'description'        => '',
			'regular_price_html' => '',
			'saving_html'        => '',
			'badge'              => array(),
		);
	}

	/**
	 * Resolve a representative variation image for one attribute option.
	 *
	 * @param string                           $attribute      Attribute key.
	 * @param string                           $option         Option value.
	 * @param array<int, array<string, mixed>> $variation_rows Variation rows.
	 *
	 * @return array<string, mixed>
	 */
	private function get_attribute_option_image(
		string $attribute,
		string $option,
		array $variation_rows
	): array {

		foreach ( $variation_rows as $row ) {
			$attributes = isset( $row['attributes'] ) && is_array( $row['attributes'] )
				? $row['attributes']
				: array();
			$image = isset( $row['image'] ) && is_array( $row['image'] )
				? $row['image']
				: array();

			if (
				(string) ( $attributes[ $attribute ] ?? '' ) === $option &&
				'' !== (string) ( $image['display_url'] ?? '' )
			) {
				return $image;
			}
		}

		return array();
	}

	/**
	 * Resolve a human-readable attribute option label.
	 *
	 * @param string $attribute Attribute taxonomy/name.
	 * @param string $option    Stored option value.
	 *
	 * @return string
	 */
	private function get_option_label(
		string $attribute,
		string $option
	): string {

		if ( taxonomy_exists( $attribute ) ) {
			$term = get_term_by( 'slug', $option, $attribute );

			if ( $term instanceof \WP_Term ) {
				return $term->name;
			}
		}

		return $option;
	}

	/**
	 * Get a concise exact-variation label.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 *
	 * @return string
	 */
	private function get_variation_label(
		\WC_Product_Variation $variation
	): string {

		$label = wc_get_formatted_variation( $variation, true, false, false );

		return '' !== $label
			? wp_strip_all_tags( $label )
			: $variation->get_name();
	}

	/**
	 * Prepare native featured/gallery images used when no curated gallery is assigned.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $mode             gallery|featured|hidden.
	 * @param string      $missing_behavior placeholder|hide.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_media_images(
		\WC_Product $product,
		string $mode,
		string $missing_behavior = 'placeholder'
	): array {

		if ( 'hidden' === $mode ) {
			return array();
		}

		$image_ids = array_filter( array( $product->get_image_id() ) );

		if ( 'gallery' === $mode ) {
			$image_ids = array_merge( $image_ids, $product->get_gallery_image_ids() );
		}

		$images = array();

		foreach ( array_values( array_unique( array_map( 'absint', $image_ids ) ) ) as $image_id ) {
			$image = $this->get_image_data( $image_id );

			if ( ! empty( $image['display_url'] ) ) {
				$images[] = $image;
			}
		}

		if ( empty( $images ) && 'hide' !== $missing_behavior ) {
			$images[] = array(
				'id'          => 0,
				'display_url' => wc_placeholder_img_src( 'woocommerce_single' ),
				'full_url'    => wc_placeholder_img_src( 'woocommerce_single' ),
				'thumb_url'   => wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'alt'         => $product->get_name(),
			);
		}

		return $images;
	}

	/**
	 * Resolve display, full and thumbnail URLs for an attachment.
	 *
	 * @param mixed $image_id Attachment ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_image_data( $image_id ): array {

		$image_id = absint( $image_id );

		$display = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : '';
		$full    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$thumb   = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		return array(
			'id'          => $image_id,
			'display_url' => is_string( $display ) ? $display : '',
			'full_url'    => is_string( $full ) ? $full : '',
			'thumb_url'   => is_string( $thumb ) ? $thumb : '',
			'alt'         => $image_id
				? sanitize_text_field( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) )
				: '',
		);
	}

	/**
	 * Resolve the image used in Order Summary.
	 *
	 * @param mixed $image_id Attachment ID.
	 *
	 * @return string
	 */
	private function get_summary_image_url( $image_id ): string {

		$image_id = absint( $image_id );

		$image = $image_id
			? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
			: '';

		return is_string( $image ) && '' !== $image
			? $image
			: wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Render an explicitly assigned reusable product gallery.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $settings   Checkout settings.
	 *
	 * @return string
	 */
	private function render_product_gallery(
		int $product_id,
		array $settings
	): string {

		if ( ! class_exists( ProductGalleryRenderer::class ) ) {
			return '';
		}

		$renderer = new ProductGalleryRenderer();

		return $renderer->render_for_product( $product_id, $settings );
	}
}
