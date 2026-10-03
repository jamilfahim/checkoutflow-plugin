<?php
/**
 * Customer Blacklist repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Blacklist;

use EilmoCheckout\Core\Installer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Customer Blacklist database records.
 */
final class BlacklistRepository {

	/**
	 * Active status.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Inactive status.
	 *
	 * @var string
	 */
	public const STATUS_INACTIVE = 'inactive';

	/**
	 * Add blacklist entry.
	 *
	 * @param string $type   Entry type.
	 * @param mixed  $value  Entry value.
	 * @param mixed  $reason Reason.
	 *
	 * @return int|WP_Error
	 */
	public function add(
		string $type,
		$value,
		$reason = ''
	) {

		global $wpdb;

		$type =
			BlacklistNormalizer::normalize_type(
				$type
			);

		if ( '' === $type ) {
			return new WP_Error(
				'eilmo_cf_invalid_blacklist_type',
				__(
					'Please select a valid blacklist type.',
					'eilmo-checkout-flow'
				)
			);
		}

		$display_value =
			BlacklistNormalizer::sanitize_display_value(
				$type,
				$value
			);

		$normalized_value =
			BlacklistNormalizer::normalize_for_blacklist(
				$type,
				$value
			);

		if (
			'' === $display_value ||
			'' === $normalized_value
		) {
			return new WP_Error(
				'eilmo_cf_invalid_blacklist_value',
				__(
					'Please enter a valid blacklist value.',
					'eilmo-checkout-flow'
				)
			);
		}

		$existing =
			$this->find(
				$type,
				$normalized_value
			);

		if ( null !== $existing ) {

			/*
			 * Existing inactive entries are restored
			 * instead of creating duplicates.
			 *
			 * normalized_value is refreshed as well so
			 * an entry restored after the administrator
			 * changes the phone-normalization preference
			 * adopts the current matching mode.
			 */
			if (
				self::STATUS_INACTIVE ===
				(
					$existing[
						'status'
					] ??
						''
				)
			) {
				$updated =
					$this->reactivate(
						(int) (
							$existing[
								'id'
							] ??
								0
						),
						$display_value,
						$normalized_value,
						(string) $reason
					);

				if ( $updated ) {
					return (int) (
						$existing[
							'id'
						] ??
							0
					);
				}

				return new WP_Error(
					'eilmo_cf_blacklist_restore_failed',
					__(
						'The blacklist entry could not be restored.',
						'eilmo-checkout-flow'
					)
				);
			}

			return new WP_Error(
				'eilmo_cf_blacklist_exists',
				__(
					'This value is already on the blacklist.',
					'eilmo-checkout-flow'
				)
			);
		}

		$now =
			current_time(
				'mysql',
				true
			);

		$inserted =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->insert(
				Installer::get_blacklist_table(),
				array(
					'type' =>
						$type,

					'value' =>
						$display_value,

					'normalized_value' =>
						$normalized_value,

					'reason' =>
						sanitize_textarea_field(
							(string) $reason
						),

					'status' =>
						self::STATUS_ACTIVE,

					'created_by' =>
						get_current_user_id(),

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
					'%d',
					'%s',
					'%s',
				)
			);

		if ( false === $inserted ) {
			return new WP_Error(
				'eilmo_cf_blacklist_insert_failed',
				__(
					'The blacklist entry could not be added.',
					'eilmo-checkout-flow'
				)
			);
		}

		return (int)
			$wpdb->insert_id;
	}

