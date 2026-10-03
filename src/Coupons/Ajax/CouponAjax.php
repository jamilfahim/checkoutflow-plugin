<?php
/**
 * Coupon AJAX handler.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons\Ajax;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\ComboOffers\Services\ComboOfferValidator;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Coupons\Services\CouponCalculator;
use EilmoCheckout\Coupons\Services\CustomerIdentity;
use EilmoCheckout\Discounts\Services\AutomaticDiscountCalculator;
use EilmoCheckout\Discounts\Services\SpecialDiscountContext;
use EilmoCheckout\Discounts\Services\SpecialDiscountEligibility;
use EilmoCheckout\OrderBumps\Services\OrderBumpResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend coupon validation.
 */
final class CouponAjax implements RegistrableInterface {

	/**
	 * AJAX action.
	 *
	 * @var string
	 */
	public const ACTION = 'eilmo_cf_apply_coupon';

	/**
	 * Frontend nonce action.
	 *
	 * Must match Assets::register_frontend_config().
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'eilmo_cf_frontend';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'wp_ajax_' . self::ACTION,
			array(
				$this,
				'apply_coupon',
			)
		);

		add_action(
			'wp_ajax_nopriv_' . self::ACTION,
			array(
				$this,
				'apply_coupon',
			)
		);
	}

	/**
	 * Apply / validate coupon.
	 *
	 * @return void
	 */
	public function apply_coupon(): void {

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

		$code =
			$this->get_coupon_code();

		if ( '' === $code ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'empty_coupon',

					'message' =>
						__(
							'Please enter a coupon code.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$items =
			$this->get_request_items();

		if ( empty( $items ) ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'empty_order',

					'message' =>
						__(
							'Please select a product before applying a coupon.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * --------------------------------------------------
		 * Normal Product context
		 * --------------------------------------------------
		 *
		 * Normal product IDs and quantities come from the
		 * frontend, but all prices are reloaded from
		 * WooCommerce.
		 */
		$normal_product_context =
			$this->build_product_context(
				$items
			);

		$normal_product_total =
			$this->normalize_amount(
				$normal_product_context[
					'product_total'
				] ??
					0
			);

		if ( $normal_product_total <= 0 ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'empty_order',

					'message' =>
						__(
							'Please select a product before applying a coupon.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * --------------------------------------------------
		 * Authoritative Combo Offer validation
		 * --------------------------------------------------
		 *
		 * The browser supplies only selected Combo IDs
		 * plus the signed checkout context.
		 *
		 * Combo products, pricing and discount
		 * compatibility are reloaded and recalculated
		 * server-side.
		 */
		$combo_request =
			$this->get_request_combo_offers();

		$combo_validator =
			new ComboOfferValidator();

		$validated_combo_offers =
			$combo_validator->validate(
				$combo_request
			);

		if (
			is_wp_error(
				$validated_combo_offers
			)
		) {
			$this->send_service_error(
				$validated_combo_offers,
				400
			);
		}

		if (
			! is_array(
				$validated_combo_offers
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_combo_result',

					'message' =>
						__(
							'Combo Offer validation failed. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * --------------------------------------------------
		 * Authoritative Order Bump validation
		 * --------------------------------------------------
		 *
		 * The browser supplies only selected Order Bump
		 * IDs. Product, quantity, pricing and discount
		 * compatibility are resolved from saved settings.
		 */
		$order_bump_request =
			$this->get_request_order_bumps();

		$special_discount_context =
			isset( $order_bump_request['context'] ) && is_array( $order_bump_request['context'] )
				? $order_bump_request['context']
				: array();
		$special_discount_context_service = new SpecialDiscountContext();

		if ( ! $special_discount_context_service->verify( $special_discount_context ) ) {
			wp_send_json_error(
				array(
					'error_code' => 'invalid_special_discount_context',
					'message' => __( 'The Special Discount selection is invalid. Please refresh the page and try again.', 'eilmo-checkout-flow' ),
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
				wp_send_json_error(
					array(
						'error_code' => 'special_discount_not_assigned',
						'message' => __( 'One of the selected Special Discounts is not available in this checkout.', 'eilmo-checkout-flow' ),
					),
					400
				);
			}
		}

		$order_bump_resolver =
			new OrderBumpResolver();

		$validated_order_bumps =
			$order_bump_resolver
				->resolve_selected(
					$selected_order_bump_ids,
					array_merge(
						( new SpecialDiscountEligibility() )->build_context( $items, $validated_combo_offers ),
						array(
							'special_discount_scope' => $special_discount_context_service->get_scope( $special_discount_context ),
							'special_discount_ids' => $special_discount_context_service->get_allowed_ids( $special_discount_context ),
							'current_checkout_product_ids' => array_values( array_unique( array_filter( array_map( static function ( $item ) { return is_array( $item ) ? absint( $item['product_id'] ?? 0 ) : 0; }, $items ) ) ) ),
						)
					)
				);

		if (
			is_wp_error(
				$validated_order_bumps
			)
		) {
			$this->send_service_error(
				$validated_order_bumps,
				400
			);
		}

		if (
			! is_array(
				$validated_order_bumps
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_order_bump_result',

					'message' =>
						__(
							'Order Bump validation failed. Please refresh the page and try again.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * Build one source-aware checkout preview.
		 *
		 * Coupon eligibility:
		 *
		 * Normal Product:
		 * - Always eligible.
		 *
		 * Combo Offer:
		 * - Controlled by apply_coupon.
		 *
		 * Order Bump:
		 * - Controlled by apply_coupon.
		 *
		 * Automatic Discount eligibility is calculated
		 * separately from apply_automatic_discount.
		 */
		$plugin_settings =
			$this->get_plugin_settings();

		$purchase_context =
			$this->build_discount_purchase_context(
				$normal_product_context,
				$validated_combo_offers,
				$validated_order_bumps,
				$plugin_settings
			);

		$product_context =
			isset(
				$purchase_context[
					'coupon_product_context'
				]
			) &&
			is_array(
				$purchase_context[
					'coupon_product_context'
				]
			)
				? $purchase_context[
					'coupon_product_context'
				]
				: array();

		$product_total =
			$this->normalize_amount(
				$product_context[
					'product_total'
				] ??
					0
			);

		if ( $product_total <= 0 ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'coupon_no_eligible_products',

					'message' =>
						__(
							'This coupon does not have any eligible products in the current selection.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * --------------------------------------------------
		 * Automatic Discount basis
		 * --------------------------------------------------
		 *
		 * Normal:
		 * - Always eligible.
		 *
		 * Combo:
		 * - apply_automatic_discount.
		 *
		 * Order Bump:
		 * - apply_automatic_discount.
		 */
		$automatic_discount_base =
			$this->normalize_amount(
				$purchase_context[
					'automatic_discount_base'
				] ??
					0
			);

		$automatic_condition_context = isset( $purchase_context['special_discount_condition_context'] ) && is_array( $purchase_context['special_discount_condition_context'] )
			? $purchase_context['special_discount_condition_context']
			: array();

		$automatic_discount =
			$this->calculate_automatic_discount(
				$automatic_discount_base,
				array(
					'special_discount_scope' => $special_discount_context_service->get_scope( $special_discount_context ),
					'special_discount_ids' => $special_discount_context_service->get_allowed_ids( $special_discount_context ),
					'current_checkout_product_ids' => isset( $normal_product_context['product_ids'] ) && is_array( $normal_product_context['product_ids'] ) ? $normal_product_context['product_ids'] : array(),
					'condition_context' => $automatic_condition_context,
				)
			);

		$automatic_discount =
			$this->normalize_amount(
				min(
					$automatic_discount,
					$automatic_discount_base
				)
			);

		/*
		 * Only the Automatic Discount share allocated to
		 * Coupon-eligible purchase contexts may reduce
		 * the Coupon stacking basis.
		 *
		 * Example:
		 *
		 * Normal:
		 * Automatic = yes
		 * Coupon    = yes
		 *
		 * Combo:
		 * Automatic = yes
		 * Coupon    = no
		 *
		 * Automatic Discount may include Combo, but its
		 * Combo share must not reduce the Coupon base.
		 */
		$coupon_eligible_automatic_discount =
			$this->calculate_discount_overlap_amount(
				$automatic_discount,
				$automatic_discount_base,
				$this->normalize_amount(
					$purchase_context[
						'automatic_coupon_overlap_base'
					] ??
						0
				)
			);

		$discounted_product_total =
			$product_total;

		/*
		 * Coupon stacking with Automatic Discount.
		 *
		 * If global Coupon stacking is disabled,
		 * CouponCalculator receives the original
		 * Coupon-eligible Product Total as its base.
		 */
		if (
			$this->coupon_stacking_allowed() &&
			$coupon_eligible_automatic_discount > 0
		) {
			$discounted_product_total =
				$this->normalize_amount(
					max(
						0,
						$product_total -
							$coupon_eligible_automatic_discount
					)
				);
		}

		/*
		 * Add source-aware totals to Coupon context.
		 */
		$product_context[
			'normal_product_total'
		] =
			$normal_product_total;

		$product_context[
			'original_product_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'original_product_total'
				] ??
					$normal_product_total
			);

		$product_context[
			'all_payable_product_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'all_payable_product_total'
				] ??
					$normal_product_total
			);

		$product_context[
			'combo_regular_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'combo_regular_total'
				] ??
					0
			);

		$product_context[
			'combo_discount'
		] =
			$this->normalize_amount(
				$purchase_context[
					'combo_discount'
				] ??
					0
			);

		$product_context[
			'combo_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'combo_total'
				] ??
					0
			);

		$product_context[
			'coupon_eligible_combo_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'coupon_eligible_combo_total'
				] ??
					0
			);

		$product_context[
			'order_bump_regular_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'order_bump_regular_total'
				] ??
					0
			);

		$product_context[
			'order_bump_discount'
		] =
			$this->normalize_amount(
				$purchase_context[
					'order_bump_discount'
				] ??
					0
			);

		$product_context[
			'order_bump_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'order_bump_total'
				] ??
					0
			);

		$product_context[
			'coupon_eligible_order_bump_total'
		] =
			$this->normalize_amount(
				$purchase_context[
					'coupon_eligible_order_bump_total'
				] ??
					0
			);

		$product_context[
			'automatic_discount_base'
		] =
			$automatic_discount_base;

		$product_context[
			'coupon_eligible_automatic_discount'
		] =
			$coupon_eligible_automatic_discount;

		$coupon_settings =
			isset(
				$plugin_settings[
					'coupons'
				]
			) &&
			is_array(
				$plugin_settings[
					'coupons'
				]
			)
				? $plugin_settings[
					'coupons'
				]
				: array();

		$custom_coupon =
			$this->find_custom_coupon(
				$code,
				$coupon_settings
			);

		$identity_service =
			new CustomerIdentity();

		$customer_context =
			$this->get_customer_context(
				$code,
				$custom_coupon,
				$coupon_settings,
				$identity_service
			);

		$context =
			array_merge(
				$product_context,
				$customer_context,
				array(
					'product_total' =>
						$product_total,

					'discounted_product_total' =>
						$discounted_product_total,

					'automatic_discount' =>
						$automatic_discount,

					'existing_coupon_discount' =>
						0.0,
				)
			);

		/**
		 * Filters Coupon AJAX calculation context.
		 *
		 * @param array<string, mixed> $context Coupon context.
		 * @param string               $code    Coupon code.
		 */
		$context =
			apply_filters(
				'eilmo_cf/coupons/ajax_context',
				$context,
				$code
			);

		if ( ! is_array( $context ) ) {
			$context =
				array();
		}

		$calculator =
			new CouponCalculator();

		$result =
			$calculator->calculate(
				$code,
				$context
			);

		if ( ! is_array( $result ) ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_coupon',

					'message' =>
						__(
							'This coupon could not be applied.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		/*
		 * Run normal coupon validation first.
		 *
		 * This allows invalid, disabled, expired or
		 * otherwise ineligible coupons to return their
		 * real validation error before requesting
		 * customer contact information.
		 */
		if (
			! empty(
				$customer_context[
					'identity_required'
				]
			) &&
			! empty(
				$result[
					'valid'
				]
			) &&
			! empty(
				$result[
					'applied'
				]
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'customer_identity_required',

					'requires_customer_contact' =>
						true,

					'identification_mode' =>
						sanitize_key(
							(string) (
								$customer_context[
									'identification_mode'
								] ??
									CustomerIdentity::MODE_EMAIL_OR_PHONE
							)
						),

					'message' =>
						$this->get_identity_required_message(
							(string) (
								$customer_context[
									'identification_mode'
								] ??
									CustomerIdentity::MODE_EMAIL_OR_PHONE
							)
						),
				),
				400
			);
		}

		if (
			! empty(
				$result[
					'delegate_to_woocommerce'
				]
			)
		) {
			$this->send_woocommerce_coupon_response(
				$code,
				$product_context
			);
		}

		if (
			empty(
				$result[
					'valid'
				]
			) ||
			empty(
				$result[
					'applied'
				]
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						sanitize_key(
							(string) (
								$result[
									'error_code'
								] ??
									'invalid_coupon'
							)
						),

					'message' =>
						sanitize_text_field(
							(string) (
								$result[
									'message'
								] ??
									__(
										'This coupon could not be applied.',
										'eilmo-checkout-flow'
									)
							)
						),
				),
				400
			);
		}

		$coupon_discount =
			$this->normalize_amount(
				$result[
					'coupon_discount'
				] ??
					0
			);

		$free_delivery =
			! empty(
				$result[
					'free_delivery'
				]
			);

		$response =
			array(
				'valid' =>
					true,

				'applied' =>
					true,

				'source' =>
					'eilmo',

				'coupon_code' =>
					sanitize_text_field(
						(string) (
							$result[
								'coupon_code'
							] ??
								$code
						)
					),

				'coupon_type' =>
					sanitize_key(
						(string) (
							$result[
								'coupon_type'
							] ??
								''
						)
					),

				'coupon_discount' =>
					$coupon_discount,

				'free_delivery' =>
					$free_delivery,

				'base_amount' =>
					$this->normalize_amount(
						$result[
							'base_amount'
						] ??
							$discounted_product_total
					),

				'discounted_total' =>
					$this->normalize_amount(
						$result[
							'discounted_total'
						] ??
							max(
								0,
								$discounted_product_total -
									$coupon_discount
							)
					),

				'allow_stacking' =>
					! empty(
						$result[
							'allow_stacking'
						]
					),

				'product_total' =>
					$product_total,

				'normal_product_total' =>
					$normal_product_total,

				'coupon_eligible_combo_total' =>
					$this->normalize_amount(
						$purchase_context[
							'coupon_eligible_combo_total'
						] ??
							0
					),

				'coupon_eligible_order_bump_total' =>
					$this->normalize_amount(
						$purchase_context[
							'coupon_eligible_order_bump_total'
						] ??
							0
					),

				'automatic_discount_base' =>
					$automatic_discount_base,

				'automatic_discount' =>
					$automatic_discount,

				'coupon_eligible_automatic_discount' =>
					$coupon_eligible_automatic_discount,

				'customer_identity_known' =>
					! empty(
						$customer_context[
							'identity_known'
						]
					),

				'identification_mode' =>
					sanitize_key(
						(string) (
							$customer_context[
								'identification_mode'
							] ??
								CustomerIdentity::MODE_EMAIL_OR_PHONE
						)
					),

				'message' =>
					__(
						'Coupon applied successfully.',
						'eilmo-checkout-flow'
					),
			);

		/**
		 * Filters successful Coupon AJAX response.
		 *
		 * @param array<string, mixed> $response Response.
		 * @param array<string, mixed> $result   Calculator result.
		 * @param array<string, mixed> $context  Context.
		 */
		$response =
			apply_filters(
				'eilmo_cf/coupons/ajax_response',
				$response,
				$result,
				$context
			);

		wp_send_json_success(
			is_array( $response )
				? $response
				: array()
		);
	}

	/**
	 * Get submitted coupon code.
	 *
	 * @return string
	 */
	private function get_coupon_code(): string {

		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'coupon_code'
				]
			)
		) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below.
		$code =
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'coupon_code'
				]
			);

		return $this->normalize_coupon_code(
			$code
		);
	}

	/**
	 * Get selected checkout items.
	 *
	 * Expected JSON:
	 *
	 * [
	 *     {
	 *         "product_id": 10,
	 *         "variation_id": 0,
	 *         "quantity": 2
	 *     }
	 * ]
	 *
	 * @return array<int, array<string, int>>
	 */
	private function get_request_items(): array {

		if (
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'items'
				]
			)
		) {
			return array();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and individual values sanitized below.
		$raw_items =
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'items'
				]
			);

		if ( is_string( $raw_items ) ) {

			$decoded =
				json_decode(
					$raw_items,
					true
				);

			$raw_items =
				is_array( $decoded )
					? $decoded
					: array();
		}

		if ( ! is_array( $raw_items ) ) {
			return array();
		}

		$items =
			array();

		foreach ( $raw_items as $item ) {

			if ( ! is_array( $item ) ) {
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
				absint(
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

			$items[] =
				array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						$quantity,
				);
		}

		return $items;
	}

	/**
	 * Get submitted Combo Offer request.
	 *
	 * Expected JSON:
	 *
	 * {
	 *     "selected_ids": ["combo-a"],
	 *     "context": {...signed context...}
	 * }
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_combo_offers(): array {

		return $this->get_request_json_object(
			'combo_offers'
		);
	}

	/**
	 * Get submitted Order Bump request.
	 *
	 * Expected JSON:
	 *
	 * {
	 *     "selected_ids": ["special-offer"]
	 * }
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_order_bumps(): array {

		return $this->get_request_json_object(
			'order_bumps'
		);
	}

	/**
	 * Get one JSON object from AJAX request.
	 *
	 * @param string $key Request key.
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_json_object(
		string $key
	): array {

		if (
			'' === $key ||
			! isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					$key
				]
			)
		) {
			return array();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Dedicated authoritative services revalidate submitted IDs/context.
		$raw =
			wp_unslash(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					$key
				]
			);

		if ( is_string( $raw ) ) {

			$decoded =
				json_decode(
					$raw,
					true
				);

			$raw =
				is_array( $decoded )
					? $decoded
					: array();
		}

		return is_array( $raw )
			? $raw
			: array();
	}

	/**
	 * Build source-aware Coupon / Automatic Discount
	 * purchase context.
	 *
	 * Normal Product:
	 * - Automatic: yes.
	 * - Coupon: yes.
	 *
	 * Combo:
	 * - Automatic: apply_automatic_discount.
	 * - Coupon: apply_coupon.
	 *
	 * Order Bump:
	 * - Automatic: apply_automatic_discount.
	 * - Coupon: apply_coupon.
	 *
	 * @param array<string, mixed> $normal_context  Normal context.
	 * @param array<string, mixed> $combo_offers    Validated Combo data.
	 * @param array<string, mixed> $order_bumps     Resolved Order Bump data.
	 * @param array<string, mixed> $plugin_settings Authoritative saved settings.
	 *
	 * @return array<string, mixed>
	 */
	private function build_discount_purchase_context(
		array $normal_context,
		array $combo_offers,
		array $order_bumps,
		array $plugin_settings
	): array {

		$normal_total =
			$this->normalize_amount(
				$normal_context[
					'product_total'
				] ??
					0
			);

		$coupon_product_total =
			$normal_total;

		$automatic_discount_base =
			$normal_total;

		$automatic_coupon_overlap_base =
			$normal_total;

		$original_product_total =
			$normal_total;

		$all_payable_product_total =
			$normal_total;

		$condition_product_total = $normal_total;
		$condition_cart_quantity = (float) ( $normal_context['cart_quantity'] ?? 0 );
		$condition_product_quantities = isset( $normal_context['product_quantities'] ) && is_array( $normal_context['product_quantities'] ) ? $normal_context['product_quantities'] : array();
		$condition_product_totals = isset( $normal_context['product_totals'] ) && is_array( $normal_context['product_totals'] ) ? $normal_context['product_totals'] : array();

		$product_ids =
			isset(
				$normal_context[
					'product_ids'
				]
			) &&
			is_array(
				$normal_context[
					'product_ids'
				]
			)
				? $normal_context[
					'product_ids'
				]
				: array();

		$category_ids =
			isset(
				$normal_context[
					'category_ids'
				]
			) &&
			is_array(
				$normal_context[
					'category_ids'
				]
			)
				? $normal_context[
					'category_ids'
				]
				: array();

		/*
		 * --------------------------------------------------
		 * Combo Offers
		 * --------------------------------------------------
		 */
		$combo_regular_total =
			0.0;

		$combo_discount =
			0.0;

		$combo_total =
			0.0;

		$coupon_eligible_combo_total =
			0.0;

		$combo_list =
			isset(
				$combo_offers[
					'offers'
				]
			) &&
			is_array(
				$combo_offers[
					'offers'
				]
			)
				? $combo_offers[
					'offers'
				]
				: array();

		foreach ( $combo_list as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			$regular_total =
				$this->normalize_amount(
					$offer[
						'regular_total'
					] ??
						0
				);

			$discount =
				min(
					$regular_total,
					$this->normalize_amount(
						$offer[
							'discount'
						] ??
							0
					)
				);

			$payable_total =
				$this->normalize_amount(
					$offer[
						'combo_total'
					] ??
						max(
							0,
							$regular_total -
								$discount
						)
				);

			if ( $regular_total > 0 ) {
				$payable_total =
					min(
						$payable_total,
						$regular_total
					);
			}

			$combo_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			/*
			 * Re-read compatibility from the current saved
			 * Combo Offer configuration. The browser never
			 * controls these flags.
			 */
			$apply_automatic_discount =
				$this->saved_offer_allows(
					$plugin_settings,
					'combo_offers',
					$combo_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			/*
			 * Combo historical default:
			 * Coupon Discount = YES.
			 */
			$apply_coupon =
				$this->saved_offer_allows(
					$plugin_settings,
					'combo_offers',
					$combo_id,
					'apply_coupon',
					true,
					$offer
				);

			$combo_regular_total =
				$this->normalize_amount(
					$combo_regular_total +
						$regular_total
				);

			$combo_discount =
				$this->normalize_amount(
					$combo_discount +
						$discount
				);

			$combo_total =
				$this->normalize_amount(
					$combo_total +
						$payable_total
				);

			$original_product_total =
				$this->normalize_amount(
					$original_product_total +
						$regular_total
				);

			$all_payable_product_total =
				$this->normalize_amount(
					$all_payable_product_total +
						$payable_total
				);

			/* Combo pricing remains owned by Combo Offers; conditions see its payable total. */
			$condition_product_total = $this->normalize_amount( $condition_product_total + $payable_total );
			$offer_items = isset( $offer['items'] ) && is_array( $offer['items'] ) ? $offer['items'] : array();
			$component_regular_total = 0.0;
			foreach ( $offer_items as $condition_item ) {
				if ( is_array( $condition_item ) ) {
					$component_regular_total += $this->normalize_amount( $condition_item['line_total'] ?? 0 );
				}
			}
			$condition_scale = $component_regular_total > 0 ? min( 1, $payable_total / $component_regular_total ) : 0;
			foreach ( $offer_items as $condition_item ) {
				if ( ! is_array( $condition_item ) ) { continue; }
				$condition_product_id = absint( $condition_item['product_id'] ?? 0 );
				$condition_variation_id = absint( $condition_item['variation_id'] ?? 0 );
				$condition_quantity = max( 0, (float) ( $condition_item['quantity'] ?? 0 ) );
				$condition_line_total = $this->normalize_amount( (float) ( $condition_item['line_total'] ?? 0 ) * $condition_scale );
				$condition_cart_quantity += $condition_quantity;
				if ( $condition_product_id > 0 ) {
					$condition_product_quantities[ $condition_product_id ] = (float) ( $condition_product_quantities[ $condition_product_id ] ?? 0 ) + $condition_quantity;
					$condition_product_totals[ $condition_product_id ] = $this->normalize_amount( (float) ( $condition_product_totals[ $condition_product_id ] ?? 0 ) + $condition_line_total );
				}
				if ( $condition_variation_id > 0 ) {
					$condition_product_quantities[ $condition_variation_id ] = (float) ( $condition_product_quantities[ $condition_variation_id ] ?? 0 ) + $condition_quantity;
					$condition_product_totals[ $condition_variation_id ] = $this->normalize_amount( (float) ( $condition_product_totals[ $condition_variation_id ] ?? 0 ) + $condition_line_total );
				}
			}

			if ( $apply_automatic_discount ) {
				$automatic_discount_base =
					$this->normalize_amount(
						$automatic_discount_base +
							$payable_total
					);
			}

			if ( $apply_coupon ) {

				$coupon_product_total =
					$this->normalize_amount(
						$coupon_product_total +
							$payable_total
					);

				$coupon_eligible_combo_total =
					$this->normalize_amount(
						$coupon_eligible_combo_total +
							$payable_total
					);

				$this->append_offer_product_taxonomy(
					$product_ids,
					$category_ids,
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
						: array()
				);
			}

			if (
				$apply_automatic_discount &&
				$apply_coupon
			) {
				$automatic_coupon_overlap_base =
					$this->normalize_amount(
						$automatic_coupon_overlap_base +
							$payable_total
					);
			}
		}

		/*
		 * --------------------------------------------------
		 * Order Bumps
		 * --------------------------------------------------
		 */
		$order_bump_regular_total =
			0.0;

		$order_bump_discount =
			0.0;

		$order_bump_total =
			0.0;

		$coupon_eligible_order_bump_total =
			0.0;

		$order_bump_list =
			isset(
				$order_bumps[
					'offers'
				]
			) &&
			is_array(
				$order_bumps[
					'offers'
				]
			)
				? $order_bumps[
					'offers'
				]
				: array();

		foreach ( $order_bump_list as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			/*
			 * OrderBumpResolver already recalculated these
			 * amounts from saved configuration and current
			 * WooCommerce pricing. Use that authoritative
			 * resolved snapshot directly.
			 */
			$regular_total =
				$this->normalize_amount(
					$offer[
						'regular_total'
					] ??
						0
				);

			$discount =
				min(
					$regular_total,
					$this->normalize_amount(
						$offer[
							'discount'
						] ??
							0
					)
				);

			$payable_total =
				$this->normalize_amount(
					$offer[
						'bump_total'
					] ??
						max(
							0,
							$regular_total -
								$discount
						)
				);

			if ( $regular_total <= 0 ) {
				continue;
			}

			$payable_total =
				min(
					$payable_total,
					$regular_total
				);

			$bump_id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			$apply_automatic_discount =
				$this->saved_offer_allows(
					$plugin_settings,
					'order_bumps',
					$bump_id,
					'apply_automatic_discount',
					false,
					$offer
				);

			/*
			 * Order Bump historical default:
			 * Coupon Discount = NO.
			 */
			$apply_coupon =
				$this->saved_offer_allows(
					$plugin_settings,
					'order_bumps',
					$bump_id,
					'apply_coupon',
					false,
					$offer
				);

			$order_bump_regular_total =
				$this->normalize_amount(
					$order_bump_regular_total +
						$regular_total
				);

			$order_bump_discount =
				$this->normalize_amount(
					$order_bump_discount +
						min(
							$discount,
							$regular_total
						)
				);

			$order_bump_total =
				$this->normalize_amount(
					$order_bump_total +
						$payable_total
				);

			$original_product_total =
				$this->normalize_amount(
					$original_product_total +
						$regular_total
				);

			$all_payable_product_total =
				$this->normalize_amount(
					$all_payable_product_total +
						$payable_total
				);

			if ( $apply_automatic_discount ) {
				$automatic_discount_base =
					$this->normalize_amount(
						$automatic_discount_base +
							$payable_total
					);
			}

			if ( $apply_coupon ) {

				$coupon_product_total =
					$this->normalize_amount(
						$coupon_product_total +
							$payable_total
					);

				$coupon_eligible_order_bump_total =
					$this->normalize_amount(
						$coupon_eligible_order_bump_total +
							$payable_total
					);

				$this->append_product_taxonomy(
					$product_ids,
					$category_ids,
					absint(
						$offer[
							'product_id'
						] ??
							0
					),
					absint(
						$offer[
							'variation_id'
						] ??
							0
					)
				);
			}

			if (
				$apply_automatic_discount &&
				$apply_coupon
			) {
				$automatic_coupon_overlap_base =
					$this->normalize_amount(
						$automatic_coupon_overlap_base +
							$payable_total
					);
			}
		}

		return array(
			'coupon_product_context' =>
				array(
					'product_total' =>
						$coupon_product_total,

					'product_ids' =>
						array_values(
							array_unique(
								array_filter(
									array_map(
										'absint',
										$product_ids
									)
								)
							)
						),

					'category_ids' =>
						array_values(
							array_unique(
								array_filter(
									array_map(
										'absint',
										$category_ids
									)
								)
							)
						),
				),

			'special_discount_condition_context' => array(
				'product_total' => $condition_product_total,
				'cart_quantity' => $condition_cart_quantity,
				'product_quantities' => $condition_product_quantities,
				'product_totals' => $condition_product_totals,
				'product_ids' => array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) ),
			),

			'automatic_discount_base' =>
				$automatic_discount_base,

			'automatic_coupon_overlap_base' =>
				min(
					$automatic_discount_base,
					$automatic_coupon_overlap_base
				),

			'original_product_total' =>
				$original_product_total,

			'all_payable_product_total' =>
				$all_payable_product_total,

			'combo_regular_total' =>
				$combo_regular_total,

			'combo_discount' =>
				$combo_discount,

			'combo_total' =>
				$combo_total,

			'coupon_eligible_combo_total' =>
				$coupon_eligible_combo_total,

			'order_bump_regular_total' =>
				$order_bump_regular_total,

			'order_bump_discount' =>
				$order_bump_discount,

			'order_bump_total' =>
				$order_bump_total,

			'coupon_eligible_order_bump_total' =>
				$coupon_eligible_order_bump_total,
		);
	}

