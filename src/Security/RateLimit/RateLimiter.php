<?php
/**
 * Checkout rate limiter.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\RateLimit;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Core\Installer;
use EilmoCheckout\Security\ClientIpResolver;
use EilmoCheckout\Security\Logging\SecurityLogger;
use EilmoCheckout\Security\Logging\SecurityLogRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Limits repeated checkout orders by client IP using the shared Security settings.
 */
final class RateLimiter {

	/**
	 * Check whether current visitor is blocked.
	 *
	 * @param string $source Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check(
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! $this->is_enabled() ||
			$this->should_bypass_current_user()
		) {
			return true;
		}

		$ip =
			$this->get_ip();

		if ( '' === $ip ) {
			return true;
		}

		$identifier_hash =
			$this->hash_identifier(
				$ip
			);

		$record =
			$this->get_record(
				$identifier_hash
			);

		if ( null === $record ) {
			return true;
		}

		$blocked_until =
			$this->parse_utc_time(
				(string) (
					$record[
						'blocked_until'
					] ??
						''
				)
			);

		if ( $blocked_until <= 0 ) {
			return true;
		}

		/*
		 * An expired temporary block starts a fresh
		 * rate-limit window. This prevents a customer
		 * from being immediately re-blocked by stale
		 * attempts from the previous block period.
		 */
		if ( $blocked_until <= time() ) {

			$this->reset_expired_record(
				$record
			);

			return true;
		}

		$remaining_seconds =
			max(
				1,
				$blocked_until -
					time()
			);

		$error =
			new WP_Error(
				'checkout_rate_limited',
				$this->get_blocked_message(
					$remaining_seconds
				),
				array(
					'remaining_seconds' =>
						$remaining_seconds,

					'blocked_until' =>
						$blocked_until,
				)
			);

		/*
		 * ---------------------------------------------
		 * Security Activity Log
		 * ---------------------------------------------
		 *
		 * Rate Limiting still blocks when Activity
		 * Logging is disabled. Only log persistence is
		 * skipped.
		 */
		if ( $this->should_log() ) {

			$log_repository =
				new SecurityLogRepository();

			$masked_ip =
				$log_repository->mask_identifier(
					'ip',
					$ip
				);

			$logger =
				new SecurityLogger(
					$log_repository
				);

			$logger->rate_limit(
				$identifier_hash,
				$masked_ip,
				$source,
				$error,
				429
			);
		}

		/**
		 * Fires when checkout is blocked by Rate Limit.
		 *
		 * @param string $ip              Client IP.
		 * @param int    $blocked_until   Block expiry timestamp.
		 * @param string $source           Checkout source.
		 * @param string $identifier_hash Privacy-safe identifier hash.
		 */
		do_action(
			'eilmo_cf/security/rate_limit_blocked',
			$ip,
			$blocked_until,
			$source,
			$identifier_hash
		);

