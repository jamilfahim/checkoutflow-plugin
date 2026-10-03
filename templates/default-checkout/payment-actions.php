<?php
/**
 * Native WooCommerce checkout payment actions for Eilmo Default Checkout.
 *
 * This template intentionally leaves gateway radios/payment fields to the
 * left-side Eilmo native adapter. WooCommerce still owns terms, nonce and the
 * real Place Order button/process lifecycle.
 *
 * @package EilmoCheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}

$eilmo_button_element_class = function_exists( 'wc_wp_theme_get_element_class_name' ) ? (string) wc_wp_theme_get_element_class_name( 'button' ) : '';
$order_button_text = isset( $order_button_text ) && is_scalar( $order_button_text ) && '' !== trim( (string) $order_button_text )
	? (string) $order_button_text
	: (string) apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
?>
<div id="payment" class="woocommerce-checkout-payment eilmo-cf-native-payment-actions">
	<div class="form-row place-order">
		<noscript>
			<?php
			printf(
				esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ),
				'<em>',
				'</em>'
			);
			?>
			<br/>
			<button type="submit" class="button alt<?php echo esc_attr( $eilmo_button_element_class ? ' ' . $eilmo_button_element_class : '' ); ?>" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php
		echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'woocommerce_order_button_html',
			'<button type="submit" class="button alt' . esc_attr( $eilmo_button_element_class ? ' ' . $eilmo_button_element_class : '' ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>'
		);
		?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
