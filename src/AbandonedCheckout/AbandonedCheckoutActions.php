<?php
/**
 * Abandoned Checkout admin actions.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AbandonedCheckout;

use EilmoCheckout\Admin\Pages\AbandonedCheckoutsPage;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Couriers\Services\CourierSuccessService;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Abandoned Checkout admin actions.
 */
final class AbandonedCheckoutActions implements RegistrableInterface {

	/**
	 * Status update action.
	 */
	private const STATUS_ACTION =
		'eilmo_cf_abandoned_checkout_update_status';

	/**
	 * Delete action.
	 */
	private const DELETE_ACTION =
		'eilmo_cf_abandoned_checkout_delete';

	/**
	 * Explicit cached-customer courier check action.
	 */
	private const COURIER_ACTION =
		'eilmo_cf_abandoned_checkout_check_courier';

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_post_' .
				self::STATUS_ACTION,
			array(
				$this,
				'update_status',
			)
		);

		add_action(
			'admin_post_' .
				self::DELETE_ACTION,
			array(
				$this,
				'delete',
			)
		);

		add_action(
			'admin_post_' .
				self::COURIER_ACTION,
			array(
				$this,
				'check_courier',
			)
		);
	}

	/**
	 * Use courier API quota only after an administrator explicitly clicks.
	 *
	 * @return void
	 */
	public function check_courier(): void {

		$this->check_permission();

		$settings = CourierSettings::get_settings();
		$live = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();
		if ( 'yes' !== (string) ( $live['manual_order_check'] ?? 'yes' ) ) {
			$this->redirect( 'courier_check_disabled' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$checkout_id = isset( $_POST['checkout_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			? absint( wp_unslash( $_POST['checkout_id'] ) )
			: 0;

		if ( $checkout_id <= 0 ) {
			$this->redirect( 'courier_check_failed' );
		}

		check_admin_referer(
			'eilmo_cf_abandoned_checkout_courier_' . $checkout_id
		);

		$checkout = ( new AbandonedCheckoutRepository() )->get( $checkout_id );
		if ( ! is_array( $checkout ) ) {
			$this->redirect( 'courier_check_failed' );
		}

		$service = new CourierSuccessService();
		$phone = $service->normalize_phone( (string) ( $checkout['billing_phone'] ?? '' ) );
		if ( '' === $phone ) {
			$this->redirect( 'courier_invalid_phone' );
		}

		$result = $service->lookup_live_phone( $phone, true );
		if ( ! is_wp_error( $result ) ) {
			$this->redirect( 'courier_checked' );
		}

		$code = $result->get_error_code();
		if ( 'eilmo_cf_courier_daily_limit_reached' === $code ) {
			$this->redirect( 'courier_limit' );
		}

		if ( 'eilmo_cf_courier_api_key_missing' === $code ) {
			$this->redirect( 'courier_not_configured' );
		}

		$this->redirect( 'courier_check_failed' );
	}

	/**
	 * Manually update checkout status.
	 *
	 * @return void
	 */
	public function update_status(): void {

		$this->check_permission();

		$checkout_id =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'checkout_id'
				]
			)
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'checkout_id'
						]
					)
				)
				: 0;

		$status =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'checkout_status'
				]
			)
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'checkout_status'
						]
					)
				)
				: '';

		if (
			$checkout_id <= 0 ||
			! in_array(
				$status,
				AbandonedCheckoutRepository::statuses(),
				true
			)
		) {
			$this->redirect(
				'status_failed'
			);
		}

		check_admin_referer(
			'eilmo_cf_abandoned_checkout_status_' .
				$checkout_id
		);

		$repository =
			new AbandonedCheckoutRepository();

		$updated =
			$repository->update_status(
				$checkout_id,
				$status
			);

		$this->redirect(
			$updated
				? 'status_updated'
				: 'status_failed'
		);
	}

	/**
	 * Permanently delete checkout.
	 *
	 * @return void
	 */
	public function delete(): void {

		$this->check_permission();

		$checkout_id =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'checkout_id'
				]
			)
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'checkout_id'
						]
					)
				)
				: 0;

		if ( $checkout_id <= 0 ) {
			$this->redirect(
				'delete_failed'
			);
		}

		check_admin_referer(
			'eilmo_cf_abandoned_checkout_delete_' .
				$checkout_id
		);

		$repository =
			new AbandonedCheckoutRepository();

		$deleted =
			$repository->delete(
				$checkout_id
			);

		$this->redirect(
			$deleted
				? 'deleted'
				: 'delete_failed'
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return void
	 */
	private function check_permission(): void {

		if (
			current_user_can(
				'manage_woocommerce'
			)
		) {
			return;
		}

		wp_die(
			esc_html__(
				'You do not have permission to perform this action.',
				'eilmo-checkout-flow'
			)
		);
	}

	/**
	 * Redirect back to Abandoned Checkout.
	 *
	 * @param string $notice Notice code.
	 *
	 * @return void
	 */
	private function redirect(
		string $notice
	): void {

		$fallback =
			add_query_arg(
				array(
					'page' =>
						AbandonedCheckoutsPage::PAGE_SLUG,
				),
				admin_url(
					'admin.php'
				)
			);

		$requested =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'redirect_to'
				]
			)
				? esc_url_raw(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'redirect_to'
						]
					)
				)
				: '';

		$redirect =
			wp_validate_redirect(
				$requested,
				$fallback
			);

		$redirect =
			remove_query_arg(
				'eilmo_notice',
				$redirect
			);

		$redirect =
			add_query_arg(
				array(
					'eilmo_notice' =>
						sanitize_key(
							$notice
						),
				),
				$redirect
			);

		wp_safe_redirect(
			$redirect
		);

		exit;
	}
}
