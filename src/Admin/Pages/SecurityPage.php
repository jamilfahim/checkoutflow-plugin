<?php
/**
 * Security page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Security\Blacklist\BlacklistRepository;
use EilmoCheckout\Security\Logging\SecurityLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Eilmo Checkout Flow Security settings, blacklist and activity logs.
 */
final class SecurityPage {

	/**
	 * Security page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-security';

	/**
	 * Protection tab.
	 *
	 * @var string
	 */
	private const TAB_PROTECTION =
		'protection';

	/**
	 * Blacklist tab.
	 *
	 * @var string
	 */
	private const TAB_BLACKLIST =
		'blacklist';

	/**
	 * Activity Logs tab.
	 *
	 * @var string
	 */
	private const TAB_ACTIVITY_LOGS =
		'activity_logs';

	/**
	 * Activity Logs per page.
	 *
	 * @var int
	 */
	private const LOGS_PER_PAGE = 20;

	/**
	 * Render Security page.
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

		$tab =
			$this->get_current_tab();

		?>
		<div class="wrap eilmo-cf-admin">

			<div class="eilmo-cf-admin__header">

				<div class="eilmo-cf-admin__heading">

					<h1>
						<?php
						esc_html_e(
							'Checkout Security',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Configure checkout access and protection for Checkout Flow, WooCommerce Classic Checkout and WooCommerce Block Checkout.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

				</div>

			</div>

			<?php
			$this->render_tabs(
				$tab
			);

			$this->render_tab(
				$tab
			);
			?>

		</div>
		<?php
	}

	/**
	 * Render Security navigation tabs.
	 *
	 * Settings, Blacklist and Activity Logs remain visible even
	 * when a protection is disabled so the administrator can
	 * re-enable/configure it from this page.
	 *
	 * @param string $current_tab Current tab.
	 *
	 * @return void
	 */
	private function render_tabs(
		string $current_tab
	): void {

		$tabs =
			array(
				self::TAB_PROTECTION =>
					__(
						'Settings',
						'eilmo-checkout-flow'
					),

				self::TAB_BLACKLIST =>
					__(
						'Blacklist',
						'eilmo-checkout-flow'
					),

				self::TAB_ACTIVITY_LOGS =>
					__(
						'Activity Logs',
						'eilmo-checkout-flow'
					),
			);

		?>
		<nav
			class="nav-tab-wrapper eilmo-cf-settings-tabs"
			aria-label="<?php
				esc_attr_e(
					'Security',
					'eilmo-checkout-flow'
				);
			?>"
		>

			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>

				<?php
				$url =
					add_query_arg(
						array(
							'page' =>
								self::PAGE_SLUG,

							'tab' =>
								$tab_key,
						),
						admin_url(
							'admin.php'
						)
					);

				$classes =
					array(
						'nav-tab',
					);

				if (
					$current_tab ===
					$tab_key
				) {
					$classes[] =
						'nav-tab-active';
				}
				?>

				<a
					class="<?php
						echo esc_attr(
							implode(
								' ',
								$classes
							)
						);
					?>"
					href="<?php
						echo esc_url(
							$url
						);
					?>"
				>
					<?php
					echo esc_html(
						$tab_label
					);
					?>
				</a>

			<?php endforeach; ?>

		</nav>
		<?php
	}

	/**
	 * Render current Security tab.
	 *
	 * @param string $tab Current tab.
	 *
	 * @return void
	 */
	private function render_tab(
		string $tab
	): void {

		switch ( $tab ) {

			case self::TAB_BLACKLIST:
				$this->render_blacklist();
				break;

			case self::TAB_ACTIVITY_LOGS:
				$this->render_activity_logs();
				break;

			case self::TAB_PROTECTION:
			default:
				$this->render_protection();
				break;
		}
	}

	/**
	 * Render Checkout Security settings.
	 *
	 * @return void
	 */
	private function render_protection(): void {

		$settings =
			SecuritySettings::get_settings();

		$access =
			$this->get_array(
				$settings,
				'access'
			);

		$protection =
			$this->get_array(
				$settings,
				'protection'
			);

		$rate_limit =
			$this->get_array(
				$protection,
				'rate_limit'
			);

		$duplicate_order =
			$this->get_array(
				$protection,
				'duplicate_order'
			);

		$honeypot =
			$this->get_array(
				$protection,
				'honeypot'
			);

		$security_token =
			$this->get_array(
				$protection,
				'security_token'
			);

		$minimum_checkout_time =
			$this->get_array(
				$protection,
				'minimum_checkout_time'
			);

		$order_cooldown =
			$this->get_array(
				$protection,
				'order_cooldown'
			);

		$blacklist =
			$this->get_array(
				$protection,
				'blacklist'
			);

		$activity_logging =
			$this->get_array(
				$protection,
				'activity_logging'
			);

		$messages =
			$this->get_array(
				$settings,
				'messages'
			);

		$advanced =
			$this->get_array(
				$settings,
				'advanced'
			);

		$security_enabled =
			'yes' ===
			(string) (
				$settings[
					'enabled'
				] ??
				'no'
			);

		?>
		<form
			method="post"
			action="options.php"
			class="eilmo-cf-settings-form"
		>
			<?php
			settings_fields(
				SecuritySettings::OPTION_GROUP
			);
			?>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Security Features',
						'eilmo-checkout-flow'
					),
					__(
						'Use one master switch for checkout protection. Individual protections keep their saved on/off state when the master switch is disabled.',
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
									'Enable Checkout Security',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[enabled]',
									'yes',
									$security_enabled
										? 'yes'
										: 'no'
								);
								?>

