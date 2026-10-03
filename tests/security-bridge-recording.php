<?php
/**
 * Isolated regression test for native checkout security recording.
 *
 * Run with: php tests/security-bridge-recording.php
 */

namespace EilmoCheckout\Contracts {
	interface RegistrableInterface {
		public function register(): void;
	}
}

namespace EilmoCheckout\Admin {
	final class SecuritySettings {
		public static bool $enabled = true;
		public static string $message = 'Dashboard checkout failure message';

		public static function is_enabled(): bool {
			return self::$enabled;
		}

		public static function is_protection_enabled( string $key ): bool {
			return self::$enabled && in_array( $key, array( 'rate_limit', 'order_cooldown' ), true );
		}

		public static function get_message( string $key, string $fallback = '' ): string {
			return 'checkout_request_failed' === $key ? self::$message : $fallback;
		}
	}
}

namespace EilmoCheckout\Security\RateLimit {
	final class RateLimiter {
		public static int $recorded = 0;

		public function record_order(): void {
			++self::$recorded;
		}
	}
}

namespace EilmoCheckout\Security\OrderCooldown {
	final class OrderCooldownGuard {
		public static array $recorded = array();

		public function record_order( \WC_Order $order, string $source ): void {
			self::$recorded[] = array( $order->get_id(), $source );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Order {
		private int $id;
		private string $created_via;
		private array $meta;
		public int $saves = 0;

		public function __construct( int $id, string $created_via, array $meta = array() ) {
			$this->id = $id;
			$this->created_via = $created_via;
			$this->meta = $meta;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_created_via(): string {
			return $this->created_via;
		}

		public function get_meta( string $key, bool $single = true ): string {
			return (string) ( $this->meta[ $key ] ?? '' );
		}

		public function update_meta_data( string $key, string $value ): void {
			$this->meta[ $key ] = $value;
		}

		public function save(): void {
			++$this->saves;
		}
	}

	function sanitize_key( string $value ): string {
		return strtolower( $value );
	}

	function __( string $message, string $domain ): string {
		return $message;
	}

	function do_action( string $hook, ...$args ): void {}

	require dirname( __DIR__ ) . '/src/Security/WooCommerce/WooCommerceSecurityBridge.php';

	use EilmoCheckout\Admin\SecuritySettings;
	use EilmoCheckout\Security\OrderCooldown\OrderCooldownGuard;
	use EilmoCheckout\Security\RateLimit\RateLimiter;
	use EilmoCheckout\Security\WooCommerce\WooCommerceSecurityBridge;

	function expect( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	$bridge = new WooCommerceSecurityBridge();
	$eilmo_meta = array( '_eilmo_cf_created_via' => 'eilmo-checkout-flow' );
	$cases = array(
		array( new WC_Order( 1, 'checkout' ), 'classic', true ),
		array( new WC_Order( 2, 'store-api' ), 'block', true ),
		array( new WC_Order( 3, 'checkout', $eilmo_meta + array( '_eilmo_cf_native_checkout' => 'yes' ) ), 'classic', true ),
		array( new WC_Order( 4, 'checkout', $eilmo_meta ), 'classic', true ),
		array( new WC_Order( 5, 'store-api', $eilmo_meta ), 'block', true ),
		array( new WC_Order( 6, '', $eilmo_meta ), 'classic', false ),
		array( new WC_Order( 7, '', $eilmo_meta + array( '_eilmo_cf_native_checkout' => 'yes' ) ), 'classic', true ),
	);

	foreach ( $cases as [ $order, $flow, $should_record ] ) {
		$before = RateLimiter::$recorded;
		if ( 'block' === $flow ) {
			$bridge->record_store_api_order( $order );
			$bridge->record_store_api_order( $order );
		} else {
			$bridge->record_classic_order( $order->get_id(), array(), $order );
			$bridge->record_classic_order( $order->get_id(), array(), $order );
		}
		expect( RateLimiter::$recorded - $before === (int) $should_record, 'Rate count failed for order ' . $order->get_id() );
		expect( $order->saves === (int) $should_record, 'Idempotent save failed for order ' . $order->get_id() );
	}

	expect( count( OrderCooldownGuard::$recorded ) === 6, 'Cooldown did not cover every native flow' );
	expect( OrderCooldownGuard::$recorded[1][1] === 'woocommerce_block', 'Store API source was not preserved' );

	SecuritySettings::$enabled = false;
	$disabled = new WC_Order( 8, 'checkout' );
	$bridge->record_classic_order( 8, array(), $disabled );
	expect( RateLimiter::$recorded === 6, 'Disabled security still recorded an order' );

	$method = new \ReflectionMethod( WooCommerceSecurityBridge::class, 'get_checkout_failure_message' );
	expect( $method->invoke( $bridge ) === SecuritySettings::$message, 'Dashboard fallback message was ignored' );

	echo "Security bridge recording regression passed.\n";
}
