<?php
/**
 * Elementor integration.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor;

use EilmoCheckout\Licensing\LicenseManager;

use EilmoCheckout\Core\Assets;

use EilmoCheckout\Elementor\Widgets\CartDrawerWidget;
use EilmoCheckout\Elementor\Widgets\CheckoutFlowWidget;
use EilmoCheckout\Elementor\Widgets\ComboButtonWidget;
use EilmoCheckout\Elementor\Widgets\ProductButtonWidget;
use EilmoCheckout\Elementor\Widgets\VariationSelectorWidget;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor integration.
 *
 * Registers Eilmo Checkout Flow widgets with Elementor.
 *
 * Business configuration remains controlled by the
 * Checkout Flow plugin settings. Elementor is used
 * only as the presentation and design layer.
 */
final class Elementor {

	/**
	 * Elementor category slug.
	 *
	 * @var string
	 */
	private const CATEGORY =
		'eilmo-checkout-flow';

	/**
	 * Register Elementor integration.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		// Ensure shared frontend CSS is available in Elementor editor/frontend.
		add_action(
			'elementor/frontend/after_enqueue_styles',
			array(
				Assets::class,
				'enqueue_frontend',
			)
		);

		add_action(
			'elementor/elements/categories_registered',
			array(
				$this,
				'register_category',
			)
		);

		add_action(
			'elementor/widgets/register',
			array(
				$this,
				'register_widgets',
			)
		);
	}

	/**
	 * Register Eilmo Checkout Flow Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 *
	 * @return void
	 */
	public function register_category(
		$elements_manager
	): void {

		if (
			! is_object(
				$elements_manager
			) ||
			! method_exists(
				$elements_manager,
				'add_category'
			)
		) {
			return;
		}

		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' =>
					__(
						'Checkout Flow',
						'eilmo-checkout-flow'
					),

				'icon' =>
					'fa fa-shopping-cart',
			)
		);
	}

	/**
	 * Register Eilmo Elementor widgets.
	 *
	 * Each widget is registered independently so one
	 * unavailable widget does not prevent the remaining
	 * widgets from loading.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 *
	 * @return void
	 */
	public function register_widgets(
		$widgets_manager
	): void {

		if (
			! is_object(
				$widgets_manager
			) ||
			! method_exists(
				$widgets_manager,
				'register'
			)
		) {
			return;
		}

		if (
			! class_exists(
				'\Elementor\Widget_Base'
			)
		) {
			return;
		}

		/*
		 * Checkout Flow.
		 */
		if (
			class_exists(
				CheckoutFlowWidget::class
			)
		) {
			$widgets_manager->register(
				new CheckoutFlowWidget()
			);
		}

		/*
		 * Combo Button.
		 */
		if ( class_exists( ComboButtonWidget::class ) ) {
			$widgets_manager->register(
				new ComboButtonWidget()
			);
		}

		/* Product Button. */
		if ( class_exists( ProductButtonWidget::class ) ) {
			$widgets_manager->register( new ProductButtonWidget() );
		}

		/* Variation Selector. */
		if ( class_exists( VariationSelectorWidget::class ) ) {
			$widgets_manager->register( new VariationSelectorWidget() );
		}

		/*
		 * Cart Drawer trigger.
		 */
		if (
			class_exists(
				CartDrawerWidget::class
			)
		) {
			$widgets_manager->register(
				new CartDrawerWidget()
			);
		}
	}

	/**
	 * Get Elementor category slug.
	 *
	 * @return string
	 */
	public static function get_category(): string {

		return self::CATEGORY;
	}
}
