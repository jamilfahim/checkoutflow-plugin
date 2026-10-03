<?php
/**
 * Order validator.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Payment\Services\PaymentProof;
use EilmoCheckout\Payment\Gateways\GatewaySettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Performs authoritative server-side validation
 * before an Eilmo checkout order can be created.
 *
 * Client-provided prices, discounts and totals are
 * intentionally not trusted here.
 */
final class OrderValidator {

	/**
	 * Validate checkout payload.
	 *
	 * Returns a normalized payload on success.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function validate(
		array $payload
	) {

		$errors =
			new WP_Error();

		if (
			! function_exists( 'wc_get_product' )
		) {
			$errors->add(
				'woocommerce_unavailable',
				__(
					'WooCommerce is not available.',
					'eilmo-checkout-flow'
				)
			);

			return $errors;
		}

		$advance_payment =
			$this->validate_advance_payment(
				$payload,
				$errors
			);

		$payment_type =
			sanitize_key(
				(string) (
					$advance_payment[
						'payment_type'
					] ??
						'full'
				)
			);

		$normalized = array(
			'items' =>
				$this->validate_items(
					$payload['items'] ??
						array(),
					$errors
				),

			'customer' =>
				$this->validate_customer(
					isset( $payload['customer'] ) &&
					is_array( $payload['customer'] )
						? $payload['customer']
						: array(),
					$errors
				),

			'delivery' =>
				$this->validate_delivery(
					$payload,
					$errors
				),

			'advance_payment' =>
				$advance_payment,

			'payment' =>
				$this->validate_payment(
					$payload,
					$payment_type,
					$errors
				),

			'coupon_code' =>
				$this->normalize_coupon_code(
					$payload['coupon_code'] ??
						''
				),
		);

		/**
		 * Filters normalized order payload before
		 * final validation result is returned.
		 *
		 * This allows other Eilmo modules to attach
		 * additional normalized data without making
		 * OrderValidator responsible for calculation.
		 *
		 * @param array<string, mixed> $normalized Normalized data.
		 * @param array<string, mixed> $payload    Raw payload.
		 * @param WP_Error             $errors     Validation errors.
		 */
		$filtered =
			apply_filters(
				'eilmo_cf/orders/validated_payload',
				$normalized,
				$payload,
				$errors
			);

		if ( is_array( $filtered ) ) {
			$normalized =
				$filtered;
		}

		/**
		 * Allows modules such as Coupons, Delivery,
		 * Discounts or custom integrations to append
		 * authoritative validation errors.
		 *
		 * @param WP_Error             $errors     Errors.
		 * @param array<string, mixed> $normalized Normalized data.
		 * @param array<string, mixed> $payload    Raw payload.
		 */
		$filtered_errors =
			apply_filters(
				'eilmo_cf/orders/validation_errors',
				$errors,
				$normalized,
				$payload
			);

		if (
			$filtered_errors instanceof
				WP_Error
		) {
			$errors =
				$filtered_errors;
		}

		if (
			! empty(
				$errors->get_error_codes()
			)
		) {
			return $errors;
		}