	/**
	 * Check one saved reusable offer compatibility flag.
	 *
	 * Saved settings are authoritative. The validated
	 * server-side offer is used only as a defensive
	 * fallback if the exact saved offer cannot be found.
	 *
	 * @param array<string, mixed> $settings       Plugin settings.
	 * @param string               $section        Settings section.
	 * @param string               $offer_id       Offer ID.
	 * @param string               $flag           Compatibility flag.
	 * @param bool                 $default        Historical default.
	 * @param array<string, mixed> $fallback_offer Validated fallback.
	 *
	 * @return bool
	 */
	private function saved_offer_allows(
		array $settings,
		string $section,
		string $offer_id,
		string $flag,
		bool $default,
		array $fallback_offer = array()
	): bool {

		$section =
			sanitize_key(
				$section
			);

		$offer_id =
			sanitize_key(
				$offer_id
			);

		$flag =
			sanitize_key(
				$flag
			);

		$offers =
			isset(
				$settings[
					$section
				][
					'offers'
				]
			) &&
			is_array(
				$settings[
					$section
				][
					'offers'
				]
			)
				? $settings[
					$section
				][
					'offers'
				]
				: array();

		if (
			'' !== $offer_id &&
			'' !== $flag
		) {
			foreach ( $offers as $saved_offer ) {

				if ( ! is_array( $saved_offer ) ) {
					continue;
				}

				$saved_offer_id =
					sanitize_key(
						(string) (
							$saved_offer[
								'id'
							] ??
								''
						)
					);

				if ( $saved_offer_id !== $offer_id ) {
					continue;
				}

				return $this->normalize_compatibility_flag(
					$saved_offer[
						$flag
					] ??
						$default,
					$default
				);
			}
		}

		return $this->normalize_compatibility_flag(
			$fallback_offer[
				$flag
			] ??
				$default,
			$default
		);
	}

