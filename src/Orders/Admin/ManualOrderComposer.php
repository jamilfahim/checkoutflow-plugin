<?php
/**
 * Checkout Flow options for orders created in WooCommerce admin.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Admin;

use EilmoCheckout\AdvancePayment\Services\AdvanceCalculator;
use EilmoCheckout\Payment\Gateways\GatewaySettings;
use EilmoCheckout\Payment\Services\PaymentProof;
use WC_Order;
use WC_Order_Item_Fee;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an explicit, repeatable admin path for Checkout Flow order pricing.
 * Customer checkout orders retain their own pricing and are never edited here.
 */
final class ManualOrderComposer {
	private const META_ADMIN = '_eilmo_cf_admin_manual';
	private const META_FEE = '_eilmo_cf_admin_fee_type';
	private const META_CREATED_VIA = '_eilmo_cf_created_via';
	private const NONCE_ACTION = 'eilmo_cf_admin_order';

	/** Register on both the HPOS and legacy order editors. */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 25, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 60, 2 );
		add_action( 'wp_ajax_eilmo_cf_admin_order_preview', array( $this, 'preview' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Register a composer only for ordinary/admin-created orders. */
	public function register_meta_box( $screen_id = '', $object = null ): void {
		unset( $screen_id );
		$order = $this->resolve_order( $object );
		if ( ! $order || ! $this->can_compose( $order ) ) {
			return;
		}
		$screen = function_exists( 'wc_get_page_screen_id' ) && $this->hpos_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box(
			'eilmo-cf-admin-order-composer',
			__( 'Checkout Flow Details', 'eilmo-checkout-flow' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'normal',
			'default'
		);
	}

	/** Render the order-specific controls and the last saved calculation. */
	public function render_meta_box( $object ): void {
		$order = $this->resolve_order( $object );
		if ( ! $order ) {
			return;
		}
		$payment_type = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) );
		$advance_override = 'yes' === (string) $order->get_meta( '_eilmo_cf_admin_advance_override', true );
		$advance_amount = 'advance' === $payment_type ? $this->amount( $order->get_meta( '_eilmo_cf_pay_now', true ) ) : '';
		$delivery_amount = $this->amount( $order->get_meta( '_eilmo_cf_delivery_charge', true ) );
		$discount_amount = 'yes' === (string) $order->get_meta( self::META_ADMIN, true )
			? $this->amount( $order->get_meta( '_eilmo_cf_admin_discount', true ) )
			: 0.0;
		if ( 'yes' === (string) $order->get_meta( self::META_ADMIN, true ) && ! $order->meta_exists( '_eilmo_cf_admin_discount' ) ) {
			$discount_amount = abs( $this->owned_fee_total( $order, 'special_discount' ) ) + abs( $this->owned_fee_total( $order, 'full_discount' ) );
		}
		$gateway_id = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_gateway_id', true ) );
		$transaction_id = sanitize_text_field( (string) $order->get_meta( '_eilmo_cf_payment_transaction_id', true ) );
		$gateways = $this->gateways();
		wp_nonce_field( self::NONCE_ACTION, 'eilmo_cf_admin_order_nonce' );
		?>
		<div id="eilmo-cf-admin-order" data-order-id="<?php echo esc_attr( (string) $order->get_id() ); ?>">
			<p><?php esc_html_e( 'Add products with WooCommerce first. Choose Checkout Flow options, then Preview or Update the order. WooCommerce Add coupon remains available for coupons.', 'eilmo-checkout-flow' ); ?></p>
			<table class="form-table"><tbody>
			<tr><th><label for="eilmo-cf-admin-payment-type"><?php esc_html_e( 'Payment option', 'eilmo-checkout-flow' ); ?></label></th><td>
			<select id="eilmo-cf-admin-payment-type" name="eilmo_cf_admin_payment_type">
				<option value=""><?php esc_html_e( 'Leave order unchanged', 'eilmo-checkout-flow' ); ?></option>
				<option value="cash_on_delivery" <?php selected( $payment_type, 'cash_on_delivery' ); ?>><?php esc_html_e( 'Cash on delivery', 'eilmo-checkout-flow' ); ?></option>
				<option value="advance" <?php selected( $payment_type, 'advance' ); ?>><?php esc_html_e( 'Advance Payment', 'eilmo-checkout-flow' ); ?></option>
				<option value="full" <?php selected( $payment_type, 'full' ); ?>><?php esc_html_e( 'Full Payment', 'eilmo-checkout-flow' ); ?></option>
			</select></td></tr>
			<tr id="eilmo-cf-admin-advance-row" <?php echo 'advance' === $payment_type ? '' : 'hidden'; ?>><th><label for="eilmo-cf-admin-advance-amount"><?php esc_html_e( 'Advance amount', 'eilmo-checkout-flow' ); ?></label></th><td><input type="number" min="0.01" step="0.01" class="small-text" id="eilmo-cf-admin-advance-amount" name="eilmo_cf_admin_advance_amount" value="<?php echo esc_attr( (string) $advance_amount ); ?>"><input type="hidden" id="eilmo-cf-admin-advance-override" name="eilmo_cf_admin_advance_override" value="<?php echo $advance_override ? 'yes' : 'no'; ?>"> <button type="button" class="button" id="eilmo-cf-admin-advance-default"><?php esc_html_e( 'Use plugin default', 'eilmo-checkout-flow' ); ?></button><span id="eilmo-cf-admin-advance-status" aria-live="polite"></span><p class="description"><?php esc_html_e( 'The plugin rule supplies the initial amount. Edit this field to use a custom advance amount.', 'eilmo-checkout-flow' ); ?></p></td></tr>
			<tr><th><label for="eilmo-cf-admin-delivery-amount"><?php esc_html_e( 'Delivery charge', 'eilmo-checkout-flow' ); ?></label></th><td><input type="number" min="0" step="0.01" class="small-text" id="eilmo-cf-admin-delivery-amount" name="eilmo_cf_admin_delivery_amount" value="<?php echo esc_attr( (string) $delivery_amount ); ?>"><p class="description"><?php esc_html_e( 'Enter 0 for Free Delivery.', 'eilmo-checkout-flow' ); ?></p></td></tr>
			<tr><th><label for="eilmo-cf-admin-discount-amount"><?php esc_html_e( 'Discount', 'eilmo-checkout-flow' ); ?></label></th><td><input type="number" min="0" step="0.01" class="small-text" id="eilmo-cf-admin-discount-amount" name="eilmo_cf_admin_discount_amount" value="<?php echo esc_attr( (string) $discount_amount ); ?>"><p class="description"><?php esc_html_e( 'Enter the discount amount manually. WooCommerce coupons can still be added separately.', 'eilmo-checkout-flow' ); ?></p></td></tr>
			<tr><th><label for="eilmo-cf-admin-gateway"><?php esc_html_e( 'Payment method', 'eilmo-checkout-flow' ); ?></label></th><td>
			<select id="eilmo-cf-admin-gateway" name="eilmo_cf_admin_gateway_id">
				<option value="manual" <?php selected( $gateway_id, 'manual' ); ?>><?php esc_html_e( 'Manual payment / bank transfer', 'eilmo-checkout-flow' ); ?></option>
					<?php foreach ( $gateways as $id => $gateway ) : if ( 'cod' === $id ) { continue; } ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $gateway_id, $id ); ?>><?php echo esc_html( wp_strip_all_tags( $gateway->get_title() ) ); ?></option>
				<?php endforeach; ?>
			</select><p class="description"><?php esc_html_e( 'Selecting a method does not charge the customer or mark the order paid.', 'eilmo-checkout-flow' ); ?></p></td></tr>
			<tr><th><label for="eilmo-cf-admin-transaction"><?php esc_html_e( 'Transaction ID', 'eilmo-checkout-flow' ); ?></label></th><td><input type="text" class="regular-text" id="eilmo-cf-admin-transaction" name="eilmo_cf_admin_transaction_id" value="<?php echo esc_attr( $transaction_id ); ?>"></td></tr>
			<tr><th><label for="eilmo-cf-admin-proof"><?php esc_html_e( 'Payment screenshot', 'eilmo-checkout-flow' ); ?></label></th><td><input type="file" id="eilmo-cf-admin-proof" accept="image/jpeg,image/png,image/webp"><input type="hidden" id="eilmo-cf-admin-proof-token" name="eilmo_cf_admin_proof_token" value=""><span id="eilmo-cf-admin-proof-status" aria-live="polite"></span><p class="description"><?php esc_html_e( 'For enabled bKash or Nagad methods. The private upload is attached when the order is updated.', 'eilmo-checkout-flow' ); ?></p></td></tr>
			</tbody></table>
			<p><button type="button" class="button" id="eilmo-cf-admin-preview-button"><?php esc_html_e( 'Preview calculation', 'eilmo-checkout-flow' ); ?></button></p>
			<div id="eilmo-cf-admin-preview" aria-live="polite"></div>
			<?php if ( 'yes' === (string) $order->get_meta( self::META_ADMIN, true ) ) : ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Saved total: %1$s · Pay now: %2$s · Remaining due: %3$s. Payment is not verified automatically.', 'eilmo-checkout-flow' ), wp_strip_all_tags( wc_price( (float) $order->get_total() ) ), wp_strip_all_tags( wc_price( (float) $order->get_meta( '_eilmo_cf_pay_now', true ) ) ), wp_strip_all_tags( wc_price( (float) $order->get_meta( '_eilmo_cf_remaining_due', true ) ) ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Load a small script only on an order edit/new screen. */
	public function enqueue_assets( $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id = $screen ? (string) $screen->id : '';
		if ( ! in_array( $id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		$file = EILMO_CF_PATH . 'assets/src/js/admin/manual-order.js';
		if ( ! file_exists( $file ) ) { return; }
		wp_enqueue_script( 'eilmo-cf-admin-manual-order', EILMO_CF_ASSETS_URL . 'src/js/admin/manual-order.js', array(), (string) filemtime( $file ), true );
		wp_add_inline_script( 'eilmo-cf-admin-manual-order', 'window.eilmoCfAdminOrder=' . wp_json_encode( array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'previewNonce' => wp_create_nonce( self::NONCE_ACTION ),
			'proofNonce' => wp_create_nonce( 'eilmo_cf_payment_proof' ),
			'uploading' => __( 'Uploading screenshot…', 'eilmo-checkout-flow' ),
			'uploadFailed' => __( 'Screenshot upload failed.', 'eilmo-checkout-flow' ),
		) ) . ';', 'before' );
	}

	/** Preview from the saved WooCommerce items without mutating the order. */
	public function preview(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
		if ( ! $order instanceof WC_Order || ! $this->can_edit( $order ) || ! $this->can_compose( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'This order cannot be edited.', 'eilmo-checkout-flow' ) ), 403 );
		}
		$result = $this->calculate( $order, $this->read_input( $_POST ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$labels = array( 'products' => __( 'Products', 'eilmo-checkout-flow' ), 'coupon_discount' => __( 'WooCommerce product discounts', 'eilmo-checkout-flow' ), 'discount' => __( 'Discount', 'eilmo-checkout-flow' ), 'delivery' => __( 'Delivery charge', 'eilmo-checkout-flow' ), 'total' => __( 'Order total', 'eilmo-checkout-flow' ), 'pay_now' => __( 'Pay now', 'eilmo-checkout-flow' ), 'remaining_due' => __( 'Remaining due', 'eilmo-checkout-flow' ) );
		$display = array();
		foreach ( $labels as $key => $label ) {
			$display[] = $label . ': ' . ( 'delivery' === $key && 0.0 === $result['delivery'] ? __( 'Free', 'eilmo-checkout-flow' ) : wp_strip_all_tags( wc_price( $result[ $key ] ) ) );
		}
		wp_send_json_success( array( 'lines' => $display, 'defaultAdvance' => $result['default_advance'] ) );
	}

	/** Apply or replace only fee rows owned by this admin integration. */
	public function save( $order_id, $object = null ): void {
		unset( $object );
		if ( empty( $_POST['eilmo_cf_admin_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['eilmo_cf_admin_order_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order instanceof WC_Order || ! $this->can_edit( $order ) || ! $this->can_compose( $order ) ) {
			return;
		}
		$input = $this->read_input( $_POST );
		if ( '' === $input['payment_type'] ) { return; }
		if ( $order->get_date_paid() ) {
			$this->error( __( 'Checkout Flow amounts cannot be recalculated after the order is marked paid.', 'eilmo-checkout-flow' ) );
			return;
		}
		$result = $this->calculate( $order, $input );
		if ( is_wp_error( $result ) ) {
			$this->error( $result->get_error_message() );
			return;
		}
		$method_key = GatewaySettings::method_key( $input['gateway_id'] );
		if ( '' !== $input['proof_token'] && ( '' === $method_key || ! PaymentProof::is_valid_token( $input['proof_token'], $method_key ) ) ) {
			$this->error( __( 'The payment screenshot expired or does not match the selected method. Upload it again.', 'eilmo-checkout-flow' ) );
			return;
		}

		$this->remove_own_fees( $order );
		$this->add_fee( $order, 'delivery', 0.0 === $result['delivery'] ? __( 'Free Delivery', 'eilmo-checkout-flow' ) : __( 'Delivery charge', 'eilmo-checkout-flow' ), $result['delivery'], true );
		$this->add_fee( $order, 'discount', __( 'Discount', 'eilmo-checkout-flow' ), -$result['discount'] );
		$order->calculate_totals( false );
		$result['discount'] = abs( $this->owned_fee_total( $order, 'discount' ) );
		$result['delivery'] = $this->owned_fee_total( $order, 'delivery' );
		$total = $this->amount( $order->get_total() );
		$payment = 'advance' === $input['payment_type'] && $input['advance_override']
			? $this->manual_advance_amounts( $this->amount( $input['advance_amount'] ), $total )
			: $this->payment_amounts( $result, $input['payment_type'], $total );
		if ( 'cash_on_delivery' === $input['payment_type'] ) {
			$gateway = $this->gateways()['cod'] ?? null;
			if ( $gateway ) { $order->set_payment_method( $gateway ); }
			else { $order->set_payment_method( 'cod' ); $order->set_payment_method_title( __( 'Cash on delivery', 'eilmo-checkout-flow' ) ); }
			$input['gateway_id'] = 'cod';
		} elseif ( 'manual' === $input['gateway_id'] ) {
			$order->set_payment_method( '' );
			$order->set_payment_method_title( __( 'Manual payment', 'eilmo-checkout-flow' ) );
		} else {
			$order->set_payment_method( $this->gateways()[ $input['gateway_id'] ] );
		}

		$order->update_meta_data( self::META_ADMIN, 'yes' );
		$order->update_meta_data( '_eilmo_cf_product_total', $result['products'] );
		$order->update_meta_data( '_eilmo_cf_coupon_discount', $result['coupon_discount'] );
		$order->update_meta_data( '_eilmo_cf_automatic_discount', 0 );
		$order->update_meta_data( '_eilmo_cf_admin_discount', $result['discount'] );
		$order->update_meta_data( '_eilmo_cf_admin_apply_special', 'no' );
		$order->update_meta_data( '_eilmo_cf_delivery_method_id', $result['delivery_id'] );
		$order->update_meta_data( '_eilmo_cf_delivery_method', $result['delivery_label'] );
		$order->update_meta_data( '_eilmo_cf_delivery_base_charge', $result['delivery_base'] );
		$order->update_meta_data( '_eilmo_cf_delivery_charge', $result['delivery'] );
		$order->update_meta_data( '_eilmo_cf_full_payment_discount', 0 );
		$order->update_meta_data( '_eilmo_cf_grand_total', $total );
		$order->update_meta_data( '_eilmo_cf_payment_type', $input['payment_type'] );
		$order->update_meta_data( '_eilmo_cf_advance_amount', $payment['advance_amount'] );
		$order->update_meta_data( '_eilmo_cf_admin_advance_override', 'advance' === $input['payment_type'] && $input['advance_override'] ? 'yes' : 'no' );
		$order->update_meta_data( '_eilmo_cf_advance_rule_id', $payment['rule_id'] );
		$order->update_meta_data( '_eilmo_cf_advance_rule_type', $payment['rule_type'] );
		$order->update_meta_data( '_eilmo_cf_pay_now', $payment['pay_now'] );
		$order->update_meta_data( '_eilmo_cf_remaining_due', $payment['remaining_due'] );
		$order->update_meta_data( '_eilmo_cf_payment_method', 'cash_on_delivery' === $input['payment_type'] ? 'wc__cod' : $input['gateway_id'] );
		$order->update_meta_data( '_eilmo_cf_payment_method_key', 'cash_on_delivery' === $input['payment_type'] ? 'cod' : ( $method_key ?: $input['gateway_id'] ) );
		$order->update_meta_data( '_eilmo_cf_payment_gateway_id', $input['gateway_id'] );
		$order->update_meta_data( '_eilmo_cf_payment_source', 'manual' === $input['gateway_id'] ? 'eilmo_manual' : ( GatewaySettings::is_eilmo_gateway( $input['gateway_id'] ) ? 'eilmo_gateway' : 'woocommerce_gateway' ) );
		$order->update_meta_data( '_eilmo_cf_payment_transaction_id', $input['transaction_id'] );
		$has_proof = '' !== $input['proof_token'] || ( '' !== $method_key && PaymentProof::is_attached_to_order( $order, $method_key ) );
		$order->update_meta_data( '_eilmo_cf_payment_status', 'cash_on_delivery' === $input['payment_type'] ? 'pay_on_delivery' : ( '' !== $input['transaction_id'] || $has_proof ? 'awaiting_verification' : 'unpaid' ) );
		$order->save();
		if ( '' !== $input['proof_token'] && ! PaymentProof::assign_to_order( $order, $input['proof_token'], $method_key ) ) {
			$this->error( __( 'The order was saved, but the payment screenshot could not be attached. Verify the transaction before approving payment.', 'eilmo-checkout-flow' ) );
		}
	}

	/** Validate the admin choices and calculate a non-mutating breakdown. */
	private function calculate( WC_Order $order, array $input ) {
		if ( ! in_array( $input['payment_type'], array( 'cash_on_delivery', 'advance', 'full' ), true ) ) {
			return new WP_Error( 'invalid_payment_type', __( 'Choose a payment option first.', 'eilmo-checkout-flow' ) );
		}
		$context = $this->product_context( $order );
		if ( $context['products'] <= 0 ) {
			return new WP_Error( 'empty_order', __( 'Add products to the order before applying Checkout Flow details.', 'eilmo-checkout-flow' ) );
		}
		if ( 'cash_on_delivery' !== $input['payment_type'] && ( 'cod' === $input['gateway_id'] || ( ! isset( $this->gateways()[ $input['gateway_id'] ] ) && 'manual' !== $input['gateway_id'] ) ) ) {
			return new WP_Error( 'invalid_gateway', __( 'Choose an enabled payment method.', 'eilmo-checkout-flow' ) );
		}
		foreach ( array( 'delivery_amount', 'discount_amount' ) as $field ) {
			if ( ! is_numeric( $input[ $field ] ) || ! is_finite( (float) $input[ $field ] ) || (float) $input[ $field ] < 0 ) {
				return new WP_Error( 'invalid_amount', __( 'Delivery charge and discount must be valid amounts of 0 or more.', 'eilmo-checkout-flow' ) );
			}
		}
		$context['payment_type'] = $input['payment_type'];
		$context['source'] = 'admin_order';
		$base_total = $this->base_total( $order );
		$base_pre_tax = $this->base_pre_tax_total( $order );
		$delivery_charge = $this->amount( $input['delivery_amount'] );
		$discount = $this->amount( $input['discount_amount'] );
		if ( $discount > $base_pre_tax + $delivery_charge ) {
			return new WP_Error( 'discount_too_large', __( 'Discount cannot exceed the order amount before tax plus delivery.', 'eilmo-checkout-flow' ) );
		}
		$context['discounted_product_total'] = $this->amount( max( 0, $context['discounted_product_total'] - $discount ) );
		$total = $this->amount( max( 0, $base_total + $delivery_charge - $discount ) );
		$payment_context = array( 'products' => $context['products'], 'discounted_product_total' => $context['discounted_product_total'], 'delivery' => $delivery_charge );
		$default_payment = $this->payment_amounts( $payment_context, $input['payment_type'], $total );
		$payment = $default_payment;
		if ( 'advance' === $input['payment_type'] && ! empty( $input['advance_override'] ) ) {
			$raw_advance = $input['advance_amount'];
			if ( ! is_numeric( $raw_advance ) || ! is_finite( (float) $raw_advance ) || (float) $raw_advance <= 0 ) {
				return new WP_Error( 'invalid_advance', __( 'Enter a valid advance amount greater than 0.', 'eilmo-checkout-flow' ) );
			}
			$advance = $this->amount( $raw_advance );
			if ( $advance <= 0 || $advance > $total ) {
				return new WP_Error( 'advance_exceeds_total', __( 'Advance amount must be greater than 0 and cannot exceed the order total.', 'eilmo-checkout-flow' ) );
			}
			$payment = $this->manual_advance_amounts( $advance, $total );
		}
		if ( 'advance' === $input['payment_type'] && empty( $payment['matched'] ) ) {
			return new WP_Error( 'advance_unavailable', __( 'No advance-payment rule applies to these products. Enter a custom advance amount or adjust the plugin rules.', 'eilmo-checkout-flow' ) );
		}
		return array(
			'products' => $context['products'],
			'coupon_discount' => $context['coupon_discount'],
			'discount' => $discount,
			'delivery' => $delivery_charge,
			'delivery_base' => $delivery_charge,
			'delivery_id' => 'manual',
			'delivery_label' => 0.0 === $delivery_charge ? __( 'Free Delivery', 'eilmo-checkout-flow' ) : __( 'Delivery charge', 'eilmo-checkout-flow' ),
			'discounted_product_total' => $context['discounted_product_total'],
			'total' => $total,
			'pay_now' => $payment['pay_now'],
			'remaining_due' => $payment['remaining_due'],
			'default_advance' => 'advance' === $input['payment_type'] && ! empty( $default_payment['matched'] ) ? $default_payment['pay_now'] : null,
		);
	}

	/** Build rule context from saved WooCommerce product lines. */
	private function product_context( WC_Order $order ): array {
		$subtotal = 0.0; $discounted = 0.0; $quantity = 0.0; $quantities = array(); $totals = array(); $ids = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = absint( $item->get_product_id() );
			$variation_id = absint( $item->get_variation_id() );
			$qty = max( 0, (float) $item->get_quantity() );
			$line = $this->amount( $item->get_total() );
			$subtotal += $this->amount( $item->get_subtotal() ); $discounted += $line; $quantity += $qty;
			foreach ( array_unique( array_filter( array( $product_id, $variation_id ) ) ) as $id ) {
				$ids[] = $id;
				$quantities[ $id ] = ( $quantities[ $id ] ?? 0 ) + $qty;
				$totals[ $id ] = ( $totals[ $id ] ?? 0 ) + $line;
			}
		}
		return array(
			'products' => $this->amount( $subtotal ),
			'discounted_product_total' => $this->amount( $discounted ),
			'coupon_discount' => $this->amount( max( 0, $subtotal - $discounted ) ),
			'product_total' => $this->amount( $discounted ),
			'cart_quantity' => $quantity,
			'product_quantities' => $quantities,
			'product_totals' => $totals,
			'product_ids' => array_values( array_unique( $ids ) ),
			'current_checkout_product_ids' => array_values( array_unique( $ids ) ),
		);
	}

	/** Sum the authoritative stored items, excluding this composer's old fees. */
	private function base_total( WC_Order $order ): float {
		$total = 0.0;
		foreach ( array( 'line_item', 'shipping', 'fee' ) as $type ) {
			foreach ( $order->get_items( $type ) as $item ) {
				if ( 'fee' === $type && '' !== (string) $item->get_meta( self::META_FEE, true ) ) { continue; }
				$total += (float) $item->get_total();
				if ( method_exists( $item, 'get_total_tax' ) ) { $total += (float) $item->get_total_tax(); }
			}
		}
		return $this->amount( max( 0, $total ) );
	}

	/** WooCommerce limits negative fees to the total before tax. */
	private function base_pre_tax_total( WC_Order $order ): float {
		$total = 0.0;
		foreach ( array( 'line_item', 'shipping', 'fee' ) as $type ) {
			foreach ( $order->get_items( $type ) as $item ) {
				if ( 'fee' === $type && '' !== (string) $item->get_meta( self::META_FEE, true ) ) { continue; }
				$total += (float) $item->get_total();
			}
		}
		return $this->amount( max( 0, $total ) );
	}

	/** Reuse the configured advance-payment tiers for admin orders. */
	private function payment_amounts( array $result, string $type, float $total ): array {
		if ( 'cash_on_delivery' === $type ) {
			return array( 'pay_now' => 0.0, 'remaining_due' => $total, 'advance_amount' => 0.0, 'rule_id' => '', 'rule_type' => '', 'matched' => true );
		}
		if ( 'full' === $type ) {
			return array( 'pay_now' => $total, 'remaining_due' => 0.0, 'advance_amount' => 0.0, 'rule_id' => '', 'rule_type' => '', 'matched' => true );
		}
		$calculation = ( new AdvanceCalculator() )->calculate( array(
			'product_total' => $result['products'],
			'discounted_product_total' => $result['discounted_product_total'],
			'delivery_charge' => $result['delivery'],
			'grand_total' => $total,
			'payment_type' => 'advance',
		) );
		$pay_now = $this->amount( min( $total, max( 0, (float) ( $calculation['pay_now'] ?? $total ) ) ) );
		return array(
			'pay_now' => $pay_now,
			'remaining_due' => $this->amount( max( 0, $total - $pay_now ) ),
			'advance_amount' => $this->amount( $calculation['advance_amount'] ?? 0 ),
			'rule_id' => sanitize_key( (string) ( $calculation['rule_id'] ?? '' ) ),
			'rule_type' => sanitize_key( (string) ( $calculation['rule_type'] ?? '' ) ),
			'matched' => ! empty( $calculation['is_advance_payment'] ) && ! empty( $calculation['matched'] ),
		);
	}

	/** Keep custom advances distinct from configured rule matches. */
	private function manual_advance_amounts( float $advance, float $total ): array {
		return array(
			'pay_now' => $advance,
			'remaining_due' => $this->amount( $total - $advance ),
			'advance_amount' => $advance,
			'rule_id' => '',
			'rule_type' => 'admin_manual',
			'matched' => true,
		);
	}

	private function add_fee( WC_Order $order, string $type, string $label, float $amount, bool $allow_zero = false ): void {
		if ( ! $allow_zero && abs( $amount ) < 0.00001 ) { return; }
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( $label );
		$fee->set_amount( $amount );
		$fee->set_total( $amount );
		$fee->set_tax_status( 'none' );
		$fee->add_meta_data( self::META_FEE, $type, true );
		$order->add_item( $fee );
	}

	private function remove_own_fees( WC_Order $order ): void {
		foreach ( $order->get_items( 'fee' ) as $item ) {
			if ( '' !== (string) $item->get_meta( self::META_FEE, true ) ) { $order->remove_item( $item->get_id() ); }
		}
	}

	private function owned_fee_total( WC_Order $order, string $type ): float {
		$total = 0.0;
		foreach ( $order->get_items( 'fee' ) as $item ) {
			if ( $type === (string) $item->get_meta( self::META_FEE, true ) ) { $total += (float) $item->get_total(); }
		}
		return round( $total, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
	}

	private function read_input( array $posted ): array {
		return array(
			'payment_type' => sanitize_key( (string) wp_unslash( $posted['eilmo_cf_admin_payment_type'] ?? '' ) ),
			'delivery_amount' => sanitize_text_field( (string) wp_unslash( $posted['eilmo_cf_admin_delivery_amount'] ?? '0' ) ),
			'discount_amount' => sanitize_text_field( (string) wp_unslash( $posted['eilmo_cf_admin_discount_amount'] ?? '0' ) ),
			'advance_amount' => sanitize_text_field( (string) wp_unslash( $posted['eilmo_cf_admin_advance_amount'] ?? '' ) ),
			'advance_override' => 'yes' === sanitize_key( (string) wp_unslash( $posted['eilmo_cf_admin_advance_override'] ?? 'no' ) ),
			'gateway_id' => sanitize_key( (string) wp_unslash( $posted['eilmo_cf_admin_gateway_id'] ?? 'manual' ) ),
			'transaction_id' => sanitize_text_field( (string) wp_unslash( $posted['eilmo_cf_admin_transaction_id'] ?? '' ) ),
			'proof_token' => sanitize_text_field( (string) wp_unslash( $posted['eilmo_cf_admin_proof_token'] ?? '' ) ),
		);
	}

	private function gateways(): array {
		$available = function_exists( 'WC' ) && WC() && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$enabled = array();
		foreach ( $available as $id => $gateway ) {
			if ( is_object( $gateway ) && 'yes' === (string) ( $gateway->enabled ?? 'no' ) ) { $enabled[ sanitize_key( (string) $id ) ] = $gateway; }
		}
		return $enabled;
	}

	private function resolve_order( $object ): ?WC_Order {
		if ( $object instanceof WC_Order ) { return $object; }
		$id = is_object( $object ) && isset( $object->ID ) ? absint( $object->ID ) : 0;
		$order = $id ? wc_get_order( $id ) : false;
		return $order instanceof WC_Order ? $order : null;
	}

	private function can_compose( WC_Order $order ): bool {
		$source = (string) $order->get_meta( self::META_CREATED_VIA, true );
		return '' === $source || 'yes' === (string) $order->get_meta( self::META_ADMIN, true );
	}

	private function can_edit( WC_Order $order ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		$post_type = get_post_type_object( 'shop_order' );
		return $post_type && current_user_can( $post_type->cap->edit_post, $order->get_id() );
	}

	private function hpos_enabled(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private function amount( $value ): float {
		return round( max( 0, (float) $value ), function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
	}

	private function error( string $message ): void {
		if ( class_exists( '\\WC_Admin_Meta_Boxes' ) ) { \WC_Admin_Meta_Boxes::add_error( $message ); }
	}
}
