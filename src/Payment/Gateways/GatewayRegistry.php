<?php
namespace EilmoCheckout\Payment\Gateways;
use EilmoCheckout\Admin\DefaultCheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
defined( 'ABSPATH' ) || exit;
final class GatewayRegistry implements RegistrableInterface {
	public function register(): void { add_filter('woocommerce_payment_gateways',array($this,'gateways'),30); add_action('init',array(GatewaySettings::class,'maybe_migrate_legacy'),30); add_action('wp_enqueue_scripts',array($this,'enqueue_direct_gateway_assets'),50); add_action('woocommerce_blocks_loaded',array($this,'register_blocks_support')); add_filter('render_block_woocommerce/checkout',array($this,'render_block_checkout'),5,2); add_filter('eilmo_cf/advance_payment/render_settings',array($this,'sync_cod_availability'),5); add_filter('eilmo_cf/advance_payment/render_data',array($this,'sync_cod_copy'),5,2); }
	public function gateways(array $gateways): array { $gateways[]=BkashGateway::class; $gateways[]=NagadGateway::class; return $gateways; }
	public function enqueue_direct_gateway_assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		/* The integration's visual layer is absent when the normal WooCommerce checkout is used. */
		if ( ! DefaultCheckoutSettings::integration_enabled() ) {
			$css = EILMO_CF_PATH . 'assets/src/css/frontend/woocommerce-mobile-gateways.css';
			if ( file_exists( $css ) ) {
				wp_enqueue_style(
					'eilmo-cf-wc-mobile-gateways',
					EILMO_CF_ASSETS_URL . 'src/css/frontend/woocommerce-mobile-gateways.css',
					array( 'woocommerce-general' ),
					(string) filemtime( $css )
				);
			}
		}

		$file = EILMO_CF_PATH . 'assets/src/js/frontend/woocommerce-mobile-gateways.js';
		if ( ! file_exists( $file ) ) {
			return;
		}
		wp_enqueue_script( 'eilmo-cf-wc-mobile-gateways', EILMO_CF_ASSETS_URL . 'src/js/frontend/woocommerce-mobile-gateways.js', array(), (string) filemtime( $file ), true );
		wp_localize_script( 'eilmo-cf-wc-mobile-gateways', 'eilmoCfWooMobileGateway', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'action'    => 'eilmo_cf_upload_payment_proof',
			'nonce'     => wp_create_nonce( 'eilmo_cf_payment_proof' ),
			'uploading' => __( 'Uploading screenshot…', 'eilmo-checkout-flow' ),
			'failed'    => __( 'Screenshot upload failed.', 'eilmo-checkout-flow' ),
		) );
	}


	public function sync_cod_availability( array $settings ): array { if(!function_exists('WC')||!WC()||!WC()->payment_gateways())return $settings; try{$all=WC()->payment_gateways()->payment_gateways();$cod=$all['cod']??null;if(!$cod||'yes'!==(string)($cod->enabled??'no'))$settings['allow_cash_on_delivery']='no';}catch(\Throwable $e){} return $settings; }
	public function sync_cod_copy( array $data, array $context ): array { if(!function_exists('WC')||!WC()||!WC()->payment_gateways())return $data; try{$all=WC()->payment_gateways()->payment_gateways();$cod=$all['cod']??null;if($cod){$data['cash_on_delivery_texts']=array('label'=>is_callable(array($cod,'get_title'))?(string)$cod->get_title():__('Cash on Delivery','eilmo-checkout-flow'),'description'=>is_callable(array($cod,'get_description'))?(string)$cod->get_description():__('Pay with cash upon delivery.','eilmo-checkout-flow'));}}catch(\Throwable $e){} return $data; }
	public function render_block_checkout( string $content, array $block ): string { if(is_admin()||!(GatewaySettings::enabled(GatewaySettings::BKASH)||GatewaySettings::enabled(GatewaySettings::NAGAD)))return $content; static $rendering=false;if($rendering)return $content;$rendering=true;$classic=do_shortcode('[woocommerce_checkout]');$rendering=false;return ''!==trim((string)$classic)?$classic:$content; }
	public function register_blocks_support(): void { add_action('woocommerce_blocks_payment_method_type_registration',static function($registry):void{ if(class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')&&class_exists(MobileGatewayBlocksSupport::class)){ $registry->register(new MobileGatewayBlocksSupport(GatewaySettings::BKASH)); $registry->register(new MobileGatewayBlocksSupport(GatewaySettings::NAGAD)); } }); }
}
