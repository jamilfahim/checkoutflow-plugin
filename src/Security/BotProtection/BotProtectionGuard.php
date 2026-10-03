<?php
/**
 * Bot Protection guard.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\BotProtection;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Security\ClientIpResolver;
use EilmoCheckout\Security\Logging\SecurityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Protects checkout against common automated
 * submissions.
 *
 * The same Security settings are shared by all checkout
 * integrations. Eilmo Checkout Flow already transports
 * the signed start token and Honeypot field directly.
 * Native WooCommerce integrations may pass equivalent
 * Security data through check_native().
 */
final class BotProtectionGuard {

	/**
	 * Check checkout Bot Protection.
	 *
	 * @param array<string,mixed> $payload Checkout payload.
	 * @param string              $source  Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check(
		array $payload,
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! SecuritySettings::is_enabled() ||
			$this->should_bypass_current_user()
		) {
			return true;
		}

		$config =
			$this->get_config();

		$honeypot =
			$this->get_array(
				$config,
				'honeypot'
			);

		$security_token =
			$this->get_array(
				$config,
				'security_token'
			);

		$minimum_checkout_time =
			$this->get_array(
				$config,
				'minimum_checkout_time'
			);

		$honeypot_enabled =
			'yes' ===
				(
					$honeypot[
						'enabled'
					] ??
						'yes'
				);

		$token_enabled =
			'yes' ===
				(
					$security_token[
						'enabled'
					] ??
						'yes'
				);

		$minimum_time_enabled =
			'yes' ===
				(
					$minimum_checkout_time[
						'enabled'
					] ??
						'yes'
				);

		/*
		 * Minimum Checkout Time is based on the signed
		 * token's issued_at value.
		 *
		 * Therefore a token is still required when
		 * Minimum Checkout Time is enabled, even if the
		 * standalone Security Token switch is disabled.
		 */
		$token_required =
			$token_enabled ||
			$minimum_time_enabled;

		$security =
			isset(
				$payload[
					'security'
				]
			) &&
			is_array(
				$payload[
					'security'
				]
			)
				? $payload[
					'security'
				]
				: array();

		/**
		 * Filters normalized Bot Protection data.
		 *
		 * Native WooCommerce integrations can use this
		 * filter or check_native() to provide an
		 * equivalent signed token / Honeypot payload
		 * without changing the guard itself.
		 *
		 * @param array<string,mixed> $security Security data.
		 * @param array<string,mixed> $payload  Checkout payload.
		 * @param string              $source   Checkout source.
		 */
		$security =
			apply_filters(
				'eilmo_cf/security/bot_protection_data',
				$security,
				$payload,
				$source
			);

		if ( ! is_array( $security ) ) {
			$security =
				array();
		}

		/*
		 * ---------------------------------------------
		 * Honeypot
		 * ---------------------------------------------
		 */
		if ( $honeypot_enabled ) {

			$honeypot_value =
				sanitize_text_field(
					(string) (
						$security[
							'honeypot'
						] ??
							''
					)
				);

			if ( '' !== $honeypot_value ) {

				$error =
					$this->get_bot_error(
						'checkout_bot_detected'
					);

				$this->log_honeypot_failure(
					$source,
					$error,
					403
				);

				/**
				 * Fires when Honeypot Protection blocks
				 * a checkout.
				 *
				 * @param string $source Checkout source.
				 */
				do_action(
					'eilmo_cf/security/honeypot_blocked',
					$source
				);

				return $error;
			}
		}

		/*
		 * Nothing else to verify when both signed-token
		 * protections are disabled.
		 */
		if ( ! $token_required ) {
			return true;
		}

		$start_token =
			sanitize_text_field(
				(string) (
					$security[
						'start_token'
					] ??
						''
				)
			);

		$minimum_seconds =
			max(
				1,
				absint(
					$minimum_checkout_time[
						'seconds'
					] ??
						3
				)
			);

		/*
		 * ---------------------------------------------
		 * Missing signed token
		 * ---------------------------------------------
		 */
		if ( '' === $start_token ) {

			$error =
				$this->get_bot_error(
					'missing_bot_protection_token'
				);

			$this->log_security_token_failure(
				$source,
				$error,
				403
			);

			do_action(
				'eilmo_cf/security/security_token_blocked',
				'missing',
				$source
			);

			return $error;
		}

		$token =
			new BotProtectionToken();

		$verified =
			$token->verify(
				$start_token
			);

		/*
		 * ---------------------------------------------
		 * Invalid / expired signed token
		 * ---------------------------------------------
		 */
		if (
			is_wp_error(
				$verified
			)
		) {
			$error_code =
				sanitize_key(
					(string) $verified
						->get_error_code()
				);

			if ( '' === $error_code ) {
				$error_code =
					'invalid_bot_protection_token';
			}

			$error =
				$this->get_bot_error(
					$error_code
				);

			$this->log_security_token_failure(
				$source,
				$error,
				403
			);

			do_action(
				'eilmo_cf/security/security_token_blocked',
				$error_code,
				$source
			);

			return $error;
		}

		if (
			! is_array(
				$verified
			)
		) {
			$error =
				$this->get_bot_error(
					'invalid_bot_protection_token'
				);

			$this->log_security_token_failure(
				$source,
				$error,
				403
			);

			do_action(
				'eilmo_cf/security/security_token_blocked',
				'invalid_bot_protection_token',
				$source
			);

			return $error;
		}

		/*
		 * Standalone Security Token validation is now
		 * complete. If Minimum Checkout Time is disabled
		 * there is no timing rule left to evaluate.
		 */
		if ( ! $minimum_time_enabled ) {
			return true;
		}

