<?php
/**
 * Customer Blacklist guard.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Blacklist;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Security\ClientIpResolver;
use EilmoCheckout\Security\Logging\SecurityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Protects checkout against blacklisted customers.
 *
 * The same Security settings are used by:
 * - Eilmo Checkout Flow.
 * - WooCommerce Classic Checkout.
 * - WooCommerce Checkout Block / Store API.
 */
final class BlacklistGuard {

	/**
	 * Check current visitor IP address.
	 *
	 * @param string $source Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check_ip(
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! $this->is_enabled() ||
			$this->should_bypass()
		) {
			return true;
		}

		$ip_address =
			$this->get_client_ip();

		if ( '' === $ip_address ) {
			return true;
		}

		$repository =
			new BlacklistRepository();

		if (
			! $repository->is_blocked(
				'ip',
				$ip_address
			)
		) {
			return true;
		}

		$record =
			$this->find_active_record(
				$repository,
				'ip',
				$ip_address
			);

		$blacklist_id =
			is_array( $record )
				? absint(
					$record[
						'id'
					] ??
						0
				)
				: 0;

		$error =
			$this->get_blocked_error();

		/*
		 * ---------------------------------------------
		 * Security Activity Log
		 * ---------------------------------------------
		 *
		 * Logging has its own Security setting. The
		 * blacklist decision must still be enforced when
		 * Activity Logging is disabled.
		 */
		if ( $this->should_log_blacklist() ) {
			$logger =
				new SecurityLogger();

			$logger->blacklist_ip(
				$ip_address,
				$blacklist_id,
				$source,
				$error,
				403
			);
		}

		/**
		 * Fires when checkout is blocked by IP.
		 *
		 * @param string $type         Blacklist type.
		 * @param string $value        Matched value.
		 * @param string $source       Checkout source.
		 * @param int    $blacklist_id Blacklist row ID.
		 */
		do_action(
			'eilmo_cf/security/blacklist_blocked',
			'ip',
			$ip_address,
			$source,
			$blacklist_id
		);

