<?php
/**
 * Coupon customer identity.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and normalizes customer identity
 * for coupon eligibility and usage checks.
 *
 * Customer identity can come from:
 * - Logged-in WordPress / WooCommerce customer.
 * - Checkout billing email.
 * - Checkout billing phone.
 *
 * This service only resolves customer identity.
 * Contact verification belongs to the final
 * checkout / order submission flow.
 */
final class CustomerIdentity {

	/**
	 * Email identification mode.
	 *
	 * @var string
	 */
	public const MODE_EMAIL = 'email';

	/**
	 * Phone identification mode.
	 *
	 * @var string
	 */
	public const MODE_PHONE = 'phone';

	/**
	 * Email or Phone identification mode.
	 *
	 * Either matching identifier is enough to
	 * treat an order as belonging to the same
	 * customer.
	 *
	 * @var string
	 */
	public const MODE_EMAIL_OR_PHONE = 'email_or_phone';

	/**
	 * Resolve customer identity.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'customer_id'     => 10,
	 *     'customer_email'  => 'customer@example.com',
	 *     'customer_phone'  => '01712345678',
	 *     'billing_country' => 'BD',
	 * )
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve(
		array $context = array()
	): array {

		$customer_id =
			absint(
				$context['customer_id'] ??
					get_current_user_id()
			);

		$email =
			$this->resolve_email(
				$customer_id,
				$context
			);

		$country =
			$this->resolve_country(
				$customer_id,
				$context
			);

		$phone =
			$this->resolve_phone(
				$customer_id,
				$context,
				$country
			);

		$identity = array(
			'customer_id' =>
				$customer_id,

			'customer_email' =>
				$email,

			'customer_phone' =>
				$phone,

			'billing_country' =>
				$country,

			'has_user_id' =>
				$customer_id > 0,

			'has_email' =>
				'' !== $email,

			'has_phone' =>
				'' !== $phone,
		);

		$identity['identity_known'] =
			$identity['has_user_id'] ||
			$identity['has_email'] ||
			$identity['has_phone'];

		/**
		 * Filters resolved coupon customer identity.
		 *
		 * @param array<string, mixed> $identity Identity.
		 * @param array<string, mixed> $context  Original context.
		 */
		$identity =
			apply_filters(
				'eilmo_cf/coupons/customer_identity',
				$identity,
				$context
			);

