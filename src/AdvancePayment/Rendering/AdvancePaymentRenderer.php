<?php
/**
 * Payment Options renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\AdvancePayment\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\AdvancePayment\Services\AdvanceCalculator;

defined( 'ABSPATH' ) || exit;

/**
 * Renders frontend Payment Options.
 */
final class AdvancePaymentRenderer {

	/**
	 * Advance calculator.
	 *
	 * @var AdvanceCalculator
	 */
	private $calculator;

	/**
	 * Constructor.
	 *
	 * @param AdvanceCalculator|null $calculator Advance calculator.
	 */
	public function __construct( ?AdvanceCalculator $calculator = null ) {
		$this->calculator = $calculator
			? $calculator
			: new AdvanceCalculator();
	}

	/**
	 * Render Payment Options section.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render( array $context = array() ): string {
		$advance_settings = $this->get_advance_settings();

		if ( 'yes' !== ( $advance_settings['enabled'] ?? 'no' ) ) {
			return '';
		}

		$payment_method_settings =
			$this->get_payment_method_settings();

		$cash_on_delivery_settings = (
			isset( $payment_method_settings['cash_on_delivery'] ) &&
			is_array( $payment_method_settings['cash_on_delivery'] )
		)
			? $payment_method_settings['cash_on_delivery']
			: array();

		$discount_settings = $this->get_discount_settings();

		$full_payment_settings = (
			isset( $discount_settings['full_payment'] ) &&
			is_array( $discount_settings['full_payment'] )
		)
			? $discount_settings['full_payment']
			: array();

		$instance_id = $this->get_instance_id( $context );

		$payment_type = $this->get_payment_type(
			$advance_settings,
			$context
		);

		$product_total = $this->normalize_amount(
			$context['product_total'] ?? 0
		);

		$discounted_product_total = $this->normalize_amount(
			$context['discounted_product_total'] ?? $product_total
		);

		$delivery_charge = $this->normalize_amount(
			$context['delivery_charge'] ?? 0
		);

		$grand_total = $this->normalize_amount(
			$context['grand_total'] ??
			( $discounted_product_total + $delivery_charge )
		);

		$calculation_context = array_merge(
			$context,
			array(
				'product_total'            => $product_total,
				'discounted_product_total' => $discounted_product_total,
				'delivery_charge'          => $delivery_charge,
				'grand_total'              => $grand_total,
				'payment_type'             => $payment_type,
			)
		);

		$result = $this->calculator->calculate(
			$calculation_context
		);

		if ( ! is_array( $result ) ) {
			$result = array();
		}

		/*
		 * Always calculate an independent Advance preview.
		 *
		 * The currently selected Payment Option may be COD or Full.
		 * Those flows correctly have an authoritative advance amount of
		 * zero, but the Advance card must still advertise what the
		 * customer would need to pay if they switch to Advance.
		 *
		 * Therefore the card preview is calculated separately with
		 * payment_type forced to "advance". The selected-flow result
		 * above remains untouched and authoritative for checkout state.
		 */
		$advance_preview_context =
			$calculation_context;

		$advance_preview_context['payment_type'] =
			'advance';

		$advance_preview_result =
			$this->calculator->calculate(
				$advance_preview_context
			);

		if ( ! is_array( $advance_preview_result ) ) {
			$advance_preview_result =
				array();
		}

		/*
		 * No product selected.
		 *
		 * Delivery charge alone must never become
		 * an advance payment amount.
		 */
		if ( $product_total <= 0 ) {
			$result['rule_id'] =
				'';

			$result['rule_type'] =
				'empty_order';

			$result['advance_amount'] =
				0.0;

			$result['pay_now'] =
				0.0;

			$result['remaining_due'] =
				0.0;

			$advance_preview_result['rule_id'] =
				'';

			$advance_preview_result['rule_type'] =
				'empty_order';

			$advance_preview_result['advance_amount'] =
				0.0;

			$advance_preview_result['pay_now'] =
				0.0;

			$advance_preview_result['remaining_due'] =
				0.0;
		}

		/*
		 * Initial server-side full-payment
		 * discount preview.
		 */
		$full_payment_discount =
			$this->calculate_full_payment_discount(
				$full_payment_settings,
				$product_total,
				$discounted_product_total,
				$grand_total,
				$delivery_charge
			);

		/*
		 * Raw discount configuration consumed
		 * by advance.js.
		 */
		$full_payment_frontend_config =
			$this->get_full_payment_frontend_config(
				$full_payment_settings
			);

		/*
		 * Initial Cash on Delivery selection.
		 *
		 * Nothing is payable now. The complete order total
		 * remains due on delivery.
		 */
		if ( 'cash_on_delivery' === $payment_type ) {
			$result['advance_amount'] =
				0.0;

			$result['pay_now'] =
				0.0;

			$result['remaining_due'] =
				$product_total > 0
					? $grand_total
					: 0.0;

			$result['is_cash_on_delivery'] =
				true;

			$result['is_full_payment'] =
				false;

			$result['is_advance_payment'] =
				false;
		}

		/*
		 * Initial Full Payment selection.
		 */
		if (
			'full' === $payment_type &&
			$product_total > 0
		) {
			$result['advance_amount'] =
				0.0;

			$result['pay_now'] =
				$this->normalize_amount(
					$full_payment_discount['payable_total'] ??
						$grand_total
				);

			$result['remaining_due'] =
				0.0;

			$result['is_cash_on_delivery'] =
				false;

			$result['is_full_payment'] =
				true;

			$result['is_advance_payment'] =
				false;
		}