	/**
	 * Normalize one Discount Compatibility value.
	 *
	 * @param mixed $value   Value.
	 * @param bool  $default Default.
	 *
	 * @return bool
	 */
	private function normalize_compatibility_flag(
		$value,
		bool $default
	): bool {

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		$normalized =
			strtolower(
				trim(
					(string) $value
				)
			);

		if (
			in_array(
				$normalized,
				array(
					'yes',
					'1',
					'true',
					'on',
				),
				true
			)
		) {
			return true;
		}

		if (
			in_array(
				$normalized,
				array(
					'no',
					'0',
					'false',
					'off',
					'',
				),
				true
			)
		) {
			return false;
		}

		return $default;
	}

	/**
	 * Append Combo component products to Coupon
	 * product restriction context.
	 *
	 * @param array<int>                       $product_ids  Product IDs.
	 * @param array<int>                       $category_ids Category IDs.
	 * @param array<int, array<string, mixed>> $items        Combo items.
	 *
	 * @return void
	 */
	private function append_offer_product_taxonomy(
		array &$product_ids,
		array &$category_ids,
		array $items
	): void {

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$this->append_product_taxonomy(
				$product_ids,
				$category_ids,
				absint(
					$item[
						'product_id'
					] ??
						0
				),
				absint(
					$item[
						'variation_id'
					] ??
						0
				)
			);
		}
	}

	/**
	 * Append product / variation and categories to
	 * Coupon restriction context.
	 *
	 * @param array<int> $product_ids  Product IDs.
	 * @param array<int> $category_ids Category IDs.
	 * @param int        $product_id   Product ID.
	 * @param int        $variation_id Variation ID.
	 *
	 * @return void
	 */
	private function append_product_taxonomy(
		array &$product_ids,
		array &$category_ids,
		int $product_id,
		int $variation_id = 0
	): void {

		if ( $product_id <= 0 ) {
			return;
		}

		$product_ids[] =
			$product_id;

		if ( $variation_id > 0 ) {
			$product_ids[] =
				$variation_id;
		}

		$terms =
			wp_get_post_terms(
				$product_id,
				'product_cat',
				array(
					'fields' =>
						'ids',
				)
			);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term_id ) {
			$category_ids[] =
				absint(
					$term_id
				);
		}
	}

	/**
	 * Calculate Automatic Discount share overlapping
	 * Coupon-eligible purchase contexts.
	 *
	 * @param float $discount     Automatic Discount.
	 * @param float $source_total Automatic eligible total.
	 * @param float $subset_total Automatic + Coupon eligible total.
	 *
	 * @return float
	 */
	private function calculate_discount_overlap_amount(
		float $discount,
		float $source_total,
		float $subset_total
	): float {

		$discount =
			$this->normalize_amount(
				$discount
			);

		$source_total =
			$this->normalize_amount(
				$source_total
			);

		$subset_total =
			$this->normalize_amount(
				$subset_total
			);

		if (
			$discount <= 0 ||
			$source_total <= 0 ||
			$subset_total <= 0
		) {
			return 0.0;
		}

		$discount =
			min(
				$discount,
				$source_total
			);

		$subset_total =
			min(
				$subset_total,
				$source_total
			);

		return $this->normalize_amount(
			min(
				$subset_total,
				$discount *
					(
						$subset_total /
							$source_total
					)
			)
		);
	}

	/**
	 * Send WP_Error returned by authoritative service.
	 *
	 * @param \WP_Error $error  Error.
	 * @param int       $status HTTP status.
	 *
	 * @return void
	 */
	private function send_service_error(
		\WP_Error $error,
		int $status = 400
	): void {

		$code =
			sanitize_key(
				(string) $error
					->get_error_code()
			);

		$message =
			sanitize_text_field(
				(string) $error
					->get_error_message()
			);

		wp_send_json_error(
			array(
				'error_code' =>
					'' !== $code
						? $code
						: 'invalid_request',

				'message' =>
					'' !== $message
						? $message
						: __(
							'The request could not be validated.',
							'eilmo-checkout-flow'
						),
			),
			$status
		);
	}

	/**
	 * Build server-side product context.
	 *
	 * Product price is loaded from WooCommerce rather
	 * than trusting a frontend-submitted total.
	 *
	 * @param array<int, array<string, int>> $items Items.
	 *
	 * @return array<string, mixed>
	 */
	private function build_product_context(
		array $items
	): array {

		$product_total =
			0.0;

		$product_ids =
			array();

		$category_ids =
			array();

		$product_quantities = array();
		$product_totals = array();
		$cart_quantity = 0;

		foreach ( $items as $item ) {

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
				absint(
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
				! $product ||
				! $product->exists()
			) {
				continue;
			}

			if (
				$variation_id > 0 &&
				absint(
					$product->get_parent_id()
				) !==
					$product_id
			) {
				continue;
			}

			if (
				0 === $variation_id &&
				$product->is_type(
					'variable'
				)
			) {
				continue;
			}

			if (
				! $product->is_purchasable()
			) {
				continue;
			}

			$price =
				(float) wc_get_price_to_display(
					$product
				);

			$line_total = $price * $quantity;
			$product_total += $line_total;
			$cart_quantity += $quantity;
			$product_quantities[ $product_id ] = (float) ( $product_quantities[ $product_id ] ?? 0 ) + $quantity;
			$product_totals[ $product_id ] = $this->normalize_amount( (float) ( $product_totals[ $product_id ] ?? 0 ) + $line_total );
			if ( $variation_id > 0 ) {
				$product_quantities[ $variation_id ] = (float) ( $product_quantities[ $variation_id ] ?? 0 ) + $quantity;
				$product_totals[ $variation_id ] = $this->normalize_amount( (float) ( $product_totals[ $variation_id ] ?? 0 ) + $line_total );
			}

			$product_ids[] =
				$product_id;

			if ( $variation_id > 0 ) {
				$product_ids[] =
					$variation_id;
			}

			$terms =
				wp_get_post_terms(
					$product_id,
					'product_cat',
					array(
						'fields' =>
							'ids',
					)
				);

			if (
				! is_wp_error(
					$terms
				)
			) {
				foreach ( $terms as $term_id ) {
					$category_ids[] =
						absint(
							$term_id
						);
				}
			}
		}

		return array(
			'product_total' =>
				$this->normalize_amount(
					$product_total
				),

			'product_ids' =>
				array_values(
					array_unique(
						array_filter(
							$product_ids
						)
					)
				),

			'cart_quantity' => $cart_quantity,
			'product_quantities' => $product_quantities,
			'product_totals' => $product_totals,

			'category_ids' =>
				array_values(
					array_unique(
						array_filter(
							$category_ids
						)
					)
				),
		);
	}

	/**
	 * Calculate Automatic Discount.
	 *
	 * @param float                $product_total Product total.
	 * @param array<string, mixed> $context       Checkout assignment context.
	 *
	 * @return float
	 */
	private function calculate_automatic_discount(
		float $product_total,
		array $context = array()
	): float {

		if (
			$product_total <= 0 ||
			! class_exists(
				AutomaticDiscountCalculator::class
			)
		) {
			return 0.0;
		}

		$calculator =
			new AutomaticDiscountCalculator();

		$context['product_total'] = $product_total;
		$result = $calculator->calculate( $context );

		if (
			! is_array( $result ) ||
			empty(
				$result[
					'matched'
				]
			)
		) {
			return 0.0;
		}

		return $this->normalize_amount(
			$result[
				'automatic_discount'
			] ??
				0
		);
	}

	/**
	 * Determine whether coupons may stack with
	 * Automatic Discounts.
	 *
	 * @return bool
	 */
	private function coupon_stacking_allowed(): bool {

		$settings =
			$this->get_plugin_settings();

		$discounts =
			isset(
				$settings[
					'discounts'
				]
			) &&
			is_array(
				$settings[
					'discounts'
				]
			)
				? $settings[
					'discounts'
				]
				: array();

		return (
			'yes' ===
				(
					$discounts[
						'allow_coupon_stacking'
					] ??
						'no'
				)
		);
	}

	/**
	 * Build customer-related coupon context.
	 *
	 * Customer matching supports:
	 * - Logged-in customer ID.
	 * - Billing email.
	 * - Billing phone.
	 *
	 * In Email or Phone mode, either matching contact
	 * identifies the same customer.
	 *
	 * @param string                    $code             Coupon code.
	 * @param array<string, mixed>|null $custom_coupon    Matching custom coupon.
	 * @param array<string, mixed>      $coupon_settings  Coupon settings.
	 * @param CustomerIdentity          $identity_service Identity service.
	 *
	 * @return array<string, mixed>
	 */
	private function get_customer_context(
		string $code,
		?array $custom_coupon,
		array $coupon_settings,
		CustomerIdentity $identity_service
	): array {

		$request_context =
			$this->get_submitted_customer_context();

		$identity =
			$identity_service->resolve(
				$request_context
			);

		$customer_id =
			absint(
				$identity[
					'customer_id'
				] ??
					0
			);

		/*
		 * Keep raw phone only for WooCommerce lookup.
		 *
		 * Actual equality checks always use the
		 * normalized CustomerIdentity value.
		 */
		$raw_phone =
			sanitize_text_field(
				(string) (
					$request_context[
						'customer_phone'
					] ??
						''
				)
			);

		if (
			'' === $raw_phone &&
			$customer_id > 0
		) {
			$raw_phone =
				sanitize_text_field(
					(string) get_user_meta(
						$customer_id,
						'billing_phone',
						true
					)
				);
		}

		$identity[
			'raw_customer_phone'
		] =
			$raw_phone;

		$identification_mode =
			$identity_service->normalize_mode(
				$coupon_settings[
					'customer_identification'
				] ??
					CustomerIdentity::MODE_EMAIL_OR_PHONE
			);

		$identity_known =
			$identity_service->has_required_identity(
				$identity,
				$identification_mode
			);

		$customer_limited =
			$this->is_customer_limited_coupon(
				$custom_coupon
			);

		$require_contact =
			'yes' ===
				(
					$coupon_settings[
						'require_contact_for_customer_limited'
					] ??
						'yes'
				);

		/*
		 * Do not automatically consider an anonymous
		 * guest eligible for customer-specific coupons.
		 */
		if (
			$customer_limited &&
			$require_contact &&
			! $identity_known
		) {
			return array(
				'customer_id' =>
					$customer_id,

				'customer_email' =>
					(string) (
						$identity[
							'customer_email'
						] ??
							''
					),

				'customer_phone' =>
					(string) (
						$identity[
							'customer_phone'
						] ??
							''
					),

				'billing_country' =>
					(string) (
						$identity[
							'billing_country'
						] ??
							''
					),

				'identity_known' =>
					false,

				'identity_required' =>
					true,

				'identification_mode' =>
					$identification_mode,

				'is_new_customer' =>
					true,

				'usage_count' =>
					$this->get_coupon_usage_count(
						$code
					),

				'customer_usage_count' =>
					0,
			);
		}

		$is_new_customer =
			$identity_known
				? $this->is_new_customer(
					$identity,
					$identification_mode,
					$identity_service
				)
				: true;

		$customer_usage_count =
			$identity_known
				? $this->get_customer_coupon_usage_count(
					$code,
					$identity,
					$identification_mode,
					$identity_service
				)
				: 0;

		return array(
			'customer_id' =>
				$customer_id,

			'customer_email' =>
				(string) (
					$identity[
						'customer_email'
					] ??
						''
				),

			'customer_phone' =>
				(string) (
					$identity[
						'customer_phone'
					] ??
						''
				),

			'billing_country' =>
				(string) (
					$identity[
						'billing_country'
					] ??
						''
				),

			'identity_known' =>
				$identity_known,

			'identity_required' =>
				false,

			'identification_mode' =>
				$identification_mode,

			'is_new_customer' =>
				$is_new_customer,

			'usage_count' =>
				$this->get_coupon_usage_count(
					$code
				),

			'customer_usage_count' =>
				$customer_usage_count,
		);
	}

	/**
	 * Get customer information submitted by frontend.
	 *
	 * Logged-in customer data is resolved by
	 * CustomerIdentity when submitted fields are empty.
	 *
	 * @return array<string, mixed>
	 */
	private function get_submitted_customer_context(): array {

		$customer_email =
			'';

		$customer_phone =
			'';

		$billing_country =
			'';

		if (
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'customer_email'
				]
			)
		) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below.
			$customer_email =
				sanitize_email(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'customer_email'
						]
					)
				);
		}

		if (
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'customer_phone'
				]
			)
		) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below.
			$customer_phone =
				sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST[
							'customer_phone'
						]
					)
				);
		}

		if (
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
				$_POST[
					'billing_country'
				]
			)
		) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below.
			$billing_country =
				strtoupper(
					sanitize_key(
						wp_unslash(
							// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
							$_POST[
								'billing_country'
							]
						)
					)
				);
		}

		return array(
			'customer_id' =>
				get_current_user_id(),

			'customer_email' =>
				$customer_email,

			'customer_phone' =>
				$customer_phone,

			'billing_country' =>
				$billing_country,
		);
	}

	/**
	 * Determine whether a custom coupon needs
	 * customer-specific identification.
	 *
	 * @param array<string, mixed>|null $coupon Coupon.
	 *
	 * @return bool
	 */
	private function is_customer_limited_coupon(
		?array $coupon
	): bool {

		if ( null === $coupon ) {
			return false;
		}

		return (
			absint(
				$coupon[
					'usage_limit_per_customer'
				] ??
					0
			) > 0 ||
			'yes' ===
				(
					$coupon[
						'new_customer_only'
					] ??
						'no'
				)
		);
	}

	/**
	 * Get missing identity message.
	 *
	 * @param string $mode Identification mode.
	 *
	 * @return string
	 */
	private function get_identity_required_message(
		string $mode
	): string {

		switch ( $mode ) {

			case CustomerIdentity::MODE_EMAIL:
				return __(
					'Enter your email to check eligibility for this coupon.',
					'eilmo-checkout-flow'
				);

			case CustomerIdentity::MODE_PHONE:
				return __(
					'Enter your phone number to check eligibility for this coupon.',
					'eilmo-checkout-flow'
				);

			case CustomerIdentity::MODE_EMAIL_OR_PHONE:
			default:
				return __(
					'Enter your email or phone number to check eligibility for this coupon.',
					'eilmo-checkout-flow'
				);
		}
	}

	/**
	 * Find matching Eilmo custom coupon.
	 *
	 * WooCommerce-only mode intentionally ignores
	 * Eilmo custom coupons.
	 *
	 * @param string               $code     Coupon code.
	 * @param array<string, mixed> $settings Coupon settings.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_custom_coupon(
		string $code,
		array $settings
	): ?array {

		$mode =
			sanitize_key(
				(string) (
					$settings[
						'mode'
					] ??
						'woocommerce'
				)
			);

		if (
			! in_array(
				$mode,
				array(
					'custom',
					'both',
				),
				true
			)
		) {
			return null;
		}

		$coupons =
			isset(
				$settings[
					'custom_coupons'
				]
			) &&
			is_array(
				$settings[
					'custom_coupons'
				]
			)
				? $settings[
					'custom_coupons'
				]
				: array();

		$code =
			$this->normalize_coupon_code(
				$code
			);

		foreach ( $coupons as $coupon ) {

			if ( ! is_array( $coupon ) ) {
				continue;
			}

			$coupon_code =
				$this->normalize_coupon_code(
					$coupon[
						'code'
					] ??
						''
				);

			if (
				'' !== $coupon_code &&
				$code === $coupon_code
			) {
				return $coupon;
			}
		}

		return null;
	}

	/**
	 * Normalize coupon code.
	 *
	 * @param mixed $code Coupon code.
	 *
	 * @return string
	 */
	private function normalize_coupon_code(
		$code
	): string {

		$code =
			trim(
				(string) $code
			);

		if ( '' === $code ) {
			return '';
		}

		if (
			function_exists(
				'wc_format_coupon_code'
			)
		) {
			$code =
				wc_format_coupon_code(
					$code
				);
		} else {
			$code =
				sanitize_text_field(
					$code
				);
		}

		return strtolower(
			trim(
				$code
			)
		);
	}

	/**
	 * Determine whether customer is new.
	 *
	 * A customer is considered existing if any
	 * configured identity matches an active
	 * historical WooCommerce order.
	 *
	 * @param array<string, mixed> $identity         Identity.
	 * @param string               $mode             Identification mode.
	 * @param CustomerIdentity     $identity_service Identity service.
	 *
	 * @return bool
	 */
	private function is_new_customer(
		array $identity,
		string $mode,
		CustomerIdentity $identity_service
	): bool {

		if (
			! function_exists(
				'wc_get_orders'
			)
		) {
			return true;
		}

		$order_ids =
			$this->get_customer_candidate_order_ids(
				$identity,
				$mode,
				$identity_service
			);

		foreach ( $order_ids as $order_id ) {

			if (
				$this->order_matches_identity(
					$order_id,
					$identity,
					$mode,
					$identity_service
				)
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get total custom coupon usage count.
	 *
	 * @param string $code Coupon code.
	 *
	 * @return int
	 */
	private function get_coupon_usage_count(
		string $code
	): int {

		return count(
			$this->get_coupon_order_ids(
				$code
			)
		);
	}

	/**
	 * Get coupon usage count for current customer.
	 *
	 * Email or Phone mode uses OR matching:
	 *
	 * User ID
	 * OR Email
	 * OR Phone.
	 *
	 * @param string               $code             Coupon code.
	 * @param array<string, mixed> $identity         Identity.
	 * @param string               $mode             Identification mode.
	 * @param CustomerIdentity     $identity_service Identity service.
	 *
	 * @return int
	 */
	private function get_customer_coupon_usage_count(
		string $code,
		array $identity,
		string $mode,
		CustomerIdentity $identity_service
	): int {

		$count =
			0;

		foreach (
			$this->get_coupon_order_ids(
				$code
			) as $order_id
		) {
			if (
				$this->order_matches_identity(
					$order_id,
					$identity,
					$mode,
					$identity_service
				)
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get orders that count toward custom
	 * coupon usage.
	 *
	 * Cancelled, failed and refunded orders do not
	 * consume coupon usage.
	 *
	 * @param string $code Coupon code.
	 *
	 * @return array<int>
	 */
	private function get_coupon_order_ids(
		string $code
	): array {

		if (
			! function_exists(
				'wc_get_orders'
			)
		) {
			return array();
		}

		$orders =
			wc_get_orders(
				array(
					'limit' =>
						-1,

					'return' =>
						'ids',

					'status' =>
						$this->get_usage_order_statuses(),

					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- WooCommerce order compatibility requires lookup by the plugin-owned coupon metadata key.
					'meta_query' =>
						array(
							array(
								'key' =>
									CouponCalculator::ORDER_COUPON_META_KEY,

								'value' =>
									$this->normalize_coupon_code(
										$code
									),

								'compare' =>
									'=',
							),
						),
				)
			);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						'absint',
						$orders
					)
				)
			)
		);
	}

	/**
	 * Get candidate orders for customer identity.
	 *
	 * Separate queries are merged deliberately.
	 *
	 * This makes Email or Phone mode use OR logic,
	 * not AND logic.
	 *
	 * @param array<string, mixed> $identity         Identity.
	 * @param string               $mode             Identification mode.
	 * @param CustomerIdentity     $identity_service Identity service.
	 *
	 * @return array<int>
	 */
	private function get_customer_candidate_order_ids(
		array $identity,
		string $mode,
		CustomerIdentity $identity_service
	): array {

		$ids =
			array();

		$identifiers =
			$identity_service
				->get_match_identifiers(
					$identity,
					$mode
				);

		$customer_id =
			absint(
				$identifiers[
					'customer_id'
				] ??
					0
			);

		$email =
			(string) (
				$identifiers[
					'customer_email'
				] ??
					''
			);

		$phone =
			(string) (
				$identifiers[
					'customer_phone'
				] ??
					''
			);

		$raw_phone =
			sanitize_text_field(
				(string) (
					$identity[
						'raw_customer_phone'
					] ??
						''
				)
			);

		$base_args =
			array(
				'limit' =>
					-1,

				'return' =>
					'ids',

				'status' =>
					$this->get_usage_order_statuses(),
			);

		if ( $customer_id > 0 ) {

			$ids =
				array_merge(
					$ids,
					$this->query_order_ids(
						array_merge(
							$base_args,
							array(
								'customer_id' =>
									$customer_id,
							)
						)
					)
				);
		}

		if ( '' !== $email ) {

			$ids =
				array_merge(
					$ids,
					$this->query_order_ids(
						array_merge(
							$base_args,
							array(
								'billing_email' =>
									$email,
							)
						)
					)
				);
		}

		$phone_candidates =
			array_values(
				array_unique(
					array_filter(
						array(
							$raw_phone,
							$phone,
						)
					)
				)
			);

		foreach (
			$phone_candidates as
				$phone_candidate
		) {

			$ids =
				array_merge(
					$ids,
					$this->query_order_ids(
						array_merge(
							$base_args,
							array(
								'billing_phone' =>
									$phone_candidate,
							)
						)
					)
				);
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						'absint',
						$ids
					)
				)
			)
		);
	}

	/**
	 * Execute WooCommerce order ID query.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return array<int>
	 */
	private function query_order_ids(
		array $args
	): array {

		if (
			! function_exists(
				'wc_get_orders'
			)
		) {
			return array();
		}

		$orders =
			wc_get_orders(
				$args
			);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					'absint',
					$orders
				)
			)
		);
	}

	/**
	 * Determine whether an order belongs to
	 * the resolved customer.
	 *
	 * @param int                  $order_id         Order ID.
	 * @param array<string, mixed> $identity         Identity.
	 * @param string               $mode             Identification mode.
	 * @param CustomerIdentity     $identity_service Identity service.
	 *
	 * @return bool
	 */
	private function order_matches_identity(
		int $order_id,
		array $identity,
		string $mode,
		CustomerIdentity $identity_service
	): bool {

		$order =
			wc_get_order(
				$order_id
			);

		if ( ! $order ) {
			return false;
		}

		$identifiers =
			$identity_service
				->get_match_identifiers(
					$identity,
					$mode
				);

		$customer_id =
			absint(
				$identifiers[
					'customer_id'
				] ??
					0
			);

		if (
			$customer_id > 0 &&
			$customer_id ===
				absint(
					$order->get_customer_id()
				)
		) {
			return true;
		}

		$email =
			(string) (
				$identifiers[
					'customer_email'
				] ??
					''
			);

		if ( '' !== $email ) {

			$order_email =
				$identity_service
					->normalize_email(
						$order->get_billing_email()
					);

			if (
				'' !== $order_email &&
				$email === $order_email
			) {
				return true;
			}
		}

		$phone =
			(string) (
				$identifiers[
					'customer_phone'
				] ??
					''
			);

		if ( '' !== $phone ) {

			$order_phone =
				$identity_service
					->normalize_phone(
						$order->get_billing_phone(),
						$order->get_billing_country()
					);

			if (
				'' !== $order_phone &&
				$phone === $order_phone
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Order statuses that consume coupon usage.
	 *
	 * @return array<string>
	 */
	private function get_usage_order_statuses(): array {

		return array(
			'wc-pending',
			'wc-on-hold',
			'wc-processing',
			'wc-completed',
		);
	}

	/**
	 * Resolve WooCommerce native coupon.
	 *
	 * This endpoint only confirms WooCommerce recognizes
	 * the code.
	 *
	 * Exact Combo / Order Bump Coupon isolation remains
	 * authoritative in OrderCreator, where WooCommerce
	 * line-item objects can be filtered individually.
	 *
	 * @param string               $code            Coupon code.
	 * @param array<string, mixed> $product_context Product context.
	 *
	 * @return void
	 */
	private function send_woocommerce_coupon_response(
		string $code,
		array $product_context
	): void {

		if (
			! class_exists(
				'WC_Coupon'
			)
		) {
			wp_send_json_error(
				array(
					'error_code' =>
						'woocommerce_coupon_unavailable',

					'message' =>
						__(
							'WooCommerce coupons are not available.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		$coupon =
			new \WC_Coupon(
				$code
			);

		if ( ! $coupon->get_id() ) {
			wp_send_json_error(
				array(
					'error_code' =>
						'invalid_coupon',

					'message' =>
						__(
							'Invalid coupon code.',
							'eilmo-checkout-flow'
						),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'valid' =>
					true,

				'applied' =>
					false,

				'source' =>
					'woocommerce',

				'delegate_to_woocommerce' =>
					true,

				'coupon_code' =>
					$code,

				'coupon_type' =>
					sanitize_key(
						(string) $coupon
							->get_discount_type()
					),

				'coupon_discount' =>
					0.0,

				'free_delivery' =>
					(bool) $coupon
						->get_free_shipping(),

				'product_total' =>
					$this->normalize_amount(
						$product_context[
							'product_total'
						] ??
							0
					),

				'message' =>
					__(
						'WooCommerce coupon recognized.',
						'eilmo-checkout-flow'
					),
			)
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * Indexed collections must preserve stored values
	 * exactly instead of being recursively merged with
	 * default collection indexes.
	 *
	 * @return array<string, mixed>
	 */
	private function get_plugin_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$settings =
			array_replace_recursive(
				$defaults,
				$stored
			);

		$indexed_paths =
			array(
				array(
					'combo_offers',
					'offers',
				),

				array(
					'order_bumps',
					'offers',
				),

				array(
					'discounts',
					'automatic_rules',
				),

				array(
					'coupons',
					'custom_coupons',
				),
			);

		foreach ( $indexed_paths as $path ) {

			$section =
				$path[0];

			$key =
				$path[1];

			if (
				isset(
					$stored[
						$section
					]
				) &&
				is_array(
					$stored[
						$section
					]
				) &&
				array_key_exists(
					$key,
					$stored[
						$section
					]
				) &&
				is_array(
					$stored[
						$section
					][
						$key
					]
				)
			) {
				$settings[
					$section
				][
					$key
				] =
					$stored[
						$section
					][
						$key
					];
			}
		}

		return $settings;
	}

	/**
	 * Normalize stock quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
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

		if ( ! is_numeric( $quantity ) ) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
	}

	/**
	 * Normalize monetary amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return float
	 */
	private function normalize_amount(
		$amount
	): float {

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount =
				wc_format_decimal(
					$amount,
					false
				);
		}

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			max(
				0,
				(float) $amount
			),
			$decimals
		);
	}
}
