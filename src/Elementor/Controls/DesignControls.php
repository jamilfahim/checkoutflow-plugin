<?php
/**
 * Elementor design controls.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Controls;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;


/**
 * Registers presentation-only Elementor controls.
 */
final class DesignControls {


	/**
	 * Register all Checkout Flow design controls.
	 *
	 * @param Widget_Base $widget Elementor widget.
	 *
	 * @return void
	 */
	public static function register(
		Widget_Base $widget
	): void {


		self::register_theme_controls(
			$widget
		);

		self::register_general_controls(
			$widget
		);
		CompactSectionControls::register( $widget );

		ProductSelectionControls::register(
			$widget
		);




		CustomerFormControls::register(
			$widget
		);


		DeliveryControls::register(
			$widget
		);


		PaymentOptionsControls::register(
			$widget
		);


		PaymentMethodsControls::register(
			$widget
		);


		CouponControls::register(
			$widget
		);


		SummaryControls::register(
			$widget
		);

		SummaryItemsControls::register(
			$widget
		);


		OrderButtonControls::register(
			$widget
		);


		WhatsAppButtonControls::register(
			$widget
		);

	}



	/**
	 * Register the small global theme shortcut used by this checkout instance.
	 *
	 * These controls mirror the plugin-level root variables. Leaving a value
	 * empty inherits Checkout Flow > Checkout Settings. Setting only these few
	 * colors is enough to restyle the complete checkout; component sections
	 * below remain optional precision overrides.
	 */
	private static function register_theme_controls(
		Widget_Base $widget
	): void {
		$root = '{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout]';

		$widget->start_controls_section(
			'eilmo_cf_style_theme',
			array(
				'label' => __( 'Theme', 'eilmo-checkout-flow' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$widget->add_control(
			'eilmo_cf_theme_notice',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw' => __( 'Quick theme: change only these colors to restyle the whole checkout. Leave them empty to inherit the plugin Global Checkout Style. Component sections below are optional overrides.', 'eilmo-checkout-flow' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$widget->add_control( 'eilmo_cf_theme_primary', array(
			'label' => __( 'Primary', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array(
				$root => '--eilmo-cf-theme-primary: {{VALUE}}; --eilmo-cf-theme-primary-hover: color-mix(in srgb, {{VALUE}} 84%, #000000); --eilmo-cf-theme-primary-soft: color-mix(in srgb, {{VALUE}} 8%, var(--eilmo-cf-theme-card-background, #ffffff)); --eilmo-cf-theme-strong-border: color-mix(in srgb, {{VALUE}} 44%, var(--eilmo-cf-theme-border, #d1d5db));',
			),
		) );

		$widget->add_control( 'eilmo_cf_theme_main_text', array(
			'label' => __( 'Main Text', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array( $root => '--eilmo-cf-theme-main-text: {{VALUE}};' ),
		) );

		$widget->add_control( 'eilmo_cf_theme_secondary_text', array(
			'label' => __( 'Secondary Text', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array( $root => '--eilmo-cf-theme-secondary-text: {{VALUE}};' ),
		) );

		$widget->add_control( 'eilmo_cf_theme_card_background', array(
			'label' => __( 'Card Background', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array(
				$root => '--eilmo-cf-theme-card-background: {{VALUE}}; --eilmo-cf-theme-muted-surface: color-mix(in srgb, {{VALUE}} 94%, var(--eilmo-cf-theme-primary, #6d28d9) 6%);',
			),
		) );

		$widget->add_control( 'eilmo_cf_theme_page_background', array(
			'label' => __( 'Page / Soft Background', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array(
				$root => '--eilmo-cf-theme-soft-background: {{VALUE}}; background-color: {{VALUE}};',
				'{{WRAPPER}}.elementor-widget[data-widget_type]' => '--eilmo-cf-theme-soft-background: {{VALUE}}; background-color: {{VALUE}};',
			),
		) );

		$widget->add_control( 'eilmo_cf_theme_border', array(
			'label' => __( 'Border', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array( $root => '--eilmo-cf-theme-border: {{VALUE}}; --eilmo-cf-theme-strong-border: color-mix(in srgb, {{VALUE}} 56%, var(--eilmo-cf-theme-primary, #6d28d9) 44%);' ),
		) );

		$widget->add_control( 'eilmo_cf_theme_button_text', array(
			'label' => __( 'Button Text', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::COLOR,
			'selectors' => array( $root => '--eilmo-cf-theme-button-text: {{VALUE}};' ),
		) );

		$widget->add_control( 'eilmo_cf_theme_shape_heading', array(
			'label' => __( 'Quick Shape', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$widget->add_responsive_control( 'eilmo_cf_theme_control_radius', array(
			'label' => __( 'Control Radius', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( $root => '--eilmo-cf-radius: {{SIZE}}{{UNIT}}; --eilmo-cf-reference-control-radius: {{SIZE}}{{UNIT}};' ),
		) );
		$widget->add_responsive_control( 'eilmo_cf_theme_card_radius', array(
			'label' => __( 'Card Radius', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ),
			'selectors' => array( $root => '--eilmo-cf-parent-radius: {{SIZE}}{{UNIT}}; --eilmo-cf-reference-card-radius: {{SIZE}}{{UNIT}}; --eilmo-cf-reference-summary-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$widget->end_controls_section();
	}

	/**
	 * Register general checkout layout controls.
	 *
	 * @param Widget_Base $widget Elementor widget.
	 *
	 * @return void
	 */
	private static function register_general_controls(
		Widget_Base $widget
	): void {


		$widget->start_controls_section(
			'eilmo_cf_style_general',
			array(
				'label' =>
					__(
						'Layout',
						'eilmo-checkout-flow'
					),

				'tab' =>
					Controls_Manager::TAB_STYLE,
			)
		);


		$widget->add_control(
			'eilmo_cf_general_main_background',
			array(
				'label' =>
					__(
						'Main Column Background',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::COLOR,

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout] .eilmo-cf-checkout__main' =>
							'background-color: {{VALUE}};',
					),
			)
		);

		$widget->add_control(
			'eilmo_cf_general_summary_background',
			array(
				'label' => __( 'Summary Background', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout] .eilmo-cf-summary' => 'background-color: {{VALUE}};',
				),
			)
		);


		$widget->add_responsive_control(
			'eilmo_cf_general_gap',
			array(
				'label' =>
					__(
						'Column Gap',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 0,
								'max' => 80,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-checkout__inner' =>
							'gap: {{SIZE}}{{UNIT}};',
					),
			)
		);


		$widget->add_responsive_control(
			'eilmo_cf_general_section_gap',
			array(
				'label' =>
					__(
						'Section Gap',
						'eilmo-checkout-flow'
					),

				'type' =>
					Controls_Manager::SLIDER,

				'range' =>
					array(
						'px' =>
							array(
								'min' => 0,
								'max' => 80,
							),
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] .eilmo-cf-checkout__main' =>
							'gap: {{SIZE}}{{UNIT}};',
					),
			)
		);


		$widget->add_responsive_control(
			'eilmo_cf_general_padding',
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
						'%',
					),

				'selectors' =>
					array(
						'{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout]' =>
							'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
			)
		);


		$widget->end_controls_section();

	}

}