		return is_array( $identity )
			? $identity
			: array();
	}

	/**
	 * Determine whether required identity exists.
	 *
	 * Logged-in customer ID is sufficient for
	 * immediate customer identification.
	 *
	 * Guest customers are identified according
	 * to the configured email / phone mode.
	 *
	 * @param array<string, mixed> $identity Identity.
	 * @param string               $mode     Identification mode.
	 *
	 * @return bool
	 */
	public function has_required_identity(
		array $identity,
		string $mode
	): bool {

		if (
			absint(
				$identity['customer_id'] ??
					0
			) > 0
		) {
			return true;
		}

		$mode =
			$this->normalize_mode(
				$mode
			);

		$has_email =
			'' !== (
				$identity['customer_email'] ??
					''
			);

		$has_phone =
			'' !== (
				$identity['customer_phone'] ??
					''
			);

		switch ( $mode ) {

			case self::MODE_EMAIL:
				return $has_email;

			case self::MODE_PHONE:
				return $has_phone;

			case self::MODE_EMAIL_OR_PHONE:
			default:
				return (
					$has_email ||
					$has_phone
				);
		}
	}

	/**
	 * Get identifiers used for matching historical
	 * customer orders.
	 *
	 * Logged-in users retain their customer ID.
	 *
	 * In email_or_phone mode either matching
	 * email or phone identifies the same customer.
	 *
	 * @param array<string, mixed> $identity Identity.
	 * @param string               $mode     Mode.
	 *
	 * @return array<string, mixed>
	 */
	public function get_match_identifiers(
		array $identity,
		string $mode
	): array {

		$mode =
			$this->normalize_mode(
				$mode
			);

		$identifiers = array(
			'customer_id' =>
				absint(
					$identity['customer_id'] ??
						0
				),

			'customer_email' =>
				'',

			'customer_phone' =>
				'',
		);

		if (
			in_array(
				$mode,
				array(
					self::MODE_EMAIL,
					self::MODE_EMAIL_OR_PHONE,
				),
				true
			)
		) {
			$identifiers['customer_email'] =
				$this->normalize_email(
					$identity['customer_email'] ??
						''
				);
		}

		if (
			in_array(
				$mode,
				array(
					self::MODE_PHONE,
					self::MODE_EMAIL_OR_PHONE,
				),
				true
			)
		) {
			$identifiers['customer_phone'] =
				$this->normalize_phone(
					$identity['customer_phone'] ??
						'',
					$identity['billing_country'] ??
						''
				);
		}

		return $identifiers;
	}

	/**
	 * Normalize identification mode.
	 *
	 * @param mixed $mode Mode.
	 *
	 * @return string
	 */
	public function normalize_mode(
		$mode
	): string {

		$mode =
			sanitize_key(
				(string) $mode
			);

		if (
			! in_array(
				$mode,
				array(
					self::MODE_EMAIL,
					self::MODE_PHONE,
					self::MODE_EMAIL_OR_PHONE,
				),
				true
			)
		) {
			return self::MODE_EMAIL_OR_PHONE;
		}

		return $mode;
	}

	/**
	 * Normalize email.
	 *
	 * @param mixed $email Email.
	 *
	 * @return string
	 */
	public function normalize_email(
		$email
	): string {

		$email =
			sanitize_email(
				(string) $email
			);

		if ( '' === $email ) {
			return '';
		}

		return strtolower(
			$email
		);
	}

	/**
	 * Normalize phone number.
	 *
	 * This does not perform contact verification.
	 * It only creates a stable comparison value.
	 *
	 * Bangladesh examples:
	 *
	 * 01712345678
	 * +8801712345678
	 * 8801712345678
	 *
	 * all normalize to:
	 *
	 * 8801712345678
	 *
	 * Other countries fall back to digits-only
	 * normalization unless filtered.
	 *
	 * @param mixed $phone   Phone.
	 * @param mixed $country Billing country.
	 *
	 * @return string
	 */
	public function normalize_phone(
		$phone,
		$country = ''
	): string {

		$raw_phone =
			sanitize_text_field(
				(string) $phone
			);

		if ( '' === $raw_phone ) {
			return '';
		}

		$country =
			strtoupper(
				sanitize_key(
					(string) $country
				)
			);

		$phone =
			preg_replace(
				'/[^0-9]+/',
				'',
				$raw_phone
			);

		if (
			! is_string( $phone ) ||
			'' === $phone
		) {
			return '';
		}

		/*
		 * International dialing prefix.
		 *
		 * 00880... -> 880...
		 */
		if (
			0 === strpos(
				$phone,
				'00'
			)
		) {
			$phone =
				substr(
					$phone,
					2
				);
		}

		/*
		 * Bangladesh normalization.
		 */
		if ( 'BD' === $country ) {

			/*
			 * 01712345678
			 * ->
			 * 8801712345678
			 */
			if (
				11 === strlen( $phone ) &&
				0 === strpos(
					$phone,
					'01'
				)
			) {
				$phone =
					'880' .
					substr(
						$phone,
						1
					);
			}

			/*
			 * 1712345678
			 * ->
			 * 8801712345678
			 */
			if (
				10 === strlen( $phone ) &&
				0 === strpos(
					$phone,
					'1'
				)
			) {
				$phone =
					'880' .
					$phone;
			}
		}

		/**
		 * Filters normalized customer phone.
		 *
		 * Useful for country-specific integrations.
		 *
		 * @param string $phone     Normalized phone.
		 * @param string $raw_phone Original phone.
		 * @param string $country   Billing country.
		 */
		$phone =
			apply_filters(
				'eilmo_cf/coupons/normalized_phone',
				$phone,
				$raw_phone,
				$country
			);

		return is_string( $phone )
			? trim( $phone )
			: '';
	}

	/**
	 * Resolve customer email.
	 *
	 * Checkout-entered billing email takes priority.
	 * Logged-in customer information is the fallback.
	 *
	 * @param int                  $customer_id Customer ID.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return string
	 */
	private function resolve_email(
		int $customer_id,
		array $context
	): string {

		$email =
			$this->normalize_email(
				$context['customer_email'] ??
					''
			);

		if ( '' !== $email ) {
			return $email;
		}

		if ( $customer_id <= 0 ) {
			return '';
		}

		/*
		 * Prefer WooCommerce billing email.
		 */
		$billing_email =
			$this->normalize_email(
				get_user_meta(
					$customer_id,
					'billing_email',
					true
				)
			);

		if ( '' !== $billing_email ) {
			return $billing_email;
		}

		$user =
			get_userdata(
				$customer_id
			);

		if ( ! $user ) {
			return '';
		}

		return $this->normalize_email(
			$user->user_email
		);
	}

	/**
	 * Resolve customer phone.
	 *
	 * Checkout-entered billing phone takes priority.
	 * Logged-in WooCommerce billing phone is fallback.
	 *
	 * @param int                  $customer_id Customer ID.
	 * @param array<string, mixed> $context     Context.
	 * @param string               $country     Country.
	 *
	 * @return string
	 */
	private function resolve_phone(
		int $customer_id,
		array $context,
		string $country
	): string {

		$phone =
			$this->normalize_phone(
				$context['customer_phone'] ??
					'',
				$country
			);

		if ( '' !== $phone ) {
			return $phone;
		}

		if ( $customer_id <= 0 ) {
			return '';
		}

		return $this->normalize_phone(
			get_user_meta(
				$customer_id,
				'billing_phone',
				true
			),
			$country
		);
	}

	/**
	 * Resolve billing country.
	 *
	 * @param int                  $customer_id Customer ID.
	 * @param array<string, mixed> $context     Context.
	 *
	 * @return string
	 */
	private function resolve_country(
		int $customer_id,
		array $context
	): string {

		$country =
			strtoupper(
				sanitize_key(
					(string) (
						$context['billing_country'] ??
							''
					)
				)
			);

		if ( '' !== $country ) {
			return $country;
		}

		if ( $customer_id > 0 ) {

			$country =
				strtoupper(
					sanitize_key(
						(string) get_user_meta(
							$customer_id,
							'billing_country',
							true
						)
					)
				);

			if ( '' !== $country ) {
				return $country;
			}
		}

		/*
		 * Store country is used only as a phone
		 * normalization fallback.
		 */
		if (
			function_exists(
				'WC'
			) &&
			WC() &&
			WC()->countries
		) {
			$base_country =
				WC()->countries
					->get_base_country();

			if (
				is_string(
					$base_country
				)
			) {
				return strtoupper(
					sanitize_key(
						$base_country
					)
				);
			}
		}

		return '';
	}
}