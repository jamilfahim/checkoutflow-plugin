<?php
/**
 * Consolidated Single Product campaign template.
 *
 * @package EilmoCheckout
 *
 * @var \WC_Product                         $product                 Parent product.
 * @var array<string, mixed>                $settings                Checkout settings.
 * @var array<string, mixed>                $single_product          Resolved settings.
 * @var array<int, \WC_Product_Variation>   $variations              Variations.
 * @var array<int, array<string, mixed>>     $variation_rows          Prepared rows.
 * @var array<int, array<string, mixed>>     $attribute_groups        Attribute groups.
 * @var int                                 $default_variation_id    Default variation.
 * @var array<int, array<string, mixed>>     $media_images            Native media.
 * @var string                              $product_gallery_html    Curated gallery.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included exclusively inside renderer method scope; template variables are local.

$product_id       = $product->get_id();
$product_name     = $product->get_name();
$is_variable      = $product instanceof \WC_Product_Variable;
$product_visible  = 'hidden' !== (string) ( $single_product['visibility'] ?? 'visible' );
$show_checkout_selector = 'no' !== (string) ( $single_product['show_checkout_selector'] ?? 'yes' );
$max_visible_variations = max( 1, min( 12, absint( $single_product['max_visible_variations'] ?? 4 ) ) );
$show_variations  = $is_variable && $show_checkout_selector && 'yes' === (string) ( $single_product['show_variations'] ?? 'yes' );
$variation_layout = 'grid' === (string) ( $single_product['variation_layout'] ?? 'sections' )
	? 'grid'
	: 'sections';
$variation_selection = 'multiple' === (string) ( $single_product['variation_selection'] ?? 'single' )
	? 'multiple'
	: 'single';

if ( 'grid' !== $variation_layout ) {
	$variation_selection = 'single';
}

$auto_add         = 'yes' === (string) ( $single_product['summary_auto_add'] ?? 'yes' );
$show_savings     = 'yes' === (string) ( $single_product['show_savings'] ?? 'no' );
$grid_show_price  = 'yes' === (string) ( $single_product['grid_show_price'] ?? 'yes' );
$grid_show_regular_price = 'yes' === (string) ( $single_product['grid_show_regular_price'] ?? 'no' );
$grid_show_savings = 'yes' === (string) ( $single_product['grid_show_savings'] ?? 'no' );
$show_selected_items = 'yes' === (string) ( $single_product['show_selected_items'] ?? 'no' );
$show_variation_badges = 'yes' === (string) ( $single_product['show_variation_badges'] ?? 'yes' );
$selected_items_show_quantity = 'yes' === (string) ( $single_product['selected_items_show_quantity'] ?? 'yes' );
$visually_hidden  = ! $product_visible && ! $show_variations && ( $is_variable || ! $show_checkout_selector );
$layout           = 'compact' === (string) ( $single_product['layout'] ?? 'campaign' )
	? 'compact'
	: 'campaign';

if ( $product_id <= 0 ) {
	return;
}

$initial_row = null;

foreach ( $variation_rows as $row ) {
	if ( (int) ( $row['id'] ?? 0 ) === $default_variation_id ) {
		$initial_row = $row;
		break;
	}
}

$simple_can_purchase = ! $is_variable && $product->is_purchasable() && $product->is_in_stock();
$simple_can_purchase = ! $is_variable
	? (bool) apply_filters( 'eilmo_cf/product_can_purchase', $simple_can_purchase, $product, $settings )
	: false;

$initial_price_html = $initial_row
	? (string) ( $initial_row['price_html'] ?? '' )
	: $product->get_price_html();
$initial_stock_html = $initial_row
	? (string) ( $initial_row['stock_html'] ?? '' )
	: wc_get_stock_html( $product );
$initial_on_sale = $initial_row
	? ! empty( $initial_row['on_sale'] )
	: $product->is_on_sale();
$initial_badge = $initial_row && isset( $initial_row['badge'] ) && is_array( $initial_row['badge'] )
	? $initial_row['badge']
	: array( 'enabled' => 'no', 'text' => '', 'type' => 'primary' );
$simple_current_price = ! $is_variable ? (float) $product->get_price() : 0.0;
$simple_regular_price = ! $is_variable ? (float) $product->get_regular_price() : 0.0;
$simple_saving_amount = $simple_regular_price > $simple_current_price
	? max( 0.0, $simple_regular_price - $simple_current_price )
	: 0.0;
$initial_saving_html = $initial_row
	? (string) ( $initial_row['saving_html'] ?? '' )
	: ( $simple_saving_amount > 0
		? wp_kses_post(
			sprintf(
				/* translators: %s: Formatted amount saved. */
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Save %s' ),
				wc_price( $simple_saving_amount )
			)
		)
		: '' );

