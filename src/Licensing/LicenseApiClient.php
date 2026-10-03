<?php
/**
 * License server HTTP client.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Licensing;

defined( 'ABSPATH' ) || exit;

final class LicenseApiClient {

	private $repository;

	public function __construct( LicenseRepository $repository ) {
		$this->repository = $repository;
	}

	public function activate( string $key ): array {
		return $this->request( 'activate', array( 'license_key' => $key ), false );
	}

	public function validate(): array {
		return $this->request( 'validate' );
	}

	public function deactivate(): array {
		return $this->request( 'deactivate' );
	}

	private function request( string $operation, array $extra = array(), bool $authenticated = true ): array {
		$state = $this->repository->get();
		$server_url = $this->server_url( $state );
		if ( ! $server_url ) {
			return array( 'success' => false, 'message' => __( 'Set the License Server URL before activating.', 'eilmo-checkout-flow' ) );
		}

		$payload = array_merge( array(
			'license_key'   => (string) $state['license_key'],
			'product_slug'  => 'eilmo-checkout-flow',
			'instance_id'   => $this->repository->ensure_instance_id(),
			'site_url'      => home_url( '/' ),
			'plugin_version'=> EILMO_CF_VERSION,
		), $extra );

		if ( $authenticated ) {
			$payload['activation_token'] = (string) $state['activation_token'];
		}

		$response = wp_safe_remote_post(
			trailingslashit( $server_url ) . 'wp-json/eilmo/v2/licenses/' . $operation,
			array(
				'timeout' => 15,
				'redirection' => 2,
				'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
				'body' => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message(), 'temporary' => true );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array( 'success' => false, 'message' => __( 'The license server returned an invalid response.', 'eilmo-checkout-flow' ), 'temporary' => true );
		}

		if ( $code < 200 || $code >= 300 || empty( $body['success'] ) ) {
			return array(
				'success' => false,
				'message' => sanitize_text_field( (string) ( $body['message'] ?? __( 'License request failed.', 'eilmo-checkout-flow' ) ) ),
				'code' => sanitize_key( (string) ( $body['code'] ?? '' ) ),
				'temporary' => $code >= 500 || 429 === $code,
			);
		}

		return array(
			'success' => true,
			'message' => sanitize_text_field( (string) ( $body['message'] ?? '' ) ),
			'data' => isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array(),
		);
	}

	private function server_url( array $state ): string {
		if ( defined( 'EILMO_CF_LICENSE_SERVER_URL' ) && EILMO_CF_LICENSE_SERVER_URL ) {
			return untrailingslashit( esc_url_raw( EILMO_CF_LICENSE_SERVER_URL ) );
		}

		return untrailingslashit( esc_url_raw( (string) $state['server_url'] ) );
	}
}
