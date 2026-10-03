<?php
/**
 * Abandoned Checkout repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AbandonedCheckout;

use EilmoCheckout\Core\Installer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Abandoned Checkout database repository.
 */
final class AbandonedCheckoutRepository {

	/**
	 * Active status.
	 */
	public const STATUS_ACTIVE =
		'active';

	/**
	 * Abandoned status.
	 */
	public const STATUS_ABANDONED =
		'abandoned';

	/**
	 * Recovered status.
	 */
	public const STATUS_RECOVERED =
		'recovered';

	/**
	 * Converted status.
	 */
	public const STATUS_CONVERTED =
		'converted';

	/**
	 * Checkout source.
	 */
	public const SOURCE_EILMO =
		'eilmo';

	/**
	 * Classic WooCommerce checkout source.
	 */
	public const SOURCE_CLASSIC =
		'classic';

	/**
	 * WooCommerce Checkout Block source.
	 */
	public const SOURCE_BLOCK =
		'block';

	/**
	 * Save checkout session.
	 *
	 * @param string              $session_key Session hash.
	 * @param array<string,mixed> $data        Data.
	 *
	 * @return int|WP_Error
	 */
	public function save(
		string $session_key,
		array $data
	) {

		$session_key =
			$this->normalize_hash(
				$session_key
			);

		if ( '' === $session_key ) {
			return new WP_Error(
				'eilmo_cf_invalid_session_key',
				__(
					'Invalid checkout session.',
					'eilmo-checkout-flow'
				)
			);
		}

		$existing =
			$this->get_by_session(
				$session_key
			);

		if (
			is_array(
				$existing
			)
		) {
			$updated =
				$this->update(
					absint(
						$existing[
							'id'
						] ??
							0
					),
					$data
				);

			if ( ! $updated ) {
				return new WP_Error(
					'eilmo_cf_checkout_update_failed',
					__(
						'Checkout activity could not be updated.',
						'eilmo-checkout-flow'
					)
				);
			}

			return absint(
				$existing[
					'id'
				] ??
					0
			);
		}

		return $this->insert(
			$session_key,
			$data
		);
	}

	/**
	 * Get checkout by ID.
	 *
	 * @param int $id Checkout ID.
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
			Installer::get_abandoned_checkout_table();

		$row =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_row(
				$wpdb->prepare(
					"SELECT *
					FROM %i
					WHERE id = %d
					LIMIT 1",
					$table,
					$id
				),
				ARRAY_A
			);

		return is_array(
			$row
		)
			? $this->hydrate_record(
				$row
			)
			: null;
	}

	/**
	 * Get checkout by session hash.
	 *
	 * @param string $session_key Session hash.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_by_session(
		string $session_key
	): ?array {

		global $wpdb;

		$session_key =
			$this->normalize_hash(
				$session_key
			);

		if ( '' === $session_key ) {
			return null;
		}

		$table =
			Installer::get_abandoned_checkout_table();

		$row =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_row(
				$wpdb->prepare(
					"SELECT *
					FROM %i
					WHERE session_key = %s
					LIMIT 1",
					$table,
					$session_key
				),
				ARRAY_A
			);

		return is_array(
			$row
		)
			? $this->hydrate_record(
				$row
			)
			: null;
	}

	/** Count checkouts that became abandoned in an instant range. */
	public function count_abandoned_between( \DateTimeInterface $start, \DateTimeInterface $end ): ?int {
		global $wpdb;

		if ( $end->getTimestamp() <= $start->getTimestamp() ) {
			return 0;
		}

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM %i WHERE status = %s AND abandoned_at >= %s AND abandoned_at < %s",
			Installer::get_abandoned_checkout_table(),
			self::STATUS_ABANDONED,
			gmdate( 'Y-m-d H:i:s', $start->getTimestamp() ),
			gmdate( 'Y-m-d H:i:s', $end->getTimestamp() )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared custom-table count must reflect current checkout activity.
		$count = $wpdb->get_var( $query );
		return is_numeric( $count ) ? max( 0, (int) $count ) : null;
	}

