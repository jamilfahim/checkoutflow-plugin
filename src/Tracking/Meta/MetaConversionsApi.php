<?php
/**
 * Meta Conversions API client.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use EilmoCheckout\Admin\MetaTrackingSettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends server events to Meta Conversions API.
 */
final class MetaConversionsApi {

	/**
	 * Current Meta Graph API version used by this plugin build.
	 */
	private const API_VERSION =
		'v26.0';

	/**
	 * Request timeout.
	 */
	private const REQUEST_TIMEOUT =
		15;

	/**
	 * Send one server event.
	 *
	 * @param array<string,mixed> $event Event.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function send_event(
		array $event
	) {

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			)
		) {
			return new WP_Error(
				'meta_capi_disabled',
				__(
					'Meta Conversions API is disabled.',
					'eilmo-checkout-flow'
				)
			);
		}

		$settings =
			MetaTrackingSettings::get_settings();

		$connection =
			isset( $settings['connection'] ) &&
			is_array( $settings['connection'] )
				? $settings['connection']
				: array();

		$pixel_id =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$connection['pixel_id'] ??
						''
				)
			);

		$access_token =
			trim(
				(string) (
					$connection['access_token'] ??
						''
				)
			);

		if (
			! is_string( $pixel_id ) ||
			'' === $pixel_id
		) {
			return new WP_Error(
				'meta_pixel_id_missing',
				__(
					'Meta Pixel / Dataset ID is not configured.',
					'eilmo-checkout-flow'
				)
			);
		}

		if ( '' === $access_token ) {
			return new WP_Error(
				'meta_access_token_missing',
				__(
					'Meta Conversions API access token is not configured.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			empty( $event['event_name'] ) ||
			empty( $event['event_time'] ) ||
			empty( $event['action_source'] ) ||
			empty( $event['user_data'] )
		) {
			return new WP_Error(
				'meta_invalid_event',
				__(
					'Meta event payload is incomplete.',
					'eilmo-checkout-flow'
				)
			);
		}

		$api_version =
			$this->get_api_version();

		$endpoint =
			sprintf(
				'https://graph.facebook.com/%1$s/%2$s/events',
				rawurlencode(
					$api_version
				),
				rawurlencode(
					$pixel_id
				)
			);

		$data_json =
			wp_json_encode(
				array(
					$event,
				),
				JSON_UNESCAPED_SLASHES
			);

		if ( ! is_string( $data_json ) ) {
			return new WP_Error(
				'meta_event_encode_failed',
				__(
					'Meta event payload could not be encoded.',
					'eilmo-checkout-flow'
				)
			);
		}

		$body =
			array(
				'data' =>
					$data_json,

				'access_token' =>
					$access_token,
			);

		$test_event_code =
			sanitize_text_field(
				(string) (
					$connection['test_event_code'] ??
						''
				)
			);

		if ( '' !== $test_event_code ) {
			$body['test_event_code'] =
				$test_event_code;
		}

		$response =
			wp_safe_remote_post(
				$endpoint,
				array(
					'timeout' =>
						self::REQUEST_TIMEOUT,

					'redirection' =>
						2,

					'headers' =>
						array(
							'Accept' =>
								'application/json',
						),

					'body' =>
						$body,
				)
			);

		if ( is_wp_error( $response ) ) {

			$this->debug_log(
				'Meta CAPI transport error.',
				array(
					'error_code' =>
						$response->get_error_code(),

					'message' =>
						$response->get_error_message(),
				)
			);

			return new WP_Error(
				'meta_capi_transport_error',
				$response->get_error_message()
			);
		}

		$status_code =
			(int) wp_remote_retrieve_response_code(
				$response
			);

		$response_body =
			(string) wp_remote_retrieve_body(
				$response
			);

		$decoded =
			json_decode(
				$response_body,
				true
			);

		if ( ! is_array( $decoded ) ) {
			$decoded =
				array();
		}

		if (
			$status_code < 200 ||
			$status_code >= 300 ||
			isset( $decoded['error'] )
		) {
			$message =
				$this->get_error_message(
					$decoded,
					$status_code
				);

			$this->debug_log(
				'Meta CAPI rejected event.',
				array(
					'status_code' =>
						$status_code,

					'message' =>
						$message,

					'event_name' =>
						sanitize_text_field(
							(string) (
								$event['event_name'] ??
									''
							)
						),

					'event_id' =>
						sanitize_text_field(
							(string) (
								$event['event_id'] ??
									''
							)
						),
				)
			);

			return new WP_Error(
				'meta_capi_api_error',
				$message,
				array(
					'status_code' =>
						$status_code,
				)
			);
		}

		$this->debug_log(
			'Meta CAPI event accepted.',
			array(
				'status_code' =>
					$status_code,

				'events_received' =>
					absint(
						$decoded['events_received'] ??
							0
					),

				'fbtrace_id' =>
					sanitize_text_field(
						(string) (
							$decoded['fbtrace_id'] ??
								''
						)
					),

				'event_name' =>
					sanitize_text_field(
						(string) (
							$event['event_name'] ??
								''
						)
					),

				'event_id' =>
					sanitize_text_field(
						(string) (
							$event['event_id'] ??
								''
						)
					),
			)
		);

		return $decoded;
	}

	/**
	 * Get API version.
	 *
	 * @return string
	 */
	private function get_api_version(): string {

		$version =
			(string) apply_filters(
				'eilmo_cf/meta_tracking/api_version',
				self::API_VERSION
			);

		$version =
			trim(
				$version
			);

		if (
			1 !==
				preg_match(
					'/^v\d+\.\d+$/',
					$version
				)
		) {
			return self::API_VERSION;
		}

		return $version;
	}

	/**
	 * Get safe API error message.
	 *
	 * @param array<string,mixed> $decoded     Response.
	 * @param int                 $status_code HTTP status.
	 *
	 * @return string
	 */
	private function get_error_message(
		array $decoded,
		int $status_code
	): string {

		$error =
			isset( $decoded['error'] ) &&
			is_array( $decoded['error'] )
				? $decoded['error']
				: array();

		$message =
			sanitize_text_field(
				(string) (
					$error['message'] ??
						''
				)
			);

		if ( '' !== $message ) {
			return $message;
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__(
				'Meta Conversions API returned HTTP %d.',
				'eilmo-checkout-flow'
			),
			$status_code
		);
	}

	/**
	 * Write sanitized debug information.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 *
	 * @return void
	 */
	private function debug_log(
		string $message,
		array $context = array()
	): void {

		$settings =
			MetaTrackingSettings::get_settings();

		$advanced =
			isset( $settings['advanced'] ) &&
			is_array( $settings['advanced'] )
				? $settings['advanced']
				: array();

		if (
			'yes' !==
				(
					$advanced['debug_mode'] ??
						'no'
				)
		) {
			return;
		}

		if (
			function_exists(
				'wc_get_logger'
			)
		) {
			wc_get_logger()->debug(
				$message .
					' ' .
					wp_json_encode(
						$context
					),
				array(
					'source' =>
						'eilmo-meta-tracking',
				)
			);
		}
	}
}
