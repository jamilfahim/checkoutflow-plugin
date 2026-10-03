<?php
/**
 * Order Cooldown guard.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\OrderCooldown;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Security\Blacklist\BlacklistNormalizer;
use EilmoCheckout\Security\ClientIpResolver;
use EilmoCheckout\Security\Logging\SecurityLogger;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents a customer from placing another order
 * until the configured cooldown period has passed.
 *
 * Cooldown identity is stored only as an HMAC hash.
 * Raw phone, email and IP values are never stored in
 * the transient key or transient payload.
 */
final class OrderCooldownGuard {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX =
		'eilmo_cf_order_cooldown_';

	/**
	 * Check whether the current customer is still in
	 * the Order Cooldown window.
	 *
	 * @param array<string,mixed> $customer Customer data.
	 * @param string              $source   Checkout source.
	 *
	 * @return true|WP_Error
	 */
	public function check(
		array $customer = array(),
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! SecuritySettings::is_protection_enabled(
				'order_cooldown'
			) ||
			$this->should_bypass()
		) {
			return true;
		}

		$identity =
			$this->resolve_identity(
				$customer
			);

		if (
			empty( $identity['type'] ) ||
			empty( $identity['value'] )
		) {
			return true;
		}

		$key =
			$this->build_transient_key(
				(string) $identity['type'],
				(string) $identity['value']
			);

		if ( '' === $key ) {
			return true;
		}

		$record =
			get_transient(
				$key
			);

		if ( ! is_array( $record ) ) {
			return true;
		}

		$expires_at =
			absint(
				$record['expires_at'] ??
					0
			);

		$now = time();

		if (
			$expires_at <= 0 ||
			$expires_at <= $now
		) {
			delete_transient(
				$key
			);

			return true;
		}

		$remaining_seconds =
			max(
				1,
				$expires_at - $now
			);

		$error =
			new WP_Error(
				'order_cooldown_active',
				$this->get_cooldown_message(
					$remaining_seconds
				),
				array(
					'status' => 429,
					'remaining_seconds' =>
						$remaining_seconds,
					'order_id' =>
						absint(
							$record['order_id'] ??
								0
						),
				)
			);

		/*
		 * SecurityLogger performs the final Activity
		 * Logging master / log_cooldown gate.
		 *
		 * Raw identifiers are not persisted.
		 */
		$logger =
			new SecurityLogger();

		$logger->order_cooldown(
			(string) $identity['type'],
			(string) $identity['value'],
			$source,
			$remaining_seconds,
			absint(
				$record['order_id'] ??
					0
			),
			$error,
			429
		);

		/**
		 * Fires when Order Cooldown blocks checkout.
		 *
		 * The raw customer identifier is intentionally not
		 * exposed. Only the identity type and stored metadata
		 * are provided.
		 *
		 * @param string              $identity_type     Identity type.
		 * @param int                 $remaining_seconds Remaining seconds.
		 * @param array<string,mixed> $record            Cooldown record.
		 * @param string              $source            Checkout source.
		 */
		do_action(
			'eilmo_cf/security/order_cooldown_blocked',
			(string) $identity['type'],
			$remaining_seconds,
			$record,
			$source
		);

