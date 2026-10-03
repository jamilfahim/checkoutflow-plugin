<?php
/**
 * Duplicate Order guard.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\DuplicateOrder;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Core\Installer;
use EilmoCheckout\Security\Blacklist\BlacklistNormalizer;
use EilmoCheckout\Security\ClientIpResolver;
use EilmoCheckout\Security\Logging\SecurityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents repeated identical checkout orders using configurable comparison rules.
 */
final class DuplicateOrderGuard {

	/**
	 * Claim a validated checkout fingerprint.
	 *
	 * An empty string means Duplicate Order Protection
	 * is disabled or no reliable customer identity
	 * could be generated.
	 *
	 * @param array<string,mixed> $validated Validated checkout.
	 * @param string              $source    Checkout source.
	 *
	 * @return string|WP_Error
	 */
	public function claim(
		array $validated,
		string $source = SecurityLogger::SOURCE_EILMO
	) {

		if (
			! $this->is_enabled() ||
			$this->should_bypass_current_user()
		) {
			return '';
		}

		$fingerprint =
			$this->build_fingerprint(
				$validated
			);

		if ( '' === $fingerprint ) {
			return '';
		}

		$window_minutes =
			$this->get_window_minutes();

		$now =
			time();

		$now_mysql =
			gmdate(
				'Y-m-d H:i:s',
				$now
			);

		$expires_at =
			gmdate(
				'Y-m-d H:i:s',
				$now +
				(
					$window_minutes *
					MINUTE_IN_SECONDS
				)
			);

		/*
		 * ---------------------------------------------
		 * First atomic claim
		 * ---------------------------------------------
		 *
		 * fingerprint_hash is the primary key.
		 *
		 * INSERT IGNORE therefore allows only one
		 * concurrent checkout to claim the fingerprint.
		 */
		if (
			$this->insert_claim(
				$fingerprint,
				$expires_at,
				$now_mysql
			)
		) {
			return $fingerprint;
		}

		/*
		 * A fingerprint row already exists.
		 */
		$existing =
			$this->get_record(
				$fingerprint
			);

		if ( null === $existing ) {

			return new WP_Error(
				'duplicate_order_check_failed',
				SecuritySettings::get_message(
					'checkout_request_failed',
					__(
						'The checkout request could not be verified. Please try again.',
						'eilmo-checkout-flow'
					)
				)
			);
		}

		$existing_expiry =
			$this->parse_utc_time(
				(string) (
					$existing[
						'expires_at'
					] ??
						''
				)
			);

		/*
		 * ---------------------------------------------
		 * Active Duplicate Order
		 * ---------------------------------------------
		 */
		if (
			$existing_expiry >
			$now
		) {

			$error =
				$this->get_duplicate_error();

			$this->log_duplicate_block(
				$fingerprint,
				$existing,
				$source,
				$error
			);

			/**
			 * Fires when a checkout is blocked by
			 * Duplicate Order Protection.
			 *
			 * Existing listeners using the original
			 * first two arguments remain compatible.
			 *
			 * @param string              $fingerprint Fingerprint.
			 * @param array<string,mixed> $existing    Existing record.
			 * @param string              $source      Checkout source.
			 */
			do_action(
				'eilmo_cf/security/duplicate_order_blocked',
				$fingerprint,
				$existing,
				$source
			);

			return $error;
		}

		/*
		 * ---------------------------------------------
		 * Expired previous claim
		 * ---------------------------------------------
		 *
		 * Attempt atomic renewal.
		 *
		 * The WHERE expires_at <= current time ensures
		 * only one concurrent request can renew it.
		 */
		$renewed =
			$this->renew_expired_claim(
				$fingerprint,
				$expires_at,
				$now_mysql
			);

		if ( $renewed ) {
			return $fingerprint;
		}

		/*
		 * Another concurrent request renewed the same
		 * fingerprint before this request.
		 *
		 * Fetch the latest record so Activity Logs can
		 * retain the currently attached order ID when
		 * one already exists.
		 */
		$latest =
			$this->get_record(
				$fingerprint
			);

		$error =
			$this->get_duplicate_error();

		$this->log_duplicate_block(
			$fingerprint,
			is_array( $latest )
				? $latest
				: array(),
			$source,
			$error
		);

		do_action(
			'eilmo_cf/security/duplicate_order_blocked',
			$fingerprint,
			is_array( $latest )
				? $latest
				: array(),
			$source
		);

		return $error;
	}

