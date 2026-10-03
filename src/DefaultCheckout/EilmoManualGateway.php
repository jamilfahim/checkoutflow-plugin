<?php
/**
 * WooCommerce bridge gateway for Eilmo manual checkout options.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\DefaultCheckout;

use EilmoCheckout\Admin\DefaultCheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets WooCommerce own the native order lifecycle while Eilmo owns the
 * Cash / Advance / Full selection and configured manual payment details.
 */
final class EilmoManualGateway extends \WC_Payment_Gateway {

	/** Configure the bridge gateway. */
	public function __construct() {
		$this->id                 = 'eilmo_cf_manual';
		$this->method_title       = __( 'Eilmo Payment', 'eilmo-checkout-flow' );
		$this->method_description = __( 'Internal bridge used by Eilmo Payment Options on the native WooCommerce checkout.', 'eilmo-checkout-flow' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->title              = __( 'Eilmo Payment', 'eilmo-checkout-flow' );
		$this->description        = '';
		$this->enabled            = 'yes';
	}

	/** The bridge is available only when native Eilmo Payment Options are on. */
	public function is_available() {
		return DefaultCheckoutSettings::payment_options_enabled();
	}

	/**
	 * Manual Eilmo payments never charge through WooCommerce immediately.
	 * The order remains pending, matching the plugin's existing custom flow.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,string>
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order instanceof \WC_Order ) {
			$order->update_status(
				'pending',
				__( 'Awaiting the selected Eilmo payment flow.', 'eilmo-checkout-flow' )
			);
		}

		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $order instanceof \WC_Order ? $this->get_return_url( $order ) : wc_get_checkout_url(),
		);
	}
}