	/**
	 * Get blacklist entries.
	 *
	 * @param string $status Status filter.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_all(
		string $status = self::STATUS_ACTIVE
	): array {

		global $wpdb;

		$status =
			$this->sanitize_status(
				$status
			);

		$table =
			Installer::get_blacklist_table();

		$query =
			$wpdb->prepare(
				"SELECT
					id,
					type,
					value,
					normalized_value,
					reason,
					status,
					created_by,
					created_at,
					updated_at
				FROM %i
				WHERE status = %s
				ORDER BY id DESC",
				$table,
				$status
			);

		$results =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array( $results )
			? $results
			: array();
	}

	/**
	 * Get one blacklist entry by ID.
	 *
	 * @param int $id Entry ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get(
		int $id
	): ?array {

		global $wpdb;

		$id =
			absint(
				$id
			);

		if ( $id <= 0 ) {
			return null;
		}

		$table =
			Installer::get_blacklist_table();

		$query =
			$wpdb->prepare(
				"SELECT
					id,
					type,
					value,
					normalized_value,
					reason,
					status,
					created_by,
					created_at,
					updated_at
				FROM %i
				WHERE id = %d
				LIMIT 1",
				$table,
				$id
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array( $result )
			? $result
			: null;
	}

	/**
	 * Determine whether a blacklist value is active.
	 *
	 * Raw values can be passed here. Matching always
	 * respects the current Blacklist phone-normalization
	 * preference.
	 *
	 * @param string $type  Type.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	public function is_blocked(
		string $type,
		$value
	): bool {

		$type =
			BlacklistNormalizer::normalize_type(
				$type
			);

		if ( '' === $type ) {
			return false;
		}

		$normalized =
			BlacklistNormalizer::normalize_for_blacklist(
				$type,
				$value
			);

		if ( '' === $normalized ) {
			return false;
		}

		$entry =
			$this->find(
				$type,
				$normalized
			);

		if ( null === $entry ) {
			return false;
		}

		return (
			self::STATUS_ACTIVE ===
				(
					$entry[
						'status'
					] ??
						''
				)
		);
	}

	/**
	 * Find a blacklist record using the value produced by
	 * BlacklistNormalizer::normalize_for_blacklist().
	 *
	 * Phone records require one compatibility detail:
	 *
	 * - With phone normalization ON, normalized_value is
	 *   queried first. If an older record was created
	 *   while exact-format matching was enabled, its
	 *   stored display value is re-normalized in PHP and
	 *   compared as a fallback.
	 *
	 * - With phone normalization OFF, exact-format
	 *   matching is authoritative, so the display value
	 *   column is queried directly. This prevents an old
	 *   canonical normalized_value from incorrectly
	 *   matching a differently formatted number.
	 *
	 * This makes changing the setting safe for existing
	 * blacklist rows without rewriting the full table.
	 *
	 * @param string $type             Type.
	 * @param string $normalized_value Current-mode normalized value.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find(
		string $type,
		string $normalized_value
	): ?array {

		$type =
			BlacklistNormalizer::normalize_type(
				$type
			);

		$normalized_value =
			sanitize_text_field(
				trim(
					$normalized_value
				)
			);

		if (
			'' === $type ||
			'' === $normalized_value
		) {
			return null;
		}

		if (
			'phone' === $type &&
			! BlacklistNormalizer::is_blacklist_phone_normalization_enabled()
		) {
			return $this->find_phone_exact(
				$normalized_value
			);
		}

		$record =
			$this->find_by_normalized_value(
				$type,
				$normalized_value
			);

		if (
			null !== $record ||
			'phone' !== $type
		) {
			return $record;
		}

		return $this->find_phone_normalized_fallback(
			$normalized_value
		);
	}

	/**
	 * Deactivate blacklist entry.
	 *
	 * We intentionally use soft deletion.
	 *
	 * @param int $id Entry ID.
	 *
	 * @return bool
	 */
	public function remove(
		int $id
	): bool {

		global $wpdb;

		$id =
			absint(
				$id
			);

		if ( $id <= 0 ) {
			return false;
		}

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_blacklist_table(),
				array(
					'status' =>
						self::STATUS_INACTIVE,

					'updated_at' =>
						current_time(
							'mysql',
							true
						),
				),
				array(
					'id' =>
						$id,
				),
				array(
					'%s',
					'%s',
				),
				array(
					'%d',
				)
			);

