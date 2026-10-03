<?php
/**
 * Cart Drawer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\CartDrawer;

use EilmoCheckout\Licensing\LicenseManager;

use EilmoCheckout\Access\CheckoutAccess;
use EilmoCheckout\Admin\QuickCheckoutSettings;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Rendering\CheckoutRenderer;
use WC_Product;

\defined( 'ABSPATH' ) || exit;

/**
 * Boots the reusable WooCommerce Cart Drawer and Cart Checkout.
 */
final class CartDrawer implements RegistrableInterface {

	/**
	 * Cart Drawer stylesheet handle.
	 */
	private const STYLE_HANDLE =
		'eilmo-cf-cart-drawer';

	/**
	 * Cart Drawer stylesheet path.
	 */
	private const STYLE_PATH =
		'src/css/frontend/cart-drawer.css';

	/**
	 * Cart Drawer script handle.
	 */
	private const SCRIPT_HANDLE =
		'eilmo-cf-cart-drawer';

	/**
	 * Cart Drawer script path.
	 */
	private const SCRIPT_PATH =
		'src/js/frontend/cart-drawer.js';

	/**
	 * Cart Checkout stylesheet handle.
	 */
	private const CHECKOUT_STYLE_HANDLE =
		'eilmo-cf-cart-checkout';

	/**
	 * Cart Checkout stylesheet path.
	 */
	private const CHECKOUT_STYLE_PATH =
		'src/css/frontend/cart-checkout.css';

	/**
	 * Cart Checkout script handle.
	 */
	private const CHECKOUT_SCRIPT_HANDLE =
		'eilmo-cf-cart-checkout';

	/**
	 * Cart Checkout script path.
	 */
	private const CHECKOUT_SCRIPT_PATH =
		'src/js/frontend/cart-checkout.js';

	/**
	 * Refresh AJAX action.
	 */
	private const REFRESH_ACTION =
		'eilmo_cf_cart_drawer_refresh';

	/**
	 * Quantity AJAX action.
	 */
	private const QUANTITY_ACTION =
		'eilmo_cf_cart_drawer_quantity';

	/**
	 * Remove AJAX action.
	 */
	private const REMOVE_ACTION =
		'eilmo_cf_cart_drawer_remove';

	/**
	 * Cart Checkout AJAX action.
	 */
	private const CHECKOUT_ACTION =
		'eilmo_cf_cart_drawer_checkout';

	/**
	 * Cart Checkout completion AJAX action.
	 */
	private const CHECKOUT_COMPLETE_ACTION =
		'eilmo_cf_cart_drawer_checkout_complete';

