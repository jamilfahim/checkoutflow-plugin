<?php
/**
 * WooCommerce Checkout Security bridge.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Security\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Security\Blacklist\BlacklistGuard;
use EilmoCheckout\Security\BotProtection\BotProtectionGuard;
use EilmoCheckout\Security\BotProtection\BotProtectionToken;
use EilmoCheckout\Security\DuplicateOrder\DuplicateOrderGuard;
use EilmoCheckout\Security\Logging\SecurityLogger;
use EilmoCheckout\Security\OrderCooldown\OrderCooldownGuard;
use EilmoCheckout\Security\RateLimit\RateLimiter;
use WC_Customer;
use WC_Order;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the shared Eilmo Security configuration to native
 * WooCommerce customer-facing checkout flows, including
 * server-signed Bot Protection transport.
 *
 * Supported:
 *
 * - WooCommerce Classic Checkout.
 * - WooCommerce Checkout Block / Store API.
 *
 * Intentionally excluded:
 *
 * - Eilmo custom checkout because OrderAjax already
 *   performs these protections directly.
 * - Admin-created orders.
 * - WooCommerce REST API order imports.
 * - Programmatically-created orders.
 */
final class WooCommerceSecurityBridge implements RegistrableInterface {

	/**
	 * Eilmo order source meta.
	 *
	 * @var string
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Eilmo created-via value.
	 *
	 * @var string
	 */
	private const CREATED_VIA_EILMO =
		'eilmo-checkout-flow';

	/**
	 * Rate Limit recorded meta.
	 *
	 * @var string
	 */
	private const META_RATE_LIMIT_RECORDED =
		'_eilmo_cf_security_rate_recorded';

	/**
	 * Native WooCommerce Order Cooldown recorded meta.
	 *
	 * @var string
	 */
	private const META_COOLDOWN_RECORDED =
		'_eilmo_cf_security_cooldown_recorded';

	/**
	 * Native WooCommerce Duplicate Order fingerprint.
	 *
	 * @var string
	 */
	private const META_DUPLICATE_FINGERPRINT =
		'_eilmo_cf_security_duplicate_fingerprint';

	/**
	 * Hash of the authoritative WooCommerce order state.
	 *
	 * @var string
	 */
	private const META_DUPLICATE_STATE =
		'_eilmo_cf_security_duplicate_state';


	/**
	 * Classic Checkout signed token field.
	 *
	 * @var string
	 */
	private const CLASSIC_TOKEN_FIELD =
		'eilmo_cf_security_start_token';

	/**
	 * Classic Checkout Honeypot field.
	 *
	 * @var string
	 */
	private const CLASSIC_HONEYPOT_FIELD =
		'eilmo_cf_security_contact';

	/**
	 * Checkout Block extension-data namespace.
	 *
	 * @var string
	 */
	private const BLOCK_EXTENSION_NAMESPACE =
		'eilmo-checkout-flow-security';

	/**
	 * Register WooCommerce security hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * ---------------------------------------------
		 * Checkout Block Store API extension schema
		 * ---------------------------------------------
		 *
		 * Checkout Block custom extension data must be
		 * registered with the Store API before WooCommerce
		 * will accept it in the checkout POST payload.
		 */
		if (
			did_action(
				'woocommerce_blocks_loaded'
			)
		) {
			$this->register_store_api_extension_data();
		} else {
			add_action(
				'woocommerce_blocks_loaded',
				array(
					$this,
					'register_store_api_extension_data',
				),
				20
			);
		}

		/*
		 * ---------------------------------------------
		 * Native Bot Protection transport
		 * ---------------------------------------------
		 *
		 * Classic Checkout receives normal form fields.
		 * Checkout Block sends the same signed token /
		 * Honeypot value through Store API extension
		 * data.
		 */
		add_action(
			'woocommerce_review_order_before_submit',
			array(
				$this,
				'render_classic_bot_fields',
			),
			20
		);

