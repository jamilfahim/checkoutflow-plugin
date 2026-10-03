<?php
/**
 * Cart Drawer Elementor widget.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Widgets;

use EilmoCheckout\Elementor\Elementor as ElementorIntegration;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Cart Drawer Elementor widget.
 *
 * The widget only renders a Cart Drawer trigger.
 *
 * The actual WooCommerce cart state, drawer content,
 * quantity updates, remove actions and checkout access
 * remain controlled by the Cart Drawer service.
 */
final class CartDrawerWidget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {

		return 'eilmo-cart-drawer';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __(
			'Cart Drawer',
			'eilmo-checkout-flow'
		);
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {

		return 'eicon-cart';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {

		return array(
			ElementorIntegration::get_category(),
		);
	}

	/**
	 * Widget keywords.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {

		return array(
			'cart',
			'cart drawer',
			'woocommerce',
			'mini cart',
			'cart icon',
			'eilmo',
		);
	}

	/**
	 * Avoid unnecessary Elementor inner wrapper.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {

		return false;
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {

		$this->register_cart_controls();
		$this->register_trigger_style_controls();
		$this->register_icon_style_controls();
		$this->register_count_style_controls();
	}

	/**
	 * Register Cart Drawer content controls.
	 *
	 * @return void
	 */
	private function register_cart_controls(): void {

		$this->start_controls_section(
			'eilmo_cf_cart_drawer_content',
			array(
				'label' =>
					__(
						'Cart Drawer',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'eilmo_cf_cart_drawer_notice',
			array(
				'type' =>
					Controls_Manager::RAW_HTML,

				'raw' =>
					__(
						'This widget opens the Eilmo Cart Drawer. Cart products, quantities and subtotal are loaded automatically from the WooCommerce cart.',
						'eilmo-checkout-flow'
					),

				'content_classes' =>
					'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'cart_icon',
			array(
				'label' =>
					__(
						'Cart Icon',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::ICONS,

				'default' =>
					array(
						'value' =>
							'fas fa-shopping-cart',

						'library' =>
							'fa-solid',
					),
			)
		);

		$this->add_control(
			'show_count',
			array(
				'label' =>
					__(
						'Show Cart Count',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SWITCHER,

				'label_on' =>
					__(
						'Show',
						'eilmo-checkout-flow'
					),

				'label_off' =>
					__(
						'Hide',
						'eilmo-checkout-flow'
					),

				'return_value' =>
					'yes',

				'default' =>
					'yes',
			)
		);

		$this->add_control(
			'drawer_position',
			array(
				'label' =>
					__(
						'Drawer Position',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SELECT,

				'default' =>
					'right',

				'options' =>
					array(
						'right' =>
							__(
								'Right',
								'eilmo-checkout-flow'
							),

						'left' =>
							__(
								'Left',
								'eilmo-checkout-flow'
							),

						'bottom' =>
							__(
								'Bottom',
								'eilmo-checkout-flow'
							),
					),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register trigger style controls.
	 *
	 * @return void
	 */
	private function register_trigger_style_controls(): void {

		$this->start_controls_section(
			'eilmo_cf_cart_trigger_style',
			array(
				'label' =>
					__(
						'Trigger',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label' =>
					__(
						'Alignment',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::CHOOSE,

				'options' =>
					array(
						'flex-start' =>
							array(
								'title' =>
									__(
										'Left',
										'eilmo-checkout-flow'
									),

								'icon' =>
									'eicon-text-align-left',
							),

						'center' =>
							array(
								'title' =>
									__(
										'Center',
										'eilmo-checkout-flow'
									),

								'icon' =>
									'eicon-text-align-center',
							),

						'flex-end' =>
							array(
								'title' =>
									__(
										'Right',
										'eilmo-checkout-flow'
									),

								'icon' =>
									'eicon-text-align-right',
							),
					),

				'default' =>
					'flex-start',

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger-wrap' =>
							'justify-content: {{VALUE}};',
					),
			)
		);

		$this->add_responsive_control(
			'trigger_size',
			array(
				'label' =>
					__(
						'Trigger Size',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'size_units' =>
					array(
						'px',
					),

				'range' =>
					array(
						'px' =>
							array(
								'min' => 30,
								'max' => 120,
							),
					),

				'default' =>
					array(
						'size' => 46,
						'unit' => 'px',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$this->add_responsive_control(
			'trigger_padding',
			array(
				'label' =>
					__(
						'Padding',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::DIMENSIONS,

				'size_units' =>
					array(
						'px',
						'em',
						'%',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$this->add_responsive_control(
			'trigger_border_radius',
			array(
				'label' =>
					__(
						'Border Radius',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::DIMENSIONS,

				'size_units' =>
					array(
						'px',
						'%',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$this->start_controls_tabs(
			'eilmo_cf_cart_trigger_tabs'
		);

		$this->start_controls_tab(
			'eilmo_cf_cart_trigger_normal',
			array(
				'label' =>
					__(
						'Normal',
						'eilmo-checkout-flow'
					),
			)
		);

		$this->add_control(
			'trigger_background',
			array(
				'label' =>
					__(
						'Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$this->add_control(
			'trigger_border_color',
			array(
				'label' =>
					__(
						'Border Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'border-color: {{VALUE}};',
					),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'eilmo_cf_cart_trigger_hover',
			array(
				'label' =>
					__(
						'Hover',
						'eilmo-checkout-flow'
					),
			)
		);

		$this->add_control(
			'trigger_hover_background',
			array(
				'label' =>
					__(
						'Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:hover' =>
							'background-color: {{VALUE}};',

						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:focus-visible' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$this->add_control(
			'trigger_hover_border_color',
			array(
				'label' =>
					__(
						'Border Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:hover' =>
							'border-color: {{VALUE}};',

						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:focus-visible' =>
							'border-color: {{VALUE}};',
					),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register icon style controls.
	 *
	 * @return void
	 */
	private function register_icon_style_controls(): void {

		$this->start_controls_section(
			'eilmo_cf_cart_icon_style',
			array(
				'label' =>
					__(
						'Icon',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label' =>
					__(
						'Size',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'size_units' =>
					array(
						'px',
					),

				'range' =>
					array(
						'px' =>
							array(
								'min' => 10,
								'max' => 80,
							),
					),

				'default' =>
					array(
						'size' => 22,
						'unit' => 'px',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__icon' =>
							'font-size: {{SIZE}}{{UNIT}};',

						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__icon svg' =>
							'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$this->start_controls_tabs(
			'eilmo_cf_cart_icon_tabs'
		);

		$this->start_controls_tab(
			'eilmo_cf_cart_icon_normal',
			array(
				'label' =>
					__(
						'Normal',
						'eilmo-checkout-flow'
					),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label' =>
					__(
						'Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'default' =>
					'#111827',

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger' =>
							'color: {{VALUE}};',
					),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'eilmo_cf_cart_icon_hover',
			array(
				'label' =>
					__(
						'Hover',
						'eilmo-checkout-flow'
					),
			)
		);

		$this->add_control(
			'icon_hover_color',
			array(
				'label' =>
					__(
						'Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:hover' =>
							'color: {{VALUE}};',

						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger:focus-visible' =>
							'color: {{VALUE}};',
					),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register Cart Count style controls.
	 *
	 * @return void
	 */
	private function register_count_style_controls(): void {

		$this->start_controls_section(
			'eilmo_cf_cart_count_style',
			array(
				'label' =>
					__(
						'Cart Count',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_STYLE,

				'condition' =>
					array(
						'show_count' =>
							'yes',
					),
			)
		);

		$this->add_control(
			'count_background',
			array(
				'label' =>
					__(
						'Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'default' =>
					'#7c3aed',

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__count' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$this->add_control(
			'count_color',
			array(
				'label' =>
					__(
						'Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'default' =>
					'#ffffff',

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__count' =>
							'color: {{VALUE}};',
					),
			)
		);

		$this->add_responsive_control(
			'count_size',
			array(
				'label' =>
					__(
						'Badge Size',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'size_units' =>
					array(
						'px',
					),

				'range' =>
					array(
						'px' =>
							array(
								'min' => 14,
								'max' => 40,
							),
					),

				'default' =>
					array(
						'size' => 22,
						'unit' => 'px',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__count' =>
							'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$this->add_responsive_control(
			'count_font_size',
			array(
				'label' =>
					__(
						'Font Size',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'size_units' =>
					array(
						'px',
					),

				'range' =>
					array(
						'px' =>
							array(
								'min' => 8,
								'max' => 20,
							),
					),

				'default' =>
					array(
						'size' => 11,
						'unit' => 'px',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__count' =>
							'font-size: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$this->add_control(
			'count_animation',
			array(
				'label' =>
					__(
						'Animation',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SELECT,

				'default' =>
					'heartbeat',

				'options' =>
					array(
						'none' =>
							__(
								'None',
								'eilmo-checkout-flow'
							),

						'heartbeat' =>
							__(
								'Heartbeat',
								'eilmo-checkout-flow'
							),

						'pulse' =>
							__(
								'Soft Pulse',
								'eilmo-checkout-flow'
							),

						'glow_wave' =>
							__(
								'Glow Wave',
								'eilmo-checkout-flow'
							),
					),
			)
		);

		$this->add_control(
			'count_animation_color',
			array(
				'label' =>
					__(
						'Animation Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'default' =>
					'#7c3aed',

				'condition' =>
					array(
						'count_animation!' =>
							'none',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-cart-trigger__count' =>
							'--eilmo-cart-count-animation-color: {{VALUE}};',
					),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 *
	 * @return void
	 */
	protected function render(): void {

		$elementor_settings =
			$this->get_settings_for_display();

		if (
			! is_array(
				$elementor_settings
			)
		) {
			$elementor_settings =
				array();
		}

		$position =
			$this->normalize_drawer_position(
				$elementor_settings[
					'drawer_position'
				] ??
					'right'
			);

		$show_count =
			'yes' ===
				(
					$elementor_settings[
						'show_count'
					] ??
						'yes'
				);

		$count_animation =
			$this->normalize_count_animation(
				$elementor_settings[
					'count_animation'
				] ??
					'heartbeat'
			);

		$icon =
			isset(
				$elementor_settings[
					'cart_icon'
				]
			) &&
			is_array(
				$elementor_settings[
					'cart_icon'
				]
			)
				? $elementor_settings[
					'cart_icon'
				]
				: array();

		$cart_count =
			$this->get_cart_count();

		?>

		<div class="eilmo-cf-cart-trigger-wrap">

			<button
				type="button"
				class="eilmo-cf-cart-trigger"
				data-eilmo-cart-drawer-trigger
				data-eilmo-cart-drawer-position="<?php echo esc_attr( $position ); ?>"
				aria-label="<?php esc_attr_e( 'Open cart', 'eilmo-checkout-flow' ); ?>"
			>

				<span
					class="eilmo-cf-cart-trigger__icon"
					aria-hidden="true"
				>

					<?php
					if (
						! empty(
							$icon[
								'value'
							]
						)
					) {
						Icons_Manager::render_icon(
							$icon,
							array(
								'aria-hidden' =>
									'true',
							)
						);
					}
					?>

				</span>

				<?php if ( $show_count ) : ?>

					<span
						class="eilmo-cf-cart-trigger__count"
						data-eilmo-cart-count
						data-count="<?php echo esc_attr( (string) $cart_count ); ?>"
						data-eilmo-count-animation="<?php echo esc_attr( $count_animation ); ?>"
						aria-hidden="true"
					>
						<?php echo esc_html( (string) $cart_count ); ?>
					</span>

				<?php endif; ?>

			</button>

		</div>

		<?php
	}

	/**
	 * Normalize Cart Drawer position.
	 *
	 * @param mixed $position Position.
	 *
	 * @return string
	 */
	private function normalize_drawer_position(
		$position
	): string {

		$position =
			sanitize_key(
				(string) $position
			);

		if (
			! in_array(
				$position,
				array(
					'right',
					'left',
					'bottom',
				),
				true
			)
		) {
			return 'right';
		}

		return $position;
	}

	/**
	 * Normalize Cart Count animation.
	 *
	 * @param mixed $animation Animation.
	 *
	 * @return string
	 */
	private function normalize_count_animation(
		$animation
	): string {

		$animation =
			sanitize_key(
				(string) $animation
			);

		if (
			! in_array(
				$animation,
				array(
					'none',
					'heartbeat',
					'pulse',
					'glow_wave',
				),
				true
			)
		) {
			return 'heartbeat';
		}

		return $animation;
	}

	/**
	 * Get current WooCommerce cart count.
	 *
	 * @return int
	 */
	private function get_cart_count(): int {

		if (
			! function_exists(
				'WC'
			)
		) {
			return 0;
		}

		$woocommerce =
			WC();

		if (
			! $woocommerce ||
			! isset(
				$woocommerce->cart
			) ||
			! $woocommerce->cart
		) {
			return 0;
		}

		return absint(
			$woocommerce
				->cart
				->get_cart_contents_count()
		);
	}
}