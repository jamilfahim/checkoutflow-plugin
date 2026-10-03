<?php
/**
 * Simple product template.
 *
 * @package EilmoCheckout
 *
 * @var \WC_Product          $product  WooCommerce product.
 * @var array<string, mixed> $settings Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included exclusively inside renderer method scope; template variables are local.

$product_id = $product->get_id();

if ( $product_id <= 0 ) {
	return;
}

$product_url   = $product->get_permalink();
$product_name  = $product->get_name();
$product_price = $product->get_price();
$price_html    = $product->get_price_html();
$summary_image_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
if ( ! is_string( $summary_image_url ) || '' === $summary_image_url ) {
	$summary_image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
}

$is_grouped =
	'grouped' === ( $settings['product_layout'] ?? 'cards' );

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

$image_size = 'woocommerce_single';

$is_purchasable = $product->is_purchasable();
$is_in_stock    = $product->is_in_stock();
$can_purchase   = $is_purchasable && $is_in_stock;

$managing_stock = $product->managing_stock();
$stock_quantity = $product->get_stock_quantity();

$maximum_quantity = $product->get_max_purchase_quantity();

if ( $product->is_sold_individually() ) {
	$maximum_quantity = 1;
}

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

/**
 * Filters whether the quantity controls are enabled.
 *
 * @param bool                 $can_purchase Whether quantity can be changed.
 * @param \WC_Product          $product      WooCommerce product.
 * @param array<string, mixed> $settings     Checkout settings.
 */
$can_purchase = (bool) apply_filters(
	'eilmo_cf/product_can_purchase',
	$can_purchase,
	$product,
	$settings
);
?>

<div
	class="eilmo-cf-product-card eilmo-cf-product-card--simple"
	data-eilmo-item
	data-item-type="simple"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-variation-id="0"
	data-price="<?php echo esc_attr( wc_format_decimal( $product_price ) ); ?>"
	data-item-name="<?php echo esc_attr( $product_name ); ?>"
	data-item-variation=""
	data-item-image="<?php echo esc_url( $summary_image_url ); ?>"
	data-purchasable="<?php echo $can_purchase ? 'yes' : 'no'; ?>"
	data-stock-status="<?php echo esc_attr( $product->get_stock_status() ); ?>"
	<?php if ( null !== $stock_quantity ) : ?>
		data-stock-quantity="<?php echo esc_attr( (string) $stock_quantity ); ?>"
	<?php endif; ?>
>
	<?php
	/**
	 * Fires at the beginning of a simple product card.
	 *
	 * @param \WC_Product          $product  WooCommerce product.
	 * @param array<string, mixed> $settings Checkout settings.
	 */
	do_action(
		'eilmo_cf/simple_product_card_start',
		$product,
		$settings
	);
	?>

	<?php if ( 'yes' === $settings['show_thumbnail'] ) : ?>

		<div class="eilmo-cf-product-card__thumbnail">
			<?php
			echo wp_kses_post(
				$product->get_image(
					$image_size,
					array(
						'class'   => 'eilmo-cf-product-card__image',
						'loading' => 'lazy',
					)
				)
			);
			?>
		</div>

	<?php endif; ?>

	<div class="eilmo-cf-product-card__content">

		<?php if ( 'yes' === $settings['show_sale_badge'] && $product->is_on_sale() ) : ?>

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

			<div class="eilmo-cf-product-card__description">
				<?php echo wp_kses_post( $description ); ?>
			</div>

		<?php endif; ?>

		<?php if ( 'yes' === $settings['show_stock'] ) : ?>

			<div class="eilmo-cf-product-card__stock">
				<?php echo wp_kses_post( wc_get_stock_html( $product ) ); ?>

				<?php
				if (
					$managing_stock &&
					null !== $stock_quantity &&
					$stock_quantity > 0
				) :
					?>

					<span
						class="eilmo-cf-product-card__remaining-stock"
						data-eilmo-stock-counter
					>
						<?php
						printf(
							/* translators: %s: Remaining product stock quantity. */
							esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( '%s remaining' ) ),
							esc_html( (string) $stock_quantity )
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
				name="eilmo_cf_items[<?php echo esc_attr( (string) $product_id ); ?>][quantity]"
				value="0"
				data-eilmo-quantity-input
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				<?php disabled( ! $can_purchase ); ?>
			/>
		<?php else : ?>
			<div
				class="eilmo-cf-quantity"
				data-eilmo-quantity
			>
				<button
					type="button"
					class="eilmo-cf-quantity__button eilmo-cf-quantity__button--minus"
					data-eilmo-quantity-minus
					aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>"
					<?php disabled( ! $can_purchase ); ?>
				>
					<svg
						class="eilmo-cf-quantity__icon"
						viewBox="0 0 24 24"
						aria-hidden="true"
						focusable="false"
					>
						<path d="M5 12h14" />
					</svg>
				</button>

				<input
					type="number"
					class="eilmo-cf-quantity__input"
					name="eilmo_cf_items[<?php echo esc_attr( (string) $product_id ); ?>][quantity]"
					value="0"
					min="0"
					step="1"
					inputmode="numeric"
					data-eilmo-quantity-input
					data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					<?php if ( $maximum_quantity > 0 ) : ?>
						max="<?php echo esc_attr( (string) $maximum_quantity ); ?>"
					<?php endif; ?>
					aria-label="<?php
					echo esc_attr(
						sprintf(
							/* translators: %s: Product name. */
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity for %s' ),
							$product_name
						)
					);
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
					<svg
						class="eilmo-cf-quantity__icon"
						viewBox="0 0 24 24"
						aria-hidden="true"
						focusable="false"
					>
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

	<?php if ( ! empty( $product_gallery_html ) ) : ?>
		<div class="eilmo-cf-product-card__gallery">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ProductGalleryRenderer escapes output internally.
			echo $product_gallery_html;
			?>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Fires at the end of a simple product card.
	 *
	 * @param \WC_Product          $product  WooCommerce product.
	 * @param array<string, mixed> $settings Checkout settings.
	 */
	do_action(
		'eilmo_cf/simple_product_card_end',
		$product,
		$settings
	);
	?>

</div>
