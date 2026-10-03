<?php
/**
 * Pathao Courier webhook receiver.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Webhooks;

use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

\defined( 'ABSPATH' ) || exit;

/**
 * Receives Pathao order lifecycle webhooks and stores
 * the latest Pathao status in WooCommerce order meta.
 */
final class PathaoWebhook implements RegistrableInterface {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE =
		'eilmo-checkout/v1';

	/**
	 * REST route.
	 */
	private const REST_ROUTE =
		'/couriers/pathao/webhook';

	/**
	 * Incoming Pathao webhook authentication header.
	 *
	 * Pathao sends the merchant-configured webhook secret
	 * in this request header.
	 */
	private const SIGNATURE_HEADER =
		'x-pathao-signature';

	/**
	 * Pathao webhook-integration response header.
	 */
	private const INTEGRATION_SECRET_HEADER =
		'X-Pathao-Merchant-Webhook-Integration-Secret';

	/**
	 * Required Pathao webhook integration response value.
	 *
	 * This value mirrors Pathao's official WooCommerce
	 * integration implementation and Merchant Panel test.
	 */
	private const INTEGRATION_RESPONSE_SECRET =
		'f3992ecc-59da-4cbe-a049-a13da2018d51';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'rest_api_init',
			array(
				$this,
				'register_route',
			)
		);
	}

	/**
	 * Register Pathao webhook endpoint.
	 *
	 * @return void
	 */
	public function register_route(): void {

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods' =>
					'POST',

				'callback' =>
					array(
						$this,
						'handle',
					),

				'permission_callback' =>
					array(
						$this,
						'verify_signature',
					),
			)
		);
	}

	/**
	 * Verify incoming Pathao webhook.
	 *
	 * Pathao sends the Secret configured in Merchant Panel
	 * through X-Pathao-Signature. The same value must be
	 * stored in Eilmo Courier Settings > Pathao > Webhook Secret.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool
	 */
	public function verify_signature(
		WP_REST_Request $request
	): bool {

		$expected =
			$this->get_webhook_secret();

		if ( '' === $expected ) {
			return false;
		}

		$received =
			trim(
				(string) $request->get_header(
					self::SIGNATURE_HEADER
				)
			);

		if ( '' === $received ) {
			return false;
		}

		return hash_equals(
			$expected,
			$received
		);
	}

	/**
	 * Handle one Pathao webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle(
		WP_REST_Request $request
	): WP_REST_Response {

		$payload =
			$request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload =
				$request->get_params();
		}

		if ( ! is_array( $payload ) ) {
			return $this->response(
				__(
					'Invalid Pathao webhook payload.',
					'eilmo-checkout-flow'
				),
				400
			);
		}

		$event =
			sanitize_text_field(
				(string) (
					$payload['event'] ??
						''
				)
			);

		/*
		 * Pathao Merchant Panel sends this event while testing
		 * the webhook integration.
		 *
		 * Pathao requires:
		 * - HTTP 202
		 * - X-Pathao-Merchant-Webhook-Integration-Secret header
		 * - the exact integration response value.
		 */
		if ( 'webhook_integration' === $event ) {
			return $this->response(
				__(
					'Successfully accepted webhook integration.',
					'eilmo-checkout-flow'
				),
				202
			);
		}

		$order_id =
			absint(
				$payload['merchant_order_id'] ??
					0
			);

		$order =
			$order_id > 0
				? wc_get_order(
					$order_id
				)
				: false;

		if ( ! $order instanceof WC_Order ) {
			return $this->response(
				__(
					'WooCommerce order not found.',
					'eilmo-checkout-flow'
				),
				404
			);
		}

		$status =
			sanitize_key(
				(string) (
					$payload['order_status'] ??
						''
				)
			);

		if ( '' === $status ) {
			$status =
				$this->status_from_event(
					$event
				);
		}

		$consignment_id =
			sanitize_text_field(
				(string) (
					$payload['consignment_id'] ??
						''
				)
			);

		$delivery_fee =
			isset( $payload['delivery_fee'] ) &&
			is_numeric( $payload['delivery_fee'] )
				? (float) $payload['delivery_fee']
				: null;

		if ( '' !== $status ) {
			$order->update_meta_data(
				'_eilmo_cf_courier_pathao_delivery_status',
				$status
			);

			$order->update_meta_data(
				'_eilmo_cf_courier_pathao_status_checked_at',
				time()
			);
		}

		if ( '' !== $consignment_id ) {
			$order->update_meta_data(
				'_eilmo_cf_courier_pathao_reference',
				$consignment_id
			);

			$current_tracking =
				sanitize_text_field(
					(string) $order->get_meta(
						'_eilmo_cf_courier_pathao_tracking_code',
						true
					)
				);

			if ( '' === $current_tracking ) {
				$order->update_meta_data(
					'_eilmo_cf_courier_pathao_tracking_code',
					$consignment_id
				);
			}
		}

		if ( null !== $delivery_fee ) {
			$order->update_meta_data(
				'_eilmo_cf_courier_pathao_delivery_fee',
				$delivery_fee
			);
		}

		/*
		 * Keep the latest raw webhook payload for debugging and
		 * future status enhancements without exposing it publicly.
		 */
		$order->update_meta_data(
			'_eilmo_cf_courier_pathao_status_response',
			wp_json_encode(
				$payload,
				JSON_UNESCAPED_UNICODE |
				JSON_UNESCAPED_SLASHES
			)
		);

		$order->save();

		return $this->response(
			__(
				'Pathao order status updated.',
				'eilmo-checkout-flow'
			),
			202
		);
	}

	/**
	 * Map Pathao event names to local status keys.
	 *
	 * @param string $event Webhook event.
	 *
	 * @return string
	 */
	private function status_from_event(
		string $event
	): string {

		$map =
			array(
				'order.created' =>
					'order_created',

				'order.updated' =>
					'order_updated',

				'order.pickup-requested' =>
					'pickup_requested',

				'order.assigned-for-pickup' =>
					'assigned_for_pickup',

				'order.picked' =>
					'picked',

				'order.pickup-failed' =>
					'pickup_failed',

				'order.pickup-cancelled' =>
					'pickup_cancelled',

				'order.at-the-sorting-hub' =>
					'at_the_sorting_hub',

				'order.in-transit' =>
					'in_transit',

				'order.received-at-last-mile-hub' =>
					'received_at_last_mile_hub',

				'order.assigned-for-delivery' =>
					'assigned_for_delivery',

				'order.delivered' =>
					'delivered',

				'order.partial-delivery' =>
					'partial_delivery',

				'order.returned' =>
					'return',

				'order.delivery-failed' =>
					'delivery_failed',

				'order.on-hold' =>
					'on_hold',

				'order.paid-return' =>
					'paid_return',

				'order.exchanged' =>
					'exchange',

				'order.paid' =>
					'payment_invoice',
			);

		return isset( $map[ $event ] )
			? (string) $map[ $event ]
			: '';
	}

	/**
	 * Get configured Pathao webhook secret.
	 *
	 * This MUST match the Secret entered in Pathao Merchant
	 * Panel > Developer API > Webhook Integration.
	 *
	 * @return string
	 */
	private function get_webhook_secret(): string {

		$settings =
			CourierSettings::get_settings();

		$pathao =
			isset( $settings['pathao'] ) &&
			is_array( $settings['pathao'] )
				? $settings['pathao']
				: array();

		return trim(
			(string) (
				$pathao['webhook_secret'] ??
					''
			)
		);
	}

	/**
	 * Build Pathao webhook response.
	 *
	 * Pathao's Merchant Panel integration test requires the
	 * fixed X-Pathao-Merchant-Webhook-Integration-Secret
	 * response header value used by Pathao's official plugin.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 *
	 * @return WP_REST_Response
	 */
	private function response(
		string $message,
		int $status
	): WP_REST_Response {

		$response =
			new WP_REST_Response(
				array(
					'status' =>
						$status,

					'message' =>
						$message,

					'data' =>
						null,
				),
				$status
			);

		$response->header(
			self::INTEGRATION_SECRET_HEADER,
			self::INTEGRATION_RESPONSE_SECRET
		);

		return $response;
	}
}
