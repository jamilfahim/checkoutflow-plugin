<?php
/**
 * Elementor Product Button widget.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Elementor\Widgets;

use EilmoCheckout\Admin\CheckoutStyle;
use EilmoCheckout\Core\Assets;
use EilmoCheckout\Elementor\Elementor as ElementorIntegration;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight Elementor trigger for adding a WooCommerce product/variation to
 * the existing Checkout Flow state. Pricing and order validation remain
 * server-authoritative.
 */
final class ProductButtonWidget extends Widget_Base {
	public function get_name(): string { return 'eilmo-product-button'; }
	public function get_title(): string { return __( 'Checkout Flow – Product Button', 'eilmo-checkout-flow' ); }
	public function get_icon(): string { return 'eicon-button'; }
	public function get_categories(): array { return array( ElementorIntegration::get_category() ); }
	public function get_keywords(): array { return array( 'product', 'buy', 'button', 'variation', 'checkout', 'woocommerce', 'eilmo' ); }
	public function get_script_depends(): array { return Assets::get_frontend_script_handles(); }
	public function get_style_depends(): array { Assets::register_frontend(); return wp_style_is( Assets::FRONTEND_STYLE, 'registered' ) ? array( Assets::FRONTEND_STYLE ) : array(); }

	protected function register_controls(): void {
		$this->start_controls_section( 'eilmo_product_button_content', array( 'label' => __( 'Product Button', 'eilmo-checkout-flow' ), 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'product_id', array(
			'label' => __( 'Product', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT2,
			'options' => $this->get_product_options(),
			'label_block' => true,
		) );
		$this->add_control( 'variation_id', array(
			'label' => __( 'Exact Variation (Optional)', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::SELECT2,
			'options' => $this->get_variation_options(),
			'label_block' => true,
			'description' => __( 'Use this only for a variable product. Leave empty when the page uses a Variation Selector widget for the same product.', 'eilmo-checkout-flow' ),
		) );
		$this->add_control( 'quantity', array(
			'label' => __( 'Quantity', 'eilmo-checkout-flow' ),
			'type' => Controls_Manager::NUMBER,
			'default' => 1,
			'min' => 1,
			'step' => 1,
		) );
		$this->add_control( 'button_text', array( 'label' => __( 'Button Text', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Buy Now', 'eilmo-checkout-flow' ), 'label_block' => true ) );
		$this->add_control( 'added_text', array( 'label' => __( 'Selected Text', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Selected', 'eilmo-checkout-flow' ), 'label_block' => true ) );
		$this->add_control( 'allow_remove', array( 'label' => __( 'Click Again to Remove', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'scroll_to_checkout', array( 'label' => __( 'Scroll to Checkout After Add', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'eilmo_product_button_style', array( 'label' => __( 'Button', 'eilmo-checkout-flow' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'alignment', array(
			'label' => __( 'Alignment', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::CHOOSE,
			'options' => array(
				'left' => array( 'title' => __( 'Left', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Center', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-center' ),
				'right' => array( 'title' => __( 'Right', 'eilmo-checkout-flow' ), 'icon' => 'eicon-text-align-right' ),
			), 'default' => 'left',
			'selectors' => array( '{{WRAPPER}} .eilmo-cf-product-button-widget' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_control( 'full_width', array( 'label' => __( 'Full Width', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '', 'prefix_class' => 'eilmo-cf-product-button--full-' ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .eilmo-cf-product-button' ) );
		$this->start_controls_tabs( 'product_button_tabs' );
		foreach ( array( 'normal' => __( 'Normal', 'eilmo-checkout-flow' ), 'hover' => __( 'Hover', 'eilmo-checkout-flow' ), 'selected' => __( 'Selected', 'eilmo-checkout-flow' ) ) as $state => $label ) {
			$this->start_controls_tab( 'product_button_' . $state, array( 'label' => $label ) );
			$selector = '{{WRAPPER}} .eilmo-cf-product-button' . ( 'hover' === $state ? ':hover' : ( 'selected' === $state ? '.is-selected' : '' ) );
			$this->add_control( $state . '_text_color', array( 'label' => __( 'Text Color', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'color: {{VALUE}};' ) ) );
			$this->add_control( $state . '_background_color', array( 'label' => __( 'Background', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $selector => 'background-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'border', 'selector' => '{{WRAPPER}} .eilmo-cf-product-button' ) );
		$this->add_responsive_control( 'border_radius', array( 'label' => __( 'Border Radius', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-product-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => __( 'Padding', 'eilmo-checkout-flow' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .eilmo-cf-product-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'shadow', 'selector' => '{{WRAPPER}} .eilmo-cf-product-button' ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$product_id = absint( $settings['product_id'] ?? 0 );
		$variation_id = absint( $settings['variation_id'] ?? 0 );
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
		if ( ! $product instanceof \WC_Product ) { return; }
		$product_type = $variation_id > 0 ? 'variation' : ( $product->is_type( 'variable' ) ? 'variable' : 'simple' );
		if ( $variation_id > 0 ) {
			if ( ! $product->is_type( 'variation' ) ) { return; }
			$product_id = absint( $product->get_parent_id() );
		}
		$can_purchase = $product->is_purchasable() && $product->is_in_stock();
		$quantity = max( 1, absint( $settings['quantity'] ?? 1 ) );
		$max = $product->get_max_purchase_quantity();
		if ( $max > 0 ) { $quantity = min( $quantity, $max ); }
		$image_id = absint( $product->get_image_id() );
		$image = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
		if ( ! is_string( $image ) || '' === $image ) { $image = wc_placeholder_img_src( 'woocommerce_thumbnail' ); }
		$name = $variation_id > 0 ? get_the_title( $product_id ) : $product->get_name();
		$variation_label = '';
		if ( $product->is_type( 'variation' ) ) {
			$variation_label = wc_get_formatted_variation( $product, true, false, false );
		}
		$button_text = sanitize_text_field( (string) ( $settings['button_text'] ?? __( 'Buy Now', 'eilmo-checkout-flow' ) ) );
		$added_text = sanitize_text_field( (string) ( $settings['added_text'] ?? __( 'Selected', 'eilmo-checkout-flow' ) ) );
		$theme_css = CheckoutStyle::get_css_variables();
		?>
		<div class="eilmo-cf-product-button-widget" style="<?php echo esc_attr( $theme_css ); ?>">
			<button type="button" class="eilmo-cf-product-button" data-eilmo-product-trigger
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-product-type="<?php echo esc_attr( $product_type ); ?>"
				data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				data-quantity="<?php echo esc_attr( (string) $quantity ); ?>"
				data-max-quantity="<?php echo esc_attr( (string) max( 0, (int) $max ) ); ?>"
				data-price="<?php echo esc_attr( wc_format_decimal( $product->get_price() ) ); ?>"
				data-item-name="<?php echo esc_attr( $name ); ?>"
				data-item-variation="<?php echo esc_attr( wp_strip_all_tags( $variation_label ) ); ?>"
				data-item-image="<?php echo esc_url( $image ); ?>"
				data-can-purchase="<?php echo esc_attr( $can_purchase ? 'yes' : 'no' ); ?>"
				data-add-label="<?php echo esc_attr( $button_text ); ?>"
				data-added-label="<?php echo esc_attr( $added_text ); ?>"
				data-allow-remove="<?php echo esc_attr( 'yes' === ( $settings['allow_remove'] ?? 'yes' ) ? 'yes' : 'no' ); ?>"
				data-scroll-to-checkout="<?php echo esc_attr( 'yes' === ( $settings['scroll_to_checkout'] ?? '' ) ? 'yes' : 'no' ); ?>"
				aria-pressed="false" <?php disabled( ! $can_purchase ); ?>>
				<span data-eilmo-product-trigger-text><?php echo esc_html( $can_purchase ? $button_text : __( 'Out of Stock', 'eilmo-checkout-flow' ) ); ?></span>
			</button>
		</div>
		<?php
	}

	private function get_product_options(): array {
		static $options = null;
		if ( is_array( $options ) ) { return $options; }
		$options = array();
		if ( ! function_exists( 'wc_get_products' ) ) { return $options; }
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) ) as $product ) {
			if ( $product instanceof \WC_Product && $product->is_type( array( 'simple', 'variable' ) ) ) { $options[ $product->get_id() ] = $product->get_name(); }
		}
		return $options;
	}

	private function get_variation_options(): array {
		static $options = null;
		if ( is_array( $options ) ) { return $options; }
		$options = array( 0 => __( 'Use product / selected variation', 'eilmo-checkout-flow' ) );
		if ( ! function_exists( 'wc_get_products' ) ) { return $options; }
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'type' => 'variable' ) ) as $parent ) {
			if ( ! $parent instanceof \WC_Product_Variable ) { continue; }
			foreach ( $parent->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof \WC_Product_Variation ) { continue; }
				$options[ $variation_id ] = $parent->get_name() . ' — ' . wc_get_formatted_variation( $variation, true, false, false );
			}
		}
		return $options;
	}
}
