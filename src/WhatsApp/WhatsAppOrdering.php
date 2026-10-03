<?php
/**
 * WhatsApp ordering.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\WhatsApp;

use EilmoCheckout\Licensing\LicenseManager;
use EilmoCheckout\Core\Assets;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\WhatsAppSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Rendering\TrustNoticeRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and configures the frontend
 * WhatsApp ordering flow.
 */
final class WhatsAppOrdering implements RegistrableInterface {

	/**
	 * WhatsApp settings option.
	 *
	 * @var string
	 */
	private const OPTION_NAME =
		'eilmo_cf_whatsapp_settings';

	/**
	 * Frontend script handle.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE =
		'eilmo-cf-whatsapp-ordering';

	/**
	 * Frontend script relative path.
	 *
	 * @var string
	 */
	private const SCRIPT_PATH =
		'src/js/frontend/whatsapp.js';

	/**
	 * Whether script configuration has
	 * already been localized.
	 *
	 * @var bool
	 */
	private static $localized =
		false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		/*
		 * Core Assets registers the normal checkout
		 * scripts at priority 10.
		 *
		 * Register WhatsApp afterwards so existing
		 * checkout / summary handles can be used as
		 * dependencies when available.
		 */
		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'register_script',
			),
			15
		);

		/*
		 * CheckoutRenderer fires this hook after the
		 * normal Order Now section.
		 */
		add_action(
			'eilmo_cf/after_checkout_actions',
			array( $this, 'render_below_payment' ),
			20,
			1
		);

		add_action(
			'eilmo_cf/summary_after_order_action',
			array( $this, 'render_below_summary' ),
			20,
			2
		);

		add_filter(
			'eilmo_cf/orders/footer_enabled',
			array( $this, 'filter_order_footer_enabled' ),
			20,
			3
		);
	}

	/**
	 * Register WhatsApp frontend JavaScript.
	 *
	 * Registration only.
	 *
	 * The script is enqueued later when an actual
	 * Eilmo checkout renders the WhatsApp button.
	 *
	 * @return void
	 */
	public function register_script(): void {

		if (
			! $this->is_enabled()
		) {
			return;
		}

		$settings =
			$this->get_settings();

		if (
			'' === $settings['number']
		) {
			return;
		}

		$absolute_path =
			EILMO_CF_PATH .
			'assets/' .
			self::SCRIPT_PATH;

		if (
			! file_exists(
				$absolute_path
			)
		) {
			return;
		}

		if (
			! wp_script_is(
				self::SCRIPT_HANDLE,
				'registered'
			)
		) {

			// Reuse the active asset strategy; source dependencies would load
			// checkout handlers twice when the production bundle is present.
			$dependencies = Assets::get_frontend_script_handles();

			/*
			 * WhatsApp uses the public checkout API
			 * for product/customer data.
			 */
			if ( ! wp_script_is( Assets::FRONTEND_SCRIPT, 'registered' ) &&
				wp_script_is(
					'eilmo-cf-checkout-submit',
					'registered'
				)
			) {
				$dependencies[] =
					'eilmo-cf-checkout-submit';
			}

			/*
			 * WhatsApp uses Summary only for the
			 * current display total.
			 */
			if ( ! wp_script_is( Assets::FRONTEND_SCRIPT, 'registered' ) &&
				wp_script_is(
					'eilmo-cf-summary',
					'registered'
				)
			) {
				$dependencies[] =
					'eilmo-cf-summary';
			}

			$file_version =
				filemtime(
					$absolute_path
				);

			wp_register_script(
				self::SCRIPT_HANDLE,
				EILMO_CF_ASSETS_URL .
					self::SCRIPT_PATH,
				$dependencies,
				false !== $file_version
					? (string) $file_version
					: null,
				array(
					'in_footer' =>
						true,

					'strategy' =>
						'defer',
				)
			);
		}

		$this->localize_script(
			$settings
		);
	}

	/** Render in normal checkout actions. */
	public function render_below_payment( array $checkout_settings = array() ): void {
		$placement = $this->get_effective_placement( $checkout_settings );
		if ( ! in_array( $placement, array( 'below_payment', 'both' ), true ) ) {
			return;
		}
		$this->render_button( 'below_payment', $checkout_settings );
	}

	/** Render under the Order Summary action area. */
	public function render_below_summary( array $amounts = array(), array $checkout_settings = array() ): void {
		unset( $amounts );
		$placement = $this->get_effective_placement( $checkout_settings );
		if ( ! in_array( $placement, array( 'below_summary', 'both' ), true ) ) {
			return;
		}
		if ( 'no' === (string) ( $checkout_settings['show_summary'] ?? 'yes' ) ) {
			return;
		}
		$this->render_button( 'below_summary', $checkout_settings );
	}

	/**
	 * Keep the shared trust notice below the final visible action.
	 *
	 * @param string               $enabled           Current footer state.
	 * @param array<string, mixed> $context           Order render context.
	 * @param array<string, mixed> $checkout_settings Prepared checkout settings.
	 *
	 * @return string
	 */
	public function filter_order_footer_enabled(
		string $enabled,
		array $context = array(),
		array $checkout_settings = array()
	): string {
		if ( 'yes' !== $enabled ) {
			return 'no';
		}

		$location = sanitize_key( (string) ( $context['action_slot'] ?? 'below_payment' ) );

		return $this->will_render_at( $location, $checkout_settings )
			? 'no'
			: 'yes';
	}

	/** Render one WhatsApp button instance. */
	private function render_button( string $location, array $checkout_settings = array() ): void {

		if (
			! $this->is_enabled()
		) {
			return;
		}

		$settings =
			$this->get_settings();

		if (
			'' === $settings['number']
		) {
			return;
		}

		/*
		 * Defensive registration in case the checkout
		 * was rendered in an unusual lifecycle.
		 */
		$this->register_script();

		if (
			! wp_script_is(
				self::SCRIPT_HANDLE,
				'registered'
			)
		) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE
		);

		?>
		<div
			class="eilmo-cf-checkout__order-submit eilmo-cf-checkout__whatsapp-order<?php echo 'below_summary' === $location ? ' eilmo-cf-summary__whatsapp-action' : ''; ?>"
			data-eilmo-whatsapp-location="<?php echo esc_attr( $location ); ?>"
			<?php if ( 'below_summary' === $location ) : ?>data-eilmo-summary-whatsapp-action<?php endif; ?>
		>

			<div
				class="eilmo-cf-order-submit eilmo-cf-whatsapp-order"
				data-eilmo-whatsapp-order-section
				style="<?php echo esc_attr( WhatsAppSettings::get_style_css_variables( isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : null ) ); ?>"
			>

				<button
					type="button"
					class="eilmo-cf-order-submit__button eilmo-cf-whatsapp-order__button"
					data-eilmo-whatsapp-order
				>

					<span
						class="eilmo-cf-order-submit__button-text"
					>
						<?php
						echo esc_html(
							$settings[
								'button_label'
							]
						);
						?>
					</span>

				</button>

				<div
					class="eilmo-cf-whatsapp-order__error"
					data-eilmo-whatsapp-error
					role="alert"
					hidden
				></div>

				<?php $this->render_order_footer( $checkout_settings ); ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Whether a real WhatsApp action can render in one checkout slot.
	 *
	 * @param string               $location          below_payment|below_summary.
	 * @param array<string, mixed> $checkout_settings Prepared checkout settings.
	 */
	private function will_render_at( string $location, array $checkout_settings = array() ): bool {
		if ( ! in_array( $location, array( 'below_payment', 'below_summary' ), true ) ) {
			return false;
		}

		if ( ! $this->is_enabled() ) {
			return false;
		}

		$settings = $this->get_settings();
		if ( '' === (string) ( $settings['number'] ?? '' ) ) {
			return false;
		}

		if ( ! file_exists( EILMO_CF_PATH . 'assets/' . self::SCRIPT_PATH ) ) {
			return false;
		}

		$placement = $this->get_effective_placement( $checkout_settings );
		if ( ! in_array( $placement, array( $location, 'both' ), true ) ) {
			return false;
		}

		return 'below_summary' !== $location
			|| 'no' !== (string) ( $checkout_settings['show_summary'] ?? 'yes' );
	}

	/**
	 * Resolve where WhatsApp belongs for this checkout instance.
	 *
	 * The reference Inline and Right-side Sticky layouts always keep WhatsApp
	 * immediately after the real Order Now action. Legacy/custom layouts can still
	 * use the saved integration placement setting.
	 *
	 * @param array<string, mixed> $checkout_settings Prepared checkout settings.
	 */
	private function get_effective_placement( array $checkout_settings = array() ): string {
		$layout = sanitize_key( (string) ( $checkout_settings['checkout_layout'] ?? '' ) );
		$order_placement = sanitize_key( (string) ( $checkout_settings['order_button_placement'] ?? '' ) );

		if ( in_array( $layout, array( 'inline', 'right_sticky' ), true ) ) {
			if ( 'below_summary' === $order_placement ) {
				return 'below_summary';
			}

			if ( 'below_payment' === $order_placement ) {
				return 'below_payment';
			}

			if ( 'both' === $order_placement ) {
				return 'both';
			}

			return 'below_summary';
		}

		$placement = sanitize_key( (string) ( $this->get_settings()['placement'] ?? 'below_summary' ) );

		return in_array( $placement, array( 'below_payment', 'below_summary', 'both' ), true )
			? $placement
			: 'below_summary';
	}

	/** Render the Order Now trust text after WhatsApp when WhatsApp is present. */
	private function render_order_footer( array $checkout_settings ): void {
		$order_display = isset( $checkout_settings['order_button_display'] ) && is_array( $checkout_settings['order_button_display'] )
			? $checkout_settings['order_button_display']
			: array();

		if ( 'yes' !== (string) ( $order_display['footer_enabled'] ?? 'yes' ) ) {
			return;
		}

		$text = sanitize_text_field( (string) ( $order_display['footer_text'] ?? '' ) );
		if ( '' === $text || ! class_exists( TrustNoticeRenderer::class ) ) {
			return;
		}

		$icon = sanitize_key( (string) ( $order_display['footer_icon'] ?? 'shield' ) );
		?>
		<div class="eilmo-cf-whatsapp-order__footer">
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer returns complete HTML and escapes every supplied value internally.
			echo ( new TrustNoticeRenderer() )->render(
				array(
					'text'    => $text,
					'icon'    => $icon,
					'context' => 'order',
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
	}

	/**
	 * Localize frontend WhatsApp configuration.
	 *
	 * @param array<string,string> $settings Settings.
	 *
	 * @return void
	 */
	private function localize_script(
		array $settings
	): void {

		if (
			self::$localized ||
			! wp_script_is(
				self::SCRIPT_HANDLE,
				'registered'
			)
		) {
			return;
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'eilmoCfWhatsApp',
			array(
				'number' =>
					$settings['number'],

				'messageTemplate' =>
					$settings[
						'message_template'
					],

				'i18n' =>
					array(
						'selectProduct' =>
							__(
								'Please select at least one product.',
								'eilmo-checkout-flow'
							),

						'unavailable' =>
							__(
								'WhatsApp ordering is currently unavailable.',
								'eilmo-checkout-flow'
							),

						'combo' =>
							__(
								'Combo',
								'eilmo-checkout-flow'
							),

						'specialOffer' =>
							__(
								'Special Offer',
								'eilmo-checkout-flow'
							),

						'product' =>
							__(
								'Product',
								'eilmo-checkout-flow'
							),

						'variation' =>
							__(
								'Variation',
								'eilmo-checkout-flow'
							),

						'notSelected' =>
							__(
								'Not selected',
								'eilmo-checkout-flow'
							),
					),
			)
		);

		self::$localized =
			true;
	}

	/**
	 * Get WhatsApp settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {

		$defaults =
			array(
				'number' =>
					'',

				'button_label' =>
					__(
						'Order via WhatsApp',
						'eilmo-checkout-flow'
					),

				'placement' =>

					'below_summary',

				'message_template' =>
					$this->get_default_message(),

				'style' =>
					WhatsAppSettings::get_defaults()['style'],
			);

		$stored =
			get_option(
				self::OPTION_NAME,
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

		$settings =
			wp_parse_args(
				$stored,
				$defaults
			);

		$number =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$settings[
						'number'
					] ??
						''
				)
			);

		$number =
			is_string(
				$number
			)
				? $number
				: '';

		$button_label =
			sanitize_text_field(
				(string) (
					$settings[
						'button_label'
					] ??
						''
				)
			);

		if (
			'' === $button_label
		) {
			$button_label =
				(string) $defaults[
					'button_label'
				];
		}

		$message_template =
			sanitize_textarea_field(
				(string) (
					$settings[
						'message_template'
					] ??
						''
				)
			);

		if (
			'' ===
				trim(
					$message_template
				)
		) {
			$message_template =
				(string) $defaults[
					'message_template'
				];
		}

		/*
		 * Checkout action placement is centralized in the main Checkout Flow
		 * display settings so Easy Setup presets and manual layout controls use
		 * one authoritative source. The legacy WhatsApp option remains a safe
		 * fallback for installations that have not migrated yet.
		 */
		$placement = sanitize_key( (string) ( $settings['placement'] ?? 'below_summary' ) );
		$checkout_settings = get_option( CheckoutSettings::OPTION_NAME, array() );
		if (
			is_array( $checkout_settings ) &&
			isset( $checkout_settings['checkout_display']['layout']['whatsapp_placement'] )
		) {
			$placement = sanitize_key(
				(string) $checkout_settings['checkout_display']['layout']['whatsapp_placement']
			);
		}

		if ( ! in_array( $placement, array( 'below_payment', 'below_summary', 'both' ), true ) ) {
			$placement = 'below_summary';
		}

		$style_defaults = WhatsAppSettings::get_defaults()['style'];
		$style = isset( $settings['style'] ) && is_array( $settings['style'] )
			? array_replace_recursive( $style_defaults, $settings['style'] )
			: $style_defaults;

		return array(
			'number' =>
				$number,

			'button_label' =>
				$button_label,

			'placement' =>
				$placement,

			'message_template' =>
				$message_template,

			'style' =>
				$style,
		);
	}

	/**
	 * Get default WhatsApp message.
	 *
	 * @return string
	 */
	private function get_default_message(): string {

		return implode(
			"\n",
			array(
				__(
					'Hello, I would like to place an order.',
					'eilmo-checkout-flow'
				),

				'',

				__(
					'Customer: {customer_name}',
					'eilmo-checkout-flow'
				),

				__(
					'Phone: {phone}',
					'eilmo-checkout-flow'
				),

				__(
					'Email: {email}',
					'eilmo-checkout-flow'
				),

				'',

				__(
					'Products:',
					'eilmo-checkout-flow'
				),

				'{products}',

				'',

				__(
					'Delivery: {delivery}',
					'eilmo-checkout-flow'
				),

				__(
					'Payment: {payment}',
					'eilmo-checkout-flow'
				),

				__(
					'Total: {total}',
					'eilmo-checkout-flow'
				),
			)
		);
	}

	/**
	 * Determine whether WhatsApp Ordering
	 * is enabled globally.
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

		$default_value =
			(string) (
				$defaults[
					'general'
				][
					'whatsapp_ordering'
				] ??
					'no'
			);

		if (
			! is_array(
				$stored
			) ||
			! isset(
				$stored[
					'general'
				]
			) ||
			! is_array(
				$stored[
					'general'
				]
			)
		) {
			return (
				'yes' ===
					$default_value
			);
		}

		return (
			'yes' ===
				(
					$stored[
						'general'
					][
						'whatsapp_ordering'
					] ??
						$default_value
				)
		);
	}
}
