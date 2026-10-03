<?php
/**
 * Delivery renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Delivery\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Delivery\Services\DeliveryCalculator;

defined( 'ABSPATH' ) || exit;

/**
 * Renders frontend delivery methods.
 */
final class DeliveryRenderer {

	/**
	 * Delivery calculator.
	 *
	 * @var DeliveryCalculator
	 */
	private $calculator;

	/**
	 * Constructor.
	 *
	 * @param DeliveryCalculator|null $calculator Delivery calculator.
	 */
	public function __construct(
		?DeliveryCalculator $calculator = null
	) {

		$this->calculator = $calculator
			? $calculator
			: new DeliveryCalculator();
	}

	/**
	 * Render delivery section.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'order_total'        => 2500,
	 *     'selected_method_id' => 'inside-dhaka',
	 *     'instance_id'        => 'checkout-1',
	 * )
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

		if (
			! $this->calculator->delivery_is_enabled()
		) {
			return '';
		}

		$settings = $this->get_settings();
        $settings = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $settings );

		$order_total = $this->get_order_total(
			$context
		);

		$methods = $this->calculator->get_available_methods(
			$order_total,
			$context
		);

		$selected_method_id =
			$this->get_selected_method_id(
				$context,
				$order_total,
				$methods
			);

		$instance_id = $this->get_instance_id(
			$context
		);



		/**
		 * Filters delivery renderer data.
		 *
		 * @param array<string, mixed> $data    Renderer data.
		 * @param array<string, mixed> $context Rendering context.
		 */
		$data = apply_filters(
			'eilmo_cf/delivery/render_data',
			array(
				'instance_id' => $instance_id,

				'title' => (string) (
					$settings['title'] ??
					\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Delivery Method' )
				),

				'required' => 'yes' === (
					$settings['required'] ??
					'yes'
				),

				'show_description' => 'yes' === (
					$settings['show_description'] ??
					'yes'
				),

				'columns_desktop' => max( 1, min( 4, absint( $settings['columns_desktop'] ?? 2 ) ) ),
				'columns_tablet'  => max( 1, min( 3, absint( $settings['columns_tablet'] ?? 2 ) ) ),
				'columns_mobile'  => max( 1, min( 2, absint( $settings['columns_mobile'] ?? 1 ) ) ),
				'content_layout'  => in_array( (string) ( $settings['content_layout'] ?? 'inline' ), array( 'inline', 'stacked' ), true )
					? (string) $settings['content_layout']
					: 'inline',

				'methods' => $methods,

				'selected_method_id' =>
					$selected_method_id,

				'order_total' => $order_total,

			),
			$context
		);

		if ( ! is_array( $data ) ) {
			return '';
		}
		$layout_overrides = isset( $context['layout_overrides'] ) && is_array( $context['layout_overrides'] ) ? $context['layout_overrides'] : array();
		foreach ( array( 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ) as $device => $maximum ) {
			$key = 'delivery_columns_' . $device;
			if ( ! empty( $layout_overrides[ $key ] ) ) {
				$data[ 'columns_' . $device ] = max( 1, min( $maximum, absint( $layout_overrides[ $key ] ) ) );
			}
		}

		ob_start();

		/**
		 * Fires before delivery section markup.
		 *
		 * @param array<string, mixed> $data Delivery data.
		 */
		do_action(
			'eilmo_cf/delivery/before',
			$data
		);

		$this->render_section(
			$data
		);

		/**
		 * Fires after delivery section markup.
		 *
		 * @param array<string, mixed> $data Delivery data.
		 */
		do_action(
			'eilmo_cf/delivery/after',
			$data
		);

