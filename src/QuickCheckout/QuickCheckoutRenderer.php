<?php
/**
 * Single Product Quick Checkout renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\QuickCheckout;

use EilmoCheckout\Admin\QuickCheckoutSettings;
use EilmoCheckout\AdvancePayment\Rendering\AdvancePaymentRenderer;
use EilmoCheckout\ComboOffers\Rendering\ComboOfferRenderer;
use EilmoCheckout\Customer\Rendering\CustomerRenderer;
use EilmoCheckout\Delivery\Rendering\DeliveryRenderer;
use EilmoCheckout\Discounts\Rendering\DiscountNoticeRenderer;
use EilmoCheckout\Discounts\Services\SpecialDiscountContext;
use EilmoCheckout\OrderBumps\Rendering\OrderBumpRenderer;
use EilmoCheckout\Payment\Rendering\PaymentRenderer;
use EilmoCheckout\Rendering\SummaryRenderer;
use EilmoCheckout\Security\BotProtection\BotProtectionToken;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Single Product Quick Checkout interface.
 */
final class QuickCheckoutRenderer {

	/**
	 * Product.
	 *
	 * @var WC_Product
	 */
	private $product;

	/**
	 * Quick Checkout settings.
	 *
	 * @var array<string,mixed>
	 */
	private $settings;

	/**
	 * Whether normal order mode is available.
	 *
	 * @var bool
	 */
	private $show_order;

	/**
	 * Whether WhatsApp mode is available.
	 *
	 * @var bool
	 */
	private $show_whatsapp;

	/**
	 * Whether the Order Now action requires login.
	 *
	 * @var bool
	 */
	private $order_requires_login;

	/**
	 * Login destination URL.
	 *
	 * @var string
	 */
	private $login_url;

	/**
	 * Login action label.
	 *
	 * @var string
	 */
	private $login_label;

	/**
	 * Constructor.
	 *
	 * @param WC_Product          $product              Product.
	 * @param array<string,mixed> $settings             Quick Checkout settings.
	 * @param bool                $show_order           Show order action.
	 * @param bool                $show_whatsapp        Show WhatsApp action.
	 * @param bool                $order_requires_login Whether Order Now requires login.
	 * @param string              $login_url            Login destination URL.
	 * @param string              $login_label          Login action label.
	 */
	public function __construct(
		WC_Product $product,
		array $settings,
		bool $show_order,
		bool $show_whatsapp,
		bool $order_requires_login = false,
		string $login_url = '',
		string $login_label = ''
	) {

		$this->product =
			$product;

		$this->settings =
			$settings;

		$this->show_order =
			$show_order;

		$this->show_whatsapp =
			$show_whatsapp;

		$this->order_requires_login =
			$order_requires_login;

		$this->login_url =
			esc_url_raw(
				$login_url
			);

		$this->login_label =
			sanitize_text_field(
				$login_label
			);

		if (
			$this->order_requires_login &&
			'' ===
				$this->login_url
		) {
			$product_url =
				get_permalink(
					$this->product->get_id()
				);

			$this->login_url =
				wp_login_url(
					is_string(
						$product_url
					)
						? $product_url
						: ''
				);
		}

		if (
			$this->order_requires_login &&
			'' ===
				$this->login_label
		) {
			$this->login_label =
				__(
					'Log In to Order',
					'eilmo-checkout-flow'
				);
		}
	}