	/**
	 * AJAX nonce action.
	 */
	private const NONCE_ACTION =
		'eilmo_cf_cart_drawer';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'enqueue_assets',
			),
			30
		);

		add_action(
			'wp_footer',
			array(
				$this,
				'render',
			),
			40
		);

		$this->register_ajax_action(
			self::REFRESH_ACTION,
			'ajax_refresh'
		);

		$this->register_ajax_action(
			self::QUANTITY_ACTION,
			'ajax_update_quantity'
		);

		$this->register_ajax_action(
			self::REMOVE_ACTION,
			'ajax_remove_item'
		);

		$this->register_ajax_action(
			self::CHECKOUT_ACTION,
			'ajax_checkout'
		);

		$this->register_ajax_action(
			self::CHECKOUT_COMPLETE_ACTION,
			'ajax_checkout_complete'
		);
	}

	/**
	 * Enqueue global Cart Drawer and Cart Checkout assets.
	 *
	 * Cart Checkout reuses the normal Eilmo Checkout Flow frontend
	 * modules, so those assets are loaded here before the bridge script.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if (
			is_admin() ||
			! $this->woocommerce_is_available() ||
			( function_exists( 'is_order_received_page' ) && is_order_received_page() )
		) {
			return;
		}

		/*
		 * Cart Checkout is not a second checkout implementation.
		 * It reuses the existing Checkout Flow modules and checkout.js.
		 */
		Assets::enqueue_frontend();

		$this->enqueue_style(
			self::STYLE_HANDLE,
			self::STYLE_PATH,
			array()
		);

		$this->enqueue_style(
			self::CHECKOUT_STYLE_HANDLE,
			self::CHECKOUT_STYLE_PATH,
			wp_style_is(
				Assets::FRONTEND_STYLE,
				'enqueued'
			)
				? array(
					Assets::FRONTEND_STYLE,
				)
				: array()
		);

		$this->enqueue_script(
			self::SCRIPT_HANDLE,
			self::SCRIPT_PATH,
			array()
		);

		$checkout_dependencies =
			Assets::get_frontend_script_handles();

		if (
			wp_script_is(
				self::SCRIPT_HANDLE,
				'enqueued'
			)
		) {
			$checkout_dependencies[] =
				self::SCRIPT_HANDLE;
		}

		$this->enqueue_script(
			self::CHECKOUT_SCRIPT_HANDLE,
			self::CHECKOUT_SCRIPT_PATH,
			array_values(
				array_unique(
					$checkout_dependencies
				)
			)
		);

		if (
			! wp_script_is(
				self::SCRIPT_HANDLE,
				'enqueued'
			)
		) {
			return;
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'eilmoCfCartDrawer',
			array(
				'ajaxUrl' =>
					admin_url(
						'admin-ajax.php'
					),

				'nonce' =>
					wp_create_nonce(
						self::NONCE_ACTION
					),

				'actions' =>
					array(
						'refresh' =>
							self::REFRESH_ACTION,

						'quantity' =>
							self::QUANTITY_ACTION,

						'remove' =>
							self::REMOVE_ACTION,

						'checkout' =>
							self::CHECKOUT_ACTION,

						'checkoutComplete' =>
							self::CHECKOUT_COMPLETE_ACTION,
					),

				'i18n' =>
					array(
						'preparing' =>
							__(
								'Preparing checkout...',
								'eilmo-checkout-flow'
							),

						'checkoutError' =>
							__(
								'Unable to prepare checkout. Please try again.',
								'eilmo-checkout-flow'
							),

						'cartChanged' =>
							__(
								'Your cart changed while checkout was loading. Please review your cart and try again.',
								'eilmo-checkout-flow'
							),
					),
			)
		);
	}

	/**
	 * Render one global Cart Drawer and one Cart Checkout shell.
	 *
	 * @return void
	 */
	public function render(): void {

		if (
			is_admin() ||
			! $this->woocommerce_is_available() ||
			( function_exists( 'is_order_received_page' ) && is_order_received_page() )
		) {
			return;
		}

		$this->ensure_cart();

		$drawer_renderer =
			new CartDrawerRenderer();

		$drawer_html =
			$drawer_renderer->render();

		if ( '' !== $drawer_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes all values internally.
			echo $drawer_html;
		}

		$checkout_renderer =
			new CartCheckoutRenderer();

		$checkout_html =
			$checkout_renderer->render();

		if ( '' !== $checkout_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes all values internally.
			echo $checkout_html;
		}
	}

	/**
	 * Refresh drawer cart data.
	 *
	 * @return void
	 */
	public function ajax_refresh(): void {

		$this->verify_ajax_request();
		$this->ensure_cart();

		$this->send_cart_state();
	}

	/**
	 * Update one cart item quantity.
	 *
	 * @return void
	 */
	public function ajax_update_quantity(): void {

		$this->verify_ajax_request();
		$this->ensure_cart();

		$cart = WC()->cart;

		if ( ! $cart ) {
			$this->send_cart_error(
				__(
					'Cart is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$cart_key =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'cart_key'
				]
			)
				? wc_clean(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST[
							'cart_key'
						]
					)
				)
				: '';

		$quantity =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'quantity'
				]
			)
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'quantity'
						]
					)
				)
				: 0;

		if (
			'' === $cart_key ||
			! isset(
				$cart->cart_contents[
					$cart_key
				]
			)
		) {
			$this->send_cart_error(
				__(
					'Cart item was not found.',
					'eilmo-checkout-flow'
				)
			);
		}

		$cart_item =
			$cart->cart_contents[
				$cart_key
			];

		$product =
			isset(
				$cart_item[
					'data'
				]
			) &&
			$cart_item[
				'data'
			] instanceof WC_Product
				? $cart_item[
					'data'
				]
				: null;

		if ( ! $product ) {
			$this->send_cart_error(
				__(
					'Product is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		if ( $quantity <= 0 ) {
			$cart->remove_cart_item(
				$cart_key
			);
		} else {
			$quantity =
				$this->normalize_quantity(
					$product,
					$quantity
				);

			$updated =
				$cart->set_quantity(
					$cart_key,
					$quantity,
					true
				);

			if ( false === $updated ) {
				$this->send_cart_error(
					__(
						'Unable to update the cart item.',
						'eilmo-checkout-flow'
					)
				);
			}
		}

		$cart->calculate_totals();
		$cart->set_session();

		$this->send_cart_state();
	}

	/**
	 * Remove one item from the cart.
	 *
	 * @return void
	 */
	public function ajax_remove_item(): void {

		$this->verify_ajax_request();
		$this->ensure_cart();

		$cart = WC()->cart;

		if ( ! $cart ) {
			$this->send_cart_error(
				__(
					'Cart is not available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$cart_key =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'cart_key'
				]
			)
				? wc_clean(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST[
							'cart_key'
						]
					)
				)
				: '';

		if (
			'' === $cart_key ||
			! isset(
				$cart->cart_contents[
					$cart_key
				]
			)
		) {
			$this->send_cart_error(
				__(
					'Cart item was not found.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $cart->remove_cart_item(
				$cart_key
			)
		) {
			$this->send_cart_error(
				__(
					'Unable to remove the cart item.',
					'eilmo-checkout-flow'
				)
			);
		}

		$cart->calculate_totals();
		$cart->set_session();

		$this->send_cart_state();
	}

	/**
	 * Build Cart Checkout from the authoritative WooCommerce cart.
	 *
	 * The normal CheckoutRenderer supplies all existing Eilmo modules,
	 * security fields, summary and final Order Now action. The visible
	 * product grid is replaced in the browser by compact cart adapters.
	 *
	 * @return void
	 */
	public function ajax_checkout(): void {

		$this->verify_ajax_request();
		$this->ensure_cart();

		$cart = WC()->cart;

		if (
			! $cart ||
			$cart->is_empty()
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'empty_cart',

					'message' =>
						__(
							'Your cart is empty.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$access =
			new CheckoutAccess();

		if ( $access->requires_login() ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'checkout_login_required',

					'message' =>
						__(
							'Please log in before placing your order.',
							'eilmo-checkout-flow'
						),

					'login_url' =>
						$access->get_login_url(),
				),
				401
			);
		}

		$items =
			$this->get_cart_checkout_items();

		if ( empty( $items ) ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_cart',

					'message' =>
						__(
							'Your cart does not contain any purchasable products.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$product_ids = array();

		foreach ( $items as $item ) {
			$product_id =
				absint(
					$item[
						'product_id'
					] ??
						0
				);

			if ( $product_id > 0 ) {
				$product_ids[] =
					$product_id;
			}
		}

		$product_ids =
			array_values(
				array_unique(
					$product_ids
				)
			);

		if ( empty( $product_ids ) ) {
			$this->send_cart_error(
				__(
					'Unable to prepare products for checkout.',
					'eilmo-checkout-flow'
				)
			);
		}

		$combo_offer_ids =
			$this->get_cart_checkout_combo_offer_ids();

		$cart_checkout_texts =
			$this->get_cart_checkout_texts();

		$checkout_settings =
			array(
				'product_id' =>
					$product_ids[0],

				'product_ids' =>
					$product_ids,

				'selection' =>
					'multiple',

				/*
				 * Runtime marker used only while this reusable
				 * WooCommerce Cart Checkout is being rendered.
				 */
				'cart_checkout' =>
					'yes',

				'show_combo_offers' =>
					empty( $combo_offer_ids )
						? 'no'
						: 'yes',

				'combo_offer_ids' =>
					$combo_offer_ids,

				'selected_combo_offer_ids' =>
					array(),

				'combo_selection_mode' =>
					'multiple',

				'combo_title' =>
					$cart_checkout_texts[
						'combo_heading'
					],

				'show_order_bumps' =>
					'yes',

				/*
				 * Empty means all globally enabled Order Bumps,
				 * matching normal CheckoutRenderer semantics.
				 */
				'order_bump_ids' =>
					array(),

				'show_summary' =>
					'yes',

				'desktop_summary_position' =>
					'right_sticky',

				'tablet_summary_position' =>
					'below',

				'mobile_summary_position' =>
					'bottom_drawer',

				'mobile_summary_collapsed' =>
					'yes',

				'order_button_label' =>
					$cart_checkout_texts[
						'order_button'
					],

				'order_processing_label' =>
					$cart_checkout_texts[
						'processing_label'
					],
			);

		/**
		 * Filters the normal CheckoutRenderer context used by Cart Checkout.
		 *
		 * @param array<string,mixed>          $checkout_settings Settings.
		 * @param array<int,array<string,int>> $items             Cart items.
		 */
		$checkout_settings =
			apply_filters(
				'eilmo_cf/cart_checkout/checkout_settings',
				$checkout_settings,
				$items
			);

		if ( ! is_array( $checkout_settings ) ) {
			$this->send_cart_error(
				__(
					'Unable to prepare checkout settings.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Cart Checkout labels are authoritative from
		 * Quick Checkout Text & Labels. Re-apply them after
		 * the public settings filter so Main Settings titles
		 * can never become a fallback for this checkout.
		 */
		$checkout_settings[
			'cart_checkout'
		] =
			'yes';

		$checkout_settings[
			'combo_title'
		] =
			$cart_checkout_texts[
				'combo_heading'
			];

		$checkout_settings[
			'order_button_label'
		] =
			$cart_checkout_texts[
				'order_button'
			];

		$checkout_settings[
			'order_processing_label'
		] =
			$cart_checkout_texts[
				'processing_label'
			];

		$settings_option_filter =
			'option_' .
			CheckoutSettings::OPTION_NAME;

		$settings_override =
			$this->get_cart_checkout_settings_override(
				$cart_checkout_texts
			);

		$summary_override =
			$this->get_cart_checkout_summary_override(
				$cart_checkout_texts
			);

		add_filter(
			$settings_option_filter,
			$settings_override,
			PHP_INT_MAX,
			1
		);

		add_filter(
			'eilmo_cf/summary_html',
			$summary_override,
			PHP_INT_MAX,
			3
		);

		$renderer =
			new CheckoutRenderer();

		try {
			$checkout_html =
				$renderer->render(
					$checkout_settings
				);
		} finally {
			remove_filter(
				$settings_option_filter,
				$settings_override,
				PHP_INT_MAX
			);

			remove_filter(
				'eilmo_cf/summary_html',
				$summary_override,
				PHP_INT_MAX
			);
		}

		$cart_checkout_renderer =
			new CartCheckoutRenderer();

		$selection_html =
			$cart_checkout_renderer
				->render_selection();

		$adapter_html =
			$cart_checkout_renderer
				->render_product_adapters(
					$items
				);

		if (
			'' === $checkout_html ||
			'' === $selection_html ||
			'' === $adapter_html
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'checkout_render_failed',

					'message' =>
						__(
							'Unable to prepare checkout. Please try again.',
							'eilmo-checkout-flow'
						),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'checkoutHtml' =>
					$checkout_html,

				'selectionHtml' =>
					$selection_html,

				'adapterHtml' =>
					$adapter_html,

				'items' =>
					$items,

				'count' =>
					absint(
						$cart->get_cart_contents_count()
					),
			)
		);
	}

	/**
	 * Clear WooCommerce cart after a confirmed Cart Checkout order.
	 *
	 * checkout.js emits eilmo:orderSuccess only after the server has
	 * confirmed order creation. cart-checkout.js calls this endpoint
	 * with keepalive/sendBeacon so redirect-based payment flows also
	 * clear the source WooCommerce cart.
	 *
	 * @return void
	 */
	public function ajax_checkout_complete(): void {

		$this->verify_ajax_request();
		$this->ensure_cart();

		$order_id =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'order_id'
				]
			)
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'order_id'
						]
					)
				)
				: 0;

		$order_key =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'order_key'
				]
			)
				? wc_clean(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST[
							'order_key'
						]
					)
				)
				: '';

		$order =
			$order_id > 0
				? wc_get_order(
					$order_id
				)
				: false;

		if (
			! $order ||
			'' === $order_key ||
			! hash_equals(
				(string) $order->get_order_key(),
				$order_key
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Unable to confirm the completed order.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$cart = WC()->cart;

		if ( $cart ) {
			$cart->empty_cart(
				true
			);
		}

		wp_send_json_success(
			array(
				'count' =>
					0,

				'isEmpty' =>
					true,
			)
		);
	}

	/**
	 * Register authenticated and guest AJAX hooks.
	 *
	 * @param string $action   AJAX action.
	 * @param string $callback Method name.
	 *
	 * @return void
	 */
	private function register_ajax_action(
		string $action,
		string $callback
	): void {

		add_action(
			'wp_ajax_' .
				$action,
			array(
				$this,
				$callback,
			)
		);

		add_action(
			'wp_ajax_nopriv_' .
				$action,
			array(
				$this,
				$callback,
			)
		);
	}

	/**
	 * Enqueue one stylesheet if it exists.
	 *
	 * @param string            $handle       Handle.
	 * @param string            $path         Asset path.
	 * @param array<int,string> $dependencies Dependencies.
	 *
	 * @return void
	 */
	private function enqueue_style(
		string $handle,
		string $path,
		array $dependencies
	): void {

		$absolute_path =
			EILMO_CF_PATH .
				'assets/' .
				$path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		wp_enqueue_style(
			$handle,
			EILMO_CF_ASSETS_URL .
				$path,
			$dependencies,
			$this->get_asset_version(
				$path
			)
		);
	}

	/**
	 * Enqueue one script if it exists.
	 *
	 * @param string            $handle       Handle.
	 * @param string            $path         Asset path.
	 * @param array<int,string> $dependencies Dependencies.
	 *
	 * @return void
	 */
	private function enqueue_script(
		string $handle,
		string $path,
		array $dependencies
	): void {

		$absolute_path =
			EILMO_CF_PATH .
				'assets/' .
				$path;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		wp_enqueue_script(
			$handle,
			EILMO_CF_ASSETS_URL .
				$path,
			$dependencies,
			$this->get_asset_version(
				$path
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
	 * Get normalized normal-product items from WooCommerce cart.
	 *
	 * Identical product/variation identities are combined because the
	 * existing Eilmo order payload models normal products by those fields.
	 *
	 * @return array<int,array<string,int>>
	 */
	private function get_cart_checkout_items(): array {

		$cart = WC()->cart;

		if ( ! $cart ) {
			return array();
		}

		$grouped = array();

		foreach (
			$cart->get_cart() as
			$cart_item
		) {

			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product =
				isset(
					$cart_item[
						'data'
					]
				) &&
				$cart_item[
					'data'
				] instanceof WC_Product
					? $cart_item[
						'data'
					]
					: null;

			if (
				! $product ||
				! $product->exists() ||
				! $product->is_purchasable()
			) {
				continue;
			}

			$product_id =
				absint(
					$cart_item[
						'product_id'
					] ??
						$product->get_id()
				);

			$variation_id =
				absint(
					$cart_item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				max(
					0,
					absint(
						$cart_item[
							'quantity'
						] ??
							0
					)
				);

			if (
				$product_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			if (
				$variation_id > 0 &&
				absint(
					$product->get_parent_id()
				) !== $product_id
			) {
				continue;
			}

			if (
				0 === $variation_id &&
				$product->is_type(
					'variable'
				)
			) {
				continue;
			}

			$key =
				$product_id .
					':' .
					$variation_id;

			if (
				! isset(
					$grouped[
						$key
					]
				)
			) {
				$grouped[
					$key
				] =
					array(
						'product_id' =>
							$product_id,

						'variation_id' =>
							$variation_id,

						'quantity' =>
							0,
					);
			}

			$grouped[
				$key
			][
				'quantity'
			] +=
				$quantity;
		}

		return array_values(
			$grouped
		);
	}

	/**
	 * Get Cart Checkout customer-facing texts.
	 *
	 * Cart Checkout intentionally uses ONLY Quick Checkout
	 * Text & Labels for the labels represented there.
	 *
	 * Resolution order:
	 *
	 * 1. Saved Quick Checkout value.
	 * 2. Quick Checkout default value.
	 * 3. Local compatibility fallback.
	 *
	 * Main Settings section titles are never used here.
	 *
	 * @return array<string,string>
	 */
	private function get_cart_checkout_texts(): array {

		$defaults =
			QuickCheckoutSettings::get_defaults();

		$stored =
			get_option(
				QuickCheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$default_texts =
			isset(
				$defaults[
					'texts'
				]
			) &&
			is_array(
				$defaults[
					'texts'
				]
			)
				? $defaults[
					'texts'
				]
				: array();

		$stored_texts =
			isset(
				$stored[
					'texts'
				]
			) &&
			is_array(
				$stored[
					'texts'
				]
			)
				? $stored[
					'texts'
				]
				: array();

		$fallbacks =
			array(
				'combo_heading' =>
					__(
						'Special Combo Offer',
						'eilmo-checkout-flow'
					),

				'order_bump_heading' =>
					__(
						'You May Also Like',
						'eilmo-checkout-flow'
					),

				'delivery_heading' =>
					__(
						'Delivery',
						'eilmo-checkout-flow'
					),

				'customer_heading' =>
					__(
						'Customer Information',
						'eilmo-checkout-flow'
					),

				'advance_payment_heading' =>
					__(
						'Payment Option',
						'eilmo-checkout-flow'
					),

				'payment_heading' =>
					__(
						'Payment Method',
						'eilmo-checkout-flow'
					),

				'coupon_heading' =>
					__(
						'Coupon',
						'eilmo-checkout-flow'
					),

				'summary_heading' =>
					__(
						'Order Summary',
						'eilmo-checkout-flow'
					),

				'total_label' =>
					__(
						'Total',
						'eilmo-checkout-flow'
					),

				'order_button' =>
					__(
						'Place Order',
						'eilmo-checkout-flow'
					),

				'processing_label' =>
					__(
						'Processing...',
						'eilmo-checkout-flow'
					),
			);

		$texts = array();

		foreach (
			$fallbacks as
				$key =>
				$fallback
		) {
			$default_value =
				sanitize_text_field(
					(string) (
						$default_texts[
							$key
						] ??
							$fallback
					)
				);

			if ( '' === trim( $default_value ) ) {
				$default_value = $fallback;
			}

			$saved_value =
				sanitize_text_field(
					(string) (
						$stored_texts[
							$key
						] ??
							''
					)
				);

			$texts[ $key ] =
				'' !== trim( $saved_value )
					? $saved_value
					: $default_value;
		}

		return $texts;
	}

	/**
	 * Create a temporary Main Settings option override.
	 *
	 * Existing renderers read their section titles from the
	 * main option. During Cart Checkout rendering only, swap
	 * those title values with Quick Checkout Text & Labels.
	 * No option is written and normal checkout rendering is
	 * unaffected after the callback is removed.
	 *
	 * @param array<string,string> $texts Cart Checkout texts.
	 *
	 * @return callable
	 */
	private function get_cart_checkout_settings_override(
		array $texts
	): callable {

		return static function ( $settings ) use ( $texts ) {

			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			$title_map =
				array(
					'combo_offers' =>
						'combo_heading',

					'order_bumps' =>
						'order_bump_heading',

					'delivery' =>
						'delivery_heading',

					'customer_information' =>
						'customer_heading',

					'advance_payment' =>
						'advance_payment_heading',

					'payment_methods' =>
						'payment_heading',

					'coupons' =>
						'coupon_heading',
				);

			foreach (
				$title_map as
					$section =>
					$text_key
			) {
				if (
					! isset(
						$settings[
							$section
						]
					) ||
					! is_array(
						$settings[
							$section
						]
					)
				) {
					$settings[
						$section
					] =
						array();
				}

				$settings[
					$section
				][
					'title'
				] =
					$texts[
						$text_key
					] ??
						'';
			}

			return $settings;
		};
	}

	/**
	 * Create a temporary Cart Checkout Summary HTML override.
	 *
	 * SummaryRenderer currently owns its heading and Grand Total
	 * labels directly. Restrict the replacement to the current
	 * Cart Checkout render and source both values from Quick
	 * Checkout Text & Labels.
	 *
	 * @param array<string,string> $texts Cart Checkout texts.
	 *
	 * @return callable
	 */
	private function get_cart_checkout_summary_override(
		array $texts
	): callable {

		return static function (
			$html,
			$amounts,
			$settings
		) use ( $texts ) {

			unset( $amounts );

			if (
				! is_string( $html ) ||
				! is_array( $settings ) ||
				'yes' !==
					(
						$settings[
							'cart_checkout'
						] ??
							'no'
					)
			) {
				return $html;
			}

			$summary_heading =
				esc_html(
					$texts[
						'summary_heading'
					] ??
						''
				);

			$total_label =
				esc_html(
					$texts[
						'total_label'
					] ??
						''
				);

			$updated =
				preg_replace_callback(
					'~(<h3\\b[^>]*class="[^"]*\\beilmo-cf-summary__title\\b[^"]*"[^>]*>).*?(</h3>)~is',
					static function ( $matches ) use ( $summary_heading ) {
						return
							$matches[1] .
							$summary_heading .
							$matches[2];
					},
					$html,
					1
				);

			if ( is_string( $updated ) ) {
				$html = $updated;
			}

			$updated =
				preg_replace_callback(
					'~(<div\\b[^>]*data-eilmo-summary-row="grand-total"[^>]*>.*?<span\\b[^>]*class="[^"]*\\beilmo-cf-summary__label\\b[^"]*"[^>]*>).*?(</span>)~is',
					static function ( $matches ) use ( $total_label ) {
						return
							$matches[1] .
							$total_label .
							$matches[2];
					},
					$html,
					1
				);

			if ( is_string( $updated ) ) {
				$html = $updated;
			}

			$updated =
				preg_replace_callback(
					'~(<span\\b[^>]*class="[^"]*\\beilmo-cf-summary-bar__label\\b[^"]*"[^>]*>).*?(</span>)~is',
					static function ( $matches ) use ( $total_label ) {
						return
							$matches[1] .
							$total_label .
							$matches[2];
					},
					$html,
					1
				);

			if ( is_string( $updated ) ) {
				$html = $updated;
			}

			return $html;
		};
	}

	/**
	 * Get globally enabled Combo IDs for the global Cart Checkout.
	 *
	 * Normal shortcode/Elementor checkouts explicitly assign Combo IDs.
	 * Cart Checkout has no per-widget source, so its default is all globally
	 * enabled reusable Combo Offers. A filter can narrow this list later.
	 *
	 * @return array<int,string>
	 */
	private function get_cart_checkout_combo_offer_ids(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$combo_defaults =
			isset(
				$defaults[
					'combo_offers'
				]
			) &&
			is_array(
				$defaults[
					'combo_offers'
				]
			)
				? $defaults[
					'combo_offers'
				]
				: array();

		$combo_stored =
			isset(
				$stored[
					'combo_offers'
				]
			) &&
			is_array(
				$stored[
					'combo_offers'
				]
			)
				? $stored[
					'combo_offers'
				]
				: array();

		$combo =
			array_replace_recursive(
				$combo_defaults,
				$combo_stored
			);

		if (
			'yes' !==
				(
					$combo[
						'enabled'
					] ??
						'no'
				)
		) {
			return array();
		}

		$offers =
			isset(
				$combo_stored[
					'offers'
				]
			) &&
			is_array(
				$combo_stored[
					'offers'
				]
			)
				? $combo_stored[
					'offers'
				]
				: (
					isset(
						$combo_defaults[
							'offers'
						]
					) &&
					is_array(
						$combo_defaults[
							'offers'
						]
					)
						? $combo_defaults[
							'offers'
						]
						: array()
				);

		$ids = array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			if (
				'yes' !==
					(
						$offer[
							'enabled'
						] ??
							'yes'
					)
			) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if (
				'' !== $id &&
				! in_array(
					$id,
					$ids,
					true
				)
			) {
				$ids[] = $id;
			}
		}

		/**
		 * Filters reusable Combo IDs exposed by global Cart Checkout.
		 *
		 * @param array<int,string> $ids Combo IDs.
		 */
		$ids =
			apply_filters(
				'eilmo_cf/cart_checkout/combo_offer_ids',
				$ids
			);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $ids as $id ) {
			$id =
				sanitize_key(
					(string) $id
				);

			if (
				'' !== $id &&
				! in_array(
					$id,
					$normalized,
					true
				)
			) {
				$normalized[] = $id;
			}
		}

		return $normalized;
	}

	/**
	 * Verify AJAX nonce and WooCommerce availability.
	 *
	 * @return void
	 */
	private function verify_ajax_request(): void {

		if (
			! check_ajax_referer(
				self::NONCE_ACTION,
				'nonce',
				false
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Your session has expired. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		if (
			! $this->woocommerce_is_available()
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'WooCommerce is not available.',
							'eilmo-checkout-flow'
						),
				),
				503
			);
		}
	}

	/**
	 * Send current cart state.
	 *
	 * @return void
	 */
	private function send_cart_state(): void {

		$renderer =
			new CartDrawerRenderer();

		wp_send_json_success(
			array(
				'itemsHtml' =>
					$renderer->render_items(),

				'subtotalHtml' =>
					$renderer->get_subtotal_html(),

				'count' =>
					$renderer->get_cart_count(),

				'isEmpty' =>
					$renderer->is_empty(),
			)
		);
	}

	/**
	 * Send AJAX cart error.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function send_cart_error(
		string $message
	): void {

		wp_send_json_error(
			array(
				'message' =>
					$message,
			),
			400
		);
	}

	/**
	 * Normalize requested quantity against product limits.
	 *
	 * @param WC_Product $product  Product.
	 * @param int        $quantity Quantity.
	 *
	 * @return int
	 */
	private function normalize_quantity(
		WC_Product $product,
		int $quantity
	): int {

		if (
			$product->is_sold_individually()
		) {
			return 1;
		}

		$minimum =
			max(
				1,
				absint(
					$product->get_min_purchase_quantity()
				)
			);

		$maximum =
			(int) $product->get_max_purchase_quantity();

		$quantity =
			max(
				$minimum,
				$quantity
			);

		if ( $maximum > 0 ) {
			$quantity =
				min(
					$maximum,
					$quantity
				);
		}

		return $quantity;
	}

	/**
	 * Ensure WooCommerce cart is loaded.
	 *
	 * @return void
	 */
	private function ensure_cart(): void {

		if (
			! function_exists(
				'WC'
			)
		) {
			return;
		}

		if (
			null === WC()->cart &&
			function_exists(
				'wc_load_cart'
			)
		) {
			wc_load_cart();
		}
	}

	/**
	 * Check WooCommerce availability.
	 *
	 * @return bool
	 */
	private function woocommerce_is_available(): bool {

		return (
			class_exists(
				'WooCommerce'
			) &&
			function_exists(
				'WC'
			)
		);
	}

	/**
	 * Get file-based asset version.
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
				$relative_path;

		if (
			file_exists(
				$absolute_path
			)
		) {
			$modified =
				filemtime(
					$absolute_path
				);

			if ( false !== $modified ) {
				return (string) $modified;
			}
		}

		return defined(
			'EILMO_CF_VERSION'
		)
			? (string) EILMO_CF_VERSION
			: '1.0.0';
	}
}
