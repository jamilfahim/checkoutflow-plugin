<?php
/**
 * Simplified Elementor Payment Methods style controls.
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

final class PaymentMethodsControls {
	public static function register( Widget_Base $widget ): void {
		$root = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-payment-methods';
		$card = $root . ' .eilmo-cf-payment-method__choice';
		$selected = $root . ' .eilmo-cf-payment-method.is-selected .eilmo-cf-payment-method__choice';
		$title = $root . ' .eilmo-cf-payment-method__title';
		$desc = $root . ' .eilmo-cf-payment-method__description';
		$check = $root . ' .eilmo-cf-payment-method__selected-check';
		$details = $root . ' .eilmo-cf-payment-method__details';

		$widget->start_controls_section( 'eilmo_cf_style_payment_methods', [
			'label' => __( 'Payment Methods', 'eilmo-checkout-flow' ),
			'tab' => Controls_Manager::TAB_ADVANCED,
		] );

		$widget->add_control( 'eilmo_cf_payment_methods_section_heading', [ 'label' => __( 'Section', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING ] );
		$widget->add_control( 'eilmo_cf_payment_methods_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_payment_methods_border', 'selector' => $root ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_methods_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $root => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_methods_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $root => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_methods_title_heading', [ 'label' => __( 'Title', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_methods_title_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root . ' .eilmo-cf-payment-methods__title' => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_methods_title_typography', 'selector' => $root . ' .eilmo-cf-payment-methods__title' ] );

		$widget->add_control( 'eilmo_cf_payment_methods_cards_heading', [ 'label' => __( 'Method Cards', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_methods_gap', [ 'label' => __( 'Gap', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ $root . ' .eilmo-cf-payment-methods__list' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $card => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_payment_method_border', 'selector' => $card ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_method_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_method_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'eilmo_cf_payment_method_shadow', 'selector' => $card ] );

		$widget->add_control( 'eilmo_cf_payment_method_selected_heading', [ 'label' => __( 'Selected Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_method_selected_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'background: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_selected_border_color', [ 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'border-color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_selected_check_background', [ 'label' => __( 'Check Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $check => 'background: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_selected_check_color', [ 'label' => __( 'Check Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $check => 'color: {{VALUE}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_method_text_heading', [ 'label' => __( 'Method Content', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_method_title_color', [ 'label' => __( 'Title Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $title => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_method_title_typography', 'selector' => $title ] );
		$widget->add_control( 'eilmo_cf_payment_method_description_color', [ 'label' => __( 'Description Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $desc => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_method_description_typography', 'selector' => $desc ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_method_icon_size', [ 'label' => __( 'Logo / Icon Size', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 20, 'max' => 160 ] ], 'selectors' => [ $root . ' .eilmo-cf-payment-method__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};', $root . ' .eilmo-cf-payment-method__icon img' => 'max-width: {{SIZE}}{{UNIT}}; max-height: {{SIZE}}{{UNIT}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_method_details_heading', [ 'label' => __( 'Selected Method Details', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_method_details_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $details => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_payment_method_details_border', 'selector' => $details ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_method_details_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $details => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_method_details_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $details => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_method_upload_heading', [ 'label' => __( 'File Upload', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_method_upload_accent', [ 'label' => __( 'Accent / Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => '--eilmo-cf-upload-accent: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_upload_surface', [ 'label' => __( 'Background Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => '--eilmo-cf-upload-surface: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_upload_text', [ 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => '--eilmo-cf-upload-text: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_method_upload_button_text', [ 'label' => __( 'Button Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => '--eilmo-cf-upload-button-text: {{VALUE}};' ] ] );

		$widget->end_controls_section();
	}
}
