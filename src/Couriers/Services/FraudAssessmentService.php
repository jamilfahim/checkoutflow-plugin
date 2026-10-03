<?php
/**
 * Local-first live fraud orchestration.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use EilmoCheckout\Admin\CourierSettings;

defined( 'ABSPATH' ) || exit;

/** Chooses local, cached courier, live courier or safe fallback data. */
final class FraudAssessmentService {

	/**
	 * Assess one normalized customer phone.
	 *
	 * @param string $phone Customer phone.
	 * @return array{snapshot:array<string,mixed>,evaluation:array<string,mixed>}
	 */
	public function assess( string $phone ): array {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();
		$strategy = sanitize_key( (string) ( $config['data_strategy'] ?? 'local_first' ) );
		if ( ! in_array( $strategy, array( 'local_first', 'courier_first', 'combined', 'local_only', 'courier_only' ), true ) ) {
			$strategy = 'local_first';
		}

		$local = ( new LocalCustomerHistoryService() )->lookup( $phone );
		$local_total = max( 0, absint( $local['total'] ?? 0 ) );
		$minimum_local = max( 1, absint( $config['minimum_local_orders'] ?? 3 ) );
		$risk = new FraudRiskService();

		if ( 'local_only' === $strategy || ( 'local_first' === $strategy && $local_total >= $minimum_local ) ) {
			$snapshot = $this->local_snapshot( $phone, $local, 'local_history' );
			return $this->result( $snapshot, $risk->evaluate( $snapshot ), 'local_history', $local );
		}

		$courier_service = new CourierSuccessService();
		$courier = $courier_service->lookup_live_phone( $phone );
		if ( is_wp_error( $courier ) ) {
			if ( 'yes' === ( $config['use_stale_on_failure'] ?? 'yes' ) ) {
				$stale = $courier_service->get_live_cached_snapshot( $phone, true );
				if ( ! empty( $stale ) ) {
					$stale['cache_status'] = 'stale';
					$stale['provider_error'] = sanitize_key( $courier->get_error_code() );
					$stale = $this->maybe_add_local( $stale, $local, $strategy );
					return $this->result( $stale, $risk->evaluate( $stale ), 'stale_cache', $local );
				}
			}

			if ( $local_total > 0 && 'courier_only' !== $strategy ) {
				$snapshot = $this->local_snapshot( $phone, $local, 'local_fallback' );
				$snapshot['provider_error'] = sanitize_key( $courier->get_error_code() );
				return $this->result( $snapshot, $risk->evaluate( $snapshot ), 'local_fallback', $local );
			}

			$evaluation = $risk->evaluate_failure( $courier->get_error_code() );
			$snapshot = array(
				'version' => 3,
				'source' => 'fraud_fallback',
				'decision_source' => 'api_unavailable',
				'phone' => $phone,
				'checked_at' => time(),
				'status' => 'error',
				'error_code' => sanitize_key( $courier->get_error_code() ),
				'cache_status' => 'unavailable',
				'stats' => array(),
			);

			return $this->result( $snapshot, $evaluation, 'api_unavailable', $local );
		}

		$courier = $this->maybe_add_local( $courier, $local, $strategy );
		$cache_status = sanitize_key( (string) ( $courier['cache_status'] ?? '' ) );
		$source = 'fresh' === $cache_status
			? 'courier_cache'
			: ( 'stale' === $cache_status ? 'stale_cache' : 'courier_live' );
		if ( isset( $courier['stats']['website'] ) ) {
			$source = 'combined';
		}
		$courier['decision_source'] = $source;

		return $this->result( $courier, $risk->evaluate( $courier ), $source, $local );
	}

	/** @return array<string,mixed> */
	private function local_snapshot( string $phone, array $local, string $source ): array {

		return array(
			'version' => 3,
			'source' => 'woocommerce_local',
			'decision_source' => $source,
			'phone' => $phone,
			'checked_at' => time(),
			'status' => 'success',
			'cache_status' => 'local',
			'stats' => array( 'website' => $local ),
		);
	}

	/** @return array<string,mixed> */
	private function maybe_add_local( array $snapshot, array $local, string $strategy ): array {

		if ( in_array( $strategy, array( 'local_first', 'combined' ), true ) && absint( $local['total'] ?? 0 ) > 0 ) {
			$stats = isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] ) ? $snapshot['stats'] : array();
			$stats['website'] = $local;
			$snapshot['stats'] = $stats;
		}

		return $snapshot;
	}

	/**
	 * @return array{snapshot:array<string,mixed>,evaluation:array<string,mixed>}
	 */
	private function result( array $snapshot, array $evaluation, string $source, array $local ): array {

		$evaluation['decision_source'] = sanitize_key( $source );
		$evaluation['local_total_orders'] = max( 0, absint( $local['total'] ?? 0 ) );
		$evaluation['local_success_orders'] = max( 0, absint( $local['success'] ?? 0 ) );
		$evaluation['local_cancel_orders'] = max( 0, absint( $local['cancel'] ?? 0 ) );
		$evaluation['local_success_rate'] = max( 0, min( 100, (float) ( $local['ratio'] ?? 0 ) ) );

		return array(
			'snapshot' => $snapshot,
			'evaluation' => $evaluation,
		);
	}
}