	/**
	 * Attach the created WooCommerce order to a claim.
	 *
	 * @param string $fingerprint Fingerprint.
	 * @param int    $order_id    Order ID.
	 *
	 * @return bool
	 */
	public function attach_order(
		string $fingerprint,
		int $order_id
	): bool {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		$order_id =
			absint(
				$order_id
			);

		if (
			'' === $fingerprint ||
			$order_id <= 0
		) {
			return false;
		}

		$updated =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->update(
				Installer::get_duplicate_order_table(),
				array(
					'order_id' =>
						$order_id,

					'updated_at' =>
						gmdate(
							'Y-m-d H:i:s'
						),
				),
				array(
					'fingerprint_hash' =>
						$fingerprint,
				),
				array(
					'%d',
					'%s',
				),
				array(
					'%s',
				)
			);

		return false !==
			$updated;
	}

	/**
	 * Release an uncommitted Duplicate Order claim.
	 *
	 * Important:
	 *
	 * This method is used by normal checkout failure
	 * cleanup.
	 *
	 * It intentionally removes ONLY order_id = 0.
	 *
	 * A persisted WooCommerce order must continue
	 * protecting its fingerprint until expiry unless an
	 * administrator explicitly releases it.
	 *
	 * @param string $fingerprint Fingerprint.
	 *
	 * @return void
	 */
	public function release(
		string $fingerprint
	): void {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
		$wpdb->delete(
			Installer::get_duplicate_order_table(),
			array(
				'fingerprint_hash' =>
					$fingerprint,

				'order_id' =>
					0,
			),
			array(
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Manually release Duplicate Order protection.
	 *
	 * Unlike release(), this method may remove a claim
	 * that is already attached to a persisted order.
	 *
	 * This is intended for an authenticated
	 * administrator Unblock / Release action.
	 *
	 * Deleting this protection record does NOT delete,
	 * cancel or modify the WooCommerce order itself.
	 *
	 * @param string $fingerprint Fingerprint.
	 *
	 * @return bool
	 */
	public function release_persistent(
		string $fingerprint
	): bool {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return false;
		}

		$deleted =
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom operational table access requires immediate, uncached read/write consistency.
			$wpdb->delete(
				Installer::get_duplicate_order_table(),
				array(
					'fingerprint_hash' =>
						$fingerprint,
				),
				array(
					'%s',
				)
			);

		if ( false === $deleted ) {
			return false;
		}

		/*
		 * Treat an already-removed / expired target as a
		 * successful idempotent release.
		 *
		 * This is useful if an administrator clicks a
		 * Release action after automatic cleanup or
		 * another request already released the record.
		 */
		do_action(
			'eilmo_cf/security/duplicate_order_released',
			$fingerprint
		);

		return true;
	}

	/**
	 * Log blocked Duplicate Order attempt.
	 *
	 * The fingerprint is already privacy-safe HMAC data.
	 *
	 * No raw phone/email/IP value is written to the
	 * Security Activity Log.
	 *
	 * @param string              $fingerprint Fingerprint.
	 * @param array<string,mixed> $record      Duplicate record.
	 * @param string              $source      Checkout source.
	 * @param WP_Error            $error       Error.
	 *
	 * @return void
	 */
	private function log_duplicate_block(
		string $fingerprint,
		array $record,
		string $source,
		WP_Error $error
	): void {

		if ( ! $this->should_log() ) {
			return;
		}

		$order_id =
			absint(
				$record[
					'order_id'
				] ??
					0
			);

		$logger =
			new SecurityLogger();

		$logger->duplicate_order(
			$fingerprint,
			$source,
			$error,
			$order_id,
			409
		);
	}


	/**
	 * Build authoritative checkout fingerprint.
	 *
	 * Raw customer information is never stored.
	 *
	 * The final fingerprint is HMAC hashed before
	 * database storage.
	 *
	 * @param array<string,mixed> $validated Validated checkout.
	 *
	 * @return string
	 */
	private function build_fingerprint(
		array $validated
	): string {

		$config =
			$this->get_config();

		$compare_customer =
			'yes' ===
				(
					$config[
						'compare_customer'
					] ??
						'yes'
				);

		$compare_products =
			'yes' ===
				(
					$config[
						'compare_products'
					] ??
						'yes'
				);

		$compare_quantities =
			'yes' ===
				(
					$config[
						'compare_quantities'
					] ??
						'yes'
				);

		$compare_total =
			'yes' ===
				(
					$config[
						'compare_total'
					] ??
						'yes'
				);

		$allow_different_products =
			'yes' ===
				(
					$config[
						'allow_different_products'
					] ??
						'yes'
				);

		$fingerprint_data =
			array();

		/*
		 * ---------------------------------------------
		 * Customer
		 * ---------------------------------------------
		 */
		if ( $compare_customer ) {

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

			$customer_id =
				absint(
					$customer[
						'customer_id'
					] ??
					$validated[
						'customer_id'
					] ??
					get_current_user_id()
				);

			$phone =
				BlacklistNormalizer::normalize(
					'phone',
					$customer[
						'billing_phone'
					] ??
						''
				);

			$email =
				BlacklistNormalizer::normalize(
					'email',
					$customer[
						'billing_email'
					] ??
						''
				);

			$ip = '';

			/*
			 * Prefer a logged-in customer ID, then
			 * validated phone/email. IP is only a
			 * fallback when no better identity exists.
			 */
			if (
				$customer_id <= 0 &&
				'' === $phone &&
				'' === $email
			) {
				$resolver =
					new ClientIpResolver();

				$ip =
					$resolver->get();
			}

			if (
				$customer_id <= 0 &&
				'' === $phone &&
				'' === $email &&
				'' === $ip
			) {
				return '';
			}

			$fingerprint_data[
				'customer'
			] =
				array(
					'customer_id' =>
						$customer_id,

					'phone' =>
						$phone,

					'email' =>
						$email,

					'ip' =>
						$ip,
				);
		}

		/*
		 * ---------------------------------------------
		 * Products / quantities
		 * ---------------------------------------------
		 *
		 * "Allow Different Products" means different
		 * product compositions must not collide even if
		 * Compare Products is manually disabled.
		 */
		$include_products =
			$compare_products ||
			$allow_different_products;

		if ( $include_products ) {

			$items =
				$this->collect_purchase_items(
					$validated,
					$compare_quantities
				);

			if ( empty( $items ) ) {
				return '';
			}

			$fingerprint_data[
				'items'
			] =
				$items;
		}

		/*
		 * ---------------------------------------------
		 * Delivery
		 * ---------------------------------------------
		 *
		 * Keep delivery in the protected composition so
		 * the same products sent using a different
		 * delivery method are not treated as identical.
		 */
		$delivery =
			isset(
				$validated[
					'delivery'
				]
			) &&
			is_array(
				$validated[
					'delivery'
				]
			)
				? $validated[
					'delivery'
				]
				: array();

		$delivery_method =
			sanitize_key(
				(string) (
					$delivery[
						'method_id'
					] ??
						''
				)
			);

		if ( '' !== $delivery_method ) {
			$fingerprint_data[
				'delivery'
			] =
				$delivery_method;
		}

		/*
		 * ---------------------------------------------
		 * Checkout value
		 * ---------------------------------------------
		 *
		 * Only an authoritative server-side amount is
		 * accepted. Native WooCommerce bridge supplies
		 * the order total directly. Eilmo integrations
		 * may supply totals through validated data or
		 * the filter below; browser totals are never
		 * trusted.
		 */
		if ( $compare_total ) {

			$total =
				$this->get_authoritative_total(
					$validated
				);

			/**
			 * Filters authoritative Duplicate Order
			 * comparison total.
			 *
			 * Returning null/empty means no trustworthy
			 * total is available and the fingerprint
			 * continues using the other enabled
			 * comparison dimensions.
			 *
			 * @param float|null          $total     Total.
			 * @param array<string,mixed> $validated Validated checkout.
			 */
			$total =
				apply_filters(
					'eilmo_cf/security/duplicate_order_total',
					$total,
					$validated
				);

			if (
				is_numeric(
					$total
				)
			) {
				$fingerprint_data[
					'total'
				] =
					$this->normalize_money(
						(float) $total
					);
			}

			/*
			 * Coupon code is already normalized by
			 * OrderValidator and is safe to include when
			 * value comparison is requested.
			 */
			$coupon_code =
				sanitize_text_field(
					(string) (
						$validated[
							'coupon_code'
						] ??
							''
					)
				);

			if ( '' !== $coupon_code ) {
				$fingerprint_data[
					'coupon_code'
				] =
					strtolower(
						$coupon_code
					);
			}
		}

		/*
		 * Never create a global/near-global fingerprint
		 * when all meaningful comparison dimensions are
		 * disabled.
		 */
		if ( empty( $fingerprint_data ) ) {
			return '';
		}

		$fingerprint_data =
			apply_filters(
				'eilmo_cf/security/duplicate_order_fingerprint_data',
				$fingerprint_data,
				$validated
			);

		if (
			! is_array(
				$fingerprint_data
			) ||
			empty(
				$fingerprint_data
			)
		) {
			return '';
		}

		$json =
			wp_json_encode(
				$fingerprint_data
			);

		if (
			! is_string(
				$json
			) ||
			'' === $json
		) {
			return '';
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
	 * Collect authoritative purchase items.
	 *
	 * Normal products, Combo components and Order Bumps
	 * are included separately so different checkout
	 * compositions produce different fingerprints.
	 *
	 * @param array<string,mixed> $validated        Validated checkout.
	 * @param bool                $include_quantity Whether quantity participates in the fingerprint.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_purchase_items(
		array $validated,
		bool $include_quantity
	): array {

		$items =
			array();

		$normal_items =
			isset(
				$validated[
					'items'
				]
			) &&
			is_array(
				$validated[
					'items'
				]
			)
				? $validated[
					'items'
				]
				: array();

		foreach (
			$normal_items as
				$item
		) {
			$this->append_item(
				$items,
				$item,
				'normal',
				$include_quantity
			);
		}

		$combo_data =
			isset(
				$validated[
					'combo_offers'
				]
			) &&
			is_array(
				$validated[
					'combo_offers'
				]
			)
				? $validated[
					'combo_offers'
				]
				: array();

		$combo_offers =
			isset(
				$combo_data[
					'offers'
				]
			) &&
			is_array(
				$combo_data[
					'offers'
				]
			)
				? $combo_data[
					'offers'
				]
				: array();

		foreach (
			$combo_offers as
				$offer
		) {

			if (
				! is_array(
					$offer
				)
			) {
				continue;
			}

			$combo_items =
				isset(
					$offer[
						'items'
					]
				) &&
				is_array(
					$offer[
						'items'
					]
				)
					? $offer[
						'items'
					]
					: array();

			foreach (
				$combo_items as
					$item
			) {
				$this->append_item(
					$items,
					$item,
					'combo',
					$include_quantity
				);
			}
		}

		$order_bump_data =
			isset(
				$validated[
					'order_bumps'
				]
			) &&
			is_array(
				$validated[
					'order_bumps'
				]
			)
				? $validated[
					'order_bumps'
				]
				: array();

		$order_bump_offers =
			isset(
				$order_bump_data[
					'offers'
				]
			) &&
			is_array(
				$order_bump_data[
					'offers'
				]
			)
				? $order_bump_data[
					'offers'
				]
				: array();

		foreach (
			$order_bump_offers as
				$offer
		) {
			$this->append_item(
				$items,
				$offer,
				'order_bump',
				$include_quantity
			);
		}

		usort(
			$items,
			static function (
				array $first,
				array $second
			): int {

				return strcmp(
					(string) (
						$first[
							'sort_key'
						] ??
							''
					),
					(string) (
						$second[
							'sort_key'
						] ??
							''
					)
				);
			}
		);

		foreach (
			$items as
				&$item
		) {
			unset(
				$item[
					'sort_key'
				]
			);
		}

		unset(
			$item
		);

		return array_values(
			$items
		);
	}

	/**
	 * Append purchase item.
	 *
	 * @param array<int,array<string,mixed>> $items   Items.
	 * @param mixed                          $item    Item.
	 * @param string                         $context Context.
	 *
	 * @return void
	 */
	private function append_item(
		array &$items,
		$item,
		string $context,
		bool $include_quantity
	): void {

		if (
			! is_array(
				$item
			)
		) {
			return;
		}

		$product_id =
			absint(
				$item[
					'product_id'
				] ??
					0
			);

		$variation_id =
			absint(
				$item[
					'variation_id'
				] ??
					0
			);

		$quantity =
			$this->normalize_quantity(
				$item[
					'quantity'
				] ??
					0
			);

		if (
			$product_id <= 0 ||
			$quantity <= 0
		) {
			return;
		}

		$context =
			sanitize_key(
				$context
			);

		$normalized_quantity =
			$include_quantity
				? $quantity
				: 1.0;

		$sort_key =
			sprintf(
				'%1$s:%2$d:%3$d:%4$s',
				$context,
				$product_id,
				$variation_id,
				(string) $normalized_quantity
			);

		$normalized_item =
			array(
				'context' =>
					$context,

				'product_id' =>
					$product_id,

				'variation_id' =>
					$variation_id,

				'sort_key' =>
					$sort_key,
			);

		if ( $include_quantity ) {
			$normalized_item[
				'quantity'
			] =
				$quantity;
		}

		$items[] =
			$normalized_item;
	}


	/**
	 * Normalize item quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
	}

	/**
	 * Insert new fingerprint claim.
	 *
	 * @param string $fingerprint Fingerprint.
	 * @param string $expires_at  Expiry.
	 * @param string $now         Current UTC time.
	 *
	 * @return bool
	 */
	private function insert_claim(
		string $fingerprint,
		string $expires_at,
		string $now
	): bool {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return false;
		}

		$table =
			Installer::get_duplicate_order_table();

		$query =
			$wpdb->prepare(
				"INSERT IGNORE INTO %i
					(
						fingerprint_hash,
						order_id,
						expires_at,
						created_at,
						updated_at
					)
				VALUES
					(
						%s,
						0,
						%s,
						%s,
						%s
					)",
				$table,
				$fingerprint,
				$expires_at,
				$now,
				$now
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query
			);

		return (
			1 ===
			(int) $result
		);
	}

	/**
	 * Renew expired fingerprint claim.
	 *
	 * @param string $fingerprint Fingerprint.
	 * @param string $expires_at  New expiry.
	 * @param string $now         Current UTC time.
	 *
	 * @return bool
	 */
	private function renew_expired_claim(
		string $fingerprint,
		string $expires_at,
		string $now
	): bool {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return false;
		}

		$table =
			Installer::get_duplicate_order_table();

		$query =
			$wpdb->prepare(
				"UPDATE %i
				SET
					order_id = 0,
					expires_at = %s,
					created_at = %s,
					updated_at = %s
				WHERE fingerprint_hash = %s
					AND expires_at <= %s",
				$table,
				$expires_at,
				$now,
				$now,
				$fingerprint,
				$now
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query
			);

		return (
			1 ===
			(int) $result
		);
	}

	/**
	 * Get fingerprint record.
	 *
	 * @param string $fingerprint Fingerprint.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_record(
		string $fingerprint
	): ?array {

		global $wpdb;

		$fingerprint =
			$this->sanitize_hash(
				$fingerprint
			);

		if ( '' === $fingerprint ) {
			return null;
		}

		$table =
			Installer::get_duplicate_order_table();

		$query =
			$wpdb->prepare(
				"SELECT
					fingerprint_hash,
					order_id,
					expires_at,
					created_at,
					updated_at
				FROM %i
				WHERE fingerprint_hash = %s
				LIMIT 1",
				$table,
				$fingerprint
			);

		$result =
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared with strict identifier/value placeholders above.
			$wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL was prepared immediately above with strict identifier/value placeholders.
				$query,
				ARRAY_A
			);

		return is_array(
			$result
		)
			? $result
			: null;
	}

	/**
	 * Determine whether Duplicate Order Protection
	 * is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		return SecuritySettings::is_protection_enabled(
			'duplicate_order'
		);
	}


	/**
	 * Get Duplicate Order settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_config(): array {

		$settings =
			SecuritySettings::get_settings();

		$protection =
			isset(
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

		return isset(
			$protection[
				'duplicate_order'
			]
		) &&
		is_array(
			$protection[
				'duplicate_order'
			]
		)
			? $protection[
				'duplicate_order'
			]
			: array();
	}

	/**
	 * Get Duplicate Order detection window.
	 *
	 * @return int
	 */
	private function get_window_minutes(): int {

		$config =
			$this->get_config();

		return max(
			1,
			absint(
				$config[
					'window_minutes'
				] ??
					10
			)
		);
	}

	/**
	 * Get Duplicate Order error.
	 *
	 * @return WP_Error
	 */
	private function get_duplicate_error(): WP_Error {

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
						'duplicate_order'
					] ??
						''
				)
			);

