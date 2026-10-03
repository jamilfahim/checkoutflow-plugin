<?php
/**
 * Elementor Coupon style controls.
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

/**
 * Registers Coupon presentation controls.
 */
final class CouponControls {

	/**
	 * Register controls.
	 *
	 * @param Widget_Base $widget Elementor widget.
	 *
	 * @return void
	 */
	public static function register(
		Widget_Base $widget
	): void {

		$widget->start_controls_section(
			'eilmo_cf_style_coupon',
			array(
				'label' =>
					__(
						'Coupon',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_ADVANCED,
			)
		);

		/*
		 * Section.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_section_heading',
			array(
				'label' =>
					__(
						'Section',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_radius',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_padding',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_shadow',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon',
			)
		);

		/*
		 * Title.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_title_heading',
			array(
				'label' =>
					__(
						'Title',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_title_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__title' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_title_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__title',
			)
		);

		/*
		 * Description.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_description_heading',
			array(
				'label' =>
					__(
						'Description',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_description_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__description' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_description_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__description',
			)
		);

		/*
		 * Label.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_label_heading',
			array(
				'label' =>
					__(
						'Label',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_label_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__label' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_label_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__label',
			)
		);

		/*
		 * Input.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_input_heading',
			array(
				'label' =>
					__(
						'Input',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_controls_gap',
			array(
				'label' =>
					__(
						'Input / Button Gap',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 0,
								'max' => 40,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__controls' =>
							'gap: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_input_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_input_color',
			array(
				'label' =>
					__(
						'Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_placeholder_color',
			array(
				'label' =>
					__(
						'Placeholder Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input::placeholder' =>
							'color: {{VALUE}}; opacity: 1;',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_input_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_input_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_input_radius',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_input_padding',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_input_focus_color',
			array(
				'label' =>
					__(
						'Focus Border Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__input:focus' =>
							'border-color: {{VALUE}}; outline-color: {{VALUE}};',
					),
			)
		);

		/*
		 * Apply button.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_apply_heading',
			array(
				'label' =>
					__(
						'Apply Button',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_apply_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_apply_color',
			array(
				'label' =>
					__(
						'Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_apply_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_apply_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_apply_radius',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_apply_padding',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_apply_hover_background',
			array(
				'label' =>
					__(
						'Hover Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply:focus-visible' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_apply_hover_color',
			array(
				'label' =>
					__(
						'Hover Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__apply:focus-visible' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Applied coupon.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_applied_heading',
			array(
				'label' =>
					__(
						'Applied Coupon',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_applied_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_applied_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_applied_radius',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_applied_padding',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_code_color',
			array(
				'label' =>
					__(
						'Code Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-code' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_code_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-code',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_discount_color',
			array(
				'label' =>
					__(
						'Discount Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-discount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-discount .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-discount .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_discount_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-discount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__applied-discount .woocommerce-Price-amount',
			)
		);

		/*
		 * Remove button.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_remove_heading',
			array(
				'label' =>
					__(
						'Remove Button',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_remove_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_remove_color',
			array(
				'label' =>
					__(
						'Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_remove_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_coupon_remove_radius',
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
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_remove_hover_background',
			array(
				'label' =>
					__(
						'Hover Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove:focus-visible' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_remove_hover_color',
			array(
				'label' =>
					__(
						'Hover Text Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__remove:focus-visible' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Message.
		 */
		$widget->add_control(
			'eilmo_cf_coupon_message_heading',
			array(
				'label' =>
					__(
						'Message',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_coupon_message_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__message' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_coupon_message_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-coupon__message',
			)
		);

		$widget->end_controls_section();
	}
}
