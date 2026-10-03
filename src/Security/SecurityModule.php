<?php
/**
 * Security module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security;

use EilmoCheckout\Access\CheckoutAccess;
use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Security\Logging\SecurityLogCleanup;
use EilmoCheckout\Security\WooCommerce\WooCommerceSecurityBridge;

defined( 'ABSPATH' ) || exit;

/**
 * Registers checkout-security services.
 *
 * Guard classes such as BlacklistGuard, RateLimiter,
 * DuplicateOrderGuard, BotProtectionGuard and
 * OrderCooldownGuard are intentionally not registered
 * here because they are invoked directly by the
 * checkout pipelines that need them.
 */
final class SecurityModule implements ModuleInterface {

	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'security';
	}

	/**
	 * Register Security services.
	 *
	 * @return void
	 */
	public function register(): void {

		$this->register_checkout_access();
		$this->register_woocommerce_security();
		$this->register_log_cleanup();
	}

	/**
	 * Register checkout access protection.
	 *
	 * Covers:
	 *
	 * - Eilmo Checkout Flow AJAX.
	 * - WooCommerce Classic Checkout.
	 * - WooCommerce Checkout Block / Store API.
	 *
	 * @return void
	 */
	private function register_checkout_access(): void {

		$checkout_access =
			new CheckoutAccess();

		$checkout_access->register();
	}

	/**
	 * Register native WooCommerce security bridge.
	 *
	 * @return void
	 */
	private function register_woocommerce_security(): void {

		$woocommerce_security =
			new WooCommerceSecurityBridge();

		$woocommerce_security->register();
	}

	/**
	 * Register Security Activity Log cleanup.
	 *
	 * @return void
	 */
	private function register_log_cleanup(): void {

		$security_log_cleanup =
			new SecurityLogCleanup();

		$security_log_cleanup->register();
	}
}
