<?php
/**
 * Elementor Order Summary style controls.
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
 * Registers Order Summary presentation controls.
 */
final class SummaryControls {

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
			'eilmo_cf_style_summary',
			array(
				'label' =>
					__(
						'Order Summary',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_ADVANCED,
			)
		);

		/*
		 * Summary panel.
		 */
		$widget->add_control(
			'eilmo_cf_summary_panel_heading',
			array(
				'label' =>
					__(
						'Summary Box',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_summary_radius',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_summary_padding',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_shadow',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary',
			)
		);

		/*
		 * Header.
		 */
		$widget->add_control(
			'eilmo_cf_summary_header_heading',
			array(
				'label' =>
					__(
						'Header',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_header_background',
			array(
				'label' =>
					__(
						'Header Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__header' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_title_color',
			array(
				'label' =>
					__(
						'Title Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__title' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_title_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__title',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_close_color',
			array(
				'label' =>
					__(
						'Close Icon Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__close, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__close span' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_header_divider_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__header' =>
							'border-bottom-color: {{VALUE}};',
					),
			)
		);

		/*
		 * Rows.
		 */
		$widget->add_control(
			'eilmo_cf_summary_rows_heading',
			array(
				'label' =>
					__(
						'Summary Rows',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_summary_rows_gap',
			array(
				'label' =>
					__(
						'Row Gap',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__rows' =>
							'gap: {{SIZE}}{{UNIT}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_label_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--discount):not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__label' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_label_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__label',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_amount_color',
			array(
				'label' =>
					__(
						'Amount Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--discount):not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--discount):not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__amount .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--discount):not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__amount .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_amount_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row:not(.eilmo-cf-summary__row--total) .eilmo-cf-summary__amount .woocommerce-Price-amount',
			)
		);

		/*
		 * Discount rows.
		 */
		$widget->add_control(
			'eilmo_cf_summary_discount_heading',
			array(
				'label' =>
					__(
						'Discount Rows',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_discount_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--discount .eilmo-cf-summary__label, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--discount .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--discount .eilmo-cf-summary__discount-prefix, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--discount .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--discount .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Separator.
		 */
		$widget->add_control(
			'eilmo_cf_summary_separator_heading',
			array(
				'label' =>
					__(
						'Separator',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_separator_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__separator' =>
							'background-color: {{VALUE}}; border-color: {{VALUE}};',
					),
			)
		);

		/*
		 * Grand total.
		 */
		$widget->add_control(
			'eilmo_cf_summary_total_heading',
			array(
				'label' =>
					__(
						'Grand Total',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_total_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .eilmo-cf-summary__label, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_total_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .eilmo-cf-summary__label, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--total .woocommerce-Price-amount',
			)
		);

		/*
		 * Pay Now.
		 */
		$widget->add_control(
			'eilmo_cf_summary_pay_now_heading',
			array(
				'label' =>
					__(
						'Pay Now',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_pay_now_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_pay_now_border',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now',
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_summary_pay_now_radius',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now' =>
							'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_responsive_control(
			'eilmo_cf_summary_pay_now_padding',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_pay_now_shadow',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_pay_now_label_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .eilmo-cf-summary__label' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_pay_now_label_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .eilmo-cf-summary__label',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_pay_now_amount_color',
			array(
				'label' =>
					__(
						'Amount Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' =>
					'eilmo_cf_summary_pay_now_amount_typography',

				'selector' =>
					'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .eilmo-cf-summary__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__row--pay-now .woocommerce-Price-amount',
			)
		);

		/*
		 * Mobile summary bar.
		 */
		$widget->add_control(
			'eilmo_cf_summary_mobile_heading',
			array(
				'label' =>
					__(
						'Mobile Summary Bar',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_mobile_background',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_mobile_border_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar' =>
							'border-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_mobile_label_color',
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
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__label, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__secondary-label' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_mobile_amount_color',
			array(
				'label' =>
					__(
						'Amount Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__secondary-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__amount .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__secondary-amount .woocommerce-Price-amount, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__amount .woocommerce-Price-currencySymbol, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__secondary-amount .woocommerce-Price-currencySymbol' =>
							'color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_mobile_action_color',
			array(
				'label' =>
					__(
						'Action Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__action-text, {{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-bar__arrow' =>
							'color: {{VALUE}};',
					),
			)
		);

		/*
		 * Mobile drawer.
		 */
		$widget->add_control(
			'eilmo_cf_summary_drawer_heading',
			array(
				'label' =>
					__(
						'Mobile Drawer',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::HEADING,

				'separator' =>
					'before',
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_drawer_handle_color',
			array(
				'label' =>
					__(
						'Handle Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__drawer-handle span' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_summary_backdrop_color',
			array(
				'label' =>
					__(
						'Backdrop Color',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-backdrop' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->end_controls_section();
	}
}
