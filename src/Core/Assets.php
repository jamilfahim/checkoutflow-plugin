<?php
/**
 * Plugin assets.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Licensing\LicenseManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin frontend and admin assets.
 */
final class Assets implements RegistrableInterface {

	/**
	 * Frontend stylesheet handle.
	 */
	public const FRONTEND_STYLE =
		'eilmo-cf-frontend';

	/**
	 * Legacy product/card presentation stylesheet.
	 *
	 * Loaded only by deprecated inline product/gallery renderers. New
	 * Elementor-first checkout pages do not download this file.
	 */
	public const LEGACY_FRONTEND_STYLE =
		'eilmo-cf-frontend-legacy';

	/**
	 * Deprecated Special Discounts stylesheet handle.
	 *
	 * Automatic Special Discounts are data-only in checkout since 2.2.1,
	 * so no promotional-card stylesheet is registered or enqueued. The
	 * constant remains for third-party compatibility only.
	 */
	public const SPECIAL_DISCOUNTS_STYLE =
		'eilmo-cf-special-discounts';

	/**
	 * Quantity script handle.
	 */
	public const QUANTITY_SCRIPT =
		'eilmo-cf-quantity';

	/**
	 * Multiple Products controller handle.
	 */
	public const MULTIPLE_PRODUCTS_SCRIPT =
		'eilmo-cf-multiple-products';

	/**
	 * Automatic discount script handle.
	 */
	public const DISCOUNT_SCRIPT =
		'eilmo-cf-discount';

	/**
	 * Summary script handle.
	 */
	public const SUMMARY_SCRIPT =
		'eilmo-cf-summary';

	/**
	 * Abandoned Checkout frontend script handle.
	 */
	public const ABANDONED_SCRIPT =
		'eilmo-cf-abandoned-checkout';

	/**
	 * Native WooCommerce checkout abandoned tracking script.
	 */
	public const NATIVE_ABANDONED_SCRIPT =
		'eilmo-cf-native-abandoned-checkout';

	/**
	 * Main frontend script handle.
	 */
	public const FRONTEND_SCRIPT =
		'eilmo-cf-frontend';

	/**
	 * Admin stylesheet handle.
	 */
	public const ADMIN_STYLE =
		'eilmo-cf-admin';

	/**
	 * Dashboard stylesheet handle.
	 */
	public const ADMIN_DASHBOARD_STYLE =
		'eilmo-cf-dashboard';

	/**
	 * Main admin script handle.
	 */
	public const ADMIN_SCRIPT =
		'eilmo-cf-admin';

	/**
	 * Admin settings script handle.
	 */
	public const ADMIN_SETTINGS_SCRIPT =
		'eilmo-cf-admin-settings';


	/**
	 * Abandoned Checkout tracking AJAX action.
	 */
	private const ABANDONED_AJAX_ACTION =
		'eilmo_cf_track_abandoned_checkout';

	/**
	 * Abandoned Checkout AJAX nonce action.
	 */
	private const ABANDONED_NONCE_ACTION =
		'eilmo_cf_abandoned_checkout_tracking';

	/**
	 * Abandoned Checkout frontend debounce.
	 *
	 * @var int
	 */
	private const ABANDONED_DEBOUNCE =
		900;

	/**
	 * Eilmo Checkout dashboard page slug.
	 */
	private const DASHBOARD_PAGE_SLUG =
		'eilmo-checkout';

	/**
	 * Eilmo Checkout settings page slug.
	 */
	private const SETTINGS_PAGE_SLUG =
		'eilmo-checkout-settings';


	/**
	 * Offers and discounts settings page slug.
	 */
	private const OFFERS_SETTINGS_PAGE_SLUG =
		'eilmo-checkout-offers';

	public const PAGE_SLUG =
		'eilmo-checkout-meta-tracking';

    /**
     * Eilmo Checkout admin page slugs.
     *
     * Shared admin.css is loaded on all these pages.
     *
     * @var array<string>
     */
    private const ADMIN_PAGE_SLUGS = array(
        'eilmo-checkout',
        'eilmo-checkout-settings',
		'eilmo-checkout-default',
        'eilmo-checkout-offers',
        'eilmo-checkout-integrations',
        'eilmo-checkout-quick-checkout',
        'eilmo-checkout-courier',
        'eilmo-checkout-meta-tracking',
        'eilmo-checkout-abandoned',
        'eilmo-checkout-security',
        'eilmo-checkout-whatsapp',
        'eilmo-checkout-help',
    );

	/**
	 * Whether frontend assets have been registered.
	 *
	 * @var bool
	 */
	private static $frontend_registered =
		false;

	/**
	 * Whether admin assets have been registered.
	 *
	 * @var bool
	 */
	private static $admin_registered =
		false;

	/**
	 * Whether admin configuration has been localized.
	 *
	 * @var bool
	 */
	private static $admin_localized =
		false;

