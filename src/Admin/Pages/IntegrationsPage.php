<?php
/**
 * Integrations settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Groups Courier, Meta Tracking and WhatsApp integrations under one admin menu.
 */
final class IntegrationsPage {

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG =
		'eilmo-checkout-integrations';

	/**
	 * Courier tab.
	 */
	public const TAB_COURIER =
		'courier';

	/**
	 * Meta Tracking tab.
	 */
	public const TAB_META_TRACKING =
		'meta_tracking';

	/**
	 * WhatsApp Ordering tab.
	 */
	public const TAB_WHATSAPP =
		'whatsapp_ordering';

	/**
	 * Render Integrations page.
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

		$tabs = $this->get_available_tabs();

		if ( empty( $tabs ) ) {
			$this->redirect_to_feature_management();
		}

		$current_tab = $this->get_current_tab( $tabs );

		?>
		<div class="wrap eilmo-cf-admin">

			<div class="eilmo-cf-admin__header">
				<div class="eilmo-cf-admin__heading">
					<h1>
						<?php esc_html_e( 'Integrations', 'eilmo-checkout-flow' ); ?>
					</h1>

					<p>
						<?php esc_html_e( 'Configure courier services, Meta tracking and WhatsApp ordering from one place.', 'eilmo-checkout-flow' ); ?>
					</p>
				</div>
			</div>

			<?php
			settings_errors();
			$this->render_tabs( $tabs, $current_tab );
			$this->render_tab( $current_tab );
			?>

		</div>
		<?php
	}

	/**
	 * Get enabled integration tabs.
	 *
	 * @return array<string, string>
	 */
	private function get_available_tabs(): array {

		$tabs = array();

		if ( $this->is_feature_enabled( 'courier' ) ) {
			$tabs[ self::TAB_COURIER ] =
				__( 'Courier', 'eilmo-checkout-flow' );
		}

		if ( $this->is_feature_enabled( 'meta_tracking' ) ) {
			$tabs[ self::TAB_META_TRACKING ] =
				__( 'Meta Tracking', 'eilmo-checkout-flow' );
		}

		if ( $this->is_feature_enabled( 'whatsapp_ordering' ) ) {
			$tabs[ self::TAB_WHATSAPP ] =
				__( 'WhatsApp Ordering', 'eilmo-checkout-flow' );
		}

		return $tabs;
	}

	/**
	 * Get current tab.
	 *
	 * @param array<string, string> $tabs Available tabs.
	 *
	 * @return string
	 */
	private function get_current_tab(
		array $tabs
	): string {

		$default = (string) array_key_first( $tabs );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$tab = isset( $_GET['tab'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: $default;

		return isset( $tabs[ $tab ] )
			? $tab
			: $default;
	}

	/**
	 * Render integration tabs.
	 *
	 * @param array<string, string> $tabs        Available tabs.
	 * @param string                $current_tab Current tab.
	 *
	 * @return void
	 */
	private function render_tabs(
		array $tabs,
		string $current_tab
	): void {

		?>
		<nav
			class="nav-tab-wrapper eilmo-cf-settings-tabs"
			aria-label="<?php esc_attr_e( 'Integrations', 'eilmo-checkout-flow' ); ?>"
		>
			<?php foreach ( $tabs as $tab => $label ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
						'tab'  => $tab,
					),
					admin_url( 'admin.php' )
				);

				$classes = array( 'nav-tab' );

				if ( $current_tab === $tab ) {
					$classes[] = 'nav-tab-active';
				}
				?>

				<a
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					href="<?php echo esc_url( $url ); ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render selected integration.
	 *
	 * @param string $tab Current tab.
	 *
	 * @return void
	 */
	private function render_tab(
		string $tab
	): void {

		if ( self::TAB_META_TRACKING === $tab ) {
			$page = new MetaTrackingSettingsPage();
			$page->render( true );
			return;
		}

		if ( self::TAB_WHATSAPP === $tab ) {
			$page = new WhatsAppOrdersPage();
			$page->render( true );
			return;
		}

		$page = new CourierSettingsPage();
		$page->render( true );
	}

	/**
	 * Determine whether a master feature is enabled.
	 *
	 * @param string $feature Feature key.
	 *
	 * @return bool
	 */
	private function is_feature_enabled(
		string $feature
	): bool {

		$stored = get_option(
			CheckoutSettings::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_replace_recursive(
			CheckoutSettings::get_defaults(),
			$stored
		);

		return 'yes' === (string) (
			$settings['general'][ $feature ] ?? 'no'
		);
	}

	/**
	 * Redirect to Checkout Settings feature management.
	 *
	 * @return void
	 */
	private function redirect_to_feature_management(): void {

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => CheckoutSettingsPage::PAGE_SLUG,
					'tab'  => 'general',
				),
				admin_url( 'admin.php' )
			) . '#eilmo-cf-optional-features'
		);

		exit;
	}
}