		if ( '' === $message ) {
			$message =
				__(
					'A similar order was recently submitted. Please wait before placing the same order again.',
					'eilmo-checkout-flow'
				);
		}

		return new WP_Error(
			'duplicate_order_detected',
			sanitize_text_field(
				$message
			)
		);
	}



	/**
	 * Determine whether the current trusted store user
	 * may bypass customer-facing Duplicate protection.
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
	 * Determine whether Duplicate blocks should be
	 * written to Security Activity Logs.
	 *
	 * @return bool
	 */
	private function should_log(): bool {

		$settings =
			SecuritySettings::get_settings();

		$logging =
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
					$logging[
						'enabled'
					] ??
						'yes'
				) &&
			'yes' ===
				(
					$logging[
						'log_duplicate'
					] ??
						'yes'
				)
		);
	}

	/**
	 * Get authoritative checkout total when supplied by
	 * the server-side checkout integration.
	 *
	 * @param array<string,mixed> $validated Validated checkout.
	 *
	 * @return float|null
	 */
	private function get_authoritative_total(
		array $validated
	): ?float {

		$totals =
			isset(
				$validated[
					'totals'
				]
			) &&
			is_array(
				$validated[
					'totals'
				]
			)
				? $validated[
					'totals'
				]
				: array();

		$candidates =
			array(
				$totals[
					'grand_total'
				] ??
					null,
				$validated[
					'order_total'
				] ??
					null,
				$validated[
					'grand_total'
				] ??
					null,
			);

		foreach (
			$candidates as
			$candidate
		) {
			if (
				null !== $candidate &&
				is_numeric(
					$candidate
				)
			) {
				return max(
					0.0,
					(float) $candidate
				);
			}
		}

		return null;
	}

	/**
	 * Normalize a monetary value for stable fingerprint
	 * comparison.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function normalize_money(
		float $amount
	): string {

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? max(
					0,
					absint(
						wc_get_price_decimals()
					)
				)
				: 2;

		return number_format(
			max(
				0.0,
				$amount
			),
			$decimals,
			'.',
			''
		);
	}

	/**
	 * Sanitize SHA-256 hash.
	 *
	 * @param string $hash Hash.
	 *
	 * @return string
	 */
	private function sanitize_hash(
		string $hash
	): string {

		$hash =
			strtolower(
				trim(
					$hash
				)
			);

		return (
			64 === strlen(
				$hash
			) &&
			1 === preg_match(
				'/^[a-f0-9]{64}$/',
				$hash
			)
		)
			? $hash
			: '';
	}

	/**
	 * Parse UTC MySQL datetime.
	 *
	 * @param string $date Date.
	 *
	 * @return int
	 */
	private function parse_utc_time(
		string $date
	): int {

		if (
			'' === $date ||
			'0000-00-00 00:00:00' ===
				$date
		) {
			return 0;
		}

		$timestamp =
			strtotime(
				$date .
				' UTC'
			);

		return false !== $timestamp
			? $timestamp
			: 0;
	}
}
