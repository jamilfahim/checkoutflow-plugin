<?php
/**
 * Meta server event builder.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Builds nonce-protected browser-originated CAPI events.
 */
final class MetaServerEventBuilder {

	/**
	 * Maximum content rows accepted in one event.
	 */
	private const MAX_CONTENTS =
		50;

	/**
	 * Build a server event.
	 *
	 * @param string              $event_name Event name.
	 * @param string              $event_id   Event ID.
	 * @param array<string,mixed> $raw_data   Browser custom data.
	 * @param array<string,mixed> $customer   Checkout customer data.
	 * @param string              $source_url Source URL.
	 *
	 * @return array<string,mixed>
	 */
	public function build(
		string $event_name,
		string $event_id,
		array $raw_data,
		array $customer,
		string $source_url
	): array {

		$event_name =
			$this->normalize_event_name(
				$event_name
			);

		$event_id =
			$this->normalize_event_id(
				$event_id
			);

		if (
			'' === $event_name ||
			'' === $event_id
		) {
			return array();
		}

		$source_url =
			$this->sanitize_source_url(
				$source_url
			);

		$user_builder =
			new MetaUserDataBuilder();

		$user_data =
			$user_builder->from_request(
				'InitiateCheckout' === $event_name
					? $customer
					: array(),
				$source_url
			);

		if ( empty( $user_data ) ) {
			return array();
		}

		$custom_data =
			$this->build_custom_data(
				$event_name,
				$raw_data
			);

		$event =
			array(
				'event_name' =>
					$event_name,

				'event_time' =>
					time(),

				'action_source' =>
					'website',

				'event_source_url' =>
					$source_url,

				'event_id' =>
					$event_id,

				'user_data' =>
					$user_data,

				'custom_data' =>
					$custom_data,
			);

		/**
		 * Filters a browser-originated Meta CAPI event.
		 *
		 * @param array<string,mixed> $event      Event.
		 * @param string              $event_name Event name.
		 */
		$event =
			apply_filters(
				'eilmo_cf/meta_tracking/server_event',
				$event,
				$event_name
			);

		return is_array( $event )
			? $event
			: array();
	}

	/**
	 * Build custom_data using server-validated products.
	 *
	 * Browser totals are deliberately not trusted. For
	 * ViewContent/AddToCart, current WooCommerce product
	 * prices are used. InitiateCheckout omits value because
	 * Eilmo discounts/delivery/final totals are authoritative
	 * only inside the order pipeline.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $raw_data   Raw data.
	 *
	 * @return array<string,mixed>
	 */
	private function build_custom_data(
		string $event_name,
		array $raw_data
	): array {

		$raw_contents =
			isset( $raw_data['contents'] ) &&
			is_array( $raw_data['contents'] )
				? $raw_data['contents']
				: array();

		$contents =
			array();

		$content_ids =
			array();

		$total_value =
			0.0;

		$num_items =
			0;

		foreach (
			array_slice(
				$raw_contents,
				0,
				self::MAX_CONTENTS
			) as $raw_content
		) {
			if ( ! is_array( $raw_content ) ) {
				continue;
			}

			$content_id =
				absint(
					$raw_content['id'] ??
						0
				);

			$quantity =
				max(
					1,
					min(
						999,
						absint(
							$raw_content['quantity'] ??
								1
						)
					)
				);

			if ( $content_id <= 0 ) {
				continue;
			}

			$product =
				wc_get_product(
					$content_id
				);

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$content_ids[] =
				(string) $content_id;

			$row =
				array(
					'id' =>
						(string) $content_id,

					'quantity' =>
						$quantity,
				);

			if (
				'InitiateCheckout' !== $event_name
			) {
				$price =
					$this->get_product_price(
						$product
					);

				if ( $price >= 0 ) {
					$row['item_price'] =
						$price;

					$total_value +=
						$price *
						$quantity;
				}
			}

			$contents[] =
				$row;

			$num_items +=
				$quantity;
		}

		$custom_data =
			array(
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

				'currency' =>
					function_exists(
						'get_woocommerce_currency'
					)
						? sanitize_text_field(
							get_woocommerce_currency()
						)
						: '',
			);

		if ( $num_items > 0 ) {
			$custom_data['num_items'] =
				$num_items;
		}

		if (
			'InitiateCheckout' !== $event_name &&
			$total_value > 0
		) {
			$custom_data['value'] =
				round(
					$total_value,
					function_exists(
						'wc_get_price_decimals'
					)
						? wc_get_price_decimals()
						: 2
				);
		}

		if (
			'ViewContent' === $event_name &&
			1 === count( $contents )
		) {
			$product =
				wc_get_product(
					absint(
						$contents[0]['id'] ??
							0
					)
				);

			if ( $product instanceof WC_Product ) {
				$custom_data['content_name'] =
					sanitize_text_field(
						$product->get_name()
					);
			}
		}

		/**
		 * Filters validated custom_data.
		 *
		 * @param array<string,mixed> $custom_data Custom data.
		 * @param string              $event_name Event name.
		 */
		$custom_data =
			apply_filters(
				'eilmo_cf/meta_tracking/server_custom_data',
				$custom_data,
				$event_name
			);

		return is_array( $custom_data )
			? $custom_data
			: array();
	}

	/**
	 * Get current WooCommerce product price.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return float
	 */
	private function get_product_price(
		WC_Product $product
	): float {

		$price =
			$product->get_price();

		if ( ! is_numeric( $price ) ) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $price
		);
	}

	/**
	 * Normalize supported event name.
	 *
	 * @param string $event_name Event name.
	 *
	 * @return string
	 */
	private function normalize_event_name(
		string $event_name
	): string {

		$event_name =
			trim(
				$event_name
			);

		return in_array(
			$event_name,
			array(
				'ViewContent',
				'AddToCart',
				'InitiateCheckout',
			),
			true
		)
			? $event_name
			: '';
	}

	/**
	 * Normalize event ID.
	 *
	 * @param string $event_id Event ID.
	 *
	 * @return string
	 */
	private function normalize_event_id(
		string $event_id
	): string {

		$event_id =
			preg_replace(
				'/[^a-zA-Z0-9_.:-]/',
				'',
				trim(
					$event_id
				)
			);

		if ( ! is_string( $event_id ) ) {
			return '';
		}

		return substr(
			$event_id,
			0,
			128
		);
	}

	/**
	 * Keep event_source_url on the current WordPress host.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function sanitize_source_url(
		string $url
	): string {

		$url =
			esc_url_raw(
				$url
			);

		$home_url =
			home_url( '/' );

		$home_host =
			strtolower(
				(string) wp_parse_url(
					$home_url,
					PHP_URL_HOST
				)
			);

		$url_host =
			strtolower(
				(string) wp_parse_url(
					$url,
					PHP_URL_HOST
				)
			);

		if (
			'' === $url ||
			'' === $home_host ||
			$home_host !== $url_host
		) {
			return esc_url_raw(
				$home_url
			);
		}

		return $url;
	}
}
