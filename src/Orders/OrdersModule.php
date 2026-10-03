<?php
/**
 * Orders module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders;

use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Orders\Ajax\OrderAjax;
use EilmoCheckout\Orders\Rendering\OrderDetailsRenderer;
use EilmoCheckout\Orders\Rendering\OrderItemDiscountRenderer;
use EilmoCheckout\Orders\Services\OrderPayGatewayFilter;
use EilmoCheckout\Orders\Services\PaymentStatusTracker;
use EilmoCheckout\Orders\Admin\ManualOrderComposer;
use EilmoCheckout\Payment\Services\PaymentProof;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Order-related services.
 */
final class OrdersModule implements ModuleInterface {

	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'orders';
	}

	/**
	 * Register Order module services.
	 *
	 * @return void
	 */
	public function register(): void {

		$this->register_payment_proof();
		$this->register_ajax();
		$this->register_order_details();
		if ( is_admin() ) {
			( new ManualOrderComposer() )->register();
		}
		$this->register_payment_status_tracker();
		$this->register_order_pay_gateway_filter();
	}

	/** Register secure manual-payment proof handling. */
	private function register_payment_proof(): void {
		if ( class_exists( PaymentProof::class ) ) {
			( new PaymentProof() )->register();
		}
	}

	/**
	 * Register Order AJAX handlers.
	 *
	 * @return void
	 */
	private function register_ajax(): void {

		if (
			! class_exists(
				OrderAjax::class
			)
		) {
			return;
		}

		$ajax =
			new OrderAjax();

		$ajax->register();
	}

	/**
	 * Register WooCommerce order detail rendering.
	 *
	 * @return void
	 */
	private function register_order_details(): void {

		if (
			! class_exists(
				OrderDetailsRenderer::class
			)
		) {
			return;
		}

		$renderer =
			new OrderDetailsRenderer();

		$renderer->register();
	}

	/**
	 * Register Eilmo payment status synchronization.
	 *
	 * @return void
	 */
	private function register_payment_status_tracker(): void {

		if (
			! class_exists(
				PaymentStatusTracker::class
			)
		) {
			return;
		}

		$tracker =
			new PaymentStatusTracker();

		$tracker->register();
	}

	/**
	 * Restrict WooCommerce Order Pay to the gateway
	 * selected during Eilmo checkout.
	 *
	 * @return void
	 */
	private function register_order_pay_gateway_filter(): void {

		if (
			! class_exists(
				OrderPayGatewayFilter::class
			)
		) {
			return;
		}

		$filter =
			new OrderPayGatewayFilter();

		$filter->register();
	}
}
