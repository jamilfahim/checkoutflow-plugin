<?php
/**
 * Combo Offer renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ComboOffers\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\ComboOffers\Services\ComboOfferCalculator;
use EilmoCheckout\ComboOffers\Services\ComboOfferContext;
use EilmoCheckout\ComboOffers\Services\ComboOfferRepository;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Combo Offers assigned to one checkout instance.
 */
final class ComboOfferRenderer {

	/**
	 * Combo Offer repository.
	 *
	 * @var ComboOfferRepository
	 */
	private $repository;

	/**
	 * Combo Offer calculator.
	 *
	 * @var ComboOfferCalculator
	 */
	private $calculator;

	/**
	 * Signed checkout context service.
	 *
	 * @var ComboOfferContext
	 */
	private $context_service;

	/**
	 * Constructor.
	 *
	 * @param ComboOfferRepository|null $repository      Repository.
	 * @param ComboOfferCalculator|null $calculator      Calculator.
	 * @param ComboOfferContext|null    $context_service Context service.
	 */
	public function __construct(
		?ComboOfferRepository $repository = null,
		?ComboOfferCalculator $calculator = null,
		?ComboOfferContext $context_service = null
	) {

		$this->repository =
			$repository
				? $repository
				: new ComboOfferRepository();

		$this->calculator =
			$calculator
				? $calculator
				: new ComboOfferCalculator();

		$this->context_service =
			$context_service
				? $context_service
				: new ComboOfferContext();
	}


	/**
	 * Render the non-visual Combo state adapter used by external triggers.
	 *
	 * All enabled Combo Offers are signed into the checkout context. The
	 * existing Combo engine, summary and order validation still operate on
	 * the same inputs, but customers never see or click a Combo card inside
	 * the checkout form.
	 *
	 * @param string $instance_id Checkout instance ID.
	 *
	 * @return string
	 */
	public function render_state( string $instance_id ): string {
		$offer_ids = array_values(
			array_filter(
				array_map(
					static function ( array $offer ): string {
						return sanitize_key( (string) ( $offer['id'] ?? '' ) );
					},
					$this->repository->get_all( true )
				)
			)
		);

		if ( empty( $offer_ids ) ) {
			return '';
		}

		$html = $this->render(
			array(
				'instance_id' => $instance_id,
				'offer_ids' => $offer_ids,
				'selected_offer_ids' => array(),
				'selection_mode' => 'multiple',
				'title' => '',
				'show_description' => 'no',
			)
		);

		if ( '' === $html ) {
			return '';
		}

		return '<div class="eilmo-cf-combo-state" data-eilmo-combo-state hidden aria-hidden="true">' . $html . '</div>';
	}

	/**
	 * Render Combo Offers.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

		$instance_id =
			$this->get_instance_id(
				$context
			);

		$offer_ids =
			$this->normalize_offer_ids(
				$context['offer_ids'] ??
					array()
			);

		if ( empty( $offer_ids ) ) {
			return '';
		}

		$offers =
			$this->repository->get_many(
				$offer_ids,
				true
			);

		if ( empty( $offers ) ) {
			return '';
		}

		$selection_mode =
			$this->get_selection_mode(
				$context
			);

		$selected_offer_ids =
			$this->normalize_offer_ids(
				$context['selected_offer_ids'] ??
					array()
			);

		$selected_offer_ids =
			array_values(
				array_intersect(
					$selected_offer_ids,
					$offer_ids
				)
			);

		if (
			'single' === $selection_mode &&
			count( $selected_offer_ids ) > 1
		) {
			$selected_offer_ids =
				array_slice(
					$selected_offer_ids,
					0,
					1
				);
		}

		$views =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$view =
				$this->build_offer_view(
					$offer
				);

			if ( empty( $view ) ) {
				continue;
			}

			$views[] =
				$view;
		}

		if ( empty( $views ) ) {
			return '';
		}

		$views =
			apply_filters(
				'eilmo_cf/combo_offers/render_views',
				$views,
				$context
			);

		if (
			! is_array( $views ) ||
			empty( $views )
		) {
			return '';
		}

		$allowed_ids =
			array_values(
				array_filter(
					array_map(
						static function ( array $view ): string {

							return sanitize_key(
								(string) (
									$view['id'] ??
										''
								)
							);
						},
						$views
					)
				)
			);

		if ( empty( $allowed_ids ) ) {
			return '';
		}

		/*
		 * Signed per-checkout context.
		 *
		 * Important:
		 * selection_mode is signed together with
		 * allowed Combo IDs and checkout instance ID.
		 */
		$signed_context =
			$this->context_service->create(
				$allowed_ids,
				$instance_id,
				$selection_mode
			);

