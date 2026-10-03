<?php
/**
 * Local WooCommerce customer history lookup.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/** Reads this store's resolved orders without any external API usage. */
final class LocalCustomerHistoryService {

	/**
	 * Build a normalized local purchase-history provider row.
	 *
	 * Only final statuses participate. Pending, on-hold and processing orders
	 * remain unresolved and therefore cannot improve or reduce the ratio.
	 *
	 * @param string $phone Normalized Bangladesh phone.
	 * @return array<string,mixed>
	 */
	public function lookup( string $phone ): array {

		$phone = ( new CourierSuccessService() )->normalize_phone( $phone );
		if ( '' === $phone || ! function_exists( 'wc_get_orders' ) ) {
			return $this->empty_result();
		}

		$order_ids = array();
		foreach ( $this->phone_variants( $phone ) as $variant ) {
			$found = wc_get_orders(
				array(
					'limit' => 100,
					'return' => 'ids',
					'status' => array( 'completed', 'cancelled', 'failed', 'refunded' ),
					'billing_phone' => $variant,
					'orderby' => 'date',
					'order' => 'DESC',
				)
			);

			if ( is_array( $found ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'absint', $found ) );
			}
		}

		$order_ids = array_values( array_unique( array_filter( $order_ids ) ) );
		$success = 0;
		$cancel = 0;
		$status_counts = array(
			'completed' => 0,
			'cancelled' => 0,
			'failed' => 0,
			'refunded' => 0,
		);

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_phone = ( new CourierSuccessService() )->normalize_phone( (string) $order->get_billing_phone() );
			if ( $phone !== $order_phone ) {
				continue;
			}

			$status = sanitize_key( (string) $order->get_status() );
			if ( ! array_key_exists( $status, $status_counts ) ) {
				continue;
			}

			++$status_counts[ $status ];
			if ( 'completed' === $status ) {
				++$success;
			} else {
				++$cancel;
			}
		}

		$total = $success + $cancel;
		$ratio = $total > 0 ? round( ( $success / $total ) * 100, 2 ) : 0.0;

		return array(
			'available' => $total > 0,
			'total' => $total,
			'success' => $success,
			'cancel' => $cancel,
			'ratio' => $ratio,
			'status_counts' => $status_counts,
		);
	}

	/**
	 * Build an admin-facing summary of every order placed on this store.
	 *
	 * This is intentionally separate from lookup(), which only counts resolved
	 * orders for fraud decisions. The summary may include open/in-progress orders
	 * so the Orders-screen hover can answer how many orders the customer has
	 * actually placed on this website without changing fraud scoring behavior.
	 *
	 * @param string $phone Normalized Bangladesh phone.
	 * @return array<string,mixed>
	 */
	public function lookup_summary( string $phone ): array {

		$phone = ( new CourierSuccessService() )->normalize_phone( $phone );
		if ( '' === $phone || ! function_exists( 'wc_get_orders' ) ) {
			return $this->empty_summary();
		}

		$order_ids = array();
		foreach ( $this->phone_variants( $phone ) as $variant ) {
			$found = wc_get_orders(
				array(
					'limit' => 100,
					'return' => 'ids',
					'billing_phone' => $variant,
					'orderby' => 'date',
					'order' => 'DESC',
				)
			);

			if ( is_array( $found ) ) {
				$order_ids = array_merge( $order_ids, array_map( 'absint', $found ) );
			}
		}

		$order_ids = array_values( array_unique( array_filter( $order_ids ) ) );
		$total = 0;
		$success = 0;
		$cancel = 0;
		$open = 0;
		$status_counts = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_phone = ( new CourierSuccessService() )->normalize_phone( (string) $order->get_billing_phone() );
			if ( $phone !== $order_phone ) {
				continue;
			}

			$status = sanitize_key( (string) $order->get_status() );
			++$total;
			$status_counts[ $status ] = isset( $status_counts[ $status ] )
				? (int) $status_counts[ $status ] + 1
				: 1;

			if ( 'completed' === $status ) {
				++$success;
			} elseif ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				++$cancel;
			} else {
				++$open;
			}
		}

		$resolved = $success + $cancel;
		$ratio = $resolved > 0 ? round( ( $success / $resolved ) * 100, 2 ) : 0.0;

		return array(
			'available' => $total > 0,
			'total' => $total,
			'success' => $success,
			'cancel' => $cancel,
			'open' => $open,
			'resolved_total' => $resolved,
			'ratio' => $ratio,
			'status_counts' => $status_counts,
		);
	}

	/** @return array<string,mixed> */
	private function empty_summary(): array {

		return array(
			'available' => false,
			'total' => 0,
			'success' => 0,
			'cancel' => 0,
			'open' => 0,
			'resolved_total' => 0,
			'ratio' => 0.0,
			'status_counts' => array(),
		);
	}

	/** @return array<int,string> */
	private function phone_variants( string $phone ): array {

		$without_zero = ltrim( $phone, '0' );
		return array_values(
			array_unique(
				array_filter(
					array(
						$phone,
						$without_zero,
						'880' . $without_zero,
						'+880' . $without_zero,
					)
				)
			)
		);
	}

	/** @return array<string,mixed> */
	private function empty_result(): array {

		return array(
			'available' => false,
			'total' => 0,
			'success' => 0,
			'cancel' => 0,
			'ratio' => 0.0,
			'status_counts' => array(),
		);
	}
}
