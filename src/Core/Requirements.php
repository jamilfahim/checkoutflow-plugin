<?php
/**
 * Plugin requirements checker.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the minimum requirements for running the plugin.
 */
final class Requirements {

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	private const MINIMUM_PHP_VERSION = '7.4';

	/**
	 * Minimum supported WordPress version.
	 *
	 * @var string
	 */
	private const MINIMUM_WP_VERSION = '6.5';

	/**
	 * Requirement errors.
	 *
	 * @var array<int, string>
	 */
	private $errors = array();

	/**
	 * Check all required dependencies.
	 *
	 * @return bool
	 */
	public function passes(): bool {

		$this->errors = array();

		$this->check_php();
		$this->check_wordpress();
		$this->check_woocommerce();

		return empty( $this->errors );
	}

	/**
	 * Check PHP version.
	 *
	 * @return void
	 */
	private function check_php(): void {

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' ) ) {
			return;
		}

		$this->errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version. */
			__(
				'Checkout Flow requires PHP %1$s or newer. Your current PHP version is %2$s.',
				'eilmo-checkout-flow'
			),
			self::MINIMUM_PHP_VERSION,
			PHP_VERSION
		);
	}

	/**
	 * Check WordPress version.
	 *
	 * @return void
	 */
	private function check_wordpress(): void {

		global $wp_version;

		if ( version_compare( $wp_version, self::MINIMUM_WP_VERSION, '>=' ) ) {
			return;
		}

		$this->errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version. */
			__(
				'Eilmo Checkout Flow requires WordPress %1$s or newer. Your current WordPress version is %2$s.',
				'eilmo-checkout-flow'
			),
			self::MINIMUM_WP_VERSION,
			$wp_version
		);
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return void
	 */
	private function check_woocommerce(): void {

		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->errors[] = __(
			'Eilmo Checkout Flow requires WooCommerce to be installed and active.',
			'eilmo-checkout-flow'
		);
	}

	/**
	 * Get requirement errors.
	 *
	 * @return array<int, string>
	 */
	public function get_errors(): array {

		return $this->errors;
	}

	/**
	 * Check whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public function has_woocommerce(): bool {

		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check whether Elementor is available.
	 *
	 * Elementor is optional because the shortcode integration must work
	 * independently when Elementor is not active.
	 *
	 * @return bool
	 */
	public function has_elementor(): bool {

		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}
}