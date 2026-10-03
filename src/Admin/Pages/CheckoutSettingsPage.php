<?php
/**
 * Checkout settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CheckoutStyle;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Eilmo Checkout Flow settings page.
 */
final class CheckoutSettingsPage {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'eilmo-checkout-settings';

	/**
	 * Available tabs.
	 *
	 * @return array<string, string>
	 */
	public static function get_tabs(): array {

		return array(
			'general' => __(
				'General',
				'eilmo-checkout-flow'
			),

			'style' => __(
				'Style',
				'eilmo-checkout-flow'
			),

			'delivery' => __(
				'Delivery',
				'eilmo-checkout-flow'
			),

			'advance_payment' => __(
				'Payment Options',
				'eilmo-checkout-flow'
			),

            'payment_methods' => __(
                'Payment Methods',
                'eilmo-checkout-flow'
            ),

            'customer_information' => __(
                'Customer Information',
                'eilmo-checkout-flow'
            ),

            'order_management' => __(
                'Order Management',
                'eilmo-checkout-flow'
            ),

		);
	}

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

		$settings = $this->get_settings();
		$tab      = $this->get_current_tab();

		?>
		<div class="wrap eilmo-cf-admin">
			<div class="eilmo-cf-admin__header">

				<div class="eilmo-cf-admin__heading">
					<h1>
						<?php
						esc_html_e(
							'Checkout Flow',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Configure the checkout experience, global appearance, delivery, payment options, payment methods and customer information.',
							'eilmo-checkout-flow'
						);
						?>
					</p>
				</div>

			</div>

			<?php $this->render_tabs( $tab ); ?>

			<form
				method="post"
				action="options.php"
				class="eilmo-cf-settings-form"
			>
				<?php
				settings_fields(
					CheckoutSettings::OPTION_GROUP
				);

				$this->render_tab(
					$tab,
					$settings
				);

