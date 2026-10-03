<?php
/**
 * Courier booking service.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Couriers\Providers\PathaoClient;
use EilmoCheckout\Couriers\Providers\SteadfastClient;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends WooCommerce orders to supported courier providers.
 */
final class CourierBookingService {

	/**
	 * Eilmo remaining-due order meta.
	 */
	private const META_REMAINING_DUE =
		'_eilmo_cf_remaining_due';

	/**
	 * Steadfast status refresh cache in seconds.
	 */
	private const STEADFAST_STATUS_CACHE =
		300;

	/**
	 * Supported providers.
	 *
	 * @var array<int,string>
	 */
	private const PROVIDERS =
		array(
			'steadfast',
			'pathao',
		);

	/**
	 * Get editable courier form defaults for one order.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provider Provider.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_form_data(
		WC_Order $order,
		string $provider
	) {

		$provider =
			$this->normalize_provider(
				$provider
			);

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$customer =
			$this->get_customer_defaults(
				$order
			);

		if ( 'steadfast' === $provider ) {
			return array(
				'provider' =>
					$provider,

				'order_id' =>
					$order->get_id(),

				'invoice' =>
					(string) $order->get_order_number(),

				'recipient_name' =>
					$customer['name'],

				'recipient_phone' =>
					$customer['phone'],

				'recipient_address' =>
					$customer['address'],

				'cod_amount' =>
					$this->get_cod_amount( $order ),

				'item_description' =>
					$this->get_item_description( $order ),

				'note' =>
					$this->get_note( $order ),
			);
		}

		$config =
			$this->get_pathao_config();

		$location =
			$this->get_pathao_location_defaults(
				$order
			);

		return array(
			'provider' =>
				$provider,

			'order_id' =>
				$order->get_id(),

			/*
			 * Keep Pathao merchant_order_id tied to the real WooCommerce
			 * order ID so webhooks and provider references can map back safely.
			 */
			'merchant_order_id' =>
				(string) $order->get_id(),

			'recipient_name' =>
				$customer['name'],

			'recipient_phone' =>
				$customer['phone'],

			'recipient_secondary_phone' =>
				'',

			'recipient_address' =>
				$customer['address'],

			'recipient_city' =>
				$location['city'],

			'recipient_zone' =>
				$location['zone'],

			'recipient_area' =>
				$location['area'],

			'amount_to_collect' =>
				$this->get_cod_amount( $order ),

			'store_id' =>
				absint(
					$config['store_id'] ??
						0
				),

			'delivery_type' =>
				max(
					1,
					absint(
						$config['delivery_type'] ??
							48
					)
				),

			'item_type' =>
				max(
					1,
					absint(
						$config['item_type'] ??
							2
					)
				),

			'item_quantity' =>
				$this->get_item_quantity( $order ),

			'item_weight' =>
				$this->get_item_weight(
					$order,
					(float) (
						$config['item_weight'] ??
							0.5
					)
				),

			'item_description' =>
				$this->get_item_description( $order ),

