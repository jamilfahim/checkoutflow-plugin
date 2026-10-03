<?php
/**
 * Elementor Customer Form style controls.
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
 * Registers Customer Form presentation controls.
 *
 * Customer fields, labels, required state and layout
 * continue to come from Checkout Flow plugin settings.
 */
final class CustomerFormControls {

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
			'eilmo_cf_style_customer',
			array(
				'label' =>
					__(
						'Customer Form',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_ADVANCED,
			)
		);

		/*
		 * Form.
		 */
		$widget->add_control(
			'eilmo_cf_customer_form_heading',
			array(
				'label' =>
					__(
						'Form',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_radius',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_padding',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_shadow',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form',
			)
		);

		/*
		 * Title.
		 */
		$widget->add_control(
			'eilmo_cf_customer_title_heading',
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
			'eilmo_cf_customer_title_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__title' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_title_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__title',
			)
		);

		/*
		 * Description.
		 */
		$widget->add_control(
			'eilmo_cf_customer_description_heading',
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
			'eilmo_cf_customer_description_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__description' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_description_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__description',
			)
		);

		/*
		 * Header divider.
		 */
		$widget->add_control(
			'eilmo_cf_customer_divider_color',
			array(
				'label' =>
					__(
						'Divider Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__header' =>
							'border-bottom-color: {{VALUE}};',
					),
			)
		);

		/*
		 * Fields.
		 */
		$widget->add_control(
			'eilmo_cf_customer_fields_heading',
			array(
				'label' =>
					__(
						'Fields',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_field_gap',
			array(
				'label' =>
					__(
						'Field Gap',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 0,
								'max' => 50,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__fields' =>
							'gap: {{SIZE}}{{UNIT}};',
					),
			)
		);

		/*
		 * Labels.
		 */
		$widget->add_control(
			'eilmo_cf_customer_label_color',
			array(
				'label' =>
					__(
						'Label Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__label' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_label_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__label',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_label_spacing',
			array(
				'label' =>
					__(
						'Label Spacing',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 0,
								'max' => 30,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__label' =>
							'margin-bottom: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_required_color',
			array(
				'label' =>
					__(
						'Required Mark Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__required' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Input controls.
		 */
		$widget->add_control(
			'eilmo_cf_customer_input_heading',
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

		$widget->add_control(
			'eilmo_cf_customer_input_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_input_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_placeholder_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control::placeholder' =>
							'color: {{VALUE}}; opacity: 1;',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_input_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_input_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_input_radius',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_input_padding',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_focus_border_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__control:focus' =>
							'border-color: {{VALUE}}; outline-color: {{VALUE}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_customer_textarea_height',
			array(
				'label' =>
					__(
						'Textarea Min Height',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 60,
								'max' => 300,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__textarea' =>
							'min-height: {{SIZE}}{{UNIT}};',
					),
			)
		);

		/*
		 * Errors.
		 */
		$widget->add_control(
			'eilmo_cf_customer_error_heading',
			array(
				'label' =>
					__(
						'Error Message',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_customer_error_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__error, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__error' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_customer_error_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-field__error, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-customer-form__error',
			)
		);

		$widget->end_controls_section();
	}
}
