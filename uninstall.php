<?php
/**
 * Eilmo Checkout Flow uninstall cleanup.
 *
 * Plugin data is preserved unless the administrator explicitly enables
 * "Remove Plugin Data on Uninstall" before deleting the plugin.
 *
 * @package EilmoCheckout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$eilmo_cf_settings = get_option( 'eilmo_cf_settings', array() );
$eilmo_cf_general  = is_array( $eilmo_cf_settings ) &&
	isset( $eilmo_cf_settings['general'] ) &&
	is_array( $eilmo_cf_settings['general'] )
		? $eilmo_cf_settings['general']
		: array();

if ( 'yes' !== (string) ( $eilmo_cf_general['delete_data_on_uninstall'] ?? 'no' ) ) {
	return;
}

/* Remove plugin-owned scheduled work. */
foreach (
	array(
		'eilmo_cf_abandoned_checkout_detection',
		'eilmo_cf_security_log_cleanup',
		'eilmo_cf_courier_success_lookup',
		'eilmo_cf_daily_license_validation',
	) as $eilmo_cf_cron_hook
) {
	wp_clear_scheduled_hook( $eilmo_cf_cron_hook );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions(
		'eilmo_cf_courier_success_lookup',
		array(),
		'eilmo-checkout-flow'
	);
}

/* Remove every registered or historical plugin settings option. */
foreach (
	array(
		'eilmo_cf_settings',
		'eilmo_cf_security_settings',
		'eilmo_cf_courier_settings',
		'eilmo_cf_meta_tracking_settings',
		'eilmo_cf_whatsapp_settings',
		'eilmo_cf_quick_checkout_settings',
		'eilmo_cf_default_checkout_settings',
		'eilmo_cf_product_settings',
		'eilmo_cf_db_version',
		'eilmo_cf_license_state',
		'eilmo_cf_update_channel',
		'eilmo_cf_remote_help_fallback',
	) as $eilmo_cf_option_name
) {
	delete_option( $eilmo_cf_option_name );
}

delete_site_transient( 'eilmo_cf_update_manifest' );

global $wpdb;

/* Remove short-lived plugin locks and transients by their private prefixes. */
$eilmo_cf_option_prefixes = array(
	'eilmo_cf_order_lock_',
	'eilmo_cf_live_fraud_',
	'_transient_eilmo_cf_',
	'_transient_timeout_eilmo_cf_',
);

foreach ( $eilmo_cf_option_prefixes as $eilmo_cf_option_prefix ) {
	$eilmo_cf_like = $wpdb->esc_like( $eilmo_cf_option_prefix ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must delete matching Eilmo-owned transient rows directly.
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s',
			$wpdb->options,
			$eilmo_cf_like
		)
	);
}

/*
 * Remove only Eilmo-owned operational tables. WooCommerce orders and their
 * historical metadata are deliberately retained.
 */
$eilmo_cf_table_suffixes = array(
	'eilmo_cf_blacklist',
	'eilmo_cf_rate_limits',
	'eilmo_cf_duplicate_orders',
	'eilmo_cf_security_logs',
	'eilmo_cf_abandoned_checkouts',
);

foreach ( $eilmo_cf_table_suffixes as $eilmo_cf_table_suffix ) {
	$eilmo_cf_table = $wpdb->prefix . $eilmo_cf_table_suffix;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall intentionally drops only Eilmo-owned operational tables.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall intentionally drops only an Eilmo-owned operational table.
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %i',
			$eilmo_cf_table
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
}

wp_cache_flush();
