<?php
/**
 * Plugin Name:       Checkout Flow for WooCommerce
 * Description:       A modular WooCommerce checkout flow builder with Elementor and shortcode support.
 * Version: 2.2.4.25
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            JH Fahim
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eilmo-checkout-flow
 * Domain Path:       /languages
 *
 * @package EilmoCheckout
 */

defined( 'ABSPATH' ) || exit;

/*
 * Prevent two copies of Checkout Flow from bootstrapping at the same time.
 *
 * This can happen when an update ZIP is extracted into a different plugin
 * directory while an older copy remains active. WordPress treats both
 * directories as separate plugins even though they define the same runtime
 * constants/classes. The first loaded copy remains authoritative; this copy
 * stops before defining anything and shows an administrator notice.
 */
if ( defined( 'EILMO_CF_VERSION' ) || defined( 'EILMO_CF_FILE' ) ) {
	$eilmo_cf_duplicate_file = __FILE__;
	$eilmo_cf_existing_file  = defined( 'EILMO_CF_FILE' ) ? (string) EILMO_CF_FILE : '';

	add_action(
		'admin_notices',
		static function () use ( $eilmo_cf_duplicate_file, $eilmo_cf_existing_file ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$message = __(
				'Checkout Flow detected another active copy of the same plugin. Deactivate and delete the duplicate copy, then keep only one Checkout Flow plugin active.',
				'eilmo-checkout-flow'
			);

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong></p><p>%2$s</p><p><code>%3$s</code><br><code>%4$s</code></p></div>',
				esc_html__( 'Checkout Flow duplicate plugin detected', 'eilmo-checkout-flow' ),
				esc_html( $message ),
				esc_html( $eilmo_cf_existing_file ),
				esc_html( $eilmo_cf_duplicate_file )
			);
		}
	);

	return;
}

/**
 * Plugin version.
 */
define( 'EILMO_CF_VERSION', '2.2.4.25' );

/**
 * Main plugin file.
 */
define( 'EILMO_CF_FILE', __FILE__ );

/**
 * Plugin basename.
 */
define(
	'EILMO_CF_BASENAME',
	plugin_basename(
		EILMO_CF_FILE
	)
);

/**
 * Absolute plugin directory path.
 */
define(
	'EILMO_CF_PATH',
	plugin_dir_path(
		EILMO_CF_FILE
	)
);

/**
 * Plugin directory URL.
 */
define(
	'EILMO_CF_URL',
	plugin_dir_url(
		EILMO_CF_FILE
	)
);

/**
 * Assets URL.
 */
define(
	'EILMO_CF_ASSETS_URL',
	EILMO_CF_URL . 'assets/'
);

/**
 * Declare support for current WooCommerce storage and checkout features.
 *
 * The Default Checkout adapter keeps WooCommerce's Store API, gateways and
 * order lifecycle authoritative while applying field visibility through
 * WooCommerce locale filters and a small Checkout Block presentation adapter.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			foreach ( array( 'custom_order_tables', 'cart_checkout_blocks' ) as $feature ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					$feature,
					EILMO_CF_FILE,
					true
				);
			}
		}
	}
);

/**
 * Load the development Composer autoloader when present. Production packages
 * use the small built-in PSR-4 loader because this plugin has no runtime
 * Composer dependencies.
 */
$eilmo_cf_autoloader =
	EILMO_CF_PATH .
	'vendor/autoload.php';

if ( file_exists( $eilmo_cf_autoloader ) ) {
	require_once $eilmo_cf_autoloader;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'EilmoCheckout\\';

			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$class_file = EILMO_CF_PATH .
				'src/' .
				str_replace( '\\', '/', $relative_class ) .
				'.php';

			if ( file_exists( $class_file ) ) {
				require_once $class_file;
			}
		}
	);
}

/**
 * Plugin activation.
 *
 * Database tables and other installation tasks
 * are handled by the Activator.
 */
register_activation_hook(
	EILMO_CF_FILE,
	array(
		\EilmoCheckout\Core\Activator::class,
		'activate',
	)
);

/**
 * Plugin deactivation.
 *
 * Temporary scheduled tasks are cleaned by
 * the Deactivator.
 */
register_deactivation_hook(
	EILMO_CF_FILE,
	array(
		\EilmoCheckout\Core\Deactivator::class,
		'deactivate',
	)
);

/**
 * Boot the plugin after all active plugins have loaded.
 */
add_action(
	'plugins_loaded',
	static function () {

		if (
			! class_exists(
				'\EilmoCheckout\Plugin'
			)
		) {
			return;
		}

		if (
			! method_exists(
				'\EilmoCheckout\Plugin',
				'instance'
			)
		) {
			return;
		}

		$plugin =
			\EilmoCheckout\Plugin::instance();

		if (
			! method_exists(
				$plugin,
				'boot'
			)
		) {
			return;
		}

		$plugin->boot();
	},
	20
);
