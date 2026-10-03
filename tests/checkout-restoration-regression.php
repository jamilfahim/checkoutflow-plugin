<?php
/** Focused checkout-instance controls and language regression. */
namespace Elementor {
	class Controls_Manager {
		public const TAB_STYLE = 'style';
		public const TAB_CONTENT = 'content';
		public const TAB_ADVANCED = 'advanced';
		public const COLOR = 'color';
		public const RAW_HTML = 'raw_html';
	}
	class Widget_Base {
		public array $sections = array();
		public array $controls = array();
		public function get_data( $key ) { return 'settings' === $key ? array( 'checkout_mode' => 'single' ) : array(); }
		public function start_controls_section( $id, $options ) { $this->sections[ $id ] = $options; }
		public function add_control( $id, $options ) { $this->controls[ $id ] = $options; }
		public function end_controls_section() {}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'EILMO_CF_ASSETS_URL', 'https://example.test/assets/' );
	function __( $value, $domain = '' ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
	function assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) { throw new \RuntimeException( $message . ': ' . var_export( $actual, true ) ); }
	}
	require dirname( __DIR__ ) . '/src/Presentation/CheckoutPresentation.php';
	require dirname( __DIR__ ) . '/src/Payment/Gateways/GatewaySettings.php';
	require dirname( __DIR__ ) . '/src/Elementor/Controls/CompactSectionControls.php';
	require dirname( __DIR__ ) . '/src/Elementor/Widgets/CheckoutFlowWidget.php';

	use EilmoCheckout\Presentation\CheckoutPresentation;
	use EilmoCheckout\Payment\Gateways\GatewaySettings;
	use EilmoCheckout\Elementor\Controls\CompactSectionControls;
	use EilmoCheckout\Elementor\Widgets\CheckoutFlowWidget;

	$global = array( 'checkout_display' => array( 'language' => 'en' ) );
	assert_same( 'bn', CheckoutPresentation::resolve( $global, array( 'checkout_language' => 'bn' ) )['language'], 'Explicit widget Bangla must win' );
	assert_same( 'en', CheckoutPresentation::resolve( $global, array( 'checkout_language' => '' ) )['language'], 'Empty widget language must inherit' );
	$widget = new CheckoutFlowWidget();
	$method = new \ReflectionMethod( CheckoutFlowWidget::class, 'build_checkout_settings' );
	$settings = $method->invoke( $widget, array(
		'checkout_mode' => 'single', 'product_id' => 7,
		'show_package_selection' => '', 'show_delivery_section' => '',
		'delivery_columns_desktop' => '3', 'payment_options_columns_mobile' => '2',
		'payment_methods_arrangement' => 'grid',
	) );
	assert_same( 'no', $settings['show_package_selection'], 'Elementor off switch must hide package' );
	assert_same( 'no', $settings['eilmo_widget_layout']['show_delivery'], 'Elementor off switch must hide delivery UI' );
	assert_same( 3, $settings['eilmo_widget_layout']['delivery_columns_desktop'], 'Delivery desktop override must reach renderer' );
	assert_same( 2, $settings['eilmo_widget_layout']['payment_options_columns_mobile'], 'Payment mobile override must reach renderer' );
	assert_same( 'grid', $settings['eilmo_widget_layout']['payment_methods_arrangement'], 'Payment method arrangement must reach renderer' );
	$controls = new \Elementor\Widget_Base();
	CompactSectionControls::register( $controls );
	assert_same( 9, count( $controls->sections ), 'Style tab should have nine compact component sections' );
	assert_same( 'style', $controls->sections['eilmo_cf_compact_products']['tab'], 'Compact controls must remain in Style' );
	$old_logo = 'https://upload.wikimedia.org/wikipedia/en/6/68/Bkash_logo.svg';
	assert_same( 'https://example.test/assets/images/bkash.png', GatewaySettings::usable_icon_url( GatewaySettings::BKASH, $old_logo ), 'Broken legacy logo must use bundled PNG' );
	assert_same( 'https://example.test/custom.png', GatewaySettings::usable_icon_url( GatewaySettings::BKASH, 'https://example.test/custom.png' ), 'Custom logo must be retained' );
	echo "Checkout restoration regression passed.\n";
}
