<?php
/**
 * Product renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\Core\Assets;
use EilmoCheckout\Products\Services\ProductResolver;
use EilmoCheckout\Products\Services\SingleProductResolver;
use EilmoCheckout\Products\Services\VariationResolver;
use EilmoCheckout\ProductGallery\Rendering\ProductGalleryRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders checkout products.
 */
final class ProductRenderer {

	/**
	 * Product resolver.
	 *
	 * @var ProductResolver
	 */
	private $product_resolver;

	/**
	 * Variation resolver.
	 *
	 * @var VariationResolver
	 */
	private $variation_resolver;

	/**
	 * Single Product resolver.
	 *
	 * @var SingleProductResolver
	 */
	private $single_product_resolver;

	/**
	 * Single Product frontend renderer.
	 *
	 * @var SingleProductRenderer
	 */
	private $single_product_renderer;

	/**
	 * Multiple Products frontend renderer.
	 *
	 * @var MultipleProductsRenderer
	 */
	private $multiple_products_renderer;

	/**
	 * Constructor.
	 *
	 * @param ProductResolver|null          $product_resolver          Product resolver.
	 * @param VariationResolver|null        $variation_resolver        Variation resolver.
	 * @param SingleProductResolver|null    $single_product_resolver   Single Product resolver.
	 * @param SingleProductRenderer|null    $single_product_renderer   Single Product renderer.
	 * @param MultipleProductsRenderer|null $multiple_products_renderer Multiple Products renderer.
	 */
	public function __construct(
		?ProductResolver $product_resolver = null,
		?VariationResolver $variation_resolver = null,
		?SingleProductResolver $single_product_resolver = null,
		?SingleProductRenderer $single_product_renderer = null,
		?MultipleProductsRenderer $multiple_products_renderer = null
	) {

		$this->product_resolver = $product_resolver ?? new ProductResolver();

		$this->variation_resolver = $variation_resolver ?? new VariationResolver();

		$this->single_product_resolver =
			$single_product_resolver ?? new SingleProductResolver( $this->product_resolver );

		$this->single_product_renderer =
			$single_product_renderer ?? new SingleProductRenderer();

		$this->multiple_products_renderer =
			$multiple_products_renderer ?? new MultipleProductsRenderer(
				null,
				$this->product_resolver,
				$this->variation_resolver
			);
	}

	/**
	 * Render products.
	 *
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
	public function render( array $settings = array() ): string {

		$product_ids = isset( $settings['product_ids'] )
			? array_values(
				array_unique(
					array_filter(
						array_map( 'absint', (array) $settings['product_ids'] )
					)
				)
			)
			: array();

		if ( empty( $product_ids ) ) {
			return '';
		}

		$product_layout =
			'grouped' === ( $settings['product_layout'] ?? 'cards' )
				? 'grouped'
				: 'cards';

		$product_mode = sanitize_key(
			(string) ( $settings['product_mode'] ?? 'auto' )
		);
		$has_explicit_product_mode = in_array(
			$product_mode,
			array( 'single', 'multiple' ),
			true
		);

		/*
		 * Explicit mode always wins, including Multiple Products containing one
		 * product. Only direct legacy calls without product_mode use the original
		 * single_product_mode/count/layout inference.
		 */
		if ( ! $has_explicit_product_mode ) {
			$product_mode =
				'yes' === (string) ( $settings['single_product_mode'] ?? '' ) ||
				(
					'cards' === $product_layout &&
					1 === count( $product_ids )
				)
					? 'single'
					: 'multiple';
		}

		$settings['product_mode'] = $product_mode;
		$settings['single_product_mode'] = 'single' === $product_mode ? 'yes' : 'no';
		$settings['multiple_products_mode'] = 'multiple' === $product_mode ? 'yes' : 'no';

		if ( 'single' === $product_mode ) {
			$product = $this->single_product_resolver->resolve( $product_ids );

			if ( ! $product && $has_explicit_product_mode ) {
				return '';
			}

			if ( $product ) {
				$output = $this->single_product_renderer->render( $product, $settings );

				return $this->filter_output( $output, $settings );
			}
		}

		if ( $has_explicit_product_mode && 'multiple' === $product_mode ) {
			$output = $this->multiple_products_renderer->render( $settings );

			return $this->filter_output( $output, $settings );
		}

		// Direct legacy calls without product_mode retain the historical renderer.
		ob_start();

