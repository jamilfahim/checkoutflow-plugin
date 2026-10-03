<?php
/**
 * Security Activity Log admin actions.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Actions;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Core\Installer;
use EilmoCheckout\Security\DuplicateOrder\DuplicateOrderGuard;
use EilmoCheckout\Security\Logging\SecurityLogRepository;
use EilmoCheckout\Security\RateLimit\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Security Activity Log administrative actions.
 */
final class SecurityLogActions implements RegistrableInterface {

	/**
	 * Admin-post action.
	 *
	 * @var string
	 */
	private const ACTION_UNBLOCK =
		'eilmo_cf_security_log_unblock';

	/**
	 * Security admin page.
	 *
	 * @var string
	 */
	private const PAGE_SLUG =
		'eilmo-checkout-security';

	/**
	 * Activity Logs tab.
	 *
	 * @var string
	 */
	private const TAB =
		'activity_logs';

	/**
	 * Register admin actions.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_post_' .
			self::ACTION_UNBLOCK,
			array(
				$this,
				'handle_unblock',
			)
		);
	}

	/**
	 * Handle Activity Log Unblock / Reset / Release.
	 *
	 * Supported targets:
	 *
	 * blacklist
	 * rate_limit
	 * duplicate_order
	 *
	 * Honeypot and Minimum Checkout Time do not create
	 * persistent blocks and therefore never use this
	 * action.
	 *
	 * @return void
	 */
	public function handle_unblock(): void {

		if (
			! current_user_can(
				'manage_woocommerce'
			)
		) {
			wp_die(
				esc_html__(
					'You do not have permission to perform this action.',
					'eilmo-checkout-flow'
				)
			);
		}

		$log_id =
			isset(
				$_POST[
					'log_id'
				]
			)
				? absint(
					wp_unslash(
						$_POST[
							'log_id'
						]
					)
				)
				: 0;

		if ( $log_id <= 0 ) {
			$this->redirect(
				'invalid_log'
			);
		}

		check_admin_referer(
			'eilmo_cf_security_log_unblock_' .
			$log_id
		);

		$repository =
			new SecurityLogRepository();

		$log =
			$repository->get_by_id(
				$log_id
			);

		if (
			! is_array(
				$log
			)
		) {
			$this->redirect(
				'log_not_found'
			);
		}

		if (
			'resolved' ===
				(
					$log[
						'status'
					] ??
						''
				)
		) {
			$this->redirect(
				'already_resolved'
			);
		}

		$target_type =
			sanitize_key(
				(string) (
					$log[
						'target_type'
					] ??
						''
				)
			);

		$target_id =
			absint(
				$log[
					'target_id'
				] ??
					0
			);

		$target_hash =
			$this->normalize_hash(
				(string) (
					$log[
						'target_hash'
					] ??
						''
				)
			);

		switch ( $target_type ) {

			case 'blacklist':
				$success =
					$this->unblock_blacklist(
						$target_id
					);
				break;

			case 'rate_limit':
				$success =
					$this->reset_rate_limit(
						$target_hash
					);
				break;

			case 'duplicate_order':
				$success =
					$this->release_duplicate_order(
						$target_hash
					);
				break;

			default:
				$this->redirect(
					'not_unblockable'
				);
		}

		if ( ! $success ) {
			$this->redirect(
				'unblock_failed'
			);
		}

		/*
		 * ---------------------------------------------
		 * Resolve related Activity Logs
		 * ---------------------------------------------
		 *
		 * One persistent security target may have many
		 * blocked-attempt logs.
		 *
		 * Example:
		 *
		 * The same blocked IP may have generated five
		 * Rate Limit Activity Logs.
		 *
		 * One admin reset resolves all logs associated
		 * with that same persistent target.
		 */
		$resolved =
			$repository->mark_target_resolved(
				$target_type,
				$target_id,
				$target_hash,
				get_current_user_id()
			);

		if (
			false ===
				$resolved
		) {

			/*
			 * The actual security target has already
			 * been successfully unblocked.
			 *
			 * A logging-status update failure must not
			 * reverse or pretend to reverse that action.
			 */
			do_action(
				'eilmo_cf/security/log_resolution_failed',
				$log_id,
				$target_type,
				$target_id,
				$target_hash
			);

			$this->redirect(
				'unblocked_log_update_failed'
			);
		}

		/**
		 * Fires after an administrator successfully
		 * unblocks a Security Activity target.
		 *
		 * @param int                 $log_id      Activity Log ID.
		 * @param string              $target_type Target type.
		 * @param int                 $target_id   Target ID.
		 * @param string              $target_hash Target hash.
		 * @param array<string,mixed> $log         Activity Log.
		 */
		do_action(
			'eilmo_cf/security/admin_unblocked',
			$log_id,
			$target_type,
			$target_id,
			$target_hash,
			$log
		);

		$this->redirect(
			'unblocked'
		);
	}

