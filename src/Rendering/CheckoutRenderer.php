<?php
/**
 * Checkout renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

use EilmoCheckout\Access\CheckoutAccess;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CheckoutStyle;
use EilmoCheckout\AdvancePayment\Rendering\AdvancePaymentRenderer;
use EilmoCheckout\ComboOffers\Rendering\ComboOfferRenderer;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Customer\Rendering\CustomerRenderer;
use EilmoCheckout\Delivery\Rendering\DeliveryRenderer;
use EilmoCheckout\Discounts\Rendering\DiscountNoticeRenderer;
use EilmoCheckout\Discounts\Services\SpecialDiscountContext;
use EilmoCheckout\OrderBumps\Rendering\OrderBumpRenderer;
use EilmoCheckout\Orders\Rendering\OrderRenderer;
use EilmoCheckout\Payment\Rendering\PaymentRenderer;
use EilmoCheckout\Products\Services\MultipleProductsConfig;
use EilmoCheckout\Products\Services\SingleProductConfig;
use EilmoCheckout\Security\BotProtection\BotProtectionToken;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the complete checkout flow.
 */
final class CheckoutRenderer {

	/**
	 * Checkout instance counter.
	 *
	 * @var int
	 */
	private static $instance_counter = 0;

	/**
	 * Render checkout.
	 *
	 * Final main-column order:
	 *
	 * Products
	 * -> Combo Offers
	 * -> Order Bumps
	 * -> Discount
	 * -> Delivery
	 * -> Customer Information
	 * -> Payment Option
	 * -> Payment Method
	 * -> Order Now
	 *
	 * @param array<string, mixed> $settings Checkout settings.
	 *
	 * @return string
	 */
    public function render(array $settings = array()): string {
        $presentation = \EilmoCheckout\Presentation\CheckoutPresentation::resolve(
            array('checkout_display'=>CheckoutDisplaySettings::get()), $settings
        );
        return \EilmoCheckout\Presentation\CheckoutLanguage::in_language($presentation['language'], function() use ($settings): string {
            return $this->render_instance($settings);
        });
    }

