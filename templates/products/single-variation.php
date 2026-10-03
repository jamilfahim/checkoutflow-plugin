<?php
/**
 * Grouped single-variation product template.
 *
 * A variable product with exactly one purchasable variation is presented
 * like a simple grouped card while preserving the variation ID for ordering.
 *
 * @package EilmoCheckout
 *
 * @var \WC_Product_Variable  $product   Parent product.
 * @var \WC_Product_Variation $variation Sole variation.
 * @var array<string, mixed>   $settings  Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included exclusively inside renderer method scope; template variables are local.

$product_id   = $product->get_id();
$variation_id = $variation->get_id();

if ( $product_id <= 0 || $variation_id <= 0 ) {
	return;
}

$product_url  = $product->get_permalink();
$product_name = $product->get_name();

$variation_label = wc_get_formatted_variation(
	$variation,
	true,
	false,
	false
);

if ( '' === $variation_label ) {
	$variation_label = $variation->get_name();
}

$image_id = $variation->get_image_id();

if ( ! $image_id ) {
	$image_id = $product->get_image_id();
}

$price      = $variation->get_price();
$price_html = $variation->get_price_html();
$summary_image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
if ( ! is_string( $summary_image_url ) || '' === $summary_image_url ) {
	$summary_image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
}

$group_quantity_enabled =
	'yes' === (string) ( $settings['group_quantity_control'] ?? 'no' );

$quantity_enabled =
	'yes' === (string) ( $settings['show_quantity'] ?? 'yes' );

$quantity_after_select =
	$quantity_enabled && $group_quantity_enabled;

$select_text_enabled =
	'yes' === (string) ( $settings['show_select_text'] ?? 'yes' );

$show_select_control =
	$select_text_enabled && ( ! $quantity_enabled || $quantity_after_select );

$is_purchasable = $variation->is_purchasable();
$is_in_stock    = $variation->is_in_stock();
$can_purchase   = $is_purchasable && $is_in_stock;

$managing_stock = $variation->managing_stock();
$stock_quantity = $variation->get_stock_quantity();

$maximum_quantity = $variation->get_max_purchase_quantity();

if ( $variation->is_sold_individually() ) {
	$maximum_quantity = 1;
}

$title_target = 'new' === $settings['title_link_target']
	? '_blank'
	: '';

$title_rel = '_blank' === $title_target
	? 'noopener noreferrer'
	: '';

$can_purchase = (bool) apply_filters(
	'eilmo_cf/variation_can_purchase',
	$can_purchase,
	$variation,
	$product,
	$settings
);
?>

<div
	class="eilmo-cf-product-card eilmo-cf-product-card--simple eilmo-cf-product-card--single-variation"
	data-eilmo-item
	data-item-type="variation"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
	data-price="<?php echo esc_attr( wc_format_decimal( $price ) ); ?>"
	data-item-name="<?php echo esc_attr( $product_name ); ?>"
	data-item-variation="<?php echo esc_attr( wp_strip_all_tags( $variation_label ) ); ?>"
	data-item-image="<?php echo esc_url( $summary_image_url ); ?>"
	data-purchasable="<?php echo $can_purchase ? 'yes' : 'no'; ?>"
	data-stock-status="<?php echo esc_attr( $variation->get_stock_status() ); ?>"
	<?php if ( null !== $stock_quantity ) : ?>
		data-stock-quantity="<?php echo esc_attr( (string) $stock_quantity ); ?>"
	<?php endif; ?>
>
	<?php if ( 'yes' === $settings['show_thumbnail'] ) : ?>
		<div class="eilmo-cf-product-card__thumbnail">
			<?php if ( $image_id ) : ?>
				<?php
				echo wp_kses_post(
					wp_get_attachment_image(
						$image_id,
						'woocommerce_single',
						false,
						array(
							'class'   => 'eilmo-cf-product-card__image',
							'loading' => 'lazy',
						)
					)
				);
				?>
			<?php else : ?>
				<?php
				echo wp_kses_post(
					wc_placeholder_img(
						'woocommerce_single',
						array(
							'class' => 'eilmo-cf-product-card__image',
						)
					)
				);
				?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="eilmo-cf-product-card__content">
		<?php if ( 'yes' === $settings['show_sale_badge'] && $variation->is_on_sale() ) : ?>
			<div class="eilmo-cf-product-card__badges">
				<span class="eilmo-cf-badge eilmo-cf-badge--sale">
					<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Sale' ) ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( 'yes' === $settings['show_title'] ) : ?>
			<h3 class="eilmo-cf-product-card__title">
				<?php if ( 'yes' === $settings['title_link'] ) : ?>
					<a
						class="eilmo-cf-product-card__title-link"
						href="<?php echo esc_url( $product_url ); ?>"
						<?php if ( '' !== $title_target ) : ?>target="<?php echo esc_attr( $title_target ); ?>"<?php endif; ?>
						<?php if ( '' !== $title_rel ) : ?>rel="<?php echo esc_attr( $title_rel ); ?>"<?php endif; ?>
					>
						<?php echo esc_html( $product_name ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $product_name ); ?>
				<?php endif; ?>
			</h3>

			<?php if ( '' !== $variation_label && 'yes' === ( $settings['variation_badge_enabled'] ?? 'yes' ) ) : ?>
				<div class="eilmo-cf-product-card__variation-label eilmo-cf-product-card__variation-label--<?php echo esc_attr( (string) ( $settings['variation_badge_style'] ?? 'badge' ) ); ?>">
					<?php echo esc_html( $variation_label ); ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( 'yes' === $settings['show_stock'] ) : ?>
			<div class="eilmo-cf-product-card__stock">
				<?php echo wp_kses_post( wc_get_stock_html( $variation ) ); ?>

				<?php if ( $managing_stock && null !== $stock_quantity && $stock_quantity > 0 ) : ?>
					<span class="eilmo-cf-product-card__remaining-stock" data-eilmo-stock-counter>
						<?php
						printf(
							/* translators: %d: Remaining stock quantity. */
							esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( '%d remaining' ) ),
							(int) $stock_quantity
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="eilmo-cf-product-card__footer">
			<div class="eilmo-cf-product-card__footer-pricing">
				<?php if ( 'yes' === $settings['show_price'] ) : ?>
					<div class="eilmo-cf-product-card__price">
						<?php echo wp_kses_post( $price_html ); ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="eilmo-cf-product-card__footer-action">

		<?php if ( $show_select_control && $can_purchase ) : ?>
			<button
				type="button"
				class="eilmo-cf-product-card__select"
				data-eilmo-select-item
				aria-pressed="false"
			>
				<span class="eilmo-cf-product-card__select-idle">
					<?php echo esc_html( (string) ( $settings['select_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select' ) ) ); ?>
				</span>
				<span class="eilmo-cf-product-card__select-selected">
					<svg class="eilmo-cf-product-card__select-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M5 12.5l4 4L19 7" />
					</svg>
					<?php echo esc_html( (string) ( $settings['selected_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Selected' ) ) ); ?>
				</span>
			</button>
		<?php endif; ?>

		<?php if ( ! $quantity_enabled ) : ?>
			<input
				type="hidden"
				name="eilmo_cf_items[<?php echo esc_attr( (string) $variation_id ); ?>][quantity]"
				value="0"
				data-eilmo-quantity-input
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				<?php disabled( ! $can_purchase ); ?>
			/>
		<?php else : ?>
		<div class="eilmo-cf-quantity" data-eilmo-quantity>
			<button
				type="button"
				class="eilmo-cf-quantity__button eilmo-cf-quantity__button--minus"
				data-eilmo-quantity-minus
				aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>"
				<?php disabled( ! $can_purchase ); ?>
			>
				<svg class="eilmo-cf-quantity__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M5 12h14" />
				</svg>
			</button>

			<input
				type="number"
				class="eilmo-cf-quantity__input"
				name="eilmo_cf_items[<?php echo esc_attr( (string) $variation_id ); ?>][quantity]"
				value="0"
				min="0"
				step="1"
				inputmode="numeric"
				data-eilmo-quantity-input
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				<?php if ( $maximum_quantity > 0 ) : ?>max="<?php echo esc_attr( (string) $maximum_quantity ); ?>"<?php endif; ?>
				aria-label="<?php
				/* translators: %s: Product variation name. */
				echo esc_attr( sprintf( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity for %s' ), $product_name ) );
				?>"
				<?php disabled( ! $can_purchase ); ?>
			/>

			<button
				type="button"
				class="eilmo-cf-quantity__button eilmo-cf-quantity__button--plus"
				data-eilmo-quantity-plus
				aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Increase quantity' ) ); ?>"
				<?php disabled( ! $can_purchase ); ?>
			>
				<svg class="eilmo-cf-quantity__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M12 5v14" />
					<path d="M5 12h14" />
				</svg>
			</button>
		</div>
		<?php endif; ?>

			</div>
		</div>

		<?php if ( ! $can_purchase ) : ?>
			<div class="eilmo-cf-product-card__unavailable">
				<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Currently unavailable' ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
