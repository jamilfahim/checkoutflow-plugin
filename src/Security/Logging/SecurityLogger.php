<?php
/**
 * Security Activity Logger.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Logging;

use EilmoCheckout\Admin\SecuritySettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a common Security Activity logging API.
 *
 * Important:
 *
 * Security logging is intentionally fail-safe.
 *
 * A database/logging failure must never replace the
 * original checkout security decision.
 */
final class SecurityLogger {

	/**
	 * Checkout source: Eilmo Checkout Flow.
	 *
	 * @var string
	 */
	public const SOURCE_EILMO =
		'eilmo';

	/**
	 * Checkout source: WooCommerce Classic Checkout.
	 *
	 * @var string
	 */
	public const SOURCE_WOOCOMMERCE_CLASSIC =
		'woocommerce_classic';

	/**
	 * Checkout source: WooCommerce Checkout Block.
	 *
	 * @var string
	 */
	public const SOURCE_WOOCOMMERCE_BLOCK =
		'woocommerce_block';

	/**
	 * Protection: Blacklist IP.
	 *
	 * @var string
	 */
	public const TYPE_BLACKLIST_IP =
		'blacklist_ip';

	/**
	 * Protection: Blacklist Phone.
	 *
	 * @var string
	 */
	public const TYPE_BLACKLIST_PHONE =
		'blacklist_phone';

	/**
	 * Protection: Blacklist Email.
	 *
	 * @var string
	 */
	public const TYPE_BLACKLIST_EMAIL =
		'blacklist_email';

	/**
	 * Protection: Rate Limit.
	 *
	 * @var string
	 */
	public const TYPE_RATE_LIMIT =
		'rate_limit';

	/**
	 * Protection: Duplicate Order.
	 *
	 * @var string
	 */
	public const TYPE_DUPLICATE_ORDER =
		'duplicate_order';

	/**
	 * Protection: Honeypot.
	 *
	 * @var string
	 */
	public const TYPE_HONEYPOT =
		'honeypot';

	/**
	 * Protection: Minimum Checkout Time.
	 *
	 * @var string
	 */
	public const TYPE_MINIMUM_CHECKOUT_TIME =
		'minimum_checkout_time';


	/**
	 * Protection: Security Token.
	 *
	 * @var string
	 */
	public const TYPE_SECURITY_TOKEN =
		'security_token';

	/**
	 * Protection: Order Cooldown.
	 *
	 * @var string
	 */
	public const TYPE_ORDER_COOLDOWN =
		'order_cooldown';

	/**
	 * Repository.
	 *
	 * @var SecurityLogRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param SecurityLogRepository|null $repository Repository.
	 */
	public function __construct(
		?SecurityLogRepository $repository = null
	) {

		$this->repository =
			$repository instanceof
				SecurityLogRepository
				? $repository
				: new SecurityLogRepository();
	}

	/**
	 * Log blocked IP Blacklist attempt.
	 *
	 * @param string        $ip           Normalized IP.
	 * @param int           $blacklist_id Blacklist row ID.
	 * @param string        $source       Checkout source.
	 * @param WP_Error|null $error        Security error.
	 * @param int           $http_status  HTTP status.
	 *
	 * @return int
	 */
	public function blacklist_ip(
		string $ip,
		int $blacklist_id = 0,
		string $source = self::SOURCE_EILMO,
		?WP_Error $error = null,
		int $http_status = 403
	): int {

		return $this->log_identifier_block(
			self::TYPE_BLACKLIST_IP,
			'ip',
			$ip,
			$source,
			$error,
			$http_status,
			'blacklist',
			$blacklist_id
		);
	}

	/**
	 * Log blocked Phone Blacklist attempt.
	 *
	 * @param string        $phone        Normalized phone.
	 * @param int           $blacklist_id Blacklist row ID.
	 * @param string        $source       Checkout source.
	 * @param WP_Error|null $error        Security error.
	 * @param int           $http_status  HTTP status.
	 *
	 * @return int
	 */
	public function blacklist_phone(
		string $phone,
		int $blacklist_id = 0,
		string $source = self::SOURCE_EILMO,
		?WP_Error $error = null,
		int $http_status = 403
	): int {

		return $this->log_identifier_block(
			self::TYPE_BLACKLIST_PHONE,
			'phone',
			$phone,
			$source,
			$error,
			$http_status,
			'blacklist',
			$blacklist_id
		);
	}