		return $error;
	}

	/**
	 * Record a successful order for future cooldown checks.
	 *
	 * @param WC_Order $order           Order.
	 * @param string   $checkout_source Checkout source.
	 *
	 * @return void
	 */
	public function record_order(
		WC_Order $order,
		string $checkout_source = SecurityLogger::SOURCE_EILMO
	): void {

		if (
			! SecuritySettings::is_protection_enabled(
				'order_cooldown'
			) ||
			$this->should_bypass()
		) {
			return;
		}

		if ( $order->get_id() <= 0 ) {
			return;
		}

		$customer =
			array(
				'customer_id' =>
					absint(
						$order->get_customer_id()
					),

				'billing_phone' =>
					(string) $order
						->get_billing_phone(),

				'billing_email' =>
					(string) $order
						->get_billing_email(),

				'billing_country' =>
					(string) $order
						->get_billing_country(),
			);

		$identity =
			$this->resolve_identity(
				$customer
			);

		if (
			empty( $identity['type'] ) ||
			empty( $identity['value'] )
		) {
			return;
		}

		$key =
			$this->build_transient_key(
				(string) $identity['type'],
				(string) $identity['value']
			);

		if ( '' === $key ) {
			return;
		}

		$duration =
			max(
				1,
				SecuritySettings::get_order_cooldown_seconds()
			);

		$now = time();

		$record =
			array(
				'order_id' =>
					$order->get_id(),

				'identity_type' =>
					sanitize_key(
						(string) $identity['type']
					),

				'checkout_source' =>
					sanitize_key(
						$checkout_source
					),

				'created_at' =>
					$now,

				'expires_at' =>
					$now + $duration,
			);

		set_transient(
			$key,
			$record,
			$duration
		);

		/**
		 * Fires after a successful order starts a cooldown.
		 *
		 * @param WC_Order            $order    Order.
		 * @param string              $type     Identity type.
		 * @param int                 $duration Cooldown seconds.
		 * @param array<string,mixed> $record   Cooldown record.
		 */
		do_action(
			'eilmo_cf/security/order_cooldown_recorded',
			$order,
			(string) $identity['type'],
			$duration,
			$record
		);
	}

	/**
	 * Resolve configured customer identity.
	 *
	 * Smart mode priority:
	 *
	 * 1. Logged-in / WooCommerce customer ID.
	 * 2. Billing phone.
	 * 3. Billing email.
	 * 4. Client IP.
	 *
	 * @param array<string,mixed> $customer Customer data.
	 *
	 * @return array{type:string,value:string}
	 */
	private function resolve_identity(
		array $customer
	): array {

		$config =
			$this->get_config();

		$identify_by =
			sanitize_key(
				(string) (
					$config['identify_by'] ??
						'smart'
				)
			);

		$customer_id =
			absint(
				$customer['customer_id'] ??
					get_current_user_id()
			);

		$phone =
			BlacklistNormalizer::normalize(
				'phone',
				$customer['billing_phone'] ??
					''
			);

		$email =
			BlacklistNormalizer::normalize(
				'email',
				$customer['billing_email'] ??
					''
			);

		$ip = '';

		if (
			'ip' === $identify_by ||
			'smart' === $identify_by
		) {
			$resolver =
				new ClientIpResolver();

			$ip =
				sanitize_text_field(
					(string) $resolver->get()
				);
		}

		$candidates =
			array(
				'customer_id' =>
					$customer_id > 0
						? (string) $customer_id
						: '',

				'phone' =>
					$phone,

				'email' =>
					$email,

				'ip' =>
					$ip,
			);

		if ( 'smart' !== $identify_by ) {
			return array(
				'type' =>
					$identify_by,

				'value' =>
					(string) (
						$candidates[
							$identify_by
						] ??
						''
					),
			);
		}

		foreach (
			array(
				'customer_id',
				'phone',
				'email',
				'ip',
			) as $type
		) {
			$value =
				(string) (
					$candidates[
						$type
					] ??
					''
				);

			if ( '' !== $value ) {
				return array(
					'type' =>
						$type,

					'value' =>
						$value,
				);
			}
		}

		return array(
			'type' => '',
			'value' => '',
		);
	}

	/**
	 * Build private transient key from normalized identity.
	 *
	 * @param string $type  Identity type.
	 * @param string $value Normalized identity value.
	 *
	 * @return string
	 */
	private function build_transient_key(
		string $type,
		string $value
	): string {

		$type =
			sanitize_key(
				$type
			);

		$value =
			trim(
				$value
			);

		if (
			'' === $type ||
			'' === $value
		) {
			return '';
		}

		$hash =
			hash_hmac(
				'sha256',
				$type . ':' . $value,
				wp_salt(
					'auth'
				)
			);

		return self::TRANSIENT_PREFIX .
			$hash;
	}

	/**
	 * Get Order Cooldown configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function get_config(): array {

		$settings =
			SecuritySettings::get_settings();

		if (
			empty( $settings['protection'] ) ||
			! is_array( $settings['protection'] ) ||
			empty(
				$settings['protection'][
					'order_cooldown'
				]
			) ||
			! is_array(
				$settings['protection'][
					'order_cooldown'
				]
			)
		) {
			return array();
		}

		return $settings['protection'][
			'order_cooldown'
		];
	}

	/**
	 * Determine whether the current user may bypass
	 * checkout Security protections.
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
			isset( $settings['advanced'] ) &&
			is_array( $settings['advanced'] )
				? $settings['advanced']
				: array();

		if (
			current_user_can(
				'manage_options'
			)
		) {
			return 'yes' ===
				(
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
				(
					$advanced[
						'bypass_shop_managers'
					] ??
					'yes'
				);
		}

		return false;
	}

	/**
	 * Build configured customer-facing cooldown message.
	 *
	 * @param int $remaining_seconds Remaining seconds.
	 *
	 * @return string
	 */
	private function get_cooldown_message(
		int $remaining_seconds
	): string {

		$settings =
			SecuritySettings::get_settings();

		$message =
			sanitize_text_field(
				(string) (
					$settings['messages']['cooldown'] ??
						__(
							'You recently placed an order. Please wait {remaining_time} before placing another order.',
							'eilmo-checkout-flow'
						)
				)
			);

		if ( '' === $message ) {
			$message =
				__(
					'You recently placed an order. Please wait {remaining_time} before placing another order.',
					'eilmo-checkout-flow'
				);
		}

		return str_replace(
			'{remaining_time}',
			$this->format_remaining_time(
				$remaining_seconds
			),
			$message
		);
	}

	/**
	 * Format remaining cooldown duration for customers.
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
				$seconds
			);

		$hours =
			(int) floor(
				$seconds /
				HOUR_IN_SECONDS
			);

		$minutes =
			(int) floor(
				(
					$seconds %
					HOUR_IN_SECONDS
				) /
				MINUTE_IN_SECONDS
			);

		$remaining_seconds =
			(int) (
				$seconds %
				MINUTE_IN_SECONDS
			);

		$parts = array();

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

		if (
			$minutes > 0 &&
			count( $parts ) < 2
		) {
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
			$remaining_seconds > 0 &&
			count( $parts ) < 2
		) {
			$parts[] =
				sprintf(
					/* translators: %d: number of seconds. */
					_n(
						'%d second',
						'%d seconds',
						$remaining_seconds,
						'eilmo-checkout-flow'
					),
					$remaining_seconds
				);
		}

		if ( empty( $parts ) ) {
			return __(
				'1 second',
				'eilmo-checkout-flow'
			);
		}

		return implode(
			' ',
			$parts
		);
	}
}
