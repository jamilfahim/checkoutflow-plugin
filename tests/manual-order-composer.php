<?php
/** Focused admin-order calculation and fee-ownership regression. */
namespace EilmoCheckout\Admin {
	final class CheckoutSettings {
		public const OPTION_NAME = 'eilmo_cf_settings';
		public static function get_defaults(): array { return array( 'discounts' => array( 'full_payment' => array( 'enabled' => 'yes', 'type' => 'percentage', 'value' => 8, 'basis' => 'discounted_product_total' ) ) ); }
	}
}
namespace EilmoCheckout\Delivery\Services {
	final class DeliveryCalculator {
		public function calculate( string $id, float $total, array $context ): array { return array( 'valid' => true, 'method_id' => $id, 'label' => 'Inside Dhaka', 'charge' => 80, 'base_charge' => 80 ); }
	}
}
namespace EilmoCheckout\Discounts\Services {
	final class SpecialDiscountEngine {
		public function evaluate( array $context ): array { return array( 'automatic_discount' => 2 ); }
	}
}
namespace EilmoCheckout\AdvancePayment\Services {
	final class AdvanceCalculator {
		public function calculate( array $context ): array { return array( 'pay_now' => 30, 'remaining_due' => $context['grand_total'] - 30, 'advance_amount' => 30, 'matched' => true, 'is_advance_payment' => true ); }
	}
}
namespace EilmoCheckout\Payment\Gateways {
	final class GatewaySettings {
		public static function method_key( string $gateway_id ): string { return ''; }
		public static function is_eilmo_gateway( string $gateway_id ): bool { return false; }
	}
}
namespace EilmoCheckout\Payment\Services {
	final class PaymentProof {
		public static function is_attached_to_order( $order, string $key = '' ): bool { return false; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function __( $text, $domain = '' ) { return $text; }
	function sanitize_key( $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
	function sanitize_text_field( $value ): string { return (string) $value; }
	function absint( $value ): int { return abs( (int) $value ); }
	function wc_get_price_decimals(): int { return 2; }
	function get_option( $name, $default = array() ) { return $default; }
	function wp_unslash( $value ) { return $value; }
	function wp_verify_nonce( $nonce, $action ): bool { return true; }
	function current_user_can( $capability, $id = null ): bool { return true; }
	function wc_get_order( $id ) { return $GLOBALS['manual_test_order']; }
	function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
	function assert_amount( float $expected, float $actual, string $message ): void {
		if ( abs( $expected - $actual ) > 0.001 ) { throw new \RuntimeException( $message . ': ' . $actual ); }
	}
	class WP_Error { public function __construct( $code, $message ) {} }
	class TestProductItem {
		public function get_product_id(): int { return 7; }
		public function get_variation_id(): int { return 0; }
		public function get_quantity(): int { return 1; }
		public function get_subtotal(): float { return 18; }
		public function get_total(): float { return 18; }
		public function get_total_tax(): float { return 0; }
	}
	class WC_Order_Item_Fee {
		private $meta = array(); private $total = 0; private $id = 0; private $name = '';
		public function __construct( int $id = 0 ) { $this->id = $id; }
		public function set_name( $name ): void { $this->name = $name; }
		public function get_name(): string { return $this->name; }
		public function set_amount( $amount ): void {}
		public function set_total( $amount ): void { $this->total = $amount; }
		public function set_tax_status( $status ): void {}
		public function add_meta_data( $key, $value, $unique ): void { $this->meta[ $key ] = $value; }
		public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
		public function meta_exists( $key ): bool { return array_key_exists( $key, $this->meta ); }
		public function get_total(): float { return $this->total; }
		public function get_total_tax(): float { return 0; }
		public function get_id(): int { return $this->id; }
		public function assign_id( int $id ): void { $this->id = $id; }
	}
	class WC_Order {
		public $fees = array();
		public $meta = array(); private $total = 18; private $next_id = 20;
		public function get_items( $type = '' ): array { return 'line_item' === $type ? array( new TestProductItem() ) : ( 'fee' === $type ? $this->fees : array() ); }
		public function add_item( $item ): void { $item->assign_id( $this->next_id++ ); $this->fees[] = $item; }
		public function remove_item( $id ): void { $this->fees = array_values( array_filter( $this->fees, static function ( $item ) use ( $id ) { return $item->get_id() !== $id; } ) ); }
		public function get_id(): int { return 44; }
		public function get_date_paid() { return false; }
		public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
		public function meta_exists( $key ): bool { return array_key_exists( $key, $this->meta ); }
		public function update_meta_data( $key, $value ): void { $this->meta[ $key ] = $value; }
		public function get_total(): float { return $this->total; }
		public function calculate_totals( $with_taxes ): void {
			$fees_total = 0;
			foreach ( $this->fees as $fee ) {
				if ( $fee->get_total() < 0 && $fee->get_total() < -( 18 + $fees_total ) ) { $fee->set_total( -( 18 + $fees_total ) ); }
				$fees_total += $fee->get_total();
			}
			$this->total = 18 + $fees_total;
		}
		public function set_payment_method( $method ): void {}
		public function set_payment_method_title( $title ): void {}
		public function save(): void {}
	}

	require dirname( __DIR__ ) . '/src/Orders/Admin/ManualOrderComposer.php';
	$composer = new \EilmoCheckout\Orders\Admin\ManualOrderComposer();
	$order = new WC_Order();
	$calculate = new \ReflectionMethod( $composer, 'calculate' );
	$full = $calculate->invoke( $composer, $order, array( 'payment_type' => 'full', 'delivery_amount' => '80', 'discount_amount' => '1.44', 'gateway_id' => 'manual' ) );
	assert_amount( 18, $full['products'], 'Product subtotal' );
	assert_amount( 80, $full['delivery'], 'Manual delivery' );
	assert_amount( 1.44, $full['discount'], 'Manual discount' );
	assert_amount( 96.56, $full['total'], 'Full-payment total' );
	assert_amount( 96.56, $full['pay_now'], 'Full-payment pay now' );
	$free = $calculate->invoke( $composer, $order, array( 'payment_type' => 'full', 'delivery_amount' => '0', 'discount_amount' => '2', 'gateway_id' => 'manual' ) );
	assert_amount( 0, $free['delivery'], 'Zero delivery' );
	if ( 'Free Delivery' !== $free['delivery_label'] ) { throw new \RuntimeException( 'Zero delivery must be labeled Free Delivery.' ); }
	assert_amount( 16, $free['total'], 'Free delivery total' );
	$cod = $calculate->invoke( $composer, $order, array( 'payment_type' => 'cash_on_delivery', 'delivery_amount' => '80', 'discount_amount' => '0', 'gateway_id' => 'manual' ) );
	assert_amount( 98, $cod['total'], 'COD total' );
	assert_amount( 0, $cod['pay_now'], 'COD pay now' );
	assert_amount( 98, $cod['remaining_due'], 'COD due' );
	$invalid = $calculate->invoke( $composer, $order, array( 'payment_type' => 'full', 'delivery_amount' => '-1', 'discount_amount' => '0', 'gateway_id' => 'manual' ) );
	if ( ! is_wp_error( $invalid ) ) { throw new \RuntimeException( 'Negative delivery must be rejected.' ); }
	$invalid = $calculate->invoke( $composer, $order, array( 'payment_type' => 'full', 'delivery_amount' => '0', 'discount_amount' => '100', 'gateway_id' => 'manual' ) );
	if ( ! is_wp_error( $invalid ) ) { throw new \RuntimeException( 'Excessive discount must be rejected.' ); }
	$advance = $calculate->invoke( $composer, $order, array( 'payment_type' => 'advance', 'delivery_amount' => '80', 'discount_amount' => '0', 'gateway_id' => 'manual' ) );
	assert_amount( 30, $advance['pay_now'], 'Advance tier' );
	$manual_advance = $calculate->invoke( $composer, $order, array( 'payment_type' => 'advance', 'delivery_amount' => '80', 'discount_amount' => '0', 'gateway_id' => 'manual', 'advance_override' => true, 'advance_amount' => '45' ) );
	assert_amount( 30, $manual_advance['default_advance'], 'Plugin default remains available' );
	assert_amount( 45, $manual_advance['pay_now'], 'Manual advance overrides the tier' );
	assert_amount( 53, $manual_advance['remaining_due'], 'Manual advance due' );
	$invalid = $calculate->invoke( $composer, $order, array( 'payment_type' => 'advance', 'delivery_amount' => '80', 'discount_amount' => '0', 'gateway_id' => 'manual', 'advance_override' => true, 'advance_amount' => '99' ) );
	if ( ! is_wp_error( $invalid ) ) { throw new \RuntimeException( 'Advance above the order total must be rejected.' ); }
	$owned = new WC_Order_Item_Fee( 11 ); $owned->add_meta_data( '_eilmo_cf_admin_fee_type', 'delivery', true );
	$other = new WC_Order_Item_Fee( 12 );
	$order->fees = array( $owned, $other );
	( new \ReflectionMethod( $composer, 'remove_own_fees' ) )->invoke( $composer, $order );
	if ( count( $order->fees ) !== 1 || $order->fees[0] !== $other ) { throw new \RuntimeException( 'Only composer-owned fees may be removed.' ); }
	( new \ReflectionMethod( $composer, 'add_fee' ) )->invoke( $composer, $order, 'delivery', 'Delivery charge', 80 );
	if ( count( $order->fees ) !== 2 || 'delivery' !== $order->fees[1]->get_meta( '_eilmo_cf_admin_fee_type' ) ) { throw new \RuntimeException( 'New fee must be owned by the composer.' ); }
	( new \ReflectionMethod( $composer, 'add_fee' ) )->invoke( $composer, $order, 'delivery', 'Free Delivery', 0, true );
	if ( count( $order->fees ) !== 3 || 'Free Delivery' !== $order->fees[2]->get_name() ) { throw new \RuntimeException( 'Free delivery must persist as a named row.' ); }
	$live_order = new WC_Order();
	$GLOBALS['manual_test_order'] = $live_order;
	$_POST = array(
		'eilmo_cf_admin_order_nonce' => 'test',
		'eilmo_cf_admin_payment_type' => 'full',
		'eilmo_cf_admin_delivery_amount' => '80',
		'eilmo_cf_admin_discount_amount' => '1.44',
		'eilmo_cf_admin_gateway_id' => 'manual',
	);
	$composer->save( 44 );
	assert_amount( 96.56, $live_order->get_total(), 'Saved order total' );
	assert_amount( 96.56, (float) $live_order->get_meta( '_eilmo_cf_pay_now' ), 'Saved pay-now metadata' );
	assert_amount( 1.44, (float) $live_order->get_meta( '_eilmo_cf_admin_discount' ), 'Saved manual discount' );
	if ( 'yes' !== $live_order->get_meta( '_eilmo_cf_admin_manual' ) || '' !== $live_order->get_meta( '_eilmo_cf_created_via' ) ) { throw new \RuntimeException( 'Admin orders must remain distinct from storefront checkout orders.' ); }
	if ( 'unpaid' !== $live_order->get_meta( '_eilmo_cf_payment_status' ) ) { throw new \RuntimeException( 'Manual order was incorrectly marked paid.' ); }
	$composer->save( 44 );
	if ( count( $live_order->fees ) !== 2 ) { throw new \RuntimeException( 'Saving twice duplicated Checkout Flow fees.' ); }
	assert_amount( 96.56, $live_order->get_total(), 'Repeat save total' );
	$_POST['eilmo_cf_admin_delivery_amount'] = '0';
	$_POST['eilmo_cf_admin_discount_amount'] = '2';
	$composer->save( 44 );
	if ( count( $live_order->fees ) !== 2 || 'Free Delivery' !== $live_order->fees[0]->get_name() ) { throw new \RuntimeException( 'Replacing the delivery fee with Free Delivery failed.' ); }
	assert_amount( 16, $live_order->get_total(), 'Saved free delivery and manual discount' );
	$_POST['eilmo_cf_admin_delivery_amount'] = '80';
	$_POST['eilmo_cf_admin_discount_amount'] = '50';
	$composer->save( 44 );
	assert_amount( 48, $live_order->get_total(), 'Delivery must be included before WooCommerce caps the discount' );
	$_POST['eilmo_cf_admin_payment_type'] = 'advance';
	$_POST['eilmo_cf_admin_advance_override'] = 'yes';
	$_POST['eilmo_cf_admin_advance_amount'] = '20';
	$composer->save( 44 );
	assert_amount( 20, (float) $live_order->get_meta( '_eilmo_cf_pay_now' ), 'Saved custom advance' );
	assert_amount( 28, (float) $live_order->get_meta( '_eilmo_cf_remaining_due' ), 'Saved custom advance balance' );
	if ( 'admin_manual' !== $live_order->get_meta( '_eilmo_cf_advance_rule_type' ) || 'yes' !== $live_order->get_meta( '_eilmo_cf_admin_advance_override' ) ) { throw new \RuntimeException( 'Manual advance was not recorded.' ); }
	$_POST['eilmo_cf_admin_advance_override'] = 'no';
	$composer->save( 44 );
	assert_amount( 30, (float) $live_order->get_meta( '_eilmo_cf_pay_now' ), 'Reset to plugin advance rule' );
	if ( 'no' !== $live_order->get_meta( '_eilmo_cf_admin_advance_override' ) ) { throw new \RuntimeException( 'Plugin default mode was not restored.' ); }
	echo "Manual order composer regression passed.\n";
}
