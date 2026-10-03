<?php
namespace EilmoCheckout\Payment\Gateways;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

final class GatewaySettings {
	public const BKASH = 'eilmo_bkash';
	public const NAGAD = 'eilmo_nagad';
	private const MIGRATION_OPTION = 'eilmo_cf_gateway_architecture_2100_migrated';

	public static function brands(): array {
		return array( self::BKASH => 'bKash', self::NAGAD => 'Nagad' );
	}
	public static function method_key( string $gateway_id ): string {
		return self::BKASH === $gateway_id ? 'bkash' : ( self::NAGAD === $gateway_id ? 'nagad' : '' );
	}
	public static function gateway_id( string $method_key ): string {
		$method_key = sanitize_key( $method_key );
		return 'bkash' === $method_key ? self::BKASH : ( 'nagad' === $method_key ? self::NAGAD : '' );
	}
	public static function is_eilmo_gateway( string $gateway_id ): bool {
		return in_array( sanitize_key( $gateway_id ), array( self::BKASH, self::NAGAD ), true );
	}
	public static function defaults( string $gateway_id ): array {
		$brand = self::brands()[ $gateway_id ] ?? 'Mobile Payment';
		$key = self::method_key( $gateway_id );
		return array(
			'enabled' => 'yes', 'title' => $brand,
			'description' => sprintf( __( 'Pay securely using your %s account.', 'eilmo-checkout-flow' ), $brand ),
			'instructions' => __( 'Send the exact payable amount, then provide the Transaction ID or upload the payment screenshot.', 'eilmo-checkout-flow' ),
			'account_label' => sprintf( __( '%s Number', 'eilmo-checkout-flow' ), $brand ),
			'account_number' => '', 'transaction_id_required' => 'yes',
			'transaction_id_label' => __( 'Transaction ID', 'eilmo-checkout-flow' ),
			'transaction_id_placeholder' => sprintf( __( 'Enter %s Transaction ID', 'eilmo-checkout-flow' ), $brand ),
			'payment_proof_enabled' => 'yes', 'payment_proof_label' => __( 'Payment Screenshot', 'eilmo-checkout-flow' ),
			'payment_proof_help' => __( 'Provide either the Transaction ID or a JPG, PNG or WebP payment screenshot.', 'eilmo-checkout-flow' ),
			'payment_proof_max_mb' => 5,
			'icon_url' => '' !== $key ? EILMO_CF_ASSETS_URL . 'images/' . $key . '.png' : '',
		);
	}
	public static function get( string $gateway_id ): array {
		if ( ! self::is_eilmo_gateway( $gateway_id ) ) return array();
		$stored = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		$settings = array_replace( self::defaults( $gateway_id ), is_array( $stored ) ? $stored : array() );
		$settings['icon_url'] = self::usable_icon_url( $gateway_id, (string) ( $settings['icon_url'] ?? '' ) );
		return $settings;
	}
	/** Replace the historically configured unavailable bKash SVG with our local PNG. */
	public static function usable_icon_url( string $gateway_id, string $url ): string {
		$url = trim( $url );
		if ( '' === $url || false !== strpos( $url, '<' ) || false !== strpos( $url, '>' ) ||
			( self::BKASH === $gateway_id && false !== stripos( $url, 'upload.wikimedia.org/wikipedia/en/6/68/Bkash_logo.svg' ) ) ) {
			return (string) ( self::defaults( $gateway_id )['icon_url'] ?? '' );
		}
		return $url;
	}
	public static function enabled( string $gateway_id ): bool {
		$s = self::get( $gateway_id ); return 'yes' === (string) ( $s['enabled'] ?? 'no' );
	}
	public static function maybe_migrate_legacy(): void {
		if ( 'yes' === get_option( self::MIGRATION_OPTION, 'no' ) ) return;
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$payment = is_array( $stored ) && isset( $stored['payment_methods'] ) && is_array( $stored['payment_methods'] ) ? $stored['payment_methods'] : array();
		$legacy = isset( $payment['custom_methods'] ) && is_array( $payment['custom_methods'] ) ? $payment['custom_methods'] : array();
		foreach ( array( self::BKASH, self::NAGAD ) as $gateway_id ) {
			$key = self::method_key( $gateway_id ); $target = self::defaults( $gateway_id );
			foreach ( $legacy as $method ) {
				if ( ! is_array( $method ) || $key !== sanitize_key( (string) ( $method['id'] ?? '' ) ) ) continue;
				$map = array('title'=>'title','description'=>'description','instructions'=>'instructions','account_label'=>'account_label','account_value'=>'account_number','transaction_id_required'=>'transaction_id_required','transaction_id_label'=>'transaction_id_label','transaction_id_placeholder'=>'transaction_id_placeholder','payment_proof_enabled'=>'payment_proof_enabled','payment_proof_label'=>'payment_proof_label','payment_proof_help'=>'payment_proof_help','payment_proof_max_mb'=>'payment_proof_max_mb','icon_url'=>'icon_url');
				foreach ( $map as $old => $new ) if ( array_key_exists( $old, $method ) ) $target[$new] = $method[$old];
				break;
			}
			$target['enabled'] = 'yes';
			$existing = get_option( 'woocommerce_' . $gateway_id . '_settings', false );
			update_option( 'woocommerce_' . $gateway_id . '_settings', false === $existing ? $target : array_replace( $target, is_array($existing)?$existing:array() ), false );
		}
		update_option( self::MIGRATION_OPTION, 'yes', false );
	}
}
