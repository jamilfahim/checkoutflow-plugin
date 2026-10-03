<?php
/**
 * Meta event builder.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Meta Conversions API events from WooCommerce data.
 */
final class MetaEventBuilder {

	/**
	 * Attribution meta keys.
	 */
	private const META_FBP =
		'_eilmo_cf_meta_fbp';

	private const META_FBC =
		'_eilmo_cf_meta_fbc';

	private const META_CLIENT_IP =
		'_eilmo_cf_meta_client_ip';

	private const META_CLIENT_USER_AGENT =
		'_eilmo_cf_meta_client_user_agent';

	private const META_EVENT_SOURCE_URL =
		'_eilmo_cf_meta_event_source_url';

	/**
	 * Build Purchase event.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $event_id Stable deduplication ID.
	 *
	 * @return array<string,mixed>
	 */
	public function build_purchase(
		WC_Order $order,
		string $event_id
	): array {

		$user_data =
			$this->build_user_data(
				$order
			);

		$custom_data =
			$this->build_purchase_custom_data(
				$order
			);

		$event =
			array(
				'event_name' =>
					'Purchase',

				'event_time' =>
					time(),

				'action_source' =>
					'website',

				'event_source_url' =>
					$this->get_event_source_url(
						$order
					),

				'event_id' =>
					sanitize_text_field(
						$event_id
					),

				'user_data' =>
					$user_data,

				'custom_data' =>
					$custom_data,
			);

		/**
		 * Filters the final Meta Purchase server event.
		 *
		 * Never put access tokens into this payload.
		 *
		 * @param array<string,mixed> $event Event.
		 * @param WC_Order            $order Order.
		 */
		$event =
			apply_filters(
				'eilmo_cf/meta_tracking/purchase_event',
				$event,
				$order
			);

		return is_array( $event )
			? $event
			: array();
	}

	/**
	 * Build Meta user_data.
	 *
	 * Personally identifying values that require hashing
	 * are normalized and SHA-256 hashed before leaving
	 * the site. Browser identifiers, IP and user agent
	 * remain unhashed as required by Meta's server-event
	 * payload format.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	private function build_user_data(
		WC_Order $order
	): array {

		$user_data =
			array();

		$email =
			$this->normalize_email(
				$order->get_billing_email()
			);

		if ( '' !== $email ) {
			$user_data['em'] =
				array(
					hash(
						'sha256',
						$email
					),
				);
		}

		$phone =
			$this->normalize_phone(
				$order->get_billing_phone()
			);

		if ( '' !== $phone ) {
			$user_data['ph'] =
				array(
					hash(
						'sha256',
						$phone
					),
				);
		}

		$customer_id =
			absint(
				$order->get_customer_id()
			);

		if ( $customer_id > 0 ) {
			$user_data['external_id'] =
				array(
					hash(
						'sha256',
						(string) $customer_id
					),
				);
		}

		$client_ip =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_CLIENT_IP,
					true
				)
			);

		if (
			'' === $client_ip &&
			method_exists(
				$order,
				'get_customer_ip_address'
			)
		) {
			$client_ip =
				sanitize_text_field(
					(string) $order
						->get_customer_ip_address()
				);
		}

		if (
			'' !== $client_ip &&
			false !==
				filter_var(
					$client_ip,
					FILTER_VALIDATE_IP
				)
		) {
			$user_data['client_ip_address'] =
				$client_ip;
		}

		$user_agent =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_CLIENT_USER_AGENT,
					true
				)
			);

		if (
			'' === $user_agent &&
			method_exists(
				$order,
				'get_customer_user_agent'
			)
		) {
			$user_agent =
				sanitize_text_field(
					(string) $order
						->get_customer_user_agent()
				);
		}

		if ( '' !== $user_agent ) {
			$user_data['client_user_agent'] =
				$user_agent;
		}

		$fbp =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_FBP,
					true
				)
			);

		if ( '' !== $fbp ) {
			$user_data['fbp'] =
				$fbp;
		}

		$fbc =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_FBC,
					true
				)
			);

		if ( '' !== $fbc ) {
			$user_data['fbc'] =
				$fbc;
		}

		/**
		 * Filters Meta user data.
		 *
		 * @param array<string,mixed> $user_data User data.
		 * @param WC_Order            $order     Order.
		 */
		$user_data =
			apply_filters(
				'eilmo_cf/meta_tracking/user_data',
				$user_data,
				$order
			);

		return is_array( $user_data )
			? $user_data
			: array();
	}

	/**
	 * Build Purchase custom_data.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	private function build_purchase_custom_data(
		WC_Order $order
	): array {

		$content_ids =
			array();

		$contents =
			array();

		$num_items =
			0;

		foreach (
			$order->get_items(
				'line_item'
			) as
				$item
		) {
			if (
				! $item instanceof
					WC_Order_Item_Product
			) {
				continue;
			}

			$quantity =
				max(
					0,
					(int) $item->get_quantity()
				);

			if ( $quantity <= 0 ) {
				continue;
			}

			$product_id =
				absint(
					$item->get_product_id()
				);

			$variation_id =
				absint(
					$item->get_variation_id()
				);

			$content_id =
				(string) (
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if ( '0' === $content_id ) {
				continue;
			}

			$content_ids[] =
				$content_id;

			$line_total =
				max(
					0.0,
					(float) $item->get_total()
				);

			$item_price =
				$quantity > 0
					? round(
						$line_total /
							$quantity,
						wc_get_price_decimals()
					)
					: 0.0;

			$contents[] =
				array(
					'id' =>
						$content_id,

					'quantity' =>
						$quantity,

					'item_price' =>
						$item_price,
				);

			$num_items +=
				$quantity;
		}

		$custom_data =
			array(
				'currency' =>
					sanitize_text_field(
						(string) $order
							->get_currency()
					),

				'value' =>
					(float) $order->get_total(),

				'content_type' =>
					'product',

				'content_ids' =>
					array_values(
						array_unique(
							$content_ids
						)
					),

				'contents' =>
					$contents,

				'num_items' =>
					$num_items,

				'order_id' =>
					(string) $order->get_id(),
			);

		/**
		 * Filters Meta Purchase custom_data.
		 *
		 * @param array<string,mixed> $custom_data Custom data.
		 * @param WC_Order            $order       Order.
		 */
		$custom_data =
			apply_filters(
				'eilmo_cf/meta_tracking/purchase_custom_data',
				$custom_data,
				$order
			);

		return is_array( $custom_data )
			? $custom_data
			: array();
	}

	/**
	 * Get event source URL.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_event_source_url(
		WC_Order $order
	): string {

		$url =
			esc_url_raw(
				(string) $order->get_meta(
					self::META_EVENT_SOURCE_URL,
					true
				)
			);

		if ( '' !== $url ) {
			return $url;
		}

		$url =
			esc_url_raw(
				$order
					->get_checkout_order_received_url()
			);

		if ( '' !== $url ) {
			return $url;
		}

		return esc_url_raw(
			home_url( '/' )
		);
	}

	/**
	 * Normalize email.
	 *
	 * @param string $email Email.
	 *
	 * @return string
	 */
	private function normalize_email(
		string $email
	): string {

		$email =
			strtolower(
				trim(
					$email
				)
			);

		return is_email( $email )
			? $email
			: '';
	}

	/**
	 * Normalize phone.
	 *
	 * @param string $phone Phone.
	 *
	 * @return string
	 */
	private function normalize_phone(
		string $phone
	): string {

		$phone =
			preg_replace(
				'/\D+/',
				'',
				$phone
			);

		return is_string( $phone )
			? $phone
			: '';
	}
}
