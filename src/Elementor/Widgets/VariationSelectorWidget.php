<?php
/**
 * Elementor Variation Selector widget.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Widgets;

use EilmoCheckout\Admin\CheckoutStyle;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Elementor\Elementor as ElementorIntegration;
use EilmoCheckout\Products\Services\VariationBadgeService;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Presentation-only exact-variation selector. It drives the same Checkout Flow
 * state as the in-form package selector and never calculates authoritative
 * prices itself.
 */
final class VariationSelectorWidget extends Widget_Base {
	public function get_name(): string { return 'eilmo-variation-selector'; }
	public function get_title(): string { return __( 'Checkout Flow – Variation Selector', 'eilmo-checkout-flow' ); }
	public function get_icon(): string { return 'eicon-gallery-grid'; }
	public function get_categories(): array { return array( ElementorIntegration::get_category() ); }
	public function get_keywords(): array { return array( 'variation', 'variant', 'package', 'size', 'color', 'checkout', 'woocommerce', 'eilmo' ); }
	public function get_script_depends(): array { return Assets::get_frontend_script_handles(); }
	public function get_style_depends(): array { Assets::register_frontend(); return wp_style_is( Assets::FRONTEND_STYLE, 'registered' ) ? array( Assets::FRONTEND_STYLE ) : array(); }

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_layout_controls();
		$this->register_card_controls();
		$this->register_image_controls();
		$this->register_text_element_controls( 'title', __( 'Variation Title', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__title' );
		$this->register_text_element_controls( 'description', __( 'Description', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__description' );
		$this->register_badge_controls();
		$this->register_text_element_controls( 'price', __( 'Price', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__price' );
		$this->register_text_element_controls( 'regular_price', __( 'Regular Price', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__regular-price' );
		$this->register_pill_element_controls( 'saving', __( 'Save Amount', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__saving' );
		$this->register_pill_element_controls( 'discount', __( 'Discount Percentage', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__discount' );
		$this->register_text_element_controls( 'stock', __( 'Stock Status', 'eilmo-checkout-flow' ), '.eilmo-cf-variation-selector__stock' );
		$this->register_check_controls();
	}

	private function register_content_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_content',
			array(
				'label' => __( 'Variation Selector', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'product_id',
			array(
				'label'       => __( 'Variable Product', 'eilmo-checkout-flow' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $this->get_variable_product_options(),
				'label_block' => true,
			)
		);
		$this->add_control(
			'display_mode',
			array(
				'label'   => __( 'Display', 'eilmo-checkout-flow' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'    => __( 'Grid Cards', 'eilmo-checkout-flow' ),
					'buttons' => __( 'Compact Buttons', 'eilmo-checkout-flow' ),
				),
			)
		);
		foreach (
			array(
				'show_image'         => __( 'Image', 'eilmo-checkout-flow' ),
				'show_title'         => __( 'Variation Title', 'eilmo-checkout-flow' ),
				'show_description'   => __( 'Description', 'eilmo-checkout-flow' ),
				'show_badge'         => __( 'Badge', 'eilmo-checkout-flow' ),
				'show_price'         => __( 'Price', 'eilmo-checkout-flow' ),
				'show_regular_price' => __( 'Regular Price', 'eilmo-checkout-flow' ),
				'show_saving'        => __( 'Save Amount', 'eilmo-checkout-flow' ),
				'show_discount'      => __( 'Discount Percentage', 'eilmo-checkout-flow' ),
				'show_stock'         => __( 'Stock Status', 'eilmo-checkout-flow' ),
			) as $key => $label
		) {
			$this->add_control(
				$key,
				array(
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => in_array( $key, array( 'show_title', 'show_price' ), true ) ? 'yes' : '',
				)
			);
		}
		$this->add_control(
			'show_check',
			array(
				'label'        => __( 'Selected Check', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'auto_add',
			array(
				'label'        => __( 'Add to Checkout on Select', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'allow_deselect',
			array(
				'label'        => __( 'Allow Deselect', 'eilmo-checkout-flow' ),
				'description'  => __( 'Click the selected variation again to deselect/remove it from checkout.', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();
	}

	private function register_layout_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_layout',
			array(
				'label' => __( 'Layout', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'eilmo-checkout-flow' ),
				'type'           => Controls_Manager::NUMBER,
				'default'        => 4,
				'tablet_default' => 2,
				'mobile_default' => 2,
				'min'            => 1,
				'max'            => 8,
				'selectors'      => array( '{{WRAPPER}} .eilmo-cf-variation-selector' => '--eilmo-vs-columns: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'     => __( 'Grid Gap', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'content_gap',
			array(
				'label'     => __( 'Content Gap', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'   => array( 'size' => 5, 'unit' => 'px' ),
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__body' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	private function register_card_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_card_style',
			array(
				'label' => __( 'Card', 'eilmo-checkout-flow' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'card_min_height',
			array(
				'label'      => __( 'Minimum Height', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 800 ) ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__option' => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( 'variation_card_tabs' );
		foreach ( $this->states() as $state => $label ) {
			$this->start_controls_tab( 'variation_card_' . $state, array( 'label' => $label ) );
			$selector = $this->state_selector( '.eilmo-cf-variation-selector__option', $state );
			$this->add_control(
				'card_' . $state . '_background',
				array(
					'label'     => __( 'Background', 'eilmo-checkout-flow' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $selector => 'background: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'card_' . $state . '_border_color',
				array(
					'label'     => __( 'Border Color', 'eilmo-checkout-flow' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $selector => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'card_' . $state . '_shadow',
					'selector' => $selector,
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .eilmo-cf-variation-selector__option',
			)
		);
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border Radius', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	private function register_image_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_image_style',
			array(
				'label'     => __( 'Image', 'eilmo-checkout-flow' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_image' => 'yes', 'display_mode' => 'grid' ),
			)
		);
		$this->add_responsive_control(
			'image_alignment',
			array(
				'label'     => __( 'Alignment', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'flex-start' => array( 'title' => __( 'Left', 'eilmo-checkout-flow' ), 'icon' => 'eicon-h-align-left' ),
					'center'     => array( 'title' => __( 'Center', 'eilmo-checkout-flow' ), 'icon' => 'eicon-h-align-center' ),
					'flex-end'   => array( 'title' => __( 'Right', 'eilmo-checkout-flow' ), 'icon' => 'eicon-h-align-right' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .eilmo-cf-variation-selector__image' => 'align-self: {{VALUE}};',
				),
			)
		);
		$this->add_responsive_control(
			'image_width',
			array(
				'label'      => __( 'Width', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 800 ), '%' => array( 'min' => 10, 'max' => 100 ) ),
				'default'    => array( 'size' => 100, 'unit' => '%' ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__image' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => __( 'Height', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 800 ) ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__image' => 'height: {{SIZE}}{{UNIT}}; aspect-ratio: auto;' ),
			)
		);
		$this->add_control(
			'image_object_fit',
			array(
				'label'     => __( 'Object Fit', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => array( 'cover' => __( 'Cover', 'eilmo-checkout-flow' ), 'contain' => __( 'Contain', 'eilmo-checkout-flow' ), 'fill' => __( 'Fill', 'eilmo-checkout-flow' ) ),
				'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__image' => 'object-fit: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'image_radius',
			array(
				'label'      => __( 'Border Radius', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( 'variation_image_states' );
		foreach ( $this->states() as $state => $label ) {
			$this->start_controls_tab( 'variation_image_' . $state, array( 'label' => $label ) );
			$selector = $this->state_descendant_selector( '.eilmo-cf-variation-selector__image', $state );
			$this->add_control(
				'image_' . $state . '_opacity',
				array(
					'label'     => __( 'Opacity', 'eilmo-checkout-flow' ),
					'type'      => Controls_Manager::SLIDER,
					'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
					'default'   => array( 'size' => 1 ),
					'selectors' => array( $selector => 'opacity: {{SIZE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function register_text_element_controls( string $id, string $label, string $class_selector ): void {
		$this->start_controls_section(
			'eilmo_variation_' . $id . '_style',
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			$id . '_alignment',
			array(
				'label'     => __( 'Alignment', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => $this->alignment_options(),
				'selectors' => array( '{{WRAPPER}} ' . $class_selector => 'text-align: {{VALUE}}; align-self: stretch;' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => $id . '_typography',
				'selector' => '{{WRAPPER}} ' . $class_selector,
			)
		);
		$this->add_responsive_control(
			$id . '_margin',
			array(
				'label'      => __( 'Margin', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'selectors'  => array( '{{WRAPPER}} ' . $class_selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( 'variation_' . $id . '_states' );
		foreach ( $this->states() as $state => $state_label ) {
			$this->start_controls_tab( 'variation_' . $id . '_' . $state, array( 'label' => $state_label ) );
			$this->add_control(
				$id . '_' . $state . '_color',
				array(
					'label'     => __( 'Text Color', 'eilmo-checkout-flow' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $this->state_descendant_selector( $class_selector, $state ) => 'color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function register_pill_element_controls( string $id, string $label, string $class_selector ): void {
		$this->start_controls_section(
			'eilmo_variation_' . $id . '_style',
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			$id . '_alignment',
			array(
				'label'     => __( 'Alignment', 'eilmo-checkout-flow' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'flex-start' => array( 'title' => __( 'Left', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-left' ),
					'center'     => array( 'title' => __( 'Center', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => __( 'Right', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} ' . $class_selector => 'align-self: {{VALUE}};' ),
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => $id . '_typography', 'selector' => '{{WRAPPER}} ' . $class_selector ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => $id . '_border', 'selector' => '{{WRAPPER}} ' . $class_selector ) );
		$this->add_responsive_control(
			$id . '_radius',
			array(
				'label'      => __( 'Border Radius', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} ' . $class_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			$id . '_padding',
			array(
				'label'      => __( 'Padding', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array( '{{WRAPPER}} ' . $class_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->start_controls_tabs( 'variation_' . $id . '_states' );
		foreach ( $this->states() as $state => $state_label ) {
			$this->start_controls_tab( 'variation_' . $id . '_' . $state, array( 'label' => $state_label ) );
			$selector = $this->state_descendant_selector( $class_selector, $state );
			$this->add_control( $id . '_' . $state . '_color', array( 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'color: {{VALUE}};' ) ) );
			$this->add_control( $id . '_' . $state . '_background', array( 'label' => __( 'Background Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( $id . '_' . $state . '_border_color', array( 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function register_badge_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_badge_style',
			array(
				'label'     => __( 'Badge', 'eilmo-checkout-flow' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_badge' => 'yes' ),
			)
		);
		$this->add_control(
			'badge_alignment',
			array(
				'label'        => __( 'Position', 'eilmo-checkout-flow' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'center',
				'options'      => $this->alignment_options(),
				'prefix_class' => 'eilmo-vs-badge-align-',
			)
		);
		$this->add_responsive_control(
			'badge_top',
			array(
				'label'      => __( 'Top Offset', 'eilmo-checkout-flow' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => -50, 'max' => 100 ), '%' => array( 'min' => -20, 'max' => 50 ) ),
				'default'    => array( 'size' => -11, 'unit' => 'px' ),
				'selectors'  => array( '{{WRAPPER}} .eilmo-cf-variation-selector__badge' => 'top: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'badge_typography', 'selector' => '{{WRAPPER}} .eilmo-cf-variation-selector__badge' ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'badge_border', 'selector' => '{{WRAPPER}} .eilmo-cf-variation-selector__badge' ) );
		$this->add_responsive_control( 'badge_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'badge_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->start_controls_tabs( 'variation_badge_states' );
		foreach ( $this->states() as $state => $state_label ) {
			$this->start_controls_tab( 'variation_badge_' . $state, array( 'label' => $state_label ) );
			$selector = $this->state_descendant_selector( '.eilmo-cf-variation-selector__badge', $state );
			$this->add_control( 'badge_' . $state . '_color', array( 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'badge_' . $state . '_background', array( 'label' => __( 'Background Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'badge_' . $state . '_border_color', array( 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	private function register_check_controls(): void {
		$this->start_controls_section(
			'eilmo_variation_check_style',
			array(
				'label'     => __( 'Selected Check', 'eilmo-checkout-flow' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_check' => 'yes' ),
			)
		);
		$this->add_responsive_control( 'check_size', array( 'label' => __( 'Size', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 14, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'check_icon_size', array( 'label' => __( 'Icon Size', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 8, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'check_top', array( 'label' => __( 'Top Offset', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -40, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'top: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'check_right', array( 'label' => __( 'Right Offset', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -40, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'right: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'check_color', array( 'label' => __( 'Icon Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'check_background', array( 'label' => __( 'Background Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .eilmo-cf-variation-selector__check' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'check_border', 'selector' => '{{WRAPPER}} .eilmo-cf-variation-selector__check' ) );
		$this->end_controls_section();
	}

	private function states(): array {
		return array(
			'normal'   => __( 'Normal', 'eilmo-checkout-flow' ),
			'hover'    => __( 'Hover', 'eilmo-checkout-flow' ),
			'selected' => __( 'Selected', 'eilmo-checkout-flow' ),
			'disabled' => __( 'Out of Stock', 'eilmo-checkout-flow' ),
		);
	}

	private function alignment_options(): array {
		return array(
			'left'   => array( 'title' => __( 'Left', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => __( 'Center', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-center' ),
			'right'  => array( 'title' => __( 'Right', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-right' ),
		);
	}

	private function state_selector( string $selector, string $state ): string {
		if ( 'hover' === $state ) {
			return '{{WRAPPER}} ' . $selector . ':hover:not(:disabled)';
		}
		if ( 'selected' === $state ) {
			return '{{WRAPPER}} ' . $selector . '.is-selected';
		}
		if ( 'disabled' === $state ) {
			return '{{WRAPPER}} ' . $selector . ':disabled';
		}
		return '{{WRAPPER}} ' . $selector;
	}

	private function state_descendant_selector( string $descendant, string $state ): string {
		if ( 'hover' === $state ) {
			return '{{WRAPPER}} .eilmo-cf-variation-selector__option:hover:not(:disabled) ' . $descendant;
		}
		if ( 'selected' === $state ) {
			return '{{WRAPPER}} .eilmo-cf-variation-selector__option.is-selected ' . $descendant;
		}
		if ( 'disabled' === $state ) {
			return '{{WRAPPER}} .eilmo-cf-variation-selector__option:disabled ' . $descendant;
		}
		return '{{WRAPPER}} .eilmo-cf-variation-selector__option ' . $descendant;
	}

	protected function render(): void {
		$s          = $this->get_settings_for_display();
		$product_id = absint( $s['product_id'] ?? 0 );
		$product    = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product_Variable ) {
			return;
		}
		$badge_service = new VariationBadgeService();
		$display       = 'buttons' === ( $s['display_mode'] ?? 'grid' ) ? 'buttons' : 'grid';
		$theme_css     = CheckoutStyle::get_css_variables();
		?>
		<div class="eilmo-cf-variation-selector eilmo-cf-variation-selector--<?php echo esc_attr( $display ); ?>" style="<?php echo esc_attr( $theme_css ); ?>" data-eilmo-external-variation-selector data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-auto-add="<?php echo esc_attr( 'yes' === ( $s['auto_add'] ?? 'yes' ) ? 'yes' : 'no' ); ?>" data-allow-deselect="<?php echo esc_attr( 'yes' === ( $s['allow_deselect'] ?? 'yes' ) ? 'yes' : 'no' ); ?>">
		<?php foreach ( $product->get_children() as $variation_id ) :
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof \WC_Product_Variation ) { continue; }
			$can_purchase = $variation->is_purchasable() && $variation->is_in_stock();
			$max_quantity = $variation->get_max_purchase_quantity();
			$current      = (float) $variation->get_price();
			$regular      = (float) $variation->get_regular_price();
			$save         = $regular > $current ? max( 0, $regular - $current ) : 0;
			$discount     = $regular > 0 && $save > 0 ? (int) round( ( $save / $regular ) * 100 ) : 0;
			$description  = trim( wp_strip_all_tags( (string) $variation->get_description() ) );
			$badge        = $badge_service->get_badge( $variation );
			$image_id     = absint( $variation->get_image_id() ?: $product->get_image_id() );
			$image        = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			if ( ! is_string( $image ) || '' === $image ) { $image = wc_placeholder_img_src( 'woocommerce_thumbnail' ); }
			$label = wc_get_formatted_variation( $variation, true, false, false );
			?>
			<button type="button" class="eilmo-cf-variation-selector__option" data-eilmo-external-variation-option
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				data-price="<?php echo esc_attr( wc_format_decimal( $current ) ); ?>" data-max-quantity="<?php echo esc_attr( (string) max( 0, (int) $max_quantity ) ); ?>" data-item-name="<?php echo esc_attr( $product->get_name() ); ?>"
				data-item-variation="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>" data-item-image="<?php echo esc_url( $image ); ?>"
				data-can-purchase="<?php echo esc_attr( $can_purchase ? 'yes' : 'no' ); ?>" aria-pressed="false" <?php disabled( ! $can_purchase ); ?>>
				<?php if ( 'yes' === ( $s['show_image'] ?? '' ) && 'grid' === $display ) : ?><img class="eilmo-cf-variation-selector__image" src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"><?php endif; ?>
				<span class="eilmo-cf-variation-selector__body">
					<?php if ( 'yes' === ( $s['show_badge'] ?? '' ) && 'yes' === (string) ( $badge['enabled'] ?? 'no' ) && '' !== (string) ( $badge['text'] ?? '' ) ) : ?><span class="eilmo-cf-variation-selector__badge"><?php echo esc_html( (string) $badge['text'] ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_title'] ?? 'yes' ) ) : ?><strong class="eilmo-cf-variation-selector__title"><?php echo esc_html( wp_strip_all_tags( $label ) ); ?></strong><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_description'] ?? '' ) && '' !== $description ) : ?><span class="eilmo-cf-variation-selector__description"><?php echo esc_html( $description ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_price'] ?? 'yes' ) ) : ?><span class="eilmo-cf-variation-selector__price"><?php echo wp_kses_post( wc_price( $current ) ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_regular_price'] ?? '' ) && $regular > $current ) : ?><del class="eilmo-cf-variation-selector__regular-price"><?php echo wp_kses_post( wc_price( $regular ) ); ?></del><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_saving'] ?? '' ) && $save > 0 ) : ?><span class="eilmo-cf-variation-selector__saving"><?php echo esc_html( sprintf( __( 'Save %s', 'eilmo-checkout-flow' ), wp_strip_all_tags( wc_price( $save ) ) ) ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_discount'] ?? '' ) && $discount > 0 ) : ?><span class="eilmo-cf-variation-selector__discount"><?php echo esc_html( sprintf( __( '%d%% OFF', 'eilmo-checkout-flow' ), $discount ) ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_stock'] ?? '' ) ) : ?><span class="eilmo-cf-variation-selector__stock"><?php echo esc_html( $can_purchase ? __( 'In stock', 'eilmo-checkout-flow' ) : __( 'Out of stock', 'eilmo-checkout-flow' ) ); ?></span><?php endif; ?>
					<?php if ( 'yes' === ( $s['show_check'] ?? 'yes' ) ) : ?><span class="eilmo-cf-variation-selector__check" aria-hidden="true">✓</span><?php endif; ?>
				</span>
			</button>
		<?php endforeach; ?>
		</div>
		<?php
	}

	private function get_variable_product_options(): array {
		$options = array();
		if ( ! function_exists( 'wc_get_products' ) ) { return $options; }
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'type' => 'variable' ) ) as $product ) {
			if ( $product instanceof \WC_Product_Variable ) { $options[ $product->get_id() ] = $product->get_name(); }
		}
		return $options;
	}
}