		$context_json =
			wp_json_encode(
				$signed_context
			);

		if ( ! is_string( $context_json ) ) {
			$context_json =
				'{}';
		}

		$title =
			sanitize_text_field(
				(string) (
					$context['title'] ??
						''
				)
			);

		if ( '' === $title ) {
			$title =
				$this->get_global_title();
		}

		$show_description =
			'yes' ===
				(
					$context[
						'show_description'
					] ??
						'yes'
				);

		$display_settings = $this->get_global_display_settings();
		$card_layout = (string) ( $display_settings['card_layout'] ?? 'two_columns' );
		$card_style = sanitize_key( (string) ( $display_settings['card_style'] ?? 'highlighted' ) );
		$style_settings = isset( $display_settings['style'] ) && is_array( $display_settings['style'] )
			? $display_settings['style']
			: array();
		$style_attribute = $this->get_combo_style_attribute( $style_settings );
		$section_classes = array(
			'eilmo-cf-combo-offers',
			'full_width' === $card_layout
				? 'eilmo-cf-combo-offers--full-width'
				: 'eilmo-cf-combo-offers--two-columns',
			'eilmo-cf-combo-offers--style-' . sanitize_html_class( $card_style ),
		);

		ob_start();
		?>

		<section
			class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"
			style="<?php echo esc_attr( $style_attribute ); ?>"
			data-eilmo-combo-offers
			data-selection-mode="<?php echo esc_attr( $selection_mode ); ?>"
			data-eilmo-combo-context="<?php echo esc_attr( $context_json ); ?>"
		>

			<div class="eilmo-cf-combo-offers__header">

				<h3 class="eilmo-cf-combo-offers__title">
					<?php echo esc_html( $title ); ?>
				</h3>

			</div>

			<div
				class="eilmo-cf-combo-offers__list"
				data-eilmo-combo-list
			>

				<?php foreach ( $views as $view_index => $view ) : ?>

					<?php
					if ( ! is_array( $view ) ) {
						continue;
					}

					$offer_id =
						sanitize_key(
							(string) (
								$view['id'] ??
									''
							)
						);

					if ( '' === $offer_id ) {
						continue;
					}

					$is_selected =
						in_array(
							$offer_id,
							$selected_offer_ids,
							true
						);

					$is_available =
						! empty(
							$view['available']
						);

					$regular_total = (float) ( $view['regular_total'] ?? 0 );
					$combo_discount = (float) ( $view['discount'] ?? 0 );
					$saving_percent = $regular_total > 0 && $combo_discount > 0
						? (int) round( ( $combo_discount / $regular_total ) * 100 )
						: 0;

					$input_type =
						'single' === $selection_mode
							? 'radio'
							: 'checkbox';

					$input_id =
						sanitize_html_class(
							$instance_id .
								'-combo-' .
								$offer_id .
								'-' .
								(string) $view_index
						);

					$input_name =
						'eilmo_combo_' .
						sanitize_key(
							$instance_id
						);

					if (
						'multiple' ===
							$selection_mode
					) {
						$input_name .=
							'[]';
					}

					$card_classes =
						array(
							'eilmo-cf-combo-offer',
						);

					if ( $is_selected ) {
						$card_classes[] =
							'is-selected';
					}

					if ( ! $is_available ) {
						$card_classes[] =
							'is-unavailable';
					}
					?>

					<div
						class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
						data-eilmo-combo-offer
						data-combo-id="<?php echo esc_attr( $offer_id ); ?>"
						data-selected="<?php echo esc_attr( $is_selected ? 'yes' : 'no' ); ?>"
						data-available="<?php echo esc_attr( $is_available ? 'yes' : 'no' ); ?>"
						data-regular-total="<?php echo esc_attr( (string) ( $view['regular_total'] ?? 0 ) ); ?>"
						data-combo-total="<?php echo esc_attr( (string) ( $view['combo_total'] ?? 0 ) ); ?>"
						data-combo-discount="<?php echo esc_attr( (string) ( $view['discount'] ?? 0 ) ); ?>"
						data-apply-automatic-discount="<?php echo esc_attr( (string) ( $view['apply_automatic_discount'] ?? 'no' ) ); ?>"
						data-apply-coupon="<?php echo esc_attr( (string) ( $view['apply_coupon'] ?? 'yes' ) ); ?>"
						data-apply-full-payment-discount="<?php echo esc_attr( (string) ( $view['apply_full_payment_discount'] ?? 'yes' ) ); ?>"
					>

