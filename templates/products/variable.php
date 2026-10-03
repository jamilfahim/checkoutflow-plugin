<?php
/**
 * Variable product template.
 *
 * @package EilmoCheckout
 *
 * @var \WC_Product_Variable              $product    Variable product.
 * @var array<int, \WC_Product_Variation> $variations Product variations.
 * @var array<string, mixed>               $settings   Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included exclusively inside renderer method scope; template variables are local.

$product_id = $product->get_id();

if ( $product_id <= 0 || empty( $variations ) ) {
	return;
}

$product_name = $product->get_name();
$product_url  = $product->get_permalink();

$title_target = 'new' === $settings['title_link_target']
	? '_blank'
	: '';

$title_rel = '_blank' === $title_target
	? 'noopener noreferrer'
	: '';

$description = $product->get_short_description();

if ( '' !== $description ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce owns this public compatibility filter name.
	$description = apply_filters(
		'woocommerce_short_description',
		$description
	);
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
}
?>

<div
	class="eilmo-cf-variable-product"
	data-eilmo-variable-product
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
>

	<?php
	/**
	 * Fires before the variable product card.
	 *
	 * @param \WC_Product_Variable              $product    Variable product.
	 * @param array<int, \WC_Product_Variation> $variations Product variations.
	 * @param array<string, mixed>               $settings   Checkout settings.
	 */
	do_action(
		'eilmo_cf/variable_product_start',
		$product,
		$variations,
		$settings
	);
	?>

	<div class="eilmo-cf-variable-product__header">

		<?php if ( 'yes' === $settings['show_title'] ) : ?>

			<h3 class="eilmo-cf-variable-product__title">

				<?php if ( 'yes' === $settings['title_link'] ) : ?>

					<a
						class="eilmo-cf-variable-product__title-link"
						href="<?php echo esc_url( $product_url ); ?>"
						<?php if ( '' !== $title_target ) : ?>
							target="<?php echo esc_attr( $title_target ); ?>"
						<?php endif; ?>
						<?php if ( '' !== $title_rel ) : ?>
							rel="<?php echo esc_attr( $title_rel ); ?>"
						<?php endif; ?>
					>
						<?php echo esc_html( $product_name ); ?>
					</a>

				<?php else : ?>

					<?php echo esc_html( $product_name ); ?>

				<?php endif; ?>

			</h3>

		<?php endif; ?>

		<?php
		if (
			'yes' === $settings['show_description'] &&
			'' !== $description
		) :
			?>

			<div class="eilmo-cf-variable-product__description">
				<?php echo wp_kses_post( $description ); ?>
			</div>

		<?php endif; ?>

	</div>

	<?php if ( ! empty( $product_gallery_html ) ) : ?>
		<div class="eilmo-cf-variable-product__gallery">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ProductGalleryRenderer escapes output internally.
			echo $product_gallery_html;
			?>
		</div>
	<?php endif; ?>

	<div class="eilmo-cf-variable-product__variations">

		<?php
		/**
		 * Fires before variable product variations.
		 *
		 * @param \WC_Product_Variable              $product    Variable product.
		 * @param array<int, \WC_Product_Variation> $variations Product variations.
		 * @param array<string, mixed>               $settings   Checkout settings.
		 */
		do_action(
			'eilmo_cf/before_variations',
			$product,
			$variations,
			$settings
		);

		foreach ( $variations as $variation ) {

			if ( ! $variation instanceof \WC_Product_Variation ) {
				continue;
			}

			$template = EILMO_CF_PATH . 'templates/products/variation.php';

			/**
			 * Filters variation template path.
			 *
			 * @param string                 $template  Template path.
			 * @param \WC_Product_Variable   $product   Parent product.
			 * @param \WC_Product_Variation  $variation Product variation.
			 * @param array<string, mixed>    $settings  Checkout settings.
			 */
			$template = (string) apply_filters(
				'eilmo_cf/variation_product_template',
				$template,
				$product,
				$variation,
				$settings
			);

			if ( ! file_exists( $template ) ) {
				continue;
			}

			require $template;
		}

		/**
		 * Fires after variable product variations.
		 *
		 * @param \WC_Product_Variable              $product    Variable product.
		 * @param array<int, \WC_Product_Variation> $variations Product variations.
		 * @param array<string, mixed>               $settings   Checkout settings.
		 */
		do_action(
			'eilmo_cf/after_variations',
			$product,
			$variations,
			$settings
		);
		?>

	</div>

	<?php
	/**
	 * Fires after the variable product card.
	 *
	 * @param \WC_Product_Variable              $product    Variable product.
	 * @param array<int, \WC_Product_Variation> $variations Product variations.
	 * @param array<string, mixed>               $settings   Checkout settings.
	 */
	do_action(
		'eilmo_cf/variable_product_end',
		$product,
		$variations,
		$settings
	);
	?>

</div>
