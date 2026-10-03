<?php
/** Verify that Elementor visibility choices survive checkout preparation. */
namespace Elementor {
	class Widget_Base {
		public function get_data( $key ) { return 'settings' === $key ? array( 'checkout_mode' => 'single' ) : array(); }
	}
}
namespace EilmoCheckout\Rendering {
	final class CheckoutDisplaySettings {
		public static function get(): array { return array(); }
	}
}
namespace EilmoCheckout\Products\Services {
	final class SingleProductConfig {
		public function get( array $overrides = array() ): array {
			/* The real config accepts only ProductSettings keys. */
			return array( 'show_description' => 'yes', 'show_variations' => 'yes', 'variation_selection' => 'single' );
		}
	}
	final class MultipleProductsConfig {
		public function get( array $overrides = array() ): array { return array(); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function absint( $value ): int { return abs( (int) $value ); }
	function sanitize_key( $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
	function sanitize_text_field( $value ): string { return (string) $value; }
	function wp_parse_args( $args, $defaults ): array { return array_merge( $defaults, $args ); }
	function is_product(): bool { return false; }
	function get_queried_object_id(): int { return 0; }
	require dirname( __DIR__ ) . '/src/Presentation/CheckoutLanguage.php';
	require dirname( __DIR__ ) . '/src/Presentation/CheckoutPresentation.php';
	require dirname( __DIR__ ) . '/src/Rendering/CheckoutRenderer.php';
	require dirname( __DIR__ ) . '/src/Elementor/Widgets/CheckoutFlowWidget.php';
	$build = new \ReflectionMethod( \EilmoCheckout\Elementor\Widgets\CheckoutFlowWidget::class, 'build_checkout_settings' );
	$widget_settings = $build->invoke( new \EilmoCheckout\Elementor\Widgets\CheckoutFlowWidget(), array(
		'checkout_mode' => 'single', 'product_id' => 7,
		'show_package_selection' => 'yes', 'show_package_title' => '',
		'show_package_helper' => '', 'show_selected_quantity' => '',
		'show_descriptions' => '', 'max_visible_variations' => 7,
	) );
	$prepare = new \ReflectionMethod( \EilmoCheckout\Rendering\CheckoutRenderer::class, 'prepare_settings' );
	$settings = $prepare->invoke( new \EilmoCheckout\Rendering\CheckoutRenderer(), $widget_settings );
	if ( 'yes' !== ( $settings['single_product']['show_checkout_selector'] ?? null ) ) {
		throw new \RuntimeException( 'Package section unexpectedly disappeared.' );
	}
	foreach ( array( 'show_package_title', 'show_package_helper', 'show_selected_quantity', 'show_variation_descriptions', 'show_description' ) as $key ) {
		if ( 'no' !== ( $settings['single_product'][ $key ] ?? null ) ) {
			throw new \RuntimeException( $key . ' was lost while preparing checkout settings.' );
		}
	}
	if ( 7 !== ( $settings['single_product']['max_visible_variations'] ?? null ) ) {
		throw new \RuntimeException( 'Variation count was lost while preparing checkout settings.' );
	}
	echo "Checkout visibility regression passed.\n";
}