								<p class="description">
									<?php
									esc_html_e(
										'Enable checkout protections for Eilmo Checkout Flow and supported native WooCommerce checkout flows. New protection settings default to enabled.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Checkout Access',
						'eilmo-checkout-flow'
					),
					__(
						'Control whether guests or logged-in customers may place orders and configure the login experience.',
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
								<label for="eilmo-cf-security-checkout-access">
									<?php
									esc_html_e(
										'Checkout Access',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<select
									id="eilmo-cf-security-checkout-access"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[access][checkout_access]"
								>
									<option
										value="everyone"
										<?php
										selected(
											(string) (
												$access[
													'checkout_access'
												] ??
												'everyone'
											),
											'everyone'
										);
										?>
									>
										<?php
										esc_html_e(
											'Everyone',
											'eilmo-checkout-flow'
										);
										?>
									</option>

									<option
										value="guest_only"
										<?php
										selected(
											(string) (
												$access[
													'checkout_access'
												] ??
												'everyone'
											),
											'guest_only'
										);
										?>
									>
										<?php
										esc_html_e(
											'Guest Users Only',
											'eilmo-checkout-flow'
										);
										?>
									</option>

									<option
										value="logged_in_only"
										<?php
										selected(
											(string) (
												$access[
													'checkout_access'
												] ??
												'everyone'
											),
											'logged_in_only'
										);
										?>
									>
										<?php
										esc_html_e(
											'Logged-in Users Only',
											'eilmo-checkout-flow'
										);
										?>
									</option>
								</select>

								<p class="description">
									<?php
									esc_html_e(
										'Choose who may place an order. Logged-in Users Only may still show the checkout but requires login before order submission.',
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
									'Custom Login URL',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[access][custom_login_url_enabled]',
									'yes',
									(string) (
										$access[
											'custom_login_url_enabled'
										] ??
										'no'
									)
								);
								?>

								<p class="description">
									<?php
									esc_html_e(
										'Use a custom login page instead of the default WordPress login URL when login is required.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-custom-login-url">
									<?php
									esc_html_e(
										'Login URL',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<input
									type="url"
									id="eilmo-cf-security-custom-login-url"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[access][custom_login_url]"
									value="<?php
										echo esc_attr(
											(string) (
												$access[
													'custom_login_url'
												] ??
												''
											)
										);
									?>"
									class="regular-text"
									placeholder="<?php
										echo esc_attr(
											home_url(
												'/login/'
											)
										);
									?>"
								>

								<p class="description">
									<?php
									esc_html_e(
										'Leave empty to use the default WordPress login page.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-login-button-text">
									<?php
									esc_html_e(
										'Login Button Text',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<input
									type="text"
									id="eilmo-cf-security-login-button-text"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[access][login_button_text]"
									value="<?php
										echo esc_attr(
											(string) (
												$access[
													'login_button_text'
												] ??
												__(
													'Log In to Order',
													'eilmo-checkout-flow'
												)
											)
										);
									?>"
									class="regular-text"
								>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-after-login">
									<?php
									esc_html_e(
										'After Login',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<select
									id="eilmo-cf-security-after-login"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[access][after_login]"
								>
									<option
										value="checkout"
										<?php
										selected(
											(string) (
												$access[
													'after_login'
												] ??
												'checkout'
											),
											'checkout'
										);
										?>
									>
										<?php
										esc_html_e(
											'Return to Checkout',
											'eilmo-checkout-flow'
										);
										?>
									</option>

									<option
										value="custom_url"
										<?php
										selected(
											(string) (
												$access[
													'after_login'
												] ??
												'checkout'
											),
											'custom_url'
										);
										?>
									>
										<?php
										esc_html_e(
											'Custom Redirect URL',
											'eilmo-checkout-flow'
										);
										?>
									</option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-after-login-url">
									<?php
									esc_html_e(
										'After Login URL',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<input
									type="url"
									id="eilmo-cf-security-after-login-url"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[access][after_login_url]"
									value="<?php
										echo esc_attr(
											(string) (
												$access[
													'after_login_url'
												] ??
												''
											)
										);
									?>"
									class="regular-text"
								>

								<p class="description">
									<?php
									esc_html_e(
										'Used only when After Login is set to Custom Redirect URL.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Order Cooldown',
						'eilmo-checkout-flow'
					),
					__(
						'Require a customer to wait after a successful order before placing another order.',
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
									'Enable Order Cooldown',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][order_cooldown][enabled]',
									'yes',
									(string) (
										$order_cooldown[
											'enabled'
										] ??
										'yes'
									)
								);
								?>

								<p class="description">
									<?php
									esc_html_e(
										'Block a new checkout until the configured waiting period has passed after the customer successfully places an order.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-cooldown-duration">
									<?php
									esc_html_e(
										'Waiting Time',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<input
									type="number"
									id="eilmo-cf-security-cooldown-duration"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][order_cooldown][duration]"
									value="<?php
										echo esc_attr(
											(string) (
												$order_cooldown[
													'duration'
												] ??
												2
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>

								<select
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][order_cooldown][unit]"
									aria-label="<?php
										esc_attr_e(
											'Cooldown time unit',
											'eilmo-checkout-flow'
										);
									?>"
								>
									<?php
									foreach (
										array(
											'seconds' =>
												__(
													'Seconds',
													'eilmo-checkout-flow'
												),
											'minutes' =>
												__(
													'Minutes',
													'eilmo-checkout-flow'
												),
											'hours' =>
												__(
													'Hours',
													'eilmo-checkout-flow'
												),
										) as
										$unit_value =>
										$unit_label
									) :
										?>
										<option
											value="<?php
												echo esc_attr(
													$unit_value
												);
											?>"
											<?php
											selected(
												(string) (
													$order_cooldown[
														'unit'
													] ??
													'minutes'
												),
												$unit_value
											);
											?>
										>
											<?php
											echo esc_html(
												$unit_label
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-cooldown-identify">
									<?php
									esc_html_e(
										'Identify Customer By',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>

							<td>
								<select
									id="eilmo-cf-security-cooldown-identify"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][order_cooldown][identify_by]"
								>
									<?php
									$identify_options =
										array(
											'smart' =>
												__(
													'Smart — Customer ID, Phone, Email, IP',
													'eilmo-checkout-flow'
												),
											'customer_id' =>
												__(
													'Logged-in Customer ID',
													'eilmo-checkout-flow'
												),
											'phone' =>
												__(
													'Phone Number',
													'eilmo-checkout-flow'
												),
											'email' =>
												__(
													'Email Address',
													'eilmo-checkout-flow'
												),
											'ip' =>
												__(
													'IP Address',
													'eilmo-checkout-flow'
												),
										);

									foreach (
										$identify_options as
										$value =>
										$label
									) :
										?>
										<option
											value="<?php
												echo esc_attr(
													$value
												);
											?>"
											<?php
											selected(
												(string) (
													$order_cooldown[
														'identify_by'
													] ??
													'smart'
												),
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
									<?php endforeach; ?>
								</select>

								<p class="description">
									<?php
									esc_html_e(
										'Smart is recommended. It avoids relying only on shared IP addresses and works for guest and logged-in customers.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Order Rate Limiting',
						'eilmo-checkout-flow'
					),
					__(
						'Limit excessive order creation and temporarily restrict customers who place too many orders within the configured window.',
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
									'Enable Rate Limit',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][rate_limit][enabled]',
									'yes',
									(string) (
										$rate_limit[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-maximum-orders">
									<?php
									esc_html_e(
										'Maximum Orders',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-maximum-orders"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][rate_limit][maximum_orders]"
									value="<?php
										echo esc_attr(
											(string) (
												$rate_limit[
													'maximum_orders'
												] ??
												3
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-window-minutes">
									<?php
									esc_html_e(
										'Order Window',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-window-minutes"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][rate_limit][window_minutes]"
									value="<?php
										echo esc_attr(
											(string) (
												$rate_limit[
													'window_minutes'
												] ??
												10
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
								<span>
									<?php
									esc_html_e(
										'minutes',
										'eilmo-checkout-flow'
									);
									?>
								</span>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-block-minutes">
									<?php
									esc_html_e(
										'Block Duration',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-block-minutes"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][rate_limit][block_minutes]"
									value="<?php
										echo esc_attr(
											(string) (
												$rate_limit[
													'block_minutes'
												] ??
												60
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
								<span>
									<?php
									esc_html_e(
										'minutes',
										'eilmo-checkout-flow'
									);
									?>
								</span>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Duplicate Order Protection',
						'eilmo-checkout-flow'
					),
					__(
						'Prevent accidental or abusive repeat submissions of the same order.',
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
									'Enable Duplicate Protection',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][enabled]',
									'yes',
									(string) (
										$duplicate_order[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-duplicate-window">
									<?php
									esc_html_e(
										'Detection Window',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-duplicate-window"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][duplicate_order][window_minutes]"
									value="<?php
										echo esc_attr(
											(string) (
												$duplicate_order[
													'window_minutes'
												] ??
												10
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
								<span>
									<?php
									esc_html_e(
										'minutes',
										'eilmo-checkout-flow'
									);
									?>
								</span>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Compare Customer',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][compare_customer]',
									'yes',
									(string) (
										$duplicate_order[
											'compare_customer'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Compare Products',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][compare_products]',
									'yes',
									(string) (
										$duplicate_order[
											'compare_products'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Compare Quantities',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][compare_quantities]',
									'yes',
									(string) (
										$duplicate_order[
											'compare_quantities'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Compare Order Total',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][compare_total]',
									'yes',
									(string) (
										$duplicate_order[
											'compare_total'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Allow Different Products',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][duplicate_order][allow_different_products]',
									'yes',
									(string) (
										$duplicate_order[
											'allow_different_products'
										] ??
										'yes'
									)
								);
								?>

								<p class="description">
									<?php
									esc_html_e(
										'Allow the same customer to place a different product order during the duplicate detection window.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Bot Protection',
						'eilmo-checkout-flow'
					),
					__(
						'Use lightweight validation against common automated and scripted checkout submissions.',
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
									'Honeypot Protection',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][honeypot][enabled]',
									'yes',
									(string) (
										$honeypot[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
								<p class="description">
									<?php
									esc_html_e(
										'Use an invisible field to detect common automated bots.',
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
									'Security Token',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][security_token][enabled]',
									'yes',
									(string) (
										$security_token[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
								<p class="description">
									<?php
									esc_html_e(
										'Require the checkout security token used by supported checkout flows.',
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
									'Minimum Checkout Time',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][minimum_checkout_time][enabled]',
									'yes',
									(string) (
										$minimum_checkout_time[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-minimum-seconds">
									<?php
									esc_html_e(
										'Minimum Time',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-minimum-seconds"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][minimum_checkout_time][seconds]"
									value="<?php
										echo esc_attr(
											(string) (
												$minimum_checkout_time[
													'seconds'
												] ??
												3
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
								<span>
									<?php
									esc_html_e(
										'seconds',
										'eilmo-checkout-flow'
									);
									?>
								</span>

								<p class="description">
									<?php
									esc_html_e(
										'Checkout submissions faster than this value may be treated as automated requests.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Customer Blacklist',
						'eilmo-checkout-flow'
					),
					__(
						'Enable customer blocking by IP address, phone number or email address.',
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
									'Enable Customer Blacklist',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][blacklist][enabled]',
									'yes',
									(string) (
										$blacklist[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Normalize Phone Numbers',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][blacklist][normalize_phone]',
									'yes',
									(string) (
										$blacklist[
											'normalize_phone'
										] ??
										'yes'
									)
								);
								?>

								<p class="description">
									<?php
									esc_html_e(
										'Normalize phone formatting before blacklist comparison so equivalent phone formats can match consistently.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Security Activity Logging',
						'eilmo-checkout-flow'
					),
					__(
						'Control which blocked checkout events are stored and how long Security logs are kept.',
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
									'Enable Activity Logging',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][activity_logging][enabled]',
									'yes',
									(string) (
										$activity_logging[
											'enabled'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Automatic Log Cleanup',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[protection][activity_logging][auto_cleanup]',
									'yes',
									(string) (
										$activity_logging[
											'auto_cleanup'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="eilmo-cf-security-retention-days">
									<?php
									esc_html_e(
										'Keep Logs For',
										'eilmo-checkout-flow'
									);
									?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="eilmo-cf-security-retention-days"
									name="<?php
										echo esc_attr(
											SecuritySettings::OPTION_NAME
										);
									?>[protection][activity_logging][retention_days]"
									value="<?php
										echo esc_attr(
											(string) (
												$activity_logging[
													'retention_days'
												] ??
												30
											)
										);
									?>"
									min="1"
									step="1"
									class="small-text"
								>
								<span>
									<?php
									esc_html_e(
										'days',
										'eilmo-checkout-flow'
									);
									?>
								</span>
							</td>
						</tr>

						<?php
						$log_options =
							array(
								'log_rate_limit' =>
									__(
										'Log Rate Limit',
										'eilmo-checkout-flow'
									),
								'log_duplicate' =>
									__(
										'Log Duplicate Orders',
										'eilmo-checkout-flow'
									),
								'log_bot' =>
									__(
										'Log Bot Protection',
										'eilmo-checkout-flow'
									),
								'log_cooldown' =>
									__(
										'Log Order Cooldown',
										'eilmo-checkout-flow'
									),
								'log_blacklist' =>
									__(
										'Log Blacklist Blocks',
										'eilmo-checkout-flow'
									),
							);

						foreach (
							$log_options as
							$log_key =>
							$log_label
						) :
							?>
							<tr>
								<th scope="row">
									<?php
									echo esc_html(
										$log_label
									);
									?>
								</th>
								<td>
									<?php
									$this->render_switch(
										SecuritySettings::OPTION_NAME .
											'[protection][activity_logging][' .
											$log_key .
											']',
										'yes',
										(string) (
											$activity_logging[
												$log_key
											] ??
											'yes'
										)
									);
									?>
								</td>
							</tr>
						<?php endforeach; ?>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Customer Messages',
						'eilmo-checkout-flow'
					),
					__(
						'Customize the messages customers see when a Security rule or protected checkout request stops order submission.',
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
						$message_fields =
							array(
								'cooldown' =>
									array(
										__(
											'Order Cooldown Message',
											'eilmo-checkout-flow'
										),
										__(
											'You recently placed an order. Please wait {remaining_time} before placing another order.',
											'eilmo-checkout-flow'
										),
									),
								'duplicate_order' =>
									array(
										__(
											'Duplicate Order Message',
											'eilmo-checkout-flow'
										),
										__(
											'A similar order was recently submitted or is still being processed. Please wait before placing the same order again.',
											'eilmo-checkout-flow'
										),
									),
								'rate_limit' =>
									array(
										__(
											'Rate Limit Message',
											'eilmo-checkout-flow'
										),
										__(
											'Too many orders were placed within the allowed time window. Please try again in {remaining_time}.',
											'eilmo-checkout-flow'
										),
									),
								'bot_protection' =>
									array(
										__(
											'Bot Protection Message',
											'eilmo-checkout-flow'
										),
										__(
											'We could not verify this checkout request. Please refresh the page and try again.',
											'eilmo-checkout-flow'
										),
									),
								'blacklist' =>
									array(
										__(
											'Blacklist Message',
											'eilmo-checkout-flow'
										),
										__(
											'This checkout request cannot be processed. Please contact us if you need assistance.',
											'eilmo-checkout-flow'
										),
									),
								'checkout_token_invalid' =>
									array(
										__(
											'Invalid Checkout Token Message',
											'eilmo-checkout-flow'
										),
										__(
											'The checkout request token is invalid. Please refresh the page and try again.',
											'eilmo-checkout-flow'
										),
									),
								'checkout_request_failed' =>
									array(
										__(
											'Checkout Request Failed Message',
											'eilmo-checkout-flow'
										),
										__(
											'The checkout request could not be finalized. Please refresh the page and try again.',
											'eilmo-checkout-flow'
										),
									),
							);

						foreach (
							$message_fields as
							$message_key =>
							$message_config
						) :
							?>
							<tr>
								<th scope="row">
									<label for="<?php
										echo esc_attr(
											'eilmo-cf-security-message-' .
											$message_key
										);
									?>">
										<?php
										echo esc_html(
											$message_config[
												0
											]
										);
										?>
									</label>
								</th>
								<td>
									<textarea
										id="<?php
											echo esc_attr(
												'eilmo-cf-security-message-' .
												$message_key
											);
										?>"
										name="<?php
											echo esc_attr(
												SecuritySettings::OPTION_NAME
											);
										?>[messages][<?php
											echo esc_attr(
												$message_key
											);
										?>]"
										rows="3"
										class="large-text"
									><?php
										echo esc_textarea(
											(string) (
												$messages[
													$message_key
												] ??
												$message_config[
													1
												]
											)
										);
									?></textarea>

									<?php if (
										in_array(
											$message_key,
											array(
												'cooldown',
												'rate_limit',
											),
											true
										)
									) : ?>
										<p class="description">
											<?php
											esc_html_e(
												'Available placeholder: {remaining_time}',
												'eilmo-checkout-flow'
											);
											?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>

					</tbody>
				</table>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Advanced',
						'eilmo-checkout-flow'
					),
					__(
						'Allow trusted store administrators to test checkout without being blocked by customer-facing Security protections.',
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
									'Bypass for Administrators',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[advanced][bypass_administrators]',
									'yes',
									(string) (
										$advanced[
											'bypass_administrators'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php
								esc_html_e(
									'Bypass for Shop Managers',
									'eilmo-checkout-flow'
								);
								?>
							</th>
							<td>
								<?php
								$this->render_switch(
									SecuritySettings::OPTION_NAME .
										'[advanced][bypass_shop_managers]',
									'yes',
									(string) (
										$advanced[
											'bypass_shop_managers'
										] ??
										'yes'
									)
								);
								?>
							</td>
						</tr>

					</tbody>
				</table>

			</section>

			<?php
			submit_button(
				__(
					'Save Security Settings',
					'eilmo-checkout-flow'
				)
			);
			?>

		</form>
		<?php
	}

	/**
	 * Render Customer Blacklist tab.
	 *
	 * @return void
	 */
	private function render_blacklist(): void {

		$security_settings =
			SecuritySettings::get_settings();

		$protection =
			$this->get_array(
				$security_settings,
				'protection'
			);

		$blacklist_settings =
			$this->get_array(
				$protection,
				'blacklist'
			);

		$blacklist_enabled =
			SecuritySettings::is_enabled() &&
			'yes' ===
				(string) (
					$blacklist_settings[
						'enabled'
					] ??
					'yes'
				);

		$repository =
			new BlacklistRepository();

		$entries =
			$repository->get_all();

		?>
		<div class="eilmo-cf-settings-form">

			<?php
			$this->render_blacklist_notice();
			?>

			<?php if ( ! $blacklist_enabled ) : ?>

				<div class="notice notice-warning inline">
					<p>
						<?php
						esc_html_e(
							'Customer Blacklist is currently not enforced. Enable Checkout Security and Customer Blacklist from the Settings tab to activate blocking. Existing entries are preserved.',
							'eilmo-checkout-flow'
						);
						?>
					</p>
				</div>

			<?php endif; ?>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Add Blacklist Entry',
						'eilmo-checkout-flow'
					),
					__(
						'Block a customer by IP address, phone number or email address.',
						'eilmo-checkout-flow'
					)
				);
				?>

				<form
					method="post"
					action="<?php
						echo esc_url(
							admin_url(
								'admin-post.php'
							)
						);
					?>"
				>

					<input
						type="hidden"
						name="action"
						value="eilmo_cf_blacklist_add"
					>

					<?php
					wp_nonce_field(
						'eilmo_cf_blacklist_add'
					);
					?>

					<table
						class="form-table"
						role="presentation"
					>
						<tbody>

							<tr>
								<th scope="row">
									<label for="eilmo-cf-blacklist-type">
										<?php
										esc_html_e(
											'Block By',
											'eilmo-checkout-flow'
										);
										?>
									</label>
								</th>

								<td>
									<select
										id="eilmo-cf-blacklist-type"
										name="type"
									>
										<option value="ip">
											<?php
											esc_html_e(
												'IP Address',
												'eilmo-checkout-flow'
											);
											?>
										</option>

										<option value="phone">
											<?php
											esc_html_e(
												'Phone Number',
												'eilmo-checkout-flow'
											);
											?>
										</option>

										<option value="email">
											<?php
											esc_html_e(
												'Email Address',
												'eilmo-checkout-flow'
											);
											?>
										</option>
									</select>

									<p class="description">
										<?php
										esc_html_e(
											'Choose which customer identifier should be blocked.',
											'eilmo-checkout-flow'
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eilmo-cf-blacklist-value">
										<?php
										esc_html_e(
											'Value',
											'eilmo-checkout-flow'
										);
										?>
									</label>
								</th>

								<td>
									<input
										type="text"
										id="eilmo-cf-blacklist-value"
										name="value"
										value=""
										class="regular-text"
										required
									>

									<p class="description">
										<?php
										esc_html_e(
											'Enter the IP address, phone number or email address to block.',
											'eilmo-checkout-flow'
										);
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="eilmo-cf-blacklist-reason">
										<?php
										esc_html_e(
											'Reason',
											'eilmo-checkout-flow'
										);
										?>
									</label>
								</th>

								<td>
									<textarea
										id="eilmo-cf-blacklist-reason"
										name="reason"
										rows="3"
										class="large-text"
									></textarea>

									<p class="description">
										<?php
										esc_html_e(
											'Optional internal note explaining why this customer was blocked.',
											'eilmo-checkout-flow'
										);
										?>
									</p>
								</td>
							</tr>

						</tbody>
					</table>

					<?php
					submit_button(
						__(
							'Add Blacklist Entry',
							'eilmo-checkout-flow'
						)
					);
					?>

				</form>

			</section>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Blocked Customers',
						'eilmo-checkout-flow'
					),
					__(
						'Active blacklist entries are shown below.',
						'eilmo-checkout-flow'
					)
				);
				?>

				<?php if ( empty( $entries ) ) : ?>

					<p class="description">
						<?php
						esc_html_e(
							'No customers are currently blacklisted.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

				<?php else : ?>

					<table class="widefat striped">

						<thead>
							<tr>
								<th>
									<?php
									esc_html_e(
										'Type',
										'eilmo-checkout-flow'
									);
									?>
								</th>

								<th>
									<?php
									esc_html_e(
										'Value',
										'eilmo-checkout-flow'
									);
									?>
								</th>

								<th>
									<?php
									esc_html_e(
										'Reason',
										'eilmo-checkout-flow'
									);
									?>
								</th>

								<th>
									<?php
									esc_html_e(
										'Added',
										'eilmo-checkout-flow'
									);
									?>
								</th>

								<th>
									<?php
									esc_html_e(
										'Action',
										'eilmo-checkout-flow'
									);
									?>
								</th>
							</tr>
						</thead>

						<tbody>

							<?php foreach ( $entries as $entry ) : ?>

								<tr>

									<td>
										<?php
										echo esc_html(
											$this->get_blacklist_type_label(
												(string) (
													$entry[
														'type'
													] ??
													''
												)
											)
										);
										?>
									</td>

									<td>
										<code>
											<?php
											echo esc_html(
												(string) (
													$entry[
														'value'
													] ??
													''
												)
											);
											?>
										</code>
									</td>

									<td>
										<?php
										$reason =
											trim(
												(string) (
													$entry[
														'reason'
													] ??
													''
												)
											);

										echo esc_html(
											'' !== $reason
												? $reason
												: '—'
										);
										?>
									</td>

									<td>
										<?php
										echo esc_html(
											$this->format_date(
												(string) (
													$entry[
														'created_at'
													] ??
													''
												)
											)
										);
										?>
									</td>

									<td>

										<form
											method="post"
											action="<?php
												echo esc_url(
													admin_url(
														'admin-post.php'
													)
												);
											?>"
										>

											<input
												type="hidden"
												name="action"
												value="eilmo_cf_blacklist_remove"
											>

											<input
												type="hidden"
												name="entry_id"
												value="<?php
													echo esc_attr(
														(string) absint(
															$entry[
																'id'
															] ??
															0
														)
													);
												?>"
											>

											<?php
											wp_nonce_field(
												'eilmo_cf_blacklist_remove_' .
												absint(
													$entry[
														'id'
													] ??
													0
												)
											);
											?>

											<button
												type="submit"
												class="button button-secondary"
											>
												<?php
												esc_html_e(
													'Remove',
													'eilmo-checkout-flow'
												);
												?>
											</button>

										</form>

									</td>

								</tr>

							<?php endforeach; ?>

						</tbody>

					</table>

				<?php endif; ?>

			</section>

		</div>
		<?php
	}

	/**
	 * Render Security Activity Logs.
	 *
	 * @return void
	 */
	private function render_activity_logs(): void {

		$security_settings =
			SecuritySettings::get_settings();

		$protection =
			$this->get_array(
				$security_settings,
				'protection'
			);

		$activity_logging =
			$this->get_array(
				$protection,
				'activity_logging'
			);

		$logging_enabled =
			SecuritySettings::is_enabled() &&
			'yes' ===
				(string) (
					$activity_logging[
						'enabled'
					] ??
					'yes'
				);

		$filters =
			$this->get_activity_log_filters();

		$repository =
			new SecurityLogRepository();

		$result =
			$repository->get_logs(
				array(
					'page' =>
						$filters[
							'page'
						],

					'per_page' =>
						self::LOGS_PER_PAGE,

					'protection_type' =>
						$filters[
							'protection_type'
						],

					'checkout_source' =>
						$filters[
							'checkout_source'
						],

					'status' =>
						$filters[
							'status'
						],

					'search' =>
						$filters[
							'search'
						],

					'orderby' =>
						'created_at',

					'order' =>
						'DESC',
				)
			);

		$logs =
			isset(
				$result[
					'items'
				]
			) &&
			is_array(
				$result[
					'items'
				]
			)
				? $result[
					'items'
				]
				: array();

		$total =
			absint(
				$result[
					'total'
				] ??
				0
			);

		$total_pages =
			absint(
				$result[
					'total_pages'
				] ??
				0
			);

		$current_page =
			max(
				1,
				absint(
					$result[
						'page'
					] ??
					1
				)
			);

		?>
		<div class="eilmo-cf-settings-form">

			<?php
			$this->render_security_log_notice();
			?>

			<?php if ( ! $logging_enabled ) : ?>

				<div class="notice notice-warning inline">
					<p>
						<?php
						esc_html_e(
							'Security Activity Logging is currently disabled. Existing historical records remain available below, but new Security events will not be stored.',
							'eilmo-checkout-flow'
						);
						?>
					</p>
				</div>

			<?php endif; ?>

			<section class="eilmo-cf-settings-section">

				<?php
				$this->render_section_header(
					__(
						'Security Activity',
						'eilmo-checkout-flow'
					),
					__(
						'Review checkout attempts blocked by Eilmo security protections and release persistent restrictions when required.',
						'eilmo-checkout-flow'
					)
				);
				?>

				<form
					method="get"
					action="<?php
						echo esc_url(
							admin_url(
								'admin.php'
							)
						);
					?>"
					style="
						display:flex;
						flex-wrap:wrap;
						gap:10px;
						align-items:flex-end;
						margin:18px 0;
					"
				>

					<input
						type="hidden"
						name="page"
						value="<?php
							echo esc_attr(
								self::PAGE_SLUG
							);
						?>"
					>

					<input
						type="hidden"
						name="tab"
						value="<?php
							echo esc_attr(
								self::TAB_ACTIVITY_LOGS
							);
						?>"
					>

					<div>
						<label
							for="eilmo-security-log-protection"
							style="display:block;margin-bottom:4px;"
						>
							<?php
							esc_html_e(
								'Protection',
								'eilmo-checkout-flow'
							);
							?>
						</label>

						<select
							id="eilmo-security-log-protection"
							name="protection_type"
						>
							<option value="">
								<?php
								esc_html_e(
									'All protections',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<?php
							$protection_options =
								array(
									'blacklist_ip' =>
										__(
											'Blacklist IP',
											'eilmo-checkout-flow'
										),

									'blacklist_phone' =>
										__(
											'Blacklist Phone',
											'eilmo-checkout-flow'
										),

									'blacklist_email' =>
										__(
											'Blacklist Email',
											'eilmo-checkout-flow'
										),

									'rate_limit' =>
										__(
											'Rate Limit',
											'eilmo-checkout-flow'
										),

									'duplicate_order' =>
										__(
											'Duplicate Order',
											'eilmo-checkout-flow'
										),

									'honeypot' =>
										__(
											'Honeypot',
											'eilmo-checkout-flow'
										),

									'minimum_checkout_time' =>
										__(
											'Minimum Checkout Time',
											'eilmo-checkout-flow'
										),

									'order_cooldown' =>
										__(
											'Order Cooldown',
											'eilmo-checkout-flow'
										),

									'security_token' =>
										__(
											'Security Token',
											'eilmo-checkout-flow'
										),
								);

							foreach (
								$protection_options as
								$value =>
								$label
							) :
								?>

								<option
									value="<?php
										echo esc_attr(
											$value
										);
									?>"
									<?php
									selected(
										$filters[
											'protection_type'
										],
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

							<?php endforeach; ?>

						</select>
					</div>

					<div>
						<label
							for="eilmo-security-log-source"
							style="display:block;margin-bottom:4px;"
						>
							<?php
							esc_html_e(
								'Source',
								'eilmo-checkout-flow'
							);
							?>
						</label>

						<select
							id="eilmo-security-log-source"
							name="checkout_source"
						>
							<option value="">
								<?php
								esc_html_e(
									'All sources',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<option
								value="eilmo"
								<?php
								selected(
									$filters[
										'checkout_source'
									],
									'eilmo'
								);
								?>
							>
								<?php
								esc_html_e(
									'Checkout Flow',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<option
								value="woocommerce_classic"
								<?php
								selected(
									$filters[
										'checkout_source'
									],
									'woocommerce_classic'
								);
								?>
							>
								<?php
								esc_html_e(
									'WooCommerce Classic',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<option
								value="woocommerce_block"
								<?php
								selected(
									$filters[
										'checkout_source'
									],
									'woocommerce_block'
								);
								?>
							>
								<?php
								esc_html_e(
									'WooCommerce Block',
									'eilmo-checkout-flow'
								);
								?>
							</option>

						</select>
					</div>

					<div>
						<label
							for="eilmo-security-log-status"
							style="display:block;margin-bottom:4px;"
						>
							<?php
							esc_html_e(
								'Status',
								'eilmo-checkout-flow'
							);
							?>
						</label>

						<select
							id="eilmo-security-log-status"
							name="status"
						>
							<option value="">
								<?php
								esc_html_e(
									'All statuses',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<option
								value="blocked"
								<?php
								selected(
									$filters[
										'status'
									],
									'blocked'
								);
								?>
							>
								<?php
								esc_html_e(
									'Blocked',
									'eilmo-checkout-flow'
								);
								?>
							</option>

							<option
								value="resolved"
								<?php
								selected(
									$filters[
										'status'
									],
									'resolved'
								);
								?>
							>
								<?php
								esc_html_e(
									'Resolved',
									'eilmo-checkout-flow'
								);
								?>
							</option>

						</select>
					</div>

					<div>
						<label
							for="eilmo-security-log-search"
							style="display:block;margin-bottom:4px;"
						>
							<?php
							esc_html_e(
								'Search',
								'eilmo-checkout-flow'
							);
							?>
						</label>

						<input
							type="search"
							id="eilmo-security-log-search"
							name="log_search"
							value="<?php
								echo esc_attr(
									$filters[
										'search'
									]
								);
							?>"
							placeholder="<?php
								esc_attr_e(
									'Identifier or reason',
									'eilmo-checkout-flow'
								);
							?>"
						>
					</div>

					<div>

						<button
							type="submit"
							class="button button-secondary"
						>
							<?php
							esc_html_e(
								'Filter',
								'eilmo-checkout-flow'
							);
							?>
						</button>

						<a
							class="button"
							href="<?php
								echo esc_url(
									add_query_arg(
										array(
											'page' =>
												self::PAGE_SLUG,

											'tab' =>
												self::TAB_ACTIVITY_LOGS,
										),
										admin_url(
											'admin.php'
										)
									)
								);
							?>"
						>
							<?php
							esc_html_e(
								'Reset',
								'eilmo-checkout-flow'
							);
							?>
						</a>

					</div>

				</form>

				<p class="description">
					<?php
					printf(
						/* translators: %d: number of security activity records. */
						esc_html__(
							'%d security activity record(s) found.',
								'eilmo-checkout-flow'
							),
							absint( $total )
					);
					?>
				</p>

				<?php if ( empty( $logs ) ) : ?>

					<p>
						<?php
						esc_html_e(
							'No security activity matches the selected filters.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

				<?php else : ?>

					<div style="overflow-x:auto;">

						<table class="widefat striped">

							<thead>
								<tr>
									<th>
										<?php
										esc_html_e(
											'Date',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Protection',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Source',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Identifier',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Reason',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Status',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Order',
											'eilmo-checkout-flow'
										);
										?>
									</th>

									<th>
										<?php
										esc_html_e(
											'Action',
											'eilmo-checkout-flow'
										);
										?>
									</th>
								</tr>
							</thead>

							<tbody>

								<?php foreach ( $logs as $log ) : ?>

									<tr>

										<td>
											<?php
											echo esc_html(
												$this->format_date(
													(string) (
														$log[
															'created_at'
														] ??
														''
													)
												)
											);
											?>
										</td>

										<td>
											<strong>
												<?php
												echo esc_html(
													$this->get_protection_label(
														(string) (
															$log[
																'protection_type'
															] ??
															''
														)
													)
												);
												?>
											</strong>
										</td>

										<td>
											<?php
											echo esc_html(
												$this->get_checkout_source_label(
													(string) (
														$log[
															'checkout_source'
														] ??
														''
													)
												)
											);
											?>
										</td>

										<td>
											<?php
											$identifier =
												trim(
													(string) (
														$log[
															'identifier_masked'
														] ??
														''
													)
												);

											if (
												'' !==
												$identifier
											) :
												?>

												<code>
													<?php
													echo esc_html(
														$identifier
													);
													?>
												</code>

											<?php else : ?>

												—

											<?php endif; ?>
										</td>

										<td>
											<?php
											$message =
												trim(
													(string) (
														$log[
															'message'
														] ??
														''
													)
												);

											$reason_code =
												trim(
													(string) (
														$log[
															'reason_code'
														] ??
														''
													)
												);

											echo esc_html(
												'' !== $message
													? $message
													: '—'
											);
											?>

											<?php if ( '' !== $reason_code ) : ?>

												<br>

												<code>
													<?php
													echo esc_html(
														$reason_code
													);
													?>
												</code>

											<?php endif; ?>
										</td>

										<td>
											<?php
											$status =
												sanitize_key(
													(string) (
														$log[
															'status'
														] ??
														''
													)
												);

											$target_type =
												sanitize_key(
													(string) (
														$log[
															'target_type'
														] ??
														''
													)
												);

											$is_persistent =
												in_array(
													$target_type,
													array(
														'blacklist',
														'rate_limit',
														'duplicate_order',
													),
													true
												);
											?>

											<?php if ( 'resolved' === $status ) : ?>

												<strong>
													<?php
													esc_html_e(
														'Resolved',
														'eilmo-checkout-flow'
													);
													?>
												</strong>

												<?php
												$resolved_at =
													(string) (
														$log[
															'resolved_at'
														] ??
														''
													);

												if (
													'' !==
													$resolved_at
												) :
													?>

													<br>

													<small>
														<?php
														echo esc_html(
															$this->format_date(
																$resolved_at
															)
														);
														?>
													</small>

												<?php endif; ?>

											<?php elseif ( $is_persistent ) : ?>

												<strong>
													<?php
													esc_html_e(
														'Blocked',
														'eilmo-checkout-flow'
													);
													?>
												</strong>

											<?php else : ?>

												<strong>
													<?php
													esc_html_e(
														'Event',
														'eilmo-checkout-flow'
													);
													?>
												</strong>

											<?php endif; ?>
										</td>

										<td>
											<?php
											$this->render_activity_order(
												absint(
													$log[
														'order_id'
													] ??
													0
												)
											);
											?>
										</td>

										<td>
											<?php
											$this->render_activity_action(
												$log
											);
											?>
										</td>

									</tr>

								<?php endforeach; ?>

							</tbody>

						</table>

					</div>

					<?php
					$this->render_activity_pagination(
						$current_page,
						$total_pages,
						$filters
					);
					?>

				<?php endif; ?>

			</section>

		</div>
		<?php
	}

	/**
	 * Render Activity Log order link.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	private function render_activity_order(
		int $order_id
	): void {

		$order_id =
			absint(
				$order_id
			);

		if ( $order_id <= 0 ) {
			echo '—';
			return;
		}

		$url = '';

		if (
			function_exists(
				'wc_get_order'
			)
		) {
			$order =
				wc_get_order(
					$order_id
				);

			if (
				$order &&
				is_callable(
					array(
						$order,
						'get_edit_order_url',
					)
				)
			) {
				$url =
					(string) $order
						->get_edit_order_url();
			}
		}

		if ( '' === $url ) {
			echo esc_html(
				'#' .
				(string) $order_id
			);

			return;
		}

		?>
		<a href="<?php echo esc_url( $url ); ?>">
			<?php
			echo esc_html(
				'#' .
				(string) $order_id
			);
			?>
		</a>
		<?php
	}

	/**
	 * Render Activity Log action.
	 *
	 * Only persistent restrictions receive an admin
	 * release action:
	 *
	 * - Blacklist.
	 * - Rate Limit.
	 * - Duplicate Order.
	 *
	 * Honeypot, Security Token, Minimum Checkout Time
	 * and Order Cooldown are event-only/transient logs
	 * and intentionally render no action.
	 *
	 * @param array<string,mixed> $log Log.
	 *
	 * @return void
	 */
	private function render_activity_action(
		array $log
	): void {

		$status =
			sanitize_key(
				(string) (
					$log[
						'status'
					] ??
					''
				)
			);

		if ( 'resolved' === $status ) {
			echo '—';
			return;
		}

		$target_type =
			sanitize_key(
				(string) (
					$log[
						'target_type'
					] ??
					''
				)
			);

		$target_id =
			absint(
				$log[
					'target_id'
				] ??
				0
			);

		$target_hash =
			$this->normalize_hash(
				(string) (
					$log[
						'target_hash'
					] ??
					''
				)
			);

		$button_label = '';

		switch ( $target_type ) {

			case 'blacklist':

				if ( $target_id <= 0 ) {
					echo '—';
					return;
				}

				$button_label =
					__(
						'Unblock',
						'eilmo-checkout-flow'
					);

				break;

			case 'rate_limit':

				if ( '' === $target_hash ) {
					echo '—';
					return;
				}

				$button_label =
					__(
						'Reset',
						'eilmo-checkout-flow'
					);

				break;

			case 'duplicate_order':

				if ( '' === $target_hash ) {
					echo '—';
					return;
				}

				$button_label =
					__(
						'Release',
						'eilmo-checkout-flow'
					);

				break;

			default:

				echo '—';
				return;
		}

		$log_id =
			absint(
				$log[
					'id'
				] ??
				0
			);

		if ( $log_id <= 0 ) {
			echo '—';
			return;
		}

		?>
		<form
			method="post"
			action="<?php
				echo esc_url(
					admin_url(
						'admin-post.php'
					)
				);
			?>"
		>

			<input
				type="hidden"
				name="action"
				value="eilmo_cf_security_log_unblock"
			>

			<input
				type="hidden"
				name="log_id"
				value="<?php
					echo esc_attr(
						(string) $log_id
					);
				?>"
			>

			<?php
			wp_nonce_field(
				'eilmo_cf_security_log_unblock_' .
					$log_id
			);
			?>

			<button
				type="submit"
				class="button button-secondary"
			>
				<?php
				echo esc_html(
					$button_label
				);
				?>
			</button>

		</form>
		<?php
	}

	/**
	 * Render Activity Log pagination.
	 *
	 * @param int                 $current_page Current page.
	 * @param int                 $total_pages  Total pages.
	 * @param array<string,mixed> $filters      Filters.
	 *
	 * @return void
	 */
	private function render_activity_pagination(
		int $current_page,
		int $total_pages,
		array $filters
	): void {

		if ( $total_pages <= 1 ) {
			return;
		}

		$current_page =
			max(
				1,
				$current_page
			);

		$total_pages =
			max(
				1,
				$total_pages
			);

		$base_args =
			array(
				'page' =>
					self::PAGE_SLUG,

				'tab' =>
					self::TAB_ACTIVITY_LOGS,
			);

		if (
			'' !==
			$filters[
				'protection_type'
			]
		) {
			$base_args[
				'protection_type'
			] =
				$filters[
					'protection_type'
				];
		}

		if (
			'' !==
			$filters[
				'checkout_source'
			]
		) {
			$base_args[
				'checkout_source'
			] =
				$filters[
					'checkout_source'
				];
		}

		if (
			'' !==
			$filters[
				'status'
			]
		) {
			$base_args[
				'status'
			] =
				$filters[
					'status'
				];
		}

		if (
			'' !==
			$filters[
				'search'
			]
		) {
			$base_args[
				'log_search'
			] =
				$filters[
					'search'
				];
		}

		?>
		<div
			class="tablenav"
			style="margin-top:16px;"
		>
			<div class="tablenav-pages">

				<span class="displaying-num">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages. */
						esc_html__(
							'Page %1$d of %2$d',
								'eilmo-checkout-flow'
							),
							absint( $current_page ),
							absint( $total_pages )
					);
					?>
				</span>

				<span class="pagination-links">

					<?php if ( $current_page > 1 ) : ?>

						<?php
						$previous_args =
							$base_args;

						$previous_args[
							'log_page'
						] =
							$current_page - 1;
						?>

						<a
							class="button"
							href="<?php
								echo esc_url(
									add_query_arg(
										$previous_args,
										admin_url(
											'admin.php'
										)
									)
								);
							?>"
						>
							<?php
							esc_html_e(
								'Previous',
								'eilmo-checkout-flow'
							);
							?>
						</a>

					<?php endif; ?>

					<?php if ( $current_page < $total_pages ) : ?>

						<?php
						$next_args =
							$base_args;

						$next_args[
							'log_page'
						] =
							$current_page + 1;
						?>

						<a
							class="button"
							href="<?php
								echo esc_url(
									add_query_arg(
										$next_args,
										admin_url(
											'admin.php'
										)
									)
								);
							?>"
						>
							<?php
							esc_html_e(
								'Next',
								'eilmo-checkout-flow'
							);
							?>
						</a>

					<?php endif; ?>

				</span>

			</div>
		</div>
		<?php
	}

	/**
	 * Get Activity Log filters.
	 *
	 * @return array<string,mixed>
	 */
	private function get_activity_log_filters(): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
		$protection_type =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'protection_type'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'protection_type'
						]
					)
				)
				: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
		$checkout_source =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'checkout_source'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'checkout_source'
						]
					)
				)
				: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
		$status =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'status'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'status'
						]
					)
				)
				: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
		$search =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'log_search'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering.
				? sanitize_text_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'log_search'
						]
					)
				)
				: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'log_page'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
				? max(
					1,
					absint(
						wp_unslash(
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
							$_GET[
								'log_page'
							]
						)
					)
				)
				: 1;

		return array(
			'protection_type' =>
				$protection_type,

			'checkout_source' =>
				$checkout_source,

			'status' =>
				$status,

			'search' =>
				$search,

			'page' =>
				$page,
		);
	}

	/**
	 * Render Activity Log action notice.
	 *
	 * @return void
	 */
	private function render_security_log_notice(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
		$notice =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'eilmo_security_notice'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'eilmo_security_notice'
						]
					)
				)
				: '';

		if ( '' === $notice ) {
			return;
		}

		$type =
			'success';

		switch ( $notice ) {

			case 'unblocked':

				$message =
					__(
						'The security restriction was released successfully.',
						'eilmo-checkout-flow'
					);

				break;

			case 'already_resolved':

				$type =
					'warning';

				$message =
					__(
						'This security activity has already been resolved.',
						'eilmo-checkout-flow'
					);

				break;

			case 'not_unblockable':

				$type =
					'warning';

				$message =
					__(
						'This activity does not have a persistent restriction to release.',
						'eilmo-checkout-flow'
					);

				break;

			case 'unblocked_log_update_failed':

				$type =
					'warning';

				$message =
					__(
						'The restriction was released, but the Activity Log status could not be updated.',
						'eilmo-checkout-flow'
					);

				break;

			case 'invalid_log':

				$type =
					'error';

				$message =
					__(
						'The selected Security Activity Log is invalid.',
						'eilmo-checkout-flow'
					);

				break;

			case 'log_not_found':

				$type =
					'error';

				$message =
					__(
						'The selected Security Activity Log could not be found.',
						'eilmo-checkout-flow'
					);

				break;

			case 'unblock_failed':
			default:

				$type =
					'error';

				$message =
					__(
						'The security restriction could not be released.',
						'eilmo-checkout-flow'
					);

				break;
		}

		?>
		<div
			class="notice notice-<?php
				echo esc_attr(
					$type
				);
			?> is-dismissible"
		>
			<p>
				<?php
				echo esc_html(
					$message
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get Security protection label.
	 *
	 * @param string $type Protection type.
	 *
	 * @return string
	 */
	private function get_protection_label(
		string $type
	): string {

		switch ( $type ) {

			case 'blacklist_ip':

				return __(
					'Blacklist IP',
					'eilmo-checkout-flow'
				);

			case 'blacklist_phone':

				return __(
					'Blacklist Phone',
					'eilmo-checkout-flow'
				);

			case 'blacklist_email':

				return __(
					'Blacklist Email',
					'eilmo-checkout-flow'
				);

			case 'rate_limit':

				return __(
					'Rate Limit',
					'eilmo-checkout-flow'
				);

			case 'duplicate_order':

				return __(
					'Duplicate Order',
					'eilmo-checkout-flow'
				);

			case 'honeypot':

				return __(
					'Honeypot',
					'eilmo-checkout-flow'
				);

			case 'minimum_checkout_time':

				return __(
					'Minimum Checkout Time',
					'eilmo-checkout-flow'
				);

			case 'security_token':

				return __(
					'Security Token',
					'eilmo-checkout-flow'
				);

			case 'order_cooldown':

				return __(
					'Order Cooldown',
					'eilmo-checkout-flow'
				);

			default:

				return __(
					'Security',
					'eilmo-checkout-flow'
				);
		}
	}

	/**
	 * Get checkout source label.
	 *
	 * @param string $source Source.
	 *
	 * @return string
	 */
	private function get_checkout_source_label(
		string $source
	): string {

		switch ( $source ) {

			case 'eilmo':

				return __(
					'Checkout Flow',
					'eilmo-checkout-flow'
				);

			case 'woocommerce_classic':

				return __(
					'WooCommerce Classic',
					'eilmo-checkout-flow'
				);

			case 'woocommerce_block':

				return __(
					'WooCommerce Block',
					'eilmo-checkout-flow'
				);

			default:

				return __(
					'Unknown',
					'eilmo-checkout-flow'
				);
		}
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
	 * Uses the same standard Eilmo admin switch markup
	 * as the main Settings and Meta Tracking pages.
	 *
	 * Hidden input ensures "no" is submitted when the
	 * checkbox is unchecked. When checked, the checkbox
	 * submits the configured checked value.
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
			name="<?php echo esc_attr( $name ); ?>"
			value="no"
		>

		<label class="eilmo-cf-admin-switch">

			<input
				type="checkbox"
				name="<?php echo esc_attr( $name ); ?>"
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
				aria-hidden="true"
			></span>

		</label>

		<?php
	}

	/**
	 * Get current Security tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		$tab =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'tab'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'tab'
						]
					)
				)
				: self::TAB_PROTECTION;

		if (
			in_array(
				$tab,
				array(
					self::TAB_PROTECTION,
					self::TAB_BLACKLIST,
					self::TAB_ACTIVITY_LOGS,
				),
				true
			)
		) {
			return $tab;
		}

		return self::TAB_PROTECTION;
	}

	/**
	 * Get nested array.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param string              $key   Key.
	 *
	 * @return array<string,mixed>
	 */
	private function get_array(
		array $input,
		string $key
	): array {

		if (
			! isset(
				$input[
					$key
				]
			) ||
			! is_array(
				$input[
					$key
				]
			)
		) {
			return array();
		}

		return $input[
			$key
		];
	}

	/**
	 * Render Blacklist action notice.
	 *
	 * @return void
	 */
	private function render_blacklist_notice(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
		$notice =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'blacklist_notice'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'blacklist_notice'
						]
					)
				)
				: '';

		if ( '' === $notice ) {
			return;
		}

		$type =
			'success';

		switch ( $notice ) {

			case 'added':

				$message =
					__(
						'Blacklist entry added successfully.',
						'eilmo-checkout-flow'
					);

				break;

			case 'removed':

				$message =
					__(
						'Blacklist entry removed successfully.',
						'eilmo-checkout-flow'
					);

				break;

			case 'invalid_type':

				$type =
					'error';

				$message =
					__(
						'Please select a valid blacklist type.',
						'eilmo-checkout-flow'
					);

				break;

			case 'invalid_value':

				$type =
					'error';

				$message =
					__(
						'Please enter a valid value for the selected blacklist type.',
						'eilmo-checkout-flow'
					);

				break;

			case 'already_exists':

				$type =
					'warning';

				$message =
					__(
						'This customer identifier is already blacklisted.',
						'eilmo-checkout-flow'
					);

				break;

			case 'remove_failed':

				$type =
					'error';

				$message =
					__(
						'The blacklist entry could not be removed.',
						'eilmo-checkout-flow'
					);

				break;

			case 'add_failed':
			default:

				$type =
					'error';

				$message =
					__(
						'The blacklist entry could not be added.',
						'eilmo-checkout-flow'
					);

				break;
		}

		?>
		<div
			class="notice notice-<?php
				echo esc_attr(
					$type
				);
			?> is-dismissible"
		>
			<p>
				<?php
				echo esc_html(
					$message
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get Blacklist type label.
	 *
	 * @param string $type Type.
	 *
	 * @return string
	 */
	private function get_blacklist_type_label(
		string $type
	): string {

		switch ( $type ) {

			case 'ip':

				return __(
					'IP Address',
					'eilmo-checkout-flow'
				);

			case 'phone':

				return __(
					'Phone Number',
					'eilmo-checkout-flow'
				);

			case 'email':

				return __(
					'Email Address',
					'eilmo-checkout-flow'
				);

			default:

				return __(
					'Unknown',
					'eilmo-checkout-flow'
				);
		}
	}

	/**
	 * Format UTC database date for admin display.
	 *
	 * @param string $date Date.
	 *
	 * @return string
	 */
	private function format_date(
		string $date
	): string {

		if (
			'' === $date ||
			'0000-00-00 00:00:00' === $date
		) {
			return '—';
		}

		$local_date =
			get_date_from_gmt(
				$date,
				'Y-m-d H:i'
			);

		return '' !== $local_date
			? $local_date
			: $date;
	}

	/**
	 * Normalize SHA-256/HMAC hash.
	 *
	 * @param string $hash Hash.
	 *
	 * @return string
	 */
	private function normalize_hash(
		string $hash
	): string {

		$hash =
			strtolower(
				trim(
					$hash
				)
			);

		if (
			64 !==
				strlen(
					$hash
				) ||
			1 !==
				preg_match(
					'/^[a-f0-9]{64}$/',
					$hash
				)
		) {
			return '';
		}

		return $hash;
	}
}
