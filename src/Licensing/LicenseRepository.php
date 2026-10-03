<?php
/**
 * Local license state repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Licensing;

defined( 'ABSPATH' ) || exit;

final class LicenseRepository {

	public const OPTION_NAME = 'eilmo_cf_license_state';

	public static function defaults(): array {
		return array(
			'server_url'          => '',
			'license_key'         => '',
			'instance_id'         => '',
			'activation_token'    => '',
			'activation_id'       => '',
			'status'              => 'inactive',
			'plan'                => '',
			'expires_at'          => '',
			'activations_used'    => 0,
			'activations_allowed' => null,
			'last_checked_at'     => '',
			'last_success_at'     => '',
			'last_error'          => '',
			'integrity'           => '',
		);
	}

	public function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		$state = array_replace( self::defaults(), is_array( $stored ) ? $stored : array() );

		if (
			in_array( (string) $state['status'], array( 'active', 'connection_error' ), true ) &&
			! $this->has_valid_integrity( $state )
		) {
			$state['status'] = 'tampered';
			$state['last_error'] = __( 'The locally stored license proof is invalid. Refresh or activate the license again.', 'eilmo-checkout-flow' );
		}

		return $state;
	}

	public function save( array $state ): void {
		$current = get_option( self::OPTION_NAME, array() );
		$current = array_replace( self::defaults(), is_array( $current ) ? $current : array() );
		$state = array_replace( $current, $state );
		$clean = array(
			'server_url'          => esc_url_raw( (string) $state['server_url'] ),
			'license_key'         => sanitize_text_field( (string) $state['license_key'] ),
			'instance_id'         => sanitize_text_field( (string) $state['instance_id'] ),
			'activation_token'    => sanitize_text_field( (string) $state['activation_token'] ),
			'activation_id'       => sanitize_text_field( (string) $state['activation_id'] ),
			'status'              => sanitize_key( (string) $state['status'] ),
			'plan'                => sanitize_text_field( (string) $state['plan'] ),
			'expires_at'          => sanitize_text_field( (string) $state['expires_at'] ),
			'activations_used'    => absint( $state['activations_used'] ),
			'activations_allowed' => null === $state['activations_allowed'] ? null : absint( $state['activations_allowed'] ),
			'last_checked_at'     => sanitize_text_field( (string) $state['last_checked_at'] ),
			'last_success_at'     => sanitize_text_field( (string) $state['last_success_at'] ),
			'last_error'          => sanitize_text_field( (string) $state['last_error'] ),
		);
		$clean['integrity'] = $this->integrity_hash( $clean );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $clean, '', false );
			return;
		}
		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Verify that entitlement fields were written by this installation.
	 *
	 * This is a tamper signal, not a substitute for the remote license server.
	 */
	public function has_valid_integrity( array $state ): bool {
		$stored = isset( $state['integrity'] ) ? (string) $state['integrity'] : '';
		return '' !== $stored && hash_equals( $this->integrity_hash( $state ), $stored );
	}

	private function integrity_hash( array $state ): string {
		$fields = array(
			'server_url',
			'license_key',
			'instance_id',
			'activation_token',
			'activation_id',
			'status',
			'plan',
			'expires_at',
			'activations_used',
			'activations_allowed',
			'last_success_at',
		);
		$values = array();
		foreach ( $fields as $field ) {
			$values[ $field ] = $state[ $field ] ?? null;
		}

		return hash_hmac( 'sha256', wp_json_encode( $values ), wp_salt( 'auth' ) );
	}

	public function ensure_instance_id(): string {
		$state = $this->get();
		if ( ! empty( $state['instance_id'] ) ) {
			return (string) $state['instance_id'];
		}

		$state['instance_id'] = wp_generate_uuid4();
		$this->save( $state );
		return (string) $state['instance_id'];
	}

	public function clear_activation(): void {
		$this->save( array(
			'license_key'         => '',
			'activation_token'    => '',
			'activation_id'       => '',
			'status'              => 'inactive',
			'plan'                => '',
			'expires_at'          => '',
			'activations_used'    => 0,
			'activations_allowed' => null,
			'last_checked_at'     => current_time( 'mysql', true ),
			'last_success_at'     => '',
			'last_error'          => '',
		) );
	}

	public static function masked_key( string $key ): string {
		$length = strlen( $key );
		if ( $length < 9 ) {
			return $key ? str_repeat( '•', $length ) : '';
		}
		return substr( $key, 0, 4 ) . str_repeat( '•', max( 4, $length - 8 ) ) . substr( $key, -4 );
	}
}