		add_filter(
			'render_block_woocommerce/checkout-actions-block',
			array(
				$this,
				'render_block_bot_fields',
			),
			999,
			1
		);

		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'enqueue_block_bot_transport',
			),
			30
		);

		/*
		 * ---------------------------------------------
		 * WooCommerce Classic Checkout
		 * ---------------------------------------------
		 */
		add_action(
			'woocommerce_after_checkout_validation',
			array(
				$this,
				'validate_classic_checkout',
			),
			20,
			2
		);

		add_action(
			'woocommerce_checkout_create_order',
			array(
				$this,
				'claim_classic_duplicate_order',
			),
			20,
			2
		);

		add_action(
			'woocommerce_checkout_order_created',
			array(
				$this,
				'attach_classic_duplicate_order',
			),
			20,
			1
		);

		add_action(
			'woocommerce_checkout_order_exception',
			array(
				$this,
				'release_classic_duplicate_order',
			),
			20,
			1
		);

		add_action(
			'woocommerce_checkout_order_processed',
			array(
				$this,
				'record_classic_order',
			),
			20,
			3
		);

		/*
		 * ---------------------------------------------
		 * WooCommerce Checkout Block / Store API
		 * ---------------------------------------------
		 */
		add_action(
			'woocommerce_store_api_checkout_update_customer_from_request',
			array(
				$this,
				'validate_store_api_checkout',
			),
			20,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array(
				$this,
				'claim_store_api_duplicate_order',
			),
			20,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			array(
				$this,
				'record_store_api_order',
			),
			20,
			1
		);
	}

	/**
	 * Register Checkout Block Security extension data
	 * with the WooCommerce Store API.
	 *
	 * Without this schema registration WooCommerce may
	 * discard the extension namespace from the final
	 * checkout request, which causes a valid browser
	 * checkout to fail with missing_bot_protection_token.
	 *
	 * @return void
	 */
	public function register_store_api_extension_data(): void {

		if (
			! function_exists(
				'woocommerce_store_api_register_endpoint_data'
			)
		) {
			return;
		}

		$checkout_schema_identifier =
			$this->get_checkout_schema_identifier();

		if ( '' === $checkout_schema_identifier ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint' =>
					$checkout_schema_identifier,

				'namespace' =>
					self::BLOCK_EXTENSION_NAMESPACE,

				'schema_callback' =>
					array(
						$this,
						'get_store_api_extension_schema',
					),

				'schema_type' =>
					ARRAY_A,
			)
		);
	}

	/**
	 * Get Checkout Block Security Store API schema.
	 *
	 * These values are request transport only. No secret
	 * or private server data is exposed by this schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_store_api_extension_schema(): array {

		return array(
			'start_token' =>
				array(
					'description' =>
						__(
							'Eilmo checkout security start token.',
							'eilmo-checkout-flow'
						),

					'type' =>
						'string',

					'context' =>
						array(
							'view',
							'edit',
						),

					'readonly' =>
						false,

					'optional' =>
						true,
				),

			'honeypot' =>
				array(
					'description' =>
						__(
							'Eilmo checkout security honeypot value.',
							'eilmo-checkout-flow'
						),

					'type' =>
						'string',

					'context' =>
						array(
							'view',
							'edit',
						),

					'readonly' =>
						false,

					'optional' =>
						true,
				),
		);
	}

	/**
	 * Resolve the WooCommerce Checkout Store API schema
	 * identifier across supported WooCommerce versions.
	 *
	 * @return string
	 */
	private function get_checkout_schema_identifier(): string {

		$schema_classes =
			array(
				'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CheckoutSchema',
				'Automattic\\WooCommerce\\StoreApi\\Schemas\\CheckoutSchema',
				'Automattic\\WooCommerce\\Blocks\\StoreApi\\Schemas\\CheckoutSchema',
			);

		foreach ( $schema_classes as $schema_class ) {

			if (
				! class_exists(
					$schema_class
				)
			) {
				continue;
			}

			$constant_name =
				$schema_class .
				'::IDENTIFIER';

			if (
				! defined(
					$constant_name
				)
			) {
				continue;
			}

			$identifier =
				sanitize_key(
					(string) constant(
						$constant_name
					)
				);

			if ( '' !== $identifier ) {
				return $identifier;
			}
		}

		return '';
	}

	/**
	 * Render Classic Checkout Bot Protection fields.
	 *
	 * The signed start token is generated server-side.
	 * The Honeypot is a normal text input positioned
	 * outside the visible viewport.
	 *
	 * @return void
	 */
	public function render_classic_bot_fields(): void {

		if (
			! $this->is_native_bot_protection_enabled() ||
			! class_exists(
				BotProtectionToken::class
			)
		) {
			return;
		}

		$token =
			$this->create_native_start_token();

		if (
			$this->is_token_or_minimum_time_enabled() &&
			'' === $token
		) {
			return;
		}

		?>
		<div
			class="eilmo-cf-native-security-fields"
			aria-hidden="true"
		>
			<?php if ( '' !== $token ) : ?>
				<input
					type="hidden"
					name="<?php
						echo esc_attr(
							self::CLASSIC_TOKEN_FIELD
						);
					?>"
					value="<?php
						echo esc_attr(
							$token
						);
					?>"
				>
			<?php endif; ?>

			<?php if (
				SecuritySettings::is_protection_enabled(
					'honeypot'
				)
			) : ?>
				<div
					style="
						position:absolute;
						left:-10000px;
						top:auto;
						width:1px;
						height:1px;
						overflow:hidden;
						opacity:0;
						pointer-events:none;
					"
				>
					<label for="eilmo-cf-native-security-contact">
						<?php
						esc_html_e(
							'Leave this field empty',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<input
						type="text"
						id="eilmo-cf-native-security-contact"
						name="<?php
							echo esc_attr(
								self::CLASSIC_HONEYPOT_FIELD
							);
						?>"
						value=""
						tabindex="-1"
						autocomplete="off"
						autocapitalize="off"
						spellcheck="false"
					>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Inject Checkout Block Bot Protection data beside
	 * the Place Order action.
	 *
	 * JavaScript transports these values through the
	 * official Checkout Store extension-data mechanism.
	 *
	 * @param string $block_content Block HTML.
	 *
	 * @return string
	 */
	public function render_block_bot_fields(
		string $block_content
	): string {

		if (
			! $this->is_native_bot_protection_enabled() ||
			! class_exists(
				BotProtectionToken::class
			)
		) {
			return $block_content;
		}

		$token =
			$this->create_native_start_token();

		if (
			$this->is_token_or_minimum_time_enabled() &&
			'' === $token
		) {
			return $block_content;
		}

		ob_start();
		?>
		<div
			data-eilmo-wc-block-security
			data-start-token="<?php
				echo esc_attr(
					$token
				);
			?>"
			aria-hidden="true"
		>
			<?php if (
				SecuritySettings::is_protection_enabled(
					'honeypot'
				)
			) : ?>
				<div
					style="
						position:absolute;
						left:-10000px;
						top:auto;
						width:1px;
						height:1px;
						overflow:hidden;
						opacity:0;
						pointer-events:none;
					"
				>
					<label for="eilmo-cf-block-security-contact">
						<?php
						esc_html_e(
							'Leave this field empty',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<input
						type="text"
						id="eilmo-cf-block-security-contact"
						value=""
						tabindex="-1"
						autocomplete="off"
						autocapitalize="off"
						spellcheck="false"
						data-eilmo-wc-block-honeypot
					>
				</div>
			<?php endif; ?>
		</div>
		<?php

		$security_html =
			ob_get_clean();

		if ( false === $security_html ) {
			return $block_content;
		}

		return
			(string) $security_html .
			$block_content;
	}

	/**
	 * Add the Checkout Block extension-data transport.
	 *
	 * A separate JavaScript asset is intentionally not
	 * required for this small bridge. The script waits
	 * for the WooCommerce checkout data store and sends
	 * the signed token / Honeypot through setExtensionData().
	 *
	 * @return void
	 */
	public function enqueue_block_bot_transport(): void {

		if (
			! $this->is_native_bot_protection_enabled() ||
			! function_exists(
				'is_checkout'
			) ||
			! is_checkout() ||
			is_order_received_page() ||
			is_checkout_pay_page()
		) {
			return;
		}

		wp_enqueue_script(
			'wp-data'
		);

		$namespace =
			wp_json_encode(
				self::BLOCK_EXTENSION_NAMESPACE
			);

		if ( ! is_string( $namespace ) ) {
			return;
		}

		$start_token =
			$this->create_native_start_token();

		if (
			$this->is_token_or_minimum_time_enabled() &&
			'' === $start_token
		) {
			return;
		}

		$encoded_start_token =
			wp_json_encode(
				$start_token
			);

		if ( ! is_string( $encoded_start_token ) ) {
			return;
		}

		/*
		 * WooCommerce has used two compatible
		 * setExtensionData() call shapes across Checkout
		 * Block versions:
		 *
		 * - setExtensionData( namespace, key, value )
		 * - setExtensionData( namespace, object )
		 *
		 * Prefer the current key/value API when available
		 * and keep a legacy fallback so stores running an
		 * older Blocks implementation are not locked out.
		 *
		 * The transport also re-syncs when Checkout Block
		 * mounts, when the hidden Honeypot changes, and
		 * immediately before Place Order is submitted.
		 */
		$script =
			"(function(){'use strict';" .
			"var namespace=" .
			$namespace .
			";" .
			"var serverToken=" .
			$encoded_start_token .
			";" .
			"var attempts=0;" .
			"var maxAttempts=80;" .
			"var timer=null;" .
			"var observer=null;" .
			"function getRoot(){" .
				"return document.querySelector('[data-eilmo-wc-block-security]');" .
			"}" .
			"function ensureRoot(){" .
				"var root=getRoot();" .
				"if(root){return root;}" .
				"if(!document.body){return null;}" .
				"root=document.createElement('div');" .
				"root.setAttribute('data-eilmo-wc-block-security','');" .
				"root.setAttribute('data-start-token',String(serverToken||''));" .
				"root.setAttribute('aria-hidden','true');" .
				"root.style.position='absolute';" .
				"root.style.left='-10000px';" .
				"root.style.width='1px';" .
				"root.style.height='1px';" .
				"root.style.overflow='hidden';" .
				"var input=document.createElement('input');" .
				"input.type='text';" .
				"input.tabIndex=-1;" .
				"input.autocomplete='off';" .
				"input.setAttribute('data-eilmo-wc-block-honeypot','');" .
				"root.appendChild(input);" .
				"document.body.appendChild(root);" .
				"return root;" .
			"}" .
			"function getDesired(root){" .
				"var honeypot=root?root.querySelector('[data-eilmo-wc-block-honeypot]'):null;" .
				"var rootToken=root?String(root.getAttribute('data-start-token')||''):'';" .
				"return {" .
					"start_token:rootToken||String(serverToken||'')," .
					"honeypot:honeypot?String(honeypot.value||''):''" .
				"};" .
			"}" .
			"function getCurrent(){" .
				"if(!window.wp||!wp.data){return null;}" .
				"var selector=wp.data.select('wc/store/checkout');" .
				"if(!selector||typeof selector.getExtensionData!=='function'){return null;}" .
				"var all=selector.getExtensionData()||{};" .
				"var current=all[namespace];" .
				"return current&&typeof current==='object'?current:{};" .
			"}" .
			"function matches(current,desired){" .
				"return !!current&&" .
					"String(current.start_token||'')===desired.start_token&&" .
					"String(current.honeypot||'')===desired.honeypot;" .
			"}" .
			"function write(dispatch,desired){" .
				"if(typeof dispatch.setExtensionData!=='function'){return false;}" .
				"try{" .
					"if(dispatch.setExtensionData.length>=3){" .
						"dispatch.setExtensionData(namespace,'start_token',desired.start_token);" .
						"dispatch.setExtensionData(namespace,'honeypot',desired.honeypot);" .
					"}else{" .
						"dispatch.setExtensionData(namespace,desired);" .
					"}" .
				"}catch(error){" .
					"try{" .
						"dispatch.setExtensionData(namespace,desired);" .
					"}catch(legacyError){" .
						"return false;" .
					"}" .
				"}" .
				"return true;" .
			"}" .
			"function sync(){" .
				"if(!window.wp||!wp.data){return false;}" .
				"var root=ensureRoot();" .
				"if(!root){return false;}" .
				"var dispatch=wp.data.dispatch('wc/store/checkout');" .
				"if(!dispatch||typeof dispatch.setExtensionData!=='function'){return false;}" .
				"var desired=getDesired(root);" .
				"var current=getCurrent();" .
				"if(matches(current,desired)){return true;}" .
				"if(!write(dispatch,desired)){return false;}" .
				"current=getCurrent();" .
				"if(matches(current,desired)){return true;}" .
				"try{" .
					"dispatch.setExtensionData(namespace,desired);" .
				"}catch(error){}" .
				"current=getCurrent();" .
				"return matches(current,desired);" .
			"}" .
			"function stopTimer(){" .
				"if(timer!==null){window.clearInterval(timer);timer=null;}" .
			"}" .
			"function retry(){" .
				"attempts++;" .
				"if(sync()||attempts>=maxAttempts){stopTimer();}" .
			"}" .
			"function watchDom(){" .
				"if(observer||!window.MutationObserver||!document.documentElement){return;}" .
				"observer=new MutationObserver(function(){" .
					"if(sync()){" .
						"observer.disconnect();" .
						"observer=null;" .
					"}" .
				"});" .
				"observer.observe(document.documentElement,{childList:true,subtree:true});" .
			"}" .
			"function boot(){" .
				"sync();" .
				"watchDom();" .
				"if(timer===null){" .
					"timer=window.setInterval(retry,250);" .
				"}" .
				"if(window.wp&&wp.data&&typeof wp.data.subscribe==='function'){" .
					"var unsubscribe=wp.data.subscribe(function(){" .
						"if(sync()&&typeof unsubscribe==='function'){" .
							"unsubscribe();" .
						"}" .
					"});" .
				"}" .
			"}" .
			"if(document.readyState==='loading'){" .
				"document.addEventListener('DOMContentLoaded',boot,{once:true});" .
			"}else{boot();}" .
			"window.addEventListener('pageshow',function(){attempts=0;boot();});" .
			"document.addEventListener('input',function(event){" .
				"if(event.target&&event.target.matches('[data-eilmo-wc-block-honeypot]')){sync();}" .
			"});" .
			"document.addEventListener('click',function(event){" .
				"var target=event.target;" .
				"if(!target||typeof target.closest!=='function'){return;}" .
				"if(target.closest('.wc-block-components-checkout-place-order-button')){sync();}" .
			"},true);" .
			"document.addEventListener('submit',function(){sync();},true);" .
			"})();";

		wp_add_inline_script(
			'wp-data',
			$script,
			'after'
		);
	}

	/**
	 * Validate native WooCommerce Classic Checkout.
	 *
	 * @param array<string,mixed> $data   Checkout data.
	 * @param WP_Error            $errors Checkout errors.
	 *
	 * @return void
	 */
	public function validate_classic_checkout(
		array $data,
		WP_Error $errors
	): void {

		if ( ! SecuritySettings::is_enabled() ) {
			return;
		}

		$source =
			SecurityLogger::SOURCE_WOOCOMMERCE_CLASSIC;

		$validated_customer =
			array(
				'customer_id' =>
					get_current_user_id(),

				'billing_phone' =>
					sanitize_text_field(
						(string) (
							$data[
								'billing_phone'
							] ??
								''
						)
					),

				'billing_email' =>
					sanitize_email(
						(string) (
							$data[
								'billing_email'
							] ??
								''
						)
					),

				'billing_country' =>
					sanitize_text_field(
						(string) (
							$data[
								'billing_country'
							] ??
								''
						)
					),
			);

		/*
		 * ---------------------------------------------
		 * Customer Blacklist
		 * ---------------------------------------------
		 */
		if (
			SecuritySettings::is_protection_enabled(
				'blacklist'
			)
		) {
			$blacklist_guard =
				new BlacklistGuard();

			$ip_check =
				$blacklist_guard->check_ip(
					$source
				);

			if (
				is_wp_error(
					$ip_check
				)
			) {
				$this->copy_error(
					$ip_check,
					$errors
				);

				return;
			}

			$customer_check =
				$blacklist_guard->check_customer(
					array(
						'customer' =>
							$validated_customer,
					),
					$source
				);

			if (
				is_wp_error(
					$customer_check
				)
			) {
				$this->copy_error(
					$customer_check,
					$errors
				);

				return;
			}
		}

		/*
		 * ---------------------------------------------
		 * Bot Protection
		 * ---------------------------------------------
		 */
		if ( $this->is_native_bot_protection_enabled() ) {

			$bot_guard =
				new BotProtectionGuard();

			$bot_check =
				$bot_guard->check_native(
					$this->get_classic_bot_data(),
					$source
				);

			if (
				is_wp_error(
					$bot_check
				)
			) {
				$this->copy_error(
					$bot_check,
					$errors
				);

				return;
			}
		}


	}


	/**
	 * Apply order-identity protections for Classic
	 * Checkout.
	 *
	 * Priority:
	 *
	 * 1. Duplicate Order Protection.
	 * 2. Order Cooldown.
	 * 3. Rate Limit.
	 *
	 * Running these checks after WooCommerce has copied
	 * authoritative cart/customer data into the order
	 * lets the most specific Duplicate Order message win
	 * before broader customer-level protections.
	 *
	 * @param WC_Order            $order Order being created.
	 * @param array<string,mixed> $data  Checkout data.
	 *
	 * @return void
	 *
	 * @throws \Exception When checkout is blocked.
	 */
	public function claim_classic_duplicate_order(
		WC_Order $order,
		array $data
	): void {

		unset( $data );

		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$duplicate_enabled =
			SecuritySettings::is_protection_enabled(
				'duplicate_order'
			);

		$cooldown_enabled =
			SecuritySettings::is_protection_enabled(
				'order_cooldown'
			);

		$rate_limit_enabled =
			SecuritySettings::is_protection_enabled(
				'rate_limit'
			);

		if (
			! $duplicate_enabled &&
			! $cooldown_enabled &&
			! $rate_limit_enabled
		) {
			return;
		}

		$validated =
			$this->build_duplicate_checkout_data(
				$order
			);

		$duplicate_guard = null;
		$fingerprint = '';
		$current_state = '';

		if (
			$duplicate_enabled &&
			! empty(
				$validated[
					'items'
				]
			)
		) {
			$current_state =
				$this->build_duplicate_state_hash(
					$validated
				);

			$existing_fingerprint =
				$this->normalize_fingerprint(
					$order->get_meta(
						self::META_DUPLICATE_FINGERPRINT,
						true
					)
				);

			$existing_state =
				$this->normalize_fingerprint(
					$order->get_meta(
						self::META_DUPLICATE_STATE,
						true
					)
				);

			/*
			 * WooCommerce may resume the same pending or
			 * failed order. Do not treat that payment retry
			 * as a new order for Duplicate, Cooldown or
			 * Rate Limit checks.
			 */
			if (
				'' !== $existing_fingerprint &&
				'' !== $current_state &&
				'' !== $existing_state &&
				hash_equals(
					$existing_state,
					$current_state
				)
			) {
				return;
			}

			$duplicate_guard =
				new DuplicateOrderGuard();

			$fingerprint =
				$duplicate_guard->claim(
					$validated,
					SecurityLogger::SOURCE_WOOCOMMERCE_CLASSIC
				);

			if (
				is_wp_error(
					$fingerprint
				)
			) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WooCommerce treats this as validation data and escapes it at the notice boundary.
				throw new \Exception(
					sanitize_text_field(
						(string) $fingerprint
							->get_error_message()
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if (
				! is_string(
					$fingerprint
				)
			) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WooCommerce treats this as validation data and escapes it at the notice boundary.
			throw new \Exception(
				$this->get_checkout_failure_message()
			);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( $cooldown_enabled ) {

			$cooldown_guard =
				new OrderCooldownGuard();

			$cooldown_check =
				$cooldown_guard->check(
					isset(
						$validated[
							'customer'
						]
					) &&
					is_array(
						$validated[
							'customer'
						]
					)
						? $validated[
							'customer'
						]
						: array(),
					SecurityLogger::SOURCE_WOOCOMMERCE_CLASSIC
				);

			if (
				is_wp_error(
					$cooldown_check
				)
			) {
				if (
					$duplicate_guard instanceof
						DuplicateOrderGuard &&
					'' !== $fingerprint
				) {
					$duplicate_guard->release(
						$fingerprint
					);
				}

				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WooCommerce treats this as validation data and escapes it at the notice boundary.
				throw new \Exception(
					sanitize_text_field(
						(string) $cooldown_check
							->get_error_message()
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( $rate_limit_enabled ) {

			$rate_limiter =
				new RateLimiter();

			$rate_limit_check =
				$rate_limiter->check(
					SecurityLogger::SOURCE_WOOCOMMERCE_CLASSIC
				);

			if (
				is_wp_error(
					$rate_limit_check
				)
			) {
				if (
					$duplicate_guard instanceof
						DuplicateOrderGuard &&
					'' !== $fingerprint
				) {
					$duplicate_guard->release(
						$fingerprint
					);
				}

				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- WooCommerce treats this as validation data and escapes it at the notice boundary.
				throw new \Exception(
					sanitize_text_field(
						(string) $rate_limit_check
							->get_error_message()
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( '' === $fingerprint ) {
			return;
		}

		$order->update_meta_data(
			self::META_DUPLICATE_FINGERPRINT,
			$fingerprint
		);

		$order->update_meta_data(
			self::META_DUPLICATE_STATE,
			$current_state
		);
	}


	/**
	 * Attach Classic Checkout duplicate claim to the
	 * persisted WooCommerce order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public function attach_classic_duplicate_order(
		WC_Order $order
	): void {

		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$fingerprint =
			$this->normalize_fingerprint(
				$order->get_meta(
					self::META_DUPLICATE_FINGERPRINT,
					true
				)
			);

		if (
			'' ===
				$fingerprint ||
			$order->get_id() <= 0
		) {
			return;
		}

		$duplicate_guard =
			new DuplicateOrderGuard();

		$attached =
			$duplicate_guard->attach_order(
				$fingerprint,
				$order->get_id()
			);

		if ( ! $attached ) {
			do_action(
				'eilmo_cf/security/woocommerce_duplicate_attach_failed',
				$fingerprint,
				$order
			);
		}
	}

	/**
	 * Release uncommitted Classic Checkout duplicate
	 * claim after WooCommerce order creation failure.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public function release_classic_duplicate_order(
		WC_Order $order
	): void {

		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$fingerprint =
			$this->normalize_fingerprint(
				$order->get_meta(
					self::META_DUPLICATE_FINGERPRINT,
					true
				)
			);

		if ( '' === $fingerprint ) {
			return;
		}

		$duplicate_guard =
			new DuplicateOrderGuard();

		/*
		 * release() deliberately deletes only an
		 * uncommitted order_id = 0 fingerprint.
		 */
		$duplicate_guard->release(
			$fingerprint
		);
	}

	/**
	 * Validate WooCommerce Checkout Block / Store API.
	 *
	 * @param WC_Customer     $customer Customer.
	 * @param WP_REST_Request $request  Request.
	 *
	 * @return void
	 *
	 * @throws RouteException When checkout is blocked.
	 */
	public function validate_store_api_checkout(
		WC_Customer $customer,
		WP_REST_Request $request
	): void {

		if ( ! SecuritySettings::is_enabled() ) {
			return;
		}

		/*
		 * Security blocking belongs to the final Place
		 * Order request.
		 */
		if (
			'POST' !==
				strtoupper(
					(string) $request
						->get_method()
				)
		) {
			return;
		}

		$source =
			SecurityLogger::SOURCE_WOOCOMMERCE_BLOCK;

		$validated_customer =
			array(
				'customer_id' =>
					absint(
						$customer->get_id()
					),

				'billing_phone' =>
					sanitize_text_field(
						(string) $customer
							->get_billing_phone()
					),

				'billing_email' =>
					sanitize_email(
						(string) $customer
							->get_billing_email()
					),

				'billing_country' =>
					sanitize_text_field(
						(string) $customer
							->get_billing_country()
					),
			);

		/*
		 * ---------------------------------------------
		 * Customer Blacklist
		 * ---------------------------------------------
		 */
		if (
			SecuritySettings::is_protection_enabled(
				'blacklist'
			)
		) {
			$blacklist_guard =
				new BlacklistGuard();

			$ip_check =
				$blacklist_guard->check_ip(
					$source
				);

			if (
				is_wp_error(
					$ip_check
				)
			) {
				$this->throw_store_api_error(
					$ip_check,
					403
				);
			}

			$customer_check =
				$blacklist_guard->check_customer(
					array(
						'customer' =>
							$validated_customer,
					),
					$source
				);

			if (
				is_wp_error(
					$customer_check
				)
			) {
				$this->throw_store_api_error(
					$customer_check,
					403
				);
			}
		}

		/*
		 * ---------------------------------------------
		 * Bot Protection
		 * ---------------------------------------------
		 */
		if (
			$this->is_native_bot_protection_enabled() &&
			! $this->should_skip_store_api_bot_protection(
				$request
			)
		) {
			$bot_guard =
				new BotProtectionGuard();

			$bot_check =
				$bot_guard->check_native(
					$this->get_store_api_bot_data(
						$request
					),
					$source
				);

			if (
				is_wp_error(
					$bot_check
				)
			) {
				$this->throw_store_api_error(
					$bot_check,
					403
				);
			}
		}


	}


	/**
	 * Apply order-identity protections for Checkout
	 * Block / Store API.
	 *
	 * Priority:
	 *
	 * 1. Duplicate Order Protection.
	 * 2. Order Cooldown.
	 * 3. Rate Limit.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 *
	 * @return void
	 *
	 * @throws RouteException When checkout is blocked.
	 */
	public function claim_store_api_duplicate_order(
		WC_Order $order,
		WP_REST_Request $request
	): void {

		/*
		 * Only the final place-order POST is allowed to
		 * run order-identity protections.
		 */
		if (
			'POST' !==
				strtoupper(
					(string) $request
						->get_method()
				)
		) {
			return;
		}

		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$duplicate_enabled =
			SecuritySettings::is_protection_enabled(
				'duplicate_order'
			);

		$cooldown_enabled =
			SecuritySettings::is_protection_enabled(
				'order_cooldown'
			);

		$rate_limit_enabled =
			SecuritySettings::is_protection_enabled(
				'rate_limit'
			);

		if (
			! $duplicate_enabled &&
			! $cooldown_enabled &&
			! $rate_limit_enabled
		) {
			return;
		}

		$validated =
			$this->build_duplicate_checkout_data(
				$order
			);

		$duplicate_guard = null;
		$fingerprint = '';
		$current_state = '';

		if (
			$duplicate_enabled &&
			! empty(
				$validated[
					'items'
				]
			)
		) {
			$current_state =
				$this->build_duplicate_state_hash(
					$validated
				);

			$existing_fingerprint =
				$this->normalize_fingerprint(
					$order->get_meta(
						self::META_DUPLICATE_FINGERPRINT,
						true
					)
				);

			$existing_state =
				$this->normalize_fingerprint(
					$order->get_meta(
						self::META_DUPLICATE_STATE,
						true
					)
				);

			/*
			 * A payment retry for the same authoritative
			 * Store API order is not a new order.
			 */
			if (
				'' !== $existing_fingerprint &&
				'' !== $current_state &&
				'' !== $existing_state &&
				hash_equals(
					$existing_state,
					$current_state
				)
			) {
				return;
			}

			$duplicate_guard =
				new DuplicateOrderGuard();

			$fingerprint =
				$duplicate_guard->claim(
					$validated,
					SecurityLogger::SOURCE_WOOCOMMERCE_BLOCK
				);

			if (
				is_wp_error(
					$fingerprint
				)
			) {
				$status =
					'duplicate_order_detected' ===
						$fingerprint
							->get_error_code()
						? 409
						: 500;

				$this->throw_store_api_error(
					$fingerprint,
					$status
				);
			}

			if (
				! is_string(
					$fingerprint
				)
			) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API exception data is serialized by WooCommerce, not printed as HTML here.
				throw new RouteException(
					'duplicate_order_check_failed',
					$this->get_checkout_failure_message(),
					500
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( $cooldown_enabled ) {

			$cooldown_guard =
				new OrderCooldownGuard();

			$cooldown_check =
				$cooldown_guard->check(
					isset(
						$validated[
							'customer'
						]
					) &&
					is_array(
						$validated[
							'customer'
						]
					)
						? $validated[
							'customer'
						]
						: array(),
					SecurityLogger::SOURCE_WOOCOMMERCE_BLOCK
				);

			if (
				is_wp_error(
					$cooldown_check
				)
			) {
				if (
					$duplicate_guard instanceof
						DuplicateOrderGuard &&
					'' !== $fingerprint
				) {
					$duplicate_guard->release(
						$fingerprint
					);
				}

				$this->throw_store_api_error(
					$cooldown_check,
					429
				);
			}
		}

		if ( $rate_limit_enabled ) {

			$rate_limiter =
				new RateLimiter();

			$rate_limit_check =
				$rate_limiter->check(
					SecurityLogger::SOURCE_WOOCOMMERCE_BLOCK
				);

			if (
				is_wp_error(
					$rate_limit_check
				)
			) {
				if (
					$duplicate_guard instanceof
						DuplicateOrderGuard &&
					'' !== $fingerprint
				) {
					$duplicate_guard->release(
						$fingerprint
					);
				}

				$this->throw_store_api_error(
					$rate_limit_check,
					429
				);
			}
		}

		if ( '' === $fingerprint ) {
			return;
		}

		if (
			$order->get_id() <= 0
		) {
			if (
				$duplicate_guard instanceof
					DuplicateOrderGuard
			) {
				$duplicate_guard->release(
					$fingerprint
				);
			}

			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API exception data is serialized by WooCommerce, not printed as HTML here.
			throw new RouteException(
				'duplicate_order_attach_failed',
				$this->get_checkout_failure_message(),
				500
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$attached =
			$duplicate_guard instanceof
				DuplicateOrderGuard
				? $duplicate_guard->attach_order(
					$fingerprint,
					$order->get_id()
				)
				: false;

		if ( ! $attached ) {

			if (
				$duplicate_guard instanceof
					DuplicateOrderGuard
			) {
				$duplicate_guard->release(
					$fingerprint
				);
			}

			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API exception data is serialized by WooCommerce, not printed as HTML here.
			throw new RouteException(
				'duplicate_order_attach_failed',
				$this->get_checkout_failure_message(),
				500
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$order->update_meta_data(
			self::META_DUPLICATE_FINGERPRINT,
			$fingerprint
		);

		$order->update_meta_data(
			self::META_DUPLICATE_STATE,
			$current_state
		);
	}


	/**
	 * Whether any native Bot Protection rule needs
	 * frontend transport / validation.
	 *
	 * @return bool
	 */
	private function is_native_bot_protection_enabled(): bool {

		if ( ! SecuritySettings::is_enabled() ) {
			return false;
		}

		return (
			SecuritySettings::is_protection_enabled(
				'honeypot'
			) ||
			SecuritySettings::is_protection_enabled(
				'security_token'
			) ||
			SecuritySettings::is_protection_enabled(
				'minimum_checkout_time'
			)
		);
	}

	/**
	 * Whether a signed start token is required.
	 *
	 * Minimum Checkout Time depends on the token's
	 * signed issued_at value.
	 *
	 * @return bool
	 */
	private function is_token_or_minimum_time_enabled(): bool {

		return (
			SecuritySettings::is_protection_enabled(
				'security_token'
			) ||
			SecuritySettings::is_protection_enabled(
				'minimum_checkout_time'
			)
		);
	}

	/**
	 * Create a signed native checkout start token.
	 *
	 * @return string
	 */
	private function create_native_start_token(): string {

		if (
			! $this->is_token_or_minimum_time_enabled() ||
			! class_exists(
				BotProtectionToken::class
			)
		) {
			return '';
		}

		$token_service =
			new BotProtectionToken();

		return sanitize_text_field(
			(string) $token_service->create()
		);
	}

	/**
	 * Read Classic Checkout Bot Protection fields.
	 *
	 * @return array<string,mixed>
	 */
	private function get_classic_bot_data(): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout nonce; this reads fields from that same checkout request.
		$start_token =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					self::CLASSIC_TOKEN_FIELD
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							self::CLASSIC_TOKEN_FIELD
						]
					)
				)
				: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout nonce; this reads fields from that same checkout request.
		$honeypot =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					self::CLASSIC_HONEYPOT_FIELD
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							self::CLASSIC_HONEYPOT_FIELD
						]
					)
				)
				: '';

		return array(
			'start_token' =>
				$start_token,

			'honeypot' =>
				$honeypot,
		);
	}

	/**
	 * Read Checkout Block Bot Protection extension data.
	 *
	 * @param WP_REST_Request $request Store API request.
	 *
	 * @return array<string,mixed>
	 */
	private function get_store_api_bot_data(
		WP_REST_Request $request
	): array {

		$extensions =
			$request->get_param(
				'extensions'
			);

		if ( ! is_array( $extensions ) ) {

			$json =
				$request->get_json_params();

			$extensions =
				is_array( $json ) &&
				isset(
					$json[
						'extensions'
					]
				) &&
				is_array(
					$json[
						'extensions'
					]
				)
					? $json[
						'extensions'
					]
					: array();
		}

		$security =
			isset(
				$extensions[
					self::BLOCK_EXTENSION_NAMESPACE
				]
			) &&
			is_array(
				$extensions[
					self::BLOCK_EXTENSION_NAMESPACE
				]
			)
				? $extensions[
					self::BLOCK_EXTENSION_NAMESPACE
				]
				: array();

		return array(
			'start_token' =>
				sanitize_text_field(
					(string) (
						$security[
							'start_token'
						] ??
							''
					)
				),

			'honeypot' =>
				sanitize_text_field(
					(string) (
						$security[
							'honeypot'
						] ??
							''
					)
				),
		);
	}

	/**
	 * Allow selected Store API payment methods to skip
	 * Bot Protection when an express/hosted flow cannot
	 * transport checkout extension data.
	 *
	 * Default is no bypass.
	 *
	 * @param WP_REST_Request $request Store API request.
	 *
	 * @return bool
	 */
	private function should_skip_store_api_bot_protection(
		WP_REST_Request $request
	): bool {

		$payment_method =
			sanitize_key(
				(string) (
					$request->get_param(
						'payment_method'
					) ??
						''
				)
			);

		/**
		 * Filters Store API payment methods that bypass
		 * Eilmo Bot Protection.
		 *
		 * Use only for trusted express/hosted payment
		 * integrations that cannot submit Checkout
		 * extension data.
		 *
		 * @param array<int,string> $methods Payment method IDs.
		 * @param WP_REST_Request   $request Store API request.
		 */
		$methods =
			apply_filters(
				'eilmo_cf/security/store_api_bot_skip_payment_methods',
				array(),
				$request
			);

		if ( ! is_array( $methods ) ) {
			return false;
		}

		$methods =
			array_values(
				array_filter(
					array_map(
						'sanitize_key',
						$methods
					)
				)
			);

		return (
			'' !== $payment_method &&
			in_array(
				$payment_method,
				$methods,
				true
			)
		);
	}

	/**
	 * Build authoritative native WooCommerce checkout
	 * data for DuplicateOrderGuard.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	private function build_duplicate_checkout_data(
		WC_Order $order
	): array {

		$items =
			array();

		foreach (
			$order->get_items(
				'line_item'
			) as $item
		) {

			if (
				! is_object(
					$item
				) ||
				! method_exists(
					$item,
					'get_product_id'
				) ||
				! method_exists(
					$item,
					'get_quantity'
				)
			) {
				continue;
			}

			$product_id =
				absint(
					$item->get_product_id()
				);

			$variation_id =
				method_exists(
					$item,
					'get_variation_id'
				)
					? absint(
						$item->get_variation_id()
					)
					: 0;

			$quantity =
				$item->get_quantity();

			if (
				$product_id <= 0 ||
				! is_numeric(
					$quantity
				) ||
				(float) $quantity <= 0
			) {
				continue;
			}

			$items[] =
				array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						(float) $quantity,
				);
		}

		/*
		 * Product insertion order must not affect the
		 * Duplicate Order fingerprint.
		 */
		usort(
			$items,
			static function (
				array $first,
				array $second
			): int {

				$first_key =
					sprintf(
						'%1$010d:%2$010d:%3$020.6f',
						absint(
							$first[
								'product_id'
							] ??
								0
						),
						absint(
							$first[
								'variation_id'
							] ??
								0
						),
						(float) (
							$first[
								'quantity'
							] ??
								0
						)
					);

				$second_key =
					sprintf(
						'%1$010d:%2$010d:%3$020.6f',
						absint(
							$second[
								'product_id'
							] ??
								0
						),
						absint(
							$second[
								'variation_id'
							] ??
								0
						),
						(float) (
							$second[
								'quantity'
							] ??
								0
						)
					);

				return strcmp(
					$first_key,
					$second_key
				);
			}
		);

		$shipping_method_ids =
			array();

		foreach (
			$order->get_items(
				'shipping'
			) as $shipping_item
		) {

			if (
				! is_object(
					$shipping_item
				)
			) {
				continue;
			}

			$method_id =
				method_exists(
					$shipping_item,
					'get_method_id'
				)
					? sanitize_key(
						(string) $shipping_item
							->get_method_id()
					)
					: '';

			$instance_id =
				method_exists(
					$shipping_item,
					'get_instance_id'
				)
					? absint(
						$shipping_item
							->get_instance_id()
					)
					: 0;

			if ( '' === $method_id ) {
				continue;
			}

			$shipping_method_ids[] =
				$method_id .
				(
					$instance_id > 0
						? '_' .
							$instance_id
						: ''
				);
		}

		$shipping_method_ids =
			array_values(
				array_unique(
					$shipping_method_ids
				)
			);

		sort(
			$shipping_method_ids,
			SORT_STRING
		);

		return array(
			'customer' =>
				array(
					'customer_id' =>
						absint(
							$order->get_customer_id()
						),

					'billing_phone' =>
						sanitize_text_field(
							(string) $order
								->get_billing_phone()
						),

					'billing_email' =>
						sanitize_email(
							(string) $order
								->get_billing_email()
						),
				),

			'totals' =>
				array(
					'grand_total' =>
						(float) $order->get_total(),
				),

			'coupon_code' =>
				implode(
					',',
					array_map(
						'sanitize_text_field',
						$order->get_coupon_codes()
					)
				),

			'items' =>
				$items,

			/*
			 * Native WooCommerce checkout does not
			 * contain Eilmo Combo Offers.
			 */
			'combo_offers' =>
				array(
					'offers' =>
						array(),
				),

			/*
			 * Native WooCommerce checkout does not
			 * contain Eilmo Order Bumps.
			 */
			'order_bumps' =>
				array(
					'offers' =>
						array(),
				),

			'delivery' =>
				array(
					'method_id' =>
						implode(
							'__',
							$shipping_method_ids
						),
				),
		);
	}

	/**
	 * Build private authoritative order-state hash.
	 *
	 * @param array<string,mixed> $validated Validated data.
	 *
	 * @return string
	 */
	private function build_duplicate_state_hash(
		array $validated
	): string {

		$json =
			wp_json_encode(
				$validated
			);

		if (
			! is_string(
				$json
			) ||
			'' === $json
		) {
			return '';
		}

		return hash_hmac(
			'sha256',
			$json,
			wp_salt(
				'auth'
			)
		);
	}

	/**
	 * Normalize fingerprint hash.
	 *
	 * @param mixed $fingerprint Fingerprint.
	 *
	 * @return string
	 */
	private function normalize_fingerprint(
		$fingerprint
	): string {

		if (
			! is_scalar(
				$fingerprint
			)
		) {
			return '';
		}

		$fingerprint =
			strtolower(
				trim(
					(string) $fingerprint
				)
			);

		if (
			64 !==
				strlen(
					$fingerprint
				) ||
			1 !==
				preg_match(
					'/^[a-f0-9]{64}$/',
					$fingerprint
				)
		) {
			return '';
		}

		return $fingerprint;
	}

	/**
	 * Record successfully-created Classic Checkout
	 * order for Rate Limiting.
	 *
	 * @param int                 $order_id    Order ID.
	 * @param array<string,mixed> $posted_data Checkout data.
	 * @param WC_Order            $order       Order.
	 *
	 * @return void
	 */
	public function record_classic_order(
		int $order_id,
		array $posted_data,
		WC_Order $order
	): void {

		unset(
			$order_id,
			$posted_data
		);

		/*
		 * Eilmo's custom OrderAjax also fires the
		 * WooCommerce compatibility hook.
		 *
		 * Never double-count an Eilmo order here.
		 */
		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$this->record_order_once(
			$order
		);
	}

	/**
	 * Record successfully processed Store API order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public function record_store_api_order(
		WC_Order $order
	): void {

		if (
			$this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$this->record_order_once(
			$order
		);
	}

	/**
	 * Record Rate Limit once per native WooCommerce
	 * order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	private function record_order_once(
		WC_Order $order
	): void {

		if (
			$order->get_id() <= 0 ||
			! SecuritySettings::is_enabled()
		) {
			return;
		}

		$changed = false;

		/*
		 * ---------------------------------------------
		 * Rate Limit record
		 * ---------------------------------------------
		 */
		if (
			SecuritySettings::is_protection_enabled(
				'rate_limit'
			) &&
			'yes' !==
				(string) $order->get_meta(
					self::META_RATE_LIMIT_RECORDED,
					true
				)
		) {
			$rate_limiter =
				new RateLimiter();

			$rate_limiter->record_order();

			$order->update_meta_data(
				self::META_RATE_LIMIT_RECORDED,
				'yes'
			);

			$changed = true;
		}

		/*
		 * ---------------------------------------------
		 * Order Cooldown record
		 * ---------------------------------------------
		 *
		 * A separate marker prevents payment retries or
		 * repeated WooCommerce hooks from extending the
		 * cooldown for the same order.
		 */
		if (
			SecuritySettings::is_protection_enabled(
				'order_cooldown'
			) &&
			'yes' !==
				(string) $order->get_meta(
					self::META_COOLDOWN_RECORDED,
					true
				)
		) {
			$cooldown_guard =
				new OrderCooldownGuard();

			$cooldown_guard->record_order(
				$order,
				$this->get_native_checkout_source(
					$order
				)
			);

			$order->update_meta_data(
				self::META_COOLDOWN_RECORDED,
				'yes'
			);

			$changed = true;
		}

		if ( ! $changed ) {
			return;
		}

		try {

			$order->save();

		} catch ( \Throwable $throwable ) {

			do_action(
				'eilmo_cf/security/native_order_marker_save_failed',
				$order,
				$throwable
			);
		}
	}

	/**
	 * Resolve native checkout source for internal
	 * Order Cooldown metadata.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_native_checkout_source(
		WC_Order $order
	): string {

		$created_via =
			sanitize_key(
				(string) $order
					->get_created_via()
			);

		if (
			in_array(
				$created_via,
				array(
					'store-api',
					'store_api',
					'checkout-block',
					'checkout_block',
				),
				true
			)
		) {
			return 'woocommerce_block';
		}

		return 'woocommerce_classic';
	}


	/**
	 * Copy WP_Error into Classic Checkout errors.
	 *
	 * @param WP_Error $source Source error.
	 * @param WP_Error $target Checkout errors.
	 *
	 * @return void
	 */
	private function copy_error(
		WP_Error $source,
		WP_Error $target
	): void {

		$codes =
			$source->get_error_codes();

		if ( empty( $codes ) ) {

			$target->add(
				'eilmo_checkout_security',
				$this->get_checkout_failure_message()
			);

			return;
		}

		foreach (
			$codes as
				$code
		) {

			$code =
				sanitize_key(
					(string) $code
				);

			if ( '' === $code ) {
				$code =
					'eilmo_checkout_security';
			}

			$messages =
				$source->get_error_messages(
					$code
				);

			if ( empty( $messages ) ) {
				$messages =
					array(
						$this->get_checkout_failure_message(),
					);
			}

			foreach (
				$messages as
					$message
			) {

				$message =
					sanitize_text_field(
						(string) $message
					);

				if ( '' === $message ) {
					continue;
				}

				$target->add(
					$code,
					$message
				);
			}
		}
	}

	/**
	 * Throw Store API compatible security error.
	 *
	 * @param WP_Error $error  Error.
	 * @param int      $status HTTP status.
	 *
	 * @return void
	 *
	 * @throws RouteException Always.
	 */
	private function throw_store_api_error(
		WP_Error $error,
		int $status
	): void {

		$codes =
			$error->get_error_codes();

		$code =
			! empty(
				$codes
			)
				? sanitize_key(
					(string) $codes[0]
				)
				: 'eilmo_checkout_security';

		if ( '' === $code ) {
			$code =
				'eilmo_checkout_security';
		}

		$message =
			sanitize_text_field(
				(string) $error
					->get_error_message()
			);

		if ( '' === $message ) {
			$message =
				$this->get_checkout_failure_message();
		}

		$status =
			absint(
				$status
			);

		if (
			$status < 400 ||
			$status > 599
		) {
			$status = 400;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API exception data is serialized by WooCommerce, not printed as HTML here.
		throw new RouteException(
			$code,
			$message,
			$status
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Use the dashboard message for an unexpected
	 * checkout security failure on either native flow.
	 *
	 * @return string
	 */
	private function get_checkout_failure_message(): string {
		return SecuritySettings::get_message(
			'checkout_request_failed',
			__(
				'The checkout request could not be verified. Please try again.',
				'eilmo-checkout-flow'
			)
		);
	}

	/**
	 * Determine whether order belongs to the Eilmo
	 * custom checkout.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function is_eilmo_order(
		WC_Order $order
	): bool {

		if (
			self::CREATED_VIA_EILMO !==
				(string) $order->get_meta(
					self::META_CREATED_VIA,
					true
				)
		) {
			return false;
		}

		/*
		 * NativePaymentBridge and Eilmo payment gateways
		 * also add the Eilmo metadata contract to orders
		 * placed through WooCommerce. The marker alone does
		 * not mean OrderAjax already recorded this order.
		 */
		if (
			'yes' ===
				(string) $order->get_meta(
					'_eilmo_cf_native_checkout',
					true
				)
		) {
			return false;
		}

		return ! in_array(
			(string) $order->get_created_via(),
			array( 'checkout', 'store-api' ),
			true
		);
	}
}
