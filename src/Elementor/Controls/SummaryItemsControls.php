<?php
/**
 * Elementor Order Summary Selected Items Controls.
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
 * Summary selected items style controls.
 */
final class SummaryItemsControls {

	public static function register( Widget_Base $widget ): void {

		$container = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary__selected-items';
		$row       = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-item';
		$image     = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-item__image';
		$name      = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-item__name';
		$variation = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-item__variation';
		$price     = '{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-summary-item__price';


		$widget->start_controls_section(
			'eilmo_cf_summary_items_style',
			[
				'label' => __( 'Order Summary Items', 'eilmo-checkout-flow' ),
			'tab'   => Controls_Manager::TAB_ADVANCED,
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_items_background',
			[
				'label' => __( 'Container Background', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					$container => 'background-color: {{VALUE}};',
				],
			]
		);


		$widget->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'eilmo_cf_summary_items_border',
				'selector' => $container,
			]
		);


		$widget->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'eilmo_cf_summary_items_shadow',
				'selector' => $container,
			]
		);


		$widget->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'eilmo_cf_summary_item_row_border',
				'selector' => $row,
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_item_row_background',
			[
				'label' => __( 'Item Background', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					$row => 'background-color: {{VALUE}};',
				],
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_image_radius',
			[
				'label' => __( 'Image Radius', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::DIMENSIONS,
				'selectors' => [
					$image => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_name_color',
			[
				'label' => __( 'Product Name Color', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					$name => 'color: {{VALUE}};',
				],
			]
		);


		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'eilmo_cf_summary_name_typography',
				'selector' => $name,
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_variation_color',
			[
				'label' => __( 'Variation Color', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					$variation => 'color: {{VALUE}};',
				],
			]
		);


		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'eilmo_cf_summary_variation_typography',
				'selector' => $variation,
			]
		);


		$widget->add_control(
			'eilmo_cf_summary_price_color',
			[
				'label' => __( 'Price Color', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					$price => 'color: {{VALUE}};',
				],
			]
		);


		$widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'eilmo_cf_summary_price_typography',
				'selector' => $price,
			]
		);


		$widget->end_controls_section();

	}

}