						<label
							class="eilmo-cf-combo-offer__label"
							for="<?php echo esc_attr( $input_id ); ?>"
						>

							<span class="eilmo-cf-combo-offer__selector">

								<input
									type="<?php echo esc_attr( $input_type ); ?>"
									id="<?php echo esc_attr( $input_id ); ?>"
									name="<?php echo esc_attr( $input_name ); ?>"
									value="<?php echo esc_attr( $offer_id ); ?>"
									data-eilmo-combo-input
									<?php checked( $is_selected ); ?>
									<?php disabled( ! $is_available ); ?>
								>

								<span
									class="eilmo-cf-combo-offer__control"
									aria-hidden="true"
								></span>

							</span>

							<span class="eilmo-cf-combo-offer__body">

								<span class="eilmo-cf-combo-offer__eyebrow">

									<span class="eilmo-cf-combo-offer__eyebrow-main">

										<?php $offer_icon = $this->get_offer_icon_html( $view ); ?>
										<?php if ( '' !== $offer_icon ) : ?>
											<span class="eilmo-cf-combo-offer__icon" aria-hidden="true">
												<?php
												// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated from trusted preset SVG or wp_get_attachment_image().
												echo $offer_icon;
												?>
											</span>
										<?php endif; ?>

										<?php if ( ! empty( $view['eyebrow'] ) ) : ?>
											<span class="eilmo-cf-combo-offer__eyebrow-label"><?php echo esc_html( (string) $view['eyebrow'] ); ?></span>
										<?php endif; ?>

									</span>

									<?php if ( ! empty( $view['badge'] ) ) : ?>
										<span class="eilmo-cf-combo-offer__badge"><?php echo esc_html( (string) $view['badge'] ); ?></span>
									<?php endif; ?>

								</span>

								<span class="eilmo-cf-combo-offer__top">

									<span class="eilmo-cf-combo-offer__title">

										<?php
										echo esc_html(
											(string) (
												$view['title'] ??
													''
											)
										);
										?>

									</span>

									<span class="eilmo-cf-combo-offer__pricing">

										<?php
										if (
											(float) (
												$view[
													'discount'
												] ??
													0
											) > 0
										) :
											?>

											<del class="eilmo-cf-combo-offer__regular-price">

												<?php
												// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() returns WooCommerce formatted price HTML.
												echo wc_price(
													(float) (
														$view[
															'regular_total'
														] ??
															0
													)
												);
												?>

											</del>

										<?php endif; ?>

										<strong class="eilmo-cf-combo-offer__price">

											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() returns WooCommerce formatted price HTML.
											echo wc_price(
												(float) (
													$view[
														'combo_total'
													] ??
														0
												)
											);
											?>

										</strong>

										<?php if ( $combo_discount > 0 ) : ?>
											<span class="eilmo-cf-combo-offer__saving">
												<?php
												printf(
													/* translators: 1: formatted saving amount, 2: percentage. */
													esc_html__( 'Save %1$s (%2$d%%)', 'eilmo-checkout-flow' ),
													wp_kses_post( wc_price( $combo_discount ) ),
													absint( $saving_percent )
												);
												?>
											</span>
										<?php endif; ?>

									</span>

								</span>

								<?php
								if (
									$show_description &&
									! empty(
										$view[
											'description'
										]
									)
								) :
									?>

									<span class="eilmo-cf-combo-offer__description">

										<?php
										echo wp_kses_post(
											(string) $view[
												'description'
											]
										);
										?>

									</span>

								<?php endif; ?>

								<?php
								if (
									isset(
										$view[
											'items'
										]
									) &&
									is_array(
										$view[
											'items'
										]
									) &&
									! empty(
										$view[
											'items'
										]
									)
								) :
									?>

									<span class="eilmo-cf-combo-offer__items">

										<?php foreach ( $view['items'] as $item ) : ?>

