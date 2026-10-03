<?php
/**
 * Steadfast Courier client.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Providers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Steadfast Courier API requests.
 */
final class SteadfastClient {

	/**
	 * Default Steadfast API base URL.
	 */
	private const DEFAULT_BASE_URL =
		'https://portal.packzy.com/api/v1';

	/**
	 * Create one Steadfast order.
	 *
	 * @param array<string,mixed> $config  Provider configuration.
	 * @param array<string,mixed> $payload Order payload.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_order(
		array $config,
		array $payload
	) {

		$credentials =
			$this->get_credentials(
				$config
			);

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$request_payload =
			$this->build_order_payload(
				$payload
			);

		$validation =
			$this->validate_order_payload(
				$request_payload
			);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$response =
			wp_safe_remote_post(
				$this->get_base_url(
					$config
				) .
					'/create_order',
				array(
					'timeout' =>
						25,

					'redirection' =>
						2,

					'headers' =>
						array(
							'Api-Key' =>
								$credentials[
									'api_key'
								],

							'Secret-Key' =>
								$credentials[
									'secret_key'
								],

							'Content-Type' =>
								'application/json',

							'Accept' =>
								'application/json',
						),

					'body' =>
						wp_json_encode(
							$request_payload,
							JSON_UNESCAPED_UNICODE |
							JSON_UNESCAPED_SLASHES
						),

					'data_format' =>
						'body',
				)
			);

		return $this->normalize_create_response(
			$response
		);
	}

	/**
	 * Build Steadfast create-order payload.
	 *
	 * Important:
	 *
	 * note and item_description must be top-level
	 * JSON properties in the /create_order request.
	 *
	 * @param array<string,mixed> $payload Payload.
	 *
	 * @return array<string,mixed>
	 */
	private function build_order_payload(
		array $payload
	): array {

		$request =
			array(
				'invoice' =>
					sanitize_text_field(
						(string) (
							$payload[
								'invoice'
							] ??
								''
						)
					),

				'recipient_name' =>
					$this->limit_text(
						sanitize_text_field(
							(string) (
								$payload[
									'recipient_name'
								] ??
									''
							)
						),
						100
					),

				'recipient_phone' =>
					$this->sanitize_phone(
						$payload[
							'recipient_phone'
						] ??
							''
					),

				'recipient_address' =>
					$this->limit_text(
						sanitize_textarea_field(
							(string) (
								$payload[
									'recipient_address'
								] ??
									''
							)
						),
						250
					),

				'cod_amount' =>
					$this->sanitize_amount(
						$payload[
							'cod_amount'
						] ??
							0
					),

				/*
				 * Steadfast Note.
				 *
				 * The popup Note must arrive here as a
				 * direct top-level JSON field.
				 */
				'note' =>
					$this->limit_text(
						sanitize_text_field(
							(string) (
								$payload[
									'note'
								] ??
									''
							)
						),
						500
					),

				/*
				 * Product/item information.
				 */
				'item_description' =>
					$this->limit_text(
						sanitize_text_field(
							(string) (
								$payload[
									'item_description'
								] ??
									''
							)
						),
						500
					),
			);

		/*
		 * Optional alternative phone.
		 */
		$alternative_phone =
			$this->sanitize_phone(
				$payload[
					'alternative_phone'
				] ??
					''
			);

		if ( '' !== $alternative_phone ) {
			$request[
				'alternative_phone'
			] =
				$alternative_phone;
		}

		/*
		 * Optional recipient email.
		 */
		$recipient_email =
			sanitize_email(
				(string) (
					$payload[
						'recipient_email'
					] ??
						''
				)
			);

		if ( '' !== $recipient_email ) {
			$request[
				'recipient_email'
			] =
				$recipient_email;
		}

		/*
		 * Optional total lot.
		 */
		if (
			isset(
				$payload[
					'total_lot'
				]
			) &&
			is_numeric(
				$payload[
					'total_lot'
				]
			)
		) {
			$total_lot =
				absint(
					$payload[
						'total_lot'
					]
				);

			if ( $total_lot > 0 ) {
				$request[
					'total_lot'
				] =
					$total_lot;
			}
		}

		/*
		 * Optional delivery type.
		 *
		 * 0 = Home Delivery.
		 * 1 = Point Delivery / Hub Pickup.
		 */
		if (
			isset(
				$payload[
					'delivery_type'
				]
			) &&
			is_numeric(
				$payload[
					'delivery_type'
				]
			)
		) {
			$delivery_type =
				(int) $payload[
					'delivery_type'
				];

			if (
				in_array(
					$delivery_type,
					array(
						0,
						1,
					),
					true
				)
			) {
				$request[
					'delivery_type'
				] =
					$delivery_type;
			}
		}

		return $request;
	}

