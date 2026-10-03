<?php
/**
 * Main plugin class.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout;

use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutActions;
use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutScheduler;
use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutTracker;
use EilmoCheckout\Admin\Admin;
use EilmoCheckout\CartDrawer\CartDrawer;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Core\Installer;
use EilmoCheckout\Core\Modules;
use EilmoCheckout\Core\Requirements;
use EilmoCheckout\Coupons\CouponsModule;
use EilmoCheckout\Couriers\CourierModule;
use EilmoCheckout\DefaultCheckout\DefaultCheckoutIntegration;
use EilmoCheckout\Elementor\Elementor;
use EilmoCheckout\Orders\OrdersModule;
use EilmoCheckout\Products\ProductsModule;
use EilmoCheckout\QuickCheckout\QuickCheckout;
use EilmoCheckout\Security\SecurityModule;
use EilmoCheckout\Shortcodes\Shortcodes;
use EilmoCheckout\Tracking\TrackingModule;
use EilmoCheckout\WhatsApp\WhatsAppOrdering;
use EilmoCheckout\Licensing\LicenseManager;
use EilmoCheckout\Payment\Gateways\GatewayRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether plugin has already booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Module registry.
	 *
	 * @var Modules|null
	 */
	private $modules = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {

		if ( null === self::$instance ) {
			self::$instance =
				new self();
		}

		return self::$instance;
	}

	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public function boot(): void {

		if ( $this->booted ) {
			return;
		}

		$this->booted =
			true;

		$requirements =
			new Requirements();

		if (
			! $requirements->passes()
		) {
			$this->register_requirement_notices(
				$requirements->get_errors()
			);

			return;
		}

		$licensing = $this->register_licensing();
		$licensed = $licensing->is_usable();

		/*
		 * wp-admin always receives the small admin asset/menu layer so an
		 * unlicensed installation can display and submit its activation form.
		 * Frontend assets and every operational module remain dormant.
		 */
		if ( is_admin() ) {
			$this->register_assets();
			$this->register_admin( $licensed );
		}

		if ( ! $licensed ) {
			return;
		}

		$this->maybe_upgrade_database();

		/**
		 * Fires before Eilmo Checkout Flow initializes.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action(
			'eilmo_cf/before_boot',
			$this
		);

		if ( ! is_admin() ) {
			$this->register_assets();
		}
		$this->register_payment_gateways();
		$this->register_modules();
		$this->register_elementor();
		$this->register_quick_checkout();
		$this->register_cart_drawer();
		$this->register_default_checkout();
		$this->register_abandoned_checkout();
		$this->register_whatsapp_ordering();

		/**
		 * Fires after Eilmo Checkout Flow initializes.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action(
			'eilmo_cf/booted',
			$this
		);
	}

	/**
	 * Register cached license lifecycle services.
	 *
	 * License state never participates in customer checkout validation, so a
	 * license-server outage cannot block an order.
	 *
	 * @return void
	 */
	private function register_licensing(): LicenseManager {

		$licensing = new LicenseManager();

		$licensing->register();

		return $licensing;

	}

	/**
	 * Upgrade plugin-owned database tables.
	 *
	 * @return void
	 */
	private function maybe_upgrade_database(): void {

		if (
			! Installer::needs_upgrade()
		) {
			return;
		}

		Installer::install();

		do_action(
			'eilmo_cf/database_upgraded',
			Installer::DB_VERSION
		);
	}

	/**
	 * Register admin functionality.
	 *
	 * @return void
	 */
	private function register_admin( bool $licensed ): void {

		$admin =
			new Admin();

		$admin->register( $licensed );
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	private function register_assets(): void {

		$assets =
			new Assets();

		$assets->register();
	}

	/** Register real WooCommerce bKash/Nagad gateways. */
	private function register_payment_gateways(): void {
		( new GatewayRegistry() )->register();
	}

	/**
	 * Register plugin modules.
	 *
	 * Meta Tracking is registered as an independent
	 * module and is not dependent on Eilmo Checkout
	 * Flow being enabled.
	 *
	 * Security is also registered as an independent
	 * module because it protects Eilmo Checkout Flow,
	 * WooCommerce Classic Checkout and WooCommerce
	 * Checkout Block / Store API.
	 *
	 * Each module remains responsible for its own
	 * feature/master-setting checks.
	 *
	 * @return void
	 */
	private function register_modules(): void {

		$this->modules =
			new Modules();

		$this->modules
			->add(
				new ProductsModule()
			)
			->add(
				new CouponsModule()
			)
			->add(
				new OrdersModule()
			)
			->add(
				new SecurityModule()
			)
			->add(
				new CourierModule()
			)
			->add(
				new TrackingModule()
			)
			->add(
				new Shortcodes()
			);

		$this->modules
			->register_all();
	}

	/**
	 * Register Elementor integration.
	 *
	 * Registers the Eilmo Elementor category and all
	 * available Eilmo Elementor widgets.
	 *
	 * @return void
	 */
	private function register_elementor(): void {

		$elementor =
			new Elementor();

		$elementor->register();
	}

	/**
	 * Register Single Product Quick Checkout.
	 *
	 * @return void
	 */
	private function register_quick_checkout(): void {

		$quick_checkout =
			new QuickCheckout();

		$quick_checkout->register();
	}

	/**
	 * Register reusable Cart Drawer.
	 *
	 * The Cart Drawer is globally available on the
	 * frontend so any supported menu, header, Elementor,
	 * or custom cart trigger can open the same drawer.
	 *
	 * The drawer reads and updates the real WooCommerce
	 * cart and supports reusable right, left and bottom
	 * drawer positions.
	 *
	 * @return void
	 */
	private function register_cart_drawer(): void {

		$cart_drawer =
			new CartDrawer();

		$cart_drawer->register();
	}

	/**
	 * Register native WooCommerce checkout integration.
	 *
	 * WooCommerce remains authoritative for the checkout form,
	 * cart, gateways, order submission and order lifecycle.
	 *
	 * @return void
	 */
	private function register_default_checkout(): void {

		$default_checkout =
			new DefaultCheckoutIntegration();

		$default_checkout->register();
	}

	/**
	 * Register Abandoned Checkout services.
	 *
	 * Tracker:
	 * Stores meaningful checkout activity.
	 *
	 * Scheduler:
	 * Marks inactive checkout sessions abandoned.
	 *
	 * Actions:
	 * Allows admin to manually change status or delete
	 * a checkout record.
	 *
	 * Abandoned Checkout never creates or modifies
	 * WooCommerce orders.
	 *
	 * @return void
	 */
	private function register_abandoned_checkout(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		$tracker =
			new AbandonedCheckoutTracker();

		$tracker->register();

		$scheduler =
			new AbandonedCheckoutScheduler();

		$scheduler->register();

		$actions =
			new AbandonedCheckoutActions();

		$actions->register();
	}

	/**
	 * Register WhatsApp Ordering.
	 *
	 * WhatsApp Ordering is available on the frontend
	 * checkout when enabled from General settings and
	 * a WhatsApp number has been configured.
	 *
	 * It prepares the current checkout information and
	 * opens WhatsApp.
	 *
	 * It never creates or modifies WooCommerce orders.
	 *
	 * @return void
	 */
	private function register_whatsapp_ordering(): void {

		$whatsapp_ordering =
			new WhatsAppOrdering();

		$whatsapp_ordering->register();
	}

	/**
	 * Register requirement error notices.
	 *
	 * @param array<int,string> $errors Errors.
	 *
	 * @return void
	 */
	private function register_requirement_notices(
		array $errors
	): void {

		if ( empty( $errors ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $errors ) {

				foreach (
					$errors as $error
				) {
					?>
					<div class="notice notice-error">
						<p>
							<?php
							echo esc_html(
								$error
							);
							?>
						</p>
					</div>
					<?php
				}
			}
		);
	}

	/**
	 * Get registered modules.
	 *
	 * @return Modules|null
	 */
	public function modules(): ?Modules {

		return $this->modules;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}
}