	/**
	 * Log blocked Email Blacklist attempt.
	 *
	 * @param string        $email        Normalized email.
	 * @param int           $blacklist_id Blacklist row ID.
	 * @param string        $source       Checkout source.
	 * @param WP_Error|null $error        Security error.
	 * @param int           $http_status  HTTP status.
	 *
	 * @return int
	 */
	public function blacklist_email(
		string $email,
		int $blacklist_id = 0,
		string $source = self::SOURCE_EILMO,
		?WP_Error $error = null,
		int $http_status = 403
	): int {

		return $this->log_identifier_block(
			self::TYPE_BLACKLIST_EMAIL,
			'email',
			$email,
			$source,
			$error,
			$http_status,
			'blacklist',
			$blacklist_id
		);
	}

	/**
	 * Log Rate Limit block.
	 *
	 * The identifier hash should be the same hash used
	 * by the RateLimiter table so Activity Logs can later
	 * reset the exact persistent Rate Limit record.
	 *
	 * @param string        $identifier_hash   Rate Limit hash.
	 * @param string        $identifier_masked Safe display value.
	 * @param string        $source            Checkout source.
	 * @param WP_Error|null $error             Security error.
	 * @param int           $http_status       HTTP status.
	 *
	 * @return int
	 */
	public function rate_limit(
		string $identifier_hash,
		string $identifier_masked = '',
		string $source = self::SOURCE_EILMO,
		?WP_Error $error = null,
		int $http_status = 429
	): int {

		$identifier_hash =
			$this->normalize_hash(
				$identifier_hash
			);

		if (
			'' ===
				$identifier_hash
		) {
			return 0;
		}

		return $this->safe_log(
			array(
				'protection_type' =>
					self::TYPE_RATE_LIMIT,

				'checkout_source' =>
					$this->normalize_source(
						$source
					),

				'status' =>
					'blocked',

				'identifier_type' =>
					'ip',

				'identifier_hash' =>
					$identifier_hash,

				'identifier_masked' =>
					sanitize_text_field(
						$identifier_masked
					),

				'reason_code' =>
					$this->get_error_code(
						$error,
						'rate_limit_exceeded'
					),

				'message' =>
					$this->get_error_message(
						$error,
						__(
							'Too many orders were submitted within the configured Rate Limit window.',
							'eilmo-checkout-flow'
						)
					),

				'http_status' =>
					$this->normalize_http_status(
						$http_status
					),

				'target_type' =>
					'rate_limit',

				'target_hash' =>
					$identifier_hash,
			)
		);
	}

	/**
	 * Log Duplicate Order block.
	 *
	 * The fingerprint is already an HMAC hash and is
	 * therefore safe to store as the persistent target.
	 *
	 * @param string        $fingerprint_hash Fingerprint.
	 * @param string        $source           Checkout source.
	 * @param WP_Error|null $error            Security error.
	 * @param int           $order_id         Existing order ID.
	 * @param int           $http_status      HTTP status.
	 *
	 * @return int
	 */
	public function duplicate_order(
		string $fingerprint_hash,
		string $source = self::SOURCE_EILMO,
		?WP_Error $error = null,
		int $order_id = 0,
		int $http_status = 409
	): int {

		$fingerprint_hash =
			$this->normalize_hash(
				$fingerprint_hash
			);

		if (
			'' ===
				$fingerprint_hash
		) {
			return 0;
		}

		return $this->safe_log(
			array(
				'protection_type' =>
					self::TYPE_DUPLICATE_ORDER,

				'checkout_source' =>
					$this->normalize_source(
						$source
					),

				'status' =>
					'blocked',

				'identifier_type' =>
					'fingerprint',

				'identifier_hash' =>
					$fingerprint_hash,

				'identifier_masked' =>
					$this->repository
						->mask_identifier(
							'fingerprint',
							$fingerprint_hash
						),

				'reason_code' =>
					$this->get_error_code(
						$error,
						'duplicate_order_detected'
					),

				'message' =>
					$this->get_error_message(
						$error,
						__(
							'A similar order was recently submitted.',
							'eilmo-checkout-flow'
						)
					),

				'http_status' =>
					$this->normalize_http_status(
						$http_status
					),

				'order_id' =>
					absint(
						$order_id
					),

				'target_type' =>
					'duplicate_order',

				'target_hash' =>
					$fingerprint_hash,
			)
		);
	}

