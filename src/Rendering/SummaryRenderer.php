<?php
/**
 * Checkout summary renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Coupons\Rendering\CouponRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the checkout order summary.
 */
final class SummaryRenderer {

	/**
	 * Summary instance counter.
	 *
	 * @var int
	 */
	private static $instance_counter = 0;

	/**
	 * Render checkout summary.
	 *
	 * Desktop:
	 * Full summary panel.
	 *
	 * Mobile:
	 * Fixed compact total bar + slide-up full summary drawer.
	 *
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
	public function render(
		array $settings = array()
	): string {

		$amounts =
			$this->get_initial_amounts();

		$labels =
			$this->get_summary_labels();
        $labels = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $labels );

		$visibility =
			$this->get_feature_visibility(
				$settings
			);

		/**
		 * Filters initial summary amounts.
		 *
		 * These are display values only.
		 * Final checkout calculations must always be
		 * validated server-side.
		 *
		 * @param array<string, float> $amounts  Summary amounts.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		$amounts =
			apply_filters(
				'eilmo_cf/summary_initial_amounts',
				$amounts,
				$settings
			);

		$amounts =
			$this->normalize_amounts(
				$amounts
			);

		/**
		 * Filters summary frontend labels.
		 *
		 * @param array<string, string> $labels   Summary labels.
		 * @param array<string, mixed>  $settings Checkout settings.
		 */
		$labels =
			apply_filters(
				'eilmo_cf/summary_labels',
				$labels,
				$settings
			);

		if ( ! is_array( $labels ) ) {
			$labels =
				$this->get_summary_labels();
		}

		/**
		 * Filters summary feature visibility.
		 *
		 * @param array<string, bool>  $visibility Feature visibility.
		 * @param array<string, mixed> $settings   Checkout settings.
		 */
		$visibility =
			apply_filters(
				'eilmo_cf/summary_visibility',
				$visibility,
				$settings
			);

		if ( ! is_array( $visibility ) ) {
			$visibility =
				$this->get_feature_visibility(
					$settings
				);
		}

		$summary_id =
			$this->generate_summary_id();

		$title_id =
			$summary_id .
			'-title';

		$coupon_html =
			$this->render_coupon(
				$summary_id,
				$settings
			);

		$mobile_collapsed =
			isset(
				$settings[
					'mobile_summary_collapsed'
				]
			)
				? (string) $settings[
					'mobile_summary_collapsed'
				]
				: 'yes';

		$is_open =
			'no' ===
				$mobile_collapsed;

		ob_start();
		?>

		<div
			class="eilmo-cf-summary-shell"
			data-eilmo-summary-shell
			data-summary-open="<?php echo esc_attr( $is_open ? 'yes' : 'no' ); ?>"
		>

			<div
				class="eilmo-cf-summary-backdrop"
				data-eilmo-summary-backdrop
				aria-hidden="true"
			></div>

			<section
				id="<?php echo esc_attr( $summary_id ); ?>"
				class="eilmo-cf-summary"
				data-eilmo-summary
				data-eilmo-summary-panel
				aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			>

				<div
					class="eilmo-cf-summary__drawer-handle"
					aria-hidden="true"
				>
					<span></span>
				</div>

				<?php
				/**
				 * Fires before checkout summary content.
				 *
				 * @param array<string, float> $amounts  Summary amounts.
				 * @param array<string, mixed> $settings Checkout settings.
				 */
				do_action(
					'eilmo_cf/summary_start',
					$amounts,
					$settings
				);
				?>

				<div class="eilmo-cf-summary__header">