	/**
	 * Validate required Steadfast order fields.
	 *
	 * @param array<string,mixed> $payload Payload.
	 *
	 * @return true|WP_Error
	 */
	private function validate_order_payload(
		array $payload
	) {

		if (
			'' ===
				trim(
					(string) (
						$payload[
							'invoice'
						] ??
							''
					)
				)
		) {
			return new WP_Error(
				'steadfast_invoice_required',
				__(
					'Steadfast invoice is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			'' ===
				trim(
					(string) (
						$payload[
							'recipient_name'
						] ??
							''
					)
				)
		) {
			return new WP_Error(
				'steadfast_recipient_name_required',
				__(
					'Steadfast recipient name is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		$phone =
			(string) (
				$payload[
					'recipient_phone'
				] ??
					''
			);

		if (
			11 !==
				strlen(
					$phone
				)
		) {
			return new WP_Error(
				'steadfast_phone_invalid',
				__(
					'Steadfast recipient phone must contain 11 digits.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			'' ===
				trim(
					(string) (
						$payload[
							'recipient_address'
						] ??
							''
					)
				)
		) {
			return new WP_Error(
				'steadfast_address_required',
				__(
					'Steadfast recipient address is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! isset(
				$payload[
					'cod_amount'
				]
			) ||
			! is_numeric(
				$payload[
					'cod_amount'
				]
			) ||
			(float) $payload[
				'cod_amount'
			] < 0
		) {
			return new WP_Error(
				'steadfast_cod_invalid',
				__(
					'Steadfast COD amount is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		return true;
	}

	/**
	 * Normalize Steadfast create-order response.
	 *
	 * @param array<string,mixed>|WP_Error $response Response.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_create_response(
		$response
	) {

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_status =
			absint(
				wp_remote_retrieve_response_code(
					$response
				)
			);

		$body =
			(string)
				wp_remote_retrieve_body(
					$response
				);

		$decoded =
			json_decode(
				$body,
				true
			);

		$api_status =
			is_array( $decoded ) &&
			isset(
				$decoded[
					'status'
				]
			) &&
			is_numeric(
				$decoded[
					'status'
				]
			)
				? absint(
					$decoded[
						'status'
					]
				)
				: 0;

		if (
			$http_status < 200 ||
			$http_status >= 300 ||
			! is_array(
				$decoded
			) ||
			$api_status >= 400
		) {
			$error_status =
				$api_status >= 400
					? $api_status
					: (
						$http_status > 0
							? $http_status
							: 500
					);

			return new WP_Error(
				'steadfast_api_error',
				$this->get_message(
					$decoded,
					$error_status
				),
				array(
					'status' =>
						$error_status,

					'errors' =>
						$this->extract_errors(
							$decoded
						),
				)
			);
		}

		$consignment =
			$this->extract_consignment(
				$decoded
			);

		$reference =
			sanitize_text_field(
				(string) (
					$consignment[
						'consignment_id'
					] ??
						''
				)
			);

		$tracking_code =
			sanitize_text_field(
				(string) (
					$consignment[
						'tracking_code'
					] ??
						''
				)
			);

		if (
			'' === $reference &&
			'' === $tracking_code
		) {
			return new WP_Error(
				'steadfast_invalid_response',
				__(
					'Steadfast did not return a consignment reference.',
					'eilmo-checkout-flow'
				),
				array(
					'status' =>
						$http_status > 0
							? $http_status
							: 500,
				)
			);
		}

		if ( '' === $reference ) {
			$reference =
				$tracking_code;
		}

		if ( '' === $tracking_code ) {
			$tracking_code =
				$reference;
		}

		return array(
			'provider' =>
				'steadfast',

			'reference' =>
				$reference,

			'tracking_code' =>
				$tracking_code,

			/*
			 * Initial Steadfast delivery status.
			 *
			 * Normally "in_review" after creation.
			 */
			'delivery_status' =>
				sanitize_key(
					(string) (
						$consignment[
							'status'
						] ??
							''
					)
				),

			/*
			 * IMPORTANT:
			 *
			 * This lets us verify that Steadfast actually
			 * accepted the Note sent from the popup.
			 */
			'note' =>
				sanitize_text_field(
					(string) (
						$consignment[
							'note'
						] ??
							''
					)
				),

			'invoice' =>
				sanitize_text_field(
					(string) (
						$consignment[
							'invoice'
						] ??
							''
					)
				),

			'response' =>
				$decoded,
		);
	}

	/**
	 * Get current Steadfast delivery status.
	 *
	 * Consignment ID is preferred. Tracking code and
	 * invoice are used as fallbacks.
	 *
	 * @param array<string,mixed> $config         Provider configuration.
	 * @param string              $consignment_id Consignment ID.
	 * @param string              $tracking_code  Tracking code.
	 * @param string              $invoice        Invoice.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_delivery_status(
		array $config,
		string $consignment_id = '',
		string $tracking_code = '',
		string $invoice = ''
	) {

		$credentials =
			$this->get_credentials(
				$config
			);

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$consignment_id =
			sanitize_text_field(
				trim(
					$consignment_id
				)
			);

		$tracking_code =
			sanitize_text_field(
				trim(
					$tracking_code
				)
			);

		$invoice =
			sanitize_text_field(
				trim(
					$invoice
				)
			);

		if ( '' !== $consignment_id ) {
			$path =
				'/status_by_cid/' .
					rawurlencode(
						$consignment_id
					);
		} elseif ( '' !== $tracking_code ) {
			$path =
				'/status_by_trackingcode/' .
					rawurlencode(
						$tracking_code
					);
		} elseif ( '' !== $invoice ) {
			$path =
				'/status_by_invoice/' .
					rawurlencode(
						$invoice
					);
		} else {
			return new WP_Error(
				'steadfast_status_reference_missing',
				__(
					'Steadfast status reference is missing.',
					'eilmo-checkout-flow'
				)
			);
		}

		$response =
			wp_safe_remote_get(
				$this->get_base_url(
					$config
				) .
					$path,
				array(
					'timeout' =>
						20,

					'redirection' =>
						2,

					'headers' =>
						array(
							'Api-Key' =>
								$credentials[
									'api_key'
								],

							'Secret-Key' =>
								$credentials[
									'secret_key'
								],

							'Content-Type' =>
								'application/json',

							'Accept' =>
								'application/json',
						),
				)
			);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_status =
			absint(
				wp_remote_retrieve_response_code(
					$response
				)
			);

		$decoded =
			json_decode(
				(string)
					wp_remote_retrieve_body(
						$response
					),
				true
			);

		$api_status =
			is_array( $decoded ) &&
			isset(
				$decoded[
					'status'
				]
			) &&
			is_numeric(
				$decoded[
					'status'
				]
			)
				? absint(
					$decoded[
						'status'
					]
				)
				: 0;

		if (
			$http_status < 200 ||
			$http_status >= 300 ||
			! is_array(
				$decoded
			) ||
			$api_status >= 400
		) {
			$error_status =
				$api_status >= 400
					? $api_status
					: (
						$http_status > 0
							? $http_status
							: 500
					);

			return new WP_Error(
				'steadfast_status_api_error',
				$this->get_message(
					$decoded,
					$error_status
				),
				array(
					'status' =>
						$error_status,

					'errors' =>
						$this->extract_errors(
							$decoded
						),
				)
			);
		}

		$delivery_status =
			sanitize_key(
				(string) (
					$decoded[
						'delivery_status'
					] ??
						$decoded[
							'data'
						][
							'delivery_status'
						] ??
							''
				)
			);

		if ( '' === $delivery_status ) {
			return new WP_Error(
				'steadfast_status_missing',
				__(
					'Steadfast did not return a delivery status.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'provider' =>
				'steadfast',

			'delivery_status' =>
				$delivery_status,

			'response' =>
				$decoded,
		);
	}

	/**
	 * Get Steadfast credentials.
	 *
	 * @param array<string,mixed> $config Provider configuration.
	 *
	 * @return array{api_key:string,secret_key:string}|WP_Error
	 */
	private function get_credentials(
		array $config
	) {

		$api_key =
			trim(
				(string) (
					$config[
						'api_key'
					] ??
						''
				)
			);

		$secret_key =
			trim(
				(string) (
					$config[
						'secret_key'
					] ??
						''
				)
			);

		if (
			'' === $api_key ||
			'' === $secret_key
		) {
			return new WP_Error(
				'steadfast_not_configured',
				__(
					'Steadfast API Key and Secret Key are required.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'api_key' =>
				$api_key,

			'secret_key' =>
				$secret_key,
		);
	}

	/**
	 * Get Steadfast API base URL.
	 *
	 * @param array<string,mixed> $config Provider configuration.
	 *
	 * @return string
	 */
	private function get_base_url(
		array $config
	): string {

		$base_url =
			untrailingslashit(
				esc_url_raw(
					(string) (
						$config[
							'base_url'
						] ??
							self::DEFAULT_BASE_URL
					)
				)
			);

		return '' !== $base_url
			? $base_url
			: self::DEFAULT_BASE_URL;
	}

	/**
	 * Extract consignment object.
	 *
	 * @param array<string,mixed> $decoded API response.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_consignment(
		array $decoded
	): array {

		if (
			isset(
				$decoded[
					'consignment'
				]
			) &&
			is_array(
				$decoded[
					'consignment'
				]
			)
		) {
			return $decoded[
				'consignment'
			];
		}

		if (
			isset(
				$decoded[
					'data'
				][
					'consignment'
				]
			) &&
			is_array(
				$decoded[
					'data'
				][
					'consignment'
				]
			)
		) {
			return $decoded[
				'data'
			][
				'consignment'
			];
		}

		if (
			isset(
				$decoded[
					'data'
				]
			) &&
			is_array(
				$decoded[
					'data'
				]
			) &&
			(
				isset(
					$decoded[
						'data'
					][
						'consignment_id'
					]
				) ||
				isset(
					$decoded[
						'data'
					][
						'tracking_code'
					]
				)
			)
		) {
			return $decoded[
				'data'
			];
		}

		return array();
	}

	/**
	 * Extract API validation errors.
	 *
	 * @param mixed $decoded Decoded API response.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_errors(
		$decoded
	): array {

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		if (
			isset(
				$decoded[
					'errors'
				]
			) &&
			is_array(
				$decoded[
					'errors'
				]
			)
		) {
			return $decoded[
				'errors'
			];
		}

		if (
			isset(
				$decoded[
					'data'
				][
					'errors'
				]
			) &&
			is_array(
				$decoded[
					'data'
				][
					'errors'
				]
			)
		) {
			return $decoded[
				'data'
			][
				'errors'
			];
		}

		return array();
	}

	/**
	 * Build readable API error message.
	 *
	 * @param mixed $decoded Decoded response.
	 * @param int   $status  HTTP/API status.
	 *
	 * @return string
	 */
	private function get_message(
		$decoded,
		int $status
	): string {

		$messages =
			$this->collect_messages(
				$decoded
			);

		if ( ! empty( $messages ) ) {
			return implode(
				' ',
				array_values(
					array_unique(
						$messages
					)
				)
			);
		}

		return sprintf(
			/* translators: %d: HTTP status. */
			__(
				'Steadfast returned HTTP %d.',
				'eilmo-checkout-flow'
			),
			$status
		);
	}

	/**
	 * Collect readable API messages recursively.
	 *
	 * @param mixed $value Value.
	 *
	 * @return array<int,string>
	 */
	private function collect_messages(
		$value
	): array {

		if ( ! is_array( $value ) ) {
			return array();
		}

		$messages =
			array();

		foreach (
			array(
				'message',
				'error',
			) as
				$key
		) {
			if (
				isset(
					$value[
						$key
					]
				) &&
				is_scalar(
					$value[
						$key
					]
				)
			) {
				$message =
					sanitize_text_field(
						(string) $value[
							$key
						]
					);

				if ( '' !== $message ) {
					$messages[] =
						$message;
				}
			}
		}

		if (
			isset(
				$value[
					'errors'
				]
			) &&
			is_array(
				$value[
					'errors'
				]
			)
		) {
			foreach (
				$value[
					'errors'
				] as
					$field_errors
			) {
				if (
					! is_array(
						$field_errors
					)
				) {
					continue;
				}

				foreach (
					$field_errors as
						$message
				) {
					if (
						! is_scalar(
							$message
						)
					) {
						continue;
					}

					$message =
						sanitize_text_field(
							(string) $message
						);

					if ( '' !== $message ) {
						$messages[] =
							$message;
					}
				}
			}
		}

		foreach (
			$value as
				$child
		) {
			if (
				is_array(
					$child
				)
			) {
				$messages =
					array_merge(
						$messages,
						$this->collect_messages(
							$child
						)
					);
			}
		}

		return array_values(
			array_filter(
				$messages
			)
		);
	}

	/**
	 * Sanitize Bangladesh phone number.
	 *
	 * @param mixed $value Phone.
	 *
	 * @return string
	 */
	private function sanitize_phone(
		$value
	): string {

		$phone =
			preg_replace(
				'/\D+/',
				'',
				(string) $value
			);

		if ( ! is_string( $phone ) ) {
			return '';
		}

		/*
		 * +8801XXXXXXXXX / 8801XXXXXXXXX
		 * becomes 01XXXXXXXXX.
		 */
		if (
			13 === strlen( $phone ) &&
			'880' ===
				substr(
					$phone,
					0,
					3
				)
		) {
			$phone =
				'0' .
				substr(
					$phone,
					3
				);
		}

		return $phone;
	}

	/**
	 * Sanitize COD amount.
	 *
	 * @param mixed $value Amount.
	 *
	 * @return float
	 */
	private function sanitize_amount(
		$value
	): float {

		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		return round(
			max(
				0,
				(float) $value
			),
			2
		);
	}

	/**
	 * Limit text safely.
	 *
	 * @param string $value  Text.
	 * @param int    $length Maximum length.
	 *
	 * @return string
	 */
	private function limit_text(
		string $value,
		int $length
	): string {

		$value =
			trim(
				$value
			);

		if (
			function_exists(
				'mb_substr'
			)
		) {
			return mb_substr(
				$value,
				0,
				$length
			);
		}

		return substr(
			$value,
			0,
			$length
		);
	}
}
