<?php
/** Focused COD state and abandoned-count regression. Run with php tests/release-payment-abandoned.php. */

namespace EilmoCheckout\Contracts {
	interface RegistrableInterface {
		public function register(): void;
	}
}

namespace EilmoCheckout\Core {
	final class Installer {
		public static function get_abandoned_checkout_table(): string {
			return 'wp_eilmo_abandoned';
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Order {
		public $meta;
		public $status;
		public $paid;

		public function __construct( array $meta, string $status = 'processing', bool $paid = true ) {
			$this->meta = $meta;
			$this->status = $status;
			$this->paid = $paid;
		}
		public function get_meta( string $key, bool $single = true ) {
			return $this->meta[ $key ] ?? '';
		}
		public function update_meta_data( string $key, $value ): void {
			$this->meta[ $key ] = $value;
		}
		public function get_status(): string {
			return $this->status;
		}
		public function is_paid(): bool {
			return $this->paid;
		}
		public function get_date_paid() {
			return null;
		}
		public function get_id(): int {
			return 1;
		}
		public function save(): void {}
	}

	function sanitize_key( string $value ): string {
		return strtolower( $value );
	}
	function do_action(): void {}

	class FakeWpdb {
		public $arguments;
		public function prepare( string $query, ...$arguments ): string {
			$this->arguments = $arguments;
			return $query;
		}
		public function get_var( string $query ) {
			return '3';
		}
	}

	require dirname( __DIR__ ) . '/src/Orders/Services/PaymentStatusTracker.php';
	require dirname( __DIR__ ) . '/src/AbandonedCheckout/AbandonedCheckoutRepository.php';

	$cod = new WC_Order( array(
		'_eilmo_cf_created_via' => 'eilmo-checkout-flow',
		'_eilmo_cf_payment_type' => 'cash_on_delivery',
		'_eilmo_cf_remaining_due' => 100,
		'_eilmo_cf_payment_status' => 'paid',
	) );
	$tracker = new \EilmoCheckout\Orders\Services\PaymentStatusTracker();
	if ( 'pay_on_delivery' !== $tracker::resolve_status( $cod ) ) {
		throw new \RuntimeException( 'Existing COD balance displays Paid.' );
	}
	$tracker->order_status_changed( 1, 'pending', 'processing', $cod );
	if ( 'pay_on_delivery' !== $cod->get_meta( '_eilmo_cf_payment_status' ) ) {
		throw new \RuntimeException( 'Processing COD was persisted as Paid.' );
	}
	$cod->status = 'refunded';
	if ( 'refunded' !== $tracker::resolve_status( $cod ) ) {
		throw new \RuntimeException( 'Refunded order lost its refund status.' );
	}

	$wpdb = new FakeWpdb();
	$repo = new \EilmoCheckout\AbandonedCheckout\AbandonedCheckoutRepository();
	$start = new \DateTimeImmutable( '2026-09-27 00:00:00', new \DateTimeZone( 'Asia/Dhaka' ) );
	$end = $start->modify( '+1 day' );
	if ( 3 !== $repo->count_abandoned_between( $start, $end ) ) {
		throw new \RuntimeException( 'Abandoned count was not returned.' );
	}
	if ( $wpdb->arguments !== array( 'wp_eilmo_abandoned', 'abandoned', '2026-09-26 18:00:00', '2026-09-27 18:00:00' ) ) {
		throw new \RuntimeException( 'Abandoned count did not use the store-local day in UTC.' );
	}

	echo "COD payment state and abandoned day count passed.\n";
}
