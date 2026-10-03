<?php
/**
 * Elementor Combo Button widget.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Widgets;

use EilmoCheckout\Admin\CheckoutStyle;
use EilmoCheckout\ComboOffers\Services\ComboOfferRepository;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Elementor\Elementor as ElementorIntegration;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * A presentation-only trigger for the existing Combo Offer engine.
 *
 * The widget never calculates prices itself. It only selects/deselects one
 * server-configured Combo Offer in the checkout's hidden signed state.
 */
final class ComboButtonWidget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'eilmo-combo-button';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Checkout Flow – Combo Button', 'eilmo-checkout-flow' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-button';
	}

	/**
	 * Widget category.
	 *
	 * @return array<int,string>
	 */
	public function get_categories(): array {
		return array( ElementorIntegration::get_category() );
	}

	/**
	 * Search keywords.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords(): array {
		return array( 'combo', 'bundle', 'offer', 'button', 'checkout', 'woocommerce', 'eilmo' );
	}

	/**
	 * Use the same frontend behavior as Checkout Flow.
	 *
	 * @return array<int,string>
	 */
	public function get_script_depends(): array {
		return Assets::get_frontend_script_handles();
	}

	/**
	 * Ensure shared frontend CSS is registered in Elementor preview.
	 *
	 * @return array<int,string>
	 */
	public function get_style_depends(): array {
		Assets::register_frontend();
		return wp_style_is( Assets::FRONTEND_STYLE, 'registered' )
			? array( Assets::FRONTEND_STYLE )
			: array();
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'eilmo_combo_button_content',
			array(
				'label' => __( 'Combo Button', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'combo_id',
			array(
				'label'       => __( 'Combo Offer', 'eilmo-checkout-flow' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $this->get_combo_options(),
				'label_block' => true,
				'description' => __( 'Create and enable Combo Offers in Checkout Flow → Offers & Discounts → Combo Offers.', 'eilmo-checkout-flow' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button Text', 'eilmo-checkout-flow' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Add Combo', 'eilmo-checkout-flow' ),
				'placeholder' => __( 'Add Combo', 'eilmo-checkout-flow' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'added_text',
			array(
				'label'       => __( 'Added Text', 'eilmo-checkout-flow' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Combo Added', 'eilmo-checkout-flow' ),
				'placeholder' => __( 'Combo Added', 'eilmo-checkout-flow' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'allow_remove',
			array(
				'label'        => __( 'Click Again to Remove', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'eilmo-checkout-flow' ),
				'label_off'    => __( 'No', 'eilmo-checkout-flow' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'eilmo_combo_button_style',
			array(
				'label' => __( 'Button', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'     => __( 'Alignment', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Left', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Center', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Right', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-right' ),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .eilmo-cf-combo-button-widget' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'full_width',
			array(
				'label'        => __( 'Full Width', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'prefix_class' => 'eilmo-cf-combo-button--full-',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .eilmo-cf-combo-button',
			)
		);

		$this->start_controls_tabs( 'combo_button_state_tabs' );

		$this->start_controls_tab(
			'combo_button_normal',
			array( 'label' => __( 'Normal', 'eilmo-checkout-flow' ) )
		);
		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text Color', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'background_color',
			array(
				'label'     => __( 'Background', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab(
			'combo_button_hover',
			array( 'label' => __( 'Hover', 'eilmo-checkout-flow' ) )
		);
		$this->add_control(
			'hover_text_color',
			array(
				'label'     => __( 'Text Color', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button:hover' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hover_background_color',
			array(
				'label'     => __( 'Background', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button:hover' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab(
			'combo_button_selected',
			array( 'label' => __( 'Added', 'eilmo-checkout-flow' ) )
		);
		$this->add_control(
			'selected_text_color',
			array(
				'label'     => __( 'Text Color', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button.is-selected' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'selected_background_color',
			array(
				'label'     => __( 'Background', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-combo-button.is-selected' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'border',
				'selector' => '{{WRAPPER}} .eilmo-cf-combo-button',
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => __( 'Border Radius', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .eilmo-cf-combo-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => __( 'Padding', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .eilmo-cf-combo-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .eilmo-cf-combo-button',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$combo_id = sanitize_key( (string) ( $settings['combo_id'] ?? '' ) );

		if ( '' === $combo_id ) {
			return;
		}

		$offer = ( new ComboOfferRepository() )->get( $combo_id, true );
		if ( ! is_array( $offer ) ) {
			return;
		}

		$button_text = sanitize_text_field( (string) ( $settings['button_text'] ?? '' ) );
		$added_text  = sanitize_text_field( (string) ( $settings['added_text'] ?? '' ) );
		if ( '' === $button_text ) {
			$button_text = __( 'Add Combo', 'eilmo-checkout-flow' );
		}
		if ( '' === $added_text ) {
			$added_text = __( 'Combo Added', 'eilmo-checkout-flow' );
		}
		$allow_remove = 'yes' === ( $settings['allow_remove'] ?? 'yes' );
		$theme_css    = CheckoutStyle::get_css_variables();
		?>
		<div class="eilmo-cf-combo-button-widget" style="<?php echo esc_attr( $theme_css ); ?>">
			<button
				type="button"
				class="eilmo-cf-combo-button"
				data-eilmo-combo-trigger
				data-combo-id="<?php echo esc_attr( $combo_id ); ?>"
				data-add-label="<?php echo esc_attr( $button_text ); ?>"
				data-added-label="<?php echo esc_attr( $added_text ); ?>"
				data-allow-remove="<?php echo esc_attr( $allow_remove ? 'yes' : 'no' ); ?>"
				aria-pressed="false"
				aria-label="<?php echo esc_attr( (string) ( $offer['title'] ?? $button_text ) ); ?>"
			>
				<span data-eilmo-combo-trigger-text><?php echo esc_html( $button_text ); ?></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Enabled Combo Offer options.
	 *
	 * @return array<string,string>
	 */
	private function get_combo_options(): array {
		$options = array();
		foreach ( ( new ComboOfferRepository() )->get_all( true ) as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $offer['id'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $offer['title'] ?? '' ) );
			if ( '' !== $id && '' !== $title ) {
				$options[ $id ] = $title;
			}
		}
		return $options;
	}
}
