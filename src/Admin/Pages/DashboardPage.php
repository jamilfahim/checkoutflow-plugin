<?php
/**
 * Dashboard page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use Automattic\WooCommerce\Utilities\OrderUtil;
use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutRepository;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Couriers\Services\CourierBookingService;
use EilmoCheckout\Tracking\Meta\MetaOrderEligibility;
use EilmoCheckout\Licensing\LicenseManager;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard page.
 */
final class DashboardPage {

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'eilmo-checkout';

	/**
	 * Admin page slugs.
	 */
	private const ABANDONED_PAGE_SLUG =
		'eilmo-checkout-abandoned';

	private const SECURITY_PAGE_SLUG =
		'eilmo-checkout-security';

	private const INTEGRATIONS_PAGE_SLUG =
		'eilmo-checkout-integrations';

	/**
	 * Keep the dashboard inexpensive on busy stores while remaining fresh.
	 */
	private const SNAPSHOT_CACHE_PREFIX = 'eilmo_cf_dashboard_snapshot_';

	private const SNAPSHOT_CACHE_TTL = 60;


	/**
	 * Meta Purchase order metadata.
	 */
	private const META_PURCHASE_SENT =
		'_eilmo_cf_meta_purchase_sent';

	private const META_PURCHASE_LAST_ERROR =
		'_eilmo_cf_meta_purchase_last_error';

