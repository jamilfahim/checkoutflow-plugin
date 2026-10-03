<?php
/**
 * Order idempotency service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Services;

use EilmoCheckout\Admin\SecuritySettings;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents duplicate Eilmo orders from repeated
 * checkout submissions.
 *
 * Flow:
 *
 * 1. Frontend generates one checkout token.
 * 2. Server claims a short-lived lock for the token.
 * 3. First request creates the order.
 * 4. Token is permanently attached to that order.
 * 5. Lock is released.
 * 6. Future requests using the same token reuse the
 *    existing order instead of creating another one.
 */
final class OrderIdempotency {

	/**
	 * Order meta key containing the checkout token.
	 */
	public const META_TOKEN =
		'_eilmo_cf_checkout_token';

	/**
	 * Lock option prefix.
	 */
	private const LOCK_PREFIX =
		'eilmo_cf_order_lock_';

	/**
	 * Default processing-lock lifetime.
	 *
	 * A stale lock may remain after a PHP fatal,
	 * request timeout or browser/network interruption.
	 *
	 * @var int
	 */
	private const DEFAULT_LOCK_TTL =
		300;

	/**
	 * Minimum accepted token length.
	 *
	 * @var int
	 */
	private const MIN_TOKEN_LENGTH =
		20;

	/**
	 * Maximum accepted token length.
	 *
	 * @var int
	 */
	private const MAX_TOKEN_LENGTH =
		100;

	/**
	 * Claim checkout token.
	 *
	 * Possible successful results:
	 *
	 * New request:
	 *
	 * [
	 *     'state'      => 'claimed',
	 *     'token'      => '...',
	 *     'lock_owner' => '...',
	 *     'order'      => null,
	 * ]
	 *
	 * Existing order:
	 *
	 * [
	 *     'state'      => 'existing',
	 *     'token'      => '...',
	 *     'lock_owner' => '',
	 *     'order'      => WC_Order,
	 * ]
	 *
	 * @param string $token Checkout token.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function claim(
		string $token
	) {

		$token =
			$this->normalize_token(
				$token
			);

		if (
			'' ===
				$token
		) {
			return new WP_Error(
				'invalid_checkout_token',
				SecuritySettings::get_message(
					'checkout_token_invalid',
					__(
						'The checkout request token is invalid. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		/*
		 * --------------------------------------------------
		 * Existing completed/created order
		 * --------------------------------------------------
		 *
		 * Always check this before creating a lock.
		 *
		 * If the original request already created an
		 * order, a retry should return that order rather
		 * than create a duplicate.
		 */
		$existing_order =
			$this->find_order(
				$token
			);

		if (
			$existing_order instanceof
				WC_Order
		) {
			return array(
				'state' =>
					'existing',

				'token' =>
					$token,

				'lock_owner' =>
					'',

				'order' =>
					$existing_order,
			);
		}

		$lock_name =
			$this->get_lock_name(
				$token
			);

		$lock_owner =
			wp_generate_uuid4();

		$lock_data =
			array(
				'owner' =>
					$lock_owner,

				'created_at' =>
					time(),
			);

		/*
		 * --------------------------------------------------
		 * First lock attempt
		 * --------------------------------------------------
		 */
		if (
			add_option(
				$lock_name,
				$lock_data,
				'',
				false
			)
		) {
			/**
			 * Fires after a checkout token lock has
			 * successfully been acquired.
			 *
			 * @param string $token      Checkout token.
			 * @param string $lock_owner Lock owner.
			 */
			do_action(
				'eilmo_cf/orders/idempotency_claimed',
				$token,
				$lock_owner
			);

			return array(
				'state' =>
					'claimed',

				'token' =>
					$token,

				'lock_owner' =>
					$lock_owner,

				'order' =>
					null,
			);
		}

		/*
		 * --------------------------------------------------
		 * Another request owns the token
		 * --------------------------------------------------
		 *
		 * Before reporting "processing", check whether
		 * that request already managed to create the
		 * order between our first lookup and lock attempt.
		 */
		$existing_order =
			$this->find_order(
				$token
			);