	/**
	 * Unblock Customer Blacklist record.
	 *
	 * Blacklist rows are soft-disabled instead of
	 * deleted so administrative history remains
	 * available.
	 *
	 * @param int $blacklist_id Blacklist row ID.
	 *
	 * @return bool
	 */
	private function unblock_blacklist(
		int $blacklist_id
	): bool {

		global $wpdb;

		$blacklist_id =
			absint(
				$blacklist_id
			);

		if ( $blacklist_id <= 0 ) {
			return false;
		}

		$table =
			Installer::get_blacklist_table();

		$record =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						id,
						status
					FROM %i
					WHERE id = %d
					LIMIT 1",
					$table,
					$blacklist_id
				),
				ARRAY_A
			);

		if (
			! is_array(
				$record
			)
		) {
			return false;
		}

		/*
		 * Already inactive is considered successfully
		 * unblocked.
		 *
		 * This makes the action idempotent.
		 */
		if (
			'inactive' ===
				(
					$record[
						'status'
					] ??
						''
				)
		) {
			return true;
		}

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				$table,
				array(
					'status' =>
						'inactive',

					'updated_at' =>
						gmdate(
							'Y-m-d H:i:s'
						),
				),
				array(
					'id' =>
						$blacklist_id,

					'status' =>
						'active',
				),
				array(
					'%s',
					'%s',
				),
				array(
					'%d',
					'%s',
				)
			);

		if ( false === $updated ) {
			return false;
		}

		if ( 0 === $updated ) {

			/*
			 * Another administrator/request may have
			 * already deactivated the record.
			 */
			$current_status =
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT status
						FROM %i
						WHERE id = %d
						LIMIT 1",
						$table,
						$blacklist_id
					)
				);

			return (
				'inactive' ===
				(string) $current_status
			);
		}

		do_action(
			'eilmo_cf/security/blacklist_unblocked',
			$blacklist_id
		);

		return true;
	}

	/**
	 * Reset Rate Limit target.
	 *
	 * @param string $identifier_hash Identifier hash.
	 *
	 * @return bool
	 */
	private function reset_rate_limit(
		string $identifier_hash
	): bool {

		$identifier_hash =
			$this->normalize_hash(
				$identifier_hash
			);

		if ( '' === $identifier_hash ) {
			return false;
		}

		$rate_limiter =
			new RateLimiter();

		return $rate_limiter->reset(
			$identifier_hash
		);
	}

	/**
	 * Release persisted Duplicate Order protection.
	 *
	 * The WooCommerce order itself is not modified or
	 * deleted.
	 *
	 * @param string $fingerprint Fingerprint.
	 *
	 * @return bool
	 */
	private function release_duplicate_order(
		string $fingerprint
	): bool {

		$fingerprint =
			$this->normalize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return false;
		}

		$guard =
			new DuplicateOrderGuard();

		return $guard->release_persistent(
			$fingerprint
		);
	}

	/**
	 * Normalize SHA-256/HMAC hash.
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
			64 !==
				strlen(
					$hash
				) ||
			1 !==
				preg_match(
					'/^[a-f0-9]{64}$/',
					$hash
				)
		) {
			return '';
		}

		return $hash;
	}

	/**
	 * Redirect back to Activity Logs.
	 *
	 * @param string $notice Notice code.
	 *
	 * @return void
	 */
	private function redirect(
		string $notice
	): void {

		$notice =
			sanitize_key(
				$notice
			);

		$url =
			add_query_arg(
				array(
					'page' =>
						self::PAGE_SLUG,

					'tab' =>
						self::TAB,

					'eilmo_security_notice' =>
						$notice,
				),
				admin_url(
					'admin.php'
				)
			);

		wp_safe_redirect(
			$url
		);

		exit;
	}
}