		/*
		 * Cash on Delivery frontend text.
		 *
		 * The presentation values remain stored under
		 * payment_methods[cash_on_delivery] for backwards
		 * compatibility, while Payment Options controls
		 * whether COD is available.
		 */
		$cash_on_delivery_texts = array(
			'label' =>
				(string) (
					$cash_on_delivery_settings['title'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Cash on Delivery' )
				),

			'description' =>
				(string) (
					$cash_on_delivery_settings['description'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay with cash upon delivery.' )
				),
		);

		/*
		 * Advance frontend text.
		 */
		$advance_texts = array(
			'advance_label' =>
				(string) (
					$advance_settings['advance_label'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Advance Payment' )
				),

			'advance_card_subtitle' =>
				(string) (
					$advance_settings['advance_card_subtitle'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {pay_now} in advance.' )
				),

			'advance_description' =>
				(string) (
					$advance_settings['advance_description'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Secure your order now. Pay the remaining {remaining_due} later.' )
				),

			'empty_description' =>
				(string) (
					$advance_settings['empty_description'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select a product to see the advance amount.' )
				),
		);

		/*
		 * Full-payment frontend text.
		 */
		$full_payment_texts = array(
			'label' =>
				(string) (
					$full_payment_settings['label'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Full Payment' )
				),

			'description' =>
				(string) (
					$full_payment_settings['description'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay the full amount in advance.' )
				),

			'discount_badge' =>
				(string) (
					$full_payment_settings['discount_badge'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Get {discount} OFF' )
				),

			'free_delivery_badge' =>
				(string) (
					$full_payment_settings['free_delivery_badge'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Free Delivery' )
				),
		);

		/*
		 * Combined frontend text payload.
		 */
		$frontend_texts = array(
			'cash_on_delivery_label' =>
				$cash_on_delivery_texts['label'],

			'cash_on_delivery_description' =>
				$cash_on_delivery_texts['description'],

			'advance_label' =>
				$advance_texts['advance_label'],

			'advance_card_subtitle' =>
				$advance_texts['advance_card_subtitle'],

			'advance_description' =>
				$advance_texts['advance_description'],

			'empty_description' =>
				$advance_texts['empty_description'],

			'full_payment_label' =>
				$full_payment_texts['label'],

			'full_payment_description' =>
				$full_payment_texts['description'],

			'discount_badge' =>
				$full_payment_texts['discount_badge'],

			'free_delivery_badge' =>
				$full_payment_texts['free_delivery_badge'],

			'advance_context_title' =>
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Complete advance payment' ),

			'full_payment_context_title' =>
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Complete full payment' ),

			'full_payment_context_description' =>
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {discounted_total} now to complete your order.' ),

			'advance_badge' =>
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Less hassle' ),

			'payment_method_pay_now' =>
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {amount} in advance.' ),
		);

		/**
		 * Filters advance-payment renderer data.
		 *
		 * @param array<string, mixed> $data    Renderer data.
		 * @param array<string, mixed> $context Rendering context.
		 */
		$data = apply_filters(
			'eilmo_cf/advance_payment/render_data',
			array(
				'instance_id' =>
					$instance_id,

				'title' =>
					(string) (
						$advance_settings['title'] ??
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Payment Option' )
					),

				'display_layout' => (string) ( $advance_settings['display_layout'] ?? 'grid' ),
				'columns_desktop' => (int) ( $advance_settings['columns_desktop'] ?? 3 ),
				'columns_tablet' => (int) ( $advance_settings['columns_tablet'] ?? 2 ),
				'columns_mobile' => (int) ( $advance_settings['columns_mobile'] ?? 1 ),

				'allow_cash_on_delivery' =>
					'yes' === (
						$advance_settings['allow_cash_on_delivery'] ??
							'no'
					),

				'allow_advance_payment' =>
					'yes' === (
						$advance_settings['allow_advance_payment'] ??
							'yes'
					),

				'allow_full_payment' =>
					'yes' === (
						$advance_settings['allow_full_payment'] ??
							'yes'
					),

				'default_payment_type' =>
					$payment_type,

				'calculation_basis' =>
					sanitize_key(
						(string) (
							$advance_settings['calculation_basis'] ??
								'grand_total'
						)
					),

				'cash_on_delivery_texts' =>
					$cash_on_delivery_texts,

				'advance_texts' =>
					$advance_texts,

				'full_payment_texts' =>
					$full_payment_texts,

				'frontend_texts' =>
					$frontend_texts,

				'rules' =>
					$this->get_frontend_rules(
						$advance_settings
					),

				'result' =>
					$result,

				/*
				 * Independent server-rendered Advance card preview.
				 */
				'advance_preview_result' =>
					$advance_preview_result,

				'full_payment_discount' =>
					$full_payment_discount,

				'full_payment_frontend_config' =>
					$full_payment_frontend_config,

				'product_total' =>
					$product_total,

				'discounted_product_total' =>
					$discounted_product_total,

				'delivery_charge' =>
					$delivery_charge,

				'grand_total' =>
					$grand_total,
			),
			$context
		);

		if ( ! is_array( $data ) ) {
			return '';
		}
		$layout_overrides = isset( $context['layout_overrides'] ) && is_array( $context['layout_overrides'] ) ? $context['layout_overrides'] : array();
		$data['show_full_payment_badge'] = 'no' !== (string) ( $layout_overrides['show_full_payment_badge'] ?? 'yes' );
		$has_column_override = false;
		foreach ( array( 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ) as $device => $maximum ) {
			$key = 'payment_options_columns_' . $device;
			if ( ! empty( $layout_overrides[ $key ] ) ) {
				$data[ 'columns_' . $device ] = max( 1, min( $maximum, absint( $layout_overrides[ $key ] ) ) );
				$has_column_override = true;
			}
		}
		if ( $has_column_override ) {
			$data['display_layout'] = 'grid';
		}

		ob_start();

		do_action(
			'eilmo_cf/advance_payment/before',
			$data
		);

		$this->render_section(
			$data
		);

		do_action(
			'eilmo_cf/advance_payment/after',
			$data
		);

		$output = ob_get_clean();

		return false === $output
			? ''
			: (string) $output;
	}

	/**
	 * Render payment section.
	 *
	 * @param array<string, mixed> $data Renderer data.
	 *
	 * @return void
	 */
	private function render_section(
		array $data
	): void {
        $data = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $data );
		$instance_id =
			sanitize_html_class(
				(string) (
					$data['instance_id'] ??
						''
				)
			);

		$display_layout = 'list' === (string) ( $data['display_layout'] ?? 'grid' ) ? 'list' : 'grid';
		$columns_desktop = max( 1, min( 4, (int) ( $data['columns_desktop'] ?? 3 ) ) );
		$columns_tablet  = max( 1, min( 4, (int) ( $data['columns_tablet'] ?? 2 ) ) );
		$columns_mobile  = max( 1, min( 2, (int) ( $data['columns_mobile'] ?? 1 ) ) );

		$payment_type =
			sanitize_key(
				(string) (
					$data['default_payment_type'] ??
						'advance'
				)
			);

		$allow_cash_on_delivery =
			! empty(
				$data['allow_cash_on_delivery']
			);

		$allow_advance_payment =
			! empty(
				$data['allow_advance_payment']
			);

		$allow_full_payment =
			! empty(
				$data['allow_full_payment']
			);

		$calculation_basis =
			sanitize_key(
				(string) (
					$data['calculation_basis'] ??
						'grand_total'
				)
			);

		$rules =
			isset( $data['rules'] ) &&
			is_array( $data['rules'] )
				? $data['rules']
				: array();

		$result =
			isset( $data['result'] ) &&
			is_array( $data['result'] )
				? $data['result']
				: array();

		$advance_preview_result =
			isset( $data['advance_preview_result'] ) &&
			is_array( $data['advance_preview_result'] )
				? $data['advance_preview_result']
				: $result;

		$cash_on_delivery_texts =
			isset( $data['cash_on_delivery_texts'] ) &&
			is_array( $data['cash_on_delivery_texts'] )
				? $data['cash_on_delivery_texts']
				: array();

		$advance_texts =
			isset( $data['advance_texts'] ) &&
			is_array( $data['advance_texts'] )
				? $data['advance_texts']
				: array();

		$full_payment_texts =
			isset( $data['full_payment_texts'] ) &&
			is_array( $data['full_payment_texts'] )
				? $data['full_payment_texts']
				: array();

		$frontend_texts =
			isset( $data['frontend_texts'] ) &&
			is_array( $data['frontend_texts'] )
				? $data['frontend_texts']
				: array();

		$full_payment_discount =
			isset( $data['full_payment_discount'] ) &&
			is_array( $data['full_payment_discount'] )
				? $data['full_payment_discount']
				: array();

		$full_payment_frontend_config =
			isset( $data['full_payment_frontend_config'] ) &&
			is_array( $data['full_payment_frontend_config'] )
				? $data['full_payment_frontend_config']
				: array();

		$grand_total =
			$this->normalize_amount(
				$data['grand_total'] ??
					0
			);

		/*
		 * Selected-flow amounts remain authoritative in $result.
		 * The Advance card itself uses its independent preview so it
		 * never displays 0 merely because COD or Full is selected.
		 */
		$advance_amount =
			$this->normalize_amount(
				$advance_preview_result['advance_amount'] ??
					0
			);

		$advance_remaining_due =
			$this->normalize_amount(
				$advance_preview_result['remaining_due'] ??
					$grand_total
			);

		$selected_remaining_due =
			$this->normalize_amount(
				$result['remaining_due'] ??
					$grand_total
			);

		$has_selected_product =
			$this->normalize_amount(
				$data['product_total'] ??
					0
			) > 0;

		$rules_json =
			$this->encode_json(
				$rules,
				'[]'
			);

		$advance_texts_json =
			$this->encode_json(
				$advance_texts,
				'{}'
			);

		$full_payment_texts_json =
			$this->encode_json(
				$full_payment_texts,
				'{}'
			);

		$frontend_texts_json =
			$this->encode_json(
				$frontend_texts,
				'{}'
			);

		$full_payment_config_json =
			$this->encode_json(
				$full_payment_frontend_config,
				'{}'
			);

		?>
		<section
			class="eilmo-cf-advance-payment eilmo-cf-advance-payment--<?php echo esc_attr( $display_layout ); ?>"
			data-eilmo-advance-payment
			data-display-layout="<?php echo esc_attr( $display_layout ); ?>"
			style="--eilmo-cf-payment-columns-desktop:<?php echo esc_attr( (string) $columns_desktop ); ?>;--eilmo-cf-payment-columns-tablet:<?php echo esc_attr( (string) $columns_tablet ); ?>;--eilmo-cf-payment-columns-mobile:<?php echo esc_attr( (string) $columns_mobile ); ?>;"
			data-payment-type="<?php echo esc_attr( $payment_type ); ?>"
			data-default-payment-type="<?php echo esc_attr( $payment_type ); ?>"
			data-allow-cash-on-delivery="<?php echo esc_attr( $allow_cash_on_delivery ? 'yes' : 'no' ); ?>"
			data-allow-advance-payment="<?php echo esc_attr( $allow_advance_payment ? 'yes' : 'no' ); ?>"
			data-allow-full-payment="<?php echo esc_attr( $allow_full_payment ? 'yes' : 'no' ); ?>"
			data-calculation-basis="<?php echo esc_attr( $calculation_basis ); ?>"
			data-rules="<?php echo esc_attr( $rules_json ); ?>"
			data-advance-texts="<?php echo esc_attr( $advance_texts_json ); ?>"
			data-full-payment-texts="<?php echo esc_attr( $full_payment_texts_json ); ?>"
			data-texts="<?php echo esc_attr( $frontend_texts_json ); ?>"
			data-full-payment-discount="<?php echo esc_attr( $full_payment_config_json ); ?>"
			data-product-total="<?php echo esc_attr( (string) ( $data['product_total'] ?? 0 ) ); ?>"
			data-discounted-product-total="<?php echo esc_attr( (string) ( $data['discounted_product_total'] ?? 0 ) ); ?>"
			data-delivery-charge="<?php echo esc_attr( (string) ( $data['delivery_charge'] ?? 0 ) ); ?>"
			data-grand-total="<?php echo esc_attr( (string) $grand_total ); ?>"
			data-base-grand-total="<?php echo esc_attr( (string) $grand_total ); ?>"
			data-advance-amount="<?php echo esc_attr( (string) $advance_amount ); ?>"
			data-advance-preview-amount="<?php echo esc_attr( (string) $advance_amount ); ?>"
			data-advance-preview-remaining-due="<?php echo esc_attr( (string) $advance_remaining_due ); ?>"
			data-remaining-due="<?php echo esc_attr( (string) $selected_remaining_due ); ?>"
			data-has-selected-product="<?php echo esc_attr( $has_selected_product ? 'yes' : 'no' ); ?>"
		>

			<div class="eilmo-cf-advance-payment__header">

				<h3 class="eilmo-cf-advance-payment__title">
					<?php
					echo esc_html(
						(string) (
							$data['title'] ??
								''
						)
					);
					?>
				</h3>

			</div>

			<div
				class="eilmo-cf-advance-payment__options"
				role="radiogroup"
				aria-label="<?php echo esc_attr( (string) ( $data['title'] ?? '' ) ); ?>"
			>

				<?php
				if ( $allow_cash_on_delivery ) {
					$this->render_cash_on_delivery_option(
						$instance_id,
						$payment_type,
						$cash_on_delivery_texts
					);
				}

				if ( $allow_advance_payment ) {
					$this->render_advance_option(
						$instance_id,
						$payment_type,
						$advance_amount,
						$advance_remaining_due,
						$grand_total,
						$advance_texts,
						$has_selected_product
					);
				}

				if ( $allow_full_payment ) {
					$this->render_full_option(
						$instance_id,
						$payment_type,
						$grand_total,
						$full_payment_discount,
						$full_payment_texts,
						! empty( $data['show_full_payment_badge'] )
					);
				}
				?>

			</div>


			<div
				class="eilmo-cf-advance-payment__error"
				data-eilmo-advance-error
				role="alert"
				hidden
			></div>

		</section>
		<?php
	}

	/**
	 * Render Cash on Delivery option.
	 *
	 * COD is a top-level Payment Option. When selected,
	 * the separate Payment Method section will later be
	 * hidden by the frontend Payment Options controller.
	 *
	 * @param string               $instance_id  Instance ID.
	 * @param string               $payment_type Selected payment type.
	 * @param array<string, mixed> $texts        COD texts.
	 *
	 * @return void
	 */
	private function render_cash_on_delivery_option(
		string $instance_id,
		string $payment_type,
		array $texts
	): void {
		$input_id =
			sprintf(
				'eilmo-cf-payment-cash-on-delivery-%s',
				$instance_id
			);

		$selected =
			'cash_on_delivery' ===
				$payment_type;

		$label =
			sanitize_text_field(
				(string) (
					$texts['label'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Cash on Delivery' )
				)
			);

		if ( '' === $label ) {
			$label =
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Cash on Delivery' );
		}

		$description =
			sanitize_text_field(
				(string) (
					$texts['description'] ??
						\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay with cash upon delivery.' )
				)
			);

		$classes = array(
			'eilmo-cf-advance-payment-option',
			'eilmo-cf-advance-payment-option--cash-on-delivery',
		);

		if ( $selected ) {
			$classes[] =
				'eilmo-cf-advance-payment-option--selected';
		}

		?>
		<label
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			for="<?php echo esc_attr( $input_id ); ?>"
			data-eilmo-payment-option
			data-payment-type="cash_on_delivery"
			data-selected="<?php echo esc_attr( $selected ? 'yes' : 'no' ); ?>"
			aria-selected="<?php echo esc_attr( $selected ? 'true' : 'false' ); ?>"
		>

			<input
				type="radio"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="eilmo-cf-advance-payment-option__input"
				name="<?php echo esc_attr( 'eilmo-cf-native-checkout' === $instance_id ? 'eilmo_cf_payment_type' : 'eilmo_cf_payment_type_' . $instance_id ); ?>"
				value="cash_on_delivery"
				data-eilmo-payment-type
				<?php checked( $selected ); ?>
			>

			<span class="eilmo-cf-advance-payment-option__selected-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
					<path d="M5 12.5L9.2 16.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>

			<span class="eilmo-cf-advance-payment-option__content">

				<span class="eilmo-cf-advance-payment-option__top">

					<span class="eilmo-cf-advance-payment-option__heading">

						<span
							class="eilmo-cf-advance-payment-option__label"
							data-eilmo-cash-on-delivery-label
						>
							<?php echo esc_html( $label ); ?>
						</span>

					</span>

				</span>

				<?php if ( '' !== $description ) : ?>

					<span
						class="eilmo-cf-advance-payment-option__description"
						data-eilmo-cash-on-delivery-description
					>
						<?php echo esc_html( $description ); ?>
					</span>

				<?php endif; ?>

			</span>

		</label>
		<?php
	}

	/**
	 * Render advance-payment option.
	 *
	 * @param string               $instance_id          Instance ID.
	 * @param string               $payment_type         Selected payment type.
	 * @param float                $advance              Advance amount.
	 * @param float                $remaining_due        Remaining amount.
	 * @param float                $grand_total          Grand total.
	 * @param array<string, mixed> $texts                Dynamic texts.
	 * @param bool                 $has_selected_product Whether product selected.
	 *
	 * @return void
	 */
	private function render_advance_option(
		string $instance_id,
		string $payment_type,
		float $advance,
		float $remaining_due,
		float $grand_total,
		array $texts,
		bool $has_selected_product
	): void {
		$input_id =
			sprintf(
				'eilmo-cf-payment-advance-%s',
				$instance_id
			);

		$selected =
			'advance' ===
				$payment_type;

		$classes = array(
			'eilmo-cf-advance-payment-option',
			'eilmo-cf-advance-payment-option--advance',
		);

		if ( $selected ) {
			$classes[] =
				'eilmo-cf-advance-payment-option--selected';
		}

		$label_template =
			sanitize_text_field(
				(string) (
					$texts['advance_label'] ??
						''
				)
			);

		/* Remove amount placeholders cleanly before product selection. */
		if ( ! $has_selected_product ) {
			$label_template =
				$this->get_empty_advance_label(
					$label_template
				);
		}

		$label_template =
			str_replace(
				array(
					'{remaining_due}',
					'{grand_total}',
				),
				array(
					$this->format_price_text(
						$remaining_due
					),

					$this->format_price_text(
						$grand_total
					),
				),
				$label_template
			);

		$label_parts =
			explode(
				'{pay_now}',
				$label_template,
				2
			);

		$label_has_live_amount =
			$has_selected_product &&
			2 === count(
				$label_parts
			);

		/*
		 * Keep the option card deliberately compact. The longer configured
		 * advance description is rendered in the completion panel below the
		 * option row by advance.js.
		 */
		if ( $has_selected_product ) {
			$card_subtitle =
				$this->replace_text_tokens(
					(string) (
						$texts['advance_card_subtitle'] ??
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Pay {pay_now} in advance.' )
					),
					array(
						'pay_now' =>
							$this->format_price_text(
								$advance
							),

						'remaining_due' =>
							$this->format_price_text(
								$remaining_due
							),

						'grand_total' =>
							$this->format_price_text(
								$grand_total
							),
					)
				);
		} else {
			$card_subtitle =
				sanitize_text_field(
					(string) (
						$texts['empty_description'] ??
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Select a product to see the advance amount.' )
					)
				);
		}

		$badge_text =
			$has_selected_product
				? sanitize_text_field(
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Less hassle' )
				)
				: '';

		?>
		<label
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			for="<?php echo esc_attr( $input_id ); ?>"
			data-eilmo-payment-option
			data-payment-type="advance"
			data-selected="<?php echo esc_attr( $selected ? 'yes' : 'no' ); ?>"
			aria-selected="<?php echo esc_attr( $selected ? 'true' : 'false' ); ?>"
		>

			<input
				type="radio"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="eilmo-cf-advance-payment-option__input"
				name="<?php echo esc_attr( 'eilmo-cf-native-checkout' === $instance_id ? 'eilmo_cf_payment_type' : 'eilmo_cf_payment_type_' . $instance_id ); ?>"
				value="advance"
				data-eilmo-payment-type
				<?php checked( $selected ); ?>
			>

			<span class="eilmo-cf-advance-payment-option__selected-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
					<path d="M5 12.5L9.2 16.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>

			<span class="eilmo-cf-advance-payment-option__content">

				<span class="eilmo-cf-advance-payment-option__top">

					<span class="eilmo-cf-advance-payment-option__heading">

						<span
							class="eilmo-cf-advance-payment-option__label"
							data-eilmo-advance-option-label
						>

							<?php if ( $label_has_live_amount ) : ?>

								<?php
								echo esc_html(
									$label_parts[0]
								);
								?>

								<strong
									class="eilmo-cf-advance-payment-option__inline-amount"
									data-eilmo-advance-option-amount
									data-value="<?php echo esc_attr( (string) $advance ); ?>"
								>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates escaped price markup.
									echo $this->format_price(
										$advance
									);
									?>
								</strong>

								<?php
								echo esc_html(
									$label_parts[1]
								);
								?>

							<?php else : ?>

								<?php
								echo esc_html(
									$label_template
								);
								?>

							<?php endif; ?>

						</span>

					</span>

				</span>

				<?php if ( '' !== $card_subtitle ) : ?>

					<span
						class="eilmo-cf-advance-payment-option__description"
						data-eilmo-advance-option-subtitle
					>
						<?php echo esc_html( $card_subtitle ); ?>
					</span>

				<?php endif; ?>

				<span
					class="eilmo-cf-advance-payment-option__badge eilmo-cf-advance-payment-option__badge--advance"
					data-eilmo-advance-payment-badge
					<?php echo '' !== $badge_text ? '' : 'hidden'; ?>
				>
					<?php echo esc_html( $badge_text ); ?>
				</span>

			</span>

		</label>
		<?php
	}

	/**
	 * Render full-payment option.
	 *
	 * Exact payable amount is intentionally not
	 * displayed here because Summary already shows it.
	 *
	 * @param string               $instance_id  Instance ID.
	 * @param string               $payment_type Selected payment type.
	 * @param float                $grand_total  Grand total.
	 * @param array<string, mixed> $discount     Discount result.
	 * @param array<string, mixed> $texts        Dynamic texts.
	 * @param bool                 $show_badge   Widget badge visibility.
	 *
	 * @return void
	 */
	private function render_full_option(
		string $instance_id,
		string $payment_type,
		float $grand_total,
		array $discount,
		array $texts,
		bool $show_badge
	): void {
		$input_id =
			sprintf(
				'eilmo-cf-payment-full-%s',
				$instance_id
			);

		$selected =
			'full' ===
				$payment_type;

		$eligible =
			! empty(
				$discount['eligible']
			);

		$offer_available =
			! empty(
				$discount['offer_available']
			);

		$saving =
			$this->normalize_amount(
				$discount['saving'] ??
					0
			);

		$payable_total =
			$this->normalize_amount(
				$discount['payable_total'] ??
					$grand_total
			);

		$discount_label =
			$this->get_discount_label(
				$discount
			);

		$tokens = array(
			'discount' =>
				$discount_label,

			'saving' =>
				$this->format_price_text(
					$saving
				),

			'grand_total' =>
				$this->format_price_text(
					$grand_total
				),

			'discounted_total' =>
				$this->format_price_text(
					$payable_total
				),
		);

		$label =
			$this->replace_text_tokens(
				(string) (
					$texts['label'] ??
						''
				),
				$tokens
			);

		$description =
			$this->replace_text_tokens(
				(string) (
					$texts['description'] ??
						''
				),
				$tokens
			);

		$badge_parts = array();

		if ( $eligible && $saving > 0 && '' !== $discount_label ) {
			$discount_badge = $this->replace_text_tokens(
				(string) ( $texts['discount_badge'] ?? '' ),
				$tokens
			);
			if ( '' !== trim( $discount_badge ) ) {
				$badge_parts[] = trim( $discount_badge );
			}
		}

		if ( $this->normalize_amount( $discount['free_delivery_saving'] ?? 0 ) > 0 ) {
			$free_badge = sanitize_text_field(
				(string) (
					$texts['free_delivery_badge'] ??
						$discount['free_delivery_badge'] ??
						''
				)
			);
			if ( '' !== trim( $free_badge ) ) {
				$badge_parts[] = trim( $free_badge );
			}
		}

		$badge_text = implode( ' + ', array_values( array_unique( $badge_parts ) ) );

		$classes = array(
			'eilmo-cf-advance-payment-option',
			'eilmo-cf-advance-payment-option--full',
		);

		if ( $selected ) {
			$classes[] =
				'eilmo-cf-advance-payment-option--selected';
		}

		if ( $offer_available ) {
			$classes[] =
				'eilmo-cf-advance-payment-option--discount';
		}

		?>
		<label
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			for="<?php echo esc_attr( $input_id ); ?>"
			data-eilmo-payment-option
			data-payment-type="full"
			data-selected="<?php echo esc_attr( $selected ? 'yes' : 'no' ); ?>"
			data-discount-offer="<?php echo esc_attr( $offer_available ? 'yes' : 'no' ); ?>"
			data-discount-eligible="<?php echo esc_attr( $eligible ? 'yes' : 'no' ); ?>"
			data-saving="<?php echo esc_attr( (string) $saving ); ?>"
			data-discounted-total="<?php echo esc_attr( (string) $payable_total ); ?>"
			aria-selected="<?php echo esc_attr( $selected ? 'true' : 'false' ); ?>"
		>

			<input
				type="radio"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="eilmo-cf-advance-payment-option__input"
				name="<?php echo esc_attr( 'eilmo-cf-native-checkout' === $instance_id ? 'eilmo_cf_payment_type' : 'eilmo_cf_payment_type_' . $instance_id ); ?>"
				value="full"
				data-eilmo-payment-type
				<?php checked( $selected ); ?>
			>

			<span class="eilmo-cf-advance-payment-option__selected-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
					<path d="M5 12.5L9.2 16.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>

			<span class="eilmo-cf-advance-payment-option__content">

				<span class="eilmo-cf-advance-payment-option__top">

					<span class="eilmo-cf-advance-payment-option__heading">

						<?php if ( '' !== $label ) : ?>

							<span
								class="eilmo-cf-advance-payment-option__label"
								data-eilmo-full-payment-label
							>
								<?php echo esc_html( $label ); ?>
							</span>

						<?php endif; ?>

					</span>

				</span>

				<?php if ( '' !== $description ) : ?>

					<span
						class="eilmo-cf-advance-payment-option__description"
						data-eilmo-full-payment-description
					>
						<?php echo esc_html( $description ); ?>
					</span>

				<?php endif; ?>

				<span
					class="eilmo-cf-advance-payment-option__badge"
					data-eilmo-full-payment-discount-badge
					data-show-badge="<?php echo esc_attr( $show_badge ? 'yes' : 'no' ); ?>"
					<?php if ( ! $show_badge || '' === $badge_text ) : ?>
						hidden
					<?php endif; ?>
				>
					<?php echo esc_html( $badge_text ); ?>
				</span>

			</span>

		</label>
		<?php
	}

	/**
	 * Get raw full-payment frontend configuration.
	 *
	 * @param array<string, mixed> $settings Full-payment settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_full_payment_frontend_config(
		array $settings
	): array {
		$type =
			sanitize_key(
				(string) (
					$settings['type'] ??
						'percentage'
				)
			);

		if (
			! in_array(
				$type,
				array(
					'percentage',
					'fixed',
				),
				true
			)
		) {
			$type =
				'percentage';
		}

		$basis =
			sanitize_key(
				(string) (
					$settings['basis'] ??
						'discounted_product_total'
				)
			);

		if (
			! in_array(
				$basis,
				array(
					'product_total',
					'discounted_product_total',
					'grand_total',
				),
				true
			)
		) {
			$basis =
				'discounted_product_total';
		}

		$value =
			$this->normalize_amount(
				$settings['value'] ??
					0
			);

		if ( 'percentage' === $type ) {
			$value =
				min(
					100,
					$value
				);
		}

		return array(
			'enabled' =>
				'yes' === (
					$settings['enabled'] ??
						'no'
				)
					? 'yes'
					: 'no',

			'type' =>
				$type,

			'value' =>
				$value,

			'minimum_amount' =>
				$this->normalize_amount(
					$settings['minimum_amount'] ??
						0
				),

			'maximum_discount' =>
				$this->normalize_amount(
					$settings['maximum_discount'] ??
						0
				),

			'basis' =>
				$basis,

			'free_delivery' =>
				'yes' === ( $settings['free_delivery'] ?? 'no' ) ? 'yes' : 'no',

			'free_delivery_badge' =>
				sanitize_text_field(
					(string) (
						$settings['free_delivery_badge'] ??
							\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Free Delivery' )
					)
				),
		);
	}

	/**
	 * Calculate full-payment discount.
	 *
	 * offer_available controls offer visibility.
	 * eligible controls actual discount application.
	 *
	 * @param array<string, mixed> $full_payment_settings    Settings.
	 * @param float                $product_total            Product total.
	 * @param float                $discounted_product_total Discounted total.
	 * @param float                $grand_total              Grand total.
	 * @param float                $delivery_charge          Current delivery charge.
	 *
	 * @return array<string, mixed>
	 */
	private function calculate_full_payment_discount(
		array $full_payment_settings,
		float $product_total,
		float $discounted_product_total,
		float $grand_total,
		float $delivery_charge = 0.0
	): array {
		$config =
			$this->get_full_payment_frontend_config(
				$full_payment_settings
			);

		$enabled =
			'yes' === (
				$config['enabled'] ??
					'no'
			);

		$type =
			sanitize_key(
				(string) (
					$config['type'] ??
						'percentage'
				)
			);

		$value =
			$this->normalize_amount(
				$config['value'] ??
					0
			);

		$basis =
			sanitize_key(
				(string) (
					$config['basis'] ??
						'discounted_product_total'
				)
			);

		$minimum_amount =
			$this->normalize_amount(
				$config['minimum_amount'] ??
					0
			);

		$maximum_discount =
			$this->normalize_amount(
				$config['maximum_discount'] ??
					0
			);

		$free_delivery =
			'yes' === (string) ( $config['free_delivery'] ?? 'no' );

		$effective_grand_total =
			$this->normalize_amount(
				max(
					0,
					$grand_total - ( $free_delivery ? $delivery_charge : 0.0 )
				)
			);

		$basis_amount =
			$this->get_full_payment_discount_basis_amount(
				$basis,
				$product_total,
				$discounted_product_total,
				$effective_grand_total
			);

		/*
		 * Offer visibility does not depend on
		 * current Product Total.
		 */
		$discount_offer_available =
			$enabled &&
			$value > 0;

		$offer_available =
			$discount_offer_available ||
			$free_delivery;

		/*
		 * Actual discount requires an eligible
		 * current order amount.
		 */
		$eligible =
			$discount_offer_available &&
			$basis_amount > 0 &&
			(
				$minimum_amount <= 0 ||
				$basis_amount >=
					$minimum_amount
			);

		$raw_saving =
			0.0;

		$saving =
			0.0;

		if ( $eligible ) {
			$raw_saving =
				'fixed' === $type
					? $value
					: (
						$basis_amount *
						(
							$value /
							100
						)
					);

			$raw_saving =
				$this->normalize_amount(
					$raw_saving
				);

			$saving =
				$raw_saving;

			if (
				$maximum_discount > 0 &&
				$saving >
					$maximum_discount
			) {
				$saving =
					$maximum_discount;
			}

			$saving =
				min(
					$saving,
					$basis_amount,
					$effective_grand_total
				);

			$saving =
				$this->normalize_amount(
					$saving
				);

			if ( $saving <= 0 ) {
				$eligible =
					false;

				$saving =
					0.0;
			}
		}

		if ( ! $eligible ) {
			$raw_saving =
				0.0;

			$saving =
				0.0;
		}

		$payable_total =
			$this->normalize_amount(
				$effective_grand_total -
					$saving
			);

		$result = array(
			'enabled' =>
				$enabled,

			'offer_available' =>
				$offer_available,

			'discount_offer_available' =>
				$discount_offer_available,

			'free_delivery' =>
				$free_delivery,

			'free_delivery_badge' =>
				(string) ( $config['free_delivery_badge'] ?? '' ),

			'eligible' =>
				$eligible,

			'type' =>
				$type,

			'value' =>
				$value,

			'basis' =>
				$basis,

			'basis_amount' =>
				$basis_amount,

			'minimum_amount' =>
				$minimum_amount,

			'maximum_discount' =>
				$maximum_discount,

			'raw_saving' =>
				$raw_saving,

			'saving' =>
				$saving,

			'original_total' =>
				$grand_total,

			'free_delivery_saving' =>
				$free_delivery ? min( $delivery_charge, $grand_total ) : 0.0,

			'payable_total' =>
				$payable_total,

			'discounted_total' =>
				$payable_total,
		);

		$result =
			apply_filters(
				'eilmo_cf/advance_payment/full_payment_discount',
				$result,
				$full_payment_settings,
				$product_total,
				$discounted_product_total,
				$grand_total
			);

		return is_array( $result )
			? $result
			: array();
	}

	/**
	 * Get full-payment discount basis.
	 *
	 * @param string $basis                    Basis.
	 * @param float  $product_total            Product total.
	 * @param float  $discounted_product_total Discounted total.
	 * @param float  $grand_total              Grand total.
	 *
	 * @return float
	 */
	private function get_full_payment_discount_basis_amount(
		string $basis,
		float $product_total,
		float $discounted_product_total,
		float $grand_total
	): float {
		switch ( $basis ) {
			case 'product_total':
				return $product_total;

			case 'grand_total':
				return $grand_total;

			case 'discounted_product_total':
			default:
				return $discounted_product_total;
		}
	}

	/**
	 * Get discount label.
	 *
	 * Configured offer remains available before
	 * a product is selected.
	 *
	 * @param array<string, mixed> $discount Discount result.
	 *
	 * @return string
	 */
	private function get_discount_label(
		array $discount
	): string {
		if (
			empty(
				$discount['offer_available']
			)
		) {
			return '';
		}

		$type =
			sanitize_key(
				(string) (
					$discount['type'] ??
						'percentage'
				)
			);

		$value =
			$this->normalize_amount(
				$discount['value'] ??
					0
			);

		$saving =
			$this->normalize_amount(
				$discount['saving'] ??
					0
			);

		$raw_saving =
			$this->normalize_amount(
				$discount['raw_saving'] ??
					$saving
			);

		if ( 'fixed' === $type ) {
			return $this->format_price_text(
				$saving > 0
					? $saving
					: $value
			);
		}

		if (
			$raw_saving > 0 &&
			$saving > 0 &&
			$saving <
				$raw_saving
		) {
			return $this->format_price_text(
				$saving
			);
		}

		return sprintf(
			'%s%%',
			$this->format_percentage(
				$value
			)
		);
	}

	/**
	 * Format percentage.
	 *
	 * @param float $value Percentage.
	 *
	 * @return string
	 */
	private function format_percentage(
		float $value
	): string {
		$formatted =
			number_format(
				$value,
				2,
				'.',
				''
			);

		return rtrim(
			rtrim(
				$formatted,
				'0'
			),
			'.'
		);
	}

	/**
	 * Replace frontend placeholders.
	 *
	 * @param string                $text   Template.
	 * @param array<string, string> $values Values.
	 *
	 * @return string
	 */
	private function replace_text_tokens(
		string $text,
		array $values
	): string {
		foreach (
			$values as
			$token => $value
		) {
			$text =
				str_replace(
					'{' . $token . '}',
					$value,
					$text
				);
		}

		return sanitize_text_field(
			$text
		);
	}

	/**
	 * Get advance label before product selection.
	 *
	 * Example:
	 *
	 * Pay {pay_now} Advance & Confirm Order
	 *
	 * becomes:
	 *
	 * Pay Advance & Confirm Order
	 *
	 * @param string $label Label.
	 *
	 * @return string
	 */
	private function get_empty_advance_label(
		string $label
	): string {
		$label =
			preg_replace(
				'/\s*\{pay_now\}\s*/',
				' ',
				$label
			);

		if ( ! is_string( $label ) ) {
			return '';
		}

		$label =
			preg_replace(
				'/\s+/',
				' ',
				$label
			);

		return sanitize_text_field(
			trim(
				(string) $label
			)
		);
	}

	/**
	 * Resolve payment type.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $context  Context.
	 *
	 * @return string
	 */
	private function get_payment_type(
		array $settings,
		array $context
	): string {
		$available =
			array();

		if (
			'yes' === (
				$settings['allow_cash_on_delivery'] ??
					'no'
			)
		) {
			$available[] =
				'cash_on_delivery';
		}

		if (
			'yes' === (
				$settings['allow_advance_payment'] ??
					'yes'
			)
		) {
			$available[] =
				'advance';
		}

		if (
			'yes' === (
				$settings['allow_full_payment'] ??
					'yes'
			)
		) {
			$available[] =
				'full';
		}

		/*
		 * Settings sanitization guarantees at least one
		 * option while Payment Options is enabled.
		 * Keep Advance as a defensive fallback for legacy
		 * or externally filtered settings.
		 */
		if ( empty( $available ) ) {
			$available[] =
				'advance';
		}

		$requested =
			sanitize_key(
				(string) (
					$context['payment_type'] ??
						''
				)
			);

		if (
			'' !== $requested &&
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
					$settings['default_payment_type'] ??
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
	 * Prepare advance rules for frontend.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_frontend_rules(
		array $settings
	): array {
		$rules =
			isset( $settings['rules'] ) &&
			is_array( $settings['rules'] )
				? $settings['rules']
				: array();

		$prepared =
			array();

		foreach ( $rules as $rule ) {
			if (
				! is_array( $rule ) ||
				'yes' !== (
					$rule['enabled'] ??
						'yes'
				)
			) {
				continue;
			}

			$type =
				sanitize_key(
					(string) (
						$rule['type'] ??
							'fixed'
					)
				);

			if (
				! in_array(
					$type,
					array(
						'fixed',
						'percentage',
						'full_payment',
						'no_advance',
					),
					true
				)
			) {
				$type =
					'fixed';
			}

			$prepared[] = array(
				'id' =>
					sanitize_key(
						(string) (
							$rule['id'] ??
								''
						)
					),

				'enabled' =>
					true,

				'minimum_amount' =>
					$this->normalize_amount(
						$rule['minimum_amount'] ??
							$rule['minimum'] ??
							0
					),

				'maximum_amount' =>
					$this->normalize_amount(
						$rule['maximum_amount'] ??
							$rule['maximum'] ??
							0
					),

				'type' =>
					$type,

				'value' =>
					$this->normalize_amount(
						$rule['value'] ??
							0
					),

				'minimum_pay_amount' =>
					$this->normalize_amount(
						$rule['minimum_pay_amount'] ??
							$rule['minimum_pay'] ??
							0
					),

				'maximum_pay_amount' =>
					$this->normalize_amount(
						$rule['maximum_pay_amount'] ??
							$rule['maximum_pay'] ??
							0
					),

				'priority' =>
					absint(
						$rule['priority'] ??
							10
					),

				'stop_processing' =>
					'yes' === (
						$rule['stop_processing'] ??
							$rule['stop'] ??
							'no'
					),
			);
		}

		usort(
			$prepared,
			static function (
				array $first,
				array $second
			): int {
				return (
					(int) (
						$first['priority'] ??
							10
					)
				) <=> (
					(int) (
						$second['priority'] ??
							10
					)
				);
			}
		);

		return array_values(
			$prepared
		);
	}

	/**
	 * Get renderer instance ID.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	private function get_instance_id(
		array $context
	): string {
		if (
			! empty(
				$context['instance_id']
			)
		) {
			return sanitize_html_class(
				(string) $context['instance_id']
			);
		}

		if (
			function_exists(
				'wp_unique_id'
			)
		) {
			return sanitize_html_class(
				wp_unique_id(
					'eilmo-cf-advance-'
				)
			);
		}

		return sanitize_html_class(
			uniqid(
				'eilmo-cf-advance-',
				false
			)
		);
	}

	/**
	 * Load advance-payment settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_advance_settings(): array {
		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset( $defaults['advance_payment'] ) &&
			is_array( $defaults['advance_payment'] )
				? $defaults['advance_payment']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset( $stored['advance_payment'] ) &&
			is_array( $stored['advance_payment'] )
				? $stored['advance_payment']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		$settings['rules'] =
			isset( $saved_settings['rules'] ) &&
			is_array( $saved_settings['rules'] )
				? $saved_settings['rules']
				: (
					$default_settings['rules'] ??
						array()
				);

		$settings =
			apply_filters(
				'eilmo_cf/advance_payment/render_settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Load Payment Method settings.
	 *
	 * Cash on Delivery title/description remain stored
	 * under payment_methods for backwards compatibility.
	 * COD availability itself is controlled by
	 * advance_payment[allow_cash_on_delivery].
	 *
	 * @return array<string, mixed>
	 */
	private function get_payment_method_settings(): array {
		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset( $defaults['payment_methods'] ) &&
			is_array( $defaults['payment_methods'] )
				? $defaults['payment_methods']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset( $stored['payment_methods'] ) &&
			is_array( $stored['payment_methods'] )
				? $stored['payment_methods']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		$settings =
			apply_filters(
				'eilmo_cf/payment_methods/render_settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Load discount settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_discount_settings(): array {
		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset( $defaults['discounts'] ) &&
			is_array( $defaults['discounts'] )
				? $defaults['discounts']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset( $stored['discounts'] ) &&
			is_array( $stored['discounts'] )
				? $stored['discounts']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		$settings['automatic_rules'] =
			isset( $saved_settings['automatic_rules'] ) &&
			is_array( $saved_settings['automatic_rules'] )
				? $saved_settings['automatic_rules']
				: (
					$default_settings['automatic_rules'] ??
						array()
				);

		$settings =
			apply_filters(
				'eilmo_cf/discounts/render_settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Encode JSON safely.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function encode_json(
		$value,
		string $fallback
	): string {
		$json =
			wp_json_encode(
				$value
			);

		return false === $json
			? $fallback
			: $json;
	}

	/**
	 * Format WooCommerce price.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function format_price(
		float $amount
	): string {
		if (
			function_exists(
				'wc_price'
			)
		) {
			return wc_price(
				$amount
			);
		}

		return esc_html(
			number_format_i18n(
				$amount,
				2
			)
		);
	}

	/**
	 * Format price as plain text.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function format_price_text(
		float $amount
	): string {
		return wp_strip_all_tags(
			$this->format_price(
				$amount
			)
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

		$amount =
			max(
				0,
				(float) $amount
			);

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			$amount,
			$decimals
		);
	}
}