	/**
	 * Manually update checkout status.
	 *
	 * @param int    $id     Checkout ID.
	 * @param string $status Status.
	 *
	 * @return bool
	 */
	public function update_status(
		int $id,
		string $status
	): bool {

		global $wpdb;

		$id =
			absint(
				$id
			);

		$status =
			$this->sanitize_status(
				$status
			);

		if (
			$id <= 0 ||
			'' === $status
		) {
			return false;
		}

		$table =
			Installer::get_abandoned_checkout_table();

		$now =
			$this->now();

		$data =
			array(
				'status' =>
					$status,

				'updated_at' =>
					$now,
			);

		switch ( $status ) {

			case self::STATUS_ACTIVE:
				$data[
					'abandoned_at'
				] =
					null;

				$data[
					'recovered_at'
				] =
					null;

				$data[
					'converted_at'
				] =
					null;
				break;

			case self::STATUS_ABANDONED:
				$data[
					'abandoned_at'
				] =
					$now;

				$data[
					'recovered_at'
				] =
					null;

				$data[
					'converted_at'
				] =
					null;
				break;

			case self::STATUS_RECOVERED:
				$data[
					'recovered_at'
				] =
					$now;

				$data[
					'converted_at'
				] =
					null;
				break;

			case self::STATUS_CONVERTED:
				$data[
					'converted_at'
				] =
					$now;
				break;
		}

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				$table,
				$data,
				array(
					'id' =>
						$id,
				)
			);