    private function render_instance(array $settings): string {

		Assets::enqueue_frontend();

		$settings =
			$this->prepare_settings(
				$settings
			);

		if ( empty( $settings['product_ids'] ) && 'yes' !== (string) ( $settings['external_products'] ?? 'no' ) ) {
			return '';
		}

		/**
		 * Filters final checkout settings before rendering.
		 *
		 * @param array<string, mixed> $settings Checkout settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/checkout_settings',
				$settings
			);

		if ( ! is_array( $settings ) ) {
			return '';
		}

		$settings =
			$this->prepare_settings(
				$settings
			);

		/*
		 * Single Product requires a configured primary product before the
		 * checkout can render. Multiple Products intentionally starts with an
		 * empty selection and receives products from Elementor Product Button /
		 * Variation Selector widgets, so the checkout shell must remain visible.
		 */
		if (
			empty( $settings['product_ids'] ) &&
			'multiple' !== (string) ( $settings['product_mode'] ?? '' )
		) {
			return '';
		}

		$instance_id =
			$this->generate_instance_id();

		$special_discount_context =
			( new SpecialDiscountContext() )->create_from_settings(
				$settings,
				$instance_id
			);
		$special_discount_context_json = wp_json_encode( $special_discount_context );
		if ( ! is_string( $special_discount_context_json ) ) {
			$special_discount_context_json = '{}';
		}

		$classes =
			$this->get_wrapper_classes(
				$settings
			);

		$style =
			$this->get_wrapper_style(
				$settings
			);

		$security_fields_html =
			$this->render_security_fields(
				$instance_id
			);


		$rendered_modules = array();

		ob_start();
		?>

		<div
			id="<?php echo esc_attr( $instance_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-eilmo-checkout
			data-eilmo-special-discount-context="<?php echo esc_attr( $special_discount_context_json ); ?>"
			data-eilmo-current-product-ids="<?php echo esc_attr( wp_json_encode( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $settings['product_ids'] ?? array() ) ) ) ) ) ) ); ?>"
			data-checkout-layout="<?php echo esc_attr( $settings['checkout_layout'] ); ?>"
            data-checkout-source-catalog="<?php echo esc_attr( wp_json_encode( \EilmoCheckout\Presentation\CheckoutLanguage::catalog( 'en' ) ) ); ?>"
            data-checkout-catalog="<?php echo esc_attr( wp_json_encode( \EilmoCheckout\Presentation\CheckoutLanguage::catalog( $settings['checkout_language'] ) ) ); ?>"
            data-checkout-language="<?php echo esc_attr( $settings['checkout_language'] ); ?>"
            data-selection="<?php echo esc_attr( $settings['selection'] ); ?>"
			data-product-mode="<?php echo esc_attr( $settings['product_mode'] ); ?>"
			data-primary-product-id="<?php echo esc_attr( (string) absint( $settings['product_id'] ?? 0 ) ); ?>"
			data-product-layout="<?php echo esc_attr( $settings['product_layout'] ); ?>"
			data-single-product="<?php echo esc_attr( $settings['single_product_mode'] ); ?>"
			data-multiple-products="<?php echo esc_attr( $settings['multiple_products_mode'] ); ?>"
			data-group-quantity="<?php echo esc_attr( $settings['group_quantity_control'] ); ?>"
			data-show-quantity="<?php echo esc_attr( $settings['show_quantity'] ); ?>"
			data-quantity-after-select="<?php echo esc_attr( $settings['group_quantity_control'] ); ?>"
			data-show-select-text="<?php echo esc_attr( $settings['show_select_text'] ); ?>"
			data-checkout-details-position="<?php echo esc_attr( $settings['checkout_details_position'] ); ?>"
			data-mobile-order-sticky="<?php echo esc_attr( $settings['mobile_order_sticky'] ); ?>"
			data-order-button-placement="<?php echo esc_attr( $settings['order_button_placement'] ); ?>"

			data-summary-desktop="<?php echo esc_attr( $settings['desktop_summary_position'] ); ?>"
			data-summary-tablet="<?php echo esc_attr( $settings['tablet_summary_position'] ); ?>"
			data-summary-mobile="<?php echo esc_attr( $settings['mobile_summary_position'] ); ?>"

			data-mobile-summary-collapsed="<?php echo esc_attr( $settings['mobile_summary_collapsed'] ); ?>"

			data-variation-layout-desktop="<?php echo esc_attr( $settings['variation_card_layout_desktop'] ); ?>"
			data-variation-layout-tablet="<?php echo esc_attr( $settings['variation_card_layout_tablet'] ); ?>"
			data-variation-layout-mobile="<?php echo esc_attr( $settings['variation_card_layout_mobile'] ); ?>"

			data-variation-columns-desktop="<?php echo esc_attr( (string) $settings['variation_columns_desktop'] ); ?>"
			data-variation-columns-tablet="<?php echo esc_attr( (string) $settings['variation_columns_tablet'] ); ?>"
			data-variation-columns-mobile="<?php echo esc_attr( (string) $settings['variation_columns_mobile'] ); ?>"

			<?php if ( '' !== $style ) : ?>
				style="<?php echo esc_attr( $style ); ?>"
			<?php endif; ?>
		>

			<?php
			/*
			 * --------------------------------------
			 * Checkout Security Fields
			 * --------------------------------------
			 *
			 * These fields are intentionally rendered
			 * inside each checkout instance.
			 *
			 * - start_token:
			 *   Server-generated signed timestamp token.
			 *
			 * - honeypot:
			 *   Legitimate customers never interact
			 *   with this field.
			 *
			 * checkout.js collects both fields and
			 * BotProtectionGuard validates them again
			 * server-side.
			 */
			if ( '' !== $security_fields_html ) {

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes all rendered values internally.
				echo $security_fields_html;
			}

			$combo_state_html = $this->render_combo_state( $instance_id );
			if ( '' !== $combo_state_html ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Combo renderer escapes its own values.
				echo $combo_state_html;
			}

			/**
			 * Fires before checkout content.
			 *
			 * @param array<string, mixed> $settings    Settings.
			 * @param string               $instance_id Instance ID.
			 */
			do_action(
				'eilmo_cf/before_checkout',
				$settings,
				$instance_id
			);
			?>

			<div class="eilmo-cf-checkout__inner">

				<main class="eilmo-cf-checkout__main">

					<?php
					// Movable modules assigned before the product collection.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Module renderers escape internally.
					echo $this->render_movable_slot( 'before_products', $settings, $instance_id, $rendered_modules );
					?>

					<?php
					/*
					 * --------------------------------------
					 * 1. Products
					 * --------------------------------------
					 */
					?>

					<div class="eilmo-cf-checkout__products">

						<?php if ( 'yes' === (string) ( $settings['multiple_products_mode'] ?? 'no' ) && empty( $settings['product_ids'] ) ) : ?>
							<header class="eilmo-cf-external-products__intro">
								<h2 class="eilmo-cf-external-products__title">
									<?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Your Selected Product' ) ); ?>
								</h2>
							</header>
						<?php endif; ?>

						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Product renderer escapes values internally.
						echo $this->render_products(
							$settings
						);
						?>

						<div class="eilmo-cf-external-products" data-eilmo-external-products <?php echo 'yes' === (string) ( $settings['multiple_products_mode'] ?? 'no' ) && empty( $settings['product_ids'] ) ? '' : 'hidden'; ?> aria-live="polite">
							<?php if ( 'yes' === (string) ( $settings['multiple_products_mode'] ?? 'no' ) && empty( $settings['product_ids'] ) ) : ?>
								<div class="eilmo-cf-external-products__empty" data-eilmo-external-products-empty>
									<span class="eilmo-cf-external-products__empty-icon" aria-hidden="true">!</span>
									<span><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Please select a product to continue.' ) ); ?></span>
								</div>
							<?php endif; ?>
						</div>

					</div>

					<?php
					// Movable modules assigned immediately after products.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Module renderers escape internally.
					echo $this->render_movable_slot( 'after_products', $settings, $instance_id, $rendered_modules );
					?>

					<?php
					/*
					 * --------------------------------------
					 * 4. Spend More, Save More
					 * --------------------------------------
					 */

					$discount_notice_html =
						$this->render_discount_notice(
							$settings,
							$instance_id
						);
					$special_discount_html =
						$this->render_special_offers(
							$settings,
							$instance_id
						);

					if ( '' !== $discount_notice_html || '' !== $special_discount_html ) :
						?>

						<div class="eilmo-cf-special-discount-data" data-eilmo-special-discounts hidden aria-hidden="true">
							<?php
							// Compatibility data adapters stay in the DOM for live preview only.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes internally.
							echo $discount_notice_html;
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes internally.
							echo $special_discount_html;
							?>
						</div>

					<?php endif; ?>

					<?php
					if ( 'main' === $settings['checkout_details_position'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child renderers escape internally.
						echo $this->render_checkout_details( $settings, $instance_id, $rendered_modules );
					}

					if ( 'yes' === (string) ( $settings['show_summary'] ?? 'yes' ) ) :
						?>
						<div
							class="eilmo-cf-mobile-checkout-details-target"
							data-eilmo-mobile-checkout-details-target
						></div>
						<?php
					endif;

					do_action(
						'eilmo_cf/after_products',
						$settings
					);
					?>

				</main>

				<?php if ( 'yes' === $settings['show_summary'] ) : ?>

					<aside
						class="eilmo-cf-checkout__summary"
						data-eilmo-summary-container
					>

						<?php
						$summary_details_html = 'below_summary' === $settings['checkout_details_position']
							? $this->render_checkout_details( $settings, $instance_id, $rendered_modules )
							: '';

						$summary_order_html = $this->should_render_order_at( $settings, 'below_summary' )
							? $this->render_order_submit(
								$settings,
								$instance_id,
								array( 'action_slot' => 'below_summary' )
							)
							: '';

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Summary/child renderers escape internally.
						echo $this->render_summary( $settings, $summary_details_html, $summary_order_html );
						?>

					</aside>

				<?php elseif ( 'below_summary' === $settings['checkout_details_position'] ) : ?>

					<div class="eilmo-cf-checkout__details-below eilmo-cf-checkout__details-below--standalone">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child renderers escape internally.
						echo $this->render_checkout_details( $settings, $instance_id, $rendered_modules );
						?>
					</div>

				<?php endif; ?>

			</div>

			<?php if ( 'yes' === $settings['mobile_order_sticky'] ) : ?>
				<div class="eilmo-cf-mobile-order-dock" data-eilmo-mobile-order-dock>
                    <div class="eilmo-cf-mobile-order-total"><span><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::text( 'summary.total', $settings['checkout_language'] ) ); ?></span><strong data-eilmo-mobile-summary-amount="grand-total" data-value="0"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong></div>
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The order renderer returns complete HTML and escapes every value internally.
					echo $this->render_order_submit(
						$settings,
						$instance_id,
						array(
							'footer_enabled' => 'no',
							'variant'        => 'mobile_sticky',
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			<?php endif; ?>

			<?php
			do_action(
				'eilmo_cf/after_checkout',
				$settings,
				$instance_id
			);
			?>

		</div>

		<?php

		$output =
			ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		return (string) apply_filters(
			'eilmo_cf/checkout_html',
			$output,
			$settings,
			$instance_id
		);
	}

	/**
	 * Render globally positioned Combo Offer / Special Offer modules for a slot.
	 *
	 * A render-once guard prevents duplicate output even when settings collide
	 * or future placement logic changes.
	 *
	 * @param string               $slot             Placement slot.
	 * @param array<string, mixed> $settings         Checkout settings.
	 * @param string               $instance_id      Checkout instance ID.
	 * @param array<string, bool>  $rendered_modules Render guard.
	 *
	 * @return string
	 */
	private function render_movable_slot(
		string $slot,
		array $settings,
		string $instance_id,
		array &$rendered_modules
	): string {
		/* Combo Offers are never presented inside checkout. */
		return '';
	}

	/**
	 * Render delivery through final order action as one movable details block.
	 *
	 * @param array<string, mixed> $settings         Checkout settings.
	 * @param string               $instance_id      Checkout instance ID.
	 * @param array<string, bool>  $rendered_modules Movable-module render guard.
	 *
	 * @return string
	 */
	private function render_checkout_details(
		array $settings,
		string $instance_id,
		array &$rendered_modules
	): string {
		ob_start();

		echo $this->render_movable_slot( 'before_customer', $settings, $instance_id, $rendered_modules );

		$customer_html = $this->render_customer_information( $settings, $instance_id );
		if ( '' !== $customer_html ) {
			echo '<div class="eilmo-cf-checkout__customer-information">' . $customer_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_movable_slot( 'after_customer', $settings, $instance_id, $rendered_modules );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child renderers escape internally.
		echo $this->render_movable_slot( 'before_delivery', $settings, $instance_id, $rendered_modules );

		$delivery_html = $this->render_delivery( $settings, $instance_id );
		if ( '' !== $delivery_html ) {
			$hide_delivery = 'no' === (string) ( $settings['eilmo_widget_layout']['show_delivery'] ?? 'yes' );
			echo '<div class="eilmo-cf-checkout__delivery"' . ( $hide_delivery ? ' style="display:none"' : '' ) . '>' . $delivery_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_movable_slot( 'after_delivery', $settings, $instance_id, $rendered_modules );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_movable_slot( 'before_payment', $settings, $instance_id, $rendered_modules );

		$advance_html = $this->render_advance_payment( $settings, $instance_id );
		if ( '' !== $advance_html ) {
			echo '<div class="eilmo-cf-checkout__advance-payment">' . $advance_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$payment_html = $this->render_payment_methods( $settings, $instance_id );
		if ( '' !== $payment_html ) {
			echo '<div class="eilmo-cf-checkout__payment-methods">' . $payment_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$payment_notice = ( '' !== $advance_html || '' !== $payment_html )
			? $this->render_payment_footer_notice()
			: '';
		if ( '' !== $payment_notice ) {
			echo '<div class="eilmo-cf-checkout__payment-footer">' . $payment_notice . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_movable_slot( 'after_payment', $settings, $instance_id, $rendered_modules );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_movable_slot( 'before_order', $settings, $instance_id, $rendered_modules );

		if ( $this->should_render_order_at( $settings, 'below_payment' ) ) {
			$order_html = $this->render_order_submit(
				$settings,
				$instance_id,
				array( 'action_slot' => 'below_payment' )
			);
			if ( '' !== $order_html ) {
				echo '<div class="eilmo-cf-checkout__order-submit">' . $order_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/* Secondary actions render once even when the Order button is duplicated. */
		do_action( 'eilmo_cf/after_checkout_actions', $settings, $instance_id );

		$output = ob_get_clean();
		return false !== $output ? (string) $output : '';
	}

	/**
	 * Determine whether the normal in-page Order button belongs at a slot.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $slot     below_payment|below_summary.
	 *
	 * @return bool
	 */
	private function should_render_order_at(
		array $settings,
		string $slot
	): bool {
		$placement = (string) ( $settings['order_button_placement'] ?? 'below_payment' );

		if ( 'both' === $placement ) {
			return true;
		}

		if ( 'below_summary' === $slot ) {
			return 'yes' === (string) ( $settings['show_summary'] ?? 'yes' )
				&& 'below_summary' === $placement;
		}

		if ( 'no' === (string) ( $settings['show_summary'] ?? 'yes' ) ) {
			return true;
		}

		return 'below_payment' === $placement;
	}

	/**
	 * Render global notice under the Payment Methods section.
	 *
	 * @return string
	 */
	private function render_payment_footer_notice(): string {
		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = array_replace_recursive( $defaults, $stored );
		$payment = isset( $settings['advance_payment'] ) && is_array( $settings['advance_payment'] ) ? $settings['advance_payment'] : array();
		if ( 'yes' !== ( $payment['footer_enabled'] ?? 'yes' ) || ! class_exists( TrustNoticeRenderer::class ) ) {
			return '';
		}
		return ( new TrustNoticeRenderer() )->render( array(
			'text' => \EilmoCheckout\Presentation\CheckoutLanguage::copy( (string) ( $payment['footer_text'] ?? '' ) ),
			'icon' => (string) ( $payment['footer_icon'] ?? 'shield' ),
			'context' => 'payment',
		) );
	}

	/**
	 * Render checkout Bot Protection fields.
	 *
	 * The minimum checkout time uses a signed token
	 * generated by PHP. The frontend only transports
	 * this value back to OrderAjax.
	 *
	 * The honeypot is deliberately not rendered with
	 * type="hidden". Basic bots commonly ignore hidden
	 * inputs, so it is an ordinary text input positioned
	 * far outside the visible viewport.
	 *
	 * Legitimate users cannot focus the field through
	 * keyboard navigation because tabindex is -1.
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

			<div
				style="
					position:absolute;
					left:-10000px;
					top:auto;
					width:1px;
					height:1px;
					overflow:hidden;
					opacity:0;
					pointer-events:none;
				"
			>

				<label
					for="<?php echo esc_attr( $honeypot_id ); ?>"
				>
					<?php
					echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Leave this field empty' ) );
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

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Prepare checkout settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_settings(
		array $settings
	): array {

		$display = CheckoutDisplaySettings::get();
		$layout_display = isset( $display['layout'] ) && is_array( $display['layout'] ) ? $display['layout'] : array();
		$product_display = isset( $display['products'] ) && is_array( $display['products'] ) ? $display['products'] : array();
		$summary_display = isset( $display['summary'] ) && is_array( $display['summary'] ) ? $display['summary'] : array();
		$order_display = isset( $display['order_button'] ) && is_array( $display['order_button'] ) ? $display['order_button'] : array();
		$single_product_defaults = ( new SingleProductConfig() )->get();
		$multiple_products_defaults = ( new MultipleProductsConfig() )->get();

		$summary_side = (string) ( $layout_display['summary_position'] ?? 'right' );
		$summary_sticky = 'yes' === ( $layout_display['summary_sticky'] ?? 'yes' );
		if ( 'below' === $summary_side ) {
			$desktop_summary_default = 'below';
		} elseif ( 'left' === $summary_side ) {
			$desktop_summary_default = $summary_sticky ? 'left_sticky' : 'left';
		} else {
			$desktop_summary_default = $summary_sticky ? 'right_sticky' : 'right';
		}

		$defaults = array(
			'product_id' =>
				0,

			'product_ids' =>
				array(),

			/* Checkout-instance content: shortcode / Elementor owns these. */
			'product_mode' =>
				'auto',

			'selection' =>
				(string) ( $multiple_products_defaults['product_selection'] ?? 'multiple' ),

			'product_layout' =>
				'cards',

			'single_product_mode' =>
				'no',

			'multiple_products_mode' =>
				'no',

			'single_product' =>
				$single_product_defaults,

			'multiple_products' =>
				$multiple_products_defaults,

			'product_group_title' =>
				'',

			'group_columns_desktop' =>
				(int) ( $product_display['group_columns_desktop'] ?? 3 ),

			'group_columns_tablet' =>
				(int) ( $product_display['group_columns_tablet'] ?? 2 ),

			'group_columns_mobile' =>
				(int) ( $product_display['group_columns_mobile'] ?? 2 ),

			'group_gallery_id' =>
				'',

			'group_quantity_control' =>
				(string) ( $product_display['group_quantity_control'] ?? 'no' ),

			'group_image_ratio' =>
				(string) ( $product_display['group_image_ratio'] ?? '16-9' ),

			'group_image_fit' =>
				(string) ( $product_display['group_image_fit'] ?? 'cover' ),

			'group_image_position' =>
				(string) ( $product_display['group_image_position'] ?? 'center' ),

			/*
			 * Product Galleries assigned to this checkout.
			 */
			'gallery_ids' =>
				array(),

			/*
			 * Combo Offers.
			 *
			 * These are per-checkout settings.
			 */
			'show_combo_offers' =>
				'yes',

			'combo_offer_ids' =>
				array(),

			'combo_selection_mode' =>
				'multiple',

			/*
			 * Empty = use global Combo title.
			 */
			'combo_title' =>
				'',

			'combo_show_description' =>
				'yes',

			'selected_combo_offer_ids' =>
				array(),

			/* Special Offers are opt-in per checkout instance. */
			'show_special_offers' =>
				(string) ( $settings['show_order_bumps'] ?? 'no' ),

			'special_offer_ids' =>
				$settings['order_bump_ids'] ?? array(),

			/* Internal compatibility aliases. */
			'show_order_bumps' =>
				'no',

			'order_bump_ids' =>
				array(),

			'show_thumbnail' =>
				(string) ( $product_display['show_thumbnail'] ?? 'yes' ),

			'show_title' =>
				(string) ( $product_display['show_title'] ?? 'yes' ),

			'show_description' =>
				(string) ( $product_display['show_description'] ?? 'yes' ),

			'show_price' =>
				(string) ( $product_display['show_price'] ?? 'yes' ),

			'show_quantity' =>
				(string) ( $product_display['show_quantity'] ?? 'no' ),

			'show_select_text' =>
				(string) ( $product_display['show_select_text'] ?? 'yes' ),

			'show_stock' =>
				(string) ( $product_display['show_stock'] ?? 'yes' ),

			'show_sale_badge' =>
				(string) ( $product_display['show_sale_badge'] ?? 'yes' ),

			'show_summary' =>
				(string) ( $product_display['show_summary'] ?? 'yes' ),

			'title_link' =>
				(string) ( $product_display['title_link'] ?? 'yes' ),

			'title_link_target' =>
				(string) ( $product_display['title_link_target'] ?? 'same' ),

			'variation_columns_desktop' =>
				(int) ( $product_display['variation_columns_desktop'] ?? 3 ),

			'variation_columns_tablet' =>
				(int) ( $product_display['variation_columns_tablet'] ?? 2 ),

			'variation_columns_mobile' =>
				(int) ( $product_display['variation_columns_mobile'] ?? 2 ),

			'variation_card_layout_desktop' =>
				'horizontal',

			'variation_card_layout_tablet' =>
				'horizontal',

			'variation_card_layout_mobile' =>
				'horizontal',

			'desktop_summary_position' =>
				$desktop_summary_default,

			'tablet_summary_position' =>
				'below',

			'mobile_summary_position' =>
				'yes' === (string) ( $layout_display['mobile_summary_sticky'] ?? 'yes' ) ? 'bottom_drawer' : 'inline',

			'desktop_summary_width' =>
				360,

			'desktop_sticky_offset' =>
				24,

			'mobile_summary_collapsed' =>
				'yes',

			'mobile_summary_sticky' =>
				(string) ( $layout_display['mobile_summary_sticky'] ?? 'yes' ),

			'checkout_details_position' =>
				(string) ( $layout_display['checkout_details_position'] ?? 'main' ),

			'variation_badge_enabled' =>
				(string) ( $product_display['variation_badge_enabled'] ?? 'yes' ),

			'variation_badge_style' =>
				(string) ( $product_display['variation_badge_style'] ?? 'badge' ),

			'select_label' =>
				(string) ( $product_display['select_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select' ) ),

			'selected_label' =>
				(string) ( $product_display['selected_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Selected' ) ),

			'summary_display' =>
				$summary_display,

			'order_button_display' =>
				$order_display,

			'order_button_placement' =>
				(string) ( $order_display['placement'] ?? 'below_payment' ),

			'mobile_order_sticky' =>
				(string) ( $order_display['mobile_sticky'] ?? 'yes' ),

			'payment_method' =>
				'',

			'payment_transaction_id' =>
				'',

			'order_button_label' =>
				(string) ( $order_display['label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Order Now' ) ),

			'order_processing_label' =>
				(string) ( $order_display['processing_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Processing...' ) ),
		);

		$settings =
			wp_parse_args(
				$settings,
				$defaults
			);

		/*
		 * Global presentation is authoritative. Checkout instances choose
		 * content/assignment only; visual card behavior remains consistent
		 * across every shortcode and Elementor checkout.
		 */
		foreach ( array(
			'show_thumbnail', 'show_title', 'show_description', 'show_price',
			'show_quantity', 'show_select_text', 'show_stock', 'show_sale_badge',
			'show_summary', 'title_link',
		) as $global_yes_no_key ) {
			$settings[ $global_yes_no_key ] =
				'yes' === (string) ( $product_display[ $global_yes_no_key ] ?? $defaults[ $global_yes_no_key ] ?? 'no' )
					? 'yes'
					: 'no';
		}

		$settings['title_link_target'] =
			'new' === (string) ( $product_display['title_link_target'] ?? 'same' ) ? 'new' : 'same';
		$settings['group_quantity_control'] =
			'yes' === (string) ( $product_display['group_quantity_control'] ?? 'no' ) ? 'yes' : 'no';
		$settings['group_columns_desktop'] = (int) ( $product_display['group_columns_desktop'] ?? 3 );
		$settings['group_columns_tablet'] = (int) ( $product_display['group_columns_tablet'] ?? 2 );
		$settings['group_columns_mobile'] = (int) ( $product_display['group_columns_mobile'] ?? 2 );
		$settings['group_image_ratio'] = (string) ( $product_display['group_image_ratio'] ?? '16-9' );
		$settings['group_image_fit'] = (string) ( $product_display['group_image_fit'] ?? 'cover' );
		$settings['group_image_position'] = (string) ( $product_display['group_image_position'] ?? 'center' );
		$settings['variation_columns_desktop'] = (int) ( $product_display['variation_columns_desktop'] ?? 3 );
		$settings['variation_columns_tablet'] = (int) ( $product_display['variation_columns_tablet'] ?? 2 );
		$settings['variation_columns_mobile'] = (int) ( $product_display['variation_columns_mobile'] ?? 2 );
		$settings['variation_badge_enabled'] =
			'no' === (string) ( $product_display['variation_badge_enabled'] ?? 'yes' ) ? 'no' : 'yes';
		$settings['variation_badge_style'] =
			'plain' === (string) ( $product_display['variation_badge_style'] ?? 'badge' ) ? 'plain' : 'badge';
		$settings['select_label'] = (string) ( $product_display['select_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select' ) );
		$settings['selected_label'] = (string) ( $product_display['selected_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Selected' ) );

		/* Combo section text/presentation stays global. */
		$settings['combo_title'] = '';
		$settings['combo_show_description'] = 'yes';

		/*
		 * Products.
		 */
		$product_ids =
			$this->normalize_product_ids(
				$settings['product_ids']
			);

		$product_id =
			absint(
				$settings['product_id']
			);

		if (
			$product_id > 0 &&
			! in_array(
				$product_id,
				$product_ids,
				true
			)
		) {
			array_unshift(
				$product_ids,
				$product_id
			);
		}

		if (
			empty( $product_ids ) &&
			function_exists( 'is_product' ) &&
			is_product()
		) {
			$current_product_id =
				absint(
					get_queried_object_id()
				);

			if ( $current_product_id > 0 ) {
				$product_ids[] =
					$current_product_id;
			}
		}

		$settings['product_ids'] =
			array_values(
				array_unique(
					$product_ids
				)
			);

		$settings['product_id'] =
			! empty(
				$settings['product_ids']
			)
				? $settings['product_ids'][0]
				: 0;

		$settings['selection'] =
			in_array(
				$settings['selection'],
				array(
					'single',
					'multiple',
				),
				true
			)
				? $settings['selection']
				: 'single';

		$settings['product_layout'] =
			'grouped' === $settings['product_layout']
				? 'grouped'
				: 'cards';

		/*
		 * New checkout instances pass an explicit product_mode. Legacy content
		 * keeps its historical count/layout inference through the auto value.
		 */
		$product_mode = sanitize_key(
			(string) ( $settings['product_mode'] ?? 'auto' )
		);

		if ( ! in_array( $product_mode, array( 'single', 'multiple' ), true ) ) {
			$product_mode =
				'cards' === $settings['product_layout'] &&
				1 === count( $settings['product_ids'] )
					? 'single'
					: 'multiple';
		}

		/* Single Product mode owns exactly one parent WooCommerce product. */
		if ( 'single' === $product_mode && count( $settings['product_ids'] ) > 1 ) {
			$settings['product_ids'] = array_slice( $settings['product_ids'], 0, 1 );
		}

		$settings['product_id'] = ! empty( $settings['product_ids'] )
			? $settings['product_ids'][0]
			: 0;

		$settings['product_mode'] = $product_mode;

		$single_product_overrides = isset( $settings['single_product'] ) &&
			is_array( $settings['single_product'] )
				? $settings['single_product']
				: array();

		$settings['single_product'] = ( new SingleProductConfig() )->get(
			$single_product_overrides
		);

		/* Elementor's checkout-only visibility controls are deliberately outside
		 * ProductSettings. Keep them after the global product config is sanitized,
		 * otherwise the widget switches are lost before SingleProductRenderer runs. */
		foreach ( array( 'show_checkout_selector', 'show_package_title', 'show_package_helper', 'show_selected_quantity', 'show_variation_descriptions', 'show_description' ) as $visibility_key ) {
			if ( array_key_exists( $visibility_key, $single_product_overrides ) ) {
				$settings['single_product'][ $visibility_key ] = 'no' === (string) $single_product_overrides[ $visibility_key ] ? 'no' : 'yes';
			}
		}
		if ( array_key_exists( 'max_visible_variations', $single_product_overrides ) ) {
			$settings['single_product']['max_visible_variations'] = max( 1, min( 12, absint( $single_product_overrides['max_visible_variations'] ) ) );
		}

		$multiple_products_overrides = isset( $settings['multiple_products'] ) &&
			is_array( $settings['multiple_products'] )
				? $settings['multiple_products']
				: array();

		/* The checkout-level selection control overrides only Multiple Products. */
		$multiple_products_overrides['product_selection'] = $settings['selection'];

		$settings['multiple_products'] = ( new MultipleProductsConfig() )->get(
			$multiple_products_overrides
		);

		$settings['single_product_mode'] = 'single' === $product_mode
			? 'yes'
			: 'no';

		$settings['multiple_products_mode'] = 'multiple' === $product_mode
			? 'yes'
			: 'no';

		if ( 'yes' === $settings['single_product_mode'] ) {
			$settings['selection'] =
				'multiple' === (string) ( $settings['single_product']['variation_selection'] ?? 'single' )
					? 'multiple'
					: 'single';

			/* Product-card quantity stays off; Selected Items opts in explicitly. */
			$settings['show_quantity'] = 'no';
			$settings['mobile_order_sticky'] =
				(string) ( $settings['single_product']['mobile_sticky_action'] ?? 'yes' );
		}

		if ( 'yes' === $settings['multiple_products_mode'] ) {
			$multiple_products = $settings['multiple_products'];

			$settings['selection'] =
				'multiple' === (string) ( $multiple_products['product_selection'] ?? 'multiple' )
					? 'multiple'
					: 'single';

			/* Multiple Products always uses independent compact white cards. */
			$settings['product_layout'] = 'cards';
			$settings['show_thumbnail'] =
				'hidden' === (string) ( $multiple_products['media'] ?? 'featured' )
					? 'no'
					: 'yes';
			$settings['show_title'] = (string) ( $multiple_products['show_title'] ?? 'yes' );
			$settings['show_description'] = (string) ( $multiple_products['show_description'] ?? 'yes' );
			$settings['show_price'] = (string) ( $multiple_products['show_price'] ?? 'yes' );
			$settings['show_quantity'] = (string) ( $multiple_products['show_quantity'] ?? 'yes' );
			$settings['show_stock'] = (string) ( $multiple_products['show_stock'] ?? 'yes' );
			$settings['show_sale_badge'] = (string) ( $multiple_products['show_sale_badge'] ?? 'yes' );
			$settings['mobile_order_sticky'] =
				(string) ( $multiple_products['mobile_sticky_action'] ?? 'yes' );
		}

		$settings['product_group_title'] =
			sanitize_text_field(
				(string) $settings['product_group_title']
			);

		$settings['group_columns_desktop'] =
			$this->sanitize_integer_range(
				$settings['group_columns_desktop'],
				1,
				6,
				3
			);

		$settings['group_columns_tablet'] =
			$this->sanitize_integer_range(
				$settings['group_columns_tablet'],
				1,
				6,
				2
			);

		$settings['group_columns_mobile'] =
			$this->sanitize_integer_range(
				$settings['group_columns_mobile'],
				1,
				4,
				2
			);

		$settings['group_gallery_id'] =
			sanitize_key( (string) $settings['group_gallery_id'] );

		$settings['group_quantity_control'] =
			'no' === $settings['group_quantity_control']
				? 'no'
				: 'yes';

		$settings['group_image_ratio'] =
			in_array(
				$settings['group_image_ratio'],
				array( '1-1', '4-3', '3-2', '16-9' ),
				true
			)
				? $settings['group_image_ratio']
				: '16-9';

		$settings['group_image_fit'] =
			'contain' === $settings['group_image_fit']
				? 'contain'
				: 'cover';

		$settings['group_image_position'] =
			in_array(
				$settings['group_image_position'],
				array( 'center', 'top', 'bottom', 'left', 'right' ),
				true
			)
				? $settings['group_image_position']
				: 'center';

		$settings['gallery_ids'] =
			$this->normalize_gallery_ids(
				$settings['gallery_ids']
			);

		/*
		 * Combo Offers.
		 */
		$settings['combo_offer_ids'] =
			$this->normalize_combo_offer_ids(
				$settings['combo_offer_ids']
			);

		$settings['selected_combo_offer_ids'] =
			$this->normalize_combo_offer_ids(
				$settings['selected_combo_offer_ids']
			);

		$settings['selected_combo_offer_ids'] =
			array_values(
				array_intersect(
					$settings['selected_combo_offer_ids'],
					$settings['combo_offer_ids']
				)
			);

		$settings['combo_selection_mode'] =
			in_array(
				$settings['combo_selection_mode'],
				array(
					'single',
					'multiple',
				),
				true
			)
				? $settings['combo_selection_mode']
				: 'multiple';

		$settings['combo_title'] =
			sanitize_text_field(
				(string) $settings['combo_title']
			);

		/* Special Offer IDs are checkout-instance assignments. */
		$settings['special_offer_ids'] =
			$this->normalize_special_offer_ids(
				$settings['special_offer_ids']
			);

		/* Keep legacy consumers synchronized without exposing old naming publicly. */
		$settings['order_bump_ids'] = $settings['special_offer_ids'];
		$settings['show_order_bumps'] = $settings['show_special_offers'];

		/*
		 * Yes / No settings.
		 */
		$yes_no_settings = array(
			'show_combo_offers',
			'combo_show_description',
			'show_special_offers',
			'show_order_bumps',
			'show_thumbnail',
			'show_title',
			'show_description',
			'show_price',
			'show_quantity',
			'show_select_text',
			'show_stock',
			'show_sale_badge',
			'show_summary',
			'title_link',
			'mobile_summary_collapsed',
		);

		foreach ( $yes_no_settings as $setting_key ) {

			$settings[ $setting_key ] =
				'yes' ===
					$settings[ $setting_key ]
						? 'yes'
						: 'no';
		}

		$settings['title_link_target'] =
			in_array(
				$settings['title_link_target'],
				array(
					'same',
					'new',
				),
				true
			)
				? $settings['title_link_target']
				: 'same';

		/*
		 * Variation layout.
		 */
		$settings['variation_columns_desktop'] =
			$this->sanitize_integer_range(
				$settings['variation_columns_desktop'],
				1,
				6,
				3
			);

		$settings['variation_columns_tablet'] =
			$this->sanitize_integer_range(
				$settings['variation_columns_tablet'],
				1,
				6,
				2
			);

		$settings['variation_columns_mobile'] =
			$this->sanitize_integer_range(
				$settings['variation_columns_mobile'],
				1,
				4,
				2
			);

		$settings['variation_card_layout_desktop'] =
			$this->sanitize_variation_card_layout(
				$settings['variation_card_layout_desktop']
			);

		$settings['variation_card_layout_tablet'] =
			$this->sanitize_variation_card_layout(
				$settings['variation_card_layout_tablet']
			);

		$settings['variation_card_layout_mobile'] =
			$this->sanitize_variation_card_layout(
				$settings['variation_card_layout_mobile']
			);

		/*
		 * Summary.
		 */
		$settings['desktop_summary_position'] =
			$this->sanitize_desktop_summary_position(
				$settings['desktop_summary_position']
			);

		$settings['tablet_summary_position'] =
			$this->sanitize_tablet_summary_position(
				$settings['tablet_summary_position']
			);

		$settings['mobile_summary_position'] =
			$this->sanitize_mobile_summary_position(
				$settings['mobile_summary_position']
			);

		$settings['desktop_summary_width'] =
			$this->sanitize_integer_range(
				$settings['desktop_summary_width'],
				280,
				600,
				360
			);

		$settings['desktop_sticky_offset'] =
			$this->sanitize_integer_range(
				$settings['desktop_sticky_offset'],
				0,
				250,
				24
			);

		$settings['checkout_details_position'] =
			'below_summary' === $settings['checkout_details_position'] ? 'below_summary' : 'main';

		$settings['mobile_summary_sticky'] =
			'no' === (string) $settings['mobile_summary_sticky'] ? 'no' : 'yes';

		$settings['mobile_summary_position'] =
			'yes' === $settings['mobile_summary_sticky'] ? 'bottom_drawer' : 'inline';

		$settings['order_button_placement'] = in_array(
			(string) $settings['order_button_placement'],
			array( 'below_payment', 'below_summary', 'both' ),
			true
		) ? (string) $settings['order_button_placement'] : 'below_payment';

		$settings['mobile_order_sticky'] =
			'no' === (string) $settings['mobile_order_sticky'] ? 'no' : 'yes';

		/*
		 * Keep the desktop sidebar choice intact in conversion-rail mode.
		 * Mobile Summary behavior is now controlled by its dedicated global
		 * switch instead of being forced inline.
		 */
		if ( 'below_summary' === $settings['checkout_details_position'] ) {
			$settings['tablet_summary_position'] = 'below';
		}

		$settings['variation_badge_enabled'] =
			'no' === $settings['variation_badge_enabled'] ? 'no' : 'yes';

		$settings['variation_badge_style'] =
			'plain' === $settings['variation_badge_style'] ? 'plain' : 'badge';

		$settings['select_label'] = sanitize_text_field( (string) $settings['select_label'] );
		$settings['selected_label'] = sanitize_text_field( (string) $settings['selected_label'] );

		/*
		 * Payment.
		 */
		$settings['payment_method'] =
			sanitize_text_field(
				(string) $settings['payment_method']
			);

		$settings['payment_transaction_id'] =
			sanitize_text_field(
				(string) $settings['payment_transaction_id']
			);

		/*
		 * Order button.
		 */
		$settings['order_button_label'] =
			sanitize_text_field(
				(string) $settings['order_button_label']
			);

		if (
			'' ===
				$settings['order_button_label']
		) {
			$settings['order_button_label'] =
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Order Now' );
		}

		$settings['order_processing_label'] =
			sanitize_text_field(
				(string) $settings['order_processing_label']
			);

		if (
			'' ===
				$settings['order_processing_label']
		) {
			$settings['order_processing_label'] =
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Processing...' );
		}

		return \EilmoCheckout\Presentation\CheckoutPresentation::apply( \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $settings ), \EilmoCheckout\Presentation\CheckoutPresentation::resolve( array( 'checkout_display' => $display ), $settings ) );
	}

	/**
	 * Normalize product IDs.
	 *
	 * @param mixed $product_ids Product IDs.
	 *
	 * @return array<int>
	 */
	private function normalize_product_ids(
		$product_ids
	): array {

		if ( is_string( $product_ids ) ) {
			$product_ids =
				preg_split(
					'/[\s,]+/',
					$product_ids
				);
		}

		if ( ! is_array( $product_ids ) ) {
			return array();
		}

		$normalized =
			array();

		foreach ( $product_ids as $product_id ) {

			$product_id =
				absint(
					$product_id
				);

			if ( $product_id <= 0 ) {
				continue;
			}

			$normalized[] =
				$product_id;
		}

		return array_values(
			array_unique(
				$normalized
			)
		);
	}

	/**
	 * Normalize Product Gallery IDs.
	 *
	 * Supports array and comma/space separated string.
	 *
	 * @param mixed $gallery_ids Gallery IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_gallery_ids(
		$gallery_ids
	): array {

		if ( is_string( $gallery_ids ) ) {
			$gallery_ids = preg_split(
				'/[\s,]+/',
				$gallery_ids
			);
		}

		if ( ! is_array( $gallery_ids ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $gallery_ids as $gallery_id ) {
			$gallery_id = sanitize_key(
				(string) $gallery_id
			);

			if ( '' !== $gallery_id ) {
				$normalized[] = $gallery_id;
			}
		}

		return array_values(
			array_unique( $normalized )
		);
	}

	/**
	 * Normalize Combo Offer IDs.
	 *
	 * Supports array and comma/space separated string.
	 *
	 * @param mixed $offer_ids Combo Offer IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_combo_offer_ids(
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

			if ( '' === $offer_id ) {
				continue;
			}

			$normalized[] =
				$offer_id;
		}

		return array_values(
			array_unique(
				$normalized
			)
		);
	}

	/**
	 * Normalize Special Offer IDs.
	 *
	 * Supports array and comma/space separated string.
	 *
	 * @param mixed $offer_ids Special Offer IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_special_offer_ids(
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

			if ( '' === $offer_id ) {
				continue;
			}

			$normalized[] =
				$offer_id;
		}

		return array_values(
			array_unique(
				$normalized
			)
		);
	}

	/**
	 * Render products.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return string
	 */
	private function render_products(
		array $settings
	): string {

		if (
			! class_exists(
				ProductRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new ProductRenderer();

		return $renderer->render(
			$settings
		);
	}

	/**
	 * Render hidden Combo state for Elementor Combo Button widgets.
	 *
	 * @param string $instance_id Checkout instance ID.
	 *
	 * @return string
	 */
	private function render_combo_state( string $instance_id ): string {
		if ( ! class_exists( ComboOfferRenderer::class ) ) {
			return '';
		}

		return ( new ComboOfferRenderer() )->render_state( $instance_id );
	}


	/**
	 * Render Special Offers.
	 *
	 * Special Offers are independent purchase contexts.
	 * The same WooCommerce product may therefore exist
	 * as a normal product, Combo component and Order
	 * Special Offer without merging those contexts.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_special_offers(
		array $settings,
		string $instance_id
	): string {
		if (
			! class_exists(
				OrderBumpRenderer::class
			)
		) {
			return '';
		}

		$context = array(
			'instance_id' =>
				$instance_id,

			'hide_title' =>
				true,

			/*
			 * Special Discounts are global automatic rules.
			 * Empty means all globally enabled rules; checkout
			 * instances no longer own presentation assignments.
			 */
			'special_offer_ids' => array(),
		);

		/**
		 * Filters Special Offer rendering context.
		 *
		 * @param array<string, mixed> $context  Context.
		 * @param array<string, mixed> $settings Settings.
		 */
		$context =
			apply_filters(
				'eilmo_cf/checkout/special_offer_context',
				$context,
				$settings
			);

		/* Preserve extensions using the historical context filter. */
		$context = apply_filters(
			'eilmo_cf/checkout/order_bump_context',
			$context,
			$settings
		);

		if ( ! is_array( $context ) ) {
			return '';
		}

		return (
			new OrderBumpRenderer()
		)->render(
			$context
		);
	}

	/**
	 * Render discount notice.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
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

		$renderer =
			new DiscountNoticeRenderer();

		$product_total =
			isset(
				$settings['product_total']
			)
				? max(
					0.0,
					(float) $settings['product_total']
				)
				: 0.0;

		$context = array(
			'product_total' =>
				$product_total,

			'instance_id' =>
				$instance_id,

			'hide_title' =>
				true,

			'special_offer_scope' => 'all',

			'special_offer_ids' => array(),
		);

		$context =
			apply_filters(
				'eilmo_cf/checkout/discount_notice_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context =
				array();
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render delivery.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_delivery(
		array $settings,
		string $instance_id
	): string {

		if (
			! class_exists(
				DeliveryRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new DeliveryRenderer();

		$context = array(
			'order_total' =>
				isset(
					$settings['order_total']
				)
					? max(
						0.0,
						(float) $settings['order_total']
					)
					: 0.0,

			'selected_method_id' =>
				isset(
					$settings['delivery_method']
				)
					? sanitize_key(
						(string) $settings['delivery_method']
					)
					: '',

			'instance_id' =>
				$instance_id,
			'layout_overrides' => isset( $settings['eilmo_widget_layout'] ) && is_array( $settings['eilmo_widget_layout'] ) ? $settings['eilmo_widget_layout'] : array(),
		);

		$context =
			apply_filters(
				'eilmo_cf/checkout/delivery_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context =
				array();
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render customer information.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_customer_information(
		array $settings,
		string $instance_id
	): string {

		if (
			! class_exists(
				CustomerRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new CustomerRenderer();

		$context = array(
			'instance_id' =>
				$instance_id,
		);

		$context_keys = array(
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

		foreach ( $context_keys as $context_key ) {

			if (
				! array_key_exists(
					$context_key,
					$settings
				)
			) {
				continue;
			}

			$context[ $context_key ] =
				$settings[ $context_key ];
		}

		$context =
			apply_filters(
				'eilmo_cf/checkout/customer_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context = array(
				'instance_id' =>
					$instance_id,
			);
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render advance payment.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_advance_payment(
		array $settings,
		string $instance_id
	): string {

		if (
			! class_exists(
				AdvancePaymentRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new AdvancePaymentRenderer();

		$product_total =
			isset(
				$settings['product_total']
			)
				? max(
					0.0,
					(float) $settings['product_total']
				)
				: 0.0;

		$discounted_product_total =
			isset(
				$settings['discounted_product_total']
			)
				? max(
					0.0,
					(float) $settings['discounted_product_total']
				)
				: $product_total;

		$delivery_charge =
			isset(
				$settings['delivery_charge']
			)
				? max(
					0.0,
					(float) $settings['delivery_charge']
				)
				: 0.0;

		if ( isset( $settings['grand_total'] ) ) {

			$grand_total =
				max(
					0.0,
					(float) $settings['grand_total']
				);

		} elseif ( isset( $settings['order_total'] ) ) {

			$grand_total =
				max(
					0.0,
					(float) $settings['order_total']
				);

		} else {

			$grand_total =
				max(
					0.0,
					$discounted_product_total +
						$delivery_charge
				);
		}

		$context = array(
			'product_total' =>
				$product_total,

			'discounted_product_total' =>
				$discounted_product_total,

			'delivery_charge' =>
				$delivery_charge,

			'grand_total' =>
				$grand_total,

			'payment_type' =>
				isset(
					$settings['payment_type']
				)
					? sanitize_key(
						(string) $settings['payment_type']
					)
					: '',

			'instance_id' =>
				$instance_id,
			'layout_overrides' => isset( $settings['eilmo_widget_layout'] ) && is_array( $settings['eilmo_widget_layout'] ) ? $settings['eilmo_widget_layout'] : array(),
		);

		$context =
			apply_filters(
				'eilmo_cf/checkout/advance_payment_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context =
				array();
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render payment methods.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_payment_methods(
		array $settings,
		string $instance_id
	): string {

		if (
			! class_exists(
				PaymentRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new PaymentRenderer();

		$context = array(
			'instance_id' =>
				$instance_id,
			'layout_overrides' => isset( $settings['eilmo_widget_layout'] ) && is_array( $settings['eilmo_widget_layout'] ) ? $settings['eilmo_widget_layout'] : array(),

			'payment_method' =>
				(string) (
					$settings['payment_method'] ??
						''
				),

			'payment_transaction_id' =>
				(string) (
					$settings['payment_transaction_id'] ??
						''
				),
		);

		$context =
			apply_filters(
				'eilmo_cf/checkout/payment_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context = array(
				'instance_id' =>
					$instance_id,
			);
		}

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render Order Now / Login action.
	 *
	 * Checkout access is resolved on the server before
	 * the final action markup is generated.
	 *
	 * Logged-out customers in Logged-in Users Only mode
	 * receive a real login link immediately. No frontend
	 * JavaScript is used to swap or rewrite the action.
	 *
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function render_order_submit(
		array $settings,
		string $instance_id,
		array $overrides = array()
	): string {

		$checkout_access =
			new CheckoutAccess();

		if (
			$checkout_access->requires_login()
		) {
			return $this->render_login_order_action(
				$checkout_access
			);
		}

		if (
			! class_exists(
				OrderRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new OrderRenderer();

		$order_display = isset( $settings['order_button_display'] ) && is_array( $settings['order_button_display'] )
			? $settings['order_button_display']
			: array();

		if ( ! empty( $overrides ) ) {
			$order_display = array_replace( $order_display, $overrides );
		}

		$full_button_template = (string) ( $order_display['full_button_template'] ?? '' );
		if ( in_array( trim( $full_button_template ), array( '{amount} Pay and confirm order', '{amount} পেমেন্ট করে অর্ডার করুন' ), true ) ) {
			$full_button_template = \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount}' );
		}

		$context = array(
			'instance_id' =>
				$instance_id,

			'show_amount' =>
				(string) ( $order_display['show_amount'] ?? 'no' ),

			'amount_source' =>
				(string) ( $order_display['amount_source'] ?? 'auto' ),

			'footer_enabled' =>
				(string) ( $order_display['footer_enabled'] ?? 'yes' ),

			'footer_text' =>
				(string) ( $order_display['footer_text'] ?? '' ),

			'footer_icon' =>
				(string) ( $order_display['footer_icon'] ?? 'shield' ),

			'variant' =>
				(string) ( $order_display['variant'] ?? 'default' ),

			'action_slot' =>
				(string) ( $order_display['action_slot'] ?? 'below_payment' ),

			'button_label' =>
				(string) (
					$settings['order_button_label'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Order Now' )
				),

			'processing_label' =>
				(string) (
					$settings['order_processing_label'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Processing...' )
			),
			'cod_button_template' => (string) ( $order_display['cod_button_template'] ?? '' ),
			'advance_button_template' => (string) ( $order_display['advance_button_template'] ?? '' ),
			'full_button_template' => $full_button_template,
		);

		$context =
			apply_filters(
				'eilmo_cf/checkout/order_context',
				$context,
				$settings
			);

		if ( ! is_array( $context ) ) {
			$context = array(
				'instance_id' =>
					$instance_id,
			);
		}

		/*
		 * Integrations rendered in the same action slot may take ownership of
		 * the shared trust footer. WhatsApp uses this to place the notice after
		 * its button, while the normal Order Now fallback remains unchanged when
		 * that integration is disabled or unavailable.
		 */
		$context['footer_enabled'] = (string) apply_filters(
			'eilmo_cf/orders/footer_enabled',
			(string) ( $context['footer_enabled'] ?? 'yes' ),
			$context,
			$settings
		);

		return $renderer->render(
			$context
		);
	}

	/**
	 * Render server-side login action.
	 *
	 * The same shared Order button classes are retained so
	 * normal Checkout Flow and Elementor styling can continue
	 * to target the final checkout action consistently.
	 *
	 * @param CheckoutAccess $checkout_access Access service.
	 *
	 * @return string
	 */
	private function render_login_order_action(
		CheckoutAccess $checkout_access
	): string {

		$login_url =
			$checkout_access->get_login_url();

		$login_label =
			$checkout_access->get_login_button_label();

		if (
			'' ===
				trim(
					$login_url
				)
		) {
			return '';
		}

		ob_start();
		?>

		<div class="eilmo-cf-order-submit eilmo-cf-order-submit--login">

			<a
				href="<?php echo esc_url( $login_url ); ?>"
				class="eilmo-cf-order-submit__button eilmo-cf-checkout-login-button"
				data-eilmo-checkout-login-button
			>

				<span class="eilmo-cf-order-submit__button-text">
					<?php echo esc_html( $login_label ); ?>
				</span>

			</a>

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
	 * Render summary.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return string
	 */
	private function render_summary(
		array $settings,
		string $checkout_details_html = '',
		string $summary_order_html = ''
	): string {

		if (
			! class_exists(
				SummaryRenderer::class
			)
		) {
			return '';
		}

		$renderer =
			new SummaryRenderer();

		if ( '' !== $checkout_details_html ) {
			$settings['_checkout_details_html'] = $checkout_details_html;
		}

		if ( '' !== $summary_order_html ) {
			$settings['_summary_order_html'] = $summary_order_html;
		}

		return $renderer->render(
			$settings
		);
	}

	/**
	 * Sanitize variation card layout.
	 *
	 * @param mixed $layout Layout.
	 *
	 * @return string
	 */
	private function sanitize_variation_card_layout(
		$layout
	): string {

		$allowed = array(
			'horizontal',
			'vertical',
		);

		return in_array(
			$layout,
			$allowed,
			true
		)
			? $layout
			: 'horizontal';
	}

	/**
	 * Sanitize desktop summary position.
	 *
	 * @param mixed $position Position.
	 *
	 * @return string
	 */
	private function sanitize_desktop_summary_position(
		$position
	): string {

		$allowed = array(
			'below',
			'left',
			'left_sticky',
			'right',
			'right_sticky',
			'right_fixed',
		);

		return in_array(
			$position,
			$allowed,
			true
		)
			? $position
			: 'right_sticky';
	}

	/**
	 * Sanitize tablet summary position.
	 *
	 * @param mixed $position Position.
	 *
	 * @return string
	 */
	private function sanitize_tablet_summary_position(
		$position
	): string {

		$allowed = array(
			'below',
			'right_sticky',
			'bottom_drawer',
		);

		return in_array(
			$position,
			$allowed,
			true
		)
			? $position
			: 'below';
	}

	/**
	 * Sanitize mobile summary position.
	 *
	 * @param mixed $position Position.
	 *
	 * @return string
	 */
	private function sanitize_mobile_summary_position(
		$position
	): string {

		$allowed = array(
			'inline',
			'bottom_drawer',
			'bottom_full',
		);

		return in_array(
			$position,
			$allowed,
			true
		)
			? $position
			: 'bottom_drawer';
	}

	/**
	 * Sanitize integer range.
	 *
	 * @param mixed $value    Value.
	 * @param int   $minimum  Minimum.
	 * @param int   $maximum  Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function sanitize_integer_range(
		$value,
		int $minimum,
		int $maximum,
		int $fallback
	): int {

		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$value =
			(int) $value;

		return max(
			$minimum,
			min(
				$maximum,
				$value
			)
		);
	}

	/**
	 * Generate checkout instance ID.
	 *
	 * @return string
	 */
	private function generate_instance_id(): string {

		++self::$instance_counter;

		return sprintf(
			'eilmo-cf-checkout-%d-%s',
			self::$instance_counter,
			wp_generate_uuid4()
		);
	}

	/**
	 * Get checkout classes.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string>
	 */
	private function get_wrapper_classes(
		array $settings
	): array {

		$classes = array(
			'eilmo-cf-checkout',

			'eilmo-cf-checkout--selection-' .
				sanitize_html_class(
					$settings['selection']
				),

			'eilmo-cf-checkout--product-layout-' .
				sanitize_html_class(
					$settings['product_layout']
				),
		);

		if (
			'yes' ===
				$settings['show_summary']
		) {
			$classes[] =
				'eilmo-cf-checkout--has-summary';
		}

		if ( 'yes' === (string) ( $settings['single_product_mode'] ?? 'no' ) ) {
			$classes[] = 'eilmo-cf-checkout--single-product';
		}

		if ( 'yes' === (string) ( $settings['multiple_products_mode'] ?? 'no' ) ) {
			$classes[] = 'eilmo-cf-checkout--multiple-products';
		}

		return $classes;
	}

	/**
	 * Get checkout inline CSS variables.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return string
	 */
	private function get_wrapper_style(
		array $settings
	): string {

		$ratio_map = array(
			'1-1'  => '1 / 1',
			'4-3'  => '4 / 3',
			'3-2'  => '3 / 2',
			'16-9' => '16 / 9',
		);

		$group_image_ratio =
			$ratio_map[ $settings['group_image_ratio'] ] ?? '16 / 9';

		$layout_variables = sprintf(
			'--eilmo-cf-summary-width:%1$dpx;' .
			'--eilmo-cf-summary-sticky-offset:%2$dpx;' .
			'--eilmo-cf-variation-columns-desktop:%3$d;' .
			'--eilmo-cf-variation-columns-tablet:%4$d;' .
			'--eilmo-cf-variation-columns-mobile:%5$d;' .
			'--eilmo-cf-group-columns-desktop:%6$d;' .
			'--eilmo-cf-group-columns-tablet:%7$d;' .
			'--eilmo-cf-group-columns-mobile:%8$d;' .
			'--eilmo-cf-group-image-ratio:%9$s;' .
			'--eilmo-cf-group-image-fit:%10$s;' .
			'--eilmo-cf-group-image-position:%11$s;',
			(int) $settings['desktop_summary_width'],
			(int) $settings['desktop_sticky_offset'],
			(int) $settings['variation_columns_desktop'],
			(int) $settings['variation_columns_tablet'],
			(int) $settings['variation_columns_mobile'],
			(int) $settings['group_columns_desktop'],
			(int) $settings['group_columns_tablet'],
			(int) $settings['group_columns_mobile'],
			$group_image_ratio,
			(string) $settings['group_image_fit'],
			(string) $settings['group_image_position']
		);

		$theme = CheckoutStyle::get();
		$overrides = isset( $settings['checkout_style_overrides'] ) && is_array( $settings['checkout_style_overrides'] )
			? $settings['checkout_style_overrides']
			: array();

		if ( ! empty( $overrides ) ) {
			$theme = array_replace_recursive( $theme, $overrides );
			$theme = CheckoutStyle::sanitize( $theme, CheckoutStyle::get() );
		}

		$inherit_theme = 'yes' === (string) ( $settings['checkout_style_inherit_theme'] ?? 'no' );

		return ( $inherit_theme ? '' : CheckoutStyle::get_css_variables( $theme ) ) .
			$layout_variables;
	}
}
