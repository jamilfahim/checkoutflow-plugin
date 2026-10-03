<?php
/**
 * Combo Offer signed context.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ComboOffers\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and verifies signed per-checkout Combo Offer context.
 */
final class ComboOfferContext {

	/**
	 * Maximum Combo IDs allowed in one checkout instance.
	 */
	private const MAX_ALLOWED_IDS = 100;

	/**
	 * Create signed Combo Offer context.
	 *
	 * The signed payload protects:
	 * - Allowed Combo Offer IDs.
	 * - Checkout instance ID.
	 * - Combo selection mode.
	 *
	 * @param array<int|string, mixed> $allowed_ids    Allowed Combo IDs.
	 * @param string                   $instance_id    Checkout instance ID.
	 * @param string                   $selection_mode Selection mode.
	 *
	 * @return array<string, mixed>
	 */
	public function create(
		array $allowed_ids,
		string $instance_id = '',
		string $selection_mode = 'multiple'
	): array {

		$payload = array(
			'allowed_ids' =>
				$this->normalize_ids(
					$allowed_ids
				),

			'instance_id' =>
				$this->normalize_instance_id(
					$instance_id
				),

			'selection_mode' =>
				$this->normalize_selection_mode(
					$selection_mode
				),
		);

		$payload['signature'] =
			$this->sign(
				$payload
			);

		return $payload;
	}

	/**
	 * Verify signed Combo Offer context.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return bool
	 */
	public function verify(
		array $context
	): bool {

		$signature =
			strtolower(
				trim(
					(string) (
						$context['signature'] ??
							''
					)
				)
			);

		if (
			1 !==
				preg_match(
					'/^[a-f0-9]{64}$/',
					$signature
				)
		) {
			return false;
		}

		$payload = array(
			'allowed_ids' =>
				$this->normalize_ids(
					isset(
						$context['allowed_ids']
					) &&
					is_array(
						$context['allowed_ids']
					)
						? $context['allowed_ids']
						: array()
				),

			'instance_id' =>
				$this->normalize_instance_id(
					(string) (
						$context['instance_id'] ??
							''
					)
				),

			'selection_mode' =>
				$this->normalize_selection_mode(
					(string) (
						$context['selection_mode'] ??
							'multiple'
					)
				),
		);

		$expected =
			$this->sign(
				$payload
			);

		return hash_equals(
			$expected,
			$signature
		);
	}

	/**
	 * Determine whether Combo ID is allowed.
	 *
	 * @param string               $offer_id Combo Offer ID.
	 * @param array<string, mixed> $context  Signed context.
	 *
	 * @return bool
	 */
	public function is_allowed(
		string $offer_id,
		array $context
	): bool {

		if (
			! $this->verify(
				$context
			)
		) {
			return false;
		}

		$offer_id =
			sanitize_key(
				$offer_id
			);

		if ( '' === $offer_id ) {
			return false;
		}

		return in_array(
			$offer_id,
			$this->get_allowed_ids(
				$context
			),
			true
		);
	}

	/**
	 * Get normalized allowed Combo IDs.
	 *
	 * Call verify() before trusting frontend context.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return array<int, string>
	 */
	public function get_allowed_ids(
		array $context
	): array {

		$allowed_ids =
			isset(
				$context['allowed_ids']
			) &&
			is_array(
				$context['allowed_ids']
			)
				? $context['allowed_ids']
				: array();

		return $this->normalize_ids(
			$allowed_ids
		);
	}

	/**
	 * Get signed Combo selection mode.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	public function get_selection_mode(
		array $context
	): string {

		return $this->normalize_selection_mode(
			(string) (
				$context['selection_mode'] ??
					'multiple'
			)
		);
	}

	/**
	 * Get checkout instance ID.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	public function get_instance_id(
		array $context
	): string {

		return $this->normalize_instance_id(
			(string) (
				$context['instance_id'] ??
					''
			)
		);
	}

	/**
	 * Sign normalized context payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 *
	 * @return string
	 */
	private function sign(
		array $payload
	): string {

		$normalized = array(
			'allowed_ids' =>
				$this->normalize_ids(
					isset(
						$payload['allowed_ids']
					) &&
					is_array(
						$payload['allowed_ids']
					)
						? $payload['allowed_ids']
						: array()
				),

			'instance_id' =>
				$this->normalize_instance_id(
					(string) (
						$payload['instance_id'] ??
							''
					)
				),

			'selection_mode' =>
				$this->normalize_selection_mode(
					(string) (
						$payload['selection_mode'] ??
							'multiple'
					)
				),
		);

		$json =
			wp_json_encode(
				$normalized
			);

		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		return hash_hmac(
			'sha256',
			$json,
			wp_salt(
				'auth'
			)
		);
	}

	/**
	 * Normalize Combo Offer IDs.
	 *
	 * @param array<int|string, mixed> $offer_ids Offer IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_ids(
		array $offer_ids
	): array {

		$normalized = array();

		foreach ( $offer_ids as $offer_id ) {

			if (
				! is_scalar(
					$offer_id
				)
			) {
				continue;
			}

			$offer_id =
				sanitize_key(
					(string) $offer_id
				);

			if (
				'' === $offer_id ||
				in_array(
					$offer_id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$offer_id;

			if (
				count( $normalized ) >=
					self::MAX_ALLOWED_IDS
			) {
				break;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize instance ID.
	 *
	 * @param string $instance_id Instance ID.
	 *
	 * @return string
	 */
	private function normalize_instance_id(
		string $instance_id
	): string {

		return sanitize_key(
			$instance_id
		);
	}

	/**
	 * Normalize Combo selection mode.
	 *
	 * @param string $selection_mode Selection mode.
	 *
	 * @return string
	 */
	private function normalize_selection_mode(
		string $selection_mode
	): string {

		$selection_mode =
			sanitize_key(
				$selection_mode
			);

		return in_array(
			$selection_mode,
			array(
				'single',
				'multiple',
			),
			true
		)
			? $selection_mode
			: 'multiple';
	}
}