		if ( 'grouped' === $product_layout ) {

			Assets::enqueue_legacy_frontend_style();

			$product_group_title = sanitize_text_field(
				(string) ( $settings['product_group_title'] ?? '' )
			);

			/**
			 * Filters the optional grouped product section title.
			 *
			 * @param string               $product_group_title Group title.
			 * @param array<string, mixed> $settings            Checkout settings.
			 */
			$product_group_title = (string) apply_filters(
				'eilmo_cf/product_group_title',
				$product_group_title,
				$settings
			);

			$group_gallery_html =
				$this->render_group_gallery(
					(string) ( $settings['group_gallery_id'] ?? '' )
				);
			?>
			<section
				class="eilmo-cf-product-group"
				data-eilmo-product-group
			>
				<?php if ( '' !== $group_gallery_html ) : ?>
					<div class="eilmo-cf-product-group__gallery">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ProductGalleryRenderer escapes output internally.
						echo $group_gallery_html;
						?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $product_group_title ) : ?>
					<header class="eilmo-cf-product-group__header">
						<h2 class="eilmo-cf-product-group__title">
							<?php echo esc_html( $product_group_title ); ?>
						</h2>
					</header>
				<?php endif; ?>

				<div
					class="eilmo-cf-products eilmo-cf-products--grouped"
					data-eilmo-product-grid
				>
					<?php $this->render_product_items( $product_ids, $settings ); ?>
				</div>
			</section>
			<?php

		} else {
			?>
			<div class="eilmo-cf-products eilmo-cf-products--cards">
				<?php $this->render_product_items( $product_ids, $settings ); ?>
			</div>
			<?php
		}

		$output = ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters rendered products HTML.
		 *
		 * @param string               $output   Products HTML.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		return $this->filter_output( $output, $settings );
	}

	/**
	 * Apply the historical rendered-products filter to either architecture.
	 *
	 * @param string               $output   Products HTML.
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
	private function filter_output(
		string $output,
		array $settings
	): string {

		/**
		 * Filters rendered products HTML.
		 *
		 * @param string               $output   Products HTML.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		return (string) apply_filters(
			'eilmo_cf/products_html',
			$output,
			$settings
		);
	}

	/**
	 * Render product items inside the selected collection layout.
	 *
	 * @param array<int, mixed>    $product_ids Product IDs.
	 * @param array<string, mixed> $settings    Checkout settings.
	 *
	 * @return void
	 */
	private function render_product_items(
		array $product_ids,
		array $settings
	): void {

		foreach ( $product_ids as $product_id ) {

			$product_id = absint( $product_id );

			if ( $product_id <= 0 ) {
				continue;
			}

			$product = $this->product_resolver->resolve_product(
				$product_id
			);

			if ( ! $product ) {
				continue;
			}

			$this->render_product(
				$product,
				$settings
			);
		}
	}

	/**
	 * Render a product according to its WooCommerce product type.
	 *
	 * @param \WC_Product          $product  WooCommerce product.
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return void
	 */
	private function render_product(
		\WC_Product $product,
		array $settings
	): void {

		/**
		 * Filters whether a product should be rendered.
		 *
		 * @param bool                 $should_render Whether product should render.
		 * @param \WC_Product          $product       WooCommerce product.
		 * @param array<string, mixed> $settings      Checkout settings.
		 */
		$should_render = (bool) apply_filters(
			'eilmo_cf/should_render_product',
			true,
			$product,
			$settings
		);

		if ( ! $should_render ) {
			return;
		}

		/**
		 * Fires before an individual product is rendered.
		 *
		 * @param \WC_Product          $product  WooCommerce product.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		do_action(
			'eilmo_cf/before_product',
			$product,
			$settings
		);

		if ( $product->is_type( 'simple' ) ) {

			$this->render_simple_product(
				$product,
				$settings
			);

		} elseif ( $product->is_type( 'variable' ) ) {

			$this->render_variable_product(
				$product,
				$settings
			);

		} else {

			/**
			 * Fires when an unsupported WooCommerce product type is found.
			 *
			 * V1 officially supports simple and variable products only.
			 *
			 * @param \WC_Product          $product  Unsupported product.
			 * @param array<string, mixed> $settings Checkout settings.
			 */
			do_action(
				'eilmo_cf/unsupported_product_type',
				$product,
				$settings
			);
		}

		/**
		 * Fires after an individual product is rendered.
		 *
		 * @param \WC_Product          $product  WooCommerce product.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		do_action(
			'eilmo_cf/after_product',
			$product,
			$settings
		);
	}

	/**
	 * Render a simple product.
	 *
	 * @param \WC_Product          $product  Simple product.
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return void
	 */
	private function render_simple_product(
		\WC_Product $product,
		array $settings
	): void {

		$template = EILMO_CF_PATH . 'templates/products/simple.php';

		if ( ! file_exists( $template ) ) {
			return;
		}

		/**
		 * Filters simple product template path.
		 *
		 * @param string               $template Template path.
		 * @param \WC_Product          $product  Product.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		$template = (string) apply_filters(
			'eilmo_cf/simple_product_template',
			$template,
			$product,
			$settings
		);

		if ( ! file_exists( $template ) ) {
			return;
		}

		$product_gallery_html =
			$this->render_product_gallery(
				$product->get_id(),
				$settings
			);

		require $template;
	}

    /**
     * Render a variable product.
     *
     * The variable product template is responsible for rendering
     * the parent wrapper and its individual variation cards.
     *
     * @param \WC_Product_Variable $product  Variable product.
     * @param array<string, mixed>  $settings Checkout settings.
     *
     * @return void
     */
    private function render_variable_product(
        \WC_Product_Variable $product,
        array $settings
    ): void {

        $variations = $this->variation_resolver->get_variations(
            $product
        );

        if ( empty( $variations ) ) {
            return;
        }

        if (
            'grouped' === ( $settings['product_layout'] ?? 'cards' ) &&
            1 === count( $variations ) &&
            $variations[0] instanceof \WC_Product_Variation
        ) {
            $this->render_single_variation_product(
                $product,
                $variations[0],
                $settings
            );

            return;
        }

        $template = EILMO_CF_PATH . 'templates/products/variable.php';

        /**
         * Filters variable product template path.
         *
         * @param string                                $template   Template path.
         * @param \WC_Product_Variable                  $product    Variable product.
         * @param array<int, \WC_Product_Variation>     $variations Product variations.
         * @param array<string, mixed>                   $settings   Checkout settings.
         */
        $template = (string) apply_filters(
            'eilmo_cf/variable_product_template',
            $template,
            $product,
            $variations,
            $settings
        );

        if ( ! file_exists( $template ) ) {
            return;
        }

        $product_gallery_html =
            $this->render_product_gallery(
                $product->get_id(),
                $settings
            );

        require $template;
    }


	/**
	 * Render one-variable product as a compact grouped grid card.
	 *
	 * The variation remains the orderable item; only presentation is
	 * simplified so a one-option variable product behaves like a simple card.
	 *
	 * @param \WC_Product_Variable  $product   Parent product.
	 * @param \WC_Product_Variation $variation Sole variation.
	 * @param array<string, mixed>    $settings  Checkout settings.
	 *
	 * @return void
	 */
	private function render_single_variation_product(
		\WC_Product_Variable $product,
		\WC_Product_Variation $variation,
		array $settings
	): void {

		$template = EILMO_CF_PATH . 'templates/products/single-variation.php';

		/**
		 * Filters grouped single-variation product template path.
		 *
		 * @param string                 $template  Template path.
		 * @param \WC_Product_Variable  $product   Parent product.
		 * @param \WC_Product_Variation $variation Sole variation.
		 * @param array<string, mixed>    $settings  Checkout settings.
		 */
		$template = (string) apply_filters(
			'eilmo_cf/single_variation_product_template',
			$template,
			$product,
			$variation,
			$settings
		);

		if ( ! file_exists( $template ) ) {
			return;
		}

		require $template;
	}

	/**
	 * Render the one checkout-level gallery assigned to a grouped grid.
	 *
	 * @param string $gallery_id Group gallery ID.
	 *
	 * @return string
	 */
	private function render_group_gallery(
		string $gallery_id
	): string {

		$gallery_id = sanitize_key( $gallery_id );

		if (
			'' === $gallery_id ||
			! class_exists( ProductGalleryRenderer::class )
		) {
			return '';
		}

		$renderer = new ProductGalleryRenderer();

		return $renderer->render_for_group( $gallery_id );
	}

	/**
	 * Render checkout-assigned Product Galleries for one product.
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

		if ( 'grouped' === ( $settings['product_layout'] ?? 'cards' ) ) {
			return '';
		}

		if ( ! class_exists( ProductGalleryRenderer::class ) ) {
			return '';
		}

		$renderer = new ProductGalleryRenderer();

		return $renderer->render_for_product(
			$product_id,
			$settings
		);
	}

}
