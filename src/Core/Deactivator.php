<?php
/**
 * Plugin deactivator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutScheduler;
use EilmoCheckout\Security\Logging\SecurityLogCleanup;
use EilmoCheckout\Licensing\LicenseManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
final class Deactivator {

	/**
	 * Deactivate plugin.
	 *
	 * Only temporary scheduled tasks are removed.
	 *
	 * Deactivation must preserve:
	 *
	 * - Plugin settings.
	 * - Database tables.
	 * - Security logs.
	 * - Blacklist records.
	 * - Abandoned Checkout records.
	 *
	 * Deactivation must never behave like uninstall.
	 *
	 * @return void
	 */
	public static function deactivate(): void {

		self::unschedule_security_log_cleanup();
		self::unschedule_abandoned_checkout_detection();
		wp_clear_scheduled_hook( LicenseManager::CRON_HOOK );
	}

	/**
	 * Remove Security Log cleanup cron.
	 *
	 * @return void
	 */
	private static function unschedule_security_log_cleanup(): void {

		if (
			class_exists(
				SecurityLogCleanup::class
			) &&
			method_exists(
				SecurityLogCleanup::class,
				'unschedule'
			)
		) {
			SecurityLogCleanup::unschedule();

			return;
		}

		/*
		 * Defensive fallback.
		 */
		wp_clear_scheduled_hook(
			'eilmo_cf_security_log_cleanup'
		);
	}

	/**
	 * Remove Abandoned Checkout detection cron.
	 *
	 * Existing Abandoned Checkout records and statuses
	 * remain untouched.
	 *
	 * @return void
	 */
	private static function unschedule_abandoned_checkout_detection(): void {

		if (
			class_exists(
				AbandonedCheckoutScheduler::class
			) &&
			method_exists(
				AbandonedCheckoutScheduler::class,
				'unschedule_all'
			)
		) {
			AbandonedCheckoutScheduler::unschedule_all();

			return;
		}

		/*
		 * Defensive fallback.
		 */
		wp_clear_scheduled_hook(
			'eilmo_cf_abandoned_checkout_detection'
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {
	}
}
