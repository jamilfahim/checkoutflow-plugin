<?php
/**
 * Meta Tracking settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\MetaTrackingSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Meta Tracking settings.
 */
final class MetaTrackingSettingsPage {

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-meta-tracking';

	/**
	 * Render settings page.
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
			MetaTrackingSettings::get_settings();

		$features =
			$this->get_array(
				$settings,
				'features'
			);

		$connection =
			$this->get_array(
				$settings,
				'connection'
			);

		$events =
			$this->get_array(
				$settings,
				'events'
			);

		$purchase_tracking =
			$this->get_array(
				$settings,
				'purchase_tracking'
			);

		$advanced =
			$this->get_array(
				$settings,
				'advanced'
			);

		if ( ! $embedded ) {
			?>
			<div class="wrap eilmo-cf-admin">

				<div class="eilmo-cf-admin__header">

					<div class="eilmo-cf-admin__heading">

					<h1>
						<?php
						esc_html_e(
							'Meta Tracking Settings',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Configure Meta Pixel and server-side Conversions API tracking across WooCommerce.',
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
				id="eilmo-cf-meta-settings-form"
			>

				<?php
				settings_fields(
					MetaTrackingSettings::OPTION_GROUP
				);

				$this->render_features(
					$features
				);

				$this->render_connection(
					$connection
				);

				$this->render_events(
					$events
				);

				$this->render_purchase_tracking(
					$purchase_tracking,
					$features,
					$connection,
					$events
				);

				$this->render_advanced(
					$advanced
				);

				submit_button(
					__(
						'Save Meta Tracking Settings',
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

		$this->render_purchase_tracking_script();
	}

	/**
	 * Render tracking channel settings.
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
					'Tracking Channels',
					'eilmo-checkout-flow'
				),
				__(
					'Use Browser Pixel, server-side Conversions API, or both. The main Meta Tracking switch is in Checkout Settings → General → Optional Features.',
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
								'Browser Pixel',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								MetaTrackingSettings::OPTION_NAME .
									'[features][browser_pixel]',
								'yes',
								(string) (
									$settings[
										'browser_pixel'
									] ??
										'yes'
								),
								'eilmo-cf-meta-browser-pixel'
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Send supported browser events through the Meta Pixel.',
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
								'Server-Side Conversions API',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								MetaTrackingSettings::OPTION_NAME .
									'[features][conversions_api]',
								'yes',
								(string) (
									$settings[
										'conversions_api'
									] ??
										'yes'
								),
								'eilmo-cf-meta-conversions-api'
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Send eligible events directly from WordPress to Meta. The access token stays server-side.',
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
	 * Render connection settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_connection(
		array $settings
	): void {

		$has_token =
			'' !==
				trim(
					(string) (
						$settings[
							'access_token'
						] ??
							''
					)
				);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Meta Connection',
					'eilmo-checkout-flow'
				),
				__(
					'Connect the Meta Pixel or Dataset used by Events Manager.',
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

							<label for="eilmo-cf-meta-pixel-id">
								<?php
								esc_html_e(
									'Pixel / Dataset ID',
									'eilmo-checkout-flow'
								);
								?>
							</label>

						</th>

						<td>

							<input
								type="text"
								inputmode="numeric"
								id="eilmo-cf-meta-pixel-id"
								class="regular-text"
								name="<?php
								echo esc_attr(
									MetaTrackingSettings::OPTION_NAME .
										'[connection][pixel_id]'
								);
								?>"
								value="<?php
								echo esc_attr(
									(string) (
										$settings[
											'pixel_id'
										] ??
											''
									)
								);
								?>"
								autocomplete="off"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Enter the Meta Pixel or Dataset ID used for browser and server-side events.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

					<tr>

						<th scope="row">

							<label for="eilmo-cf-meta-access-token">
								<?php
								esc_html_e(
									'Conversions API Access Token',
									'eilmo-checkout-flow'
								);
								?>
							</label>

						</th>

						<td>

							<input
								type="password"
								id="eilmo-cf-meta-access-token"
								class="regular-text"
								name="<?php
								echo esc_attr(
									MetaTrackingSettings::OPTION_NAME .
										'[connection][access_token]'
								);
								?>"
								value=""
								autocomplete="new-password"
								data-eilmo-has-saved-token="<?php
								echo esc_attr(
									$has_token
										? 'yes'
										: 'no'
								);
								?>"
								placeholder="<?php
								echo esc_attr(
									$has_token
										? __(
											'Saved — leave blank to keep current token',
											'eilmo-checkout-flow'
										)
										: __(
											'Paste access token',
											'eilmo-checkout-flow'
										)
								);
								?>"
							>

							<p class="description">
								<?php
								esc_html_e(
									'The saved token is never printed back into the page or exposed to frontend JavaScript.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

					<tr>

						<th scope="row">

							<label for="eilmo-cf-meta-test-event-code">
								<?php
								esc_html_e(
									'Test Event Code',
									'eilmo-checkout-flow'
								);
								?>
							</label>

						</th>

						<td>

							<input
								type="text"
								id="eilmo-cf-meta-test-event-code"
								class="regular-text"
								name="<?php
								echo esc_attr(
									MetaTrackingSettings::OPTION_NAME .
										'[connection][test_event_code]'
								);
								?>"
								value="<?php
								echo esc_attr(
									(string) (
										$settings[
											'test_event_code'
										] ??
											''
									)
								);
								?>"
								autocomplete="off"
							>

							<p class="description">
								<?php
								esc_html_e(
									'Optional. Use the code from Meta Events Manager while testing, then clear it before production tracking.',
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
	 * Render event switches.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_events(
		array $settings
	): void {

		$events =
			array(
				'page_view' =>
					array(
						'label' =>
							__(
								'PageView',
								'eilmo-checkout-flow'
							),

						'description' =>
							__(
								'Track page views across the website using the Browser Pixel.',
								'eilmo-checkout-flow'
							),
					),

				'view_content' =>
					array(
						'label' =>
							__(
								'ViewContent',
								'eilmo-checkout-flow'
							),

						'description' =>
							__(
								'Track WooCommerce product views through Browser Pixel and Conversions API.',
								'eilmo-checkout-flow'
							),
					),

				'add_to_cart' =>
					array(
						'label' =>
							__(
								'AddToCart',
								'eilmo-checkout-flow'
							),

						'description' =>
							__(
								'Track products added to cart from Eilmo and supported WooCommerce storefront flows.',
								'eilmo-checkout-flow'
							),
					),

				'initiate_checkout' =>
					array(
						'label' =>
							__(
								'InitiateCheckout',
								'eilmo-checkout-flow'
							),

						'description' =>
							__(
								'Track customers who begin the checkout process.',
								'eilmo-checkout-flow'
							),
					),

				'purchase' =>
					array(
						'label' =>
							__(
								'Purchase',
								'eilmo-checkout-flow'
							),

						'description' =>
							__(
								'Track eligible WooCommerce purchases with duplicate protection.',
								'eilmo-checkout-flow'
							),
					),
			);

		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Events',
					'eilmo-checkout-flow'
				),
				__(
					'Choose which Meta ecommerce events Eilmo may send.',
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
						$events as
							$key =>
							$event
					) :
						?>

						<tr>

							<th scope="row">
								<?php
								echo esc_html(
									(string) (
										$event[
											'label'
										] ??
											''
									)
								);
								?>
							</th>

							<td>

								<?php
								$switch_id =
									'purchase' === $key
										? 'eilmo-cf-meta-purchase-event'
										: '';

								$this->render_switch(
									MetaTrackingSettings::OPTION_NAME .
										'[events][' .
										$key .
										']',
									'yes',
									(string) (
										$settings[
											$key
										] ??
											'yes'
									),
									$switch_id
								);
								?>

								<?php
								if (
									! empty(
										$event[
											'description'
										]
									)
								) :
									?>

									<p class="description">
										<?php
										echo esc_html(
											(string) $event[
												'description'
											]
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
		<?php
	}

	/**
	 * Render Purchase tracking settings.
	 *
	 * @param array<string,mixed> $settings   Purchase settings.
	 * @param array<string,mixed> $features   Feature settings.
	 * @param array<string,mixed> $connection Connection settings.
	 * @param array<string,mixed> $events     Event settings.
	 *
	 * @return void
	 */
	private function render_purchase_tracking(
		array $settings,
		array $features,
		array $connection,
		array $events
	): void {

		$strategy =
			sanitize_key(
				(string) (
					$settings[
						'strategy'
					] ??
						MetaTrackingSettings::
							PURCHASE_STRATEGY_BASIC
				)
			);

		if (
			MetaTrackingSettings::
				PURCHASE_STRATEGY_ADVANCED !==
			$strategy
		) {
			$strategy =
				MetaTrackingSettings::
					PURCHASE_STRATEGY_BASIC;
		}

		/*
		 * Use the runtime-normalized status list instead of
		 * directly trusting stored option data.
		 *
		 * This automatically removes old invalid values such
		 * as cancelled, failed, refunded or checkout-draft.
		 */
		$selected_statuses =
			MetaTrackingSettings::
				get_purchase_trigger_statuses();

		$status_options =
			MetaTrackingSettings::
				get_order_status_options();

		$purchase_enabled =
			'yes' ===
				(
					$events[
						'purchase'
					] ??
						'yes'
				);

		$capi_enabled =
			'yes' ===
				(
					$features[
						'conversions_api'
					] ??
						'no'
				);

		$pixel_id =
			preg_replace(
				'/\D+/',
				'',
				(string) (
					$connection[
						'pixel_id'
					] ??
						''
				)
			);

		if ( ! is_string( $pixel_id ) ) {
			$pixel_id =
				'';
		}

		$has_token =
			'' !==
				trim(
					(string) (
						$connection[
							'access_token'
						] ??
							''
					)
				);

		$is_advanced =
			MetaTrackingSettings::
				PURCHASE_STRATEGY_ADVANCED ===
			$strategy;

		/*
		 * The central settings helper remains the source of
		 * truth for saved Advanced Purchase readiness.
		 */
		$advanced_ready =
			MetaTrackingSettings::
				advanced_purchase_is_ready();

		/*
		 * Defensive local calculation keeps the initial UI
		 * correct even if this renderer is reused with a
		 * filtered settings array.
		 */
		if (
			! $purchase_enabled ||
			! $capi_enabled ||
			'' === $pixel_id ||
			! $has_token ||
			empty( $selected_statuses )
		) {
			$advanced_ready =
				false;
		}

		?>
		<section
			class="eilmo-cf-settings-section"
			id="eilmo-cf-meta-purchase-tracking"
		>

			<?php
			$this->render_section_header(
				__(
					'Purchase Tracking',
					'eilmo-checkout-flow'
				),
				__(
					'Choose when Eilmo should count a WooCommerce order as a Meta Purchase conversion.',
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
								'Purchase Strategy',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<fieldset>

								<label>

									<input
										type="radio"
										name="<?php
										echo esc_attr(
											MetaTrackingSettings::OPTION_NAME .
												'[purchase_tracking][strategy]'
										);
										?>"
										value="<?php
										echo esc_attr(
											MetaTrackingSettings::
												PURCHASE_STRATEGY_BASIC
										);
										?>"
										<?php
										checked(
											$strategy,
											MetaTrackingSettings::
												PURCHASE_STRATEGY_BASIC
										);
										?>
										data-eilmo-meta-purchase-strategy
									>

									<strong>
										<?php
										esc_html_e(
											'Basic — Order Placed',
											'eilmo-checkout-flow'
										);
										?>
									</strong>

								</label>

								<p
									class="description"
									style="margin: 4px 0 16px 24px;"
								>
									<?php
									esc_html_e(
										'Use the normal successful checkout flow. Browser Purchase may fire on the Thank You page and server-side Purchase uses the same stable event ID for deduplication.',
										'eilmo-checkout-flow'
									);
									?>
								</p>

								<label>

									<input
										type="radio"
										name="<?php
										echo esc_attr(
											MetaTrackingSettings::OPTION_NAME .
												'[purchase_tracking][strategy]'
										);
										?>"
										value="<?php
										echo esc_attr(
											MetaTrackingSettings::
												PURCHASE_STRATEGY_ADVANCED
										);
										?>"
										<?php
										checked(
											$strategy,
											MetaTrackingSettings::
												PURCHASE_STRATEGY_ADVANCED
										);
										?>
										data-eilmo-meta-purchase-strategy
									>

									<strong>
										<?php
										esc_html_e(
											'Advanced — Order Status',
											'eilmo-checkout-flow'
										);
										?>
									</strong>

								</label>

								<p
									class="description"
									style="margin: 4px 0 0 24px;"
								>
									<?php
									esc_html_e(
										'Send Purchase only after the order reaches one of the selected WooCommerce statuses. Recommended for COD, confirmed-order and fulfillment-based conversion tracking.',
										'eilmo-checkout-flow'
									);
									?>
								</p>

							</fieldset>

						</td>

					</tr>

					<tr
						id="eilmo-cf-meta-purchase-status-row"
						<?php
						if ( ! $is_advanced ) :
							?>
							hidden
							<?php
						endif;
						?>
					>

						<th scope="row">
							<?php
							esc_html_e(
								'Purchase Trigger Status',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<fieldset>

								<legend class="screen-reader-text">
									<?php
									esc_html_e(
										'Purchase Trigger Status',
										'eilmo-checkout-flow'
									);
									?>
								</legend>

								<input
									type="hidden"
									name="<?php
									echo esc_attr(
										MetaTrackingSettings::OPTION_NAME .
											'[purchase_tracking][trigger_statuses][]'
									);
									?>"
									value=""
								>

								<?php
								foreach (
									$status_options as
										$status =>
										$label
								) :

									$is_completed =
										'completed' ===
										$status;
									?>

									<label
										style="
											display: block;
											margin-bottom: 10px;
										"
									>

										<input
											type="checkbox"
											name="<?php
											echo esc_attr(
												MetaTrackingSettings::OPTION_NAME .
													'[purchase_tracking][trigger_statuses][]'
											);
											?>"
											value="<?php
											echo esc_attr(
												$status
											);
											?>"
											<?php
											checked(
												in_array(
													$status,
													$selected_statuses,
													true
												)
											);
											?>
											data-eilmo-meta-purchase-status
										>

										<span>
											<?php
											echo esc_html(
												(string) $label
											);
											?>
										</span>

										<?php
										if ( $is_completed ) :
											?>

											<strong>
												<?php
												esc_html_e(
													'— Recommended for COD',
													'eilmo-checkout-flow'
												);
												?>
											</strong>

										<?php endif; ?>

									</label>

								<?php endforeach; ?>

							</fieldset>

							<p class="description">
								<?php
								esc_html_e(
									'Purchase is sent only once per order. If multiple statuses are selected, the first matching status triggers the conversion.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

							<p class="description">
								<?php
								esc_html_e(
									'Cancelled, failed, refunded and other invalid terminal statuses are excluded automatically.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

							<p class="description">
								<?php
								esc_html_e(
									'Example: if Processing and Completed are selected, Purchase fires when the order first reaches Processing and does not fire again when it later becomes Completed.',
									'eilmo-checkout-flow'
								);
								?>
							</p>

						</td>

					</tr>

					<tr
						id="eilmo-cf-meta-purchase-advanced-info"
						<?php
						if ( ! $is_advanced ) :
							?>
							hidden
							<?php
						endif;
						?>
					>

						<th scope="row">
							<?php
							esc_html_e(
								'Advanced Requirements',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<div
								id="eilmo-cf-meta-purchase-requirement-note"
								class="notice notice-info inline"
								style="
									margin: 0 0 12px;
									padding: 10px 12px;
								"
							>
								<p style="margin: 0;">
									<?php
									esc_html_e(
										'Advanced Purchase tracking is server-side only. Browser Purchase is not used on the Thank You page because the qualifying order status may be reached later.',
										'eilmo-checkout-flow'
									);
									?>
								</p>
							</div>

							<div
								id="eilmo-cf-meta-purchase-warning"
								class="notice notice-warning inline"
								style="
									margin: 0;
									padding: 10px 12px;
								"
								<?php
								if ( $advanced_ready ) :
									?>
									hidden
									<?php
								endif;
								?>
							>

								<p style="margin: 0;">

									<strong>
										<?php
										esc_html_e(
											'Advanced Purchase tracking is not ready.',
											'eilmo-checkout-flow'
										);
										?>
									</strong>

									<span
										id="eilmo-cf-meta-purchase-warning-text"
									>
										<?php
										esc_html_e(
											' Enable Purchase, Conversions API, configure the Meta connection and select at least one valid Purchase status.',
											'eilmo-checkout-flow'
										);
										?>
									</span>

								</p>

							</div>

							<div
								id="eilmo-cf-meta-purchase-ready"
								class="notice notice-success inline"
								style="
									margin: 0;
									padding: 10px 12px;
								"
								<?php
								if ( ! $advanced_ready ) :
									?>
									hidden
									<?php
								endif;
								?>
							>

								<p style="margin: 0;">

									<strong>
										<?php
										esc_html_e(
											'Advanced Purchase tracking is ready.',
											'eilmo-checkout-flow'
										);
										?>
									</strong>

									<?php
									esc_html_e(
										' Purchase will be sent server-side when an order reaches the first selected qualifying status.',
										'eilmo-checkout-flow'
									);
									?>

								</p>

							</div>

						</td>

					</tr>

				</tbody>
			</table>

		</section>
		<?php
	}

	/**
	 * Render advanced settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_advanced(
		array $settings
	): void {
		?>
		<section class="eilmo-cf-settings-section">

			<?php
			$this->render_section_header(
				__(
					'Advanced',
					'eilmo-checkout-flow'
				),
				__(
					'Development and troubleshooting options.',
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
								'Debug Mode',
								'eilmo-checkout-flow'
							);
							?>
						</th>

						<td>

							<?php
							$this->render_switch(
								MetaTrackingSettings::OPTION_NAME .
									'[advanced][debug_mode]',
								'yes',
								(string) (
									$settings[
										'debug_mode'
									] ??
										'no'
								)
							);
							?>

							<p class="description">
								<?php
								esc_html_e(
									'Write sanitized Meta request results to the WooCommerce logger. Access tokens are never logged.',
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
	 * Render Purchase tracking UI behavior.
	 *
	 * @return void
	 */
	private function render_purchase_tracking_script(): void {
		?>
		<script>
			(function () {
				'use strict';

				const strategyInputs =
					document.querySelectorAll(
						'[data-eilmo-meta-purchase-strategy]'
					);

				const statusInputs =
					document.querySelectorAll(
						'[data-eilmo-meta-purchase-status]'
					);

				const statusRow =
					document.getElementById(
						'eilmo-cf-meta-purchase-status-row'
					);

				const advancedInfo =
					document.getElementById(
						'eilmo-cf-meta-purchase-advanced-info'
					);

				const purchaseInput =
					document.getElementById(
						'eilmo-cf-meta-purchase-event'
					);

				const capiInput =
					document.getElementById(
						'eilmo-cf-meta-conversions-api'
					);

				const pixelInput =
					document.getElementById(
						'eilmo-cf-meta-pixel-id'
					);

				const tokenInput =
					document.getElementById(
						'eilmo-cf-meta-access-token'
					);

				const warning =
					document.getElementById(
						'eilmo-cf-meta-purchase-warning'
					);

				const warningText =
					document.getElementById(
						'eilmo-cf-meta-purchase-warning-text'
					);

				const ready =
					document.getElementById(
						'eilmo-cf-meta-purchase-ready'
					);

				/**
				 * Get selected Purchase strategy.
				 *
				 * @return {string}
				 */
				function getStrategy() {
					const selected =
						document.querySelector(
							'[data-eilmo-meta-purchase-strategy]:checked'
						);

					return selected
						? String(
							selected.value || ''
						)
						: 'basic';
				}

				/**
				 * Determine whether a saved or newly entered
				 * Conversions API token is available.
				 *
				 * @return {boolean}
				 */
				function hasAccessToken() {
					if (!tokenInput) {
						return false;
					}

					const entered =
						String(
							tokenInput.value || ''
						).trim();

					if (entered) {
						return true;
					}

					return (
						String(
							tokenInput.dataset
								.eilmoHasSavedToken ||
								''
						) === 'yes'
					);
				}

				/**
				 * Get current Pixel / Dataset ID.
				 *
				 * @return {string}
				 */
				function getPixelId() {
					if (!pixelInput) {
						return '';
					}

					return String(
						pixelInput.value || ''
					)
						.replace(
							/\D+/g,
							''
						)
						.trim();
				}

				/**
				 * Determine whether at least one valid
				 * Purchase status is selected.
				 *
				 * @return {boolean}
				 */
				function hasTriggerStatus() {
					return Array.from(
						statusInputs
					).some(
						function (input) {
							return Boolean(
								input.checked
							);
						}
					);
				}

				/**
				 * Determine whether Advanced Purchase
				 * currently has all required settings.
				 *
				 * @return {boolean}
				 */
				function advancedIsReady() {
					const purchaseEnabled =
						Boolean(
							purchaseInput &&
							purchaseInput.checked
						);

					const capiEnabled =
						Boolean(
							capiInput &&
							capiInput.checked
						);

					return (
						purchaseEnabled &&
						capiEnabled &&
						Boolean(
							getPixelId()
						) &&
						hasAccessToken() &&
						hasTriggerStatus()
					);
				}

				/**
				 * Build the most useful current warning.
				 *
				 * @return {string}
				 */
				function getWarningMessage() {
					if (
						!purchaseInput ||
						!purchaseInput.checked
					) {
						return (
							' Enable the Purchase event before using Advanced Purchase tracking.'
						);
					}

					if (
						!capiInput ||
						!capiInput.checked
					) {
						return (
							' Enable Server-Side Conversions API before using status-based Purchase tracking.'
						);
					}

					if (!getPixelId()) {
						return (
							' Configure a valid Meta Pixel / Dataset ID.'
						);
					}

					if (!hasAccessToken()) {
						return (
							' Configure a Conversions API Access Token.'
						);
					}

					if (!hasTriggerStatus()) {
						return (
							' Select at least one valid WooCommerce Purchase trigger status.'
						);
					}

					return (
						' Complete the required Advanced Purchase configuration.'
					);
				}

				/**
				 * Refresh Purchase strategy UI.
				 *
				 * @return {void}
				 */
				function refreshPurchaseUi() {
					const advanced =
						'advanced' ===
							getStrategy();

					if (statusRow) {
						statusRow.hidden =
							!advanced;
					}

					if (advancedInfo) {
						advancedInfo.hidden =
							!advanced;
					}

					if (!advanced) {
						return;
					}

					const isReady =
						advancedIsReady();

					if (warning) {
						warning.hidden =
							isReady;
					}

					if (warningText) {
						warningText.textContent =
							isReady
								? ''
								: getWarningMessage();
					}

					if (ready) {
						ready.hidden =
							!isReady;
					}
				}

				strategyInputs.forEach(
					function (input) {
						input.addEventListener(
							'change',
							refreshPurchaseUi
						);
					}
				);

				statusInputs.forEach(
					function (input) {
						input.addEventListener(
							'change',
							refreshPurchaseUi
						);
					}
				);

				if (purchaseInput) {
					purchaseInput.addEventListener(
						'change',
						refreshPurchaseUi
					);
				}

				if (capiInput) {
					capiInput.addEventListener(
						'change',
						refreshPurchaseUi
					);
				}

				if (pixelInput) {
					pixelInput.addEventListener(
						'input',
						refreshPurchaseUi
					);
				}

				if (tokenInput) {
					tokenInput.addEventListener(
						'input',
						refreshPurchaseUi
					);
				}

				refreshPurchaseUi();
			})();
		</script>
		<?php
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
	 * Hidden input is intentionally outside the label.
	 * When the checkbox is unchecked WordPress receives
	 * "no". When checked the later checkbox value "yes"
	 * becomes the saved value.
	 *
	 * @param string $name    Input name.
	 * @param string $value   Checked value.
	 * @param string $current Current value.
	 * @param string $id      Optional checkbox ID.
	 *
	 * @return void
	 */
	private function render_switch(
		string $name,
		string $value,
		string $current,
		string $id = ''
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
				<?php
				if ( '' !== $id ) :
					?>
					id="<?php echo esc_attr( $id ); ?>"
					<?php
				endif;
				?>
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php
				checked(
					$current,
					$value
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
	 * Get array child.
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

		return (
			isset(
				$source[
					$key
				]
			) &&
			is_array(
				$source[
					$key
				]
			)
		)
			? $source[
				$key
			]
			: array();
	}
}