		if (
			$existing_order instanceof
				WC_Order
		) {
			return array(
				'state' =>
					'existing',

				'token' =>
					$token,

				'lock_owner' =>
					'',

				'order' =>
					$existing_order,
			);
		}

		/*
		 * --------------------------------------------------
		 * Recover stale lock
		 * --------------------------------------------------
		 */
		if (
			$this->is_lock_stale(
				$lock_name
			)
		) {
			delete_option(
				$lock_name
			);

			/*
			 * Another request may race us after the stale
			 * lock was removed.
			 *
			 * add_option() decides which request obtains
			 * the replacement lock.
			 */
			if (
				add_option(
					$lock_name,
					$lock_data,
					'',
					false
				)
			) {
				/**
				 * Fires after a stale checkout lock has
				 * been replaced.
				 *
				 * @param string $token      Checkout token.
				 * @param string $lock_owner Lock owner.
				 */
				do_action(
					'eilmo_cf/orders/idempotency_reclaimed',
					$token,
					$lock_owner
				);

				return array(
					'state' =>
						'claimed',

					'token' =>
						$token,

					'lock_owner' =>
						$lock_owner,

					'order' =>
						null,
				);
			}
		}

		/*
		 * One final order lookup.
		 *
		 * The competing request could have finished while
		 * the stale/active-lock checks were running.
		 */
		$existing_order =
			$this->find_order(
				$token
			);

		if (
			$existing_order instanceof
				WC_Order
		) {
			return array(
				'state' =>
					'existing',

				'token' =>
					$token,

				'lock_owner' =>
					'',

				'order' =>
					$existing_order,
			);
		}

