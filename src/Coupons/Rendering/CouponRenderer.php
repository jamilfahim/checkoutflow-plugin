<?php
/**
 * Coupon renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Coupons\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders checkout coupon form.
 */
final class CouponRenderer {

	/**
	 * Render coupon form.
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

		if (
			'yes' !== (
				$settings['enabled'] ??
					'no'
			)
		) {
			return '';
		}

		$mode =
			sanitize_key(
				(string) (
					$settings['mode'] ??
						'woocommerce'
				)
			);

		if (
			! in_array(
				$mode,
				array(
					'woocommerce',
					'custom',
					'both',
				),
				true
			)
		) {
			$mode = 'woocommerce';
		}

		$instance_id =
			sanitize_key(
				(string) (
					$context['instance_id'] ??
						''
				)
			);

		if ( '' === $instance_id ) {
			$instance_id =
				wp_unique_id(
					'eilmo-cf-coupon-'
				);
		}

		$data = array(
			'mode'        => $mode,
			'instance_id' => $instance_id,
		);

		/**
		 * Filters coupon renderer data.
		 *
		 * @param array<string, mixed> $data     Render data.
		 * @param array<string, mixed> $settings Coupon settings.
		 * @param array<string, mixed> $context  Render context.
		 */
		$data =
			apply_filters(
				'eilmo_cf/coupons/render_data',
				$data,
				$settings,
				$context
			);

		if ( ! is_array( $data ) ) {
			return '';
		}

		$mode =
			sanitize_key(
				(string) (
					$data['mode'] ??
						$mode
				)
			);

		if (
			! in_array(
				$mode,
				array(
					'woocommerce',
					'custom',
					'both',
				),
				true
			)
		) {
			$mode = 'woocommerce';
		}

		$instance_id =
			sanitize_key(
				(string) (
					$data['instance_id'] ??
						$instance_id
				)
			);

		if ( '' === $instance_id ) {
			$instance_id =
				wp_unique_id(
					'eilmo-cf-coupon-'
				);
		}

		$input_id =
			$instance_id .
			'-code';

		ob_start();

		/**
		 * Fires before coupon form.
		 *
		 * @param array<string, mixed> $data Render data.
		 */
		do_action(
			'eilmo_cf/coupons/before',
			$data
		);
		?>

		<section
			class="eilmo-cf-coupon"
			data-eilmo-coupon
			data-mode="<?php echo esc_attr( $mode ); ?>"
			data-instance-id="<?php echo esc_attr( $instance_id ); ?>"
			data-applied="no"
			data-coupon-code=""
			data-coupon-discount="0"
			data-free-delivery="no"
			data-loading="no"
		>

			<div class="eilmo-cf-coupon__header">

				<h3 class="eilmo-cf-coupon__title">
					<?php
					echo esc_html__(
						'Have a coupon?',
						'eilmo-checkout-flow'
					);
					?>
				</h3>

				<p class="eilmo-cf-coupon__description">
					<?php
					echo esc_html__(
						'Enter your coupon code below.',
						'eilmo-checkout-flow'
					);
					?>
				</p>

			</div>

			<form
				class="eilmo-cf-coupon__form"
				data-eilmo-coupon-form
				method="post"
				action=""
				novalidate
			>

				<label
					class="eilmo-cf-coupon__label"
					for="<?php echo esc_attr( $input_id ); ?>"
				>
					<?php
					echo esc_html__(
						'Coupon Code',
						'eilmo-checkout-flow'
					);
					?>
				</label>

				<div class="eilmo-cf-coupon__controls">

					<input
						type="text"
						id="<?php echo esc_attr( $input_id ); ?>"
						class="eilmo-cf-coupon__input"
						data-eilmo-coupon-input
						name="eilmo_cf_coupon_code"
						value=""
						placeholder="<?php
						echo esc_attr__(
							'Enter coupon code',
							'eilmo-checkout-flow'
						);
						?>"
						autocomplete="off"
						autocapitalize="none"
						spellcheck="false"
						aria-describedby="<?php echo esc_attr( $instance_id ); ?>-message"
					/>

					<button
						type="submit"
						class="eilmo-cf-coupon__apply"
						data-eilmo-coupon-apply
					>
						<?php
						echo esc_html__(
							'Apply Coupon',
							'eilmo-checkout-flow'
						);
						?>
					</button>

				</div>

			</form>

			<div
				class="eilmo-cf-coupon__applied"
				data-eilmo-coupon-applied
				hidden
			>

				<div class="eilmo-cf-coupon__applied-info">

					<span
						class="eilmo-cf-coupon__applied-code"
						data-eilmo-coupon-applied-code
					></span>

					<span
						class="eilmo-cf-coupon__applied-discount"
						data-eilmo-coupon-applied-discount
					></span>

				</div>

				<button
					type="button"
					class="eilmo-cf-coupon__remove"
					data-eilmo-coupon-remove
				>
					<?php
					echo esc_html__(
						'Remove',
						'eilmo-checkout-flow'
					);
					?>
				</button>

			</div>

			<div
				id="<?php echo esc_attr( $instance_id ); ?>-message"
				class="eilmo-cf-coupon__message"
				data-eilmo-coupon-message
				role="status"
				aria-live="polite"
				aria-atomic="true"
				hidden
			></div>

		</section>

		<?php
		/**
		 * Fires after coupon form.
		 *
		 * @param array<string, mixed> $data Render data.
		 */
		do_action(
			'eilmo_cf/coupons/after',
			$data
		);

		$output =
			ob_get_clean();

		return false === $output
			? ''
			: (string) $output;
	}

	/**
	 * Load coupon settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset(
				$defaults['coupons']
			) &&
			is_array(
				$defaults['coupons']
			)
				? $defaults['coupons']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$saved_settings =
			isset(
				$stored['coupons']
			) &&
			is_array(
				$stored['coupons']
			)
				? $stored['coupons']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		/*
		 * Custom coupons are an indexed collection.
		 * Preserve them exactly as stored.
		 */
		$settings['custom_coupons'] =
			isset(
				$saved_settings['custom_coupons']
			) &&
			is_array(
				$saved_settings['custom_coupons']
			)
				? $saved_settings['custom_coupons']
				: (
					$default_settings['custom_coupons'] ??
						array()
				);

		/**
		 * Filters Coupon renderer settings.
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		$settings =
			apply_filters(
				'eilmo_cf/coupons/render_settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}
}