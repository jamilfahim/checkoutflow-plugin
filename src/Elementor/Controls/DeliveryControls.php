<?php
/**
 * Simplified Elementor Delivery style controls.
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

final class DeliveryControls {
	public static function register( Widget_Base $widget ): void {
		$root = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-delivery';
		$card = $root . ' .eilmo-cf-delivery-method';
		$selected = $root . ' .eilmo-cf-delivery-method--selected, ' . $root . ' .eilmo-cf-delivery-method:has(input:checked)';
		$label = $root . ' .eilmo-cf-delivery-method__label';
		$desc = $root . ' .eilmo-cf-delivery-method__description';
		$price = $root . ' .eilmo-cf-delivery-method__price';
		$free = $root . ' .eilmo-cf-delivery-method--free .eilmo-cf-delivery-method__price';

		$widget->start_controls_section( 'eilmo_cf_style_delivery', [
			'label' => __( 'Delivery', 'eilmo-checkout-flow' ),
			'tab' => Controls_Manager::TAB_ADVANCED,
		] );

		$widget->add_control( 'eilmo_cf_delivery_section_heading', [ 'label' => __( 'Section', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING ] );
		$widget->add_control( 'eilmo_cf_delivery_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => 'background: {{VALUE}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $root => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->add_control( 'eilmo_cf_delivery_title_heading', [ 'label' => __( 'Title', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_delivery_title_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root . ' .eilmo-cf-delivery__title' => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_delivery_title_typography', 'selector' => $root . ' .eilmo-cf-delivery__title' ] );
		$widget->add_control( 'eilmo_cf_delivery_required_color', [ 'label' => __( 'Required Mark Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root . ' .eilmo-cf-delivery__required' => 'color: {{VALUE}};' ] ] );

		$widget->add_control( 'eilmo_cf_delivery_methods_heading', [ 'label' => __( 'Delivery Cards', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_methods_gap', [ 'label' => __( 'Gap', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ $root . ' .eilmo-cf-delivery__methods' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$widget->add_control( 'eilmo_cf_delivery_method_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $card => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_delivery_method_border', 'selector' => $card ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_method_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_method_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'eilmo_cf_delivery_method_shadow', 'selector' => $card ] );

		$widget->add_control( 'eilmo_cf_delivery_selected_heading', [ 'label' => __( 'Selected Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_delivery_selected_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'background: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_delivery_selected_border_color', [ 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'border-color: {{VALUE}};' ] ] );

		$widget->add_control( 'eilmo_cf_delivery_label_heading', [ 'label' => __( 'Card Label', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_delivery_label_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $label => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_delivery_label_typography', 'selector' => $label ] );

		$widget->add_control( 'eilmo_cf_delivery_description_heading', [ 'label' => __( 'Card Description', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_delivery_description_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $desc => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_delivery_description_typography', 'selector' => $desc ] );

		$widget->add_control( 'eilmo_cf_delivery_price_heading', [ 'label' => __( 'Price', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_delivery_price_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $price . ', ' . $price . ' .woocommerce-Price-amount, ' . $price . ' .woocommerce-Price-currencySymbol' => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_delivery_price_typography', 'selector' => $price ] );

		$widget->add_control( 'eilmo_cf_delivery_free_badge_heading', [ 'label' => __( 'Free Badge', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_delivery_free_badge_typography', 'selector' => $free ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_free_badge_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $free => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_delivery_free_badge_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $free => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->end_controls_section();
	}
}
