<?php
/**
 * Meta Purchase order admin panel.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Admin;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Tracking\Meta\MetaOrderEligibility;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows read-only Meta Purchase tracking information
 * on WooCommerce order edit and order list screens.
 */
final class MetaOrderAdminPanel implements RegistrableInterface {

	/**
	 * Meta box identifier.
	 */
	private const META_BOX_ID =
		'eilmo-cf-meta-purchase';

	/**
	 * Order list column identifier.
	 */
	private const ORDER_COLUMN =
		'eilmo_meta_purchase';

	/**
	 * Purchase tracking metadata.
	 */
	private const META_PURCHASE_EVENT_ID =
		'_eilmo_cf_meta_purchase_event_id';

	private const META_PURCHASE_SENT =
		'_eilmo_cf_meta_purchase_sent';

	private const META_PURCHASE_SENT_AT =
		'_eilmo_cf_meta_purchase_sent_at';

	private const META_PURCHASE_STRATEGY =
		'_eilmo_cf_meta_purchase_strategy';

	private const META_PURCHASE_TRIGGER_STATUS =
		'_eilmo_cf_meta_purchase_trigger_status';

	private const META_PURCHASE_LAST_ERROR =
		'_eilmo_cf_meta_purchase_last_error';

	private const META_PURCHASE_LAST_ERROR_AT =
		'_eilmo_cf_meta_purchase_last_error_at';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( ! is_admin() ) {
			return;
		}

		/*
		 * Order edit screen Meta Purchase panel.
		 */
		add_action(
			'add_meta_boxes',
			array(
				$this,
				'register_meta_box',
			),
			30
		);

		/*
		 * Legacy WooCommerce order list.
		 */
		add_filter(
			'manage_shop_order_posts_columns',
			array(
				$this,
				'add_order_list_column',
			),
			20
		);

		add_action(
			'manage_shop_order_posts_custom_column',
			array(
				$this,
				'render_order_list_column',
			),
			20,
			2
		);

		/*
		 * WooCommerce HPOS order list.
		 */
		add_filter(
			'manage_woocommerce_page_wc-orders_columns',
			array(
				$this,
				'add_order_list_column',
			),
			20
		);

		add_action(
			'manage_woocommerce_page_wc-orders_custom_column',
			array(
				$this,
				'render_order_list_column',
			),
			20,
			2
		);

