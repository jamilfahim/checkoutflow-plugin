<?php
/**
 * Pathao Courier client.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Providers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Pathao authentication, locations and order creation.
 *
 * The implementation follows the supplied Pathao WooCommerce plugin:
 * - Live: https://api-hermes.pathao.com
 * - Staging: https://courier-api-sandbox.pathao.com
 * - Login: /aladdin/api/v1/external/login
 * - Orders: /aladdin/api/v1/orders
 */
final class PathaoClient {

	/**
	 * Live API base URL.
	 */
	private const LIVE_BASE_URL =
		'https://api-hermes.pathao.com';

	/**
	 * Staging API base URL.
	 */
	private const STAGING_BASE_URL =
		'https://courier-api-sandbox.pathao.com';

	/**
	 * Get stores.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_stores(
		array $config
	) {

		$result =
			$this->authorized_request(
				$config,
				'GET',
				'/aladdin/api/v1/stores'
			);

		return is_wp_error( $result )
			? $result
			: $this->extract_list(
				$result,
				'store'
			);
	}

	/**
	 * Get Bangladesh city list.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_cities(
		array $config
	) {

		$result =
			$this->authorized_request(
				$config,
				'GET',
				'/aladdin/api/v1/countries/1/city-list'
			);

		return is_wp_error( $result )
			? $result
			: $this->extract_list(
				$result,
				'city'
			);
	}

	/**
	 * Get zones for one city.
	 *
	 * @param array<string,mixed> $config  Provider config.
	 * @param int                 $city_id City ID.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_zones(
		array $config,
		int $city_id
	) {

		if ( $city_id <= 0 ) {
			return new WP_Error(
				'pathao_city_required',
				__(
					'Pathao city is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		$result =
			$this->authorized_request(
				$config,
				'GET',
				'/aladdin/api/v1/cities/' .
					$city_id .
					'/zone-list'
			);

		return is_wp_error( $result )
			? $result
			: $this->extract_list(
				$result,
				'zone'
			);
	}

	/**
	 * Get areas for one zone.
	 *
	 * @param array<string,mixed> $config  Provider config.
	 * @param int                 $zone_id Zone ID.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_areas(
		array $config,
		int $zone_id
	) {

		if ( $zone_id <= 0 ) {
			return new WP_Error(
				'pathao_zone_required',
				__(
					'Pathao zone is required.',
					'eilmo-checkout-flow'
				)
			);
		}

		$result =
			$this->authorized_request(
				$config,
				'GET',
				'/aladdin/api/v1/zones/' .
					$zone_id .
					'/area-list'
			);

		return is_wp_error( $result )
			? $result
			: $this->extract_list(
				$result,
				'area'
			);
	}

	/**
	 * Create one Pathao order.
	 *
	 * @param array<string,mixed> $config  Provider config.
	 * @param array<string,mixed> $payload Order payload.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_order(
		array $config,
		array $payload
	) {

		$result =
			$this->authorized_request(
				$config,
				'POST',
				'/aladdin/api/v1/orders',
				$payload
			);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data =
			$this->extract_data(
				$result
			);

		$reference =
			sanitize_text_field(
				(string) (
					$data['consignment_id'] ??
						$this->find_reference(
							$result
						)
				)
			);

		if ( '' === $reference ) {
			return new WP_Error(
				'pathao_invalid_response',
				__(
					'Pathao did not return a consignment reference.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'provider' =>
				'pathao',

			'reference' =>
				$reference,

			/*
			 * Pathao's official WooCommerce plugin stores
			 * "pending" immediately after successful creation.
			 * Webhooks will replace this with the real lifecycle
			 * status as the parcel moves through Pathao.
			 */
			'delivery_status' =>
				'pending',

			'tracking_code' =>
				sanitize_text_field(
					(string) (
						$data['tracking_code'] ??
							$reference
					)
				),

			'delivery_fee' =>
				isset(
					$data['delivery_fee']
				) &&
				is_numeric(
					$data['delivery_fee']
				)
					? (float) $data['delivery_fee']
					: 0.0,