	/**
	 * Frontend module scripts.
	 *
	 * This release manifest contains only shipped, non-empty scripts.
	 * Release validation verifies every declared path before packaging.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private const FRONTEND_SCRIPTS = array(

		/*
		 * Product Gallery slider and lightbox.
		 */
		'eilmo-cf-product-gallery' => array(
			'path' =>
				'src/js/frontend/product-gallery.js',

			'deps' =>
				array(),
		),

		/*
		 * Core product selection.
		 */
		'eilmo-cf-quantity' => array(
			'path' =>
				'src/js/frontend/quantity.js',

			'deps' =>
					array(),
		),

		/*
		 * Consolidated Single Product attribute and variation controls.
		 */
		'eilmo-cf-single-product' => array(
			'path' =>
				'src/js/frontend/single-product.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
				),
			),

		/*
		 * Multiple Products parent-product, variation and quantity controls.
		 *
		 * This controller uses its own selectors but shares the authoritative
		 * quantity-change event contract with Summary and checkout submission.
		 */
		'eilmo-cf-multiple-products' => array(
			'path' =>
				'src/js/frontend/multiple-products.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
				),
		),

		/*
		 * Live order summary.
		 */
		'eilmo-cf-summary' => array(
			'path' =>
				'src/js/frontend/summary.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
					'eilmo-cf-single-product',
					'eilmo-cf-multiple-products',
				),
		),

		/*
		 * Live order button amount display.
		 */
		'eilmo-cf-order-display' => array(
			'path' =>
				'src/js/frontend/order-display.js',

			'deps' =>
				array(
					'eilmo-cf-summary',
				),
		),

		/*
		 * Automatic discount calculation and notice.
		 */
		'eilmo-cf-discount' => array(
			'path' =>
				'src/js/frontend/discount.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
					'eilmo-cf-summary',
				),
		),

		/*
		 * Combo Offers.
		 */
		'eilmo-cf-combo' => array(
			'path' =>
				'src/js/frontend/combo.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
					'eilmo-cf-summary',
				),
		),

		/*
		 * Delivery calculation.
		 */
		'eilmo-cf-delivery' => array(
			'path' =>
				'src/js/frontend/delivery.js',

			'deps' =>
				array(
					'eilmo-cf-summary',
					'eilmo-cf-discount',
				),
		),

		/*
		 * Advance payment calculation.
		 */
		'eilmo-cf-advance' => array(
			'path' =>
				'src/js/frontend/advance.js',

			'deps' =>
				array(
					'eilmo-cf-summary',
					'eilmo-cf-discount',
				),
		),

		/*
		 * Coupon handling.
		 */
		'eilmo-cf-coupon' => array(
			'path' =>
				'src/js/frontend/coupon.js',

			'deps' =>
				array(
					'eilmo-cf-summary',
				),
		),

		/* One-time live courier-risk verification. */
		'eilmo-cf-live-fraud' => array(
			'path' =>
				'src/js/frontend/fraud-check.js',

			'deps' =>
				array(
					'eilmo-cf-advance',
				),
		),

		/*
		 * Payment methods.
		 */
		'eilmo-cf-payment' => array(
			'path' =>
				'src/js/frontend/payment.js',

			'deps' =>
				array(
					'eilmo-cf-summary',
				),
		),

		/*
		 * Order bump.
		 */
		'eilmo-cf-order-bump' => array(
			'path' =>
				'src/js/frontend/order-bump.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
					'eilmo-cf-summary',
				),
		),

		/*
		 * Checkout / order submission.
		 *
		 * This script runs only after all modules
		 * required for final checkout validation and
		 * payload collection are available.
		 */
		'eilmo-cf-checkout-submit' => array(
			'path' =>
				'src/js/frontend/checkout.js',

			'deps' =>
				array(
					'eilmo-cf-quantity',
					'eilmo-cf-summary',
					'eilmo-cf-discount',
					'eilmo-cf-combo',
					'eilmo-cf-delivery',
					'eilmo-cf-advance',
					'eilmo-cf-coupon',
					'eilmo-cf-live-fraud',
					'eilmo-cf-payment',
					'eilmo-cf-order-bump',
				),
		),

		/*
		 * Tracking integrations.
		 */
		'eilmo-cf-tracking' => array(
			'path' =>
				'src/js/frontend/tracking.js',

			'deps' =>
				array(),
		),

		/*
		 * Abandoned Checkout tracking.
		 *
		 * This script only stores meaningful checkout
		 * activity.
		 *
		 * It does not create WooCommerce orders,
		 * modify orders or perform recovery flows.
		 */
		'eilmo-cf-abandoned-checkout' => array(
			'path' =>
				'src/js/frontend/abandoned.js',

			'deps' =>
				array(
					'eilmo-cf-checkout-submit',
				),
		),

	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'register_frontend_assets',
			),
			10
		);

		/*
		 * WooCommerce endpoint pages do not render
		 * the Eilmo checkout shortcode/widget.
		 *
		 * Load only the shared frontend stylesheet on:
		 *
		 * - Order Received / Thank You.
		 * - Order Pay.
		 */
		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'enqueue_woocommerce_order_endpoint_assets',
			),
			20
		);

		/*
		 * Track native WooCommerce Classic and Checkout Block pages too.
		 */
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_native_abandoned_checkout_assets' ),
			25
		);

		/*
		 * Run after WooCommerce admin assets. Older WooCommerce
		 * releases register wc-enhanced-select during the
		 * admin_enqueue_scripts hook itself (instead of admin_init).
		 * Using a late priority guarantees the Product Gallery
		 * Linked Product search can safely depend on and enqueue
		 * WooCommerce's AJAX product selector on our custom screen.
		 */
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_admin_assets',
			),
			99
		);
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_frontend_assets(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		if (
			self::$frontend_registered
		) {
			return;
		}

		self::$frontend_registered =
			true;

		$this->register_frontend_style();
		$this->register_frontend_scripts();
		$this->register_frontend_config();
	}

	/**
	 * Register frontend stylesheet.
	 *
	 * @return void
	 */
	private function register_frontend_style(): void {

		$relative_path =
			'build/css/frontend.css';

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			$relative_path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		wp_register_style(
			self::FRONTEND_STYLE,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			array(),
			$this->get_asset_version(
				$relative_path
			)
		);

		$legacy_relative_path = 'build/css/frontend-legacy.css';
		$legacy_absolute_path = EILMO_CF_PATH . 'assets/' . $legacy_relative_path;

		if ( file_exists( $legacy_absolute_path ) ) {
			wp_register_style(
				self::LEGACY_FRONTEND_STYLE,
				EILMO_CF_ASSETS_URL . $legacy_relative_path,
				array( self::FRONTEND_STYLE ),
				$this->get_asset_version( $legacy_relative_path )
			);
		}

	}

	/**
	 * Enqueue Eilmo frontend stylesheet on native
	 * WooCommerce order endpoint pages.
	 *
	 * Supported pages:
	 *
	 * - Order Received / Thank You.
	 * - Order Pay.
	 *
	 * @return void
	 */
	public function enqueue_woocommerce_order_endpoint_assets(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		$is_order_received =
			function_exists(
				'is_order_received_page'
			) &&
			is_order_received_page();

		$is_order_pay =
			false;

		if (
			function_exists(
				'is_checkout_pay_page'
			)
		) {
			$is_order_pay =
				is_checkout_pay_page();
		} elseif (
			function_exists(
				'is_wc_endpoint_url'
			)
		) {
			$is_order_pay =
				is_wc_endpoint_url(
					'order-pay'
				);
		}

		if (
			! $is_order_received &&
			! $is_order_pay
		) {
			return;
		}

		self::ensure_frontend_assets_registered();

		if (
			wp_style_is(
				self::FRONTEND_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				self::FRONTEND_STYLE
			);
		}
	}

	/**
	 * Register frontend scripts.
	 *
	 * @return void
	 */
	private function register_frontend_scripts(): void {
		$this->register_script( 'eilmo-cf-dom', 'src/js/frontend/dom.js', array() );
        $this->register_script( 'eilmo-cf-language', 'src/js/frontend/checkout-language.js', array() );
        $this->register_script( 'eilmo-cf-presentation', 'src/js/frontend/checkout-presentation.js', array( 'eilmo-cf-language' ) );

		foreach (
			self::FRONTEND_SCRIPTS as
				$handle =>
				$asset
		) {

			/*
			 * Do not register Abandoned Checkout
			 * JavaScript when the feature is disabled.
			 */
			if (
				self::ABANDONED_SCRIPT ===
					$handle &&
				! $this->is_abandoned_checkout_enabled()
			) {
				continue;
			}

			if (
				empty(
					$asset[
						'path'
					]
				) ||
				! is_string(
					$asset[
						'path'
					]
				)
			) {
				continue;
			}

			$dependencies =
				isset(
					$asset[
						'deps'
					]
				) &&
				is_array(
					$asset[
						'deps'
					]
				)
					? $asset[
						'deps'
					]
					: array();

			$dependencies[] = 'eilmo-cf-dom';
            $dependencies[] = 'eilmo-cf-language';
            if ( 'eilmo-cf-tracking' !== $handle ) { $dependencies[] = 'eilmo-cf-presentation'; }
			$this->register_script(
				$handle,
				$asset[
					'path'
				],
				$dependencies
			);
		}

		/*
		 * Optional main frontend bootstrap.
		 */
		$frontend_path =
			'build/js/frontend.js';

		if (
			$this->asset_has_content(
				$frontend_path
			)
		) {
			wp_register_script(
				self::FRONTEND_SCRIPT,
				EILMO_CF_ASSETS_URL .
					$frontend_path,
				array(),
				$this->get_asset_version(
					$frontend_path
				),
				array(
					'in_footer' =>
						true,

					'strategy' =>
						'defer',
				)
			);
		}
	}

	/**
	 * Register individual frontend script.
	 *
	 * @param string        $handle        Script handle.
	 * @param string        $relative_path Relative path.
	 * @param array<string> $dependencies  Dependencies.
	 *
	 * @return void
	 */
	private function register_script(
		string $handle,
		string $relative_path,
		array $dependencies = array()
	): void {

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			$relative_path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		$dependencies =
			array_values(
				array_filter(
					$dependencies,
					static function (
						$dependency
					): bool {

						return (
							is_string(
								$dependency
							) &&
							wp_script_is(
								$dependency,
								'registered'
							)
						);
					}
				)
			);

		wp_register_script(
			$handle,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			$dependencies,
			$this->get_asset_version(
				$relative_path
			),
			array(
				'in_footer' =>
					true,

				'strategy' =>
					'defer',
			)
		);
	}

	/**
	 * Register frontend JavaScript configuration.
	 *
	 * @return void
	 */
	private function register_frontend_config(): void {

		$handle =
			$this->get_first_registered_frontend_script();

		if (
			null === $handle
		) {
			return;
		}

		$abandoned_enabled =
			$this->is_abandoned_checkout_enabled();

		$courier_settings = CourierSettings::get_settings();
		$live_fraud = isset( $courier_settings['live_fraud'] ) && is_array( $courier_settings['live_fraud'] )
			? $courier_settings['live_fraud']
			: array();
		$live_fraud_config = array(
			'enabled' => CourierSettings::live_fraud_is_enabled() ? 'yes' : 'no',
			'action' => 'eilmo_cf_live_fraud_check',
			'debounce' => 700,
			'paymentDisplayMode' => 'conditional' === ( $live_fraud['payment_display_mode'] ?? 'always' ) ? 'conditional' : 'always',
			'checkingMessage' => sanitize_text_field( (string) ( $live_fraud['checking_message'] ?? __( 'Checking delivery history…', 'eilmo-checkout-flow' ) ) ),
			'checkFailedMessage' => __( 'Phone verification failed. Please try again.', 'eilmo-checkout-flow' ),
			'checkRequiredMessage' => __( 'Please complete phone verification before placing the order.', 'eilmo-checkout-flow' ),
		);

		wp_localize_script(
			$handle,
			'eilmoCf',
			array(
				'ajaxUrl' =>
					admin_url(
						'admin-ajax.php'
					),

				'ajaxNonce' =>
					wp_create_nonce(
						'eilmo_cf_frontend'
					),

				'restUrl' =>
					esc_url_raw(
						rest_url(
							'eilmo-cf/v1/'
						)
					),

				'restNonce' =>
					wp_create_nonce(
						'wp_rest'
					),

				/*
				 * -------------------------------------
				 * Abandoned Checkout
				 * -------------------------------------
				 *
				 * Tracking only.
				 */
				'abandonedCheckout' =>
					array(
						'enabled' =>
							$abandoned_enabled
								? 'yes'
								: 'no',

						'action' =>
							self::ABANDONED_AJAX_ACTION,

						'nonce' =>
							$abandoned_enabled
								? wp_create_nonce(
									self::ABANDONED_NONCE_ACTION
								)
								: '',

						'debounce' =>
							self::ABANDONED_DEBOUNCE,
					),

				'liveFraud' => $live_fraud_config,

				'paymentProof' => array(
					'action' => 'eilmo_cf_upload_payment_proof',
					'nonce' => wp_create_nonce( 'eilmo_cf_payment_proof' ),
					'uploading' => __( 'Uploading screenshot…', 'eilmo-checkout-flow' ),
					'uploaded' => __( 'Payment screenshot uploaded.', 'eilmo-checkout-flow' ),
					'failed' => __( 'Screenshot upload failed. Please try again.', 'eilmo-checkout-flow' ),
				),

				'currency' =>
					array(
						'code' =>
							get_woocommerce_currency(),

						'symbol' =>
							get_woocommerce_currency_symbol(),

						'position' =>
							get_option(
								'woocommerce_currency_pos',
								'left'
							),

						'decimals' =>
							wc_get_price_decimals(),

						'decimalSeparator' =>
							wc_get_price_decimal_separator(),

						'thousandSeparator' =>
							wc_get_price_thousand_separator(),
					),

				'i18n' =>
					array(
						'processing' =>
							__(
								'Processing...',
								'eilmo-checkout-flow'
							),

						'error' =>
							__(
								'Something went wrong. Please try again.',
								'eilmo-checkout-flow'
							),

						'selectProduct' =>
							__(
								'Please select at least one product.',
								'eilmo-checkout-flow'
							),

						'deliveryRequired' =>
							__(
								'Please select a delivery method.',
								'eilmo-checkout-flow'
							),

						'paymentRequired' =>
							__(
								'Please select a payment method.',
								'eilmo-checkout-flow'
							),

						'customerRequired' =>
							__(
								'Please complete the required customer information.',
								'eilmo-checkout-flow'
							),

						'couponProcessing' =>
							__(
								'Please wait for coupon validation to finish.',
								'eilmo-checkout-flow'
							),

						'orderSuccess' =>
							__(
								'Your order has been placed successfully.',
								'eilmo-checkout-flow'
							),

						'free' =>
							__(
								'Free',
								'eilmo-checkout-flow'
							),
					),
			)
		);

		/*
		 * Keep the live controller self-sufficient. Elementor can resolve
		 * dependency handles in a different order than shortcode rendering;
		 * this merge guarantees the AJAX URL, nonce and feature config exist
		 * immediately before fraud-check.js executes.
		 */
		if (
			self::FRONTEND_SCRIPT !== $handle &&
			wp_script_is( 'eilmo-cf-live-fraud', 'registered' )
		) {
			$live_bootstrap = wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'ajaxNonce' => wp_create_nonce( 'eilmo_cf_frontend' ),
					'liveFraud' => $live_fraud_config,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			if ( is_string( $live_bootstrap ) ) {
				wp_add_inline_script(
					'eilmo-cf-live-fraud',
					'window.eilmoCf=Object.assign(window.eilmoCf||{},' . $live_bootstrap . ');',
					'before'
				);
			}
		}
	}

	/**
	 * Get first registered frontend script.
	 *
	 * @return string|null
	 */
	private function get_first_registered_frontend_script(): ?string {
		if (
			wp_script_is(
				self::FRONTEND_SCRIPT,
				'registered'
			)
		) {
			return self::FRONTEND_SCRIPT;
		}

		foreach (
			array_keys(
				self::FRONTEND_SCRIPTS
			) as $handle
		) {

			if (
				wp_script_is(
					$handle,
					'registered'
				)
			) {
				return $handle;
			}
		}

		return null;
	}

	/**
	 * Register all admin assets.
	 *
	 * @return void
	 */
	public function register_admin_assets(): void {

		if (
			self::$admin_registered
		) {
			return;
		}

		self::$admin_registered =
			true;

		$this->register_admin_style();
		$this->register_dashboard_admin_style();
		$this->register_main_admin_script();
		$this->register_settings_admin_script();
		$this->register_admin_config();
	}

	/**
	 * Register admin stylesheet.
	 *
	 * @return void
	 */
	private function register_admin_style(): void {

		$relative_path =
			'build/css/admin.css';

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			$relative_path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		wp_register_style(
			self::ADMIN_STYLE,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			array(),
			$this->get_asset_version(
				$relative_path
			)
		);
	}

	/**
	 * Register dashboard stylesheet.
	 *
	 * This stylesheet is loaded only on the main Eilmo
	 * Dashboard page. Keeping dashboard styles separate
	 * prevents dashboard-specific rules from affecting
	 * Settings, Courier, Meta Tracking or other admin
	 * screens.
	 *
	 * @return void
	 */
	private function register_dashboard_admin_style(): void {

		$relative_path =
			'src/css/admin/dashboard.css';

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			$relative_path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		$dependencies =
			array();

		if (
			wp_style_is(
				self::ADMIN_STYLE,
				'registered'
			)
		) {
			$dependencies[] =
				self::ADMIN_STYLE;
		}

		wp_register_style(
			self::ADMIN_DASHBOARD_STYLE,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			$dependencies,
			$this->get_asset_version(
				$relative_path
			)
		);
	}

	/**
	 * Register optional main admin script.
	 *
	 * @return void
	 */
	private function register_main_admin_script(): void {

		$relative_path =
			'build/js/admin.js';

		if (
			! $this->asset_has_content(
				$relative_path
			)
		) {
			return;
		}

		wp_register_script(
			self::ADMIN_SCRIPT,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			array(),
			$this->get_asset_version(
				$relative_path
			),
			array(
				'in_footer' =>
					true,

				'strategy' =>
					'defer',
			)
		);
	}

	/**
	 * Register settings page script.
	 *
	 * @return void
	 */
	private function register_settings_admin_script(): void {

		$relative_path =
			'src/js/admin/settings.js';

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			$relative_path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		$dependencies =
			array();

		if ( wp_script_is( 'jquery', 'registered' ) ) {
			$dependencies[] = 'jquery';
		}

		if (
			wp_script_is(
				self::ADMIN_SCRIPT,
				'registered'
			)
		) {
			$dependencies[] =
				self::ADMIN_SCRIPT;
		}

		wp_register_script(
			self::ADMIN_SETTINGS_SCRIPT,
			EILMO_CF_ASSETS_URL .
				$relative_path,
			$dependencies,
			$this->get_asset_version(
				$relative_path
			),
			true
		);
	}


	/**
	 * Register admin JavaScript configuration.
	 *
	 * @return void
	 */
	private function register_admin_config(): void {

		if (
			self::$admin_localized
		) {
			return;
		}

		$handle =
			null;

		/*
		 * Prefer shared main admin script.
		 */
		if (
			wp_script_is(
				self::ADMIN_SCRIPT,
				'registered'
			)
		) {
			$handle =
				self::ADMIN_SCRIPT;
		} elseif (
			wp_script_is(
				self::ADMIN_SETTINGS_SCRIPT,
				'registered'
			)
		) {
			$handle =
				self::ADMIN_SETTINGS_SCRIPT;
		}

		if (
			null === $handle
		) {
			return;
		}

		wp_localize_script(
			$handle,
			'eilmoCfAdmin',
			array(
				'ajaxUrl' =>
					admin_url(
						'admin-ajax.php'
					),

				'ajaxNonce' =>
					wp_create_nonce(
						'eilmo_cf_admin'
					),

				'restUrl' =>
					esc_url_raw(
						rest_url(
							'eilmo-cf/v1/'
						)
					),

				'restNonce' =>
					wp_create_nonce(
						'wp_rest'
					),

				'i18n' =>
					array(
						'deliveryMethod' =>
							__(
								'Delivery Method',
								'eilmo-checkout-flow'
							),

						'advanceRule' =>
							__(
								'Advance Rule',
								'eilmo-checkout-flow'
							),

						'discountRule' =>
							__(
								'Discount Rule',
								'eilmo-checkout-flow'
							),

						'coupon' =>
							__(
								'Coupon',
								'eilmo-checkout-flow'
							),

						'remove' =>
							__(
								'Remove',
								'eilmo-checkout-flow'
							),

						'noMaximum' =>
							__(
								'No maximum',
								'eilmo-checkout-flow'
							),
					),
			)
		);

		self::$admin_localized =
			true;
	}

	/**
	 * Enqueue admin assets for current admin screen.
	 *
	 * Shared admin.css is loaded on:
	 *
	 * - Dashboard.
	 * - Settings.
	 * - Default Checkout.
	 * - Quick Checkout.
	 * - Integrations.
	 * - Courier.
	 * - Meta Tracking.
	 * - Abandoned Checkouts.
	 * - Security.
	 * - WhatsApp Orders.
	 * - WooCommerce order edit screen.
	 *
	 * Settings-specific JavaScript remains restricted
	 * to Checkout, Product and Offers settings pages.
	 *
	 * @param string $hook_suffix Current admin hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(
		string $hook_suffix
	): void {

		$is_eilmo_screen =
			$this->is_eilmo_admin_screen(
				$hook_suffix
			);

		$is_settings_screen =
			$this->is_settings_screen(
				$hook_suffix
			);


		$is_dashboard_screen =
			$this->is_dashboard_screen(
				$hook_suffix
			);

		$is_order_screen =
			$this->is_order_screen();

		$is_product_screen =
			$this->is_product_edit_screen();

		/*
		 * Eilmo assets are not required on unrelated
		 * WordPress admin screens.
		 */
		if (
			! $is_eilmo_screen &&
			! $is_order_screen &&
			! $is_product_screen
		) {
			return;
		}

		$this->register_admin_assets();

		/*
		 * Shared Eilmo admin stylesheet.
		 */
		if (
			wp_style_is(
				self::ADMIN_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				self::ADMIN_STYLE
			);
		}

		/*
		 * Dashboard-specific stylesheet.
		 *
		 * This is intentionally not included in the
		 * shared admin stylesheet so dashboard layout
		 * rules remain isolated to the Dashboard page.
		 */
		if (
			$is_dashboard_screen &&
			wp_style_is(
				self::ADMIN_DASHBOARD_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				self::ADMIN_DASHBOARD_STYLE
			);
		}

		/*
		 * WooCommerce order edit screen only needs the
		 * shared admin stylesheet and proof-viewer script.
		 */
		if (
			! $is_eilmo_screen
		) {
			if ( $is_order_screen && wp_script_is( self::ADMIN_SCRIPT, 'registered' ) ) {
				wp_enqueue_script( self::ADMIN_SCRIPT );
			}
			return;
		}

		/*
		 * Optional shared admin JavaScript.
		 */
		if (
			wp_script_is(
				self::ADMIN_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::ADMIN_SCRIPT
			);
		}

		/*
		 * Main Settings-specific JavaScript.
		 *
		 * Quick Checkout currently uses standard form
		 * controls only, so settings.js is not required
		 * on that page.
		 */
		if ( $is_settings_screen ) {
			/*
			 * Offers & Discounts uses WooCommerce's
			 * AJAX product search (`.wc-product-search`). Enqueue the enhanced
			 * selector on every Eilmo settings screen so Special Discount
			 * Selected Products / Gift Product fields are interactive too.
			 */
			wp_enqueue_media();

			if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
				wp_enqueue_script( 'wc-enhanced-select' );
			}

			if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}

			if (
				wp_script_is(
					self::ADMIN_SETTINGS_SCRIPT,
					'registered'
				)
			) {
				wp_enqueue_script(
					self::ADMIN_SETTINGS_SCRIPT
				);
			}
		}

	}

	/**
	 * Ensure frontend assets are registered.
	 *
	 * @return void
	 */
	public static function register_frontend(): void {

		self::ensure_frontend_assets_registered();
	}

	/**
	 * Get registered frontend script handles.
	 *
	 * Elementor uses these handles as widget
	 * dependencies.
	 *
	 * @return array<int,string>
	 */
	public static function get_frontend_script_handles(): array {

		self::ensure_frontend_assets_registered();

		if (
			wp_script_is(
				self::FRONTEND_SCRIPT,
				'registered'
			)
		) {
			return array(
				self::FRONTEND_SCRIPT,
			);
		}

		$handles =
			array();

		foreach (
			array_keys(
				self::FRONTEND_SCRIPTS
			) as $handle
		) {

			if (
				wp_script_is(
					$handle,
					'registered'
				)
			) {
				$handles[] =
					$handle;
			}
		}

		return array_values(
			array_unique(
				$handles
			)
		);
	}

	/**
	 * Enqueue all required frontend assets.
	 *
	 * This should be called only when an Eilmo Checkout
	 * Flow shortcode/widget is actually rendered.
	 *
	 * @return void
	 */
	public static function enqueue_frontend(): void {
		self::ensure_frontend_assets_registered();

		if (
			wp_style_is(
				self::FRONTEND_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				self::FRONTEND_STYLE
			);
		}

		if (
			wp_script_is(
				self::FRONTEND_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::FRONTEND_SCRIPT
			);
			return;
		}

		foreach (
			array_keys(
				self::FRONTEND_SCRIPTS
			) as $handle
		) {

			if (
				wp_script_is(
					$handle,
					'registered'
				)
			) {
				wp_enqueue_script(
					$handle
				);
			}
		}
	}

	/**
	 * Enqueue legacy inline product/card presentation only when an old renderer
	 * actually needs it. Modern Elementor-first checkouts stay on one CSS file.
	 *
	 * @return void
	 */
	public static function enqueue_legacy_frontend_style(): void {
		self::ensure_frontend_assets_registered();

		if ( wp_style_is( self::FRONTEND_STYLE, 'registered' ) ) {
			wp_enqueue_style( self::FRONTEND_STYLE );
		}

		if ( wp_style_is( self::LEGACY_FRONTEND_STYLE, 'registered' ) ) {
			wp_enqueue_style( self::LEGACY_FRONTEND_STYLE );
		}
	}

	/**
	 * Enqueue abandoned tracking on native WooCommerce checkout pages.
	 *
	 * @return void
	 */
	public function enqueue_native_abandoned_checkout_assets(): void {

		if ( ! $this->is_abandoned_checkout_enabled() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}

		$relative_path = 'src/js/frontend/abandoned-native.js';
		$absolute_path = EILMO_CF_PATH . 'assets/' . $relative_path;

		if ( ! file_exists( $absolute_path ) ) {
			return;
		}

		wp_register_script(
			self::NATIVE_ABANDONED_SCRIPT,
			EILMO_CF_ASSETS_URL . $relative_path,
			array(),
			$this->get_asset_version( $relative_path ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);

		$items = array();
		$subtotal = 0;
		$total = 0;

		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = absint( $cart_item['product_id'] ?? 0 );
				$variation_id = absint( $cart_item['variation_id'] ?? 0 );
				$quantity = absint( $cart_item['quantity'] ?? 0 );

				if ( $product_id > 0 && $quantity > 0 ) {
					$items[] = array(
						'product_id' => $product_id,
						'variation_id' => $variation_id,
						'quantity' => $quantity,
					);
				}
			}

			$subtotal = (float) WC()->cart->get_subtotal();
			$total = (float) WC()->cart->get_total( 'edit' );
		}

		wp_localize_script(
			self::NATIVE_ABANDONED_SCRIPT,
			'eilmoCfNativeAbandoned',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action' => self::ABANDONED_AJAX_ACTION,
				'nonce' => wp_create_nonce( self::ABANDONED_NONCE_ACTION ),
				'debounce' => self::ABANDONED_DEBOUNCE,
				'items' => $items,
				'subtotal' => $subtotal,
				'total' => $total,
			)
		);

		wp_enqueue_script( self::NATIVE_ABANDONED_SCRIPT );
	}

	/**
	 * Enqueue shared Eilmo admin assets.
	 *
	 * @return void
	 */
	public static function enqueue_admin(): void {

		self::ensure_admin_assets_registered();

		if (
			wp_style_is(
				self::ADMIN_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				self::ADMIN_STYLE
			);
		}

		if (
			wp_script_is(
				self::ADMIN_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::ADMIN_SCRIPT
			);
		}
	}

	/**
	 * Determine whether current admin screen belongs
	 * to Eilmo Checkout.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 *
	 * @return bool
	 */
	private function is_eilmo_admin_screen(
		string $hook_suffix
	): bool {

		if (
			'toplevel_page_eilmo-checkout' ===
				$hook_suffix
		) {
			return true;
		}

		/*
		 * Prefer page query parameter because submenu
		 * hook suffixes can vary.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'page'
				]
			)
		) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$page =
			sanitize_key(
				wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
					$_GET[
						'page'
					]
				)
			);

		return in_array(
			$page,
			self::ADMIN_PAGE_SLUGS,
			true
		);
	}

	/**
	 * Determine whether current screen is the main
	 * Eilmo Dashboard page.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 *
	 * @return bool
	 */
	private function is_dashboard_screen(
		string $hook_suffix
	): bool {

		if (
			'toplevel_page_' .
			self::DASHBOARD_PAGE_SLUG ===
				$hook_suffix
		) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'page'
				]
			)
		) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$page =
			sanitize_key(
				wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
					$_GET[
						'page'
					]
				)
			);

		return (
			self::DASHBOARD_PAGE_SLUG ===
				$page
		);
	}

	/**
	 * Determine whether current screen is the main
	 * Eilmo Settings page.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 *
	 * @return bool
	 */
	private function is_settings_screen(
		string $hook_suffix
	): bool {

		if (
			false !== strpos( $hook_suffix, self::SETTINGS_PAGE_SLUG ) ||
			false !== strpos( $hook_suffix, self::OFFERS_SETTINGS_PAGE_SLUG )
		) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'page'
				]
			)
		) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$page =
			sanitize_key(
				wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
					$_GET[
						'page'
					]
				)
			);

		return in_array(
			$page,
			array(
				self::SETTINGS_PAGE_SLUG,
				self::OFFERS_SETTINGS_PAGE_SLUG,
			),
			true
		);
	}

	/**
	 * Determine whether current admin screen is a WooCommerce order list or
	 * editor. The list needs the shared column styles before its first paint.
	 *
	 * Supports:
	 *
	 * - WooCommerce HPOS order screens.
	 * - Legacy shop_order post screens.
	 *
	 * @return bool
	 */
	private function is_order_screen(): bool {
		$screen = function_exists( 'get_current_screen' )
			? get_current_screen()
			: null;

		if ( $screen ) {
			if ( in_array(
				(string) $screen->id,
				array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ),
				true
			) ) {
				return true;
			}

			if ( 'shop_order' === (string) ( $screen->post_type ?? '' ) ) {
				return true;
			}
		}

		if (
			class_exists(
				'\Automattic\WooCommerce\Utilities\OrderUtil'
			) &&
			method_exists(
				'\Automattic\WooCommerce\Utilities\OrderUtil',
				'is_order_edit_screen'
			)
		) {
			return (bool)
				\Automattic\WooCommerce\Utilities\OrderUtil
					::is_order_edit_screen();
		}

		return false;
	}

	/**
	 * Determine whether the current screen edits a WooCommerce product.
	 *
	 * The shared stylesheet supplies only scoped Eilmo variation-field styles
	 * on this screen; settings JavaScript remains disabled.
	 *
	 * @return bool
	 */
	private function is_product_edit_screen(): bool {

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return 'product' === (string) ( $screen->post_type ?? '' ) &&
			in_array(
				(string) ( $screen->base ?? '' ),
				array( 'post', 'post-new' ),
				true
			);
	}

	/**
	 * Determine whether Abandoned Checkout is enabled.
	 *
	 * The frontend tracking script is not registered
	 * when this feature is disabled.
	 *
	 * @return bool
	 */
	private function is_abandoned_checkout_enabled(): bool {

		$settings =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$settings
			)
		) {
			return false;
		}

		$general =
			isset(
				$settings[
					'general'
				]
			) &&
			is_array(
				$settings[
					'general'
				]
			)
				? $settings[
					'general'
				]
				: array();

		return (
			'yes' ===
				(
					$general[
						'abandoned_checkout'
					] ??
						'no'
				)
		);
	}

	/**
	 * Ensure frontend assets are registered.
	 *
	 * @return void
	 */
	private static function ensure_frontend_assets_registered(): void {

		if (
			self::$frontend_registered
		) {
			return;
		}

		$assets =
			new self();

		$assets->register_frontend_assets();
	}

	/**
	 * Ensure admin assets are registered.
	 *
	 * @return void
	 */
	private static function ensure_admin_assets_registered(): void {

		if (
			self::$admin_registered
		) {
			return;
		}

		$assets =
			new self();

		$assets->register_admin_assets();
	}

	/**
	 * Get asset version.
	 *
	 * Uses file modification time during development
	 * so browser cache is automatically refreshed.
	 *
	 * Falls back to plugin version.
	 *
	 * @param string $relative_path Relative asset path.
	 *
	 * @return string
	 */
	private function get_asset_version(
		string $relative_path
	): string {

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			ltrim(
				$relative_path,
				'/'
			);

		if (
			file_exists(
				$absolute_path
			)
		) {
			$modified_time =
				filemtime(
					$absolute_path
				);

			if (
				false !==
					$modified_time
			) {
				return (string)
					$modified_time;
			}
		}

		return EILMO_CF_VERSION;
	}

	/**
	 * Determine whether asset exists and contains code.
	 *
	 * @param string $relative_path Relative asset path.
	 *
	 * @return bool
	 */
	private function asset_has_content(
		string $relative_path
	): bool {

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			ltrim(
				$relative_path,
				'/'
			);

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return false;
		}

		$file_size =
			filesize(
				$absolute_path
			);

		return (
			false !== $file_size &&
			$file_size > 0
		);
	}
}