		return $normalized;
	}

	/**
	 * Validate order items.
	 *
	 * Only product identity and quantity are accepted.
	 * Client prices/totals are deliberately ignored.
	 *
	 * @param mixed    $items  Raw items.
	 * @param WP_Error $errors Errors.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_items(
		$items,
		WP_Error $errors
	): array {

		if (
			! is_array( $items ) ||
			empty( $items )
		) {
			$errors->add(
				'no_products_selected',
				__(
					'Please select at least one product.',
					'eilmo-checkout-flow'
				)
			);

			return array();
		}

		$normalized =
			array();

		/*
		 * Merge duplicate product/variation rows.
		 *
		 * This also prevents splitting one quantity
		 * across duplicate rows to bypass stock checks.
		 */
		$grouped =
			array();

		foreach ( $items as $item ) {

			if ( ! is_array( $item ) ) {
				$errors->add(
					'invalid_product_item',
					__(
						'One of the selected product entries is invalid.',
						'eilmo-checkout-flow'
					)
				);

				continue;
			}

			$product_id =
				$this->normalize_product_id(
					$item['product_id'] ??
						0,
					false
				);

			$variation_id =
				$this->normalize_product_id(
					$item['variation_id'] ??
						0,
					true
				);

			$quantity =
				$this->normalize_quantity(
					$item['quantity'] ??
						0
				);

			if (
				$product_id <= 0 ||
				$variation_id < 0 ||
				$quantity <= 0
			) {
				$errors->add(
					'invalid_product_item',
					__(
						'One of the selected product entries is invalid.',
						'eilmo-checkout-flow'
					)
				);

				continue;
			}

			$key =
				sprintf(
					'%1$d:%2$d',
					$product_id,
					$variation_id
				);

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						0,
				);
			}

			$combined_quantity =
				(float) $grouped[ $key ]['quantity'] +
				$quantity;

			if (
				! is_finite(
					$combined_quantity
				) ||
				$combined_quantity <= 0
			) {
				$errors->add(
					'invalid_product_quantity',
					__(
						'One of the selected product quantities is invalid.',
						'eilmo-checkout-flow'
					)
				);

				unset( $grouped[ $key ] );

				continue;
			}

			$grouped[ $key ]['quantity'] =
				$combined_quantity;
		}

		if ( empty( $grouped ) ) {
			$errors->add(
				'no_valid_products',
				__(
					'Please select a valid product quantity.',
					'eilmo-checkout-flow'
				)
			);

			return array();
		}

		foreach ( $grouped as $item ) {

			$product_id =
				(int) $item['product_id'];

			$variation_id =
				(int) $item['variation_id'];

			$quantity =
				(float) $item['quantity'];

			$product =
				wc_get_product(
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if ( ! $product ) {
				$errors->add(
					'invalid_product',
					__(
						'One of the selected products is no longer available.',
						'eilmo-checkout-flow'
					)
				);

				continue;
			}

			/*
			 * Variable parent products require an
			 * actual variation to be selected.
			 */
			if (
				$variation_id <= 0 &&
				$product->is_type(
					'variable'
				)
			) {
				$errors->add(
					'variation_required',
					sprintf(
						/* translators: %s: Product name. */
						__(
							'Please select an option for %s.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);

				continue;
			}

			/*
			 * Ensure submitted variation belongs to the
			 * submitted parent product.
			 */
			if ( $variation_id > 0 ) {

				if (
					! $product->is_type(
						'variation'
					)
				) {
					$errors->add(
						'invalid_variation',
						__(
							'An invalid product variation was selected.',
							'eilmo-checkout-flow'
						)
					);

					continue;
				}

				if (
					(int) $product->get_parent_id() !==
						$product_id
				) {
					$errors->add(
						'variation_parent_mismatch',
						__(
							'The selected product variation does not belong to this product.',
							'eilmo-checkout-flow'
						)
					);

					continue;
				}
			}

			if (
				! $product->is_purchasable()
			) {
				$errors->add(
					'product_not_purchasable',
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s cannot currently be purchased.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);

				continue;
			}

			if (
				! $product->is_in_stock()
			) {
				$errors->add(
					'product_out_of_stock',
					sprintf(
						/* translators: %s: Product name. */
						__(
							'%s is currently out of stock.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);

				continue;
			}

			if (
				$product->is_sold_individually() &&
				$quantity > 1
			) {
				$errors->add(
					'product_sold_individually',
					sprintf(
						/* translators: %s: Product name. */
						__(
							'Only one %s can be purchased per order.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);

				continue;
			}

			if (
				$product->managing_stock() &&
				! $product->has_enough_stock(
					$quantity
				)
			) {
				$errors->add(
					'insufficient_stock',
					sprintf(
						/* translators: %s: Product name. */
						__(
							'There is not enough stock available for %s.',
							'eilmo-checkout-flow'
						),
						$product->get_name()
					)
				);

				continue;
			}

			$normalized[] = array(
				'product_id' =>
					$product_id,

				'variation_id' =>
					$variation_id,

				'quantity' =>
					$quantity,
			);
		}

		if (
			empty( $normalized ) &&
			empty(
				$errors->get_error_codes()
			)
		) {
			$errors->add(
				'no_valid_products',
				__(
					'No valid products were selected.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array_values(
			$normalized
		);
	}

	/**
	 * Validate customer information.
	 *
	 * Required fields are determined from the current
	 * Customer Information backend settings.
	 *
	 * @param array<string, mixed> $customer Customer data.
	 * @param WP_Error             $errors   Errors.
	 *
	 * @return array<string, string>
	 */
	private function validate_customer(
		array $customer,
		WP_Error $errors
	): array {

		$settings =
			$this->get_section_settings(
				'customer_information'
			);

		$customer =
			$this->normalize_customer(
				$customer
			);

		/*
		 * Use current logged-in WooCommerce/customer
		 * profile as a safe server-side fallback.
		 */
		if (
			'yes' === (
				$settings['autofill_logged_in'] ??
					'yes'
			) &&
			is_user_logged_in()
		) {
			$customer =
				$this->populate_customer_fallbacks(
					$customer
				);
		}

		if (
			'yes' !== (
				$settings['enabled'] ??
					'yes'
			)
		) {
			return $customer;
		}

		$fields =
			isset( $settings['fields'] ) &&
			is_array( $settings['fields'] )
				? $settings['fields']
				: array();

		$field_map =
			$this->get_customer_field_map();

		foreach ( $fields as $field_key => $field ) {

			if (
				! is_array( $field ) ||
				'yes' !== (
					$field['enabled'] ??
						'no'
				)
			) {
				continue;
			}

			$customer_key =
				$field_map[ $field_key ] ??
					'';

			if ( '' === $customer_key ) {
				continue;
			}

			$value =
				trim(
					(string) (
						$customer[ $customer_key ] ??
							''
					)
				);

			if (
				'yes' === (
					$field['required'] ??
						'no'
				) &&
				'' === $value
			) {
				$label =
					sanitize_text_field(
						(string) (
							$field['label'] ??
								$field_key
						)
					);

				$errors->add(
					'customer_field_required_' .
						sanitize_key(
							$field_key
						),
					sprintf(
						/* translators: %s: Field label. */
						__(
							'%s is required.',
							'eilmo-checkout-flow'
						),
						$label
					)
				);
			}
		}

		$email =
			(string) (
				$customer['billing_email'] ??
					''
			);

		if (
			'' !== $email &&
			! is_email( $email )
		) {
			$errors->add(
				'invalid_customer_email',
				__(
					'Please enter a valid email address.',
					'eilmo-checkout-flow'
				)
			);
		}

		$country =
			(string) (
				$customer['billing_country'] ??
					''
			);

		if (
			'' !== $country &&
			! $this->is_valid_country(
				$country
			)
		) {
			$errors->add(
				'invalid_billing_country',
				__(
					'Please select a valid country.',
					'eilmo-checkout-flow'
				)
			);
		}

		return $customer;
	}

	/**
	 * Validate delivery method.
	 *
	 * Delivery charge is intentionally not accepted
	 * from the browser. Order creation will calculate
	 * the authoritative amount again.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param WP_Error             $errors  Errors.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_delivery(
		array $payload,
		WP_Error $errors
	): array {

		$settings =
			$this->get_section_settings(
				'delivery'
			);

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return array(
				'method_id' =>
					'',
			);
		}

		$delivery =
			isset( $payload['delivery'] ) &&
			is_array( $payload['delivery'] )
				? $payload['delivery']
				: array();

		$method_id =
			sanitize_key(
				(string) (
					$delivery['method_id'] ??
					$payload['delivery_method'] ??
						''
				)
			);

		if (
			'' === $method_id &&
			'yes' === (
				$settings['required'] ??
					'yes'
			)
		) {
			$errors->add(
				'delivery_method_required',
				__(
					'Please select a delivery method.',
					'eilmo-checkout-flow'
				)
			);

			return array(
				'method_id' =>
					'',
			);
		}

		if ( '' === $method_id ) {
			return array(
				'method_id' =>
					'',
			);
		}

		$methods =
			isset( $settings['methods'] ) &&
			is_array( $settings['methods'] )
				? $settings['methods']
				: array();

		$found =
			false;

		foreach ( $methods as $method ) {

			if (
				! is_array( $method ) ||
				'yes' !== (
					$method['enabled'] ??
						'no'
				)
			) {
				continue;
			}

			if (
				$method_id ===
				sanitize_key(
					(string) (
						$method['id'] ??
							''
					)
				)
			) {
				$found =
					true;

				break;
			}
		}

		if ( ! $found ) {
			$errors->add(
				'invalid_delivery_method',
				__(
					'The selected delivery method is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'method_id' =>
				$method_id,
		);
	}

	/**
	 * Validate the selected top-level Payment Option.
	 *
	 * Supported Payment Options:
	 *
	 * - Cash on Delivery.
	 * - Advance Payment.
	 * - Full Payment.
	 *
	 * No pay-now amount is accepted from the client.
	 * Availability is always reloaded from current
	 * server-side settings.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param WP_Error             $errors  Errors.
	 *
	 * @return array<string, string>
	 */
	private function validate_advance_payment(
		array $payload,
		WP_Error $errors
	): array {

		$settings =
			$this->get_section_settings(
				'advance_payment'
			);

		/*
		 * Historical compatibility:
		 *
		 * When the Payment Options section itself is
		 * disabled, Eilmo keeps the old behavior where
		 * the complete order amount is payable normally.
		 */
		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return array(
				'payment_type' =>
					'full',
			);
		}

		$available =
			array();

		if (
			'yes' === (
				$settings[
					'allow_cash_on_delivery'
				] ??
					'no'
			)
		) {
			$available[] =
				'cash_on_delivery';
		}

		if (
			'yes' === (
				$settings[
					'allow_advance_payment'
				] ??
					'yes'
			)
		) {
			$available[] =
				'advance';
		}

		if (
			'yes' === (
				$settings[
					'allow_full_payment'
				] ??
					'yes'
			)
		) {
			$available[] =
				'full';
		}

		/*
		 * Settings sanitization should never allow this,
		 * but keep a hard server-side guard in case the
		 * option is modified directly or through filters.
		 */
		if ( empty( $available ) ) {
			$errors->add(
				'payment_options_unavailable',
				__(
					'No payment options are currently available for this checkout.',
					'eilmo-checkout-flow'
				)
			);

			return array(
				'payment_type' =>
					'advance',
			);
		}

		$advance =
			isset(
				$payload['advance_payment']
			) &&
			is_array(
				$payload['advance_payment']
			)
				? $payload['advance_payment']
				: array();

		$payment_type =
			sanitize_key(
				(string) (
					$advance['payment_type'] ??
					$payload['payment_type'] ??
					$settings['default_payment_type'] ??
						''
				)
			);

		if (
			! in_array(
				$payment_type,
				array(
					'cash_on_delivery',
					'advance',
					'full',
				),
				true
			)
		) {
			$errors->add(
				'invalid_payment_type',
				__(
					'Please select a valid payment option.',
					'eilmo-checkout-flow'
				)
			);

			$payment_type =
				(string) reset(
					$available
				);
		}

		if (
			! in_array(
				$payment_type,
				$available,
				true
			)
		) {
			switch ( $payment_type ) {
				case 'cash_on_delivery':
					$errors->add(
						'cash_on_delivery_not_allowed',
						__(
							'Cash on Delivery is not currently available for this checkout.',
							'eilmo-checkout-flow'
						)
					);
					break;

				case 'full':
					$errors->add(
						'full_payment_not_allowed',
						__(
							'Full payment is not currently available for this checkout.',
							'eilmo-checkout-flow'
						)
					);
					break;

				case 'advance':
				default:
					$errors->add(
						'advance_payment_not_allowed',
						__(
							'Advance payment is not currently available for this checkout.',
							'eilmo-checkout-flow'
						)
					);
					break;
			}

			/*
			 * Continue validation with a valid server-side
			 * fallback. The accumulated error still causes
			 * the checkout request to fail.
			 */
			$payment_type =
				(string) reset(
					$available
				);
		}

		return array(
			'payment_type' =>
				$payment_type,
		);
	}

	/**
	 * Validate selected Payment Method.
	 *
	 * Cash on Delivery is not a Payment Method choice
	 * anymore. It is a top-level Payment Option.
	 *
	 * Therefore:
	 *
	 * - COD receives a canonical internal Eilmo payment
	 *   payload without trusting the browser method.
	 *
	 * - Advance / Full require a non-COD Payment Method
	 *   whenever Payment Options are enabled.
	 *
	 * @param array<string, mixed> $payload      Payload.
	 * @param string               $payment_type Validated Payment Option.
	 * @param WP_Error             $errors       Errors.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_payment(
		array $payload,
		string $payment_type,
		WP_Error $errors
	): array {

		/*
		 * Cash on Delivery is authoritative from the
		 * validated Payment Option, not the browser's
		 * submitted Payment Method fields.
		 */
		if (
			'cash_on_delivery' ===
				$payment_type
		) {
			return array(
				'method' =>
					'eilmo_cash_on_delivery',

				'method_key' =>
					'cash_on_delivery',

				'source' =>
					( '' !== $gateway_id ? 'eilmo_gateway' : 'eilmo_manual' ),

				'gateway_id' =>
					$gateway_id,

				'transaction_id' =>
					'',

				'proof_token' =>
					'',
			);
		}

		$settings =
			$this->get_section_settings(
				'payment_methods'
			);

		$payment_option_settings =
			$this->get_section_settings(
				'advance_payment'
			);

		$payment_options_enabled =
			'yes' === (
				$payment_option_settings[
					'enabled'
				] ??
					'no'
			);

		/*
		 * Advance / Full selected from Payment Options
		 * must always have an actual non-COD payment
		 * method. A disabled Payment Methods section is
		 * therefore invalid for that configuration.
		 *
		 * If Payment Options itself is disabled, preserve
		 * the legacy Payment Methods master switch.
		 */
		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			if (
				$payment_options_enabled &&
				in_array(
					$payment_type,
					array(
						'advance',
						'full',
					),
					true
				)
			) {
				$errors->add(
					'payment_methods_unavailable',
					__(
						'No payment methods are currently available for the selected payment option.',
						'eilmo-checkout-flow'
					)
				);
			}

			return $this->get_empty_payment_data();
		}

		$payment =
			isset( $payload['payment'] ) &&
			is_array( $payload['payment'] )
				? $payload['payment']
				: array();

		$method =
			sanitize_text_field(
				(string) (
					$payment['method'] ??
					$payload['payment_method'] ??
						''
				)
			);

		$transaction_id =
			sanitize_text_field(
				(string) (
					$payment['transaction_id'] ??
					$payload['payment_transaction_id'] ??
						''
				)
			);

		$proof_token = sanitize_text_field(
			(string) ( $payment['proof_token'] ?? $payload['payment_proof_token'] ?? '' )
		);

		$required =
			$payment_options_enabled &&
			in_array(
				$payment_type,
				array(
					'advance',
					'full',
				),
				true
			)
				? true
				: (
					'yes' === (
						$settings['required'] ??
							'yes'
					)
				);

		if ( '' === $method ) {
			if ( $required ) {
				$errors->add(
					'payment_method_required',
					__(
						'Please select a payment method.',
						'eilmo-checkout-flow'
					)
				);
			}

			return $this->get_empty_payment_data();
		}

		/*
		 * Never allow COD to be smuggled in as a
		 * Payment Method while Advance or Full is the
		 * selected top-level Payment Option.
		 */
		if (
			$this->is_cash_on_delivery_payment_method(
				$method
			)
		) {
			$errors->add(
				'cash_on_delivery_method_not_allowed',
				__(
					'Cash on Delivery cannot be used as the payment method for Advance or Full Payment.',
					'eilmo-checkout-flow'
				)
			);

			return $this->get_empty_payment_data();
		}

		if (
			'eilmo_bank_transfer' ===
				$method
		) {
			return $this->validate_bank_transfer(
				$settings,
				$method,
				$transaction_id,
				$proof_token,
				$errors
			);
		}

		if (
			0 === strpos(
				$method,
				'eilmo_custom__'
			)
		) {
			return $this->validate_custom_payment_method(
				$settings,
				$method,
				$transaction_id,
				$proof_token,
				$errors
			);
		}

		if (
			0 === strpos(
				$method,
				'wc__'
			)
		) {
			return $this->validate_woocommerce_gateway(
				$settings,
				$method,
				$errors
			);
		}

		$errors->add(
			'invalid_payment_method',
			__(
				'The selected payment method is invalid.',
				'eilmo-checkout-flow'
			)
		);

		return $this->get_empty_payment_data();
	}

	/**
	 * Get an empty normalized Payment Method payload.
	 *
	 * @return array<string, string>
	 */
	private function get_empty_payment_data(): array {

		return array(
			'method' =>
				'',

			'method_key' =>
				'',

			'source' =>
				'',

			'gateway_id' =>
				'',

			'transaction_id' =>
				'',

			'proof_token' =>
				'',
		);
	}

	/**
	 * Determine whether a submitted Payment Method
	 * represents Cash on Delivery.
	 *
	 * This blocks:
	 *
	 * - Eilmo's historical COD method.
	 * - WooCommerce native COD.
	 * - Reserved custom COD identifiers.
	 *
	 * @param string $method Payment Method.
	 *
	 * @return bool
	 */
	private function is_cash_on_delivery_payment_method(
		string $method
	): bool {

		$method =
			trim(
				$method
			);

		if (
			in_array(
				$method,
				array(
					'eilmo_cash_on_delivery',
					'cash_on_delivery',
					'cod',
					'wc__cod',
				),
				true
			)
		) {
			return true;
		}

		if (
			0 === strpos(
				$method,
				'eilmo_custom__'
			)
		) {
			$method_key =
				sanitize_key(
					substr(
						$method,
						strlen(
							'eilmo_custom__'
						)
					)
				);

			return in_array(
				$method_key,
				array(
					'cod',
					'cash_on_delivery',
				),
				true
			);
		}

		if (
			0 === strpos(
				$method,
				'wc__'
			)
		) {
			$gateway_id =
				sanitize_key(
					substr(
						$method,
						strlen(
							'wc__'
						)
					)
				);

			return (
				'cod' ===
					$gateway_id
			);
		}

		return false;
	}

	/**
	 * Validate Eilmo Bank Transfer.
	 *
	 * @param array<string, mixed> $settings       Settings.
	 * @param string               $method         Method.
	 * @param string               $transaction_id Transaction ID.
	 * @param string               $proof_token    Temporary proof token.
	 * @param WP_Error             $errors         Errors.
	 *
	 * @return array<string, string>
	 */
	private function validate_bank_transfer(
		array $settings,
		string $method,
		string $transaction_id,
		string $proof_token,
		WP_Error $errors
	): array {

		$bank =
			isset(
				$settings['bank_transfer']
			) &&
			is_array(
				$settings['bank_transfer']
			)
				? $settings['bank_transfer']
				: array();

		if (
			'yes' !== (
				$bank['enabled'] ??
					'no'
			)
		) {
			$errors->add(
				'bank_transfer_unavailable',
				__(
					'Bank Transfer is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		$transaction_required = 'yes' === ( $bank['transaction_id_required'] ?? 'no' );
		$proof_enabled = 'yes' === ( $bank['payment_proof_enabled'] ?? 'no' );
		$proof_valid = $proof_enabled && PaymentProof::is_valid_token( $proof_token, 'bank_transfer' );

		if ( $proof_enabled && '' === $transaction_id && ! $proof_valid ) {
			$errors->add( 'bank_payment_proof_required', $transaction_required ? __( 'Please enter the bank transaction ID or upload a payment screenshot.', 'eilmo-checkout-flow' ) : __( 'Please upload the bank payment screenshot.', 'eilmo-checkout-flow' ) );
		} elseif ( $transaction_required && ! $proof_enabled && '' === $transaction_id ) {
			$errors->add(
				'bank_transaction_id_required',
				__(
					'Please enter the bank transfer transaction ID.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'method' =>
				$method,

			'method_key' =>
				'bank_transfer',

			'source' =>
				'eilmo_manual',

			'gateway_id' =>
				'',

			'transaction_id' =>
				$transaction_id,

			'proof_token' =>
				$proof_valid ? $proof_token : '',
		);
	}

	/**
	 * Validate custom manual payment method.
	 *
	 * @param array<string, mixed> $settings       Settings.
	 * @param string               $method         Method value.
	 * @param string               $transaction_id Transaction ID.
	 * @param string               $proof_token    Temporary proof token.
	 * @param WP_Error             $errors         Errors.
	 *
	 * @return array<string, string>
	 */
	private function validate_custom_payment_method(
		array $settings,
		string $method,
		string $transaction_id,
		string $proof_token,
		WP_Error $errors
	): array {

		$method_key =
			sanitize_key(
				substr(
					$method,
					strlen(
						'eilmo_custom__'
					)
				)
			);

		$gateway_id = GatewaySettings::gateway_id( $method_key );
		$matched = '' !== $gateway_id ? GatewaySettings::get( $gateway_id ) : null;

		if ( ! is_array( $matched ) ) {
			$methods = isset( $settings['custom_methods'] ) && is_array( $settings['custom_methods'] ) ? $settings['custom_methods'] : array();
			foreach ( $methods as $custom_method ) {
				if ( ! is_array( $custom_method ) ) continue;
				if ( $method_key !== sanitize_key( (string) ( $custom_method['id'] ?? '' ) ) ) continue;
				$matched = $custom_method;
				break;
			}
		}

		if (
			! is_array( $matched ) ||
			'yes' !== (
				$matched['enabled'] ??
					'no'
			)
		) {
			$errors->add(
				'custom_payment_unavailable',
				__(
					'The selected payment method is no longer available.',
					'eilmo-checkout-flow'
				)
			);

			return array(
				'method' =>
					$method,

				'method_key' =>
					$method_key,

				'source' =>
					'eilmo_manual',

				'gateway_id' =>
					'',

				'transaction_id' =>
					$transaction_id,

				'proof_token' =>
					'',
			);
		}

		$transaction_required = 'yes' === ( $matched['transaction_id_required'] ?? 'no' );
		$proof_enabled = 'yes' === ( $matched['payment_proof_enabled'] ?? 'no' );
		$proof_valid = $proof_enabled && PaymentProof::is_valid_token( $proof_token, $method_key );

		if ( $proof_enabled && '' === $transaction_id && ! $proof_valid ) {
			$errors->add(
				'custom_payment_proof_required',
				$transaction_required
					? sprintf( __( 'Please enter the transaction ID or upload a payment screenshot for %s.', 'eilmo-checkout-flow' ), sanitize_text_field( (string) ( $matched['title'] ?? $method_key ) ) )
					: sprintf( __( 'Please upload the payment screenshot for %s.', 'eilmo-checkout-flow' ), sanitize_text_field( (string) ( $matched['title'] ?? $method_key ) ) )
			);
		} elseif ( $transaction_required && ! $proof_enabled && '' === $transaction_id ) {
			$errors->add(
				'custom_transaction_id_required',
				sprintf(
					/* translators: %s: Payment method title. */
					__(
						'Please enter the transaction ID for %s.',
						'eilmo-checkout-flow'
					),
					sanitize_text_field(
						(string) (
							$matched['title'] ??
								$method_key
						)
					)
				)
			);
		}

		return array(
			'method' =>
				$method,

			'method_key' =>
				$method_key,

			'source' =>
				( '' !== $gateway_id ? 'eilmo_gateway' : 'eilmo_manual' ),

			'gateway_id' =>
				$gateway_id,

			'transaction_id' =>
				$transaction_id,

			'proof_token' =>
				$proof_valid ? $proof_token : '',
		);
	}

	/**
	 * Validate WooCommerce payment gateway.
	 *
	 * Gateway-specific fields are validated again
	 * immediately before process_payment().
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $method   Method.
	 * @param WP_Error             $errors   Errors.
	 *
	 * @return array<string, string>
	 */
	private function validate_woocommerce_gateway(
		array $settings,
		string $method,
		WP_Error $errors
	): array {

		$gateway_settings =
			isset(
				$settings['woocommerce_gateways']
			) &&
			is_array(
				$settings['woocommerce_gateways']
			)
				? $settings['woocommerce_gateways']
				: array();

		$gateway_id =
			sanitize_key(
				substr(
					$method,
					strlen( 'wc__' )
				)
			);

		if (
			'yes' !== (
				$gateway_settings['enabled'] ??
					'no'
			) ||
			'' === $gateway_id
		) {
			$errors->add(
				'woocommerce_gateway_unavailable',
				__(
					'The selected payment gateway is no longer available.',
					'eilmo-checkout-flow'
				)
			);

			return array(
				'method' =>
					$method,

				'method_key' =>
					$gateway_id,

				'source' =>
					'woocommerce_gateway',

				'gateway_id' =>
					$gateway_id,

				'transaction_id' =>
					'',
			);
		}

		$available_gateways =
			$this->get_available_woocommerce_gateways();

		if (
			! isset(
				$available_gateways[
					$gateway_id
				]
			)
		) {
			$errors->add(
				'woocommerce_gateway_unavailable',
				__(
					'The selected payment gateway is no longer available.',
					'eilmo-checkout-flow'
				)
			);
		}

		/*
		 * WooCommerce native COD is never a Payment
		 * Method in the new Payment Options architecture.
		 */
		if (
			'cod' === $gateway_id
		) {
			$errors->add(
				'cash_on_delivery_gateway_not_allowed',
				__(
					'Cash on Delivery cannot be selected as a payment gateway for Advance or Full Payment.',
					'eilmo-checkout-flow'
				)
			);
		}

		if (
			'bacs' === $gateway_id &&
			'yes' === (
				$settings['bank_transfer']['enabled'] ??
					'no'
			) &&
			'yes' === (
				$gateway_settings['exclude_duplicate_bacs'] ??
					'yes'
			)
		) {
			$errors->add(
				'duplicate_bacs_gateway',
				__(
					'This bank transfer gateway is not available in the Checkout Flow.',
					'eilmo-checkout-flow'
				)
			);
		}

		return array(
			'method' =>
				$method,

			'method_key' =>
				$gateway_id,

			'source' =>
				'woocommerce_gateway',

			'gateway_id' =>
				$gateway_id,

			'transaction_id' =>
				'',
		);
	}

	/**
	 * Normalize customer fields.
	 *
	 * @param array<string, mixed> $customer Customer.
	 *
	 * @return array<string, string>
	 */
	private function normalize_customer(
		array $customer
	): array {

		$normalized = array(
			'billing_first_name' =>
				sanitize_text_field(
					(string) (
						$customer['billing_first_name'] ??
							''
					)
				),

			'billing_last_name' =>
				sanitize_text_field(
					(string) (
						$customer['billing_last_name'] ??
							''
					)
				),

			'billing_company' =>
				sanitize_text_field(
					(string) (
						$customer['billing_company'] ??
							''
					)
				),

			'billing_phone' =>
				sanitize_text_field(
					(string) (
						$customer['billing_phone'] ??
							''
					)
				),

			'billing_email' =>
				sanitize_email(
					(string) (
						$customer['billing_email'] ??
							''
					)
				),

			'billing_address_1' =>
				sanitize_text_field(
					(string) (
						$customer['billing_address_1'] ??
							''
					)
				),

			'billing_address_2' =>
				sanitize_text_field(
					(string) (
						$customer['billing_address_2'] ??
							''
					)
				),

			'billing_city' =>
				sanitize_text_field(
					(string) (
						$customer['billing_city'] ??
							''
					)
				),

			'billing_state' =>
				sanitize_text_field(
					(string) (
						$customer['billing_state'] ??
							''
					)
				),

			'billing_postcode' =>
				sanitize_text_field(
					(string) (
						$customer['billing_postcode'] ??
							''
					)
				),

			'billing_country' =>
				strtoupper(
					sanitize_key(
						(string) (
							$customer['billing_country'] ??
								''
						)
					)
				),

			'order_comments' =>
				sanitize_textarea_field(
					(string) (
						$customer['order_comments'] ??
							''
					)
				),
		);

		return $normalized;
	}

	/**
	 * Populate logged-in customer fallback values.
	 *
	 * Checkout-entered values always take priority.
	 *
	 * @param array<string, string> $customer Customer.
	 *
	 * @return array<string, string>
	 */
	private function populate_customer_fallbacks(
		array $customer
	): array {
		$customer_settings = $this->get_section_settings( 'customer_information' );
		$has_full_name = '' !== trim( (string) ( $customer['billing_first_name'] ?? '' ) )
			&& 'yes' !== ( $customer_settings['fields']['last_name']['enabled'] ?? 'no' );

		$user_id =
			get_current_user_id();

		if ( $user_id <= 0 ) {
			return $customer;
		}

		$user =
			get_userdata(
				$user_id
			);

		$fallbacks = array(
			'billing_first_name' =>
				get_user_meta(
					$user_id,
					'billing_first_name',
					true
				),

			'billing_last_name' =>
				get_user_meta(
					$user_id,
					'billing_last_name',
					true
				),

			'billing_company' =>
				get_user_meta(
					$user_id,
					'billing_company',
					true
				),

			'billing_phone' =>
				get_user_meta(
					$user_id,
					'billing_phone',
					true
				),

			'billing_email' =>
				get_user_meta(
					$user_id,
					'billing_email',
					true
				),

			'billing_address_1' =>
				get_user_meta(
					$user_id,
					'billing_address_1',
					true
				),

			'billing_address_2' =>
				get_user_meta(
					$user_id,
					'billing_address_2',
					true
				),

			'billing_city' =>
				get_user_meta(
					$user_id,
					'billing_city',
					true
				),

			'billing_state' =>
				get_user_meta(
					$user_id,
					'billing_state',
					true
				),

			'billing_postcode' =>
				get_user_meta(
					$user_id,
					'billing_postcode',
					true
				),

			'billing_country' =>
				get_user_meta(
					$user_id,
					'billing_country',
					true
				),
		);

		if (
			'' ===
				trim(
					(string) (
						$fallbacks['billing_email'] ??
							''
					)
				) &&
			$user
		) {
			$fallbacks['billing_email'] =
				$user->user_email;
		}

		if (
			'' ===
				trim(
					(string) (
						$fallbacks['billing_first_name'] ??
							''
					)
				)
		) {
			$fallbacks['billing_first_name'] =
				get_user_meta(
					$user_id,
					'first_name',
					true
				);
		}

		if (
			'' ===
				trim(
					(string) (
						$fallbacks['billing_last_name'] ??
							''
					)
				)
		) {
			$fallbacks['billing_last_name'] =
				get_user_meta(
					$user_id,
					'last_name',
					true
				);
		}

		foreach ( $fallbacks as $key => $value ) {
			// With a single Full Name field, the submitted name is complete.
			if ( 'billing_last_name' === $key && $has_full_name ) {
				continue;
			}

			if (
				'' !==
					trim(
						(string) (
							$customer[ $key ] ??
								''
						)
					)
			) {
				continue;
			}

			if (
				'billing_email' === $key
			) {
				$customer[ $key ] =
					sanitize_email(
						(string) $value
					);

				continue;
			}

			if (
				'billing_country' === $key
			) {
				$customer[ $key ] =
					strtoupper(
						sanitize_key(
							(string) $value
						)
					);

				continue;
			}

			$customer[ $key ] =
				sanitize_text_field(
					(string) $value
				);
		}

		return $customer;
	}

	/**
	 * Customer settings field map.
	 *
	 * @return array<string, string>
	 */
	private function get_customer_field_map(): array {

		return array(
			'first_name' =>
				'billing_first_name',

			'last_name' =>
				'billing_last_name',

			'company' =>
				'billing_company',

			'phone' =>
				'billing_phone',

			'email' =>
				'billing_email',

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

			'order_notes' =>
				'order_comments',
		);
	}

	/**
	 * Determine whether country code is valid.
	 *
	 * @param string $country Country.
	 *
	 * @return bool
	 */
	private function is_valid_country(
		string $country
	): bool {

		if ( '' === $country ) {
			return true;
		}

		if (
			! function_exists( 'WC' ) ||
			! WC() ||
			! WC()->countries
		) {
			return true;
		}

		$countries =
			WC()->countries->get_countries();

		return is_array( $countries ) &&
			array_key_exists(
				$country,
				$countries
			);
	}

	/**
	 * Get currently available WooCommerce gateways.
	 *
	 * @return array<string, object>
	 */
	private function get_available_woocommerce_gateways(): array {

		if (
			! function_exists( 'WC' ) ||
			! WC() ||
			! WC()->payment_gateways()
		) {
			return array();
		}

		$manager =
			WC()->payment_gateways();

		if (
			! method_exists(
				$manager,
				'get_available_payment_gateways'
			)
		) {
			return array();
		}

		try {

			$gateways =
				$manager
					->get_available_payment_gateways();

		} catch ( \Throwable $throwable ) {

			return array();
		}

		return is_array( $gateways )
			? $gateways
			: array();
	}

	/**
	 * Get a settings section merged with defaults.
	 *
	 * @param string $section Section.
	 *
	 * @return array<string, mixed>
	 */
	private function get_section_settings(
		string $section
	): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$section_defaults =
			isset(
				$defaults[ $section ]
			) &&
			is_array(
				$defaults[ $section ]
			)
				? $defaults[ $section ]
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$section_stored =
			is_array( $stored ) &&
			isset(
				$stored[ $section ]
			) &&
			is_array(
				$stored[ $section ]
			)
				? $stored[ $section ]
				: array();

		$settings =
			array_replace_recursive(
				$section_defaults,
				$section_stored
			);

		/*
		 * Indexed collections need explicit replacement.
		 */
		$indexed_keys = array(
			'methods',
			'rules',
			'custom_methods',
			'automatic_rules',
			'custom_coupons',
		);

		foreach ( $indexed_keys as $key ) {

			if (
				array_key_exists(
					$key,
					$section_stored
				) &&
				is_array(
					$section_stored[ $key ]
				)
			) {
				$settings[ $key ] =
					$section_stored[ $key ];
			}
		}

		return $settings;
	}

	/**
	 * Normalize a browser-supplied product identity.
	 *
	 * Unlike absint(), this must not turn a negative or malformed
	 * value into a different valid WooCommerce product ID.
	 *
	 * @param mixed $value      Value.
	 * @param bool  $allow_zero Whether zero is valid.
	 *
	 * @return int Negative one indicates an invalid value.
	 */
	private function normalize_product_id(
		$value,
		bool $allow_zero
	): int {

		if ( is_int( $value ) ) {
			$integer =
				$value;
		} elseif (
			is_string(
				$value
			) &&
			1 === preg_match(
				'/^[0-9]+$/',
				trim( $value )
			)
		) {
			$integer =
				(int) trim(
					$value
				);
		} else {
			return -1;
		}

		if (
			$integer < 0 ||
			(
				! $allow_zero &&
				0 === $integer
			)
		) {
			return -1;
		}

		return $integer;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			! is_int( $quantity ) &&
			! is_float( $quantity ) &&
			! is_string( $quantity )
		) {
			return 0.0;
		}

		if ( ! is_numeric( $quantity ) ) {
			return 0.0;
		}

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

		$quantity =
			(float) $quantity;

		if (
			! is_finite(
				$quantity
			) ||
			$quantity <= 0
		) {
			return 0.0;
		}

		return $quantity;
	}

	/**
	 * Normalize coupon code.
	 *
	 * Eligibility is revalidated by the Coupon module
	 * during final order creation.
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
			return strtolower(
				wc_format_coupon_code(
					$code
				)
			);
		}

		return strtolower(
			sanitize_text_field(
				$code
			)
		);
	}
}