	/**
	 * Log Honeypot block.
	 *
	 * Honeypot does not create a persistent block,
	 * therefore there is no target_type or target hash.
	 *
	 * Optional IP is used only for privacy-safe Activity
	 * Log identification.
	 *
	 * @param string        $source      Checkout source.
	 * @param string        $ip          Visitor IP.
	 * @param WP_Error|null $error       Security error.
	 * @param int           $http_status HTTP status.
	 *
	 * @return int
	 */
	public function honeypot(
		string $source = self::SOURCE_EILMO,
		string $ip = '',
		?WP_Error $error = null,
		int $http_status = 403
	): int {

		return $this->log_nonpersistent_ip_event(
			self::TYPE_HONEYPOT,
			$source,
			$ip,
			$error,
			'checkout_bot_detected',
			__(
				'A suspicious automated checkout submission was detected.',
				'eilmo-checkout-flow'
			),
			$http_status
		);
	}

	/**
	 * Log Minimum Checkout Time block.
	 *
	 * Minimum Checkout Time is not a persistent block.
	 *
	 * @param string        $source          Checkout source.
	 * @param int           $elapsed         Elapsed seconds.
	 * @param int           $minimum_seconds Required seconds.
	 * @param string        $ip              Visitor IP.
	 * @param WP_Error|null $error           Security error.
	 * @param int           $http_status     HTTP status.
	 *
	 * @return int
	 */
	public function minimum_checkout_time(
		string $source,
		int $elapsed,
		int $minimum_seconds,
		string $ip = '',
		?WP_Error $error = null,
		int $http_status = 429
	): int {

		$elapsed =
			max(
				0,
				absint(
					$elapsed
				)
			);

		$minimum_seconds =
			max(
				1,
				absint(
					$minimum_seconds
				)
			);

		$fallback_message =
			sprintf(
				/* translators: 1: elapsed seconds, 2: required seconds. */
				__(
					'Checkout was submitted in %1$d second(s). Minimum required time is %2$d second(s).',
					'eilmo-checkout-flow'
				),
				$elapsed,
				$minimum_seconds
			);

		return $this->log_nonpersistent_ip_event(
			self::TYPE_MINIMUM_CHECKOUT_TIME,
			$source,
			$ip,
			$error,
			'checkout_too_fast',
			$fallback_message,
			$http_status
		);
	}

	/**
	 * Log Security Token failure.
	 *
	 * Security Token is not a persistent restriction.
	 * Only a privacy-safe IP hash/mask may be stored.
	 *
	 * @param string        $source      Checkout source.
	 * @param string        $ip          Visitor IP.
	 * @param WP_Error|null $error       Security error.
	 * @param int           $http_status HTTP status.
	 *
	 * @return int
	 */
	public function security_token(
		string $source,
		string $ip = '',
		?WP_Error $error = null,
		int $http_status = 403
	): int {

		return $this->log_nonpersistent_ip_event(
			self::TYPE_SECURITY_TOKEN,
			$source,
			$ip,
			$error,
			'invalid_bot_protection_token',
			__(
				'The checkout security token could not be verified.',
				'eilmo-checkout-flow'
			),
			$http_status
		);
	}

	/**
	 * Log active Order Cooldown block.
	 *
	 * Raw customer identifiers are never stored. The
	 * repository receives only an HMAC hash and a safe
	 * masked display value.
	 *
	 * @param string        $identity_type     Identity type.
	 * @param string        $identity_value    Identity value.
	 * @param string        $source            Checkout source.
	 * @param int           $remaining_seconds Remaining seconds.
	 * @param int           $order_id          Previous order ID.
	 * @param WP_Error|null $error             Security error.
	 * @param int           $http_status       HTTP status.
	 *
	 * @return int
	 */
	public function order_cooldown(
		string $identity_type,
		string $identity_value,
		string $source,
		int $remaining_seconds,
		int $order_id = 0,
		?WP_Error $error = null,
		int $http_status = 429
	): int {

		$identity_type =
			sanitize_key(
				$identity_type
			);

		$identity_value =
			trim(
				$identity_value
			);

		$identifier_hash = '';
		$identifier_masked = '';

		if ( '' !== $identity_value ) {

			$identifier_hash =
				$this->repository
					->hash_identifier(
						$identity_value
					);

			switch ( $identity_type ) {

				case 'phone':
				$identifier_masked =
					$this->repository
						->mask_identifier(
							'phone',
							$identity_value
						);
				break;

				case 'email':
				$identifier_masked =
					$this->repository
						->mask_identifier(
							'email',
							$identity_value
						);
				break;

				case 'ip':
				$identifier_masked =
					$this->repository
						->mask_identifier(
							'ip',
							$identity_value
						);
				break;

				case 'customer_id':
					$identifier_masked =
						$this->repository
							->mask_identifier(
								'customer_id',
								$identity_value
							);
				break;

				default:
					$identity_type = 'customer';
					$identifier_masked =
						$this->repository
							->mask_identifier(
								'customer',
								$identity_value
							);
				break;
			}
		}

		$remaining_seconds =
			max(
				1,
				absint(
					$remaining_seconds
				)
			);

		$fallback_message =
			sprintf(
				/* translators: %d: remaining cooldown seconds. */
				__(
					'Customer is still within the Order Cooldown window. %d second(s) remaining.',
					'eilmo-checkout-flow'
				),
				$remaining_seconds
			);

		return $this->safe_log(
			array(
				'protection_type' =>
					self::TYPE_ORDER_COOLDOWN,

				'checkout_source' =>
					$this->normalize_source(
						$source
					),

				'status' =>
					'blocked',

				'identifier_type' =>
					$identity_type,

				'identifier_hash' =>
					$identifier_hash,

				'identifier_masked' =>
					sanitize_text_field(
						$identifier_masked
					),

				'reason_code' =>
					$this->get_error_code(
						$error,
						'order_cooldown_active'
					),

				'message' =>
					$this->get_error_message(
						$error,
						$fallback_message
					),

				'http_status' =>
					$this->normalize_http_status(
						$http_status
					),

				'order_id' =>
					absint(
						$order_id
					),
			)
		);
	}