			'special_instruction' =>
				$this->get_note( $order ),
		);
	}

	/**
	 * Send order to one provider using editable payload values.
	 *
	 * @param WC_Order             $order    Order.
	 * @param string               $provider Provider.
	 * @param array<string,mixed>  $values   Edited courier values.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function send(
		WC_Order $order,
		string $provider,
		array $values = array()
	) {

		$provider =
			$this->normalize_provider(
				$provider
			);

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$existing =
			$this->get_booking_status(
				$order,
				$provider
			);

		if ( ! empty( $existing['sent'] ) ) {
			$existing['idempotent'] =
				true;

			return $existing;
		}

		$defaults =
			$this->get_form_data(
				$order,
				$provider
			);

		if ( is_wp_error( $defaults ) ) {
			return $defaults;
		}

		$payload =
			array_replace(
				$defaults,
				$values
			);

		if ( 'steadfast' === $provider ) {
			$payload =
				$this->sanitize_steadfast_payload(
					$payload,
					$order
				);

			$validation =
				$this->validate_common_payload(
					$payload,
					'cod_amount'
				);

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$config =
				$this->get_steadfast_config();

			$client =
				new SteadfastClient();

			$result =
				$client->create_order(
					$config,
					$payload
				);
		} else {
			$payload =
				$this->sanitize_pathao_payload(
					$payload,
					$order
				);

			$validation =
				$this->validate_common_payload(
					$payload,
					'amount_to_collect'
				);

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			if ( absint( $payload['store_id'] ?? 0 ) <= 0 ) {
				return new WP_Error(
					'pathao_store_missing',
					__(
						'Please select a Pathao store.',
						'eilmo-checkout-flow'
					)
				);
			}

			$config =
				$this->get_pathao_config();

			$client =
				new PathaoClient();

			$result =
				$client->create_order(
					$config,
					$payload
				);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->save_booking(
			$order,
			$provider,
			$result,
			$payload
		);

		return $this->get_booking_status(
			$order,
			$provider
		);
	}

	/**
	 * Get Pathao selectable API data.
	 *
	 * @param string $type      stores|cities|zones|areas.
	 * @param int    $parent_id City or zone ID.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_pathao_options(
		string $type,
		int $parent_id = 0
	) {

		$type =
			sanitize_key(
				$type
			);

		$client =
			new PathaoClient();

		$config =
			$this->get_pathao_config();

		switch ( $type ) {
			case 'stores':
				$result =
					$client->get_stores(
						$config
					);
				break;

			case 'cities':
				$result =
					$client->get_cities(
						$config
					);
				break;

			case 'zones':
				$result =
					$client->get_zones(
						$config,
						$parent_id
					);
				break;

			case 'areas':
				$result =
					$client->get_areas(
						$config,
						$parent_id
					);
				break;

			default:
				return new WP_Error(
					'invalid_pathao_option_type',
					__(
						'Invalid Pathao option request.',
						'eilmo-checkout-flow'
					)
				);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->normalize_pathao_options(
			$result,
			$type
		);
	}

	/**
	 * Get provider booking status from order meta.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provider Provider.
	 *
	 * @return array<string,mixed>
	 */
	public function get_booking_status(
		WC_Order $order,
		string $provider
	): array {

		$provider =
			sanitize_key(
				$provider
			);

		$sent =
			'yes' ===
				(string) $order->get_meta(
					$this->meta_key(
						$provider,
						'sent'
					),
					true
				);

		return array(
			'provider' =>
				$provider,

			'sent' =>
				$sent,

			'reference' =>
				sanitize_text_field(
					(string) $order->get_meta(
						$this->meta_key(
							$provider,
							'reference'
						),
						true
					)
				),

			'tracking_code' =>
				sanitize_text_field(
					(string) $order->get_meta(
						$this->meta_key(
							$provider,
							'tracking_code'
						),
						true
					)
				),

			'delivery_status' =>
				sanitize_key(
					(string) $order->get_meta(
						$this->meta_key(
							$provider,
							'delivery_status'
						),
						true
					)
				),

			'status_checked_at' =>
				absint(
					$order->get_meta(
						$this->meta_key(
							$provider,
							'status_checked_at'
						),
						true
					)
				),

			'sent_at' =>
				sanitize_text_field(
					(string) $order->get_meta(
						$this->meta_key(
							$provider,
							'sent_at'
						),
						true
					)
				),

			'delivery_fee' =>
				(float) $order->get_meta(
					$this->meta_key(
						$provider,
						'delivery_fee'
					),
					true
				),
		);
	}

	/**
	 * Refresh current courier delivery status.
	 *
	 * Steadfast status is refreshed at most once every
	 * five minutes unless a forced refresh is requested.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provider Provider.
	 * @param bool     $force    Force remote refresh.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function refresh_delivery_status(
		WC_Order $order,
		string $provider,
		bool $force = false
	) {

		$provider =
			$this->normalize_provider(
				$provider
			);

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$booking =
			$this->get_booking_status(
				$order,
				$provider
			);

		if ( empty( $booking['sent'] ) ) {
			return $booking;
		}

		/*
		 * Pathao status tracking will be implemented
		 * independently. Do not manufacture a status.
		 */
		if ( 'steadfast' !== $provider ) {
			return $booking;
		}

		$checked_at =
			absint(
				$booking[
					'status_checked_at'
				] ??
					0
			);

		$delivery_status =
			sanitize_key(
				(string) (
					$booking[
						'delivery_status'
					] ??
						''
				)
			);

		if (
			! $force &&
			'' !== $delivery_status &&
			$checked_at > 0 &&
			( time() - $checked_at ) <
				self::STEADFAST_STATUS_CACHE
		) {
			return $booking;
		}

		$client =
			new SteadfastClient();

		$result =
			$client->get_delivery_status(
				$this->get_steadfast_config(),
				(string) (
					$booking[
						'reference'
					] ??
						''
				),
				(string) (
					$booking[
						'tracking_code'
					] ??
						''
				),
				(string) $order->get_order_number()
			);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status =
			sanitize_key(
				(string) (
					$result[
						'delivery_status'
					] ??
						''
				)
			);

		if ( '' !== $status ) {
			$order->update_meta_data(
				$this->meta_key(
					$provider,
					'delivery_status'
				),
				$status
			);
		}

		$order->update_meta_data(
			$this->meta_key(
				$provider,
				'status_checked_at'
			),
			time()
		);

		$order->update_meta_data(
			$this->meta_key(
				$provider,
				'status_response'
			),
			wp_json_encode(
				$result[
					'response'
				] ??
					array()
			)
		);

		$order->save();

		return $this->get_booking_status(
			$order,
			$provider
		);
	}

	/**
	 * Save successful booking result.
	 *
	 * @param WC_Order            $order    Order.
	 * @param string              $provider Provider.
	 * @param array<string,mixed> $result   Result.
	 * @param array<string,mixed> $payload  Submitted payload.
	 *
	 * @return void
	 */
	private function save_booking(
		WC_Order $order,
		string $provider,
		array $result,
		array $payload
	): void {

		$order->update_meta_data(
			$this->meta_key( $provider, 'sent' ),
			'yes'
		);

		$order->update_meta_data(
			$this->meta_key( $provider, 'reference' ),
			sanitize_text_field(
				(string) (
					$result['reference'] ??
						''
				)
			)
		);

		$order->update_meta_data(
			$this->meta_key( $provider, 'tracking_code' ),
			sanitize_text_field(
				(string) (
					$result['tracking_code'] ??
						''
				)
			)
		);

		$delivery_status =
			sanitize_key(
				(string) (
					$result[
						'delivery_status'
					] ??
						''
				)
			);

		if ( '' !== $delivery_status ) {
			$order->update_meta_data(
				$this->meta_key(
					$provider,
					'delivery_status'
				),
				$delivery_status
			);

			$order->update_meta_data(
				$this->meta_key(
					$provider,
					'status_checked_at'
				),
				time()
			);
		}

		$order->update_meta_data(
			$this->meta_key( $provider, 'sent_at' ),
			gmdate( 'c' )
		);

		$order->update_meta_data(
			$this->meta_key( $provider, 'payload' ),
			wp_json_encode( $payload )
		);

		$order->update_meta_data(
			$this->meta_key( $provider, 'response' ),
			wp_json_encode(
				$result['response'] ??
					array()
			)
		);

		if (
			isset( $result['delivery_fee'] ) &&
			is_numeric( $result['delivery_fee'] )
		) {
			$order->update_meta_data(
				$this->meta_key( $provider, 'delivery_fee' ),
				(float) $result['delivery_fee']
			);
		}

		$order->save();
	}

	/**
	 * Sanitize Steadfast payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_steadfast_payload(
		array $payload,
		WC_Order $order
	): array {

		return array(
			'invoice' =>
				sanitize_text_field(
					(string) (
						$payload['invoice'] ??
							''
					)
				),

			'recipient_name' =>
				sanitize_text_field(
					(string) (
						$payload['recipient_name'] ??
							''
					)
				),

			'recipient_phone' =>
				$this->sanitize_phone(
					(string) (
						$payload['recipient_phone'] ??
							''
					)
				),

			'recipient_address' =>
				sanitize_textarea_field(
					(string) (
						$payload['recipient_address'] ??
							''
					)
				),

			'cod_amount' =>
				$this->sanitize_amount(
					$payload['cod_amount'] ??
						0
				),

			'item_description' =>
				$this->limit_text(
					sanitize_textarea_field(
						(string) (
							$payload['item_description'] ??
								''
						)
					),
					500
				),

			'note' =>
				$this->resolve_steadfast_note(
					$payload,
					$order
				),
		);
	}

	/**
	 * Resolve Steadfast courier note.
	 *
	 * The edited Courier popup note has priority. When the
	 * popup note is empty, fall back to the WooCommerce
	 * customer/order note.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @param WC_Order            $order   Order.
	 *
	 * @return string
	 */
	private function resolve_steadfast_note(
		array $payload,
		WC_Order $order
	): string {

		$note =
			isset( $payload['note'] ) &&
			is_scalar( $payload['note'] )
				? sanitize_textarea_field(
					(string) $payload['note']
				)
				: '';

		if ( '' === trim( $note ) ) {
			$note =
				sanitize_textarea_field(
					(string) $order->get_customer_note()
				);
		}

		return $this->limit_text(
			$note,
			500
		);
	}

	/**
	 * Sanitize Pathao payload.
	 *
	 * Pathao merchant_order_id cannot be changed from the popup because it
	 * is the stable WooCommerce order mapping used by provider callbacks.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @param WC_Order            $order   Order.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_pathao_payload(
		array $payload,
		WC_Order $order
	): array {

		$output =
			array(
				'store_id' =>
					absint(
						$payload['store_id'] ??
							0
					),

				'merchant_order_id' =>
					(string) $order->get_id(),

				'recipient_name' =>
					sanitize_text_field(
						(string) (
							$payload['recipient_name'] ??
								''
						)
					),

				'recipient_phone' =>
					$this->sanitize_phone(
						(string) (
							$payload['recipient_phone'] ??
								''
						)
					),

				'recipient_secondary_phone' =>
					$this->sanitize_optional_phone(
						(string) (
							$payload['recipient_secondary_phone'] ??
								''
						)
					),

				'recipient_address' =>
					sanitize_textarea_field(
						(string) (
							$payload['recipient_address'] ??
								''
						)
					),

				'delivery_type' =>
					max(
						1,
						absint(
							$payload['delivery_type'] ??
								48
						)
					),

				'item_type' =>
					max(
						1,
						absint(
							$payload['item_type'] ??
								2
						)
					),

				'special_instruction' =>
					$this->limit_text(
						sanitize_textarea_field(
							(string) (
								$payload['special_instruction'] ??
									''
							)
						),
						500
					),

				'item_quantity' =>
					max(
						1,
						absint(
							$payload['item_quantity'] ??
								1
						)
					),

				'item_weight' =>
					max(
						0.1,
						(float) (
							$payload['item_weight'] ??
								0.5
						)
					),

				'item_description' =>
					$this->limit_text(
						sanitize_textarea_field(
							(string) (
								$payload['item_description'] ??
									''
							)
						),
						500
					),

				'amount_to_collect' =>
					$this->sanitize_amount(
						$payload['amount_to_collect'] ??
							0
					),
			);

		foreach (
			array(
				'recipient_city',
				'recipient_zone',
				'recipient_area',
			) as
				$key
		) {
			$value =
				absint(
					$payload[ $key ] ??
						0
				);

			if ( $value > 0 ) {
				$output[ $key ] =
					$value;
			}
		}

		if ( '' === $output['recipient_secondary_phone'] ) {
			unset(
				$output['recipient_secondary_phone']
			);
		}

		return $output;
	}

	/**
	 * Validate required common courier data.
	 *
	 * @param array<string,mixed> $payload    Payload.
	 * @param string              $amount_key Amount field key.
	 *
	 * @return true|WP_Error
	 */
	private function validate_common_payload(
		array $payload,
		string $amount_key
	) {

		if ( '' === trim( (string) ( $payload['recipient_name'] ?? '' ) ) ) {
			return new WP_Error(
				'courier_customer_name_missing',
				__(
					'Customer name is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		$phone =
			(string) (
				$payload['recipient_phone'] ??
					''
			);

		if (
			'' === (
				new CourierSuccessService()
			)->normalize_phone( $phone )
		) {
			return new WP_Error(
				'courier_customer_phone_missing',
				__(
					'Enter a valid Bangladesh customer phone number.',
					'eilmo-checkout-flow'
				)
			);
		}

		if ( '' === trim( (string) ( $payload['recipient_address'] ?? '' ) ) ) {
			return new WP_Error(
				'courier_customer_address_missing',
				__(
					'Customer delivery address is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! isset( $payload[ $amount_key ] ) ||
			! is_numeric( $payload[ $amount_key ] ) ||
			(float) $payload[ $amount_key ] < 0
		) {
			return new WP_Error(
				'courier_invalid_cod',
				__(
					'Courier collectable amount is not valid.',
					'eilmo-checkout-flow'
				)
			);
		}

		return true;
	}

	/**
	 * Get customer defaults without blocking popup opening.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{name:string,phone:string,address:string}
	 */
	private function get_customer_defaults(
		WC_Order $order
	): array {

		$name =
			trim(
				$order->get_formatted_shipping_full_name()
			);

		if ( '' === $name ) {
			$name =
				trim(
					$order->get_formatted_billing_full_name()
				);
		}

		$raw_phone =
			(string) $order->get_billing_phone();

		$normalized_phone =
			(
				new CourierSuccessService()
			)->normalize_phone(
				$raw_phone
			);

		$phone =
			'' !== $normalized_phone
				? $normalized_phone
				: sanitize_text_field( $raw_phone );

		$shipping_address =
			$this->build_address(
				array(
					$order->get_shipping_address_1(),
					$order->get_shipping_address_2(),
					$order->get_shipping_city(),
					$order->get_shipping_state(),
					$order->get_shipping_postcode(),
					$order->get_shipping_country(),
				)
			);

		$billing_address =
			$this->build_address(
				array(
					$order->get_billing_address_1(),
					$order->get_billing_address_2(),
					$order->get_billing_city(),
					$order->get_billing_state(),
					$order->get_billing_postcode(),
					$order->get_billing_country(),
				)
			);

		return array(
			'name' =>
				sanitize_text_field( $name ),

			'phone' =>
				$phone,

			'address' =>
				'' !== $shipping_address
					? $shipping_address
					: $billing_address,
		);
	}

	/**
	 * Build address from parts.
	 *
	 * @param array<int,mixed> $parts Address parts.
	 *
	 * @return string
	 */
	private function build_address(
		array $parts
	): string {

		$parts =
			array_values(
				array_filter(
					array_map(
						static function ( $value ): string {
							return sanitize_text_field(
								trim( (string) $value )
							);
						},
						$parts
					),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			);

		return implode(
			', ',
			$parts
		);
	}

	/**
	 * Calculate COD amount.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return float
	 */
	private function get_cod_amount(
		WC_Order $order
	): float {

		/*
		 * Courier collection must reflect the money that is still due, not
		 * WooCommerce's generic `is_paid()` flag. WooCommerce treats Processing
		 * and Completed as paid statuses, which makes a normal COD order appear
		 * as 0 collectible even though the courier still has to collect it.
		 *
		 * Eilmo orders persist an authoritative remaining-due snapshot. When that
		 * snapshot exists (including an explicit zero for fully-paid orders), use
		 * it first. Native WooCommerce COD orders do not have that snapshot, so
		 * fall back to the full order total for COD and to zero only for a truly
		 * paid non-COD order.
		 */
		$remaining =
			$order->get_meta(
				self::META_REMAINING_DUE,
				true
			);

		if ( '' !== $remaining && is_numeric( $remaining ) ) {
			$amount =
				max(
					0.0,
					(float) $remaining
				);
		} else {
			$payment_method =
				sanitize_key(
					(string) $order->get_payment_method()
				);

			$eilmo_method =
				sanitize_key(
					(string) $order->get_meta(
						'_eilmo_cf_payment_method_key',
						true
					)
				);

			$is_cod =
				in_array(
					$payment_method,
					array(
						'cod',
						'wc__cod',
						'eilmo_cash_on_delivery',
					),
					true
				) ||
				in_array(
					$eilmo_method,
					array(
						'cod',
						'cash_on_delivery',
					),
					true
				);

			if ( $is_cod ) {
				$amount =
					(float) $order->get_total();
			} elseif ( $order->is_paid() || $order->get_date_paid() ) {
				$amount =
					0.0;
			} else {
				$amount =
					(float) $order->get_total();
			}
		}

		$amount =
			apply_filters(
				'eilmo_cf/courier/cod_amount',
				max( 0.0, round( $amount, 2 ) ),
				$order
			);

		return max(
			0.0,
			round( (float) $amount, 2 )
		);
	}

	/**
	 * Get total item quantity.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return int
	 */
	private function get_item_quantity(
		WC_Order $order
	): int {

		$quantity =
			0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$quantity +=
					max(
						0,
						(int) $item->get_quantity()
					);
			}
		}

		return max(
			1,
			$quantity
		);
	}

	/**
	 * Calculate Pathao item weight.
	 *
	 * @param WC_Order $order    Order.
	 * @param float    $fallback Fallback weight.
	 *
	 * @return float
	 */
	private function get_item_weight(
		WC_Order $order,
		float $fallback
	): float {

		$weight =
			0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product =
				$item->get_product();

			if ( ! $product ) {
				continue;
			}

			$product_weight =
				(float) $product->get_weight();

			if ( $product_weight > 0 ) {
				$weight +=
					$product_weight *
					max(
						1,
						(int) $item->get_quantity()
					);
			}
		}

		if ( $weight <= 0 ) {
			$weight =
				$fallback;
		}

		return max(
			0.1,
			round( $weight, 2 )
		);
	}

	/**
	 * Build compact product description.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_item_description(
		WC_Order $order
	): string {

		$items =
			array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$items[] =
				sprintf(
					'%s x%d',
					$item->get_name(),
					max(
						1,
						(int) $item->get_quantity()
					)
				);
		}

		return $this->limit_text(
			sanitize_textarea_field(
				implode(
					"\n",
					$items
				)
			),
			500
		);
	}

	/**
	 * Get order/customer note.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_note(
		WC_Order $order
	): string {

		return $this->limit_text(
			sanitize_textarea_field(
				(string) $order->get_customer_note()
			),
			500
		);
	}

	/**
	 * Get Pathao location IDs saved by compatible checkout integrations.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{city:int,zone:int,area:int}
	 */
	private function get_pathao_location_defaults(
		WC_Order $order
	): array {

		$shipping_city =
			absint(
				$order->get_meta(
					'_shipping_pathao_city',
					true
				)
			);

		$shipping_zone =
			absint(
				$order->get_meta(
					'_shipping_pathao_zone',
					true
				)
			);

		$shipping_area =
			absint(
				$order->get_meta(
					'_shipping_pathao_area',
					true
				)
			);

		return array(
			'city' =>
				$shipping_city > 0
					? $shipping_city
					: absint(
						$order->get_meta(
							'_billing_pathao_city',
							true
						)
					),

			'zone' =>
				$shipping_zone > 0
					? $shipping_zone
					: absint(
						$order->get_meta(
							'_billing_pathao_zone',
							true
						)
					),

			'area' =>
				$shipping_area > 0
					? $shipping_area
					: absint(
						$order->get_meta(
							'_billing_pathao_area',
							true
						)
					),
		);
	}

	/**
	 * Normalize Pathao option objects for admin JS.
	 *
	 * @param array<int,array<string,mixed>> $items Items.
	 * @param string                         $type  Type.
	 *
	 * @return array<int,array{id:int,name:string,is_default:bool}>
	 */
	private function normalize_pathao_options(
		array $items,
		string $type
	): array {

		$output =
			array();

		foreach ( $items as $item ) {
			$id =
				absint(
					$item['id'] ??
						$item['store_id'] ??
						0
				);

			$name =
				sanitize_text_field(
					(string) (
						$item['name'] ??
						$item['store_name'] ??
						''
					)
				);

			if (
				$id <= 0 ||
				'' === $name
			) {
				continue;
			}

			$output[] =
				array(
					'id' =>
						$id,

					'name' =>
						$name,

					'is_default' =>
						'stores' === $type &&
						! empty(
							$item['is_default_store']
						),
				);
		}

		return $output;
	}

	/**
	 * Sanitize phone into Bangladesh 01XXXXXXXXX form when valid.
	 *
	 * @param string $phone Phone.
	 *
	 * @return string
	 */
	private function sanitize_phone(
		string $phone
	): string {

		$normalized =
			(
				new CourierSuccessService()
			)->normalize_phone(
				$phone
			);

		return '' !== $normalized
			? $normalized
			: sanitize_text_field( $phone );
	}

	/**
	 * Sanitize optional phone.
	 *
	 * @param string $phone Phone.
	 *
	 * @return string
	 */
	private function sanitize_optional_phone(
		string $phone
	): string {

		$phone =
			trim( $phone );

		return '' === $phone
			? ''
			: $this->sanitize_phone( $phone );
	}

	/**
	 * Sanitize monetary amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return float
	 */
	private function sanitize_amount(
		$amount
	): float {

		return max(
			0.0,
			round(
				is_numeric( $amount )
					? (float) $amount
					: 0.0,
				2
			)
		);
	}

	/**
	 * Limit text length safely.
	 *
	 * @param string $value Value.
	 * @param int    $limit Limit.
	 *
	 * @return string
	 */
	private function limit_text(
		string $value,
		int $limit
	): string {

		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $limit )
			: substr( $value, 0, $limit );
	}

	/**
	 * Normalize provider.
	 *
	 * @param string $provider Provider.
	 *
	 * @return string|WP_Error
	 */
	private function normalize_provider(
		string $provider
	) {

		$provider =
			sanitize_key(
				$provider
			);

		if (
			! in_array(
				$provider,
				self::PROVIDERS,
				true
			)
		) {
			return new WP_Error(
				'invalid_courier',
				__(
					'Invalid courier provider.',
					'eilmo-checkout-flow'
				)
			);
		}

		return $provider;
	}

	/**
	 * Build provider order-meta key.
	 *
	 * @param string $provider Provider.
	 * @param string $suffix   Suffix.
	 *
	 * @return string
	 */
	private function meta_key(
		string $provider,
		string $suffix
	): string {

		return sprintf(
			'_eilmo_cf_courier_%s_%s',
			sanitize_key( $provider ),
			sanitize_key( $suffix )
		);
	}

	/**
	 * Get Steadfast config.
	 *
	 * @return array<string,mixed>
	 */
	private function get_steadfast_config(): array {

		$settings =
			CourierSettings::get_settings();

		return isset( $settings['steadfast'] ) &&
			is_array( $settings['steadfast'] )
				? $settings['steadfast']
				: array();
	}

	/**
	 * Get Pathao config.
	 *
	 * @return array<string,mixed>
	 */
	private function get_pathao_config(): array {

		$settings =
			CourierSettings::get_settings();

		return isset( $settings['pathao'] ) &&
			is_array( $settings['pathao'] )
				? $settings['pathao']
				: array();
	}
}
