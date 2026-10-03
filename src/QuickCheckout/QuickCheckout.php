<?php
/**
 * Single Product Quick Checkout.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\QuickCheckout;

use EilmoCheckout\Licensing\LicenseManager;

use EilmoCheckout\Access\CheckoutAccess;
use EilmoCheckout\Admin\DefaultCheckoutSettings;
use EilmoCheckout\Admin\QuickCheckoutSettings;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CheckoutStyle;
use EilmoCheckout\Admin\WhatsAppSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Core\Assets;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Boots Quick Checkout on WooCommerce single-product pages.
 */
final class QuickCheckout implements RegistrableInterface {

	/**
	 * Quick Checkout stylesheet.
	 */
	private const STYLE_HANDLE =
		'eilmo-cf-quick-checkout';

	/**
	 * Quick Checkout stylesheet path.
	 */
	private const STYLE_PATH =
		'src/css/frontend/quick-checkout.css';

	/**
	 * Quick Checkout JavaScript.
	 */
	private const SCRIPT_HANDLE =
		'eilmo-cf-quick-checkout';

	/**
	 * Quick Checkout JavaScript path.
	 */
	private const SCRIPT_PATH =
		'src/js/frontend/quick-checkout.js';

	/**
	 * Quick WhatsApp JavaScript.
	 */
	private const WHATSAPP_SCRIPT_HANDLE =
		'eilmo-cf-quick-whatsapp';

	/**
	 * Quick WhatsApp JavaScript path.
	 */
	private const WHATSAPP_SCRIPT_PATH =
		'src/js/frontend/quick-whatsapp.js';

	/**
	 * Existing checkout submission script.
	 */
	private const CHECKOUT_SUBMIT_SCRIPT =
		'eilmo-cf-checkout-submit';

	/**
	 * Combo frontend handle.
	 */
	private const COMBO_SCRIPT =
		'eilmo-cf-combo';