											<?php
											if ( ! is_array( $item ) ) {
												continue;
											}
											?>

											<span class="eilmo-cf-combo-offer__item<?php echo empty( $item['image'] ) ? ' eilmo-cf-combo-offer__item--no-image' : ''; ?>" data-product-id="<?php echo esc_attr( (string) absint( $item['product_id'] ?? 0 ) ); ?>" data-variation-id="<?php echo esc_attr( (string) absint( $item['variation_id'] ?? 0 ) ); ?>" data-quantity="<?php echo esc_attr( (string) (float) ( $item['quantity'] ?? 1 ) ); ?>" data-line-total="<?php echo esc_attr( (string) (float) ( $item['line_total'] ?? 0 ) ); ?>">

												<?php if ( ! empty( $item['image'] ) ) : ?>

													<span class="eilmo-cf-combo-offer__item-image">

														<?php
														echo wp_kses_post(
															(string) $item[
																'image'
															]
														);
														?>

													</span>

												<?php endif; ?>

												<span class="eilmo-cf-combo-offer__item-content">

													<span class="eilmo-cf-combo-offer__item-title">

														<?php
														echo esc_html(
															(string) (
																$item[
																	'title'
																] ??
																	''
															)
														);
														?>

													</span>

													<span class="eilmo-cf-combo-offer__item-meta">

														<?php
														printf(
															/* translators: %s: item quantity. */
															esc_html__(
																'Qty: %s',
																'eilmo-checkout-flow'
															),
															esc_html(
																$this->format_quantity(
																	(float) (
																		$item[
																			'quantity'
																		] ??
																			1
																	)
																)
															)
														);
														?>

													</span>

												</span>

											</span>

										<?php endforeach; ?>

									</span>

								<?php endif; ?>

								<span class="eilmo-cf-combo-offer__footer">

									<?php if ( ! $is_available ) : ?>

										<span class="eilmo-cf-combo-offer__availability">

											<?php
											esc_html_e(
												'Currently unavailable',
												'eilmo-checkout-flow'
											);
											?>

										</span>

									<?php else : ?>

										<span class="eilmo-cf-combo-offer__action">

											<?php
											esc_html_e(
												'Add Combo',
												'eilmo-checkout-flow'
											);
											?>

										</span>

									<?php endif; ?>

								</span>

							</span>

						</label>

					</div>

				<?php endforeach; ?>

			</div>

		</section>

		<?php

