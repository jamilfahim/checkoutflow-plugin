<?php
/**
 * Default WooCommerce checkout settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\DefaultCheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders native checkout integration settings.
 */
final class DefaultCheckoutSettingsPage {

	/** Page slug. */
	public const PAGE_SLUG = 'eilmo-checkout-default';

	/** Render page. */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to access this page.',
					'eilmo-checkout-flow'
				)
			);
		}

		$settings = DefaultCheckoutSettings::get();
		?>
		<div class="wrap eilmo-cf-admin">
			<div class="eilmo-cf-admin__header">
				<div class="eilmo-cf-admin__heading">
					<h1><?php esc_html_e( 'Default Checkout Settings', 'eilmo-checkout-flow' ); ?></h1>
					<p><?php esc_html_e( 'Customize WooCommerce Classic Checkout and Checkout Block. Delivery always uses the billing address, so separate shipping-address fields are intentionally removed.', 'eilmo-checkout-flow' ); ?></p>
				</div>
			</div>

			<?php settings_errors(); ?>

			<form method="post" action="options.php" class="eilmo-cf-settings-form">
				<?php settings_fields( DefaultCheckoutSettings::OPTION_GROUP ); ?>
				<?php $this->render_settings( $settings ); ?>
				<?php submit_button( __( 'Save Default Checkout Settings', 'eilmo-checkout-flow' ) ); ?>
			</form>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var fraud = document.getElementById('eilmo-cf-native-live-fraud');
			var payment = document.getElementById('eilmo-cf-native-payment-options');
			if (fraud && payment) {
				fraud.addEventListener('change', function () {
					if (fraud.checked) { payment.checked = true; }
				});
			}
			document.querySelectorAll('[data-eilmo-native-style-color]').forEach(function (input) {
				input.addEventListener('input', function () {
					var code = input.parentElement ? input.parentElement.querySelector('code') : null;
					if (code) { code.textContent = input.value; }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render native checkout display and field controls.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function render_settings( array $settings ): void {
		$advanced = isset( $settings['advanced'] ) && is_array( $settings['advanced'] )
			? $settings['advanced']
			: array();
		$display = isset( $settings['display'] ) && is_array( $settings['display'] )
			? $settings['display']
			: array();
		$purchase_flow = isset( $settings['purchase_flow'] ) && is_array( $settings['purchase_flow'] )
			? $settings['purchase_flow']
			: array();
		$style = isset( $settings['style'] ) && is_array( $settings['style'] )
			? $settings['style']
			: array();
		$fields = isset( $settings['fields'] ) && is_array( $settings['fields'] )
			? $settings['fields']
			: array();
		?>
		<section class="eilmo-cf-settings-section">
			<?php
			$this->render_section_header(
				__( 'Native Checkout Features', 'eilmo-checkout-flow' ),
				__( 'Enable only the Checkout Flow systems you want to reuse on WooCommerce native checkout.', 'eilmo-checkout-flow' )
			);
			?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Custom Payment / Payment Options', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php $this->render_switch( DefaultCheckoutSettings::OPTION_NAME . '[advanced][payment_options_enabled]', (string) ( $advanced['payment_options_enabled'] ?? 'no' ), 'eilmo-cf-native-payment-options' ); ?>
							<p class="description"><?php esc_html_e( 'Shows the plugin’s existing Cash on Delivery, Advance Payment and Full Payment selector on Classic Checkout, Checkout Block frontend and compatible custom checkout templates. Existing advance rules, full-payment discount and configured Eilmo manual methods are reused.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Live Fraud Check', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php $this->render_switch( DefaultCheckoutSettings::OPTION_NAME . '[advanced][live_fraud_enabled]', (string) ( $advanced['live_fraud_enabled'] ?? 'no' ), 'eilmo-cf-native-live-fraud' ); ?>
							<p class="description"><?php esc_html_e( 'Default: OFF. When enabled, billing phone becomes required and the existing signed Live Fraud decision controls which Cash / Advance / Full options are allowed. Enabling Live Fraud automatically enables Payment Options.', 'eilmo-checkout-flow' ); ?></p>
							<p class="description"><strong><?php esc_html_e( 'Checkout Block:', 'eilmo-checkout-flow' ); ?></strong> <?php esc_html_e( 'the page can stay a Checkout Block in the editor; the frontend automatically uses WooCommerce’s classic checkout processor so Eilmo’s advanced fields remain secure and functional.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delivery System', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php $this->render_switch( DefaultCheckoutSettings::OPTION_NAME . '[advanced][delivery_enabled]', (string) ( $advanced['delivery_enabled'] ?? 'no' ), 'eilmo-cf-native-delivery' ); ?>
							<p class="description"><?php esc_html_e( 'Reuse Checkout Settings → Delivery Methods on Classic Checkout and Checkout Block. Configure automatic Free Delivery only from Offers & Discounts → Special Discounts.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="eilmo-cf-settings-section" data-eilmo-native-checkout-style>
			<?php
			$this->render_section_header(
				__( 'Checkout Visual Style', 'eilmo-checkout-flow' ),
				__( 'Default Checkout uses the same visual sizing, grid, spacing, fields and summary rhythm as campaign checkout, but owns a separate theme. The default is Eilmo purple and customers can change every colour here.', 'eilmo-checkout-flow' )
			);
			?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Use Plugin Checkout Styling', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php $this->render_switch( DefaultCheckoutSettings::OPTION_NAME . '[style][enabled]', (string) ( $style['enabled'] ?? 'yes' ), 'eilmo-cf-native-style-enabled' ); ?>
							<p class="description"><strong><?php esc_html_e( 'Default: ON.', 'eilmo-checkout-flow' ); ?></strong> <?php esc_html_e( 'ON loads the isolated Eilmo Default Checkout UI adapter with the purple palette below. OFF unloads the complete visual layer while keeping only the functional WooCommerce adapter. Campaign CSS is never reused. The stylesheet contains no !important rules and loads before normal theme styles, so theme/child-theme/custom CSS can remain the final visual authority.', 'eilmo-checkout-flow' ); ?></p>
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
										DefaultCheckoutSettings::OPTION_NAME . '[style][' . $key . ']',
										(string) ( $style[ $key ] ?? '#ffffff' ),
										$label,
										$key
									);
								}
								?>
							</div>
							<p class="description"><?php esc_html_e( 'Card Background controls checkout panels and choice cards. Page / Soft Background controls form fields and softer surfaces, so changing Card Background does not recolour the inputs.', 'eilmo-checkout-flow' ); ?></p>
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
									<input type="number" min="0" max="<?php echo esc_attr( (string) $control[1] ); ?>" name="<?php echo esc_attr( DefaultCheckoutSettings::OPTION_NAME . '[style][' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $style[ $key ] ?? 16 ) ); ?>">
									<span>px</span>
								</label>
								<?php
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="eilmo-cf-settings-section">
			<?php
			$this->render_section_header(
				__( 'Native Checkout Display', 'eilmo-checkout-flow' ),
				__( 'Leave a text field blank to keep the current WooCommerce or theme value.', 'eilmo-checkout-flow' )
			);
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="eilmo-cf-default-order-button-label">
								<?php esc_html_e( 'Place Order Button Label', 'eilmo-checkout-flow' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="eilmo-cf-default-order-button-label"
								class="regular-text"
								name="<?php echo esc_attr( DefaultCheckoutSettings::OPTION_NAME ); ?>[display][order_button_label]"
								value="<?php echo esc_attr( (string) ( $display['order_button_label'] ?? '' ) ); ?>"
								placeholder="<?php esc_attr_e( 'Keep WooCommerce default', 'eilmo-checkout-flow' ); ?>"
							>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide Add to Cart Button', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php
							$this->render_switch(
								DefaultCheckoutSettings::OPTION_NAME . '[purchase_flow][hide_add_to_cart]',
								(string) ( $purchase_flow['hide_add_to_cart'] ?? 'no' )
							);
							?>
							<p class="description"><?php esc_html_e( 'Hide the native Add to Cart button on single-product pages.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Direct Checkout', 'eilmo-checkout-flow' ); ?></th>
						<td>
							<?php
							$this->render_switch(
								DefaultCheckoutSettings::OPTION_NAME . '[purchase_flow][direct_checkout]',
								(string) ( $purchase_flow['direct_checkout'] ?? 'yes' )
							);
							?>
							<p class="description"><?php esc_html_e( 'Send Order Now directly to the native WooCommerce checkout after adding the selected product, variation and quantity.', 'eilmo-checkout-flow' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<?php foreach ( $this->get_field_groups() as $group ) : ?>
			<section class="eilmo-cf-settings-section">
				<?php
				$this->render_section_header(
					(string) $group['title'],
					(string) $group['description']
				);
				?>

				<table class="form-table" role="presentation">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'eilmo-checkout-flow' ); ?></th>
							<th><?php esc_html_e( 'Show', 'eilmo-checkout-flow' ); ?></th>
							<th><?php esc_html_e( 'Label / Placeholder', 'eilmo-checkout-flow' ); ?></th>
							<th><?php esc_html_e( 'Required', 'eilmo-checkout-flow' ); ?></th>
							<th><?php esc_html_e( 'Width', 'eilmo-checkout-flow' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'eilmo-checkout-flow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $group['fields'] as $field_key => $field_label ) : ?>
							<?php
							$field = isset( $fields[ $field_key ] ) && is_array( $fields[ $field_key ] )
								? $fields[ $field_key ]
								: array();
							$base = DefaultCheckoutSettings::OPTION_NAME . '[fields][' . $field_key . ']';
							?>
							<tr>
								<th scope="row">
									<?php echo esc_html( (string) $field_label ); ?>
									<br><code><?php echo esc_html( $field_key ); ?></code>
								</th>
								<td><?php $this->render_switch( $base . '[enabled]', (string) ( $field['enabled'] ?? 'yes' ) ); ?></td>
								<td>
									<input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Inherit label', 'eilmo-checkout-flow' ); ?>">
									<br>
									<input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Inherit placeholder', 'eilmo-checkout-flow' ); ?>">
								</td>
								<td>
									<select name="<?php echo esc_attr( $base . '[required]' ); ?>">
										<?php $this->render_option( 'inherit', __( 'Inherit', 'eilmo-checkout-flow' ), (string) ( $field['required'] ?? 'inherit' ) ); ?>
										<?php $this->render_option( 'yes', __( 'Required', 'eilmo-checkout-flow' ), (string) ( $field['required'] ?? 'inherit' ) ); ?>
										<?php $this->render_option( 'no', __( 'Optional', 'eilmo-checkout-flow' ), (string) ( $field['required'] ?? 'inherit' ) ); ?>
									</select>
								</td>
								<td>
									<select name="<?php echo esc_attr( $base . '[width]' ); ?>">
										<?php $this->render_option( 'inherit', __( 'Inherit', 'eilmo-checkout-flow' ), (string) ( $field['width'] ?? 'inherit' ) ); ?>
										<?php $this->render_option( 'full', __( 'Full', 'eilmo-checkout-flow' ), (string) ( $field['width'] ?? 'inherit' ) ); ?>
										<?php $this->render_option( 'half', __( 'Half', 'eilmo-checkout-flow' ), (string) ( $field['width'] ?? 'inherit' ) ); ?>
									</select>
								</td>
								<td>
									<input type="number" min="0" max="999" step="1" class="small-text" name="<?php echo esc_attr( $base . '[priority]' ); ?>" value="<?php echo esc_attr( (string) absint( $field['priority'] ?? 0 ) ); ?>">
									<p class="description"><?php esc_html_e( '0 = inherit', 'eilmo-checkout-flow' ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Get field groups.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_field_groups(): array {
		return array(
			array(
				'title'       => __( 'Billing Fields', 'eilmo-checkout-flow' ),
				'description' => __( 'Modify the standard billing/contact fields. Field keys and submitted names never change.', 'eilmo-checkout-flow' ),
				'fields'      => array(
					'billing_first_name' => __( 'First Name', 'eilmo-checkout-flow' ),
					'billing_last_name'  => __( 'Last Name', 'eilmo-checkout-flow' ),
					'billing_company'    => __( 'Company', 'eilmo-checkout-flow' ),
					'billing_country'    => __( 'Country', 'eilmo-checkout-flow' ),
					'billing_address_1'  => __( 'Address Line 1', 'eilmo-checkout-flow' ),
					'billing_address_2'  => __( 'Address Line 2', 'eilmo-checkout-flow' ),
					'billing_city'       => __( 'City', 'eilmo-checkout-flow' ),
					'billing_state'      => __( 'State / District', 'eilmo-checkout-flow' ),
					'billing_postcode'   => __( 'Postcode', 'eilmo-checkout-flow' ),
					'billing_phone'      => __( 'Phone', 'eilmo-checkout-flow' ),
					'billing_email'      => __( 'Email', 'eilmo-checkout-flow' ),
				),
			),
			array(
				'title'       => __( 'Additional Information', 'eilmo-checkout-flow' ),
				'description' => __( 'Control the native order notes field.', 'eilmo-checkout-flow' ),
				'fields'      => array(
					'order_comments' => __( 'Order Notes', 'eilmo-checkout-flow' ),
				),
			),
		);
	}

	/** Render shared section header. */
	private function render_section_header( string $title, string $description ): void {
		?>
		<div class="eilmo-cf-settings-section__header">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	/** Render one colour control for the independent Default Checkout theme. */
	private function render_style_color_control( string $name, string $value, string $label, string $key ): void {
		?>
		<label class="eilmo-cf-admin-color-control">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="eilmo-cf-admin-color-control__input">
				<input type="color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" data-eilmo-native-style-color="<?php echo esc_attr( $key ); ?>">
				<code><?php echo esc_html( $value ); ?></code>
			</span>
		</label>
		<?php
	}

	/** Render established admin switch markup. */
	private function render_switch( string $name, string $current, string $id = '' ): void {
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no">
		<label class="eilmo-cf-admin-switch">
			<input type="checkbox" <?php echo '' !== $id ? 'id="' . esc_attr( $id ) . '"' : ''; ?> name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( 'yes', $current ); ?>>
			<span class="eilmo-cf-admin-switch__slider" aria-hidden="true"></span>
		</label>
		<?php
	}

	/** Render select option. */
	private function render_option( string $value, string $label, string $current ): void {
		?>
		<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $current ); ?>>
			<?php echo esc_html( $label ); ?>
		</option>
		<?php
	}
}
