<?php
/**
 * Shortcodes module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Shortcodes;

use EilmoCheckout\Contracts\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin shortcodes.
 */
final class Shortcodes implements ModuleInterface {

	/**
	 * Get module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'shortcodes';
	}

	/**
	 * Register shortcode functionality.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'init',
			array(
				$this,
				'register_shortcodes',
			)
		);
	}

	/**
	 * Register all plugin shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {

		$checkout_shortcode = new CheckoutFlowShortcode();

		$checkout_shortcode->register();
	}
}