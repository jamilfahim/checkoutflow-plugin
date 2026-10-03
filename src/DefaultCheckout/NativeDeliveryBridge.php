<?php
/**
 * Eilmo Delivery bridge for native WooCommerce checkout.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\DefaultCheckout;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\DefaultCheckoutSettings;
use EilmoCheckout\Delivery\Services\DeliveryCalculator;
use WC_Cart;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/** Reuses Checkout Flow delivery methods on native WooCommerce checkout. */
final class NativeDeliveryBridge {

	/** Prevent duplicate Delivery UI when custom themes expose several fallback hooks. */
	private $controls_rendered = false;

	/** Register hooks. */
	public function register(): void {
		if ( ! DefaultCheckoutSettings::delivery_enabled() ) {
			return;
		}

		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_checkout_block' ), 15, 2 );
		add_action( 'wp', array( $this, 'reset_checkout_presence' ), 5 );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_controls' ), 20 );
		add_action( 'woocommerce_after_checkout_shipping_form', array( $this, 'render_controls' ), 20 );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'render_controls' ), 20 );
		add_action( 'woocommerce_checkout_before_order_review', array( $this, 'render_controls' ), 4 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync_session_from_review' ), 8 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_fragment' ), 20 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_delivery_fee' ), 35, 1 );
		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'filter_delivery_fee_html' ), 20, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 12, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_order_meta' ), 18, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
	}

	/** Render Classic checkout inside a Checkout Block page when Delivery is enabled. */
	public function render_checkout_block( string $content, array $block ): string {
		if ( is_admin() || ! $this->is_checkout_request() ) {
			return $content;
		}
		if ( false !== strpos( $content, 'woocommerce-checkout' ) || false !== strpos( $content, 'checkout woocommerce-checkout' ) ) {
			return $content;
		}
		static $rendering = false;
		if ( $rendering ) {
			return $content;
		}
		$rendering = true;
		$classic   = do_shortcode( '[woocommerce_checkout]' );
		$rendering = false;
		return '' !== trim( (string) $classic ) ? (string) $classic : $content;
	}

	/** Output native delivery controls. */
	public function render_controls(): void {
		if ( $this->controls_rendered || ! $this->is_checkout_request() ) {
			return;
		}
		$this->controls_rendered = true;
		$this->set_controls_active( true );
		echo $this->render_fragment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes its markup.
	}

	/** Refresh delivery markup whenever WooCommerce refreshes checkout fragments. */
	public function refresh_fragment( array $fragments ): array {
		$fragments['#eilmo-cf-native-delivery-slot'] = $this->render_fragment();
		return $fragments;
	}

	/** Sync selected delivery method from serialized checkout data. */
	public function sync_session_from_review( string $posted_data ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$data = array();
		parse_str( $posted_data, $data );
		if ( '1' !== sanitize_text_field( (string) ( $data['eilmo_cf_native_delivery_present'] ?? '' ) ) ) {
			$this->clear_delivery_session_state();
			return;
		}
		$this->set_controls_active( true );
		$method_id = sanitize_key( (string) ( $data['eilmo_cf_delivery_method'] ?? '' ) );
		if ( '' !== $method_id ) {
			WC()->session->set( 'eilmo_cf_native_delivery_method', $method_id );
		}
	}

	/** Add the selected Eilmo delivery charge as a WooCommerce fee. */
	public function apply_delivery_fee( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $this->controls_are_active() ) {
			return;
		}
		$calculation = $this->current_calculation( $cart );
		if ( empty( $calculation['valid'] ) ) {
			return;
		}
		$method_id = sanitize_key( (string) ( $calculation['method_id'] ?? '' ) );
		if ( '' !== $method_id && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'eilmo_cf_native_delivery_method', $method_id );
		}
		$charge  = max( 0.0, (float) ( $calculation['charge'] ?? 0 ) );
		$is_free = ! empty( $calculation['free_delivery_applied'] );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'eilmo_cf_native_delivery_is_free', $is_free ? 'yes' : 'no' );
		}

		/*
		 * Keep the checkout summary label stable. The chosen method remains
		 * available in order meta, while the summary always says Delivery charge.
		 * A zero-value fee is intentionally retained so FREE can still be shown.
		 */
		$cart->add_fee( __( 'Delivery charge', 'eilmo-checkout-flow' ), $charge, false );
	}

	/** Show FREE instead of a zero currency amount for the native delivery fee. */
	public function filter_delivery_fee_html( string $html, $fee ): string {
		$name = isset( $fee->name ) ? (string) $fee->name : '';
		if ( __( 'Delivery charge', 'eilmo-checkout-flow' ) !== $name ) {
			return $html;
		}
		$is_free = function_exists( 'WC' ) && WC()->session
			? 'yes' === (string) WC()->session->get( 'eilmo_cf_native_delivery_is_free', 'no' )
			: false;
		return $is_free ? esc_html__( 'FREE', 'eilmo-checkout-flow' ) : $html;
	}

	/** Validate selected delivery method server-side. */
	public function validate_checkout( array $data, WP_Error $errors ): void {
		if ( ! $this->is_checkout_request() || ! function_exists( 'WC' ) || ! WC()->cart || ! $this->controls_were_submitted() ) {
			return;
		}
		$calculation = $this->current_calculation( WC()->cart );
		if ( empty( $calculation['valid'] ) ) {
			$errors->add(
				'eilmo_cf_native_delivery',
				sanitize_text_field( (string) ( $calculation['message'] ?? __( 'Please select a valid delivery method.', 'eilmo-checkout-flow' ) ) )
			);
		}
	}

	/** Preserve delivery data using the existing Checkout Flow order-meta keys. */
	public function store_order_meta( WC_Order $order, array $data ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! $this->controls_were_submitted() ) {
			return;
		}
		$calculation = $this->current_calculation( WC()->cart );
		if ( empty( $calculation['valid'] ) ) {
			return;
		}
		$order->update_meta_data( '_eilmo_cf_delivery_method', sanitize_text_field( (string) ( $calculation['label'] ?? '' ) ) );
		$order->update_meta_data( '_eilmo_cf_delivery_method_id', sanitize_key( (string) ( $calculation['method_id'] ?? '' ) ) );
		$order->update_meta_data( '_eilmo_cf_delivery_charge', wc_format_decimal( (float) ( $calculation['charge'] ?? 0 ) ) );
		$order->update_meta_data( '_eilmo_cf_delivery_base_charge', wc_format_decimal( (float) ( $calculation['base_charge'] ?? 0 ) ) );
		$order->update_meta_data( '_eilmo_cf_free_delivery_applied', ! empty( $calculation['free_delivery_applied'] ) ? 'yes' : 'no' );
	}

	/** Native delivery uses the same isolated Default Checkout asset bundle as payment. */
	public function enqueue_assets(): void {
		if ( ! $this->is_checkout_request() ) {
			return;
		}
		$css_relative = 'src/css/frontend/default-checkout-native.css';
		$css_absolute = EILMO_CF_PATH . 'assets/' . $css_relative;
		if ( file_exists( $css_absolute ) ) {
			wp_enqueue_style( 'eilmo-cf-default-checkout-native', EILMO_CF_ASSETS_URL . $css_relative, array( 'woocommerce-general' ), (string) filemtime( $css_absolute ) );
		}
		$js_relative = 'src/js/frontend/default-checkout-native.js';
		$js_absolute = EILMO_CF_PATH . 'assets/' . $js_relative;
		if ( file_exists( $js_absolute ) && ! wp_script_is( 'eilmo-cf-default-checkout-native', 'enqueued' ) ) {
			wp_enqueue_script( 'eilmo-cf-default-checkout-native', EILMO_CF_ASSETS_URL . $js_relative, array( 'jquery', 'wc-checkout' ), (string) filemtime( $js_absolute ), true );
		}
	}

	/** Render native-prefixed Delivery markup without campaign renderer classes. */
	private function render_fragment(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '<div id="eilmo-cf-native-delivery-slot"></div>';
		}
		$basis = $this->cart_basis( WC()->cart );
		$calculator = new DeliveryCalculator();
		$methods = $calculator->get_available_methods(
			$basis,
			array(
				'source'       => 'native_checkout',
				'payment_type' => $this->current_payment_type(),
			)
		);
		$selected = $this->selected_method();
		if ( '' === $selected || ! $this->method_exists( $selected, $methods ) ) {
			$first = reset( $methods );
			$selected = is_array( $first ) ? sanitize_key( (string) ( $first['id'] ?? '' ) ) : '';
		}
		$settings = $this->delivery_settings();
		$display = \EilmoCheckout\Rendering\CheckoutDisplaySettings::get();
		$language = 'bn' === ( $display['language'] ?? 'en' ) ? 'bn' : 'en';
		$title = sanitize_text_field( \EilmoCheckout\Presentation\CheckoutLanguage::copy( (string) ( $settings['title'] ?? 'Delivery Method' ), $language ) );
		$required = 'yes' === (string) ( $settings['required'] ?? 'yes' );
		$show_description = 'yes' === (string) ( $settings['show_description'] ?? 'yes' );

		ob_start();
		?>
		<div id="eilmo-cf-native-delivery-slot" class="eilmo-cf-native-delivery-slot" data-eilmo-native-delivery-slot>
			<input type="hidden" name="eilmo_cf_native_delivery_present" value="1" data-eilmo-native-delivery-present>
			<section class="eilmo-cf-native-delivery" data-eilmo-native-delivery>
				<div class="eilmo-cf-native-control-label eilmo-cf-native-delivery-label" role="heading" aria-level="3"><?php echo esc_html( $title ); ?><?php if ( $required ) : ?> <span class="required" aria-hidden="true">*</span><?php endif; ?></div>
				<?php if ( empty( $methods ) ) : ?>
					<p class="eilmo-cf-native-empty"><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'No delivery methods are currently available.', $language ) ); ?></p>
				<?php else : ?>
					<div class="eilmo-cf-native-delivery-grid" role="radiogroup" aria-label="<?php echo esc_attr( $title ); ?>">
						<?php foreach ( $methods as $method ) : ?>
							<?php if ( ! is_array( $method ) ) { continue; } ?>
							<?php
							$id = sanitize_key( (string) ( $method['id'] ?? '' ) );
							$label = sanitize_text_field( \EilmoCheckout\Presentation\CheckoutLanguage::localized_field( $method, 'label', $language ) );
							if ( '' === $id || '' === $label ) { continue; }
							$charge = max( 0.0, (float) ( $method['charge'] ?? 0 ) );
							$description = sanitize_text_field( \EilmoCheckout\Presentation\CheckoutLanguage::localized_field( $method, 'description', $language ) );
							$is_selected = $id === $selected;
							?>
							<label class="eilmo-cf-native-delivery-card<?php echo $is_selected ? ' is-selected' : ''; ?>" data-eilmo-native-delivery-card="<?php echo esc_attr( $id ); ?>">
								<input type="radio" class="eilmo-cf-native-visually-hidden" name="eilmo_cf_delivery_method" value="<?php echo esc_attr( $id ); ?>" <?php checked( $is_selected ); ?> data-eilmo-native-delivery-input>
								<span class="eilmo-cf-native-delivery-copy"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $show_description && '' !== $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?></span>
								<span class="eilmo-cf-native-delivery-price"><?php echo 0.0 >= $charge ? esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Free', $language ) ) : wp_kses_post( wc_price( $charge ) ); ?></span>
								<span class="eilmo-cf-native-check" aria-hidden="true">✓</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Reset per-page presence so hidden/custom templates cannot leave stale delivery fees active. */
	public function reset_checkout_presence(): void {
		if ( ! $this->is_checkout_request() ) {
			return;
		}
		$this->controls_rendered = false;
		$this->set_controls_active( false );
	}

	/** Whether the final checkout POST explicitly contains the delivery controls marker. */
	private function controls_were_submitted(): bool {
		$value = isset( $_POST['eilmo_cf_native_delivery_present'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['eilmo_cf_native_delivery_present'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return '1' === $value;
	}

	/** Track whether the Eilmo delivery selector is actually mounted on this checkout. */
	private function set_controls_active( bool $active ): void {
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			WC()->session->set( 'eilmo_cf_native_delivery_active', $active ? 'yes' : 'no' );
		}
	}

	/** Whether delivery fees may currently be applied. */
	private function controls_are_active(): bool {
		return function_exists( 'WC' ) && WC() && WC()->session
			? 'yes' === (string) WC()->session->get( 'eilmo_cf_native_delivery_active', 'no' )
			: false;
	}

	/** Clear only Eilmo-owned delivery session state. */
	private function clear_delivery_session_state(): void {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}
		WC()->session->set( 'eilmo_cf_native_delivery_active', 'no' );
		WC()->session->set( 'eilmo_cf_native_delivery_method', '' );
		WC()->session->set( 'eilmo_cf_native_delivery_is_free', 'no' );
	}

	/** Current Delivery settings merged with defaults. */
	private function delivery_settings(): array {
		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$merged = array_replace_recursive( $defaults, is_array( $stored ) ? $stored : array() );
		return isset( $merged['delivery'] ) && is_array( $merged['delivery'] ) ? $merged['delivery'] : array();
	}

	/** Whether a selected method still exists in the current available list. */
	private function method_exists( string $method_id, array $methods ): bool {
		foreach ( $methods as $method ) {
			if ( is_array( $method ) && $method_id === sanitize_key( (string) ( $method['id'] ?? '' ) ) ) {
				return true;
			}
		}
		return false;
	}


	/** Calculate current server-authoritative delivery selection. */
	private function current_calculation( WC_Cart $cart ): array {
		$calculator = new DeliveryCalculator();
		return $calculator->calculate(
			$this->selected_method(),
			$this->cart_basis( $cart ),
			array(
				'source'       => 'native_checkout',
				'payment_type' => $this->current_payment_type(),
			)
		);
	}

	/** Product total used by delivery and Special Discount calculations. */
	private function cart_basis( WC_Cart $cart ): float {
		$value = (float) $cart->get_cart_contents_total();
		if ( $value <= 0 ) {
			$value = max( 0.0, (float) $cart->get_subtotal() - (float) $cart->get_discount_total() );
		}
		return max( 0.0, $value );
	}

	/** Current Payment Option from the Eilmo native checkout session. */
	private function current_payment_type(): string {
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			return sanitize_key( (string) WC()->session->get( 'eilmo_cf_native_payment_type', '' ) );
		}
		return '';
	}

	/** Current selected method from session. */
	private function selected_method(): string {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return sanitize_key( (string) WC()->session->get( 'eilmo_cf_native_delivery_method', '' ) );
		}
		return '';
	}

	/** Whether this is a checkout frontend request. */
	private function is_checkout_request(): bool {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' );
	}
}
