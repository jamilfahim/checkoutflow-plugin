<?php
/**
 * Bot Protection token.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\BotProtection;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and verifies signed checkout-start tokens.
 */
final class BotProtectionToken {

	/**
	 * Token version.
	 *
	 * @var int
	 */
	private const VERSION = 1;

	/**
	 * Maximum token length.
	 *
	 * @var int
	 */
	private const MAX_TOKEN_LENGTH = 1024;

	/**
	 * Default token lifetime.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_AGE =
		DAY_IN_SECONDS;

	/**
	 * Allowed clock difference.
	 *
	 * @var int
	 */
	private const FUTURE_TOLERANCE = 60;

	/**
	 * Create signed checkout-start token.
	 *
	 * @return string
	 */
	public function create(): string {

		$payload =
			array(
				'version' =>
					self::VERSION,

				'issued_at' =>
					time(),

				'nonce' =>
					wp_generate_uuid4(),
			);

		$json =
			wp_json_encode(
				$payload
			);

		if (
			! is_string(
				$json
			) ||
			'' === $json
		) {
			return '';
		}

		$encoded =
			$this->base64_url_encode(
				$json
			);

		if ( '' === $encoded ) {
			return '';
		}

		$signature =
			$this->sign(
				$encoded
			);

		if ( '' === $signature ) {
			return '';
		}

		return $encoded .
			'.' .
			$signature;
	}

	/**
	 * Verify signed checkout-start token.
	 *
	 * @param mixed $token Token.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function verify(
		$token
	) {

		if (
			! is_scalar(
				$token
			)
		) {
			return $this->invalid_token_error();
		}

		$token =
			trim(
				(string) $token
			);

		if (
			'' === $token ||
			strlen(
				$token
			) >
				self::MAX_TOKEN_LENGTH
		) {
			return $this->invalid_token_error();
		}

		$parts =
			explode(
				'.',
				$token
			);

		if (
			2 !== count(
				$parts
			)
		) {
			return $this->invalid_token_error();
		}

		$encoded =
			(string) $parts[0];

		$signature =
			strtolower(
				(string) $parts[1]
			);

		if (
			'' === $encoded ||
			64 !== strlen(
				$signature
			) ||
			1 !== preg_match(
				'/^[a-f0-9]{64}$/',
				$signature
			)
		) {
			return $this->invalid_token_error();
		}

		$expected_signature =
			$this->sign(
				$encoded
			);

		if (
			'' === $expected_signature ||
			! hash_equals(
				$expected_signature,
				$signature
			)
		) {
			return $this->invalid_token_error();
		}

		$json =
			$this->base64_url_decode(
				$encoded
			);

		if ( '' === $json ) {
			return $this->invalid_token_error();
		}

		$payload =
			json_decode(
				$json,
				true
			);

		if (
			JSON_ERROR_NONE !==
				json_last_error() ||
			! is_array(
				$payload
			)
		) {
			return $this->invalid_token_error();
		}

		$version =
			absint(
				$payload[
					'version'
				] ??
					0
			);

		$issued_at =
			absint(
				$payload[
					'issued_at'
				] ??
					0
			);

		$nonce =
			sanitize_text_field(
				(string) (
					$payload[
						'nonce'
					] ??
						''
				)
			);

		if (
			self::VERSION !==
				$version ||
			$issued_at <= 0 ||
			'' === $nonce
		) {
			return $this->invalid_token_error();
		}

		$now =
			time();

		/*
		 * A token must not claim to have been generated
		 * significantly in the future.
		 */
		if (
			$issued_at >
			(
				$now +
				self::FUTURE_TOLERANCE
			)
		) {
			return $this->invalid_token_error();
		}

		$max_age =
			$this->get_max_age();

		if (
			(
				$now -
				$issued_at
			) >
			$max_age
		) {
			return new WP_Error(
				'bot_protection_token_expired',
				__(
					'The checkout session has expired. Please refresh the page and try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'version' =>
				$version,

			'issued_at' =>
				$issued_at,

			'nonce' =>
				$nonce,
		);
	}

	/**
	 * Sign encoded token payload.
	 *
	 * @param string $encoded Encoded payload.
	 *
	 * @return string
	 */
	private function sign(
		string $encoded
	): string {

		if ( '' === $encoded ) {
			return '';
		}

		return hash_hmac(
			'sha256',
			$encoded,
			wp_salt(
				'nonce'
			)
		);
	}

	/**
	 * Base64 URL encode.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function base64_url_encode(
		string $value
	): string {

		if ( '' === $value ) {
			return '';
		}

		return rtrim(
			strtr(
				base64_encode(
					$value
				),
				'+/',
				'-_'
			),
			'='
		);
	}

	/**
	 * Base64 URL decode.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function base64_url_decode(
		string $value
	): string {

		if ( '' === $value ) {
			return '';
		}

		$value =
			strtr(
				$value,
				'-_',
				'+/'
			);

		$remainder =
			strlen(
				$value
			) %
				4;

		if ( $remainder > 0 ) {
			$value .=
				str_repeat(
					'=',
					4 -
						$remainder
				);
		}

		$decoded =
			base64_decode(
				$value,
				true
			);

		return false !== $decoded
			? $decoded
			: '';
	}

	/**
	 * Get token maximum age.
	 *
	 * @return int
	 */
	private function get_max_age(): int {

		$max_age =
			apply_filters(
				'eilmo_cf/security/bot_token_max_age',
				self::DEFAULT_MAX_AGE
			);

		if (
			! is_numeric(
				$max_age
			)
		) {
			return self::DEFAULT_MAX_AGE;
		}

		return max(
			MINUTE_IN_SECONDS,
			(int) $max_age
		);
	}

	/**
	 * Invalid token error.
	 *
	 * @return WP_Error
	 */
	private function invalid_token_error(): WP_Error {

		return new WP_Error(
			'invalid_bot_protection_token',
			__(
				'The checkout security verification failed. Please refresh the page and try again.',
				'eilmo-checkout-flow'
			)
		);
	}
}