	/**
	 * Render Quick Checkout.
	 *
	 * @return string
	 */
	public function render(): string {

		if (
			! $this->show_order &&
			! $this->show_whatsapp
		) {
			return '';
		}

		$product_id =
			absint(
				$this->product->get_id()
			);

		if ( $product_id <= 0 ) {
			return '';
		}

		$texts =
			$this->get_texts();

		$buttons =
			isset(
				$this->settings[
					'buttons'
				]
			) &&
			is_array(
				$this->settings[
					'buttons'
				]
			)
				? $this->settings[
					'buttons'
				]
				: array();

		$direct_checkout =
			'yes' ===
				(
					$buttons[
						'direct_checkout'
					] ??
						'yes'
				);

		/*
		 * Quick Checkout drawer is only rendered when the
		 * customer is allowed to start the normal order flow.
		 *
		 * When login is required, PHP renders the login action
		 * immediately and no hidden checkout drawer/modules are
		 * generated unnecessarily.
		 *
		 * WhatsApp-only mode also remains a lightweight direct
		 * product-page action without a checkout drawer.
		 */
		$can_render_checkout =
			$this->show_order &&
			! $this->order_requires_login;

		if (
			! $can_render_checkout
		) {
			ob_start();
			?>

			<div
				class="eilmo-cf-quick-checkout-actions"
				data-eilmo-quick-checkout-actions
			>

				<?php
				if (
					$this->show_order &&
					$this->order_requires_login
				) :
					?>

					<a
						href="<?php echo esc_url( $this->login_url ); ?>"
						class="eilmo-cf-order-submit__button eilmo-cf-quick-checkout-order-action eilmo-cf-quick-checkout-login"
						data-eilmo-quick-checkout-login
					>

						<span class="eilmo-cf-order-submit__button-text">

							<?php
							echo esc_html(
								$this->login_label
							);
							?>

						</span>

					</a>

				<?php endif; ?>

				<?php if ( $this->show_whatsapp ) : ?>

					<button
						type="button"
						class="eilmo-cf-order-submit__button eilmo-cf-whatsapp-order__button"
						data-eilmo-quick-whatsapp
						data-variation-required="<?php
						echo esc_attr(
							$texts[
								'variation_required'
							]
						);
						?>"
					>

						<span class="eilmo-cf-order-submit__button-text">

							<?php
							echo esc_html(
								$texts[
									'product_whatsapp_button'
								]
							);
							?>

						</span>

					</button>

				<?php endif; ?>

				<p
					class="eilmo-cf-quick-checkout-actions__error"
					data-eilmo-quick-checkout-product-message
					role="alert"
					hidden
				></p>

			</div>

			<?php

			$output =
				ob_get_clean();

			if (
				false ===
					$output
			) {
				return '';
			}

			$action_context =
				array(
					'quick_checkout' =>
						'yes',

					'product_id' =>
						$product_id,

					'order_requires_login' =>
						$this->order_requires_login
							? 'yes'
							: 'no',
				);

			return (string)
				apply_filters(
					'eilmo_cf/quick_checkout/html',
					$output,
					$this->product,
					$this->settings,
					$action_context
				);
		}

		$content =
			$this->get_content_settings();

		$presentation =
			$this->get_presentation_settings();

		$instance_id =
			$this->generate_instance_id();

		$dialog_id =
			$instance_id .
				'-dialog';

		$title_id =
			$instance_id .
				'-title';

		$product_type =
			sanitize_key(
				$this->product->get_type()
			);

		$initial_price =
			$this->get_initial_product_price();

		$initial_quantity =
			$this->get_initial_quantity();

		$checkout_context =
			$this->build_checkout_context(
				$instance_id,
				$content,
				$texts,
				$initial_price,
				$initial_quantity
			);

		$special_discount_context =
			( new SpecialDiscountContext() )->create_from_settings(
				$checkout_context,
				$instance_id
			);
		$special_discount_context_json = wp_json_encode( $special_discount_context );
		if ( ! is_string( $special_discount_context_json ) ) {
			$special_discount_context_json = '{}';
		}

		/*
		 * Normal Checkout Flow uses the exact same
		 * server-signed Bot Protection token.
		 */
		$security_fields_html =
			$this->render_security_fields(
				$instance_id
			);

		$combo_html =
			$this->render_combo_offers(
				$checkout_context,
				$instance_id,
				$texts
			);

		$order_bump_html =
			$this->render_order_bumps(
				$checkout_context,
				$instance_id,
				$texts
			);

		$discount_html =
			$this->render_discount_notice(
				$checkout_context,
				$instance_id
			);

		$delivery_html =
			$this->render_delivery(
				$checkout_context,
				$instance_id
			);

		$customer_html =
			$this->render_customer_information(
				$checkout_context,
				$instance_id
			);

		$advance_html =
			$this->render_advance_payment(
				$checkout_context,
				$instance_id
			);

		$payment_html =
			$this->render_payment_methods(
				$checkout_context,
				$instance_id
			);

		$order_submit_html =
			$this->render_quick_order_submit(
				$texts
			);

		$summary_html =
			$this->render_summary(
				$checkout_context,
				$content,
				$order_submit_html
			);

		$product_image =
			$this->get_product_image_url();

		$product_name =
			$this->product->get_name();

		$product_price_html =
			$this->product->get_price_html();

		if (
			'' ===
				trim(
					(string) $product_price_html
				)
		) {
			$product_price_html =
				wc_price(
					$initial_price
				);
		}

		$default_mode =
			'order';

		ob_start();
		?>

		<div
			class="eilmo-cf-quick-checkout-actions"
			data-eilmo-quick-checkout-actions
		>

			<?php if ( $this->show_order ) : ?>

				<button
					type="button"
					class="eilmo-cf-order-submit__button eilmo-cf-quick-checkout-trigger eilmo-cf-quick-checkout-order-action"
					data-eilmo-quick-checkout-trigger="order"
					data-eilmo-direct-checkout="<?php echo esc_attr( $direct_checkout ? 'yes' : 'no' ); ?>"
					data-variation-required="<?php
					echo esc_attr(
						$texts[
							'variation_required'
						]
					);
					?>"
					aria-controls="<?php
					echo esc_attr(
						$dialog_id
					);
					?>"
				>

					<span class="eilmo-cf-order-submit__button-text">

						<?php
						echo esc_html(
							$texts[
								'product_order_button'
							]
						);
						?>

					</span>

				</button>

			<?php endif; ?>

			<?php if ( $this->show_whatsapp ) : ?>

				<button
					type="button"
					class="eilmo-cf-order-submit__button eilmo-cf-whatsapp-order__button"
					data-eilmo-quick-whatsapp
					data-variation-required="<?php
					echo esc_attr(
						$texts[
							'variation_required'
						]
					);
					?>"
				>

					<span class="eilmo-cf-order-submit__button-text">

						<?php
						echo esc_html(
							$texts[
								'product_whatsapp_button'
							]
						);
						?>

					</span>

				</button>

			<?php endif; ?>

			<p
				class="eilmo-cf-quick-checkout-actions__error"
				data-eilmo-quick-checkout-product-message
				role="alert"
				hidden
			></p>

		</div>

		<div
			id="<?php echo esc_attr( $dialog_id ); ?>"
			class="eilmo-cf-quick-checkout"
			data-eilmo-quick-checkout
			data-display-mode="<?php
			echo esc_attr(
				$presentation[
					'display_mode'
				]
			);
			?>"
			data-desktop-width="<?php
			echo esc_attr(
				(string) $presentation[
					'desktop_width'
				]
			);
			?>"
			data-product-id="<?php
			echo esc_attr(
				(string) $product_id
			);
			?>"
			data-product-type="<?php
			echo esc_attr(
				$product_type
			);
			?>"
			data-base-price="<?php
			echo esc_attr(
				wc_format_decimal(
					$initial_price
				)
			);
			?>"
			data-order-button-text="<?php
			echo esc_attr(
				$texts[
					'order_button'
				]
			);
			?>"
			data-whatsapp-button-text="<?php
			echo esc_attr(
				$texts[
					'whatsapp_button'
				]
			);
			?>"
			data-variation-required="<?php
			echo esc_attr(
				$texts[
					'variation_required'
				]
			);
			?>"
			data-checkout-not-ready="<?php
			echo esc_attr(
				$texts[
					'checkout_not_ready'
				]
			);
			?>"
			data-mode="<?php echo esc_attr( $default_mode ); ?>"
			aria-hidden="true"
			hidden
		>

			<div
				class="eilmo-cf-quick-checkout__backdrop"
				data-eilmo-quick-checkout-close
				aria-hidden="true"
			></div>

			<section
				class="eilmo-cf-quick-checkout__panel"
				data-eilmo-quick-checkout-panel
				role="dialog"
				aria-modal="true"
				aria-labelledby="<?php
				echo esc_attr(
					$title_id
				);
				?>"
			>

				<header class="eilmo-cf-quick-checkout__header">

					<div class="eilmo-cf-quick-checkout__heading">

						<h2
							id="<?php echo esc_attr( $title_id ); ?>"
							class="eilmo-cf-quick-checkout__title"
						>

							<?php
							echo esc_html(
								$texts[
									'drawer_title'
								]
							);
							?>

						</h2>

						<?php
						if (
							'' !==
								trim(
									$texts[
										'drawer_description'
									]
								)
						) :
							?>

							<p class="eilmo-cf-quick-checkout__description">

								<?php
								echo esc_html(
									$texts[
										'drawer_description'
									]
								);
								?>

							</p>

						<?php endif; ?>

					</div>

					<button
						type="button"
						class="eilmo-cf-quick-checkout__close"
						data-eilmo-quick-checkout-close
						aria-label="<?php
						echo esc_attr(
							$texts[
								'close_label'
							]
						);
						?>"
					>

						<svg
							class="eilmo-cf-quick-checkout__close-icon"
							viewBox="0 0 24 24"
							aria-hidden="true"
							focusable="false"
						>
							<path
								d="M6 6L18 18M18 6L6 18"
								fill="none"
								stroke="currentColor"
								stroke-width="2.2"
								stroke-linecap="round"
							/>
						</svg>

					</button>

				</header>

				<div class="eilmo-cf-quick-checkout__body">

					<div
						id="<?php echo esc_attr( $instance_id ); ?>"
						class="eilmo-cf-quick-checkout__checkout eilmo-cf-checkout eilmo-cf-checkout--selection-single eilmo-cf-checkout--has-summary"
						data-eilmo-checkout
						data-eilmo-quick-checkout-context
						data-eilmo-special-discount-context="<?php echo esc_attr( $special_discount_context_json ); ?>"
						data-selection="single"
						data-summary-desktop="right_sticky"
						data-summary-tablet="below"
						data-summary-mobile="bottom_drawer"
						data-mobile-summary-collapsed="yes"
					>

						<?php
						/*
						 * --------------------------------------
						 * Checkout Security Fields
						 * --------------------------------------
						 *
						 * Same structure used by the normal
						 * CheckoutRenderer.
						 *
						 * checkout.js collects these fields and
						 * sends them to BotProtectionGuard.
						 */
						if ( '' !== $security_fields_html ) {

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Security method escapes values internally.
							echo $security_fields_html;
						}

						/**
						 * Keep normal Checkout Flow lifecycle.
						 *
						 * Security and other integrations may
						 * hook into this action.
						 */
						do_action(
							'eilmo_cf/before_checkout',
							$checkout_context,
							$instance_id
						);
						?>

						<div class="eilmo-cf-checkout__inner">

							<main class="eilmo-cf-checkout__main">

								<?php
								if (
									'yes' ===
										$content[
											'show_product_summary'
										]
								) :
									?>

									<section
										class="eilmo-cf-quick-selection"
										data-eilmo-quick-checkout-selection
									>

										<div class="eilmo-cf-quick-selection__header">

											<h3 class="eilmo-cf-quick-selection__heading">

												<?php
												echo esc_html(
													$texts[
														'selection_heading'
													]
												);
												?>

											</h3>

											<button
												type="button"
												class="eilmo-cf-quick-selection__change"
												data-eilmo-quick-checkout-change
											>

												<?php
												echo esc_html(
													$texts[
														'change_selection'
													]
												);
												?>

											</button>

										</div>

										<div class="eilmo-cf-quick-selection__card">

											<div class="eilmo-cf-quick-selection__image">

												<img
													src="<?php
													echo esc_url(
														$product_image
													);
													?>"
													alt="<?php
													echo esc_attr(
														$product_name
													);
													?>"
													data-eilmo-quick-checkout-image
													data-eilmo-quick-product-image
												>

											</div>

											<div class="eilmo-cf-quick-selection__content">

												<strong
													class="eilmo-cf-quick-selection__title"
													data-eilmo-quick-checkout-product-title
												>

													<?php
													echo esc_html(
														$product_name
													);
													?>

												</strong>

												<span
													class="eilmo-cf-quick-selection__variation"
													data-eilmo-quick-checkout-variation
													hidden
												></span>

												<span class="eilmo-cf-quick-selection__quantity">

													<?php
													echo esc_html(
														$texts[
															'quantity_label'
														]
													);
													?>

													<strong data-eilmo-quick-checkout-quantity>
														<?php
														echo esc_html(
															(string) $initial_quantity
														);
														?>
													</strong>

												</span>

											</div>

											<div
												class="eilmo-cf-quick-selection__price"
												data-eilmo-quick-checkout-price
											>

												<?php
												echo wp_kses_post(
													$product_price_html
												);
												?>

											</div>

										</div>

									</section>

								<?php endif; ?>

								<?php
								/*
								 * Hidden normal-product adapter.
								 *
								 * Existing summary.js and checkout.js
								 * intentionally consume this as a
								 * normal checkout product.
								 */
								?>

								<div
									class="eilmo-cf-quick-checkout__product-adapter"
									data-eilmo-quick-checkout-product-adapter
									data-eilmo-product-item
									data-eilmo-item
									data-product-id="<?php
									echo esc_attr(
										(string) $product_id
									);
									?>"
									data-parent-product-id="<?php
									echo esc_attr(
										(string) $product_id
									);
									?>"
									data-parent-id="<?php
									echo esc_attr(
										(string) $product_id
									);
									?>"
									data-variation-id="0"
									data-price="<?php
									echo esc_attr(
										wc_format_decimal(
											$initial_price
										)
									);
									?>"
									data-product-price="<?php
									echo esc_attr(
										wc_format_decimal(
											$initial_price
										)
									);
									?>"
									hidden
								>

									<input
										type="hidden"
										value="<?php
										echo esc_attr(
											(string) $initial_quantity
										);
										?>"
										data-eilmo-quantity-input
										data-product-id="<?php
										echo esc_attr(
											(string) $product_id
										);
										?>"
										data-parent-product-id="<?php
										echo esc_attr(
											(string) $product_id
										);
										?>"
										data-parent-id="<?php
										echo esc_attr(
											(string) $product_id
										);
										?>"
										data-variation-id="0"
										data-price="<?php
										echo esc_attr(
											wc_format_decimal(
												$initial_price
											)
										);
										?>"
									>

								</div>
								<?php if ( '' !== $combo_html ) : ?>
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally. ?>
									<?php echo $combo_html; ?>
								<?php endif; ?>
								<?php if ( '' !== $order_bump_html || '' !== $discount_html ) : ?>
									<div class="eilmo-cf-special-discount-data" data-eilmo-special-discounts hidden aria-hidden="true">
										<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderers escape internally. ?>
										<?php echo $discount_html; ?>
										<?php echo $order_bump_html; ?>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $customer_html ) : ?>

