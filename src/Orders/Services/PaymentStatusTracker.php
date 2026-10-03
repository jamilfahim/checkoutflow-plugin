<?php
/**
 * Payment status tracker.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Services;

use EilmoCheckout\Contracts\RegistrableInterface;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps Eilmo payment status synchronized with
 * WooCommerce's authoritative payment state.
 */
final class PaymentStatusTracker implements RegistrableInterface {

	/**
	 * Eilmo order marker.
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Eilmo payment status.
	 */
	private const META_PAYMENT_STATUS =
		'_eilmo_cf_payment_status';

	/**
	 * Register payment-status hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * Primary successful-payment event.
		 *
		 * Stripe, PayPal and other WooCommerce gateways
		 * normally call WC_Order::payment_complete().
		 */
		add_action(
			'woocommerce_payment_complete',
			array(
				$this,
				'payment_complete',
			),
			20,
			2
		);

		/*
		 * Defensive synchronization.
		 *
		 * Some gateways or administrators can change
		 * the WooCommerce order status through another
		 * valid WooCommerce flow.
		 */
		add_action(
			'woocommerce_order_status_changed',
			array(
				$this,
				'order_status_changed',
			),
			20,
			4
		);
	}

	/**
	 * WooCommerce payment completed.
	 *
	 * @param int    $order_id      Order ID.
	 * @param string $transaction_id Transaction ID.
	 *
	 * @return void
	 */
	public function payment_complete(
		$order_id,
		$transaction_id = ''
	): void {

		unset( $transaction_id );

		$order =
			wc_get_order(
				absint(
					$order_id
				)
			);

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		if ( self::has_uncollected_cod_balance( $order ) ) {
			$this->update_status( $order, 'pay_on_delivery' );
			return;
		}

		$this->update_status(
			$order,
			'paid'
		);
	}

	/**
	 * Synchronize Eilmo status after a WooCommerce
	 * order status transition.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 *
	 * @return void
	 */
	public function order_status_changed(
		$order_id,
		$from,
		$to,
		$order
	): void {

		unset(
			$order_id,
			$from
		);

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$to = sanitize_key( (string) $to );
		if ( in_array( $to, array( 'on-hold', 'processing', 'completed' ), true ) && self::has_uncollected_cod_balance( $order ) ) {
			$this->update_status( $order, 'pay_on_delivery' );
			return;
		}

		/*
		 * WooCommerce itself is authoritative.
		 *
		 * If WooCommerce considers the order paid,
		 * Eilmo must also show Paid regardless of an
		 * older/stale Eilmo meta value.
		 */
		if (
			$order->is_paid() ||
			$order->get_date_paid()
		) {
			$this->update_status(
				$order,
				'paid'
			);

			return;
		}

		if ( 'on-hold' === $to ) {
			$payment_type = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) );
			if ( in_array( $payment_type, array( 'advance', 'full' ), true ) ) {
				$this->update_status( $order, 'awaiting_verification' );
				return;
			}
			if ( 'cash_on_delivery' === $payment_type ) {
				$this->update_status( $order, 'pay_on_delivery' );
				return;
			}
		}

		switch ( $to ) {

			case 'failed':
				$this->update_status(
					$order,
					'payment_failed'
				);
				break;

			case 'refunded':
				$this->update_status(
					$order,
					'refunded'
				);
				break;

			case 'cancelled':
				$this->update_status(
					$order,
					'cancelled'
				);
				break;
		}
	}

	/**
	 * Resolve the current Eilmo payment status.
	 *
	 * This method is also used by the renderer so an
	 * old order with stale `_eilmo_cf_payment_status`
	 * immediately displays the real WooCommerce status.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	public static function resolve_status(
		WC_Order $order
	): string {
		$order_status = sanitize_key( (string) $order->get_status() );
		if ( 'refunded' === $order_status ) {
			return 'refunded';
		}
		if ( 'failed' === $order_status ) {
			return 'payment_failed';
		}
		if ( 'cancelled' === $order_status ) {
			return 'cancelled';
		}
		if ( self::has_uncollected_cod_balance( $order ) ) {
			return 'pay_on_delivery';
		}

		/*
		 * WooCommerce paid state always has priority
		 * over Eilmo's cached payment-status meta.
		 */
		if (
			$order->is_paid() ||
			$order->get_date_paid()
		) {
			return 'paid';
		}

		$status =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_STATUS,
						true
					)
			);

		$payment_type = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) );
		if ( 'on-hold' === $order_status && in_array( $payment_type, array( 'advance', 'full' ), true ) && in_array( $status, array( '', 'unpaid', 'payment_initiated' ), true ) ) {
			return 'awaiting_verification';
		}
		if ( in_array( $order_status, array( 'on-hold', 'processing' ), true ) && 'cash_on_delivery' === $payment_type && in_array( $status, array( '', 'unpaid' ), true ) ) {
			return 'pay_on_delivery';
		}

		if (
			'' !==
				$status
		) {
			return $status;
		}

		return 'unpaid';
	}

	/** A COD order with a recorded balance has not collected that balance. */
	private static function has_uncollected_cod_balance( WC_Order $order ): bool {
		return 'cash_on_delivery' === sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) )
			&& (float) $order->get_meta( '_eilmo_cf_remaining_due', true ) > 0;
	}

	/**
	 * Update Eilmo payment status.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $status Payment status.
	 *
	 * @return void
	 */
	private function update_status(
		WC_Order $order,
		string $status
	): void {

		$status =
			sanitize_key(
				$status
			);

		if (
			'' ===
				$status
		) {
			return;
		}

		$current =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_STATUS,
						true
					)
			);

		if (
			$current ===
				$status
		) {
			return;
		}

		$order->update_meta_data(
			self::META_PAYMENT_STATUS,
			$status
		);

		$order->save();

		/**
		 * Fires after Eilmo payment status changes.
		 *
		 * @param int      $order_id Order ID.
		 * @param string   $status   New Eilmo status.
		 * @param string   $previous Previous Eilmo status.
		 * @param WC_Order $order    Order.
		 */
		do_action(
			'eilmo_cf/orders/payment_status_changed',
			$order->get_id(),
			$status,
			$current,
			$order
		);
	}

	/**
	 * Determine whether this is an Eilmo order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function is_eilmo_order(
		WC_Order $order
	): bool {

		return (
			'yes' === (string) $order->get_meta( '_eilmo_cf_admin_manual', true ) ||
			'eilmo-checkout-flow' ===
				(string) $order
					->get_meta(
						self::META_CREATED_VIA,
						true
					)
		);
	}
}
