<?php
/**
 * Abandoned checkouts page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\AbandonedCheckout\AbandonedCheckoutRepository;
use EilmoCheckout\Admin\CourierSettings;
use EilmoCheckout\Couriers\Services\CourierSuccessService;
use EilmoCheckout\Couriers\Services\FraudRiskService;
use EilmoCheckout\Couriers\Services\LocalCustomerHistoryService;

defined( 'ABSPATH' ) || exit;

/**
 * Abandoned checkouts page.
 */
final class AbandonedCheckoutsPage {

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-abandoned';

	/**
	 * Records per page.
	 */
	private const PER_PAGE =
		20;

	/**
	 * Render page.
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

		$status =
			$this->get_status_filter();

		$search =
			$this->get_search_query();

		$current_page =
			$this->get_current_page();

		$repository =
			new AbandonedCheckoutRepository();

		$result =
			$repository->get_all(
				array(
					'status' =>
						$status,

					'search' =>
						$search,

					'page' =>
						$current_page,

					'per_page' =>
						self::PER_PAGE,
				)
			);

		$items =
			is_array(
				$result[
					'items'
				] ??
					null
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

		?>
		<div class="wrap eilmo-cf-admin eilmo-cf-abandoned">

			<div class="eilmo-cf-admin__header">

				<div class="eilmo-cf-admin__heading">

					<h1>
						<?php
						esc_html_e(
							'Abandoned Checkouts',
							'eilmo-checkout-flow'
						);
						?>
					</h1>

					<p>
						<?php
						esc_html_e(
							'Track checkout activity and manually manage customer follow-up status.',
							'eilmo-checkout-flow'
						);
						?>
					</p>

				</div>

			</div>

			<?php
			$this->render_notice();
			?>

			<div class="eilmo-cf-abandoned__toolbar">

				<?php
				$this->render_filters(
					$status,
					$search
				);
				?>

				<div class="eilmo-cf-abandoned__count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Number of abandoned checkouts. */
							_n(
								'%d checkout',
								'%d checkouts',
								$total,
								'eilmo-checkout-flow'
							),
							$total
						)
					);
					?>
				</div>

			</div>

			<div class="eilmo-cf-abandoned__table-wrap">

				<table
					class="widefat fixed striped eilmo-cf-abandoned__table"
				>

					<thead>
						<tr>

							<th class="column-customer">
								<?php
								esc_html_e(
									'Customer',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-contact">
								<?php
								esc_html_e(
									'Contact',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-address">
								<?php esc_html_e( 'Address', 'eilmo-checkout-flow' ); ?>
							</th>

							<th class="column-products">
								<?php
								esc_html_e(
									'Products',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-total">
								<?php
								esc_html_e(
									'Total',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-courier">
								<?php
								esc_html_e(
									'Courier Performance',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-status">
								<?php
								esc_html_e(
									'Status',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-activity">
								<?php
								esc_html_e(
									'Last Activity',
									'eilmo-checkout-flow'
								);
								?>
							</th>

							<th class="column-actions">
								<?php
								esc_html_e(
									'Actions',
									'eilmo-checkout-flow'
								);
								?>
							</th>

						</tr>
					</thead>

					<tbody>

						<?php
						if ( empty( $items ) ) {

							$this->render_empty_row();

						} else {

							foreach (
								$items as $item
							) {

								if (
									is_array(
										$item
									)
								) {
									$this->render_row(
										$item
									);
								}
							}
						}
						?>

					</tbody>

				</table>

			</div>

			<?php
			$this->render_pagination(
				$current_page,
				$total_pages,
				$status,
				$search
			);
			?>

		</div>
		<?php
	}

	/**
	 * Render filters.
	 *
	 * @param string $status Status.
	 * @param string $search Search.
	 *
	 * @return void
	 */
	private function render_filters(
		string $status,
		string $search
	): void {
		?>
		<form
			method="get"
			class="eilmo-cf-abandoned__filters"
		>

			<input
				type="hidden"
				name="page"
				value="<?php echo esc_attr( self::PAGE_SLUG ); ?>"
			>

			<select name="status">

				<option value="">
					<?php
					esc_html_e(
						'All statuses',
						'eilmo-checkout-flow'
					);
					?>
				</option>

				<?php
				foreach (
					$this->get_statuses() as
						$key => $label
				) {
					?>
					<option
						value="<?php echo esc_attr( $key ); ?>"
						<?php selected( $status, $key ); ?>
					>
						<?php
						echo esc_html(
							$label
						);
						?>
					</option>
					<?php
				}
				?>

			</select>

			<input
				type="search"
				name="s"
				value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php
				echo esc_attr__(
					'Name, phone, email or address',
					'eilmo-checkout-flow'
				);
				?>"
			>

			<button
				type="submit"
				class="button"
			>
				<?php
				esc_html_e(
					'Filter',
					'eilmo-checkout-flow'
				);
				?>
			</button>

			<?php
			if (
				'' !== $status ||
				'' !== $search
			) {
				?>
				<a
					href="<?php echo esc_url( $this->get_page_url() ); ?>"
					class="button"
				>
					<?php
					esc_html_e(
						'Reset',
						'eilmo-checkout-flow'
					);
					?>
				</a>
				<?php
			}
			?>

		</form>
		<?php
	}

	/**
	 * Render row.
	 *
	 * @param array<string,mixed> $item Item.
	 *
	 * @return void
	 */
	private function render_row(
		array $item
	): void {

		$id =
			absint(
				$item[
					'id'
				] ??
					0
			);

		if ( $id <= 0 ) {
			return;
		}

		$name =
			trim(
				sanitize_text_field(
					(string) (
						$item[
							'billing_first_name'
						] ??
							''
					)
				) .
				' ' .
				sanitize_text_field(
					(string) (
						$item[
							'billing_last_name'
						] ??
							''
					)
				)
			);

		$phone =
			sanitize_text_field(
				(string) (
					$item[
						'billing_phone'
					] ??
						''
				)
			);

		$email =
			sanitize_email(
				(string) (
					$item[
						'billing_email'
					] ??
						''
				)
			);

		$address_line_1 = sanitize_text_field( (string) ( $item['billing_address_1'] ?? '' ) );
		$address_city = sanitize_text_field( (string) ( $item['billing_city'] ?? '' ) );

		$address_display = implode(
			', ',
			array_values(
				array_filter(
					array( $address_line_1, $address_city ),
					static function ( $value ): bool {
						return '' !== trim( (string) $value );
					}
				)
			)
		);

		$status =
			sanitize_key(
				(string) (
					$item[
						'status'
					] ??
						''
				)
			);

		?>
		<tr>

			<td class="column-customer">

				<strong>
					<?php
					echo esc_html(
						'' !== $name
							? $name
							: __(
								'Guest Customer',
								'eilmo-checkout-flow'
							)
					);
					?>
				</strong>

				<div class="eilmo-cf-abandoned__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: Abandoned checkout record ID. */
							__(
								'Checkout #%d',
								'eilmo-checkout-flow'
							),
							$id
						)
					);
					?>
				</div>

			</td>

			<td class="column-contact">

				<?php
				if ( '' !== $phone ) {
					?>
					<div>
						<strong>
							<?php
							esc_html_e(
								'Phone:',
								'eilmo-checkout-flow'
							);
							?>
						</strong>

						<?php
						echo esc_html(
							$phone
						);
						?>
					</div>
					<?php
				}

				if ( '' !== $email ) {
					?>
					<div>
						<strong>
							<?php
							esc_html_e(
								'Email:',
								'eilmo-checkout-flow'
							);
							?>
						</strong>

						<a
							href="<?php echo esc_url( 'mailto:' . $email ); ?>"
						>
							<?php
							echo esc_html(
								$email
							);
							?>
						</a>
					</div>
					<?php
				}

				if (
					'' === $phone &&
					'' === $email
				) {
					echo esc_html( '—' );
				}
				?>

			</td>

			<td class="column-address">
				<?php echo esc_html( '' !== $address_display ? $address_display : '—' ); ?>
			</td>

			<td class="column-products">
				<?php
				$this->render_products(
					$item
				);
				?>
			</td>

			<td class="column-total">
				<strong>
					<?php
					echo wp_kses_post(
						$this->format_total(
							$item
						)
					);
					?>
				</strong>
			</td>

			<td class="column-courier">
				<?php
				$this->render_courier_performance(
					$id,
					$phone
				);
				?>
			</td>

			<td class="column-status">
				<?php
				$this->render_status(
					$status
				);
				?>
			</td>

			<td class="column-activity">
				<?php
				$this->render_activity(
					$item
				);
				?>
			</td>

			<td class="column-actions">
				<?php
				$this->render_actions(
					$id,
					$status
				);
				?>
			</td>

		</tr>
		<?php
	}

	/**
	 * Render saved courier history and the configured risk band.
	 *
	 * This method only reads the durable phone cache. The provider API is used
	 * exclusively when an administrator presses the Check/Refresh button.
	 *
	 * @param int    $checkout_id Checkout ID.
	 * @param string $phone       Customer phone.
	 *
	 * @return void
	 */
	private function render_courier_performance(
		int $checkout_id,
		string $phone
	): void {

		$courier = new CourierSuccessService();
		$phone = $courier->normalize_phone( $phone );
		$snapshot = '' !== $phone
			? $courier->get_live_cached_snapshot( $phone, true )
			: array();
		$has_data = ! empty( $snapshot ) && $courier->snapshot_has_courier_data( $snapshot );
		$is_stale = $has_data && $courier->snapshot_is_stale( $snapshot );
		$stats = $has_data && isset( $snapshot['stats'] ) && is_array( $snapshot['stats'] )
			? $snapshot['stats']
			: array();
		$risk_service = new FraudRiskService();
		$decision = $has_data
			? $risk_service->evaluate( $snapshot )
			: array( 'band' => 'unknown', 'success_rate' => 0 );
		$band = sanitize_key( (string) ( $decision['band'] ?? 'unknown' ) );
		if ( ! in_array( $band, array( 'trusted', 'review', 'high', 'critical', 'unknown' ), true ) ) {
			$band = 'unknown';
		}
		$labels = array(
			'trusted'  => __( 'Trusted', 'eilmo-checkout-flow' ),
			'review'   => __( 'Review', 'eilmo-checkout-flow' ),
			'high'     => __( 'High Risk', 'eilmo-checkout-flow' ),
			'critical' => __( 'Critical', 'eilmo-checkout-flow' ),
			'unknown'  => __( 'No History', 'eilmo-checkout-flow' ),
		);
		?>
		<div class="eilmo-cf-abandoned-risk eilmo-cf-abandoned-risk--<?php echo esc_attr( $band ); ?><?php echo 'steadfast' === CourierSettings::get_success_provider() ? ' eilmo-cf-abandoned-risk--steadfast' : ''; ?>">
			<div class="eilmo-cf-abandoned-risk__providers">
				<?php
				$success_provider = CourierSettings::get_success_provider();
				if ( 'steadfast' === $success_provider ) {
					$provider = isset( $stats['steadfast'] ) && is_array( $stats['steadfast'] )
						? $stats['steadfast']
						: array();
					$available = ! empty( $provider['available'] ) && array_key_exists( 'delivery_ratio', $provider );
					$provider_band = $available
						? sanitize_key( (string) ( $risk_service->evaluate( array( 'stats' => array( 'steadfast' => $provider ) ) )['band'] ?? 'unknown' ) )
						: 'unknown';
					static $history_cache = array();
					$history = array();
					if ( '' !== $phone ) {
						if ( ! isset( $history_cache[ $phone ] ) ) {
							$history_cache[ $phone ] = ( new LocalCustomerHistoryService() )->lookup_summary( $phone );
						}
						$history = $history_cache[ $phone ];
					}
					$profile_payload = wp_json_encode( array(
						'provider' => 'steadfast',
						'profile' => $provider,
						'band' => $provider_band,
						'checked_at' => absint( $snapshot['checked_at'] ?? 0 ),
						'store' => $history,
					), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
					?>
					<div class="eilmo-cf-abandoned-risk__provider is-<?php echo esc_attr( $provider_band ); ?>">
						<strong>Steadfast</strong>
						<span class="eilmo-cf-abandoned-risk__combined-trigger" tabindex="0" data-eilmo-abandoned-courier-breakdown="<?php echo esc_attr( is_string( $profile_payload ) ? $profile_payload : '{}' ); ?>">
							<?php
							echo esc_html(
								$available
									? sprintf(
										'%s%% Delivered',
										rtrim( rtrim( number_format_i18n( (float) ( $provider['ratio'] ?? 0 ), 1 ), '0' ), '.' )
									)
									: __( 'No history', 'eilmo-checkout-flow' )
							);
							?>
						</span>
					</div>
					<?php
				} else {
					$total = 0;
					$success = 0;
					$cancel = 0;
					$steadfast_rate_only_display = '';
					$steadfast_rate_only_band = '';
					$breakdown = array();
					foreach ( $stats as $provider_key => $provider ) {
						if ( ! is_string( $provider_key ) || ! is_array( $provider ) || 'website' === $provider_key ) {
							continue;
						}

						$provider_total = max( 0, absint( $provider['total'] ?? 0 ) );
						$provider_success = max( 0, absint( $provider['success'] ?? 0 ) );
						$provider_cancel = max( 0, absint( $provider['cancel'] ?? max( 0, $provider_total - $provider_success ) ) );
						$provider_ratio = $provider_total > 0
							? ( $provider_success / $provider_total ) * 100
							: 0;
						$available = ! empty( $provider['available'] ) && $provider_total > 0;
						$provider_band = $available
							? sanitize_key( (string) ( $risk_service->evaluate( array( 'stats' => array( $provider_key => $provider ) ) )['band'] ?? 'unknown' ) )
							: 'unknown';

						if ( ! in_array( $provider_band, array( 'trusted', 'review', 'high', 'critical', 'unknown' ), true ) ) {
							$provider_band = 'unknown';
						}

						$total += $provider_total;
						$success += $provider_success;
						$cancel += $provider_cancel;

						$label = sanitize_text_field( (string) ( $provider['label'] ?? '' ) );
						if ( '' === $label ) {
							$label = ucwords( str_replace( array( '_', '-' ), ' ', sanitize_key( $provider_key ) ) );
						}
						$rate_only = 'steadfast' === $provider_key && ! empty( $provider['rate_only'] ) &&
							! empty( $provider['parcel_range'] ) && ! empty( $provider['volume_band'] );
						if ( $rate_only ) {
							$provider_ratio = max( 0, min( 100, (float) ( $provider['ratio'] ?? 0 ) ) );
							$provider_band = sanitize_key( (string) ( $risk_service->evaluate( array(
								'stats' => array( 'steadfast' => array(
									'delivery_ratio' => $provider_ratio,
									'volume_band' => $provider['volume_band'],
									'parcel_range' => $provider['parcel_range'],
								) ),
							) )['band'] ?? 'unknown' ) );
							$steadfast_rate_only_band = $provider_band;
							$steadfast_rate_only_display = sprintf(
								'%1$s (%2$s) %3$s%%',
								(string) $provider['parcel_range'],
								ucwords( str_replace( '_', ' ', (string) $provider['volume_band'] ) ),
								rtrim( rtrim( number_format_i18n( $provider_ratio, 1 ), '0' ), '.' )
							);
						}

						$breakdown[] = array(
							'key'       => sanitize_key( $provider_key ),
							'label'     => $label,
							'success'   => $provider_success,
							'cancel'    => $provider_cancel,
							'total'     => $provider_total,
							'ratio'     => $provider_ratio,
							'available' => $available,
							'band'      => $provider_band,
							'rate_only' => $rate_only,
							'parcel_range' => $rate_only ? (string) $provider['parcel_range'] : '',
							'volume_band' => $rate_only ? (string) $provider['volume_band'] : '',
						);
					}

					$ratio = $total > 0 ? ( $success / $total ) * 100 : 0;
					$settings = CourierSettings::get_settings();
					$ratio_api = isset( $settings['ratio_api'] ) && is_array( $settings['ratio_api'] )
						? $settings['ratio_api']
						: array();
					$show_store_history = 'yes' === ( $ratio_api['show_store_history'] ?? 'yes' );
					$store_history = null;
					if ( $show_store_history && '' !== $phone ) {
						static $bd_history_cache = array();
						if ( ! isset( $bd_history_cache[ $phone ] ) ) {
							$bd_history_cache[ $phone ] = ( new LocalCustomerHistoryService() )->lookup_summary( $phone );
						}
						$store_history = $bd_history_cache[ $phone ];
					}
					$breakdown_data = array(
							'success' => $success,
							'cancel'  => $cancel,
							'total'   => $total,
							'ratio'   => $ratio,
							'entries' => $breakdown,
						);
					if ( is_array( $store_history ) ) {
						$breakdown_data['store'] = $store_history;
					}
					$breakdown_payload = wp_json_encode(
						$breakdown_data,
						JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
					);
					?>
					<div class="eilmo-cf-abandoned-risk__provider is-<?php echo esc_attr( $total > 0 || '' === $steadfast_rate_only_band ? $band : $steadfast_rate_only_band ); ?>">
						<strong><?php esc_html_e( 'All Couriers', 'eilmo-checkout-flow' ); ?></strong>
						<span
							class="eilmo-cf-abandoned-risk__combined-trigger"
							tabindex="0"
							data-eilmo-abandoned-courier-breakdown="<?php echo esc_attr( is_string( $breakdown_payload ) ? $breakdown_payload : '{}' ); ?>"
							aria-label="<?php esc_attr_e( 'Combined BD Courier delivery history. Hover for courier breakdown.', 'eilmo-checkout-flow' ); ?>"
						>
							<?php
							echo esc_html(
								$total > 0
									? sprintf(
										'%1$d/%2$d (%3$s%%)',
										$success,
										$total,
										rtrim( rtrim( number_format_i18n( $ratio, 1 ), '0' ), '.' )
									)
									: ( '' !== $steadfast_rate_only_display ? $steadfast_rate_only_display : __( 'No history', 'eilmo-checkout-flow' ) )
							);
							?>
						</span>
					</div>
					<?php
				}
				?>
			</div>

			<div class="eilmo-cf-abandoned-risk__summary">
				<span class="eilmo-cf-abandoned-risk__bar" aria-hidden="true">
					<?php
					$filled = $has_data ? (int) round( max( 0, min( 100, (float) ( $decision['success_rate'] ?? 0 ) ) ) / 10 ) : 0;
					for ( $segment = 1; $segment <= 10; ++$segment ) {
						echo '<i class="' . esc_attr( $segment <= $filled ? 'is-success' : ( $has_data ? 'is-failed' : 'is-empty' ) ) . '"></i>';
					}
					?>
				</span>
				<strong><?php echo esc_html( $labels[ $band ] ); ?></strong>
			</div>

			<?php
			$settings = CourierSettings::get_settings();
			$live = isset( $settings['live_fraud'] ) && is_array( $settings['live_fraud'] )
				? $settings['live_fraud']
				: array();
			if ( $has_data && 'yes' === (string) ( $live['show_cache_age'] ?? 'yes' ) ) :
				if ( $is_stale ) {
					/* translators: %s: Number of days since courier data was saved. */
					$age_format = __( 'Saved %s days ago · expired', 'eilmo-checkout-flow' );
				} else {
					/* translators: %s: Number of days since courier data was saved. */
					$age_format = __( 'Saved %s days ago', 'eilmo-checkout-flow' );
				}
				?>
				<small class="eilmo-cf-abandoned-risk__age">
					<?php
						echo esc_html(
							sprintf(
								$age_format,
							number_format_i18n( (float) ( $snapshot['cache_age_days'] ?? 0 ), 1 )
						)
					);
					?>
				</small>
			<?php endif; ?>

			<?php
			if ( '' !== $phone && 'yes' === (string) ( $live['manual_order_check'] ?? 'yes' ) ) :
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="eilmo_cf_abandoned_checkout_check_courier">
					<input type="hidden" name="checkout_id" value="<?php echo esc_attr( (string) $checkout_id ); ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>">
					<?php wp_nonce_field( 'eilmo_cf_abandoned_checkout_courier_' . $checkout_id ); ?>
					<button type="submit" class="button button-small">
						<?php echo esc_html( $has_data ? __( 'Refresh', 'eilmo-checkout-flow' ) : __( 'Check', 'eilmo-checkout-flow' ) ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render products.
	 *
	 * @param array<string,mixed> $item Item.
	 *
	 * @return void
	 */
	private function render_products(
		array $item
	): void {

		$snapshot =
			is_array(
				$item[
					'cart_snapshot'
				] ??
					null
			)
				? $item[
					'cart_snapshot'
				]
				: array();

		$items =
			is_array(
				$snapshot[
					'items'
				] ??
					null
			)
				? $snapshot[
					'items'
				]
				: array();

		if ( empty( $items ) ) {
			echo esc_html( '—' );

			return;
		}

		$shown =
			0;

		foreach (
			$items as $product_item
		) {

			if (
				$shown >= 3 ||
				! is_array(
					$product_item
				)
			) {
				continue;
			}

			$product_id =
				absint(
					$product_item[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$product_item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				absint(
					$product_item[
						'quantity'
					] ??
						0
				);

			if (
				$product_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			$product =
				function_exists(
					'wc_get_product'
				)
					? wc_get_product(
						$variation_id > 0
							? $variation_id
							: $product_id
					)
					: false;

			$product_name =
				$product
					? $product->get_name()
					: sprintf(
						/* translators: %d: WooCommerce product ID. */
						__(
							'Product #%d',
							'eilmo-checkout-flow'
						),
						$product_id
					);

			?>
			<div class="eilmo-cf-abandoned__product">

				<span>
					<?php
					echo esc_html(
						$product_name
					);
					?>
				</span>

				<span>
					<?php
					echo esc_html(
						'× ' .
						$quantity
					);
					?>
				</span>

			</div>
			<?php

			++$shown;
		}

		if ( 0 === $shown ) {
			echo esc_html( '—' );
		}
	}

	/**
	 * Render status badge.
	 *
	 * @param string $status Status.
	 *
	 * @return void
	 */
	private function render_status(
		string $status
	): void {

		$statuses =
			$this->get_statuses();

		$label =
			$statuses[
				$status
			] ??
				__(
					'Unknown',
					'eilmo-checkout-flow'
				);

		?>
		<span
			class="<?php
			echo esc_attr(
				'eilmo-cf-abandoned-status eilmo-cf-abandoned-status--' .
				$status
			);
			?>"
		>
			<?php
			echo esc_html(
				$label
			);
			?>
		</span>
		<?php
	}

	/**
	 * Render activity.
	 *
	 * @param array<string,mixed> $item Item.
	 *
	 * @return void
	 */
	private function render_activity(
		array $item
	): void {

		echo esc_html(
			$this->format_date(
				(string) (
					$item[
						'last_activity_at'
					] ??
						''
				)
			)
		);

		$abandoned_at =
			(string) (
				$item[
					'abandoned_at'
				] ??
					''
			);

		if ( '' !== $abandoned_at ) {
			?>
			<div class="eilmo-cf-abandoned__meta">
				<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Formatted abandoned checkout date and time. */
							__(
							'Abandoned: %s',
							'eilmo-checkout-flow'
						),
						$this->format_date(
							$abandoned_at
						)
					)
				);
				?>
			</div>
			<?php
		}
	}

	/**
	 * Render manual actions.
	 *
	 * @param int    $checkout_id Checkout ID.
	 * @param string $status      Current status.
	 *
	 * @return void
	 */
	private function render_actions(
		int $checkout_id,
		string $status
	): void {
		?>
		<div class="eilmo-cf-abandoned__actions">

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				class="eilmo-cf-abandoned__status-form"
			>

				<input
					type="hidden"
					name="action"
					value="eilmo_cf_abandoned_checkout_update_status"
				>

				<input
					type="hidden"
					name="checkout_id"
					value="<?php echo esc_attr( (string) $checkout_id ); ?>"
				>

				<input
					type="hidden"
					name="redirect_to"
					value="<?php echo esc_url( $this->get_current_url() ); ?>"
				>

				<?php
				wp_nonce_field(
					'eilmo_cf_abandoned_checkout_status_' .
						$checkout_id
				);
				?>

				<select name="checkout_status">

					<?php
					foreach (
						$this->get_statuses() as
							$key => $label
					) {
						?>
						<option
							value="<?php echo esc_attr( $key ); ?>"
							<?php selected( $status, $key ); ?>
						>
							<?php
							echo esc_html(
								$label
							);
							?>
						</option>
						<?php
					}
					?>

				</select>

				<button
					type="submit"
					class="button button-small"
				>
					<?php
					esc_html_e(
						'Update',
						'eilmo-checkout-flow'
					);
					?>
				</button>

			</form>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>

				<input
					type="hidden"
					name="action"
					value="eilmo_cf_abandoned_checkout_delete"
				>

				<input
					type="hidden"
					name="checkout_id"
					value="<?php echo esc_attr( (string) $checkout_id ); ?>"
				>

				<input
					type="hidden"
					name="redirect_to"
					value="<?php echo esc_url( $this->get_current_url() ); ?>"
				>

				<?php
				wp_nonce_field(
					'eilmo_cf_abandoned_checkout_delete_' .
						$checkout_id
				);
				?>

				<button
					type="submit"
					class="button button-small eilmo-cf-abandoned__delete"
				>
					<?php
					esc_html_e(
						'Delete',
						'eilmo-checkout-flow'
					);
					?>
				</button>

			</form>

		</div>
		<?php
	}

	/**
	 * Render empty row.
	 *
	 * @return void
	 */
	private function render_empty_row(): void {
		?>
		<tr>
			<td
				colspan="9"
				class="eilmo-cf-abandoned__empty"
			>
				<?php
				esc_html_e(
					'No checkout records found.',
					'eilmo-checkout-flow'
				);
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Format total.
	 *
	 * @param array<string,mixed> $item Item.
	 *
	 * @return string
	 */
	private function format_total(
		array $item
	): string {

		$total =
			is_numeric(
				$item[
					'total'
				] ??
					null
			)
				? max(
					0,
					(float) $item[
						'total'
					]
				)
				: 0;

		$currency =
			sanitize_text_field(
				(string) (
					$item[
						'currency'
					] ??
						''
				)
			);

		return function_exists(
			'wc_price'
		)
			? wc_price(
				$total,
				array(
					'currency' =>
						'' !== $currency
							? strtoupper(
								$currency
							)
							: get_woocommerce_currency(),
				)
			)
			: number_format_i18n(
				$total,
				2
			);
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notice(): void {

		$notice =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'eilmo_notice'
				]
			)
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'eilmo_notice'
						]
					)
				)
				: '';

		$notices =
			array(
				'status_updated' =>
					array(
						'success',
						__(
							'Checkout status updated.',
							'eilmo-checkout-flow'
						),
					),

				'status_failed' =>
					array(
						'error',
						__(
							'Checkout status could not be updated.',
							'eilmo-checkout-flow'
						),
					),

				'deleted' =>
					array(
						'success',
						__(
							'Checkout record deleted.',
							'eilmo-checkout-flow'
						),
					),

				'delete_failed' =>
					array(
						'error',
						__(
							'Checkout record could not be deleted.',
							'eilmo-checkout-flow'
						),
					),

				'courier_checked' =>
					array(
						'success',
						__( 'Courier performance updated.', 'eilmo-checkout-flow' ),
					),

				'courier_limit' =>
					array(
						'warning',
						__( 'Courier API usage is currently unavailable. The checkout record is unchanged; try again after quota is available.', 'eilmo-checkout-flow' ),
					),

				'courier_not_configured' =>
					array(
						'warning',
						__( 'Configure the credentials for the selected Courier Success source before checking this customer.', 'eilmo-checkout-flow' ),
					),

				'courier_check_disabled' =>
					array(
						'warning',
						__( 'Manual courier checks are disabled in Live Fraud settings.', 'eilmo-checkout-flow' ),
					),

				'courier_invalid_phone' =>
					array(
						'error',
						__( 'This checkout does not have a valid Bangladesh mobile number.', 'eilmo-checkout-flow' ),
					),

				'courier_check_failed' =>
					array(
						'error',
						__( 'Courier performance could not be refreshed. Saved data was not removed.', 'eilmo-checkout-flow' ),
					),
			);

		if (
			! isset(
				$notices[
					$notice
				]
			)
		) {
			return;
		}

		list(
			$type,
			$message
		) =
			$notices[
				$notice
			];

		?>
		<div
			class="<?php echo esc_attr( 'notice notice-' . $type . ' is-dismissible' ); ?>"
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
	 * Get statuses.
	 *
	 * @return array<string,string>
	 */
	private function get_statuses(): array {

		return array(
			AbandonedCheckoutRepository::STATUS_ACTIVE =>
				__(
					'Active',
					'eilmo-checkout-flow'
				),

			AbandonedCheckoutRepository::STATUS_ABANDONED =>
				__(
					'Abandoned',
					'eilmo-checkout-flow'
				),

			AbandonedCheckoutRepository::STATUS_RECOVERED =>
				__(
					'Recovered',
					'eilmo-checkout-flow'
				),

			AbandonedCheckoutRepository::STATUS_CONVERTED =>
				__(
					'Converted',
					'eilmo-checkout-flow'
				),
		);
	}

	/**
	 * Get status filter.
	 *
	 * @return string
	 */
	private function get_status_filter(): string {

		$status =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'status'
				]
			)
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'status'
						]
					)
				)
				: '';

		return array_key_exists(
			$status,
			$this->get_statuses()
		)
			? $status
			: '';
	}

	/**
	 * Get search.
	 *
	 * @return string
	 */
	private function get_search_query(): string {

		return isset(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
			$_GET[
				's'
			]
		)
			? sanitize_text_field(
				wp_unslash(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
					$_GET[
						's'
					]
				)
			)
			: '';
	}

	/**
	 * Current pagination page.
	 *
	 * @return int
	 */
	private function get_current_page(): int {

		return max(
			1,
			absint(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'paged'
				] ??
					1
			)
		);
	}

	/**
	 * Format UTC date.
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
			'0000-00-00 00:00:00' ===
				$date
		) {
			return '—';
		}

		return get_date_from_gmt(
			$date,
			get_option(
				'date_format'
			) .
			' ' .
			get_option(
				'time_format'
			)
		);
	}

	/**
	 * Render pagination.
	 *
	 * @param int    $current_page Current page.
	 * @param int    $total_pages  Total pages.
	 * @param string $status       Status.
	 * @param string $search       Search.
	 *
	 * @return void
	 */
	private function render_pagination(
		int $current_page,
		int $total_pages,
		string $status,
		string $search
	): void {

		if ( $total_pages <= 1 ) {
			return;
		}

		$args =
			array(
				'page' =>
					self::PAGE_SLUG,

				'paged' =>
					999999999,
			);

		if ( '' !== $status ) {
			$args[
				'status'
			] =
				$status;
		}

		if ( '' !== $search ) {
			$args[
				's'
			] =
				$search;
		}

		$base =
			str_replace(
				'999999999',
				'%#%',
				add_query_arg(
					$args,
					admin_url(
						'admin.php'
					)
				)
			);

		$links =
			paginate_links(
				array(
					'base' =>
						$base,

					'current' =>
						$current_page,

					'total' =>
						$total_pages,

					'type' =>
						'list',
				)
			);

		if ( $links ) {
			?>
			<nav class="eilmo-cf-abandoned__pagination">
				<?php
				echo wp_kses_post(
					$links
				);
				?>
			</nav>
			<?php
		}
	}

	/**
	 * Get page URL.
	 *
	 * @return string
	 */
	private function get_page_url(): string {

		return add_query_arg(
			array(
				'page' =>
					self::PAGE_SLUG,
			),
			admin_url(
				'admin.php'
			)
		);
	}

	/**
	 * Get current filtered URL.
	 *
	 * @return string
	 */
	private function get_current_url(): string {

		$args =
			array(
				'page' =>
					self::PAGE_SLUG,
			);

		$status =
			$this->get_status_filter();

		$search =
			$this->get_search_query();

		$paged =
			$this->get_current_page();

		if ( '' !== $status ) {
			$args[
				'status'
			] =
				$status;
		}

		if ( '' !== $search ) {
			$args[
				's'
			] =
				$search;
		}

		if ( $paged > 1 ) {
			$args[
				'paged'
			] =
				$paged;
		}

		return add_query_arg(
			$args,
			admin_url(
				'admin.php'
			)
		);
	}
}
