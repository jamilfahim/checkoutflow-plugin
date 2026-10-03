<?php
/**
 * Quick Checkout settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\QuickCheckoutSettings;
use EilmoCheckout\Admin\CheckoutStyle;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Single Product Quick Checkout
 * settings page.
 */
final class QuickCheckoutSettingsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-quick-checkout';

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {

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
			QuickCheckoutSettings::get_settings();

		$presentation =
			$this->get_array(
				$settings,
				'presentation'
			);

		$buttons =
			$this->get_array(
				$settings,
				'buttons'
			);

		$style =
			$this->get_array(
				$settings,
				'style'
			);

		$content =
			$this->get_array(
				$settings,
				'content'
			);

		$texts =
			$this->get_array(
				$settings,
				'texts'
			);

		?>
		<div class="wrap eilmo-cf-admin">

			<div class="eilmo-cf-admin__header">

				<div class="eilmo-cf-admin__heading">

					<h1>
						<?php
						esc_html_e(
							'Quick Checkout Settings',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Configure the Single Product Quick Checkout experience, selected offers and customer-facing text.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

				</div>

			</div>

			<form
				method="post"
				action="options.php"
				class="eilmo-cf-settings-form"
			>

				<?php
				settings_fields(
					QuickCheckoutSettings::OPTION_GROUP
				);

				$this->render_presentation(
					$presentation
				);

				$this->render_style(
					$style
				);

				$this->render_buttons(
					$buttons
				);

				$this->render_button_style_sources();

				$this->render_content(
					$content
				);

				$this->render_texts(
					$texts
				);

				submit_button(
					__(
						'Save Quick Checkout Settings',
						'eilmo-checkout-flow'
					)
				);
				?>

			</form>

		</div>
		<?php
	}

	/**
	 * Render presentation settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_presentation(
		array $settings
	): void {

		$display_mode =
			(string) (
				$settings[
					'display_mode'
				] ??
					'bottom_sheet'
			);

		$desktop_width =
			absint(
				$settings[
					'desktop_width'
				] ??
					1180
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Presentation',
					'eilmo-checkout-flow'
				),
				__(
					'Control how Quick Checkout opens from the WooCommerce single product page.',
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

							<label for="eilmo-cf-quick-checkout-display-mode">
								<?php
								esc_html_e(
									'Display Mode',
									'eilmo-checkout-flow'
								);
								?>
							</label>

						</th>

						<td>

							<select
								id="eilmo-cf-quick-checkout-display-mode"
								name="eilmo_cf_quick_checkout_settings[presentation][display_mode]"
							>

								<?php
								$this->render_option(
									'bottom_sheet',
									__(
										'Bottom Sheet',
										'eilmo-checkout-flow'
									),
									$display_mode
								);

								$this->render_option(
									'modal',
									__(
										'Modal',
										'eilmo-checkout-flow'
									),
									$display_mode
								);
								?>

							</select>

							<p class="description">
								<?php
								esc_html_e(
									'Bottom Sheet slides upward from the bottom. Modal opens as a centered popup.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

					<tr>

						<th scope="row">

							<label for="eilmo-cf-quick-checkout-desktop-width">
								<?php
								esc_html_e(
									'Desktop Width',
									'eilmo-checkout-flow'
								);
								?>
							</label>

						</th>

						<td>

							<input
								type="number"
								id="eilmo-cf-quick-checkout-desktop-width"
								name="eilmo_cf_quick_checkout_settings[presentation][desktop_width]"
								value="<?php
								echo esc_attr(
									(string) $desktop_width
								);
								?>"
								min="1040"
								max="1200"
								step="10"
							>

							<span>
								<?php
								esc_html_e(
									'px',
									'eilmo-checkout-flow'
								);
								?>
							</span>

							<p class="description">
								<?php
								esc_html_e(
									'Desktop width for the shared two-column checkout layout. 1180px is recommended.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

				</tbody>
			</table>

		</section>
		<?php
	}


	/**
	 * Render Quick Checkout appearance settings.
	 *
	 * The default path reuses the exact CheckoutStyle token set that powers the
	 * normal Checkout Flow and Elementor/Block Editor surfaces. A custom override
	 * is available without introducing another frontend CSS system.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_style( array $settings ): void {
		$source = sanitize_key( (string) ( $settings['source'] ?? 'default' ) );
		if ( 'global' === $source ) {
			$source = 'default';
		}
		if ( ! in_array( $source, array( 'default', 'custom' ), true ) ) {
			$source = 'default';
		}

		$custom = isset( $settings['custom'] ) && is_array( $settings['custom'] )
			? array_replace_recursive( CheckoutStyle::get_defaults(), $settings['custom'] )
			: CheckoutStyle::get_defaults();

		$prefix = QuickCheckoutSettings::OPTION_NAME . '[style][custom]';
		?>
		<section class="eilmo-cf-settings-section" data-eilmo-quick-checkout-style data-eilmo-checkout-style>
			<?php
			$this->render_section_header(
				__( 'Checkout Appearance', 'eilmo-checkout-flow' ),
				__( 'Quick Checkout has its own isolated theme. Plugin Default uses the canonical purple palette and does not inherit Checkout Settings -> Global Checkout Style.', 'eilmo-checkout-flow' )
			);
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="eilmo-cf-quick-style-source"><?php esc_html_e( 'Style Source', 'eilmo-checkout-flow' ); ?></label>
						</th>
						<td>
							<select id="eilmo-cf-quick-style-source" name="<?php echo esc_attr( QuickCheckoutSettings::OPTION_NAME . '[style][source]' ); ?>">
								<?php $this->render_option( 'default', __( 'Plugin Default Theme (Recommended)', 'eilmo-checkout-flow' ), $source ); ?>
								<?php $this->render_option( 'custom', __( 'Custom Quick Checkout Style', 'eilmo-checkout-flow' ), $source ); ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Plugin Default is independent from Checkout Settings → Global Checkout Style. Choose Custom only when Quick Checkout itself needs a different skin.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Custom Theme Colours', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<div class="eilmo-cf-admin-color-grid">
								<?php
								foreach (
									array(
										'primary'         => __( 'Primary', 'eilmo-checkout-flow' ),
										'text'            => __( 'Main Text', 'eilmo-checkout-flow' ),
										'muted_text'      => __( 'Secondary Text', 'eilmo-checkout-flow' ),
										'background'      => __( 'Card Background', 'eilmo-checkout-flow' ),
										'soft_background' => __( 'Page / Soft Background', 'eilmo-checkout-flow' ),
										'border'          => __( 'Border', 'eilmo-checkout-flow' ),
										'button_text'     => __( 'Button / Badge Text', 'eilmo-checkout-flow' ),
									) as $key => $label
								) :
									$field_id = 'eilmo-cf-quick-style-' . str_replace( '_', '-', $key );
									?>
									<label for="<?php echo esc_attr( $field_id ); ?>">
										<span><?php echo esc_html( $label ); ?></span>
										<input type="color" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $custom[ $key ] ?? '#ffffff' ) ); ?>" data-eilmo-style-color="<?php echo esc_attr( $key ); ?>">
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'These values are saved for Custom mode only. They do not change the global checkout theme.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Custom Shape and Spacing', 'eilmo-checkout-flow' ); ?></th>
						<td class="eilmo-cf-admin-number-grid">
							<?php
							foreach (
								array(
									'radius'        => array( __( 'Control Radius', 'eilmo-checkout-flow' ), 40 ),
									'parent_radius' => array( __( 'Card Radius', 'eilmo-checkout-flow' ), 48 ),
									'gap'           => array( __( 'Section Gap', 'eilmo-checkout-flow' ), 48 ),
									'card_gap'      => array( __( 'Card Gap', 'eilmo-checkout-flow' ), 48 ),
								) as $key => $control
							) :
								?>
								<label>
									<span><?php echo esc_html( $control[0] ); ?></span>
									<input type="number" min="0" max="<?php echo esc_attr( (string) $control[1] ); ?>" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $custom[ $key ] ?? 16 ) ); ?>" data-eilmo-style-number="<?php echo esc_attr( $key ); ?>">
									<span>px</span>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="eilmo-cf-admin-style-preview" data-eilmo-style-preview style="<?php echo esc_attr( CheckoutStyle::get_css_variables( $custom ) ); ?>">
				<span class="eilmo-cf-admin-style-preview__badge"><?php esc_html_e( 'Quick Checkout', 'eilmo-checkout-flow' ); ?></span>
				<h3><?php esc_html_e( 'Quick Checkout Style Preview', 'eilmo-checkout-flow' ); ?></h3>
				<p><?php esc_html_e( 'Customer fields, delivery, payment, summary and the final action use one consistent design system.', 'eilmo-checkout-flow' ); ?></p>
				<button type="button"><?php esc_html_e( 'Place Order', 'eilmo-checkout-flow' ); ?></button>
			</div>
		</section>
		<?php
	}

	/**
	 * Render product-page button settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_buttons(
		array $settings
	): void {

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Product Page Buttons',
					'eilmo-checkout-flow'
				),
				__(
					'Choose which Quick Checkout actions appear on WooCommerce single product pages.',
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
							<?php
							esc_html_e(
								'Order Now',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								'eilmo_cf_quick_checkout_settings[buttons][show_order_button]',
								'yes',
								(string) (
									$settings[
										'show_order_button'
									] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show the Quick Checkout Order Now button on supported single product pages.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

						</tr>

						<tr>

							<th scope="row">
								<?php
							esc_html_e(
								'WhatsApp Order',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								'eilmo_cf_quick_checkout_settings[buttons][show_whatsapp_button]',
								'yes',
								(string) (
									$settings[
										'show_whatsapp_button'
									] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show the WhatsApp Quick Checkout button when the main WhatsApp Ordering feature is also enabled.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

				</tbody>
			</table>

		</section>
		<?php
	}

	/**
	 * Render product-page button appearance settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_button_styles(
		array $settings
	): void {

		$defaults =
			QuickCheckoutSettings::get_defaults();

		$default_styles =
			$this->get_array(
				$defaults,
				'button_styles'
			);

		$order_defaults =
			$this->get_array(
				$default_styles,
				'order'
			);

		$whatsapp_defaults =
			$this->get_array(
				$default_styles,
				'whatsapp'
			);

		$order =
			array_replace(
				$order_defaults,
				$this->get_array(
					$settings,
					'order'
				)
			);

		$whatsapp =
			array_replace(
				$whatsapp_defaults,
				$this->get_array(
					$settings,
					'whatsapp'
				)
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Button Appearance',
					'eilmo-checkout-flow'
				),
				__(
					'Customize the Order Now and WhatsApp buttons so they can match the active WooCommerce theme.',
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

						<th
							scope="row"
							colspan="2"
						>
							<h3>
								<?php
								esc_html_e(
									'Order Now Button',
									'eilmo-checkout-flow'
								);
								?>
							</h3>
						</th>

					</tr>

					<?php
					$this->render_button_color_row(
						'order',
						'background_color',
						__(
							'Background Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'background_color'
							] ??
								'#6d28d9'
						)
					);

					$this->render_button_color_row(
						'order',
						'text_color',
						__(
							'Text Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'text_color'
							] ??
								'#ffffff'
						)
					);

					$this->render_button_color_row(
						'order',
						'border_color',
						__(
							'Border Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'border_color'
							] ??
								'#6d28d9'
						)
					);

					$this->render_button_color_row(
						'order',
						'hover_background_color',
						__(
							'Hover Background Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'hover_background_color'
							] ??
								'#5b21b6'
						)
					);

					$this->render_button_color_row(
						'order',
						'hover_text_color',
						__(
							'Hover Text Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'hover_text_color'
							] ??
								'#ffffff'
						)
					);

					$this->render_button_color_row(
						'order',
						'hover_border_color',
						__(
							'Hover Border Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$order[
								'hover_border_color'
							] ??
								'#5b21b6'
						)
					);

					$this->render_button_number_row(
						'order',
						'border_width',
						__(
							'Border Width',
							'eilmo-checkout-flow'
						),
						absint(
							$order[
								'border_width'
							] ??
								1
						),
						0,
						10
					);

					$this->render_button_number_row(
						'order',
						'border_radius',
						__(
							'Border Radius',
							'eilmo-checkout-flow'
						),
						absint(
							$order[
								'border_radius'
							] ??
								4
						),
						0,
						100
					);
					?>

					<tr>

						<th
							scope="row"
							colspan="2"
						>
							<h3>
								<?php
								esc_html_e(
									'WhatsApp Button',
									'eilmo-checkout-flow'
								);
								?>
							</h3>
						</th>

					</tr>

					<?php
					$this->render_button_color_row(
						'whatsapp',
						'background_color',
						__(
							'Background Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'background_color'
							] ??
								'#25d366'
						)
					);

					$this->render_button_color_row(
						'whatsapp',
						'text_color',
						__(
							'Text Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'text_color'
							] ??
								'#ffffff'
						)
					);

					$this->render_button_color_row(
						'whatsapp',
						'border_color',
						__(
							'Border Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'border_color'
							] ??
								'#25d366'
						)
					);

					$this->render_button_color_row(
						'whatsapp',
						'hover_background_color',
						__(
							'Hover Background Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'hover_background_color'
							] ??
								'#1ebe5d'
						)
					);

					$this->render_button_color_row(
						'whatsapp',
						'hover_text_color',
						__(
							'Hover Text Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'hover_text_color'
							] ??
								'#ffffff'
						)
					);

					$this->render_button_color_row(
						'whatsapp',
						'hover_border_color',
						__(
							'Hover Border Color',
							'eilmo-checkout-flow'
						),
						(string) (
							$whatsapp[
								'hover_border_color'
							] ??
								'#1ebe5d'
						)
					);

					$this->render_button_number_row(
						'whatsapp',
						'border_width',
						__(
							'Border Width',
							'eilmo-checkout-flow'
						),
						absint(
							$whatsapp[
								'border_width'
							] ??
								1
						),
						0,
						10
					);

					$this->render_button_number_row(
						'whatsapp',
						'border_radius',
						__(
							'Border Radius',
							'eilmo-checkout-flow'
						),
						absint(
							$whatsapp[
								'border_radius'
							] ??
								4
						),
						0,
						100
					);
					?>

				</tbody>
			</table>

			<p class="description">
				<?php
				esc_html_e(
					'These appearance settings affect only the two action buttons shown on the WooCommerce single product page.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

		</section>
		<?php
	}

	/**
	 * Explain the authoritative product-page button style sources.
	 *
	 * Quick Checkout no longer duplicates global or WhatsApp appearance
	 * controls. This keeps the single-product actions consistent everywhere.
	 *
	 * @return void
	 */
	private function render_button_style_sources(): void {
		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Button Appearance',
					'eilmo-checkout-flow'
				),
				__(
					'Quick Checkout buttons automatically use the plugin\'s shared visual settings.',
					'eilmo-checkout-flow'
				)
			);
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Order Now', 'eilmo-checkout-flow' ); ?>
						</th>
						<td>
							<?php esc_html_e( 'Uses Checkout Settings → Style.', 'eilmo-checkout-flow' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'WhatsApp Order', 'eilmo-checkout-flow' ); ?>
						</th>
						<td>
							<?php esc_html_e( 'Uses Integrations → WhatsApp Ordering style.', 'eilmo-checkout-flow' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

		</section>
		<?php
	}

    /**
     * Render one button color field.
     *
     * @param string $button Button identifier.
     * @param string $key    Setting key.
     * @param string $label  Label.
     * @param string $value  Current color.
     *
     * @return void
     */
    private function render_button_color_row(
        string $button,
        string $key,
        string $label,
        string $value
    ): void {

        $field_id =
            'eilmo-cf-quick-checkout-' .
            $button .
            '-' .
            str_replace(
                '_',
                '-',
                $key
            );

        ?>
        <tr>

            <th scope="row">

                <label
                    for="<?php
                    echo esc_attr(
                        $field_id
                    );
                    ?>"
                >
                    <?php
                    echo esc_html(
                        $label
                    );
                    ?>
                </label>

            </th>

            <td>

                <input
                    type="color"
                    id="<?php
                    echo esc_attr(
                        $field_id
                    );
                    ?>"
                    name="eilmo_cf_quick_checkout_settings[button_styles][<?php
                    echo esc_attr(
                        $button
                    );
                    ?>][<?php
                    echo esc_attr(
                        $key
                    );
                    ?>]"
                    value="<?php
                    echo esc_attr(
                        $value
                    );
                    ?>"
                >

            </td>

        </tr>
        <?php
    }

	/**
	 * Render one button numeric style field.
	 *
	 * @param string $button  Button identifier.
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param int    $value   Current value.
	 * @param int    $minimum Minimum.
	 * @param int    $maximum Maximum.
	 *
	 * @return void
	 */
	private function render_button_number_row(
		string $button,
		string $key,
		string $label,
		int $value,
		int $minimum,
		int $maximum
	): void {

		$field_id =
			'eilmo-cf-quick-checkout-' .
			$button .
			'-' .
			str_replace(
				'_',
				'-',
				$key
			);

		?>
		<tr>

			<th scope="row">

				<label
					for="<?php
					echo esc_attr(
						$field_id
					);
					?>"
				>
					<?php
					echo esc_html(
						$label
					);
					?>
				</label>

			</th>

			<td>

				<input
					type="number"
					id="<?php
					echo esc_attr(
						$field_id
					);
					?>"
					name="eilmo_cf_quick_checkout_settings[button_styles][<?php
					echo esc_attr(
						$button
					);
					?>][<?php
					echo esc_attr(
						$key
					);
					?>]"
					value="<?php
					echo esc_attr(
						(string) $value
					);
					?>"
					min="<?php
					echo esc_attr(
						(string) $minimum
					);
					?>"
					max="<?php
					echo esc_attr(
						(string) $maximum
					);
					?>"
					step="1"
				>

				<span>
					<?php
					esc_html_e(
						'px',
						'eilmo-checkout-flow'
					);
					?>
				</span>

			</td>

		</tr>
		<?php
	}

	/**
	 * Render Quick Checkout content settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_content(
		array $settings
	): void {



		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Quick Checkout Content',
					'eilmo-checkout-flow'
				),
				__(
					'Choose which existing checkout flow features and offers may appear inside Quick Checkout.',
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
							<?php
							esc_html_e(
								'Selected Product Summary',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								'eilmo_cf_quick_checkout_settings[content][show_product_summary]',
								'yes',
								(string) (
									$settings[
										'show_product_summary'
									] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show the selected product, variation and quantity at the top of Quick Checkout.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

					<tr hidden aria-hidden="true">
						<td colspan="2">
							<!-- Combo selection is Elementor-only; Special Discounts are automatic. -->
							<input type="hidden" name="eilmo_cf_quick_checkout_settings[content][show_combo_offers]" value="no">
							<input type="hidden" name="eilmo_cf_quick_checkout_settings[content][combo_offer_ids]" value="">
							<input type="hidden" name="eilmo_cf_quick_checkout_settings[content][show_order_bumps]" value="yes">
							<input type="hidden" name="eilmo_cf_quick_checkout_settings[content][order_bump_ids]" value="">
						</td>
					</tr>

					<?php
					if ( false ) {
						$this->render_content_switch_row(
						__(
							'Discounts',
							'eilmo-checkout-flow'
						),
						'show_discounts',
						(string) (
							$settings[
								'show_discounts'
							] ??
								'yes'
						),
						__(
							'Allow existing discount information and promotional discount notices to appear when applicable.',
							'eilmo-checkout-flow'
						)
						);
					}

					$this->render_content_switch_row(
						__(
							'Coupons',
							'eilmo-checkout-flow'
						),
						'show_coupons',
						(string) (
							$settings[
								'show_coupons'
							] ??
								'no'
						),
						__(
							'Allow customers to apply available coupons from inside Quick Checkout.',
							'eilmo-checkout-flow'
						)
					);

					$this->render_content_switch_row(
						__(
							'Delivery',
							'eilmo-checkout-flow'
						),
						'show_delivery',
						(string) (
							$settings[
								'show_delivery'
							] ??
								'yes'
						),
						__(
							'Show the existing Delivery section when Delivery is enabled globally.',
							'eilmo-checkout-flow'
						)
					);

					$this->render_content_switch_row(
						__(
							'Customer Information',
							'eilmo-checkout-flow'
						),
						'show_customer_information',
						(string) (
							$settings[
								'show_customer_information'
							] ??
								'yes'
						),
						__(
							'Show the existing Customer Information fields inside Quick Checkout.',
							'eilmo-checkout-flow'
						)
					);

					$this->render_content_switch_row(
						__(
							'Payment Methods',
							'eilmo-checkout-flow'
						),
						'show_payment_methods',
						(string) (
							$settings[
								'show_payment_methods'
							] ??
								'yes'
						),
						__(
							'Show the existing Payment Methods section when it is enabled globally.',
							'eilmo-checkout-flow'
						)
					);

					$this->render_content_switch_row(
						__(
							'Advance Payment',
							'eilmo-checkout-flow'
						),
						'show_advance_payment',
						(string) (
							$settings[
								'show_advance_payment'
							] ??
								'yes'
						),
						__(
							'Show the existing Advance Payment options when they are enabled and applicable.',
							'eilmo-checkout-flow'
						)
					);
					?>

				</tbody>
			</table>

			<p class="description">
				<?php
				esc_html_e(
					'Quick Checkout only displays configured offers that also exist and are eligible. These settings do not create or enable offers.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

		</section>
		<?php
	}

	/**
	 * Render customer-facing text settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_texts(
		array $settings
	): void {

		$fields =
			array(

				'product_order_button' =>
					__(
						'Product Page Order Button',
						'eilmo-checkout-flow'
					),

				'product_whatsapp_button' =>
					__(
						'Product Page WhatsApp Button',
						'eilmo-checkout-flow'
					),

				'drawer_title' =>
					__(
						'Quick Checkout Title',
						'eilmo-checkout-flow'
					),

				'drawer_description' =>
					__(
						'Quick Checkout Description',
						'eilmo-checkout-flow'
					),

				'selection_heading' =>
					__(
						'Selected Product Heading',
						'eilmo-checkout-flow'
					),

				'change_selection' =>
					__(
						'Change Selection Text',
						'eilmo-checkout-flow'
					),






				'delivery_heading' =>
					__(
						'Delivery Heading',
						'eilmo-checkout-flow'
					),

				'customer_heading' =>
					__(
						'Customer Information Heading',
						'eilmo-checkout-flow'
					),

				'payment_heading' =>
					__(
						'Payment Heading',
						'eilmo-checkout-flow'
					),

				'advance_payment_heading' =>
					__(
						'Advance Payment Heading',
						'eilmo-checkout-flow'
					),

				'coupon_heading' =>
					__(
						'Coupon Heading',
						'eilmo-checkout-flow'
					),

				'summary_heading' =>
					__(
						'Order Summary Heading',
						'eilmo-checkout-flow'
					),

				'total_label' =>
					__(
						'Total Label',
						'eilmo-checkout-flow'
					),

				'order_button' =>
					__(
						'Final Order Button',
						'eilmo-checkout-flow'
					),

				'whatsapp_button' =>
					__(
						'Final WhatsApp Button',
						'eilmo-checkout-flow'
					),

				'added_label' =>
					__(
						'Offer Added Text',
						'eilmo-checkout-flow'
					),

				'variation_required' =>
					__(
						'Variation Required Message',
						'eilmo-checkout-flow'
					),

				'close_label' =>
					__(
						'Close Label',
						'eilmo-checkout-flow'
					),
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Text & Labels',
					'eilmo-checkout-flow'
				),
				__(
					'Customize the customer-facing text used by Single Product Quick Checkout.',
					'eilmo-checkout-flow'
				)
			);
			?>

			<table
				class="form-table"
				role="presentation"
			>
				<tbody>

					<?php
					foreach (
						$fields as
							$key =>
							$label
					) :

						$field_id =
							'eilmo-cf-quick-checkout-' .
							str_replace(
								'_',
								'-',
								(string) $key
							);

						$value =
							(string) (
								$settings[
									$key
								] ??
									''
							);
						?>

						<tr>

							<th scope="row">

								<label
									for="<?php
									echo esc_attr(
										$field_id
									);
									?>"
								>
									<?php
									echo esc_html(
										$label
									);
									?>
								</label>

							</th>

							<td>

								<?php
								if (
									'drawer_description' ===
										$key
								) :
									?>

									<textarea
										id="<?php
										echo esc_attr(
											$field_id
										);
										?>"
										class="large-text"
										name="eilmo_cf_quick_checkout_settings[texts][<?php
										echo esc_attr(
											(string) $key
										);
										?>]"
										rows="3"
									><?php
									echo esc_textarea(
										$value
									);
									?></textarea>

								<?php else : ?>

									<input
										type="text"
										id="<?php
										echo esc_attr(
											$field_id
										);
										?>"
										class="regular-text"
										name="eilmo_cf_quick_checkout_settings[texts][<?php
										echo esc_attr(
											(string) $key
										);
										?>]"
										value="<?php
										echo esc_attr(
											$value
										);
										?>"
									>

								<?php endif; ?>

							</td>

						</tr>

					<?php endforeach; ?>

				</tbody>
			</table>

			<p class="description">
				<?php
				esc_html_e(
					'If an important text field is saved empty, the plugin automatically falls back to its default text.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

		</section>
		<?php
	}

	/**
	 * Render one Quick Checkout content switch row.
	 *
	 * @param string $label       Label.
	 * @param string $key         Setting key.
	 * @param string $current     Current value.
	 * @param string $description Description.
	 *
	 * @return void
	 */
	private function render_content_switch_row(
		string $label,
		string $key,
		string $current,
		string $description
	): void {

		?>
		<tr>

			<th scope="row">
				<?php
				echo esc_html(
					$label
				);
				?>
			</th>

			<td>

				<?php
				$this->render_switch(
					'eilmo_cf_quick_checkout_settings[content][' .
						$key .
						']',
					'yes',
					$current
				);
				?>

				<p class="description">
					<?php
					echo esc_html(
						$description
					);
					?>
				</p>

			</td>

		</tr>
		<?php
	}

	/**
	 * Convert stored Offer IDs to an editable string.
	 *
	 * @param mixed $offer_ids Offer IDs.
	 *
	 * @return string
	 */
	private function get_offer_ids_string(
		$offer_ids
	): string {

		if (
			is_string(
				$offer_ids
			)
		) {
			return $offer_ids;
		}

		if (
			! is_array(
				$offer_ids
			)
		) {
			return '';
		}

		$offer_ids =
			array_filter(
				array_map(
					static function (
						$offer_id
					): string {

						if (
							! is_scalar(
								$offer_id
							)
						) {
							return '';
						}

						return trim(
							(string) $offer_id
						);
					},
					$offer_ids
				)
			);

		return implode(
			', ',
			$offer_ids
		);
	}

	/**
	 * Render section header.
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 *
	 * @return void
	 */
	private function render_section_header(
		string $title,
		string $description = ''
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

			<?php if ( '' !== $description ) : ?>

				<p>
					<?php
					echo esc_html(
						$description
					);
					?>
				</p>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render yes/no switch.
	 *
	 * @param string $name          Field name.
	 * @param string $checked_value Checked value.
	 * @param string $current       Current value.
	 *
	 * @return void
	 */
	private function render_switch(
		string $name,
		string $checked_value,
		string $current
	): void {

		?>
		<input
			type="hidden"
			name="<?php
			echo esc_attr(
				$name
			);
			?>"
			value="no"
		>

		<label class="eilmo-cf-admin-switch">

			<input
				type="checkbox"
				name="<?php
				echo esc_attr(
					$name
				);
				?>"
				value="<?php
				echo esc_attr(
					$checked_value
				);
				?>"
				<?php
				checked(
					$current,
					$checked_value
				);
				?>
			>

			<span
				class="eilmo-cf-admin-switch__slider"
			></span>

		</label>
		<?php
	}

	/**
	 * Render select option.
	 *
	 * @param string $value   Value.
	 * @param string $label   Label.
	 * @param string $current Current value.
	 *
	 * @return void
	 */
	private function render_option(
		string $value,
		string $label,
		string $current
	): void {

		?>
		<option
			value="<?php
			echo esc_attr(
				$value
			);
			?>"
			<?php
			selected(
				$current,
				$value
			);
			?>
		>
			<?php
			echo esc_html(
				$label
			);
			?>
		</option>
		<?php
	}

	/**
	 * Get nested array.
	 *
	 * @param array<string,mixed> $source Source.
	 * @param string              $key    Key.
	 *
	 * @return array<string,mixed>
	 */
	private function get_array(
		array $source,
		string $key
	): array {

		if (
			! isset(
				$source[
					$key
				]
			) ||
			! is_array(
				$source[
					$key
				]
			)
		) {
			return array();
		}

		return $source[
			$key
		];
	}
}
