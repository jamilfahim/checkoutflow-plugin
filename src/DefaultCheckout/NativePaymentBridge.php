<?php
/**
 * Eilmo Payment Options and Live Fraud bridge for native WooCommerce checkout.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\DefaultCheckout;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Admin\DefaultCheckoutSettings;
use EilmoCheckout\AdvancePayment\Services\AdvanceCalculator;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Couriers\Services\FraudDecisionToken;
use EilmoCheckout\Payment\Services\PaymentProof;
use EilmoCheckout\Payment\Gateways\GatewaySettings;
use WC_Cart;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/** Native checkout adapter. */
final class NativePaymentBridge {

	/** Verified live-fraud result from the current checkout request. */
	private $verified_fraud = array();

	/** Prevent duplicate Payment Option output when a custom theme fires fallback hooks too. */
	private $controls_rendered = false;

	/** Register hooks. */
	public function register(): void {
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'force_bridge_gateway_posted_data' ), 999 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_checkout_block' ), 20, 2 );
		add_filter( 'body_class', array( $this, 'native_payment_body_class' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'decorate_gateway_title' ), 20, 2 );

		/*
		 * Keep WooCommerce's checkout form, order review and submit lifecycle intact.
		 * Only inject the Eilmo selector UI after native billing fields. The actual
		 * Payment Method radios below are WooCommerce gateway radios, not proxies.
		 */
		add_action( 'wp', array( $this, 'reset_checkout_presence' ), 5 );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_controls' ), 30 );
		add_action( 'woocommerce_after_checkout_shipping_form', array( $this, 'render_controls' ), 30 );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'render_controls' ), 30 );
		add_action( 'woocommerce_checkout_before_order_review', array( $this, 'render_controls' ), 5 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_native_controls_fragment' ), 25 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_checkout_payment_template' ), 30, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync_session_from_review' ), 5 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_native_totals_fragment' ), 30 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_full_payment_discount' ), 60, 1 );
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'render_payment_breakdown_rows' ), 25 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_order_meta' ), 30, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'after_order_processed' ), 30, 3 );
	}

	/** 2.1+: bKash/Nagad are real WooCommerce gateways; no internal bridge gateway is registered. */

	/**
	 * Keep WooCommerce's posted gateway synchronized with the visible Eilmo
	 * Payment Option. This runs only when the Eilmo controls marker was actually
	 * submitted, so a custom theme that omits the UI remains WooCommerce-owned.
	 *
	 * @param array<string,mixed> $data Checkout posted data.
	 * @return array<string,mixed>
	 */
	public function force_bridge_gateway_posted_data( array $data ): array {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() || ( ! $this->is_checkout_request() && ! wp_doing_ajax() ) ) {
			return $data;
		}

		/* Never invent Eilmo payment state when a theme did not render our UI. */
		if ( ! $this->controls_were_submitted() ) {
			return $data;
		}

		$type = $this->posted_payment_type();
		if ( '' === $type ) {
			return $data;
		}
		$gateway_id = '';
		if ( 'cash_on_delivery' === $type ) {
			$gateway_id = 'cod';
		} else {
			$gateway_id = sanitize_key( (string) ( $data['payment_method'] ?? '' ) );
			if ( 'cod' === $gateway_id ) {
				$gateway_id = '';
			}
			if ( '' === $gateway_id ) {
				$gateway_id = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_gateway_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( '' === $gateway_id ) {
				$method_key = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_method_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$gateway_id = GatewaySettings::gateway_id( $method_key );
			}
			if ( '' === $gateway_id ) {
				$gateway_id = $this->first_non_cod_gateway_id();
			}
		}

		if ( '' !== $gateway_id ) {
			$data['payment_method'] = $gateway_id;
		}
		return $data;
	}

	/**
	 * Checkout Block cannot expose the plugin's existing manual-payment fields
	 * or signed phone workflow safely. When either advanced Eilmo feature is on,
	 * render WooCommerce's classic form inside the block page on the frontend.
	 * The page can remain a Checkout Block in the editor.
	 */
	public function render_checkout_block( string $content, array $block ): string {
		if ( is_admin() || ! $this->is_checkout_request() || ! $this->advanced_enabled() ) {
			return $content;
		}
		if ( false !== strpos( $content, 'woocommerce-checkout' ) || false !== strpos( $content, 'checkout woocommerce-checkout' ) ) {
			return $content;
		}

		static $rendering = false;
		if ( $rendering ) {
			return $content;
		}

		$rendering = true;
		$classic = do_shortcode( '[woocommerce_checkout]' );
		$rendering = false;

		return '' !== trim( (string) $classic ) ? $classic : $content;
	}

	/** Whether the active theme/child theme owns checkout/payment.php. */
	private function theme_overrides_payment_template(): bool {
		$relative = 'woocommerce/checkout/payment.php';
		$stylesheet = function_exists( 'get_stylesheet_directory' ) ? trailingslashit( get_stylesheet_directory() ) . $relative : '';
		$template = function_exists( 'get_template_directory' ) ? trailingslashit( get_template_directory() ) . $relative : '';
		return ( '' !== $stylesheet && file_exists( $stylesheet ) ) || ( '' !== $template && file_exists( $template ) );
	}

	/**
	 * Use the Eilmo payment-actions template only when WooCommerce core would
	 * otherwise render checkout/payment.php. A theme/child-theme override always
	 * wins, preserving merchant template authority.
	 *
	 * @param string $template      Located template path.
	 * @param string $template_name Requested template name.
	 * @param string $template_path Template path prefix.
	 * @return string
	 */
	public function locate_checkout_payment_template( string $template, string $template_name, string $template_path ): string {
		if ( 'checkout/payment.php' !== $template_name || ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return $template;
		}

		if ( ! $this->is_checkout_request() && ! wp_doing_ajax() ) {
			return $template;
		}

		/* Theme/child-theme payment template remains authoritative. */
		if ( $this->theme_overrides_payment_template() ) {
			return $template;
		}

		/* Respect a payment.php supplied by another plugin as well. */
		if ( function_exists( 'WC' ) && WC() && is_callable( array( WC(), 'plugin_path' ) ) ) {
			$core = trailingslashit( WC()->plugin_path() ) . 'templates/' . $template_name;
			$resolved_template = realpath( $template );
			$resolved_core = realpath( $core );
			if ( $resolved_template && $resolved_core && $resolved_template !== $resolved_core ) {
				return $template;
			}
		}

		$custom = EILMO_CF_PATH . 'templates/default-checkout/payment-actions.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	/** Add a native-payment body class without replacing WooCommerce templates. */
	public function native_payment_body_class( array $classes ): array {
		if ( $this->is_checkout_request() && DefaultCheckoutSettings::payment_options_enabled() ) {
			$classes[] = 'eilmo-cf-native-payment-enabled';
		}
		return array_values( array_unique( $classes ) );
	}


	/**
	 * Add the gateway description to the native selector card without duplicating
	 * it inside the payment details panel. Values always come from WooCommerce /
	 * the gateway settings, so bKash, Nagad and future gateway copy stays dynamic.
	 *
	 * @param string $title      Gateway title HTML.
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public function decorate_gateway_title( string $title, string $gateway_id ): string {
		if ( ! $this->is_checkout_request() || ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return $title;
		}

		$gateway_id = sanitize_key( $gateway_id );
		if ( '' === $gateway_id || 'cod' === $gateway_id ) {
			return $title;
		}

		$description = '';
		if ( GatewaySettings::is_eilmo_gateway( $gateway_id ) ) {
			$config = GatewaySettings::get( $gateway_id );
			$description = sanitize_text_field( (string) ( $config['description'] ?? '' ) );
		}

		$title_html = '<span class="eilmo-cf-native-gateway-title">' . wp_kses_post( $title ) . '</span>';
		if ( '' !== trim( $description ) ) {
			$title_html .= '<small class="eilmo-cf-native-gateway-description">' . esc_html( $description ) . '</small>';
		}

		return $title_html;
	}

	/** Render the native, WooCommerce-backed Payment Option / Method selector. */
	public function render_controls(): void {
		if ( $this->controls_rendered || ! $this->is_checkout_request() || ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return;
		}
		$this->controls_rendered = true;
		$this->set_controls_active( true );
		echo $this->render_native_controls_fragment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Refresh only WooCommerce's real gateway engine after update_checkout.
	 * Payment Option radios and fraud/token state stay mounted, so a totals
	 * refresh never destroys the customer's current Eilmo selection.
	 */
	public function refresh_native_controls_fragment( array $fragments ): array {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() || ( ! $this->is_checkout_request() && ! wp_doing_ajax() ) ) {
			return $fragments;
		}
		if ( ! $this->theme_overrides_payment_template() ) {
			$fragments['[data-eilmo-native-gateway-host]'] = $this->render_native_gateway_host();
		}
		return $fragments;
	}

	/** Enqueue the isolated native adapter and optional native fraud controller. */
	public function enqueue_assets(): void {
		if ( ! $this->is_checkout_request() || ! $this->advanced_enabled() ) {
			return;
		}

		$css_relative = 'src/css/frontend/default-checkout-native.css';
		$css_absolute = EILMO_CF_PATH . 'assets/' . $css_relative;
		if ( file_exists( $css_absolute ) ) {
			wp_enqueue_style(
				'eilmo-cf-default-checkout-native',
				EILMO_CF_ASSETS_URL . $css_relative,
				array( 'woocommerce-general' ),
				(string) filemtime( $css_absolute )
			);
		}

		$js_relative = 'src/js/frontend/default-checkout-native.js';
		$js_absolute = EILMO_CF_PATH . 'assets/' . $js_relative;
		if ( file_exists( $js_absolute ) ) {
			wp_enqueue_script(
				'eilmo-cf-default-checkout-native',
				EILMO_CF_ASSETS_URL . $js_relative,
				array( 'jquery', 'wc-checkout' ),
				(string) filemtime( $js_absolute ),
				true
			);
			wp_localize_script(
				'eilmo-cf-default-checkout-native',
				'eilmoCFNativeCheckout',
				array(
					'codGatewayId'   => 'cod',
					'paymentOptions' => DefaultCheckoutSettings::payment_options_enabled() ? 'yes' : 'no',
					'liveFraud'      => DefaultCheckoutSettings::live_fraud_enabled() && CourierSettings::live_fraud_is_enabled() ? 'yes' : 'no',
				)
			);
		}

		if ( DefaultCheckoutSettings::live_fraud_enabled() && CourierSettings::live_fraud_is_enabled() ) {
			$fraud_relative = 'src/js/frontend/default-checkout-native-fraud.js';
			$fraud_absolute = EILMO_CF_PATH . 'assets/' . $fraud_relative;
			if ( file_exists( $fraud_absolute ) ) {
				wp_enqueue_script(
					'eilmo-cf-default-checkout-native-fraud',
					EILMO_CF_ASSETS_URL . $fraud_relative,
					array( 'jquery', 'eilmo-cf-default-checkout-native' ),
					(string) filemtime( $fraud_absolute ),
					true
				);
				$courier = CourierSettings::get_settings();
				$fraud = isset( $courier['live_fraud'] ) && is_array( $courier['live_fraud'] ) ? $courier['live_fraud'] : array();
				wp_localize_script(
					'eilmo-cf-default-checkout-native-fraud',
					'eilmoCFNativeFraud',
					array(
						'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
						'ajaxNonce'            => wp_create_nonce( 'eilmo_cf_frontend' ),
						'action'               => 'eilmo_cf_live_fraud_check',
						'debounce'             => 700,
						'paymentDisplayMode'   => 'conditional' === ( $fraud['payment_display_mode'] ?? 'always' ) ? 'conditional' : 'always',
						'checkingMessage'      => sanitize_text_field( (string) ( $fraud['checking_message'] ?? __( 'Checking delivery history…', 'eilmo-checkout-flow' ) ) ),
						'checkFailedMessage'   => __( 'Phone verification failed. Please try again.', 'eilmo-checkout-flow' ),
						'phoneRequiredMessage' => __( 'Enter a valid phone number to see available payment options.', 'eilmo-checkout-flow' ),
						'blockedMessage'       => __( 'Ordering is unavailable for this phone number.', 'eilmo-checkout-flow' ),
					)
				);
			}
		}
	}

	/** Build the full native selector fragment with WooCommerce gateway inputs. */
	private function render_native_controls_fragment(): string {
		$this->ensure_native_cart();
		$context = $this->checkout_context();
		$state = $this->native_payment_option_state();
		$available_types = isset( $state['available'] ) && is_array( $state['available'] ) ? $state['available'] : array();
		$payment_type = $this->normalize_payment_type( (string) ( $context['payment_type'] ?? '' ) );
		$gateways = $this->available_gateways();
		$cod = isset( $gateways['cod'] ) ? $gateways['cod'] : null;
		$non_cod = array();
		foreach ( $gateways as $gateway_id => $gateway ) {
			$gateway_id = sanitize_key( (string) $gateway_id );
			if ( '' !== $gateway_id && 'cod' !== $gateway_id ) {
				$non_cod[ $gateway_id ] = $gateway;
			}
		}

		/* WooCommerce is the gateway source of truth. Prime its real chosen gateway
		 * before the native #payment template renders. The real gateway radios and
		 * gateway-owned fields are rendered once in the left-side adapter. */
		$chosen_gateway = 'cash_on_delivery' === $payment_type && $cod
			? 'cod'
			: $this->selected_gateway_id( $non_cod );
		if ( '' === $chosen_gateway && ! empty( $non_cod ) ) {
			$chosen_gateway = sanitize_key( (string) array_key_first( $non_cod ) );
		}
		if ( '' !== $chosen_gateway && function_exists( 'WC' ) && WC() && WC()->session ) {
			WC()->session->set( 'chosen_payment_method', $chosen_gateway );
		}
		if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() && is_callable( array( WC()->payment_gateways(), 'set_current_gateway' ) ) ) {
			WC()->payment_gateways()->set_current_gateway( $gateways );
		}

		$advance = $this->native_advance_preview( $context );
		$full = $this->native_full_preview( $context );
		$settings = $this->payment_option_settings();
		$checkout_settings = $this->checkout_settings();
		$full_settings = isset( $checkout_settings['discounts']['full_payment'] ) && is_array( $checkout_settings['discounts']['full_payment'] )
			? $checkout_settings['discounts']['full_payment']
			: array();

		$cod_title = $cod && is_callable( array( $cod, 'get_title' ) ) ? wp_strip_all_tags( (string) $cod->get_title() ) : __( 'Cash on delivery', 'eilmo-checkout-flow' );
		$cod_desc = $cod && is_callable( array( $cod, 'get_description' ) ) ? wp_strip_all_tags( (string) $cod->get_description() ) : __( 'Pay with cash upon delivery.', 'eilmo-checkout-flow' );
		$advance_label = sanitize_text_field( (string) ( $settings['advance_label'] ?? __( 'Advance Payment', 'eilmo-checkout-flow' ) ) );
		$full_label = sanitize_text_field( (string) ( $full_settings['label'] ?? __( 'Full Payment', 'eilmo-checkout-flow' ) ) );
		$advance_badge = __( 'Less hassle', 'eilmo-checkout-flow' );
		$full_badge = $this->full_payment_badge( $full_settings, (float) ( $full['discount'] ?? 0 ) );

		/* Match the Campaign Checkout Payment Method header note. Keep the
		 * amount server-derived so the first paint already contains the correct
		 * copy; the native controller mirrors it instantly when the option changes. */
		$advance_pay_note = str_replace(
			'{amount}',
			$this->price_text( (float) ( $advance['pay_now'] ?? 0 ) ),
			\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' )
		);
		$full_pay_note = str_replace(
			'{amount}',
			$this->price_text( (float) ( $full['pay_now'] ?? 0 ) ),
			\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' )
		);
		$payment_method_pay_note = 'advance' === $payment_type ? $advance_pay_note : ( 'full' === $payment_type ? $full_pay_note : '' );

		ob_start();
		?>
		<div id="eilmo-cf-native-payment-controls" class="eilmo-cf-native-payment-controls" data-eilmo-native-payment-controls data-payment-type="<?php echo esc_attr( $payment_type ); ?>" data-gateway-mode="<?php echo esc_attr( $this->theme_overrides_payment_template() ? 'theme' : 'embedded' ); ?>">
			<input type="hidden" name="eilmo_cf_native_controls_present" value="1" data-eilmo-native-controls-present>
			<section class="eilmo-cf-native-payment-options" data-eilmo-native-payment-options>
				<div class="eilmo-cf-native-control-label eilmo-cf-native-payment-option-label" role="heading" aria-level="3"><?php esc_html_e( 'Payment Option', 'eilmo-checkout-flow' ); ?></div>
				<div class="eilmo-cf-native-option-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Payment Option', 'eilmo-checkout-flow' ); ?>">
					<?php if ( in_array( 'cash_on_delivery', $available_types, true ) && $cod ) : ?>
						<label class="eilmo-cf-native-option-card<?php echo 'cash_on_delivery' === $payment_type ? ' is-selected' : ''; ?>" data-eilmo-native-payment-option="cash_on_delivery">
							<input type="radio" class="eilmo-cf-native-visually-hidden" name="eilmo_cf_payment_type" value="cash_on_delivery" data-eilmo-native-payment-type <?php checked( 'cash_on_delivery', $payment_type ); ?>>
							<span class="eilmo-cf-native-option-copy"><strong><?php echo esc_html( $cod_title ); ?></strong><?php if ( '' !== trim( $cod_desc ) ) : ?><small><?php echo esc_html( $cod_desc ); ?></small><?php endif; ?></span>
							<span class="eilmo-cf-native-check" aria-hidden="true">✓</span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'advance', $available_types, true ) ) : ?>
						<label class="eilmo-cf-native-option-card<?php echo 'advance' === $payment_type ? ' is-selected' : ''; ?>" data-eilmo-native-payment-option="advance">
							<input type="radio" class="eilmo-cf-native-visually-hidden" name="eilmo_cf_payment_type" value="advance" data-eilmo-native-payment-type <?php checked( 'advance', $payment_type ); ?>>
							<span class="eilmo-cf-native-option-copy"><strong><?php echo esc_html( $advance_label ); ?></strong><small><?php echo esc_html( str_replace( '{amount}', $this->price_text( (float) ( $advance['pay_now'] ?? 0 ) ), \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' ) ) ); ?></small><em><?php echo esc_html( $advance_badge ); ?></em></span>
							<span class="eilmo-cf-native-check" aria-hidden="true">✓</span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'full', $available_types, true ) ) : ?>
						<label class="eilmo-cf-native-option-card<?php echo 'full' === $payment_type ? ' is-selected' : ''; ?>" data-eilmo-native-payment-option="full">
							<input type="radio" class="eilmo-cf-native-visually-hidden" name="eilmo_cf_payment_type" value="full" data-eilmo-native-payment-type <?php checked( 'full', $payment_type ); ?>>
							<span class="eilmo-cf-native-option-copy"><strong><?php echo esc_html( $full_label ); ?></strong><small><?php echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay the full amount in advance.' ) ); ?></small><?php if ( '' !== trim( $full_badge ) ) : ?><em><?php echo esc_html( $full_badge ); ?></em><?php endif; ?></span>
							<span class="eilmo-cf-native-check" aria-hidden="true">✓</span>
						</label>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( ! $this->theme_overrides_payment_template() ) : ?>
			<section
				class="eilmo-cf-native-payment-methods"
				data-eilmo-native-payment-methods
				data-advance-pay-note="<?php echo esc_attr( $advance_pay_note ); ?>"
				data-full-pay-note="<?php echo esc_attr( $full_pay_note ); ?>"
				<?php echo 'cash_on_delivery' === $payment_type ? 'hidden aria-hidden="true"' : ''; ?>
			>
				<div class="eilmo-cf-native-payment-methods-header">
					<div class="eilmo-cf-native-control-label eilmo-cf-native-payment-method-label" role="heading" aria-level="3"><?php esc_html_e( 'Payment Method', 'eilmo-checkout-flow' ); ?></div>
					<p class="eilmo-cf-native-payment-method-pay-note" data-eilmo-native-payment-method-pay-note<?php echo '' === $payment_method_pay_note ? ' hidden' : ''; ?>><?php echo esc_html( $payment_method_pay_note ); ?></p>
				</div>
				<?php echo $this->render_native_gateway_host( $gateways ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
			<?php endif; ?>

			<?php echo $this->native_totals_marker_html( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="eilmo_cf_native_fraud_token" value="" data-eilmo-native-fraud-token>
			<input type="hidden" name="eilmo_cf_native_payment_method" value="<?php echo esc_attr( $this->gateway_title( $chosen_gateway, $gateways ) ); ?>" data-eilmo-native-payment-method>
			<input type="hidden" name="eilmo_cf_native_payment_method_key" value="<?php echo esc_attr( $this->gateway_method_key( $chosen_gateway ) ); ?>" data-eilmo-native-payment-method-key>
			<input type="hidden" name="eilmo_cf_native_payment_source" value="<?php echo esc_attr( 'cod' === $chosen_gateway ? 'woocommerce_cod' : 'woocommerce_gateway' ); ?>" data-eilmo-native-payment-source>
			<input type="hidden" name="eilmo_cf_native_payment_gateway_id" value="<?php echo esc_attr( $chosen_gateway ); ?>" data-eilmo-native-payment-gateway-id>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the gateway host as a standalone checkout fragment.
	 *
	 * @param array<string,object>|null $gateways Optional preloaded gateways.
	 * @return string
	 */
	private function render_native_gateway_host( ?array $gateways = null ): string {
		$gateways = is_array( $gateways ) ? $gateways : $this->available_gateways();
		if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() && is_callable( array( WC()->payment_gateways(), 'set_current_gateway' ) ) ) {
			WC()->payment_gateways()->set_current_gateway( $gateways );
		}
		return '<div class="eilmo-cf-native-gateway-host" data-eilmo-native-gateway-host>' . $this->render_native_gateway_list( $gateways ) . '</div>';
	}

	/**
	 * Render WooCommerce's real gateway radios and gateway-owned payment fields
	 * directly in the left-side native adapter. Nothing is cloned or re-parented
	 * in JavaScript, so gateway scripts keep one stable DOM contract.
	 *
	 * @param array<string,object> $gateways Available WooCommerce gateways.
	 * @return string
	 */
	private function render_native_gateway_list( array $gateways ): string {
		if ( empty( $gateways ) ) {
			return '<p class="eilmo-cf-native-empty">' . esc_html__( 'No payment methods are currently available.', 'eilmo-checkout-flow' ) . '</p>';
		}

		ob_start();
		?>
		<ul class="wc_payment_methods payment_methods methods eilmo-cf-native-gateway-grid" data-eilmo-native-gateway-grid aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>">
			<?php foreach ( $gateways as $gateway ) : ?>
				<?php
				if ( ! is_object( $gateway ) ) {
					continue;
				}

				$gateway_id = isset( $gateway->id ) ? sanitize_key( (string) $gateway->id ) : '';
				if ( 'cod' === $gateway_id ) {
					/* Keep WooCommerce's real COD radio in the form for native checkout
					 * processing, but never expose it as a second Payment Method card.
					 * The Eilmo Payment Option card is the only visible COD selector. */
					ob_start();
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
					$cod_markup = (string) ob_get_clean();
					$cod_markup = preg_replace( '/<li\b/', '<li hidden aria-hidden="true" data-eilmo-native-cod-engine', $cod_markup, 1 );
					echo $cod_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output.
					continue;
				}

				wc_get_template(
					'checkout/payment-method.php',
					array(
						'gateway' => $gateway,
					)
				);
				?>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/** Available gateways from WooCommerce; this is the single Payment Method source. */
	private function available_gateways(): array {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
			return array();
		}
		try {
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		} catch ( \Throwable $throwable ) {
			return array();
		}
		return is_array( $gateways ) ? $gateways : array();
	}

	/** Select the current non-COD Woo gateway, falling back to the first available. */
	private function selected_gateway_id( array $non_cod ): string {
		$chosen = '';
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$chosen = sanitize_key( (string) WC()->session->get( 'chosen_payment_method', '' ) );
			if ( 'cod' === $chosen ) {
				$chosen = sanitize_key( (string) WC()->session->get( 'eilmo_cf_native_payment_gateway_id', '' ) );
			}
		}
		if ( '' !== $chosen && isset( $non_cod[ $chosen ] ) ) {
			return $chosen;
		}
		return ! empty( $non_cod ) ? sanitize_key( (string) array_key_first( $non_cod ) ) : '';
	}

	/** Gateway title for order meta / hidden bridge state. */
	private function gateway_title( string $gateway_id, array $gateways ): string {
		if ( '' === $gateway_id || ! isset( $gateways[ $gateway_id ] ) || ! is_object( $gateways[ $gateway_id ] ) ) {
			return '';
		}

		if ( GatewaySettings::is_eilmo_gateway( $gateway_id ) ) {
			$config = GatewaySettings::get( $gateway_id );
			return sanitize_text_field( (string) ( $config['title'] ?? $gateway_id ) );
		}

		$gateway = $gateways[ $gateway_id ];
		return sanitize_text_field( is_callable( array( $gateway, 'get_title' ) ) ? wp_strip_all_tags( (string) $gateway->get_title() ) : $gateway_id );
	}

	/** Stable method key for Eilmo gateways; generic gateways use their Woo ID. */
	private function gateway_method_key( string $gateway_id ): string {
		$key = GatewaySettings::method_key( $gateway_id );
		return '' !== $key ? $key : sanitize_key( $gateway_id );
	}

	/** Server-side Advance preview for the currently hydrated cart. */
	private function native_advance_preview( array $context ): array {
		$advance_context = $context;
		$advance_context['payment_type'] = 'advance';
		$result = ( new AdvanceCalculator() )->calculate( $advance_context );
		return is_array( $result ) ? $result : array();
	}

	/** Server-side Full preview before / after the fee is applied. */
	private function native_full_preview( array $context ): array {
		$cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;
		$grand = $this->full_payment_base_grand( $context, $cart );
		$discount = $cart instanceof WC_Cart ? $this->full_payment_discount( $cart ) : 0.0;
		return array(
			'discount' => $discount,
			'pay_now'  => max( 0.0, $grand - $discount ),
		);
	}

	/** Human-readable configured Full Payment discount for the badge. */
	private function full_discount_label( array $settings ): string {
		$value = max( 0.0, (float) ( $settings['value'] ?? 0 ) );
		if ( $value <= 0 ) {
			return '';
		}
		if ( 'fixed' === sanitize_key( (string) ( $settings['type'] ?? 'percentage' ) ) ) {
			return $this->price_text( $value );
		}
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	/** Build the customer-facing Full Payment benefit badge. */
	private function full_payment_badge( array $settings, float $actual_discount ): string {
		$parts = array();
		$actual_discount = max( 0.0, $actual_discount );

		if ( $actual_discount > 0 ) {
			$discount_text = $this->full_discount_label( $settings );
			if ( '' !== $discount_text ) {
				$template = sanitize_text_field( (string) ( $settings['discount_badge'] ?? __( 'Get {discount} OFF', 'eilmo-checkout-flow' ) ) );
				$label = trim( str_replace( '{discount}', $discount_text, $template ) );
				if ( '' !== $label ) {
					$parts[] = $label;
				}
			}
		}

		if ( 'yes' === (string) ( $settings['free_delivery'] ?? 'no' ) ) {
			$label = sanitize_text_field( (string) ( $settings['free_delivery_badge'] ?? __( 'Free Delivery', 'eilmo-checkout-flow' ) ) );
			if ( '' !== trim( $label ) ) {
				$parts[] = trim( $label );
			}
		}

		return implode( ' + ', array_values( array_unique( $parts ) ) );
	}

	/** Base total that Full Payment would use before its own discount. */
	private function full_payment_base_grand( array $context, $cart ): float {
		$grand = max( 0.0, (float) ( $context['grand_total'] ?? 0 ) );
		if ( $cart instanceof WC_Cart && 'full' === sanitize_key( (string) ( $context['payment_type'] ?? '' ) ) ) {
			$grand += $this->current_full_payment_fee( $cart );
		}

		/* Preview Full Payment as if its free-delivery benefit were already active. */
		if ( $this->full_payment_free_delivery_enabled() ) {
			$grand -= max( 0.0, (float) ( $context['delivery_charge'] ?? 0 ) );
		}

		return max( 0.0, $grand );
	}

	/** Whether Full Payment is configured to waive Eilmo delivery charges. */
	private function full_payment_free_delivery_enabled(): bool {
		$settings = $this->checkout_settings();
		$full = isset( $settings['discounts']['full_payment'] ) && is_array( $settings['discounts']['full_payment'] )
			? $settings['discounts']['full_payment']
			: array();
		return 'yes' === (string) ( $full['free_delivery'] ?? 'no' );
	}

	/** Render server-side Pay Now / Remaining Due rows inside Woo's native review table. */
	public function render_payment_breakdown_rows(): void {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() || ! $this->controls_are_active() ) {
			return;
		}
		$context = $this->checkout_context();
		$type = $this->normalize_payment_type( (string) ( $context['payment_type'] ?? '' ) );
		$grand = max( 0.0, (float) ( $context['grand_total'] ?? 0 ) );
		$pay_now = 0.0;
		$remaining = $grand;
		if ( 'advance' === $type ) {
			$preview = $this->native_advance_preview( $context );
			$pay_now = max( 0.0, (float) ( $preview['pay_now'] ?? 0 ) );
			$remaining = max( 0.0, (float) ( $preview['remaining_due'] ?? ( $grand - $pay_now ) ) );
		} elseif ( 'full' === $type ) {
			$pay_now = $grand;
			$remaining = 0.0;
		}
		?>
		<tr class="eilmo-cf-native-summary-row eilmo-cf-native-summary-row--pay-now"><th><?php esc_html_e( 'Pay Now', 'eilmo-checkout-flow' ); ?></th><td><?php echo wp_kses_post( wc_price( $pay_now ) ); ?></td></tr>
		<tr class="eilmo-cf-native-summary-row eilmo-cf-native-summary-row--remaining"><th><?php esc_html_e( 'Remaining Due', 'eilmo-checkout-flow' ); ?></th><td><?php echo wp_kses_post( wc_price( $remaining ) ); ?></td></tr>
		<?php
	}

	/** Return authoritative WooCommerce cart totals and payment calculations. */
	public function ajax_native_checkout_context(): void {
		check_ajax_referer( 'eilmo_cf_frontend', 'nonce' );

		if ( ! DefaultCheckoutSettings::payment_options_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Payment Options are disabled.', 'eilmo-checkout-flow' ) ), 400 );
		}

		$this->ensure_native_cart();
		$context = $this->checkout_context();
		$cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;

		$full_discount = $cart instanceof WC_Cart ? $this->full_payment_discount( $cart ) : 0.0;
		$base_grand = $this->full_payment_base_grand( $context, $cart );

		$calculation_context = $context;
		$calculation_context['grand_total'] = $base_grand;
		$calculation_context['payment_type'] = 'advance';
		$advance = ( new AdvanceCalculator() )->calculate( $calculation_context );
		$advance_pay_now = max( 0.0, (float) ( $advance['pay_now'] ?? 0 ) );
		$advance_remaining = max( 0.0, (float) ( $advance['remaining_due'] ?? max( 0, $base_grand - $advance_pay_now ) ) );

		$full_payable = max( 0.0, $base_grand - max( 0.0, $full_discount ) );
		$option_state = $this->native_payment_option_state();
		$selected_type = $this->normalize_payment_type( (string) ( $context['payment_type'] ?? '' ) );
		$selected_pay_now = 0.0;
		$selected_due = $base_grand;
		if ( 'advance' === $selected_type ) {
			$selected_pay_now = $advance_pay_now;
			$selected_due = $advance_remaining;
		} elseif ( 'full' === $selected_type ) {
			$selected_pay_now = $full_payable;
			$selected_due = 0.0;
		}

		wp_send_json_success(
			array(
				'productTotal'           => (float) ( $context['product_total'] ?? 0 ),
				'discountedProductTotal' => (float) ( $context['discounted_product_total'] ?? 0 ),
				'deliveryCharge'         => (float) ( $context['delivery_charge'] ?? 0 ),
				'grandTotal'             => $base_grand,
				'paymentTypes'           => $option_state,
				'selected'               => array(
					'type'          => $selected_type,
					'payNow'        => $selected_pay_now,
					'remainingDue'  => $selected_due,
					'payNowText'    => $this->price_text( $selected_pay_now ),
					'remainingText' => $this->price_text( $selected_due ),
				),
				'advance'                => array(
					'matched'      => ! empty( $advance['matched'] ),
					'ruleId'       => sanitize_key( (string) ( $advance['rule_id'] ?? '' ) ),
					'ruleType'     => sanitize_key( (string) ( $advance['rule_type'] ?? '' ) ),
					'advanceAmount'=> max( 0.0, (float) ( $advance['advance_amount'] ?? $advance_pay_now ) ),
					'payNow'       => $advance_pay_now,
					'remainingDue' => $advance_remaining,
					'payNowText'   => $this->price_text( $advance_pay_now ),
					'remainingText'=> $this->price_text( $advance_remaining ),
				),
				'full'                   => array(
					'discount'     => max( 0.0, $full_discount ),
					'payNow'       => $full_payable,
					'payNowText'   => $this->price_text( $full_payable ),
				),
			)
		);
	}

	/**
	 * Refresh the authoritative cart totals marker with every WooCommerce
	 * checkout update. The Eilmo controls live outside the order-review
	 * fragment in compatible themes, so this tiny custom fragment keeps the
	 * Advance preview synchronized without re-rendering the whole control UI.
	 *
	 * @param array<string,string> $fragments Checkout fragments.
	 * @return array<string,string>
	 */
	public function refresh_native_totals_fragment( array $fragments ): array {
		if ( ( ! $this->is_checkout_request() && ! wp_doing_ajax() ) || ! $this->advanced_enabled() ) {
			return $fragments;
		}

		$fragments['[data-eilmo-native-cart-totals]'] = $this->native_totals_marker_html( $this->checkout_context() );
		return $fragments;
	}

	/** Build the hidden native cart totals marker consumed by the adapter. */
	private function native_totals_marker_html( array $context ): string {
		$payment_type = $this->normalize_payment_type( (string) ( $context['payment_type'] ?? '' ) );
		$grand_total = max( 0.0, (float) ( $context['grand_total'] ?? 0 ) );
		$advance = $this->native_advance_preview( $context );
		$full = $this->native_full_preview( $context );
		$advance_pay_now = max( 0.0, (float) ( $advance['pay_now'] ?? 0 ) );
		$advance_remaining = max( 0.0, (float) ( $advance['remaining_due'] ?? max( 0.0, $grand_total - $advance_pay_now ) ) );
		$full_pay_now = max( 0.0, (float) ( $full['pay_now'] ?? $grand_total ) );

		$advance_note = str_replace(
			'{amount}',
			$this->price_text( $advance_pay_now ),
			\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' )
		);
		$full_note = str_replace(
			'{amount}',
			$this->price_text( $full_pay_now ),
			\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' )
		);

		return sprintf(
			'<span hidden data-eilmo-native-cart-totals data-payment-type="%1$s" data-product-total="%2$s" data-discounted-product-total="%3$s" data-delivery-charge="%4$s" data-grand-total="%5$s" data-grand-total-text="%6$s" data-advance-pay-now="%7$s" data-advance-pay-now-text="%8$s" data-advance-remaining="%9$s" data-advance-remaining-text="%10$s" data-advance-pay-note="%11$s" data-full-pay-now="%12$s" data-full-pay-now-text="%13$s" data-full-pay-note="%14$s"></span>',
			esc_attr( $payment_type ),
			esc_attr( (string) ( $context['product_total'] ?? 0 ) ),
			esc_attr( (string) ( $context['discounted_product_total'] ?? 0 ) ),
			esc_attr( (string) ( $context['delivery_charge'] ?? 0 ) ),
			esc_attr( (string) $grand_total ),
			esc_attr( $this->price_text( $grand_total ) ),
			esc_attr( (string) $advance_pay_now ),
			esc_attr( $this->price_text( $advance_pay_now ) ),
			esc_attr( (string) $advance_remaining ),
			esc_attr( $this->price_text( $advance_remaining ) ),
			esc_attr( $advance_note ),
			esc_attr( (string) $full_pay_now ),
			esc_attr( $this->price_text( $full_pay_now ) ),
			esc_attr( $full_note )
		);
	}

	/** Store selected native Eilmo state in Woo session during update_checkout. */
	public function sync_session_from_review( string $posted_data ): void {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}
		$data = array();
		parse_str( $posted_data, $data );

		if ( ! $this->serialized_controls_present( $data ) ) {
			$this->clear_payment_session_state();
			return;
		}

		$payment_type = $this->normalize_payment_type( (string) ( $data['eilmo_cf_payment_type'] ?? '' ) );
		if ( '' === $payment_type ) {
			return;
		}
		$this->set_controls_active( true );
		WC()->session->set( 'eilmo_cf_native_payment_type', $payment_type );
		/* Keep WooCommerce's chosen gateway synchronized with the Eilmo mode. */
		$gateway_id = 'cash_on_delivery' === $payment_type ? 'cod' : sanitize_key( (string) ( $data['payment_method'] ?? '' ) );
		if ( 'cod' === $gateway_id && 'cash_on_delivery' !== $payment_type ) {
			$gateway_id = '';
		}
		if ( '' === $gateway_id && 'cash_on_delivery' !== $payment_type ) {
			$gateway_id = sanitize_key( (string) ( $data['eilmo_cf_native_payment_gateway_id'] ?? '' ) );
		}
		if ( '' === $gateway_id && 'cash_on_delivery' !== $payment_type ) {
			$gateway_id = GatewaySettings::gateway_id( sanitize_key( (string) ( $data['eilmo_cf_native_payment_method_key'] ?? '' ) ) );
		}
		if ( '' === $gateway_id && 'cash_on_delivery' !== $payment_type ) {
			$gateway_id = $this->first_non_cod_gateway_id();
		}
		if ( '' !== $gateway_id ) {
			WC()->session->set( 'chosen_payment_method', $gateway_id );
		}

		foreach ( array(
			'eilmo_cf_native_payment_method',
			'eilmo_cf_native_payment_method_key',
			'eilmo_cf_native_payment_source',
			'eilmo_cf_native_payment_gateway_id',
			'eilmo_cf_native_transaction_id',
			'eilmo_cf_native_proof_token',
		) as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				WC()->session->set( $key, sanitize_text_field( (string) $data[ $key ] ) );
			}
		}
	}

	/** Apply the existing configured Full Payment discount to native cart totals. */
	public function apply_full_payment_discount( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() || ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}
		if ( ! $this->controls_are_active() ) {
			return;
		}
		if ( 'full' !== sanitize_key( (string) WC()->session->get( 'eilmo_cf_native_payment_type', '' ) ) ) {
			return;
		}

		$discount = $this->full_payment_discount( $cart );
		if ( $discount > 0 ) {
			$cart->add_fee( __( 'Full Payment Discount', 'eilmo-checkout-flow' ), -1 * $discount, false );
		}
	}

	/** Validate signed Live Fraud decision and manual-payment requirements. */
	public function validate_checkout( array $data, WP_Error $errors ): void {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() || ! $this->controls_were_submitted() ) {
			return;
		}

		$payment_type = $this->posted_payment_type();
		if ( '' === $payment_type ) {
			$errors->add( 'eilmo_cf_native_payment_type', __( 'Please select an available payment option.', 'eilmo-checkout-flow' ) );
			return;
		}

		if ( 'cash_on_delivery' === $payment_type ) {
			if ( ! $this->gateway_is_available( 'cod' ) ) {
				$errors->add( 'eilmo_cf_native_cod_unavailable', __( 'Cash on Delivery is not currently available.', 'eilmo-checkout-flow' ) );
			}
		} else {
			$gateway_id = sanitize_key( (string) ( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( 'cod' === $gateway_id ) {
				$gateway_id = '';
			}
			if ( '' === $gateway_id ) {
				$gateway_id = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_gateway_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			$method_key = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_method_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $method_key && '' !== $gateway_id ) {
				$method_key = $this->gateway_method_key( $gateway_id );
			}
			if ( '' === $gateway_id && $this->has_configured_payment_methods() ) {
				$errors->add( 'eilmo_cf_native_payment_method', __( 'Please select a payment method.', 'eilmo-checkout-flow' ) );
			} elseif ( '' !== $gateway_id && ! $this->gateway_is_available( $gateway_id ) ) {
				$errors->add( 'eilmo_cf_native_gateway_unavailable', __( 'The selected payment method is no longer available.', 'eilmo-checkout-flow' ) );
			}

			$credentials = $this->posted_payment_credentials( $gateway_id, $method_key );
			$proof = $credentials['proof_token'];
			$transaction = $credentials['transaction_id'];
			if ( '' !== $proof && '' !== $method_key && ! PaymentProof::is_valid_token( $proof, $method_key ) ) {
				$errors->add( 'eilmo_cf_native_payment_proof', __( 'The payment screenshot expired. Please upload it again.', 'eilmo-checkout-flow' ) );
			}
			if ( GatewaySettings::is_eilmo_gateway( $gateway_id ) ) {
				$config = GatewaySettings::get( $gateway_id );
				$proof_enabled = 'yes' === (string) ( $config['payment_proof_enabled'] ?? 'no' );
				$proof_valid = $proof_enabled && '' !== $proof && PaymentProof::is_valid_token( $proof, $method_key );
				$transaction_required = 'yes' === (string) ( $config['transaction_id_required'] ?? 'yes' );
				if ( $proof_enabled && '' === $transaction && ! $proof_valid ) {
					$errors->add( 'eilmo_cf_native_payment_credentials', $transaction_required ? __( 'Please enter the transaction ID or upload the payment screenshot.', 'eilmo-checkout-flow' ) : __( 'Please upload the payment screenshot.', 'eilmo-checkout-flow' ) );
				} elseif ( $transaction_required && ! $proof_enabled && '' === $transaction ) {
					$errors->add( 'eilmo_cf_native_transaction_id', __( 'Please enter the transaction ID.', 'eilmo-checkout-flow' ) );
				}
			}
		}

		if ( ! DefaultCheckoutSettings::live_fraud_enabled() || ! CourierSettings::live_fraud_is_enabled() ) {
			return;
		}

		$phone = ( new CourierSuccessService() )->normalize_phone( (string) ( $data['billing_phone'] ?? '' ) );
		if ( '' === $phone ) {
			$errors->add( 'eilmo_cf_native_fraud_phone', __( 'Please provide a valid Bangladesh mobile number.', 'eilmo-checkout-flow' ) );
			return;
		}

		$token = sanitize_text_field( (string) ( $_POST['eilmo_cf_native_fraud_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$verified = ( new FraudDecisionToken() )->verify( $token, $phone );
		if ( is_wp_error( $verified ) ) {
			$errors->add( $verified->get_error_code(), $verified->get_error_message() );
			return;
		}

		$evaluation = isset( $verified['evaluation'] ) && is_array( $verified['evaluation'] ) ? $verified['evaluation'] : array();
		$action = sanitize_key( (string) ( $evaluation['action'] ?? 'allow' ) );
		$allowed = isset( $evaluation['allowed_payment_types'] ) && is_array( $evaluation['allowed_payment_types'] ) ? $evaluation['allowed_payment_types'] : array();
		if ( 'block' === $action || ! in_array( $payment_type, $allowed, true ) ) {
			$errors->add( 'eilmo_cf_native_fraud_payment', $this->fraud_message( $action ) );
			return;
		}
		$this->verified_fraud = $verified;
	}

	/** Persist Eilmo checkout data on the native WooCommerce order. */
	public function store_order_meta( WC_Order $order, array $data ): void {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return;
		}

		/* Gateway titles are decorated with checkout-only HTML. WooCommerce
		 * copies that title into the order before this hook; keep order data plain. */
		$gateway_title = (string) $order->get_payment_method_title();
		$gateway_title = preg_replace( '#<small\b[^>]*>.*?</small>#is', '', $gateway_title );
		$order->set_payment_method_title(
			sanitize_text_field( (string) $gateway_title )
		);

		if ( ! $this->controls_were_submitted() ) {
			return;
		}

		$type = $this->posted_payment_type();
		if ( '' === $type ) {
			return;
		}

		$method = sanitize_text_field( (string) ( $_POST['eilmo_cf_native_payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$key = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_method_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_source'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$gateway_id = sanitize_key( (string) ( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $gateway_id ) {
			$gateway_id = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_gateway_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		$credentials = $this->posted_payment_credentials( $gateway_id, $key );
		$transaction = $credentials['transaction_id'];

		if ( 'cash_on_delivery' === $type ) {
			$method = 'cod';
			$key = 'cod';
			$source = 'woocommerce_gateway';
			$gateway_id = 'cod';
		} elseif ( '' === $gateway_id ) {
			$gateway_id = GatewaySettings::gateway_id( $key );
			if ( '' === $gateway_id ) {
				$gateway_id = $this->first_non_cod_gateway_id();
			}
		}
		if ( '' !== $gateway_id && '' === $key ) {
			$key = $this->gateway_method_key( $gateway_id );
		}
		if ( '' !== $gateway_id && '' === $source ) {
			$source = 'woocommerce_gateway';
		}
		if ( '' !== $gateway_id && '' === $method ) {
			$method = $this->gateway_title( $gateway_id, $this->available_gateways() );
		}
		if ( GatewaySettings::is_eilmo_gateway( $gateway_id ) ) {
			$source = 'eilmo_gateway';
			if ( '' === $method ) {
				$method = 'eilmo_custom__' . GatewaySettings::method_key( $gateway_id );
			}
		}

		$totals = $this->checkout_context( $type );
		$calculator = new AdvanceCalculator();
		$calculated = $calculator->calculate( $totals );
		$order_total = max( 0.0, (float) $order->get_total() );
		$pay_now = 'cash_on_delivery' === $type ? 0.0 : ( 'full' === $type ? $order_total : (float) ( $calculated['pay_now'] ?? 0 ) );
		$remaining = 'cash_on_delivery' === $type ? $order_total : ( 'full' === $type ? 0.0 : max( 0, $order_total - $pay_now ) );
		$product_total = max( 0.0, (float) ( $totals['product_total'] ?? 0 ) );
		$delivery_charge = max( 0.0, (float) ( $totals['delivery_charge'] ?? 0 ) );
		$coupon_discount = max( 0.0, $product_total - max( 0.0, (float) ( $totals['discounted_product_total'] ?? $product_total ) ) );

		/* Use the same metadata contract as Checkout Flow custom orders. */
		$order->update_meta_data( '_eilmo_cf_created_via', 'eilmo-checkout-flow' );
		$order->update_meta_data( '_eilmo_cf_native_checkout', 'yes' );
		$order->update_meta_data( '_eilmo_cf_product_total', wc_format_decimal( $product_total ) );
		$order->update_meta_data( '_eilmo_cf_coupon_discount', wc_format_decimal( $coupon_discount ) );
		$order->update_meta_data( '_eilmo_cf_delivery_charge', wc_format_decimal( $delivery_charge ) );
		$order->update_meta_data( '_eilmo_cf_grand_total', wc_format_decimal( $order_total ) );
		$order->update_meta_data( '_eilmo_cf_payment_type', $type );
		$order->update_meta_data( '_eilmo_cf_payment_method', $method );
		$order->update_meta_data( '_eilmo_cf_payment_method_key', $key );
		$order->update_meta_data( '_eilmo_cf_payment_source', $source );
		$order->update_meta_data( '_eilmo_cf_payment_gateway_id', $gateway_id );
		$order->update_meta_data( '_eilmo_cf_payment_transaction_id', $transaction );
		$initial_payment_status = 'cash_on_delivery' === $type ? 'pay_on_delivery' : 'payment_initiated';
		if ( in_array( $gateway_id, array( GatewaySettings::BKASH, GatewaySettings::NAGAD, 'bacs' ), true ) && in_array( $type, array( 'advance', 'full' ), true ) ) {
			$initial_payment_status = 'awaiting_verification';
		}
		$order->update_meta_data( '_eilmo_cf_payment_status', $initial_payment_status );
		$order->update_meta_data( '_eilmo_cf_pay_now', wc_format_decimal( $pay_now ) );
		$order->update_meta_data( '_eilmo_cf_remaining_due', wc_format_decimal( $remaining ) );
		$order->update_meta_data( '_eilmo_cf_advance_amount', wc_format_decimal( 'advance' === $type ? $pay_now : 0 ) );
		$order->update_meta_data( '_eilmo_cf_advance_rule_id', sanitize_key( (string) ( $calculated['rule_id'] ?? '' ) ) );
		$order->update_meta_data( '_eilmo_cf_advance_rule_type', sanitize_key( (string) ( $calculated['rule_type'] ?? '' ) ) );

		if ( 'full' === $type ) {
			$discount = $this->full_payment_discount_from_order( $order );
			$order->update_meta_data( '_eilmo_cf_full_payment_discount', wc_format_decimal( $discount ) );
		}

		/* Proof files and fraud snapshots are attached after WooCommerce saves the order ID. */
	}

	/** Attach proof and verified fraud data after the native order has a persistent ID. */
	public function after_order_processed( int $order_id, array $posted_data, WC_Order $order ): void {
		if ( ! DefaultCheckoutSettings::payment_options_enabled() || $order_id < 1 || ! $this->controls_were_submitted() ) {
			return;
		}

		$key = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_method_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce protects this request.
		$gateway_id = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_gateway_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$credentials = $this->posted_payment_credentials( $gateway_id, $key );
		$proof = $credentials['proof_token'];

		if ( '' !== $proof && '' !== $key ) {
			PaymentProof::assign_to_order( $order, $proof, $key );
		}

		if ( ! empty( $this->verified_fraud ) ) {
			( new CourierSuccessService() )->attach_live_result( $order, $this->verified_fraud );
		}

		$order->save();
	}

	/** Checkout renderer context. */
	private function checkout_context( string $payment_type = '' ): array {
		$this->ensure_native_cart();
		$cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;
		$product_total = 0.0;
		$discounted = 0.0;
		$delivery = 0.0;
		$grand = 0.0;

		if ( $cart instanceof WC_Cart ) {
			/*
			 * Always derive product totals from cart lines as well as aggregate
			 * getters. Some Checkout Block/theme render paths invoke the payment
			 * hook before the aggregate getters are hydrated, while line items are
			 * already present in the Woo session.
			 */
			$line_subtotal_sum = 0.0;
			$line_total_sum = 0.0;
			foreach ( $cart->get_cart() as $cart_item ) {
				$quantity = max( 0.0, (float) ( $cart_item['quantity'] ?? 0 ) );
				$product  = isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ? $cart_item['data'] : null;
				$fallback = $product && is_callable( array( $product, 'get_price' ) ) ? max( 0.0, (float) $product->get_price() * $quantity ) : 0.0;
				$line_subtotal = isset( $cart_item['line_subtotal'] ) ? max( 0.0, (float) $cart_item['line_subtotal'] ) : $fallback;
				$line_total = isset( $cart_item['line_total'] ) ? max( 0.0, (float) $cart_item['line_total'] ) : $line_subtotal;
				if ( $line_subtotal <= 0 && $fallback > 0 ) {
					$line_subtotal = $fallback;
				}
				if ( $line_total <= 0 && $line_subtotal > 0 ) {
					$line_total = $line_subtotal;
				}
				$line_subtotal_sum += $line_subtotal;
				$line_total_sum += $line_total;
			}

			$product_total = max( (float) $cart->get_subtotal(), $line_subtotal_sum );
			$aggregate_discounted = (float) $cart->get_cart_contents_total();
			$discounted = $aggregate_discounted > 0 ? $aggregate_discounted : $line_total_sum;
			if ( $discounted <= 0 && $product_total > 0 ) {
				$discounted = max( 0.0, $product_total - (float) $cart->get_discount_total() );
			}

			$delivery = max( 0.0, (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax() );
			foreach ( $cart->get_fees() as $fee ) {
				if ( __( 'Delivery charge', 'eilmo-checkout-flow' ) !== (string) ( $fee->name ?? '' ) ) {
					continue;
				}
				$delivery += max( 0.0, (float) ( $fee->amount ?? 0 ) );
				$delivery += max( 0.0, (float) ( $fee->tax ?? 0 ) );
			}
			$grand = max( 0.0, (float) $cart->get_total( 'edit' ) );
			if ( $grand <= 0 && $discounted > 0 ) {
				$grand = $discounted + $delivery + max( 0.0, (float) $cart->get_cart_contents_tax() );
				foreach ( $cart->get_fees() as $fee ) {
					$grand += (float) $fee->amount;
				}
				$grand = max( 0.0, $grand );
			}
		}

		if ( '' === $payment_type && function_exists( 'WC' ) && WC() && WC()->session ) {
			$payment_type = sanitize_key( (string) WC()->session->get( 'eilmo_cf_native_payment_type', '' ) );
		}
		$payment_type = $this->normalize_payment_type( $payment_type );

		$selected_method = '';
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$selected_method = sanitize_text_field( (string) WC()->session->get( 'eilmo_cf_native_payment_method', '' ) );
		}

		return array(
			'instance_id'              => 'eilmo-cf-native-checkout',
			'product_total'            => round( $product_total, wc_get_price_decimals() ),
			'discounted_product_total' => round( $discounted, wc_get_price_decimals() ),
			'delivery_charge'          => round( $delivery, wc_get_price_decimals() ),
			'grand_total'              => round( $grand, wc_get_price_decimals() ),
			'payment_type'             => $payment_type,
			'payment_method'           => $selected_method,
		);
	}

	/** Ensure WooCommerce session/cart are available on frontend and admin-ajax requests. */
	private function ensure_native_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			try {
				wc_load_cart();
			} catch ( \Throwable $throwable ) {
				return;
			}
		}
	}

	/** Amount of the currently-applied Eilmo full-payment discount fee. */
	private function current_full_payment_fee( WC_Cart $cart ): float {
		$amount = 0.0;
		foreach ( $cart->get_fees() as $fee ) {
			if ( __( 'Full Payment Discount', 'eilmo-checkout-flow' ) === $fee->name && (float) $fee->amount < 0 ) {
				$amount += abs( (float) $fee->amount );
			}
		}
		return max( 0.0, $amount );
	}

	/** Plain-text WooCommerce price for JSON/UI copy. */
	private function price_text( float $amount ): string {
		$html = wc_price( max( 0.0, $amount ) );
		$text = wp_strip_all_tags( $html );
		return html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	}

	/** Calculate native Full Payment discount from configured plugin rules. */
	private function full_payment_discount( WC_Cart $cart ): float {
		$settings = $this->checkout_settings();
		$full = isset( $settings['discounts']['full_payment'] ) && is_array( $settings['discounts']['full_payment'] ) ? $settings['discounts']['full_payment'] : array();
		if ( 'yes' !== ( $full['enabled'] ?? 'no' ) ) {
			return 0.0;
		}
		$value = max( 0, (float) ( $full['value'] ?? 0 ) );
		if ( $value <= 0 ) {
			return 0.0;
		}
		$product_total = (float) $cart->get_subtotal();
		$discounted = (float) $cart->get_cart_contents_total();
		$grand = $discounted + (float) $cart->get_cart_contents_tax() + (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
		$free_delivery = 'yes' === (string) ( $full['free_delivery'] ?? 'no' );
		foreach ( $cart->get_fees() as $fee ) {
			$name = isset( $fee->name ) ? (string) $fee->name : '';
			if ( __( 'Full Payment Discount', 'eilmo-checkout-flow' ) === $name ) {
				continue;
			}
			if ( $free_delivery && __( 'Delivery charge', 'eilmo-checkout-flow' ) === $name ) {
				continue;
			}
			$grand += (float) ( $fee->amount ?? 0 );
			$grand += (float) ( $fee->tax ?? 0 );
		}
		$grand = max( 0.0, $grand );
		$basis_key = sanitize_key( (string) ( $full['basis'] ?? 'discounted_product_total' ) );
		$basis = 'product_total' === $basis_key ? $product_total : ( 'grand_total' === $basis_key ? $grand : $discounted );
		$minimum = max( 0, (float) ( $full['minimum_amount'] ?? 0 ) );
		if ( $basis <= 0 || ( $minimum > 0 && $basis < $minimum ) ) {
			return 0.0;
		}
		$discount = 'fixed' === sanitize_key( (string) ( $full['type'] ?? 'percentage' ) ) ? $value : $basis * ( $value / 100 );
		$maximum = max( 0, (float) ( $full['maximum_discount'] ?? 0 ) );
		if ( $maximum > 0 ) {
			$discount = min( $discount, $maximum );
		}
		return max( 0, min( round( $discount, wc_get_price_decimals() ), $basis, $grand ) );
	}

	/** Read the fee amount saved to the final order. */
	private function full_payment_discount_from_order( WC_Order $order ): float {
		$discount = 0.0;
		foreach ( $order->get_fees() as $fee ) {
			if ( __( 'Full Payment Discount', 'eilmo-checkout-flow' ) === $fee->get_name() && (float) $fee->get_total() < 0 ) {
				$discount += abs( (float) $fee->get_total() );
			}
		}
		return $discount;
	}

	/** Merged Checkout Flow settings. */
	private function checkout_settings(): array {
		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		return array_replace_recursive( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/** Payment Option settings. */
	private function payment_option_settings(): array {
		$settings = $this->checkout_settings();
		return isset( $settings['advance_payment'] ) && is_array( $settings['advance_payment'] ) ? $settings['advance_payment'] : array();
	}

	/** Native Payment Option availability derived from the merchant's Checkout Flow settings. */
	private function native_payment_option_state(): array {
		$settings = $this->payment_option_settings();
		$available = array();
		if ( 'yes' === (string) ( $settings['allow_cash_on_delivery'] ?? 'no' ) && $this->gateway_is_available( 'cod' ) ) {
			$available[] = 'cash_on_delivery';
		}
		$has_prepay_gateway = '' !== $this->first_non_cod_gateway_id();
		if ( $has_prepay_gateway && 'yes' === (string) ( $settings['allow_advance_payment'] ?? 'yes' ) ) {
			$available[] = 'advance';
		}
		if ( $has_prepay_gateway && 'yes' === (string) ( $settings['allow_full_payment'] ?? 'yes' ) ) {
			$available[] = 'full';
		}
		if ( empty( $available ) ) {
			$available[] = $this->gateway_is_available( 'cod' ) ? 'cash_on_delivery' : 'advance';
		}
		$default = sanitize_key( (string) ( $settings['default_payment_type'] ?? 'advance' ) );
		if ( ! in_array( $default, $available, true ) ) {
			$default = (string) reset( $available );
		}
		return array(
			'cashOnDelivery' => in_array( 'cash_on_delivery', $available, true ) ? 'yes' : 'no',
			'advance'       => in_array( 'advance', $available, true ) ? 'yes' : 'no',
			'full'          => in_array( 'full', $available, true ) ? 'yes' : 'no',
			'defaultType'   => $default,
			'available'     => $available,
		);
	}

	/** Normalize a requested Payment Option against merchant-enabled choices. */
	private function normalize_payment_type( string $type ): string {
		$state = $this->native_payment_option_state();
		$available = isset( $state['available'] ) && is_array( $state['available'] ) ? $state['available'] : array();
		$type = sanitize_key( $type );
		if ( in_array( $type, $available, true ) ) {
			return $type;
		}
		return sanitize_key( (string) ( $state['defaultType'] ?? ( $available[0] ?? 'advance' ) ) );
	}

	/** Whether at least one merchant-configured payment method exists for Advance / Full. */
	private function has_configured_payment_methods(): bool {
		return '' !== $this->first_non_cod_gateway_id();
	}



	/** Whether one WooCommerce gateway is currently available. */
	private function gateway_is_available( string $gateway_id ): bool {
		if ( '' === $gateway_id || ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
			return false;
		}
		try {
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		} catch ( \Throwable $throwable ) {
			return false;
		}
		return isset( $gateways[ $gateway_id ] );
	}

	/** First available WooCommerce gateway for Advance / Full, excluding COD. */
	private function first_non_cod_gateway_id(): string {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
			return '';
		}
		try {
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		} catch ( \Throwable $throwable ) {
			return '';
		}
		foreach ( (array) $gateways as $gateway_id => $gateway ) {
			$gateway_id = sanitize_key( (string) $gateway_id );
			if ( '' !== $gateway_id && 'cod' !== $gateway_id ) {
				return $gateway_id;
			}
		}
		return '';
	}

	/**
	 * Read manual-payment credentials from every supported checkout field shape.
	 *
	 * Native checkout mirrors the selected credentials to stable hidden fields,
	 * while the Eilmo renderer also posts method-scoped field names. Reading both
	 * shapes makes validation resilient to WooCommerce fragment refreshes and
	 * theme-specific checkout markup.
	 *
	 * @return array{transaction_id:string,proof_token:string}
	 */
	private function posted_payment_credentials( string $gateway_id = '', string $method_key = '' ): array {
		$gateway_id = sanitize_key( $gateway_id );
		$method_key = sanitize_key( $method_key );

		if ( '' === $gateway_id ) {
			$gateway_id = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_gateway_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( '' === $method_key ) {
			$method_key = sanitize_key( (string) ( $_POST['eilmo_cf_native_payment_method_key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( '' === $method_key && '' !== $gateway_id ) {
			$method_key = GatewaySettings::method_key( $gateway_id );
		}

		$transaction = sanitize_text_field( wp_unslash( (string) ( $_POST['eilmo_cf_native_transaction_id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$proof = sanitize_text_field( wp_unslash( (string) ( $_POST['eilmo_cf_native_proof_token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $transaction && '' !== $gateway_id ) {
			$transaction = sanitize_text_field( wp_unslash( (string) ( $_POST[ $gateway_id . '_transaction_id' ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( '' === $proof && '' !== $gateway_id ) {
			$proof = sanitize_text_field( wp_unslash( (string) ( $_POST[ $gateway_id . '_proof_token' ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		/* Eilmo PaymentRenderer fields are scoped by checkout instance + method key. */
		if ( ( '' === $transaction || '' === $proof ) && '' !== $method_key ) {
			$transaction_prefix = 'eilmo_payment_transaction_';
			$proof_prefix = 'eilmo_payment_proof_';
			$suffix = '_' . $method_key;
			foreach ( $_POST as $posted_key => $posted_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_scalar( $posted_value ) ) {
					continue;
				}
				$posted_key = (string) $posted_key;
				if ( '' === $transaction && 0 === strpos( $posted_key, $transaction_prefix ) && substr( $posted_key, -strlen( $suffix ) ) === $suffix ) {
					$transaction = sanitize_text_field( wp_unslash( (string) $posted_value ) );
				}
				if ( '' === $proof && 0 === strpos( $posted_key, $proof_prefix ) && substr( $posted_key, -strlen( $suffix ) ) === $suffix ) {
					$proof = sanitize_text_field( wp_unslash( (string) $posted_value ) );
				}
				if ( '' !== $transaction && '' !== $proof ) {
					break;
				}
			}
		}

		return array(
			'transaction_id' => $transaction,
			'proof_token'    => $proof,
		);
	}

	/** Reset per-page presence before the checkout renderer gets a chance to mount controls. */
	public function reset_checkout_presence(): void {
		if ( ! $this->is_checkout_request() || ! DefaultCheckoutSettings::payment_options_enabled() ) {
			return;
		}
		$this->controls_rendered = false;
		$this->set_controls_active( false );
	}

	/** Whether the current checkout POST explicitly contains the Eilmo controls marker. */
	private function controls_were_submitted(): bool {
		$value = isset( $_POST['eilmo_cf_native_controls_present'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['eilmo_cf_native_controls_present'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return '1' === $value;
	}

	/** Whether serialized update_checkout data contains the Eilmo controls marker. */
	private function serialized_controls_present( array $data ): bool {
		return '1' === sanitize_text_field( (string) ( $data['eilmo_cf_native_controls_present'] ?? '' ) );
	}

	/** Track whether Eilmo's Payment Option UI is really mounted for this checkout. */
	private function set_controls_active( bool $active ): void {
		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			WC()->session->set( 'eilmo_cf_native_controls_active', $active ? 'yes' : 'no' );
		}
	}

	/** Current request/session has an actually-rendered Eilmo payment UI. */
	private function controls_are_active(): bool {
		return function_exists( 'WC' ) && WC() && WC()->session
			? 'yes' === (string) WC()->session->get( 'eilmo_cf_native_controls_active', 'no' )
			: false;
	}

	/** Clear only Eilmo-owned session state; never touch WooCommerce's chosen gateway. */
	private function clear_payment_session_state(): void {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->session ) {
			return;
		}
		foreach ( array(
			'eilmo_cf_native_controls_active',
			'eilmo_cf_native_payment_type',
			'eilmo_cf_native_payment_method',
			'eilmo_cf_native_payment_method_key',
			'eilmo_cf_native_payment_source',
			'eilmo_cf_native_payment_gateway_id',
			'eilmo_cf_native_transaction_id',
			'eilmo_cf_native_proof_token',
		) as $key ) {
			WC()->session->set( $key, '' );
		}
	}

	/** Posted payment type. */
	private function posted_payment_type(): string {
		$type = sanitize_key( (string) ( $_POST['eilmo_cf_payment_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce protects this request.
		return in_array( $type, array( 'cash_on_delivery', 'advance', 'full' ), true ) ? $type : '';
	}

	/** Live-fraud message. */
	private function fraud_message( string $action ): string {
		$courier = CourierSettings::get_settings();
		$fraud = isset( $courier['live_fraud'] ) && is_array( $courier['live_fraud'] ) ? $courier['live_fraud'] : array();
		$key = in_array( $action, array( 'advance', 'full', 'block' ), true ) ? $action . '_message' : 'allow_message';
		return sanitize_text_field( (string) ( $fraud[ $key ] ?? __( 'The selected payment option is not available for this phone number.', 'eilmo-checkout-flow' ) ) );
	}

	/** Whether this request is the native cart-context AJAX call. */
	private function is_native_context_request(): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only used to scope runtime settings; endpoint validates its nonce.
		return 'eilmo_cf_native_checkout_context' === $action;
	}

	/** Current request is the customer checkout form, not pay/received endpoints. */
	private function is_checkout_request(): bool {
		return function_exists( 'is_checkout' ) && is_checkout()
			&& ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
			&& ! ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() );
	}

	/** Payment or Live Fraud enabled for native checkout. */
	private function advanced_enabled(): bool {
		return DefaultCheckoutSettings::payment_options_enabled() || DefaultCheckoutSettings::live_fraud_enabled();
	}
}