		return $error;
	}


	/**
	 * Record one newly created order.
	 *
	 * Only genuinely newly created orders should be
	 * recorded.
	 *
	 * Idempotent replays must not call this method.
	 *
	 * @return void
	 */
	public function record_order(): void {

		if (
			! $this->is_enabled() ||
			$this->should_bypass_current_user()
		) {
			return;
		}

		$ip =
			$this->get_ip();

		if ( '' === $ip ) {
			return;
		}

		$config =
			$this->get_config();

		$maximum_orders =
			max(
				1,
				absint(
					$config[
						'maximum_orders'
					] ??
						3
				)
			);

		$window_minutes =
			max(
				1,
				absint(
					$config[
						'window_minutes'
					] ??
						10
				)
			);

		$block_minutes =
			max(
				1,
				absint(
					$config[
						'block_minutes'
					] ??
						60
				)
			);

		$hash =
			$this->hash_identifier(
				$ip
			);

		$record =
			$this->get_record(
				$hash
			);

		$now =
			time();

		$now_mysql =
			gmdate(
				'Y-m-d H:i:s',
				$now
			);

		/*
		 * ---------------------------------------------
		 * First order
		 * ---------------------------------------------
		 */
		if ( null === $record ) {

			$this->insert_record(
				$hash,
				1,
				$now_mysql,
				null,
				$now_mysql
			);

			return;
		}

		$window_started =
			$this->parse_utc_time(
				(string) (
					$record[
						'window_started_at'
					] ??
						''
				)
			);

		$window_seconds =
			$window_minutes *
			MINUTE_IN_SECONDS;

		/*
		 * ---------------------------------------------
		 * Previous window expired
		 * ---------------------------------------------
		 *
		 * Start a completely new counting window from
		 * the newly created order.
		 */
		if (
			$window_started <= 0 ||
			(
				$now -
				$window_started
			) >=
				$window_seconds
		) {
			$this->update_record(
				absint(
					$record[
						'id'
					] ??
						0
				),
				array(
					'attempts' =>
						1,

					'window_started_at' =>
						$now_mysql,

					'blocked_until' =>
						null,

					'updated_at' =>
						$now_mysql,
				)
			);

			return;
		}

		$current_attempts =
			max(
				0,
				absint(
					$record[
						'attempts'
					] ??
						0
				)
			);

		$new_attempts =
			$current_attempts +
			1;

		$blocked_until =
			null;

		/*
		 * ---------------------------------------------
		 * Apply temporary block
		 * ---------------------------------------------
		 *
		 * Example:
		 *
		 * maximum_orders = 3
		 *
		 * Order 1 -> allowed
		 * Order 2 -> allowed
		 * Order 3 -> allowed and block starts
		 *
		 * The next checkout attempt is then rejected by
		 * check() until blocked_until expires.
		 */
		if (
			$new_attempts >=
			$maximum_orders
		) {
			$blocked_until =
				gmdate(
					'Y-m-d H:i:s',
					$now +
					(
						$block_minutes *
						MINUTE_IN_SECONDS
					)
				);
		}

		$this->update_record(
			absint(
				$record[
					'id'
				] ??
					0
			),
			array(
				'attempts' =>
					$new_attempts,

				'blocked_until' =>
					$blocked_until,

				'updated_at' =>
					$now_mysql,
			)
		);
	}

	/**
	 * Reset / Unblock a Rate Limit record.
	 *
	 * This method accepts only the privacy-safe
	 * identifier hash stored in the Rate Limit table.
	 *
	 * Raw IP addresses are not required.
	 *
	 * Activity Logs can call this method using:
	 *
	 * target_type = rate_limit
	 * target_hash = identifier_hash
	 *
	 * @param string $identifier_hash Identifier hash.
	 *
	 * @return bool
	 */
	public function reset(
		string $identifier_hash
	): bool {

		global $wpdb;

		$identifier_hash =
			$this->normalize_hash(
				$identifier_hash
			);

		if ( '' === $identifier_hash ) {
			return false;
		}

		$now =
			gmdate(
				'Y-m-d H:i:s'
			);

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_rate_limit_table(),
				array(
					'attempts' =>
						0,

					'window_started_at' =>
						$now,

					'blocked_until' =>
						null,

					'updated_at' =>
						$now,
				),
				array(
					'identifier_hash' =>
						$identifier_hash,
				),
				array(
					'%d',
					'%s',
					'%s',
					'%s',
				),
				array(
					'%s',
				)
			);

		if ( false === $updated ) {
			return false;
		}

		/*
		 * A valid matching row returning 0 affected rows
		 * can still mean the record was already reset.
		 *
		 * Confirm that the target exists before
		 * reporting success.
		 */
		if ( 0 === $updated ) {

			$record =
				$this->get_record(
					$identifier_hash
				);

			if ( null === $record ) {
				return false;
			}
		}

		/**
		 * Fires after a Rate Limit record is manually
		 * reset.
		 *
		 * @param string $identifier_hash Identifier hash.
		 */
		do_action(
			'eilmo_cf/security/rate_limit_reset',
			$identifier_hash
		);

		return true;
	}

	/**
	 * Determine whether Rate Limiting is enabled.
	 *
	 * Both the General Security master switch and the
	 * individual Rate Limit switch must be enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		return SecuritySettings::is_protection_enabled(
			'rate_limit'
		);
	}


	/**
	 * Get Rate Limit configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function get_config(): array {

		$settings =
			SecuritySettings::get_settings();

		$protection =
			isset(
				$settings[
					'protection'
				]
			) &&
			is_array(
				$settings[
					'protection'
				]
			)
				? $settings[
					'protection'
				]
				: array();

		return isset(
			$protection[
				'rate_limit'
			]
		) &&
		is_array(
			$protection[
				'rate_limit'
			]
		)
			? $protection[
				'rate_limit'
			]
			: array();
	}


	/**
	 * Whether current user may bypass customer-facing
	 * Security protections.
	 *
	 * @return bool
	 */
	private function should_bypass_current_user(): bool {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings =
			SecuritySettings::get_settings();

		$advanced =
			isset(
				$settings[
					'advanced'
				]
			) &&
			is_array(
				$settings[
					'advanced'
				]
			)
				? $settings[
					'advanced'
				]
				: array();

		$user =
			wp_get_current_user();

		$roles =
			is_array(
				$user->roles
			)
				? $user->roles
				: array();

		$is_administrator =
			is_super_admin(
				$user->ID
			) ||
			in_array(
				'administrator',
				$roles,
				true
			);

		if (
			$is_administrator &&
			'yes' ===
				(
					$advanced[
						'bypass_administrators'
					] ??
						'yes'
				)
		) {
			return true;
		}

		return (
			in_array(
				'shop_manager',
				$roles,
				true
			) &&
			'yes' ===
				(
					$advanced[
						'bypass_shop_managers'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Whether Rate Limit blocks should be stored in
	 * Security Activity Logs.
	 *
	 * @return bool
	 */
	private function should_log(): bool {

		$settings =
			SecuritySettings::get_settings();

		$logging =
			isset(
				$settings[
					'protection'
				][
					'activity_logging'
				]
			) &&
			is_array(
				$settings[
					'protection'
				][
					'activity_logging'
				]
			)
				? $settings[
					'protection'
				][
					'activity_logging'
				]
				: array();

		return (
			'yes' ===
				(
					$logging[
						'enabled'
					] ??
						'yes'
				) &&
			'yes' ===
				(
					$logging[
						'log_rate_limit'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Get configured customer-facing Rate Limit message.
	 *
	 * @param int $remaining_seconds Remaining block time.
	 *
	 * @return string
	 */
	private function get_blocked_message(
		int $remaining_seconds
	): string {

		$settings =
			SecuritySettings::get_settings();

		$messages =
			isset(
				$settings[
					'messages'
				]
			) &&
			is_array(
				$settings[
					'messages'
				]
			)
				? $settings[
					'messages'
				]
				: array();

		$message =
			trim(
				(string) (
					$messages[
						'rate_limit'
					] ??
						''
				)
			);

		if ( '' === $message ) {
			$message =
				__(
					'Too many order attempts have been made. Please try again in {remaining_time}.',
					'eilmo-checkout-flow'
				);
		}

		$message =
			str_replace(
				'{remaining_time}',
				$this->format_remaining_time(
					$remaining_seconds
				),
				$message
			);

		return sanitize_text_field(
			$message
		);
	}

	/**
	 * Format remaining time for customer messages.
	 *
	 * @param int $seconds Seconds.
	 *
	 * @return string
	 */
	private function format_remaining_time(
		int $seconds
	): string {

		$seconds =
			max(
				1,
				absint(
					$seconds
				)
			);

		$hours =
			(int) floor(
				$seconds /
				HOUR_IN_SECONDS
			);

		$seconds %=
			HOUR_IN_SECONDS;

		$minutes =
			(int) floor(
				$seconds /
				MINUTE_IN_SECONDS
			);

		$seconds %=
			MINUTE_IN_SECONDS;

		$parts =
			array();

		if ( $hours > 0 ) {
			$parts[] =
				sprintf(
					/* translators: %d: number of hours. */
					_n(
						'%d hour',
						'%d hours',
						$hours,
						'eilmo-checkout-flow'
					),
					$hours
				);
		}

		if ( $minutes > 0 ) {
			$parts[] =
				sprintf(
					/* translators: %d: number of minutes. */
					_n(
						'%d minute',
						'%d minutes',
						$minutes,
						'eilmo-checkout-flow'
					),
					$minutes
				);
		}

		if (
			$seconds > 0 &&
			count( $parts ) < 2
		) {
			$parts[] =
				sprintf(
					/* translators: %d: number of seconds. */
					_n(
						'%d second',
						'%d seconds',
						$seconds,
						'eilmo-checkout-flow'
					),
					$seconds
				);
		}

		return implode(
			' ',
			array_slice(
				$parts,
				0,
				2
			)
		);
	}

	/**
	 * Reset stale Rate Limit state after a temporary
	 * block has expired.
	 *
	 * The next successful order starts a fresh counting
	 * window from one instead of inheriting old attempts.
	 *
	 * @param array<string,mixed> $record Rate Limit record.
	 *
	 * @return void
	 */
	private function reset_expired_record(
		array $record
	): void {

		$id =
			absint(
				$record[
					'id'
				] ??
					0
			);

		if ( $id <= 0 ) {
			return;
		}

		$now =
			gmdate(
				'Y-m-d H:i:s'
			);

		$this->update_record(
			$id,
			array(
				'attempts' =>
					0,

				'window_started_at' =>
					$now,

				'blocked_until' =>
					null,

				'updated_at' =>
					$now,
			)
		);
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_ip(): string {

		$resolver =
			new ClientIpResolver();

		return $resolver->get();
	}

	/**
	 * Hash IP before database storage.
	 *
	 * Raw IP addresses are not required by the limiter.
	 *
	 * @param string $identifier Identifier.
	 *
	 * @return string
	 */
	private function hash_identifier(
		string $identifier
	): string {

		$identifier =
			trim(
				$identifier
			);

		if ( '' === $identifier ) {
			return '';
		}

		return hash_hmac(
			'sha256',
			$identifier,
			wp_salt(
				'auth'
			)
		);
	}

	/**
	 * Normalize SHA-256 identifier hash.
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

		if (
			64 !== strlen( $hash ) ||
			1 !== preg_match(
				'/^[a-f0-9]{64}$/',
				$hash
			)
		) {
			return '';
		}

		return $hash;
	}

	/**
	 * Get identifier record.
	 *
	 * @param string $hash Identifier hash.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_record(
		string $hash
	): ?array {

		global $wpdb;

		$hash =
			$this->normalize_hash(
				$hash
			);

		if ( '' === $hash ) {
			return null;
		}

		$table =
			Installer::get_rate_limit_table();

		$query =
			$wpdb->prepare(
				"SELECT
					id,
					identifier_hash,
					attempts,
					window_started_at,
					blocked_until,
					updated_at
				FROM %i
				WHERE identifier_hash = %s
				LIMIT 1",
				$table,
				$hash
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array(
			$result
		)
			? $result
			: null;
	}

	/**
	 * Insert Rate Limit record.
	 *
	 * @param string      $hash               Identifier hash.
	 * @param int         $attempts           Attempts.
	 * @param string      $window_started_at  Window start.
	 * @param string|null $blocked_until      Block expiry.
	 * @param string      $updated_at         Updated date.
	 *
	 * @return void
	 */
	private function insert_record(
		string $hash,
		int $attempts,
		string $window_started_at,
		?string $blocked_until,
		string $updated_at
	): void {

		global $wpdb;

		$hash =
			$this->normalize_hash(
				$hash
			);

		if ( '' === $hash ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
		$wpdb->insert(
			Installer::get_rate_limit_table(),
			array(
				'identifier_hash' =>
					$hash,

				'attempts' =>
					max(
						0,
						$attempts
					),

				'window_started_at' =>
					$window_started_at,

				'blocked_until' =>
					$blocked_until,

				'updated_at' =>
					$updated_at,
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Update Rate Limit record.
	 *
	 * @param int                 $id   Record ID.
	 * @param array<string,mixed> $data Data.
	 *
	 * @return void
	 */
	private function update_record(
		int $id,
		array $data
	): void {

		global $wpdb;

		$id =
			absint(
				$id
			);

		if ( $id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
		$wpdb->update(
			Installer::get_rate_limit_table(),
			$data,
			array(
				'id' =>
					$id,
			),
			null,
			array(
				'%d',
			)
		);
	}

	/**
	 * Parse UTC MySQL datetime.
	 *
	 * @param string $date Date.
	 *
	 * @return int
	 */
	private function parse_utc_time(
		string $date
	): int {

		if (
			'' === $date ||
			'0000-00-00 00:00:00' ===
				$date
		) {
			return 0;
		}

		$timestamp =
			strtotime(
				$date . ' UTC'
			);

		return false !== $timestamp
			? $timestamp
			: 0;
	}
}
