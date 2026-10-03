<?php
/**
 * Order AJAX handler.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Ajax;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\ComboOffers\Services\ComboOfferValidator;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Discounts\Services\SpecialDiscountEligibility;
use EilmoCheckout\Discounts\Services\SpecialDiscountContext;
use EilmoCheckout\Couriers\Services\FraudCheckoutGuard;
use EilmoCheckout\OrderBumps\Services\OrderBumpResolver;
use EilmoCheckout\Orders\Services\OrderCreator;
use EilmoCheckout\Orders\Services\OrderIdempotency;
use EilmoCheckout\Orders\Services\OrderValidator;
use EilmoCheckout\Security\Blacklist\BlacklistGuard;
use EilmoCheckout\Security\DuplicateOrder\DuplicateOrderGuard;
use EilmoCheckout\Security\OrderCooldown\OrderCooldownGuard;
use EilmoCheckout\Security\RateLimit\RateLimiter;
use EilmoCheckout\Security\BotProtection\BotProtectionGuard;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles final Eilmo checkout submission.
 */
final class OrderAjax implements RegistrableInterface {

	/**
	 * AJAX action.
	 *
	 * @var string
	 */
	public const ACTION =
		'eilmo_cf_create_order';

	/**
	 * Frontend nonce action.
	 *
	 * Must match Assets frontend config.
	 *
	 * @var string
	 */
	public const NONCE_ACTION =
		'eilmo_cf_frontend';

	/**
	 * Maximum encoded checkout request size.
	 *
	 * Multiple Products legitimately submits more rows than
	 * Single Product, but the JSON envelope must remain bounded
	 * before WooCommerce objects are loaded.
	 *
	 * @var int
	 */
	private const MAX_REQUEST_PAYLOAD_BYTES =
		1048576;

	/**
	 * Maximum normal product rows accepted from one checkout.
	 *
	 * Duplicate rows are still grouped authoritatively by
	 * OrderValidator after this transport-level guard.
	 *
	 * @var int
	 */
	private const MAX_NORMAL_ITEM_ROWS =
		200;

	/**
	 * Eilmo order source meta.
	 *
	 * @var string
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Payment-status meta key.
	 *
	 * @var string
	 */
	private const PAYMENT_STATUS_META =
		'_eilmo_cf_payment_status';

	/**
	 * Payment type meta.
	 *
	 * @var string
	 */
	private const META_PAYMENT_TYPE =
		'_eilmo_cf_payment_type';

	/**
	 * Payment method meta.
	 *
	 * @var string
	 */
	private const META_PAYMENT_METHOD =
		'_eilmo_cf_payment_method';

	/**
	 * Payment method key meta.
	 *
	 * @var string
	 */
	private const META_PAYMENT_METHOD_KEY =
		'_eilmo_cf_payment_method_key';

	/**
	 * Payment source meta.
	 *
	 * @var string
	 */
	private const META_PAYMENT_SOURCE =
		'_eilmo_cf_payment_source';

	/**
	 * WooCommerce gateway ID meta.
	 *
	 * @var string
	 */
	private const META_GATEWAY_ID =
		'_eilmo_cf_payment_gateway_id';

	/**
	 * Product total meta.
	 *
	 * @var string
	 */
	private const META_PRODUCT_TOTAL =
		'_eilmo_cf_product_total';

	/**
	 * Automatic discount meta.
	 *
	 * @var string
	 */
	private const META_AUTOMATIC_DISCOUNT =
		'_eilmo_cf_automatic_discount';

	/**
	 * Coupon discount meta.
	 *
	 * @var string
	 */
	private const META_COUPON_DISCOUNT =
		'_eilmo_cf_coupon_discount';

	/**
	 * Delivery charge meta.
	 *
	 * @var string
	 */
	private const META_DELIVERY_CHARGE =
		'_eilmo_cf_delivery_charge';

	/**
	 * Full-payment discount meta.
	 *
	 * @var string
	 */
	private const META_FULL_PAYMENT_DISCOUNT =
		'_eilmo_cf_full_payment_discount';

	/**
	 * Grand total meta.
	 *
	 * @var string
	 */
	private const META_GRAND_TOTAL =
		'_eilmo_cf_grand_total';

	/**
	 * Pay-now amount meta.
	 *
	 * @var string
	 */
	private const META_PAY_NOW =
		'_eilmo_cf_pay_now';

