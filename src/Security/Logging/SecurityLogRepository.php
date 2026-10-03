<?php
/**
 * Security Activity Log repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Logging;

use EilmoCheckout\Core\Installer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Security Activity Log persistence.
 */
final class SecurityLogRepository {

	/**
	 * Allowed protection types.
	 *
	 * @var array<int,string>
	 */
	private const PROTECTION_TYPES = array(
		'blacklist_ip',
		'blacklist_phone',
		'blacklist_email',
		'rate_limit',
		'duplicate_order',
		'honeypot',
		'security_token',
		'minimum_checkout_time',
		'order_cooldown',
	);

	/**
	 * Allowed checkout sources.
	 *
	 * @var array<int,string>
	 */
	private const CHECKOUT_SOURCES = array(
		'eilmo',
		'woocommerce_classic',
		'woocommerce_block',
	);

	/**
	 * Allowed statuses.
	 *
	 * @var array<int,string>
	 */
	private const STATUSES = array(
		'blocked',
		'resolved',
	);

	/**
	 * Allowed identifier types.
	 *
	 * @var array<int,string>
	 */
	private const IDENTIFIER_TYPES = array(
		'ip',
		'phone',
		'email',
		'fingerprint',
		'customer_id',
		'customer',
	);

	/**
	 * Allowed persistent target types.
	 *
	 * @var array<int,string>
	 */
	private const TARGET_TYPES = array(
		'blacklist',
		'rate_limit',
		'duplicate_order',
	);

	/**
	 * Default logs per page.
	 *
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Maximum logs per page.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Default cleanup age.
	 *
	 * @var int
	 */
	private const DEFAULT_RETENTION_DAYS = 30;