		$output =
			ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		return (string) apply_filters(
			'eilmo_cf/combo_offers/html',
			$output,
			$context,
			$views
		);
	}

	/**
	 * Build one Combo Offer view.
	 *
	 * @param array<string, mixed> $offer Offer.
	 *
	 * @return array<string, mixed>
	 */
	private function build_offer_view(
		array $offer
	): array {

		$offer_id =
			sanitize_key(
				(string) (
					$offer['id'] ??
						''
				)
			);

		if ( '' === $offer_id ) {
			return array();
		}

		$items =
			isset( $offer['items'] ) &&
			is_array( $offer['items'] )
				? $offer['items']
				: array();

		if ( empty( $items ) ) {
			return array();
		}

		$item_views =
			array();

		$regular_total =
			0.0;

		$available =
			true;

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				$available =
					false;

				continue;
			}

			$item_view =
				$this->build_item_view(
					$item
				);

			if ( empty( $item_view ) ) {
				$available =
					false;

				continue;
			}

			$item_views[] =
				$item_view;

			$regular_total +=
				(float) (
					$item_view[
						'line_total'
					] ??
						0
				);

			if (
				empty(
					$item_view[
						'available'
					]
				)
			) {
				$available =
					false;
			}
		}

		$regular_total =
			$this->normalize_amount(
				$regular_total
			);

		if (
			empty( $item_views ) ||
			$regular_total <= 0
		) {
			return array();
		}

		$calculation =
			$this->calculator->calculate(
				$offer,
				$regular_total
			);

		if (
			! is_array( $calculation ) ||
			empty(
				$calculation[
					'valid'
				]
			)
		) {
			return array();
		}

		return array(
			'id' =>
				$offer_id,

			'title' =>
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							''
					)
				),

			'description' =>
				wp_kses_post(
					(string) (
						$offer[
							'description'
						] ??
							''
					)
				),

			'badge' =>
				sanitize_text_field(
					(string) (
						$offer[
							'badge'
						] ??
							''
					)
				),

			'eyebrow' =>
				sanitize_text_field(
					(string) ( $offer['eyebrow'] ?? 'COMBO OFFER' )
				),

			'icon_type' =>
				sanitize_key( (string) ( $offer['icon_type'] ?? 'preset' ) ),

			'icon_preset' =>
				sanitize_key( (string) ( $offer['icon_preset'] ?? 'flame' ) ),

			'icon_media_id' =>
				absint( $offer['icon_media_id'] ?? 0 ),

			'pricing_type' =>
				sanitize_key(
					(string) (
						$calculation[
							'pricing_type'
						] ??
							''
					)
				),

			'pricing_value' =>
				$this->normalize_amount(
					$calculation[
						'pricing_value'
					] ??
						0
				),

			'regular_total' =>
				$this->normalize_amount(
					$calculation[
						'regular_total'
					] ??
						$regular_total
				),

			'discount' =>
				$this->normalize_amount(
					$calculation[
						'discount'
					] ??
						0
				),

			'combo_total' =>
				$this->normalize_amount(
					$calculation[
						'combo_total'
					] ??
						$regular_total
				),

			/*
			 * Discount compatibility is presentation data only.
			 *
			 * The server still reloads and validates these values
			 * from saved Combo Offer settings before order creation.
			 * These frontend values are used only so live checkout
			 * calculations can mirror the authoritative behavior.
			 */
			'apply_automatic_discount' =>
				'yes' ===
					(
						$offer[
							'apply_automatic_discount'
						] ??
							'no'
					)
						? 'yes'
						: 'no',

			'apply_coupon' =>
				'no' ===
					(
						$offer[
							'apply_coupon'
						] ??
							'yes'
					)
						? 'no'
						: 'yes',

			'apply_full_payment_discount' =>
				'no' ===
					(
						$offer[
							'apply_full_payment_discount'
						] ??
							'yes'
					)
						? 'no'
						: 'yes',

			'available' =>
				$available,

			'items' =>
				$item_views,
		);
	}

	/**
	 * Build Combo item view.
	 *
	 * @param array<string, mixed> $item Item.
	 *
	 * @return array<string, mixed>
	 */
	private function build_item_view(
		array $item
	): array {

		if (
			! function_exists(
				'wc_get_product'
			)
		) {
			return array();
		}

		$product_id =
			absint(
				$item[
					'product_id'
				] ??
					0
			);

		$variation_id =
			absint(
				$item[
					'variation_id'
				] ??
					0
			);

		$quantity =
			$this->normalize_quantity(
				$item[
					'quantity'
				] ??
					1
			);

		if (
			$product_id <= 0 ||
			$quantity <= 0
		) {
			return array();
		}

		$lookup_id =
			$variation_id > 0
				? $variation_id
				: $product_id;

		$product =
			wc_get_product(
				$lookup_id
			);

		if (
			! $product instanceof
				WC_Product
		) {
			return array();
		}

		if (
			$variation_id > 0 &&
			absint(
				$product->get_parent_id()
			) !==
				$product_id
		) {
			return array();
		}

		$line_total =
			function_exists(
				'wc_get_price_to_display'
			)
				? (float) wc_get_price_to_display(
					$product,
					array(
						'qty' =>
							$quantity,
					)
				)
				: (
					(float) $product->get_price() *
						$quantity
				);

		$line_total =
			$this->normalize_amount(
				$line_total
			);

		$available =
			$this->is_product_available(
				$product,
				$quantity,
				$variation_id
			);

		$image_source = sanitize_key( (string) ( $item['image_source'] ?? 'product' ) );
		$image_id = absint( $item['image_id'] ?? 0 );
		$image = '';

		if ( 'hidden' !== $image_source ) {
			if ( 'custom' === $image_source && $image_id > 0 ) {
				$image = (string) wp_get_attachment_image(
					$image_id,
					'woocommerce_thumbnail',
					false,
					array( 'loading' => 'lazy' )
				);
			}

			if ( '' === $image ) {
				$image = (string) $product->get_image(
					'woocommerce_thumbnail',
					array( 'loading' => 'lazy' )
				);
			}
		}

		return array(
			'product_id' =>
				$product_id,

			'variation_id' =>
				$variation_id,

			'quantity' =>
				$quantity,

			'title' =>
				sanitize_text_field(
					(string) $product->get_name()
				),

			'image' =>
				$image,

			'image_source' =>
				$image_source,

			'image_id' =>
				$image_id,

			'line_total' =>
				$line_total,

			'available' =>
				$available,
		);
	}

	/**
	 * Check product availability.
	 *
	 * @param WC_Product $product      Product.
	 * @param float      $quantity     Quantity.
	 * @param int        $variation_id Variation.
	 *
	 * @return bool
	 */
	private function is_product_available(
		WC_Product $product,
		float $quantity,
		int $variation_id
	): bool {

		if (
			! $product->is_purchasable()
		) {
			return false;
		}

		if (
			0 === $variation_id &&
			$product->is_type(
				'variable'
			)
		) {
			return false;
		}

		if (
			! $product->is_in_stock()
		) {
			return false;
		}

		if (
			$product->managing_stock() &&
			! $product->backorders_allowed() &&
			! $product->has_enough_stock(
				$quantity
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get one trusted Combo Offer icon.
	 *
	 * @param array<string, mixed> $view Offer view.
	 *
	 * @return string
	 */
	private function get_offer_icon_html(
		array $view
	): string {

		$type = sanitize_key( (string) ( $view['icon_type'] ?? 'preset' ) );

		if ( 'none' === $type ) {
			return '';
		}

		if ( 'custom' === $type ) {
			$media_id = absint( $view['icon_media_id'] ?? 0 );

			return $media_id > 0
				? (string) wp_get_attachment_image(
					$media_id,
					'thumbnail',
					false,
					array( 'loading' => 'lazy' )
				)
				: '';
		}

		$icons = array(
			'flame' => '<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M13.5 2.5c.6 3-1.7 4.2-1.7 6.4 0 1.4.9 2.2 2 2.2 1.8 0 2.9-1.6 2.6-3.5 2.1 1.9 3.1 4.2 2.6 6.9-.7 3.9-3.8 6.5-7.5 6.5-4.1 0-7.5-3.2-7.5-7.4 0-3.1 1.7-5.7 4.8-8 .1 2.4.8 3.7 2 4.1-.4-2.8 1-5.4 2.7-7.2Z" fill="currentColor"/></svg>',
			'gift'  => '<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M4 10h16v10H4V10Zm-1-4h18v4H3V6Zm9 0v14M12 6H8.5A2.5 2.5 0 1 1 11 3.5L12 6Zm0 0h3.5A2.5 2.5 0 1 0 13 3.5L12 6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'star'  => '<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="m12 2.8 2.8 5.7 6.3.9-4.6 4.4 1.1 6.3-5.6-3-5.6 3 1.1-6.3-4.6-4.4 6.3-.9L12 2.8Z" fill="currentColor"/></svg>',
			'bolt'  => '<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M13.5 2 5 13h6l-.5 9L19 10h-6l.5-8Z" fill="currentColor"/></svg>',
		);

		$preset = sanitize_key( (string) ( $view['icon_preset'] ?? 'flame' ) );

		return (string) ( $icons[ $preset ] ?? $icons['flame'] );
	}

	/**
	 * Build sanitized Combo style custom properties.
	 *
	 * @param array<string, mixed> $style Style settings.
	 *
	 * @return string
	 */
	private function get_combo_style_attribute(
		array $style
	): string {

		$color_keys = array(
			'gradient_start',
			'gradient_end',
			'card_border',
			'card_text',
			'muted_text',
			'icon_background',
			'icon_color',
			'badge_background',
			'badge_gradient_end',
			'badge_text',
			'regular_price',
			'price',
			'saving_background',
			'saving_text',
			'image_background',
			'plus_color',
			'button_background',
			'button_text',
			'button_hover_background',
			'button_hover_text',
			'selected_border',
			'selected_border_end',
			'shadow_color',
		);

		$declarations = array();

		foreach ( $color_keys as $key ) {
			$value = sanitize_hex_color( (string) ( $style[ $key ] ?? '' ) );

			if ( $value ) {
				$declarations[] = sprintf(
					'--eilmo-cf-combo-%1$s:%2$s',
					str_replace( '_', '-', $key ),
					$value
				);
			}
		}

		$number_keys = array(
			'card_radius'   => array( 0, 40 ),
			'card_padding'  => array( 8, 48 ),
			'image_size'    => array( 42, 140 ),
			'button_radius' => array( 0, 40 ),
		);

		foreach ( $number_keys as $key => $limits ) {
			$value = max( (int) $limits[0], min( (int) $limits[1], absint( $style[ $key ] ?? 0 ) ) );
			$declarations[] = sprintf(
				'--eilmo-cf-combo-%1$s:%2$dpx',
				str_replace( '_', '-', $key ),
				$value
			);
		}

		return implode( ';', $declarations );
	}

	/**
	 * Global Combo card display settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_global_display_settings(): array {

		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$combo_defaults = isset( $defaults['combo_offers'] ) && is_array( $defaults['combo_offers'] )
			? $defaults['combo_offers']
			: array();
		$combo_stored = isset( $stored['combo_offers'] ) && is_array( $stored['combo_offers'] )
			? $stored['combo_offers']
			: array();

		$settings = array_replace_recursive( $combo_defaults, $combo_stored );
		$layout = sanitize_key( (string) ( $settings['card_layout'] ?? 'full_width' ) );
		if ( ! in_array( $layout, array( 'full_width', 'two_columns' ), true ) ) {
			$layout = 'full_width';
		}

		$card_style = sanitize_key( (string) ( $settings['card_style'] ?? 'highlighted' ) );
		if ( 'highlighted' !== $card_style ) {
			$card_style = 'highlighted';
		}

		return array(
			'card_layout' => $layout,
			'card_style'  => $card_style,
			'style'       => isset( $settings['style'] ) && is_array( $settings['style'] )
				? $settings['style']
				: array(),
		);
	}

	/**
	 * Global Combo section title.
	 *
	 * @return string
	 */
	private function get_global_title(): string {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$default_title =
			sanitize_text_field(
				(string) (
					$defaults[
						'combo_offers'
					][
						'title'
					] ??
						__(
							'Combo Offers',
							'eilmo-checkout-flow'
						)
				)
			);

		$stored_title =
			isset(
				$stored[
					'combo_offers'
				]
			) &&
			is_array(
				$stored[
					'combo_offers'
				]
			)
				? sanitize_text_field(
					(string) (
						$stored[
							'combo_offers'
						][
							'title'
						] ??
							''
					)
				)
				: '';

		return '' !== $stored_title
			? $stored_title
			: $default_title;
	}

	/**
	 * Checkout instance ID.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	private function get_instance_id(
		array $context
	): string {

		$instance_id =
			sanitize_key(
				(string) (
					$context[
						'instance_id'
					] ??
						''
				)
			);

		return '' !== $instance_id
			? $instance_id
			: 'eilmo-cf-checkout';
	}

	/**
	 * Selection mode.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	private function get_selection_mode(
		array $context
	): string {

		$selection_mode =
			sanitize_key(
				(string) (
					$context[
						'selection_mode'
					] ??
						'multiple'
				)
			);

		return in_array(
			$selection_mode,
			array(
				'single',
				'multiple',
			),
			true
		)
			? $selection_mode
			: 'multiple';
	}

	/**
	 * Normalize Combo IDs.
	 *
	 * @param mixed $offer_ids IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_offer_ids(
		$offer_ids
	): array {

		if ( is_string( $offer_ids ) ) {
			$offer_ids =
				preg_split(
					'/[\s,]+/',
					$offer_ids
				);
		}

		if ( ! is_array( $offer_ids ) ) {
			return array();
		}

		$normalized =
			array();

		foreach ( $offer_ids as $offer_id ) {

			$offer_id =
				sanitize_key(
					(string) $offer_id
				);

			if (
				'' === $offer_id ||
				in_array(
					$offer_id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$offer_id;
		}

		return $normalized;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		if ( ! is_numeric( $quantity ) ) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
	}

	/**
	 * Format quantity.
	 *
	 * @param float $quantity Quantity.
	 *
	 * @return string
	 */
	private function format_quantity(
		float $quantity
	): string {

		if (
			(float) (int) $quantity ===
				$quantity
		) {
			return (string) (int) $quantity;
		}

		return rtrim(
			rtrim(
				number_format(
					$quantity,
					2,
					'.',
					''
				),
				'0'
			),
			'.'
		);
	}

	/**
	 * Normalize amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return float
	 */
	private function normalize_amount(
		$amount
	): float {

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount =
				wc_format_decimal(
					$amount,
					false
				);
		}

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}