		$issued_at =
			absint(
				$verified[
					'issued_at'
				] ??
					0
			);

		if ( $issued_at <= 0 ) {

			$error =
				$this->get_bot_error(
					'invalid_bot_protection_token'
				);

			$this->log_security_token_failure(
				$source,
				$error,
				403
			);

			return $error;
		}

		$elapsed =
			max(
				0,
				time() -
					$issued_at
			);

		/*
		 * ---------------------------------------------
		 * Checkout submitted too quickly
		 * ---------------------------------------------
		 */
		if (
			$elapsed <
				$minimum_seconds
		) {
			$error =
				$this->get_bot_error(
					'checkout_too_fast'
				);

			$this->log_minimum_time_failure(
				$source,
				$elapsed,
				$minimum_seconds,
				$error,
				429
			);

			/**
			 * Fires when Minimum Checkout Time blocks
			 * a request.
			 *
			 * @param int    $elapsed         Elapsed seconds.
			 * @param int    $minimum_seconds Minimum seconds.
			 * @param string $source          Checkout source.
			 */
			do_action(
				'eilmo_cf/security/minimum_checkout_time_blocked',
				$elapsed,
				$minimum_seconds,
				$source
			);

			return $error;
		}

		return true;
	}

	/**
	 * Check Bot Protection using native WooCommerce
	 * Security data.
	 *
	 * This keeps the authoritative validation identical
	 * to Eilmo Checkout Flow while allowing the native
	 * WooCommerce bridge to provide its own transport
	 * mechanism for the token / Honeypot values.
	 *
	 * @param array<string,mixed> $security Security data.
	 * @param string              $source   Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check_native(
		array $security,
		string $source
	) {

		return $this->check(
			array(
				'security' =>
					$security,
			),
			$source
		);
	}

	/**
	 * Log a Honeypot failure when Activity Logging is
	 * enabled for Bot Protection.
	 *
	 * @param string   $source      Checkout source.
	 * @param WP_Error $error       Error.
	 * @param int      $http_status HTTP status.
	 *
	 * @return void
	 */
	private function log_honeypot_failure(
		string $source,
		WP_Error $error,
		int $http_status
	): void {

		if ( ! $this->should_log_bot() ) {
			return;
		}

		$logger =
			new SecurityLogger();

		$logger->honeypot(
			$source,
			$this->get_client_ip(),
			$error,
			$http_status
		);
	}

	/**
	 * Log Security Token failure.
	 *
	 * @param string   $source      Checkout source.
	 * @param WP_Error $error       Error.
	 * @param int      $http_status HTTP status.
	 *
	 * @return void
	 */
	private function log_security_token_failure(
		string $source,
		WP_Error $error,
		int $http_status
	): void {

		if ( ! $this->should_log_bot() ) {
			return;
		}

		$logger =
			new SecurityLogger();

		$logger->security_token(
			$source,
			$this->get_client_ip(),
			$error,
			$http_status
		);
	}

	/**
	 * Log Minimum Checkout Time failure.
	 *
	 * Only elapsed-time failures belong to the Minimum Checkout Time event.
	 *
	 * @param string   $source          Checkout source.
	 * @param int      $elapsed         Elapsed seconds.
	 * @param int      $minimum_seconds Required seconds.
	 * @param WP_Error $error           Error.
	 * @param int      $http_status     HTTP status.
	 *
	 * @return void
	 */
	private function log_minimum_time_failure(
		string $source,
		int $elapsed,
		int $minimum_seconds,
		WP_Error $error,
		int $http_status
	): void {

		if ( ! $this->should_log_bot() ) {
			return;
		}

		$logger =
			new SecurityLogger();

		$logger->minimum_checkout_time(
			$source,
			max(
				0,
				$elapsed
			),
			max(
				1,
				$minimum_seconds
			),
			$this->get_client_ip(),
			$error,
			$http_status
		);
	}

	/**
	 * Determine whether Bot events should be written to
	 * Security Activity Logs.
	 *
	 * @return bool
	 */
	private function should_log_bot(): bool {

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
				(
					$activity_logging[
						'enabled'
					] ??
						'yes'
				) &&
			'yes' ===
				(
					$activity_logging[
						'log_bot'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Determine whether the current trusted store user
	 * may bypass customer-facing Security protections.
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
	 * Get customer-facing Bot Protection error.
	 *
	 * A generic message is intentional so Security
	 * internals are not exposed to the customer.
	 *
	 * @param string $code Error code.
	 *
	 * @return WP_Error
	 */
	private function get_bot_error(
		string $code
	): WP_Error {

		$code =
			sanitize_key(
				$code
			);

		if ( '' === $code ) {
			$code =
				'checkout_bot_detected';
		}

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
						'bot_protection'
					] ??
						''
				)
			);

		if ( '' === $message ) {
			$message =
				__(
					'We could not verify this checkout request. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				);
		}

		return new WP_Error(
			$code,
			sanitize_text_field(
				$message
			)
		);
	}

	/**
	 * Get Bot Protection configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function get_config(): array {

		$settings =
			SecuritySettings::get_settings();

		return isset(
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
	}

	/**
	 * Get nested array.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param string              $key   Key.
	 *
	 * @return array<string,mixed>
	 */
	private function get_array(
		array $input,
		string $key
	): array {

		return isset(
			$input[
				$key
			]
		) &&
		is_array(
			$input[
				$key
			]
		)
			? $input[
				$key
			]
			: array();
	}

	/**
	 * Get current visitor IP.
	 *
	 * Used only for privacy-safe Security Activity
	 * identification.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {

		$resolver =
			new ClientIpResolver();

		return $resolver->get();
	}
}
