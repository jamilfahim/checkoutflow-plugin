<?php
/**
 * Multiple Products frontend renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\Core\Assets;
use EilmoCheckout\ProductGallery\Rendering\ProductGalleryRenderer;
use EilmoCheckout\Products\Services\AttributeStyleResolver;
use EilmoCheckout\Products\Services\DefaultVariationResolver;
use EilmoCheckout\Products\Services\MultipleProductsConfig;
use EilmoCheckout\Products\Services\ProductResolver;
use EilmoCheckout\Products\Services\VariationBadgeService;
use EilmoCheckout\Products\Services\VariationResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Renders assigned Simple and Variable products as independent compact cards.
 */
final class MultipleProductsRenderer {

	/** @var MultipleProductsConfig */
	private $config;

	/** @var ProductResolver */
	private $product_resolver;

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
	 * @param MultipleProductsConfig|null   $config                     Configuration.
	 * @param ProductResolver|null          $product_resolver           Product resolver.
	 * @param VariationResolver|null        $variation_resolver         Variation resolver.
	 * @param DefaultVariationResolver|null $default_variation_resolver Default resolver.
	 * @param VariationBadgeService|null    $variation_badge_service    Badge service.
	 * @param AttributeStyleResolver|null   $attribute_style_resolver   Attribute styles.
	 */
	public function __construct(
		?MultipleProductsConfig $config = null,
		?ProductResolver $product_resolver = null,
		?VariationResolver $variation_resolver = null,
		?DefaultVariationResolver $default_variation_resolver = null,
		?VariationBadgeService $variation_badge_service = null,
		?AttributeStyleResolver $attribute_style_resolver = null
	) {

		$this->config = $config ?? new MultipleProductsConfig();
		$this->product_resolver = $product_resolver ?? new ProductResolver();
		$this->variation_resolver = $variation_resolver ?? new VariationResolver();
		$this->default_variation_resolver =
			$default_variation_resolver ?? new DefaultVariationResolver( $this->variation_resolver );
		$this->variation_badge_service =
			$variation_badge_service ?? new VariationBadgeService();
		$this->attribute_style_resolver =
			$attribute_style_resolver ?? new AttributeStyleResolver();
	}

