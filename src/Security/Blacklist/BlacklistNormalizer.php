<?php
/**
 * Blacklist value normalizer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\Blacklist;

use EilmoCheckout\Admin\SecuritySettings;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes Customer Blacklist values.
 *
 * Important:
 *
 * normalize() remains the general-purpose canonical
 * security normalizer used by other protections such as
 * Duplicate Order and Order Cooldown.
 *
 * normalize_for_blacklist() is the Blacklist-specific
 * entry point and respects the Security setting:
 *
 * protection.blacklist.normalize_phone
 *
 * Keeping these two paths separate prevents a Blacklist
 * preference from accidentally changing Duplicate Order
 * or Order Cooldown identity behavior.
 */
final class BlacklistNormalizer {

	/**
	 * Supported blacklist types.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_TYPES = array(
		'ip',
		'phone',
		'email',
	);

	/**
	 * Normalize blacklist type.
	 *
	 * @param mixed $type Type.
	 *
	 * @return string
	 */
	public static function normalize_type(
		$type
	): string {

		$type =
			sanitize_key(
				(string) $type
			);

		return in_array(
			$type,
			self::ALLOWED_TYPES,
			true
		)
			? $type
			: '';
	}

	/**
	 * Normalize value using the canonical security rules.
	 *
	 * This method deliberately does NOT read the
	 * Customer Blacklist normalize-phone preference.
	 *
	 * Other protections depend on stable phone
	 * normalization even if an administrator chooses
	 * exact-format phone matching for Blacklist entries.
	 *
	 * @param string $type  Identifier type.
	 * @param mixed  $value Raw value.
	 *
	 * @return string
	 */
	public static function normalize(
		string $type,
		$value
	): string {

		$type =
			self::normalize_type(
				$type
			);

		if ( '' === $type ) {
			return '';
		}

		switch ( $type ) {

			case 'ip':
				return self::normalize_ip(
					$value
				);

			case 'phone':
				return self::normalize_phone(
					$value
				);

			case 'email':
				return self::normalize_email(
					$value
				);

			default:
				return '';
		}
	}

	/**
	 * Normalize a value specifically for Customer
	 * Blacklist storage and matching.
	 *
	 * Phone behavior:
	 *
	 * normalize_phone = yes
	 * - formatting is removed.
	 * - +880 1712-345678 and +8801712345678 match.
	 * - leading international "00" is normalized.
	 *
	 * normalize_phone = no
	 * - sanitized trimmed phone text is preserved.
	 * - comparison becomes exact-format, apart from
	 *   normal text sanitization/whitespace trimming.
	 *
	 * IP and Email always use canonical normalization.
	 *
	 * @param string $type  Blacklist type.
	 * @param mixed  $value Raw value.
	 *
	 * @return string
	 */
	public static function normalize_for_blacklist(
		string $type,
		$value
	): string {

		$type =
			self::normalize_type(
				$type
			);

		if ( '' === $type ) {
			return '';
		}

		if (
			'phone' === $type &&
			! self::is_blacklist_phone_normalization_enabled()
		) {
			return self::sanitize_phone_exact(
				$value
			);
		}

		return self::normalize(
			$type,
			$value
		);
	}

