<?php
/**
 * Meta browser/frontend tracking bridge.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Core\Assets;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Loads Meta Pixel and exposes safe frontend configuration.
 *
 * This bridge is independent of Eilmo Checkout Flow itself.
 * When Meta Tracking is enabled it can track native WooCommerce
 * product pages, classic checkout, Checkout Block/Store API and
 * Eilmo checkout pages.
 *
 * The Conversions API access token is never localized or printed
 * into frontend HTML.
 *
 * Purchase strategy behavior:
 *
 * Basic:
 * - Browser Purchase may be exposed on the WooCommerce
 *   Order Received / Thank You page.
 * - Browser and server Purchase share the same stable event ID.
 *
 * Advanced:
 * - Browser Purchase is disabled.
 * - Purchase is authoritative server-side only and is sent
 *   after a configured WooCommerce order status is reached.
 */
final class MetaBrowserPixel implements RegistrableInterface {

	/**
	 * Existing Eilmo tracking script handle.
	 */
	private const SCRIPT_HANDLE =
		'eilmo-cf-tracking';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! $this->frontend_tracking_needed() ) {
			return;
		}

		add_action(
			'wp_head',
			array(
				$this,
				'render_pixel_loader',
			),
			5
		);

		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'enqueue_tracking_script',
			),
			40
		);
	}

	/**
	 * Output Meta Pixel bootstrap only when Browser Pixel is enabled.
	 *
	 * PageView is intentionally not fired here. tracking.js sends
	 * enabled standard events and supplies matching event IDs when
	 * a browser/server pair must be deduplicated.
	 *
	 * @return void
	 */
	public function render_pixel_loader(): void {

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'browser_pixel'
			) ||
			! $this->browser_tracking_allowed()
		) {
			return;
		}

		$pixel_id =
			$this->get_pixel_id();

		if ( '' === $pixel_id ) {
			return;
		}

		?>
		<script id="eilmo-cf-meta-pixel-bootstrap">
		!function(f,b,e,v,n,t,s){
			if(f.fbq){return;}
			n=f.fbq=function(){
				n.callMethod?
					n.callMethod.apply(n,arguments):
					n.queue.push(arguments);
			};
			if(!f._fbq){f._fbq=n;}
			n.push=n;
			n.loaded=!0;
			n.version='2.0';
			n.queue=[];
			t=b.createElement(e);
			t.async=!0;
			t.src=v;
			s=b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t,s);
		}(
			window,
			document,
			'script',
			'https://connect.facebook.net/en_US/fbevents.js'
		);
		fbq(
			'init',
			<?php echo wp_json_encode( $pixel_id ); ?>
		);
		</script>
		<?php
	}

	/**
	 * Enqueue tracking.js and localize safe frontend config.
	 *
	 * This runs site-wide when Meta Tracking needs browser signals,
	 * even if the Eilmo checkout-flow feature is disabled.
	 *
	 * @return void
	 */
	public function enqueue_tracking_script(): void {

		if ( ! $this->frontend_tracking_needed() ) {
			return;
		}

		Assets::register_frontend();

		if (
			! wp_script_is(
				self::SCRIPT_HANDLE,
				'registered'
			)
		) {
			return;
		}

		$config = $this->get_frontend_config();

		/*
		 * The checkout bundle already contains tracking.js. Give it the
		 * configuration even when a shortcode enqueues it later in the page.
		 */
		if ( wp_script_is( Assets::FRONTEND_SCRIPT, 'registered' ) ) {
			wp_localize_script(
				Assets::FRONTEND_SCRIPT,
				'eilmoCfMeta',
				$config
			);

			if ( wp_script_is( Assets::FRONTEND_SCRIPT, 'enqueued' ) ) {
				return;
			}
		}

		/* Other storefront pages still need the standalone tracker. */
		wp_enqueue_script( self::SCRIPT_HANDLE );
		wp_localize_script( self::SCRIPT_HANDLE, 'eilmoCfMeta', $config );
	}

	/**
	 * Get safe frontend configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function get_frontend_config(): array {

		$settings =
			MetaTrackingSettings::get_settings();

		$events =
			isset( $settings['events'] ) &&
			is_array( $settings['events'] )
				? $settings['events']
				: array();

		$advanced =
			isset( $settings['advanced'] ) &&
			is_array( $settings['advanced'] )
				? $settings['advanced']
				: array();

		$browser_enabled =
			MetaTrackingSettings::feature_is_enabled(
				'browser_pixel'
			) &&
			$this->browser_tracking_allowed() &&
			'' !== $this->get_pixel_id();

		$server_enabled =
			MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			) &&
			$this->server_tracking_allowed();

		/*
		 * Browser Purchase belongs only to Basic strategy.
		 *
		 * Advanced Purchase is authoritative server-side
		 * and must never be triggered by tracking.js from
		 * the Thank You page or an Eilmo no-redirect flow.
		 */
		$browser_purchase_enabled =
			'yes' ===
				(
					$events['purchase'] ??
						'yes'
				) &&
			MetaTrackingSettings::uses_basic_purchase_tracking();

		return array(
			'enabled' =>
				$browser_enabled ||
				$server_enabled,

			'browser' =>
				array(
					'enabled' =>
						$browser_enabled,

					'pixelId' =>
						$browser_enabled
							? $this->get_pixel_id()
							: '',
				),

			'server' =>
				array(
					'enabled' =>
						$server_enabled,

					'ajaxUrl' =>
						admin_url(
							'admin-ajax.php'
						),

					'action' =>
						MetaServerEventController::AJAX_ACTION,

					'nonce' =>
						wp_create_nonce(
							MetaServerEventController::NONCE_ACTION
						),
				),

			'woo' =>
				array(
					'storeApiCartUrl' =>
						esc_url_raw(
							rest_url(
								'wc/store/v1/cart'
							)
						),
				),

			'currency' =>
				function_exists(
					'get_woocommerce_currency'
				)
					? get_woocommerce_currency()
					: '',

			'events' =>
				array(
					'pageView' =>
						'yes' ===
							(
								$events['page_view'] ??
									'yes'
							),

					'viewContent' =>
						'yes' ===
							(
								$events['view_content'] ??
									'yes'
							),

					'addToCart' =>
						'yes' ===
							(
								$events['add_to_cart'] ??
									'yes'
							),

					'initiateCheckout' =>
						'yes' ===
							(
								$events['initiate_checkout'] ??
									'yes'
							),

					'purchase' =>
						$browser_purchase_enabled,
				),

			'purchaseTracking' =>
				array(
					'strategy' =>
						MetaTrackingSettings::get_purchase_strategy(),

					'browserPurchase' =>
						$browser_purchase_enabled,
				),

			'viewContent' =>
				$this->get_product_view_context(),

			'nativeCheckout' =>
				$this->get_native_checkout_context(),

			'purchase' =>
				$this->get_order_received_purchase_context(),

			'debug' =>
				'yes' ===
					(
						$advanced['debug_mode'] ??
							'no'
					),
		);
	}

	/**
	 * Get WooCommerce product ViewContent context.
	 *
	 * @return array<string,mixed>
	 */
	private function get_product_view_context(): array {

		if (
			! function_exists( 'is_product' ) ||
			! is_product()
		) {
			return array();
		}

		$product_id =
			absint(
				get_queried_object_id()
			);

		if ( $product_id <= 0 ) {
			return array();
		}

		$product =
			wc_get_product(
				$product_id
			);

		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$value =
			$this->normalize_amount(
				$product->get_price()
			);

		$context =
			array(
				'content_type' =>
					'product',

				'content_ids' =>
					array(
						(string) $product_id,
					),

				'contents' =>
					array(
						array(
							'id' =>
								(string) $product_id,

							'quantity' =>
								1,
						),
					),

				'content_name' =>
					sanitize_text_field(
						$product->get_name()
					),

				'currency' =>
					function_exists(
						'get_woocommerce_currency'
					)
						? get_woocommerce_currency()
						: '',
			);

		if ( $value > 0 ) {
			$context['value'] =
				$value;
		}

		$context =
			apply_filters(
				'eilmo_cf/meta_tracking/browser_view_content',
				$context,
				$product
			);

		return is_array( $context )
			? $context
			: array();
	}

	/**
	 * Get a native WooCommerce checkout context.
	 *
	 * This is generated from WC()->cart and therefore works for
	 * classic checkout and Checkout Block pages without depending
	 * on any Eilmo checkout JavaScript.
	 *
	 * @return array<string,mixed>
	 */
	private function get_native_checkout_context(): array {

		if (
			! function_exists( 'is_checkout' ) ||
			! is_checkout() ||
			(
				function_exists(
					'is_order_received_page'
				) &&
				is_order_received_page()
			) ||
			(
				function_exists(
					'is_checkout_pay_page'
				) &&
				is_checkout_pay_page()
			) ||
			! function_exists( 'WC' ) ||
			! WC() ||
			! WC()->cart ||
			WC()->cart->is_empty()
		) {
			return array();
		}

		$content_ids =
			array();

		$contents =
			array();

		$num_items =
			0;

		foreach (
			WC()->cart->get_cart() as
				$cart_item
		) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product_id =
				absint(
					$cart_item['product_id'] ??
						0
				);

			$variation_id =
				absint(
					$cart_item['variation_id'] ??
						0
				);

			$quantity =
				max(
					0,
					(int) (
						$cart_item['quantity'] ??
							0
					)
				);

			$content_id =
				$variation_id > 0
					? $variation_id
					: $product_id;

			if (
				$content_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			$content_ids[] =
				(string) $content_id;

			$contents[] =
				array(
					'id' =>
						(string) $content_id,

					'quantity' =>
						$quantity,
				);

			$num_items +=
				$quantity;
		}

		if ( empty( $contents ) ) {
			return array();
		}

		$cart_hash =
			sanitize_text_field(
				(string) WC()->cart->get_cart_hash()
			);

		$session_id =
			'';

		if (
			WC()->session &&
			method_exists(
				WC()->session,
				'get_customer_unique_id'
			)
		) {
			$session_id =
				sanitize_text_field(
					(string) WC()->session
						->get_customer_unique_id()
				);
		} elseif (
			WC()->session &&
			method_exists(
				WC()->session,
				'get_customer_id'
			)
		) {
			$session_id =
				sanitize_text_field(
					(string) WC()->session
						->get_customer_id()
				);
		}

		$event_seed =
			$cart_hash .
			'|' .
			$session_id;

		if ( '' === trim( $event_seed, '|' ) ) {
			$event_seed =
				(string) get_current_user_id() .
				'|' .
				wp_salt(
					'nonce'
				);
		}

		$value =
			$this->normalize_amount(
				WC()->cart->get_cart_contents_total()
			) +
			$this->normalize_amount(
				WC()->cart->get_cart_contents_tax()
			);

		$context =
			array(
				'event_id' =>
					'eilmo_initiate_checkout_wc_' .
					substr(
						hash(
							'sha256',
							$event_seed
						),
						0,
						32
					),

				'content_type' =>
					'product',

				'content_ids' =>
					array_values(
						array_unique(
							$content_ids
						)
					),

				'contents' =>
					$contents,

				'num_items' =>
					$num_items,

				'value' =>
					round(
						$value,
						function_exists(
							'wc_get_price_decimals'
						)
							? wc_get_price_decimals()
							: 2
					),

				'currency' =>
					function_exists(
						'get_woocommerce_currency'
					)
						? sanitize_text_field(
							get_woocommerce_currency()
						)
						: '',
			);

		$context =
			apply_filters(
				'eilmo_cf/meta_tracking/native_checkout_context',
				$context
			);

		return is_array( $context )
			? $context
			: array();
	}

	/**
	 * Get authoritative browser Purchase context on a native
	 * WooCommerce Order Received page.
	 *
	 * Browser Purchase is available only in Basic Purchase mode.
	 *
	 * Advanced status-based Purchase is authoritative server-side,
	 * therefore no Purchase payload is exposed to JavaScript on the
	 * Thank You page while Advanced mode is active.
	 *
	 * Only website-checkout orders in an eligible placed/paid
	 * state are exposed. Admin-created/REST-imported orders are
	 * excluded by default.
	 *
	 * @return array<string,mixed>
	 */
	private function get_order_received_purchase_context(): array {

		/*
		 * Advanced Purchase tracking must never expose a
		 * browser Purchase payload.
		 */
		if (
			! MetaTrackingSettings::uses_basic_purchase_tracking()
		) {
			return array();
		}

		/*
		 * Do not expose Purchase data when the Purchase
		 * event itself is disabled.
		 */
		if (
			! MetaTrackingSettings::event_is_enabled(
				'purchase'
			)
		) {
			return array();
		}

		if (
			! function_exists(
				'is_order_received_page'
			) ||
			! is_order_received_page()
		) {
			return array();
		}

		$order_id =
			absint(
				get_query_var(
					'order-received'
				)
			);

		if ( $order_id <= 0 ) {
			return array();
		}

		$order =
			wc_get_order(
				$order_id
			);

		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		$order_key =
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
			isset( $_GET['key'] )
				? wc_clean(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						(string) $_GET['key']
					)
				)
				: '';

		if (
			'' === $order_key ||
			! hash_equals(
				(string) $order->get_order_key(),
				$order_key
			)
		) {
			return array();
		}

		$eligibility =
			new MetaOrderEligibility();

		if (
			! $eligibility->is_website_checkout_order(
				$order
			) ||
			! $eligibility->is_purchase_state_eligible(
				$order
			)
		) {
			return array();
		}

		$content_ids =
			array();

		$contents =
			array();

		$num_items =
			0;

		foreach (
			$order->get_items(
				'line_item'
			) as
				$item
		) {
			if (
				! $item instanceof
					WC_Order_Item_Product
			) {
				continue;
			}

			$quantity =
				max(
					0,
					(int) $item->get_quantity()
				);

			if ( $quantity <= 0 ) {
				continue;
			}

			$product_id =
				absint(
					$item->get_product_id()
				);

			$variation_id =
				absint(
					$item->get_variation_id()
				);

			$content_id =
				(string) (
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if (
				'' === $content_id ||
				'0' === $content_id
			) {
				continue;
			}

			$content_ids[] =
				$content_id;

			$contents[] =
				array(
					'id' =>
						$content_id,

					'quantity' =>
						$quantity,
				);

			$num_items +=
				$quantity;
		}

		$context =
			array(
				'event_id' =>
					'eilmo_purchase_' .
					$order->get_id(),

				'order_id' =>
					$order->get_id(),

				'content_type' =>
					'product',

				'content_ids' =>
					array_values(
						array_unique(
							$content_ids
						)
					),

				'contents' =>
					$contents,

				'num_items' =>
					$num_items,

				'value' =>
					(float) $order->get_total(),

				'currency' =>
					sanitize_text_field(
						(string) $order->get_currency()
					),
			);

		$context =
			apply_filters(
				'eilmo_cf/meta_tracking/browser_purchase',
				$context,
				$order
			);

		return is_array( $context )
			? $context
			: array();
	}

	/**
	 * Get configured Pixel / Dataset ID.
	 *
	 * @return string
	 */
	private function get_pixel_id(): string {

		$settings =
			MetaTrackingSettings::get_settings();

		$connection =
			isset( $settings['connection'] ) &&
			is_array( $settings['connection'] )
				? $settings['connection']
				: array();

		$pixel_id =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$connection['pixel_id'] ??
						''
				)
			);

		return is_string( $pixel_id )
			? $pixel_id
			: '';
	}

	/**
	 * Determine whether any frontend tracking bridge is needed.
	 *
	 * @return bool
	 */
	private function frontend_tracking_needed(): bool {

		if ( ! MetaTrackingSettings::is_enabled() ) {
			return false;
		}

		/*
		 * Browser Pixel requires the frontend bridge for
		 * PageView and all supported browser events.
		 */
		if (
			MetaTrackingSettings::feature_is_enabled(
				'browser_pixel'
			) &&
			$this->browser_tracking_allowed()
		) {
			return true;
		}

		/*
		 * Browser-originated CAPI events require tracking.js
		 * even when Browser Pixel itself is disabled.
		 *
		 * Purchase does not belong here because authoritative
		 * Purchase CAPI is handled directly from WC_Order by
		 * MetaTrackingService.
		 */
		if (
			MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			) &&
			$this->server_tracking_allowed() &&
			(
				MetaTrackingSettings::event_is_enabled(
					'view_content'
				) ||
				MetaTrackingSettings::event_is_enabled(
					'add_to_cart'
				) ||
				MetaTrackingSettings::event_is_enabled(
					'initiate_checkout'
				)
			)
		) {
			return true;
		}

		return false;
	}

	/**
	 * Allow consent/privacy integrations to block browser Pixel.
	 *
	 * @return bool
	 */
	private function browser_tracking_allowed(): bool {

		return (bool) apply_filters(
			'eilmo_cf/meta_tracking/browser_allowed',
			true
		);
	}

	/**
	 * Allow consent/privacy integrations to block first-party CAPI bridge.
	 *
	 * @return bool
	 */
	private function server_tracking_allowed(): bool {

		return (bool) apply_filters(
			'eilmo_cf/meta_tracking/frontend_server_allowed',
			true
		);
	}

	/**
	 * Normalize monetary value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return float
	 */
	private function normalize_amount(
		$value
	): float {

		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $value
		);
	}
}