									<div class="eilmo-cf-checkout__customer-information">

										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally.
										echo $customer_html;
										?>

									</div>

								<?php endif; ?>

								<?php if ( '' !== $delivery_html ) : ?>

									<div class="eilmo-cf-checkout__delivery">

										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally.
										echo $delivery_html;
										?>

									</div>

								<?php endif; ?>

								<?php if ( '' !== $advance_html ) : ?>

									<div class="eilmo-cf-checkout__advance-payment">

										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally.
										echo $advance_html;
										?>

									</div>

								<?php endif; ?>

								<?php if ( '' !== $payment_html ) : ?>

									<div class="eilmo-cf-checkout__payment-methods">

										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally.
										echo $payment_html;
										?>

									</div>

								<?php endif; ?>


							</main>

							<?php if ( '' !== $summary_html ) : ?>

								<aside
									class="eilmo-cf-checkout__summary"
									data-eilmo-summary-container
								>

									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing renderer escapes internally.
									echo $summary_html;
									?>

								</aside>

							<?php endif; ?>

						</div>

						<?php
						do_action(
							'eilmo_cf/after_checkout',
							$checkout_context,
							$instance_id
						);
						?>

					</div>

				</div>

			</section>

		</div>

		<?php

		$output =
			ob_get_clean();

