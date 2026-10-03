<?php
/**
 * Coupons module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons;

use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Coupons\Ajax\CouponAjax;
use EilmoCheckout\Coupons\Orders\CouponUsageTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Coupon-related services.
 */
final class CouponsModule implements ModuleInterface {

	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'coupons';
	}

	/**
	 * Register Coupon module services.
	 *
	 * @return void
	 */
	public function register(): void {

		$this->register_ajax();
		$this->register_usage_tracker();
	}

	/**
	 * Register Coupon AJAX handlers.
	 *
	 * @return void
	 */
	private function register_ajax(): void {

		if (
			! class_exists(
				CouponAjax::class
			)
		) {
			return;
		}

		$ajax =
			new CouponAjax();

		$ajax->register();
	}

	/**
	 * Register Coupon order usage tracker.
	 *
	 * @return void
	 */
	private function register_usage_tracker(): void {

		if (
			! class_exists(
				CouponUsageTracker::class
			)
		) {
			return;
		}

		$tracker =
			new CouponUsageTracker();

		$tracker->register();
	}
}