	/**
	 * Prepare original value for storage/display.
	 *
	 * Display values intentionally retain the
	 * administrator-entered phone formatting. Matching
	 * uses normalize_for_blacklist() separately.
	 *
	 * @param string $type  Blacklist type.
	 * @param mixed  $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_display_value(
		string $type,
		$value
	): string {

		$type =
			self::normalize_type(
				$type
			);

		if ( '' === $type ) {
			return '';
		}

		$value =
			trim(
				(string) $value
			);

		switch ( $type ) {

			case 'email':
				return sanitize_email(
					$value
				);

			case 'ip':
			case 'phone':
			default:
				return sanitize_text_field(
					$value
				);
		}
	}

	/**
	 * Whether Blacklist phone normalization is enabled.
	 *
	 * Defaults to yes so fresh installs and incomplete
	 * legacy options keep the safer normalized matching
	 * behavior.
	 *
	 * @return bool
	 */
	public static function is_blacklist_phone_normalization_enabled(): bool {

		$settings =
			SecuritySettings::get_settings();

		$blacklist =
			isset(
				$settings[
					'protection'
				][
					'blacklist'
				]
			) &&
			is_array(
				$settings[
					'protection'
				][
					'blacklist'
				]
			)
				? $settings[
					'protection'
				][
					'blacklist'
				]
				: array();

		return (
			'yes' ===
				(
					$blacklist[
						'normalize_phone'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Normalize IP address.
	 *
	 * Supports IPv4 and IPv6.
	 *
	 * @param mixed $value IP address.
	 *
	 * @return string
	 */
	private static function normalize_ip(
		$value
	): string {

		$value =
			trim(
				(string) $value
			);

		if (
			false ===
			filter_var(
				$value,
				FILTER_VALIDATE_IP
			)
		) {
			return '';
		}

		/*
		 * Convert valid IP addresses to their canonical
		 * representation where possible.
		 *
		 * This is particularly useful for IPv6 because
		 * the same address can be written in several
		 * equivalent forms.
		 */
		$packed =
			@inet_pton(
				$value
			);

		if ( false === $packed ) {
			return strtolower(
				$value
			);
		}

		$normalized =
			@inet_ntop(
				$packed
			);

		if (
			false === $normalized ||
			'' === $normalized
		) {
			return strtolower(
				$value
			);
		}

		return strtolower(
			$normalized
		);
	}

	/**
	 * Normalize phone number canonically.
	 *
	 * Country rules are intentionally not hardcoded.
	 *
	 * Formatting characters are removed so values such as:
	 *
	 * +880 1712-345678
	 * +8801712345678
	 *
	 * resolve to the same normalized value.
	 *
	 * A leading international 00 prefix is also removed.
	 *
	 * @param mixed $value Phone number.
	 *
	 * @return string
	 */
	private static function normalize_phone(
		$value
	): string {

		$value =
			trim(
				(string) $value
			);

		if ( '' === $value ) {
			return '';
		}

		$normalized =
			preg_replace(
				'/\D+/',
				'',
				$value
			);

		if (
			! is_string(
				$normalized
			) ||
			'' === $normalized
		) {
			return '';
		}

		/*
		 * Convert international prefix:
		 *
		 * 008801712345678
		 * becomes:
		 * 8801712345678
		 */
		if (
			0 === strpos(
				$normalized,
				'00'
			)
		) {
			$normalized =
				substr(
					$normalized,
					2
				);
		}

		/*
		 * Defensive sanity check.
		 *
		 * Do not attempt country-specific validation here.
		 */
		if (
			strlen(
				$normalized
			) < 5
		) {
			return '';
		}

		return $normalized;
	}

	/**
	 * Sanitize phone while preserving entered formatting.
	 *
	 * Used only when the administrator explicitly turns
	 * Blacklist phone normalization off.
	 *
	 * @param mixed $value Phone number.
	 *
	 * @return string
	 */
	private static function sanitize_phone_exact(
		$value
	): string {

		$value =
			sanitize_text_field(
				trim(
					(string) $value
				)
			);

		if ( '' === $value ) {
			return '';
		}

		/*
		 * Still require a minimally useful phone-like
		 * value. This prevents tiny/empty strings from
		 * becoming broad or accidental blacklist keys.
		 */
		$digits =
			preg_replace(
				'/\D+/',
				'',
				$value
			);

		if (
			! is_string(
				$digits
			) ||
			strlen(
				$digits
			) < 5
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Normalize email address.
	 *
	 * @param mixed $value Email.
	 *
	 * @return string
	 */
	private static function normalize_email(
		$value
	): string {

		$value =
			sanitize_email(
				trim(
					(string) $value
				)
			);

		if (
			'' === $value ||
			! is_email(
				$value
			)
		) {
			return '';
		}

		return strtolower(
			$value
		);
	}
}
