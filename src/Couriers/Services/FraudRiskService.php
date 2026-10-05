<?php
/**
 * Explainable live courier-risk assessment.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CourierSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Converts authoritative courier history into an enforceable decision.
 */
final class FraudRiskService {

	/**
	 * Evaluate a normalized courier snapshot.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $snapshot ): array {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();
		$stats = isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] )
			? $snapshot['stats']
			: array();
		/* Steadfast's published ratio is authoritative. Its optional counts and
		 * store history must never be substituted for that ratio. */
		$steadfast = isset( $stats['steadfast'] ) && is_array( $stats['steadfast'] ) && array_key_exists( 'delivery_ratio', $stats['steadfast'] )
			? $stats['steadfast'] : null;
		if ( null !== $steadfast ) {
			return $this->evaluate_steadfast( $steadfast, $config );
		}

		$total = 0;
		$success = 0;
		$cancel = 0;

		foreach ( $stats as $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}

			$total += max( 0, absint( $provider['total'] ?? 0 ) );
			$success += max( 0, absint( $provider['success'] ?? 0 ) );
			$cancel += max( 0, absint( $provider['cancel'] ?? 0 ) );
		}

		if ( $total <= 0 && ( $success > 0 || $cancel > 0 ) ) {
			$total = $success + $cancel;
		}

		$ratio = $total > 0
			? round( max( 0, min( 100, ( $success / $total ) * 100 ) ), 2 )
			: 0.0;
		$trusted_rate = (float) ( $config['trusted_rate'] ?? 95 );
		$advance_rate = (float) ( $config['advance_rate'] ?? 90 );
		$block_rate = (float) ( $config['block_rate'] ?? 40 );
		$minimum_orders = max( 1, absint( $config['minimum_orders_for_block'] ?? 5 ) );
		$band = 'unknown';
		$action = $this->action( $config['unknown_action'] ?? 'allow', 'allow' );

		if ( $total > 0 ) {
			if ( $total >= $minimum_orders && $ratio < $block_rate ) {
				$band = 'critical';
				$action = $this->action( $config['critical_action'] ?? 'block', 'block' );
			} elseif ( $ratio < $advance_rate ) {
				$band = 'high';
				$action = $this->action( $config['high_risk_action'] ?? 'advance', 'advance' );
			} elseif ( $ratio < $trusted_rate ) {
				$band = 'review';
				$action = $this->action( $config['middle_action'] ?? 'allow', 'allow' );
			} else {
				$band = 'trusted';
				$action = 'allow';
			}
		}

		$payment_types = $this->resolve_payment_types( $band, $action, $config );
		$action = $this->effective_action( $action, $payment_types );
		$display_mode = $this->display_mode( $config['payment_display_mode'] ?? 'always' );

		/*
		 * Bayesian smoothing makes the displayed score confidence-aware:
		 * one successful parcel cannot carry the same confidence as thirty.
		 * Enforcement still follows the merchant's explicit raw-rate bands.
		 */
		$prior_orders = 5;
		$prior_success_rate = 0.9;
		$confidence_ratio = ( (float) $success + ( $prior_orders * $prior_success_rate ) ) /
			max( 1, $total + $prior_orders );
		$risk_score = (int) round( max( 0, min( 100, ( 1 - $confidence_ratio ) * 100 ) ) );

		return array(
			'version' => 1,
			'action' => $action,
			'band' => $band,
			'total_orders' => $total,
			'success_orders' => $success,
			'cancel_orders' => $cancel,
			'success_rate' => $ratio,
			'risk_score' => $risk_score,
			'confidence' => $total <= 0 ? 'none' : ( $total < 5 ? 'low' : ( $total < 15 ? 'medium' : 'high' ) ),
			'allowed_payment_types' => $payment_types,
			'payment_display_mode' => $display_mode,
			'policy_hash' => hash( 'sha256', wp_json_encode( array(
				$trusted_rate,
				$advance_rate,
				$block_rate,
				$minimum_orders,
				$action,
				$payment_types,
				$display_mode,
			) ) ),
		);
	}

	/** Apply merchant thresholds to Steadfast's ratio and published volume range. */
	private function evaluate_steadfast( array $profile, array $config ): array {
		$volume = sanitize_key( (string) ( $profile['volume_band'] ?? 'none' ) );
		$has_history = 'none' !== $volume;
		$ratio = max( 0, min( 100, (float) ( $profile['delivery_ratio'] ?? 0 ) ) );
		$minimum = max( 1, absint( $config['minimum_orders_for_block'] ?? 5 ) );
		$volume_minimum = SteadfastProfileService::volume_minimum( $profile );
		$enough_for_block = null !== $volume_minimum && $volume_minimum >= $minimum;
		$band = 'unknown';
		$action = $this->action( $config['unknown_action'] ?? 'allow', 'allow' );
		if ( $has_history ) {
			if ( $enough_for_block && $ratio < (float) ( $config['block_rate'] ?? 40 ) ) {
				$band = 'critical';
				$action = $this->action( $config['critical_action'] ?? 'block', 'block' );
			} elseif ( $ratio < (float) ( $config['advance_rate'] ?? 90 ) ) {
				$band = 'high';
				$action = $this->action( $config['high_risk_action'] ?? 'advance', 'advance' );
			} elseif ( $ratio < (float) ( $config['trusted_rate'] ?? 95 ) ) {
				$band = 'review';
				$action = $this->action( $config['middle_action'] ?? 'allow', 'allow' );
			} else {
				$band = 'trusted';
				$action = 'allow';
			}
		}
		$payment_types = $this->resolve_payment_types( $band, $action, $config );
		$action = $this->effective_action( $action, $payment_types );
		return array(
			'version' => 1,
			'action' => $action,
			'band' => $band,
			'total_orders' => 0,
			'success_orders' => 0,
			'cancel_orders' => 0,
			'success_rate' => $has_history ? $ratio : 0,
			'risk_score' => null,
			'confidence' => $has_history ? $volume : 'none',
			'allowed_payment_types' => $payment_types,
			'payment_display_mode' => $this->display_mode( $config['payment_display_mode'] ?? 'always' ),
			'policy_hash' => hash( 'sha256', wp_json_encode( array( $config, $action, $payment_types ) ) ),
		);
	}

	/**
	 * Build a configured fallback when the provider cannot be reached.
	 *
	 * @param string $error_code Provider error code.
	 * @return array<string,mixed>
	 */
	public function evaluate_failure( string $error_code ): array {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();

		/* API failure must never hard-block checkout. The merchant's selected
		 * unavailable-band payments determine the effective fallback action. */
		$base_action = 'allow';
		$payment_types = $this->resolve_payment_types( 'unavailable', $base_action, $config );
		$action = $this->effective_action( $base_action, $payment_types );
		$display_mode = $this->display_mode( $config['payment_display_mode'] ?? 'always' );

		return array(
			'version' => 1,
			'action' => $action,
			'band' => 'unavailable',
			'total_orders' => 0,
			'success_orders' => 0,
			'cancel_orders' => 0,
			'success_rate' => 0.0,
			'risk_score' => 0,
			'confidence' => 'none',
			'error_code' => sanitize_key( $error_code ),
			'allowed_payment_types' => $payment_types,
			'payment_display_mode' => $display_mode,
			'policy_hash' => hash( 'sha256', wp_json_encode( array( 'failure', $action, $payment_types, $display_mode ) ) ),
		);
	}

	/**
	 * Sanitize one enforcement action.
	 *
	 * @param mixed  $value Value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function action( $value, string $fallback ): string {

		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'allow', 'advance', 'full', 'block' ), true ) ? $value : $fallback;
	}

	/**
	 * Resolve the authoritative payment intersection for one risk band.
	 *
	 * @param string              $band Risk band.
	 * @param string              $action Base risk action.
	 * @param array<string,mixed> $config Live fraud settings.
	 * @return array<int,string>
	 */
	private function resolve_payment_types( string $band, string $action, array $config ): array {

		$rules = isset( $config['payment_rules'] ) && is_array( $config['payment_rules'] )
			? $config['payment_rules']
			: array();
		$configured = isset( $rules[ $band ] ) && is_array( $rules[ $band ] )
			? $this->payment_types( $rules[ $band ] )
			: array();

		$action_cap = array( 'cash_on_delivery', 'advance', 'full' );
		if ( 'advance' === $action ) {
			$action_cap = array( 'advance', 'full' );
		} elseif ( 'full' === $action ) {
			$action_cap = array( 'full' );
		} elseif ( 'block' === $action ) {
			$action_cap = array();
		}

		$resolved = array_values(
			array_intersect(
				array( 'cash_on_delivery', 'advance', 'full' ),
				$configured,
				$action_cap,
				$this->global_payment_types()
			)
		);

		/* A quota outage must leave at least one globally enabled option. If a
		 * saved allowlist became incompatible with global payment settings, use
		 * the global list instead of blocking a genuine customer. */
		if ( 'unavailable' === $band && empty( $resolved ) ) {
			return $this->global_payment_types();
		}

		return $resolved;
	}

	/** @return array<int,string> */
	private function global_payment_types(): array {

		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$settings = array_replace_recursive( CheckoutSettings::get_defaults(), $stored );
		$section = isset( $settings['advance_payment'] ) && is_array( $settings['advance_payment'] )
			? $settings['advance_payment']
			: array();

		if ( 'yes' !== ( $section['enabled'] ?? 'no' ) ) {
			return array( 'full' );
		}

		$available = array();
		if ( 'yes' === ( $section['allow_cash_on_delivery'] ?? 'no' ) ) {
			$available[] = 'cash_on_delivery';
		}
		if ( 'yes' === ( $section['allow_advance_payment'] ?? 'yes' ) ) {
			$available[] = 'advance';
		}
		if ( 'yes' === ( $section['allow_full_payment'] ?? 'yes' ) ) {
			$available[] = 'full';
		}

		return $available;
	}

	/** @param array<int,mixed> $types @return array<int,string> */
	private function payment_types( array $types ): array {

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $type ): string {
							return is_scalar( $type ) ? sanitize_key( (string) $type ) : '';
						},
						$types
					),
					static function ( string $type ): bool {
						return in_array( $type, array( 'cash_on_delivery', 'advance', 'full' ), true );
					}
				)
			)
		);
	}

	/** @param array<int,string> $payment_types */
	private function effective_action( string $base_action, array $payment_types ): string {

		if ( 'block' === $base_action || empty( $payment_types ) ) {
			return 'block';
		}
		if ( 'full' === $base_action ) {
			return in_array( 'full', $payment_types, true ) ? 'full' : 'block';
		}
		if ( 'advance' === $base_action ) {
			return in_array( 'advance', $payment_types, true ) ? 'advance' : ( in_array( 'full', $payment_types, true ) ? 'full' : 'block' );
		}
		if ( in_array( 'cash_on_delivery', $payment_types, true ) ) {
			return 'allow';
		}
		if ( in_array( 'advance', $payment_types, true ) ) {
			return 'advance';
		}

		return in_array( 'full', $payment_types, true ) ? 'full' : 'block';
	}

	private function display_mode( $value ): string {

		return 'conditional' === sanitize_key( (string) $value ) ? 'conditional' : 'always';
	}
}
