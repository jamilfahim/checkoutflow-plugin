<?php
/**
 * Help Center and license page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Licensing\LicenseManager;
use EilmoCheckout\Licensing\LicenseRepository;
use EilmoCheckout\Support\RemoteContent;

defined( 'ABSPATH' ) || exit;

final class HelpCenterPage {

	public const PAGE_SLUG = 'eilmo-checkout-help';
	public const TAB_LICENSE = 'license';

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'eilmo-checkout-flow' ) );
		}

		$tabs = array(
			'getting-started' => __( 'Getting Started', 'eilmo-checkout-flow' ),
			'documentation' => __( 'Documentation', 'eilmo-checkout-flow' ),
			'video-tutorials' => __( 'Video Tutorials', 'eilmo-checkout-flow' ),
			self::TAB_LICENSE => __( 'License', 'eilmo-checkout-flow' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'getting-started';
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'getting-started';
		?>
		<div class="wrap eilmo-cf-admin eilmo-cf-help">
			<div class="eilmo-cf-admin__header">
				<div class="eilmo-cf-admin__heading">
					<h1><?php esc_html_e( 'Help Center', 'eilmo-checkout-flow' ); ?></h1>
					<p><?php esc_html_e( 'Setup guides, feature tutorials and license management in one place.', 'eilmo-checkout-flow' ); ?></p>
				</div>
			</div>

			<?php if ( self::TAB_LICENSE !== $tab ) { $this->render_notice(); } ?>

			<nav class="nav-tab-wrapper eilmo-cf-settings-tabs" aria-label="<?php esc_attr_e( 'Help Center', 'eilmo-checkout-flow' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="eilmo-cf-help__content">
				<?php
				if ( self::TAB_LICENSE === $tab ) {
					$this->render_license_panel();
				} elseif ( 'documentation' === $tab ) {
					$this->render_documentation();
				} elseif ( 'video-tutorials' === $tab ) {
					$this->render_videos();
				} else {
					$this->render_getting_started();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_getting_started(): void {
		$items = array(
			array( __( '1. Enable your features', 'eilmo-checkout-flow' ), __( 'Open Checkout Settings → General and turn on only the optional modules you need.', 'eilmo-checkout-flow' ), CheckoutSettingsPage::PAGE_SLUG ),
			array( __( '2. Configure checkout', 'eilmo-checkout-flow' ), __( 'Set delivery, payment, customer fields and your global checkout style.', 'eilmo-checkout-flow' ), CheckoutSettingsPage::PAGE_SLUG ),
		);
		?>
		<div class="eilmo-cf-help__grid">
			<?php foreach ( $items as $item ) : ?>
				<section class="eilmo-cf-help__card">
					<h2><?php echo esc_html( $item[0] ); ?></h2>
					<p><?php echo esc_html( $item[1] ); ?></p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'page', $item[2], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open', 'eilmo-checkout-flow' ); ?></a>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_documentation(): void {
		$content = ( new RemoteContent() )->get();
		$documents = apply_filters( 'eilmo_cf/documentation_items', $content['documents'] );
		$documents = is_array( $documents ) ? $documents : array();
		if ( ! $documents ) {
			$legacy_url = (string) apply_filters( 'eilmo_cf/documentation_url', '' );
			if ( $legacy_url ) {
				$documents[] = array(
					'title' => __( 'Checkout Flow Documentation', 'eilmo-checkout-flow' ),
					'description' => __( 'Installation, checkout setup, payments, courier risk checks, tracking and troubleshooting.', 'eilmo-checkout-flow' ),
					'url' => $legacy_url,
					'category' => __( 'Documentation', 'eilmo-checkout-flow' ),
				);
			}
		}
		if ( ! $documents ) {
			$this->render_empty_content( __( 'Quick start', 'eilmo-checkout-flow' ), __( '1. Create WooCommerce products and variations. 2. Configure delivery and payment options in Checkout Flow Settings. 3. Enable and configure a gateway in WooCommerce → Settings → Payments before requiring advance or full payment. 4. In Elementor, add the Checkout Flow widget and choose its products and offers. Use Style → General → Accent Color for the main theme, then the individual Style sections for detailed changes. 5. Preview on desktop and mobile and submit a test order before sharing your page.', 'eilmo-checkout-flow' ) );
			$this->render_empty_content( __( 'Testing and troubleshooting', 'eilmo-checkout-flow' ), __( 'Expired offers are hidden. Check rule dates and product conditions if an offer is missing. Test duplicate-order and rate-limit protection with a customer account because administrator bypass may be enabled. Manual payment proof does not verify a payment: review it before changing the order status. Completed checkout activity is removed from Abandoned Checkouts; use WooCommerce orders to review submitted orders.', 'eilmo-checkout-flow' ) );
			return;
		}
		?>
		<div class="eilmo-cf-help__grid">
			<?php foreach ( $documents as $document ) : ?>
				<section class="eilmo-cf-help__card">
					<?php if ( ! empty( $document['category'] ) ) : ?><span class="eilmo-cf-help__category"><?php echo esc_html( (string) $document['category'] ); ?></span><?php endif; ?>
					<h2><?php echo esc_html( (string) ( $document['title'] ?? __( 'Documentation', 'eilmo-checkout-flow' ) ) ); ?></h2>
					<p><?php echo esc_html( (string) ( $document['description'] ?? '' ) ); ?></p>
					<?php if ( ! empty( $document['url'] ) ) : ?><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $document['url'] ); ?>"><?php esc_html_e( 'Read Guide', 'eilmo-checkout-flow' ); ?></a><?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_videos(): void {
		$content = ( new RemoteContent() )->get();
		$videos = apply_filters( 'eilmo_cf/tutorial_videos', $content['videos'] );
		$videos = is_array( $videos ) ? $videos : array();
		?>
		<?php if ( ! $videos ) : ?>
			<?php $this->render_empty_content( __( 'Video Tutorials', 'eilmo-checkout-flow' ), __( 'Published tutorials will appear here automatically.', 'eilmo-checkout-flow' ) ); ?>
		<?php else : ?>
			<div class="eilmo-cf-help__grid">
				<?php foreach ( $videos as $video ) : ?>
					<section class="eilmo-cf-help__card">
						<?php if ( ! empty( $video['thumbnail'] ) ) : ?><a class="eilmo-cf-help__thumbnail" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $video['url'] ); ?>"><img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="" loading="lazy"></a><?php endif; ?>
						<div class="eilmo-cf-help__meta">
							<?php if ( ! empty( $video['category'] ) ) : ?><span class="eilmo-cf-help__category"><?php echo esc_html( (string) $video['category'] ); ?></span><?php endif; ?>
							<?php if ( ! empty( $video['duration'] ) ) : ?><span><?php echo esc_html( (string) $video['duration'] ); ?></span><?php endif; ?>
						</div>
						<h2><?php echo esc_html( (string) ( $video['title'] ?? __( 'Tutorial', 'eilmo-checkout-flow' ) ) ); ?></h2>
						<p><?php echo esc_html( (string) ( $video['description'] ?? '' ) ); ?></p>
						<?php if ( ! empty( $video['url'] ) ) : ?><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $video['url'] ); ?>"><?php esc_html_e( 'Watch Video', 'eilmo-checkout-flow' ); ?></a><?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_empty_content( string $title, string $message ): void {
		?>
		<section class="eilmo-cf-help__card eilmo-cf-help__card--wide">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $message ); ?></p>
		</section>
		<?php
	}

	public function render_license_panel(): void {
		$this->render_notice();
		$manager = new LicenseManager();
		$state = $manager->get_state();
		$status = sanitize_key( (string) $state['status'] );
		$is_active = $manager->is_usable();
		$server_locked = defined( 'EILMO_CF_LICENSE_SERVER_URL' ) && EILMO_CF_LICENSE_SERVER_URL;
		?>
		<div class="eilmo-cf-license">
			<?php if ( ! empty( $state['last_error'] ) ) : ?>
				<div class="notice notice-warning inline eilmo-cf-license__notice"><p><?php echo esc_html( (string) $state['last_error'] ); ?></p></div>
			<?php endif; ?>
			<section class="eilmo-cf-help__card eilmo-cf-license__summary">
				<div>
					<span class="eilmo-cf-license__badge is-<?php echo esc_attr( $is_active ? 'active' : 'inactive' ); ?>"><?php echo esc_html( $is_active ? __( 'Active', 'eilmo-checkout-flow' ) : ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span>
					<h2><?php esc_html_e( 'Checkout Flow License', 'eilmo-checkout-flow' ); ?></h2>
					<p><?php echo $state['license_key'] ? esc_html( LicenseRepository::masked_key( (string) $state['license_key'] ) ) : esc_html__( 'No license is connected.', 'eilmo-checkout-flow' ); ?></p>
				</div>
				<div class="eilmo-cf-license__facts">
					<div><span><?php esc_html_e( 'Plan', 'eilmo-checkout-flow' ); ?></span><strong><?php echo esc_html( $state['plan'] ?: '—' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Expires', 'eilmo-checkout-flow' ); ?></span><strong><?php echo esc_html( $state['expires_at'] ?: __( 'Lifetime / not available', 'eilmo-checkout-flow' ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Activations', 'eilmo-checkout-flow' ); ?></span><strong><?php echo esc_html( (string) $state['activations_used'] . ' / ' . ( null === $state['activations_allowed'] ? '∞' : (string) $state['activations_allowed'] ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Last checked', 'eilmo-checkout-flow' ); ?></span><strong><?php echo esc_html( $state['last_checked_at'] ?: '—' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Installed version', 'eilmo-checkout-flow' ); ?></span><strong><?php echo esc_html( EILMO_CF_VERSION ); ?></strong></div>
				</div>
			</section>

			<?php if ( ! $is_active ) : ?>
				<section class="eilmo-cf-help__card">
					<h2><?php esc_html_e( 'Activate License', 'eilmo-checkout-flow' ); ?></h2>
					<p><?php esc_html_e( 'Connect this installation to your License Manager. A previously verified installation remains available during a temporary license-server outage.', 'eilmo-checkout-flow' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="eilmo_cf_activate_license">
						<?php wp_nonce_field( 'eilmo_cf_activate_license' ); ?>
						<?php if ( ! $server_locked ) : ?>
							<label for="eilmo-cf-license-server"><?php esc_html_e( 'License Server URL', 'eilmo-checkout-flow' ); ?></label>
							<input id="eilmo-cf-license-server" class="regular-text" type="url" required name="server_url" value="<?php echo esc_attr( (string) $state['server_url'] ); ?>" placeholder="https://licenses.example.com">
						<?php else : ?>
							<input type="hidden" name="server_url" value="<?php echo esc_attr( EILMO_CF_LICENSE_SERVER_URL ); ?>">
						<?php endif; ?>
						<label for="eilmo-cf-license-key"><?php esc_html_e( 'License Key', 'eilmo-checkout-flow' ); ?></label>
						<input id="eilmo-cf-license-key" class="regular-text" type="password" required autocomplete="off" name="license_key" value="">
						<?php submit_button( __( 'Activate License', 'eilmo-checkout-flow' ), 'primary', 'submit', false ); ?>
					</form>
					<?php if ( ! empty( $state['license_key'] ) && ! empty( $state['activation_token'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="eilmo_cf_refresh_license">
							<?php wp_nonce_field( 'eilmo_cf_refresh_license' ); ?>
							<?php submit_button( __( 'Verify Existing Activation', 'eilmo-checkout-flow' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</section>
			<?php else : ?>
				<section class="eilmo-cf-help__card eilmo-cf-license__actions">
					<h2><?php esc_html_e( 'License Actions', 'eilmo-checkout-flow' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="eilmo_cf_refresh_license"><?php wp_nonce_field( 'eilmo_cf_refresh_license' ); ?><?php submit_button( __( 'Refresh Status', 'eilmo-checkout-flow' ), 'secondary', 'submit', false ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="eilmo_cf_deactivate_license"><?php wp_nonce_field( 'eilmo_cf_deactivate_license' ); ?><?php submit_button( __( 'Deactivate License', 'eilmo-checkout-flow' ), 'delete', 'submit', false ); ?></form>
				</section>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect-only status notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
		if ( empty( $_GET['license_notice'] ) || empty( $_GET['license_message'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect-only status notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
		$type = 'success' === sanitize_key( wp_unslash( $_GET['license_notice'] ) ) ? 'success' : 'error';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect-only status notice.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter value; it is normalized immediately and cannot mutate state.
		$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['license_message'] ) ) );
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