		return (string) ob_get_clean();
	}

	/**
	 * Render delivery section markup.
	 *
	 * @param array<string, mixed> $data Renderer data.
	 *
	 * @return void
	 */
	private function render_section(
		array $data
	): void {

		$methods = isset(
			$data['methods']
		) && is_array(
			$data['methods']
		)
			? $data['methods']
			: array();



		$instance_id = sanitize_html_class(
			(string) (
				$data['instance_id'] ??
				''
			)
		);

		$selected_method_id = sanitize_key(
			(string) (
				$data['selected_method_id'] ??
					''
			)
		);

		$order_total = $this->normalize_amount(
			$data['order_total'] ??
				0
		);



		/*
		 * Do not recalculate Free Delivery eligibility here. The unified
		 * SpecialDiscountEngine has already been applied by DeliveryCalculator.
		 * This flag is presentation-only and mirrors the resolved method state.
		 */
		$free_delivery_active = false;
		foreach ( $methods as $resolved_method ) {
			if ( is_array( $resolved_method ) && ! empty( $resolved_method['free_delivery_applied'] ) ) {
				$free_delivery_active = true;
				break;
			}
		}

		$columns_desktop = max( 1, min( 4, absint( $data['columns_desktop'] ?? 2 ) ) );
		$columns_tablet = max( 1, min( 3, absint( $data['columns_tablet'] ?? 2 ) ) );
		$columns_mobile = max( 1, min( 2, absint( $data['columns_mobile'] ?? 1 ) ) );
		$content_layout = in_array( (string) ( $data['content_layout'] ?? 'inline' ), array( 'inline', 'stacked' ), true )
			? (string) $data['content_layout']
			: 'inline';
		$delivery_style = sprintf(
			'--eilmo-cf-delivery-columns-desktop:%1$d;--eilmo-cf-delivery-columns-tablet:%2$d;--eilmo-cf-delivery-columns-mobile:%3$d;',
			$columns_desktop,
			$columns_tablet,
			$columns_mobile
		);

		?>
		<section
			class="eilmo-cf-delivery"
			style="<?php echo esc_attr( $delivery_style ); ?>"
			data-content-layout="<?php echo esc_attr( $content_layout ); ?>"
			data-eilmo-delivery
			data-required="<?php echo esc_attr( ! empty( $data['required'] ) ? 'yes' : 'no' ); ?>"
			data-selected-method="<?php echo esc_attr( $selected_method_id ); ?>"
			data-order-total="<?php echo esc_attr( (string) $order_total ); ?>"
			data-free-delivery-active="<?php echo esc_attr( $free_delivery_active ? 'yes' : 'no' ); ?>"
			data-selected-charge="0"
		>
			<div class="eilmo-cf-delivery__header">

				<h3 class="eilmo-cf-delivery__title">
					<?php
					echo esc_html(
						(string) (
							$data['title'] ??
								''
						)
					);
					?>

					<?php if ( ! empty( $data['required'] ) ) : ?>

						<span
							class="eilmo-cf-delivery__required"
							aria-hidden="true"
						>
							*
						</span>

					<?php endif; ?>
				</h3>

			</div>

			<?php if ( empty( $methods ) ) : ?>

				<div
					class="eilmo-cf-delivery__notice"
					data-eilmo-delivery-empty
					role="status"
				>
					<?php
					echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'No delivery methods are currently available.' ) );
					?>
				</div>

			<?php else : ?>

				<div
					class="eilmo-cf-delivery__methods"
					data-eilmo-delivery-methods
					role="radiogroup"
					aria-label="<?php echo esc_attr( (string) ( $data['title'] ?? '' ) ); ?>"
				>
					<?php foreach ( $methods as $index => $method ) : ?>

						<?php
						if ( ! is_array( $method ) ) {
							continue;
						}

						$this->render_method(
							$method,
							(int) $index,
							$instance_id,
							$selected_method_id,
							! empty(
								$data['show_description']
							)
						);
						?>

					<?php endforeach; ?>
				</div>

				<div
					class="eilmo-cf-delivery__error"
					data-eilmo-delivery-error
					role="alert"
					hidden
				></div>

			<?php endif; ?>

		</section>
		<?php
	}

	/**
	 * Render one delivery method.
	 *
	 * @param array<string, mixed> $method             Method.
	 * @param int                  $index              Index.
	 * @param string               $instance_id        Instance ID.
	 * @param string               $selected_method_id Selected method.
	 * @param bool                 $show_description   Show description.
	 *
	 * @return void
	 */
	private function render_method(
		array $method,
		int $index,
		string $instance_id,
		string $selected_method_id,
		bool $show_description
	): void {

		$method_id = sanitize_key(
			(string) (
				$method['id'] ??
					''
			)
		);

		$label = sanitize_text_field(
			\EilmoCheckout\Presentation\CheckoutLanguage::localized_field( $method, 'label' )
		);

		$description = wp_kses_post(
			\EilmoCheckout\Presentation\CheckoutLanguage::localized_field( $method, 'description' )
		);

		if (
			'' === $method_id ||
			'' === $label
		) {
			return;
		}

		/*
		 * Current effective charge.
		 *
		 * This may already be zero when server-side
		 * free delivery applies during initial render.
		 */
		$charge = $this->normalize_amount(
			$method['charge'] ??
				0
		);

		/*
		 * Original configured charge.
		 *
		 * Frontend keeps this value so the normal
		 * amount can be restored if a free-delivery
		 * threshold stops applying.
		 */
		$base_charge = $this->normalize_amount(
			$method['base_charge'] ??
				$charge
		);

		$is_selected =
			$selected_method_id ===
			$method_id;

		$is_free =
			$charge <= 0.0;

		$free_delivery_applied =
			! empty(
				$method['free_delivery_applied']
			);

		$input_id = sprintf(
			'eilmo-cf-delivery-%1$s-%2$d',
			$instance_id,
			$index
		);

		$classes = array(
			'eilmo-cf-delivery-method',
		);

		if ( $is_selected ) {
			$classes[] =
				'eilmo-cf-delivery-method--selected';
		}

		if ( $is_free ) {
			$classes[] =
				'eilmo-cf-delivery-method--free';
		}

		/**
		 * Filters delivery method CSS classes.
		 *
		 * @param array<string>        $classes Classes.
		 * @param array<string, mixed> $method  Method.
		 */
		$classes = apply_filters(
			'eilmo_cf/delivery/method_classes',
			$classes,
			$method
		);

		if ( ! is_array( $classes ) ) {
			$classes = array(
				'eilmo-cf-delivery-method',
			);
		}

		?>
		<label
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			for="<?php echo esc_attr( $input_id ); ?>"
			data-eilmo-delivery-method
			data-method-id="<?php echo esc_attr( $method_id ); ?>"
			data-charge="<?php echo esc_attr( (string) $charge ); ?>"
			data-base-charge="<?php echo esc_attr( (string) $base_charge ); ?>"
			data-free-delivery="<?php echo esc_attr( $free_delivery_applied ? 'yes' : 'no' ); ?>"
			data-selected="<?php echo esc_attr( $is_selected ? 'yes' : 'no' ); ?>"
			aria-selected="<?php echo esc_attr( $is_selected ? 'true' : 'false' ); ?>"
		>
			<input
				type="radio"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="eilmo-cf-delivery-method__input"
				name="<?php echo esc_attr( 'native-checkout' === $instance_id ? 'eilmo_cf_delivery_method' : 'eilmo_cf_delivery_method_' . $instance_id ); ?>"
				value="<?php echo esc_attr( $method_id ); ?>"
				data-eilmo-delivery-input
				data-method-id="<?php echo esc_attr( $method_id ); ?>"
				data-charge="<?php echo esc_attr( (string) $charge ); ?>"
				data-base-charge="<?php echo esc_attr( (string) $base_charge ); ?>"
				<?php checked( $is_selected ); ?>
				<?php
				disabled(
					'yes' !== (
						$method['enabled'] ??
							'yes'
					)
				);
				?>
			>


			<span class="eilmo-cf-delivery-method__content">

				<span class="eilmo-cf-delivery-method__top">

					<span class="eilmo-cf-delivery-method__label">
						<?php echo esc_html( $label ); ?>
					</span>

					<span
						class="eilmo-cf-delivery-method__price"
						data-eilmo-delivery-price
						data-normal-price="<?php echo esc_attr( wp_strip_all_tags( $this->format_price( $base_charge ) ) ); ?>"
					>
						<?php
						if ( $is_free ) {

							echo esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Free' ) );

						} else {

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates escaped price markup.
							echo $this->format_price(
								$charge
							);
						}
						?>
					</span>

				</span>

				<?php if ( $show_description && '' !== trim( wp_strip_all_tags( $description ) ) ) : ?>

					<span class="eilmo-cf-delivery-method__description">
						<?php echo wp_kses_post( $description ); ?>
					</span>

				<?php endif; ?>

			</span>
		</label>
		<?php
	}

	/**
	 * Resolve selected method.
	 *
	 * User-provided selection is accepted only when it
	 * exists in the server-approved available methods.
	 *
	 * @param array<string, mixed>             $context     Context.
	 * @param float                            $order_total Order total.
	 * @param array<int, array<string, mixed>> $methods     Methods.
	 *
	 * @return string
	 */
	private function get_selected_method_id(
		array $context,
		float $order_total,
		array $methods
	): string {

		$selected = sanitize_key(
			(string) (
				$context['selected_method_id'] ??
					''
			)
		);

		if (
			'' !== $selected &&
			$this->method_exists(
				$selected,
				$methods
			)
		) {
			return $selected;
		}

		return $this->calculator->get_default_method(
			$order_total,
			$context
		);
	}

	/**
	 * Determine whether method exists.
	 *
	 * @param string                            $method_id Method ID.
	 * @param array<int, array<string, mixed>> $methods   Methods.
	 *
	 * @return bool
	 */
	private function method_exists(
		string $method_id,
		array $methods
	): bool {

		foreach ( $methods as $method ) {

			if (
				is_array( $method ) &&
				isset( $method['id'] ) &&
				$method_id ===
					(string) $method['id']
			) {
				return true;
			}
		}

		return false;
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
					'eilmo-cf-'
				)
			);
		}

		return sanitize_html_class(
			uniqid(
				'eilmo-cf-',
				false
			)
		);
	}

	/**
	 * Get order total from context.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return float
	 */
	private function get_order_total(
		array $context
	): float {

		return $this->normalize_amount(
			$context['order_total'] ??
				0
		);
	}

	/**
	 * Load delivery settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults = CheckoutSettings::get_defaults();

		$default_delivery = isset(
			$defaults['delivery']
		) && is_array(
			$defaults['delivery']
		)
			? $defaults['delivery']
			: array();

		$stored = get_option(
			CheckoutSettings::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$delivery = isset(
			$stored['delivery']
		) && is_array(
			$stored['delivery']
		)
			? $stored['delivery']
			: array();

		$settings = array_replace_recursive(
			$default_delivery,
			$delivery
		);

		/*
		 * Delivery methods are an indexed collection.
		 *
		 * They must replace defaults rather than
		 * recursively merging by array index.
		 */
		$settings['methods'] = isset(
			$delivery['methods']
		) && is_array(
			$delivery['methods']
		)
			? $delivery['methods']
			: (
				$default_delivery['methods'] ??
					array()
			);

		/**
		 * Filters renderer delivery settings.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings = apply_filters(
			'eilmo_cf/delivery/render_settings',
			$settings
		);

		return is_array( $settings )
			? $settings
			: $default_delivery;
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
			$amount = wc_format_decimal(
				$amount,
				false
			);
		}

		if ( ! is_numeric( $amount ) ) {
			return 0.0;
		}

		$amount = max(
			0,
			(float) $amount
		);

		$decimals = function_exists(
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
