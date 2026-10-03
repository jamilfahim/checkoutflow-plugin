<?php
/**
 * Abandoned Checkout scheduler.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AbandonedCheckout;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Handles automatic Abandoned Checkout detection.
 */
final class AbandonedCheckoutScheduler implements RegistrableInterface {

	/**
	 * Cron hook.
	 */
	public const CRON_HOOK =
		'eilmo_cf_abandoned_checkout_detection';

	/**
	 * Custom cron schedule.
	 */
	private const CRON_SCHEDULE =
		'eilmo_cf_every_five_minutes';

	/**
	 * Cron interval.
	 *
	 * @var int
	 */
	private const CRON_INTERVAL =
		5 * MINUTE_IN_SECONDS;

	/**
	 * Default abandonment threshold.
	 *
	 * @var int
	 */
	private const DEFAULT_ABANDONMENT_MINUTES =
		30;

	/**
	 * Register scheduler.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'cron_schedules',
			array(
				$this,
				'register_cron_schedule',
			)
		);

		add_action(
			'init',
			array(
				$this,
				'maybe_schedule',
			),
			20
		);

		add_action(
			self::CRON_HOOK,
			array(
				$this,
				'run',
			)
		);
	}

	/**
	 * Register five-minute cron schedule.
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function register_cron_schedule(
		array $schedules
	): array {

		if (
			isset(
				$schedules[
					self::CRON_SCHEDULE
				]
			)
		) {
			return $schedules;
		}

		$schedules[
			self::CRON_SCHEDULE
		] =
			array(
				'interval' =>
					self::CRON_INTERVAL,

				'display' =>
					__(
						'Every 5 Minutes',
						'eilmo-checkout-flow'
					),
			);

		return $schedules;
	}

	/**
	 * Schedule or remove abandoned checkout detection.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {

		if (
			! $this->is_enabled()
		) {
			self::unschedule_all();

			return;
		}

		if (
			wp_next_scheduled(
				self::CRON_HOOK
			)
		) {
			return;
		}

		wp_schedule_event(
			time() +
				MINUTE_IN_SECONDS,
			self::CRON_SCHEDULE,
			self::CRON_HOOK
		);
	}

	/**
	 * Detect inactive checkouts.
	 *
	 * Only:
	 *
	 * Active -> Abandoned
	 *
	 * Recovered and Converted are manual admin statuses.
	 *
	 * @return void
	 */
	public function run(): void {

		if (
			! $this->is_enabled()
		) {
			self::unschedule_all();

			return;
		}

		$minutes =
			$this->get_abandonment_minutes();

		try {

			$repository =
				new AbandonedCheckoutRepository();

			$updated =
				$repository
					->mark_stale_as_abandoned(
						$minutes
					);

			if (
				false === $updated
			) {
				do_action(
					'eilmo_cf/abandoned_checkout/detection_failed',
					$minutes
				);

				return;
			}

			do_action(
				'eilmo_cf/abandoned_checkout/detection_completed',
				absint(
					$updated
				),
				$minutes
			);

		} catch ( Throwable $throwable ) {

			do_action(
				'eilmo_cf/abandoned_checkout/detection_error',
				$throwable,
				$minutes
			);
		}
	}

	/**
	 * Get abandonment threshold.
	 *
	 * @return int
	 */
	private function get_abandonment_minutes(): int {

		$minutes =
			apply_filters(
				'eilmo_cf/abandoned_checkout/abandonment_minutes',
				self::DEFAULT_ABANDONMENT_MINUTES
			);

		return max(
			1,
			absint(
				$minutes
			)
		);
	}

	/**
	 * Remove all Abandoned Checkout cron events.
	 *
	 * Public static method so plugin deactivation can
	 * safely call it without booting the full plugin.
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {

		wp_clear_scheduled_hook(
			self::CRON_HOOK
		);
	}

	/**
	 * Determine whether feature is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		$settings =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$settings
			)
		) {
			return false;
		}

		$general =
			isset(
				$settings[
					'general'
				]
			) &&
			is_array(
				$settings[
					'general'
				]
			)
				? $settings[
					'general'
				]
				: array();

		return (
			'yes' ===
				(
					$general[
						'abandoned_checkout'
					] ??
						'no'
				)
		);
	}
}