				submit_button(
					__(
						'Save Settings',
						'eilmo-checkout-flow'
					)
				);
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render settings tabs.
	 *
	 * @param string $current_tab Current tab.
	 *
	 * @return void
	 */
	private function render_tabs(
		string $current_tab
	): void {

		$tabs = self::get_tabs();

		?>
		<nav
			class="nav-tab-wrapper eilmo-cf-settings-tabs"
			aria-label="<?php esc_attr_e( 'Settings', 'eilmo-checkout-flow' ); ?>"
		>
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>

				<?php
				$url = add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
						'tab'  => $tab_key,
					),
					admin_url( 'admin.php' )
				);

				$classes = array(
					'nav-tab',
				);

				if ( $current_tab === $tab_key ) {
					$classes[] = 'nav-tab-active';
				}
				?>

				<a
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					href="<?php echo esc_url( $url ); ?>"
				>
					<?php echo esc_html( $tab_label ); ?>
				</a>

			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render current tab.
	 *
	 * @param string               $tab      Tab.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_tab(
		string $tab,
		array $settings
	): void {

		switch ( $tab ) {

			case 'style':
				$this->render_checkout_style(
					isset( $settings['checkout_style'] ) && is_array( $settings['checkout_style'] )
						? $settings['checkout_style']
						: CheckoutStyle::get_defaults()
				);
				break;

			case 'delivery':
				$this->render_delivery(
					$settings['delivery']
				);
				break;

			case 'advance_payment':
				$this->render_payment_options(
					$settings['advance_payment'],
					$settings['payment_methods'],
					isset( $settings['discounts'] ) && is_array( $settings['discounts'] ) ? $settings['discounts'] : array()
				);
				break;

            case 'payment_methods':
                $this->render_payment_methods(
                    $settings['payment_methods']
                );
                break;

            case 'customer_information':
            $this->render_customer_information(
                $settings['customer_information']
            );
            break;

            case 'order_management':
                $this->render_order_management(
                    isset( $settings['order_management'] ) && is_array( $settings['order_management'] )
                        ? $settings['order_management']
                        : array()
                );
                break;

			case 'general':
			default:
				$this->render_general(
					$settings['general'],
					isset( $settings['checkout_display'] ) && is_array( $settings['checkout_display'] ) ? $settings['checkout_display'] : array()
				);
				break;
		}
	}

	/**
	 * Render the single global checkout theme.
	 *
	 * Product, customer, delivery, payment, summary and Order Now presentation
	 * share these tokens. Per-component overrides are intentionally Elementor-
	 * only; WhatsApp remains owned by its Integration settings.
	 *
	 * @param array<string, mixed> $settings Global theme values.
	 *
	 * @return void
	 */
	private function render_checkout_style( array $settings ): void {

		$prefix = CheckoutSettings::OPTION_NAME . '[checkout_style]';
		?>
		<section class="eilmo-cf-settings-section" data-eilmo-checkout-style>
			<?php
			$this->render_section_header(
				__( 'Global Checkout Style', 'eilmo-checkout-flow' ),
				__( 'One consistent theme for products, customer fields, delivery, payment, order summary and the Order Now action. Individual component overrides are available only in Elementor.', 'eilmo-checkout-flow' )
			);
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Theme Preset', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $prefix . '[preset]' ); ?>" data-eilmo-style-preset>
								<?php $this->render_option( 'premium_purple', __( 'Default Theme', 'eilmo-checkout-flow' ), (string) ( $settings['preset'] ?? 'premium_purple' ) ); ?>
								<?php $this->render_option( 'custom', __( 'Custom', 'eilmo-checkout-flow' ), (string) ( $settings['preset'] ?? 'premium_purple' ) ); ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Theme Colours', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<div class="eilmo-cf-admin-color-grid">
								<?php
								foreach (
									array(
										'primary'          => __( 'Primary', 'eilmo-checkout-flow' ),
										'text'             => __( 'Main Text', 'eilmo-checkout-flow' ),
										'muted_text'       => __( 'Secondary Text', 'eilmo-checkout-flow' ),
										'background'       => __( 'Card Background', 'eilmo-checkout-flow' ),
										'soft_background'  => __( 'Page / Soft Background', 'eilmo-checkout-flow' ),
										'border'           => __( 'Border', 'eilmo-checkout-flow' ),
										'button_text'      => __( 'Button / Badge Text', 'eilmo-checkout-flow' ),
									
									) as $key => $label
								) {
									$this->render_style_color_control(
										$prefix . '[' . $key . ']',
										(string) ( $settings[ $key ] ?? '#ffffff' ),
										$label,
										$key
									);
								}
								?>
							</div>
						</td>
					</tr>


					<tr>
						<th scope="row"><?php esc_html_e( 'Shape and Spacing', 'eilmo-checkout-flow' ); ?></th>
						<td class="eilmo-cf-admin-number-grid">
							<?php
							foreach (
								array(
									'radius'        => array( __( 'Control Radius', 'eilmo-checkout-flow' ), 40 ),
									'parent_radius' => array( __( 'Card Radius', 'eilmo-checkout-flow' ), 48 ),
									'gap'           => array( __( 'Section Gap', 'eilmo-checkout-flow' ), 48 ),
									'card_gap'      => array( __( 'Card Gap', 'eilmo-checkout-flow' ), 48 ),
								) as $key => $control
							) {
								?>
								<label>
									<span><?php echo esc_html( $control[0] ); ?></span>
									<input type="number" min="0" max="<?php echo esc_attr( (string) $control[1] ); ?>" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $settings[ $key ] ?? 16 ) ); ?>" data-eilmo-style-number="<?php echo esc_attr( $key ); ?>">
									<span>px</span>
								</label>
								<?php
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'WhatsApp button styling is intentionally excluded. Configure it from Integrations → WhatsApp Ordering.', 'eilmo-checkout-flow' ); ?></p>
			</div>

			<div class="eilmo-cf-admin-style-preview" data-eilmo-style-preview style="<?php echo esc_attr( CheckoutStyle::get_css_variables( $settings ) ); ?>">
				<span class="eilmo-cf-admin-style-preview__badge"><?php esc_html_e( 'Selected Product', 'eilmo-checkout-flow' ); ?></span>
				<h3><?php esc_html_e( 'Global Checkout Preview', 'eilmo-checkout-flow' ); ?></h3>
				<p><?php esc_html_e( 'Products, forms, delivery, payment and summary inherit one consistent theme.', 'eilmo-checkout-flow' ); ?></p>
				<button type="button"><?php esc_html_e( 'Place Order Now', 'eilmo-checkout-flow' ); ?></button>
			</div>
		</section>
		<?php
	}

    /**
     * Render general settings.
     *
     * Security, Customer Blacklist and Checkout Access
     * are intentionally configured from the dedicated
     * Security page.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return void
     */
    private function render_general(
        array $settings,
        array $display = array()
    ): void {

        ?>
        <section class="eilmo-cf-settings-section">

            <?php
            $this->render_section_header(
                __(
                    'General Settings',
                    'eilmo-checkout-flow'
                ),
				__(
					'Configure the core checkout experience and presentation.',
					'eilmo-checkout-flow'
				)
            );
            ?>

            <table
                class="form-table"
                role="presentation"
            >
                <tbody>

                    <!-- Enable Checkout Flow -->
                    <tr>

                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Enable Checkout Flow',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>

                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[general][enabled]',
                                'yes',
                                (string) (
                                    $settings[
                                        'enabled'
                                    ] ??
                                        'yes'
                                )
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Globally enable or disable Checkout Flow.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>

                        </td>

                    </tr>

				</tbody>
            </table>

        </section>

        <section id="eilmo-cf-optional-features" class="eilmo-cf-settings-section">
            <?php
            $this->render_section_header(
                __( 'Optional Features', 'eilmo-checkout-flow' ),
                __( 'Turn optional modules on only when you use them. Integration credentials and detailed behavior stay in their dedicated pages.', 'eilmo-checkout-flow' )
            );

            $features = array(
                'abandoned_checkout' => array(
                    __( 'Abandoned Checkout', 'eilmo-checkout-flow' ),
                    __( 'Track meaningful checkout activity for recovery workflows.', 'eilmo-checkout-flow' ),
                ),
                'whatsapp_ordering' => array(
                    __( 'WhatsApp Ordering', 'eilmo-checkout-flow' ),
                    __( 'Enable WhatsApp order actions and integration controls.', 'eilmo-checkout-flow' ),
                ),
                'single_product_checkout' => array(
                    __( 'Single Product Quick Checkout', 'eilmo-checkout-flow' ),
                    __( 'Enable the single-product checkout flow where configured.', 'eilmo-checkout-flow' ),
                ),
                'default_checkout_integration' => array(
                    __( 'WooCommerce Checkout Integration', 'eilmo-checkout-flow' ),
                    __( 'Extend the normal WooCommerce checkout with Eilmo payment and delivery features.', 'eilmo-checkout-flow' ),
                ),
                'courier' => array(
                    __( 'Courier', 'eilmo-checkout-flow' ),
                    __( 'Enable courier booking and courier-related order tools.', 'eilmo-checkout-flow' ),
                ),
                'meta_tracking' => array(
                    __( 'Meta Tracking', 'eilmo-checkout-flow' ),
                    __( 'Enable Meta Pixel and Conversions API tracking features.', 'eilmo-checkout-flow' ),
                ),
            );
            ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <?php foreach ( $features as $feature_key => $feature_copy ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $feature_copy[0] ); ?></th>
                            <td>
                                <?php
                                $this->render_switch(
                                    'eilmo_cf_settings[general][' . $feature_key . ']',
                                    'yes',
                                    (string) ( $settings[ $feature_key ] ?? 'no' )
                                );
                                ?>
                                <p class="description"><?php echo esc_html( $feature_copy[1] ); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php
        $layout   = isset( $display['layout'] ) && is_array( $display['layout'] ) ? $display['layout'] : array();
        $summary  = isset( $display['summary'] ) && is_array( $display['summary'] ) ? $display['summary'] : array();
        $order    = isset( $display['order_button'] ) && is_array( $display['order_button'] ) ? $display['order_button'] : array();
        ?>

        <section class="eilmo-cf-settings-section">
            <?php $this->render_section_header( 'Checkout Layout & Language', 'Choose your checkout presentation. Elementor widgets may override these defaults. Payment and delivery rules remain separate.' );
            $presentation = \EilmoCheckout\Presentation\CheckoutPresentation::resolve( array( 'checkout_display' => $display ) ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="eilmo-checkout-preset">Checkout Layout</label></th><td>
                    <select id="eilmo-checkout-preset" name="eilmo_cf_settings[checkout_display][layout][preset]">
                        <?php $this->render_option( 'inline', 'Full Inline', $presentation['layout'] ); ?>
                        <?php $this->render_option( 'right_sticky', 'Right-side Sticky', $presentation['layout'] ); ?>
                    </select>
                </td></tr>
                <tr><th scope="row"><label for="eilmo-checkout-language">Checkout Language</label></th><td>
                    <select id="eilmo-checkout-language" name="eilmo_cf_settings[checkout_display][language]">
                        <?php $this->render_option( 'en', 'English', $presentation['language'] ); ?>
                        <?php $this->render_option( 'bn', 'বাংলা', $presentation['language'] ); ?>
                    </select>
                    <p class="description">Switches known plugin UI text between English and বাংলা. Custom merchant/customer content is never guessed; bilingual Delivery Method fields use their Bangla value when provided. Admin settings remain in English.</p>
                </td></tr>
            </tbody></table>
        </section>

        <section class="eilmo-cf-settings-section">
            <?php $this->render_section_header( __( 'Order Summary', 'eilmo-checkout-flow' ), __( 'Control selected-item visibility and the summary labels that did not already exist in other feature tabs.', 'eilmo-checkout-flow' ) ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e( 'Selected Items', 'eilmo-checkout-flow' ); ?></th><td><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][summary][show_selected_items]', 'yes', (string) ( $summary['show_selected_items'] ?? 'yes' ) ); ?>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;max-width:560px;margin-top:10px">
                    <label><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][summary][show_thumbnail]', 'yes', (string) ( $summary['show_thumbnail'] ?? 'yes' ) ); ?> <?php esc_html_e( 'Thumbnail', 'eilmo-checkout-flow' ); ?></label>
                    <label><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][summary][show_variation]', 'yes', (string) ( $summary['show_variation'] ?? 'yes' ) ); ?> <?php esc_html_e( 'Variation', 'eilmo-checkout-flow' ); ?></label>
                    <label><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][summary][show_item_price]', 'yes', (string) ( $summary['show_item_price'] ?? 'yes' ) ); ?> <?php esc_html_e( 'Item Price', 'eilmo-checkout-flow' ); ?></label>
                    </div>
                </td></tr>
                <?php
                $summary_fields = array(
                    'title' => __( 'Summary Heading', 'eilmo-checkout-flow' ), 'selected_items_title' => __( 'Selected Items Heading', 'eilmo-checkout-flow' ),
                    'product_total_label' => __( 'Product Total Label', 'eilmo-checkout-flow' ), 'combo_discount_label' => __( 'Combo Discount Label', 'eilmo-checkout-flow' ),
                    'special_offer_label' => __( 'Special Discount Label', 'eilmo-checkout-flow' ), 'automatic_discount_label' => __( 'Automatic Discount Label', 'eilmo-checkout-flow' ),
                    'coupon_discount_label' => __( 'Coupon Discount Label', 'eilmo-checkout-flow' ), 'delivery_charge_label' => __( 'Delivery Charge Label', 'eilmo-checkout-flow' ),
                    'full_payment_discount_label' => __( 'Full Payment Discount Label', 'eilmo-checkout-flow' ), 'advance_payment_label' => __( 'Advance Payment Label', 'eilmo-checkout-flow' ),
                    'grand_total_label' => __( 'Grand Total Label', 'eilmo-checkout-flow' ), 'pay_now_label' => __( 'Pay Now Label', 'eilmo-checkout-flow' ),
                    'remaining_due_label' => __( 'Remaining Due Label', 'eilmo-checkout-flow' ), 'view_summary_label' => __( 'View Summary Label', 'eilmo-checkout-flow' ),
                );
                foreach ( $summary_fields as $key => $label ) : ?>
                    <tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][summary][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $summary[ $key ] ?? '' ) ); ?>"></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        </section>

        <section class="eilmo-cf-settings-section">
            <?php $this->render_section_header( __( 'Order Button', 'eilmo-checkout-flow' ), __( 'Global order-action text, optional live amount, and the notice below the button.', 'eilmo-checkout-flow' ) ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e( 'Button Text', 'eilmo-checkout-flow' ); ?></th><td><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][order_button][label]" value="<?php echo esc_attr( (string) ( $order['label'] ?? __( 'Order Now', 'eilmo-checkout-flow' ) ) ); ?>"></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Payment CTA templates', 'eilmo-checkout-flow' ); ?></th><td>
                    <div class="eilmo-cf-payment-cta-fields">
                        <label><span><?php esc_html_e( 'Cash on Delivery', 'eilmo-checkout-flow' ); ?></span><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][order_button][cod_button_template]" value="<?php echo esc_attr( (string) ( $order['cod_button_template'] ?? 'Confirm order' ) ); ?>" placeholder="Confirm order"></label>
                        <label><span><?php esc_html_e( 'Advance Payment', 'eilmo-checkout-flow' ); ?></span><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][order_button][advance_button_template]" value="<?php echo esc_attr( (string) ( $order['advance_button_template'] ?? '{amount} Pay and confirm order' ) ); ?>" placeholder="{amount} Pay and confirm order"></label>
                        <label><span><?php esc_html_e( 'Full Payment', 'eilmo-checkout-flow' ); ?></span><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][order_button][full_button_template]" value="<?php echo esc_attr( (string) ( $order['full_button_template'] ?? 'Pay {amount}' ) ); ?>" placeholder="Pay {amount}"></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Use {amount}; the checkout language controls the default wording.', 'eilmo-checkout-flow' ); ?></p>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Processing Text', 'eilmo-checkout-flow' ); ?></th><td><input type="text" class="regular-text" name="eilmo_cf_settings[checkout_display][order_button][processing_label]" value="<?php echo esc_attr( (string) ( $order['processing_label'] ?? __( 'Processing...', 'eilmo-checkout-flow' ) ) ); ?>"></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'WhatsApp Button Placement', 'eilmo-checkout-flow' ); ?></th><td>
                    <select name="eilmo_cf_settings[checkout_display][layout][whatsapp_placement]">
                        <?php $this->render_option( 'below_payment', __( 'Below Payment / Checkout Actions', 'eilmo-checkout-flow' ), (string) ( $layout['whatsapp_placement'] ?? 'below_summary' ) ); ?>
                        <?php $this->render_option( 'below_summary', __( 'Below Order Summary', 'eilmo-checkout-flow' ), (string) ( $layout['whatsapp_placement'] ?? 'below_summary' ) ); ?>
                        <?php $this->render_option( 'both', __( 'Both Locations', 'eilmo-checkout-flow' ), (string) ( $layout['whatsapp_placement'] ?? 'below_summary' ) ); ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Controls checkout placement only. WhatsApp number, button text and message template remain in WhatsApp Configuration.', 'eilmo-checkout-flow' ); ?></p>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Show Amount on Button', 'eilmo-checkout-flow' ); ?></th><td><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][order_button][show_amount]', 'yes', (string) ( $order['show_amount'] ?? 'no' ) ); ?> <select name="eilmo_cf_settings[checkout_display][order_button][amount_source]"><?php $this->render_option( 'auto', __( 'Auto', 'eilmo-checkout-flow' ), (string) ( $order['amount_source'] ?? 'auto' ) ); ?><?php $this->render_option( 'grand_total', __( 'Grand Total', 'eilmo-checkout-flow' ), (string) ( $order['amount_source'] ?? 'auto' ) ); ?><?php $this->render_option( 'pay_now', __( 'Pay Now', 'eilmo-checkout-flow' ), (string) ( $order['amount_source'] ?? 'auto' ) ); ?></select></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Button Footer Notice', 'eilmo-checkout-flow' ); ?></th><td><?php $this->render_switch( 'eilmo_cf_settings[checkout_display][order_button][footer_enabled]', 'yes', (string) ( $order['footer_enabled'] ?? 'yes' ) ); ?><br><input type="text" class="large-text" name="eilmo_cf_settings[checkout_display][order_button][footer_text]" value="<?php echo esc_attr( (string) ( $order['footer_text'] ?? '' ) ); ?>"><select name="eilmo_cf_settings[checkout_display][order_button][footer_icon]"><?php foreach ( array( 'shield'=>__( 'Shield','eilmo-checkout-flow' ), 'lock'=>__( 'Lock','eilmo-checkout-flow' ), 'none'=>__( 'No Icon','eilmo-checkout-flow' ) ) as $value=>$label ) { $this->render_option( $value, $label, (string) ( $order['footer_icon'] ?? 'shield' ) ); } ?></select></td></tr>
            </tbody></table>
        </section>

		<section class="eilmo-cf-settings-section">
			<?php
			$this->render_section_header(
				__( 'Data Retention', 'eilmo-checkout-flow' ),
				__( 'Choose what happens only when the plugin is deleted from WordPress.', 'eilmo-checkout-flow' )
			);
			?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove Plugin Data on Uninstall', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[general][delete_data_on_uninstall]',
								'yes',
								(string) ( $settings['delete_data_on_uninstall'] ?? 'no' )
							);
							?>
							<p class="description">
								<?php esc_html_e( 'Disabled by default. When enabled, deleting the plugin removes Eilmo settings, caches, scheduled tasks and plugin-owned tables. WooCommerce orders and their historical order metadata are preserved.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>
        <?php
    }


	/**
	 * Render delivery settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_delivery(
		array $settings
	): void {

		$methods = isset( $settings['methods'] ) &&
			is_array( $settings['methods'] )
				? $settings['methods']
				: array();



		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Delivery Settings',
					'eilmo-checkout-flow'
				),
				__(
					'Create unlimited delivery methods and control delivery charges from the backend.',
					'eilmo-checkout-flow'
				)
			);
			?>

			<table class="form-table" role="presentation">
				<tbody>

					<tr>
						<th scope="row">
							<?php
							esc_html_e(
								'Enable Delivery',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[delivery][enabled]',
								'yes',
								(string) $settings['enabled']
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-delivery-title">
								<?php
								esc_html_e(
									'Section Title',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-delivery-title"
								class="regular-text"
								name="eilmo_cf_settings[delivery][title]"
								value="<?php echo esc_attr( (string) $settings['title'] ); ?>"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php
							esc_html_e(
								'Required',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[delivery][required]',
								'yes',
								(string) $settings['required']
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php
							esc_html_e(
								'Show Descriptions',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[delivery][show_description]',
								'yes',
								(string) $settings['show_description']
							);
							?>
						</td>
					</tr>


					<tr>
						<th scope="row"><?php esc_html_e( 'Method Columns', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<label style="display:inline-block;margin-right:12px;">
								<?php esc_html_e( 'Desktop', 'eilmo-checkout-flow' ); ?>
								<input type="number" min="1" max="4" class="small-text" name="eilmo_cf_settings[delivery][columns_desktop]" value="<?php echo esc_attr( (string) ( $settings['columns_desktop'] ?? 2 ) ); ?>">
							</label>
							<label style="display:inline-block;margin-right:12px;">
								<?php esc_html_e( 'Tablet', 'eilmo-checkout-flow' ); ?>
								<input type="number" min="1" max="3" class="small-text" name="eilmo_cf_settings[delivery][columns_tablet]" value="<?php echo esc_attr( (string) ( $settings['columns_tablet'] ?? 2 ) ); ?>">
							</label>
							<label style="display:inline-block;">
								<?php esc_html_e( 'Mobile', 'eilmo-checkout-flow' ); ?>
								<input type="number" min="1" max="2" class="small-text" name="eilmo_cf_settings[delivery][columns_mobile]" value="<?php echo esc_attr( (string) ( $settings['columns_mobile'] ?? 1 ) ); ?>">
							</label>
							<p class="description"><?php esc_html_e( 'Controls how many delivery method cards appear per row.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Name & Price Layout', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<select name="eilmo_cf_settings[delivery][content_layout]">
								<?php $this->render_option( 'inline', __( 'Side by Side — Name Left, Price Right', 'eilmo-checkout-flow' ), (string) ( $settings['content_layout'] ?? 'inline' ) ); ?>
								<?php $this->render_option( 'stacked', __( 'Stacked — Name Above, Price Below', 'eilmo-checkout-flow' ), (string) ( $settings['content_layout'] ?? 'inline' ) ); ?>
							</select>
						</td>
					</tr>

				</tbody>
			</table>

			<hr>

			<div class="eilmo-cf-admin-repeater">

				<div class="eilmo-cf-admin-repeater__heading">
					<div>
						<h2>
							<?php
							esc_html_e(
								'Delivery Methods',
								'eilmo-checkout-flow'
							);
							?>
						</h2>

						<p>
							<?php
							esc_html_e(
								'Add as many delivery options as required.',
								'eilmo-checkout-flow'
							);
							?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Delivery method names and descriptions are merchant content. Enter English normally and optionally add Bangla. When Checkout Language is বাংলা, the Bangla value is used; if it is empty, the English value is shown.', 'eilmo-checkout-flow' ); ?>
						</p>
					</div>

					<button
						type="button"
						class="button button-secondary"
						data-eilmo-add-delivery-method
					>
						<?php
						esc_html_e(
							'Add Delivery Method',
							'eilmo-checkout-flow'
						);
						?>
					</button>
				</div>

				<div
					class="eilmo-cf-admin-repeater__items"
					data-eilmo-delivery-methods
				>
					<?php foreach ( $methods as $index => $method ) : ?>
						<?php
						if ( ! is_array( $method ) ) {
							continue;
						}

						$this->render_delivery_method(
							(int) $index,
							$method
						);
						?>
					<?php endforeach; ?>
				</div>

			</div>

			<hr>

			<h2>
				<?php
				esc_html_e(
					'Default Delivery Method',
					'eilmo-checkout-flow'
				);
				?>
			</h2>

			<select
				name="eilmo_cf_settings[delivery][default_method]"
			>
				<option value="">
					<?php
					esc_html_e(
						'Automatic / First Available',
						'eilmo-checkout-flow'
					);
					?>
				</option>

				<?php foreach ( $methods as $method ) : ?>

					<?php
					if (
						! is_array( $method ) ||
						empty( $method['id'] ) ||
						empty( $method['label'] )
					) {
						continue;
					}
					?>

					<option
						value="<?php echo esc_attr( (string) $method['id'] ); ?>"
						<?php
						selected(
							(string) $settings['default_method'],
							(string) $method['id']
						);
						?>
					>
						<?php echo esc_html( (string) $method['label'] ); ?>
					</option>

				<?php endforeach; ?>
			</select>


		</section>
		<?php
	}

	/**
	 * Render one delivery method.
	 *
	 * @param int                  $index  Index.
	 * @param array<string, mixed> $method Method.
	 *
	 * @return void
	 */
	private function render_delivery_method(
		int $index,
		array $method
	): void {
		?>
		<div
			class="eilmo-cf-admin-repeater__item"
			data-eilmo-delivery-method
		>

			<div class="eilmo-cf-admin-repeater__item-header">
				<strong>
					<?php
					echo esc_html(
						! empty( $method['label'] )
							? (string) $method['label']
							: __(
								'Delivery Method',
								'eilmo-checkout-flow'
							)
					);
					?>
				</strong>

				<button
					type="button"
					class="button-link-delete"
					data-eilmo-remove-repeater-item
				>
					<?php
					esc_html_e(
						'Remove',
						'eilmo-checkout-flow'
					);
					?>
				</button>
			</div>

			<div class="eilmo-cf-admin-grid">

				<div class="eilmo-cf-admin-field">
					<label><?php esc_html_e( 'Method Name — English', 'eilmo-checkout-flow' ); ?></label>
					<input
						type="text"
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][label]"
						value="<?php echo esc_attr( (string) ( $method['label'] ?? '' ) ); ?>"
						data-eilmo-delivery-label
					>
				</div>

				<div class="eilmo-cf-admin-field">
					<label><?php esc_html_e( 'Method Name — বাংলা (optional)', 'eilmo-checkout-flow' ); ?></label>
					<input
						type="text"
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][label_bn]"
						value="<?php echo esc_attr( (string) ( $method['label_bn'] ?? '' ) ); ?>"
						placeholder="ঢাকার ভেতরে"
					>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php
						esc_html_e(
							'Method ID',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<input
						type="text"
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][id]"
						value="<?php echo esc_attr( (string) ( $method['id'] ?? '' ) ); ?>"
						placeholder="inside-dhaka"
					>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php
						esc_html_e(
							'Charge',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<input
						type="number"
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][charge]"
						value="<?php echo esc_attr( (string) ( $method['charge'] ?? 0 ) ); ?>"
						min="0"
						step="0.01"
					>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php
						esc_html_e(
							'Sort Order',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<input
						type="number"
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][sort_order]"
						value="<?php echo esc_attr( (string) ( $method['sort_order'] ?? 10 ) ); ?>"
						min="0"
						step="1"
					>
				</div>

				<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
					<label><?php esc_html_e( 'Description — English', 'eilmo-checkout-flow' ); ?></label>
					<textarea
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][description]"
						rows="3"
					><?php echo esc_textarea( (string) ( $method['description'] ?? '' ) ); ?></textarea>
				</div>

				<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
					<label><?php esc_html_e( 'Description — বাংলা (optional)', 'eilmo-checkout-flow' ); ?></label>
					<textarea
						name="eilmo_cf_settings[delivery][methods][<?php echo esc_attr( (string) $index ); ?>][description_bn]"
						rows="3"
					><?php echo esc_textarea( (string) ( $method['description_bn'] ?? '' ) ); ?></textarea>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php
						esc_html_e(
							'Enabled',
							'eilmo-checkout-flow'
						);
						?>
					</label>

					<?php
					$this->render_switch(
						sprintf(
							'eilmo_cf_settings[delivery][methods][%d][enabled]',
							$index
						),
						'yes',
						(string) ( $method['enabled'] ?? 'yes' )
					);
					?>
				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Render payment-option settings.
	 *
	 * The internal settings key remains "advance_payment"
	 * for backwards compatibility. The admin UI now treats
	 * this section as the top-level payment-choice controller:
	 *
	 * - Cash on Delivery.
	 * - Advance Payment.
	 * - Full Payment.
	 *
	 * Cash on Delivery title/description remain stored under
	 * payment_methods[cash_on_delivery] for backwards
	 * compatibility, but are configured from this tab.
	 *
	 * @param array<string, mixed> $settings        Payment option settings.
	 * @param array<string, mixed> $payment_methods Payment method settings.
	 * @param array<string, mixed> $discounts       Discount settings.
	 *
	 * @return void
	 */
	private function render_payment_options(
		array $settings,
		array $payment_methods,
		array $discounts
	): void {

		$rules =
			isset(
				$settings['rules']
			) &&
			is_array(
				$settings['rules']
			)
				? $settings['rules']
				: array();

		$cash_on_delivery =
			isset(
				$payment_methods['cash_on_delivery']
			) &&
			is_array(
				$payment_methods['cash_on_delivery']
			)
				? $payment_methods['cash_on_delivery']
				: array();

		$full_payment =
			isset( $discounts['full_payment'] ) && is_array( $discounts['full_payment'] )
				? $discounts['full_payment']
				: array();

		$current_payment_type =
			sanitize_key(
				(string) (
					$settings['default_payment_type'] ??
						'advance'
				)
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Payment Options',
					'eilmo-checkout-flow'
				),
				__(
					'Choose which payment choices customers can use at checkout. Cash on Delivery skips the Payment Method section, while Advance and Full Payment require a payment method.',
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
								'Enable Payment Options',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[advance_payment][enabled]',
								'yes',
								(string) (
									$settings['enabled'] ??
										'no'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Enable the top-level payment choice section on Checkout Flow.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-payment-options-title">
								<?php
								esc_html_e(
									'Section Title',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-payment-options-title"
								class="regular-text"
								name="eilmo_cf_settings[advance_payment][title]"
								value="<?php
								echo esc_attr(
									(string) (
										$settings['title'] ??
											''
									)
								);
								?>"
							>

							<p class="description">
								<?php esc_html_e( 'Heading displayed above the available payment choices.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

				</tbody>
			</table>

			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><?php esc_html_e( 'Payment Option Layout', 'eilmo-checkout-flow' ); ?></th><td><select name="eilmo_cf_settings[advance_payment][display_layout]"><?php $this->render_option( 'grid', __( 'Grid', 'eilmo-checkout-flow' ), (string) ( $settings['display_layout'] ?? 'grid' ) ); ?><?php $this->render_option( 'list', __( 'One Column List', 'eilmo-checkout-flow' ), (string) ( $settings['display_layout'] ?? 'grid' ) ); ?></select></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Grid Columns', 'eilmo-checkout-flow' ); ?></th><td>
					<label><?php esc_html_e( 'Desktop', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="4" name="eilmo_cf_settings[advance_payment][columns_desktop]" value="<?php echo esc_attr( (string) ( $settings['columns_desktop'] ?? 3 ) ); ?>"></label>&nbsp;
					<label><?php esc_html_e( 'Tablet', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="4" name="eilmo_cf_settings[advance_payment][columns_tablet]" value="<?php echo esc_attr( (string) ( $settings['columns_tablet'] ?? 2 ) ); ?>"></label>&nbsp;
					<label><?php esc_html_e( 'Mobile', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="2" name="eilmo_cf_settings[advance_payment][columns_mobile]" value="<?php echo esc_attr( (string) ( $settings['columns_mobile'] ?? 1 ) ); ?>"></label>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Payment Footer Notice', 'eilmo-checkout-flow' ); ?></th><td><?php $this->render_switch( 'eilmo_cf_settings[advance_payment][footer_enabled]', 'yes', (string) ( $settings['footer_enabled'] ?? 'yes' ) ); ?><br><input type="text" class="large-text" name="eilmo_cf_settings[advance_payment][footer_text]" value="<?php echo esc_attr( (string) ( $settings['footer_text'] ?? '' ) ); ?>"><select name="eilmo_cf_settings[advance_payment][footer_icon]"><?php foreach ( array( 'shield'=>__( 'Shield','eilmo-checkout-flow' ), 'lock'=>__( 'Lock','eilmo-checkout-flow' ), 'none'=>__( 'No Icon','eilmo-checkout-flow' ) ) as $value=>$label ) { $this->render_option( $value, $label, (string) ( $settings['footer_icon'] ?? 'shield' ) ); } ?></select></td></tr>
			</tbody></table>

			<hr>

			<h2>
				<?php
				esc_html_e(
					'Available Payment Options',
					'eilmo-checkout-flow'
				);
				?>
			</h2>

			<p class="description">
				<?php
				esc_html_e(
					'Enable any combination of Cash on Delivery, Advance Payment and Full Payment. At least one option should remain enabled when Payment Options is active.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

			<table
				class="form-table"
				role="presentation"
			>
				<tbody>

					<tr>
						<th scope="row">
							<?php
							esc_html_e(
								'Cash on Delivery',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[advance_payment][allow_cash_on_delivery]',
								'yes',
								(string) (
									$settings['allow_cash_on_delivery'] ??
										'no'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show Cash on Delivery as a top-level payment option. When selected, the Payment Method section will be hidden.',
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
								'Advance Payment',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[advance_payment][allow_advance_payment]',
								'yes',
								(string) (
									$settings['allow_advance_payment'] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Allow customers to pay the configured advance amount. A Payment Method is required for this option.',
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
								'Full Payment',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_settings[advance_payment][allow_full_payment]',
								'yes',
								(string) (
									$settings['allow_full_payment'] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Allow customers to pay the full order amount. A Payment Method is required for this option.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-default-payment-option">
								<?php
								esc_html_e(
									'Default Payment Option',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<select
								id="eilmo-cf-default-payment-option"
								name="eilmo_cf_settings[advance_payment][default_payment_type]"
							>
								<?php
								$this->render_option(
									'cash_on_delivery',
									__(
										'Cash on Delivery',
										'eilmo-checkout-flow'
									),
									$current_payment_type
								);

								$this->render_option(
									'advance',
									__(
										'Pay Advance',
										'eilmo-checkout-flow'
									),
									$current_payment_type
								);

								$this->render_option(
									'full',
									__(
										'Pay Full Amount',
										'eilmo-checkout-flow'
									),
									$current_payment_type
								);
								?>
							</select>

							<p class="description">
								<?php
								esc_html_e(
									'The settings sanitizer will keep the default aligned with the enabled payment options.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

				</tbody>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Full Payment Benefits', 'eilmo-checkout-flow' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure the incentive for customers who choose Full Payment. Percentage and flat discounts are calculated dynamically from the current cart. Free Delivery can be enabled by itself or combined with a discount.', 'eilmo-checkout-flow' ); ?>
			</p>

			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Full Payment Discount', 'eilmo-checkout-flow' ); ?></th>
					<td>
						<?php $this->render_switch( 'eilmo_cf_settings[discounts][full_payment][enabled]', 'yes', (string) ( $full_payment['enabled'] ?? 'no' ) ); ?>
						<p class="description"><?php esc_html_e( 'Turn on a percentage or flat discount for Full Payment.', 'eilmo-checkout-flow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Discount Type', 'eilmo-checkout-flow' ); ?></th>
					<td><select name="eilmo_cf_settings[discounts][full_payment][type]">
						<?php $this->render_option( 'percentage', __( 'Percentage Discount', 'eilmo-checkout-flow' ), (string) ( $full_payment['type'] ?? 'percentage' ) ); ?>
						<?php $this->render_option( 'fixed', __( 'Flat Discount', 'eilmo-checkout-flow' ), (string) ( $full_payment['type'] ?? 'percentage' ) ); ?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><label for="eilmo-cf-full-payment-discount-value"><?php esc_html_e( 'Discount Value', 'eilmo-checkout-flow' ); ?></label></th>
					<td><input type="number" min="0" step="0.01" id="eilmo-cf-full-payment-discount-value" name="eilmo_cf_settings[discounts][full_payment][value]" value="<?php echo esc_attr( (string) ( $full_payment['value'] ?? 0 ) ); ?>"><p class="description"><?php esc_html_e( 'For Percentage, enter 5 for 5%. For Flat Discount, enter the currency amount.', 'eilmo-checkout-flow' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Discount Basis', 'eilmo-checkout-flow' ); ?></th>
					<td><select name="eilmo_cf_settings[discounts][full_payment][basis]">
						<?php $this->render_option( 'product_total', __( 'Product Total', 'eilmo-checkout-flow' ), (string) ( $full_payment['basis'] ?? 'discounted_product_total' ) ); ?>
						<?php $this->render_option( 'discounted_product_total', __( 'Product Total After Discounts', 'eilmo-checkout-flow' ), (string) ( $full_payment['basis'] ?? 'discounted_product_total' ) ); ?>
						<?php $this->render_option( 'grand_total', __( 'Grand Total', 'eilmo-checkout-flow' ), (string) ( $full_payment['basis'] ?? 'discounted_product_total' ) ); ?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Minimum Amount', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="number" min="0" step="0.01" name="eilmo_cf_settings[discounts][full_payment][minimum_amount]" value="<?php echo esc_attr( (string) ( $full_payment['minimum_amount'] ?? 0 ) ); ?>"><p class="description"><?php esc_html_e( '0 means the discount is available at any order amount.', 'eilmo-checkout-flow' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Maximum Discount', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="number" min="0" step="0.01" name="eilmo_cf_settings[discounts][full_payment][maximum_discount]" value="<?php echo esc_attr( (string) ( $full_payment['maximum_discount'] ?? 0 ) ); ?>"><p class="description"><?php esc_html_e( 'Optional cap. 0 means no maximum.', 'eilmo-checkout-flow' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Free Delivery on Full Payment', 'eilmo-checkout-flow' ); ?></th>
					<td>
						<?php $this->render_switch( 'eilmo_cf_settings[discounts][full_payment][free_delivery]', 'yes', (string) ( $full_payment['free_delivery'] ?? 'no' ) ); ?>
						<p class="description"><?php esc_html_e( 'When Full Payment is selected, the currently selected Eilmo delivery method becomes free. Switching back to COD or Advance restores its normal charge.', 'eilmo-checkout-flow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full Payment Label', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="text" class="regular-text" name="eilmo_cf_settings[discounts][full_payment][label]" value="<?php echo esc_attr( (string) ( $full_payment['label'] ?? __( 'Full Payment', 'eilmo-checkout-flow' ) ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full Payment Description', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="text" class="large-text" name="eilmo_cf_settings[discounts][full_payment][description]" value="<?php echo esc_attr( (string) ( $full_payment['description'] ?? __( 'Pay the full amount in advance.', 'eilmo-checkout-flow' ) ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Discount Badge', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="text" class="regular-text" name="eilmo_cf_settings[discounts][full_payment][discount_badge]" value="<?php echo esc_attr( (string) ( $full_payment['discount_badge'] ?? __( 'Get {discount} OFF', 'eilmo-checkout-flow' ) ) ); ?>"><p class="description"><?php esc_html_e( 'Use {discount} for the configured percentage or flat amount. The badge is hidden when no discount is active.', 'eilmo-checkout-flow' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Free Delivery Badge', 'eilmo-checkout-flow' ); ?></th>
					<td><input type="text" class="regular-text" name="eilmo_cf_settings[discounts][full_payment][free_delivery_badge]" value="<?php echo esc_attr( (string) ( $full_payment['free_delivery_badge'] ?? __( 'Free Delivery', 'eilmo-checkout-flow' ) ) ); ?>"></td>
				</tr>
			</tbody></table>

			<hr>

			<h2>
				<?php
				esc_html_e(
					'Cash on Delivery Text',
					'eilmo-checkout-flow'
				);
				?>
			</h2>

			<p class="description">
				<?php
				esc_html_e(
					'Cash on Delivery is now configured as a Payment Option. These values remain stored in the existing Cash on Delivery settings for backwards compatibility.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

			<input
				type="hidden"
				name="eilmo_cf_settings[payment_methods][cash_on_delivery][enabled]"
				value="yes"
			>

			<table
				class="form-table"
				role="presentation"
			>
				<tbody>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-cod-title">
								<?php
								esc_html_e(
									'Title',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-cod-title"
								class="regular-text"
								name="eilmo_cf_settings[payment_methods][cash_on_delivery][title]"
								value="<?php
								echo esc_attr(
									(string) (
										$cash_on_delivery['title'] ??
											__(
												'Cash on Delivery',
												'eilmo-checkout-flow'
											)
									)
								);
								?>"
							>

							<p class="description">
								<?php esc_html_e( 'Keep this title short so the option card remains easy to scan.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-cod-description">
								<?php
								esc_html_e(
									'Description',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<textarea
								id="eilmo-cf-cod-description"
								class="large-text"
								name="eilmo_cf_settings[payment_methods][cash_on_delivery][description]"
								rows="3"
							><?php
							echo esc_textarea(
								(string) (
									$cash_on_delivery['description'] ??
										__(
											'Pay with cash upon delivery.',
											'eilmo-checkout-flow'
										)
								)
							);
							?></textarea>
						</td>
					</tr>

				</tbody>
			</table>

			<hr>

			<h2>
				<?php
				esc_html_e(
					'Advance Payment',
					'eilmo-checkout-flow'
				);
				?>
			</h2>

			<p class="description">
				<?php
				esc_html_e(
					'The following settings are used only when the Advance Payment option is enabled.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

			<table
				class="form-table"
				role="presentation"
			>
				<tbody>

					<tr>
						<th scope="row">
							<?php
							esc_html_e(
								'Calculation Basis',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<select
								name="eilmo_cf_settings[advance_payment][calculation_basis]"
							>
								<?php
								$current_basis =
									(string) (
										$settings['calculation_basis'] ??
											'grand_total'
									);

								$this->render_option(
									'product_total',
									__(
										'Product Total',
										'eilmo-checkout-flow'
									),
									$current_basis
								);

								$this->render_option(
									'discounted_product_total',
									__(
										'Product Total After Discounts',
										'eilmo-checkout-flow'
									),
									$current_basis
								);

								$this->render_option(
									'grand_total',
									__(
										'Grand Total',
										'eilmo-checkout-flow'
									),
									$current_basis
								);
								?>
							</select>
						</td>
					</tr>

				</tbody>
			</table>

			<hr>

			<h2>
				<?php
				esc_html_e(
					'Advance Payment Text',
					'eilmo-checkout-flow'
				);
				?>
			</h2>

			<p class="description">
				<?php
				esc_html_e(
					'Customize the customer-facing advance-payment text. Available placeholders: {pay_now}, {remaining_due}, {grand_total}.',
					'eilmo-checkout-flow'
				);
				?>
			</p>

			<table
				class="form-table"
				role="presentation"
			>
				<tbody>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-advance-label">
								<?php
								esc_html_e(
									'Advance Option Label',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-advance-label"
								class="regular-text"
								name="eilmo_cf_settings[advance_payment][advance_label]"
								value="<?php
								echo esc_attr(
									(string) (
										$settings['advance_label'] ??
											''
									)
								);
								?>"
							>

							<p class="description">
								<?php esc_html_e( 'Keep this title short, for example “Advance Payment”. Longer instructions belong in Expanded Details Description.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-advance-card-subtitle">
								<?php
								esc_html_e(
									'Advance Card Subtitle',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-advance-card-subtitle"
								class="large-text"
								name="eilmo_cf_settings[advance_payment][advance_card_subtitle]"
								value="<?php
								echo esc_attr(
									(string) (
										$settings['advance_card_subtitle'] ??
											__( 'Pay {pay_now} in advance.', 'eilmo-checkout-flow' )
									)
								);
								?>"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Keep this short. It appears directly below the Advance Payment card title and supports {pay_now}.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-advance-details-description">
								<?php esc_html_e( 'Expanded Details Description', 'eilmo-checkout-flow' ); ?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-advance-details-description"
								class="large-text"
								name="eilmo_cf_settings[advance_payment][advance_description]"
								value="<?php echo esc_attr( (string) ( $settings['advance_description'] ?? '' ) ); ?>"
							>

							<p class="description">
								<?php esc_html_e( 'Shown below the option cards when Advance Payment is selected. Supports {pay_now}, {remaining_due} and {grand_total}.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-advance-empty-description">
								<?php
								esc_html_e(
									'Before Product Selection',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="text"
								id="eilmo-cf-advance-empty-description"
								class="large-text"
								name="eilmo_cf_settings[advance_payment][empty_description]"
								value="<?php
								echo esc_attr(
									(string) (
										$settings['empty_description'] ??
											__(
												'Select a product to see the advance amount.',
												'eilmo-checkout-flow'
											)
									)
								);
								?>"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Shown in the Advance Payment option before a product is selected.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

				</tbody>
			</table>

			<hr>

			<div class="eilmo-cf-admin-repeater">

				<div class="eilmo-cf-admin-repeater__heading">

					<div>
						<h2>
							<?php
							esc_html_e(
								'Advance Rules',
								'eilmo-checkout-flow'
							);
							?>
						</h2>

						<p>
							<?php
							esc_html_e(
								'Example: ৳1,000–৳1,999 requires ৳200 advance.',
								'eilmo-checkout-flow'
							);
							?>
						</p>
					</div>

					<button
						type="button"
						class="button button-secondary"
						data-eilmo-add-advance-rule
					>
						<?php
						esc_html_e(
							'Add Advance Rule',
							'eilmo-checkout-flow'
						);
						?>
					</button>

				</div>

				<div data-eilmo-advance-rules>

					<?php foreach ( $rules as $index => $rule ) : ?>

						<?php
						if (
							is_array(
								$rule
							)
						) {
							$this->render_advance_rule(
								(int) $index,
								$rule
							);
						}
						?>

					<?php endforeach; ?>

				</div>

			</div>

		</section>
		<?php
	}

    /**
     * Render payment-method settings.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return void
     */
    private function render_payment_methods(
        array $settings
    ): void {

        $woocommerce_gateways = isset( $settings['woocommerce_gateways'] ) && is_array( $settings['woocommerce_gateways'] )
            ? $settings['woocommerce_gateways']
            : array();
        $default_method = (string) ( $settings['default_method'] ?? 'first_available' );
        $payments_url = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
        ?>
        <section class="eilmo-cf-settings-section">
            <?php
            $this->render_section_header(
                __( 'Payment Methods', 'eilmo-checkout-flow' ),
                __( 'Eilmo 2.1 uses real WooCommerce gateways. Configure bKash, Nagad, Direct Bank Transfer and any other gateways from WooCommerce → Settings → Payments. This tab only controls how those enabled gateways appear inside Checkout Flow and Quick Checkout.', 'eilmo-checkout-flow' )
            );
            ?>

            <div class="notice notice-info inline" style="margin:0 0 24px;padding:14px 18px;">
                <p>
                    <strong><?php esc_html_e( 'New payment architecture:', 'eilmo-checkout-flow' ); ?></strong>
                    <?php esc_html_e( 'Eilmo bKash and Eilmo Nagad are now native WooCommerce gateways. Cash on Delivery uses WooCommerce COD, and Bank Transfer uses WooCommerce Direct Bank Transfer (BACS).', 'eilmo-checkout-flow' ); ?>
                    <a class="button button-secondary" style="margin-left:10px" href="<?php echo esc_url( $payments_url ); ?>"><?php esc_html_e( 'Open WooCommerce Payments', 'eilmo-checkout-flow' ); ?></a>
                </p>
            </div>

            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e( 'Enable Payment Methods', 'eilmo-checkout-flow' ); ?></th><td>
                    <?php $this->render_switch( 'eilmo_cf_settings[payment_methods][enabled]', 'yes', (string) ( $settings['enabled'] ?? 'yes' ) ); ?>
                    <p class="description"><?php esc_html_e( 'Show enabled WooCommerce gateways for Advance and Full Payment.', 'eilmo-checkout-flow' ); ?></p>
                </td></tr>
                <tr><th scope="row"><label for="eilmo-cf-payment-methods-title"><?php esc_html_e( 'Section Title', 'eilmo-checkout-flow' ); ?></label></th><td>
                    <input type="text" id="eilmo-cf-payment-methods-title" class="regular-text" name="eilmo_cf_settings[payment_methods][title]" value="<?php echo esc_attr( (string) ( $settings['title'] ?? __( 'Payment Method', 'eilmo-checkout-flow' ) ) ); ?>">
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Display Layout', 'eilmo-checkout-flow' ); ?></th><td>
                    <select name="eilmo_cf_settings[payment_methods][display_layout]">
                        <?php $this->render_option( 'list', __( 'One Column List', 'eilmo-checkout-flow' ), (string) ( $settings['display_layout'] ?? 'list' ) ); ?>
                        <?php $this->render_option( 'grid', __( 'Grid', 'eilmo-checkout-flow' ), (string) ( $settings['display_layout'] ?? 'list' ) ); ?>
                    </select>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Grid Columns', 'eilmo-checkout-flow' ); ?></th><td>
                    <label style="margin-right:12px;display:inline-block;"><?php esc_html_e( 'Desktop', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="6" class="small-text" name="eilmo_cf_settings[payment_methods][columns_desktop]" value="<?php echo esc_attr( (string) ( $settings['columns_desktop'] ?? 3 ) ); ?>"></label>
                    <label style="margin-right:12px;display:inline-block;"><?php esc_html_e( 'Tablet', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="4" class="small-text" name="eilmo_cf_settings[payment_methods][columns_tablet]" value="<?php echo esc_attr( (string) ( $settings['columns_tablet'] ?? 2 ) ); ?>"></label>
                    <label style="display:inline-block;"><?php esc_html_e( 'Mobile', 'eilmo-checkout-flow' ); ?> <input type="number" min="1" max="2" class="small-text" name="eilmo_cf_settings[payment_methods][columns_mobile]" value="<?php echo esc_attr( (string) ( $settings['columns_mobile'] ?? 1 ) ); ?>"></label>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Required', 'eilmo-checkout-flow' ); ?></th><td>
                    <?php $this->render_switch( 'eilmo_cf_settings[payment_methods][required]', 'yes', (string) ( $settings['required'] ?? 'yes' ) ); ?>
                </td></tr>
                <tr><th scope="row"><label for="eilmo-cf-default-payment-method"><?php esc_html_e( 'Default Payment Method', 'eilmo-checkout-flow' ); ?></label></th><td>
                    <select id="eilmo-cf-default-payment-method" name="eilmo_cf_settings[payment_methods][default_method]">
                        <?php $this->render_option( 'first_available', __( 'Automatic / First Available', 'eilmo-checkout-flow' ), $default_method ); ?>
                        <?php $this->render_option( 'bkash', 'bKash', $default_method ); ?>
                        <?php $this->render_option( 'nagad', 'Nagad', $default_method ); ?>
                        <?php $this->render_option( 'bacs', __( 'Direct Bank Transfer', 'eilmo-checkout-flow' ), $default_method ); ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'The option is used only when that WooCommerce gateway is enabled and available.', 'eilmo-checkout-flow' ); ?></p>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'WooCommerce Gateways', 'eilmo-checkout-flow' ); ?></th><td>
                    <input type="hidden" name="eilmo_cf_settings[payment_methods][woocommerce_gateways][enabled]" value="yes">
                    <span><?php esc_html_e( 'Managed by Enable Payment Methods above.', 'eilmo-checkout-flow' ); ?></span>
                    <p class="description"><?php esc_html_e( 'Read enabled gateways from WooCommerce. COD is kept as the top-level Cash option and is never duplicated here.', 'eilmo-checkout-flow' ); ?></p>
                </td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Gateway Descriptions', 'eilmo-checkout-flow' ); ?></th><td>
                    <?php $this->render_switch( 'eilmo_cf_settings[payment_methods][woocommerce_gateways][show_description]', 'yes', (string) ( $woocommerce_gateways['show_description'] ?? 'yes' ) ); ?>
                </td></tr>
            </tbody></table>

            <input type="hidden" name="eilmo_cf_settings[payment_methods][woocommerce_gateways][exclude_duplicate_cod]" value="yes">
            <input type="hidden" name="eilmo_cf_settings[payment_methods][woocommerce_gateways][exclude_duplicate_bacs]" value="no">
            <input type="hidden" name="eilmo_cf_settings[payment_methods][woocommerce_gateways][sort_order]" value="30">
        </section>
        <?php
    }

    /**
     * Render one custom manual payment method.
     *
     * @param int                  $index  Method index.
     * @param array<string, mixed> $method Method settings.
     *
     * @return void
     */
    private function render_custom_payment_method(
        int $index,
        array $method
    ): void {

        $prefix =
            sprintf(
                'eilmo_cf_settings[payment_methods][custom_methods][%d]',
                $index
            );

        $title =
            (string) (
                $method['title'] ??
                    ''
            );

        ?>
        <div
            class="eilmo-cf-admin-repeater__item"
            data-eilmo-payment-method
        >

            <div class="eilmo-cf-admin-repeater__item-header">

                <strong data-eilmo-payment-method-title>
                    <?php
                    echo esc_html(
                        '' !== $title
                            ? $title
                            : __(
                                'Payment Method',
                                'eilmo-checkout-flow'
                            )
                    );
                    ?>
                </strong>

                <button
                    type="button"
                    class="button-link-delete"
                    data-eilmo-remove-repeater-item
                >
                    <?php
                    esc_html_e(
                        'Remove',
                        'eilmo-checkout-flow'
                    );
                    ?>
                </button>

            </div>

            <div class="eilmo-cf-admin-grid">

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Method Name',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[title]' ); ?>"
                        value="<?php echo esc_attr( $title ); ?>"
                        placeholder="<?php esc_attr_e( 'e.g. bKash', 'eilmo-checkout-flow' ); ?>"
                        data-eilmo-payment-method-name
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Method ID',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[id]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['id'] ??
                                    ''
                            )
                        ); ?>"
                        placeholder="bkash"
                    >

                    <p class="description">
                        <?php
                        esc_html_e(
                            'Leave empty to generate automatically from the method name.',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </p>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Account Label',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[account_label]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['account_label'] ??
                                    ''
                            )
                        ); ?>"
                        placeholder="<?php esc_attr_e( 'e.g. bKash Number', 'eilmo-checkout-flow' ); ?>"
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Account Number / ID',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[account_value]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['account_value'] ??
                                    ''
                            )
                        ); ?>"
                        placeholder="<?php esc_attr_e( 'Account number or payment ID', 'eilmo-checkout-flow' ); ?>"
                    >
                </div>

                <div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
                    <label>
                        <?php
                        esc_html_e(
                            'Description',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <textarea
                        name="<?php echo esc_attr( $prefix . '[description]' ); ?>"
                        rows="3"
                        placeholder="<?php esc_attr_e( 'Short description shown with the payment method.', 'eilmo-checkout-flow' ); ?>"
                    ><?php
                    echo esc_textarea(
                        (string) (
                            $method['description'] ??
                                ''
                        )
                    );
                    ?></textarea>
                </div>

                <div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
                    <label>
                        <?php
                        esc_html_e(
                            'Payment Instructions',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <textarea
                        name="<?php echo esc_attr( $prefix . '[instructions]' ); ?>"
                        rows="4"
                        placeholder="<?php esc_attr_e( 'Tell the customer how to complete the payment.', 'eilmo-checkout-flow' ); ?>"
                    ><?php
                    echo esc_textarea(
                        (string) (
                            $method['instructions'] ??
                                ''
                        )
                    );
                    ?></textarea>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Transaction ID Required',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <?php
                    $this->render_switch(
                        $prefix .
                            '[transaction_id_required]',
                        'yes',
                        (string) (
                            $method['transaction_id_required'] ??
                                'no'
                        )
                    );
                    ?>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label><?php esc_html_e( 'Accept Payment Screenshot', 'eilmo-checkout-flow' ); ?></label>
                    <?php $this->render_switch(
                        $prefix . '[payment_proof_enabled]',
                        'yes',
                        (string) ( $method['payment_proof_enabled'] ?? 'no' )
                    ); ?>
                    <p class="description"><?php esc_html_e( 'When both proof options are enabled, either one is sufficient.', 'eilmo-checkout-flow' ); ?></p>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label><?php esc_html_e( 'Screenshot Label', 'eilmo-checkout-flow' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $prefix . '[payment_proof_label]' ); ?>" value="<?php echo esc_attr( (string) ( $method['payment_proof_label'] ?? __( 'Payment Screenshot', 'eilmo-checkout-flow' ) ) ); ?>">
                </div>

                <div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide">
                    <label><?php esc_html_e( 'Screenshot Help Text', 'eilmo-checkout-flow' ); ?></label>
                    <textarea rows="2" name="<?php echo esc_attr( $prefix . '[payment_proof_help]' ); ?>"><?php echo esc_textarea( (string) ( $method['payment_proof_help'] ?? '' ) ); ?></textarea>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label><?php esc_html_e( 'Maximum Screenshot Size (MB)', 'eilmo-checkout-flow' ); ?></label>
                    <input type="number" min="1" max="10" name="<?php echo esc_attr( $prefix . '[payment_proof_max_mb]' ); ?>" value="<?php echo esc_attr( (string) ( $method['payment_proof_max_mb'] ?? 5 ) ); ?>">
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Transaction ID Label',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[transaction_id_label]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['transaction_id_label'] ??
                                    __(
                                        'Transaction ID',
                                        'eilmo-checkout-flow'
                                    )
                            )
                        ); ?>"
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Transaction ID Placeholder',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="text"
                        name="<?php echo esc_attr( $prefix . '[transaction_id_placeholder]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['transaction_id_placeholder'] ??
                                    __(
                                        'Enter transaction ID',
                                        'eilmo-checkout-flow'
                                    )
                            )
                        ); ?>"
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Icon URL',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="url"
                        name="<?php echo esc_attr( $prefix . '[icon_url]' ); ?>"
                        value="<?php echo esc_url(
                            (string) (
                                $method['icon_url'] ??
                                    ''
                            )
                        ); ?>"
                        placeholder="https://example.com/icon.png"
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Sort Order',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="number"
                        name="<?php echo esc_attr( $prefix . '[sort_order]' ); ?>"
                        value="<?php echo esc_attr(
                            (string) (
                                $method['sort_order'] ??
                                    40
                            )
                        ); ?>"
                        min="0"
                        step="1"
                    >
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Enabled',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <?php
                    $this->render_switch(
                        $prefix . '[enabled]',
                        'yes',
                        (string) (
                            $method['enabled'] ??
                                'yes'
                        )
                    );
                    ?>
                </div>

            </div>

        </div>
        <?php
    }

    /**
     * Render customer-information settings.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return void
     */
    private function render_customer_information(
        array $settings
    ): void {

        $fields =
            isset(
                $settings['fields']
            ) &&
            is_array(
                $settings['fields']
            )
                ? $settings['fields']
                : array();

        ?>
        <section class="eilmo-cf-settings-section">

            <?php
            $this->render_section_header(
                __(
                    'Customer Information',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Configure the customer information section and control every checkout field individually.',
                    'eilmo-checkout-flow'
                )
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Enable Customer Information',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[customer_information][enabled]',
                                'yes',
                                (string) (
                                    $settings['enabled'] ??
                                        'yes'
                                )
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="eilmo-cf-customer-title">
                                <?php
                                esc_html_e(
                                    'Section Title',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                id="eilmo-cf-customer-title"
                                class="regular-text"
                                name="eilmo_cf_settings[customer_information][title]"
                                value="<?php echo esc_attr(
                                    (string) (
                                        $settings['title'] ??
                                            ''
                                    )
                                ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="eilmo-cf-customer-description">
                                <?php
                                esc_html_e(
                                    'Section Description',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </label>
                        </th>

                        <td>
                            <textarea
                                id="eilmo-cf-customer-description"
                                class="large-text"
                                name="eilmo_cf_settings[customer_information][description]"
                                rows="3"
                            ><?php
                            echo esc_textarea(
                                (string) (
                                    $settings['description'] ??
                                        ''
                                )
                            );
                            ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Show Description',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[customer_information][show_description]',
                                'yes',
                                (string) (
                                    $settings['show_description'] ??
                                        'no'
                                )
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Autofill Logged-in Customer',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[customer_information][autofill_logged_in]',
                                'yes',
                                (string) (
                                    $settings['autofill_logged_in'] ??
                                        'yes'
                                )
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Automatically fill available WooCommerce billing information for logged-in customers.',
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
                                'Show Required Mark',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[customer_information][show_required_mark]',
                                'yes',
                                (string) (
                                    $settings['show_required_mark'] ??
                                        'yes'
                                )
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Show an asterisk beside required customer fields.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="eilmo-cf-customer-layout">
                                <?php
                                esc_html_e(
                                    'Field Layout',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="eilmo-cf-customer-layout"
                                name="eilmo_cf_settings[customer_information][layout]"
                            >
                                <?php
                                $layout =
                                    (string) (
                                        $settings['layout'] ??
                                            'two_columns'
                                    );

                                $this->render_option(
                                    'two_columns',
                                    __(
                                        'Two Columns',
                                        'eilmo-checkout-flow'
                                    ),
                                    $layout
                                );

                                $this->render_option(
                                    'one_column',
                                    __(
                                        'One Column',
                                        'eilmo-checkout-flow'
                                    ),
                                    $layout
                                );
                                ?>
                            </select>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Individual fields can still be set to Full Width or Half Width.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <hr>

            <div class="eilmo-cf-admin-repeater">

                <div class="eilmo-cf-admin-repeater__heading">

                    <div>
                        <h2>
                            <?php
                            esc_html_e(
                                'Customer Fields',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </h2>

                        <p>
                            <?php
                            esc_html_e(
                                'Control visibility, requirement, text, layout width and order for every customer field.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                </div>

                <div class="eilmo-cf-admin-repeater__items">

                    <?php foreach ( $fields as $field_key => $field ) : ?>

                        <?php
                        if ( ! is_array( $field ) ) {
                            continue;
                        }

                        $this->render_customer_field_settings(
                            (string) $field_key,
                            $field
                        );
                        ?>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>
        <?php
    }

    /**
     * Render settings for one customer field.
     *
     * @param string               $field_key Field key.
     * @param array<string, mixed> $field     Field settings.
     *
     * @return void
     */
    private function render_customer_field_settings(
        string $field_key,
        array $field
    ): void {

        $prefix =
            sprintf(
                'eilmo_cf_settings[customer_information][fields][%s]',
                $field_key
            );

        $label =
            (string) (
                $field['label'] ??
                    $field_key
            );

        ?>
        <div class="eilmo-cf-admin-repeater__item">

            <div class="eilmo-cf-admin-repeater__item-header">

                <strong>
                    <?php echo esc_html( $label ); ?>
                </strong>

                <code>
                    <?php echo esc_html( $field_key ); ?>
                </code>

            </div>

            <div class="eilmo-cf-admin-grid">

                <?php
                $this->render_text_field(
                    $prefix . '[label]',
                    __(
                        'Label',
                        'eilmo-checkout-flow'
                    ),
                    $label
                );

                $this->render_text_field(
                    $prefix . '[placeholder]',
                    __(
                        'Placeholder',
                        'eilmo-checkout-flow'
                    ),
                    (string) (
                        $field['placeholder'] ??
                            ''
                    )
                );
                ?>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Field Width',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <select
                        name="<?php echo esc_attr(
                            $prefix .
                                '[width]'
                        ); ?>"
                    >
                        <?php
                        $width =
                            (string) (
                                $field['width'] ??
                                    'full'
                            );

                        $this->render_option(
                            'full',
                            __(
                                'Full Width',
                                'eilmo-checkout-flow'
                            ),
                            $width
                        );

                        $this->render_option(
                            'half',
                            __(
                                'Half Width',
                                'eilmo-checkout-flow'
                            ),
                            $width
                        );
                        ?>
                    </select>
                </div>

                <?php
                $this->render_number_field(
                    $prefix . '[sort_order]',
                    __(
                        'Sort Order',
                        'eilmo-checkout-flow'
                    ),
                    $field['sort_order'] ??
                        10,
                    '1'
                );
                ?>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Enabled',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <?php
                    $this->render_switch(
                        $prefix . '[enabled]',
                        'yes',
                        (string) (
                            $field['enabled'] ??
                                'no'
                        )
                    );
                    ?>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Required',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <?php
                    $this->render_switch(
                        $prefix . '[required]',
                        'yes',
                        (string) (
                            $field['required'] ??
                                'no'
                        )
                    );
                    ?>
                </div>

            </div>

        </div>
		<?php
    }

	/**
	 * Render advance rule.
	 *
	 * @param int                  $index Index.
	 * @param array<string, mixed> $rule  Rule.
	 *
	 * @return void
	 */
	private function render_advance_rule(
		int $index,
		array $rule
	): void {

		$prefix = sprintf(
			'eilmo_cf_settings[advance_payment][rules][%d]',
			$index
		);

		?>
		<div class="eilmo-cf-admin-repeater__item">

			<div class="eilmo-cf-admin-repeater__item-header">
				<strong>
					<?php
					echo esc_html(
						! empty( $rule['name'] )
							? (string) $rule['name']
							: __( 'Advance Rule', 'eilmo-checkout-flow' )
					);
					?>
				</strong>

				<button
					type="button"
					class="button-link-delete"
					data-eilmo-remove-repeater-item
				>
					<?php esc_html_e( 'Remove', 'eilmo-checkout-flow' ); ?>
				</button>
			</div>

			<div class="eilmo-cf-admin-grid">

				<?php
				$this->render_text_field(
					$prefix . '[name]',
					__( 'Rule Name', 'eilmo-checkout-flow' ),
					(string) ( $rule['name'] ?? '' )
				);

				$this->render_number_field(
					$prefix . '[minimum_amount]',
					__( 'Minimum Amount', 'eilmo-checkout-flow' ),
					$rule['minimum_amount'] ?? $rule['minimum'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[maximum_amount]',
					__( 'Maximum Amount', 'eilmo-checkout-flow' ),
					$rule['maximum_amount'] ?? $rule['maximum'] ?? 0
				);
				?>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Advance Type', 'eilmo-checkout-flow' ); ?>
					</label>

					<select name="<?php echo esc_attr( $prefix . '[type]' ); ?>">
						<?php
						$current_type = (string) ( $rule['type'] ?? 'fixed' );

						$this->render_option( 'fixed', __( 'Fixed Amount', 'eilmo-checkout-flow' ), $current_type );
						$this->render_option( 'percentage', __( 'Percentage', 'eilmo-checkout-flow' ), $current_type );
						$this->render_option( 'full_payment', __( 'Full Payment', 'eilmo-checkout-flow' ), $current_type );
						$this->render_option( 'no_advance', __( 'No Advance', 'eilmo-checkout-flow' ), $current_type );
						?>
					</select>
				</div>

				<?php
				$this->render_number_field(
					$prefix . '[value]',
					__( 'Value', 'eilmo-checkout-flow' ),
					$rule['value'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[minimum_pay_amount]',
					__( 'Minimum Pay', 'eilmo-checkout-flow' ),
					$rule['minimum_pay_amount'] ?? $rule['minimum_pay'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[maximum_pay_amount]',
					__( 'Maximum Pay', 'eilmo-checkout-flow' ),
					$rule['maximum_pay_amount'] ?? $rule['maximum_pay'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[priority]',
					__( 'Priority', 'eilmo-checkout-flow' ),
					$rule['priority'] ?? 10,
					'1'
				);
				?>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Enabled', 'eilmo-checkout-flow' ); ?>
					</label>

					<?php
					$this->render_switch(
						$prefix . '[enabled]',
						'yes',
						(string) ( $rule['enabled'] ?? 'yes' )
					);
					?>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Stop Processing', 'eilmo-checkout-flow' ); ?>
					</label>

					<?php
					$this->render_switch(
						$prefix . '[stop_processing]',
						'yes',
						(string) ( $rule['stop_processing'] ?? 'yes' )
					);
					?>
				</div>

				<input
					type="hidden"
					name="<?php echo esc_attr( $prefix . '[id]' ); ?>"
					value="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>"
				>

			</div>

		</div>
		<?php
	}

	/**
	 * Render section heading.
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 *
	 * @return void
	 */
	private function render_section_header(
		string $title,
		string $description
	): void {
		?>
		<div class="eilmo-cf-settings-section__header">
			<h2><?php echo esc_html( $title ); ?></h2>

			<p>
				<?php echo esc_html( $description ); ?>
			</p>
		</div>
		<?php
	}

	/** Render one global theme colour control. */
	private function render_style_color_control(
		string $name,
		string $value,
		string $label,
		string $key
	): void {
		?>
		<label class="eilmo-cf-admin-color-control">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="eilmo-cf-admin-color-control__input">
				<input type="color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" data-eilmo-style-color="<?php echo esc_attr( $key ); ?>">
				<code><?php echo esc_html( $value ); ?></code>
			</span>
		</label>
		<?php
	}

	/**
	 * Render yes/no switch.
	 *
	 * Hidden input ensures "no" is submitted when unchecked.
	 *
	 * @param string $name    Input name.
	 * @param string $value   Checked value.
	 * @param string $current Current value.
	 *
	 * @return void
	 */
	private function render_switch(
		string $name,
		string $value,
		string $current
	): void {
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr( $name ); ?>"
			value="no"
		>

		<label class="eilmo-cf-admin-switch">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( $current, $value ); ?>
			>

			<span class="eilmo-cf-admin-switch__slider"></span>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 *
	 * @return void
	 */
	private function render_text_field(
		string $name,
		string $label,
		string $value
	): void {
		?>
		<div class="eilmo-cf-admin-field">
			<label>
				<?php echo esc_html( $label ); ?>
			</label>

			<input
				type="text"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
			>
		</div>
		<?php
	}

	/**
	 * Render numeric field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param string $step  Step.
	 *
	 * @return void
	 */
	private function render_number_field(
		string $name,
		string $label,
		$value,
		string $step = '0.01'
	): void {
		?>
		<div class="eilmo-cf-admin-field">
			<label>
				<?php echo esc_html( $label ); ?>
			</label>

			<input
				type="number"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				min="0"
				step="<?php echo esc_attr( $step ); ?>"
			>
		</div>
		<?php
	}

	/**
	 * Checkout placement slots available to movable modules.
	 *
	 * @return array<string, string>
	 */
	private function get_checkout_position_options(): array {
		return array(
			'before_products' => __( 'Before Products', 'eilmo-checkout-flow' ),
			'after_products' => __( 'After Products', 'eilmo-checkout-flow' ),
			'before_delivery' => __( 'Before Delivery', 'eilmo-checkout-flow' ),
			'after_delivery' => __( 'After Delivery', 'eilmo-checkout-flow' ),
			'before_customer' => __( 'Before Customer Information', 'eilmo-checkout-flow' ),
			'after_customer' => __( 'After Customer Information', 'eilmo-checkout-flow' ),
			'before_payment' => __( 'Before Payment', 'eilmo-checkout-flow' ),
			'after_payment' => __( 'After Payment', 'eilmo-checkout-flow' ),
			'before_order' => __( 'Before Order Button', 'eilmo-checkout-flow' ),
		);
	}

	/**
	 * Render select option.
	 *
	 * @param string $value   Option value.
	 * @param string $label   Option label.
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
			value="<?php echo esc_attr( $value ); ?>"
			<?php selected( $current, $value ); ?>
		>
			<?php echo esc_html( $label ); ?>
		</option>
		<?php
	}

    /**
     * Render direct Order Table Management settings.
     *
     * @param array<string,mixed> $settings Settings.
     * @return void
     */
    private function render_order_management( array $settings ): void {
        $defaults = CheckoutSettings::get_defaults()['order_management'];
        $settings = array_replace_recursive( is_array( $defaults ) ? $defaults : array(), $settings );
        $prefix   = CheckoutSettings::OPTION_NAME . '[order_management]';
        $default_columns = array(
            'order'    => __( 'Order', 'eilmo-checkout-flow' ),
            'date'     => __( 'Date', 'eilmo-checkout-flow' ),
            'status'   => __( 'Status', 'eilmo-checkout-flow' ),
            'billing'  => __( 'Billing', 'eilmo-checkout-flow' ),
            'shipping' => __( 'Ship to', 'eilmo-checkout-flow' ),
            'courier'  => __( 'Courier Performance', 'eilmo-checkout-flow' ),
            'meta'     => __( 'Meta', 'eilmo-checkout-flow' ),
            'security' => __( 'Security', 'eilmo-checkout-flow' ),
            'total'    => __( 'Total', 'eilmo-checkout-flow' ),
            'origin'   => __( 'Origin', 'eilmo-checkout-flow' ),
            'actions'  => __( 'Actions', 'eilmo-checkout-flow' ),
        );
        $columns  = array(
            'order'    => __( 'Order', 'eilmo-checkout-flow' ),
            'customer' => __( 'Customer / Address', 'eilmo-checkout-flow' ),
            'products' => __( 'Products & Variations', 'eilmo-checkout-flow' ),
            'date'     => __( 'Date', 'eilmo-checkout-flow' ),
            'status'   => __( 'Order Status', 'eilmo-checkout-flow' ),
            'security' => __( 'Security / Block Customer', 'eilmo-checkout-flow' ),
            'payment'  => __( 'Payment Details', 'eilmo-checkout-flow' ),
            'proof'      => __( 'Payment Proof + Transaction ID', 'eilmo-checkout-flow' ),
            'courier'    => __( 'Courier Performance', 'eilmo-checkout-flow' ),
            'meta'       => __( 'Meta', 'eilmo-checkout-flow' ),
            'order_note' => __( 'Customer Order Note', 'eilmo-checkout-flow' ),
            'total'    => __( 'Total', 'eilmo-checkout-flow' ),
            'origin'   => __( 'Origin', 'eilmo-checkout-flow' ),
            'actions'  => __( 'Actions', 'eilmo-checkout-flow' ),
        );
        ?>
        <section class="eilmo-cf-settings-section">
            <?php $this->render_section_header( __( 'Default Order Table Customize', 'eilmo-checkout-flow' ), __( 'Choose which columns appear in the regular WooCommerce Orders table when Order Table Management is off. These choices apply to all admins.', 'eilmo-checkout-flow' ) ); ?>
            <div class="eilmo-cf-qom-check-grid"><?php foreach ( $default_columns as $key => $label ) : ?><label class="eilmo-cf-qom-check"><input type="hidden" name="<?php echo esc_attr( $prefix . '[default_columns][' . $key . ']' ); ?>" value="no"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[default_columns][' . $key . ']' ); ?>" value="yes" <?php checked( 'yes', (string) ( $settings['default_columns'][ $key ] ?? 'yes' ) ); ?>><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></div>
        </section>
        <section class="eilmo-cf-settings-section">
            <?php $this->render_section_header( __( 'Order Table Management', 'eilmo-checkout-flow' ), __( 'Show the information needed to verify and manage an order directly in WooCommerce → Orders. No Quick View or order-editor visit is required for routine checks.', 'eilmo-checkout-flow' ) ); ?>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e( 'Enable Order Table Management', 'eilmo-checkout-flow' ); ?></th><td><label><input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="no"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="yes" <?php checked( 'yes', (string) ( $settings['enabled'] ?? 'no' ) ); ?>> <?php esc_html_e( 'Add direct verification columns to the WooCommerce Orders table.', 'eilmo-checkout-flow' ); ?></label></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'Inline Status Update', 'eilmo-checkout-flow' ); ?></th><td><label><input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[allow_status_update]" value="no"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[allow_status_update]" value="yes" <?php checked( 'yes', (string) ( $settings['allow_status_update'] ?? 'yes' ) ); ?>> <?php esc_html_e( 'Allow staff to change WooCommerce order status directly from the Status column without reloading the Orders page.', 'eilmo-checkout-flow' ); ?></label></td></tr>
            </tbody></table>
            <h3><?php esc_html_e( 'Show / Hide Order Details', 'eilmo-checkout-flow' ); ?></h3>
            <p><?php esc_html_e( 'All details can be switched off individually. New installations show every detail except Meta by default.', 'eilmo-checkout-flow' ); ?></p>
            <div class="eilmo-cf-qom-check-grid"><?php foreach ( $columns as $key => $label ) : ?><label class="eilmo-cf-qom-check"><input type="hidden" name="<?php echo esc_attr( $prefix . '[columns][' . $key . ']' ); ?>" value="no"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[columns][' . $key . ']' ); ?>" value="yes" <?php checked( 'yes', (string) ( $settings['columns'][ $key ] ?? $defaults['columns'][ $key ] ?? 'yes' ) ); ?>><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></div>
            <div class="notice notice-info inline"><p><?php esc_html_e( 'Customer / Address shows only the customer name, phone and delivery/billing address. Email, customer number and guest labels are intentionally hidden. Payment Proof includes the Transaction ID and opens screenshots in a secure popup. Products include quantity and variation data. Payment keeps method, payment state, paid-now amount and remaining due. Only customer-entered order notes are shown; system timeline notes stay in the order editor.', 'eilmo-checkout-flow' ); ?></p></div>
        </section>
        <style>.eilmo-cf-qom-check-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:12px 0 24px;max-width:1100px}.eilmo-cf-qom-check{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.eilmo-cf-qom-check input[type=checkbox]{margin:0}</style>
        <?php
    }

	/**
	 * Get current tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {

		$tabs = self::get_tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$tab = isset( $_GET['tab'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: 'general';

		if (
			! array_key_exists(
				$tab,
				$tabs
			)
		) {
			return 'general';
		}

		return $tab;
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults = CheckoutSettings::get_defaults();

		$stored = get_option(
			CheckoutSettings::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/*
		 * Keep the admin Style tab aligned with the same normalized global theme
		 * used on the frontend. This prevents stale legacy green values from being
		 * displayed when the saved preset is the canonical Default Theme.
		 */
		if ( isset( $stored['checkout_style'] ) && is_array( $stored['checkout_style'] ) ) {
			$stored['checkout_style'] = CheckoutStyle::normalize_global_style( $stored['checkout_style'] );
		}

		/*
		 * Rules Engine has been removed. Ignore any legacy
		 * Rules Engine data that may still exist in the option
		 * until the settings are saved again.
		 */
		unset(
			$stored['rules_engine']
		);

		/*
		 * Security settings now live in the dedicated
		 * SecuritySettings option. Ignore duplicate
		 * legacy General keys until Settings.php removes
		 * them from the main option on the next save.
		 */
		if (
			isset(
				$stored[
					'general'
				]
			) &&
			is_array(
				$stored[
					'general'
				]
			)
		) {
			unset(
				$stored['general']['checkout_security'],
				$stored['general']['customer_blacklist'],
				$stored['general']['checkout_access'],
				$stored['general']['custom_login_url_enabled'],
				$stored['general']['custom_login_url']
			);
		}

		return $this->merge_settings(
			$defaults,
			$stored
		);
	}

	/**
	 * Recursively merge associative settings.
	 *
	 * Indexed collections replace defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored.
	 *
	 * @return array<string, mixed>
	 */
	private function merge_settings(
		array $defaults,
		array $stored
	): array {

		foreach ( $stored as $key => $value ) {

			if (
				isset( $defaults[ $key ] ) &&
				is_array( $defaults[ $key ] ) &&
				is_array( $value ) &&
				$this->is_associative(
					$defaults[ $key ]
				) &&
				$this->is_associative(
					$value
				)
			) {
				$defaults[ $key ] =
					$this->merge_settings(
						$defaults[ $key ],
						$value
					);

				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Determine whether array is associative.
	 *
	 * @param array<mixed> $array Array.
	 *
	 * @return bool
	 */
	private function is_associative(
		array $array
	): bool {

		if ( empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !== range(
			0,
			count( $array ) - 1
		);
	}

    /**
     * Format monetary value for admin display.
     *
     * @param mixed $amount Amount.
     *
     * @return string
     */
    private function format_admin_price(
        $amount
    ): string {

        if (
            function_exists( 'wc_price' )
        ) {
            return wp_strip_all_tags(
                wc_price(
                    (float) $amount
                )
            );
        }

        $amount = is_numeric( $amount )
            ? (float) $amount
            : 0.0;

        return number_format_i18n(
            $amount,
            2
        );
    }
}
