<?php
/**
 * Couriers module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers;

use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Couriers\Admin\OrderCourierColumn;
use EilmoCheckout\Couriers\Ajax\LiveFraudAjax;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Couriers\Webhooks\PathaoWebhook;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Courier services.
 */
final class CourierModule implements ModuleInterface {

	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'couriers';
	}

	/**
	 * Register Courier module services.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! CourierSettings::is_enabled() ) {
			return;
		}

		$this->register_success_capture();
		$this->register_live_fraud();
		$this->register_pathao_webhook();
		$this->register_admin_orders();
	}

	/** Register the secure checkout-time phone verification endpoint. */
	private function register_live_fraud(): void {

		if ( ! class_exists( LiveFraudAjax::class ) ) {
			return;
		}

		( new LiveFraudAjax() )->register();
	}

	/**
	 * Register Courier Success order lifecycle handling.
	 *
	 * This service is registered whenever the Courier module itself is
	 * enabled, even when the Courier Success sub-feature is currently off.
	 *
	 * That is intentional: every newly created order must permanently
	 * remember whether Courier Success was enabled at the exact time the
	 * order was created. Orders created while the feature is off are marked
	 * ineligible and can never consume courier-history API quota later simply
	 * because the feature is turned back on.
	 *
	 * The registration must run outside wp-admin because WooCommerce orders
	 * are normally created from frontend checkout/AJAX requests.
	 *
	 * @return void
	 */
	private function register_success_capture(): void {

		if (
			! class_exists(
				CourierSuccessService::class
			)
		) {
			return;
		}

		$service =
			new CourierSuccessService();

		$service->register();
	}

	/**
	 * Register Pathao delivery-status webhook.
	 *
	 * The webhook must be available on frontend REST requests,
	 * so this is registered outside wp-admin.
	 *
	 * @return void
	 */
	private function register_pathao_webhook(): void {

		if (
			! class_exists(
				PathaoWebhook::class
			)
		) {
			return;
		}

		$webhook =
			new PathaoWebhook();

		$webhook->register();
	}

	/**
	 * Register WooCommerce Orders Courier UI.
	 *
	 * @return void
	 */
	private function register_admin_orders(): void {

		if (
			! is_admin() ||
			! class_exists(
				OrderCourierColumn::class
			)
		) {
			return;
		}

		$orders =
			new OrderCourierColumn();

		$orders->register();
	}
}
