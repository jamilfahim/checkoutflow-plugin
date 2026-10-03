<?php
/**
 * License lifecycle coordinator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Licensing;

defined( 'ABSPATH' ) || exit;

final class LicenseManager {

	public const CRON_HOOK = 'eilmo_cf_daily_license_validation';
	public const GRACE_PERIOD = 259200; // 72 hours.

	private $repository;
	private $client;

	public function __construct() {
		$this->repository = new LicenseRepository();
		$this->client = new LicenseApiClient( $this->repository );
	}

	public function register(): void {
		$this->repository->ensure_instance_id();
		add_action( self::CRON_HOOK, array( $this, 'scheduled_validation' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public function activate( string $server_url, string $key ): array {
		$this->repository->save( array(
			'server_url' => untrailingslashit( esc_url_raw( $server_url ) ),
			'license_key' => sanitize_text_field( $key ),
			'last_error' => '',
		) );

		$result = $this->client->activate( sanitize_text_field( $key ) );
		return $this->consume_result( $result, true );
	}

	public function refresh(): array {
		$state = $this->repository->get();
		if ( empty( $state['license_key'] ) || empty( $state['activation_token'] ) ) {
			return array( 'success' => false, 'message' => __( 'Activate a license first.', 'eilmo-checkout-flow' ) );
		}

		return $this->consume_result( $this->client->validate() );
	}

	public function deactivate(): array {
		$state = $this->repository->get();
		if ( empty( $state['license_key'] ) || empty( $state['activation_token'] ) ) {
			$this->repository->clear_activation();
			return array( 'success' => true, 'message' => __( 'License is already inactive.', 'eilmo-checkout-flow' ) );
		}

		$result = $this->client->deactivate();
		if ( ! empty( $result['success'] ) ) {
			$this->repository->clear_activation();
		}
		return $result;
	}

	public function scheduled_validation(): void {
		$state = $this->repository->get();
		if ( ! empty( $state['license_key'] ) && ! empty( $state['activation_token'] ) ) {
			$this->refresh();
		}
	}

	public function get_state(): array {
		return $this->repository->get();
	}

	public function is_usable(): bool {
		$state = $this->repository->get();
		if ( 'active' === $state['status'] ) {
			return true;
		}

		// A temporary API outage never disables a working checkout. Cached active
		// entitlement remains usable during the grace period.
		if ( 'connection_error' === $state['status'] && ! empty( $state['last_success_at'] ) ) {
			$last_success = strtotime( (string) $state['last_success_at'] . ' UTC' );
			return $last_success && ( time() - $last_success ) <= (int) apply_filters( 'eilmo_cf/license_grace_period', self::GRACE_PERIOD );
		}

		return false;
	}

	private function consume_result( array $result, bool $activation = false ): array {
		$now = current_time( 'mysql', true );
		if ( ! empty( $result['success'] ) ) {
			$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
			$save = array(
				'status' => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
				'plan' => sanitize_text_field( (string) ( $data['plan'] ?? '' ) ),
				'expires_at' => sanitize_text_field( (string) ( $data['expires_at'] ?? '' ) ),
				'activation_id' => sanitize_text_field( (string) ( $data['activation_id'] ?? '' ) ),
				'activations_used' => absint( $data['activations_used'] ?? 0 ),
				'activations_allowed' => array_key_exists( 'activations_allowed', $data ) && null !== $data['activations_allowed'] ? absint( $data['activations_allowed'] ) : null,
				'last_checked_at' => $now,
				'last_success_at' => $now,
				'last_error' => '',
			);
			if ( $activation && ! empty( $data['activation_token'] ) ) {
				$save['activation_token'] = sanitize_text_field( (string) $data['activation_token'] );
			}
			$this->repository->save( $save );
			return $result;
		}

		$state = $this->repository->get();
		$temporary = ! empty( $result['temporary'] );
		$this->repository->save( array(
			'status' => $temporary && 'active' === $state['status'] ? 'connection_error' : sanitize_key( (string) ( $result['code'] ?? 'invalid' ) ),
			'last_checked_at' => $now,
			'last_error' => sanitize_text_field( (string) ( $result['message'] ?? __( 'License request failed.', 'eilmo-checkout-flow' ) ) ),
		) );

		return $result;
	}
}
