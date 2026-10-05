<?php
/** Steadfast's ratio-based fraud-check profile. */
namespace EilmoCheckout\Couriers\Services;

defined( 'ABSPATH' ) || exit;

final class SteadfastProfileService {
	public const SCHEMA_VERSION = 2;

	/** Normalize the score endpoint without inventing parcel counts. */
	public static function normalize( array $response ): array {
		$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		$band = sanitize_key( (string) ( $data['volume_band'] ?? 'none' ) );
		if ( ! in_array( $band, array( 'none', 'low', 'medium', 'high', 'very_high' ), true ) ) {
			$band = 'none';
		}
		$categories = array();
		foreach ( (array) ( $data['fraud_categories'] ?? array() ) as $key => $value ) {
			if ( count( $categories ) >= 20 ) {
				break;
			}
			if ( is_string( $key ) && is_numeric( $value ) ) {
				$categories[ sanitize_key( $key ) ] = max( 0, absint( $value ) );
			} elseif ( is_string( $value ) ) {
				$categories[ sanitize_key( $value ) ] = 1;
			}
		}
		$keywords = array();
		foreach ( (array) ( $data['fraud_keywords'] ?? array() ) as $value ) {
			if ( count( $keywords ) >= 10 ) {
				break;
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$keywords[] = substr( sanitize_text_field( $value ), 0, 80 );
			}
		}
		$fraud_details = array();
		foreach ( (array) ( $data['frauds'] ?? ( $data['fraud_details'] ?? array() ) ) as $fraud ) {
			if ( count( $fraud_details ) >= 5 ) {
				break;
			}
			if ( is_string( $fraud ) ) {
				$detail = substr( sanitize_text_field( $fraud ), 0, 160 );
			} elseif ( is_array( $fraud ) ) {
				$parts = array();
				foreach ( array( 'category', 'reason', 'description', 'keyword' ) as $field ) {
					if ( isset( $fraud[ $field ] ) && is_scalar( $fraud[ $field ] ) ) {
						$parts[] = substr( sanitize_text_field( (string) $fraud[ $field ] ), 0, 80 );
					}
				}
				$detail = implode( ' · ', $parts );
			} else {
				continue;
			}
			if ( '' !== $detail ) {
				$fraud_details[] = $detail;
			}
		}
		$range = isset( $data['volume_range'] ) && is_scalar( $data['volume_range'] )
			? trim( (string) $data['volume_range'] ) : '';
		if ( ! preg_match( '/^\d+(?:\s*(?:-|–)\s*\d+|\+)?$/u', $range ) ) {
			$range = '';
		}
		$delivery = max( 0, min( 100, (float) ( $data['delivery_ratio'] ?? 0 ) ) );
		$cancel = max( 0, min( 100, (float) ( $data['cancellation_ratio'] ?? 0 ) ) );
		$reports = $data['fraud_reports'] ?? ( $data['total_reports'] ?? null );
		$reports = null !== $reports && is_numeric( $reports ) ? max( 0, absint( $reports ) ) : null;
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'label' => 'Steadfast',
			'provider' => 'steadfast',
			'available' => 'none' !== $band,
			'state' => 'none' === $band ? 'no_history' : 'available',
			'ratio' => $delivery,
			'delivery_ratio' => $delivery,
			'cancellation_ratio' => $cancel,
			'volume_band' => $band,
			'volume_range' => $range,
			'delivered_count' => self::optional_count( $data['delivered_count'] ?? null ),
			'cancelled_count' => self::optional_count( $data['cancelled_count'] ?? null ),
			'fraud_reports' => $reports,
			'reported_by_you' => isset( $data['reported_by_you'] ) && is_bool( $data['reported_by_you'] ) ? $data['reported_by_you'] : null,
			'fraud_categories' => $categories,
			'fraud_keywords' => $keywords,
			'fraud_details' => $fraud_details,
		);
	}

	/** Counts are optional in Steadfast's response; zero and unavailable differ. */
	private static function optional_count( $value ): ?int {
		return null !== $value && is_numeric( $value ) && (float) $value >= 0 && floor( (float) $value ) === (float) $value
			? absint( $value ) : null;
	}

	/** The published range gives a lower bound, never an exact parcel total. */
	public static function volume_minimum( array $profile ): ?int {
		$range = (string) ( $profile['volume_range'] ?? ( $profile['parcel_range'] ?? '' ) );
		if ( preg_match( '/^(\d+)(?:\s*(?:-|–)\s*\d+|\+)?$/u', $range, $matches ) ) {
			return absint( $matches[1] );
		}
		$delivered = self::optional_count( $profile['delivered_count'] ?? null );
		$cancelled = self::optional_count( $profile['cancelled_count'] ?? null );
		return null !== $delivered && null !== $cancelled ? $delivered + $cancelled : null;
	}
}