		return false !== $updated;
	}

	/**
	 * Permanently delete blacklist entry.
	 *
	 * @param int $id Entry ID.
	 *
	 * @return bool
	 */
	public function delete(
		int $id
	): bool {

		global $wpdb;

		$id =
			absint(
				$id
			);

		if ( $id <= 0 ) {
			return false;
		}

		$deleted =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->delete(
				Installer::get_blacklist_table(),
				array(
					'id' =>
						$id,
				),
				array(
					'%d',
				)
			);

		return false !== $deleted;
	}

	/**
	 * Find record by normalized_value.
	 *
	 * @param string $type             Type.
	 * @param string $normalized_value Normalized value.
	 *
	 * @return array<string,mixed>|null
	 */
	private function find_by_normalized_value(
		string $type,
		string $normalized_value
	): ?array {

		global $wpdb;

		$table =
			Installer::get_blacklist_table();

		$query =
			$wpdb->prepare(
				"SELECT
					id,
					type,
					value,
					normalized_value,
					reason,
					status,
					created_by,
					created_at,
					updated_at
				FROM %i
				WHERE type = %s
					AND normalized_value = %s
				ORDER BY id DESC
				LIMIT 1",
				$table,
				$type,
				$normalized_value
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array( $result )
			? $result
			: null;
	}

	/**
	 * Find phone record using exact stored display value.
	 *
	 * Used only when phone normalization is disabled.
	 *
	 * @param string $value Exact sanitized phone value.
	 *
	 * @return array<string,mixed>|null
	 */
	private function find_phone_exact(
		string $value
	): ?array {

		global $wpdb;

		$table =
			Installer::get_blacklist_table();

		$query =
			$wpdb->prepare(
				"SELECT
					id,
					type,
					value,
					normalized_value,
					reason,
					status,
					created_by,
					created_at,
					updated_at
				FROM %i
				WHERE type = %s
					AND value = %s
				ORDER BY id DESC
				LIMIT 1",
				$table,
				'phone',
				$value
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array( $result )
			? $result
			: null;
	}

	/**
	 * Compatibility lookup for phone rows created while a
	 * different phone-normalization mode was active.
	 *
	 * This runs only when direct normalized lookup misses.
	 *
	 * @param string $normalized_value Current normalized phone.
	 *
	 * @return array<string,mixed>|null
	 */
	private function find_phone_normalized_fallback(
		string $normalized_value
	): ?array {

		global $wpdb;

		$table =
			Installer::get_blacklist_table();

		$query =
			$wpdb->prepare(
				"SELECT
				id,
				type,
				value,
				normalized_value,
				reason,
				status,
				created_by,
				created_at,
				updated_at
			FROM %i
			WHERE type = %s
			ORDER BY id DESC",
				$table,
				'phone'
			);

		$rows =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$current =
				BlacklistNormalizer::normalize_for_blacklist(
					'phone',
					$row[
						'value'
					] ??
						''
				);

			if (
				'' !== $current &&
				hash_equals(
					$normalized_value,
					$current
				)
			) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Reactivate an existing blacklist entry.
	 *
	 * @param int    $id               Entry ID.
	 * @param string $display_value    Display value.
	 * @param string $normalized_value Current-mode normalized value.
	 * @param string $reason           Reason.
	 *
	 * @return bool
	 */
	private function reactivate(
		int $id,
		string $display_value,
		string $normalized_value,
		string $reason
	): bool {

		global $wpdb;

		$id =
			absint(
				$id
			);

		if (
			$id <= 0 ||
			'' === $display_value ||
			'' === $normalized_value
		) {
			return false;
		}

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_blacklist_table(),
				array(
					'value' =>
						$display_value,

					'normalized_value' =>
						$normalized_value,

					'reason' =>
						sanitize_textarea_field(
							$reason
						),

					'status' =>
						self::STATUS_ACTIVE,

					'created_by' =>
						get_current_user_id(),

					'updated_at' =>
						current_time(
							'mysql',
							true
						),
				),
				array(
					'id' =>
						$id,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
				),
				array(
					'%d',
				)
			);

		return false !== $updated;
	}

	/**
	 * Sanitize status.
	 *
	 * @param mixed $status Status.
	 *
	 * @return string
	 */
	private function sanitize_status(
		$status
	): string {

		$status =
			sanitize_key(
				(string) $status
			);

		return in_array(
			$status,
			array(
				self::STATUS_ACTIVE,
				self::STATUS_INACTIVE,
			),
			true
		)
			? $status
			: self::STATUS_ACTIVE;
	}
}
