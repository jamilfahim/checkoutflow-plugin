<?php
namespace EilmoCheckout\Payment\Gateways;
defined( 'ABSPATH' ) || exit;
if(class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')){
final class MobileGatewayBlocksSupport extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	protected $name; private $gateway_settings=array();
	public function __construct(string $gateway_id){$this->name=sanitize_key($gateway_id);} public function initialize(){$this->gateway_settings=GatewaySettings::get($this->name);} public function is_active(){return 'yes'===(string)($this->gateway_settings['enabled']??'no');}
	public function get_payment_method_script_handles(){ $h='eilmo-cf-block-mobile-gateways'; if(!wp_script_is($h,'registered')){$f=EILMO_CF_PATH.'assets/src/js/frontend/woocommerce-mobile-gateways-block.js';wp_register_script($h,EILMO_CF_ASSETS_URL.'src/js/frontend/woocommerce-mobile-gateways-block.js',array('wc-blocks-registry','wc-settings','wp-element','wp-html-entities'),file_exists($f)?(string)filemtime($f):EILMO_CF_VERSION,true);} return array($h); }
	public function get_payment_method_data(){return array('title'=>sanitize_text_field((string)($this->gateway_settings['title']??$this->name)),'description'=>wp_kses_post((string)($this->gateway_settings['description']??'')),'accountLabel'=>sanitize_text_field((string)($this->gateway_settings['account_label']??'')),'accountNumber'=>sanitize_text_field((string)($this->gateway_settings['account_number']??'')),'instructions'=>wp_kses_post((string)($this->gateway_settings['instructions']??'')),'supports'=>array('products'));}
}}
