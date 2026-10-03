<?php
/**
 * WooCommerce Order List blacklist actions.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Orders;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Security\Blacklist\BlacklistRepository;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds quick Customer Blacklist actions to the
 * WooCommerce Orders list.
 */
final class OrderBlacklistActions implements RegistrableInterface {

	/**
	 * Orders list Security column.
	 *
	 * @var string
	 */
	private const COLUMN =
		'eilmo_cf_security';

	/**
	 * Admin action.
	 *
	 * @var string
	 */
	private const ACTION =
		'eilmo_cf_order_blacklist';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * ---------------------------------------------
		 * HPOS Orders screen
		 * ---------------------------------------------
		 */
		add_filter(
			'manage_woocommerce_page_wc-orders_columns',
			array(
				$this,
				'add_security_column',
			),
			20
		);

		add_action(
			'manage_woocommerce_page_wc-orders_custom_column',
			array(
				$this,
				'render_hpos_column',
			),
			20,
			2
		);

		/*
		 * ---------------------------------------------
		 * Legacy Orders screen
		 * ---------------------------------------------
		 */
		add_filter(
			'manage_edit-shop_order_columns',
			array(
				$this,
				'add_security_column',
			),
			20
		);

		add_filter(
			'manage_shop_order_posts_columns',
			array(
				$this,
				'add_security_column',
			),
			20
		);

		add_action(
			'manage_shop_order_posts_custom_column',
			array(
				$this,
				'render_legacy_column',
			),
			20,
			2
		);

		/*
		 * ---------------------------------------------
		 * Block action
		 * ---------------------------------------------
		 */
		add_action(
			'admin_post_' .
				self::ACTION,
			array(
				$this,
				'handle_block',
			)
		);

		/*
		 * ---------------------------------------------
		 * Orders list notice
		 * ---------------------------------------------
		 */
		add_action(
			'admin_notices',
			array(
				$this,
				'render_notice',
			)
		);

