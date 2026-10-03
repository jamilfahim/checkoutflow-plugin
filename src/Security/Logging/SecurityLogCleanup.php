<?php
/**
 * Security Activity Log cleanup.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Logging;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules automatic cleanup of old Security
 * Activity Log records.
 *
 * Cleanup is controlled by:
 *
 * - Security master switch.
 * - Activity Logging switch.
 * - Automatic Log Cleanup switch.
 * - Retention Days.
 *
 * The repository remains authoritative for deciding
 * which log rows are safe to delete.
 */
final class SecurityLogCleanup implements RegistrableInterface {

	/**
	 * WordPress Cron hook.
	 *
	 * @var string
	 */
	public const CRON_HOOK =
		'eilmo_cf_security_log_cleanup';

	/**
	 * Fallback Activity Log retention.
	 *
	 * @var int
	 */
	private const DEFAULT_RETENTION_DAYS = 30;

	/**
	 * Register cleanup hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'init',
			array(
				$this,
				'schedule',
			)
		);

		add_action(
			self::CRON_HOOK,
			array(
				$this,
				'cleanup',
			)
		);
	}

	/**
	 * Synchronize the scheduled cleanup event with the
	 * current Security settings.
	 *
	 * When automatic cleanup is disabled, any existing
	 * cron event is removed immediately on the next
	 * WordPress request.
	 *
	 * @return void
	 */
	public function schedule(): void {

		if ( ! $this->is_automatic_cleanup_enabled() ) {

			if (
				false !==
					wp_next_scheduled(
						self::CRON_HOOK
					)
			) {
				self::unschedule();
			}

			return;
		}

		if (
			false !==
				wp_next_scheduled(
					self::CRON_HOOK
				)
		) {
			return;
		}

		/*
		 * Cleanup is not time-critical.
		 *
		 * First run: approximately one hour after the
		 * scheduler is registered.
		 *
		 * Recurrence: once per day.
		 */
		$scheduled =
			wp_schedule_event(
				time() +
					HOUR_IN_SECONDS,
				'daily',
				self::CRON_HOOK
			);

		if ( false === $scheduled ) {

			/**
			 * Fires when Security Activity Log cleanup
			 * could not be scheduled.
			 */
			do_action(
				'eilmo_cf/security/log_cleanup_schedule_failed'
			);
		}
	}

	/**
	 * Clean old Security Activity Logs.
	 *
	 * The settings are checked again at execution time.
	 * This protects against a stale WP-Cron event running
	 * after the administrator disabled automatic cleanup.
	 *
	 * Only records allowed by SecurityLogRepository are
	 * deleted.
	 *
	 * @return void
	 */
	public function cleanup(): void {

		if ( ! $this->is_automatic_cleanup_enabled() ) {

			self::unschedule();

			return;
		}

		$retention_days =
			$this->get_retention_days();

		$repository =
			new SecurityLogRepository();

		try {

			$deleted =
				$repository->cleanup_old_logs(
					$retention_days
				);

		} catch ( \Throwable $throwable ) {

			/**
			 * Cleanup failures must never affect the
			 * frontend request that triggered WP-Cron.
			 *
			 * @param \Throwable $throwable Error.
			 */
			do_action(
				'eilmo_cf/security/log_cleanup_failed',
				$throwable
			);

			return;
		}

		if ( false === $deleted ) {

			do_action(
				'eilmo_cf/security/log_cleanup_failed',
				null
			);

			return;
		}

		/**
		 * Fires after automatic Activity Log cleanup.
		 *
		 * @param int $deleted        Number of deleted logs.
		 * @param int $retention_days Retention period used.
		 */
		do_action(
			'eilmo_cf/security/log_cleanup_completed',
			absint(
				$deleted
			),
			$retention_days
		);
	}

	/**
	 * Determine whether automatic Activity Log cleanup
	 * should run.
	 *
	 * All three switches must be enabled:
	 *
	 * - Security master.
	 * - Activity Logging.
	 * - Automatic Log Cleanup.
	 *
	 * @return bool
	 */
	private function is_automatic_cleanup_enabled(): bool {

		if ( ! SecuritySettings::is_enabled() ) {
			return false;
		}

		$activity =
			$this->get_activity_logging_settings();

		return (
			'yes' ===
				(
					$activity[
						'enabled'
					] ??
						'yes'
				) &&
			'yes' ===
				(
					$activity[
						'auto_cleanup'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Get configured Activity Log retention period.
	 *
	 * @return int
	 */
	private function get_retention_days(): int {

		$activity =
			$this->get_activity_logging_settings();

		$retention_days =
			absint(
				$activity[
					'retention_days'
				] ??
					self::DEFAULT_RETENTION_DAYS
			);

		if ( $retention_days <= 0 ) {
			$retention_days =
				self::DEFAULT_RETENTION_DAYS;
		}

		/*
		 * SecurityLogRepository is the canonical location
		 * for the log_retention_days filter. Keeping the
		 * filter there prevents callbacks from being applied
		 * twice during scheduled cleanup.
		 */
		return max(
			1,
			$retention_days
		);
	}

	/**
	 * Get Activity Logging settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_activity_logging_settings(): array {

		$settings =
			SecuritySettings::get_settings();

		if (
			! isset(
				$settings[
					'protection'
				][
					'activity_logging'
				]
			) ||
			! is_array(
				$settings[
					'protection'
				][
					'activity_logging'
				]
			)
		) {
			return array();
		}

		return $settings[
			'protection'
		][
			'activity_logging'
		];
	}

	/**
	 * Remove all scheduled Security Log cleanup events.
	 *
	 * This is safe to call repeatedly.
	 *
	 * Call this during plugin deactivation as well.
	 *
	 * @return void
	 */
	public static function unschedule(): void {

		wp_clear_scheduled_hook(
			self::CRON_HOOK
		);
	}
}
