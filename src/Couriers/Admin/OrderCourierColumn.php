<?php
/**
 * WooCommerce Orders Courier column.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Couriers\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Couriers\Services\CourierBookingService;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Couriers\Services\LocalCustomerHistoryService;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds courier success + editable courier booking to WooCommerce Orders.
 */
final class OrderCourierColumn implements RegistrableInterface {

	/**
	 * Column key.
	 */
	private const COLUMN =
		'eilmo_cf_courier_success';

	/**
	 * Admin nonce action.
	 */
	private const NONCE_ACTION =
		'eilmo_cf_courier_admin';

	/**
	 * Stats AJAX action.
	 */
	private const STATS_ACTION =
		'eilmo_cf_courier_success_batch';

	/** Manual courier-history lookup AJAX action. */
	private const MANUAL_STATS_ACTION =
		'eilmo_cf_courier_success_manual';

	/**
	 * Delivery status AJAX action.
	 */
	private const STATUS_ACTION =
		'eilmo_cf_courier_status_batch';

	/**
	 * Editable form AJAX action.
	 */
	private const FORM_ACTION =
		'eilmo_cf_courier_form';

	/**
	 * Pathao option AJAX action.
	 */
	private const PATHAO_OPTIONS_ACTION =
		'eilmo_cf_courier_pathao_options';

	/**
	 * Send AJAX action.
	 */
	private const SEND_ACTION =
		'eilmo_cf_courier_send';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter(
			'manage_edit-shop_order_columns',
			array(
				$this,
				'add_column',
			),
			30
		);

		add_action(
			'manage_shop_order_posts_custom_column',
			array(
				$this,
				'render_legacy_column',
			),
			30,
			2
		);

		add_filter(
			'manage_woocommerce_page_wc-orders_columns',
			array(
				$this,
				'add_column',
			),
			30
		);

