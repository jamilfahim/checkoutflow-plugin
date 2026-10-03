<?php
/**
 * Admin bootstrap.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Admin\Actions\SecurityLogActions;
use EilmoCheckout\Admin\Actions\LicenseActions;
use EilmoCheckout\Admin\Orders\OrderBlacklistActions;
use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Admin bootstrap.
 */
final class Admin implements RegistrableInterface {

	/**
	 * Register admin components.
	 *
	 * @return void
	 */
	public function register( bool $licensed = true ): void {

		/*
		 * Admin menu.
		 */
		$menu =
			new Menu();

		$menu->register();

		/*
		 * License actions must remain available before runtime authorization.
		 */
		$license_actions = new LicenseActions();

		$license_actions->register();

		if ( ! $licensed ) {
			return;
		}

		/*
		 * Main plugin settings.
		 */
		$settings =
			new CheckoutSettings();

		$settings->register();

		/*
		 * Native WooCommerce checkout integration settings.
		 *
		 * The master switch remains in the main General option,
		 * while detailed field and module configuration is stored
		 * separately.
		 */
		$default_checkout_settings =
			new DefaultCheckoutSettings();

		$default_checkout_settings->register();

		/*
		 * Single Product Quick Checkout settings.
		 *
		 * The feature master switch lives in the
		 * main General settings. Detailed Quick
		 * Checkout configuration is stored separately.
		 */
		$quick_checkout_settings =
			new QuickCheckoutSettings();

		$quick_checkout_settings->register();

		/*
		 * Courier settings.
		 *
		 * The Courier master switch lives in the
		 * main General settings. Detailed Courier
		 * configuration is stored separately.
		 */
		$courier_settings =
			new CourierSettings();

		$courier_settings->register();

		/*
		 * Meta Tracking settings.
		 *
		 * The Meta Tracking master switch lives in the
		 * main General settings. Pixel/CAPI credentials
		 * and event configuration are stored separately.
		 */
		$meta_tracking_settings =
			new MetaTrackingSettings();

		$meta_tracking_settings->register();

		/*
		 * Security settings.
		 */
		$security_settings =
			new SecuritySettings();

		$security_settings->register();

		/*
		 * WhatsApp Ordering settings.
		 */
		$whatsapp_settings =
			new WhatsAppSettings();

		$whatsapp_settings->register();

		/*
		 * Customer Blacklist actions.
		 */
		$blacklist_actions =
			new BlacklistActions();

		$blacklist_actions->register();

		/*
		 * Security Activity Log actions.
		 */
		$security_log_actions =
			new SecurityLogActions();

		$security_log_actions->register();

		/*
		 * WooCommerce order blacklist actions.
		 */
		$order_blacklist_actions =
			new OrderBlacklistActions();

		$order_blacklist_actions->register();

        /* Quick Order Management on WooCommerce Orders. */
        if ( class_exists( \EilmoCheckout\Admin\Orders\QuickOrderManagement::class ) ) {
            ( new \EilmoCheckout\Admin\Orders\QuickOrderManagement() )->register();
        }

	}
}
