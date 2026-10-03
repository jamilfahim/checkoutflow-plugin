<?php
/** Steadfast's ratio-based fraud-check profile. */
namespace EilmoCheckout\Couriers\Services;

defined( 'ABSPATH' ) || exit;

final class SteadfastProfileService {
	/** Normalize the score endpoint without inventing parcel counts. */
	public static function normalize( array $response ): array {
		$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		$band = sanitize_key( (string) ( $data['volume_band'] ?? 'none' ) );
		if ( ! in_array( $band, array( 'none', 'low', 'medium', 'high', 'very_high' ), true ) ) {
			$band = 'none';
		}
		$categories = array();
		foreach ( (array) ( $data['fraud_categories'] ?? array() ) as $key => $value ) {
			if ( is_string( $key ) && is_numeric( $value ) ) {
				$categories[ sanitize_key( $key ) ] = max( 0, absint( $value ) );
			} elseif ( is_string( $value ) ) {
				$categories[ sanitize_key( $value ) ] = 1;
			}
		}
		$delivery = max( 0, min( 100, (float) ( $data['delivery_ratio'] ?? 0 ) ) );
		$cancel = max( 0, min( 100, (float) ( $data['cancellation_ratio'] ?? 0 ) ) );
		return array(
			'label' => 'Steadfast',
			'provider' => 'steadfast',
			'available' => 'none' !== $band,
			'state' => 'none' === $band ? 'no_history' : 'available',
			'ratio' => $delivery,
			'delivery_ratio' => $delivery,
			'cancellation_ratio' => $cancel,
			'volume_band' => $band,
			'total_reports' => max( 0, absint( $data['total_reports'] ?? 0 ) ),
			'fraud_categories' => $categories,
		);
	}
}
