<?php
/**
 * Plugin activator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
final class Activator {

	/**
	 * Run plugin activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {

		/*
		 * Install or update plugin-owned
		 * database tables.
		 */
		Installer::install();

		/**
		 * Fires after Eilmo Checkout Flow
		 * has completed its activation tasks.
		 */
		do_action(
			'eilmo_cf/activated'
		);
	}
}