	/**
	 * Log persistent Blacklist identifier event.
	 *
	 * @param string        $protection_type Protection type.
	 * @param string        $identifier_type Identifier type.
	 * @param string        $identifier      Identifier.
	 * @param string        $source          Source.
	 * @param WP_Error|null $error           Error.
	 * @param int           $http_status     HTTP status.
	 * @param string        $target_type     Target type.
	 * @param int           $target_id       Target ID.
	 *
	 * @return int
	 */
	private function log_identifier_block(
		string $protection_type,
		string $identifier_type,
		string $identifier,
		string $source,
		?WP_Error $error,
		int $http_status,
		string $target_type,
		int $target_id
	): int {

		$identifier =
			trim(
				$identifier
			);

		$identifier_hash =
			$this->repository
				->hash_identifier(
					$identifier
				);

		$identifier_masked =
			$this->repository
				->mask_identifier(
					$identifier_type,
					$identifier
				);

		return $this->safe_log(
			array(
				'protection_type' =>
					$protection_type,

				'checkout_source' =>
					$this->normalize_source(
						$source
					),

				'status' =>
					'blocked',

				'identifier_type' =>
					$identifier_type,

				'identifier_hash' =>
					$identifier_hash,

				'identifier_masked' =>
					$identifier_masked,

				'reason_code' =>
					$this->get_error_code(
						$error,
						'checkout_restricted'
					),

				'message' =>
					$this->get_error_message(
						$error,
						__(
							'This customer is blocked from checkout.',
							'eilmo-checkout-flow'
						)
					),

				'http_status' =>
					$this->normalize_http_status(
						$http_status
					),

				'target_type' =>
					$target_type,

				'target_id' =>
					absint(
						$target_id
					),
			)
		);
	}

	/**
	 * Log non-persistent security event using an
	 * optional visitor IP.
	 *
	 * @param string        $protection_type Protection type.
	 * @param string        $source          Checkout source.
	 * @param string        $ip              Visitor IP.
	 * @param WP_Error|null $error           Error.
	 * @param string        $fallback_code   Fallback code.
	 * @param string        $fallback_message Fallback message.
	 * @param int           $http_status     HTTP status.
	 *
	 * @return int
	 */
	private function log_nonpersistent_ip_event(
		string $protection_type,
		string $source,
		string $ip,
		?WP_Error $error,
		string $fallback_code,
		string $fallback_message,
		int $http_status
	): int {

		$ip =
			trim(
				$ip
			);

		$identifier_hash =
			'';

		$identifier_masked =
			'';

		if (
			'' !==
				$ip
		) {
			$identifier_hash =
				$this->repository
					->hash_identifier(
						$ip
					);

			$identifier_masked =
				$this->repository
					->mask_identifier(
						'ip',
						$ip
					);
		}

		return $this->safe_log(
			array(
				'protection_type' =>
					$protection_type,

				'checkout_source' =>
					$this->normalize_source(
						$source
					),

				'status' =>
					'blocked',

				'identifier_type' =>
					'' !==
						$ip
							? 'ip'
							: '',

				'identifier_hash' =>
					$identifier_hash,

				'identifier_masked' =>
					$identifier_masked,

				'reason_code' =>
					$this->get_error_code(
						$error,
						$fallback_code
					),

				'message' =>
					$this->get_error_message(
						$error,
						$fallback_message
					),

				'http_status' =>
					$this->normalize_http_status(
						$http_status
					),
			)
		);
	}