		if (
			false ===
				$output
		) {
			return '';
		}

		return (string)
			apply_filters(
				'eilmo_cf/quick_checkout/html',
				$output,
				$this->product,
				$this->settings,
				$checkout_context
			);
	}

	/**
	 * Render checkout Bot Protection fields.
	 *
	 * Uses the exact same signed BotProtectionToken
	 * service as the normal CheckoutRenderer.
	 *
	 * checkout.js transports:
	 *
	 * - data-eilmo-security-start-token
	 * - data-eilmo-security-honeypot
	 *
	 * Server-side BotProtectionGuard performs the
	 * authoritative validation.
	 *
	 * @param string $instance_id Checkout instance ID.
	 *
	 * @return string
	 */
	private function render_security_fields(
		string $instance_id
	): string {

		if (
			! class_exists(
				BotProtectionToken::class
			)
		) {
			return '';
		}

		$token_service =
			new BotProtectionToken();

		$start_token =
			$token_service->create();

		if ( '' === $start_token ) {
			return '';
		}

		$honeypot_id =
			sanitize_html_class(
				$instance_id .
					'-security-contact'
			);

		$honeypot_name =
			sanitize_key(
				'eilmo_cf_security_contact_' .
					$instance_id
			);

		ob_start();
		?>

		<div
			class="eilmo-cf-checkout__security-fields"
			aria-hidden="true"
		>

			<input
				type="hidden"
				value="<?php echo esc_attr( $start_token ); ?>"
				data-eilmo-security-start-token
			>

			<div class="eilmo-cf-checkout__security-honeypot">

				<label
					for="<?php echo esc_attr( $honeypot_id ); ?>"
				>
					<?php
					esc_html_e(
						'Leave this field empty',
						'eilmo-checkout-flow'
					);
					?>
				</label>

				<input
					type="text"
					id="<?php echo esc_attr( $honeypot_id ); ?>"
					name="<?php echo esc_attr( $honeypot_name ); ?>"
					value=""
					tabindex="-1"
					autocomplete="off"
					autocapitalize="off"
					spellcheck="false"
					data-eilmo-security-honeypot
				>

			</div>

		</div>

		<?php

		$output =
			ob_get_clean();

		return false !==
			$output
				? $output
				: '';
	}

	/**
	 * Build normal Checkout Flow compatible context.
	 *
	 * @param string               $instance_id      Instance ID.
	 * @param array<string,mixed>  $content          Content settings.
	 * @param array<string,string> $texts            Texts.
	 * @param float                $unit_price       Initial unit price.
	 * @param int                  $initial_quantity Initial quantity.
	 *
	 * @return array<string,mixed>
	 */
	private function build_checkout_context(
		string $instance_id,
		array $content,
		array $texts,
		float $unit_price,
		int $initial_quantity
	): array {

		$product_id =
			absint(
				$this->product->get_id()
			);

		$product_total =
			max(
				0.0,
				$unit_price *
					$initial_quantity
			);

		$context =
			array(
				'quick_checkout' =>
					'yes',

				'instance_id' =>
					$instance_id,

				'product_id' =>
					$product_id,

				'product_ids' =>
					array(
						$product_id,
					),

				'selection' =>
					'single',

				/*
				 * Combo Offers.
				 */
				'show_combo_offers' =>
					$content[
						'show_combo_offers'
					],

				'combo_offer_ids' =>
					$content[
						'combo_offer_ids'
					],

				'combo_selection_mode' =>
					'multiple',

				'combo_title' =>
					$texts[
						'combo_heading'
					],

				'combo_show_description' =>
					'yes',

				'selected_combo_offer_ids' =>
					array(),

				/*
				 * Special Offers / Order Bumps.
				 */
				'show_order_bumps' =>
					$content[
						'show_order_bumps'
					],

				'order_bump_ids' =>
					$content[
						'order_bump_ids'
					],

				/*
				 * Normal checkout modules.
				 */
				'show_discounts' =>
					$content[
						'show_discounts'
					],

				'show_coupons' =>
					$content[
						'show_coupons'
					],

				'show_delivery' =>
					$content[
						'show_delivery'
					],

				'show_customer_information' =>
					$content[
						'show_customer_information'
					],

				'show_payment_methods' =>
					$content[
						'show_payment_methods'
					],

				'show_advance_payment' =>
					$content[
						'show_advance_payment'
					],

				'show_summary' =>
					'yes',

				/*
				 * Initial display totals only.
				 *
				 * Server recalculates authoritative
				 * pricing during order creation.
				 */
				'product_total' =>
					$product_total,

				'discounted_product_total' =>
					$product_total,

				'delivery_charge' =>
					0.0,

				'order_total' =>
					$product_total,

				'grand_total' =>
					$product_total,

				'delivery_method' =>
					'',

				'payment_type' =>
					'',

				'payment_method' =>
					'',

				'payment_transaction_id' =>
					'',

				/*
				 * Shared Summary layout.
				 */
				'mobile_summary_collapsed' =>
					'yes',

				'desktop_summary_position' =>
					'right_sticky',

				'tablet_summary_position' =>
					'below',

				'mobile_summary_position' =>
					'bottom_drawer',

				/*
				 * Order labels.
				 */
				'order_button_label' =>
					$texts[
						'order_button'
					],

				'order_processing_label' =>
					$texts[
						'processing_label'
					],
			);

		$context =
			apply_filters(
				'eilmo_cf/quick_checkout/checkout_context',
				$context,
				$this->product,
				$this->settings
			);

		return is_array(
			$context
		)
			? $context
			: array();
	}

	/**
	 * Render Combo Offers.
	 *
	 * @param array<string,mixed>  $settings    Checkout context.
	 * @param string               $instance_id Instance ID.
	 * @param array<string,string> $texts       Texts.
	 *
	 * @return string
	 */
	private function render_combo_offers(
		array $settings,
		string $instance_id,
		array $texts
	): string {
		if ( ! class_exists( ComboOfferRenderer::class ) ) {
			return '';
		}

		return ( new ComboOfferRenderer() )->render_state( $instance_id );
	}

	/**
	 * Render Order Bumps.
	 *
	 * Empty Quick Checkout IDs intentionally mean NONE.
	 *
	 * Native OrderBumpRenderer uses empty IDs as ALL,
	 * therefore Quick Checkout guards against an empty
	 * configured allow-list before calling it.
	 *
	 * @param array<string,mixed>  $settings    Checkout context.
	 * @param string               $instance_id Instance ID.
	 * @param array<string,string> $texts       Texts.
	 *
	 * @return string
	 */
	private function render_order_bumps(
		array $settings,
		string $instance_id,
		array $texts
	): string {
		if (
			! class_exists(
				OrderBumpRenderer::class
			)
		) {
			return '';
		}

		$context =
			array(
				'instance_id' =>
					$instance_id,

				'order_bump_ids' => array(),
				'hide_title' => true,
			);

		$context =
			apply_filters(
				'eilmo_cf/quick_checkout/order_bump_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			return '';
		}

		$renderer =
			new OrderBumpRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render automatic discount notice.
	 *
	 * @param array<string,mixed> $settings    Checkout context.
	 * @param string              $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_discount_notice(
		array $settings,
		string $instance_id
	): string {
		if (
			! class_exists(
				DiscountNoticeRenderer::class
			)
		) {
			return '';
		}

		$context =
			array(
				'hide_title' => true,
				'product_total' =>
					max(
						0.0,
						(float) (
							$settings[
								'product_total'
							] ??
								0
						)
					),

				'instance_id' =>
					$instance_id,

				'quick_checkout' =>
					'yes',

				'special_offer_scope' => 'all',

				'special_offer_ids' => array(),
			);

		$context =
			apply_filters(
				'eilmo_cf/checkout/discount_notice_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			return '';
		}

		$renderer =
			new DiscountNoticeRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render delivery.
	 *
	 * @param array<string,mixed> $settings    Checkout context.
	 * @param string              $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_delivery(
		array $settings,
		string $instance_id
	): string {

		if (
			'yes' !==
				(
					$settings[
						'show_delivery'
					] ??
						'yes'
				) ||
			! class_exists(
				DeliveryRenderer::class
			)
		) {
			return '';
		}

		$context =
			array(
				'order_total' =>
					max(
						0.0,
						(float) (
							$settings[
								'order_total'
							] ??
								0
						)
					),

				'selected_method_id' =>
					sanitize_key(
						(string) (
							$settings[
								'delivery_method'
							] ??
								''
						)
					),

				'instance_id' =>
					$instance_id,

				'quick_checkout' =>
					'yes',
			);

		$context =
			apply_filters(
				'eilmo_cf/checkout/delivery_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			return '';
		}

		$renderer =
			new DeliveryRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render customer information.
	 *
	 * @param array<string,mixed> $settings    Checkout context.
	 * @param string              $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_customer_information(
		array $settings,
		string $instance_id
	): string {

		if (
			'yes' !==
				(
					$settings[
						'show_customer_information'
					] ??
						'yes'
				) ||
			! class_exists(
				CustomerRenderer::class
			)
		) {
			return '';
		}

		$context =
			array(
				'instance_id' =>
					$instance_id,

				'quick_checkout' =>
					'yes',
			);

		$context_keys =
			array(
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_phone',
				'billing_email',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'order_comments',
			);

		foreach (
			$context_keys as
				$context_key
		) {

			if (
				! array_key_exists(
					$context_key,
					$settings
				)
			) {
				continue;
			}

			$context[
				$context_key
			] =
				$settings[
					$context_key
				];
		}

		$context =
			apply_filters(
				'eilmo_cf/checkout/customer_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			$context =
				array(
					'instance_id' =>
						$instance_id,

					'quick_checkout' =>
						'yes',
				);
		}

		$renderer =
			new CustomerRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render Advance / Full payment options.
	 *
	 * @param array<string,mixed> $settings    Checkout context.
	 * @param string              $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_advance_payment(
		array $settings,
		string $instance_id
	): string {

		if (
			'yes' !==
				(
					$settings[
						'show_advance_payment'
					] ??
						'yes'
				) ||
			! class_exists(
				AdvancePaymentRenderer::class
			)
		) {
			return '';
		}

		$product_total =
			max(
				0.0,
				(float) (
					$settings[
						'product_total'
					] ??
						0
				)
			);

		$discounted_product_total =
			max(
				0.0,
				(float) (
					$settings[
						'discounted_product_total'
					] ??
						$product_total
				)
			);

		$delivery_charge =
			max(
				0.0,
				(float) (
					$settings[
						'delivery_charge'
					] ??
						0
				)
			);

		$grand_total =
			max(
				0.0,
				(float) (
					$settings[
						'grand_total'
					] ??
						(
							$discounted_product_total +
							$delivery_charge
						)
				)
			);

		$context =
			array(
				'product_total' =>
					$product_total,

				'discounted_product_total' =>
					$discounted_product_total,

				'delivery_charge' =>
					$delivery_charge,

				'grand_total' =>
					$grand_total,

				'payment_type' =>
					sanitize_key(
						(string) (
							$settings[
								'payment_type'
							] ??
								''
						)
					),

				'instance_id' =>
					$instance_id,

				'quick_checkout' =>
					'yes',
			);

		$context =
			apply_filters(
				'eilmo_cf/checkout/advance_payment_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			return '';
		}

		$renderer =
			new AdvancePaymentRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render payment methods.
	 *
	 * @param array<string,mixed> $settings    Checkout context.
	 * @param string              $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_payment_methods(
		array $settings,
		string $instance_id
	): string {

		if (
			'yes' !==
				(
					$settings[
						'show_payment_methods'
					] ??
						'yes'
				) ||
			! class_exists(
				PaymentRenderer::class
			)
		) {
			return '';
		}

		$context =
			array(
				'instance_id' =>
					$instance_id,

				'payment_method' =>
					(string) (
						$settings[
							'payment_method'
						] ??
							''
					),

				'payment_transaction_id' =>
					(string) (
						$settings[
							'payment_transaction_id'
						] ??
							''
					),

				'quick_checkout' =>
					'yes',
			);

		$context =
			apply_filters(
				'eilmo_cf/checkout/payment_context',
				$context,
				$settings
			);

		if (
			! is_array(
				$context
			)
		) {
			$context =
				array(
					'instance_id' =>
						$instance_id,

					'quick_checkout' =>
						'yes',
				);
		}

		$renderer =
			new PaymentRenderer();

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render the Quick Checkout final order action.
	 *
	 * The action is embedded into SummaryRenderer through
	 * `_summary_order_html`, matching the normal Checkout Flow architecture.
	 * JavaScript selectors stay unchanged; only presentation ownership moves
	 * from the main column into the shared summary action slot.
	 *
	 * @param array<string,string> $texts Customer-facing Quick Checkout copy.
	 *
	 * @return string
	 */
	private function render_quick_order_submit( array $texts ): string {
		ob_start();
		?>
		<div
			class="eilmo-cf-quick-checkout__submit eilmo-cf-order-submit"
			data-eilmo-order-submit-section
		>
			<p
				class="eilmo-cf-order-submit__notice"
				data-eilmo-order-notice
				role="status"
				hidden
			></p>

			<p
				class="eilmo-cf-order-submit__error"
				data-eilmo-order-error
				role="alert"
				hidden
			></p>

			<button
				type="button"
				class="eilmo-cf-order-submit__button"
				data-eilmo-quick-checkout-submit
				data-label="<?php echo esc_attr( (string) ( $texts['order_button'] ?? 'Place Order' ) ); ?>"
				data-processing-label="<?php echo esc_attr( (string) ( $texts['processing_label'] ?? 'Processing...' ) ); ?>"
				aria-busy="false"
			>
				<span
					class="eilmo-cf-order-submit__spinner"
					data-eilmo-order-spinner
					aria-hidden="true"
					hidden
				></span>

				<span
					class="eilmo-cf-order-submit__button-text"
					data-eilmo-order-submit-text
					data-eilmo-quick-checkout-submit-text
				>
					<?php echo esc_html( (string) ( $texts['order_button'] ?? 'Place Order' ) ); ?>
				</span>
			</button>
		</div>
		<?php

		$output = ob_get_clean();

		return false === $output ? '' : (string) $output;
	}

	/**
	 * Render shared Summary.
	 *
	 * Quick Checkout settings may hide individual
	 * Summary rows without changing the global
	 * Checkout Flow configuration.
	 *
	 * @param array<string,mixed> $settings Checkout context.
	 * @param array<string,mixed> $content           Content settings.
	 * @param string              $order_submit_html Shared Summary order action HTML.
	 *
	 * @return string
	 */
	private function render_summary(
		array $settings,
		array $content,
		string $order_submit_html = ''
	): string {

		if (
			! class_exists(
				SummaryRenderer::class
			)
		) {
			return '';
		}

		if ( '' !== $order_submit_html ) {
			$settings['_summary_order_html'] = $order_submit_html;
		}

		$visibility_filter =
			static function (
				$visibility,
				$summary_settings
			) use (
				$content
			) {

				if (
					! is_array(
						$summary_settings
					) ||
					'yes' !==
						(
							$summary_settings[
								'quick_checkout'
							] ??
								'no'
						)
				) {
					return $visibility;
				}

				if (
					! is_array(
						$visibility
					)
				) {
					$visibility =
						array();
				}

				if (
					'yes' !==
						(
							$content[
								'show_combo_offers'
							] ??
								'yes'
						)
				) {
					$visibility[
						'combo_discount'
					] =
						false;
				}

				if (
					'yes' !==
						(
							$content[
								'show_order_bumps'
							] ??
								'yes'
						)
				) {
					$visibility[
						'order_bump_discount'
					] =
						false;
				}

				if (
					'yes' !==
						(
							$content[
								'show_discounts'
							] ??
								'yes'
						)
				) {
					$visibility[
						'automatic_discount'
					] =
						false;

					$visibility[
						'full_payment_discount'
					] =
						false;
				}

				if (
					'yes' !==
						(
							$content[
								'show_coupons'
							] ??
								'yes'
						)
				) {
					$visibility[
						'coupon_discount'
					] =
						false;
				}

				if (
					'yes' !==
						(
							$content[
								'show_delivery'
							] ??
								'yes'
						)
				) {
					$visibility[
						'delivery_charge'
					] =
						false;
				}

				if (
					'yes' !==
						(
							$content[
								'show_advance_payment'
							] ??
								'yes'
						)
				) {
					$visibility[
						'advance_payment'
					] =
						false;

					$visibility[
						'payment_breakdown'
					] =
						false;

					$visibility[
						'full_payment_discount'
					] =
						false;
				}

				return $visibility;
			};

		add_filter(
			'eilmo_cf/summary_visibility',
			$visibility_filter,
			999,
			2
		);

		try {

			$renderer =
				new SummaryRenderer();

			return $renderer->render(
				$settings
			);

		} finally {

			remove_filter(
				'eilmo_cf/summary_visibility',
				$visibility_filter,
				999
			);
		}
	}

	/**
	 * Get content settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_content_settings(): array {

		$content =
			isset(
				$this->settings[
					'content'
				]
			) &&
			is_array(
				$this->settings[
					'content'
				]
			)
				? $this->settings[
					'content'
				]
				: array();

		$yes_no_keys =
			array(
				'show_product_summary',
				'show_combo_offers',
				'show_order_bumps',
				'show_discounts',
				'show_coupons',
				'show_delivery',
				'show_customer_information',
				'show_payment_methods',
				'show_advance_payment',
			);

		$normalized =
			array();

		foreach (
			$yes_no_keys as
				$key
		) {

			$normalized[
				$key
			] =
				'yes' ===
					(
						$content[
							$key
						] ??
							'yes'
					)
					? 'yes'
					: 'no';
		}

		$normalized[
			'combo_offer_ids'
		] =
			$this->normalize_offer_ids(
				$content[
					'combo_offer_ids'
				] ??
					array()
			);

		$normalized[
			'order_bump_ids'
		] =
			$this->normalize_offer_ids(
				$content[
					'order_bump_ids'
				] ??
					array()
			);

		return $normalized;
	}

	/**
	 * Get presentation settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_presentation_settings(): array {

		$presentation =
			isset(
				$this->settings[
					'presentation'
				]
			) &&
			is_array(
				$this->settings[
					'presentation'
				]
			)
				? $this->settings[
					'presentation'
				]
				: array();

		$display_mode =
			sanitize_key(
				(string) (
					$presentation[
						'display_mode'
					] ??
						'bottom_sheet'
				)
			);

		if (
			! in_array(
				$display_mode,
				array(
					'bottom_sheet',
					'modal',
				),
				true
			)
		) {
			$display_mode =
				'bottom_sheet';
		}

		$desktop_width =
			isset(
				$presentation[
					'desktop_width'
				]
			) &&
			is_numeric(
				$presentation[
					'desktop_width'
				]
			)
				? (int) $presentation[
					'desktop_width'
				]
				: 800;

		$desktop_width =
			max(
				420,
				min(
					1200,
					$desktop_width
				)
			);

		return array(
			'display_mode' =>
				$display_mode,

			'desktop_width' =>
				$desktop_width,
		);
	}

	/**
	 * Get frontend texts.
	 *
	 * @return array<string,string>
	 */
	private function get_texts(): array {

		$defaults =
			QuickCheckoutSettings::get_defaults();

		$default_texts =
			isset(
				$defaults[
					'texts'
				]
			) &&
			is_array(
				$defaults[
					'texts'
				]
			)
				? $defaults[
					'texts'
				]
				: array();

		$saved_texts =
			isset(
				$this->settings[
					'texts'
				]
			) &&
			is_array(
				$this->settings[
					'texts'
				]
			)
				? $this->settings[
					'texts'
				]
				: array();

		$fallbacks =
			array(
				'product_order_button' =>
					__(
						'Order Now',
						'eilmo-checkout-flow'
					),

				'product_whatsapp_button' =>
					__(
						'Order via WhatsApp',
						'eilmo-checkout-flow'
					),

				'drawer_title' =>
					__(
						'Almost Done!',
						'eilmo-checkout-flow'
					),

				'drawer_description' =>
					__(
						'Review your selection and complete your order.',
						'eilmo-checkout-flow'
					),

				'selection_heading' =>
					__(
						'Your Selection',
						'eilmo-checkout-flow'
					),

				'change_selection' =>
					__(
						'Change',
						'eilmo-checkout-flow'
					),

				'offer_heading' =>
					__(
						'Complete Your Order',
						'eilmo-checkout-flow'
					),

				'combo_heading' =>
					__(
						'Special Combo Offer',
						'eilmo-checkout-flow'
					),

				'order_bump_heading' =>
					__(
						'You May Also Like',
						'eilmo-checkout-flow'
					),

				'total_label' =>
					__(
						'Total',
						'eilmo-checkout-flow'
					),

				'order_button' =>
					__(
						'Place Order',
						'eilmo-checkout-flow'
					),

				'whatsapp_button' =>
					__(
						'Continue on WhatsApp',
						'eilmo-checkout-flow'
					),

				'variation_required' =>
					__(
						'Please select product options first.',
						'eilmo-checkout-flow'
					),

				'close_label' =>
					__(
						'Close',
						'eilmo-checkout-flow'
					),

				'quantity_label' =>
					__(
						'Qty:',
						'eilmo-checkout-flow'
					),

				'processing_label' =>
					__(
						'Processing...',
						'eilmo-checkout-flow'
					),

				'checkout_not_ready' =>
					__(
						'Checkout is not ready. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					),
			);

		$texts =
			array();

		foreach (
			$fallbacks as
				$key =>
				$fallback
		) {

			$value =
				(string) (
					$saved_texts[
						$key
					] ??
						$default_texts[
							$key
						] ??
						$fallback
				);

			$value =
				sanitize_text_field(
					$value
				);

			$texts[
				$key
			] =
				'' !==
					$value
					? $value
					: $fallback;
		}

		return $texts;
	}

	/**
	 * Get initial display price.
	 *
	 * @return float
	 */
	private function get_initial_product_price(): float {

		if (
			function_exists(
				'wc_get_price_to_display'
			)
		) {
			$price =
				wc_get_price_to_display(
					$this->product
				);

			if (
				is_numeric(
					$price
				)
			) {
				return max(
					0.0,
					(float) $price
				);
			}
		}

		$price =
			$this->product->get_price();

		return is_numeric(
			$price
		)
			? max(
				0.0,
				(float) $price
			)
			: 0.0;
	}

	/**
	 * Get initial quantity.
	 *
	 * @return int
	 */
	private function get_initial_quantity(): int {

		$minimum =
			max(
				1,
				(int)
					$this->product
						->get_min_purchase_quantity()
			);

		return $minimum;
	}

	/**
	 * Get product image URL.
	 *
	 * @return string
	 */
	private function get_product_image_url(): string {

		$image_id =
			absint(
				$this->product->get_image_id()
			);

		if (
			$image_id > 0
		) {
			$image =
				wp_get_attachment_image_url(
					$image_id,
					'woocommerce_thumbnail'
				);

			if (
				is_string(
					$image
				) &&
				'' !==
					$image
			) {
				return $image;
			}
		}

		if (
			function_exists(
				'wc_placeholder_img_src'
			)
		) {
			return (string)
				wc_placeholder_img_src(
					'woocommerce_thumbnail'
				);
		}

		return '';
	}

	/**
	 * Normalize reusable Offer IDs.
	 *
	 * @param mixed $ids IDs.
	 *
	 * @return array<int,string>
	 */
	private function normalize_offer_ids(
		$ids
	): array {

		if (
			is_string(
				$ids
			)
		) {
			$ids =
				preg_split(
					'/[\s,]+/',
					$ids
				);
		}

		if (
			! is_array(
				$ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach (
			$ids as
				$id
		) {

			if (
				! is_scalar(
					$id
				)
			) {
				continue;
			}

			$id =
				sanitize_key(
					(string) $id
				);

			if (
				'' ===
					$id ||
				in_array(
					$id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$id;
		}

		return $normalized;
	}

	/**
	 * Generate unique instance ID.
	 *
	 * @return string
	 */
	private function generate_instance_id(): string {

		if (
			function_exists(
				'wp_unique_id'
			)
		) {
			return sanitize_html_class(
				wp_unique_id(
					'eilmo-cf-quick-checkout-'
				)
			);
		}

		return sanitize_html_class(
			uniqid(
				'eilmo-cf-quick-checkout-',
				false
			)
		);
	}
}
