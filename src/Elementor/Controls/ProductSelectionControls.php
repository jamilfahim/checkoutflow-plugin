<?php
/**
 * Elementor checkout product-selection style controls.
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
 * Mode-aware controls for the product area rendered inside Checkout Flow.
 *
 * Single Product and Multiple Products intentionally expose different controls
 * because their frontend structures are different. Shared theme tokens stay in
 * DesignControls so merchants can restyle the complete checkout with only a
 * handful of colors, while these controls remain optional fine tuning.
 */
final class ProductSelectionControls {

	/** Register controls. */
	public static function register( Widget_Base $widget ): void {
		self::register_single_product_controls( $widget );
		self::register_multiple_product_controls( $widget );
	}

	/** Register Single Product package/fixed-product controls. */
	private static function register_single_product_controls( Widget_Base $widget ): void {
		$checkout = '{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout]';
		$products = $checkout . ' .eilmo-cf-checkout__products';
		$options = $checkout . ' .eilmo-cf-single-product__options';
		$header_title = $checkout . ' .eilmo-cf-single-product__package-header h3, ' . $checkout . ' .eilmo-cf-single-product__option-title';
		$header_helper = $checkout . ' .eilmo-cf-single-product__package-header p, ' . $checkout . ' .eilmo-cf-single-product__option-helper';
		$grid = $checkout . ' .eilmo-cf-single-product__variation-grid, ' . $checkout . ' .eilmo-cf-single-product__option-list--package';
		$card = $checkout . ' button.eilmo-cf-single-product__variation-choice, ' . $checkout . ' button.eilmo-cf-single-product__option--package';
		$selected = $checkout . ' button.eilmo-cf-single-product__variation-choice.is-selected, ' . $checkout . ' button.eilmo-cf-single-product__option--package.is-selected';
		$title = $checkout . ' .eilmo-cf-single-product__choice-label, ' . $checkout . ' .eilmo-cf-single-product__option-label';
		$description = $checkout . ' .eilmo-cf-single-product__choice-description, ' . $checkout . ' .eilmo-cf-single-product__option-description';
		$price = $checkout . ' .eilmo-cf-single-product__choice-price, ' . $checkout . ' .eilmo-cf-single-product__option-price';
		$regular = $checkout . ' .eilmo-cf-single-product__choice-regular-price, ' . $checkout . ' .eilmo-cf-single-product__option-regular-price';
		$saving = $checkout . ' .eilmo-cf-single-product__choice-saving, ' . $checkout . ' .eilmo-cf-single-product__option-saving';
		$badge = $checkout . ' .eilmo-cf-single-product__choice-badge, ' . $checkout . ' .eilmo-cf-single-product__option-badge';
		$status = $checkout . ' .eilmo-cf-single-product__selection-message';
		$quantity = $checkout . ' .eilmo-cf-reference-quantity';
		$quantity_title = $quantity . ' strong';
		$quantity_helper = $quantity . ' small';
		$stepper = $checkout . ' .eilmo-cf-reference-stepper';
		$stepper_button = $stepper . ' button';
		$stepper_value = $stepper . ' output';
		$fixed = $checkout . ' .eilmo-cf-reference-fixed';
		$fixed_title = $fixed . ' h3';
		$fixed_description = $fixed . ' > div';
		$fixed_price = $fixed . ' > strong';

		$condition = array(
			'checkout_mode' => 'single',
			'show_package_selection' => 'yes',
		);

		$widget->start_controls_section(
			'eilmo_cf_style_single_product_selection',
			array(
				'label' => __( 'Single Product / Package', 'eilmo-checkout-flow' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array( 'checkout_mode' => 'single' ),
			)
		);

		$widget->add_control( 'eilmo_cf_single_surface_heading', array( 'label' => __( 'Product Section', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING ) );
		$widget->add_control( 'eilmo_cf_single_surface_background', array(
			'label' => __( 'Background', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array( $products => 'background: {{VALUE}};' ),
		) );
		$widget->add_responsive_control( 'eilmo_cf_single_surface_padding', array(
			'label' => __( 'Padding', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px' ),
			'selectors' => array( $products => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$widget->add_control( 'eilmo_cf_single_header_heading', array( 'label' => __( 'Package Heading', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_header_title_color', array( 'label' => __( 'Title Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $header_title => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_header_title_typography', 'selector' => $header_title, 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_header_title_align', array(
			'label' => __( 'Title Alignment', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => 'left',
			'options' => array( 'left' => __( 'Left', 'eilmo-checkout-flow' ), 'center' => __( 'Center', 'eilmo-checkout-flow' ), 'right' => __( 'Right', 'eilmo-checkout-flow' ) ),
			'condition' => $condition,
			'selectors' => array( $header_title => 'text-align: {{VALUE}};' ),
		) );
		$widget->add_control( 'eilmo_cf_single_header_helper_color', array( 'label' => __( 'Description Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $header_helper => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_header_helper_typography', 'selector' => $header_helper, 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_header_helper_align', array(
			'label' => __( 'Description Alignment', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => 'left',
			'options' => array( 'left' => __( 'Left', 'eilmo-checkout-flow' ), 'center' => __( 'Center', 'eilmo-checkout-flow' ), 'right' => __( 'Right', 'eilmo-checkout-flow' ) ),
			'condition' => $condition,
			'selectors' => array( $header_helper => 'text-align: {{VALUE}};' ),
		) );

		$widget->add_control( 'eilmo_cf_single_grid_heading', array( 'label' => __( 'Package Grid', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_responsive_control( 'eilmo_cf_single_grid_columns', array(
			'label' => __( 'Columns', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::NUMBER,
			'default' => 2,
			'min' => 1,
			'max' => 4,
			'condition' => $condition,
			'selectors' => array( $grid => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
		) );
		$widget->add_responsive_control( 'eilmo_cf_single_grid_gap', array(
			'label' => __( 'Gap', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
			'condition' => $condition,
			'selectors' => array( $grid => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$widget->add_control( 'eilmo_cf_single_card_heading', array( 'label' => __( 'Package Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_card_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $card => 'background: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_single_card_border', 'selector' => $card, 'condition' => $condition ) );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'eilmo_cf_single_card_shadow', 'selector' => $card, 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_card_hover_background', array( 'label' => __( 'Hover Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $card . ':hover:not(:disabled)' => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_card_hover_border_color', array( 'label' => __( 'Hover Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $card . ':hover:not(:disabled)' => 'border-color: {{VALUE}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_single_card_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => $condition, 'selectors' => array( $card => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_single_card_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => $condition, 'selectors' => array( $card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );

		$widget->add_control( 'eilmo_cf_single_selected_heading', array( 'label' => __( 'Selected Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_selected_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $selected => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_selected_border_color', array( 'label' => __( 'Border Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $selected => 'border-color: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_selected_check_background', array( 'label' => __( 'Check Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $selected . '::before' => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_selected_check_color', array( 'label' => __( 'Check Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $selected . '::before' => 'color: {{VALUE}};' ) ) );

		self::register_text_control_group( $widget, 'eilmo_cf_single_card_title', __( 'Package Title', 'eilmo-checkout-flow' ), $title, $condition, true );
		self::register_text_control_group( $widget, 'eilmo_cf_single_card_description', __( 'Package Description', 'eilmo-checkout-flow' ), $description, $condition, true );
		self::register_text_control_group( $widget, 'eilmo_cf_single_card_price', __( 'Price', 'eilmo-checkout-flow' ), $price, $condition, true );
		self::register_text_control_group( $widget, 'eilmo_cf_single_card_regular', __( 'Regular Price', 'eilmo-checkout-flow' ), $regular, $condition, true );
		self::register_text_control_group( $widget, 'eilmo_cf_single_card_saving', __( 'Saving Text', 'eilmo-checkout-flow' ), $saving, $condition, true );
		$widget->add_control( 'eilmo_cf_single_saving_background', array( 'label' => __( 'Saving Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $saving => 'background: {{VALUE}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_single_saving_radius', array( 'label' => __( 'Saving Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => $condition, 'selectors' => array( $saving => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );

		$widget->add_control( 'eilmo_cf_single_badge_heading', array( 'label' => __( 'Package Badge', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_badge_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $badge => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_badge_color', array( 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $badge => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_badge_typography', 'selector' => $badge, 'condition' => $condition ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_single_badge_border', 'selector' => $badge, 'condition' => $condition ) );
		$widget->add_responsive_control( 'eilmo_cf_single_badge_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => $condition, 'selectors' => array( $badge => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_single_badge_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => $condition, 'selectors' => array( $badge => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );

		$widget->add_control( 'eilmo_cf_single_status_heading', array( 'label' => __( 'Selected Status', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_status_color', array( 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'condition' => $condition, 'selectors' => array( $status => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_status_typography', 'selector' => $status, 'condition' => $condition ) );
		$widget->add_control( 'eilmo_cf_single_status_align', array( 'label' => __( 'Alignment', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SELECT, 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'eilmo-checkout-flow' ), 'center' => __( 'Center', 'eilmo-checkout-flow' ), 'right' => __( 'Right', 'eilmo-checkout-flow' ) ), 'condition' => $condition, 'selectors' => array( $status => 'text-align: {{VALUE}};' ) ) );

		$widget->add_control( 'eilmo_cf_single_quantity_heading', array( 'label' => __( 'Quantity Strip', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_single_quantity_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity => 'background: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_single_quantity_border', 'selector' => $quantity ) );
		$widget->add_responsive_control( 'eilmo_cf_single_quantity_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $quantity => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_single_quantity_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $quantity => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_quantity_title_color', array( 'label' => __( 'Title Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity_title => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_quantity_title_typography', 'selector' => $quantity_title ) );
		$widget->add_control( 'eilmo_cf_single_quantity_helper_color', array( 'label' => __( 'Description Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity_helper => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_quantity_helper_typography', 'selector' => $quantity_helper ) );
		$widget->add_control( 'eilmo_cf_single_stepper_background', array( 'label' => __( 'Stepper Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $stepper => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_stepper_button_background', array( 'label' => __( 'Stepper Button Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $stepper_button => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_stepper_button_color', array( 'label' => __( 'Stepper Button Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $stepper_button => 'color: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_stepper_value_color', array( 'label' => __( 'Quantity Value Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $stepper_value => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_stepper_value_typography', 'selector' => $stepper_value ) );

		$widget->add_control( 'eilmo_cf_single_fixed_heading', array( 'label' => __( 'Fixed Product Card', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_single_fixed_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $fixed => 'background: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_single_fixed_border', 'selector' => $fixed ) );
		$widget->add_responsive_control( 'eilmo_cf_single_fixed_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $fixed => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_control( 'eilmo_cf_single_fixed_title_color', array( 'label' => __( 'Title Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $fixed_title => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_fixed_title_typography', 'selector' => $fixed_title ) );
		$widget->add_control( 'eilmo_cf_single_fixed_description_color', array( 'label' => __( 'Description Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $fixed_description => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_fixed_description_typography', 'selector' => $fixed_description ) );
		$widget->add_control( 'eilmo_cf_single_fixed_price_color', array( 'label' => __( 'Price Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $fixed_price => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_single_fixed_price_typography', 'selector' => $fixed_price ) );

		$widget->end_controls_section();
	}

	/** Register Multiple Products selected-row controls. */
	private static function register_multiple_product_controls( Widget_Base $widget ): void {
		$checkout = '{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout]';
		$products = $checkout . ' .eilmo-cf-checkout__products';
		$title = $checkout . ' .eilmo-cf-external-products__title';
		$empty = $checkout . ' .eilmo-cf-external-products__empty';
		$empty_icon = $checkout . ' .eilmo-cf-external-products__empty-icon';
		$list = $checkout . ' .eilmo-cf-external-products';
		$row = $checkout . ' .eilmo-cf-external-product-row';
		$name = $row . ' .eilmo-cf-external-product-row__name';
		$variation = $row . ' .eilmo-cf-external-product-row__variation';
		$price = $row . ' .eilmo-cf-external-product-row__price';
		$quantity = $row . ' .eilmo-cf-external-product-row__quantity';
		$quantity_buttons = $quantity . ' button';
		$quantity_value = $quantity . ' output';
		$remove = $row . ' .eilmo-cf-external-product-row__remove';

		$condition = array( 'checkout_mode' => 'multiple' );

		$widget->start_controls_section(
			'eilmo_cf_style_multiple_products_selection',
			array(
				'label' => __( 'Selected Products', 'eilmo-checkout-flow' ),
				'tab' => Controls_Manager::TAB_ADVANCED,
				'condition' => $condition,
			)
		);

		$widget->add_control( 'eilmo_cf_multiple_surface_heading', array( 'label' => __( 'Section', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING ) );
		$widget->add_control( 'eilmo_cf_multiple_surface_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $products => 'background: {{VALUE}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_surface_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $products => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_list_gap', array( 'label' => __( 'Product Gap', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( $list => 'gap: {{SIZE}}{{UNIT}};' ) ) );

		$widget->add_control( 'eilmo_cf_multiple_title_heading', array( 'label' => __( 'Section Title', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_multiple_title_color', array( 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $title => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_multiple_title_typography', 'selector' => $title ) );
		$widget->add_control( 'eilmo_cf_multiple_title_align', array( 'label' => __( 'Alignment', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SELECT, 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'eilmo-checkout-flow' ), 'center' => __( 'Center', 'eilmo-checkout-flow' ), 'right' => __( 'Right', 'eilmo-checkout-flow' ) ), 'selectors' => array( $title => 'text-align: {{VALUE}};' ) ) );

		$widget->add_control( 'eilmo_cf_multiple_empty_heading', array( 'label' => __( 'Empty Notice', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_multiple_empty_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $empty => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_empty_color', array( 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $empty => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_multiple_empty_typography', 'selector' => $empty ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_multiple_empty_border', 'selector' => $empty ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_empty_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $empty => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_empty_icon_background', array( 'label' => __( 'Icon Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $empty_icon => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_empty_icon_color', array( 'label' => __( 'Icon Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $empty_icon => 'color: {{VALUE}};' ) ) );

		$widget->add_control( 'eilmo_cf_multiple_row_heading', array( 'label' => __( 'Product Row', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_multiple_row_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $row => 'background: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_multiple_row_border', 'selector' => $row ) );
		$widget->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'eilmo_cf_multiple_row_shadow', 'selector' => $row ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_row_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $row => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_row_padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $row => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );

		self::register_text_control_group( $widget, 'eilmo_cf_multiple_name', __( 'Product Name', 'eilmo-checkout-flow' ), $name, $condition, false );
		self::register_text_control_group( $widget, 'eilmo_cf_multiple_variation', __( 'Variation / Description', 'eilmo-checkout-flow' ), $variation, $condition, false );
		self::register_text_control_group( $widget, 'eilmo_cf_multiple_price', __( 'Price', 'eilmo-checkout-flow' ), $price, $condition, false );

		$widget->add_control( 'eilmo_cf_multiple_quantity_heading', array( 'label' => __( 'Quantity Control', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_multiple_quantity_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity => 'background: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'eilmo_cf_multiple_quantity_border', 'selector' => $quantity ) );
		$widget->add_responsive_control( 'eilmo_cf_multiple_quantity_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( $quantity => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_quantity_button_background', array( 'label' => __( 'Button Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity_buttons => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_quantity_button_color', array( 'label' => __( 'Button Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity_buttons => 'color: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_quantity_value_color', array( 'label' => __( 'Value Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $quantity_value => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'eilmo_cf_multiple_quantity_value_typography', 'selector' => $quantity_value ) );

		$widget->add_control( 'eilmo_cf_multiple_remove_heading', array( 'label' => __( 'Remove Button', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$widget->add_control( 'eilmo_cf_multiple_remove_background', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $remove => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_remove_color', array( 'label' => __( 'Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $remove => 'color: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_remove_hover_background', array( 'label' => __( 'Hover Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $remove . ':hover' => 'background: {{VALUE}};' ) ) );
		$widget->add_control( 'eilmo_cf_multiple_remove_hover_color', array( 'label' => __( 'Hover Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $remove . ':hover' => 'color: {{VALUE}};' ) ) );

		$widget->end_controls_section();
	}

	/**
	 * Register the recurring Color / Typography / Alignment controls for text.
	 */
	private static function register_text_control_group(
		Widget_Base $widget,
		string $prefix,
		string $label,
		string $selector,
		array $condition,
		bool $separator
	): void {
		$widget->add_control( $prefix . '_heading', array(
			'label' => $label,
			'type' => Controls_Manager::HEADING,
			'separator' => $separator ? 'before' : 'before',
			'condition' => $condition,
		) );
		$widget->add_control( $prefix . '_color', array(
			'label' => __( 'Color', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'condition' => $condition,
			'selectors' => array( $selector => 'color: {{VALUE}};' ),
		) );
		$widget->add_group_control( Group_Control_Typography::get_type(), array(
			'name' => $prefix . '_typography',
			'selector' => $selector,
			'condition' => $condition,
		) );
		$widget->add_control( $prefix . '_align', array(
			'label' => __( 'Alignment', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT,
			'default' => 'left',
			'options' => array(
				'left' => __( 'Left', 'eilmo-checkout-flow' ),
				'center' => __( 'Center', 'eilmo-checkout-flow' ),
				'right' => __( 'Right', 'eilmo-checkout-flow' ),
			),
			'condition' => $condition,
			'selectors' => array( $selector => 'text-align: {{VALUE}};' ),
		) );
	}
}