		return new WP_Error(
			'order_already_processing',
			SecuritySettings::get_message(
				'duplicate_order',
				__(
					'A similar order was recently submitted or is still being processed. Please wait before placing the same order again.',
					'eilmo-checkout-flow'
				)
			)
		);
	}

	/**
	 * Attach idempotency token to an order.
	 *
	 * This must happen before the processing lock is
	 * released.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $token Checkout token.
	 *
	 * @return true|WP_Error
	 */
	public function attach_to_order(
		WC_Order $order,
		string $token
	) {

		$token =
			$this->normalize_token(
				$token
			);

		if (
			'' ===
				$token
		) {
			return new WP_Error(
				'invalid_checkout_token',
				SecuritySettings::get_message(
					'checkout_token_invalid',
					__(
						'The checkout request token is invalid. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		if (
			$order->get_id() <= 0
		) {
			return new WP_Error(
				'invalid_order',
				SecuritySettings::get_message(
					'checkout_request_failed',
					__(
						'The checkout request could not be finalized. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		$current_token =
			$this->normalize_token(
				(string) $order
					->get_meta(
						self::META_TOKEN,
						true
					)
			);

		/*
		 * Existing matching token is already correct.
		 */
		if (
			'' !==
				$current_token &&
			hash_equals(
				$current_token,
				$token
			)
		) {
			return true;
		}

		/*
		 * An order must never silently change from one
		 * checkout token to another.
		 */
		if (
			'' !==
				$current_token
		) {
			return new WP_Error(
				'checkout_token_conflict',
				SecuritySettings::get_message(
					'checkout_request_failed',
					__(
						'The checkout request could not be finalized. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		$order->update_meta_data(
			self::META_TOKEN,
			$token
		);

		try {

			$order->save();

		} catch ( \Throwable $throwable ) {

			return new WP_Error(
				'checkout_token_save_failed',
				SecuritySettings::get_message(
					'checkout_request_failed',
					__(
						'The checkout request could not be finalized. Please refresh the page and try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		return true;
	}

	/**
	 * Release processing lock.
	 *
	 * The owner ID prevents an old/slow request from
	 * accidentally deleting a newer request's lock.
	 *
	 * @param string $token      Checkout token.
	 * @param string $lock_owner Lock owner.
	 *
	 * @return void
	 */
	public function release(
		string $token,
		string $lock_owner
	): void {

		$token =
			$this->normalize_token(
				$token
			);

		$lock_owner =
			sanitize_text_field(
				$lock_owner
			);

		if (
			'' ===
				$token ||
			'' ===
				$lock_owner
		) {
			return;
		}

		$lock_name =
			$this->get_lock_name(
				$token
			);

		$lock =
			get_option(
				$lock_name,
				null
			);

		if (
			! is_array(
				$lock
			)
		) {
			return;
		}

		$current_owner =
			sanitize_text_field(
				(string) (
					$lock[
						'owner'
					] ??
						''
				)
			);

		if (
			'' ===
				$current_owner ||
			! hash_equals(
				$current_owner,
				$lock_owner
			)
		) {
			return;
		}

		delete_option(
			$lock_name
		);

		/**
		 * Fires after the checkout processing lock
		 * has been released.
		 *
		 * @param string $token      Checkout token.
		 * @param string $lock_owner Lock owner.
		 */
		do_action(
			'eilmo_cf/orders/idempotency_released',
			$token,
			$lock_owner
		);
	}

	/**
	 * Find an order previously created with token.
	 *
	 * Uses wc_get_orders() so order retrieval remains
	 * compatible with WooCommerce order storage.
	 *
	 * @param string $token Checkout token.
	 *
	 * @return WC_Order|null
	 */
	public function find_order(
		string $token
	): ?WC_Order {

		$token =
			$this->normalize_token(
				$token
			);

		if (
			'' ===
				$token ||
			! function_exists(
				'wc_get_orders'
			)
		) {
			return null;
		}

		try {

			$orders =
				wc_get_orders(
					array(
						'limit' =>
							1,

						'orderby' =>
							'date',

						'order' =>
							'DESC',

						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Idempotency recovery must locate the order by its plugin-owned token metadata.
						'meta_query' =>
							array(
								array(
									'key' =>
										self::META_TOKEN,

									'value' =>
										$token,

									'compare' =>
										'=',
								),
							),
					)
				);

		} catch ( \Throwable $throwable ) {

			return null;
		}

		if (
			! is_array(
				$orders
			) ||
			empty(
				$orders
			)
		) {
			return null;
		}

		$order =
			reset(
				$orders
			);

		return $order instanceof
			WC_Order
				? $order
				: null;
	}

	/**
	 * Normalize checkout token.
	 *
	 * Accepted characters intentionally support
	 * UUIDs as well as future URL-safe random tokens.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	public function normalize_token(
		string $token
	): string {

		$token =
			trim(
				wp_unslash(
					$token
				)
			);

		$length =
			strlen(
				$token
			);

		if (
			$length <
				self::MIN_TOKEN_LENGTH ||
			$length >
				self::MAX_TOKEN_LENGTH
		) {
			return '';
		}

		if (
			1 !==
				preg_match(
					'/^[A-Za-z0-9_-]+$/',
					$token
				)
		) {
			return '';
		}

		return $token;
	}

	/**
	 * Determine whether a processing lock is stale.
	 *
	 * @param string $lock_name Option name.
	 *
	 * @return bool
	 */
	private function is_lock_stale(
		string $lock_name
	): bool {

		$lock =
			get_option(
				$lock_name,
				null
			);

		if (
			! is_array(
				$lock
			)
		) {
			/*
			 * Option exists but contains invalid data.
			 * Treat it as stale so checkout can recover.
			 */
			return true;
		}

		$created_at =
			absint(
				$lock[
					'created_at'
				] ??
					0
			);

		if (
			$created_at <= 0
		) {
			return true;
		}

		/**
		 * Filters checkout processing-lock lifetime.
		 *
		 * @param int $ttl Lock lifetime in seconds.
		 */
		$ttl =
			absint(
				apply_filters(
					'eilmo_cf/orders/idempotency_lock_ttl',
					self::DEFAULT_LOCK_TTL
				)
			);

		if (
			$ttl < 30
		) {
			$ttl =
				30;
		}

		return (
			(
				time() -
				$created_at
			) >
			$ttl
		);
	}

	/**
	 * Generate lock option name.
	 *
	 * Never place the raw frontend token directly
	 * into the WordPress option name.
	 *
	 * @param string $token Checkout token.
	 *
	 * @return string
	 */
	private function get_lock_name(
		string $token
	): string {

		return self::LOCK_PREFIX .
			hash(
				'sha256',
				$token
			);
	}
}