	/**
	 * Order Bump frontend handle.
	 */
	private const ORDER_BUMP_SCRIPT =
		'eilmo-cf-order-bump';

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
				'register_assets',
			),
			20
		);

		add_action(
			'woocommerce_after_add_to_cart_form',
			array(
				$this,
				'render',
			),
			20
		);

		add_filter(
			'body_class',
			array(
				$this,
				'filter_body_class',
			)
		);

		add_filter(
			'woocommerce_add_to_cart_redirect',
			array(
				$this,
				'filter_direct_checkout_redirect',
			),
			100,
			2
		);
	}

	/**
	 * Add the product-page class that hides only the native submit button.
	 *
	 * The WooCommerce cart form, quantity control and variation selectors stay
	 * in the document so Order Now can use WooCommerce's normal add-to-cart
	 * validation and request.
	 *
	 * @param array<int, string> $classes Body classes.
	 *
	 * @return array<int, string>
	 */
	public function filter_body_class( array $classes ): array {

		if (
			! $this->is_enabled() ||
			! $this->is_single_product_page()
		) {
			return $classes;
		}

		$settings = $this->get_settings();
		$buttons = isset( $settings['buttons'] ) &&
			is_array( $settings['buttons'] )
				? $settings['buttons']
				: array();

		if ( 'yes' === (string) ( $buttons['hide_add_to_cart'] ?? 'no' ) ) {
			$classes[] = 'eilmo-cf-hide-native-add-to-cart';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Redirect an Eilmo Order Now native add-to-cart request to checkout.
	 *
	 * Normal Add to Cart requests are untouched. The request marker is added by
	 * quick-checkout.js only after product/variation validation succeeds.
	 *
	 * @param mixed      $url     Existing redirect URL.
	 * @param WC_Product $product Product being added.
	 *
	 * @return mixed
	 */
	public function filter_direct_checkout_redirect( $url, $product ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce owns and validates the native add-to-cart request.
		$direct_request = isset( $_REQUEST['eilmo_cf_direct_checkout'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request marker for a native WooCommerce action.
			? sanitize_key( wp_unslash( $_REQUEST['eilmo_cf_direct_checkout'] ) )
			: '';

		if (
			'yes' !== $direct_request ||
			! $this->is_enabled() ||
			! $this->direct_checkout_enabled() ||
			! function_exists( 'wc_get_checkout_url' )
		) {
			return $url;
		}

		return wc_get_checkout_url();
	}

	/**
	 * Register Quick Checkout assets.
	 *
	 * Assets are registered only when Quick Checkout
	 * is enabled on a WooCommerce single-product page.
	 *
	 * @return void
	 */
	public function register_assets(): void {

		if (
			! $this->is_single_product_page() ||
			! $this->is_enabled()
		) {
			return;
		}

		Assets::register_frontend();

		$this->register_style();

		$this->register_script(
			self::SCRIPT_HANDLE,
			self::SCRIPT_PATH
		);

		$this->register_script(
			self::WHATSAPP_SCRIPT_HANDLE,
			self::WHATSAPP_SCRIPT_PATH
		);

		$settings = $this->get_settings();
		$buttons = isset( $settings['buttons'] ) &&
			is_array( $settings['buttons'] )
				? $settings['buttons']
				: array();

		/*
		 * Enqueue the shared checkout stylesheet and Quick Checkout shell during
		 * wp_enqueue_scripts, before wp_head prints styles. Quick Checkout used to
		 * attach its theme variables only while rendering the product action, which
		 * can happen after styles have already been printed. That left shared
		 * components such as SummaryRenderer without the canonical theme tokens.
		 */
		if ( wp_style_is( Assets::FRONTEND_STYLE, 'registered' ) ) {
			wp_enqueue_style( Assets::FRONTEND_STYLE );
		}

		if ( wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
			$this->add_checkout_styles( $settings );
			$this->add_button_styles( $settings );
		}
	}

	/**
	 * Register Quick Checkout stylesheet.
	 *
	 * @return void
	 */
	private function register_style(): void {

		if (
			wp_style_is(
				self::STYLE_HANDLE,
				'registered'
			)
		) {
			return;
		}

		$absolute_path =
			EILMO_CF_PATH .
				'assets/' .
				self::STYLE_PATH;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		$dependencies =
			wp_style_is(
				Assets::FRONTEND_STYLE,
				'registered'
			)
				? array(
					Assets::FRONTEND_STYLE,
				)
				: array();

		wp_register_style(
			self::STYLE_HANDLE,
			EILMO_CF_ASSETS_URL .
				self::STYLE_PATH,
			$dependencies,
			$this->get_asset_version(
				self::STYLE_PATH
			)
		);
	}

	/**
	 * Register one Quick Checkout JavaScript asset.
	 *
	 * @param string $handle Handle.
	 * @param string $path   Relative asset path.
	 *
	 * @return void
	 */
	private function register_script(
		string $handle,
		string $path
	): void {

		if (
			wp_script_is(
				$handle,
				'registered'
			)
		) {
			return;
		}

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

		wp_register_script(
			$handle,
			EILMO_CF_ASSETS_URL .
				$path,
			array(),
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
	 * Render Quick Checkout.
	 *
	 * Checkout access is resolved in PHP before
	 * product-page action markup is rendered.
	 *
	 * Logged-out visitors in Logged-in Users Only mode
	 * therefore receive the login action immediately.
	 * JavaScript never changes Order Now into Login.
	 *
	 * @return void
	 */
	public function render(): void {

		if (
			! $this->is_enabled()
		) {
			return;
		}

		$product =
			$this->get_product();

		if (
			! $product ||
			! $this->is_supported_product(
				$product
			)
		) {
			return;
		}

		$settings =
			$this->get_settings();

		$buttons =
			isset(
				$settings[
					'buttons'
				]
			) &&
			is_array(
				$settings[
					'buttons'
				]
			)
				? $settings[
					'buttons'
				]
				: array();

		$show_order =
			'yes' ===
				(
					$buttons[
						'show_order_button'
					] ??
						'yes'
				);

		$show_whatsapp =
			'yes' ===
				(
					$buttons[
						'show_whatsapp_button'
					] ??
						'yes'
				) &&
			$this->whatsapp_is_available();

		if (
			! $show_order &&
			! $show_whatsapp
		) {
			return;
		}

		$order_requires_login =
			false;

		$login_url =
			'';

		$login_label =
			'';

		/*
		 * Reuse the existing Checkout Access policy.
		 *
		 * Do not duplicate checkout_access logic inside
		 * Quick Checkout.
		 */
		if (
			$show_order
		) {
			$checkout_access =
				new CheckoutAccess();

			$order_requires_login =
				$checkout_access
					->requires_login();

			if (
				$order_requires_login
			) {
				$login_url =
					$checkout_access
						->get_login_url();

				$login_label =
					$checkout_access
						->get_login_button_label();
			}
		}

		$this->enqueue_assets(
			$settings,
			$show_order,
			$show_whatsapp,
			$order_requires_login
		);

		$renderer =
			new QuickCheckoutRenderer(
				$product,
				$settings,
				$show_order,
				$show_whatsapp,
				$order_requires_login,
				$login_url,
				$login_label
			);

		$html =
			$renderer->render();

		if (
			'' ===
				$html
		) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its own output.
		echo $html;
	}

	/**
	 * Enqueue assets required by the current
	 * Quick Checkout configuration.
	 *
	 * @param array<string,mixed> $settings             Settings.
	 * @param bool                $show_order           Show Order Now.
	 * @param bool                $show_whatsapp        Show WhatsApp.
	 * @param bool                $order_requires_login Whether login is required.
	 *
	 * @return void
	 */
	private function enqueue_assets(
		array $settings,
		bool $show_order,
		bool $show_whatsapp,
		bool $order_requires_login
	): void {

		$this->register_assets();

		Assets::register_frontend();

		/*
		 * The Quick Checkout drawer should only be
		 * interactive when the customer is allowed
		 * to start the order flow.
		 */
		$can_run_order_action =
			$show_order &&
			! $order_requires_login;

		$can_open_checkout =
			$can_run_order_action &&
			! $this->direct_checkout_enabled();

		/*
		 * Shared Checkout Flow stylesheet.
		 */
		if (
			wp_style_is(
				Assets::FRONTEND_STYLE,
				'registered'
			)
		) {
			wp_enqueue_style(
				Assets::FRONTEND_STYLE
			);
		}

		/*
		 * Quick Checkout stylesheet.
		 *
		 * Required even when Order Now becomes the
		 * server-rendered Login action because both
		 * actions share the same visual component.
		 */
		if (
			wp_style_is(
				self::STYLE_HANDLE,
				'registered'
			)
		) {
			/* Already enqueued and themed during wp_enqueue_scripts. */
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		$content =
			isset(
				$settings[
					'content'
				]
			) &&
			is_array(
				$settings[
					'content'
				]
			)
				? $settings[
					'content'
				]
				: array();

		$combo_ids =
			$this->normalize_offer_ids(
				$content[
					'combo_offer_ids'
				] ??
					array()
			);

		$order_bump_ids =
			$this->normalize_offer_ids(
				$content[
					'order_bump_ids'
				] ??
					array()
			);

		/*
		 * Prefer the same production bundle used by shortcode and Elementor
		 * checkouts. Enqueuing its individual fallback controllers alongside the
		 * bundle would register two phone-verification state stores and two order
		 * submit listeners on mixed product/checkout pages.
		 */
		$use_frontend_bundle =
			$can_open_checkout &&
			wp_script_is(
				Assets::FRONTEND_SCRIPT,
				'registered'
			);

		if ( $use_frontend_bundle ) {
			wp_enqueue_script(
				Assets::FRONTEND_SCRIPT
			);
		}

		/*
		 * Combo Offers.
		 *
		 * There is no reason to load the interactive
		 * checkout module when login is required and
		 * the drawer cannot be opened.
		 */
		if (
			! $use_frontend_bundle &&
			$can_open_checkout &&
			'yes' ===
				(
					$content[
						'show_combo_offers'
					] ??
						'yes'
				) &&
			! empty(
				$combo_ids
			) &&
			wp_script_is(
				self::COMBO_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::COMBO_SCRIPT
			);
		}

		/*
		 * Special Offers / Order Bumps.
		 */
		if (
			! $use_frontend_bundle &&
			$can_open_checkout &&
			'yes' ===
				(
					$content[
						'show_order_bumps'
					] ??
						'yes'
				) &&
			! empty(
				$order_bump_ids
			) &&
			wp_script_is(
				self::ORDER_BUMP_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::ORDER_BUMP_SCRIPT
			);
		}

		/*
		 * Existing Eilmo order submission pipeline.
		 *
		 * Never load it for a login-only action.
		 */
		if (
			! $use_frontend_bundle &&
			$can_open_checkout &&
			wp_script_is(
				self::CHECKOUT_SUBMIT_SCRIPT,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::CHECKOUT_SUBMIT_SCRIPT
			);
		}

		/*
		 * Product-page Order Now behaviour.
		 *
		 * Logged-out visitors in Logged-in Users Only
		 * mode receive a normal login link instead, so
		 * this script is intentionally not enqueued. Direct Checkout also uses
		 * this adapter, but does not load the drawer submission modules above.
		 */
		if (
			$can_run_order_action &&
			wp_script_is(
				self::SCRIPT_HANDLE,
				'registered'
			)
		) {
			wp_enqueue_script(
				self::SCRIPT_HANDLE
			);
		}

		/*
		 * Direct single-product WhatsApp.
		 *
		 * WhatsApp remains independent from normal
		 * checkout access and the Quick Checkout drawer.
		 */
		if (
			$show_whatsapp &&
			wp_script_is(
				self::WHATSAPP_SCRIPT_HANDLE,
				'registered'
			)
		) {
			$this->localize_whatsapp_script(
				$settings
			);

			wp_enqueue_script(
				self::WHATSAPP_SCRIPT_HANDLE
			);
		}
	}


	/**
	 * Attach the shared checkout design tokens to the Quick Checkout root.
	 *
	 * All existing checkout renderers live beneath this root, therefore the CSS
	 * custom properties naturally flow into Customer, Delivery, Payment and
	 * Summary without duplicating component styles.
	 *
	 * @param array<string,mixed> $settings Quick Checkout settings.
	 *
	 * @return void
	 */
	private function add_checkout_styles( array $settings ): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			return;
		}

		$theme = $this->resolve_checkout_theme( $settings );

		/*
		 * Quick Checkout owns a private colour-token namespace. The shared
		 * checkout components inside the modal are bridged to these tokens by
		 * quick-checkout.css. This prevents a theme, Elementor widget or another
		 * checkout surface from overwriting the modal through the generic
		 * --eilmo-cf-theme-* variables.
		 */
		$variables = CheckoutStyle::get_css_variables( $theme, '--eilmo-qc-theme-' );
		if ( '' === $variables ) {
			return;
		}

		wp_add_inline_style(
			self::STYLE_HANDLE,
			'.eilmo-cf-quick-checkout,.eilmo-cf-quick-checkout-actions{' . $variables . '}'
		);
	}

	/**
	 * Resolve the visual theme used by Quick Checkout.
	 *
	 * @param array<string,mixed> $settings Quick Checkout settings.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve_checkout_theme( array $settings ): array {
		$style = isset( $settings['style'] ) && is_array( $settings['style'] )
			? $settings['style']
			: array();
		$source = sanitize_key( (string) ( $style['source'] ?? 'default' ) );

		/*
		 * Quick Checkout is intentionally independent from the campaign/form
		 * Global Checkout Style. `global` is accepted only as a legacy alias for
		 * the plugin default so an old option cannot pull CheckoutStyle::get()
		 * and leak a merchant's green/custom form palette into this modal.
		 */
		if ( 'custom' !== $source ) {
			return CheckoutStyle::get_defaults();
		}

		$custom = isset( $style['custom'] ) && is_array( $style['custom'] )
			? $style['custom']
			: array();

		return CheckoutStyle::sanitize( $custom, CheckoutStyle::get_defaults() );
	}

	/**
	 * Add configured product-page button appearance.
	 *
	 * Dynamic CSS variables are attached through the
	 * WordPress Styles API.
	 *
	 * No style="" attributes are rendered in markup and
	 * no frontend JavaScript is required for appearance.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function add_button_styles(
		array $settings
	): void {

		$theme = $this->resolve_checkout_theme( $settings );
		$theme_defaults = CheckoutStyle::get_defaults();
		$color = function ( string $key ) use ( $theme, $theme_defaults ): string {
			return $this->sanitize_css_color(
				$theme[ $key ] ?? $theme_defaults[ $key ],
				(string) $theme_defaults[ $key ]
			);
		};

		$order_background = $color( 'primary' );
		if ( 'yes' === (string) ( $theme['gradient_enabled'] ?? 'yes' ) ) {
			$order_background = sprintf(
				'linear-gradient(%1$ddeg,%2$s,%3$s)',
				$this->sanitize_integer_range( $theme['gradient_angle'] ?? 135, 0, 360, 135 ),
				$color( 'primary' ),
				$color( 'gradient_end' )
			);
		}

		$order = array(
			'background_color'       => $order_background,
			'text_color'             => $color( 'button_text' ),
			'border_color'           => $color( 'primary' ),
			'hover_background_color' => $color( 'primary_hover' ),
			'hover_text_color'       => $color( 'button_text' ),
			'hover_border_color'     => $color( 'primary_hover' ),
			'border_width'           => 1,
			'border_radius'          => $this->sanitize_integer_range( $theme['radius'] ?? 12, 0, 40, 12 ),
		);

		$whatsapp_settings = WhatsAppSettings::get();
		$whatsapp_style = isset( $whatsapp_settings['style'] ) && is_array( $whatsapp_settings['style'] )
			? $whatsapp_settings['style']
			: array();
		$whatsapp_defaults = WhatsAppSettings::get_defaults()['style'];
		$whatsapp_color = function ( string $key ) use ( $whatsapp_style, $whatsapp_defaults ): string {
			return $this->sanitize_css_color(
				$whatsapp_style[ $key ] ?? $whatsapp_defaults[ $key ],
				(string) $whatsapp_defaults[ $key ]
			);
		};
		$whatsapp = array(
			'background_color'       => $whatsapp_color( 'background' ),
			'text_color'             => $whatsapp_color( 'text' ),
			'border_color'           => $whatsapp_color( 'border' ),
			'hover_background_color' => $whatsapp_color( 'hover_background' ),
			'hover_text_color'       => $whatsapp_color( 'text' ),
			'hover_border_color'     => $whatsapp_color( 'hover_background' ),
			'border_width'           => 1,
			'border_radius'          => $this->sanitize_integer_range( $whatsapp_style['radius'] ?? 12, 0, 40, 12 ),
		);

		$css =
			sprintf(
				'.eilmo-cf-quick-checkout-actions{' .
					'--eilmo-qc-order-background:%1$s;' .
					'--eilmo-qc-order-color:%2$s;' .
					'--eilmo-qc-order-border-color:%3$s;' .
					'--eilmo-qc-order-hover-background:%4$s;' .
					'--eilmo-qc-order-hover-color:%5$s;' .
					'--eilmo-qc-order-hover-border-color:%6$s;' .
					'--eilmo-qc-order-border-width:%7$dpx;' .
					'--eilmo-qc-order-border-radius:%8$dpx;' .

					'--eilmo-qc-whatsapp-background:%9$s;' .
					'--eilmo-qc-whatsapp-color:%10$s;' .
					'--eilmo-qc-whatsapp-border-color:%11$s;' .
					'--eilmo-qc-whatsapp-hover-background:%12$s;' .
					'--eilmo-qc-whatsapp-hover-color:%13$s;' .
					'--eilmo-qc-whatsapp-hover-border-color:%14$s;' .
					'--eilmo-qc-whatsapp-border-width:%15$dpx;' .
					'--eilmo-qc-whatsapp-border-radius:%16$dpx;' .
				'}',
				$order[
					'background_color'
				],
				$order[
					'text_color'
				],
				$order[
					'border_color'
				],
				$order[
					'hover_background_color'
				],
				$order[
					'hover_text_color'
				],
				$order[
					'hover_border_color'
				],
				$order[
					'border_width'
				],
				$order[
					'border_radius'
				],
				$whatsapp[
					'background_color'
				],
				$whatsapp[
					'text_color'
				],
				$whatsapp[
					'border_color'
				],
				$whatsapp[
					'hover_background_color'
				],
				$whatsapp[
					'hover_text_color'
				],
				$whatsapp[
					'hover_border_color'
				],
				$whatsapp[
					'border_width'
				],
				$whatsapp[
					'border_radius'
				]
			);

		wp_add_inline_style(
			self::STYLE_HANDLE,
			$css
		);
	}

	/**
	 * Merge and validate one button appearance config.
	 *
	 * Settings are sanitized when saved, but this
	 * runtime validation protects old/manual option
	 * values as well.
	 *
	 * @param mixed $defaults Default configuration.
	 * @param mixed $saved    Saved configuration.
	 *
	 * @return array<string,mixed>
	 */
	private function merge_button_style(
		$defaults,
		$saved
	): array {

		$defaults =
			is_array(
				$defaults
			)
				? $defaults
				: array();

		$saved =
			is_array(
				$saved
			)
				? $saved
				: array();

		$style =
			array_replace(
				$defaults,
				$saved
			);

		return array(
			'background_color' =>
				$this->sanitize_css_color(
					$style[
						'background_color'
					] ??
						'#6d28d9',
					'#6d28d9'
				),

			'text_color' =>
				$this->sanitize_css_color(
					$style[
						'text_color'
					] ??
						'#ffffff',
					'#ffffff'
				),

			'border_color' =>
				$this->sanitize_css_color(
					$style[
						'border_color'
					] ??
						'#6d28d9',
					'#6d28d9'
				),

			'hover_background_color' =>
				$this->sanitize_css_color(
					$style[
						'hover_background_color'
					] ??
						'#5b21b6',
					'#5b21b6'
				),

			'hover_text_color' =>
				$this->sanitize_css_color(
					$style[
						'hover_text_color'
					] ??
						'#ffffff',
					'#ffffff'
				),

			'hover_border_color' =>
				$this->sanitize_css_color(
					$style[
						'hover_border_color'
					] ??
						'#5b21b6',
					'#5b21b6'
				),

			'border_width' =>
				$this->sanitize_integer_range(
					$style[
						'border_width'
					] ??
						1,
					0,
					10,
					1
				),

			'border_radius' =>
				$this->sanitize_integer_range(
					$style[
						'border_radius'
					] ??
						4,
					0,
					100,
					4
				),
		);
	}

	/**
	 * Sanitize CSS hexadecimal color.
	 *
	 * @param mixed  $value    Color.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_css_color(
		$value,
		string $fallback
	): string {

		$color =
			is_scalar(
				$value
			)
				? sanitize_hex_color(
					(string) $value
				)
				: null;

		if (
			is_string(
				$color
			) &&
			'' !==
				$color
		) {
			return strtolower(
				$color
			);
		}

		$fallback_color =
			sanitize_hex_color(
				$fallback
			);

		return (
			is_string(
				$fallback_color
			) &&
			'' !==
				$fallback_color
		)
			? strtolower(
				$fallback_color
			)
			: '#6d28d9';
	}

	/**
	 * Sanitize integer within range.
	 *
	 * @param mixed $value    Value.
	 * @param int   $minimum  Minimum.
	 * @param int   $maximum  Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function sanitize_integer_range(
		$value,
		int $minimum,
		int $maximum,
		int $fallback
	): int {

		if (
			! is_numeric(
				$value
			)
		) {
			return $fallback;
		}

		$value =
			(int) $value;

		return max(
			$minimum,
			min(
				$maximum,
				$value
			)
		);
	}

	/**
	 * Localize direct WhatsApp configuration.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function localize_whatsapp_script(
		array $settings
	): void {

		if (
			! wp_script_is(
				self::WHATSAPP_SCRIPT_HANDLE,
				'registered'
			)
		) {
			return;
		}

		$whatsapp =
			get_option(
				WhatsAppSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$whatsapp
			)
		) {
			$whatsapp =
				array();
		}

		$number =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$whatsapp[
						'number'
					] ??
						''
				)
			);

		if (
			! is_string(
				$number
			)
		) {
			$number =
				'';
		}

		$default_template =
			"Hello, I would like to place an order.\n\n" .
			"Customer: {customer_name}\n" .
			"Phone: {phone}\n" .
			"Email: {email}\n" .
			"Address: {address}\n\n" .
			"Products:\n" .
			"{products}\n\n" .
			"Delivery: {delivery}\n" .
			"Payment: {payment}\n" .
			"Total: {total}\n\n" .
			"Checkout Page: {checkout_url}";

		$message_template =
			isset(
				$whatsapp[
					'message_template'
				]
			)
				? sanitize_textarea_field(
					(string) $whatsapp[
						'message_template'
					]
				)
				: $default_template;

		if (
			'' ===
				trim(
					$message_template
				)
		) {
			$message_template =
				$default_template;
		}

		$texts =
			isset(
				$settings[
					'texts'
				]
			) &&
			is_array(
				$settings[
					'texts'
				]
			)
				? $settings[
					'texts'
				]
				: array();

		$variation_required =
			sanitize_text_field(
				(string) (
					$texts[
						'variation_required'
					] ??
						__(
							'Please select product options first.',
							'eilmo-checkout-flow'
						)
				)
			);

		if (
			'' ===
				$variation_required
		) {
			$variation_required =
				__(
					'Please select product options first.',
					'eilmo-checkout-flow'
				);
		}

		wp_localize_script(
			self::WHATSAPP_SCRIPT_HANDLE,
			'eilmoCfQuickWhatsApp',
			array(
				'number' =>
					$number,

				'messageTemplate' =>
					$message_template,

				'i18n' =>
					array(
						'variationRequired' =>
							$variation_required,

						'unavailable' =>
							__(
								'WhatsApp ordering is currently unavailable.',
								'eilmo-checkout-flow'
							),
					),
			)
		);
	}

	/**
	 * Is Quick Checkout globally enabled?
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		$default_general =
			isset(
				$defaults[
					'general'
				]
			) &&
			is_array(
				$defaults[
					'general'
				]
			)
				? $defaults[
					'general'
				]
				: array();

		$stored_general =
			isset(
				$stored[
					'general'
				]
			) &&
			is_array(
				$stored[
					'general'
				]
			)
				? $stored[
					'general'
				]
				: array();

		$general =
			array_replace(
				$default_general,
				$stored_general
			);

		return 'yes' ===
			(
				$general[
					'single_product_checkout'
				] ??
					'no'
			);
	}

	/**
	 * Get Quick Checkout settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {

		$settings = QuickCheckoutSettings::get_settings();
		$default_checkout = DefaultCheckoutSettings::get();
		$purchase_flow = isset( $default_checkout['purchase_flow'] ) &&
			is_array( $default_checkout['purchase_flow'] )
				? $default_checkout['purchase_flow']
				: array();

		if ( ! isset( $settings['buttons'] ) || ! is_array( $settings['buttons'] ) ) {
			$settings['buttons'] = array();
		}

		// Runtime-only bridge: these controls live on Default Checkout Settings.
		$settings['buttons']['hide_add_to_cart'] =
			'yes' === (string) ( $purchase_flow['hide_add_to_cart'] ?? 'no' )
				? 'yes'
				: 'no';
		$settings['buttons']['direct_checkout'] =
			'yes' === (string) ( $purchase_flow['direct_checkout'] ?? 'yes' )
				? 'yes'
				: 'no';

		return $settings;
	}

	/**
	 * Determine whether Order Now should bypass the Quick Checkout drawer.
	 *
	 * @return bool
	 */
	private function direct_checkout_enabled(): bool {

		$settings = DefaultCheckoutSettings::get();
		$purchase_flow = isset( $settings['purchase_flow'] ) &&
			is_array( $settings['purchase_flow'] )
				? $settings['purchase_flow']
				: array();

		return 'yes' === (string) (
			$purchase_flow['direct_checkout'] ?? 'yes'
		);
	}

	/**
	 * Determine whether direct WhatsApp ordering
	 * is available.
	 *
	 * Both conditions are required:
	 *
	 * - Global WhatsApp Ordering is enabled.
	 * - A WhatsApp number is configured.
	 *
	 * @return bool
	 */
	private function whatsapp_is_available(): bool {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		$default_general =
			isset(
				$defaults[
					'general'
				]
			) &&
			is_array(
				$defaults[
					'general'
				]
			)
				? $defaults[
					'general'
				]
				: array();

		$stored_general =
			isset(
				$stored[
					'general'
				]
			) &&
			is_array(
				$stored[
					'general'
				]
			)
				? $stored[
					'general'
				]
				: array();

		$general =
			array_replace(
				$default_general,
				$stored_general
			);

		if (
			'yes' !==
				(
					$general[
						'whatsapp_ordering'
					] ??
						'no'
				)
		) {
			return false;
		}

		$whatsapp =
			get_option(
				WhatsAppSettings::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$whatsapp
			)
		) {
			return false;
		}

		$number =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$whatsapp[
						'number'
					] ??
						''
				)
			);

		return (
			is_string(
				$number
			) &&
			'' !==
				$number
		);
	}

	/**
	 * Get current WooCommerce product.
	 *
	 * @return WC_Product|null
	 */
	private function get_product(): ?WC_Product {

		global $product;

		if (
			$product instanceof
				WC_Product
		) {
			return $product;
		}

		if (
			! function_exists(
				'wc_get_product'
			)
		) {
			return null;
		}

		$product_id =
			absint(
				get_queried_object_id()
			);

		if (
			$product_id <= 0
		) {
			return null;
		}

		$current =
			wc_get_product(
				$product_id
			);

		return (
			$current instanceof
				WC_Product
		)
			? $current
			: null;
	}

	/**
	 * Is supported single-product type?
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return bool
	 */
	private function is_supported_product(
		WC_Product $product
	): bool {

		if (
			! $product->is_type(
				array(
					'simple',
					'variable',
				)
			)
		) {
			return false;
		}

		if (
			! $product->is_purchasable()
		) {
			return false;
		}

		if (
			! $product->is_in_stock()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Is WooCommerce single-product page?
	 *
	 * @return bool
	 */
	private function is_single_product_page(): bool {

		return (
			function_exists(
				'is_product'
			) &&
			is_product()
		);
	}

	/**
	 * Normalize reusable Offer IDs.
	 *
	 * IDs are text keys rather than numeric database
	 * post IDs.
	 *
	 * @param mixed $ids IDs.
	 *
	 * @return array<int,string>
	 */
	private function normalize_offer_ids(
		$ids
	): array {

		if (
			is_string(
				$ids
			)
		) {
			$ids =
				preg_split(
					'/[\s,]+/',
					$ids
				);
		}

		if (
			! is_array(
				$ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach (
			$ids as
				$id
		) {

			if (
				! is_scalar(
					$id
				)
			) {
				continue;
			}

			$id =
				sanitize_key(
					(string) $id
				);

			if (
				'' ===
					$id ||
				in_array(
					$id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$id;
		}

		return $normalized;
	}

	/**
	 * Get asset version.
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
			$modified =
				filemtime(
					$absolute_path
				);

			if (
				false !==
					$modified
			) {
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
