<?php
/**
 * Client IP resolver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the current client IP address.
 */
final class ClientIpResolver {

	/**
	 * Get current client IP.
	 *
	 * REMOTE_ADDR is used by default because forwarded
	 * headers must not be trusted unless a proxy is known
	 * and explicitly trusted.
	 *
	 * @return string
	 */
	public function get(): string {

		if (
			! isset(
				$_SERVER['REMOTE_ADDR']
			)
		) {
			return '';
		}

		$ip =
			sanitize_text_field(
				wp_unslash(
					$_SERVER['REMOTE_ADDR']
				)
			);

		if (
			'' === $ip ||
			false === filter_var(
				$ip,
				FILTER_VALIDATE_IP
			)
		) {
			return '';
		}

		$packed =
			@inet_pton(
				$ip
			);

		if ( false === $packed ) {
			return strtolower(
				$ip
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
				$ip
			);
		}

		return strtolower(
			$normalized
		);
	}
}