					<h3
						id="<?php echo esc_attr( $title_id ); ?>"
						class="eilmo-cf-summary__title"
					>
						<?php echo esc_html( (string) ( $labels['title'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Order Summary' ) ) ); ?>
					</h3>

					<button
						type="button"
						class="eilmo-cf-summary__close"
						data-eilmo-summary-close
						aria-label="<?php
						echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Close order summary' ) );
						?>"
					>
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" /></svg>
					</button>

				</div>

				<?php
				$summary_display = CheckoutDisplaySettings::get_section( 'summary' );
				$show_summary_items = (string) ( $summary_display['show_selected_items'] ?? 'yes' );
				$show_summary_quantity = (string) ( $summary_display['show_quantity'] ?? 'yes' );
					$single_product = isset( $settings['single_product'] ) && is_array( $settings['single_product'] )
						? $settings['single_product']
						: array();
					$multiple_products = isset( $settings['multiple_products'] ) && is_array( $settings['multiple_products'] )
						? $settings['multiple_products']
						: array();
					$product_mode = (string) ( $settings['product_mode'] ?? '' );
					$is_single_product = 'single' === $product_mode
						|| ( '' === $product_mode && 'yes' === (string) ( $settings['single_product_mode'] ?? 'no' ) );
					$is_multiple_products = 'multiple' === $product_mode
						|| ( '' === $product_mode && 'yes' === (string) ( $settings['multiple_products_mode'] ?? 'no' ) );

					if ( $is_single_product ) {
						$show_summary_items = (string) ( $single_product['show_summary_items'] ?? 'yes' );
						$show_summary_quantity = (string) ( $single_product['summary_items_show_quantity'] ?? 'yes' );
					} elseif ( $is_multiple_products ) {
						$show_summary_items = (string) ( $multiple_products['show_summary_items'] ?? 'yes' );
						$show_summary_quantity = (string) ( $multiple_products['summary_items_show_quantity'] ?? 'yes' );
					}

					$show_summary_items = 'no' === $show_summary_items ? 'no' : 'yes';
					/* Quantity is edited beside the selected product in the checkout body, never inside the summary. */
					$show_summary_quantity = 'no';

				if ( 'yes' === $show_summary_items ) :
				?>
					<div class="eilmo-cf-summary__selected-items" data-eilmo-summary-items
						data-show-thumbnail="<?php echo esc_attr( (string) ( $summary_display['show_thumbnail'] ?? 'yes' ) ); ?>"
						data-show-variation="<?php echo esc_attr( (string) ( $summary_display['show_variation'] ?? 'yes' ) ); ?>"
						data-show-quantity="<?php echo esc_attr( $show_summary_quantity ); ?>"
						data-show-price="<?php echo esc_attr( (string) ( $summary_display['show_item_price'] ?? 'yes' ) ); ?>">
						<h4 class="eilmo-cf-summary__selected-title"><?php echo esc_html( (string) ( $labels['selected_items_title'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Your Order' ) ) ); ?></h4>
						<div class="eilmo-cf-summary__selected-list" data-eilmo-summary-items-list></div>
					</div>
				<?php endif; ?>

				<div class="eilmo-cf-summary__rows">

					<?php
					/*
					 * ---------------------------------------------
					 * Product Total
					 * ---------------------------------------------
					 *
					 * Frontend summary.js calculates this as:
					 *
					 * Normal selected product regular total
					 * + selected Combo component regular total
					 * + selected Order Bump regular total.
					 */
					$this->render_row(
						'product-total',
						(string) ( $labels['product_total_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Product Total' ) ),
						$amounts[
							'product_total'
						]
					);

					/*
					 * ---------------------------------------------
					 * Combo Discount
					 * ---------------------------------------------
					 *
					 * This is independent from Automatic Discount.
					 *
					 * It represents only the saving produced by
					 * selected Combo Offers.
					 */
					if (
						! empty(
							$visibility[
								'combo_discount'
							]
						)
					) {
						$this->render_row(
							'combo-discount',
							(string) (
								$labels[
									'combo_discount'
								] ??
									\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Combo Discount' )
							),
							$amounts[
								'combo_discount'
							],
							true
						);
					}

					/*
					 * ---------------------------------------------
					 * Special Offer
					 * ---------------------------------------------
					 *
					 * Customer-facing label for Order Bump savings.
					 *
					 * The initial label is "Special Offer".
					 * Frontend summary.js may replace this label
					 * dynamically from the selected Order Bump badge.
					 *
					 * Examples:
					 *
					 * Special Offer
					 * Special Offer — Save 20%
					 * Special Offers
					 */
					if (
						! empty(
							$visibility[
								'order_bump_discount'
							]
						)
					) {
						$this->render_row(
							'order-bump-discount',
							(string) (
								$labels[
									'order_bump_discount'
								] ??
									\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Special Offer' )
							),
							$amounts[
								'order_bump_discount'
							],
							true
						);
					}

					/*
					 * ---------------------------------------------
					 * Automatic Discount
					 * ---------------------------------------------
					 */
					if (
						! empty(
							$visibility[
								'automatic_discount'
							]
						)
					) {
						$this->render_row(
							'automatic-discount',
							(string) ( $labels['automatic_discount_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Automatic Discount' ) ),
							$amounts[
								'automatic_discount'
							],
							true
						);
					}

					/*
					 * ---------------------------------------------
					 * Free Gift
					 * ---------------------------------------------
					 * Populated live from auto-applied Free Gift rules.
					 */
					?>
					<div class="eilmo-cf-summary__row eilmo-cf-summary__row--free-gift" data-eilmo-summary-row="free-gift" hidden>
						<span class="eilmo-cf-summary__label"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Free Gift' ) ); ?></span>
						<span class="eilmo-cf-summary__amount" data-eilmo-summary-text="free-gift"></span>
					</div>
					<?php

					/*
					 * ---------------------------------------------
					 * Coupon Discount
					 * ---------------------------------------------
					 */
					if (
						! empty(
							$visibility[
								'coupon_discount'
							]
						)
					) {
						$this->render_row(
							'coupon-discount',
							(string) ( $labels['coupon_discount_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Coupon Discount' ) ),
							$amounts[
								'coupon_discount'
							],
							true
						);
					}

					/*
					 * ---------------------------------------------
					 * Delivery Charge
					 * ---------------------------------------------
					 */
					if (
						! empty(
							$visibility[
								'delivery_charge'
							]
						)
					) {
						$this->render_row(
							'delivery-charge',
							(string) ( $labels['delivery_charge_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Delivery Charge' ) ),
							$amounts[
								'delivery_charge'
							]
						);
					}

					/*
					 * ---------------------------------------------
					 * Full Payment Discount
					 * ---------------------------------------------
					 */
					if (
						! empty(
							$visibility[
								'full_payment_discount'
							]
						)
					) {
						$this->render_row(
							'full-payment-discount',
							(string) ( $labels['full_payment_discount_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Full Payment Discount' ) ),
							$amounts[
								'full_payment_discount'
							],
							true
						);
					}

					/*
					 * ---------------------------------------------
					 * Advance Payment state
					 * ---------------------------------------------
					 *
					 * Do not render Advance Payment as a separate visible row.
					 * Pay Now / Remaining Due already communicate the payment
					 * split, so a second Advance Payment line is redundant.
					 * Keep the amount in a hidden state node because Summary JS
					 * uses the canonical `advance-payment` amount key.
					 */
					if (
						! empty(
							$visibility[
								'advance_payment'
							]
						)
					) {
						$this->render_state_amount(
							'advance-payment',
							$amounts[
								'advance_payment'
							]
						);
					}
					?>

					<?php
					/*
					 * ---------------------------------------------
					 * Coupon Form
					 * ---------------------------------------------
					 *
					 * Rendered inside Order Summary immediately
					 * before the separator and Grand Total.
					 */
					if (
						! empty(
							$visibility[
								'coupon_discount'
							]
						) &&
						'' !==
							$coupon_html
					) :
						?>

						<div class="eilmo-cf-summary__coupon">

							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Coupon renderer escapes its dynamic values internally.
							echo $coupon_html;
							?>

						</div>

					<?php endif; ?>

					<div
						class="eilmo-cf-summary__separator"
						aria-hidden="true"
					></div>

					<?php
					/*
					 * ---------------------------------------------
					 * Pay Now / Remaining Due
					 * ---------------------------------------------
					 */
					if (
						! empty(
							$visibility[
								'payment_breakdown'
							]
						)
					) {
						$this->render_row(
							'pay-now',
							(string) (
								$labels[
									'pay_now'
								] ??
									\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay Now' )
							),
							$amounts[
								'pay_now'
							]
						);

						$this->render_row(
							'remaining-due',
							(string) (
								$labels[
									'remaining_due'
								] ??
									\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Remaining Due' )
							),
							$amounts[
								'remaining_due'
							]
						);

						?>
						<div class="eilmo-cf-summary__separator" aria-hidden="true"></div>
						<?php
					}

					/*
					 * ---------------------------------------------
					 * Grand Total
					 * ---------------------------------------------
					 */
					$this->render_row(
						'grand-total',
						(string) ( $labels['grand_total_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Grand Total' ) ),
						$amounts[
							'grand_total'
						],
						false,
						true
					);
					?>

				</div>

				<?php
				$summary_order_html = isset( $settings['_summary_order_html'] )
					? (string) $settings['_summary_order_html']
					: '';
				if ( '' !== $summary_order_html ) :
					?>
					<div class="eilmo-cf-summary__order-action" data-eilmo-summary-order-action>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Order renderer escapes internally.
						echo $summary_order_html;
						?>
					</div>
				<?php endif; ?>

				<?php
				/**
				 * Fires after an optional Summary Order action and before embedded
				 * checkout details. Secondary actions such as WhatsApp can use this
				 * slot without becoming part of the totals markup.
				 *
				 * @param array<string, float> $amounts  Summary amounts.
				 * @param array<string, mixed> $settings Checkout settings.
				 */
				do_action( 'eilmo_cf/summary_after_order_action', $amounts, $settings );
				?>

				<?php
				$checkout_details_html = isset( $settings['_checkout_details_html'] )
					? (string) $settings['_checkout_details_html']
					: '';
				if ( '' !== $checkout_details_html ) :
					?>
					<div class="eilmo-cf-summary__checkout-details" data-eilmo-summary-checkout-details>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child renderers escape their own output.
						echo $checkout_details_html;
						?>
					</div>
				<?php endif; ?>

				<span
					data-eilmo-summary-relocation-anchor
					hidden
				></span>

				<?php
				/**
				 * Fires after checkout summary content.
				 *
				 * @param array<string, float> $amounts  Summary amounts.
				 * @param array<string, mixed> $settings Checkout settings.
				 */
				do_action(
					'eilmo_cf/summary_end',
					$amounts,
					$settings
				);
				?>

			</section>

			<button
				type="button"
				class="eilmo-cf-summary-bar"
				data-eilmo-summary-toggle
				aria-controls="<?php echo esc_attr( $summary_id ); ?>"
				aria-expanded="<?php echo esc_attr( $is_open ? 'true' : 'false' ); ?>"
			>

				<span class="eilmo-cf-summary-bar__totals">

					<span class="eilmo-cf-summary-bar__primary">

						<span class="eilmo-cf-summary-bar__label">
							<?php echo esc_html( (string) ( $labels['grand_total_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Grand Total' ) ) ); ?>
						</span>

						<strong
							class="eilmo-cf-summary-bar__amount"
							data-eilmo-mobile-summary-amount="grand-total"
							data-value="<?php
							echo esc_attr(
								wc_format_decimal(
									$amounts[
										'grand_total'
									]
								)
							);
							?>"
						>
							<?php
							echo wp_kses_post(
								wc_price(
									$amounts[
										'grand_total'
									]
								)
							);
							?>
						</strong>

					</span>

					<?php
					if (
						! empty(
							$visibility[
								'payment_breakdown'
							]
						)
					) :
						?>

						<span class="eilmo-cf-summary-bar__secondary">

							<span class="eilmo-cf-summary-bar__secondary-label">

								<?php
								echo esc_html(
									(string) (
										$labels[
											'pay_now'
										] ??
											\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay Now' )
									)
								);
								?>

							</span>

							<strong
								class="eilmo-cf-summary-bar__secondary-amount"
								data-eilmo-mobile-summary-amount="pay-now"
								data-value="<?php
								echo esc_attr(
									wc_format_decimal(
										$amounts[
											'pay_now'
										]
									)
								);
								?>"
							>
								<?php
								echo wp_kses_post(
									wc_price(
										$amounts[
											'pay_now'
										]
									)
								);
								?>
							</strong>

						</span>

					<?php endif; ?>

				</span>

				<span class="eilmo-cf-summary-bar__action">

					<span class="eilmo-cf-summary-bar__action-text">
						<?php
						echo esc_html( (string) ( $labels['view_summary_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'View Summary' ) ) );
						?>
					</span>

					<span class="eilmo-cf-summary-bar__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 15l6-6 6 6" /></svg></span>

				</span>

			</button>

		</div>

		<?php

		$output =
			ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters checkout summary HTML.
		 *
		 * @param string               $output   Summary HTML.
		 * @param array<string, float> $amounts  Summary amounts.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		return (string) apply_filters(
			'eilmo_cf/summary_html',
			$output,
			$amounts,
			$settings
		);
	}

	/**
	 * Render coupon form inside Order Summary.
	 *
	 * The full interactive component is returned directly.
	 * Do not wrap the complete component in wp_kses_post(),
	 * otherwise form inputs may be stripped.
	 *
	 * @param string               $summary_id Summary instance ID.
	 * @param array<string, mixed> $settings   Checkout settings.
	 *
	 * @return string
	 */
	private function render_coupon(
		string $summary_id,
		array $settings
	): string {

		if (
			! class_exists(
				CouponRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new CouponRenderer();

		$context =
			array(
				'instance_id' =>
					$summary_id,
			);

		/**
		 * Filters coupon context rendered inside Summary.
		 *
		 * @param array<string, mixed> $context  Coupon context.
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		$context =
			apply_filters(
				'eilmo_cf/summary/coupon_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context =
				array(
					'instance_id' =>
						$summary_id,
				);
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Get initial summary amounts.
	 *
	 * @return array<string, float>
	 */
	private function get_initial_amounts(): array {

		return array(
			'product_total' =>
				0.0,

			'combo_discount' =>
				0.0,

			'order_bump_discount' =>
				0.0,

			'automatic_discount' =>
				0.0,

			'coupon_discount' =>
				0.0,

			'delivery_charge' =>
				0.0,

			'full_payment_discount' =>
				0.0,

			'advance_payment' =>
				0.0,

			'grand_total' =>
				0.0,

			'pay_now' =>
				0.0,

			'remaining_due' =>
				0.0,
		);
	}

	/**
	 * Get feature visibility.
	 *
	 * Combo Discount and Order Bump Discount visibility
	 * are checkout-instance aware.
	 *
	 * @param array<string, mixed> $checkout_settings Checkout settings.
	 *
	 * @return array<string, bool>
	 */
	private function get_feature_visibility(
		array $checkout_settings = array()
	): array {

		$settings =
			$this->get_plugin_settings();

		$combo_offers =
			isset(
				$settings[
					'combo_offers'
				]
			) &&
			is_array(
				$settings[
					'combo_offers'
				]
			)
				? $settings[
					'combo_offers'
				]
				: array();

		$order_bumps =
			isset(
				$settings[
					'order_bumps'
				]
			) &&
			is_array(
				$settings[
					'order_bumps'
				]
			)
				? $settings[
					'order_bumps'
				]
				: array();

		$delivery =
			isset(
				$settings[
					'delivery'
				]
			) &&
			is_array(
				$settings[
					'delivery'
				]
			)
				? $settings[
					'delivery'
				]
				: array();

		$advance_payment =
			isset(
				$settings[
					'advance_payment'
				]
			) &&
			is_array(
				$settings[
					'advance_payment'
				]
			)
				? $settings[
					'advance_payment'
				]
				: array();

		$discounts =
			isset(
				$settings[
					'discounts'
				]
			) &&
			is_array(
				$settings[
					'discounts'
				]
			)
				? $settings[
					'discounts'
				]
				: array();

		$coupons =
			isset(
				$settings[
					'coupons'
				]
			) &&
			is_array(
				$settings[
					'coupons'
				]
			)
				? $settings[
					'coupons'
				]
				: array();

		$full_payment =
			isset(
				$discounts[
					'full_payment'
				]
			) &&
			is_array(
				$discounts[
					'full_payment'
				]
			)
				? $discounts[
					'full_payment'
				]
				: array();

		/*
		 * ---------------------------------------------
		 * Combo Discount visibility
		 * ---------------------------------------------
		 */
		$combo_library_enabled =
			'yes' ===
				(
					$combo_offers[
						'enabled'
					] ??
						'no'
				);

		$checkout_combo_enabled =
			'yes' ===
				(
					$checkout_settings[
						'show_combo_offers'
					] ??
						'yes'
				);

		$checkout_combo_ids =
			$this->normalize_combo_offer_ids(
				$checkout_settings[
					'combo_offer_ids'
				] ??
					array()
			);

		$combo_discount_enabled =
			$combo_library_enabled &&
			$checkout_combo_enabled &&
			! empty(
				$checkout_combo_ids
			);

		/*
		 * ---------------------------------------------
		 * Special Offer / Order Bump Discount visibility
		 * ---------------------------------------------
		 *
		 * An empty allow-list is used only when the checkout explicitly
		 * enables all Special Offers; otherwise show_order_bumps is off.
		 */
		$order_bump_library_enabled =
			'yes' ===
				(
					$order_bumps[
						'enabled'
					] ??
						'no'
				);

		$checkout_order_bump_enabled =
			'yes' ===
				(
					$checkout_settings[
						'show_order_bumps'
					] ??
						'no'
				);

		$checkout_order_bump_ids =
			$this->normalize_order_bump_ids(
				$checkout_settings[
					'order_bump_ids'
				] ??
					array()
			);

		$order_bump_discount_enabled =
			$order_bump_library_enabled &&
			$checkout_order_bump_enabled &&
			$this->has_available_order_bump(
				$order_bumps,
				$checkout_order_bump_ids
			);

		/*
		 * ---------------------------------------------
		 * Full Payment Discount
		 * ---------------------------------------------
		 */
		$full_payment_enabled =
			'yes' ===
				(
					$full_payment[
						'enabled'
					] ??
						'no'
				);

		/*
		 * ---------------------------------------------
		 * Automatic Discount
		 * ---------------------------------------------
		 *
		 * Special Discounts remain visible beside the payment discount. Their
		 * calculators use separate canonical amounts, so hiding this row made an
		 * applied Special Discount appear as 0 in the Summary.
		 */
		$discounts_enabled =
			'yes' ===
				(
					$discounts[
						'enabled'
					] ??
						'no'
				);

		$automatic_discount_enabled =
			$discounts_enabled &&
			$this->has_enabled_automatic_discount_rule(
				$discounts
			);

		/*
		 * ---------------------------------------------
		 * Advance Payment
		 * ---------------------------------------------
		 */
		$advance_enabled =
			'yes' ===
				(
					$advance_payment[
						'enabled'
					] ??
						'no'
				);

		return array(
			'combo_discount' =>
				$combo_discount_enabled,

			'order_bump_discount' =>
				$order_bump_discount_enabled,

			'automatic_discount' =>
				$automatic_discount_enabled,

			'coupon_discount' =>
				'yes' ===
					(
						$coupons[
							'enabled'
						] ??
							'no'
					),

			'delivery_charge' =>
				'yes' ===
					(
						$delivery[
							'enabled'
						] ??
							'no'
					),

			'full_payment_discount' =>
				$full_payment_enabled,

			'advance_payment' =>
				$advance_enabled,

			'payment_breakdown' =>
				$advance_enabled,
		);
	}

	/**
	 * Determine whether at least one enabled automatic
	 * discount rule exists.
	 *
	 * @param array<string, mixed> $discounts Discount settings.
	 *
	 * @return bool
	 */
	private function has_enabled_automatic_discount_rule(
		array $discounts
	): bool {

		$rules =
			isset(
				$discounts[
					'automatic_rules'
				]
			) &&
			is_array(
				$discounts[
					'automatic_rules'
				]
			)
				? $discounts[
					'automatic_rules'
				]
				: array();

		foreach ( $rules as $rule ) {

			if ( ! is_array( $rule ) ) {
				continue;
			}

			if (
				'yes' ===
					(
						$rule[
							'enabled'
						] ??
							'yes'
					)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get plugin settings merged with defaults.
	 *
	 * Indexed collections are preserved from the saved
	 * settings rather than recursively merged.
	 *
	 * @return array<string, mixed>
	 */
	private function get_plugin_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$settings =
			array_replace_recursive(
				$defaults,
				$stored
			);

		/*
		 * Combo Offers are an indexed collection and must
		 * remain exactly as saved.
		 */
		if (
			isset(
				$stored[
					'combo_offers'
				][
					'offers'
				]
			) &&
			is_array(
				$stored[
					'combo_offers'
				][
					'offers'
				]
			)
		) {
			$settings[
				'combo_offers'
			][
				'offers'
			] =
				$stored[
					'combo_offers'
				][
					'offers'
				];
		}

		/*
		 * Order Bumps are an indexed collection and must
		 * remain exactly as saved.
		 */
		if (
			isset(
				$stored[
					'order_bumps'
				][
					'offers'
				]
			) &&
			is_array(
				$stored[
					'order_bumps'
				][
					'offers'
				]
			)
		) {
			$settings[
				'order_bumps'
			][
				'offers'
			] =
				$stored[
					'order_bumps'
				][
					'offers'
				];
		}

		/*
		 * Automatic Discount rules are an indexed
		 * collection and must remain exactly as saved.
		 */
		if (
			isset(
				$stored[
					'discounts'
				][
					'automatic_rules'
				]
			) &&
			is_array(
				$stored[
					'discounts'
				][
					'automatic_rules'
				]
			)
		) {
			$settings[
				'discounts'
			][
				'automatic_rules'
			] =
				$stored[
					'discounts'
				][
					'automatic_rules'
				];
		}

		/*
		 * Custom coupons are an indexed collection and
		 * must remain exactly as saved.
		 */
		if (
			isset(
				$stored[
					'coupons'
				][
					'custom_coupons'
				]
			) &&
			is_array(
				$stored[
					'coupons'
				][
					'custom_coupons'
				]
			)
		) {
			$settings[
				'coupons'
			][
				'custom_coupons'
			] =
				$stored[
					'coupons'
				][
					'custom_coupons'
				];
		}

		return $settings;
	}

	/**
	 * Get dynamic summary labels.
	 *
	 * @return array<string, string>
	 */
	private function get_summary_labels(): array {
		$summary = CheckoutDisplaySettings::get_section( 'summary' );
		$defaults = CheckoutSettings::get_defaults();
		$summary_defaults = isset( $defaults['checkout_display']['summary'] ) && is_array( $defaults['checkout_display']['summary'] )
			? $defaults['checkout_display']['summary']
			: array();

		$keys = array(
			'title', 'selected_items_title', 'product_total_label', 'combo_discount_label',
			'special_offer_label', 'automatic_discount_label', 'coupon_discount_label',
			'delivery_charge_label', 'full_payment_discount_label', 'advance_payment_label',
			'grand_total_label', 'pay_now_label', 'remaining_due_label', 'view_summary_label',
		);
		$labels = array();
		foreach ( $keys as $key ) {
			$value = sanitize_text_field( (string) ( $summary[ $key ] ?? $summary_defaults[ $key ] ?? '' ) );
			$labels[ $key ] = $value;
		}

		if ( in_array( $labels['automatic_discount_label'], array( 'Automatic Discount', 'Special Offer' ), true ) ) {
			$labels['automatic_discount_label'] = \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Special Discount' );
		}

		// Historical filter keys kept for backwards compatibility.
		$labels['combo_discount'] = $labels['combo_discount_label'];
		$labels['order_bump_discount'] = $labels['special_offer_label'];
		$labels['pay_now'] = $labels['pay_now_label'];
		$labels['remaining_due'] = $labels['remaining_due_label'];

		return $labels;
	}

	/**
	 * Normalize Summary amounts.
	 *
	 * Combo Discount, Order Bump Discount and Automatic
	 * Discount are separate canonical amounts.
	 *
	 * @param mixed $amounts Summary amounts.
	 *
	 * @return array<string, float>
	 */
	private function normalize_amounts(
		$amounts
	): array {

		$defaults =
			$this->get_initial_amounts();

		if ( ! is_array( $amounts ) ) {
			return $defaults;
		}

		foreach (
			$defaults as
				$key => $default
		) {

			if (
				! isset(
					$amounts[
						$key
					]
				) ||
				! is_numeric(
					$amounts[
						$key
					]
				)
			) {
				$amounts[
					$key
				] =
					$default;

				continue;
			}

			$amounts[
				$key
			] =
				max(
					0,
					(float) $amounts[
						$key
					]
				);
		}

		return array_intersect_key(
			$amounts,
			$defaults
		);
	}

	/**
	 * Normalize Combo Offer IDs.
	 *
	 * Supports arrays and comma/space separated strings.
	 *
	 * @param mixed $offer_ids Combo Offer IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_combo_offer_ids(
		$offer_ids
	): array {

		if (
			is_string(
				$offer_ids
			)
		) {
			$offer_ids =
				preg_split(
					'/[\s,]+/',
					$offer_ids
				);
		}

		if (
			! is_array(
				$offer_ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach (
			$offer_ids as
				$offer_id
		) {

			if (
				! is_scalar(
					$offer_id
				)
			) {
				continue;
			}

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
	 * Normalize Order Bump IDs.
	 *
	 * Supports arrays and comma/space separated strings.
	 *
	 * Empty result means all globally enabled Order Bumps
	 * are available for the checkout instance.
	 *
	 * @param mixed $offer_ids Order Bump IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_order_bump_ids(
		$offer_ids
	): array {

		if (
			is_string(
				$offer_ids
			)
		) {
			$offer_ids =
				preg_split(
					'/[\s,]+/',
					$offer_ids
				);
		}

		if (
			! is_array(
				$offer_ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach (
			$offer_ids as
				$offer_id
		) {

			if (
				! is_scalar(
					$offer_id
				)
			) {
				continue;
			}

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
	 * Determine whether the checkout can expose at least
	 * one enabled Order Bump.
	 *
	 * When $allowed_ids is empty, all globally enabled
	 * Order Bumps are considered available.
	 *
	 * @param array<string, mixed> $order_bumps Order Bump settings.
	 * @param array<int, string>   $allowed_ids Allowed IDs.
	 *
	 * @return bool
	 */
	private function has_available_order_bump(
		array $order_bumps,
		array $allowed_ids = array()
	): bool {

		$offers =
			isset(
				$order_bumps[
					'offers'
				]
			) &&
			is_array(
				$order_bumps[
					'offers'
				]
			)
				? $order_bumps[
					'offers'
				]
				: array();

		foreach ( $offers as $offer ) {

			if (
				! is_array(
					$offer
				) ||
				'yes' !==
					(
						$offer[
							'enabled'
						] ??
							'yes'
					)
			) {
				continue;
			}

			$offer_type = sanitize_key( (string) ( $offer['offer_type'] ?? '' ) );
			$apply_behavior = sanitize_key( (string) ( $offer['apply_behavior'] ?? 'customer_selectable' ) );
			if (
				'auto_apply' === $apply_behavior &&
				in_array( $offer_type, array( 'percentage_discount', 'fixed_discount', 'free_delivery' ), true )
			) {
				continue;
			}

			$offer_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if (
				'' === $offer_id
			) {
				continue;
			}

			if (
				empty(
					$allowed_ids
				) ||
				in_array(
					$offer_id,
					$allowed_ids,
					true
				)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render a hidden canonical amount state without a visible Summary row.
	 *
	 * @param string $key    Summary key.
	 * @param float  $amount Amount.
	 *
	 * @return void
	 */
	private function render_state_amount(
		string $key,
		float $amount
	): void {
		?>
		<span
			hidden
			aria-hidden="true"
			data-eilmo-summary-amount="<?php echo esc_attr( $key ); ?>"
			data-value="<?php echo esc_attr( wc_format_decimal( $amount ) ); ?>"
		></span>
		<?php
	}

	/**
	 * Render a Summary row.
	 *
	 * @param string $key         Summary key.
	 * @param string $label       Row label.
	 * @param float  $amount      Amount.
	 * @param bool   $is_discount Whether row is a discount.
	 * @param bool   $is_total    Whether row is grand total.
	 *
	 * @return void
	 */
	private function render_row(
		string $key,
		string $label,
		float $amount,
		bool $is_discount = false,
		bool $is_total = false
	): void {

		$classes =
			array(
				'eilmo-cf-summary__row',

				'eilmo-cf-summary__row--' .
					sanitize_html_class(
						$key
					),
			);

		if ( $is_discount ) {
			$classes[] =
				'eilmo-cf-summary__row--discount';
		}

		if ( $is_total ) {
			$classes[] =
				'eilmo-cf-summary__row--total';
		}

		$formatted_amount =
			wc_price(
				$amount
			);
		?>

		<div
			class="<?php
			echo esc_attr(
				implode(
					' ',
					$classes
				)
			);
			?>"
			data-eilmo-summary-row="<?php echo esc_attr( $key ); ?>"
			<?php if ( $is_discount ) : ?>
				data-eilmo-summary-discount="yes"
			<?php endif; ?>
			<?php if ( $is_discount && $amount <= 0 ) : ?>
				hidden
			<?php endif; ?>
		>

			<span
				class="eilmo-cf-summary__label"
				data-eilmo-summary-label="<?php echo esc_attr( $key ); ?>"
			>
				<?php echo esc_html( $label ); ?>
			</span>

			<span
				class="eilmo-cf-summary__amount"
				data-eilmo-summary-amount="<?php echo esc_attr( $key ); ?>"
				data-value="<?php
				echo esc_attr(
					wc_format_decimal(
						$amount
					)
				);
				?>"
			>

				<?php if ( $is_discount && $amount > 0 ) : ?>

					<span
						class="eilmo-cf-summary__discount-prefix"
						aria-hidden="true"
					>
						&minus;
					</span>

				<?php endif; ?>

				<?php
				echo wp_kses_post(
					$formatted_amount
				);
				?>

			</span>

		</div>

		<?php
	}

	/**
	 * Generate unique Summary ID.
	 *
	 * @return string
	 */
	private function generate_summary_id(): string {

		++self::$instance_counter;

		return sprintf(
			'eilmo-cf-summary-%d-%s',
			self::$instance_counter,
			wp_generate_uuid4()
		);
	}
}
