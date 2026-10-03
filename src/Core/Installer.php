<?php
/**
 * Plugin installer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin database installation and upgrades.
 */
final class Installer {

	/**
	 * Database schema version.
	 *
	 * Version 7:
	 * - Added persisted billing address fields to Abandoned Checkout records.
	 *
	 * Version 6:
	 * - Simplified Abandoned Checkout storage.
	 * - Removed recovery token storage.
	 * - Removed automatic WooCommerce order relation.
	 * - Removed recovery attempt tracking.
	 *
	 * @var int
	 */
	public const DB_VERSION =
		7;

	/**
	 * Database version option.
	 *
	 * @var string
	 */
	private const DB_VERSION_OPTION =
		'eilmo_cf_db_version';

	/**
	 * Blacklist table suffix.
	 *
	 * @var string
	 */
	public const BLACKLIST_TABLE =
		'eilmo_cf_blacklist';

	/**
	 * Rate Limit table suffix.
	 *
	 * @var string
	 */
	public const RATE_LIMIT_TABLE =
		'eilmo_cf_rate_limits';

	/**
	 * Duplicate Order table suffix.
	 *
	 * @var string
	 */
	public const DUPLICATE_ORDER_TABLE =
		'eilmo_cf_duplicate_orders';

	/**
	 * Security Activity Log table suffix.
	 *
	 * @var string
	 */
	public const SECURITY_LOG_TABLE =
		'eilmo_cf_security_logs';

	/**
	 * Abandoned Checkout table suffix.
	 *
	 * @var string
	 */
	public const ABANDONED_CHECKOUT_TABLE =
		'eilmo_cf_abandoned_checkouts';

	/**
	 * Install or update plugin database tables.
	 *
	 * Safe to run multiple times.
	 *
	 * @return void
	 */
	public static function install(): void {

		self::create_blacklist_table();
		self::create_rate_limit_table();
		self::create_duplicate_order_table();
		self::create_security_log_table();
		self::create_abandoned_checkout_table();

		/*
		 * dbDelta() does not reliably remove columns or
		 * indexes that are no longer present in the
		 * CREATE TABLE definition.
		 *
		 * Explicitly remove legacy Abandoned Checkout
		 * recovery/order columns from older installs.
		 */
		self::cleanup_legacy_abandoned_checkout_schema();

		update_option(
			self::DB_VERSION_OPTION,
			self::DB_VERSION,
			false
		);
	}

	/**
	 * Get Blacklist table name.
	 *
	 * @return string
	 */
	public static function get_blacklist_table(): string {

		global $wpdb;

		return $wpdb->prefix .
			self::BLACKLIST_TABLE;
	}

	/**
	 * Create Customer Blacklist table.
	 *
	 * @return void
	 */
	private static function create_blacklist_table(): void {

		global $wpdb;

		$table_name =
			self::get_blacklist_table();

		$charset_collate =
			$wpdb->get_charset_collate();

		require_once ABSPATH .
			'wp-admin/includes/upgrade.php';

		$sql = "
			CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				type varchar(20) NOT NULL,
				value varchar(255) NOT NULL,
				normalized_value varchar(255) NOT NULL,
				reason text NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY type_value (type, normalized_value),
				KEY type (type),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset_collate};
		";

