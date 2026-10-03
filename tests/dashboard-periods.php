<?php
/**
 * Isolated dashboard date-range and paginated metric regression.
 *
 * Run with: php tests/dashboard-periods.php
 */

define( 'ABSPATH', __DIR__ );

class WC_Order {
	private $meta;
	private $payment_method;
	private $status;
	private $total;

	public function __construct( array $meta = array(), string $payment_method = '', string $status = 'processing', float $total = 10.0 ) {
		$this->meta = $meta;
		$this->payment_method = $payment_method;
		$this->status = $status;
		$this->total = $total;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_total(): float {
		return $this->total;
	}

	public function get_meta( string $key, bool $single = true ) {
		return $this->meta[ $key ] ?? '';
	}

	public function get_payment_method(): string {
		return $this->payment_method;
	}
}

function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Asia/Dhaka' );
}

function wp_date( string $format ): string {
	return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( $format );
}

function __( string $text, string $domain ): string {
	return $text;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_key( string $value ): string {
	return strtolower( $value );
}

function get_transient( string $key ) {
	return false;
}

function set_transient( string $key, $value, int $seconds ): bool {
	return true;
}

function wc_get_order_statuses(): array {
	return array( 'wc-processing' => 'Processing', 'wc-checkout-draft' => 'Draft' );
}

function wc_get_is_paid_statuses(): array {
	return array( 'processing' );
}

$GLOBALS['dashboard_test_pages'] = array();

function wc_get_orders( array $args ): array {
	$GLOBALS['dashboard_test_pages'][] = $args;
	return 1 === $args['paged']
		? array_fill( 0, 200, new WC_Order() )
		: array( new WC_Order() );
}

require dirname( __DIR__ ) . '/src/Admin/Pages/DashboardPage.php';

$class    = EilmoCheckout\Admin\Pages\DashboardPage::class;
$page     = new $class();
$range_fn = new ReflectionMethod( $class, 'get_period_range' );
$range_fn->setAccessible( true );
$today  = new DateTimeImmutable( 'today', wp_timezone() );
$ranges = array(
	'today'       => array( $today, $today->modify( '+1 day -1 second' ) ),
	'last_7_days' => array( $today->modify( '-6 days' ), $today->modify( '+1 day -1 second' ) ),
	'this_month'  => array( $today->modify( 'first day of this month' ), $today->modify( '+1 day -1 second' ) ),
	'last_month'  => array( $today->modify( 'first day of last month' ), $today->modify( 'first day of this month -1 second' ) ),
);

foreach ( $ranges as $period => $expected ) {
	$actual = $range_fn->invoke( $page, $period );
	if ( $actual[0] != $expected[0] || $actual[1] != $expected[1] ) {
		throw new RuntimeException( 'Incorrect range: ' . $period );
	}
}

$snapshot_fn = new ReflectionMethod( $class, 'get_dashboard_snapshot' );
$snapshot_fn->setAccessible( true );
$last_month = $ranges['last_month'];
$snapshot = $snapshot_fn->invoke( $page, $last_month[0], $last_month[1] );

if ( 201 !== $snapshot['orders'] || 2010.0 !== $snapshot['revenue'] ) {
	throw new RuntimeException( 'Paginated dashboard totals are incorrect.' );
}

if ( array_column( $GLOBALS['dashboard_test_pages'], 'paged' ) !== array( 1, 2 ) ) {
	throw new RuntimeException( 'Dashboard did not fetch both order pages.' );
}

if ( strpos( $GLOBALS['dashboard_test_pages'][0]['date_created'], '...' ) === false ) {
	throw new RuntimeException( 'Order query has no date range.' );
}

$payment_fn = new ReflectionMethod( $class, 'get_paid_order_value' );
$payment_fn->setAccessible( true );
$received = $payment_fn->invoke( $page, array(
	new WC_Order( array( '_eilmo_cf_payment_type' => 'cash_on_delivery', '_eilmo_cf_payment_status' => 'paid', '_eilmo_cf_remaining_due' => 10 ), 'cod' ),
	new WC_Order( array( '_eilmo_cf_payment_type' => 'advance', '_eilmo_cf_payment_status' => 'paid', '_eilmo_cf_pay_now' => 4 ), '', 'completed' ),
	new WC_Order( array( '_eilmo_cf_payment_type' => 'full', '_eilmo_cf_payment_status' => 'awaiting_verification', '_eilmo_cf_pay_now' => 10 ), '', 'on-hold' ),
	new WC_Order(),
) );
if ( 14.0 !== $received ) {
	throw new RuntimeException( 'COD balances or unverified payments were counted as received.' );
}

echo "Dashboard date ranges, paginated totals, and received payments passed.\n";
