<?php
/**
 * Elementor Order Button style controls.
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
 * Registers final Order Now button presentation controls.
 */
final class OrderButtonControls {

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
			'eilmo_cf_style_order_button',
			array(
				'label' =>
					__(
						'Order Button',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_ADVANCED,
			)
		);

		/*
		 * Button.
		 */
		$widget->add_control(
			'eilmo_cf_order_button_heading',
			array(
				'label' =>
					__(
						'Button',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_order_button_width',
			array(
				'label' =>
					__(
						'Width',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'size_units' =>
					array(
						'%',
						'px',
					),

				'range' =>
					array(
						'%' =>
							array(
								'min' => 10,
								'max' => 100,
							),

						'px' =>
							array(
								'min' => 100,
								'max' => 1000,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button' =>
							'width: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button' =>
							'background: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button-text' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_order_button_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button',
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_order_button_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_order_button_radius',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_order_button_padding',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' =>
					'eilmo_cf_order_button_shadow',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button',
			)
		);

		/*
		 * Hover.
		 */
		$widget->add_control(
			'eilmo_cf_order_button_hover_heading',
			array(
				'label' =>
					__(
						'Hover',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_hover_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:focus-visible' =>
							'background: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_hover_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:hover .eilmo-cf-order-submit__button-text, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:focus-visible, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:focus-visible .eilmo-cf-order-submit__button-text' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_hover_border_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:hover, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button:focus-visible' =>
							'border-color: {{VALUE}};',
					),
			)
		);

		/*
		 * Loading.
		 */
		$widget->add_control(
			'eilmo_cf_order_button_loading_heading',
			array(
				'label' =>
					__(
						'Loading',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_loading_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button.is-loading' =>
							'background: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_button_loading_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button.is-loading, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit:not(.eilmo-cf-whatsapp-order) .eilmo-cf-order-submit__button.is-loading .eilmo-cf-order-submit__button-text' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Spinner.
		 */
		$widget->add_control(
			'eilmo_cf_order_spinner_heading',
			array(
				'label' =>
					__(
						'Spinner',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_order_spinner_size',
			array(
				'label' =>
					__(
						'Size',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 8,
								'max' => 50,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__spinner' =>
							'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_order_spinner_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__spinner' =>
							'color: {{VALUE}}; border-color: {{VALUE}}; border-top-color: transparent;',
					),
			)
		);

		/*
		 * Notice.
		 */
		$widget->add_control(
			'eilmo_cf_order_notice_heading',
			array(
				'label' =>
					__(
						'Notice',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_order_notice_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__notice' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_order_notice_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__notice',
			)
		);

		/*
		 * Error.
		 */
		$widget->add_control(
			'eilmo_cf_order_error_heading',
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
			'eilmo_cf_order_error_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__error' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_order_error_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-order-submit__error',
			)
		);

		$widget->end_controls_section();
	}
}
