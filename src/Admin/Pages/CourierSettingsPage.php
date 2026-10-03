<?php
/**
 * Courier settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\CourierSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Courier settings.
 */
final class CourierSettingsPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-courier';

	/**
	 * Render Courier settings page.
	 *
	 * @param bool $embedded Whether to render inside the Integrations page.
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
			CourierSettings::get_settings();

		$features =
			$this->get_array(
				$settings,
				'features'
			);

		$ratio_api =
			$this->get_array(
				$settings,
				'ratio_api'
			);

		$live_fraud =
			$this->get_array(
				$settings,
				'live_fraud'
			);

		$steadfast =
			$this->get_array(
				$settings,
				'steadfast'
			);

		$pathao =
			$this->get_array(
				$settings,
				'pathao'
			);

		if ( ! $embedded ) {
			?>
			<div class="wrap eilmo-cf-admin">

				<div class="eilmo-cf-admin__header">

					<div class="eilmo-cf-admin__heading">

					<h1>
						<?php
						esc_html_e(
							'Courier Settings',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Configure customer courier success checking and one-click Steadfast and Pathao booking.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

					</div>

				</div>
			<?php
		}
		?>

		<form
				method="post"
				action="options.php"
				class="eilmo-cf-settings-form"
			>

				<?php
				settings_fields(
					CourierSettings::OPTION_GROUP
				);

				$this->render_features(
					$features
				);

				$this->render_success_api(
					$ratio_api
				);

				$this->render_live_fraud(
					$live_fraud
				);

				$this->render_steadfast(
					$steadfast
				);

				$this->render_pathao(
					$pathao
				);

				submit_button(
					__(
						'Save Courier Settings',
						'eilmo-checkout-flow'
					)
				);
				?>

		</form>

		<?php
		if ( ! $embedded ) {
			?>
			</div>
			<?php
		}
	}

	/**
	 * Render Courier feature settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_features(
		array $settings
	): void {
		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Courier Features',
					'eilmo-checkout-flow'
				),
				__(
					'Control Courier functionality shown in WooCommerce Orders. The main Courier switch is in Checkout Settings → General → Optional Features.',
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
								'Courier Success',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_courier_settings[features][courier_success]',
								'yes',
								(string) (
									$settings['courier_success'] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show customer delivery success count and percentage in WooCommerce Orders. Steadfast mode shows only Steadfast. BD Courier Full Courier Check shows one combined all-courier result with a hover breakdown.',
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
								'One Click Courier',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>
							<?php
							$this->render_switch(
								'eilmo_cf_courier_settings[features][courier_one_click]',
								'yes',
								(string) (
									$settings['courier_one_click'] ??
										'yes'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Show direct Send to Steadfast and Send to Pathao actions. Use the edit icon beside each courier only when order details need adjustment before sending.',
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
	 * Render customer Courier Success API settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_success_api(
		array $settings
	): void {

		$provider = sanitize_key( (string) ( $settings['provider'] ?? 'steadfast' ) );
		if ( ! in_array( $provider, array( 'steadfast', 'bdcourier' ), true ) ) {
			$provider = 'steadfast';
		}

		$has_api_key =
			'' !== trim(
				(string) (
					$settings['api_key'] ??
						''
				)
			);

		$cache_minutes =
			absint(
				$settings['cache_minutes'] ??
					360
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Courier Success Source',
					'eilmo-checkout-flow'
				),
				__(
					'Choose the external courier-history source. Steadfast is the default free source; BD Courier is optional for combined Steadfast + Pathao history.',
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
							<label for="eilmo-cf-courier-success-provider">
								<?php esc_html_e( 'Fraud / Success Source', 'eilmo-checkout-flow' ); ?>
							</label>
						</th>

						<td>
							<select
								id="eilmo-cf-courier-success-provider"
								name="eilmo_cf_courier_settings[ratio_api][provider]"
							>
								<option value="steadfast" <?php selected( $provider, 'steadfast' ); ?>><?php esc_html_e( 'Steadfast — Default / Free', 'eilmo-checkout-flow' ); ?></option>
								<option value="bdcourier" <?php selected( $provider, 'bdcourier' ); ?>><?php esc_html_e( 'BD Courier — Full Courier Check', 'eilmo-checkout-flow' ); ?></option>
							</select>

							<p class="description">
								<?php esc_html_e( 'Steadfast uses the API Key + Secret Key configured in the Steadfast section and shows only Steadfast history. BD Courier uses the token below, combines every courier history returned by BD Courier into one success result, and exposes the provider breakdown on hover. This source also powers Live Checkout Fraud when that feature is enabled.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-courier-success-api-key">
								<?php
								esc_html_e(
									'BD Courier API Token (Full Check)',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[ratio_api][api_key]',
								'eilmo-cf-courier-success-api-key',
								$has_api_key
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Required only when BD Courier — Full Courier Check is selected. Leave blank to keep the currently saved token.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'BD Courier Fallback', 'eilmo-checkout-flow' ); ?>
						</th>

						<td>
							<input type="hidden" name="eilmo_cf_courier_settings[ratio_api][fallback_to_steadfast]" value="no">
							<label>
								<input
									type="checkbox"
									name="eilmo_cf_courier_settings[ratio_api][fallback_to_steadfast]"
									value="yes"
									<?php checked( 'yes', (string) ( $settings['fallback_to_steadfast'] ?? 'yes' ) ); ?>
								>
								<?php esc_html_e( 'Automatically use Steadfast when BD Courier is unavailable', 'eilmo-checkout-flow' ); ?>
							</label>

							<p class="description">
								<?php esc_html_e( 'Default: ON. Applies only when BD Courier is the selected Fraud / Success Source. If the BD Courier token/quota is unavailable, the request times out, or the API returns an unusable error, Eilmo retries once with the configured Steadfast fraud-check API. If Steadfast also fails, the existing fail-open Live Fraud behavior remains unchanged.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Store Order History', 'eilmo-checkout-flow' ); ?>
						</th>

						<td>
							<input type="hidden" name="eilmo_cf_courier_settings[ratio_api][show_store_history]" value="no">
							<label>
								<input
									type="checkbox"
									name="eilmo_cf_courier_settings[ratio_api][show_store_history]"
									value="yes"
									<?php checked( 'yes', (string) ( $settings['show_store_history'] ?? 'yes' ) ); ?>
								>
								<?php esc_html_e( "Show this customer's order history from this website in courier history hover cards", 'eilmo-checkout-flow' ); ?>
							</label>

							<p class="description">
								<?php esc_html_e( 'Default: ON. Applies to both All Couriers and individual courier hover cards. Local store orders are always shown separately and are never added to external courier totals, so courier statistics are not double-counted.', 'eilmo-checkout-flow' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-courier-cache-minutes">
								<?php
								esc_html_e(
									'Cache Duration',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="number"
								id="eilmo-cf-courier-cache-minutes"
								name="eilmo_cf_courier_settings[ratio_api][cache_minutes]"
								value="<?php echo esc_attr( (string) $cache_minutes ); ?>"
								min="5"
								max="1440"
								step="5"
							>

							<span>
								<?php
								esc_html_e(
									'minutes',
									'eilmo-checkout-flow'
								);
								?>
							</span>

							<p class="description">
								<?php
								esc_html_e(
									'Legacy Courier Success cache. Advanced Live Fraud uses the separate day-based cache setting below.',
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
	 * Render advanced live checkout fraud settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_live_fraud( array $settings ): void {

		$actions = array(
			'allow' => __( 'Allow order', 'eilmo-checkout-flow' ),
			'advance' => __( 'Require advance payment', 'eilmo-checkout-flow' ),
			'full' => __( 'Require full payment', 'eilmo-checkout-flow' ),
			'block' => __( 'Block order', 'eilmo-checkout-flow' ),
		);
		?>
		<section class="eilmo-cf-settings-section">
			<?php
			$this->render_section_header(
				__( 'Advanced Live Fraud Check', 'eilmo-checkout-flow' ),
				__( 'Use confidence-aware, explainable risk scoring to check a valid phone once inside checkout, save the signed result with the order, and enforce the selected action. Disabled by default; while disabled the existing post-order Courier Success workflow remains unchanged.', 'eilmo-checkout-flow' )
			);
			?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><?php esc_html_e( 'Enable Live Check', 'eilmo-checkout-flow' ); ?></th><td>
					<?php $this->render_switch( 'eilmo_cf_courier_settings[live_fraud][enabled]', 'yes', (string) ( $settings['enabled'] ?? 'no' ) ); ?>
					<p class="description"><?php esc_html_e( 'Runs after a complete Bangladesh mobile number is entered. Local and cached data are reused before an external request is considered, using the Courier Success Source selected above.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<?php $strategy = sanitize_key( (string) ( $settings['data_strategy'] ?? 'local_first' ) ); ?>
				<tr><th scope="row"><label for="eilmo-cf-live-data-strategy"><?php esc_html_e( 'Data Priority', 'eilmo-checkout-flow' ); ?></label></th><td>
					<select id="eilmo-cf-live-data-strategy" name="eilmo_cf_courier_settings[live_fraud][data_strategy]">
						<option value="local_first" <?php selected( $strategy, 'local_first' ); ?>><?php esc_html_e( 'Website First — recommended', 'eilmo-checkout-flow' ); ?></option>
						<option value="courier_first" <?php selected( $strategy, 'courier_first' ); ?>><?php esc_html_e( 'Courier First', 'eilmo-checkout-flow' ); ?></option>
						<option value="combined" <?php selected( $strategy, 'combined' ); ?>><?php esc_html_e( 'Always Combine Website + Courier', 'eilmo-checkout-flow' ); ?></option>
						<option value="local_only" <?php selected( $strategy, 'local_only' ); ?>><?php esc_html_e( 'Website History Only', 'eilmo-checkout-flow' ); ?></option>
						<option value="courier_only" <?php selected( $strategy, 'courier_only' ); ?>><?php esc_html_e( 'Courier History Only', 'eilmo-checkout-flow' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Website First checks this store’s completed, cancelled, failed and refunded orders for free. Courier data is requested only when local history is insufficient.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="eilmo-cf-live-minimum-local-orders"><?php esc_html_e( 'Minimum Website Orders', 'eilmo-checkout-flow' ); ?></label></th><td>
					<input type="number" id="eilmo-cf-live-minimum-local-orders" name="eilmo_cf_courier_settings[live_fraud][minimum_local_orders]" value="<?php echo esc_attr( (string) ( $settings['minimum_local_orders'] ?? 3 ) ); ?>" min="1" max="100" step="1">
					<p class="description"><?php esc_html_e( 'Website First may decide without Courier API after this many resolved local orders.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="eilmo-cf-live-cache-days"><?php esc_html_e( 'Courier Cache Validity', 'eilmo-checkout-flow' ); ?></label></th><td>
					<input type="number" id="eilmo-cf-live-cache-days" name="eilmo_cf_courier_settings[live_fraud][cache_days]" value="<?php echo esc_attr( (string) ( $settings['cache_days'] ?? 30 ) ); ?>" min="1" max="365" step="1"> <?php esc_html_e( 'days', 'eilmo-checkout-flow' ); ?>
					<p class="description"><?php esc_html_e( 'Default 30 days. Expired data is refreshed during the next checkout, but remains available if API quota is exhausted.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Refresh Expired Cache', 'eilmo-checkout-flow' ); ?></th><td>
					<?php $this->render_switch( 'eilmo_cf_courier_settings[live_fraud][refresh_expired_cache]', 'yes', (string) ( $settings['refresh_expired_cache'] ?? 'yes' ) ); ?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Use Stale Data on API Failure', 'eilmo-checkout-flow' ); ?></th><td>
					<?php $this->render_switch( 'eilmo_cf_courier_settings[live_fraud][use_stale_on_failure]', 'yes', (string) ( $settings['use_stale_on_failure'] ?? 'yes' ) ); ?>
					<p class="description"><?php esc_html_e( 'Keeps checkout available when courier quota or service is unavailable.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="eilmo-cf-live-error-cooldown"><?php esc_html_e( 'API Error Cooldown', 'eilmo-checkout-flow' ); ?></label></th><td>
					<input type="number" id="eilmo-cf-live-error-cooldown" name="eilmo_cf_courier_settings[live_fraud][api_error_cooldown_minutes]" value="<?php echo esc_attr( (string) ( $settings['api_error_cooldown_minutes'] ?? 30 ) ); ?>" min="1" max="1440" step="1"> <?php esc_html_e( 'minutes', 'eilmo-checkout-flow' ); ?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Manual Check in Orders', 'eilmo-checkout-flow' ); ?></th><td>
					<?php $this->render_switch( 'eilmo_cf_courier_settings[live_fraud][manual_order_check]', 'yes', (string) ( $settings['manual_order_check'] ?? 'yes' ) ); ?>
					<p class="description"><?php esc_html_e( 'Adds Check/Refresh buttons. Opening the Orders page never spends courier API quota.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Show Cache Age', 'eilmo-checkout-flow' ); ?></th><td>
					<?php $this->render_switch( 'eilmo_cf_courier_settings[live_fraud][show_cache_age]', 'yes', (string) ( $settings['show_cache_age'] ?? 'yes' ) ); ?>
				</td></tr>
				<?php
				$number_fields = array(
					'trusted_rate' => array( __( 'Trusted Rate', 'eilmo-checkout-flow' ), 95, 0, 100, __( 'At or above this success rate, the order is allowed.', 'eilmo-checkout-flow' ) ),
					'advance_rate' => array( __( 'High-risk Rate', 'eilmo-checkout-flow' ), 90, 0, 100, __( 'Below this success rate, the High-risk Action applies.', 'eilmo-checkout-flow' ) ),
					'block_rate' => array( __( 'Critical Rate', 'eilmo-checkout-flow' ), 40, 0, 100, __( 'Below this rate, and after the minimum order count, the Critical Action applies.', 'eilmo-checkout-flow' ) ),
					'minimum_orders_for_block' => array( __( 'Minimum Orders for Critical Action', 'eilmo-checkout-flow' ), 5, 1, 100, __( 'Prevents one or two courier records from causing an automatic hard block.', 'eilmo-checkout-flow' ) ),
				);
				foreach ( $number_fields as $key => $field ) :
					$value = isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ? $settings[ $key ] : $field[1];
					?>
					<tr><th scope="row"><label for="eilmo-cf-live-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th><td>
						<input type="number" id="eilmo-cf-live-<?php echo esc_attr( $key ); ?>" name="eilmo_cf_courier_settings[live_fraud][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" min="<?php echo esc_attr( (string) $field[2] ); ?>" max="<?php echo esc_attr( (string) $field[3] ); ?>" step="<?php echo 'minimum_orders_for_block' === $key ? '1' : '0.1'; ?>">
						<?php if ( 'minimum_orders_for_block' !== $key ) : ?><span>%</span><?php endif; ?>
						<p class="description"><?php echo esc_html( $field[4] ); ?></p>
					</td></tr>
				<?php endforeach; ?>
				<?php
				$action_fields = array(
					'unknown_action' => array( __( 'No-history Action', 'eilmo-checkout-flow' ), 'allow' ),
					'middle_action' => array( __( 'Review-band Action', 'eilmo-checkout-flow' ), 'allow' ),
					'high_risk_action' => array( __( 'High-risk Action', 'eilmo-checkout-flow' ), 'advance' ),
					'critical_action' => array( __( 'Critical Action', 'eilmo-checkout-flow' ), 'block' ),
				);
				foreach ( $action_fields as $key => $field ) :
					$selected = sanitize_key( (string) ( $settings[ $key ] ?? $field[1] ) );
					?>
					<tr><th scope="row"><label for="eilmo-cf-live-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th><td>
						<select id="eilmo-cf-live-<?php echo esc_attr( $key ); ?>" name="eilmo_cf_courier_settings[live_fraud][<?php echo esc_attr( $key ); ?>]">
							<?php foreach ( $actions as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
						</select>
					</td></tr>
				<?php endforeach; ?>
				<?php
				$display_mode = sanitize_key( (string) ( $settings['payment_display_mode'] ?? 'always' ) );
				$payment_rules = isset( $settings['payment_rules'] ) && is_array( $settings['payment_rules'] )
					? $settings['payment_rules']
					: array();
				$payment_types = array(
					'cash_on_delivery' => __( 'Cash on Delivery', 'eilmo-checkout-flow' ),
					'advance' => __( 'Advance Payment', 'eilmo-checkout-flow' ),
					'full' => __( 'Full Payment', 'eilmo-checkout-flow' ),
				);
				$payment_bands = array(
					'trusted' => __( 'Trusted Customer Payments', 'eilmo-checkout-flow' ),
					'review' => __( 'Review-band Payments', 'eilmo-checkout-flow' ),
					'high' => __( 'High-risk Payments', 'eilmo-checkout-flow' ),
					'critical' => __( 'Critical-risk Payments', 'eilmo-checkout-flow' ),
					'unknown' => __( 'No-history Payments', 'eilmo-checkout-flow' ),
					'unavailable' => __( 'No Local Data + API Unavailable Payments', 'eilmo-checkout-flow' ),
				);
				?>
				<tr><th scope="row"><label for="eilmo-cf-live-payment-display-mode"><?php esc_html_e( 'Payment Option Display', 'eilmo-checkout-flow' ); ?></label></th><td>
					<select id="eilmo-cf-live-payment-display-mode" name="eilmo_cf_courier_settings[live_fraud][payment_display_mode]">
						<option value="always" <?php selected( $display_mode, 'always' ); ?>><?php esc_html_e( 'Always Show — disable unavailable options', 'eilmo-checkout-flow' ); ?></option>
						<option value="conditional" <?php selected( $display_mode, 'conditional' ); ?>><?php esc_html_e( 'Conditional Show — show only available options', 'eilmo-checkout-flow' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Always Show keeps globally enabled cards visible and disables rejected choices. Conditional Show hides the section before verification, then shows only choices accepted by the result.', 'eilmo-checkout-flow' ); ?></p>
				</td></tr>
				<?php foreach ( $payment_bands as $band => $label ) :
					$selected_payments = isset( $payment_rules[ $band ] ) && is_array( $payment_rules[ $band ] ) ? $payment_rules[ $band ] : array();
					?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td>
						<input type="hidden" name="eilmo_cf_courier_settings[live_fraud][payment_rules][<?php echo esc_attr( $band ); ?>][]" value="">
						<?php foreach ( $payment_types as $payment_type => $payment_label ) : ?>
							<label class="eilmo-cf-fraud-payment-choice">
								<input type="checkbox" name="eilmo_cf_courier_settings[live_fraud][payment_rules][<?php echo esc_attr( $band ); ?>][]" value="<?php echo esc_attr( $payment_type ); ?>" <?php checked( in_array( $payment_type, $selected_payments, true ) ); ?>>
								<?php echo esc_html( $payment_label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php echo 'unavailable' === $band ? esc_html__( 'Select one or more fallback options. If none remain globally available, the plugin safely uses an enabled global option so API quota cannot block checkout.', 'eilmo-checkout-flow' ) : esc_html__( 'Only globally enabled options can become available. This list is also restricted by the selected Action above.', 'eilmo-checkout-flow' ); ?></p>
					</td></tr>
				<?php endforeach; ?>
				<?php
				$message_fields = array(
					'checking_message' => __( 'Checking Message', 'eilmo-checkout-flow' ),
					'allow_message' => __( 'Allowed Message', 'eilmo-checkout-flow' ),
					'advance_message' => __( 'Advance-required Message', 'eilmo-checkout-flow' ),
					'full_message' => __( 'Full-payment Message', 'eilmo-checkout-flow' ),
					'block_message' => __( 'Blocked Message', 'eilmo-checkout-flow' ),
					'unknown_message' => __( 'No-history Message', 'eilmo-checkout-flow' ),
					'unavailable_message' => __( 'API-unavailable Message', 'eilmo-checkout-flow' ),
					'local_message' => __( 'Website-history Message', 'eilmo-checkout-flow' ),
					'stale_message' => __( 'Stale-cache Message', 'eilmo-checkout-flow' ),
				);
				foreach ( $message_fields as $key => $label ) : ?>
					<tr><th scope="row"><label for="eilmo-cf-live-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
						<input type="text" class="large-text" id="eilmo-cf-live-<?php echo esc_attr( $key ); ?>" name="eilmo_cf_courier_settings[live_fraud][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $settings[ $key ] ?? '' ) ); ?>">
					</td></tr>
				<?php endforeach; ?>
			</tbody></table>
		</section>
		<?php
	}

	/**
	 * Render Steadfast settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_steadfast(
		array $settings
	): void {

		$base_url =
			(string) (
				$settings['base_url'] ??
					'https://portal.packzy.com/api/v1'
			);

		$has_api_key =
			'' !== trim(
				(string) (
					$settings['api_key'] ??
						''
				)
			);

		$has_secret_key =
			'' !== trim(
				(string) (
					$settings['secret_key'] ??
						''
				)
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Steadfast Courier',
					'eilmo-checkout-flow'
				),
				__(
					'Connect Steadfast so orders can be reviewed and sent directly from the WooCommerce Orders screen.',
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
							<label for="eilmo-cf-steadfast-base-url">
								<?php
								esc_html_e(
									'API Base URL',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="url"
								id="eilmo-cf-steadfast-base-url"
								class="regular-text"
								name="eilmo_cf_courier_settings[steadfast][base_url]"
								value="<?php echo esc_attr( $base_url ); ?>"
								placeholder="https://portal.packzy.com/api/v1"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Default Steadfast API base URL. Change only when Steadfast provides a different endpoint.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-steadfast-api-key">
								<?php
								esc_html_e(
									'API Key',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[steadfast][api_key]',
								'eilmo-cf-steadfast-api-key',
								$has_api_key
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Leave blank to keep the currently saved Steadfast API key.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-steadfast-secret-key">
								<?php
								esc_html_e(
									'Secret Key',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[steadfast][secret_key]',
								'eilmo-cf-steadfast-secret-key',
								$has_secret_key
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Leave blank to keep the currently saved Steadfast secret key.',
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
	 * Render Pathao settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_pathao(
		array $settings
	): void {

		$environment =
			sanitize_key(
				(string) (
					$settings['environment'] ??
						'live'
				)
			);

		$has_client_id =
			'' !== trim(
				(string) (
					$settings['client_id'] ??
						''
				)
			);

		$has_client_secret =
			'' !== trim(
				(string) (
					$settings['client_secret'] ??
						''
				)
			);

		$has_webhook_secret =
			'' !== trim(
				(string) (
					$settings['webhook_secret'] ??
						''
				)
			);

		$webhook_url =
			rest_url(
				'eilmo-checkout/v1/couriers/pathao/webhook'
			);

		$store_id =
			absint(
				$settings['store_id'] ??
					0
			);

		$delivery_type =
			absint(
				$settings['delivery_type'] ??
					48
			);

		$item_type =
			absint(
				$settings['item_type'] ??
					2
			);

		$item_weight =
			isset( $settings['item_weight'] ) &&
			is_numeric( $settings['item_weight'] )
				? max(
					0.1,
					(float) $settings['item_weight']
				)
				: 0.5;

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Pathao Courier',
					'eilmo-checkout-flow'
				),
				__(
					'Connect Pathao using Client ID and Client Secret. Access tokens are requested and refreshed automatically.',
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
							<label for="eilmo-cf-pathao-environment">
								<?php
								esc_html_e(
									'Environment',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<select
								id="eilmo-cf-pathao-environment"
								name="eilmo_cf_courier_settings[pathao][environment]"
							>
								<?php
								$this->render_option(
									'live',
									__(
										'Live',
										'eilmo-checkout-flow'
									),
									$environment
								);

								$this->render_option(
									'staging',
									__(
										'Staging',
										'eilmo-checkout-flow'
									),
									$environment
								);
								?>
							</select>

							<p class="description">
								<?php
								esc_html_e(
									'Use Live for real courier bookings. Use Staging only with Pathao sandbox credentials.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-client-id">
								<?php
								esc_html_e(
									'Client ID',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[pathao][client_id]',
								'eilmo-cf-pathao-client-id',
								$has_client_id
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Leave blank to keep the currently saved Pathao Client ID.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-client-secret">
								<?php
								esc_html_e(
									'Client Secret',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[pathao][client_secret]',
								'eilmo-cf-pathao-client-secret',
								$has_client_secret
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Leave blank to keep the currently saved Pathao Client Secret.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-webhook-url">
								<?php
								esc_html_e(
									'Webhook URL',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="url"
								id="eilmo-cf-pathao-webhook-url"
								class="regular-text code"
								value="<?php echo esc_attr( $webhook_url ); ?>"
								readonly
								onclick="this.select();"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Copy this URL into Pathao Merchant Panel → Developer API → Webhook Integration.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-webhook-secret">
								<?php
								esc_html_e(
									'Webhook Secret',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<?php
							$this->render_secret_field(
								'eilmo_cf_courier_settings[pathao][webhook_secret]',
								'eilmo-cf-pathao-webhook-secret',
								$has_webhook_secret
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Enter the exact same secret that you configure in Pathao Merchant Panel → Developer API → Webhook Integration. This field is required for authenticated Pathao status webhooks.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-store-id">
								<?php
								esc_html_e(
									'Default Store ID',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="number"
								id="eilmo-cf-pathao-store-id"
								name="eilmo_cf_courier_settings[pathao][store_id]"
								value="<?php echo esc_attr( (string) $store_id ); ?>"
								min="0"
								step="1"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Optional. Use 0 to choose a Pathao store from the editable courier popup.',
									'eilmo-checkout-flow'
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-delivery-type">
								<?php
								esc_html_e(
									'Default Delivery Type',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<select
								id="eilmo-cf-pathao-delivery-type"
								name="eilmo_cf_courier_settings[pathao][delivery_type]"
							>
								<?php
								$this->render_option(
									'48',
									__(
										'Normal Delivery',
										'eilmo-checkout-flow'
									),
									(string) $delivery_type
								);

								$this->render_option(
									'12',
									__(
										'On Demand',
										'eilmo-checkout-flow'
									),
									(string) $delivery_type
								);

								$this->render_option(
									'24',
									__(
										'Express Delivery',
										'eilmo-checkout-flow'
									),
									(string) $delivery_type
								);
								?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-item-type">
								<?php
								esc_html_e(
									'Default Item Type',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<select
								id="eilmo-cf-pathao-item-type"
								name="eilmo_cf_courier_settings[pathao][item_type]"
							>
								<?php
								$this->render_option(
									'1',
									__(
										'Document',
										'eilmo-checkout-flow'
									),
									(string) $item_type
								);

								$this->render_option(
									'2',
									__(
										'Parcel',
										'eilmo-checkout-flow'
									),
									(string) $item_type
								);

								$this->render_option(
									'3',
									__(
										'Fragile',
										'eilmo-checkout-flow'
									),
									(string) $item_type
								);
								?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="eilmo-cf-pathao-item-weight">
								<?php
								esc_html_e(
									'Default Item Weight',
									'eilmo-checkout-flow'
								);
								?>
							</label>
						</th>

						<td>
							<input
								type="number"
								id="eilmo-cf-pathao-item-weight"
								name="eilmo_cf_courier_settings[pathao][item_weight]"
								value="<?php echo esc_attr( (string) $item_weight ); ?>"
								min="0.1"
								step="0.1"
							>

							<span>
								<?php
								esc_html_e(
									'kg',
									'eilmo-checkout-flow'
								);
								?>
							</span>

							<p class="description">
								<?php
								esc_html_e(
									'Used as the default weight in the editable Pathao booking popup. It can be changed before sending.',
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
	 * Render credential field without exposing the stored value.
	 *
	 * CourierSettings preserves an existing credential when an empty
	 * value is submitted, so password fields intentionally render blank.
	 *
	 * @param string $name      Input name.
	 * @param string $id        Input ID.
	 * @param bool   $has_value Whether a credential is already stored.
	 *
	 * @return void
	 */
	private function render_secret_field(
		string $name,
		string $id,
		bool $has_value
	): void {
		?>
		<input
			type="password"
			id="<?php echo esc_attr( $id ); ?>"
			class="regular-text"
			name="<?php echo esc_attr( $name ); ?>"
			value=""
			autocomplete="new-password"
			placeholder="<?php
				echo esc_attr(
					$has_value
						? __(
							'Saved — enter a new value to replace',
							'eilmo-checkout-flow'
						)
						: __(
							'Enter credential',
							'eilmo-checkout-flow'
						)
				);
			?>"
		>
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
			value="<?php echo esc_attr( $value ); ?>"
			<?php selected( $current, $value ); ?>
		>
			<?php echo esc_html( $label ); ?>
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
			! isset( $source[ $key ] ) ||
			! is_array( $source[ $key ] )
		) {
			return array();
		}

		return $source[ $key ];
	}
}