			'response' =>
				$result,
		);
	}

	/**
	 * Perform one authenticated Pathao request.
	 *
	 * A 401 response automatically clears the cached token
	 * and retries the request once with a fresh token.
	 *
	 * @param array<string,mixed> $config       Provider config.
	 * @param string              $method       HTTP method.
	 * @param string              $path         API path.
	 * @param array<string,mixed> $payload      Request payload.
	 * @param bool                $retry_on_401 Retry once on 401.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function authorized_request(
		array $config,
		string $method,
		string $path,
		array $payload = array(),
		bool $retry_on_401 = true
	) {

		$token =
			$this->get_access_token(
				$config
			);

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$method =
			strtoupper(
				$method
			);

		$args =
			array(
				'method' =>
					$method,

				'timeout' =>
					25,

				'redirection' =>
					2,

				'headers' =>
					array(
						'Accept' =>
							'application/json',

						'Content-Type' =>
							'application/json',

						'Authorization' =>
							'Bearer ' .
								$token,

						'source' =>
							'woocommerce',
					),
			);

		if ( 'GET' !== $method ) {
			$args['body'] =
				wp_json_encode(
					$payload
				);

			$args['data_format'] =
				'body';
		}

		$response =
			wp_safe_remote_request(
				$this->get_base_url(
					$config
				) .
					$path,
				$args
			);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status =
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

		if (
			401 === $status &&
			$retry_on_401
		) {
			$this->delete_cached_token(
				$config
			);

			return $this->authorized_request(
				$config,
				$method,
				$path,
				$payload,
				false
			);
		}

		if (
			$status < 200 ||
			$status >= 300 ||
			! is_array( $decoded )
		) {
			return new WP_Error(
				'pathao_api_error',
				$this->get_message(
					$decoded,
					$status
				),
				array(
					'status' =>
						$status > 0
							? $status
							: 500,

					'errors' =>
						$this->extract_errors(
							$decoded
						),
				)
			);
		}

		return $decoded;
	}

	/**
	 * Get access token, issuing a new token when needed.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token(
		array $config
	) {

		$credentials =
			$this->get_credentials(
				$config
			);

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$cache_key =
			$this->get_token_cache_key(
				$config
			);

		$cached =
			get_transient(
				$cache_key
			);

		if (
			is_array( $cached ) &&
			! empty(
				$cached['access_token']
			)
		) {
			return sanitize_text_field(
				(string)
				$cached['access_token']
			);
		}

		$response =
			wp_safe_remote_post(
				$this->get_base_url(
					$config
				) .
					'/aladdin/api/v1/external/login',
				array(
					'timeout' =>
						20,

					'redirection' =>
						2,

					'headers' =>
						array(
							'Accept' =>
								'application/json',

							'Content-Type' =>
								'application/json',
						),

					'body' =>
						wp_json_encode(
							array(
								'client_id' =>
									$credentials[
										'client_id'
									],

								'client_secret' =>
									$credentials[
										'client_secret'
									],
							)
						),

					'data_format' =>
						'body',
				)
			);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status =
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

		$auth_data =
			is_array( $decoded )
				? $this->extract_data(
					$decoded
				)
				: array();

		$access_token =
			sanitize_text_field(
				(string) (
					$decoded['access_token'] ??
						$auth_data['access_token'] ??
						''
				)
			);

		if (
			$status < 200 ||
			$status >= 300 ||
			! is_array( $decoded ) ||
			'' === $access_token
		) {
			return new WP_Error(
				'pathao_auth_failed',
				$this->get_message(
					$decoded,
					$status
				),
				array(
					'status' =>
						$status > 0
							? $status
							: 500,

					'errors' =>
						$this->extract_errors(
							$decoded
						),
				)
			);
		}

		$expires_in =
			absint(
				$decoded['expires_in'] ??
					$auth_data['expires_in'] ??
					3600
			);

		if ( $expires_in <= 0 ) {
			$expires_in =
				3600;
		}

		$ttl =
			max(
				60,
				$expires_in -
					60
			);

		set_transient(
			$cache_key,
			array(
				'access_token' =>
					$access_token,
			),
			$ttl
		);

		return $access_token;
	}

	/**
	 * Validate credentials.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return array{client_id:string,client_secret:string}|WP_Error
	 */
	private function get_credentials(
		array $config
	) {

		$client_id =
			trim(
				(string) (
					$config['client_id'] ??
						''
				)
			);

		$client_secret =
			trim(
				(string) (
					$config['client_secret'] ??
						''
				)
			);

		if (
			'' === $client_id ||
			'' === $client_secret
		) {
			return new WP_Error(
				'pathao_not_configured',
				__(
					'Pathao Client ID and Client Secret are required.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'client_id' =>
				$client_id,

			'client_secret' =>
				$client_secret,
		);
	}

	/**
	 * Get environment base URL.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return string
	 */
	private function get_base_url(
		array $config
	): string {

		$environment =
			sanitize_key(
				(string) (
					$config['environment'] ??
						'live'
				)
			);

		return 'staging' ===
			$environment
				? self::STAGING_BASE_URL
				: self::LIVE_BASE_URL;
	}

	/**
	 * Get credential-specific transient key.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return string
	 */
	private function get_token_cache_key(
		array $config
	): string {

		return 'eilmo_cf_pathao_token_' .
			md5(
				(string) (
					$config['environment'] ??
						'live'
				) .
				'|' .
				(string) (
					$config['client_id'] ??
						''
				) .
				'|' .
				(string) (
					$config['client_secret'] ??
						''
				)
			);
	}

	/**
	 * Delete cached access token.
	 *
	 * @param array<string,mixed> $config Provider config.
	 *
	 * @return void
	 */
	private function delete_cached_token(
		array $config
	): void {

		delete_transient(
			$this->get_token_cache_key(
				$config
			)
		);
	}

	/**
	 * Extract one API data object.
	 *
	 * Handles both:
	 * - data => [...]
	 * - data => [ data => [...] ]
	 *
	 * @param array<string,mixed> $response Response.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_data(
		array $response
	): array {

		$data =
			isset(
				$response['data']
			) &&
			is_array(
				$response['data']
			)
				? $response['data']
				: array();

		if (
			isset(
				$data['data']
			) &&
			is_array(
				$data['data']
			) &&
			$this->is_associative(
				$data['data']
			)
		) {
			return $data['data'];
		}

		return $data;
	}

	/**
	 * Extract and normalize Pathao list response.
	 *
	 * The Pathao API uses provider-specific field names
	 * such as store_id/store_name, city_id/city_name,
	 * zone_id/zone_name and area_id/area_name.
	 *
	 * Admin JavaScript receives a stable id/name shape.
	 *
	 * @param array<string,mixed> $response Response.
	 * @param string              $type     List type.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function extract_list(
		array $response,
		string $type
	): array {

		$list =
			$response['data']['data'] ??
				$response['data'] ??
				array();

		if ( ! is_array( $list ) ) {
			return array();
		}

		$output =
			array();

		foreach ( $list as $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$normalized =
				$this->normalize_list_item(
					$item,
					$type
				);

			if (
				$normalized['id'] <= 0 ||
				'' === $normalized['name']
			) {
				continue;
			}

			$output[] =
				$normalized;
		}

		return array_values(
			$output
		);
	}

	/**
	 * Normalize one Pathao list item.
	 *
	 * @param array<string,mixed> $item Item.
	 * @param string              $type Type.
	 *
	 * @return array<string,mixed>
	 */
	private function normalize_list_item(
		array $item,
		string $type
	): array {

		$id_keys =
			array(
				$type . '_id',
				'id',
			);

		$name_keys =
			array(
				$type . '_name',
				'name',
			);

		$id =
			0;

		foreach ( $id_keys as $key ) {
			if (
				isset( $item[ $key ] ) &&
				is_numeric( $item[ $key ] )
			) {
				$id =
					absint(
						$item[ $key ]
					);

				break;
			}
		}

		$name =
			'';

		foreach ( $name_keys as $key ) {
			if (
				isset( $item[ $key ] ) &&
				is_scalar( $item[ $key ] )
			) {
				$name =
					sanitize_text_field(
						(string) $item[ $key ]
					);

				if ( '' !== $name ) {
					break;
				}
			}
		}

		$is_default =
			! empty(
				$item['is_default']
			) ||
			! empty(
				$item['is_default_store']
			);

		return array(
			'id' =>
				$id,

			'name' =>
				$name,

			'is_default' =>
				$is_default,

			'raw' =>
				$item,
		);
	}

	/**
	 * Find a useful reference recursively.
	 *
	 * @param array<mixed> $data Response.
	 *
	 * @return string
	 */
	private function find_reference(
		array $data
	): string {

		foreach (
			array(
				'consignment_id',
				'tracking_code',
				'tracking_id',
				'order_id',
				'id',
			) as
				$candidate
		) {
			if (
				isset(
					$data[ $candidate ]
				) &&
				is_scalar(
					$data[ $candidate ]
				)
			) {
				$value =
					sanitize_text_field(
						(string)
						$data[ $candidate ]
					);

				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$found =
					$this->find_reference(
						$value
					);

				if ( '' !== $found ) {
					return $found;
				}
			}
		}

		return '';
	}

	/**
	 * Extract API validation errors.
	 *
	 * @param mixed $decoded Decoded response.
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
				$decoded['errors']
			) &&
			is_array(
				$decoded['errors']
			)
		) {
			return $decoded['errors'];
		}

		if (
			isset(
				$decoded['data']['errors']
			) &&
			is_array(
				$decoded['data']['errors']
			)
		) {
			return $decoded['data']['errors'];
		}

		return array();
	}

	/**
	 * Build readable Pathao error message.
	 *
	 * @param mixed $decoded Decoded response.
	 * @param int   $status  HTTP status.
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
				'Pathao returned HTTP %d.',
				'eilmo-checkout-flow'
			),
			$status
		);
	}

	/**
	 * Collect readable scalar messages recursively.
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
				isset( $value[ $key ] ) &&
				is_scalar(
					$value[ $key ]
				)
			) {
				$message =
					sanitize_text_field(
						(string)
						$value[ $key ]
					);

				if ( '' !== $message ) {
					$messages[] =
						$message;
				}
			}
		}

		if (
			isset(
				$value['errors']
			) &&
			is_array(
				$value['errors']
			)
		) {
			foreach (
				$value['errors'] as
					$field_errors
			) {
				if ( is_array( $field_errors ) ) {
					foreach (
						$field_errors as
							$message
					) {
						if (
							is_scalar(
								$message
							)
						) {
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
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
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
	 * Determine whether array is associative.
	 *
	 * @param array<mixed> $array Array.
	 *
	 * @return bool
	 */
	private function is_associative(
		array $array
	): bool {

		if ( empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !==
			range(
				0,
				count( $array ) - 1
			);
	}
}