		return false !== $result;
	}

	/**
	 * Mark stale Active records as Abandoned.
	 *
	 * @param int $inactive_minutes Inactivity threshold.
	 *
	 * @return int|false
	 */
	public function mark_stale_as_abandoned(
		int $inactive_minutes
	) {

		global $wpdb;

		$inactive_minutes =
			max(
				1,
				absint(
					$inactive_minutes
				)
			);

		$table =
			Installer::get_abandoned_checkout_table();

		$threshold =
			gmdate(
				'Y-m-d H:i:s',
				time() -
					(
						$inactive_minutes *
						MINUTE_IN_SECONDS
					)
			);

		$now =
			$this->now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This writes an Eilmo-owned operational table and must take effect immediately.
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = %s,
					abandoned_at = %s,
					updated_at = %s
				WHERE status = %s
				AND last_activity_at <= %s",
				$table,
				self::STATUS_ABANDONED,
				$now,
				$now,
				self::STATUS_ACTIVE,
				$threshold
			)
		);
	}

	/**
	 * Get checkout records.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function get_all(
		array $args = array()
	): array {

		global $wpdb;

		$table =
			Installer::get_abandoned_checkout_table();

		$status =
			isset(
				$args[
					'status'
				]
			)
				? $this->sanitize_status(
					(string) $args[
						'status'
					]
				)
				: '';

		$search =
			sanitize_text_field(
				(string) (
					$args[
						'search'
					] ??
						''
				)
			);

		$page =
			max(
				1,
				absint(
					$args[
						'page'
					] ??
						1
				)
			);

		$per_page =
			min(
				100,
				max(
					1,
					absint(
						$args[
							'per_page'
						] ??
							20
					)
				)
			);

		$where =
			array(
				'1 = 1',
			);

		$values =
			array();

		if ( '' !== $status ) {
			$where[] =
				'status = %s';

			$values[] =
				$status;
		}

		if ( '' !== $search ) {

			$like =
				'%' .
				$wpdb->esc_like(
					$search
				) .
				'%';

			$where[] =
				'(
					billing_first_name LIKE %s
					OR billing_last_name LIKE %s
					OR billing_phone LIKE %s
					OR billing_email LIKE %s
					OR billing_address_1 LIKE %s
					OR billing_address_2 LIKE %s
					OR billing_city LIKE %s
					OR billing_state LIKE %s
					OR billing_postcode LIKE %s
				)';

			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql =
			implode(
				' AND ',
				$where
			);

		$count_values =
			array_merge(
				array(
					$table,
				),
				$values
			);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE clauses determine the matching dynamic argument list.
		$count_sql =
			$wpdb->prepare(
				"SELECT COUNT(id)
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

		$offset =
			(
				$page -
				1
			) *
			$per_page;

		$list_values =
			array_merge(
				array(
					$table,
				),
				$values
			);

		$list_values[] =
			$per_page;

		$list_values[] =
			$offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Fixed WHERE clauses determine the matching dynamic argument list.
		$list_sql =
			$wpdb->prepare(
				"SELECT *
				FROM %i
				WHERE {$where_sql}
				ORDER BY last_activity_at DESC, id DESC
				LIMIT %d OFFSET %d",
				...$list_values
			);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$rows =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with a strict identifier, fixed WHERE clauses and value placeholders above.
			$wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$list_sql,
				ARRAY_A
			);

		$items =
			array();

		if (
			is_array(
				$rows
			)
		) {
			foreach (
				$rows as $row
			) {
				if (
					! is_array(
						$row
					)
				) {
					continue;
				}

				$items[] =
					$this->hydrate_record(
						$row
					);
			}
		}

		return array(
			'items' =>
				$items,

			'total' =>
				$total,

			'page' =>
				$page,

			'per_page' =>
				$per_page,

			'total_pages' =>
				$total > 0
					? (int) ceil(
						$total /
						$per_page
					)
					: 0,
		);
	}

	/**
	 * Permanently delete checkout by ID.
	 *
	 * @param int $id Checkout ID.
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

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->delete(
				Installer::get_abandoned_checkout_table(),
				array(
					'id' =>
						$id,
				),
				array(
					'%d',
				)
			);

		return false !== $result;
	}

	/**
	 * Permanently delete checkout by session hash.
	 *
	 * Used when the same checkout session successfully
	 * places an order.
	 *
	 * Returning true when zero rows matched makes the
	 * operation safely idempotent.
	 *
	 * @param string $session_key Session hash.
	 *
	 * @return bool
	 */
	public function delete_by_session(
		string $session_key
	): bool {

		global $wpdb;

		$session_key =
			$this->normalize_hash(
				$session_key
			);

		if ( '' === $session_key ) {
			return false;
		}

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->delete(
				Installer::get_abandoned_checkout_table(),
				array(
					'session_key' =>
						$session_key,
				),
				array(
					'%s',
				)
			);

		return false !== $result;
	}

	/**
	 * Get supported statuses.
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {

		return array(
			self::STATUS_ACTIVE,
			self::STATUS_ABANDONED,
			self::STATUS_RECOVERED,
			self::STATUS_CONVERTED,
		);
	}

	/**
	 * Insert checkout.
	 *
	 * @param string              $session_key Session hash.
	 * @param array<string,mixed> $data        Data.
	 *
	 * @return int|WP_Error
	 */
	private function insert(
		string $session_key,
		array $data
	) {

		global $wpdb;

		$table =
			Installer::get_abandoned_checkout_table();

		$now =
			$this->now();

		$prepared =
			$this->prepare_data(
				$data
			);

		$prepared[
			'session_key'
		] =
			$session_key;

		$prepared[
			'status'
		] =
			self::STATUS_ACTIVE;

		$prepared[
			'first_seen_at'
		] =
			$now;

		$prepared[
			'last_activity_at'
		] =
			$now;

		$prepared[
			'created_at'
		] =
			$now;

		$prepared[
			'updated_at'
		] =
			$now;

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->insert(
				$table,
				$prepared
			);

		if ( false === $result ) {

			/*
			 * Another tracking request may have created
			 * the same session between SELECT and INSERT.
			 */
			$existing =
				$this->get_by_session(
					$session_key
				);

			if (
				is_array(
					$existing
				)
			) {
				$id =
					absint(
						$existing[
							'id'
						] ??
							0
					);

				if (
					$id > 0 &&
					$this->update(
						$id,
						$data
					)
				) {
					return $id;
				}
			}

			return new WP_Error(
				'eilmo_cf_checkout_insert_failed',
				__(
					'Checkout activity could not be saved.',
					'eilmo-checkout-flow'
				)
			);
		}

		return absint(
			$wpdb->insert_id
		);
	}

	/**
	 * Update checkout activity.
	 *
	 * Tracking itself must never overwrite an admin
	 * selected lifecycle status.
	 *
	 * @param int                 $id   Checkout ID.
	 * @param array<string,mixed> $data Data.
	 *
	 * @return bool
	 */
	private function update(
		int $id,
		array $data
	): bool {

		global $wpdb;

		if ( $id <= 0 ) {
			return false;
		}

		$prepared =
			$this->prepare_data(
				$data
			);

		$now =
			$this->now();

		$prepared[
			'last_activity_at'
		] =
			$now;

		$prepared[
			'updated_at'
		] =
			$now;

		unset(
			$prepared[
				'status'
			]
		);

		$result =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_abandoned_checkout_table(),
				$prepared,
				array(
					'id' =>
						$id,
				)
			);

		return false !== $result;
	}

	/**
	 * Prepare allowed checkout data.
	 *
	 * @param array<string,mixed> $data Data.
	 *
	 * @return array<string,mixed>
	 */
	private function prepare_data(
		array $data
	): array {

		$snapshot =
			$data[
				'cart_snapshot'
			] ??
				array();

		if (
			is_string(
				$snapshot
			)
		) {
			$decoded =
				json_decode(
					$snapshot,
					true
				);

			$snapshot =
				is_array(
					$decoded
				)
					? $decoded
					: array();
		}

		if (
			! is_array(
				$snapshot
			)
		) {
			$snapshot =
				array();
		}

		return array(
			'checkout_source' =>
				sanitize_key(
					(string) (
						$data[
							'checkout_source'
						] ??
							self::SOURCE_EILMO
					)
				),

			'customer_id' =>
				absint(
					$data[
						'customer_id'
					] ??
						0
				),

			'billing_first_name' =>
				sanitize_text_field(
					(string) (
						$data[
							'billing_first_name'
						] ??
							''
					)
				),

			'billing_last_name' =>
				sanitize_text_field(
					(string) (
						$data[
							'billing_last_name'
						] ??
							''
					)
				),

			'billing_phone' =>
				sanitize_text_field(
					(string) (
						$data[
							'billing_phone'
						] ??
							''
					)
				),

			'billing_email' =>
				sanitize_email(
					(string) (
						$data[
							'billing_email'
						] ??
							''
					)
				),

			'billing_address_1' =>
				sanitize_text_field( (string) ( $data['billing_address_1'] ?? '' ) ),

			'billing_address_2' =>
				sanitize_text_field( (string) ( $data['billing_address_2'] ?? '' ) ),

			'billing_city' =>
				sanitize_text_field( (string) ( $data['billing_city'] ?? '' ) ),

			'billing_state' =>
				sanitize_text_field( (string) ( $data['billing_state'] ?? '' ) ),

			'billing_postcode' =>
				sanitize_text_field( (string) ( $data['billing_postcode'] ?? '' ) ),

			'billing_country' =>
				sanitize_text_field( (string) ( $data['billing_country'] ?? '' ) ),

			'cart_snapshot' =>
				wp_json_encode(
					$snapshot
				),

			'delivery_method' =>
				sanitize_key(
					(string) (
						$data[
							'delivery_method'
						] ??
							''
					)
				),

			'payment_method' =>
				sanitize_key(
					(string) (
						$data[
							'payment_method'
						] ??
							''
					)
				),

			'currency' =>
				sanitize_key(
					(string) (
						$data[
							'currency'
						] ??
							''
					)
				),

			'subtotal' =>
				$this->normalize_amount(
					$data[
						'subtotal'
					] ??
						0
				),

			'total' =>
				$this->normalize_amount(
					$data[
						'total'
					] ??
						0
				),
		);
	}

	/**
	 * Hydrate database record.
	 *
	 * @param array<string,mixed> $row Database row.
	 *
	 * @return array<string,mixed>
	 */
	private function hydrate_record(
		array $row
	): array {

		$snapshot =
			$row[
				'cart_snapshot'
			] ??
				'';

		if (
			is_string(
				$snapshot
			) &&
			'' !== $snapshot
		) {
			$decoded =
				json_decode(
					$snapshot,
					true
				);

			$row[
				'cart_snapshot'
			] =
				is_array(
					$decoded
				)
					? $decoded
					: array();
		} else {
			$row[
				'cart_snapshot'
			] =
				array();
		}

		return $row;
	}

	/**
	 * Sanitize status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function sanitize_status(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		return in_array(
			$status,
			self::statuses(),
			true
		)
			? $status
			: '';
	}

	/**
	 * Normalize 64-character session hash.
	 *
	 * @param string $hash Hash.
	 *
	 * @return string
	 */
	private function normalize_hash(
		string $hash
	): string {

		$hash =
			strtolower(
				trim(
					$hash
				)
			);

		return (
			64 === strlen(
				$hash
			) &&
			1 === preg_match(
				'/^[a-f0-9]{64}$/',
				$hash
			)
		)
			? $hash
			: '';
	}

	/**
	 * Normalize monetary amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return string
	 */
	private function normalize_amount(
		$amount
	): string {

		if (
			! is_numeric(
				$amount
			)
		) {
			return '0';
		}

		$amount =
			max(
				0,
				(float) $amount
			);

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			return wc_format_decimal(
				$amount,
				8
			);
		}

		return number_format(
			$amount,
			8,
			'.',
			''
		);
	}

	/**
	 * Current UTC database time.
	 *
	 * @return string
	 */
	private function now(): string {

		return gmdate(
			'Y-m-d H:i:s'
		);
	}
}
