<?php
/**
 * Elementor WhatsApp button style controls.
 *
 * @package EilmoCheckout
 */
namespace EilmoCheckout\Elementor\Controls;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class WhatsAppButtonControls {
	public static function register( Widget_Base $widget ): void {
		$button = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-whatsapp-order .eilmo-cf-whatsapp-order__button';
		$widget->start_controls_section( 'eilmo_cf_whatsapp_style', [
			'label' => __( 'WhatsApp Button', 'eilmo-checkout-flow' ),
		'tab' => Controls_Manager::TAB_ADVANCED,
		] );
		$widget->add_responsive_control( 'eilmo_cf_whatsapp_width', [
			'label' => __( 'Width', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SLIDER,
			'size_units' => [ '%', 'px' ],
			'range' => [ '%' => [ 'min' => 10, 'max' => 100 ], 'px' => [ 'min' => 100, 'max' => 1000 ] ],
			'selectors' => [ $button => 'width: {{SIZE}}{{UNIT}};' ],
		] );
		$widget->add_control( 'eilmo_cf_whatsapp_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $button => 'background: {{VALUE}}; border-color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_whatsapp_text_color', [ 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $button => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_whatsapp_typography', 'selector' => $button ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_whatsapp_border', 'selector' => $button ] );
		$widget->add_responsive_control( 'eilmo_cf_whatsapp_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $button => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_whatsapp_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $button => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'eilmo_cf_whatsapp_shadow', 'selector' => $button ] );
		$widget->add_control( 'eilmo_cf_whatsapp_hover_heading', [ 'label' => __( 'Hover', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_whatsapp_hover_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $button . ':hover, ' . $button . ':focus-visible' => 'background: {{VALUE}}; border-color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_whatsapp_hover_text_color', [ 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $button . ':hover, ' . $button . ':focus-visible' => 'color: {{VALUE}};' ] ] );
		$widget->end_controls_section();
	}
}