	/**
	 * Render dashboard page.
	 *
	 * The dashboard intentionally stays operational rather than becoming a
	 * second settings screen. It shows order trends, today's operational alerts,
	 * and the next place the merchant should go.
	 *
	 * @return void
	 */
	public function render(): void {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to access this page.',
					'eilmo-checkout-flow'
				)
			);
		}

		$license_manager = new LicenseManager();

		if ( ! $license_manager->is_usable() ) {
			$this->render_activation_dashboard();
			return;
		}

		$period    = $this->get_selected_period();
		$range     = $this->get_period_range( $period );
		$snapshot  = $this->get_dashboard_snapshot( $range[0], $range[1] );
		$today     = 'today' === $period
			? $snapshot
			: $this->get_dashboard_snapshot( ...$this->get_period_range( 'today' ) );
		$month     = 'this_month' === $period
			? $snapshot
			: $this->get_dashboard_snapshot( ...$this->get_period_range( 'this_month' ) );
		$abandoned = $this->get_abandoned_checkout_count();
		$attention = (int) $today['payment_verification']
			+ (int) $today['meta_failed']
			+ (int) $today['courier_pending'];

		?>
		<div class="wrap eilmo-cf-dashboard">
			<?php settings_errors(); ?>

			<div class="eilmo-cf-dashboard__header">
				<div>
					<h1><?php esc_html_e( 'Checkout Flow', 'eilmo-checkout-flow' ); ?></h1>
					<p><?php esc_html_e( 'Order trends and the items that need your attention today.', 'eilmo-checkout-flow' ); ?></p>
				</div>

				<span class="eilmo-cf-dashboard__date">
					<?php echo esc_html( wp_date( get_option( 'date_format' ) ) ); ?>
				</span>
			</div>

			<nav class="eilmo-cf-dashboard__periods" aria-label="<?php esc_attr_e( 'Orders and revenue period', 'eilmo-checkout-flow' ); ?>">
				<span class="eilmo-cf-dashboard__period-label"><?php esc_html_e( 'Orders & revenue', 'eilmo-checkout-flow' ); ?></span>
				<?php foreach ( $this->get_period_options() as $key => $label ) : ?>
					<a class="eilmo-cf-dashboard__period<?php echo $period === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'period' => $key ), admin_url( 'admin.php' ) ) ); ?>" <?php echo $period === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<span class="eilmo-cf-dashboard__range"><?php echo esc_html( wp_date( get_option( 'date_format' ), $range[0]->getTimestamp() ) . ' – ' . wp_date( get_option( 'date_format' ), $range[1]->getTimestamp() ) ); ?></span>
			</nav>

			<div class="eilmo-cf-dashboard__grid eilmo-cf-dashboard__grid--metrics">
				<?php
				$this->render_number_card(
					__( 'Orders', 'eilmo-checkout-flow' ),
					(int) $snapshot['orders'],
					__( 'Created in selected period', 'eilmo-checkout-flow' ),
					'this_month' === $period ? '' : sprintf( __( 'This month: %s', 'eilmo-checkout-flow' ), number_format_i18n( (int) $month['orders'] ) )
				);
				?>

				<section class="eilmo-cf-dashboard__card">
					<div class="eilmo-cf-dashboard__label"><?php esc_html_e( 'Payment Received', 'eilmo-checkout-flow' ); ?></div>
					<div class="eilmo-cf-dashboard__value eilmo-cf-dashboard__value--price">
						<?php
						echo function_exists( 'wc_price' )
							? wp_kses_post( wc_price( (float) $snapshot['revenue'] ) )
							: esc_html( number_format_i18n( (float) $snapshot['revenue'], 2 ) );
						?>
					</div>
					<p><?php esc_html_e( 'Confirmed payment from orders created in selected period', 'eilmo-checkout-flow' ); ?></p>
					<?php if ( 'this_month' !== $period ) : ?>
						<div class="eilmo-cf-dashboard__month-context"><?php esc_html_e( 'This month:', 'eilmo-checkout-flow' ); ?> <?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) $month['revenue'] ) ) : esc_html( number_format_i18n( (float) $month['revenue'], 2 ) ); ?></div>
					<?php endif; ?>
				</section>

				<section class="eilmo-cf-dashboard__card">
					<div class="eilmo-cf-dashboard__label"><?php esc_html_e( 'Abandoned Today', 'eilmo-checkout-flow' ); ?></div>
					<div class="eilmo-cf-dashboard__value<?php echo null === $abandoned ? ' is-muted' : ''; ?>">
						<?php echo null === $abandoned ? '&mdash;' : esc_html( (string) $abandoned ); ?>
					</div>
					<p><?php esc_html_e( 'Currently abandoned checkouts detected today', 'eilmo-checkout-flow' ); ?></p>
				</section>

				<section class="eilmo-cf-dashboard__card eilmo-cf-dashboard__card--attention<?php echo $attention > 0 ? ' has-attention' : ''; ?>">
					<div class="eilmo-cf-dashboard__label"><?php esc_html_e( 'Needs Attention Today', 'eilmo-checkout-flow' ); ?></div>
					<div class="eilmo-cf-dashboard__value"><?php echo esc_html( (string) $attention ); ?></div>
					<div class="eilmo-cf-dashboard__attention-breakdown" aria-label="<?php esc_attr_e( 'Attention breakdown', 'eilmo-checkout-flow' ); ?>">
						<span><?php echo esc_html( sprintf( __( '%d payment', 'eilmo-checkout-flow' ), (int) $today['payment_verification'] ) ); ?></span>
						<span><?php echo esc_html( sprintf( __( '%d tracking', 'eilmo-checkout-flow' ), (int) $today['meta_failed'] ) ); ?></span>
						<span><?php echo esc_html( sprintf( __( '%d courier', 'eilmo-checkout-flow' ), (int) $today['courier_pending'] ) ); ?></span>
					</div>
				</section>
			</div>

			<?php $this->render_integration_health(); ?>

			<section class="eilmo-cf-dashboard__actions">
				<h2><?php esc_html_e( 'Quick Actions', 'eilmo-checkout-flow' ); ?></h2>
				<p><?php esc_html_e( 'Jump directly to the areas you use most.', 'eilmo-checkout-flow' ); ?></p>

				<div class="eilmo-cf-dashboard__actions-list">
					<a class="button button-primary" href="<?php echo esc_url( $this->get_settings_url() ); ?>"><?php esc_html_e( 'Checkout Settings', 'eilmo-checkout-flow' ); ?></a>
					<a class="button" href="<?php echo esc_url( $this->get_orders_url() ); ?>"><?php esc_html_e( 'WooCommerce Orders', 'eilmo-checkout-flow' ); ?></a>
					<a class="button" href="<?php echo esc_url( $this->get_admin_page_url( self::INTEGRATIONS_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Integrations', 'eilmo-checkout-flow' ); ?></a>
					<a class="button" href="<?php echo esc_url( $this->get_admin_page_url( self::ABANDONED_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Abandoned Checkouts', 'eilmo-checkout-flow' ); ?></a>
					<a class="button" href="<?php echo esc_url( $this->get_admin_page_url( self::SECURITY_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Security', 'eilmo-checkout-flow' ); ?></a>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Available order trend periods.
	 *
	 * @return array<string,string>
	 */
	private function get_period_options(): array {
		return array(
			'today'      => __( 'Today', 'eilmo-checkout-flow' ),
			'last_7_days' => __( 'Last 7 days', 'eilmo-checkout-flow' ),
			'this_month' => __( 'This month', 'eilmo-checkout-flow' ),
			'last_month' => __( 'Last month', 'eilmo-checkout-flow' ),
		);
	}

	/** Get the read-only period parameter, falling back to today. */
	private function get_selected_period(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dashboard filter.
		$period = isset( $_GET['period'] ) && is_string( $_GET['period'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dashboard filter.
			? sanitize_key( wp_unslash( $_GET['period'] ) )
			: 'today';

		return array_key_exists( $period, $this->get_period_options() ) ? $period : 'today';
	}

	/**
	 * Get inclusive store-local timestamps for a preset period.
	 *
	 * @param string $period Selected period.
	 * @return array{0:\DateTimeImmutable,1:\DateTimeImmutable}
	 */
	private function get_period_range( string $period ): array {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );

		switch ( $period ) {
			case 'last_7_days':
				$start = $today->modify( '-6 days' );
				$end   = $today->modify( '+1 day' );
				break;
			case 'this_month':
				$start = $today->modify( 'first day of this month' );
				$end   = $today->modify( '+1 day' );
				break;
			case 'last_month':
				$start = $today->modify( 'first day of last month' );
				$end   = $today->modify( 'first day of this month' );
				break;
			case 'today':
			default:
				$start = $today;
				$end   = $today->modify( '+1 day' );
		}

		return array( $start, $end->modify( '-1 second' ) );
	}

	/**
	 * Build the operational dashboard snapshot.
	 *
	 * High-volume stores can create many orders in a day. The dashboard does
	 * not need second-by-second precision, so the expensive order hydration is
	 * cached briefly and reused across page refreshes.
	 *
	 * @param \DateTimeImmutable $start First day of the period.
	 * @param \DateTimeImmutable $end   Last second of the period.
	 * @return array{orders:int,revenue:float,meta_failed:int,courier_pending:int,payment_verification:int}
	 */
	private function get_dashboard_snapshot( \DateTimeImmutable $start, \DateTimeImmutable $end ): array {

		$cache_key =
			self::SNAPSHOT_CACHE_PREFIX .
			$start->format( 'Ymd' ) . '_' . $end->format( 'Ymd' );

		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return array(
				'orders'               => max( 0, absint( $cached['orders'] ?? 0 ) ),
				'revenue'              => max( 0.0, (float) ( $cached['revenue'] ?? 0 ) ),
				'meta_failed'          => max( 0, absint( $cached['meta_failed'] ?? 0 ) ),
				'courier_pending'      => max( 0, absint( $cached['courier_pending'] ?? 0 ) ),
				'payment_verification' => max( 0, absint( $cached['payment_verification'] ?? 0 ) ),
			);
		}

		$is_today = $start->format( 'Ymd' ) === wp_date( 'Ymd' )
			&& $end->format( 'Ymd' ) === wp_date( 'Ymd' );

		$snapshot = array(
			'orders'               => 0,
			'revenue'              => 0.0,
			'meta_failed'          => 0,
			'courier_pending'      => 0,
			'payment_verification' => 0,
		);

		// Read in batches so a monthly view does not hydrate every order at once.
		for ( $page = 1; ; ++$page ) {
			$orders = $this->get_period_orders( $start, $end, $page );
			if ( empty( $orders ) ) {
				break;
			}

			$snapshot['orders']  += count( $orders );
			$snapshot['revenue'] += $this->get_paid_order_value( $orders );

			if ( $is_today ) {
				$meta = $this->get_meta_purchase_metrics( $orders );
				$snapshot['meta_failed']          += max( 0, absint( $meta['failed'] ?? 0 ) );
				$snapshot['courier_pending']      += $this->get_courier_pending_count( $orders );
				$snapshot['payment_verification'] += $this->get_payment_verification_count( $orders );
			}

			if ( count( $orders ) < 200 ) {
				break;
			}
		}

		$snapshot['revenue'] = round( $snapshot['revenue'], 2 );

		set_transient(
			$cache_key,
			$snapshot,
			self::SNAPSHOT_CACHE_TTL
		);

		return $snapshot;
	}

	/**
	 * Count orders waiting for manual payment verification.
	 *
	 * @param array<int, WC_Order> $orders Orders.
	 *
	 * @return int
	 */
	private function get_payment_verification_count( array $orders ): int {

		$count = 0;

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if (
				'awaiting_verification' ===
				sanitize_key(
					(string) $order->get_meta(
						'_eilmo_cf_payment_status',
						true
					)
				)
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Render a compact integration-health overview without remote requests.
	 *
	 * @return void
	 */
	private function render_integration_health(): void {

		$defaults = CheckoutSettings::get_defaults();
		$stored   = get_option( CheckoutSettings::OPTION_NAME, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$general  = array_replace(
			is_array( $defaults['general'] ?? null ) ? $defaults['general'] : array(),
			is_array( $stored['general'] ?? null ) ? $stored['general'] : array()
		);

		$items = array(
			array(
				'label'  => __( 'License', 'eilmo-checkout-flow' ),
				'active' => true,
				'url'    => admin_url( 'admin.php?page=' . HelpCenterPage::PAGE_SLUG . '&tab=' . HelpCenterPage::TAB_LICENSE ),
			),
			array(
				'label'  => __( 'Courier', 'eilmo-checkout-flow' ),
				'active' => 'yes' === (string) ( $general['courier'] ?? 'no' ),
				'url'    => $this->get_integration_url( IntegrationsPage::TAB_COURIER ),
			),
			array(
				'label'  => __( 'Meta', 'eilmo-checkout-flow' ),
				'active' => 'yes' === (string) ( $general['meta_tracking'] ?? 'no' ),
				'url'    => $this->get_integration_url( IntegrationsPage::TAB_META_TRACKING ),
			),
			array(
				'label'  => __( 'WhatsApp', 'eilmo-checkout-flow' ),
				'active' => 'yes' === (string) ( $general['whatsapp_ordering'] ?? 'no' ),
				'url'    => $this->get_integration_url( IntegrationsPage::TAB_WHATSAPP ),
			),
		);
		?>
		<section class="eilmo-cf-dashboard__health">
			<div class="eilmo-cf-dashboard__section-heading">
				<div>
					<h2><?php esc_html_e( 'Integration Health', 'eilmo-checkout-flow' ); ?></h2>
					<p><?php esc_html_e( 'A quick status view without contacting external services.', 'eilmo-checkout-flow' ); ?></p>
				</div>
				<a href="<?php echo esc_url( $this->get_admin_page_url( self::INTEGRATIONS_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Manage integrations', 'eilmo-checkout-flow' ); ?></a>
			</div>

			<div class="eilmo-cf-dashboard__health-grid">
				<?php foreach ( $items as $item ) : ?>
					<a class="eilmo-cf-dashboard__health-item" href="<?php echo esc_url( (string) $item['url'] ); ?>">
						<span class="eilmo-cf-dashboard__health-name"><?php echo esc_html( (string) $item['label'] ); ?></span>
						<span class="eilmo-cf-dashboard__health-status <?php echo ! empty( $item['active'] ) ? 'is-active' : 'is-off'; ?>">
							<?php echo ! empty( $item['active'] ) ? esc_html__( 'Active', 'eilmo-checkout-flow' ) : esc_html__( 'Off', 'eilmo-checkout-flow' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the only screen available before successful activation.
	 *
	 * @return void
	 */
	private function render_activation_dashboard(): void {
		?>
		<div class="wrap eilmo-cf-dashboard eilmo-cf-admin eilmo-cf-help">
			<?php settings_errors(); ?>
			<div class="eilmo-cf-dashboard__header">
				<div>
					<h1><?php esc_html_e( 'Activate Checkout Flow', 'eilmo-checkout-flow' ); ?></h1>
					<p><?php esc_html_e( 'Connect a valid license to unlock the Checkout Flow dashboard and features.', 'eilmo-checkout-flow' ); ?></p>
				</div>
			</div>
			<div class="eilmo-cf-help__content">
				<?php ( new HelpCenterPage() )->render_license_panel(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get WooCommerce orders created in the requested period.
	 *
	 * Uses WC CRUD so this remains compatible with
	 * both HPOS and legacy order storage.
	 *
	 * @param \DateTimeImmutable $start First day of the period.
	 * @param \DateTimeImmutable $end   Last second of the period.
	 * @param int                $page  Page of 200 orders.
	 * @return array<int, WC_Order>
	 */
	private function get_period_orders( \DateTimeImmutable $start, \DateTimeImmutable $end, int $page ): array {

		if (
			! function_exists(
				'wc_get_orders'
			) ||
			! function_exists(
				'wc_get_order_statuses'
			)
		) {
			return array();
		}

		$statuses =
			array_values(
				array_filter(
					array_keys(
						wc_get_order_statuses()
					),
					static function (
						string $status
					): bool {

						return ! in_array(
							$status,
							array(
								'wc-checkout-draft',
								'checkout-draft',
							),
							true
						);
					}
				)
			);

		try {

			$orders =
				wc_get_orders(
					array(
						'type' =>
							'shop_order',

						'status' =>
							$statuses,

						'date_created' =>
							$start->getTimestamp() .
							'...' .
							$end->getTimestamp(),

						'limit' =>
							200,

						'paged' =>
							$page,

						'return' =>
							'objects',
					)
				);

		} catch ( \Throwable $throwable ) {

			return array();
		}

		if (
			! is_array(
				$orders
			)
		) {
			return array();
		}

		return array_values(
			array_filter(
				$orders,
				static function (
					$order
				): bool {

					return $order instanceof
						WC_Order;
				}
			)
		);
	}

	/**
	 * Get confirmed payment received for orders created in the period.
	 * COD balances and unverified manual payments are not revenue received.
	 *
	 * @param array<int, WC_Order> $orders Orders.
	 *
	 * @return float
	 */
	private function get_paid_order_value(
		array $orders
	): float {

		$paid_statuses =
			function_exists(
				'wc_get_is_paid_statuses'
			)
				? array_map(
					array(
						$this,
						'normalize_status',
					),
					wc_get_is_paid_statuses()
				)
				: array(
					'processing',
					'completed',
				);

		$total =
			0.0;

		foreach ( $orders as $order ) {

			if (
				! $order instanceof
					WC_Order
			) {
				continue;
			}

			$status =
				$this->normalize_status(
					$order->get_status()
				);
			$type = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) );
			$payment_status = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_status', true ) );
			$amount = (float) $order->get_total();

			if ( 'cash_on_delivery' === $type || 'cod' === $order->get_payment_method() ) {
				// A COD order is not a payment merely because WooCommerce processes it.
				if ( 'paid' !== $payment_status || (float) $order->get_meta( '_eilmo_cf_remaining_due', true ) > 0 ) {
					continue;
				}
			} elseif ( in_array( $type, array( 'advance', 'full' ), true ) ) {
				if ( 'paid' !== $payment_status ) {
					continue;
				}
				$amount = min( $amount, max( 0.0, (float) $order->get_meta( '_eilmo_cf_pay_now', true ) ) );
			} elseif ( ! in_array( $status, $paid_statuses, true ) ) {
				continue;
			}

			if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				continue;
			}

			$total += $amount;
		}

		return max(
			0.0,
			round(
				$total,
				2
			)
		);
	}

	/**
	 * Get Abandoned Checkout count.
	 *
	 * Dashboard must not guess the Abandoned Checkout
	 * storage table/repository.
	 *
	 * Count checkouts marked abandoned during the current store-local day.
	 * The filter remains available for installations using another provider.
	 *
	 * @return int|null
	 */
	private function get_abandoned_checkout_count(): ?int {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$count = ( new AbandonedCheckoutRepository() )->count_abandoned_between(
			$today,
			$today->modify( '+1 day' )
		);

		$count = apply_filters( 'eilmo_cf/dashboard/abandoned_checkout_count', $count );

		if (
			! is_numeric(
				$count
			)
		) {
			return null;
		}

		return max(
			0,
			(int) $count
		);
	}

	/**
	 * Get Meta Purchase metrics.
	 *
	 * Existing Purchase order metadata is the
	 * authoritative dashboard source.
	 *
	 * @param array<int, WC_Order> $orders Orders.
	 *
	 * @return array{sent:int,waiting:int,failed:int}
	 */
	private function get_meta_purchase_metrics(
		array $orders
	): array {

		$result =
			array(
				'sent' =>
					0,

				'waiting' =>
					0,

				'failed' =>
					0,
			);

		$can_wait =
			MetaTrackingSettings::is_enabled() &&
			MetaTrackingSettings::event_is_enabled(
				'purchase'
			) &&
			MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			);

		$eligibility =
			class_exists(
				MetaOrderEligibility::class
			)
				? new MetaOrderEligibility()
				: null;

		foreach ( $orders as $order ) {

			if (
				! $order instanceof
					WC_Order
			) {
				continue;
			}

			$sent =
				'yes' ===
				(string) $order->get_meta(
					self::META_PURCHASE_SENT,
					true
				);

			if ( $sent ) {

				++$result[
					'sent'
				];

				continue;
			}

			$error =
				$order->get_meta(
					self::META_PURCHASE_LAST_ERROR,
					true
				);

			$has_error =
				is_array(
					$error
				)
					? ! empty(
						$error
					)
					: '' !== trim(
						(string) $error
					);

			if ( $has_error ) {

				++$result[
					'failed'
				];

				continue;
			}

			if (
				! $can_wait ||
				$this->is_terminal_status(
					$order->get_status()
				)
			) {
				continue;
			}

			if (
				$eligibility instanceof
					MetaOrderEligibility &&
				! $eligibility->is_website_checkout_order(
					$order
				)
			) {
				continue;
			}

			++$result[
				'waiting'
			];
		}

		return $result;
	}

	/**
	 * Count today's courier-pending orders.
	 *
	 * This method reads saved booking metadata only.
	 * It never refreshes Steadfast or Pathao status.
	 *
	 * @param array<int, WC_Order> $orders Orders.
	 *
	 * @return int
	 */
	private function get_courier_pending_count(
		array $orders
	): int {

		if (
			! CourierSettings::feature_is_enabled(
				'courier_one_click'
			) ||
			! class_exists(
				CourierBookingService::class
			)
		) {
			return 0;
		}

		$service =
			new CourierBookingService();

		$count =
			0;

		foreach ( $orders as $order ) {

			if (
				! $order instanceof
					WC_Order
			) {
				continue;
			}

			if (
				! in_array(
					$order->get_status(),
					array(
						'pending',
						'on-hold',
						'processing',
					),
					true
				)
			) {
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

			if (
				empty(
					$steadfast[
						'sent'
					]
				) &&
				empty(
					$pathao[
						'sent'
					]
				)
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Render number card.
	 *
	 * @param string $title       Title.
	 * @param int    $value       Value.
	 * @param string $description Description.
	 * @param string $month_context Current-month context.
	 *
	 * @return void
	 */
	private function render_number_card(
		string $title,
		int $value,
		string $description,
		string $month_context = ''
	): void {

		?>
		<section class="eilmo-cf-dashboard__card">

			<div class="eilmo-cf-dashboard__label">
				<?php
				echo esc_html(
					$title
				);
				?>
			</div>

			<div class="eilmo-cf-dashboard__value">
				<?php
				echo esc_html(
					(string) $value
				);
				?>
			</div>

			<p>
				<?php
				echo esc_html(
					$description
				);
				?>
			</p>

			<?php if ( '' !== $month_context ) : ?>
				<div class="eilmo-cf-dashboard__month-context"><?php echo esc_html( $month_context ); ?></div>
			<?php endif; ?>

		</section>
		<?php
	}

	/**
	 * Normalize WooCommerce order status.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function normalize_status(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if (
			0 === strpos(
				$status,
				'wc-'
			)
		) {
			$status =
				substr(
					$status,
					3
				);
		}

		return $status;
	}

	/**
	 * Determine whether order status is terminal.
	 *
	 * @param string $status Status.
	 *
	 * @return bool
	 */
	private function is_terminal_status(
		string $status
	): bool {

		return in_array(
			$this->normalize_status(
				$status
			),
			array(
				'cancelled',
				'failed',
				'refunded',
				'trash',
				'checkout-draft',
			),
			true
		);
	}

	/**
	 * Get Checkout Settings URL.
	 *
	 * @return string
	 */
	private function get_settings_url(): string {

		return admin_url(
			'admin.php?page=' .
			CheckoutSettingsPage::PAGE_SLUG
		);
	}

	/**
	 * Get WooCommerce Orders URL.
	 *
	 * Supports both HPOS and legacy order screens.
	 *
	 * @return string
	 */
	private function get_orders_url(): string {

		try {

			if (
				class_exists(
					OrderUtil::class
				) &&
				OrderUtil::custom_orders_table_usage_is_enabled()
			) {
				return admin_url(
					'admin.php?page=wc-orders'
				);
			}

		} catch ( \Throwable $throwable ) {

			/*
			 * Fall through to legacy URL.
			 */
		}

		return admin_url(
			'edit.php?post_type=shop_order'
		);
	}

	/**
	 * Get Eilmo admin page URL.
	 *
	 * @param string $slug Page slug.
	 *
	 * @return string
	 */
	private function get_admin_page_url(
		string $slug
	): string {

		return admin_url(
			'admin.php?page=' .
			sanitize_key(
				$slug
			)
		);
	}

	/**
	 * Get an Integration tab URL.
	 *
	 * @param string $tab Integration tab.
	 *
	 * @return string
	 */
	private function get_integration_url(
		string $tab
	): string {

		return add_query_arg(
			array(
				'page' => self::INTEGRATIONS_PAGE_SLUG,
				'tab'  => sanitize_key( $tab ),
			),
			admin_url( 'admin.php' )
		);
	}

}
