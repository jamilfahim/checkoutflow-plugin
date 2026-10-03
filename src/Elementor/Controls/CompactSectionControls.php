<?php
/** Small, theme-inheriting style shortcuts for Checkout Flow. */
namespace EilmoCheckout\Elementor\Controls;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class CompactSectionControls {
	public static function register( Widget_Base $widget ): void {
		$checkout = '{{WRAPPER}}.elementor-widget[data-widget_type] [data-eilmo-checkout]';
		$sections = array(
			'products' => array( 'Products & Packages', '.eilmo-cf-checkout__products', '.eilmo-cf-single-product__variation-choice, .eilmo-cf-single-product__option--package, .eilmo-cf-reference-fixed', '.eilmo-cf-single-product__choice-label, .eilmo-cf-single-product__option-label, .eilmo-cf-single-product__choice-price, .eilmo-cf-single-product__option-price', '.eilmo-cf-single-product__choice-description, .eilmo-cf-single-product__option-description', '.eilmo-cf-single-product__variation-choice.is-selected, .eilmo-cf-single-product__option--package.is-selected' ),
			'customer' => array( 'Customer Form', '.eilmo-cf-customer-form', '.eilmo-cf-customer-form input, .eilmo-cf-customer-form textarea, .eilmo-cf-customer-form select', '.eilmo-cf-customer-form__title, .eilmo-cf-customer-form label', '.eilmo-cf-customer-form__description, .eilmo-cf-customer-form small', '.eilmo-cf-customer-form input:focus, .eilmo-cf-customer-form textarea:focus' ),
			'delivery' => array( 'Delivery', '.eilmo-cf-delivery', '.eilmo-cf-delivery-method', '.eilmo-cf-delivery__title, .eilmo-cf-delivery-method__label, .eilmo-cf-delivery-method__price', '.eilmo-cf-delivery-method__description', '.eilmo-cf-delivery-method--selected' ),
			'payment_options' => array( 'Payment Options', '.eilmo-cf-advance-payment', '.eilmo-cf-advance-payment-option', '.eilmo-cf-advance-payment__title, .eilmo-cf-advance-payment-option__label', '.eilmo-cf-advance-payment-option__description', '.eilmo-cf-advance-payment-option--selected' ),
			'payment_methods' => array( 'Payment Methods', '.eilmo-cf-payment-methods', '.eilmo-cf-payment-method', '.eilmo-cf-payment-methods__title, .eilmo-cf-payment-method__title', '.eilmo-cf-payment-methods__pay-note, .eilmo-cf-payment-method__description', '.eilmo-cf-payment-method.is-selected' ),
			'coupon' => array( 'Coupon', '.eilmo-cf-summary__coupon', '.eilmo-cf-summary__coupon input', '.eilmo-cf-summary__coupon label', '.eilmo-cf-summary__coupon small', '.eilmo-cf-summary__coupon button' ),
			'summary' => array( 'Order Summary', '.eilmo-cf-summary', '.eilmo-cf-summary__selected-items, .eilmo-cf-summary__row', '.eilmo-cf-summary__title, .eilmo-cf-summary__selected-title, .eilmo-cf-summary__amount', '.eilmo-cf-summary__label', '.eilmo-cf-summary__row--grand-total' ),
			'order_button' => array( 'Order Button', '.eilmo-cf-order-submit', '.eilmo-cf-order-submit__button', '.eilmo-cf-order-submit__button', '.eilmo-cf-order-submit__button small', '.eilmo-cf-order-submit__button:hover' ),
			'whatsapp' => array( 'WhatsApp Button', '.eilmo-cf-whatsapp-order', '.eilmo-cf-whatsapp-order__button', '.eilmo-cf-whatsapp-order__button', '.eilmo-cf-whatsapp-order__button small', '.eilmo-cf-whatsapp-order__button:hover' ),
		);
		foreach ( $sections as $key => $parts ) {
			list( $title, $surface, $card, $main, $secondary, $selected ) = $parts;
			$root = $checkout . ' ' . $surface;
			$card_selector = self::scope( $checkout, $card );
			$hover_selector = implode( ', ', array_map( static function ( string $selector ): string {
				return trim( $selector ) . ':hover';
			}, explode( ',', $card_selector ) ) );
			$main_selector = self::scope( $checkout, $main );
			$secondary_selector = self::scope( $checkout, $secondary );
			$selected_selector = self::scope( $checkout, $selected );
			$widget->start_controls_section( 'eilmo_cf_compact_' . $key, array(
				'label' => __( $title, 'eilmo-checkout-flow' ),
				'tab' => Controls_Manager::TAB_STYLE,
			) );
			if ( 'products' === $key ) {
				$widget->add_control( 'eilmo_cf_compact_notice', array(
					'type' => Controls_Manager::RAW_HTML,
					'raw' => __( 'Leave a color empty to inherit Theme. Detailed styling remains in Advanced; reset an older detailed override there if it takes precedence.', 'eilmo-checkout-flow' ),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				) );
			}
			$widget->add_control( 'eilmo_cf_compact_' . $key . '_accent', array(
				'label' => __( 'Accent / Selected Color', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array( $selected_selector . ', ' . $hover_selector => 'border-color: {{VALUE}};', $root => '--eilmo-cf-theme-primary: {{VALUE}};' ),
			) );
			$widget->add_control( 'eilmo_cf_compact_' . $key . '_surface', array(
				'label' => __( 'Card Surface', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array( $card_selector => 'background-color: {{VALUE}};' ),
			) );
			$widget->add_control( 'eilmo_cf_compact_' . $key . '_main', array(
				'label' => __( 'Main Text', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array( $main_selector => 'color: {{VALUE}};' ),
			) );
			$widget->add_control( 'eilmo_cf_compact_' . $key . '_secondary', array(
				'label' => __( 'Secondary Text', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array( $secondary_selector => 'color: {{VALUE}};' ),
			) );
			$widget->add_control( 'eilmo_cf_compact_' . $key . '_border', array(
				'label' => __( 'Border', 'eilmo-checkout-flow' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array( $card_selector => 'border-color: {{VALUE}};' ),
			) );
			$widget->end_controls_section();
		}
	}

	private static function scope( string $root, string $selectors ): string {
		return implode( ', ', array_map( static function ( string $selector ) use ( $root ): string {
			return $root . ' ' . trim( $selector );
		}, explode( ',', $selectors ) ) );
	}
}