		/*
		 * Compact column styling.
		 */
		add_action(
			'admin_head',
			array(
				$this,
				'render_order_list_styles',
			)
		);
	}

	/**
	 * Register Meta Purchase meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {

		if (
			! current_user_can(
				'manage_woocommerce'
			)
		) {
			return;
		}

		$screen =
			$this->get_order_screen_id();

		if ( '' === $screen ) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			__(
				'Meta Purchase',
				'eilmo-checkout-flow'
			),
			array(
				$this,
				'render',
			),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Add Meta column to WooCommerce order list.
	 *
	 * The column is inserted immediately after the
	 * WooCommerce order status column when possible.
	 *
	 * @param array<string,string> $columns Columns.
	 *
	 * @return array<string,string>
	 */
	public function add_order_list_column(
		array $columns
	): array {

		if (
			isset(
				$columns[
					self::ORDER_COLUMN
				]
			)
		) {
			return $columns;
		}

		$updated =
			array();

		$inserted =
			false;

		foreach (
			$columns as
				$key =>
				$label
		) {
			$updated[ $key ] =
				$label;

			if (
				in_array(
					$key,
					array(
						'order_status',
						'status',
					),
					true
				)
			) {
				$updated[
					self::ORDER_COLUMN
				] =
					__(
						'Meta',
						'eilmo-checkout-flow'
					);

				$inserted =
					true;
			}
		}

		if ( ! $inserted ) {
			$updated[
				self::ORDER_COLUMN
			] =
				__(
					'Meta',
					'eilmo-checkout-flow'
				);
		}

		return $updated;
	}

	/**
	 * Render Meta status in WooCommerce order list.
	 *
	 * Legacy order lists generally pass an order/post ID.
	 * HPOS order lists pass the WC_Order object.
	 *
	 * The resolver intentionally supports both so the same
	 * callback can safely serve both storage modes.
	 *
	 * @param string $column_name Column name.
	 * @param mixed  $order_value Order object or ID.
	 *
	 * @return void
	 */
	public function render_order_list_column(
		string $column_name,
		$order_value
	): void {

		if (
			self::ORDER_COLUMN !==
			$column_name
		) {
			return;
		}

		$order =
			$this->resolve_list_order(
				$order_value
			);

		if ( ! $order instanceof WC_Order ) {
			echo '<span class="eilmo-cf-meta-list-status eilmo-cf-meta-list-status--none">—</span>';

			return;
		}

		$status =
			$this->get_compact_list_status(
				$order
			);

		$classes =
			array(
				'eilmo-cf-meta-list-status',
				'eilmo-cf-meta-list-status--' .
					sanitize_html_class(
						$status['key']
					),
			);

		?>
		<span
			class="<?php
			echo esc_attr(
				implode(
					' ',
					$classes
				)
			);
			?>"
			title="<?php
			echo esc_attr(
				$status['title']
			);
			?>"
		>
			<?php
			echo esc_html(
				$status['label']
			);
			?>
		</span>
		<?php
	}

	/**
	 * Render compact Meta column styling.
	 *
	 * @return void
	 */
	public function render_order_list_styles(): void {

		if ( ! $this->is_order_list_screen() ) {
			return;
		}

		?>
		<style id="eilmo-cf-meta-order-list-styles">

			.column-eilmo_meta_purchase {
				box-sizing: border-box ;
				text-align: center ;
			}

			.eilmo-cf-meta-list-status {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 58px;
				padding: 4px 8px;
				border-radius: 999px;
				font-size: 12px;
				font-weight: 600;
				line-height: 1.3;
				box-sizing: border-box;
				white-space: nowrap;
			}

			.eilmo-cf-meta-list-status--sent {
				color: #166534;
				background: #dcfce7;
			}

			.eilmo-cf-meta-list-status--waiting {
				color: #854d0e;
				background: #fef9c3;
			}

			.eilmo-cf-meta-list-status--failed {
				color: #991b1b;
				background: #fee2e2;
			}

			.eilmo-cf-meta-list-status--none {
				min-width: 0;
				padding: 0;
				color: #646970;
				background: transparent;
			}

		</style>
		<?php
	}

	/**
	 * Render Meta Purchase information.
	 *
	 * HPOS may pass WC_Order while the legacy editor may
	 * pass WP_Post. Resolve both to WC_Order first, then
	 * use WooCommerce CRUD/meta APIs only.
	 *
	 * @param mixed $post_or_order Order editor object.
	 *
	 * @return void
	 */
	public function render(
		$post_or_order
	): void {

		$order =
			$this->resolve_order(
				$post_or_order
			);

		if ( ! $order instanceof WC_Order ) {
			echo '<p>' .
				esc_html__(
					'Unable to load Meta Purchase tracking information for this order.',
					'eilmo-checkout-flow'
				) .
			'</p>';

			return;
		}

		$tracking_status =
			$this->get_tracking_status(
				$order
			);

		$strategy =
			$this->get_display_strategy(
				$order
			);

		$event_id =
			$this->get_event_id(
				$order
			);

		$trigger_status =
			$this->get_trigger_status(
				$order
			);

		$sent_at =
			$this->format_utc_datetime(
				(string) $order->get_meta(
					self::META_PURCHASE_SENT_AT,
					true
				)
			);

		$error =
			$this->get_last_error(
				$order
			);

		$error_at =
			$this->format_utc_datetime(
				(string) $order->get_meta(
					self::META_PURCHASE_LAST_ERROR_AT,
					true
				)
			);

		$is_sent =
			'yes' ===
				(string) $order->get_meta(
					self::META_PURCHASE_SENT,
					true
				);

		?>
		<div class="eilmo-cf-meta-order-panel">

			<p style="margin-top: 0;">
				<strong>
					<?php
					esc_html_e(
						'Status:',
						'eilmo-checkout-flow'
					);
					?>
				</strong>

				<?php
				echo esc_html(
					$tracking_status['label']
				);
				?>
			</p>

			<p>
				<strong>
					<?php
					esc_html_e(
						'Strategy:',
						'eilmo-checkout-flow'
					);
					?>
				</strong>

				<?php
				echo esc_html(
					$strategy
				);
				?>
			</p>

			<?php
			if (
				! $is_sent &&
				MetaTrackingSettings::uses_advanced_purchase_tracking()
			) :
				?>

				<p>
					<strong>
						<?php
						esc_html_e(
							'Waiting For:',
							'eilmo-checkout-flow'
						);
						?>
					</strong>

					<?php
					echo esc_html(
						$this->get_configured_trigger_status_labels()
					);
					?>
				</p>

			<?php endif; ?>

			<?php if ( '' !== $trigger_status ) : ?>

				<p>
					<strong>
						<?php
						esc_html_e(
							'Trigger Status:',
							'eilmo-checkout-flow'
						);
						?>
					</strong>

					<?php
					echo esc_html(
						$this->get_status_label(
							$trigger_status
						)
					);
					?>
				</p>

			<?php endif; ?>

			<p>
				<strong>
					<?php
					esc_html_e(
						'Event ID:',
						'eilmo-checkout-flow'
					);
					?>
				</strong>

				<code>
					<?php
					echo esc_html(
						$event_id
					);
					?>
				</code>
			</p>

			<?php if ( '' !== $sent_at ) : ?>

				<p>
					<strong>
						<?php
						esc_html_e(
							'Sent At:',
							'eilmo-checkout-flow'
						);
						?>
					</strong>

					<?php
					echo esc_html(
						$sent_at
					);
					?>
				</p>

			<?php endif; ?>

			<?php if ( '' !== $error['message'] ) : ?>

				<hr>

				<p>
					<strong>
						<?php
						esc_html_e(
							'Last Error:',
							'eilmo-checkout-flow'
						);
						?>
					</strong>

					<?php
					echo esc_html(
						$error['message']
					);
					?>
				</p>

				<?php if ( '' !== $error_at ) : ?>

					<p>
						<strong>
							<?php
							esc_html_e(
								'Last Attempt:',
								'eilmo-checkout-flow'
							);
							?>
						</strong>

						<?php
						echo esc_html(
							$error_at
						);
						?>
					</p>

				<?php endif; ?>

			<?php endif; ?>

			<?php
			if (
				'' !==
					$tracking_status['description']
			) :
				?>

				<hr>

				<p
					class="description"
					style="margin-bottom: 0;"
				>
					<?php
					echo esc_html(
						$tracking_status['description']
					);
					?>
				</p>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Resolve legacy or HPOS order editor object.
	 *
	 * @param mixed $post_or_order Object.
	 *
	 * @return WC_Order|null
	 */
	private function resolve_order(
		$post_or_order
	): ?WC_Order {

		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( $post_or_order instanceof WP_Post ) {
			$order =
				wc_get_order(
					$post_or_order->ID
				);

			return $order instanceof WC_Order
				? $order
				: null;
		}

		return null;
	}

	/**
	 * Resolve an order from legacy or HPOS list callback.
	 *
	 * @param mixed $order_value WC_Order, WP_Post or ID.
	 *
	 * @return WC_Order|null
	 */
	private function resolve_list_order(
		$order_value
	): ?WC_Order {

		if ( $order_value instanceof WC_Order ) {
			return $order_value;
		}

		if ( $order_value instanceof WP_Post ) {
			$order_value =
				$order_value->ID;
		}

		$order_id =
			absint(
				$order_value
			);

		if ( $order_id <= 0 ) {
			return null;
		}

		$order =
			wc_get_order(
				$order_id
			);

		return $order instanceof WC_Order
			? $order
			: null;
	}

	/**
	 * Get compact order-list status.
	 *
	 * The list intentionally stays limited to the three
	 * operational states requested for quick monitoring:
	 *
	 * - Sent.
	 * - Waiting.
	 * - Failed.
	 *
	 * Orders for which Meta Purchase is not applicable
	 * display a dash instead.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{key:string,label:string,title:string}
	 */
	private function get_compact_list_status(
		WC_Order $order
	): array {

		if (
			'yes' ===
				(string) $order->get_meta(
					self::META_PURCHASE_SENT,
					true
				)
		) {
			return array(
				'key' =>
					'sent',

				'label' =>
					__(
						'Sent',
						'eilmo-checkout-flow'
					),

				'title' =>
					__(
						'Meta Purchase was sent successfully.',
						'eilmo-checkout-flow'
					),
			);
		}

		$error =
			$this->get_last_error(
				$order
			);

		if ( '' !== $error['message'] ) {
			return array(
				'key' =>
					'failed',

				'label' =>
					__(
						'Failed',
						'eilmo-checkout-flow'
					),

				'title' =>
					$error['message'],
			);
		}

		$eligibility =
			new MetaOrderEligibility();

		if (
			! $eligibility->is_website_checkout_order(
				$order
			)
		) {
			return array(
				'key' =>
					'none',

				'label' =>
					'—',

				'title' =>
					__(
						'This order is excluded from Meta Purchase tracking.',
						'eilmo-checkout-flow'
					),
			);
		}

		if (
			! MetaTrackingSettings::is_enabled() ||
			! MetaTrackingSettings::event_is_enabled(
				'purchase'
			)
		) {
			return array(
				'key' =>
					'none',

				'label' =>
					'—',

				'title' =>
					__(
						'Meta Purchase tracking is currently disabled.',
						'eilmo-checkout-flow'
					),
			);
		}

		if (
			MetaTrackingSettings::uses_advanced_purchase_tracking() &&
			! MetaTrackingSettings::advanced_purchase_is_ready()
		) {
			return array(
				'key' =>
					'none',

				'label' =>
					'—',

				'title' =>
					__(
						'Advanced Meta Purchase tracking is not fully configured.',
						'eilmo-checkout-flow'
					),
			);
		}

		return array(
			'key' =>
				'waiting',

			'label' =>
				__(
					'Waiting',
					'eilmo-checkout-flow'
				),

			'title' =>
				MetaTrackingSettings::uses_advanced_purchase_tracking()
					? sprintf(
						/* translators: %s: configured order statuses. */
						__(
							'Waiting for: %s',
							'eilmo-checkout-flow'
						),
						$this->get_configured_trigger_status_labels()
					)
					: __(
						'No successful server-side Meta Purchase is recorded yet.',
						'eilmo-checkout-flow'
					),
		);
	}

	/**
	 * Get WooCommerce order editor screen ID.
	 *
	 * @return string
	 */
	private function get_order_screen_id(): string {

		if (
			class_exists(
				CustomOrdersTableController::class
			) &&
			function_exists(
				'wc_get_container'
			) &&
			function_exists(
				'wc_get_page_screen_id'
			)
		) {
			try {
				$controller =
					wc_get_container()->get(
						CustomOrdersTableController::class
					);

				if (
					$controller &&
					method_exists(
						$controller,
						'custom_orders_table_usage_is_enabled'
					) &&
					$controller->custom_orders_table_usage_is_enabled()
				) {
					return wc_get_page_screen_id(
						'shop-order'
					);
				}
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
			}
		}

		return 'shop_order';
	}

	/**
	 * Determine whether current admin screen is a
	 * WooCommerce order list screen.
	 *
	 * @return bool
	 */
	private function is_order_list_screen(): bool {

		if (
			! function_exists(
				'get_current_screen'
			)
		) {
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

	/**
	 * Get current tracking state for the order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{label:string,description:string}
	 */
	private function get_tracking_status(
		WC_Order $order
	): array {

		if (
			'yes' ===
				(string) $order->get_meta(
					self::META_PURCHASE_SENT,
					true
				)
		) {
			return array(
				'label' =>
					__(
						'Sent',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'Meta accepted the server-side Purchase event for this order. Duplicate server-side Purchase sends are blocked for this order.',
						'eilmo-checkout-flow'
					),
			);
		}

		$eligibility =
			new MetaOrderEligibility();

		if (
			! $eligibility->is_website_checkout_order(
				$order
			)
		) {
			return array(
				'label' =>
					__(
						'Excluded',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'This order is not recognized as a supported customer-facing website checkout, so Eilmo will not send it as a Meta Purchase by default.',
						'eilmo-checkout-flow'
					),
			);
		}

		if ( ! MetaTrackingSettings::is_enabled() ) {
			return array(
				'label' =>
					__(
						'Disabled',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'Meta Tracking is currently disabled in Eilmo General Settings.',
						'eilmo-checkout-flow'
					),
			);
		}

		if (
			! MetaTrackingSettings::event_is_enabled(
				'purchase'
			)
		) {
			return array(
				'label' =>
					__(
						'Disabled',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'The Meta Purchase event is currently disabled.',
						'eilmo-checkout-flow'
					),
			);
		}

		$error =
			$this->get_last_error(
				$order
			);

		if ( '' !== $error['message'] ) {
			return array(
				'label' =>
					__(
						'Failed — Retryable',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'The last server-side Purchase attempt failed and was not marked as sent. A later eligible tracking hook may retry the same stable event ID.',
						'eilmo-checkout-flow'
					),
			);
		}

		if (
			MetaTrackingSettings::uses_advanced_purchase_tracking()
		) {
			if (
				! MetaTrackingSettings::advanced_purchase_is_ready()
			) {
				return array(
					'label' =>
						__(
							'Not Ready',
							'eilmo-checkout-flow'
						),

					'description' =>
						__(
							'Advanced Purchase tracking requires an enabled Conversions API connection, valid Meta credentials and at least one qualifying order status.',
							'eilmo-checkout-flow'
						),
				);
			}

			$current_status =
				sanitize_key(
					(string) $order->get_status()
				);

			if (
				MetaTrackingSettings::is_purchase_trigger_status(
					$current_status
				)
			) {
				return array(
					'label' =>
						__(
							'Eligible — Not Sent',
							'eilmo-checkout-flow'
						),

					'description' =>
						__(
							'This order is currently in a configured Purchase trigger status, but no successful server-side Purchase is recorded yet.',
							'eilmo-checkout-flow'
						),
				);
			}

			return array(
				'label' =>
					__(
						'Waiting',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'Purchase will be sent server-side when this order first reaches one of the configured Advanced Purchase trigger statuses.',
						'eilmo-checkout-flow'
					),
			);
		}

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			)
		) {
			return array(
				'label' =>
					__(
						'Browser Only',
						'eilmo-checkout-flow'
					),

				'description' =>
					__(
						'Conversions API is disabled. Basic browser Purchase may still be sent when Browser Pixel is enabled, but browser delivery cannot be confirmed from the WooCommerce order record.',
						'eilmo-checkout-flow'
					),
			);
		}

		return array(
			'label' =>
				__(
					'Pending',
					'eilmo-checkout-flow'
				),

			'description' =>
				__(
					'No successful server-side Purchase is recorded for this Basic Purchase order yet.',
					'eilmo-checkout-flow'
				),
		);
	}

	/**
	 * Get strategy displayed for this order.
	 *
	 * Sent orders retain the stored strategy that produced
	 * the event. Unsent orders show the current setting.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_display_strategy(
		WC_Order $order
	): string {

		$strategy =
			sanitize_key(
				(string) $order->get_meta(
					self::META_PURCHASE_STRATEGY,
					true
				)
			);

		if ( '' === $strategy ) {
			$strategy =
				MetaTrackingSettings::get_purchase_strategy();
		}

		if (
			MetaTrackingSettings::PURCHASE_STRATEGY_ADVANCED ===
			$strategy
		) {
			return __(
				'Advanced — Order Status',
				'eilmo-checkout-flow'
			);
		}

		return __(
			'Basic — Order Placed',
			'eilmo-checkout-flow'
		);
	}

	/**
	 * Get stable Purchase event ID.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_event_id(
		WC_Order $order
	): string {

		$event_id =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_PURCHASE_EVENT_ID,
					true
				)
			);

		if ( '' !== $event_id ) {
			return $event_id;
		}

		return 'eilmo_purchase_' .
			absint(
				$order->get_id()
			);
	}

	/**
	 * Get stored Purchase trigger status.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_trigger_status(
		WC_Order $order
	): string {

		return sanitize_key(
			(string) $order->get_meta(
				self::META_PURCHASE_TRIGGER_STATUS,
				true
			)
		);
	}

	/**
	 * Get configured Advanced trigger status labels.
	 *
	 * @return string
	 */
	private function get_configured_trigger_status_labels(): string {

		$labels =
			array_map(
				array(
					$this,
					'get_status_label',
				),
				MetaTrackingSettings::get_purchase_trigger_statuses()
			);

		$labels =
			array_values(
				array_filter(
					$labels
				)
			);

		return implode(
			', ',
			$labels
		);
	}

	/**
	 * Get readable WooCommerce order status label.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function get_status_label(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if ( '' === $status ) {
			return '';
		}

		if (
			function_exists(
				'wc_get_order_status_name'
			)
		) {
			$label =
				wc_get_order_status_name(
					$status
				);

			if (
				is_string( $label ) &&
				'' !== trim( $label )
			) {
				return $label;
			}
		}

		return ucwords(
			str_replace(
				array(
					'-',
					'_',
				),
				' ',
				$status
			)
		);
	}

	/**
	 * Get last stored Meta Purchase error.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{code:string,message:string}
	 */
	private function get_last_error(
		WC_Order $order
	): array {

		$raw =
			(string) $order->get_meta(
				self::META_PURCHASE_LAST_ERROR,
				true
			);

		if ( '' === trim( $raw ) ) {
			return array(
				'code' =>
					'',

				'message' =>
					'',
			);
		}

		$decoded =
			json_decode(
				$raw,
				true
			);

		if ( ! is_array( $decoded ) ) {
			return array(
				'code' =>
					'',

				'message' =>
					sanitize_text_field(
						$raw
					),
			);
		}

		return array(
			'code' =>
				sanitize_key(
					(string) (
						$decoded['code'] ??
							''
					)
				),

			'message' =>
				sanitize_text_field(
					(string) (
						$decoded['message'] ??
							''
					)
				),
		);
	}

	/**
	 * Format stored UTC MySQL datetime in site timezone.
	 *
	 * @param string $value UTC datetime.
	 *
	 * @return string
	 */
	private function format_utc_datetime(
		string $value
	): string {

		$value =
			trim(
				$value
			);

		if ( '' === $value ) {
			return '';
		}

		$local =
			get_date_from_gmt(
				$value,
				'Y-m-d H:i:s'
			);

		if ( '' === $local ) {
			return $value;
		}

		$timezone =
			function_exists(
				'wp_timezone_string'
			)
				? wp_timezone_string()
				: '';

		return '' !== $timezone
			? $local .
				' ' .
				$timezone
			: $local;
	}
}