	/**
	 * Render the Multiple Products list.
	 *
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
	public function render( array $settings = array() ): string {

		Assets::enqueue_legacy_frontend_style();

		$overrides = isset( $settings['multiple_products'] ) &&
			is_array( $settings['multiple_products'] )
				? $settings['multiple_products']
				: array();

		$multiple_products = $this->config->get( $overrides );

		if ( empty( $multiple_products ) ) {
			return '';
		}

		$product_ids = $this->normalize_product_ids(
			$settings['product_ids'] ?? array()
		);
		$products = $this->product_resolver->resolve_products( $product_ids );
		$product_rows = array();

		foreach ( $products as $product ) {
			if (
				! $product instanceof \WC_Product ||
				! $product->is_type( array( 'simple', 'variable' ) )
			) {
				continue;
			}

			$row = $this->prepare_product_row(
				$product,
				$multiple_products,
				count( $product_rows ) + 1,
				$settings
			);

			if ( ! empty( $row ) ) {
				$product_rows[] = $row;
			}
		}

		if ( empty( $product_rows ) ) {
			return '';
		}

		$product_rows = $this->apply_initial_product_selection(
			$product_rows,
			$multiple_products
		);
		$settings['multiple_products'] = $multiple_products;
		$settings['product_mode'] = 'multiple';
		$settings['single_product_mode'] = 'no';
		$settings['multiple_products_mode'] = 'yes';
		$settings['selection'] =
			'single' === (string) ( $multiple_products['product_selection'] ?? 'multiple' )
				? 'single'
				: 'multiple';

		$template = EILMO_CF_PATH . 'templates/products/multiple-products.php';

		/**
		 * Filters the Multiple Products list template path.
		 *
		 * @param string                            $template          Template path.
		 * @param array<int, array<string, mixed>> $product_rows      Prepared products.
		 * @param array<string, mixed>              $settings          Checkout settings.
		 * @param array<string, mixed>              $multiple_products Resolved configuration.
		 */
		$template = (string) apply_filters(
			'eilmo_cf/multiple_products_template',
			$template,
			$product_rows,
			$settings,
			$multiple_products
		);

		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		require $template;
		$output = ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters rendered Multiple Products HTML.
		 *
		 * @param string                            $output            Rendered HTML.
		 * @param array<int, array<string, mixed>> $product_rows      Prepared products.
		 * @param array<string, mixed>              $settings          Checkout settings.
		 * @param array<string, mixed>              $multiple_products Resolved configuration.
		 */
		return (string) apply_filters(
			'eilmo_cf/multiple_products_html',
			$output,
			$product_rows,
			$settings,
			$multiple_products
		);
	}

	/**
	 * Prepare one independent parent-product card.
	 *
	 * @param \WC_Product          $product  Product.
	 * @param array<string, mixed> $config   Multiple Products configuration.
	 * @param int                  $position Visible card position.
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_product_row(
		\WC_Product $product,
		array $config,
		int $position,
		array $settings = array()
	): array {

		$is_variable = $product instanceof \WC_Product_Variable;
		$variations = array();
		$variation_rows = array();
		$attribute_groups = array();
		$default_variation_id = 0;

		if ( $is_variable ) {
			$variations = $this->variation_resolver->get_variations( $product );

			if ( empty( $variations ) ) {
				return array();
			}

			$default_variation = $this->default_variation_resolver->resolve(
				$product,
				(string) ( $config['default_selection'] ?? 'customer' )
			);

			if (
				! $default_variation &&
				(
					1 === count( $variations ) ||
					'no' === (string) ( $config['show_variations'] ?? 'yes' )
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
			$variation_rows = $this->prepare_variation_rows(
				$product,
				$variations,
				$config
			);
			$attribute_groups = $this->prepare_attribute_groups(
				$product,
				$variation_rows,
				$config
			);
		}

		$image_id = absint( $product->get_image_id() );
		$image = $this->get_image_data(
			$image_id,
			$product->get_name(),
			'hide' !== (string) ( $config['missing_image_behavior'] ?? 'placeholder' )
		);
		$gallery_html = '';

		if ( 'gallery' === (string) ( $config['media'] ?? 'featured' ) ) {
			$gallery_renderer = new ProductGalleryRenderer();
			$gallery_html = $gallery_renderer->render_for_product(
				$product->get_id(),
				$settings
			);

			if ( '' === $gallery_html ) {
				$gallery_html = $gallery_renderer->render_native_for_product( $product );
			}
		}
		$current_price = (float) $product->get_price();
		$regular_price = (float) $product->get_regular_price();
		$saving_amount = $regular_price > $current_price
			? max( 0.0, $regular_price - $current_price )
			: 0.0;
		$can_purchase = $is_variable
			? $this->has_purchasable_variation( $variation_rows )
			: $product->is_purchasable() && $product->is_in_stock();

		$row = array(
			'position'             => max( 1, $position ),
			'product'              => $product,
			'product_id'           => $product->get_id(),
			'type'                 => $is_variable ? 'variable' : 'simple',
			'name'                 => $product->get_name(),
			'permalink'            => $product->get_permalink(),
			'image'                => $image,
			'gallery_html'         => $gallery_html,
			'summary_image'        => $this->get_summary_image_url( $image_id ),
			'price'                => wc_format_decimal( $product->get_price() ),
			'price_html'           => wp_kses_post( $product->get_price_html() ),
			'current_price_html'   => wp_kses_post( wc_price( $current_price ) ),
			'regular_price_html'   => $regular_price > $current_price
				? wp_kses_post( wc_price( $regular_price ) )
				: '',
			'saving_amount'        => wc_format_decimal( $saving_amount ),
			'saving_html'          => $saving_amount > 0
				? wp_kses_post(
					sprintf(
						/* translators: %s: Formatted amount saved. */
						__( 'Save %s', 'eilmo-checkout-flow' ),
						wc_price( $saving_amount )
					)
				)
				: '',
			'rating_html'          => wp_kses_post(
				wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() )
			),
			'description_html'     => wp_kses_post(
				wc_format_content( $product->get_short_description() )
			),
			'stock_html'           => wp_kses_post( wc_get_stock_html( $product ) ),
			'stock_status'         => $product->get_stock_status(),
			'stock_quantity'       => $product->get_stock_quantity(),
			'maximum_quantity'     => $this->get_maximum_quantity(
				$product,
				'yes' === (string) ( $config['use_woocommerce_quantity_limits'] ?? 'yes' )
			),
			'can_purchase'         => $can_purchase,
			'on_sale'              => $product->is_on_sale(),
			'selected'             => false,
			'initial_quantity'     => 0,
			'variations'           => $variations,
			'variation_rows'       => $variation_rows,
			'attribute_groups'     => $attribute_groups,
			'default_variation_id' => $default_variation_id,
		);

		/**
		 * Filters one prepared Multiple Products card row.
		 *
		 * @param array<string, mixed> $row     Prepared row.
		 * @param \WC_Product          $product Product.
		 * @param array<string, mixed> $config  Multiple Products configuration.
		 */
		$row = apply_filters(
			'eilmo_cf/multiple_product_row',
			$row,
			$product,
			$config
		);

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Apply the configured initial parent-product selection safely.
	 *
	 * @param array<int, array<string, mixed>> $rows   Prepared rows.
	 * @param array<string, mixed>             $config Multiple Products configuration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_initial_product_selection(
		array $rows,
		array $config
	): array {

		$default_selection = sanitize_key(
			(string) ( $config['default_product_selection'] ?? 'customer' )
		);
		$product_selection = 'single' === (string) ( $config['product_selection'] ?? 'multiple' )
			? 'single'
			: 'multiple';

		if ( 'customer' === $default_selection ) {
			return $rows;
		}

		$selected_count = 0;

		foreach ( $rows as $index => $row ) {
			$default_variation_id = absint( $row['default_variation_id'] ?? 0 );

			if (
				'variable' === (string) ( $row['type'] ?? '' ) &&
				$default_variation_id <= 0
			) {
				$default_variation_id = $this->find_first_purchasable_variation_id(
					(array) ( $row['variation_rows'] ?? array() )
				);
				$rows[ $index ]['default_variation_id'] = $default_variation_id;
			}

			if (
				empty( $row['can_purchase'] ) ||
				(
					'variable' === (string) ( $row['type'] ?? '' ) &&
					$default_variation_id <= 0
				)
			) {
				continue;
			}

			$rows[ $index ]['selected'] = true;
			$rows[ $index ]['initial_quantity'] = 1;
			$rows[ $index ]['variation_rows'] = $this->mark_default_variation(
				(array) ( $row['variation_rows'] ?? array() ),
				$default_variation_id
			);
			++$selected_count;

			if (
				'first_available' === $default_selection ||
				'single' === $product_selection ||
				$selected_count >= count( $rows )
			) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Mark only the resolved default exact variation as initially selected.
	 *
	 * @param array<int, array<string, mixed>> $rows                 Variation rows.
	 * @param int                             $default_variation_id Default variation ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function mark_default_variation(
		array $rows,
		int $default_variation_id
	): array {

		foreach ( $rows as $index => $row ) {
			$is_selected = $default_variation_id > 0 &&
				$default_variation_id === absint( $row['id'] ?? 0 );
			$rows[ $index ]['selected'] = $is_selected;
			$rows[ $index ]['initial_quantity'] = $is_selected ? 1 : 0;
		}

		return $rows;
	}

	/**
	 * Prepare exact variation rows for valid frontend combination matching.
	 *
	 * @param \WC_Product_Variable              $product    Parent product.
	 * @param array<int, \WC_Product_Variation> $variations Variations.
	 * @param array<string, mixed>               $config     Multiple Products configuration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_variation_rows(
		\WC_Product_Variable $product,
		array $variations,
		array $config
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

			$image_id = absint( $variation->get_image_id() ?: $product->get_image_id() );
			$current_price = (float) $variation->get_price();
			$regular_price = (float) $variation->get_regular_price();
			$saving_amount = $regular_price > $current_price
				? max( 0.0, $regular_price - $current_price )
				: 0.0;

			$rows[] = array(
				'variation'             => $variation,
				'id'                    => $variation->get_id(),
				'label'                 => $this->get_variation_label( $variation ),
				'attributes'            => $attributes,
				'price'                 => wc_format_decimal( $variation->get_price() ),
				'price_html'            => wp_kses_post( $variation->get_price_html() ),
				'current_price_html'    => wp_kses_post( wc_price( $current_price ) ),
				'regular_price_html'    => $regular_price > $current_price
					? wp_kses_post( wc_price( $regular_price ) )
					: '',
				'saving_amount'         => wc_format_decimal( $saving_amount ),
				'saving_html'           => $saving_amount > 0
					? wp_kses_post(
						sprintf(
							/* translators: %s: Formatted amount saved. */
							__( 'Save %s', 'eilmo-checkout-flow' ),
							wc_price( $saving_amount )
						)
					)
					: '',
				'image'                 => $this->get_image_data( $image_id, $product->get_name() ),
				'summary_image'         => $this->get_summary_image_url( $image_id ),
				'stock_html'            => wp_kses_post( wc_get_stock_html( $variation ) ),
				'stock_status'          => $variation->get_stock_status(),
				'stock_quantity'        => $variation->get_stock_quantity(),
				'maximum_quantity'      => $this->get_maximum_quantity(
					$variation,
					'yes' === (string) ( $config['use_woocommerce_quantity_limits'] ?? 'yes' )
				),
				'can_purchase'          => $variation->is_purchasable() && $variation->is_in_stock(),
				'on_sale'               => $variation->is_on_sale(),
				'badge'                 => $this->variation_badge_service->get_badge( $variation ),
				'selected'              => false,
				'initial_quantity'      => 0,
			);
		}

		return $rows;
	}

	/**
	 * Prepare compact attribute groups using the shared presentation resolver.
	 *
	 * @param \WC_Product_Variable               $product        Parent product.
	 * @param array<int, array<string, mixed>> $variation_rows Exact variations.
	 * @param array<string, mixed>             $config         Multiple Products configuration.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_attribute_groups(
		\WC_Product_Variable $product,
		array $variation_rows,
		array $config
	): array {

		$groups = array();

		foreach ( $product->get_variation_attributes() as $attribute => $options ) {
			$attribute_key = sanitize_title( (string) $attribute );
			$presentation = $this->attribute_style_resolver->resolve(
				$attribute_key,
				$config['attribute_styles'] ?? array()
			);
			$option_rows = array();

			foreach ( (array) $options as $option ) {
				$option = (string) $option;
				$pricing = $this->get_attribute_option_pricing(
					$attribute_key,
					$option,
					$variation_rows
				);

				$option_rows[] = array(
					'value'                 => $option,
					'label'                 => $this->get_option_label( (string) $attribute, $option ),
					'image'                 => $this->get_attribute_option_image(
						$attribute_key,
						$option,
						$variation_rows
					),
					'price_html'            => (string) $pricing['price_html'],
					'regular_price_html'    => (string) $pricing['regular_price_html'],
					'saving_html'           => (string) $pricing['saving_html'],
					'badge'                 => isset( $pricing['badge'] ) && is_array( $pricing['badge'] )
						? $pricing['badge']
						: array(),
				);
			}

			if ( empty( $option_rows ) ) {
				continue;
			}

			$groups[] = array(
				'key'                    => $attribute_key,
				'label'                  => wc_attribute_label( (string) $attribute, $product ),
				'options'                => $option_rows,
				'style'                  => (string) $presentation['style'],
				'columns'                => (int) $presentation['columns'],
				'show_price'             => (string) $presentation['show_price'],
				'show_regular_price'     => (string) $presentation['show_regular_price'],
				'show_savings'           => (string) $presentation['show_savings'],
				'show_badge'             => 'yes' === (string) ( $config['show_variation_badges'] ?? 'yes' ) && $attribute_key === sanitize_key(
					(string) ( $config['badge_attribute'] ?? '' )
				)
					? 'yes'
					: 'no',
			);
		}

		return $groups;
	}

	/**
	 * Resolve exact price, saving, and badge copy for one attribute option.
	 *
	 * @param string                            $attribute      Attribute key.
	 * @param string                            $option         Option value.
	 * @param array<int, array<string, mixed>> $variation_rows Variation rows.
	 *
	 * @return array{price_html:string,regular_price_html:string,saving_html:string,badge:array<string,mixed>}
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
				'regular_price_html' => (string) ( $row['regular_price_html'] ?? '' ),
				'saving_html'        => (string) ( $row['saving_html'] ?? '' ),
				'badge'              => isset( $row['badge'] ) && is_array( $row['badge'] )
					? $row['badge']
					: array(),
			);
		}

		return array(
			'price_html'         => '',
			'regular_price_html' => '',
			'saving_html'        => '',
			'badge'              => array(),
		);
	}

	/**
	 * Resolve a representative variation image for one attribute option.
	 *
	 * @param string                            $attribute      Attribute key.
	 * @param string                            $option         Option value.
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
	 * Resolve a human-readable option label.
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
	 * Determine whether any exact variation is currently purchasable.
	 *
	 * @param array<int, array<string, mixed>> $variation_rows Variation rows.
	 *
	 * @return bool
	 */
	private function has_purchasable_variation( array $variation_rows ): bool {

		foreach ( $variation_rows as $row ) {
			if ( ! empty( $row['can_purchase'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the first exact variation that WooCommerce allows customers to buy.
	 *
	 * @param array<int, array<string, mixed>> $variation_rows Variation rows.
	 *
	 * @return int
	 */
	private function find_first_purchasable_variation_id(
		array $variation_rows
	): int {

		foreach ( $variation_rows as $row ) {
			if ( ! empty( $row['can_purchase'] ) ) {
				return absint( $row['id'] ?? 0 );
			}
		}

		return 0;
	}

	/**
	 * Resolve WooCommerce quantity limits safely.
	 *
	 * @param \WC_Product $product    Product or variation.
	 * @param bool        $use_limits Whether WooCommerce maximums are enforced.
	 *
	 * @return int
	 */
	private function get_maximum_quantity(
		\WC_Product $product,
		bool $use_limits = true
	): int {

		if ( $product->is_sold_individually() ) {
			return 1;
		}

		if ( ! $use_limits ) {
			return 0;
		}

		$maximum_quantity = $product->get_max_purchase_quantity();

		return is_numeric( $maximum_quantity )
			? (int) $maximum_quantity
			: 0;
	}

	/**
	 * Resolve featured image data with a WooCommerce placeholder fallback.
	 *
	 * @param mixed  $image_id Attachment ID.
	 * @param string $fallback_alt    Fallback alternative text.
	 * @param bool   $use_placeholder Whether a placeholder is allowed.
	 *
	 * @return array<string, mixed>
	 */
	private function get_image_data(
		$image_id,
		string $fallback_alt = '',
		bool $use_placeholder = true
	): array {

		$image_id = absint( $image_id );
		$display = $image_id
			? wp_get_attachment_image_url( $image_id, 'woocommerce_single' )
			: '';
		$thumb = $image_id
			? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
			: '';

		return array(
			'id'          => $image_id,
			'display_url' => is_string( $display ) && '' !== $display
				? $display
					: ( $use_placeholder ? wc_placeholder_img_src( 'woocommerce_single' ) : '' ),
			'thumb_url'   => is_string( $thumb ) && '' !== $thumb
				? $thumb
					: ( $use_placeholder ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '' ),
			'alt'         => $image_id
				? sanitize_text_field(
					(string) get_post_meta( $image_id, '_wp_attachment_image_alt', true )
				)
				: sanitize_text_field( $fallback_alt ),
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
	 * Normalize and de-duplicate assigned parent product IDs.
	 *
	 * @param mixed $product_ids Product IDs.
	 *
	 * @return array<int, int>
	 */
	private function normalize_product_ids( $product_ids ): array {

		if ( is_string( $product_ids ) ) {
			$product_ids = preg_split( '/[\s,]+/', $product_ids );
		}

		if ( ! is_array( $product_ids ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );

			if ( $product_id > 0 ) {
				$normalized[] = $product_id;
			}
		}

		return array_values( array_unique( $normalized ) );
	}
}
