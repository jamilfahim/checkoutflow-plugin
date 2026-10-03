<?php
/**
 * Multiple Products compact list template.
 *
 * @package EilmoCheckout
 *
 * @var array<int, array<string, mixed>> $product_rows      Prepared product rows.
 * @var array<string, mixed>             $settings          Checkout settings.
 * @var array<string, mixed>             $multiple_products Resolved configuration.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included exclusively inside renderer method scope; template variables are local.

$product_selection = 'single' === (string) ( $multiple_products['product_selection'] ?? 'multiple' )
	? 'single'
	: 'multiple';
$variation_layout = 'sections' === (string) ( $multiple_products['variation_layout'] ?? 'grid' )
	? 'sections'
	: 'grid';
$variation_selection = 'multiple' === (string) ( $multiple_products['variation_selection'] ?? 'single' ) &&
	'grid' === $variation_layout
		? 'multiple'
		: 'single';
$show_media = 'hidden' !== (string) ( $multiple_products['media'] ?? 'featured' );
$show_quantity = 'yes' === (string) ( $multiple_products['show_quantity'] ?? 'yes' );
$grid_show_quantity = 'yes' === (string) ( $multiple_products['grid_show_quantity'] ?? 'yes' );
$allow_product_removal = 'yes' === (string) ( $multiple_products['allow_product_removal'] ?? 'yes' );
$show_selected_items = 'yes' === (string) ( $multiple_products['show_selected_items'] ?? 'yes' );
$show_variation_badges = 'yes' === (string) ( $multiple_products['show_variation_badges'] ?? 'yes' );
$initial_selected_count = 0;

foreach ( $product_rows as $count_row ) {
	if ( 'simple' === (string) ( $count_row['type'] ?? '' ) ) {
		$initial_selected_count += ! empty( $count_row['selected'] ) ? 1 : 0;
		continue;
	}

	foreach ( (array) ( $count_row['variation_rows'] ?? array() ) as $count_variation ) {
		$initial_selected_count += ! empty( $count_variation['selected'] ) ? 1 : 0;
	}
}
?>

<section
	class="eilmo-cf-multiple-products"
	data-eilmo-multiple-products
	data-product-selection="<?php echo esc_attr( $product_selection ); ?>"
	data-default-product-selection="<?php echo esc_attr( (string) ( $multiple_products['default_product_selection'] ?? 'customer' ) ); ?>"
	data-require-product-selection="<?php echo esc_attr( (string) ( $multiple_products['require_product_selection'] ?? 'yes' ) ); ?>"
	data-allow-product-removal="<?php echo $allow_product_removal ? 'yes' : 'no'; ?>"
	data-select-on-quantity-change="<?php echo esc_attr( (string) ( $multiple_products['select_on_quantity_change'] ?? 'yes' ) ); ?>"
	data-variation-layout="<?php echo esc_attr( $variation_layout ); ?>"
	data-variation-selection="<?php echo esc_attr( $variation_selection ); ?>"
	data-summary-auto-add="<?php echo esc_attr( (string) ( $multiple_products['summary_auto_add'] ?? 'yes' ) ); ?>"
	data-show-quantity="<?php echo $show_quantity ? 'yes' : 'no'; ?>"
	data-grid-show-quantity="<?php echo $grid_show_quantity ? 'yes' : 'no'; ?>"
	data-show-selected-items="<?php echo $show_selected_items ? 'yes' : 'no'; ?>"
>
	<div class="eilmo-cf-multiple-products__list" data-eilmo-multiple-list>
		<?php foreach ( $product_rows as $row ) : ?>
			<?php
			$product = $row['product'] ?? null;

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$product_id = absint( $row['product_id'] ?? 0 );
			$is_variable = 'variable' === (string) ( $row['type'] ?? '' );
			$is_selected = ! empty( $row['selected'] );
			$can_purchase = ! empty( $row['can_purchase'] );
			$position = max( 1, absint( $row['position'] ?? 1 ) );
			$image = isset( $row['image'] ) && is_array( $row['image'] )
				? $row['image']
				: array();
			$variation_rows = isset( $row['variation_rows'] ) && is_array( $row['variation_rows'] )
				? $row['variation_rows']
				: array();
			$attribute_groups = isset( $row['attribute_groups'] ) && is_array( $row['attribute_groups'] )
				? $row['attribute_groups']
				: array();
			$default_variation_id = absint( $row['default_variation_id'] ?? 0 );
			$default_attributes = array();
			$variation_payload = array();

			foreach ( $variation_rows as $variation_row ) {
				if ( absint( $variation_row['id'] ?? 0 ) === $default_variation_id ) {
					$default_attributes = isset( $variation_row['attributes'] ) && is_array( $variation_row['attributes'] )
						? $variation_row['attributes']
						: array();
				}

				$badge = isset( $variation_row['badge'] ) && is_array( $variation_row['badge'] )
					? $variation_row['badge']
					: array();
				$variation_image = isset( $variation_row['image'] ) && is_array( $variation_row['image'] )
					? $variation_row['image']
					: array();
				$variation_payload[] = array(
					'id'                 => absint( $variation_row['id'] ?? 0 ),
					'attributes'         => $variation_row['attributes'] ?? array(),
					'price'              => (string) ( $variation_row['price'] ?? '' ),
					'priceHtml'          => (string) ( $variation_row['price_html'] ?? '' ),
					'currentPriceHtml'   => (string) ( $variation_row['current_price_html'] ?? '' ),
					'regularPriceHtml'   => (string) ( $variation_row['regular_price_html'] ?? '' ),
					'savingHtml'         => (string) ( $variation_row['saving_html'] ?? '' ),
					'image'              => (string) ( $variation_image['display_url'] ?? '' ),
					'imageAlt'           => (string) ( $variation_image['alt'] ?? '' ),
					'summaryImage'       => (string) ( $variation_row['summary_image'] ?? '' ),
					'stockHtml'          => (string) ( $variation_row['stock_html'] ?? '' ),
					'stockStatus'        => (string) ( $variation_row['stock_status'] ?? '' ),
					'stockQuantity'      => $variation_row['stock_quantity'] ?? null,
					'maximumQuantity'    => (int) ( $variation_row['maximum_quantity'] ?? 0 ),
					'canPurchase'        => ! empty( $variation_row['can_purchase'] ),
					'onSale'             => ! empty( $variation_row['on_sale'] ),
					'badgeEnabled'       => $show_variation_badges && 'yes' === (string) ( $badge['enabled'] ?? 'no' ),
					'badgeText'          => (string) ( $badge['text'] ?? '' ),
					'badgeType'          => (string) ( $badge['type'] ?? 'primary' ),
				);
			}

			do_action( 'eilmo_cf/before_product', $product, $settings );
			?>

			<?php
			$gallery_html = (string) ( $row['gallery_html'] ?? '' );
			$has_gallery = $show_media && '' !== $gallery_html;
			$has_featured_media = $show_media && ! $has_gallery && '' !== (string) ( $image['display_url'] ?? '' );
			$card_has_media = $has_gallery || $has_featured_media;
			?>
			<article
				class="eilmo-cf-multiple-card<?php echo $is_selected ? ' is-selected' : ''; ?><?php echo $can_purchase ? '' : ' is-unavailable'; ?><?php echo $has_gallery ? ' eilmo-cf-multiple-card--has-gallery' : ''; ?><?php echo $card_has_media ? '' : ' eilmo-cf-multiple-card--no-media'; ?>"
				data-eilmo-multiple-card
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-product-type="<?php echo $is_variable ? 'variable' : 'simple'; ?>"
				data-selected="<?php echo $is_selected ? 'yes' : 'no'; ?>"
				data-purchasable="<?php echo $can_purchase ? 'yes' : 'no'; ?>"
				data-default-variation-id="<?php echo esc_attr( (string) $default_variation_id ); ?>"
			>
				<?php if ( 'yes' === (string) ( $multiple_products['show_product_number'] ?? 'yes' ) ) : ?>
					<span class="eilmo-cf-multiple-card__number" aria-hidden="true">
						<?php echo esc_html( sprintf( '%02d', $position ) ); ?>
					</span>
				<?php endif; ?>

				<button
					type="button"
					class="eilmo-cf-multiple-card__toggle"
					data-eilmo-multiple-product-toggle
					aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
					aria-label="<?php
					echo esc_attr(
						sprintf(
							/* translators: %s: Product name. */
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select %s' ),
							(string) ( $row['name'] ?? '' )
						)
					);
					?>"
					<?php disabled( ! $can_purchase ); ?>
				>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M5 12.5l4 4L19 7" />
					</svg>
				</button>

				<?php if ( $has_gallery ) : ?>
					<div class="eilmo-cf-multiple-card__gallery">
						<?php echo $gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gallery renderer escapes internally. ?>
					</div>
				<?php endif; ?>

				<div class="eilmo-cf-multiple-card__main">
					<?php if ( $has_featured_media ) : ?>
						<div class="eilmo-cf-multiple-card__media">
							<img
								class="eilmo-cf-multiple-card__image"
								src="<?php echo esc_url( (string) ( $image['display_url'] ?? '' ) ); ?>"
								alt="<?php echo esc_attr( (string) ( $image['alt'] ?? $row['name'] ?? '' ) ); ?>"
								loading="lazy"
								data-eilmo-multiple-main-image
							/>
						</div>
					<?php endif; ?>

					<div class="eilmo-cf-multiple-card__content">
						<header class="eilmo-cf-multiple-card__header">
							<div class="eilmo-cf-multiple-card__heading">
								<?php if ( 'yes' === (string) ( $multiple_products['show_title'] ?? 'yes' ) ) : ?>
									<h3 class="eilmo-cf-multiple-card__title">
										<?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?>
									</h3>
								<?php endif; ?>

								<?php if ( 'yes' === (string) ( $multiple_products['show_rating'] ?? 'yes' ) && '' !== (string) ( $row['rating_html'] ?? '' ) ) : ?>
									<div class="eilmo-cf-multiple-card__rating">
										<?php echo wp_kses_post( (string) $row['rating_html'] ); ?>
									</div>
								<?php endif; ?>
							</div>

							<div class="eilmo-cf-multiple-card__pricing">
								<?php if ( 'yes' === (string) ( $multiple_products['show_price'] ?? 'yes' ) ) : ?>
									<div class="eilmo-cf-multiple-card__price" data-eilmo-multiple-price>
										<?php echo wp_kses_post( (string) ( $row['price_html'] ?? '' ) ); ?>
									</div>
								<?php endif; ?>

								<?php if ( 'yes' === (string) ( $multiple_products['show_savings'] ?? 'no' ) && '' !== (string) ( $row['saving_html'] ?? '' ) ) : ?>
									<div class="eilmo-cf-multiple-card__saving" data-eilmo-multiple-saving>
										<?php echo wp_kses_post( (string) $row['saving_html'] ); ?>
									</div>
								<?php endif; ?>
							</div>
						</header>

						<?php if ( 'yes' === (string) ( $multiple_products['show_sale_badge'] ?? 'yes' ) && ! empty( $row['on_sale'] ) ) : ?>
							<span class="eilmo-cf-multiple-card__sale-badge" data-eilmo-multiple-sale-badge>
								<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Sale' ) ); ?>
							</span>
						<?php endif; ?>

						<?php if ( 'yes' === (string) ( $multiple_products['show_description'] ?? 'yes' ) && '' !== (string) ( $row['description_html'] ?? '' ) ) : ?>
							<div class="eilmo-cf-multiple-card__description">
								<?php echo wp_kses_post( (string) $row['description_html'] ); ?>
							</div>
						<?php endif; ?>

						<?php if ( 'yes' === (string) ( $multiple_products['show_stock'] ?? 'yes' ) ) : ?>
							<div class="eilmo-cf-multiple-card__stock" data-eilmo-multiple-stock>
								<?php echo wp_kses_post( (string) ( $row['stock_html'] ?? '' ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $is_variable && 'yes' === (string) ( $multiple_products['show_variations'] ?? 'yes' ) ) : ?>
							<div class="eilmo-cf-multiple-card__options eilmo-cf-multiple-card__options--<?php echo esc_attr( $variation_layout ); ?>" data-eilmo-multiple-options>
								<?php if ( 'sections' === $variation_layout ) : ?>
									<?php foreach ( $attribute_groups as $group ) : ?>
										<fieldset
											class="eilmo-cf-multiple-card__attribute eilmo-cf-multiple-card__attribute--<?php echo esc_attr( (string) ( $group['style'] ?? 'buttons' ) ); ?>"
										data-eilmo-multiple-attribute-group="<?php echo esc_attr( (string) ( $group['key'] ?? '' ) ); ?>"
										data-show-badge="<?php echo esc_attr( (string) ( $group['show_badge'] ?? 'no' ) ); ?>"
											style="--eilmo-cf-multiple-option-columns:<?php echo esc_attr( (string) ( $group['columns'] ?? 4 ) ); ?>;"
										>
											<legend class="eilmo-cf-multiple-card__attribute-label">
												<?php echo esc_html( (string) ( $group['label'] ?? '' ) ); ?>
											</legend>

											<div class="eilmo-cf-multiple-card__attribute-options">
												<?php foreach ( (array) ( $group['options'] ?? array() ) as $option ) : ?>
													<?php
													$option_value = (string) ( $option['value'] ?? '' );
													$option_selected = $option_value !== '' &&
														$option_value === (string) ( $default_attributes[ $group['key'] ] ?? '' );
													$option_image = isset( $option['image'] ) && is_array( $option['image'] )
														? $option['image']
														: array();
										$option_badge = isset( $option['badge'] ) && is_array( $option['badge'] )
											? $option['badge']
											: array();
										$option_badge_enabled = 'yes' === (string) ( $group['show_badge'] ?? 'no' ) &&
											'yes' === (string) ( $option_badge['enabled'] ?? 'no' ) &&
											'' !== (string) ( $option_badge['text'] ?? '' );
										?>
										<button
											type="button"
											class="eilmo-cf-multiple-option<?php echo $option_selected ? ' is-selected' : ''; ?><?php echo $option_badge_enabled ? ' has-badge' : ''; ?>"
											data-eilmo-multiple-attribute-choice
											data-attribute="<?php echo esc_attr( (string) ( $group['key'] ?? '' ) ); ?>"
											data-value="<?php echo esc_attr( $option_value ); ?>"
											aria-pressed="<?php echo $option_selected ? 'true' : 'false'; ?>"
										>
											<span
												class="eilmo-cf-multiple-option__badge eilmo-cf-multiple-option__badge--<?php echo esc_attr( (string) ( $option_badge['type'] ?? 'primary' ) ); ?>"
												data-eilmo-multiple-option-badge
												<?php echo $option_badge_enabled ? '' : 'hidden'; ?>
											>
												<?php echo esc_html( (string) ( $option_badge['text'] ?? '' ) ); ?>
											</span>
											<?php if ( 'image_grid' === (string) ( $group['style'] ?? '' ) && '' !== (string) ( $option_image['display_url'] ?? '' ) ) : ?>
												<img src="<?php echo esc_url( (string) $option_image['display_url'] ); ?>" alt="" loading="lazy" />
											<?php endif; ?>
											<span class="eilmo-cf-multiple-option__label">
												<?php echo esc_html( (string) ( $option['label'] ?? '' ) ); ?>
											</span>
											<?php if ( 'yes' === (string) ( $group['show_price'] ?? 'yes' ) ) : ?>
												<span class="eilmo-cf-multiple-option__price" data-eilmo-multiple-option-price>
													<?php echo wp_kses_post( (string) ( $option['price_html'] ?? '' ) ); ?>
												</span>
											<?php endif; ?>
											<?php if ( 'yes' === (string) ( $group['show_regular_price'] ?? 'no' ) && '' !== (string) ( $option['regular_price_html'] ?? '' ) ) : ?>
												<del class="eilmo-cf-multiple-option__regular-price" data-eilmo-multiple-option-regular-price>
													<?php echo wp_kses_post( (string) $option['regular_price_html'] ); ?>
												</del>
											<?php endif; ?>
											<?php if ( 'yes' === (string) ( $group['show_savings'] ?? 'no' ) && '' !== (string) ( $option['saving_html'] ?? '' ) ) : ?>
												<span class="eilmo-cf-multiple-option__saving" data-eilmo-multiple-option-saving>
													<?php echo wp_kses_post( (string) $option['saving_html'] ); ?>
												</span>
											<?php endif; ?>
										</button>
												<?php endforeach; ?>
											</div>
										</fieldset>
									<?php endforeach; ?>
								<?php else : ?>
									<div class="eilmo-cf-multiple-card__variation-grid" data-eilmo-multiple-variation-grid>
										<?php foreach ( $variation_rows as $variation_row ) : ?>
											<?php
										$variation_id = absint( $variation_row['id'] ?? 0 );
										$variation_selected = ! empty( $variation_row['selected'] );
										$variation_can_purchase = ! empty( $variation_row['can_purchase'] );
										$variation_badge = isset( $variation_row['badge'] ) && is_array( $variation_row['badge'] )
											? $variation_row['badge']
											: array();
											$variation_quantity = max( 0, (int) ( $variation_row['initial_quantity'] ?? 0 ) );
											$variation_maximum = (int) ( $variation_row['maximum_quantity'] ?? 0 );
											?>
											<div
												class="eilmo-cf-multiple-variation<?php echo $variation_selected ? ' is-selected' : ''; ?><?php echo $variation_can_purchase ? '' : ' is-unavailable'; ?>"
												data-eilmo-product-item
												data-eilmo-multiple-order-item
												data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
												data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
												data-price="<?php echo esc_attr( (string) ( $variation_row['price'] ?? '' ) ); ?>"
												data-item-name="<?php echo esc_attr( (string) ( $row['name'] ?? '' ) ); ?>"
												data-item-variation="<?php echo esc_attr( (string) ( $variation_row['label'] ?? '' ) ); ?>"
												data-item-image="<?php echo esc_url( (string) ( $variation_row['summary_image'] ?? '' ) ); ?>"
												data-purchasable="<?php echo $variation_can_purchase ? 'yes' : 'no'; ?>"
											>
											<button
												type="button"
												class="eilmo-cf-multiple-variation__choice"
												data-eilmo-multiple-variation-choice
												data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
												aria-pressed="<?php echo $variation_selected ? 'true' : 'false'; ?>"
												<?php disabled( ! $variation_can_purchase ); ?>
											>
												<span class="eilmo-cf-multiple-variation__label">
													<?php echo esc_html( (string) ( $variation_row['label'] ?? '' ) ); ?>
												</span>
												<?php if ( 'yes' === (string) ( $multiple_products['grid_show_price'] ?? 'yes' ) ) : ?>
													<span class="eilmo-cf-multiple-variation__price">
														<?php echo wp_kses_post( (string) ( $variation_row['current_price_html'] ?? '' ) ); ?>
													</span>
												<?php endif; ?>
												<?php if ( 'yes' === (string) ( $multiple_products['grid_show_regular_price'] ?? 'no' ) && '' !== (string) ( $variation_row['regular_price_html'] ?? '' ) ) : ?>
													<del class="eilmo-cf-multiple-variation__regular-price">
														<?php echo wp_kses_post( (string) $variation_row['regular_price_html'] ); ?>
													</del>
												<?php endif; ?>
												<?php if ( 'yes' === (string) ( $multiple_products['grid_show_savings'] ?? 'no' ) && '' !== (string) ( $variation_row['saving_html'] ?? '' ) ) : ?>
													<span class="eilmo-cf-multiple-variation__saving">
														<?php echo wp_kses_post( (string) $variation_row['saving_html'] ); ?>
													</span>
												<?php endif; ?>
										<?php if ( $show_variation_badges && 'yes' === (string) ( $variation_badge['enabled'] ?? 'no' ) && '' !== (string) ( $variation_badge['text'] ?? '' ) ) : ?>
													<span class="eilmo-cf-multiple-variation__badge eilmo-cf-multiple-variation__badge--<?php echo esc_attr( (string) ( $variation_badge['type'] ?? 'primary' ) ); ?>">
														<?php echo esc_html( (string) $variation_badge['text'] ); ?>
													</span>
												<?php endif; ?>
												<?php if ( 'yes' === (string) ( $multiple_products['grid_show_stock'] ?? 'no' ) ) : ?>
													<span class="eilmo-cf-multiple-variation__stock">
														<?php echo wp_kses_post( (string) ( $variation_row['stock_html'] ?? '' ) ); ?>
													</span>
												<?php endif; ?>
												</button>

											<div
												class="eilmo-cf-multiple-quantity eilmo-cf-multiple-variation__quantity"
												data-eilmo-multiple-quantity
												<?php echo $grid_show_quantity && $variation_selected ? '' : 'hidden'; ?>
											>
												<button
													type="button"
													class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--minus"
													data-eilmo-multiple-quantity-minus
													aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>"
													<?php disabled( ! $variation_can_purchase ); ?>
												>−</button>
												<input
													type="number"
													class="eilmo-cf-multiple-quantity__input eilmo-cf-multiple-variation__quantity-input"
													name="eilmo_cf_items[<?php echo esc_attr( (string) $variation_id ); ?>][quantity]"
													value="<?php echo esc_attr( (string) $variation_quantity ); ?>"
													min="0"
													step="1"
													data-eilmo-quantity-input
													data-eilmo-multiple-item-input
													data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
													data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
													aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity' ) ); ?>"
													<?php if ( $variation_maximum > 0 ) : ?>max="<?php echo esc_attr( (string) $variation_maximum ); ?>"<?php endif; ?>
													<?php disabled( ! $variation_can_purchase ); ?>
												/>
												<button
													type="button"
													class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--plus"
													data-eilmo-multiple-quantity-plus
													aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Increase quantity' ) ); ?>"
													<?php disabled( ! $variation_can_purchase ); ?>
												>+</button>
											</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
							<p class="eilmo-cf-multiple-card__message" data-eilmo-multiple-message aria-live="polite"></p>
						<?php endif; ?>

						<?php if ( ! $is_variable ) : ?>
							<div
								class="eilmo-cf-multiple-card__simple-action"
								data-eilmo-product-item
								data-eilmo-multiple-order-item
								data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
								data-variation-id="0"
								data-price="<?php echo esc_attr( (string) ( $row['price'] ?? '' ) ); ?>"
								data-item-name="<?php echo esc_attr( (string) ( $row['name'] ?? '' ) ); ?>"
								data-item-variation=""
								data-item-image="<?php echo esc_url( (string) ( $row['summary_image'] ?? '' ) ); ?>"
								data-purchasable="<?php echo $can_purchase ? 'yes' : 'no'; ?>"
							>
								<?php $simple_quantity = max( 0, (int) ( $row['initial_quantity'] ?? 0 ) ); ?>
								<?php $simple_maximum = (int) ( $row['maximum_quantity'] ?? 0 ); ?>
								<button
									type="button"
									class="eilmo-cf-multiple-card__select-action"
									data-eilmo-multiple-product-toggle
									aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
									<?php disabled( ! $can_purchase ); ?>
								>
									<span data-eilmo-multiple-select-label><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select' ) ); ?></span>
									<span data-eilmo-multiple-selected-label><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Selected' ) ); ?></span>
								</button>

								<div
									class="eilmo-cf-multiple-quantity eilmo-cf-multiple-card__quantity"
									data-eilmo-multiple-quantity
									<?php echo $show_quantity ? '' : 'hidden'; ?>
								>
									<button
										type="button"
										class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--minus"
										data-eilmo-multiple-quantity-minus
										aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>"
										<?php disabled( ! $can_purchase ); ?>
									>−</button>
									<input
										type="number"
										class="eilmo-cf-multiple-quantity__input eilmo-cf-multiple-card__quantity-input"
										name="eilmo_cf_items[<?php echo esc_attr( (string) $product_id ); ?>][quantity]"
										value="<?php echo esc_attr( (string) $simple_quantity ); ?>"
										min="0"
										step="1"
										data-eilmo-quantity-input
										data-eilmo-multiple-item-input
										data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
										data-variation-id="0"
										aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity' ) ); ?>"
										<?php if ( $simple_maximum > 0 ) : ?>max="<?php echo esc_attr( (string) $simple_maximum ); ?>"<?php endif; ?>
										<?php disabled( ! $can_purchase ); ?>
									/>
									<button
										type="button"
										class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--plus"
										data-eilmo-multiple-quantity-plus
										aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Increase quantity' ) ); ?>"
										<?php disabled( ! $can_purchase ); ?>
									>+</button>
								</div>
							</div>
						<?php elseif ( 'grid' !== $variation_layout || 'no' === (string) ( $multiple_products['show_variations'] ?? 'yes' ) ) : ?>
							<div class="eilmo-cf-multiple-card__order-items" data-eilmo-multiple-order-items>
								<?php foreach ( $variation_rows as $variation_row ) : ?>
									<?php
									$variation_id = absint( $variation_row['id'] ?? 0 );
									$variation_selected = ! empty( $variation_row['selected'] );
									$variation_quantity = max( 0, (int) ( $variation_row['initial_quantity'] ?? 0 ) );
									$variation_maximum = (int) ( $variation_row['maximum_quantity'] ?? 0 );
									?>
									<div
										class="eilmo-cf-multiple-card__order-item<?php echo $variation_selected ? ' is-selected' : ''; ?>"
										data-eilmo-product-item
										data-eilmo-multiple-order-item
										data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
										data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
										data-price="<?php echo esc_attr( (string) ( $variation_row['price'] ?? '' ) ); ?>"
										data-item-name="<?php echo esc_attr( (string) ( $row['name'] ?? '' ) ); ?>"
										data-item-variation="<?php echo esc_attr( (string) ( $variation_row['label'] ?? '' ) ); ?>"
										data-item-image="<?php echo esc_url( (string) ( $variation_row['summary_image'] ?? '' ) ); ?>"
										data-purchasable="<?php echo ! empty( $variation_row['can_purchase'] ) ? 'yes' : 'no'; ?>"
										<?php echo $variation_selected && $show_quantity ? '' : 'hidden'; ?>
									>
										<span class="eilmo-cf-multiple-card__order-item-label">
											<?php echo esc_html( (string) ( $variation_row['label'] ?? '' ) ); ?>
										</span>
										<div class="eilmo-cf-multiple-quantity" data-eilmo-multiple-quantity>
											<button
												type="button"
												class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--minus"
												data-eilmo-multiple-quantity-minus
												aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Decrease quantity' ) ); ?>"
												<?php disabled( empty( $variation_row['can_purchase'] ) ); ?>
											>−</button>
											<input
												type="number"
												class="eilmo-cf-multiple-quantity__input"
												name="eilmo_cf_items[<?php echo esc_attr( (string) $variation_id ); ?>][quantity]"
												value="<?php echo esc_attr( (string) $variation_quantity ); ?>"
												min="0"
												step="1"
												data-eilmo-quantity-input
												data-eilmo-multiple-item-input
												data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
												data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
												aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Quantity' ) ); ?>"
												<?php if ( $variation_maximum > 0 ) : ?>max="<?php echo esc_attr( (string) $variation_maximum ); ?>"<?php endif; ?>
												<?php disabled( empty( $variation_row['can_purchase'] ) ); ?>
											/>
											<button
												type="button"
												class="eilmo-cf-multiple-quantity__button eilmo-cf-multiple-quantity__button--plus"
												data-eilmo-multiple-quantity-plus
												aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Increase quantity' ) ); ?>"
												<?php disabled( empty( $variation_row['can_purchase'] ) ); ?>
											>+</button>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! $can_purchase ) : ?>
							<p class="eilmo-cf-multiple-card__unavailable">
								<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Currently unavailable' ) ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $is_variable ) : ?>
					<script type="application/json" data-eilmo-multiple-variation-data><?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX flags make script data safe.
						echo wp_json_encode( $variation_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
					?></script>
				<?php endif; ?>
			</article>

			<?php
			do_action( 'eilmo_cf/after_product', $product, $settings );
			?>
		<?php endforeach; ?>
	</div>

	<section
		class="eilmo-cf-multiple-selected"
		data-eilmo-multiple-selected-items
		<?php echo $show_selected_items && $initial_selected_count > 0 ? '' : 'hidden'; ?>
	>
		<header class="eilmo-cf-multiple-selected__header">
			<h3 class="eilmo-cf-multiple-selected__title">
				<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Your Selected Items' ) ); ?>
				<span>(<span data-eilmo-multiple-selected-count><?php echo esc_html( (string) $initial_selected_count ); ?></span>)</span>
			</h3>

			<?php if ( $allow_product_removal ) : ?>
				<button type="button" class="eilmo-cf-multiple-selected__clear" data-eilmo-multiple-clear>
					<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Clear All' ) ); ?>
				</button>
			<?php endif; ?>
		</header>

		<div class="eilmo-cf-multiple-selected__list" data-eilmo-multiple-selected-list></div>
	</section>
</section>
