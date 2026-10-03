<?php
/**
 * Payment renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Payment\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Payment\Gateways\GatewaySettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders payment methods for the Eilmo checkout.
 *
 * Payment architecture:
 *
 * - Cash on Delivery is a top-level Payment Option
 *   and is never rendered as a Payment Method.
 *
 * - Advance and Full Payment use the Payment Method
 *   section for Bank Transfer, custom manual methods
 *   and native WooCommerce gateways.
 *
 * - Native WooCommerce COD is always excluded from
 *   this section because COD belongs to Payment Options.
 *
 * - Native WooCommerce gateways are selected inside
 *   Eilmo, but actual secure payment is completed
 *   through WooCommerce's native order-pay flow.
 *
 * This keeps Stripe, PayPal and other third-party
 * gateways inside the payment lifecycle they expect.
 */
final class PaymentRenderer {

	/**
	 * Render payment methods.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

		$settings =
			$this->get_settings();
		$settings = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $settings );
		$layout_overrides = isset( $context['layout_overrides'] ) && is_array( $context['layout_overrides'] ) ? $context['layout_overrides'] : array();
		$arrangement = sanitize_key( (string) ( $layout_overrides['payment_methods_arrangement'] ?? '' ) );
		if ( in_array( $arrangement, array( 'list', 'grid' ), true ) ) {
			$settings['display_layout'] = $arrangement;
		}
		foreach ( array( 'desktop' => 6, 'tablet' => 4, 'mobile' => 2 ) as $device => $maximum ) {
			$key = 'payment_methods_columns_' . $device;
			if ( ! empty( $layout_overrides[ $key ] ) ) {
				$settings[ 'columns_' . $device ] = max( 1, min( $maximum, absint( $layout_overrides[ $key ] ) ) );
			}
		}

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return '';
		}

		$payment_option_settings =
			$this->get_payment_option_settings();

		$payment_type =
			$this->resolve_payment_type(
				$payment_option_settings,
				$context
			);

		$hidden_for_cash_on_delivery =
			'cash_on_delivery' ===
				$payment_type;

		$instance_id =
			sanitize_html_class(
				(string) (
					$context['instance_id'] ??
						'eilmo-cf-checkout'
				)
			);

		if ( '' === $instance_id ) {
			$instance_id =
				'eilmo-cf-checkout';
		}

		$methods =
			$this->get_methods(
				$settings
			);

		$requested_method =
			sanitize_text_field(
				(string) (
					$context['payment_method'] ??
					$context['selected_method'] ??
						''
				)
			);

		$selected_method =
			$this->resolve_selected_method(
				$requested_method,
				(string) (
					$settings['default_method'] ??
						'first_available'
				),
				$methods
			);

		$transaction_id =
			sanitize_text_field(
				(string) (
					$context[
						'payment_transaction_id'
					] ??
						''
				)
			);

		$required =
			'yes' === (
				$settings['required'] ??
					'yes'
			);

		$display_layout = sanitize_key( (string) ( $settings['display_layout'] ?? 'list' ) );
		if ( ! in_array( $display_layout, array( 'grid', 'list' ), true ) ) {
			$display_layout = 'list';
		}
		$columns_desktop = max( 1, min( 6, absint( $settings['columns_desktop'] ?? 3 ) ) );
		$columns_tablet = max( 1, min( 4, absint( $settings['columns_tablet'] ?? 2 ) ) );
		$columns_mobile = max( 1, min( 2, absint( $settings['columns_mobile'] ?? 1 ) ) );

		$section_classes = array(
			'eilmo-cf-payment-methods',
			'eilmo-cf-payment-methods--' . $display_layout,
		);

		$section_style = sprintf(
			'--eilmo-cf-payment-method-columns-desktop:%1$d;--eilmo-cf-payment-method-columns-tablet:%2$d;--eilmo-cf-payment-method-columns-mobile:%3$d;',
			$columns_desktop,
			$columns_tablet,
			$columns_mobile
		);

		ob_start();
		?>
		<section
			class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"
			style="<?php echo esc_attr( $section_style ); ?>"
			data-eilmo-payment-methods
			data-required="<?php echo esc_attr( $required ? 'yes' : 'no' ); ?>"
			data-default-method="<?php echo esc_attr( (string) ( $settings['default_method'] ?? 'first_available' ) ); ?>"
			data-selected-method="<?php echo esc_attr( $selected_method ); ?>"
			data-instance-id="<?php echo esc_attr( $instance_id ); ?>"
			data-payment-type="<?php echo esc_attr( $payment_type ); ?>"
			data-payment-option-hidden="<?php echo esc_attr( $hidden_for_cash_on_delivery ? 'yes' : 'no' ); ?>"
			data-layout="<?php echo esc_attr( $display_layout ); ?>"
			<?php if ( $hidden_for_cash_on_delivery ) : ?>
				hidden
				aria-hidden="true"
			<?php endif; ?>
		>

			<div class="eilmo-cf-payment-methods__header">

				<h3 class="eilmo-cf-payment-methods__title">
					<?php
					echo esc_html(
						(string) (
							$settings['title'] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Method' )
						)
					);
					?>
				</h3>

				<p
					class="eilmo-cf-payment-methods__pay-note"
					data-eilmo-payment-methods-pay-note
					hidden
				></p>

			</div>

			<?php if ( empty( $methods ) ) : ?>

				<div
					class="eilmo-cf-payment-methods__empty"
					data-eilmo-payment-empty
				>
					<?php
					echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'No payment methods are currently available.' ) );
					?>
				</div>

			<?php else : ?>

				<div
					class="eilmo-cf-payment-methods__list"
					role="radiogroup"
					aria-label="<?php echo esc_attr( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Method' ) ); ?>"
				>

					<?php foreach ( $methods as $method ) : ?>

						<?php
						$this->render_method(
							$method,
							$selected_method,
							$transaction_id,
							$instance_id,
							$required
						);
						?>

					<?php endforeach; ?>

				</div>

			<?php endif; ?>

			<p
				class="eilmo-cf-payment-methods__error"
				data-eilmo-payment-form-error
				hidden
			></p>

		</section>
		<?php

		$output =
			ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters payment-method HTML.
		 *
		 * @param string               $output   HTML.
		 * @param array<string, mixed> $settings Payment settings.
		 * @param array<string, mixed> $context  Rendering context.
		 */
		return (string) apply_filters(
			'eilmo_cf/payment/html',
			$output,
			$settings,
			$context
		);
	}

	/**
	 * Render one payment method.
	 *
	 * @param array<string, mixed> $method         Payment method.
	 * @param string               $selected_value Selected value.
	 * @param string               $transaction_id Transaction ID.
	 * @param string               $instance_id    Checkout instance.
	 * @param bool                 $required       Required status.
	 *
	 * @return void
	 */
	private function render_method(
		array $method,
		string $selected_value,
		string $transaction_id,
		string $instance_id,
		bool $required
	): void {
        $method = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $method );


		$value =
			sanitize_text_field(
				(string) (
					$method['value'] ??
						''
				)
			);

		if ( '' === $value ) {
			return;
		}

		$method_key =
			sanitize_key(
				(string) (
					$method['key'] ??
						''
				)
			);

		$source =
			sanitize_key(
				(string) (
					$method['source'] ??
						''
				)
			);

		$gateway_id =
			sanitize_key(
				(string) (
					$method['gateway_id'] ??
						''
				)
			);

		$is_woocommerce_gateway =
			'woocommerce_gateway' ===
				$source &&
			'' !==
				$gateway_id;

		$is_selected =
			$value ===
				$selected_value;

		$radio_id =
			sanitize_html_class(
				$instance_id .
				'-payment-' .
				(
					'' !== $method_key
						? $method_key
						: md5( $value )
				)
			);

		$transaction_input_id =
			$radio_id .
				'-transaction-id';

		$proof_input_id = $radio_id . '-payment-proof';

		$transaction_required =
			'yes' === (
				$method[
					'transaction_id_required'
				] ??
					'no'
			);

		$transaction_label =
			trim(
				(string) (
					$method[
						'transaction_id_label'
					] ??
						''
				)
			);

		$transaction_placeholder =
			sanitize_text_field(
				(string) (
					$method[
						'transaction_id_placeholder'
					] ??
						''
				)
			);

		$proof_enabled =
			! $is_woocommerce_gateway &&
			'yes' === ( $method['payment_proof_enabled'] ?? 'no' );

		$proof_label = trim( (string) ( $method['payment_proof_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Screenshot' ) ) );
		$proof_help = trim( (string) ( $method['payment_proof_help'] ?? '' ) );
		$proof_max_mb = max( 1, min( 10, absint( $method['payment_proof_max_mb'] ?? 5 ) ) );

		$account_label =
			trim(
				(string) (
					$method[
						'account_label'
					] ??
						''
				)
			);

		$account_value =
			trim(
				(string) (
					$method[
						'account_value'
					] ??
						''
				)
			);

		$instructions =
			trim(
				(string) (
					$method[
						'instructions'
					] ??
						''
				)
			);

		$secure_notice =
			trim(
				(string) (
					$method[
						'secure_notice'
					] ??
						''
				)
			);

		$has_account =
			'' !== $account_label ||
			'' !== $account_value;

		$has_instructions =
			'' !== $instructions;

		$has_transaction_field =
			'' !== $transaction_label;

		$has_secure_notice =
			$is_woocommerce_gateway &&
			'' !== $secure_notice;

		$has_details =
			$has_account ||
			$has_instructions ||
			$has_transaction_field ||
			$has_secure_notice ||
			$proof_enabled;

		$classes =
			array(
				'eilmo-cf-payment-method',
			);

		if ( $is_selected ) {
			$classes[] =
				'is-selected';
		}

		if ( $is_woocommerce_gateway ) {
			$classes[] =
				'eilmo-cf-payment-method--woocommerce';
		} else {
			$classes[] =
				'eilmo-cf-payment-method--manual';
		}

		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-eilmo-payment-method
			data-method-value="<?php echo esc_attr( $value ); ?>"
			data-method-key="<?php echo esc_attr( $method_key ); ?>"
			data-payment-source="<?php echo esc_attr( $source ); ?>"
			data-gateway-id="<?php echo esc_attr( $gateway_id ); ?>"
			data-native-payment-value="<?php echo esc_attr( $gateway_id ); ?>"
			data-transaction-required="<?php echo esc_attr( $transaction_required ? 'yes' : 'no' ); ?>"
			data-proof-enabled="<?php echo esc_attr( $proof_enabled ? 'yes' : 'no' ); ?>"
			data-proof-max-mb="<?php echo esc_attr( (string) $proof_max_mb ); ?>"
			data-has-details="<?php echo esc_attr( $has_details ? 'yes' : 'no' ); ?>"
		>

			<label
				class="eilmo-cf-payment-method__choice"
				for="<?php echo esc_attr( $radio_id ); ?>"
			>

				<input
					type="radio"
					id="<?php echo esc_attr( $radio_id ); ?>"
					class="eilmo-cf-payment-method__radio"
					name="<?php echo esc_attr( $instance_id . '-payment-method' ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					data-eilmo-payment-radio
					data-eilmo-method-value="<?php echo esc_attr( $value ); ?>"
					data-eilmo-method-key="<?php echo esc_attr( $method_key ); ?>"
					data-eilmo-payment-source="<?php echo esc_attr( $source ); ?>"
					data-eilmo-gateway-id="<?php echo esc_attr( $gateway_id ); ?>"
					<?php checked( $is_selected ); ?>
					<?php if ( $required ) : ?>
						required
					<?php endif; ?>
				>


				<span class="eilmo-cf-payment-method__selected-check" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
						<path d="M5 12.5L9.2 16.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>

				<span class="eilmo-cf-payment-method__content">

					<span class="eilmo-cf-payment-method__heading">

						<span class="eilmo-cf-payment-method__title">
							<?php
							echo esc_html(
								(string) (
									$method['title'] ??
										''
								)
							);
							?>
						</span>

						<?php
						$icon_html =
							$this->get_method_icon_html(
								$method
							);

						if ( '' !== $icon_html ) :
							?>

							<span class="eilmo-cf-payment-method__icon">

								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized internally.
								echo $icon_html;
								?>

							</span>

						<?php endif; ?>

					</span>

					<?php if ( ! empty( $method['description'] ) ) : ?>

						<span class="eilmo-cf-payment-method__description">
							<?php
							echo wp_kses_post(
								(string) $method[
									'description'
								]
							);
							?>
						</span>

					<?php endif; ?>

				</span>

			</label>

			<?php if ( $has_details ) : ?>

				<div
					class="eilmo-cf-payment-method__details"
					data-eilmo-payment-details
					<?php if ( ! $is_selected ) : ?>
						hidden
					<?php endif; ?>
				>

					<?php if ( $has_secure_notice ) : ?>

						<div
							class="eilmo-cf-payment-method__secure-notice"
							data-eilmo-secure-payment-notice
						>

							<span
								class="eilmo-cf-payment-method__secure-icon"
								aria-hidden="true"
							>
								<svg
									width="18"
									height="18"
									viewBox="0 0 24 24"
									fill="none"
									xmlns="http://www.w3.org/2000/svg"
								>
									<path
										d="M7 10V8C7 5.23858 9.23858 3 12 3C14.7614 3 17 5.23858 17 8V10"
										stroke="currentColor"
										stroke-width="1.8"
										stroke-linecap="round"
									/>
									<path
										d="M6 10H18C19.1046 10 20 10.8954 20 12V19C20 20.1046 19.1046 21 18 21H6C4.89543 21 4 20.1046 4 19V12C4 10.8954 4.89543 10 6 10Z"
										stroke="currentColor"
										stroke-width="1.8"
									/>
									<path
										d="M12 14V17"
										stroke="currentColor"
										stroke-width="1.8"
										stroke-linecap="round"
									/>
								</svg>
							</span>

							<span>
								<?php
								echo esc_html(
									$secure_notice
								);
								?>
							</span>

						</div>

					<?php endif; ?>

					<?php if ( $has_account ) : ?>

						<div class="eilmo-cf-payment-method__account">

							<?php if ( '' !== $account_label ) : ?>

								<span class="eilmo-cf-payment-method__account-label">
									<?php
									echo esc_html(
										$account_label
									);
									?>
								</span>

							<?php endif; ?>

							<?php if ( '' !== $account_value ) : ?>

								<strong class="eilmo-cf-payment-method__account-value">
									<?php
									echo esc_html(
										$account_value
									);
									?>
								</strong>

							<?php endif; ?>

						</div>

					<?php endif; ?>

					<?php if ( $has_instructions ) : ?>

						<div class="eilmo-cf-payment-method__instructions">
							<?php
							echo nl2br(
								esc_html(
									$instructions
								)
							);
							?>
						</div>

					<?php endif; ?>

					<?php if ( $has_transaction_field ) : ?>

						<div
							class="eilmo-cf-payment-method__transaction"
							data-eilmo-payment-transaction-wrap
						>

							<label
								class="eilmo-cf-payment-method__transaction-label"
								for="<?php echo esc_attr( $transaction_input_id ); ?>"
							>
								<?php
								echo esc_html(
									$transaction_label
								);
								?>

								<?php if ( $transaction_required && ! $proof_enabled ) : ?>

									<span
										class="eilmo-cf-payment-method__required"
										aria-hidden="true"
									>*</span>

								<?php endif; ?>

							</label>

							<input
								type="text"
								id="<?php echo esc_attr( $transaction_input_id ); ?>"
								class="eilmo-cf-payment-method__transaction-input"
								name="<?php
								echo esc_attr(
									'eilmo_payment_transaction_' .
									$instance_id .
									'_' .
									$method_key
								);
								?>"
								value="<?php
								echo esc_attr(
									$is_selected
										? $transaction_id
										: ''
								);
								?>"
								placeholder="<?php echo esc_attr( $transaction_placeholder ); ?>"
								autocomplete="off"
								data-eilmo-payment-transaction
								<?php if ( $transaction_required && ! $proof_enabled ) : ?>
									required
								<?php endif; ?>
							>

							<span
								class="eilmo-cf-payment-method__field-error"
								data-eilmo-payment-field-error
								hidden
							></span>

						</div>

					<?php endif; ?>

					<?php if ( $proof_enabled ) : ?>

						<div class="eilmo-cf-payment-method__proof" data-eilmo-payment-proof-wrap>
							<label class="eilmo-cf-payment-method__transaction-label" for="<?php echo esc_attr( $proof_input_id ); ?>">
								<?php echo esc_html( '' !== $proof_label ? $proof_label : \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Screenshot' ) ); ?>
							</label>

							<input type="file" id="<?php echo esc_attr( $proof_input_id ); ?>" class="eilmo-cf-payment-method__proof-input" accept="image/jpeg,image/png,image/webp" data-eilmo-payment-proof>
							<input
								type="hidden"
								name="<?php echo esc_attr( 'eilmo_payment_proof_' . $instance_id . '_' . $method_key ); ?>"
								value=""
								data-eilmo-payment-proof-token
							>
							<span class="eilmo-cf-payment-method__proof-status" data-eilmo-payment-proof-status><?php echo esc_html( $proof_help ); ?></span>
							<span class="eilmo-cf-payment-method__field-error" data-eilmo-payment-field-error data-eilmo-payment-proof-error hidden></span>
						</div>

					<?php endif; ?>

				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Get payment settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$payment_defaults =
			isset(
				$defaults[
					'payment_methods'
				]
			) &&
			is_array(
				$defaults[
					'payment_methods'
				]
			)
				? $defaults[
					'payment_methods'
				]
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$payment_stored =
			is_array( $stored ) &&
			isset(
				$stored[
					'payment_methods'
				]
			) &&
			is_array(
				$stored[
					'payment_methods'
				]
			)
				? $stored[
					'payment_methods'
				]
				: array();

		$settings =
			array_replace_recursive(
				$payment_defaults,
				$payment_stored
			);

		/*
		 * Indexed collections must replace defaults.
		 */
		if (
			array_key_exists(
				'custom_methods',
				$payment_stored
			) &&
			is_array(
				$payment_stored[
					'custom_methods'
				]
			)
		) {
			$settings[
				'custom_methods'
			] =
				$payment_stored[
					'custom_methods'
				];
		}

		$settings = apply_filters(
			'eilmo_cf/payment_methods/render_settings',
			$settings
		);

		return is_array( $settings ) ? $settings : $payment_defaults;
	}

	/**
	 * Build all available payment methods.
	 *
	 * @param array<string, mixed> $settings Payment settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_methods(
		array $settings
	): array {

		$methods =
			array();

		/* 2.1+: Bank Transfer is the native WooCommerce BACS gateway. */
		$bank = array( 'enabled' => 'no' );

		$gateway_settings =
			isset(
				$settings[
					'woocommerce_gateways'
				]
			) &&
			is_array(
				$settings[
					'woocommerce_gateways'
				]
			)
				? $settings[
					'woocommerce_gateways'
				]
				: array();

		/*
		 * --------------------------------------------------
		 * Cash on Delivery
		 * --------------------------------------------------
		 *
		 * Intentionally not added here.
		 *
		 * COD is now a top-level Payment Option and must
		 * never appear inside the Payment Method list used
		 * by Advance or Full Payment.
		 */

		/*
		 * --------------------------------------------------
		 * Fixed Bank Transfer
		 * --------------------------------------------------
		 */
		if (
			'yes' === (
				$bank['enabled'] ??
					'no'
			)
		) {
			$methods[] =
				array(
					'value' =>
						'eilmo_bank_transfer',

					'key' =>
						'bank_transfer',

					'source' =>
						'eilmo_manual',

					'gateway_id' =>
						'',

					'title' =>
						(string) (
							$bank['title'] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Bank Transfer' )
						),

					'description' =>
						(string) (
							$bank[
								'description'
							] ??
								''
						),

					'instructions' =>
						$this->build_bank_information(
							$bank
						),

					'account_label' =>
						'',

					'account_value' =>
						'',

					'transaction_id_required' =>
						(string) (
							$bank[
								'transaction_id_required'
							] ??
								'no'
						),

					'transaction_id_label' =>
						(string) (
							$bank[
								'transaction_id_label'
							] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Transaction ID' )
						),

					'transaction_id_placeholder' =>
						(string) (
							$bank[
								'transaction_id_placeholder'
							] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Enter transaction ID' )
						),

					'payment_proof_enabled' => (string) ( $bank['payment_proof_enabled'] ?? 'no' ),
					'payment_proof_label' => (string) ( $bank['payment_proof_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Screenshot' ) ),
					'payment_proof_help' => (string) ( $bank['payment_proof_help'] ?? '' ),
					'payment_proof_max_mb' => absint( $bank['payment_proof_max_mb'] ?? 5 ),

					'icon_url' =>
						'',

					'gateway_icon' =>
						'',

					'secure_notice' =>
						'',

					'sort_order' =>
						absint(
							$bank[
								'sort_order'
							] ??
								20
						),

					'position' =>
						count(
							$methods
						),
				);
		}

		/*
		 * --------------------------------------------------
		 * Native WooCommerce gateways
		 * --------------------------------------------------
		 */
		// Native gateways are the only supported source. The parent section's
		// enabled switch controls visibility; the legacy source switch must not
		// leave advance/full-payment customers without a payment path.
		if ( 'no' !== ( $settings['enabled'] ?? 'yes' ) ) {
			$gateway_methods =
				$this->get_woocommerce_gateway_methods(
					$gateway_settings,
					'yes' === (
						$bank[
							'enabled'
						] ??
							'no'
					)
				);

			foreach (
				$gateway_methods as
				$method
			) {
				$method[
					'position'
				] =
					count(
						$methods
					);

				$methods[] =
					$method;
			}
		}

		/*
		 * --------------------------------------------------
		 * Custom Manual Payment Methods
		 * --------------------------------------------------
		 */
		/* 2.1+: legacy custom methods are replaced by real WooCommerce gateways. */
		$custom_methods = array();

		foreach (
			$custom_methods as
				$custom_method
		) {

			if (
				! is_array(
					$custom_method
				) ||
				'yes' !== (
					$custom_method[
						'enabled'
					] ??
						'no'
				)
			) {
				continue;
			}

			$method_id =
				sanitize_key(
					(string) (
						$custom_method[
							'id'
						] ??
							''
					)
				);

			$title =
				sanitize_text_field(
					(string) (
						$custom_method[
							'title'
						] ??
							''
					)
				);

			if (
				'' === $method_id ||
				'' === $title
			) {
				continue;
			}

			/*
			 * Defensive protection for legacy/custom data.
			 *
			 * COD identifiers are reserved for the
			 * top-level Payment Option and cannot be
			 * recreated as a custom Payment Method.
			 */
			if (
				in_array(
					$method_id,
					array(
						'cash_on_delivery',
						'cod',
					),
					true
				)
			) {
				continue;
			}

			$methods[] =
				array(
					'value' =>
						'eilmo_custom__' .
							$method_id,

					'key' =>
						$method_id,

					'source' =>
						'eilmo_manual',

					'gateway_id' =>
						'',

					'title' =>
						$title,

					'description' =>
						(string) (
							$custom_method[
								'description'
							] ??
								''
						),

					'instructions' =>
						(string) (
							$custom_method[
								'instructions'
							] ??
								''
						),

					'account_label' =>
						(string) (
							$custom_method[
								'account_label'
							] ??
								''
						),

					'account_value' =>
						(string) (
							$custom_method[
								'account_value'
							] ??
								''
						),

					'transaction_id_required' =>
						(string) (
							$custom_method[
								'transaction_id_required'
							] ??
								'no'
						),

					'transaction_id_label' =>
						(string) (
							$custom_method[
								'transaction_id_label'
							] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Transaction ID' )
						),

					'transaction_id_placeholder' =>
						(string) (
							$custom_method[
								'transaction_id_placeholder'
							] ??
								\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Enter transaction ID' )
						),

					'payment_proof_enabled' => (string) ( $custom_method['payment_proof_enabled'] ?? 'no' ),
					'payment_proof_label' => (string) ( $custom_method['payment_proof_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Screenshot' ) ),
					'payment_proof_help' => (string) ( $custom_method['payment_proof_help'] ?? '' ),
					'payment_proof_max_mb' => absint( $custom_method['payment_proof_max_mb'] ?? 5 ),

					'icon_url' =>
						(string) (
							$custom_method[
								'icon_url'
							] ??
								''
						),

					'gateway_icon' =>
						'',

					'secure_notice' =>
						'',

					'sort_order' =>
						absint(
							$custom_method[
								'sort_order'
							] ??
								40
						),

					'position' =>
						count(
							$methods
						),
				);
		}

		usort(
			$methods,
			static function (
				array $first,
				array $second
			): int {

				$sort =
					(int) (
						$first[
							'sort_order'
						] ??
							0
					)
					<=>
					(int) (
						$second[
							'sort_order'
						] ??
							0
					);

				if ( 0 !== $sort ) {
					return $sort;
				}

				return (
					(int) (
						$first[
							'position'
						] ??
							0
					)
					<=>
					(int) (
						$second[
							'position'
						] ??
							0
					)
				);
			}
		);

		return array_values(
			$methods
		);
	}

	/**
	 * Get available WooCommerce payment gateways.
	 *
	 * IMPORTANT:
	 *
	 * Gateway payment_fields() are intentionally NOT
	 * rendered here.
	 *
	 * Native WooCommerce COD is always excluded because
	 * Cash on Delivery is now a top-level Payment Option.
	 *
	 * Actual secure payment happens after Order Now
	 * through WooCommerce's native order-pay flow.
	 *
	 * @param array<string, mixed> $settings           Settings.
	 * @param bool                 $eilmo_bacs_enabled Eilmo BACS enabled.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_woocommerce_gateway_methods(
		array $settings,
		bool $eilmo_bacs_enabled
	): array {

		if (
			! function_exists(
				'WC'
			) ||
			! WC() ||
			! WC()->payment_gateways()
		) {
			return array();
		}

		$gateway_manager =
			WC()->payment_gateways();

		if (
			! method_exists(
				$gateway_manager,
				'get_available_payment_gateways'
			)
		) {
			return array();
		}

		try {

			$gateways =
				$gateway_manager
					->get_available_payment_gateways();

		} catch ( \Throwable $throwable ) {

			return array();
		}

		if (
			! is_array(
				$gateways
			)
		) {
			return array();
		}

		$methods =
			array();

		$base_sort_order =
			absint(
				$settings[
					'sort_order'
				] ??
					30
			);

		$show_description =
			'yes' === (
				$settings[
					'show_description'
				] ??
					'yes'
			);

		/*
		 * WooCommerce COD is always excluded.
		 *
		 * Cash on Delivery now belongs exclusively to
		 * the top-level Payment Options section.
		 */
		$exclude_bacs =
			$eilmo_bacs_enabled &&
			'yes' === (
				$settings[
					'exclude_duplicate_bacs'
				] ??
					'yes'
			);

		$gateway_index =
			0;

		foreach (
			$gateways as
				$gateway_id =>
				$gateway
		) {

			if (
				! is_object(
					$gateway
				)
			) {
				continue;
			}

			$gateway_id =
				sanitize_key(
					(string)
						$gateway_id
				);

			if (
				'' ===
					$gateway_id
			) {
				continue;
			}

			if ( in_array( $gateway_id, array( 'cod', 'eilmo_cf_manual' ), true ) ) {
				continue;
			}

			if (
				$exclude_bacs &&
				'bacs' ===
					$gateway_id
			) {
				continue;
			}

			$title =
				is_callable(
					array(
						$gateway,
						'get_title',
					)
				)
					? wp_strip_all_tags(
						(string)
							$gateway
								->get_title()
					)
					: $gateway_id;

			if (
				'' ===
					trim(
						$title
					)
			) {
				$title =
					$this->format_key_label(
						$gateway_id
					);
			}

			$description =
				'';

			if (
				$show_description &&
				is_callable(
					array(
						$gateway,
						'get_description',
					)
				)
			) {
				$description =
					wp_kses_post(
						(string)
							$gateway
								->get_description()
					);
			}

			$gateway_icon =
				is_callable(
					array(
						$gateway,
						'get_icon',
					)
				)
					? (string)
						$gateway
							->get_icon()
					: '';

			$secure_notice =
				(string)
					apply_filters(
						'eilmo_cf/payment/gateway_secure_notice',
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'You will continue to a secure payment page after placing your order.' ),
						$gateway_id,
						$gateway
					);

			/* Eilmo bKash/Nagad are real Woo gateways, but the Eilmo checkout
			 * renders their manual fields inline so Quick Checkout and native
			 * Payment Options keep the same transaction/proof UX. */
			if ( GatewaySettings::is_eilmo_gateway( $gateway_id ) && is_callable( array( $gateway, 'get_eilmo_payment_config' ) ) ) {
				$config = $gateway->get_eilmo_payment_config();
				$method_key = GatewaySettings::method_key( $gateway_id );
				$methods[] = array(
					'value' => 'eilmo_custom__' . $method_key,
					'key' => $method_key,
					'source' => 'eilmo_gateway',
					'gateway_id' => $gateway_id,
					'title' => $title,
					'description' => (string) ( $config['description'] ?? $description ),
					'instructions' => (string) ( $config['instructions'] ?? '' ),
					'account_label' => (string) ( $config['account_label'] ?? '' ),
					'account_value' => (string) ( $config['account_number'] ?? '' ),
					'transaction_id_required' => (string) ( $config['transaction_id_required'] ?? 'yes' ),
					'transaction_id_label' => (string) ( $config['transaction_id_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Transaction ID' ) ),
					'transaction_id_placeholder' => (string) ( $config['transaction_id_placeholder'] ?? '' ),
					'payment_proof_enabled' => (string) ( $config['payment_proof_enabled'] ?? 'yes' ),
					'payment_proof_label' => (string) ( $config['payment_proof_label'] ?? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Screenshot' ) ),
					'payment_proof_help' => (string) ( $config['payment_proof_help'] ?? '' ),
					'payment_proof_max_mb' => absint( $config['payment_proof_max_mb'] ?? 5 ),
					'icon_url' => (string) ( $config['icon_url'] ?? '' ),
					'gateway_icon' => $gateway_icon,
					'secure_notice' => '',
					'sort_order' => $base_sort_order + $gateway_index,
					'position' => $gateway_index,
				);
				++$gateway_index;
				continue;
			}

			$methods[] =
				array(
					'value' =>
						'wc__' .
							$gateway_id,

					'key' =>
						$gateway_id,

					'source' =>
						'woocommerce_gateway',

					'gateway_id' =>
						$gateway_id,

					'title' =>
						$title,

					'description' =>
						$description,

					'instructions' =>
						'',

					'account_label' =>
						'',

					'account_value' =>
						'',

					'transaction_id_required' =>
						'no',

					'transaction_id_label' =>
						'',

					'transaction_id_placeholder' =>
						'',

					'icon_url' =>
						'',

					'gateway_icon' =>
						$gateway_icon,

					'secure_notice' =>
						$secure_notice,

					'sort_order' =>
						$base_sort_order +
							$gateway_index,

					'position' =>
						$gateway_index,
				);

			++$gateway_index;
		}

		return $methods;
	}


	/**
	 * Get Payment Options settings merged with defaults.
	 *
	 * Historical internal storage key "advance_payment"
	 * is intentionally preserved for compatibility.
	 *
	 * @return array<string, mixed>
	 */
	private function get_payment_option_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$payment_option_defaults =
			isset(
				$defaults[
					'advance_payment'
				]
			) &&
			is_array(
				$defaults[
					'advance_payment'
				]
			)
				? $defaults[
					'advance_payment'
				]
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$payment_option_stored =
			is_array( $stored ) &&
			isset(
				$stored[
					'advance_payment'
				]
			) &&
			is_array(
				$stored[
					'advance_payment'
				]
			)
				? $stored[
					'advance_payment'
				]
				: array();

		return array_replace_recursive(
			$payment_option_defaults,
			$payment_option_stored
		);
	}

	/**
	 * Resolve the current top-level Payment Option.
	 *
	 * The resolved value is only used to prevent an
	 * initial Payment Method flash when Cash on Delivery
	 * is the default/current option. Frontend JavaScript
	 * continues to synchronize later changes.
	 *
	 * @param array<string, mixed> $settings Payment Option settings.
	 * @param array<string, mixed> $context  Rendering context.
	 *
	 * @return string
	 */
	private function resolve_payment_type(
		array $settings,
		array $context
	): string {

		if (
			'yes' !== (
				$settings[
					'enabled'
				] ??
					'no'
			)
		) {
			return '';
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

		if (
			empty(
				$available
			)
		) {
			return '';
		}

		$requested =
			sanitize_key(
				(string) (
					$context[
						'payment_type'
					] ??
						''
				)
			);

		if (
			'' !==
				$requested &&
			in_array(
				$requested,
				$available,
				true
			)
		) {
			return $requested;
		}

		$default =
			sanitize_key(
				(string) (
					$settings[
						'default_payment_type'
					] ??
						'advance'
				)
			);

		if (
			in_array(
				$default,
				$available,
				true
			)
		) {
			return $default;
		}

		return (string)
			reset(
				$available
			);
	}

	/**
	 * Build bank information.
	 *
	 * @param array<string, mixed> $bank Bank settings.
	 *
	 * @return string
	 */
	private function build_bank_information(
		array $bank
	): string {

		$lines =
			array();

		$fields =
			array(
				'bank_name' =>
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Bank' ),

				'account_name' =>
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Account Name' ),

				'account_number' =>
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Account Number / IBAN' ),

				'branch' =>
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Branch' ),

				'routing_swift' =>
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Routing / SWIFT / BIC' ),
			);

		foreach (
			$fields as
			$key =>
			$label
		) {
			$value =
				trim(
					(string) (
						$bank[
							$key
						] ??
							''
					)
				);

			if (
				'' ===
					$value
			) {
				continue;
			}

			$lines[] =
				sprintf(
					'%1$s: %2$s',
					$label,
					$value
				);
		}

		$instructions =
			trim(
				(string) (
					$bank[
						'instructions'
					] ??
						''
				)
			);

		if (
			'' !==
				$instructions
		) {
			if (
				! empty(
					$lines
				)
			) {
				$lines[] =
					'';
			}

			$lines[] =
				$instructions;
		}

		return implode(
			"\n",
			$lines
		);
	}

	/**
	 * Get payment method icon HTML.
	 *
	 * @param array<string, mixed> $method Payment method.
	 *
	 * @return string
	 */
	private function get_method_icon_html(
		array $method
	): string {
		$method_key = sanitize_key( (string) ( $method['key'] ?? '' ) );
		$raw_icon = '' !== (string) ( $method['gateway_icon'] ?? '' )
			? (string) $method['gateway_icon']
			: (string) ( $method['icon_url'] ?? '' );
		if ( 'bkash' === $method_key && ( '' === $raw_icon || false !== stripos( $raw_icon, 'upload.wikimedia.org/wikipedia/en/6/68/Bkash_logo.svg' ) ) ) {
			return sprintf( '<img src="%s" alt="" loading="lazy">', esc_url( GatewaySettings::defaults( GatewaySettings::BKASH )['icon_url'] ) );
		}

		if (
			! empty(
				$method[
					'gateway_icon'
				]
			)
		) {
			return wp_kses_post(
				(string) $method[
					'gateway_icon'
				]
			);
		}

		$icon_url =
			esc_url(
				(string) (
					$method[
						'icon_url'
					] ??
						''
				)
			);

		if (
			'' ===
				$icon_url
		) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" alt="" loading="lazy">',
			esc_url(
				$icon_url
			)
		);
	}

	/**
	 * Resolve initially selected method.
	 *
	 * @param string                           $requested_method Requested method.
	 * @param string                           $default_method   Default method.
	 * @param array<int, array<string, mixed>> $methods          Methods.
	 *
	 * @return string
	 */
	private function resolve_selected_method(
		string $requested_method,
		string $default_method,
		array $methods
	): string {

		if (
			empty(
				$methods
			)
		) {
			return '';
		}

		$requested_method =
			trim(
				$requested_method
			);

		if (
			'' !==
				$requested_method
		) {
			foreach (
				$methods as
				$method
			) {
				if (
					$this->method_matches(
						$method,
						$requested_method
					)
				) {
					return (string) (
						$method[
							'value'
						] ??
							''
					);
				}
			}
		}

		if (
			'' !==
				$default_method &&
			'first_available' !==
				$default_method
		) {
			foreach (
				$methods as
				$method
			) {
				if (
					$this->method_matches(
						$method,
						$default_method
					)
				) {
					return (string) (
						$method[
							'value'
						] ??
							''
					);
				}
			}
		}

		return (string) (
			$methods[0][
				'value'
			] ??
				''
		);
	}

	/**
	 * Determine whether method matches identifier.
	 *
	 * @param array<string, mixed> $method     Method.
	 * @param string               $identifier Identifier.
	 *
	 * @return bool
	 */
	private function method_matches(
		array $method,
		string $identifier
	): bool {

		$value =
			(string) (
				$method[
					'value'
				] ??
					''
			);

		$key =
			(string) (
				$method[
					'key'
				] ??
					''
			);

		$gateway_id =
			(string) (
				$method[
					'gateway_id'
				] ??
					''
			);

		return (
			$identifier === $value ||
			$identifier === $key ||
			(
				'' !== $gateway_id &&
				$identifier ===
					$gateway_id
			)
		);
	}

	/**
	 * Format machine key as human label.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function format_key_label(
		string $value
	): string {

		return ucwords(
			str_replace(
				array(
					'_',
					'-',
				),
				' ',
				$value
			)
		);
	}
}