$summary_image_url = wp_get_attachment_image_url(
	$product->get_image_id(),
	'woocommerce_thumbnail'
);

if ( ! is_string( $summary_image_url ) || '' === $summary_image_url ) {
	$summary_image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
}

$description = $product->get_short_description();

if ( '' !== $description ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce owns this compatibility filter.
	$description = apply_filters( 'woocommerce_short_description', $description );
}

$rating_count = $product->get_rating_count();
$rating_html  = wc_get_rating_html( $product->get_average_rating(), $rating_count );

$variation_payload = array();
$has_custom_badges = false;
$has_sale_badges   = $initial_on_sale;

foreach ( $variation_rows as $row ) {
	$image = isset( $row['image'] ) && is_array( $row['image'] )
		? $row['image']
		: array();
	$badge = isset( $row['badge'] ) && is_array( $row['badge'] )
		? $row['badge']
		: array();
	$badge_enabled = $show_variation_badges && 'yes' === (string) ( $badge['enabled'] ?? 'no' ) &&
		'' !== (string) ( $badge['text'] ?? '' );

	$has_custom_badges = $has_custom_badges || $badge_enabled;
	$has_sale_badges   = $has_sale_badges || ! empty( $row['on_sale'] );

	$variation_payload[] = array(
		'id'             => (int) ( $row['id'] ?? 0 ),
		'label'          => (string) ( $row['label'] ?? '' ),
		'attributes'     => isset( $row['attributes'] ) && is_array( $row['attributes'] ) ? $row['attributes'] : array(),
		'price'          => (string) ( $row['price'] ?? '' ),
		'priceHtml'      => (string) ( $row['price_html'] ?? '' ),
		'currentPriceHtml' => (string) ( $row['current_price_html'] ?? '' ),
		'regularPriceHtml' => (string) ( $row['regular_price_html'] ?? '' ),
		'savingHtml'     => (string) ( $row['saving_html'] ?? '' ),
		'stockHtml'      => (string) ( $row['stock_html'] ?? '' ),
		'canPurchase'    => ! empty( $row['can_purchase'] ),
		'onSale'         => ! empty( $row['on_sale'] ),
		'image'          => (string) ( $image['display_url'] ?? '' ),
		'imageFull'      => (string) ( $image['full_url'] ?? '' ),
		'imageAlt'       => (string) ( $image['alt'] ?? '' ),
		'badgeEnabled'   => $badge_enabled,
		'badgeText'      => (string) ( $badge['text'] ?? '' ),
		'badgeType'      => (string) ( $badge['type'] ?? 'primary' ),
	);
}