	/**
	 * Insert Security Activity Log.
	 *
	 * Expected data:
	 *
	 * protection_type
	 * checkout_source
	 * status
	 * identifier_type
	 * identifier_hash
	 * identifier_masked
	 * reason_code
	 * message
	 * http_status
	 * order_id
	 * target_type
	 * target_id
	 * target_hash
	 *
	 * Raw IP, phone number or email address should
	 * never be passed as identifier_masked.
	 *
	 * @param array<string,mixed> $data Log data.
	 *
	 * @return int|WP_Error
	 */
	public function log(
		array $data
	) {

		global $wpdb;

		$table_name =
			Installer::get_security_log_table();

		$protection_type =
			$this->normalize_protection_type(
				$data[
					'protection_type'
				] ??
					''
			);

		if (
			'' ===
				$protection_type
		) {
			return new WP_Error(
				'invalid_security_log_type',
				__(
					'The security log type is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		$checkout_source =
			$this->normalize_checkout_source(
				$data[
					'checkout_source'
				] ??
					''
			);

		if ( '' === $checkout_source ) {
			$checkout_source =
				'eilmo';
		}

		$status =
			$this->normalize_status(
				$data[
					'status'
				] ??
					'blocked'
			);

		$identifier_type =
			$this->normalize_identifier_type(
				$data[
					'identifier_type'
				] ??
					''
			);

		$identifier_hash =
			$this->normalize_hash(
				$data[
					'identifier_hash'
				] ??
					''
			);

		$identifier_masked =
			sanitize_text_field(
				(string) (
					$data[
						'identifier_masked'
					] ??
						''
				)
			);

		if (
			strlen(
				$identifier_masked
			) > 190
		) {
			$identifier_masked =
				substr(
					$identifier_masked,
					0,
					190
				);
		}

		$reason_code =
			sanitize_key(
				(string) (
					$data[
						'reason_code'
					] ??
						''
				)
			);

		if (
			strlen(
				$reason_code
			) > 100
		) {
			$reason_code =
				substr(
					$reason_code,
					0,
					100
				);
		}

		$message =
			sanitize_textarea_field(
				(string) (
					$data[
						'message'
					] ??
						''
				)
			);

		$http_status =
			absint(
				$data[
					'http_status'
				] ??
					0
			);

		if (
			$http_status > 599
		) {
			$http_status = 0;
		}

		$order_id =
			absint(
				$data[
					'order_id'
				] ??
					0
			);

		$target_type =
			$this->normalize_target_type(
				$data[
					'target_type'
				] ??
					''
			);

		$target_id =
			absint(
				$data[
					'target_id'
				] ??
					0
			);

		$target_hash =
			$this->normalize_hash(
				$data[
					'target_hash'
				] ??
					''
			);

		$now =
			current_time(
				'mysql',
				true
			);

		$inserted =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->insert(
				$table_name,
				array(
					'protection_type' =>
						$protection_type,

					'checkout_source' =>
						$checkout_source,

					'status' =>
						$status,

					'identifier_type' =>
						$identifier_type,

					'identifier_hash' =>
						$identifier_hash,

					'identifier_masked' =>
						$identifier_masked,

					'reason_code' =>
						$reason_code,

					'message' =>
						$message,

					'http_status' =>
						$http_status,

					'order_id' =>
						$order_id,

					'target_type' =>
						$target_type,

					'target_id' =>
						$target_id,

					'target_hash' =>
						$target_hash,

					'resolved_by' =>
						0,

					'resolved_at' =>
						null,

					'created_at' =>
						$now,

					'updated_at' =>
						$now,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%s',
					'%d',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);

		if (
			false ===
				$inserted
		) {
			return new WP_Error(
				'security_log_insert_failed',
				__(
					'The security activity could not be recorded.',
					'eilmo-checkout-flow'
				)
			);
		}

		$log_id =
			absint(
				$wpdb->insert_id
			);

		/**
		 * Fires after a Security Activity Log
		 * record is created.
		 *
		 * @param int                 $log_id Log ID.
		 * @param array<string,mixed> $data   Original data.
		 */
		do_action(
			'eilmo_cf/security/log_created',
			$log_id,
			$data
		);

		return $log_id;
	}

	/**
	 * Get Security Activity Logs.
	 *
	 * Supported arguments:
	 *
	 * page
	 * per_page
	 * protection_type
	 * checkout_source
	 * status
	 * identifier_type
	 * order_id
	 * target_type
	 * search
	 * date_from
	 * date_to
	 * orderby
	 * order
	 *
	 * @param array<string,mixed> $args Arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function get_logs(
		array $args = array()
	): array {

		global $wpdb;

		$table_name =
			Installer::get_security_log_table();

		$args =
			wp_parse_args(
				$args,
				array(
					'page' =>
						1,

					'per_page' =>
						self::DEFAULT_PER_PAGE,

					'protection_type' =>
						'',

					'checkout_source' =>
						'',

					'status' =>
						'',

					'identifier_type' =>
						'',

					'order_id' =>
						0,

					'target_type' =>
						'',

					'search' =>
						'',

					'date_from' =>
						'',

					'date_to' =>
						'',

					'orderby' =>
						'created_at',

					'order' =>
						'DESC',
				)
			);

		$page =
			max(
				1,
				absint(
					$args[
						'page'
					]
				)
			);

		$per_page =
			absint(
				$args[
					'per_page'
				]
			);

		if (
			$per_page <= 0
		) {
			$per_page =
				self::DEFAULT_PER_PAGE;
		}

		$per_page =
			min(
				self::MAX_PER_PAGE,
				$per_page
			);

		$where =
			array(
				'1=1',
			);

		$values =
			array();

		$protection_type =
			$this->normalize_protection_type(
				$args[
					'protection_type'
				]
			);

		if (
			'' !==
				$protection_type
		) {
			$where[] =
				'protection_type = %s';

			$values[] =
				$protection_type;
		}

		$checkout_source =
			$this->normalize_checkout_source(
				$args[
					'checkout_source'
				]
			);

		if (
			'' !==
				$checkout_source
		) {
			$where[] =
				'checkout_source = %s';

			$values[] =
				$checkout_source;
		}

		$status =
			$this->normalize_status(
				$args[
					'status'
				],
				true
			);

		if (
			'' !==
				$status
		) {
			$where[] =
				'status = %s';

			$values[] =
				$status;
		}

		$identifier_type =
			$this->normalize_identifier_type(
				$args[
					'identifier_type'
				]
			);

		if (
			'' !==
				$identifier_type
		) {
			$where[] =
				'identifier_type = %s';

			$values[] =
				$identifier_type;
		}

		$order_id =
			absint(
				$args[
					'order_id'
				]
			);

		if (
			$order_id > 0
		) {
			$where[] =
				'order_id = %d';

			$values[] =
				$order_id;
		}

		$target_type =
			$this->normalize_target_type(
				$args[
					'target_type'
				]
			);

		if (
			'' !==
				$target_type
		) {
			$where[] =
				'target_type = %s';

			$values[] =
				$target_type;
		}

		$search =
			sanitize_text_field(
				(string) (
					$args[
						'search'
					] ??
						''
				)
			);

		if (
			'' !==
				$search
		) {
			$like =
				'%' .
				$wpdb->esc_like(
					$search
				) .
				'%';

			$where[] =
				'(
					protection_type LIKE %s
					OR checkout_source LIKE %s
					OR identifier_masked LIKE %s
					OR reason_code LIKE %s
					OR message LIKE %s
				)';

			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$date_from =
			$this->normalize_date(
				$args[
					'date_from'
				] ??
					''
			);

		if (
			'' !==
				$date_from
		) {
			$where[] =
				'created_at >= %s';

			$values[] =
				$date_from .
				' 00:00:00';
		}

		$date_to =
			$this->normalize_date(
				$args[
					'date_to'
				] ??
					''
			);

		if (
			'' !==
				$date_to
		) {
			$where[] =
				'created_at <= %s';

			$values[] =
				$date_to .
				' 23:59:59';
		}

		$where_sql =
			implode(
				' AND ',
				$where
			);

		$orderby =
			$this->normalize_orderby(
				$args[
					'orderby'
				]
			);

		$order =
			'ASC' ===
				strtoupper(
					(string) $args[
						'order'
					]
				)
				? 'ASC'
				: 'DESC';

		$offset =
			(
				$page -
				1
			) *
			$per_page;

		$count_values =
			array_merge(
				array(
					$table_name,
				),
				$values
			);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE clauses determine the matching dynamic argument list.
		$count_sql =
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM %i
				WHERE {$where_sql}",
				...$count_values
			);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$total =
			absint(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared above from a strict identifier and fixed clauses; this live admin count must not be stale.
				$wpdb->get_var(
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
					$count_sql
				)
			);

		$query_values =
			array_merge(
				array(
					$table_name,
				),
				$values,
				array(
					$orderby,
					$per_page,
					$offset,
				)
			);

		if ( 'ASC' === $order ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE/order clauses determine the matching dynamic argument list.
			$query =
				$wpdb->prepare(
					"SELECT *
					FROM %i
					WHERE {$where_sql}
					ORDER BY %i ASC
					LIMIT %d OFFSET %d",
					...$query_values
				);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE/order clauses determine the matching dynamic argument list.
			$query =
				$wpdb->prepare(
					"SELECT *
					FROM %i
					WHERE {$where_sql}
					ORDER BY %i DESC
					LIMIT %d OFFSET %d",
					...$query_values
				);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}

		$items =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders and fixed WHERE clauses above.
			$wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		if (
			! is_array(
				$items
			)
		) {
			$items =
				array();
		}

		$total_pages =
			$total > 0
				? (int) ceil(
					$total /
					$per_page
				)
				: 0;

		return array(
			'items' =>
				array_values(
					$items
				),

			'total' =>
				$total,

			'page' =>
				$page,

			'per_page' =>
				$per_page,

			'total_pages' =>
				$total_pages,
		);
	}

	/**
	 * Get one log by ID.
	 *
	 * @param int $log_id Log ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_by_id(
		int $log_id
	): ?array {

		global $wpdb;

		$log_id =
			absint(
				$log_id
			);

		if (
			$log_id <= 0
		) {
			return null;
		}

		$table_name =
			Installer::get_security_log_table();

		$sql =
			$wpdb->prepare(
				"SELECT *
				FROM %i
				WHERE id = %d
				LIMIT 1",
				$table_name,
				$log_id
			);

		$record =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$sql,
				ARRAY_A
			);

		return is_array(
			$record
		)
			? $record
			: null;
	}

	/**
	 * Mark Security Activity Log as resolved.
	 *
	 * This does NOT remove the underlying block.
	 *
	 * Actual Blacklist / Rate Limit / Duplicate Order
	 * release must happen before this method is called.
	 *
	 * @param int $log_id  Log ID.
	 * @param int $user_id Resolving user ID.
	 *
	 * @return bool
	 */
	public function mark_resolved(
		int $log_id,
		int $user_id = 0
	): bool {

		global $wpdb;

		$log_id =
			absint(
				$log_id
			);

		if (
			$log_id <= 0
		) {
			return false;
		}

		if (
			$user_id <= 0
		) {
			$user_id =
				get_current_user_id();
		}

		$now =
			current_time(
				'mysql',
				true
			);

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_security_log_table(),
				array(
					'status' =>
						'resolved',

					'resolved_by' =>
						absint(
							$user_id
						),

					'resolved_at' =>
						$now,

					'updated_at' =>
						$now,
				),
				array(
					'id' =>
						$log_id,
				),
				array(
					'%s',
					'%d',
					'%s',
					'%s',
				),
				array(
					'%d',
				)
			);

		if (
			false ===
				$updated
		) {
			return false;
		}

		/**
		 * Fires after a Security Activity Log is marked
		 * as resolved.
		 *
		 * @param int $log_id  Log ID.
		 * @param int $user_id User ID.
		 */
		do_action(
			'eilmo_cf/security/log_resolved',
			$log_id,
			absint(
				$user_id
			)
		);

		return true;
	}

	/**
	 * Mark all matching target logs as resolved.
	 *
	 * Useful when one Unblock action resolves multiple
	 * Activity Log entries associated with the same
	 * underlying protection record.
	 *
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target row ID.
	 * @param string $target_hash Target hash.
	 * @param int    $user_id     Admin user ID.
	 *
	 * @return int|false
	 */
	public function mark_target_resolved(
		string $target_type,
		int $target_id = 0,
		string $target_hash = '',
		int $user_id = 0
	) {

		global $wpdb;

		$target_type =
			$this->normalize_target_type(
				$target_type
			);

		if (
			'' ===
				$target_type
		) {
			return false;
		}

		$target_id =
			absint(
				$target_id
			);

		$target_hash =
			$this->normalize_hash(
				$target_hash
			);

		if (
			$target_id <= 0 &&
			'' ===
				$target_hash
		) {
			return false;
		}

		if (
			$user_id <= 0
		) {
			$user_id =
				get_current_user_id();
		}

		$table_name =
			Installer::get_security_log_table();

		$where =
			array(
				'status = %s',
				'target_type = %s',
			);

		$where_values =
			array(
				'blocked',
				$target_type,
			);

		if (
			$target_id > 0
		) {
			$where[] =
				'target_id = %d';

			$where_values[] =
				$target_id;
		}

		if (
			'' !==
				$target_hash
		) {
			$where[] =
				'target_hash = %s';

			$where_values[] =
				$target_hash;
		}

		$where_sql =
			implode(
				' AND ',
				$where
			);

		$now =
			current_time(
				'mysql',
				true
			);

		$query_values =
			array_merge(
				array(
					$table_name,
					'resolved',
					absint(
						$user_id
					),
					$now,
					$now,
				),
				$where_values
			);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE clauses determine the matching dynamic argument list.
		$sql =
			$wpdb->prepare(
				"UPDATE %i
				SET
					status = %s,
					resolved_by = %d,
					resolved_at = %s,
					updated_at = %s
				WHERE {$where_sql}",
				...$query_values
			);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders and fixed WHERE clauses above.
		return $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
			$sql
		);
	}

	/**
	 * Delete one Activity Log record.
	 *
	 * Important:
	 *
	 * Deleting the log does NOT unblock anything.
	 *
	 * @param int $log_id Log ID.
	 *
	 * @return bool
	 */
	public function delete(
		int $log_id
	): bool {

		global $wpdb;

		$log_id =
			absint(
				$log_id
			);

		if (
			$log_id <= 0
		) {
			return false;
		}

		$deleted =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->delete(
				Installer::get_security_log_table(),
				array(
					'id' =>
						$log_id,
				),
				array(
					'%d',
				)
			);

		return (
			false !==
				$deleted &&
			$deleted > 0
		);
	}

	/**
	 * Delete old Security Activity Logs.
	 *
	 * Resolved persistent logs are eligible for cleanup
	 * after the configured retention period has elapsed
	 * from their resolution time.
	 *
	 * Non-persistent security events have no target_type
	 * and therefore no underlying block that an admin can
	 * manually release. Those event-only records are
	 * eligible for cleanup after the retention period
	 * from their creation time.
	 *
	 * Active persistent blocked records are intentionally
	 * retained, even if old, so administrators do not lose
	 * visibility into unresolved Blacklist, Rate Limit or
	 * Duplicate Order blocks.
	 *
	 * @param int $days Retention days.
	 *
	 * @return int|false
	 */
	public function cleanup_old_logs(
		int $days = self::DEFAULT_RETENTION_DAYS
	) {

		global $wpdb;

		$days =
			max(
				1,
				absint(
					$days
				)
			);

		/**
		 * Filters Security Activity Log retention.
		 *
		 * @param int $days Retention days.
		 */
		$days =
			absint(
				apply_filters(
					'eilmo_cf/security/log_retention_days',
					$days
				)
			);

		$days =
			max(
				1,
				$days
			);

		$cutoff_timestamp =
			time() -
			(
				$days *
				DAY_IN_SECONDS
			);

		$cutoff =
			gmdate(
				'Y-m-d H:i:s',
				$cutoff_timestamp
			);

		$table_name =
			Installer::get_security_log_table();

		$sql =
			$wpdb->prepare(
				"DELETE FROM %i
				WHERE (
					status = %s
					AND COALESCE(
						resolved_at,
						updated_at,
						created_at
					) < %s
				)
				OR (
					target_type = %s
					AND created_at < %s
				)",
				$table_name,
				'resolved',
				$cutoff,
				'',
				$cutoff
			);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
		return $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
			$sql
		);
	}

	/**
	 * Create privacy-safe HMAC identifier hash.
	 *
	 * The raw identifier is never persisted by this
	 * method.
	 *
	 * @param string $value Normalized identifier.
	 *
	 * @return string
	 */
	public function hash_identifier(
		string $value
	): string {

		$value =
			trim(
				$value
			);

		if (
			'' ===
				$value
		) {
			return '';
		}

		return hash_hmac(
			'sha256',
			$value,
			wp_salt(
				'auth'
			)
		);
	}

	/**
	 * Create safe masked identifier for admin display.
	 *
	 * @param string $type  Identifier type.
	 * @param string $value Identifier.
	 *
	 * @return string
	 */
	public function mask_identifier(
		string $type,
		string $value
	): string {

		$type =
			$this->normalize_identifier_type(
				$type
			);

		$value =
			trim(
				$value
			);

		if (
			'' ===
				$type ||
			'' ===
				$value
		) {
			return '';
		}

		switch ( $type ) {

			case 'ip':
				return $this->mask_ip(
					$value
				);

			case 'phone':
				return $this->mask_phone(
					$value
				);

			case 'email':
				return $this->mask_email(
					$value
				);

			case 'fingerprint':
				return $this->mask_hash(
					$value
				);

			case 'customer_id':
				return __(
					'Customer account',
					'eilmo-checkout-flow'
				);

			case 'customer':
				return __(
					'Customer',
					'eilmo-checkout-flow'
				);

			default:
				return '';
		}
	}

	/**
	 * Normalize protection type.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function normalize_protection_type(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return in_array(
			$value,
			self::PROTECTION_TYPES,
			true
		)
			? $value
			: '';
	}

	/**
	 * Normalize checkout source.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function normalize_checkout_source(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return in_array(
			$value,
			self::CHECKOUT_SOURCES,
			true
		)
			? $value
			: '';
	}

	/**
	 * Normalize status.
	 *
	 * @param mixed $value       Value.
	 * @param bool  $allow_empty Allow empty.
	 *
	 * @return string
	 */
	private function normalize_status(
		$value,
		bool $allow_empty = false
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		if (
			$allow_empty &&
			'' ===
				$value
		) {
			return '';
		}

		return in_array(
			$value,
			self::STATUSES,
			true
		)
			? $value
			: (
				$allow_empty
					? ''
					: 'blocked'
			);
	}

	/**
	 * Normalize identifier type.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function normalize_identifier_type(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return in_array(
			$value,
			self::IDENTIFIER_TYPES,
			true
		)
			? $value
			: '';
	}

	/**
	 * Normalize target type.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function normalize_target_type(
		$value
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return in_array(
			$value,
			self::TARGET_TYPES,
			true
		)
			? $value
			: '';
	}

	/**
	 * Normalize SHA-256 hash.
	 *
	 * @param mixed $value Hash.
	 *
	 * @return string
	 */
	private function normalize_hash(
		$value
	): string {

		if (
			! is_scalar(
				$value
			)
		) {
			return '';
		}

		$value =
			strtolower(
				trim(
					(string) $value
				)
			);

		if (
			64 !==
				strlen(
					$value
				) ||
			1 !==
				preg_match(
					'/^[a-f0-9]{64}$/',
					$value
				)
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Normalize YYYY-MM-DD date.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function normalize_date(
		$value
	): string {

		$value =
			sanitize_text_field(
				(string) $value
			);

		if (
			1 !==
				preg_match(
					'/^\d{4}-\d{2}-\d{2}$/',
					$value
				)
		) {
			return '';
		}

		$parts =
			array_map(
				'absint',
				explode(
					'-',
					$value
				)
			);

		if (
			3 !==
				count(
					$parts
				) ||
			! checkdate(
				$parts[1],
				$parts[2],
				$parts[0]
			)
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Normalize ORDER BY.
	 *
	 * @param mixed $orderby Order by.
	 *
	 * @return string
	 */
	private function normalize_orderby(
		$orderby
	): string {

		$allowed =
			array(
				'id',
				'protection_type',
				'checkout_source',
				'status',
				'http_status',
				'order_id',
				'resolved_at',
				'created_at',
				'updated_at',
			);

		$orderby =
			sanitize_key(
				(string) $orderby
			);

		return in_array(
			$orderby,
			$allowed,
			true
		)
			? $orderby
			: 'created_at';
	}

	/**
	 * Mask IP address.
	 *
	 * IPv4:
	 * 192.168.20.55 → 192.168.*.*
	 *
	 * IPv6:
	 * Keeps only the first two segments visible.
	 *
	 * @param string $value IP.
	 *
	 * @return string
	 */
	private function mask_ip(
		string $value
	): string {

		if (
			false ===
				filter_var(
					$value,
					FILTER_VALIDATE_IP
				)
		) {
			return '';
		}

		if (
			false !==
				strpos(
					$value,
					':'
				)
		) {
			$parts =
				explode(
					':',
					$value
				);

			$visible =
				array_slice(
					$parts,
					0,
					2
				);

			return implode(
				':',
				$visible
			) .
				':****';
		}

		$parts =
			explode(
				'.',
				$value
			);

		if (
			4 !==
				count(
					$parts
				)
		) {
			return '';
		}

		return sprintf(
			'%1$s.%2$s.*.*',
			$parts[0],
			$parts[1]
		);
	}

	/**
	 * Mask phone number.
	 *
	 * @param string $value Phone.
	 *
	 * @return string
	 */
	private function mask_phone(
		string $value
	): string {

		$digits =
			preg_replace(
				'/\D+/',
				'',
				$value
			);

		if (
			! is_string(
				$digits
			) ||
			'' ===
				$digits
		) {
			return '';
		}

		$length =
			strlen(
				$digits
			);

		if (
			$length <= 4
		) {
			return str_repeat(
				'*',
				$length
			);
		}

		$prefix_length =
			min(
				3,
				max(
					1,
					$length - 4
				)
			);

		$suffix_length =
			min(
				4,
				$length -
					$prefix_length
			);

		$hidden_length =
			max(
				1,
				$length -
					$prefix_length -
					$suffix_length
			);

		return substr(
			$digits,
			0,
			$prefix_length
		) .
			str_repeat(
				'*',
				$hidden_length
			) .
			substr(
				$digits,
				-$suffix_length
			);
	}

	/**
	 * Mask email.
	 *
	 * Example:
	 *
	 * john@example.com → j***@example.com
	 *
	 * @param string $value Email.
	 *
	 * @return string
	 */
	private function mask_email(
		string $value
	): string {

		$value =
			sanitize_email(
				$value
			);

		if (
			'' ===
				$value ||
			false ===
				strpos(
					$value,
					'@'
				)
		) {
			return '';
		}

		list(
			$local,
			$domain
		) =
			explode(
				'@',
				$value,
				2
			);

		if (
			'' ===
				$local ||
			'' ===
				$domain
		) {
			return '';
		}

		return substr(
			$local,
			0,
			1
		) .
			'***@' .
			$domain;
	}

	/**
	 * Mask security hash.
	 *
	 * @param string $value Hash.
	 *
	 * @return string
	 */
	private function mask_hash(
		string $value
	): string {

		$value =
			$this->normalize_hash(
				$value
			);

		if (
			'' ===
				$value
		) {
			return '';
		}

		return substr(
			$value,
			0,
			8
		) .
			'…' .
			substr(
				$value,
				-6
			);
	}
}