		dbDelta(
			$sql
		);
	}

	/**
	 * Get Rate Limit table name.
	 *
	 * @return string
	 */
	public static function get_rate_limit_table(): string {

		global $wpdb;

		return $wpdb->prefix .
			self::RATE_LIMIT_TABLE;
	}

	/**
	 * Create Rate Limit table.
	 *
	 * @return void
	 */
	private static function create_rate_limit_table(): void {

		global $wpdb;

		$table_name =
			self::get_rate_limit_table();

		$charset_collate =
			$wpdb->get_charset_collate();

		require_once ABSPATH .
			'wp-admin/includes/upgrade.php';

		$sql = "
			CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				identifier_hash char(64) NOT NULL,
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				window_started_at datetime NOT NULL,
				blocked_until datetime NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY identifier_hash (identifier_hash),
				KEY blocked_until (blocked_until),
				KEY updated_at (updated_at)
			) {$charset_collate};
		";

		dbDelta(
			$sql
		);
	}

	/**
	 * Get Duplicate Order table name.
	 *
	 * @return string
	 */
	public static function get_duplicate_order_table(): string {

		global $wpdb;

		return $wpdb->prefix .
			self::DUPLICATE_ORDER_TABLE;
	}

	/**
	 * Create Duplicate Order protection table.
	 *
	 * @return void
	 */
	private static function create_duplicate_order_table(): void {

		global $wpdb;

		$table_name =
			self::get_duplicate_order_table();

		$charset_collate =
			$wpdb->get_charset_collate();

		require_once ABSPATH .
			'wp-admin/includes/upgrade.php';

		$sql = "
			CREATE TABLE {$table_name} (
				fingerprint_hash char(64) NOT NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				expires_at datetime NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (fingerprint_hash),
				KEY order_id (order_id),
				KEY expires_at (expires_at)
			) {$charset_collate};
		";

		dbDelta(
			$sql
		);
	}

	/**
	 * Get Security Activity Log table name.
	 *
	 * @return string
	 */
	public static function get_security_log_table(): string {

		global $wpdb;

		return $wpdb->prefix .
			self::SECURITY_LOG_TABLE;
	}

	/**
	 * Create Security Activity Log table.
	 *
	 * Raw sensitive customer identifiers should not be
	 * stored in this table.
	 *
	 * @return void
	 */
	private static function create_security_log_table(): void {

		global $wpdb;

		$table_name =
			self::get_security_log_table();

		$charset_collate =
			$wpdb->get_charset_collate();

		require_once ABSPATH .
			'wp-admin/includes/upgrade.php';

		$sql = "
			CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				protection_type varchar(40) NOT NULL,
				checkout_source varchar(40) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'blocked',
				identifier_type varchar(20) NOT NULL DEFAULT '',
				identifier_hash char(64) NOT NULL DEFAULT '',
				identifier_masked varchar(190) NOT NULL DEFAULT '',
				reason_code varchar(100) NOT NULL DEFAULT '',
				message text NULL,
				http_status smallint(5) unsigned NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				target_type varchar(40) NOT NULL DEFAULT '',
				target_id bigint(20) unsigned NOT NULL DEFAULT 0,
				target_hash char(64) NOT NULL DEFAULT '',
				resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
				resolved_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY protection_type (protection_type),
				KEY checkout_source (checkout_source),
				KEY status (status),
				KEY identifier_hash (identifier_hash),
				KEY order_id (order_id),
				KEY target_record (target_type, target_id),
				KEY target_hash (target_hash),
				KEY resolved_by (resolved_by),
				KEY created_at (created_at),
				KEY updated_at (updated_at)
			) {$charset_collate};
		";

		dbDelta(
			$sql
		);
	}

	/**
	 * Get Abandoned Checkout table name.
	 *
	 * @return string
	 */
	public static function get_abandoned_checkout_table(): string {

		global $wpdb;

		return $wpdb->prefix .
			self::ABANDONED_CHECKOUT_TABLE;
	}

	/**
	 * Create Abandoned Checkout table.
	 *
	 * This feature is intentionally independent from
	 * WooCommerce order creation.
	 *
	 * Lifecycle statuses:
	 *
	 * active
	 *     Customer currently has meaningful checkout
	 *     activity.
	 *
	 * abandoned
	 *     Checkout has been inactive longer than the
	 *     configured abandonment threshold.
	 *
	 * recovered
	 *     Admin manually marked the customer/checkout
	 *     as recovered.
	 *
	 * converted
	 *     Admin manually marked the checkout as
	 *     converted.
	 *
	 * Abandoned Checkout never:
	 *
	 * - Creates WooCommerce orders.
	 * - Automatically changes status after an order.
	 * - Stores recovery links or recovery tokens.
	 * - Stores WooCommerce order relationships.
	 *
	 * cart_snapshot stores only safe checkout reference
	 * data required by the admin:
	 *
	 * - Selected products.
	 * - Quantities.
	 * - Variation IDs.
	 * - Combo Offer IDs.
	 * - Order Bump IDs.
	 *
	 * Never store:
	 *
	 * - Payment credentials.
	 * - Gateway tokens.
	 * - Card information.
	 * - Checkout nonces.
	 * - Arbitrary raw request payloads.
	 *
	 * @return void
	 */
	private static function create_abandoned_checkout_table(): void {

		global $wpdb;

		$table_name =
			self::get_abandoned_checkout_table();

		$charset_collate =
			$wpdb->get_charset_collate();

		require_once ABSPATH .
			'wp-admin/includes/upgrade.php';

		$sql = "
			CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_key char(64) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				checkout_source varchar(40) NOT NULL DEFAULT 'eilmo',
				customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				billing_first_name varchar(100) NOT NULL DEFAULT '',
				billing_last_name varchar(100) NOT NULL DEFAULT '',
				billing_phone varchar(100) NOT NULL DEFAULT '',
				billing_email varchar(190) NOT NULL DEFAULT '',
				billing_address_1 varchar(255) NOT NULL DEFAULT '',
				billing_address_2 varchar(255) NOT NULL DEFAULT '',
				billing_city varchar(100) NOT NULL DEFAULT '',
				billing_state varchar(100) NOT NULL DEFAULT '',
				billing_postcode varchar(50) NOT NULL DEFAULT '',
				billing_country varchar(10) NOT NULL DEFAULT '',
				cart_snapshot longtext NULL,
				delivery_method varchar(100) NOT NULL DEFAULT '',
				payment_method varchar(100) NOT NULL DEFAULT '',
				currency varchar(10) NOT NULL DEFAULT '',
				subtotal decimal(26,8) NOT NULL DEFAULT 0,
				total decimal(26,8) NOT NULL DEFAULT 0,
				first_seen_at datetime NOT NULL,
				last_activity_at datetime NOT NULL,
				abandoned_at datetime NULL,
				recovered_at datetime NULL,
				converted_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY session_key (session_key),
				KEY status (status),
				KEY checkout_source (checkout_source),
				KEY customer_id (customer_id),
				KEY billing_phone (billing_phone),
				KEY billing_email (billing_email),
				KEY last_activity_at (last_activity_at),
				KEY abandoned_at (abandoned_at),
				KEY recovered_at (recovered_at),
				KEY converted_at (converted_at),
				KEY created_at (created_at),
				KEY updated_at (updated_at)
			) {$charset_collate};
		";

		dbDelta(
			$sql
		);
	}

	/**
	 * Remove legacy Abandoned Checkout recovery/order
	 * schema from versions prior to DB version 6.
	 *
	 * dbDelta() is designed mainly to create/update
	 * schema and should not be relied on to remove old
	 * columns and indexes.
	 *
	 * Fresh installations simply skip these operations
	 * because the legacy fields do not exist.
	 *
	 * @return void
	 */
	private static function cleanup_legacy_abandoned_checkout_schema(): void {

		global $wpdb;

		$table_name =
			self::get_abandoned_checkout_table();

		if (
			! self::table_exists(
				$table_name
			)
		) {
			return;
		}

		/*
		 * Remove indexes before removing their columns.
		 */
		if (
			self::index_exists(
				$table_name,
				'recovery_token_hash'
			)
		) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP INDEX %i',
					$table_name,
					'recovery_token_hash'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		if (
			self::index_exists(
				$table_name,
				'order_id'
			)
		) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP INDEX %i',
					$table_name,
					'order_id'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		/*
		 * Remove obsolete recovery/order columns.
		 */
		if (
			self::column_exists(
				$table_name,
				'recovery_token_hash'
			)
		) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP COLUMN %i',
					$table_name,
					'recovery_token_hash'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		if (
			self::column_exists(
				$table_name,
				'order_id'
			)
		) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP COLUMN %i',
					$table_name,
					'order_id'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		if (
			self::column_exists(
				$table_name,
				'recovery_attempts'
			)
		) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Versioned migration intentionally changes an Eilmo-owned operational table.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP COLUMN %i',
					$table_name,
					'recovery_attempts'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}

	/**
	 * Determine whether database table exists.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return bool
	 */
	private static function table_exists(
		string $table_name
	): bool {

		global $wpdb;

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$wpdb->esc_like(
						$table_name
					)
				)
			);

		return (
			$table_name ===
				$result
		);
	}

	/**
	 * Determine whether table column exists.
	 *
	 * Column name is supplied only from hard-coded
	 * installer migration values.
	 *
	 * @param string $table_name  Table name.
	 * @param string $column_name Column name.
	 *
	 * @return bool
	 */
	private static function column_exists(
		string $table_name,
		string $column_name
	): bool {

		global $wpdb;

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS
					FROM %i
					LIKE %s",
					$table_name,
					$column_name
				)
			);

		return (
			$column_name ===
				$result
		);
	}

	/**
	 * Determine whether table index exists.
	 *
	 * Index name is supplied only from hard-coded
	 * installer migration values.
	 *
	 * @param string $table_name Table name.
	 * @param string $index_name Index name.
	 *
	 * @return bool
	 */
	private static function index_exists(
		string $table_name,
		string $index_name
	): bool {

		global $wpdb;

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_var(
				$wpdb->prepare(
					"SHOW INDEX
					FROM %i
					WHERE Key_name = %s",
					$table_name,
					$index_name
				)
			);

		return (
			null !==
				$result
		);
	}

	/**
	 * Determine whether database upgrade is required.
	 *
	 * @return bool
	 */
	public static function needs_upgrade(): bool {

		$installed_version =
			absint(
				get_option(
					self::DB_VERSION_OPTION,
					0
				)
			);

		return (
			$installed_version <
				self::DB_VERSION
		);
	}
}