		return $error;
	}

	/**
	 * Check validated customer information.
	 *
	 * @param array<string, mixed> $validated Validated checkout.
	 * @param string               $source    Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check_customer(
		array $validated,
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! $this->is_enabled() ||
			$this->should_bypass()
		) {
			return true;
		}

		$customer =
			isset(
				$validated[
					'customer'
				]
			) &&
			is_array(
				$validated[
					'customer'
				]
			)
				? $validated[
					'customer'
				]
				: array();

		$phone =
			sanitize_text_field(
				(string) (
					$customer[
						'billing_phone'
					] ??
						''
				)
			);

		$email =
			sanitize_email(
				(string) (
					$customer[
						'billing_email'
					] ??
						''
				)
			);

		$repository =
			new BlacklistRepository();

		/*
		 * ---------------------------------------------
		 * Phone
		 * ---------------------------------------------
		 */
		if (
			'' !== $phone &&
			$repository->is_blocked(
				'phone',
				$phone
			)
		) {
			$record =
				$this->find_active_record(
					$repository,
					'phone',
					$phone
				);

			$blacklist_id =
				is_array( $record )
					? absint(
						$record[
							'id'
						] ??
							0
					)
					: 0;

			$error =
				$this->get_blocked_error();

			if ( $this->should_log_blacklist() ) {
				$logger =
					new SecurityLogger();

				$logger->blacklist_phone(
					$this->normalize_for_lookup(
						'phone',
						$phone
					),
					$blacklist_id,
					$source,
					$error,
					403
				);
			}

			/**
			 * Fires when checkout is blocked by phone.
			 *
			 * @param string $type         Blacklist type.
			 * @param string $value        Matched value.
			 * @param string $source       Checkout source.
			 * @param int    $blacklist_id Blacklist row ID.
			 */
			do_action(
				'eilmo_cf/security/blacklist_blocked',
				'phone',
				$phone,
				$source,
				$blacklist_id
			);

			return $error;
		}

		/*
		 * ---------------------------------------------
		 * Email
		 * ---------------------------------------------
		 */
		if (
			'' !== $email &&
			$repository->is_blocked(
				'email',
				$email
			)
		) {
			$record =
				$this->find_active_record(
					$repository,
					'email',
					$email
				);

			$blacklist_id =
				is_array( $record )
					? absint(
						$record[
							'id'
						] ??
							0
					)
					: 0;

			$error =
				$this->get_blocked_error();

			if ( $this->should_log_blacklist() ) {
				$logger =
					new SecurityLogger();

				$logger->blacklist_email(
					$this->normalize_for_lookup(
						'email',
						$email
					),
					$blacklist_id,
					$source,
					$error,
					403
				);
			}

			/**
			 * Fires when checkout is blocked by email.
			 *
			 * @param string $type         Blacklist type.
			 * @param string $value        Matched value.
			 * @param string $source       Checkout source.
			 * @param int    $blacklist_id Blacklist row ID.
			 */
			do_action(
				'eilmo_cf/security/blacklist_blocked',
				'email',
				$email,
				$source,
				$blacklist_id
			);

			return $error;
		}

		return true;
	}

	/**
	 * Find the active Blacklist record responsible for
	 * the current block.
	 *
	 * BlacklistRepository remains authoritative for the
	 * actual block decision through is_blocked().
	 *
	 * This secondary lookup exists only so Activity Logs
	 * can retain the exact persistent Blacklist row ID
	 * required by the Unblock action.
	 *
	 * @param BlacklistRepository $repository Repository.
	 * @param string              $type       Identifier type.
	 * @param string              $value      Identifier value.
	 *
	 * @return array<string,mixed>|null
	 */
	private function find_active_record(
		BlacklistRepository $repository,
		string $type,
		string $value
	): ?array {

		$normalized =
			$this->normalize_for_lookup(
				$type,
				$value
			);

		if ( '' === $normalized ) {
			return null;
		}

		$record =
			$repository->find(
				$type,
				$normalized
			);

		if (
			! is_array(
				$record
			)
		) {
			return null;
		}

		if (
			'active' !==
				(
					$record[
						'status'
					] ??
						''
				)
		) {
			return null;
		}

		return $record;
	}

	/**
	 * Normalize Blacklist identifier for repository lookup.
	 *
	 * The repository remains authoritative for the block
	 * decision. This normalized value is used to retrieve
	 * the already-matched row and for privacy-safe logging.
	 *
	 * @param string $type  Identifier type.
	 * @param string $value Identifier.
	 *
	 * @return string
	 */
	private function normalize_for_lookup(
		string $type,
		string $value
	): string {

		return BlacklistNormalizer::normalize_for_blacklist(
			$type,
			$value
		);
	}

	/**
	 * Determine whether Customer Blacklist protection is enabled.
	 *
	 * SecuritySettings is the single source of truth for both
	 * Eilmo and native WooCommerce checkout flows.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		return SecuritySettings::is_protection_enabled(
			'blacklist'
		);
	}

	/**
	 * Determine whether current trusted admin user may bypass
	 * customer-facing Security protections.
	 *
	 * @return bool
	 */
	private function should_bypass(): bool {

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

		if (
			current_user_can(
				'manage_options'
			)
		) {
			return 'yes' ===
				(string) (
					$advanced[
						'bypass_administrators'
					] ??
						'yes'
				);
		}

		if (
			current_user_can(
				'manage_woocommerce'
			)
		) {
			return 'yes' ===
				(string) (
					$advanced[
						'bypass_shop_managers'
					] ??
						'yes'
				);
		}

		return false;
	}

	/**
	 * Determine whether blocked blacklist events should be logged.
	 *
	 * Blacklist blocking itself does not depend on Activity Logging.
	 *
	 * @return bool
	 */
	private function should_log_blacklist(): bool {

		$settings =
			SecuritySettings::get_settings();

		$activity_logging =
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
				(string) (
					$activity_logging[
						'enabled'
					] ??
						'yes'
				) &&
			'yes' ===
				(string) (
					$activity_logging[
						'log_blacklist'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Get current client IP.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {

		$resolver =
			new ClientIpResolver();

		return $resolver->get();
	}

	/**
	 * Get configured blocked-checkout error.
	 *
	 * The message deliberately does not expose whether IP,
	 * phone or email caused the block.
	 *
	 * @return WP_Error
	 */
	private function get_blocked_error(): WP_Error {

		$settings =
			SecuritySettings::get_settings();

		$message =
			sanitize_text_field(
				(string) (
					$settings[
						'messages'
					][
						'blacklist'
					] ??
						__(
							'This checkout request cannot be processed. Please contact us if you need assistance.',
							'eilmo-checkout-flow'
						)
				)
			);

		if ( '' === $message ) {
			$message =
				__(
					'This checkout request cannot be processed. Please contact us if you need assistance.',
					'eilmo-checkout-flow'
				);
		}

		return new WP_Error(
			'checkout_restricted',
			$message,
			array(
				'status' => 403,
			)
		);
	}
}