$render_quantity = static function (
	int $parent_product_id,
	int $variation_id,
	int $value,
	int $maximum,
	bool $can_purchase,
	bool $visible,
	string $label
): void {
	$input_key = $variation_id > 0 ? $variation_id : $parent_product_id;
	/* translators: %s: Product or variation name. */
	$quantity_aria_label = sprintf( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity for %s' ), $label );
	?>
	<?php if ( $visible ) : ?>
		<div class="eilmo-cf-quantity eilmo-cf-single-product__quantity" data-eilmo-quantity>
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
				name="eilmo_cf_items[<?php echo esc_attr( (string) $input_key ); ?>][quantity]"
				value="<?php echo esc_attr( (string) $value ); ?>"
				min="0"
				step="1"
				inputmode="numeric"
				data-eilmo-quantity-input
				data-product-id="<?php echo esc_attr( (string) $parent_product_id ); ?>"
				data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				<?php if ( $maximum > 0 ) : ?>max="<?php echo esc_attr( (string) $maximum ); ?>"<?php endif; ?>
				aria-label="<?php echo esc_attr( $quantity_aria_label ); ?>"
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
	<?php else : ?>
		<input
			type="hidden"
			name="eilmo_cf_items[<?php echo esc_attr( (string) $input_key ); ?>][quantity]"
			value="<?php echo esc_attr( (string) $value ); ?>"
			data-eilmo-quantity-input
			data-product-id="<?php echo esc_attr( (string) $parent_product_id ); ?>"
			data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
			<?php disabled( ! $can_purchase ); ?>
		/>
	<?php endif; ?>
	<?php
};

$root_classes = array(
	'eilmo-cf-product-card',
	'eilmo-cf-single-product',
	'eilmo-cf-single-product--' . $layout,
	$product_visible ? 'eilmo-cf-single-product--visible' : 'eilmo-cf-single-product--product-hidden',
);

$has_media = 'hidden' !== (string) ( $single_product['media'] ?? 'gallery' ) &&
	( '' !== $product_gallery_html || ! empty( $media_images ) );

if ( ! $has_media ) {
	$root_classes[] = 'eilmo-cf-single-product--no-media';
}

if ( $visually_hidden ) {
	$root_classes[] = 'eilmo-cf-single-product--visually-hidden';
}

if ( $is_variable ) {
	$root_classes[] = 'eilmo-cf-single-product--variable';
} else {
	$root_classes[] = 'eilmo-cf-product-card--simple';
	$root_classes[] = 'eilmo-cf-single-product--simple';
}

$simple_maximum = ! $is_variable ? $product->get_max_purchase_quantity() : 0;

if ( ! $is_variable && $product->is_sold_individually() ) {
	$simple_maximum = 1;
}

$simple_initial_quantity = $simple_can_purchase && $auto_add ? 1 : 0;
?>

<section
	class="<?php echo esc_attr( implode( ' ', $root_classes ) ); ?>"
	data-eilmo-single-product
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	data-product-type="<?php echo $is_variable ? 'variable' : 'simple'; ?>"
	data-default-variation-id="<?php echo esc_attr( (string) $default_variation_id ); ?>"
	data-variation-layout="<?php echo esc_attr( $variation_layout ); ?>"
	data-variation-selection="<?php echo esc_attr( $variation_selection ); ?>"
	data-multiple-remove-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Click again to remove' ) ); ?>"
	data-summary-auto-add="<?php echo $auto_add ? 'yes' : 'no'; ?>"
	data-show-checkout-selector="<?php echo esc_attr( $show_checkout_selector ? 'yes' : 'no' ); ?>"
	data-max-visible-variations="<?php echo esc_attr( (string) $max_visible_variations ); ?>"
	data-show-selected-items="<?php echo $show_selected_items ? 'yes' : 'no'; ?>"
	data-eilmo-no-card-toggle
	<?php if ( $visually_hidden ) : ?>hidden aria-hidden="true"<?php endif; ?>
	<?php if ( ! $is_variable ) : ?>
		data-eilmo-item
		data-item-type="simple"
		data-variation-id="0"
		data-price="<?php echo esc_attr( wc_format_decimal( $product->get_price() ) ); ?>"
		data-item-name="<?php echo esc_attr( $product_name ); ?>"
		data-item-variation=""
		data-item-image="<?php echo esc_url( $summary_image_url ); ?>"
		data-purchasable="<?php echo $simple_can_purchase ? 'yes' : 'no'; ?>"
		data-stock-status="<?php echo esc_attr( $product->get_stock_status() ); ?>"
	<?php endif; ?>
>
	<?php
	if ( $is_variable ) {
		do_action( 'eilmo_cf/variable_product_start', $product, $variations, $settings );
	} else {
		do_action( 'eilmo_cf/simple_product_card_start', $product, $settings );
	}
	?>


    <?php if ( ! $is_variable && $show_checkout_selector && isset( $settings['checkout_layout'] ) ) : ?>
    <div class="eilmo-cf-reference-fixed">
        <span class="eilmo-cf-reference-fixed__check" aria-hidden="true">✓</span>
        <h3><?php echo esc_html( $product_name ); ?></h3>
        <?php if ( 'yes' === (string) ( $single_product['show_description'] ?? 'yes' ) && $description ) : ?><div><?php echo wp_kses_post( $description ); ?></div><?php endif; ?>
        <strong><?php echo wp_kses_post( $product->get_price_html() ); ?></strong>
    </div>
    <?php endif; ?>
	<?php if ( $product_visible && ( $is_variable || ! isset( $settings['checkout_layout'] ) ) ) : ?>
		<div class="eilmo-cf-single-product__presentation">
			<?php if ( $has_media ) : ?>
				<div class="eilmo-cf-single-product__media">
					<?php if ( '' !== $product_gallery_html ) : ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gallery renderer escapes internally.
						echo $product_gallery_html;
						?>
						<?php elseif ( ! empty( $media_images ) ) : ?>
							<?php $main_image = $initial_row && ! empty( $initial_row['image']['display_url'] ) ? $initial_row['image'] : $media_images[0]; ?>
						<figure class="eilmo-cf-single-product__media-main">
							<img
								class="eilmo-cf-single-product__image"
								src="<?php echo esc_url( (string) ( $main_image['display_url'] ?? '' ) ); ?>"
								alt="<?php echo esc_attr( (string) ( $main_image['alt'] ?? $product_name ) ); ?>"
								data-eilmo-single-main-image
							>
						</figure>
						<?php if ( count( $media_images ) > 1 ) : ?>
							<div class="eilmo-cf-single-product__media-thumbnails" aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Product images' ) ); ?>">
								<?php foreach ( $media_images as $index => $image ) : ?>
									<?php
									/* translators: %d: Product image number. */
									$image_aria_label = sprintf( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Show image %d' ), $index + 1 );
									?>
									<button
										type="button"
										class="eilmo-cf-single-product__media-thumbnail<?php echo 0 === $index ? ' is-active' : ''; ?>"
										data-eilmo-single-media-thumbnail
										data-image="<?php echo esc_url( (string) ( $image['display_url'] ?? '' ) ); ?>"
										data-alt="<?php echo esc_attr( (string) ( $image['alt'] ?? '' ) ); ?>"
										aria-label="<?php echo esc_attr( $image_aria_label ); ?>"
									>
										<img src="<?php echo esc_url( (string) ( $image['thumb_url'] ?? '' ) ); ?>" alt="" loading="lazy">
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="eilmo-cf-single-product__details">
				<?php if ( ( 'yes' === (string) ( $single_product['show_sale_badge'] ?? 'yes' ) && $has_sale_badges ) || $has_custom_badges ) : ?>
					<div class="eilmo-cf-single-product__badges">
					<?php if ( 'yes' === (string) ( $single_product['show_sale_badge'] ?? 'yes' ) && $has_sale_badges ) : ?>
						<span class="eilmo-cf-single-product__badge eilmo-cf-single-product__badge--sale" data-eilmo-single-sale-badge <?php echo $initial_on_sale ? '' : 'hidden'; ?>>
							<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Sale' ) ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $has_custom_badges ) : ?>
					<span
						class="eilmo-cf-single-product__badge eilmo-cf-single-product__badge--<?php echo esc_attr( (string) ( $initial_badge['type'] ?? 'primary' ) ); ?>"
						data-eilmo-single-custom-badge
						<?php echo 'yes' === (string) ( $initial_badge['enabled'] ?? 'no' ) ? '' : 'hidden'; ?>
					>
						<?php echo esc_html( (string) ( $initial_badge['text'] ?? '' ) ); ?>
					</span>
					<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === (string) ( $single_product['show_title'] ?? 'yes' ) ) : ?>
					<h2 class="eilmo-cf-single-product__title"><?php echo esc_html( $product_name ); ?></h2>
				<?php endif; ?>

				<?php if ( 'yes' === (string) ( $single_product['show_rating'] ?? 'yes' ) && '' !== $rating_html ) : ?>
					<div class="eilmo-cf-single-product__rating">
						<?php echo wp_kses_post( $rating_html ); ?>
						<?php if ( $rating_count > 0 ) : ?>
							<?php
							/* translators: %s: Localized review count. */
							$review_format = _n( '%s review', '%s reviews', $rating_count, 'eilmo-checkout-flow' );
							?>
							<span><?php echo esc_html( sprintf( $review_format, number_format_i18n( $rating_count ) ) ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === (string) ( $single_product['show_price'] ?? 'yes' ) ) : ?>
					<div class="eilmo-cf-single-product__price" data-eilmo-single-price>
						<?php echo wp_kses_post( $initial_price_html ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_savings ) : ?>
					<div class="eilmo-cf-single-product__saving" data-eilmo-single-savings <?php echo '' !== $initial_saving_html ? '' : 'hidden'; ?>>
						<?php echo wp_kses_post( $initial_saving_html ); ?>
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === (string) ( $single_product['show_description'] ?? 'yes' ) && '' !== $description ) : ?>
					<div class="eilmo-cf-single-product__description"><?php echo wp_kses_post( $description ); ?></div>
				<?php endif; ?>

				<?php if ( 'yes' === (string) ( $single_product['show_stock'] ?? 'yes' ) ) : ?>
					<div class="eilmo-cf-single-product__stock" data-eilmo-single-stock>
						<?php echo wp_kses_post( $initial_stock_html ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $show_variations ) : ?>
		<div class="eilmo-cf-single-product__options eilmo-cf-single-product__options--<?php echo esc_attr( $variation_layout ); ?>" data-eilmo-single-options>
			<?php do_action( 'eilmo_cf/before_variations', $product, $variations, $settings ); ?>

			<?php if ( 'grid' === $variation_layout ) : ?>
				<header class="eilmo-cf-single-product__package-header">
					<?php if ( 'no' !== (string) ( $single_product['show_package_title'] ?? 'yes' ) ) : ?><h3><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Choose Package' ) ); ?></h3><?php endif; ?>
					<?php if ( 'no' !== (string) ( $single_product['show_package_helper'] ?? 'yes' ) ) : ?><p><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Tap the package you want' ) ); ?></p><?php endif; ?>
				</header>
			<?php endif; ?>

			<?php if ( 'sections' === $variation_layout ) : ?>
				<?php $is_package_group = 1 === count( $attribute_groups ); ?>
				<?php foreach ( $attribute_groups as $group_index => $group ) : ?>
					<fieldset class="eilmo-cf-single-product__option-section eilmo-cf-single-product__option-section--<?php echo esc_attr( (string) ( $group['style'] ?? 'buttons' ) ); ?><?php echo $is_package_group ? ' eilmo-cf-single-product__option-section--package' : ''; ?>" data-eilmo-single-attribute-group="<?php echo esc_attr( (string) $group['key'] ); ?>" data-attribute-style="<?php echo esc_attr( (string) ( $group['style'] ?? 'buttons' ) ); ?>" data-show-badge="<?php echo esc_attr( $is_package_group && $show_variation_badges ? 'yes' : (string) ( $group['show_badge'] ?? 'no' ) ); ?>">
						<legend class="eilmo-cf-single-product__option-title">
							<?php
							if ( 1 === count( $attribute_groups ) ) {
								echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Choose Package' ) );
							} else {
								printf(
									/* translators: %s: Product attribute label. */
									esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Choose %s' ) ),
									esc_html( (string) $group['label'] )
								);
							}
							?>
						</legend>
						<?php if ( $is_package_group ) : ?>
							<p class="eilmo-cf-single-product__option-helper"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Tap the package you want' ) ); ?></p>
						<?php endif; ?>
						<div class="eilmo-cf-single-product__option-list eilmo-cf-single-product__option-list--<?php echo esc_attr( (string) ( $group['style'] ?? 'buttons' ) ); ?><?php echo $is_package_group ? ' eilmo-cf-single-product__option-list--package' : ''; ?>" style="--eilmo-cf-attribute-columns: <?php echo esc_attr( (string) ( $group['columns'] ?? 4 ) ); ?>;">
							<?php foreach ( (array) $group['options'] as $option ) : ?>
								<?php
								$option_image = isset( $option['image'] ) && is_array( $option['image'] ) ? $option['image'] : array();
								$option_badge = isset( $option['badge'] ) && is_array( $option['badge'] ) ? $option['badge'] : array();
								$option_badge_enabled = $show_variation_badges &&
									( $is_package_group || 'yes' === (string) ( $group['show_badge'] ?? 'no' ) ) &&
									'yes' === (string) ( $option_badge['enabled'] ?? 'no' ) &&
									'' !== (string) ( $option_badge['text'] ?? '' );
								$is_grid_option = in_array( (string) ( $group['style'] ?? 'buttons' ), array( 'text_grid', 'image_grid' ), true );
								$render_rich_option = $is_grid_option || $is_package_group;
								?>
								<button
									type="button"
									class="eilmo-cf-single-product__option eilmo-cf-single-product__option--<?php echo esc_attr( (string) ( $group['style'] ?? 'buttons' ) ); ?><?php echo $is_package_group ? ' eilmo-cf-single-product__option--package' : ''; ?><?php echo $option_badge_enabled ? ' has-badge' : ''; ?>"
									data-eilmo-single-attribute-choice
									data-attribute="<?php echo esc_attr( (string) $group['key'] ); ?>"
									data-value="<?php echo esc_attr( (string) $option['value'] ); ?>"
									aria-pressed="false"
								>
									<?php if ( $is_package_group || 'yes' === (string) ( $group['show_badge'] ?? 'no' ) ) : ?>
										<span class="eilmo-cf-single-product__option-badge eilmo-cf-single-product__option-badge--<?php echo esc_attr( (string) ( $option_badge['type'] ?? 'primary' ) ); ?>" data-eilmo-single-option-badge <?php echo $option_badge_enabled ? '' : 'hidden'; ?>><?php echo esc_html( (string) ( $option_badge['text'] ?? '' ) ); ?></span>
									<?php endif; ?>
									<?php if ( 'image_grid' === (string) ( $group['style'] ?? 'buttons' ) ) : ?>
										<span class="eilmo-cf-single-product__option-media">
											<img src="<?php echo esc_url( (string) ( $option_image['display_url'] ?? wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) ); ?>" alt="" loading="lazy">
										</span>
									<?php endif; ?>
									<span class="eilmo-cf-single-product__option-label"><?php echo esc_html( (string) $option['label'] ); ?></span>
									<?php if ( 'no' !== (string) ( $single_product['show_variation_descriptions'] ?? 'yes' ) && $is_package_group && '' !== trim( (string) ( $option['description'] ?? '' ) ) ) : ?>
										<span class="eilmo-cf-single-product__option-description"><?php echo esc_html( (string) $option['description'] ); ?></span>
									<?php endif; ?>
									<?php if ( $render_rich_option && ( $is_package_group || 'yes' === (string) ( $group['show_price'] ?? 'yes' ) ) ) : ?>
										<span class="eilmo-cf-single-product__option-price-row">
											<span class="eilmo-cf-single-product__option-price" data-eilmo-single-option-price><?php echo wp_kses_post( (string) ( $option['price_html'] ?? '' ) ); ?></span>
											<?php if ( $is_package_group || 'yes' === (string) ( $group['show_regular_price'] ?? 'no' ) ) : ?>
												<del class="eilmo-cf-single-product__option-regular-price" data-eilmo-single-option-regular-price <?php echo '' !== (string) ( $option['regular_price_html'] ?? '' ) ? '' : 'hidden'; ?>><?php echo wp_kses_post( (string) ( $option['regular_price_html'] ?? '' ) ); ?></del>
											<?php endif; ?>
										</span>
									<?php endif; ?>
									<?php if ( $render_rich_option && ( $is_package_group || 'yes' === (string) ( $group['show_savings'] ?? 'no' ) ) ) : ?>
										<span class="eilmo-cf-single-product__option-saving" data-eilmo-single-option-saving <?php echo '' !== (string) ( $option['saving_html'] ?? '' ) ? '' : 'hidden'; ?>><?php echo wp_kses_post( (string) ( $option['saving_html'] ?? '' ) ); ?></span>
									<?php endif; ?>
								</button>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="eilmo-cf-single-product__variation-grid" role="<?php echo 'multiple' === $variation_selection ? 'group' : 'radiogroup'; ?>" aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Choose Package' ) ); ?>">
					<?php foreach ( $variation_rows as $row ) : ?>
						<?php $badge = isset( $row['badge'] ) && is_array( $row['badge'] ) ? $row['badge'] : array(); ?>
						<button
							type="button"
							class="eilmo-cf-single-product__variation-choice<?php echo (int) $row['id'] === $default_variation_id && $auto_add ? ' is-selected' : ''; ?>"
							data-eilmo-single-variation-choice
							data-variation-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
							aria-pressed="<?php echo (int) $row['id'] === $default_variation_id && $auto_add ? 'true' : 'false'; ?>"
							<?php disabled( empty( $row['can_purchase'] ) ); ?>
						>
						<?php if ( $show_variation_badges && 'yes' === (string) ( $badge['enabled'] ?? 'no' ) ) : ?>
								<span class="eilmo-cf-single-product__choice-badge eilmo-cf-single-product__choice-badge--<?php echo esc_attr( (string) ( $badge['type'] ?? 'primary' ) ); ?>">
									<?php echo esc_html( (string) ( $badge['text'] ?? '' ) ); ?>
								</span>
							<?php endif; ?>
							<span class="eilmo-cf-single-product__choice-label"><?php echo esc_html( (string) $row['label'] ); ?></span>
							<?php if ( 'no' !== (string) ( $single_product['show_variation_descriptions'] ?? 'yes' ) && '' !== trim( (string) ( $row['description'] ?? '' ) ) ) : ?>
								<span class="eilmo-cf-single-product__choice-description"><?php echo esc_html( (string) $row['description'] ); ?></span>
							<?php endif; ?>
							<?php if ( $grid_show_price ) : ?>
								<span class="eilmo-cf-single-product__choice-price-row">
									<span class="eilmo-cf-single-product__choice-price"><?php echo wp_kses_post( (string) ( $row['current_price_html'] ?? '' ) ); ?></span>
									<?php if ( $grid_show_regular_price && '' !== (string) ( $row['regular_price_html'] ?? '' ) ) : ?>
										<del class="eilmo-cf-single-product__choice-regular-price"><?php echo wp_kses_post( (string) $row['regular_price_html'] ); ?></del>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ( $grid_show_savings && '' !== (string) ( $row['saving_html'] ?? '' ) ) : ?>
								<span class="eilmo-cf-single-product__choice-saving"><?php echo wp_kses_post( (string) $row['saving_html'] ); ?></span>
							<?php endif; ?>
							<?php if ( empty( $row['can_purchase'] ) ) : ?>
								<span class="eilmo-cf-single-product__choice-unavailable"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Unavailable' ) ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="eilmo-cf-single-product__selection-message" data-eilmo-single-selection-message aria-live="polite"<?php echo 'no' === (string) ( $single_product['show_selected_quantity'] ?? 'yes' ) ? ' style="display:none"' : ''; ?>></p>
			<?php do_action( 'eilmo_cf/after_variations', $product, $variations, $settings ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $is_variable ) : ?>
		<?php
		$initial_selected_count = 0;

		foreach ( $variation_rows as $row ) {
			if (
				(int) $row['id'] === $default_variation_id &&
				$auto_add &&
				! empty( $row['can_purchase'] )
			) {
				$initial_selected_count++;
			}
		}
		?>
		<section class="eilmo-cf-single-product__selected-items" data-eilmo-single-selected-items <?php echo $initial_selected_count > 0 && $show_selected_items ? '' : 'hidden'; ?>>
			<header class="eilmo-cf-single-product__selected-header">
				<h3 class="eilmo-cf-single-product__selected-title">
					<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Your Selected Items' ) ); ?>
					<span>(<span data-eilmo-single-selected-count><?php echo esc_html( (string) $initial_selected_count ); ?></span>)</span>
				</h3>
				<button type="button" class="eilmo-cf-single-product__clear" data-eilmo-single-clear>
					<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Clear All' ) ); ?>
				</button>
			</header>

			<div class="eilmo-cf-single-product__variation-items" data-eilmo-single-variation-items>
			<?php foreach ( $variation_rows as $row ) : ?>
				<?php
				$is_selected = (int) $row['id'] === $default_variation_id && $auto_add && ! empty( $row['can_purchase'] );
				$quantity  = $is_selected ? 1 : 0;
				$stock_quantity = isset( $row['stock_quantity'] ) ? $row['stock_quantity'] : null;
				?>
				<div
					class="eilmo-cf-product-card eilmo-cf-single-product__variation-item<?php echo $is_selected ? ' is-selected-item' : ''; ?>"
					data-eilmo-item
					data-eilmo-no-card-toggle
					<?php if ( $selected_items_show_quantity ) : ?>data-eilmo-force-quantity<?php endif; ?>
					data-item-type="variation"
					data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					data-variation-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
					data-price="<?php echo esc_attr( (string) $row['price'] ); ?>"
					data-item-name="<?php echo esc_attr( $product_name ); ?>"
					data-item-variation="<?php echo esc_attr( (string) $row['label'] ); ?>"
					data-item-image="<?php echo esc_url( (string) $row['summary_image'] ); ?>"
					data-purchasable="<?php echo ! empty( $row['can_purchase'] ) ? 'yes' : 'no'; ?>"
					data-stock-status="<?php echo esc_attr( (string) $row['stock_status'] ); ?>"
					<?php if ( null !== $stock_quantity ) : ?>data-stock-quantity="<?php echo esc_attr( (string) $stock_quantity ); ?>"<?php endif; ?>
					<?php echo $is_selected ? '' : 'hidden'; ?>
				>
					<img class="eilmo-cf-single-product__selected-image" src="<?php echo esc_url( (string) $row['summary_image'] ); ?>" alt="" loading="lazy">
					<div class="eilmo-cf-single-product__selected-content">
						<strong class="eilmo-cf-single-product__selected-name"><?php echo esc_html( $product_name ); ?></strong>
						<span class="eilmo-cf-single-product__selected-variation" data-eilmo-single-selected-label>
							<?php echo esc_html( (string) $row['label'] ); ?>
							<?php if ( 'yes' === (string) ( $row['badge']['enabled'] ?? 'no' ) ) : ?>
								<span class="eilmo-cf-single-product__selected-badge eilmo-cf-single-product__selected-badge--<?php echo esc_attr( (string) ( $row['badge']['type'] ?? 'primary' ) ); ?>">
									<?php echo esc_html( (string) ( $row['badge']['text'] ?? '' ) ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<?php
					$render_quantity(
						$product_id,
						(int) $row['id'],
						$quantity,
						(int) $row['maximum_quantity'],
						! empty( $row['can_purchase'] ),
						$selected_items_show_quantity,
						(string) $row['label']
					);
					?>
					<div class="eilmo-cf-single-product__selected-price">
						<?php echo wp_kses_post( (string) $row['price_html'] ); ?>
						<?php if ( $show_savings && '' !== (string) ( $row['saving_html'] ?? '' ) ) : ?>
							<small><?php echo wp_kses_post( (string) $row['saving_html'] ); ?></small>
						<?php endif; ?>
					</div>
					<button type="button" class="eilmo-cf-single-product__remove" data-eilmo-single-remove-variation data-variation-id="<?php echo esc_attr( (string) $row['id'] ); ?>" aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Remove selected item' ) ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2m-7 4v7m6-7v7M6 6l1 14h10l1-14" /></svg>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
		</section>

		<script type="application/json" data-eilmo-single-variation-data><?php
			echo wp_json_encode(
				$variation_payload,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);
		?></script>
	<?php else : ?>
		<div class="eilmo-cf-single-product__simple-action" <?php echo $product_visible && ! $auto_add ? '' : 'hidden'; ?>>
			<?php
			$render_quantity(
				$product_id,
				0,
				$simple_initial_quantity,
				(int) $simple_maximum,
				$simple_can_purchase,
				false,
				$product_name
			);
			?>

			<?php if ( ! $auto_add && $simple_can_purchase ) : ?>
				<button type="button" class="eilmo-cf-single-product__include" data-eilmo-single-include-simple aria-pressed="false">
					<span class="eilmo-cf-product-card__select-idle"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select Product' ) ); ?></span>
					<span class="eilmo-cf-product-card__select-selected"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Selected' ) ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ( $is_variable && empty( $variation_rows ) ) || ( ! $is_variable && ! $simple_can_purchase ) ) : ?>
		<div class="eilmo-cf-single-product__unavailable"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Currently unavailable' ) ); ?></div>
	<?php endif; ?>

    <?php if ( 'single' === $variation_selection ) : ?>
    <div class="eilmo-cf-reference-quantity" data-eilmo-reference-quantity<?php echo 'no' === (string) ( $single_product['show_selected_quantity'] ?? 'yes' ) ? ' style="display:none"' : ''; ?>>
        <div><strong><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity' ) ); ?></strong><small><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Choose how many sets you need' ) ); ?></small></div>
        <div class="eilmo-cf-reference-stepper">
            <button type="button" data-reference-delta="-1" aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>">−</button>
            <output aria-live="polite">1</output>
            <button type="button" data-reference-delta="1" aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Increase quantity' ) ); ?>">+</button>
        </div>
    </div>
    <?php endif; ?>

	<?php
	if ( $is_variable ) {
		do_action( 'eilmo_cf/variable_product_end', $product, $variations, $settings );
	} else {
		do_action( 'eilmo_cf/simple_product_card_end', $product, $settings );
	}
	?>
</section>