	/**
	 * Safely create Activity Log.
	 *
	 * Logging must never interrupt checkout security.
	 *
	 * @param array<string,mixed> $data Log data.
	 *
	 * @return int
	 */
	private function safe_log(
		array $data
	): int {

		$protection_type =
			sanitize_key(
				(string) (
					$data[
						'protection_type'
					] ??
						''
				)
			);

		if (
			! $this->should_log(
				$protection_type
			)
		) {
			return 0;
		}

		try {

			$result =
				$this->repository
					->log(
						$data
					);

		} catch ( \Throwable $throwable ) {

			do_action(
				'eilmo_cf/security/log_failed',
				$data,
				$throwable
			);

			return 0;
		}

		if (
			is_wp_error(
				$result
			)
		) {
			do_action(
				'eilmo_cf/security/log_failed',
				$data,
				$result
			);

			return 0;
		}

		return absint(
			$result
		);
	}

	/**
	 * Determine whether a protection event should be
	 * written to Security Activity Logs.
	 *
	 * This is the final central gate. Individual guards
	 * may also check their logging setting early, but
	 * callers cannot accidentally bypass the Activity
	 * Logging master switch by using SecurityLogger
	 * directly.
	 *
	 * @param string $protection_type Protection type.
	 *
	 * @return bool
	 */
	private function should_log(
		string $protection_type
	): bool {

		if ( ! SecuritySettings::is_enabled() ) {
			return false;
		}

		$settings =
			SecuritySettings::get_settings();

		$activity =
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

		if (
			'yes' !==
				(
					$activity[
						'enabled'
					] ??
						'yes'
				)
		) {
			return false;
		}

		$setting_key = '';

		switch ( $protection_type ) {

			case self::TYPE_BLACKLIST_IP:
			case self::TYPE_BLACKLIST_PHONE:
			case self::TYPE_BLACKLIST_EMAIL:
				$setting_key =
					'log_blacklist';
			break;

			case self::TYPE_RATE_LIMIT:
				$setting_key =
					'log_rate_limit';
			break;

			case self::TYPE_DUPLICATE_ORDER:
				$setting_key =
					'log_duplicate';
			break;

			case self::TYPE_HONEYPOT:
			case self::TYPE_SECURITY_TOKEN:
			case self::TYPE_MINIMUM_CHECKOUT_TIME:
				$setting_key =
					'log_bot';
			break;

			case self::TYPE_ORDER_COOLDOWN:
				$setting_key =
					'log_cooldown';
			break;

			default:
				return false;
		}

		return (
			'yes' ===
				(
					$activity[
						$setting_key
					] ??
						'yes'
				)
		);
	}

	/**
	 * Get WP_Error code.
	 *
	 * @param WP_Error|null $error    Error.
	 * @param string        $fallback Fallback.
	 *
	 * @return string
	 */
	private function get_error_code(
		?WP_Error $error,
		string $fallback
	): string {

		if (
			$error instanceof
				WP_Error
		) {
			$code =
				sanitize_key(
					(string) $error
						->get_error_code()
				);

			if (
				'' !==
					$code
			) {
				return $code;
			}
		}

		return sanitize_key(
			$fallback
		);
	}

	/**
	 * Get WP_Error message.
	 *
	 * @param WP_Error|null $error    Error.
	 * @param string        $fallback Fallback.
	 *
	 * @return string
	 */
	private function get_error_message(
		?WP_Error $error,
		string $fallback
	): string {

		if (
			$error instanceof
				WP_Error
		) {
			$message =
				sanitize_text_field(
					(string) $error
						->get_error_message()
				);

			if (
				'' !==
					$message
			) {
				return $message;
			}
		}

		return sanitize_text_field(
			$fallback
		);
	}

	/**
	 * Normalize checkout source.
	 *
	 * @param string $source Source.
	 *
	 * @return string
	 */
	private function normalize_source(
		string $source
	): string {

		$source =
			sanitize_key(
				$source
			);

		$allowed =
			array(
				self::SOURCE_EILMO,
				self::SOURCE_WOOCOMMERCE_CLASSIC,
				self::SOURCE_WOOCOMMERCE_BLOCK,
			);

		return in_array(
			$source,
			$allowed,
			true
		)
			? $source
			: self::SOURCE_EILMO;
	}

	/**
	 * Normalize HTTP status.
	 *
	 * @param int $status HTTP status.
	 *
	 * @return int
	 */
	private function normalize_http_status(
		int $status
	): int {

		$status =
			absint(
				$status
			);

		if (
			$status < 100 ||
			$status > 599
		) {
			return 0;
		}

		return $status;
	}

	/**
	 * Normalize SHA-256 hash.
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
}