	/**
	 * Remaining due meta.
	 *
	 * @var string
	 */
	private const META_REMAINING_DUE =
		'_eilmo_cf_remaining_due';

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_ajax_' . self::ACTION,
			array(
				$this,
				'create_order',
			)
		);

		add_action(
			'wp_ajax_nopriv_' . self::ACTION,
			array(
				$this,
				'create_order',
			)
		);
	}

	/**
	 * Create an Eilmo checkout order.
	 *
	 * @return void
	 */
	public function create_order(): void {

		/*
		 * Security.
		 */
		if (
			! check_ajax_referer(
				self::NONCE_ACTION,
				'nonce',
				false
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_nonce',

					'message' =>
						__(
							'Your session has expired. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		/*
		 * Checkout availability.
		 */
		if (
			! $this->is_checkout_enabled()
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'checkout_disabled',

					'message' =>
						__(
							'Checkout is currently unavailable.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		/*
		 * ---------------------------------------------
		 * Customer Blacklist - IP
		 * ---------------------------------------------
		 *
		 * Check the current visitor IP before processing
		 * the checkout payload.
		 */
		$blacklist_guard =
			new BlacklistGuard();

		$blacklist_ip_check =
			$blacklist_guard->check_ip();

		if (
			is_wp_error(
				$blacklist_ip_check
			)
		) {
			$this->send_error(
				$blacklist_ip_check,
				403
			);
		}

		/*
		 * Request payload.
		 */
		$payload =
			$this->get_request_payload();

		if (
			is_wp_error(
				$payload
			)
		) {
			$this->send_error(
				$payload,
				400
			);
		}

		/**
		 * Filters raw order request before validation.
		 *
		 * @param array<string,mixed> $payload Payload.
		 */
		$payload =
			apply_filters(
				'eilmo_cf/orders/request_payload',
				$payload
			);

		if (
			! is_array(
				$payload
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_request',

					'message' =>
						__(
							'Invalid checkout request.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * Multiple Products sends a list of independent Simple
		 * products and exact variations. Validate only the request
		 * envelope here; product ownership, purchasability, stock
		 * and quantities remain authoritative in OrderValidator.
		 */
		$payload_envelope_check =
			$this->validate_request_payload_envelope(
				$payload
			);

		if (
			is_wp_error(
				$payload_envelope_check
			)
		) {
			$this->send_error(
				$payload_envelope_check,
				400
			);
		}

        /*
        * ---------------------------------------------
        * Bot Protection
        * ---------------------------------------------
        *
        * Honeypot and Minimum Checkout Time are checked
        * before expensive checkout validation begins.
        *
        * The minimum-time token is generated and signed
        * server-side by CheckoutRenderer, transported by
        * checkout.js, and verified here.
        */
        $bot_protection_guard =
            new BotProtectionGuard();

        $bot_protection_check =
            $bot_protection_guard->check(
                $payload
            );

        if (
            is_wp_error(
                $bot_protection_check
            )
        ) {
            $bot_protection_status =
                'checkout_too_fast' ===
                    $bot_protection_check
                        ->get_error_code()
                        ? 429
                        : 403;

            $this->send_error(
                $bot_protection_check,
                $bot_protection_status
            );
        }

		/*
		 * ---------------------------------------------
		 * Checkout Idempotency Token
		 * ---------------------------------------------
		 */
		$idempotency =
			new OrderIdempotency();

		$checkout_token =
			$idempotency->normalize_token(
				(string) (
					$payload[
						'checkout_token'
					] ??
						''
				)
			);

		if (
			'' ===
				$checkout_token
		) {
			$this->send_error(
				new WP_Error(
					'invalid_checkout_token',
					SecuritySettings::get_message(
						'checkout_token_invalid',
						__(
							'The checkout request token is invalid. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						)
					)
				),
				400
			);
		}

		/*
		 * ---------------------------------------------
		 * Authoritative checkout validation
		 * ---------------------------------------------
		 *
		 * Normal checkout data is validated here.
		 *
		 * Combo Offers and Order Bumps receive their
		 * own authoritative validation below because
		 * both features must reload server configuration
		 * instead of trusting browser-supplied values.
		 */
		$validator =
			new OrderValidator();

		$validated =
			$validator->validate(
				$payload
			);

		if (
			is_wp_error(
				$validated
			)
		) {
			$this->send_error(
				$validated,
				400
			);
		}

		if (
			! is_array(
				$validated
			)
		) {
			$this->send_error(
				new WP_Error(
					'invalid_validation_result',
					__(
						'Checkout validation failed. Please try again.',
						'eilmo-checkout-flow'
					)
				),
				400
			);
		}

		/*
		 * ---------------------------------------------
		 * Customer Blacklist - Phone / Email
		 * ---------------------------------------------
		 *
		 * Customer identifiers are checked only after
		 * OrderValidator has normalized and validated
		 * the submitted customer information.
		 */
		$blacklist_customer_check =
			$blacklist_guard->check_customer(
				$validated
			);

		if (
			is_wp_error(
				$blacklist_customer_check
			)
		) {
			$this->send_error(
				$blacklist_customer_check,
				403
			);
		}

		/*
		 * ---------------------------------------------
		 * Signed live courier-risk enforcement
		 * ---------------------------------------------
		 *
		 * This runs after normal customer validation and the existing
		 * blacklist guard. The browser cannot alter a decision or reuse
		 * it for another phone because the token is signed and phone-bound.
		 */
		$fraud_result = ( new FraudCheckoutGuard() )->validate( $payload, $validated );

		if ( is_wp_error( $fraud_result ) ) {
			$this->send_error(
				$fraud_result,
				'eilmo_cf_live_fraud_blocked' === $fraud_result->get_error_code() ? 403 : 400
			);
		}

		$validated['fraud_check'] = is_array( $fraud_result ) ? $fraud_result : array();


		/*
		 * ---------------------------------------------
		 * Authoritative Combo Offer validation
		 * ---------------------------------------------
		 *
		 * Combo IDs, selection mode and pricing from the
		 * browser are never trusted.
		 *
		 * The signed checkout context is verified and
		 * all selected Combo Offers are reloaded and
		 * recalculated from server settings.
		 */
		$combo_validator =
			new ComboOfferValidator();

		$validated_combo_offers =
			$combo_validator->validate(
				$payload[
					'combo_offers'
				] ?? array()
			);

		if (
			is_wp_error(
				$validated_combo_offers
			)
		) {
			$this->send_error(
				$validated_combo_offers,
				400
			);
		}

		if (
			! is_array(
				$validated_combo_offers
			)
		) {
			$this->send_error(
				new WP_Error(
					'invalid_combo_result',
					__(
						'Combo Offer validation failed. Please try again.',
						'eilmo-checkout-flow'
					)
				),
				400
			);
		}

		/*
		 * Pass only the trusted server-side Combo result
		 * forward to OrderCreator.
		 */
		$validated[
			'combo_offers'
		] =
			$validated_combo_offers;

		/**
		 * Fires after Combo Offer validation.
		 *
		 * @param array<string,mixed> $validated_combo_offers Validated Combo data.
		 * @param array<string,mixed> $validated              Validated checkout.
		 * @param array<string,mixed> $payload                Raw request payload.
		 */
		do_action(
			'eilmo_cf/orders/combo_offers_validated',
			$validated_combo_offers,
			$validated,
			$payload
		);

		/*
		 * ---------------------------------------------
		 * Authoritative Order Bump validation
		 * ---------------------------------------------
		 *
		 * IMPORTANT:
		 *
		 * The browser submits only configured Order
		 * Bump IDs.
		 *
		 * We deliberately ignore any browser-supplied:
		 *
		 * - Product ID.
		 * - Variation ID.
		 * - Quantity.
		 * - Regular price.
		 * - Offer price.
		 * - Discount amount.
		 * - Pricing type.
		 * - Pricing value.
		 * - Discount compatibility flags.
		 *
		 * OrderBumpResolver reloads all authoritative
		 * information from saved Eilmo settings.
		 */
		$order_bump_request =
			isset(
				$payload[
					'order_bumps'
				]
			) &&
			is_array(
				$payload[
					'order_bumps'
				]
			)
				? $payload[
					'order_bumps'
				]
				: array();

		$special_discount_context =
			isset( $order_bump_request['context'] ) && is_array( $order_bump_request['context'] )
				? $order_bump_request['context']
				: array();
		$special_discount_context_service = new SpecialDiscountContext();

		if ( ! $special_discount_context_service->verify( $special_discount_context ) ) {
			$this->send_error(
				new WP_Error(
					'invalid_special_discount_context',
					__( 'The Special Discount selection is invalid. Please refresh the page and try again.', 'eilmo-checkout-flow' )
				),
				400
			);
		}

		$selected_order_bump_ids =
			$order_bump_request[
				'selected_ids'
			] ??
				array();

		$selected_order_bump_ids = $special_discount_context_service->normalize_ids(
			is_array( $selected_order_bump_ids ) ? $selected_order_bump_ids : array()
		);

		foreach ( $selected_order_bump_ids as $selected_order_bump_id ) {
			if ( ! $special_discount_context_service->is_allowed( $selected_order_bump_id, $special_discount_context ) ) {
				$this->send_error(
					new WP_Error(
						'special_discount_not_assigned',
						__( 'One of the selected Special Discounts is not available in this checkout.', 'eilmo-checkout-flow' )
					),
					400
				);
			}
		}

		$validated['special_discount_scope'] =
			$special_discount_context_service->get_scope( $special_discount_context );
		$validated['special_discount_ids'] =
			$special_discount_context_service->get_allowed_ids( $special_discount_context );

		$order_bump_resolver =
			new OrderBumpResolver();

		$validated_order_bumps =
			$order_bump_resolver
				->resolve_selected(
					$selected_order_bump_ids,
					array_merge(
						( new SpecialDiscountEligibility() )->build_context(
							isset( $validated['items'] ) && is_array( $validated['items'] )
								? $validated['items']
								: array(),
							$validated_combo_offers
						),
						array(
							'special_discount_scope' => $validated['special_discount_scope'],
							'special_discount_ids' => $validated['special_discount_ids'],
							'current_checkout_product_ids' => array_values( array_unique( array_filter( array_map( static function ( $item ) { return is_array( $item ) ? absint( $item['product_id'] ?? 0 ) : 0; }, isset( $validated['items'] ) && is_array( $validated['items'] ) ? $validated['items'] : array() ) ) ) ),
						)
					)
				);

		if (
			is_wp_error(
				$validated_order_bumps
			)
		) {
			$this->send_error(
				$validated_order_bumps,
				400
			);
		}

		if (
			! is_array(
				$validated_order_bumps
			)
		) {
			$this->send_error(
				new WP_Error(
					'invalid_order_bump_result',
					__(
						'Special Discount validation failed. Please try again.',
						'eilmo-checkout-flow'
					)
				),
				400
			);
		}

		/*
		 * Never pass raw frontend Order Bump data to
		 * OrderCreator.
		 *
		 * Only the trusted server-side resolved result
		 * is forwarded.
		 */
		$validated[
			'order_bumps'
		] =
			$validated_order_bumps;

        do_action(
            'eilmo_cf/orders/order_bumps_validated',
            $validated_order_bumps,
            $validated,
            $payload
        );

        /*
        * ---------------------------------------------
        * Combined purchase stock validation
        * ---------------------------------------------
        *
        * Normal products, Combo components and Order
        * Bumps are intentionally separate purchase
        * contexts and remain separate WooCommerce
        * line items.
        *
        * Stock, however, is shared by the underlying
        * WooCommerce product. The same product can be
        * selected through more than one context, so we
        * must validate the combined requested quantity
        * before creating the order.
        *
        * Example:
        *
        * Normal product x 1
        * + Combo component x 1
        * + Order Bump x 1
        *
        * If available stock is only 2, the checkout
        * must fail before creating the order.
        */
        $combined_stock_validation =
            $this->validate_combined_purchase_stock(
                $validated
            );

        if (
            is_wp_error(
                $combined_stock_validation
            )
        ) {
            $this->send_error(
                $combined_stock_validation,
                400
            );
        }

        /**
         * Fires after the final combined product stock
         * validation succeeds.
         *
         * @param array<string,mixed> $validated Validated checkout.
         */
        do_action(
            'eilmo_cf/orders/combined_stock_validated',
            $validated
        );

		/*
		 * Validate WooCommerce gateway availability.
		 */
		$gateway_validation =
			$this->validate_gateway_selection(
				$validated
			);

		if (
			is_wp_error(
				$gateway_validation
			)
		) {
			$this->send_error(
				$gateway_validation,
				400
			);
		}

		/*
		 * ---------------------------------------------
		 * Claim Checkout Token
		 * ---------------------------------------------
		 */
		$claim =
			$idempotency->claim(
				$checkout_token
			);

		if (
			is_wp_error(
				$claim
			)
		) {
			$status =
				'order_already_processing' ===
					$claim->get_error_code()
						? 409
						: 400;

			$this->send_error(
				$claim,
				$status
			);
		}

		if (
			! is_array(
				$claim
			)
		) {
			$this->send_error(
				new WP_Error(
					'idempotency_failed',
					SecuritySettings::get_message(
						'checkout_request_failed',
						__(
							'The checkout request could not be finalized. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						)
					)
				),
				500
			);
		}

		/*
		 * ---------------------------------------------
		 * Existing Order
		 * ---------------------------------------------
		 *
		 * Same checkout token already produced an order.
		 * Never create another order.
		 */
		if (
			'existing' ===
				(
					$claim[
						'state'
					] ??
						''
				)
		) {
			$existing_order =
				$claim[
					'order'
				] ??
					null;

			if (
				! $existing_order instanceof WC_Order ||
				! $this->is_eilmo_order(
					$existing_order
				)
			) {
				$this->send_error(
					new WP_Error(
						'idempotent_order_invalid',
						SecuritySettings::get_message(
							'checkout_request_failed',
							__(
								'The checkout request could not be finalized. Please refresh the page and try again.',
								'eilmo-checkout-flow'
							)
						)
					),
					500
				);
			}

			$this->send_existing_order_success(
				$existing_order
			);
		}

		$lock_owner =
			sanitize_text_field(
				(string) (
					$claim[
						'lock_owner'
					] ??
						''
				)
			);

		if (
			'claimed' !==
				(
					$claim[
						'state'
					] ??
						''
				) ||
			'' ===
				$lock_owner
		) {
			$this->send_error(
				new WP_Error(
					'idempotency_claim_failed',
					SecuritySettings::get_message(
						'checkout_request_failed',
						__(
							'The checkout request could not be finalized. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						)
					)
				),
				500
			);
		}

		/*
		 * ---------------------------------------------
		 * Duplicate Order Protection
		 * ---------------------------------------------
		 *
		 * The checkout token has already been claimed, so
		 * an idempotent retry cannot create a second
		 * duplicate fingerprint claim.
		 *
		 * The fingerprint is built only from authoritative
		 * validated checkout data.
		 */
		$duplicate_guard =
			new DuplicateOrderGuard();

		$duplicate_fingerprint =
			$duplicate_guard->claim(
				$validated
			);

		if (
			is_wp_error(
				$duplicate_fingerprint
			)
		) {
			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$duplicate_status =
				'duplicate_order_detected' ===
					$duplicate_fingerprint
						->get_error_code()
						? 409
						: 500;

			$this->send_error(
				$duplicate_fingerprint,
				$duplicate_status
			);
		}

		if (
			! is_string(
				$duplicate_fingerprint
			)
		) {
			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				new WP_Error(
					'duplicate_order_check_failed',
					SecuritySettings::get_message(
						'checkout_request_failed',
						__(
							'The checkout request could not be verified. Please try again.',
							'eilmo-checkout-flow'
						)
					)
				),
				500
			);
		}

		/*
		 * ---------------------------------------------
		 * Order Cooldown
		 * ---------------------------------------------
		 *
		 * Duplicate Order Protection intentionally runs
		 * first. This means the same/similar repeated
		 * order receives the Duplicate Order message,
		 * while a different order placed too quickly
		 * receives the Order Cooldown message.
		 */
		$order_cooldown_guard =
			new OrderCooldownGuard();

		$order_cooldown_check =
			$order_cooldown_guard->check(
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
				'eilmo'
			);

		if (
			is_wp_error(
				$order_cooldown_check
			)
		) {
			if ( '' !== $duplicate_fingerprint ) {
				$duplicate_guard->release(
					$duplicate_fingerprint
				);
			}

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				$order_cooldown_check,
				429
			);
		}

		/*
		 * ---------------------------------------------
		 * Checkout Rate Limit
		 * ---------------------------------------------
		 *
		 * Rate Limit runs after Duplicate Order and
		 * Order Cooldown so the most specific customer
		 * message wins first.
		 */
		$rate_limiter =
			new RateLimiter();

		$rate_limit_check =
			$rate_limiter->check();

		if (
			is_wp_error(
				$rate_limit_check
			)
		) {
			if ( '' !== $duplicate_fingerprint ) {
				$duplicate_guard->release(
					$duplicate_fingerprint
				);
			}

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				$rate_limit_check,
				429
			);
		}

		/*
		 * ---------------------------------------------
		 * Create authoritative WooCommerce order.
		 * ---------------------------------------------
		 *
		 * $validated now contains:
		 *
		 * - Valid normal products.
		 * - Trusted Combo Offers.
		 * - Trusted Order Bumps.
		 * - Valid customer data.
		 * - Valid delivery.
		 * - Valid payment selection.
		 * - Valid coupon request.
		 */
		$creator =
			new OrderCreator();

		try {

			$result =
				$creator->create(
					$validated
				);

		} catch ( \Throwable $throwable ) {

			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				new WP_Error(
					'order_creation_failed',
					__(
						'The order could not be created. Please try again.',
						'eilmo-checkout-flow'
					)
				),
				500
			);
		}

		if (
			is_wp_error(
				$result
			)
		) {
			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				$result,
				400
			);
		}

		if (
			! is_array(
				$result
			)
		) {
			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'order_creation_failed',

					'message' =>
						__(
							'The order could not be created. Please try again.',
							'eilmo-checkout-flow'
						),
				),
				500
			);
		}

		$order =
			$this->get_created_order(
				$result
			);

		if (
			! $order
		) {
			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'order_creation_failed',

					'message' =>
						__(
							'The order could not be loaded after creation.',
							'eilmo-checkout-flow'
						),
				),
				500
			);
		}

		/*
		 * ---------------------------------------------
		 * Generic Woo Gateway + Partial Payment
		 * ---------------------------------------------
		 */
		if (
			'woocommerce_gateway' ===
				(
					$result[
						'payment_source'
					] ??
						''
				) &&
			! empty(
				$result[
					'is_partial_payment'
				]
			)
		) {
			$this->delete_unprocessed_order(
				$order
			);

			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'gateway_partial_payment_unsupported',

					'message' =>
						__(
							'This payment gateway cannot safely process an advance payment. Please select Full Payment or use a supported manual payment method.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * ---------------------------------------------
		 * Attach Checkout Token to Order
		 * ---------------------------------------------
		 *
		 * Important:
		 *
		 * We attach the token before payment routing.
		 *
		 * Even if the network response is lost after
		 * this point, a retry finds the same order.
		 */
		$attached =
			$idempotency->attach_to_order(
				$order,
				$checkout_token
			);

		if (
			is_wp_error(
				$attached
			)
		) {
			$this->delete_unprocessed_order(
				$order
			);

			$duplicate_guard->release(
				$duplicate_fingerprint
			);

			$idempotency->release(
				$checkout_token,
				$lock_owner
			);

			$this->send_error(
				$attached,
				500
			);
		}

		/*
		 * ---------------------------------------------
		 * Attach Duplicate Order Claim
		 * ---------------------------------------------
		 *
		 * An empty fingerprint means Duplicate Order
		 * Protection is disabled for this checkout.
		 *
		 * A failed metadata attachment is intentionally
		 * non-fatal because the active fingerprint claim
		 * still protects the configured detection window.
		 */
		if (
			'' !==
				$duplicate_fingerprint
		) {
			$duplicate_attached =
				$duplicate_guard->attach_order(
					$duplicate_fingerprint,
					$order->get_id()
				);

			if ( ! $duplicate_attached ) {
				do_action(
					'eilmo_cf/security/duplicate_order_attach_failed',
					$duplicate_fingerprint,
					$order
				);
			}
		}

		/*
		 * The persisted order now owns the token.
		 * Processing lock is no longer required.
		 */
		$idempotency->release(
			$checkout_token,
			$lock_owner
		);

		/*
		 * ---------------------------------------------
		 * Record Successful Order for Rate Limit
		 * ---------------------------------------------
		 *
		 * Only newly created and successfully persisted
		 * orders increment the rate-limit counter.
		 *
		 * Idempotent replays return earlier and therefore
		 * never count as additional orders.
		 */
		$rate_limiter->record_order();

		/*
		 * ---------------------------------------------
		 * Record Successful Order Cooldown
		 * ---------------------------------------------
		 *
		 * Only a newly created, successfully persisted
		 * order starts the cooldown. Idempotent replays
		 * return earlier and therefore do not extend it.
		 */
		$order_cooldown_guard->record_order(
			$order,
			'eilmo'
		);

		/*
		 * ---------------------------------------------
		 * WooCommerce Compatibility Hooks
		 * ---------------------------------------------
		 */
		$posted_data =
			$this->build_woocommerce_posted_data(
				$validated,
				$payload
			);

		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce owns these checkout compatibility hooks.
		do_action(
			'woocommerce_checkout_update_order_meta',
			$order->get_id(),
			$posted_data
		);

		do_action(
			'woocommerce_checkout_order_created',
			$order
		);

		do_action(
			'woocommerce_checkout_order_processed',
			$order->get_id(),
			$posted_data,
			$order
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/*
		 * ---------------------------------------------
		 * Cash on Delivery
		 * ---------------------------------------------
		 *
		 * COD intentionally has pay_now = 0, but it is
		 * NOT a "no payment required" order. The complete
		 * grand total remains due on delivery.
		 *
		 * Therefore COD must be routed before the generic
		 * no-payment branch below.
		 */
		if (
			$this->is_cash_on_delivery_result(
				$result
			)
		) {
			$this->process_manual_payment(
				$order,
				$result
			);
		}

		/*
		 * ---------------------------------------------
		 * No Payment Required
		 * ---------------------------------------------
		 */
		if (
			empty(
				$result[
					'requires_payment'
				]
			)
		) {
			$this->complete_without_payment(
				$order,
				$result
			);
		}

		/*
		 * ---------------------------------------------
		 * WooCommerce Gateway
		 * ---------------------------------------------
		 */
		if (
			'woocommerce_gateway' ===
				(
					$result[
						'payment_source'
					] ??
						''
				)
		) {
			$this->handoff_woocommerce_gateway(
				$order,
				$result,
				$validated
			);
		}

		/*
		 * ---------------------------------------------
		 * Eilmo Manual Payment
		 * ---------------------------------------------
		 */
		$this->process_manual_payment(
			$order,
			$result
		);
	}

	/**
	 * Send success response for an order that was
	 * already created with the same checkout token.
	 *
	 * @param WC_Order $order Existing order.
	 *
	 * @return void
	 */
	private function send_existing_order_success(
		WC_Order $order
	): void {

		$result =
			$this->build_existing_order_result(
				$order
			);

		$payment_source =
			sanitize_key(
				(string) (
					$result[
						'payment_source'
					] ??
						''
				)
			);

		$is_cash_on_delivery =
			$this->is_cash_on_delivery_result(
				$result
			);

		$redirect =
			$order
				->get_checkout_order_received_url();

		$flow =
			'existing_order';

		$message =
			__(
				'This order has already been placed.',
				'eilmo-checkout-flow'
			);

		if (
			$is_cash_on_delivery
		) {
			$flow =
				'cash_on_delivery';

			$message =
				__(
					'This Cash on Delivery order has already been placed.',
					'eilmo-checkout-flow'
				);
		} /*
		 * Existing unpaid WooCommerce gateway order.
		 *
		 * Continue the same order payment instead of
		 * creating another order.
		 */ elseif (
			'woocommerce_gateway' ===
				$payment_source &&
			! $order->is_paid() &&
			$order->needs_payment()
		) {
			$redirect =
				$order
					->get_checkout_payment_url();

			$flow =
				'gateway_handoff';

			$message =
				__(
					'This order has already been created. Continue to secure payment.',
					'eilmo-checkout-flow'
				);
		} elseif (
			$order->is_paid()
		) {
			$message =
				__(
					'This order has already been paid.',
					'eilmo-checkout-flow'
				);
		}

		$response =
			$this->build_success_response(
				$order,
				$result,
				$redirect,
				$flow
			);

		$response[
			'message'
		] =
			$message;

		$response[
			'idempotent_replay'
		] =
			true;

		/**
		 * Filters idempotent replay response.
		 *
		 * @param array<string,mixed> $response Response.
		 * @param WC_Order            $order    Order.
		 */
		$response =
			apply_filters(
				'eilmo_cf/orders/idempotent_response',
				$response,
				$order
			);

		if (
			! is_array(
				$response
			)
		) {
			$response =
				array(
					'result' =>
						'success',

					'message' =>
						$message,

					'order_id' =>
						$order->get_id(),

					'order_key' =>
						$order->get_order_key(),

					'redirect' =>
						esc_url_raw(
							$redirect
						),

					'idempotent_replay' =>
						true,
				);
		}

		wp_send_json_success(
			$response
		);
	}

	/**
	 * Build creator-like result from existing order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	private function build_existing_order_result(
		WC_Order $order
	): array {

		$payment_type =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_TYPE,
						true
					)
			);

		$payment_method =
			sanitize_text_field(
				(string) $order
					->get_meta(
						self::META_PAYMENT_METHOD,
						true
					)
			);

		$payment_method_key =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_METHOD_KEY,
						true
					)
			);

		$payment_source =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_SOURCE,
						true
					)
			);

		$gateway_id =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_GATEWAY_ID,
						true
					)
			);

		/*
		 * Only WooCommerce-gateway orders may fall back
		 * to the native order payment method.
		 *
		 * COD is an Eilmo manual Payment Option and must
		 * never be interpreted as a WooCommerce gateway.
		 */
		if (
			'' === $gateway_id &&
			'woocommerce_gateway' ===
				$payment_source
		) {
			$gateway_id =
				sanitize_key(
					(string) $order
						->get_payment_method()
				);
		}

		$pay_now =
			$this->get_order_amount(
				$order,
				self::META_PAY_NOW
			);

		$remaining_due =
			$this->get_order_amount(
				$order,
				self::META_REMAINING_DUE
			);

		$is_cash_on_delivery =
			'cash_on_delivery' ===
				$payment_type ||
			'cash_on_delivery' ===
				$payment_method_key;

		return array(
			'payment_type' =>
				$payment_type,

			'is_cash_on_delivery' =>
				$is_cash_on_delivery,

			'payment_method' =>
				$payment_method,

			'payment_method_key' =>
				$payment_method_key,

			'payment_source' =>
				$payment_source,

			'gateway_id' =>
				$gateway_id,

			/*
			 * Eilmo's authoritative Pay Now amount decides
			 * whether checkout-time payment is required.
			 *
			 * A COD WooCommerce order can still report
			 * needs_payment() because its order total is
			 * positive, even though Eilmo correctly has
			 * Pay Now = 0.
			 */
			'requires_payment' =>
				! $is_cash_on_delivery &&
				$pay_now > 0,

			'is_partial_payment' =>
				$pay_now > 0 &&
				$remaining_due > 0,

			'totals' =>
				array(
					'product_total' =>
						$this->get_order_amount(
							$order,
							self::META_PRODUCT_TOTAL
						),

					'automatic_discount' =>
						$this->get_order_amount(
							$order,
							self::META_AUTOMATIC_DISCOUNT
						),

					'coupon_discount' =>
						$this->get_order_amount(
							$order,
							self::META_COUPON_DISCOUNT
						),

					'delivery_charge' =>
						$this->get_order_amount(
							$order,
							self::META_DELIVERY_CHARGE
						),

					'full_payment_discount' =>
						$this->get_order_amount(
							$order,
							self::META_FULL_PAYMENT_DISCOUNT
						),

					'grand_total' =>
						$this->get_order_amount(
							$order,
							self::META_GRAND_TOTAL
						),

					'pay_now' =>
						$pay_now,

					'remaining_due' =>
						$remaining_due,
				),
		);
	}

	/**
	 * Get numeric Eilmo order meta.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $meta_key Meta key.
	 *
	 * @return float
	 */
	private function get_order_amount(
		WC_Order $order,
		string $meta_key
	): float {

		$value =
			$order->get_meta(
				$meta_key,
				true
			);

		if (
			! is_numeric(
				$value
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $value
		);
	}

	/**
	 * Determine whether order belongs to Eilmo.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function is_eilmo_order(
		WC_Order $order
	): bool {

		return (
			'eilmo-checkout-flow' ===
				(string) $order
					->get_meta(
						self::META_CREATED_VIA,
						true
					)
		);
	}

/**
 * Validate the combined quantity of every product
 * involved in the final authoritative purchase.
 *
 * Purchase contexts remain independent:
 *
 * - Normal products.
 * - Combo components.
 * - Order Bumps.
 *
 * Stock is not independent. WooCommerce can point
 * multiple purchasable products/variations to the
 * same stock-managed product ID. We therefore group
 * quantities by get_stock_managed_by_id() before
 * checking availability.
 *
 * @param array<string,mixed> $validated Validated checkout.
 *
 * @return true|WP_Error
 */
private function validate_combined_purchase_stock(
	array $validated
) {

	if (
		! function_exists(
			'wc_get_product'
		)
	) {
		return new WP_Error(
			'woocommerce_unavailable',
			__(
				'WooCommerce is not available.',
				'eilmo-checkout-flow'
			)
		);
	}

	$purchase_items =
		$this->collect_authoritative_purchase_items(
			$validated
		);

	if (
		empty(
			$purchase_items
		)
	) {
		return new WP_Error(
			'no_products_selected',
			__(
				'Please select at least one product.',
				'eilmo-checkout-flow'
			)
		);
	}

	/*
	 * Quantity grouped by the actual WooCommerce
	 * stock owner.
	 *
	 * This is important for variations which may use
	 * their parent product's shared stock.
	 */
	$stock_groups =
		array();

	/*
	 * Sold-individually quantities are also aggregated
	 * across Normal / Combo / Order Bump contexts.
	 */
	$sold_individually_groups =
		array();

	foreach (
		$purchase_items as
			$item
	) {

		if (
			! is_array(
				$item
			)
		) {
			continue;
		}

		$product_id =
			absint(
				$item[
					'product_id'
				] ??
					0
			);

		$variation_id =
			absint(
				$item[
					'variation_id'
				] ??
					0
			);

		$quantity =
			$this->normalize_stock_quantity(
				$item[
					'quantity'
				] ??
					0
			);

		if (
			$product_id <= 0 ||
			$quantity <= 0
		) {
			continue;
		}

		$product =
			wc_get_product(
				$variation_id > 0
					? $variation_id
					: $product_id
			);

		if (
			! $product
		) {
			return new WP_Error(
				'combined_invalid_product',
				__(
					'One of the selected products is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Reconfirm variation ownership after all
		 * module-specific validation.
		 */
		if (
			$variation_id > 0 &&
			(
				! $product->is_type(
					'variation'
				) ||
				absint(
					$product->get_parent_id()
				) !==
					$product_id
			)
		) {
			return new WP_Error(
				'combined_invalid_variation',
				__(
					'One of the selected product variations is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			! $product->is_purchasable()
		) {
			return new WP_Error(
				'combined_product_not_purchasable',
				sprintf(
					/* translators: %s: Product name. */
					__(
						'%s cannot currently be purchased.',
						'eilmo-checkout-flow'
					),
					$product->get_name()
				)
			);
		}

		if (
			! $product->is_in_stock()
		) {
			return new WP_Error(
				'combined_product_out_of_stock',
				sprintf(
					/* translators: %s: Product name. */
					__(
						'%s is currently out of stock.',
						'eilmo-checkout-flow'
					),
					$product->get_name()
				)
			);
		}

		/*
		 * -----------------------------------------
		 * Sold individually
		 * -----------------------------------------
		 */
		if (
			$product->is_sold_individually()
		) {
			$sold_key =
				sprintf(
					'%1$d:%2$d',
					$product_id,
					$variation_id
				);

			if (
				! isset(
					$sold_individually_groups[
						$sold_key
					]
				)
			) {
				$sold_individually_groups[
					$sold_key
				] =
					array(
						'product' =>
							$product,

						'quantity' =>
							0.0,
					);
			}

			$sold_individually_groups[
				$sold_key
			][
				'quantity'
			] +=
				$quantity;
		}

		/*
		 * -----------------------------------------
		 * Shared stock owner
		 * -----------------------------------------
		 *
		 * A variation may use parent-level stock.
		 * Therefore product/variation IDs alone are
		 * not sufficient for combined stock checks.
		 */
		$stock_managed_by_id =
			method_exists(
				$product,
				'get_stock_managed_by_id'
			)
				? absint(
					$product
						->get_stock_managed_by_id()
				)
				: absint(
					$product->get_id()
				);

		if (
			$stock_managed_by_id <= 0
		) {
			$stock_managed_by_id =
				absint(
					$product->get_id()
				);
		}

		if (
			! isset(
				$stock_groups[
					$stock_managed_by_id
				]
			)
		) {
			$stock_groups[
				$stock_managed_by_id
			] =
				array(
					'quantity' =>
						0.0,

					'product' =>
						$product,
				);
		}

		$stock_groups[
			$stock_managed_by_id
		][
			'quantity'
		] +=
			$quantity;
	}

	/*
	 * ---------------------------------------------
	 * Sold Individually final check
	 * ---------------------------------------------
	 */
	foreach (
		$sold_individually_groups as
			$group
	) {

		if (
			! is_array(
				$group
			)
		) {
			continue;
		}

		$quantity =
			$this->normalize_stock_quantity(
				$group[
					'quantity'
				] ??
					0
			);

		if (
			$quantity <= 1
		) {
			continue;
		}

		$product =
			$group[
				'product'
			] ??
				null;

		$product_name =
			is_object(
				$product
			) &&
			method_exists(
				$product,
				'get_name'
			)
				? sanitize_text_field(
					(string) $product
						->get_name()
				)
				: __(
					'This product',
					'eilmo-checkout-flow'
				);

		return new WP_Error(
			'combined_product_sold_individually',
			sprintf(
				/* translators: %s: Product name. */
				__(
					'Only one %s can be purchased per order.',
					'eilmo-checkout-flow'
				),
				$product_name
			)
		);
	}

	/*
	 * ---------------------------------------------
	 * Combined stock final check
	 * ---------------------------------------------
	 */
	foreach (
		$stock_groups as
			$stock_managed_by_id =>
				$group
	) {

		if (
			! is_array(
				$group
			)
		) {
			continue;
		}

		$quantity =
			$this->normalize_stock_quantity(
				$group[
					'quantity'
				] ??
					0
			);

		if (
			$quantity <= 0
		) {
			continue;
		}

		$stock_product =
			wc_get_product(
				absint(
					$stock_managed_by_id
				)
			);

		/*
		 * Fallback to the purchasable product object
		 * if the stock owner cannot be loaded.
		 */
		if (
			! $stock_product
		) {
			$stock_product =
				$group[
					'product'
				] ??
					null;
		}

		if (
			! is_object(
				$stock_product
			) ||
			! method_exists(
				$stock_product,
				'has_enough_stock'
			)
		) {
			return new WP_Error(
				'combined_stock_product_invalid',
				__(
					'Product stock could not be validated. Please try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * WooCommerce handles stock management and
		 * backorder allowance inside has_enough_stock().
		 */
		if (
			! $stock_product
				->has_enough_stock(
					$quantity
				)
		) {
			$product_name =
				method_exists(
					$stock_product,
					'get_name'
				)
					? sanitize_text_field(
						(string) $stock_product
							->get_name()
					)
					: __(
						'this product',
						'eilmo-checkout-flow'
					);

			$stock_quantity =
				method_exists(
					$stock_product,
					'get_stock_quantity'
				)
					? $stock_product
						->get_stock_quantity()
					: null;

			if (
				is_numeric(
					$stock_quantity
				)
			) {
				return new WP_Error(
					'combined_insufficient_stock',
					sprintf(
						/* translators: 1: Product name. 2: Available stock quantity. */
						__(
							'There is not enough stock available for %1$s. Only %2$s is currently available.',
							'eilmo-checkout-flow'
						),
						$product_name,
						(string) $stock_quantity
					)
				);
			}

			return new WP_Error(
				'combined_insufficient_stock',
				sprintf(
					/* translators: %s: Product name. */
					__(
						'There is not enough stock available for %s.',
						'eilmo-checkout-flow'
					),
					$product_name
				)
			);
		}
	}

	return true;
}

    /**
     * Collect every authoritative product quantity from
     * the final validated checkout.
     *
     * Only server-validated structures are used:
     *
     * - validated normal products.
     * - validated Combo Offers.
     * - resolved Order Bumps.
     *
     * @param array<string,mixed> $validated Validated checkout.
     *
     * @return array<int, array<string,mixed>>
     */
    private function collect_authoritative_purchase_items(
        array $validated
    ): array {

        $items =
            array();

        /*
        * ---------------------------------------------
        * Normal products
        * ---------------------------------------------
        */
        $normal_items =
            isset(
                $validated[
                    'items'
                ]
            ) &&
            is_array(
                $validated[
                    'items'
                ]
            )
                ? $validated[
                    'items'
                ]
                : array();

        foreach (
            $normal_items as
                $item
        ) {
            $this->append_authoritative_purchase_item(
                $items,
                $item,
                'normal'
            );
        }

        /*
        * ---------------------------------------------
        * Combo components
        * ---------------------------------------------
        */
        $combo_data =
            isset(
                $validated[
                    'combo_offers'
                ]
            ) &&
            is_array(
                $validated[
                    'combo_offers'
                ]
            )
                ? $validated[
                    'combo_offers'
                ]
                : array();

        $combo_offers =
            isset(
                $combo_data[
                    'offers'
                ]
            ) &&
            is_array(
                $combo_data[
                    'offers'
                ]
            )
                ? $combo_data[
                    'offers'
                ]
                : array();

        foreach (
            $combo_offers as
                $offer
        ) {

            if (
                ! is_array(
                    $offer
                )
            ) {
                continue;
            }

            $combo_items =
                isset(
                    $offer[
                        'items'
                    ]
                ) &&
                is_array(
                    $offer[
                        'items'
                    ]
                )
                    ? $offer[
                        'items'
                    ]
                    : array();

            foreach (
                $combo_items as
                    $item
            ) {
                $this->append_authoritative_purchase_item(
                    $items,
                    $item,
                    'combo'
                );
            }
        }

        /*
        * ---------------------------------------------
        * Order Bumps
        * ---------------------------------------------
        *
        * Each resolved Order Bump directly contains its
        * authoritative product / variation / quantity.
        */
        $order_bump_data =
            isset(
                $validated[
                    'order_bumps'
                ]
            ) &&
            is_array(
                $validated[
                    'order_bumps'
                ]
            )
                ? $validated[
                    'order_bumps'
                ]
                : array();

        $order_bump_offers =
            isset(
                $order_bump_data[
                    'offers'
                ]
            ) &&
            is_array(
                $order_bump_data[
                    'offers'
                ]
            )
                ? $order_bump_data[
                    'offers'
                ]
                : array();

        foreach (
            $order_bump_offers as
                $offer
        ) {
            $this->append_authoritative_purchase_item(
                $items,
                $offer,
                'order_bump'
            );
        }

        return array_values(
            $items
        );
    }

    /**
     * Append one authoritative purchase item.
     *
     * @param array<int, array<string,mixed>> $items   Items.
     * @param mixed                           $item    Item.
     * @param string                          $context Context.
     *
     * @return void
     */
    private function append_authoritative_purchase_item(
        array &$items,
        $item,
        string $context
    ): void {

        if (
            ! is_array(
                $item
            )
        ) {
            return;
        }

        $product_id =
            absint(
                $item[
                    'product_id'
                ] ??
                    0
            );

        $variation_id =
            absint(
                $item[
                    'variation_id'
                ] ??
                    0
            );

        $quantity =
            $this->normalize_stock_quantity(
                $item[
                    'quantity'
                ] ??
                    0
            );

        if (
            $product_id <= 0 ||
            $quantity <= 0
        ) {
            return;
        }

        $items[] =
            array(
                'product_id' =>
                    $product_id,

                'variation_id' =>
                    $variation_id,

                'quantity' =>
                    $quantity,

                'context' =>
                    sanitize_key(
                        $context
                    ),
            );
    }

    /**
     * Normalize stock quantity.
     *
     * @param mixed $quantity Quantity.
     *
     * @return float
     */
    private function normalize_stock_quantity(
        $quantity
    ): float {

        if (
            function_exists(
                'wc_stock_amount'
            )
        ) {
            $quantity =
                wc_stock_amount(
                    $quantity
                );
        }

        if (
            ! is_numeric(
                $quantity
            )
        ) {
            return 0.0;
        }

        return max(
            0.0,
            (float) $quantity
        );
    }
	/**
	 * Validate selected WooCommerce gateway.
	 *
	 * @param array<string,mixed> $validated Validated checkout.
	 *
	 * @return true|WP_Error
	 */
	private function validate_gateway_selection(
		array $validated
	) {

		$advance_payment =
			isset(
				$validated[
					'advance_payment'
				]
			) &&
			is_array(
				$validated[
					'advance_payment'
				]
			)
				? $validated[
					'advance_payment'
				]
				: array();

		$payment_type =
			sanitize_key(
				(string) (
					$advance_payment[
						'payment_type'
					] ??
						''
				)
			);

		$payment =
			isset(
				$validated[
					'payment'
				]
			) &&
			is_array(
				$validated[
					'payment'
				]
			)
				? $validated[
					'payment'
				]
				: array();

		$method =
			sanitize_text_field(
				(string) (
					$payment[
						'method'
					] ??
						''
				)
			);

		$method_key =
			sanitize_key(
				(string) (
					$payment[
						'method_key'
					] ??
						''
				)
			);

		$source =
			sanitize_key(
				(string) (
					$payment[
						'source'
					] ??
						''
				)
			);

		$gateway_id =
			sanitize_key(
				(string) (
					$payment[
						'gateway_id'
					] ??
						''
				)
			);

		/*
		 * Cash on Delivery is not a WooCommerce gateway
		 * selection in Eilmo. OrderValidator must already
		 * have normalized it to the internal manual method.
		 */
		if (
			'cash_on_delivery' ===
				$payment_type
		) {
			/*
			 * COD is a top-level Eilmo payment option.
			 * Do not reject the checkout because a stale frontend
			 * payload contains an old WooCommerce gateway value.
			 * The authoritative COD normalization happens later.
			 */
			return true;
		}

		if (
			'woocommerce_gateway' !==
				$source
		) {
			return true;
		}

		if (
			'' === $gateway_id
		) {
			return new WP_Error(
				'invalid_payment_gateway',
				__(
					'The selected payment gateway is invalid.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * Native WooCommerce COD is never a Payment Method
		 * for Advance or Full Payment.
		 */
		if (
			'cod' ===
				$gateway_id ||
			'wc__cod' ===
				$method
		) {
			return new WP_Error(
				'cod_gateway_not_allowed',
				__(
					'Cash on Delivery cannot be used with Advance or Full Payment.',
					'eilmo-checkout-flow'
				)
			);
		}

		$customer =
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
				: array();

		$this->sync_woocommerce_customer(
			$customer,
			$gateway_id
		);

		$gateway =
			$this->get_available_gateway(
				$gateway_id
			);

		if (
			! $gateway
		) {
			return new WP_Error(
				'payment_gateway_unavailable',
				__(
					'The selected payment gateway is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		return true;
	}

	/**
	 * Hand native WooCommerce gateway to Order Pay.
	 *
	 * @param WC_Order             $order     Order.
	 * @param array<string,mixed>  $result    Creator result.
	 * @param array<string,mixed>  $validated Validated checkout.
	 *
	 * @return void
	 */
	private function handoff_woocommerce_gateway(
		WC_Order $order,
		array $result,
		array $validated
	): void {

		$gateway_id =
			sanitize_key(
				(string) (
					$result[
						'gateway_id'
					] ??
						''
				)
			);

		if (
			'' ===
				$gateway_id ||
			'cod' ===
				$gateway_id
		) {
			$this->delete_unprocessed_order(
				$order
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_payment_gateway',

					'message' =>
						__(
							'The selected payment gateway is invalid.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$customer =
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
				: array();

		$this->sync_woocommerce_customer(
			$customer,
			$gateway_id
		);

		$gateway =
			$this->get_available_gateway(
				$gateway_id
			);

		if (
			! $gateway
		) {
			$this->delete_unprocessed_order(
				$order
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'payment_gateway_unavailable',

					'message' =>
						__(
							'The selected payment gateway is no longer available.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		if (
			method_exists(
				$order,
				'set_payment_method'
			)
		) {
			$order->set_payment_method(
				$gateway
			);
		}

		$order->update_meta_data(
			self::PAYMENT_STATUS_META,
			'unpaid'
		);

		$order->add_order_note(
			sprintf(
				/* translators: %s: payment gateway title. */
				__(
					'Awaiting payment through %s. Customer was sent to the WooCommerce secure payment flow.',
					'eilmo-checkout-flow'
				),
				sanitize_text_field(
					(string) (
						method_exists(
							$gateway,
							'get_title'
						)
							? $gateway->get_title()
							: $gateway_id
					)
				)
			)
		);

		$order->save();

		if (
			function_exists(
				'WC'
			) &&
			WC() &&
			WC()->session
		) {
			WC()->session->set(
				'order_awaiting_payment',
				$order->get_id()
			);

			WC()->session->set(
				'chosen_payment_method',
				$gateway_id
			);

			if (
				method_exists(
					WC()->session,
					'save_data'
				)
			) {
				WC()->session
					->save_data();
			}
		}

		$redirect =
			$order
				->get_checkout_payment_url();

		/**
		 * Filters gateway handoff URL.
		 *
		 * @param string              $redirect Payment URL.
		 * @param WC_Order            $order    Order.
		 * @param object              $gateway  Gateway.
		 * @param array<string,mixed> $result   Result.
		 */
		$redirect =
			(string) apply_filters(
				'eilmo_cf/orders/gateway_payment_url',
				$redirect,
				$order,
				$gateway,
				$result
			);

		$redirect =
			esc_url_raw(
				$redirect
			);

		if (
			'' ===
				$redirect
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'payment_url_unavailable',

					'message' =>
						__(
							'The secure payment page could not be generated. Please try again.',
							'eilmo-checkout-flow'
						),

					'order_id' =>
						$order->get_id(),

					'order_key' =>
						$order->get_order_key(),

					'retry_url' =>
						$order
							->get_checkout_payment_url(),
				),
				500
			);
		}

		do_action(
			'eilmo_cf/orders/gateway_handoff',
			$order,
			$gateway,
			$result
		);

		wp_send_json_success(
			$this->build_success_response(
				$order,
				$result,
				$redirect,
				'gateway_handoff'
			)
		);
	}

	/**
	 * Process Eilmo manual payment.
	 *
	 * @param WC_Order             $order  Order.
	 * @param array<string,mixed>  $result Creator result.
	 *
	 * @return void
	 */
	private function process_manual_payment(
		WC_Order $order,
		array $result
	): void {

		$method_key =
			sanitize_key(
				(string) (
					$result[
						'payment_method_key'
					] ??
						''
				)
			);

		if (
			$this->is_cash_on_delivery_result(
				$result
			)
		) {
			$status =
				$order->has_downloadable_item()
					? 'on-hold'
					: 'processing';

			$status =
				sanitize_key(
					(string) apply_filters(
						'eilmo_cf/orders/cod_status',
						$status,
						$order
					)
				);

			if (
				'' ===
					$status
			) {
				$status =
					'processing';
			}

			$order->update_meta_data(
				self::PAYMENT_STATUS_META,
				'pay_on_delivery'
			);

			$order->update_status(
				$status,
				__(
					'Payment will be collected on delivery.',
					'eilmo-checkout-flow'
				)
			);

			$order->save();

			$this->send_manual_success(
				$order,
				$result
			);
		}

		/*
		 * A non-COD manual payment must have a valid
		 * normalized method key. This is defensive;
		 * OrderValidator and OrderCreator already enforce
		 * the authoritative payment selection.
		 */
		if (
			'' ===
				$method_key
		) {
			$this->delete_unprocessed_order(
				$order
			);

			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_manual_payment',

					'message' =>
						__(
							'The selected payment method is invalid.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$order->update_meta_data(
			self::PAYMENT_STATUS_META,
			'awaiting_verification'
		);

		$order->update_status(
			'on-hold',
			__(
				'Awaiting manual payment verification.',
				'eilmo-checkout-flow'
			)
		);

		$order->save();

		$this->send_manual_success(
			$order,
			$result
		);
	}


	/**
	 * Determine whether creator/existing-order result is
	 * a Cash on Delivery checkout.
	 *
	 * Payment type is authoritative. Method-key fallback
	 * keeps the check compatible with older Eilmo orders.
	 *
	 * @param array<string, mixed> $result Result.
	 *
	 * @return bool
	 */
	private function is_cash_on_delivery_result(
		array $result
	): bool {

		$payment_type =
			sanitize_key(
				(string) (
					$result[
						'payment_type'
					] ??
						''
				)
			);

		if (
			'cash_on_delivery' ===
				$payment_type
		) {
			return true;
		}

		$method_key =
			sanitize_key(
				(string) (
					$result[
						'payment_method_key'
					] ??
						''
				)
			);

		return (
			'cash_on_delivery' ===
				$method_key
		);
	}

	/**
	 * Complete order where payment is not required.
	 *
	 * @param WC_Order             $order  Order.
	 * @param array<string,mixed>  $result Result.
	 *
	 * @return void
	 */
	private function complete_without_payment(
		WC_Order $order,
		array $result
	): void {

		/*
		 * COD is not a free / zero-payment order. The
		 * checkout-time Pay Now amount is zero only because
		 * the complete balance is collected on delivery.
		 */
		if (
			$this->is_cash_on_delivery_result(
				$result
			)
		) {
			$this->process_manual_payment(
				$order,
				$result
			);
		}

		$status =
			$order->needs_processing()
				? 'processing'
				: 'completed';

		$order->update_meta_data(
			self::PAYMENT_STATUS_META,
			'not_required'
		);

		$order->update_status(
			$status,
			__(
				'No payment is required for this order.',
				'eilmo-checkout-flow'
			)
		);

		$order->save();

		wp_send_json_success(
			$this->build_success_response(
				$order,
				$result,
				$order
					->get_checkout_order_received_url(),
				'no_payment'
			)
		);
	}

	/**
	 * Send manual-payment success response.
	 *
	 * @param WC_Order             $order  Order.
	 * @param array<string,mixed>  $result Result.
	 *
	 * @return void
	 */
	private function send_manual_success(
		WC_Order $order,
		array $result
	): void {

		do_action(
			'eilmo_cf/orders/manual_processed',
			$order,
			$result
		);

		$flow =
			$this->is_cash_on_delivery_result(
				$result
			)
				? 'cash_on_delivery'
				: 'manual';

		wp_send_json_success(
			$this->build_success_response(
				$order,
				$result,
				$order
					->get_checkout_order_received_url(),
				$flow
			)
		);
	}

	/**
	 * Get available WooCommerce gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return object|null
	 */
	private function get_available_gateway(
		string $gateway_id
	) {

		if (
			'' ===
				$gateway_id ||
			! function_exists(
				'WC'
			) ||
			! WC() ||
			! WC()->payment_gateways()
		) {
			return null;
		}

		try {

			$gateways =
				WC()
					->payment_gateways()
					->get_available_payment_gateways();

		} catch ( \Throwable $throwable ) {

			return null;
		}

		if (
			! is_array(
				$gateways
			) ||
			! isset(
				$gateways[
					$gateway_id
				]
			) ||
			! is_object(
				$gateways[
					$gateway_id
				]
			)
		) {
			return null;
		}

		return $gateways[
			$gateway_id
		];
	}

	/**
	 * Sync customer into WooCommerce session.
	 *
	 * @param array<string,mixed> $customer   Customer.
	 * @param string              $gateway_id Gateway ID.
	 *
	 * @return void
	 */
	private function sync_woocommerce_customer(
		array $customer,
		string $gateway_id
	): void {

		if (
			! function_exists(
				'WC'
			) ||
			! WC()
		) {
			return;
		}

		$wc_customer =
			WC()->customer;

		if (
			$wc_customer
		) {
			$field_map =
				array(
					'first_name' =>
						'billing_first_name',

					'last_name' =>
						'billing_last_name',

					'company' =>
						'billing_company',

					'email' =>
						'billing_email',

					'phone' =>
						'billing_phone',

					'address_1' =>
						'billing_address_1',

					'address_2' =>
						'billing_address_2',

					'city' =>
						'billing_city',

					'state' =>
						'billing_state',

					'postcode' =>
						'billing_postcode',

					'country' =>
						'billing_country',
				);

			foreach (
				$field_map as
					$wc_field =>
					$customer_field
			) {
				$value =
					(string) (
						$customer[
							$customer_field
						] ??
							''
					);

				$billing_setter =
					'set_billing_' .
						$wc_field;

				if (
					is_callable(
						array(
							$wc_customer,
							$billing_setter,
						)
					)
				) {
					$wc_customer
						->{$billing_setter}(
							$value
						);
				}

				$shipping_setter =
					'set_shipping_' .
						$wc_field;

				if (
					! in_array(
						$wc_field,
						array(
							'email',
							'phone',
						),
						true
					) &&
					is_callable(
						array(
							$wc_customer,
							$shipping_setter,
						)
					)
				) {
					$wc_customer
						->{$shipping_setter}(
							$value
						);
				}
			}

			try {

				$wc_customer->save();

			} catch ( \Throwable $throwable ) {

				/*
				 * Session synchronization is optional.
				 */
			}
		}

		if (
			WC()->session
		) {
			WC()->session->set(
				'chosen_payment_method',
				$gateway_id
			);
		}
	}

	/**
	 * Build WooCommerce-compatible checkout data.
	 *
	 * @param array<string,mixed> $validated Validated checkout.
	 * @param array<string,mixed> $payload   Raw payload.
	 *
	 * @return array<string,mixed>
	 */
	private function build_woocommerce_posted_data(
		array $validated,
		array $payload
	): array {

		$customer =
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
				: array();

		$payment =
			isset(
				$validated[
					'payment'
				]
			) &&
			is_array(
				$validated[
					'payment'
				]
			)
				? $validated[
					'payment'
				]
				: array();

		$delivery =
			isset(
				$validated[
					'delivery'
				]
			) &&
			is_array(
				$validated[
					'delivery'
				]
			)
				? $validated[
					'delivery'
				]
				: array();

		$gateway_id =
			sanitize_key(
				(string) (
					$payment[
						'gateway_id'
					] ??
						''
				)
			);

		$data =
			array_merge(
				$customer,
				array(
					'payment_method' =>
						'' !==
							$gateway_id
								? $gateway_id
								: sanitize_text_field(
									(string) (
										$payment[
											'method'
										] ??
											''
									)
								),

					'shipping_method' =>
						array(
							sanitize_key(
								(string) (
									$delivery[
										'method_id'
									] ??
										''
								)
							),
						),

					'order_comments' =>
						sanitize_textarea_field(
							(string) (
								$customer[
									'order_comments'
								] ??
									''
							)
						),
				)
			);

		$data =
			apply_filters(
				'eilmo_cf/orders/woocommerce_posted_data',
				$data,
				$validated,
				$payload
			);

		return is_array(
			$data
		)
			? $data
			: array();
	}

	/**
	 * Validate the normal-product request envelope.
	 *
	 * This is deliberately transport validation only. The
	 * authoritative OrderValidator still reloads every Simple
	 * product or exact variation from WooCommerce, groups
	 * duplicates and validates stock.
	 *
	 * @param array<string,mixed> $payload Decoded request payload.
	 *
	 * @return true|WP_Error
	 */
	private function validate_request_payload_envelope(
		array $payload
	) {

		if (
			! array_key_exists(
				'items',
				$payload
			)
		) {
			return true;
		}

		$items =
			$payload[
				'items'
			];

		if (
			! is_array(
				$items
			) ||
			! $this->is_list_array(
				$items
			)
		) {
			return new WP_Error(
				'invalid_product_items',
				__(
					'Invalid product selection data was received.',
					'eilmo-checkout-flow'
				)
			);
		}

		/**
		 * Filters the maximum number of normal product rows accepted
		 * from one browser request.
		 *
		 * @param int $maximum Maximum row count.
		 */
		$maximum =
			(int) apply_filters(
				'eilmo_cf/orders/max_normal_item_rows',
				self::MAX_NORMAL_ITEM_ROWS
			);

		$maximum =
			max(
				1,
				min(
					1000,
					$maximum
				)
			);

		if (
			count(
				$items
			) > $maximum
		) {
			return new WP_Error(
				'too_many_product_items',
				__(
					'Too many products were selected. Please reduce the selection and try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		foreach ( $items as $item ) {

			if (
				! is_array(
					$item
				) ||
				! $this->is_transport_integer(
					$item[
						'product_id'
					] ?? null,
					false
				) ||
				! $this->is_transport_integer(
					$item[
						'variation_id'
					] ?? 0,
					true
				) ||
				! $this->is_transport_quantity(
					$item[
						'quantity'
					] ?? null
				)
			) {
				return new WP_Error(
					'invalid_product_item',
					__(
						'One of the selected product entries is invalid.',
						'eilmo-checkout-flow'
					)
				);
			}
		}

		return true;
	}

	/**
	 * Determine whether an array uses JSON-list keys.
	 *
	 * PHP 7.4-compatible list-key check.
	 *
	 * @param array<mixed> $value Array.
	 *
	 * @return bool
	 */
	private function is_list_array(
		array $value
	): bool {

		if ( array() === $value ) {
			return true;
		}

		return array_keys(
			$value
		) === range(
			0,
			count( $value ) - 1
		);
	}

	/**
	 * Validate a product or variation ID transport value.
	 *
	 * @param mixed $value      Value.
	 * @param bool  $allow_zero Whether zero is valid.
	 *
	 * @return bool
	 */
	private function is_transport_integer(
		$value,
		bool $allow_zero
	): bool {

		if ( is_int( $value ) ) {
			return $allow_zero
				? $value >= 0
				: $value > 0;
		}

		if (
			! is_string(
				$value
			)
		) {
			return false;
		}

		$value =
			trim(
				$value
			);

		if (
			'' === $value ||
			1 !== preg_match(
				'/^[0-9]+$/',
				$value
			)
		) {
			return false;
		}

		$integer =
			(int) $value;

		return $allow_zero
			? $integer >= 0
			: $integer > 0;
	}

	/**
	 * Validate a quantity transport value.
	 *
	 * WooCommerce may support decimal stock quantities, so this
	 * guard accepts finite positive numeric values and leaves
	 * store-specific normalization to OrderValidator.
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	private function is_transport_quantity(
		$value
	): bool {

		if (
			! is_int( $value ) &&
			! is_float( $value ) &&
			! is_string( $value )
		) {
			return false;
		}

		if (
			! is_numeric(
				$value
			)
		) {
			return false;
		}

		$quantity =
			(float) $value;

		return is_finite(
			$quantity
		) && $quantity > 0;
	}

	/**
	 * Get raw request payload.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function get_request_payload() {

		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'payload'
				]
			)
		) {
			return new WP_Error(
				'missing_payload',
				__(
					'Checkout data is missing.',
					'eilmo-checkout-flow'
				)
			);
		}

		$raw =
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				(string) $_POST[
					'payload'
				]
			);

		if (
			'' ===
				trim(
					$raw
				)
		) {
			return new WP_Error(
				'empty_payload',
				__(
					'Checkout data is empty.',
					'eilmo-checkout-flow'
				)
			);
		}

		/**
		 * Filters the maximum encoded checkout request size.
		 *
		 * @param int $maximum_bytes Maximum bytes.
		 */
		$maximum_bytes =
			(int) apply_filters(
				'eilmo_cf/orders/max_request_payload_bytes',
				self::MAX_REQUEST_PAYLOAD_BYTES
			);

		$maximum_bytes =
			max(
				65536,
				min(
					5242880,
					$maximum_bytes
				)
			);

		if (
			strlen(
				$raw
			) > $maximum_bytes
		) {
			return new WP_Error(
				'payload_too_large',
				__(
					'The checkout request is too large. Please reduce the product selection and try again.',
					'eilmo-checkout-flow'
				)
			);
		}

		$payload =
			json_decode(
				$raw,
				true,
				64
			);

		if (
			JSON_ERROR_NONE !==
				json_last_error() ||
			! is_array(
				$payload
			)
		) {
			return new WP_Error(
				'invalid_payload',
				__(
					'Invalid checkout data was received.',
					'eilmo-checkout-flow'
				)
			);
		}

		return $payload;
	}

	/**
	 * Get created order.
	 *
	 * @param array<string,mixed> $result Creator result.
	 *
	 * @return WC_Order|null
	 */
	private function get_created_order(
		array $result
	): ?WC_Order {

		if (
			isset(
				$result[
					'order'
				]
			) &&
			$result[
				'order'
			] instanceof WC_Order
		) {
			return $result[
				'order'
			];
		}

		$order_id =
			absint(
				$result[
					'order_id'
				] ??
					0
			);

		if (
			$order_id <= 0
		) {
			return null;
		}

		$order =
			wc_get_order(
				$order_id
			);

		return $order instanceof WC_Order
			? $order
			: null;
	}

	/**
	 * Build success response.
	 *
	 * @param WC_Order             $order    Order.
	 * @param array<string,mixed>  $result   Creator result.
	 * @param string               $redirect Redirect.
	 * @param string               $flow     Payment flow.
	 *
	 * @return array<string,mixed>
	 */
	private function build_success_response(
		WC_Order $order,
		array $result,
		string $redirect,
		string $flow
	): array {

		if (
			'gateway_handoff' ===
				$flow
		) {
			$message =
				__(
					'Your order has been created. Continue to secure payment.',
					'eilmo-checkout-flow'
				);
		} elseif (
			'cash_on_delivery' ===
				$flow
		) {
			$message =
				__(
					'Your Cash on Delivery order has been placed successfully.',
					'eilmo-checkout-flow'
				);
		} else {
			$message =
				__(
					'Your order has been placed successfully.',
					'eilmo-checkout-flow'
				);
		}

		return array(
			'result' =>
				'success',

			'message' =>
				$message,

			'order_id' =>
				$order->get_id(),

			'order_key' =>
				$order->get_order_key(),

			'order_number' =>
				$order->get_order_number(),

			'order_status' =>
				$order->get_status(),

			'payment_flow' =>
				$flow,

			'payment_type' =>
				sanitize_key(
					(string) (
						$result[
							'payment_type'
						] ??
							''
					)
				),

			'payment_method' =>
				sanitize_text_field(
					(string) (
						$result[
							'payment_method'
						] ??
							''
					)
				),

			'payment_method_key' =>
				sanitize_key(
					(string) (
						$result[
							'payment_method_key'
						] ??
							''
					)
				),

			'payment_source' =>
				sanitize_key(
					(string) (
						$result[
							'payment_source'
						] ??
							''
					)
				),

			'gateway_id' =>
				sanitize_key(
					(string) (
						$result[
							'gateway_id'
						] ??
							''
					)
				),

			'redirect' =>
				esc_url_raw(
					$redirect
				),

			'totals' =>
				isset(
					$result[
						'totals'
					]
				) &&
				is_array(
					$result[
						'totals'
					]
				)
					? $result[
						'totals'
					]
					: array(),
		);
	}

	/**
	 * Send WP_Error JSON response.
	 *
	 * @param WP_Error             $error      Error.
	 * @param int                  $status     HTTP status.
	 * @param array<string,mixed>  $extra_data Extra response data.
	 *
	 * @return void
	 */
	private function send_error(
		WP_Error $error,
		int $status = 400,
		array $extra_data = array()
	): void {

		$codes =
			$error->get_error_codes();

		$error_code =
			! empty(
				$codes
			)
				? sanitize_key(
					(string) $codes[0]
				)
				: 'checkout_error';

		$messages =
			array_values(
				array_filter(
					array_map(
						'sanitize_text_field',
						$error
							->get_error_messages()
					)
				)
			);

		$message =
			! empty(
				$messages
			)
				? $messages[0]
				: SecuritySettings::get_message(
					'checkout_request_failed',
					__(
						'Something went wrong. Please try again.',
						'eilmo-checkout-flow'
					)
				);

		wp_send_json_error(
			array_merge(
				array(
					'error_code' =>
						$error_code,

					'message' =>
						$message,

					'errors' =>
						$messages,
				),
				$extra_data
			),
			$status
		);
	}

	/**
	 * Determine whether Eilmo Checkout Flow is enabled.
	 *
	 * @return bool
	 */
	private function is_checkout_enabled(): bool {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$default_enabled =
			(string) (
				$defaults[
					'general'
				][
					'enabled'
				] ??
					'yes'
			);

		if (
			! is_array(
				$stored
			) ||
			! isset(
				$stored[
					'general'
				]
			) ||
			! is_array(
				$stored[
					'general'
				]
			)
		) {
			return (
				'yes' ===
					$default_enabled
			);
		}

		return (
			'yes' ===
				(
					$stored[
						'general'
					][
						'enabled'
					] ??
						$default_enabled
				)
		);
	}

	/**
	 * Delete an order before payment processing.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	private function delete_unprocessed_order(
		WC_Order $order
	): void {

		try {

			if (
				function_exists(
					'wc_release_stock_for_order'
				)
			) {
				wc_release_stock_for_order(
					$order
				);
			}

			if (
				function_exists(
					'wc_release_coupons_for_order'
				)
			) {
				wc_release_coupons_for_order(
					$order
				);
			}

			if (
				$order->get_id() > 0
			) {
				$order->delete(
					true
				);
			}

		} catch ( \Throwable $throwable ) {

			/*
			 * Cleanup failure must not replace
			 * the original checkout error.
			 */
		}
	}
}
