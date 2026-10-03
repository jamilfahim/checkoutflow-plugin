<?php
/**
 * Order renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Rendering;

use EilmoCheckout\Rendering\TrustNoticeRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the final Order Now section.
 */
final class OrderRenderer {

	/**
	 * Render order-submit section.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

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

		$button_label =
			sanitize_text_field(
				(string) (
					$context['button_label'] ??
						__(
							'Order Now',
							'eilmo-checkout-flow'
						)
				)
			);

		if ( '' === $button_label ) {
			$button_label =
				__(
					'Order Now',
					'eilmo-checkout-flow'
				);
		}

		$show_amount =
			'yes' === (string) ( $context['show_amount'] ?? 'no' );

		$amount_source = sanitize_key( (string) ( $context['amount_source'] ?? 'auto' ) );
		if ( ! in_array( $amount_source, array( 'auto', 'grand_total', 'pay_now' ), true ) ) {
			$amount_source = 'auto';
		}

		$footer_enabled = 'yes' === (string) ( $context['footer_enabled'] ?? 'yes' );
		$footer_text = sanitize_text_field( (string) ( $context['footer_text'] ?? '' ) );
		$footer_icon = sanitize_key( (string) ( $context['footer_icon'] ?? 'shield' ) );

		$variant = sanitize_key( (string) ( $context['variant'] ?? 'default' ) );
		$is_mobile_sticky = 'mobile_sticky' === $variant;

		$processing_label =
			sanitize_text_field(
				(string) (
					$context['processing_label'] ??
						__(
							'Processing...',
							'eilmo-checkout-flow'
						)
				)
			);

		if ( '' === $processing_label ) {
			$processing_label =
				__(
					'Processing...',
					'eilmo-checkout-flow'
				);
		}

		/**
		 * Filters Order Now button label.
		 *
		 * @param string               $button_label Button label.
		 * @param array<string, mixed> $context      Context.
		 */
		$button_label =
			(string) apply_filters(
				'eilmo_cf/orders/button_label',
				$button_label,
				$context
			);

		/**
		 * Filters order processing label.
		 *
		 * @param string               $processing_label Processing label.
		 * @param array<string, mixed> $context          Context.
		 */
		$processing_label =
			(string) apply_filters(
				'eilmo_cf/orders/processing_label',
				$processing_label,
				$context
			);

		ob_start();
		?>
		<section
			class="eilmo-cf-order-submit<?php echo $is_mobile_sticky ? ' eilmo-cf-order-submit--mobile-sticky' : ''; ?>"
			data-eilmo-order-submit-section
			data-instance-id="<?php echo esc_attr( $instance_id ); ?>"
		>

			<?php if ( ! $is_mobile_sticky ) : ?>
				<div
					class="eilmo-cf-order-submit__notice"
					data-eilmo-order-notice
					role="status"
					aria-live="polite"
					hidden
				></div>
			<?php endif; ?>

			<button
				type="button"
				class="eilmo-cf-order-submit__button"
				data-eilmo-order-submit
				data-label="<?php echo esc_attr( $button_label ); ?>"
				data-processing-label="<?php echo esc_attr( $processing_label ); ?>"
				data-show-amount="<?php echo esc_attr( $show_amount ? 'yes' : 'no' ); ?>"
				data-amount-source="<?php echo esc_attr( $amount_source ); ?>"
				data-cod-template="<?php echo esc_attr( (string) ( $context['cod_button_template'] ?? '' ) ); ?>"
				data-advance-template="<?php echo esc_attr( (string) ( $context['advance_button_template'] ?? '' ) ); ?>"
				data-full-template="<?php echo esc_attr( (string) ( $context['full_button_template'] ?? '' ) ); ?>"
				aria-busy="false"
			>
				<span
					class="eilmo-cf-order-submit__button-text"
					data-eilmo-order-submit-text
				>
					<?php echo esc_html( $button_label ); ?>
				</span>


				<?php if ( $show_amount ) : ?>
					<span class="eilmo-cf-order-submit__amount" data-eilmo-order-submit-amount aria-live="polite"></span>
				<?php endif; ?>

				<span
					class="eilmo-cf-order-submit__spinner"
					data-eilmo-order-spinner
					aria-hidden="true"
					hidden
				></span>
			</button>

			<?php if ( ! $is_mobile_sticky ) : ?>
				<div
					class="eilmo-cf-order-submit__error"
					data-eilmo-order-error
					role="alert"
					hidden
				></div>
			<?php endif; ?>

			<?php if ( ! $is_mobile_sticky && $footer_enabled && '' !== $footer_text && class_exists( TrustNoticeRenderer::class ) ) : ?>
				<div class="eilmo-cf-order-submit__footer">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes values internally.
					echo ( new TrustNoticeRenderer() )->render( array( 'text' => $footer_text, 'icon' => $footer_icon, 'context' => 'order' ) );
					?>
				</div>
			<?php endif; ?>

		</section>
		<?php

		$output =
			ob_get_clean();

		if ( false === $output ) {
			return '';
		}

		/**
		 * Filters Order Now section HTML.
		 *
		 * @param string               $output  HTML.
		 * @param array<string, mixed> $context Context.
		 */
		return (string) apply_filters(
			'eilmo_cf/orders/html',
			$output,
			$context
		);
	}
}
