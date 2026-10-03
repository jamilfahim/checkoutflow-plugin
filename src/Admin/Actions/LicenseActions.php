<?php
/**
 * License admin actions.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Actions;

use EilmoCheckout\Admin\Pages\HelpCenterPage;
use EilmoCheckout\Admin\Pages\DashboardPage;
use EilmoCheckout\Licensing\LicenseManager;
use EilmoCheckout\Support\RemoteContent;

defined( 'ABSPATH' ) || exit;

final class LicenseActions {

	public function register(): void {
		add_action( 'admin_post_eilmo_cf_activate_license', array( $this, 'activate' ) );
		add_action( 'admin_post_eilmo_cf_refresh_license', array( $this, 'refresh' ) );
		add_action( 'admin_post_eilmo_cf_deactivate_license', array( $this, 'deactivate' ) );
	}

	public function activate(): void {
		$this->authorize( 'eilmo_cf_activate_license' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$server = isset( $_POST['server_url'] ) ? esc_url_raw( wp_unslash( $_POST['server_url'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The owning endpoint/lifecycle verifies its nonce before this field is normalized and validated.
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( ! $key ) {
			$this->redirect( false, __( 'Enter a license key.', 'eilmo-checkout-flow' ) );
		}
		$this->finish( ( new LicenseManager() )->activate( $server, $key ) );
	}

	public function refresh(): void {
		$this->authorize( 'eilmo_cf_refresh_license' );
		$this->finish( ( new LicenseManager() )->refresh() );
	}

	public function deactivate(): void {
		$this->authorize( 'eilmo_cf_deactivate_license' );
		$result = ( new LicenseManager() )->deactivate();
		RemoteContent::clear_cache();
		$this->redirect( ! empty( $result['success'] ), (string) ( $result['message'] ?? '' ), true );
	}

	private function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the license.', 'eilmo-checkout-flow' ) );
		}
		check_admin_referer( $action );
	}

	private function finish( array $result ): void {
		RemoteContent::clear_cache();
		$this->redirect( ! empty( $result['success'] ), (string) ( $result['message'] ?? '' ) );
	}

	private function redirect( bool $success, string $message, bool $dashboard = false ): void {
		wp_safe_redirect( add_query_arg( array(
			'page' => $success && ! $dashboard ? HelpCenterPage::PAGE_SLUG : DashboardPage::PAGE_SLUG,
			'tab' => $success && ! $dashboard ? HelpCenterPage::TAB_LICENSE : false,
			'license_notice' => $success ? 'success' : 'error',
			'license_message' => rawurlencode( $message ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}
