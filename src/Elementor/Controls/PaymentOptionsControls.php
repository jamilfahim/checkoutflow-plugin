<?php
/**
 * Simplified Elementor Payment Options style controls.
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

final class PaymentOptionsControls {
	public static function register( Widget_Base $widget ): void {
		$root = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-advance-payment--unified';
		$card = $root . ' .eilmo-cf-advance-payment-option';
		$selected = $root . ' .eilmo-cf-advance-payment-option--selected, ' . $root . ' [data-eilmo-payment-option][data-selected="yes"]';
		$label = $root . ' .eilmo-cf-advance-payment-option__label';
		$desc = $root . ' .eilmo-cf-advance-payment-option__description';
		$amount = $root . ' .eilmo-cf-advance-payment-option__inline-amount, ' . $root . ' .eilmo-cf-advance-payment-option__amount';
		$badge = $root . ' .eilmo-cf-advance-payment-option__badge';
		$check = $root . ' .eilmo-cf-advance-payment-option__selected-icon';

		$widget->start_controls_section( 'eilmo_cf_style_payment_options', [
			'label' => __( 'Payment Options', 'eilmo-checkout-flow' ),
			'tab' => Controls_Manager::TAB_STYLE,
		] );

		$widget->add_control( 'eilmo_cf_payment_options_section_heading', [ 'label' => __( 'Section', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING ] );
		$widget->add_control( 'eilmo_cf_payment_options_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_payment_options_border', 'selector' => $root ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_options_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $root => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_options_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $root => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_options_title_heading', [ 'label' => __( 'Section Title', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_options_title_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $root . ' .eilmo-cf-advance-payment__title' => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_options_title_typography', 'selector' => $root . ' .eilmo-cf-advance-payment__title' ] );

		$widget->add_control( 'eilmo_cf_payment_options_cards_heading', [ 'label' => __( 'Cards', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_options_gap', [ 'label' => __( 'Gap', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ $root . ' .eilmo-cf-advance-payment__options' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_option_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $card => 'background: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'eilmo_cf_payment_option_border', 'selector' => $card ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_option_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_payment_option_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'eilmo_cf_payment_option_shadow', 'selector' => $card ] );

		$widget->add_control( 'eilmo_cf_payment_option_selected_heading', [ 'label' => __( 'Selected Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_option_selected_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'background: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_option_selected_border_color', [ 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selected => 'border-color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_option_selected_check_background', [ 'label' => __( 'Check Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $check => 'background: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_payment_option_selected_check_color', [ 'label' => __( 'Check Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $check => 'color: {{VALUE}};' ] ] );

		$widget->add_control( 'eilmo_cf_payment_card_label_heading', [ 'label' => __( 'Card Label', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_card_label_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $label => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_card_label_typography', 'selector' => $label ] );

		$widget->add_control( 'eilmo_cf_payment_card_description_heading', [ 'label' => __( 'Card Description', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_card_description_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $desc => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_card_description_typography', 'selector' => $desc ] );

		$widget->add_control( 'eilmo_cf_payment_card_amount_heading', [ 'label' => __( 'Card Amount', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_payment_card_amount_color', [ 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $amount . ', ' . $amount . ' .woocommerce-Price-amount, ' . $amount . ' .woocommerce-Price-currencySymbol' => 'color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_payment_card_amount_typography', 'selector' => $amount ] );

		$widget->add_control( 'eilmo_cf_full_badge_heading', [ 'label' => __( 'Card Badge', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$widget->add_control( 'eilmo_cf_full_badge_background', [ 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $badge => 'background-color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_full_badge_color', [ 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $badge => 'color: {{VALUE}};' ] ] );
		$widget->add_control( 'eilmo_cf_full_badge_border_color', [ 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $badge => 'border-color: {{VALUE}};' ] ] );
		$widget->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'eilmo_cf_full_badge_typography', 'selector' => $badge ] );
		$widget->add_responsive_control( 'eilmo_cf_full_badge_radius', [ 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $badge => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$widget->add_responsive_control( 'eilmo_cf_full_badge_padding', [ 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ $badge => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );

		$widget->end_controls_section();
	}
}