		/*
		 * ---------------------------------------------
		 * Orders list Security column styles
		 * ---------------------------------------------
		 */
		add_action(
			'admin_head',
			array(
				$this,
				'render_styles',
			)
		);
	}

	/**
	 * Add Security column.
	 *
	 * @param array<string,string> $columns Columns.
	 *
	 * @return array<string,string>
	 */
	public function add_security_column(
		array $columns
	): array {

		if (
			! $this->is_blacklist_enabled()
		) {
			return $columns;
		}

		if (
			isset(
				$columns[
					self::COLUMN
				]
			)
		) {
			return $columns;
		}

		$new_columns =
			array();

		$inserted =
			false;

		foreach (
			$columns as
				$key =>
				$label
		) {

			$new_columns[
				$key
			] =
				$label;

			/*
			 * Place Security after Order Status.
			 */
			if (
				'order_status' ===
					$key
			) {
				$new_columns[
					self::COLUMN
				] =
					__(
						'Security',
						'eilmo-checkout-flow'
					);

				$inserted =
					true;
			}
		}

		if ( ! $inserted ) {
			$new_columns[
				self::COLUMN
			] =
				__(
					'Security',
					'eilmo-checkout-flow'
				);
		}

		return $new_columns;
	}

	/**
	 * Render HPOS Security column.
	 *
	 * @param string $column Column.
	 * @param mixed  $order  Order.
	 *
	 * @return void
	 */
	public function render_hpos_column(
		string $column,
		$order
	): void {

		if (
			self::COLUMN !==
				$column
		) {
			return;
		}

		if (
			! $order instanceof
				WC_Order
		) {
			echo '—';
			return;
		}

		$this->render_security_actions(
			$order,
			'hpos'
		);
	}

	/**
	 * Render Legacy Orders Security column.
	 *
	 * @param string $column  Column.
	 * @param mixed  $post_id Order ID.
	 *
	 * @return void
	 */
	public function render_legacy_column(
		string $column,
		$post_id
	): void {

		if (
			self::COLUMN !==
				$column
		) {
			return;
		}

		$order_id =
			absint(
				$post_id
			);

		if (
			$order_id <= 0 ||
			! function_exists(
				'wc_get_order'
			)
		) {
			echo '—';
			return;
		}

		$order =
			wc_get_order(
				$order_id
			);

		if (
			! $order instanceof
				WC_Order
		) {
			echo '—';
			return;
		}

		$this->render_security_actions(
			$order,
			'legacy'
		);
	}

	/**
	 * Render blacklist controls.
	 *
	 * WooCommerce Orders list is already wrapped inside
	 * a form, so nested forms must not be rendered here.
	 *
	 * Nonce-protected admin-post action links are used
	 * instead.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $screen Screen.
	 *
	 * @return void
	 */
	private function render_security_actions(
		WC_Order $order,
		string $screen
	): void {

		if (
			! $this->is_blacklist_enabled()
		) {
			echo '—';
			return;
		}

		$order_id =
			absint(
				$order->get_id()
			);

		if ( $order_id <= 0 ) {
			echo '—';
			return;
		}

		$phone =
			sanitize_text_field(
				(string) $order
					->get_billing_phone()
			);

		$email =
			sanitize_email(
				(string) $order
					->get_billing_email()
			);

		$ip =
			sanitize_text_field(
				(string) $order
					->get_customer_ip_address()
			);

		$repository =
			new BlacklistRepository();

		$has_phone =
			'' !==
				$phone;

		$has_email =
			'' !==
				$email;

		$has_ip =
			'' !==
				$ip;

		$phone_blocked =
			$has_phone &&
			$repository->is_blocked(
				'phone',
				$phone
			);

		$email_blocked =
			$has_email &&
			$repository->is_blocked(
				'email',
				$email
			);

		$ip_blocked =
			$has_ip &&
			$repository->is_blocked(
				'ip',
				$ip
			);

		$customer_available =
			$has_phone ||
			$has_email;

		/*
		 * Customer is fully blocked only when every
		 * available customer identity is blocked.
		 */
		$customer_blocked =
			$customer_available &&
			(
				! $has_phone ||
				$phone_blocked
			) &&
			(
				! $has_email ||
				$email_blocked
			);

		?>
		<div class="eilmo-cf-order-security">

			<?php if ( $customer_available ) : ?>

				<?php if ( $customer_blocked ) : ?>

					<span
						class="
							eilmo-cf-order-security__badge
							eilmo-cf-order-security__badge--blocked
						"
					>
						<?php
						esc_html_e(
							'Customer Blocked',
							'eilmo-checkout-flow'
						);
						?>
					</span>

				<?php else : ?>

					<?php
					$this->render_block_action(
						$order_id,
						'customer',
						$screen,
						__(
							'Block Customer',
							'eilmo-checkout-flow'
						)
					);
					?>

				<?php endif; ?>

				<div class="eilmo-cf-order-security__details">

					<?php if ( $has_phone ) : ?>

						<div>
							<span>
								<?php
								esc_html_e(
									'Phone:',
									'eilmo-checkout-flow'
								);
								?>
							</span>

							<span
								class="<?php
									echo esc_attr(
										$phone_blocked
											? 'eilmo-cf-order-security__state eilmo-cf-order-security__state--blocked'
											: 'eilmo-cf-order-security__state eilmo-cf-order-security__state--allowed'
									);
								?>"
							>
								<?php
								echo esc_html(
									$phone_blocked
										? __(
											'Blocked',
											'eilmo-checkout-flow'
										)
										: __(
											'Allowed',
											'eilmo-checkout-flow'
										)
								);
								?>
							</span>
						</div>

					<?php endif; ?>

					<?php if ( $has_email ) : ?>

						<div>
							<span>
								<?php
								esc_html_e(
									'Email:',
									'eilmo-checkout-flow'
								);
								?>
							</span>

							<span
								class="<?php
									echo esc_attr(
										$email_blocked
											? 'eilmo-cf-order-security__state eilmo-cf-order-security__state--blocked'
											: 'eilmo-cf-order-security__state eilmo-cf-order-security__state--allowed'
									);
								?>"
							>
								<?php
								echo esc_html(
									$email_blocked
										? __(
											'Blocked',
											'eilmo-checkout-flow'
										)
										: __(
											'Allowed',
											'eilmo-checkout-flow'
										)
								);
								?>
							</span>
						</div>

					<?php endif; ?>

				</div>

			<?php else : ?>

				<span class="eilmo-cf-order-security__empty">
					<?php
					esc_html_e(
						'No phone or email',
						'eilmo-checkout-flow'
					);
					?>
				</span>

			<?php endif; ?>

			<?php if ( $has_ip ) : ?>

				<?php if ( $ip_blocked ) : ?>

					<span
						class="
							eilmo-cf-order-security__badge
							eilmo-cf-order-security__badge--blocked
						"
					>
						<?php
						esc_html_e(
							'IP Blocked',
							'eilmo-checkout-flow'
						);
						?>
					</span>

				<?php else : ?>

					<?php
					$this->render_block_action(
						$order_id,
						'ip',
						$screen,
						__(
							'Block IP',
							'eilmo-checkout-flow'
						)
					);
					?>

				<?php endif; ?>

			<?php else : ?>

				<span class="eilmo-cf-order-security__empty">
					<?php
					esc_html_e(
						'No IP recorded',
						'eilmo-checkout-flow'
					);
					?>
				</span>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render nonce-protected blacklist action.
	 *
	 * A link is deliberately used instead of a form
	 * because WooCommerce Orders list itself is already
	 * wrapped inside a form.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $mode     Block mode.
	 * @param string $screen   Screen.
	 * @param string $label    Label.
	 *
	 * @return void
	 */
	private function render_block_action(
		int $order_id,
		string $mode,
		string $screen,
		string $label
	): void {

		$order_id =
			absint(
				$order_id
			);

		$mode =
			sanitize_key(
				$mode
			);

		$screen =
			'legacy' ===
				$screen
				? 'legacy'
				: 'hpos';

		if (
			$order_id <= 0 ||
			! in_array(
				$mode,
				array(
					'customer',
					'ip',
				),
				true
			)
		) {
			return;
		}

		$nonce_action =
			'eilmo_cf_order_blacklist_' .
				$mode .
				'_' .
				$order_id;

		$url =
			add_query_arg(
				array(
					'action' =>
						self::ACTION,

					'order_id' =>
						$order_id,

					'block_mode' =>
						$mode,

					'order_screen' =>
						$screen,

					'_wpnonce' =>
						wp_create_nonce(
							$nonce_action
						),
				),
				admin_url(
					'admin-post.php'
				)
			);

		$confirmation =
			'ip' ===
				$mode
				? __(
					'Block this customer IP address?',
					'eilmo-checkout-flow'
				)
				: __(
					'Block this customer phone number and email address?',
					'eilmo-checkout-flow'
				);

		?>
		<a
			href="<?php
				echo esc_url(
					$url
				);
			?>"
			class="button button-small eilmo-cf-order-security__button"
			onclick="return window.confirm('<?php
				echo esc_js(
					$confirmation
				);
			?>');"
		>
			<?php
			echo esc_html(
				$label
			);
			?>
		</a>
		<?php
	}

	/**
	 * Handle Order list blacklist action.
	 *
	 * @return void
	 */
	public function handle_block(): void {

		if (
			! current_user_can(
				'manage_woocommerce'
			)
		) {
			wp_die(
				esc_html__(
					'You do not have permission to perform this action.',
					'eilmo-checkout-flow'
				)
			);
		}

		$order_id =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'order_id'
				]
			)
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'order_id'
						]
					)
				)
				: 0;

		$mode =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'block_mode'
				]
			)
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'block_mode'
						]
					)
				)
				: '';

		$screen =
			$this->get_requested_screen();

		if (
			$order_id <= 0 ||
			! in_array(
				$mode,
				array(
					'customer',
					'ip',
				),
				true
			)
		) {
			$this->redirect(
				'invalid_request',
				$order_id,
				$screen
			);
		}

		check_admin_referer(
			'eilmo_cf_order_blacklist_' .
				$mode .
				'_' .
				$order_id
		);

		if (
			! $this->is_blacklist_enabled()
		) {
			$this->redirect(
				'feature_disabled',
				$order_id,
				$screen
			);
		}

		if (
			! function_exists(
				'wc_get_order'
			)
		) {
			$this->redirect(
				'order_not_found',
				$order_id,
				$screen
			);
		}

		$order =
			wc_get_order(
				$order_id
			);

		if (
			! $order instanceof
				WC_Order
		) {
			$this->redirect(
				'order_not_found',
				$order_id,
				$screen
			);
		}

		if (
			'customer' ===
				$mode
		) {
			$notice =
				$this->block_customer(
					$order
				);
		} else {
			$notice =
				$this->block_ip(
					$order
				);
		}

		$this->redirect(
			$notice,
			$order_id,
			$screen
		);
	}

	/**
	 * Block customer phone and email.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function block_customer(
		WC_Order $order
	): string {

		$order_id =
			absint(
				$order->get_id()
			);

		$phone =
			sanitize_text_field(
				(string) $order
					->get_billing_phone()
			);

		$email =
			sanitize_email(
				(string) $order
					->get_billing_email()
			);

		$identifiers =
			array();

		if ( '' !== $phone ) {
			$identifiers[] =
				array(
					'type' =>
						'phone',

					'value' =>
						$phone,
				);
		}

		if ( '' !== $email ) {
			$identifiers[] =
				array(
					'type' =>
						'email',

					'value' =>
						$email,
				);
		}

		if ( empty( $identifiers ) ) {
			return 'no_customer_identity';
		}

		$repository =
			new BlacklistRepository();

		$reason =
			sprintf(
				/* translators: %d: order ID. */
				__(
					'Blocked from WooCommerce Order #%d',
					'eilmo-checkout-flow'
				),
				$order_id
			);

		$added_ids =
			array();

		$already_blocked =
			0;

		$failed =
			0;

		foreach (
			$identifiers as
				$identifier
		) {

			$type =
				(string) (
					$identifier[
						'type'
					] ??
						''
				);

			$value =
				(string) (
					$identifier[
						'value'
					] ??
						''
				);

			if (
				$repository->is_blocked(
					$type,
					$value
				)
			) {
				$already_blocked++;
				continue;
			}

			$result =
				$repository->add(
					$type,
					$value,
					$reason
				);

			if (
				is_wp_error(
					$result
				)
			) {

				if (
					'eilmo_cf_blacklist_exists' ===
						$result->get_error_code()
				) {
					$already_blocked++;
					continue;
				}

				$failed++;
				continue;
			}

			$entry_id =
				absint(
					$result
				);

			if ( $entry_id > 0 ) {
				$added_ids[] =
					$entry_id;
			} else {
				$failed++;
			}
		}

		if (
			! empty(
				$added_ids
			)
		) {
			do_action(
				'eilmo_cf/security/order_customer_blacklisted',
				$order_id,
				$added_ids,
				$order
			);
		}

		if (
			$failed > 0 &&
			! empty(
				$added_ids
			)
		) {
			return 'customer_partial';
		}

		if ( $failed > 0 ) {
			return 'block_failed';
		}

		if (
			empty(
				$added_ids
			) &&
			$already_blocked > 0
		) {
			return 'already_blocked';
		}

		return 'customer_blocked';
	}

	/**
	 * Block order customer IP.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function block_ip(
		WC_Order $order
	): string {

		$order_id =
			absint(
				$order->get_id()
			);

		$ip =
			sanitize_text_field(
				(string) $order
					->get_customer_ip_address()
			);

		if ( '' === $ip ) {
			return 'no_ip';
		}

		$repository =
			new BlacklistRepository();

		if (
			$repository->is_blocked(
				'ip',
				$ip
			)
		) {
			return 'already_blocked';
		}

		$reason =
			sprintf(
				/* translators: %d: order ID. */
				__(
					'Blocked from WooCommerce Order #%d',
					'eilmo-checkout-flow'
				),
				$order_id
			);

		$result =
			$repository->add(
				'ip',
				$ip,
				$reason
			);

		if (
			is_wp_error(
				$result
			)
		) {

			if (
				'eilmo_cf_blacklist_exists' ===
					$result->get_error_code()
			) {
				return 'already_blocked';
			}

			return 'block_failed';
		}

		$entry_id =
			absint(
				$result
			);

		if ( $entry_id <= 0 ) {
			return 'block_failed';
		}

		do_action(
			'eilmo_cf/security/order_ip_blacklisted',
			$order_id,
			$entry_id,
			$order
		);

		return 'ip_blocked';
	}

	/**
	 * Render Orders list notice.
	 *
	 * @return void
	 */
	public function render_notice(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$notice =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'eilmo_order_blacklist_notice'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'eilmo_order_blacklist_notice'
						]
					)
				)
				: '';

		if ( '' === $notice ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$order_id =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'eilmo_order_id'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
				? absint(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'eilmo_order_id'
						]
					)
				)
				: 0;

		$type =
			'success';

		switch ( $notice ) {

			case 'customer_blocked':
				$message =
					sprintf(
						/* translators: %d: order ID. */
						__(
							'Customer phone and/or email from Order #%d were added to the blacklist.',
							'eilmo-checkout-flow'
						),
						$order_id
					);
				break;

			case 'ip_blocked':
				$message =
					sprintf(
						/* translators: %d: order ID. */
						__(
							'Customer IP from Order #%d was added to the blacklist.',
							'eilmo-checkout-flow'
						),
						$order_id
					);
				break;

			case 'customer_partial':
				$type =
					'warning';

				$message =
					sprintf(
						/* translators: %d: order ID. */
						__(
							'Some customer identifiers from Order #%d were blocked, but one could not be added.',
							'eilmo-checkout-flow'
						),
						$order_id
					);
				break;

			case 'already_blocked':
				$type =
					'warning';

				$message =
					__(
						'The selected customer identifier is already blocked.',
						'eilmo-checkout-flow'
					);
				break;

			case 'no_customer_identity':
				$type =
					'warning';

				$message =
					__(
						'This order does not contain a billing phone or email address that can be blocked.',
						'eilmo-checkout-flow'
					);
				break;

			case 'no_ip':
				$type =
					'warning';

				$message =
					__(
						'This order does not contain a customer IP address.',
						'eilmo-checkout-flow'
					);
				break;

			case 'feature_disabled':
				$type =
					'warning';

				$message =
					__(
						'Customer Blacklist is currently disabled.',
						'eilmo-checkout-flow'
					);
				break;

			case 'order_not_found':
				$type =
					'error';

				$message =
					__(
						'The selected WooCommerce order could not be found.',
						'eilmo-checkout-flow'
					);
				break;

			case 'invalid_request':
				$type =
					'error';

				$message =
					__(
						'The blacklist request is invalid.',
						'eilmo-checkout-flow'
					);
				break;

			case 'block_failed':
			default:
				$type =
					'error';

				$message =
					__(
						'The customer could not be added to the blacklist.',
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
	 * Render Security column styles.
	 *
	 * @return void
	 */
	public function render_styles(): void {

		if (
			! $this->is_blacklist_enabled()
		) {
			return;
		}

		if (
			! function_exists(
				'get_current_screen'
			)
		) {
			return;
		}

		$screen =
			get_current_screen();

		if ( ! $screen ) {
			return;
		}

		if (
			! in_array(
				(string) $screen->id,
				array(
					'woocommerce_page_wc-orders',
					'edit-shop_order',
				),
				true
			)
		) {
			return;
		}

		?>
		<style>
			.column-eilmo_cf_security {
				width: 170px;
			}

			.eilmo-cf-order-security {
				display: flex;
				flex-direction: column;
				align-items: flex-start;
				gap: 7px;
				min-width: 145px;
			}

			.eilmo-cf-order-security__button {
				box-sizing: border-box;
				min-height: 30px;
				margin: 0;
				padding: 0 10px;
				line-height: 28px;
				white-space: nowrap;
			}

			.eilmo-cf-order-security__badge {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				box-sizing: border-box;
				min-height: 30px;
				margin: 0;
				padding: 0 10px;
				border: 1px solid transparent;
				border-radius: 4px;
				font-size: 13px;
				font-weight: 600;
				line-height: 28px;
				white-space: nowrap;
			}

			.eilmo-cf-order-security__badge--blocked {
				border-color: #d63638;
				background: #fcf0f1;
				color: #b32d2e;
			}

			.eilmo-cf-order-security__details {
				display: flex;
				flex-direction: column;
				gap: 2px;
				margin: 0;
				color: #50575e;
				font-size: 12px;
				line-height: 1.5;
			}

			.eilmo-cf-order-security__details > div {
				display: flex;
				align-items: center;
				gap: 4px;
				margin: 0;
			}

			.eilmo-cf-order-security__state {
				font-weight: 500;
			}

			.eilmo-cf-order-security__state--blocked {
				color: #b32d2e;
			}

			.eilmo-cf-order-security__state--allowed {
				color: #50575e;
			}

			.eilmo-cf-order-security__empty {
				color: #646970;
				font-size: 12px;
				line-height: 1.5;
			}
		</style>
		<?php
	}

	/**
	 * Get requested Orders screen type.
	 *
	 * @return string
	 */
	private function get_requested_screen(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sanitized before nonce verification.
		$screen =
			isset(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
				$_GET[
					'order_screen'
				]
			)
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sanitized before nonce verification.
				? sanitize_key(
					wp_unslash(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
						$_GET[
							'order_screen'
						]
					)
				)
				: 'hpos';

		return 'legacy' ===
			$screen
				? 'legacy'
				: 'hpos';
	}

	/**
	 * Redirect to WooCommerce Orders list.
	 *
	 * @param string $notice   Notice.
	 * @param int    $order_id Order ID.
	 * @param string $screen   Screen.
	 *
	 * @return void
	 */
	private function redirect(
		string $notice,
		int $order_id,
		string $screen
	): void {

		$notice =
			sanitize_key(
				$notice
			);

		$order_id =
			absint(
				$order_id
			);

		if (
			'legacy' ===
				$screen
		) {
			$url =
				add_query_arg(
					array(
						'post_type' =>
							'shop_order',

						'eilmo_order_blacklist_notice' =>
							$notice,

						'eilmo_order_id' =>
							$order_id,
					),
					admin_url(
						'edit.php'
					)
				);
		} else {
			$url =
				add_query_arg(
					array(
						'page' =>
							'wc-orders',

						'eilmo_order_blacklist_notice' =>
							$notice,

						'eilmo_order_id' =>
							$order_id,
					),
					admin_url(
						'admin.php'
					)
				);
		}

		wp_safe_redirect(
			$url
		);

		exit;
	}

	/**
	 * Determine whether Customer Blacklist is enabled.
	 *
	 * @return bool
	 */
	private function is_blacklist_enabled(): bool {

		/*
		 * Security settings moved out of the legacy Checkout Settings
		 * option. The Orders-list Block Customer / Block IP controls
		 * must follow the current Security master + Blacklist switch.
		 */
		return SecuritySettings::is_protection_enabled( 'blacklist' );
	}
}