<?php
/**
 * WhatsApp Ordering page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\WhatsAppSettings;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Ordering page.
 */
final class WhatsAppOrdersPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-whatsapp';

	/**
	 * Render page.
	 *
	 * @param bool $embedded Whether the page is rendered inside another page.
	 *
	 * @return void
	 */
	public function render(
		bool $embedded = false
	): void {

		if (
			! current_user_can(
				'manage_woocommerce'
			)
		) {
			wp_die(
				esc_html__(
					'You do not have permission to access this page.',
					'eilmo-checkout-flow'
				)
			);
		}

		$settings =
			WhatsAppSettings::get();

		if ( ! $embedded ) {
			?>
			<div class="wrap eilmo-cf-admin">

				<div class="eilmo-cf-admin__header">

					<div class="eilmo-cf-admin__heading">

						<h1>
						<?php
						esc_html_e(
							'WhatsApp Ordering',
							'eilmo-checkout-flow'
						);
						?>
						</h1>

						<p>
						<?php
						esc_html_e(
							'Configure WhatsApp ordering for your Checkout Flow.',
							'eilmo-checkout-flow'
						);
						?>
						</p>

					</div>

				</div>
			<?php
		}
		?>

			<?php if ( ! $embedded ) : ?>
				<?php settings_errors(); ?>
			<?php endif; ?>

			<form
				method="post"
				action="options.php"
				class="eilmo-cf-settings-form"
			>

				<?php
				settings_fields(
					WhatsAppSettings::OPTION_GROUP
				);
				?>

				<section class="eilmo-cf-settings-section">

					<?php
					$this->render_section_header(
						__(
							'WhatsApp Configuration',
							'eilmo-checkout-flow'
						),
						__(
							'Configure the WhatsApp number, checkout button label and pre-filled customer order message.',
							'eilmo-checkout-flow'
						)
					);
					?>

					<table
						class="form-table"
						role="presentation"
					>
						<tbody>

							<tr>

								<th scope="row">

									<label for="eilmo-cf-whatsapp-number">
										<?php
										esc_html_e(
											'WhatsApp Number',
											'eilmo-checkout-flow'
										);
										?>
									</label>

								</th>

								<td>

									<input
										type="text"
										id="eilmo-cf-whatsapp-number"
										class="regular-text"
										name="<?php
										echo esc_attr(
											WhatsAppSettings::OPTION_NAME
										);
										?>[number]"
										value="<?php
										echo esc_attr(
											(string) (
												$settings[
													'number'
												] ??
													''
											)
										);
										?>"
										inputmode="numeric"
										autocomplete="off"
										placeholder="<?php
										echo esc_attr__(
											'Example: 8801712345678',
											'eilmo-checkout-flow'
										);
										?>"
									>

									<p class="description">
										<?php
										esc_html_e(
											'Enter the full WhatsApp number including country code. Use numbers only without +, spaces or dashes.',
											'eilmo-checkout-flow'
										);
										?>
									</p>

								</td>

							</tr>

							<tr>

								<th scope="row">

									<label for="eilmo-cf-whatsapp-button-label">
										<?php
										esc_html_e(
											'Button Label',
											'eilmo-checkout-flow'
										);
										?>
									</label>

								</th>

								<td>

									<input
										type="text"
										id="eilmo-cf-whatsapp-button-label"
										class="regular-text"
										name="<?php
										echo esc_attr(
											WhatsAppSettings::OPTION_NAME
										);
										?>[button_label]"
										value="<?php
										echo esc_attr(
											(string) (
												$settings[
													'button_label'
												] ??
													''
											)
										);
										?>"
									>

									<p class="description">
										<?php
										esc_html_e(
											'Text displayed on the WhatsApp ordering button.',
											'eilmo-checkout-flow'
										);
										?>
									</p>

								</td>

							</tr>

							<tr>

								<th scope="row">
									<?php esc_html_e( 'Button Placement', 'eilmo-checkout-flow' ); ?>
								</th>

								<td>
									<p class="description">
										<?php esc_html_e( 'Placement is controlled from Checkout Flow → Settings → General → Easy Setup / Order Button so layout presets and manual controls stay in one place.', 'eilmo-checkout-flow' ); ?>
									</p>
								</td>

							</tr>

							<tr>

								<th scope="row">

									<label for="eilmo-cf-whatsapp-message-template">
										<?php
										esc_html_e(
											'Message Template',
											'eilmo-checkout-flow'
										);
										?>
									</label>

								</th>

								<td>

									<textarea
										id="eilmo-cf-whatsapp-message-template"
										class="large-text code"
										name="<?php
										echo esc_attr(
											WhatsAppSettings::OPTION_NAME
										);
										?>[message_template]"
										rows="14"
									><?php
									echo esc_textarea(
										(string) (
											$settings[
												'message_template'
											] ??
												''
										)
									);
									?></textarea>

									<p class="description">
										<?php
										esc_html_e(
											'Only information entered or selected by the customer will be included. Lines containing empty placeholders are automatically removed.',
											'eilmo-checkout-flow'
										);
										?>
									</p>

									<p class="description">
										<strong>
											<?php
											esc_html_e(
												'Available placeholders:',
												'eilmo-checkout-flow'
											);
											?>
										</strong>
									</p>

									<p>
										<code>{customer_name}</code>
										<code>{phone}</code>
										<code>{email}</code>
										<code>{address}</code>
										<code>{products}</code>
										<code>{delivery}</code>
										<code>{payment}</code>
										<code>{total}</code>
										<code>{checkout_url}</code>
									</p>

								</td>

							</tr>

						</tbody>
					</table>

					<div class="notice notice-info inline">

						<p>
							<?php
							esc_html_e(
								'WhatsApp Ordering is a quick alternative ordering channel. Customers only need to select a product. Any customer, address, delivery or payment information already entered will be added to the WhatsApp message automatically.',
								'eilmo-checkout-flow'
							);
							?>
						</p>

					</div>

				</section>

				<section class="eilmo-cf-settings-section" data-eilmo-whatsapp-style-settings>

					<?php
					$this->render_section_header(
						__( 'WhatsApp Button Style', 'eilmo-checkout-flow' ),
						__( 'Control the WhatsApp action independently from the global checkout theme. New installations use the official WhatsApp green by default.', 'eilmo-checkout-flow' )
					);
					$style = isset( $settings['style'] ) && is_array( $settings['style'] )
						? $settings['style']
						: WhatsAppSettings::get_defaults()['style'];
					$style_prefix = WhatsAppSettings::OPTION_NAME . '[style]';
					?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Button Colours', 'eilmo-checkout-flow' ); ?></th>
								<td>
									<div class="eilmo-cf-admin-color-grid">
										<?php
										foreach (
											array(
												'background'       => __( 'Background', 'eilmo-checkout-flow' ),
												'hover_background' => __( 'Hover Background', 'eilmo-checkout-flow' ),
												'active_background' => __( 'Active Background', 'eilmo-checkout-flow' ),
												'text'             => __( 'Text and Icon', 'eilmo-checkout-flow' ),
												'border'           => __( 'Border', 'eilmo-checkout-flow' ),
												'focus_ring'       => __( 'Focus Ring', 'eilmo-checkout-flow' ),
											) as $key => $label
										) {
											$this->render_style_color_control(
												$style_prefix . '[' . $key . ']',
												(string) ( $style[ $key ] ?? '#25d366' ),
												$label
											);
										}
										?>
									</div>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="eilmo-cf-whatsapp-radius"><?php esc_html_e( 'Border Radius', 'eilmo-checkout-flow' ); ?></label></th>
								<td>
									<input id="eilmo-cf-whatsapp-radius" type="number" min="0" max="40" name="<?php echo esc_attr( $style_prefix . '[radius]' ); ?>" value="<?php echo esc_attr( (string) ( $style['radius'] ?? 12 ) ); ?>">
									<span>px</span>
								</td>
							</tr>
						</tbody>
					</table>

				</section>

				<?php
				submit_button(
					__(
						'Save WhatsApp Settings',
						'eilmo-checkout-flow'
					)
				);
				?>

			</form>

		<?php if ( ! $embedded ) : ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render section heading.
	 *
	 * @param string $title       Section title.
	 * @param string $description Section description.
	 *
	 * @return void
	 */
	private function render_section_header(
		string $title,
		string $description
	): void {
		?>
		<div class="eilmo-cf-settings-section__header">

			<h2>
				<?php
				echo esc_html(
					$title
				);
				?>
			</h2>

			<p>
				<?php
				echo esc_html(
					$description
				);
				?>
			</p>

		</div>
		<?php
	}

	/** Render one WhatsApp colour control. */
	private function render_style_color_control(
		string $name,
		string $value,
		string $label
	): void {
		?>
		<label class="eilmo-cf-admin-color-control">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="eilmo-cf-admin-color-control__input">
				<input type="color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<code><?php echo esc_html( $value ); ?></code>
			</span>
		</label>
		<?php
	}
}
