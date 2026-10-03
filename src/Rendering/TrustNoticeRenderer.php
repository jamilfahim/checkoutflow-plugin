<?php
/**
 * Shared checkout trust notice renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Rendering;

defined( 'ABSPATH' ) || exit;

/**
 * Renders small payment/order notices with accessible inline SVG icons.
 */
final class TrustNoticeRenderer {

	/**
	 * Render notice.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	public function render( array $context = array() ): string {
		$text = sanitize_text_field( (string) ( $context['text'] ?? '' ) );
		if ( '' === $text ) {
			return '';
		}

		$icon = sanitize_key( (string) ( $context['icon'] ?? 'shield' ) );
		if ( ! in_array( $icon, array( 'shield', 'lock', 'none' ), true ) ) {
			$icon = 'shield';
		}

		$notice_context = sanitize_html_class( (string) ( $context['context'] ?? 'checkout' ) );

		ob_start();
		?>
		<div class="eilmo-cf-trust-notice eilmo-cf-trust-notice--<?php echo esc_attr( $notice_context ); ?>">
			<?php if ( 'none' !== $icon ) : ?>
				<span class="eilmo-cf-trust-notice__icon" aria-hidden="true">
					<?php if ( 'lock' === $icon ) : ?>
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
					<?php else : ?>
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 3l7 3v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<span class="eilmo-cf-trust-notice__text"><?php echo esc_html( $text ); ?></span>
		</div>
		<?php
		$output = ob_get_clean();
		return false !== $output ? (string) $output : '';
	}
}