		add_action(
			'manage_woocommerce_page_wc-orders_custom_column',
			array(
				$this,
				'render_hpos_column',
			),
			30,
			2
		);

		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_assets',
			)
		);

		add_action(
			'admin_footer',
			array(
				$this,
				'render_modal',
			),
			30
		);

		add_action(
			'wp_ajax_' .
				self::STATS_ACTION,
			array(
				$this,
				'ajax_stats',
			)
		);

		add_action(
			'wp_ajax_' . self::MANUAL_STATS_ACTION,
			array( $this, 'ajax_manual_stats' )
		);

		add_action(
			'wp_ajax_' .
				self::STATUS_ACTION,
			array(
				$this,
				'ajax_statuses',
			)
		);

		add_action(
			'wp_ajax_' .
				self::FORM_ACTION,
			array(
				$this,
				'ajax_form',
			)
		);

		add_action(
			'wp_ajax_' .
				self::PATHAO_OPTIONS_ACTION,
			array(
				$this,
				'ajax_pathao_options',
			)
		);

		add_action(
			'wp_ajax_' .
				self::SEND_ACTION,
			array(
				$this,
				'ajax_send',
			)
		);
	}

	/**
	 * Add Courier Success column.
	 *
	 * @param array<string,string> $columns Columns.
	 *
	 * @return array<string,string>
	 */
	public function add_column(
		array $columns
	): array {

		if (
			! $this->is_enabled( 'courier_success' ) &&
			! $this->is_enabled( 'courier_one_click' )
		) {
			return $columns;
		}

		$output =
			array();

		$inserted =
			false;

		foreach ( $columns as $key => $label ) {
			$output[ $key ] =
				$label;

			if ( 'order_status' === $key ) {
				$output[ self::COLUMN ] =
					__(
						'Courier Performance',
						'eilmo-checkout-flow'
					);

				$inserted =
					true;
			}
		}

		if ( ! $inserted ) {
			$output[ self::COLUMN ] =
				__(
					'Courier Performance',
					'eilmo-checkout-flow'
				);
		}

		return $output;
	}

	/**
	 * Render legacy order-list column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order ID.
	 *
	 * @return void
	 */
	public function render_legacy_column(
		string $column,
		int $post_id
	): void {

		if ( self::COLUMN !== $column ) {
			return;
		}

		$order =
			wc_get_order(
				$post_id
			);

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->render_cell(
			$order
		);
	}

	/**
	 * Render HPOS order-list column.
	 *
	 * @param string         $column Column.
	 * @param WC_Order|mixed $order  Order.
	 *
	 * @return void
	 */
	public function render_hpos_column(
		string $column,
		$order
	): void {

		if ( self::COLUMN !== $column ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order =
				wc_get_order(
					absint( $order )
				);
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->render_cell(
			$order
		);
	}

	/**
	 * Render courier cell shell.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	private function render_cell(
		WC_Order $order
	): void {

		$order_id =
			$order->get_id();

		$success_service = new CourierSuccessService();

		$phone =
			$success_service->normalize_phone(
				(string) $order->get_billing_phone()
			);

		$snapshot = $success_service->get_saved_snapshot_for_selected_provider( $order );
		$has_courier_data = $success_service->snapshot_has_courier_data( $snapshot );
		$snapshot_stale = $has_courier_data && $success_service->snapshot_is_stale( $snapshot );
		$courier_settings = CourierSettings::get_settings();
		$success_provider = CourierSettings::get_success_provider();
		$live_fraud = isset( $courier_settings['live_fraud'] ) && is_array( $courier_settings['live_fraud'] )
			? $courier_settings['live_fraud']
			: array();
		$manual_check_enabled = 'yes' === ( $live_fraud['manual_order_check'] ?? 'yes' );
		$show_cache_age = 'yes' === ( $live_fraud['show_cache_age'] ?? 'yes' );
		$checked_at = max( 0, absint( $snapshot['checked_at'] ?? 0 ) );

		$booking =
			new CourierBookingService();

		$steadfast =
			$booking->get_booking_status(
				$order,
				'steadfast'
			);

		$pathao =
			$booking->get_booking_status(
				$order,
				'pathao'
			);

		$stats_enabled =
			$this->is_enabled(
				'courier_success'
			);

		$send_enabled =
			$this->is_enabled(
				'courier_one_click'
			);

		?>
		<div
			class="eilmo-cf-courier"
			data-eilmo-courier
			data-order-id="<?php echo esc_attr( (string) $order_id ); ?>"
			data-has-phone="<?php echo esc_attr( '' !== $phone ? 'yes' : 'no' ); ?>"
			data-steadfast-sent="<?php echo esc_attr( ! empty( $steadfast['sent'] ) ? 'yes' : 'no' ); ?>"
			data-steadfast-status="<?php echo esc_attr( (string) ( $steadfast['delivery_status'] ?? '' ) ); ?>"
			data-pathao-sent="<?php echo esc_attr( ! empty( $pathao['sent'] ) ? 'yes' : 'no' ); ?>"
			data-pathao-status="<?php echo esc_attr( (string) ( $pathao['delivery_status'] ?? '' ) ); ?>"
		>
			<?php if ( $stats_enabled ) : ?>
				<div class="eilmo-cf-courier__stats">
					<?php if ( 'bdcourier' === $success_provider ) : ?>
						<div class="eilmo-cf-courier__provider eilmo-cf-courier__provider--combined">
							<strong><?php esc_html_e( 'All Couriers', 'eilmo-checkout-flow' ); ?></strong>
							<span data-eilmo-courier-stat="combined" tabindex="0" aria-label="<?php esc_attr_e( 'Combined BD Courier delivery history. Hover for courier breakdown.', 'eilmo-checkout-flow' ); ?>">
								<?php echo '' !== $phone ? esc_html__( 'Loading…', 'eilmo-checkout-flow' ) : '—'; ?>
							</span>
						</div>
					<?php else : ?>
						<div class="eilmo-cf-courier__provider">
							<strong>Steadfast</strong>
							<span data-eilmo-courier-stat="steadfast">
								<?php echo '' !== $phone ? esc_html__( 'Loading…', 'eilmo-checkout-flow' ) : '—'; ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $stats_enabled && '' === $phone ) : ?>
				<div class="eilmo-cf-courier__notice">
					<?php esc_html_e( 'Edit phone before sending', 'eilmo-checkout-flow' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $stats_enabled && '' !== $phone && $manual_check_enabled ) : ?>
				<div class="eilmo-cf-courier__check" data-eilmo-courier-check-wrap>
					<small data-eilmo-courier-cache-age <?php echo ! ( $show_cache_age && $has_courier_data && $checked_at > 0 ) ? 'hidden' : ''; ?>>
						<?php
						if ( $show_cache_age && $has_courier_data && $checked_at > 0 ) {
							echo esc_html(
								sprintf(
									/* translators: %s human-readable time difference. */
									__( 'Checked %s ago', 'eilmo-checkout-flow' ),
									human_time_diff( $checked_at, time() )
								)
							);
						}
						?>
					</small>
					<button
						type="button"
						class="button button-small"
						data-eilmo-courier-check
					>
						<?php echo esc_html( $has_courier_data ? __( 'Refresh Courier Data', 'eilmo-checkout-flow' ) : __( 'Check Courier History', 'eilmo-checkout-flow' ) ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $send_enabled ) : ?>
				<div class="eilmo-cf-courier__actions">
					<div class="eilmo-cf-courier__action-block" data-eilmo-courier-action-block="steadfast">
						<div class="eilmo-cf-courier__action-row">
							<button
								type="button"
								class="button button-small eilmo-cf-courier__send-button"
								data-eilmo-courier-send="steadfast"
								<?php disabled( ! empty( $steadfast['sent'] ) ); ?>
							>
								<?php
								if ( ! empty( $steadfast['sent'] ) ) {
									$status_label =
										$this->format_delivery_status(
											(string) (
												$steadfast['delivery_status'] ??
													''
											)
										);

									echo esc_html(
										'' !== $status_label
											? $status_label
											: __( 'Steadfast Sent', 'eilmo-checkout-flow' )
									);
								} else {
									esc_html_e(
										'Send to Steadfast',
										'eilmo-checkout-flow'
									);
								}
								?>
							</button>

							<button
								type="button"
								class="button button-small eilmo-cf-courier__edit-button"
								data-eilmo-courier-edit="steadfast"
								title="<?php esc_attr_e( 'Edit Steadfast order before sending', 'eilmo-checkout-flow' ); ?>"
								aria-label="<?php esc_attr_e( 'Edit Steadfast order before sending', 'eilmo-checkout-flow' ); ?>"
								<?php disabled( ! empty( $steadfast['sent'] ) ); ?>
							>
								<span class="dashicons dashicons-edit" aria-hidden="true"></span>
							</button>
						</div>

						<?php $this->render_reference( $steadfast ); ?>
					</div>

					<div class="eilmo-cf-courier__action-block" data-eilmo-courier-action-block="pathao">
						<div class="eilmo-cf-courier__action-row">
							<button
								type="button"
								class="button button-small eilmo-cf-courier__send-button"
								data-eilmo-courier-send="pathao"
								<?php disabled( ! empty( $pathao['sent'] ) ); ?>
							>
								<?php
								if ( ! empty( $pathao['sent'] ) ) {
									$status_label =
										$this->format_delivery_status(
											(string) (
												$pathao['delivery_status'] ??
													''
											)
										);

									echo esc_html(
										'' !== $status_label
											? $status_label
											: __( 'Pathao Sent', 'eilmo-checkout-flow' )
									);
								} else {
									esc_html_e(
										'Send to Pathao',
										'eilmo-checkout-flow'
									);
								}
								?>
							</button>

							<button
								type="button"
								class="button button-small eilmo-cf-courier__edit-button"
								data-eilmo-courier-edit="pathao"
								title="<?php esc_attr_e( 'Edit Pathao order before sending', 'eilmo-checkout-flow' ); ?>"
								aria-label="<?php esc_attr_e( 'Edit Pathao order before sending', 'eilmo-checkout-flow' ); ?>"
								<?php disabled( ! empty( $pathao['sent'] ) ); ?>
							>
								<span class="dashicons dashicons-edit" aria-hidden="true"></span>
							</button>
						</div>

						<?php $this->render_reference( $pathao ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $stats_enabled ) : ?>
				<div class="eilmo-cf-courier__risk <?php echo esc_attr( '' !== $phone ? 'is-loading' : 'is-unknown' ); ?>" data-eilmo-courier-risk>
					<div class="eilmo-cf-courier__risk-row">
						<div
							class="eilmo-cf-courier__risk-bar"
							data-eilmo-courier-risk-bar
							role="progressbar"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="0"
							aria-label="<?php esc_attr_e( 'Combined courier success rate', 'eilmo-checkout-flow' ); ?>"
						>
							<?php for ( $segment = 0; $segment < 10; $segment++ ) : ?>
								<span class="eilmo-cf-courier__risk-segment<?php echo '' === $phone ? ' is-neutral' : ''; ?>" aria-hidden="true"></span>
							<?php endfor; ?>
						</div>
						<strong class="eilmo-cf-courier__risk-status" data-eilmo-courier-risk-status>
							<?php echo '' !== $phone ? esc_html__( 'Loading…', 'eilmo-checkout-flow' ) : esc_html__( 'No History', 'eilmo-checkout-flow' ); ?>
						</strong>
					</div>
				</div>
			<?php endif; ?>

			<div
				class="eilmo-cf-courier__message"
				data-eilmo-courier-message
				aria-live="polite"
			></div>
		</div>
		<?php
	}

	/**
	 * Render existing booking reference.
	 *
	 * @param array<string,mixed> $status Status.
	 *
	 * @return void
	 */
	private function render_reference(
		array $status
	): void {

		if ( empty( $status['sent'] ) ) {
			return;
		}

		$reference =
			(string) (
				$status['tracking_code'] ??
					''
			);

		if ( '' === $reference ) {
			$reference =
				(string) (
					$status['reference'] ??
						''
				);
		}

		if ( '' === $reference ) {
			return;
		}

		?>
		<small class="eilmo-cf-courier__reference">
			<?php echo esc_html( $reference ); ?>
		</small>
		<?php
	}

	/**
	 * Render one reusable editable courier modal.
	 *
	 * @return void
	 */
	public function render_modal(): void {

		if (
			! $this->is_order_list_screen() ||
			! $this->is_enabled( 'courier_one_click' )
		) {
			return;
		}

		?>
		<div
			class="eilmo-cf-courier-modal"
			data-eilmo-courier-modal
			hidden
		>
			<div
				class="eilmo-cf-courier-modal__backdrop"
				data-eilmo-courier-close
			></div>

			<div
				class="eilmo-cf-courier-modal__dialog"
				role="dialog"
				aria-modal="true"
				aria-labelledby="eilmo-cf-courier-modal-title"
			>
				<div class="eilmo-cf-courier-modal__header">
					<div>
						<h2 id="eilmo-cf-courier-modal-title">
							<?php esc_html_e( 'Edit Courier Order', 'eilmo-checkout-flow' ); ?>
						</h2>
						<p data-eilmo-courier-modal-subtitle></p>
					</div>

					<button
						type="button"
						class="eilmo-cf-courier-modal__close"
						data-eilmo-courier-close
						aria-label="<?php esc_attr_e( 'Close', 'eilmo-checkout-flow' ); ?>"
					>
						<span aria-hidden="true">&times;</span>
					</button>
				</div>

				<div class="eilmo-cf-courier-modal__body">
					<div class="eilmo-cf-courier-modal__loading" data-eilmo-courier-loading hidden>
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading order information…', 'eilmo-checkout-flow' ); ?>
					</div>

					<div class="eilmo-cf-courier-modal__error" data-eilmo-courier-modal-error hidden></div>

					<form data-eilmo-courier-form hidden>
						<div class="eilmo-cf-courier-form-grid">
							<label class="eilmo-cf-courier-field">
								<span data-eilmo-reference-label><?php esc_html_e( 'Invoice', 'eilmo-checkout-flow' ); ?></span>
								<input type="text" name="reference">
							</label>

							<label class="eilmo-cf-courier-field">
								<span><?php esc_html_e( 'Customer Name', 'eilmo-checkout-flow' ); ?></span>
								<input type="text" name="recipient_name" required>
							</label>

							<label class="eilmo-cf-courier-field">
								<span><?php esc_html_e( 'Phone', 'eilmo-checkout-flow' ); ?></span>
								<input type="text" name="recipient_phone" required>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Secondary Phone', 'eilmo-checkout-flow' ); ?></span>
								<input type="text" name="recipient_secondary_phone">
							</label>

							<label class="eilmo-cf-courier-field eilmo-cf-courier-field--wide">
								<span><?php esc_html_e( 'Delivery Address', 'eilmo-checkout-flow' ); ?></span>
								<textarea name="recipient_address" rows="3" required></textarea>
							</label>

							<label class="eilmo-cf-courier-field">
								<span><?php esc_html_e( 'Collectable Amount', 'eilmo-checkout-flow' ); ?></span>
								<input type="number" min="0" step="0.01" name="collect_amount" required>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Store', 'eilmo-checkout-flow' ); ?></span>
								<select name="store_id"></select>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Delivery Type', 'eilmo-checkout-flow' ); ?></span>
								<select name="delivery_type">
									<option value="48"><?php esc_html_e( 'Normal Delivery', 'eilmo-checkout-flow' ); ?></option>
									<option value="12"><?php esc_html_e( 'On Demand', 'eilmo-checkout-flow' ); ?></option>
									<option value="24"><?php esc_html_e( 'Express Delivery', 'eilmo-checkout-flow' ); ?></option>
								</select>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Item Type', 'eilmo-checkout-flow' ); ?></span>
								<select name="item_type">
									<option value="2"><?php esc_html_e( 'Parcel', 'eilmo-checkout-flow' ); ?></option>
									<option value="1"><?php esc_html_e( 'Document', 'eilmo-checkout-flow' ); ?></option>
									<option value="3"><?php esc_html_e( 'Fragile', 'eilmo-checkout-flow' ); ?></option>
								</select>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Quantity', 'eilmo-checkout-flow' ); ?></span>
								<input type="number" min="1" step="1" name="item_quantity">
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Weight (kg)', 'eilmo-checkout-flow' ); ?></span>
								<input type="number" min="0.1" step="0.1" name="item_weight">
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'City', 'eilmo-checkout-flow' ); ?></span>
								<select name="recipient_city"></select>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Zone', 'eilmo-checkout-flow' ); ?></span>
								<select name="recipient_zone"></select>
							</label>

							<label class="eilmo-cf-courier-field" data-eilmo-pathao-only hidden>
								<span><?php esc_html_e( 'Area', 'eilmo-checkout-flow' ); ?></span>
								<select name="recipient_area"></select>
							</label>

							<label class="eilmo-cf-courier-field eilmo-cf-courier-field--wide">
								<span><?php esc_html_e( 'Item Description', 'eilmo-checkout-flow' ); ?></span>
								<textarea name="item_description" rows="3"></textarea>
							</label>

							<label class="eilmo-cf-courier-field eilmo-cf-courier-field--wide">
								<span data-eilmo-note-label><?php esc_html_e( 'Note', 'eilmo-checkout-flow' ); ?></span>
								<textarea name="note" rows="3"></textarea>
							</label>
						</div>

						<div class="eilmo-cf-courier-modal__footer">
							<button type="button" class="button" data-eilmo-courier-close>
								<?php esc_html_e( 'Cancel', 'eilmo-checkout-flow' ); ?>
							</button>

							<button type="submit" class="button button-primary" data-eilmo-courier-submit>
								<?php esc_html_e( 'Send to Courier', 'eilmo-checkout-flow' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Load admin assets only on WooCommerce order-list screens.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {

		if ( ! $this->is_order_list_screen() ) {
			return;
		}

		if (
			! $this->is_enabled( 'courier_success' ) &&
			! $this->is_enabled( 'courier_one_click' )
		) {
			return;
		}

		$js =
			'src/js/admin/courier-orders.js';

		$css =
			'src/css/admin/courier-orders.css';

		$js_path =
			EILMO_CF_PATH .
				'assets/' .
				$js;

		$css_path =
			EILMO_CF_PATH .
				'assets/' .
				$css;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'eilmo-cf-courier-orders',
				EILMO_CF_ASSETS_URL .
					$css,
				array(),
				(string) filemtime( $css_path )
			);
		}

		if ( ! file_exists( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'eilmo-cf-courier-orders',
			EILMO_CF_ASSETS_URL .
				$js,
			array(),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'eilmo-cf-courier-orders',
			'eilmoCfCourierOrders',
			array(
				'ajaxUrl' =>
					admin_url( 'admin-ajax.php' ),

				'nonce' =>
					wp_create_nonce( self::NONCE_ACTION ),

				'successProvider' =>
					CourierSettings::get_success_provider(),

				'showStoreHistory' =>
					$this->show_store_history(),

				'actions' =>
					array(
						'stats' =>
							self::STATS_ACTION,

						'manualStats' =>
							self::MANUAL_STATS_ACTION,

						'status' =>
							self::STATUS_ACTION,

						'form' =>
							self::FORM_ACTION,

						'pathaoOptions' =>
							self::PATHAO_OPTIONS_ACTION,

						'send' =>
							self::SEND_ACTION,
					),

				'risk' =>
					$this->risk_config(),

				'i18n' =>
					array(
						'unavailable' =>
							__( 'Unavailable', 'eilmo-checkout-flow' ),

						'notConfigured' =>
							__( 'Not configured', 'eilmo-checkout-flow' ),

						'apiError' =>
							__( 'API error', 'eilmo-checkout-flow' ),

						'noHistory' =>
							__( 'No history', 'eilmo-checkout-flow' ),

						'customerOnStore' =>
							__( 'Customer on This Store', 'eilmo-checkout-flow' ),

						'storeOrderHistory' =>
							__( 'Store order history', 'eilmo-checkout-flow' ),

						'ordersLabel' =>
							__( 'orders', 'eilmo-checkout-flow' ),

						'completedLabel' =>
							__( 'Completed', 'eilmo-checkout-flow' ),

						'unsuccessfulLabel' =>
							__( 'Unsuccessful', 'eilmo-checkout-flow' ),

						'openLabel' =>
							__( 'Open', 'eilmo-checkout-flow' ),

						'successLabel' =>
							__( 'Success', 'eilmo-checkout-flow' ),

						'noStoreHistory' =>
							__( 'No orders found on this store.', 'eilmo-checkout-flow' ),

						'noData' =>
							__( 'No data', 'eilmo-checkout-flow' ),

						'allCouriers' =>
							__( 'All Couriers', 'eilmo-checkout-flow' ),

						'courierBreakdown' =>
							__( 'Courier breakdown', 'eilmo-checkout-flow' ),

						'courierHistory' =>
							__( 'Courier history', 'eilmo-checkout-flow' ),

						'trusted' =>
							__( 'Trusted', 'eilmo-checkout-flow' ),

						'review' =>
							__( 'Review', 'eilmo-checkout-flow' ),

						'highRisk' =>
							__( 'High Risk', 'eilmo-checkout-flow' ),

						'critical' =>
							__( 'Critical', 'eilmo-checkout-flow' ),

						'loading' =>
							__( 'Loading…', 'eilmo-checkout-flow' ),

						'checkingHistory' =>
							__( 'Checking…', 'eilmo-checkout-flow' ),

						'checkHistory' =>
							__( 'Check Courier History', 'eilmo-checkout-flow' ),

						'refreshHistory' =>
							__( 'Refresh Courier Data', 'eilmo-checkout-flow' ),

						'checkedNow' =>
							__( 'Checked just now', 'eilmo-checkout-flow' ),

						'sending' =>
							__( 'Sending…', 'eilmo-checkout-flow' ),

						'sendSteadfast' =>
							__( 'Send to Steadfast', 'eilmo-checkout-flow' ),

						'sendPathao' =>
							__( 'Send to Pathao', 'eilmo-checkout-flow' ),

						'sentSteadfast' =>
							__( 'Steadfast Sent', 'eilmo-checkout-flow' ),

						'sentPathao' =>
							__( 'Pathao Sent', 'eilmo-checkout-flow' ),

						'sentSuccessfully' =>
							__( 'Sent successfully.', 'eilmo-checkout-flow' ),

						'useEdit' =>
							__( 'Use the edit icon to review courier details.', 'eilmo-checkout-flow' ),

						'editSteadfast' =>
							__( 'Edit & Send to Steadfast', 'eilmo-checkout-flow' ),

						'editPathao' =>
							__( 'Edit & Send to Pathao', 'eilmo-checkout-flow' ),

						'confirmOtherCourier' =>
							__(
								'This order was already sent to the other courier. Send it to this courier too?',
								'eilmo-checkout-flow'
							),

						'selectStore' =>
							__( 'Select store', 'eilmo-checkout-flow' ),

						'selectCity' =>
							__( 'Select city', 'eilmo-checkout-flow' ),

						'selectZone' =>
							__( 'Select zone', 'eilmo-checkout-flow' ),

						'selectArea' =>
							__( 'Select area', 'eilmo-checkout-flow' ),
					),
			)
		);
	}

	/**
	 * Expose the same thresholds used by Advanced Live Fraud Check.
	 *
	 * @return array<string,int|float>
	 */
	private function risk_config(): array {

		$settings = CourierSettings::get_settings();
		$config = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();

		return array(
			'trustedRate' => max( 0, min( 100, (float) ( $config['trusted_rate'] ?? 95 ) ) ),
			'advanceRate' => max( 0, min( 100, (float) ( $config['advance_rate'] ?? 90 ) ) ),
			'blockRate' => max( 0, min( 100, (float) ( $config['block_rate'] ?? 40 ) ) ),
			'minimumOrders' => max( 1, absint( $config['minimum_orders_for_block'] ?? 5 ) ),
		);
	}

	/**
	 * AJAX: load stats for current order rows.
	 *
	 * @return void
	 */
	public function ajax_stats(): void {

		$this->verify_request();

		if ( ! $this->is_enabled( 'courier_success' ) ) {
			wp_send_json_success(
				array(
					'orders' =>
						array(),
				)
			);
		}

		$order_ids =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['order_ids'] ) &&
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			is_array( $_POST['order_ids'] )
				? array_slice(
					array_values(
						array_unique(
							array_filter(
								array_map(
									'absint',
									// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
									wp_unslash( $_POST['order_ids'] )
								)
							)
						)
					),
					0,
					50
				)
				: array();

		$service =
			new CourierSuccessService();

		$orders =
			array();

		$show_store_history = $this->show_store_history();
		$local_history_service = $show_store_history ? new LocalCustomerHistoryService() : null;
		$local_history_cache = array();

		foreach ( $order_ids as $order_id ) {
			$order =
				wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$phone =
				$service->normalize_phone(
					(string) $order->get_billing_phone()
				);

			if ( '' === $phone ) {
				$orders[ $order_id ] =
					array(
						'error' =>
							__( 'Valid phone required', 'eilmo-checkout-flow' ),

						'code' =>
							'eilmo_cf_courier_invalid_phone',
					);

				continue;
			}

			/* Orders page reads saved data only. External usage is reserved
			 * for checkout or the explicit Check/Refresh button. */
			$snapshot =
				$service->get_saved_snapshot_for_selected_provider(
					$order
				);

			$result =
				$service->get_saved_stats(
					$order
				);

			if ( is_wp_error( $result ) ) {
				$orders[ $order_id ] =
					array(
						'error' =>
							$result->get_error_message(),

						'code' =>
							$result->get_error_code(),

						'_meta' =>
							$this->stats_meta( $snapshot, $service ),
					);

				continue;
			}

			if ( $show_store_history && $local_history_service instanceof LocalCustomerHistoryService ) {
				if ( ! array_key_exists( $phone, $local_history_cache ) ) {
					$local_history_cache[ $phone ] = $local_history_service->lookup_summary( $phone );
				}
				$result['_storeHistory'] = $local_history_cache[ $phone ];
			}

			$result['_meta'] = $this->stats_meta( $snapshot, $service );
			$orders[ $order_id ] = $result;
		}

		wp_send_json_success(
			array(
				'orders' =>
					$orders,
			)
		);
	}

	/** AJAX: explicitly spend one courier API request for a selected order. */
	public function ajax_manual_stats(): void {

		$this->verify_request();
		$settings = CourierSettings::get_settings();
		$live_fraud = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
			? $settings['live_fraud']
			: array();
		if ( 'yes' !== ( $live_fraud['manual_order_check'] ?? 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Manual courier checking is disabled.', 'eilmo-checkout-flow' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'eilmo-checkout-flow' ) ), 404 );
		}

		$service = new CourierSuccessService();
		$phone = $service->normalize_phone( (string) $order->get_billing_phone() );
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'A valid Bangladesh phone number is required.', 'eilmo-checkout-flow' ) ), 400 );
		}

		$snapshot = $service->lookup_live_phone( $phone, true );
		if ( is_wp_error( $snapshot ) ) {
			$status = 'eilmo_cf_courier_rate_limited' === $snapshot->get_error_code() ? 429 : 502;
			wp_send_json_error(
				array(
					'message' => $snapshot->get_error_message(),
					'code' => $snapshot->get_error_code(),
				),
				$status
			);
		}

		$service->save_manual_snapshot( $order, $snapshot );
		$stats = isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] ) ? $snapshot['stats'] : array();
		if ( $this->show_store_history() ) {
			$stats['_storeHistory'] = ( new LocalCustomerHistoryService() )->lookup_summary( $phone );
		}
		$stats['_meta'] = $this->stats_meta( $snapshot, $service );
		wp_send_json_success( array( 'result' => $stats ) );
	}

	/**
	 * Whether local store history should be added to courier history hover cards.
	 *
	 * @return bool
	 */
	private function show_store_history(): bool {

		$settings = CourierSettings::get_settings();
		$ratio_api = isset( $settings['ratio_api'] ) && is_array( $settings['ratio_api'] )
			? $settings['ratio_api']
			: array();

		return 'yes' === ( $ratio_api['show_store_history'] ?? 'yes' );
	}

	/** @return array<string,mixed> */
	private function stats_meta( array $snapshot, CourierSuccessService $service ): array {

		$has_data = $service->snapshot_has_courier_data( $snapshot );
		return array(
			'hasCourierData' => $has_data,
			'stale' => $has_data && $service->snapshot_is_stale( $snapshot ),
			'checkedAt' => max( 0, absint( $snapshot['checked_at'] ?? 0 ) ),
		);
	}


	/**
	 * AJAX: refresh delivery status for sent Steadfast orders.
	 *
	 * The status replaces the disabled "Steadfast Sent"
	 * button text inside the existing Courier Success column.
	 *
	 * @return void
	 */
	public function ajax_statuses(): void {

		$this->verify_request();

		if (
			! $this->is_enabled(
				'courier_one_click'
			)
		) {
			wp_send_json_success(
				array(
					'orders' =>
						array(),
				)
			);
		}

		$order_ids =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['order_ids'] ) &&
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			is_array( $_POST['order_ids'] )
				? array_slice(
					array_values(
						array_unique(
							array_filter(
								array_map(
									'absint',
									wp_unslash(
										// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
										$_POST['order_ids']
									)
								)
							)
						)
					),
					0,
					50
				)
				: array();

		$service =
			new CourierBookingService();

		$orders =
			array();

		foreach ( $order_ids as $order_id ) {

			$order =
				wc_get_order(
					$order_id
				);

			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$steadfast =
				$service->get_booking_status(
					$order,
					'steadfast'
				);

			$pathao =
				$service->get_booking_status(
					$order,
					'pathao'
				);

			$row =
				array();

			/*
			 * Steadfast is refreshed from its status API using
			 * the existing five-minute service cache.
			 */
			if ( ! empty( $steadfast['sent'] ) ) {
				$result =
					$service->refresh_delivery_status(
						$order,
						'steadfast'
					);

				if ( is_wp_error( $result ) ) {
					$row['steadfast'] =
						$steadfast;

					$row['steadfast_error'] =
						$result->get_error_message();
				} else {
					$row['steadfast'] =
						$result;
				}
			}

			/*
			 * Pathao is webhook-driven. Never poll Pathao here.
			 * Just return the latest status already saved by
			 * PathaoWebhook.
			 */
			if ( ! empty( $pathao['sent'] ) ) {
				$row['pathao'] =
					$pathao;
			}

			if ( ! empty( $row ) ) {
				$orders[ $order_id ] =
					$row;
			}
		}

		wp_send_json_success(
			array(
				'orders' =>
					$orders,
			)
		);
	}

	/**
	 * AJAX: load editable form defaults.
	 *
	 * @return void
	 */
	public function ajax_form(): void {

		$this->verify_request();

		if ( ! $this->is_enabled( 'courier_one_click' ) ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'One Click Courier is disabled.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		$order =
			$this->get_ajax_order();

		$provider =
			$this->get_ajax_provider();

		$service =
			new CourierBookingService();

		$result =
			$service->get_form_data(
				$order,
				$provider
			);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' =>
						$result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'form' =>
					$result,
			)
		);
	}

	/**
	 * AJAX: Pathao stores/cities/zones/areas.
	 *
	 * @return void
	 */
	public function ajax_pathao_options(): void {

		$this->verify_request();

		if ( ! $this->is_enabled( 'courier_one_click' ) ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'One Click Courier is disabled.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		$type =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['type'] )
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['type']
					)
				)
				: '';

		$parent_id =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['parent_id'] )
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST['parent_id']
					)
				)
				: 0;

		$service =
			new CourierBookingService();

		$result =
			$service->get_pathao_options(
				$type,
				$parent_id
			);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' =>
						$result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'items' =>
					$result,
			)
		);
	}

	/**
	 * AJAX: send one edited order to one courier.
	 *
	 * @return void
	 */
	public function ajax_send(): void {

		$this->verify_request();

		if ( ! $this->is_enabled( 'courier_one_click' ) ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'One Click Courier is disabled.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		$order =
			$this->get_ajax_order();

		$provider =
			$this->get_ajax_provider();

		$fields_json =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['fields'] )
				? wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
					(string) $_POST['fields']
				)
				: '{}';

		$fields =
			json_decode(
				$fields_json,
				true
			);

		if ( ! is_array( $fields ) ) {
			$fields =
				array();
		}

		/*
		 * Courier note is submitted separately from the
		 * editable fields JSON. This makes the manually
		 * entered popup note explicit and prevents it from
		 * being lost during JSON serialization/merging.
		 */
		$has_popup_note =
			isset( $_POST['courier_note'] );

		$popup_note =
			$has_popup_note
				? sanitize_textarea_field(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['courier_note']
					)
				)
				: '';

		/*
		 * Only override the saved/order-derived courier note when the
		 * editable modal explicitly submitted a note. Direct one-click
		 * sending omits courier_note and therefore keeps service defaults.
		 */
		if ( $has_popup_note && 'steadfast' === $provider ) {
			$fields['note'] =
				$popup_note;
		} elseif ( $has_popup_note && 'pathao' === $provider ) {
			$fields['special_instruction'] =
				$popup_note;
		}

		$service =
			new CourierBookingService();

		$result =
			$service->send(
				$order,
				$provider,
				$fields
			);

		if ( is_wp_error( $result ) ) {
			$error_data =
				$result->get_error_data();

			if ( ! is_array( $error_data ) ) {
				$error_data =
					array();
			}

			$status =
				absint(
					$error_data['status'] ??
						400
				);

			if ( $status < 400 ) {
				$status =
					400;
			}

			$errors =
				isset( $error_data['errors'] ) &&
				is_array( $error_data['errors'] )
					? $error_data['errors']
					: array();

			wp_send_json_error(
				array(
					'message' =>
						$result->get_error_message(),

					'errors' =>
						$errors,
				),
				$status
			);
		}

		wp_send_json_success(
			array(
				'booking' =>
					$result,
			)
		);
	}

	/**
	 * Get AJAX order or terminate request.
	 *
	 * @return WC_Order
	 */
	private function get_ajax_order(): WC_Order {

		$order_id =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['order_id'] )
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						$_POST['order_id']
					)
				)
				: 0;

		$order =
			wc_get_order(
				$order_id
			);

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error(
				array(
					'message' =>
						__( 'Order was not found.', 'eilmo-checkout-flow' ),
				),
				404
			);
		}

		return $order;
	}

	/**
	 * Get AJAX provider.
	 *
	 * @return string
	 */
	private function get_ajax_provider(): string {

		$provider =
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
			isset( $_POST['provider'] )
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
						(string) $_POST['provider']
					)
				)
				: '';

		if (
			! in_array(
				$provider,
				array(
					'steadfast',
					'pathao',
				),
				true
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__( 'Invalid courier provider.', 'eilmo-checkout-flow' ),
				),
				400
			);
		}

		return $provider;
	}

	/**
	 * Verify AJAX request.
	 *
	 * @return void
	 */
	private function verify_request(): void {

		if ( ! CourierSettings::is_enabled() ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Courier is disabled.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'You do not have permission to manage courier orders.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}

		if (
			! check_ajax_referer(
				self::NONCE_ACTION,
				'nonce',
				false
			)
		) {
			wp_send_json_error(
				array(
					'message' =>
						__(
							'Your session has expired. Refresh the page and try again.',
							'eilmo-checkout-flow'
						),
				),
				403
			);
		}
	}


	/**
	 * Format provider delivery status for admin display.
	 *
	 * @param string $status Raw provider status.
	 *
	 * @return string
	 */
	private function format_delivery_status(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if ( '' === $status ) {
			return '';
		}

		$labels =
			array(
				'in_review' =>
					__( 'In Review', 'eilmo-checkout-flow' ),

				'pending' =>
					__( 'Pending', 'eilmo-checkout-flow' ),

				'delivered_approval_pending' =>
					__( 'Delivered Approval Pending', 'eilmo-checkout-flow' ),

				'partial_delivered_approval_pending' =>
					__( 'Partial Delivered Approval Pending', 'eilmo-checkout-flow' ),

				'cancelled_approval_pending' =>
					__( 'Cancelled Approval Pending', 'eilmo-checkout-flow' ),

				'unknown_approval_pending' =>
					__( 'Unknown Approval Pending', 'eilmo-checkout-flow' ),

				'delivered' =>
					__( 'Delivered', 'eilmo-checkout-flow' ),

				'partial_delivered' =>
					__( 'Partial Delivered', 'eilmo-checkout-flow' ),

				'cancelled' =>
					__( 'Cancelled', 'eilmo-checkout-flow' ),

				'hold' =>
					__( 'On Hold', 'eilmo-checkout-flow' ),

				'unknown' =>
					__( 'Unknown', 'eilmo-checkout-flow' ),

				'order_created' =>
					__( 'Order Created', 'eilmo-checkout-flow' ),

				'order_updated' =>
					__( 'Order Updated', 'eilmo-checkout-flow' ),

				'pickup_requested' =>
					__( 'Pickup Requested', 'eilmo-checkout-flow' ),

				'assigned_for_pickup' =>
					__( 'Assigned for Pickup', 'eilmo-checkout-flow' ),

				'picked' =>
					__( 'Picked', 'eilmo-checkout-flow' ),

				'pickup_failed' =>
					__( 'Pickup Failed', 'eilmo-checkout-flow' ),

				'pickup_cancelled' =>
					__( 'Pickup Cancelled', 'eilmo-checkout-flow' ),

				'at_the_sorting_hub' =>
					__( 'At the Sorting Hub', 'eilmo-checkout-flow' ),

				'in_transit' =>
					__( 'In Transit', 'eilmo-checkout-flow' ),

				'received_at_last_mile_hub' =>
					__( 'Received at Last Mile Hub', 'eilmo-checkout-flow' ),

				'assigned_for_delivery' =>
					__( 'Assigned for Delivery', 'eilmo-checkout-flow' ),

				'partial_delivery' =>
					__( 'Partial Delivery', 'eilmo-checkout-flow' ),

				'return' =>
					__( 'Return', 'eilmo-checkout-flow' ),

				'delivery_failed' =>
					__( 'Delivery Failed', 'eilmo-checkout-flow' ),

				'on_hold' =>
					__( 'On Hold', 'eilmo-checkout-flow' ),

				'paid_return' =>
					__( 'Paid Return', 'eilmo-checkout-flow' ),

				'exchange' =>
					__( 'Exchange', 'eilmo-checkout-flow' ),

				'payment_invoice' =>
					__( 'Payment Invoice', 'eilmo-checkout-flow' ),
			);

		if ( isset( $labels[ $status ] ) ) {
			return (string) $labels[ $status ];
		}

		return ucwords(
			str_replace(
				'_',
				' ',
				$status
			)
		);
	}

	/**
	 * Check Courier sub-feature toggle.
	 *
	 * Detailed Courier feature settings are stored in the
	 * dedicated Courier settings option, while the global
	 * Courier master switch remains in General settings.
	 *
	 * @param string $key Courier feature key.
	 *
	 * @return bool
	 */
	private function is_enabled(
		string $key
	): bool {

		return CourierSettings::feature_is_enabled(
			$key
		);
	}

	/**
	 * Determine whether current admin screen is an order list.
	 *
	 * @return bool
	 */
	private function is_order_list_screen(): bool {

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen =
			get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array(
			(string) $screen->id,
			array(
				'edit-shop_order',
				'woocommerce_page_wc-orders',
			),
			true
		);
	}
}
