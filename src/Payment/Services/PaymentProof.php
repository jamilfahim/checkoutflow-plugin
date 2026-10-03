<?php
/**
 * Secure manual-payment proof uploads.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Payment\Services;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Payment\Gateways\GatewaySettings;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Stores payment screenshots privately and attaches them to orders by token.
 */
final class PaymentProof {

	public const META_FILE   = '_eilmo_cf_payment_proof_file';
	public const META_RELATIVE = '_eilmo_cf_payment_proof_relative';
	public const META_NAME   = '_eilmo_cf_payment_proof_name';
	public const META_MIME   = '_eilmo_cf_payment_proof_mime';
	public const META_METHOD = '_eilmo_cf_payment_proof_method';

	private const TRANSIENT_PREFIX = 'eilmo_cf_proof_';
	private const MAX_UPLOADS_PER_HOUR = 10;

	/** Register upload, viewer and cleanup hooks. */
	public function register(): void {
		add_action( 'wp_ajax_eilmo_cf_upload_payment_proof', array( $this, 'upload' ) );
		add_action( 'wp_ajax_nopriv_eilmo_cf_upload_payment_proof', array( $this, 'upload' ) );
		add_action( 'wp_ajax_eilmo_cf_view_payment_proof', array( $this, 'view' ) );
		add_action( 'before_delete_post', array( $this, 'delete_legacy_order_file' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_order_file' ) );
	}

	/** Handle a temporary screenshot upload. */
	public function upload(): void {
		check_ajax_referer( 'eilmo_cf_payment_proof', 'nonce' );
		$this->cleanup_temporary_files();

		$method_key = sanitize_key( (string) ( $_POST['method_key'] ?? '' ) );
		$config = self::get_method_config( $method_key );

		if ( empty( $config ) || 'yes' !== ( $config['payment_proof_enabled'] ?? 'no' ) ) {
			wp_send_json_error( array( 'message' => __( 'Payment screenshot is not available for this method.', 'eilmo-checkout-flow' ) ), 400 );
		}

		if ( ! $this->consume_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Too many upload attempts. Please try again later.', 'eilmo-checkout-flow' ) ), 429 );
		}

		if ( empty( $_FILES['proof'] ) || ! is_array( $_FILES['proof'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a payment screenshot.', 'eilmo-checkout-flow' ) ), 400 );
		}

		$file = $_FILES['proof'];
		$error = absint( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		$tmp_name = (string) ( $file['tmp_name'] ?? '' );
		$original_name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$size = absint( $file['size'] ?? 0 );
		$max_mb = max( 1, min( 10, absint( $config['payment_proof_max_mb'] ?? 5 ) ) );

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			wp_send_json_error( array( 'message' => __( 'The screenshot upload failed. Please choose the file again.', 'eilmo-checkout-flow' ) ), 400 );
		}

		if ( $size < 1 || $size > ( $max_mb * MB_IN_BYTES ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'The screenshot must be no larger than %d MB.', 'eilmo-checkout-flow' ), $max_mb ) ), 400 );
		}

		$allowed = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$checked = wp_check_filetype_and_ext( $tmp_name, $original_name, $allowed );
		$mime = sanitize_mime_type( (string) ( $checked['type'] ?? '' ) );
		$extension = sanitize_key( (string) ( $checked['ext'] ?? '' ) );

		if ( ! in_array( $mime, array_values( $allowed ), true ) || '' === $extension ) {
			wp_send_json_error( array( 'message' => __( 'Only JPG, PNG and WebP screenshots are allowed.', 'eilmo-checkout-flow' ) ), 400 );
		}

		$directory = trailingslashit( self::get_storage_directory() ) . 'temporary';
		if ( '' === $directory || ! wp_mkdir_p( $directory ) ) {
			wp_send_json_error( array( 'message' => __( 'The server could not prepare secure upload storage.', 'eilmo-checkout-flow' ) ), 500 );
		}

		$this->protect_directory( $directory );
		$token = wp_generate_uuid4();
		$filename = wp_generate_password( 32, false, false ) . '.' . $extension;
		$path = trailingslashit( $directory ) . $filename;

		if ( ! move_uploaded_file( $tmp_name, $path ) ) {
			wp_send_json_error( array( 'message' => __( 'The screenshot could not be saved. Please try again.', 'eilmo-checkout-flow' ) ), 500 );
		}

		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array( 'file' => $path, 'name' => $original_name, 'mime' => $mime, 'method' => $method_key ),
			HOUR_IN_SECONDS
		);

		wp_send_json_success( array( 'token' => $token, 'name' => $original_name, 'message' => __( 'Payment screenshot uploaded.', 'eilmo-checkout-flow' ) ) );
	}

	/** Determine whether a temporary token belongs to the selected method. */
	public static function is_valid_token( string $token, string $method_key ): bool {
		if ( '' === $token || '' === $method_key ) {
			return false;
		}

		$record = get_transient( self::TRANSIENT_PREFIX . $token );
		return is_array( $record )
			&& sanitize_key( (string) ( $record['method'] ?? '' ) ) === $method_key
			&& is_file( (string) ( $record['file'] ?? '' ) );
	}

	/** Determine whether a valid proof is already attached to this order. */
	public static function is_attached_to_order( WC_Order $order, string $method_key = '' ): bool {
		if ( '' === self::get_order_file( $order ) ) {
			return false;
		}

		$method_key = sanitize_key( $method_key );
		if ( '' === $method_key ) {
			return true;
		}

		return $method_key === sanitize_key( (string) $order->get_meta( self::META_METHOD, true ) );
	}

	/** Attach a temporary proof to an order. */
	public static function assign_to_order( WC_Order $order, string $token, string $method_key ): bool {
		if ( ! self::is_valid_token( $token, $method_key ) ) {
			return false;
		}

		$record = get_transient( self::TRANSIENT_PREFIX . $token );
		$source = (string) $record['file'];
		$orders_directory = trailingslashit( self::get_storage_directory() ) . 'orders';
		if ( ! wp_mkdir_p( $orders_directory ) ) {
			return false;
		}
		( new self() )->protect_directory( $orders_directory );
		$extension = pathinfo( $source, PATHINFO_EXTENSION );
		$destination = trailingslashit( $orders_directory ) . $order->get_id() . '-' . wp_generate_password( 24, false, false ) . '.' . sanitize_key( $extension );
		if ( ! rename( $source, $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			return false;
		}
		@chmod( $destination, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$order->update_meta_data( self::META_FILE, $destination );
		$order->update_meta_data( self::META_RELATIVE, 'orders/' . basename( $destination ) );
		$order->update_meta_data( self::META_NAME, sanitize_file_name( (string) ( $record['name'] ?? 'payment-proof' ) ) );
		$order->update_meta_data( self::META_MIME, sanitize_mime_type( (string) ( $record['mime'] ?? 'image/jpeg' ) ) );
		$order->update_meta_data( self::META_METHOD, $method_key );

		try {
			/* Persist immediately so HPOS and classic order storage behave alike. */
			$order->save_meta_data();
		} catch ( \Throwable $throwable ) {
			self::clear_order_metadata( $order );
			try {
				$order->save_meta_data();
			} catch ( \Throwable $cleanup_error ) {
				/* Keep the order object clean even when storage is temporarily unavailable. */
			}
			wp_delete_file( $destination );
			return false;
		}

		if ( '' === self::get_order_file( $order ) ) {
			self::clear_order_metadata( $order );
			try {
				$order->save_meta_data();
			} catch ( \Throwable $throwable ) {
				/* The in-memory order is already clean; avoid masking the proof failure. */
			}
			wp_delete_file( $destination );
			return false;
		}

		delete_transient( self::TRANSIENT_PREFIX . $token );
		return true;
	}

	/**
	 * Resolve an attached proof across upload-root or server-path changes.
	 *
	 * Existing orders only have the historical absolute-path meta. New orders
	 * also store a safe relative path, and the basename fallback can recover a
	 * moved historical file without allowing traversal outside private storage.
	 */
	public static function get_order_file( WC_Order $order ): string {
		$storage = self::get_storage_directory();
		$storage_real = realpath( $storage );
		if ( '' === $storage || false === $storage_real ) {
			return '';
		}

		$absolute = (string) $order->get_meta( self::META_FILE, true );
		$relative = (string) $order->get_meta( self::META_RELATIVE, true );
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		$candidates = array();
		if ( '' !== $absolute ) {
			$candidates[] = $absolute;
		}
		if ( 0 === strpos( $relative, 'orders/' ) && false === strpos( $relative, '..' ) ) {
			$candidates[] = trailingslashit( $storage ) . $relative;
		}
		if ( '' !== $absolute ) {
			$candidates[] = trailingslashit( $storage ) . 'orders/' . basename( $absolute );
		}

		$allowed_prefix = trailingslashit( $storage_real );
		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( ! is_file( $candidate ) ) {
				continue;
			}

			$candidate_real = realpath( $candidate );
			if ( false !== $candidate_real && 0 === strpos( $candidate_real, $allowed_prefix ) ) {
				return $candidate_real;
			}
		}

		return '';
	}

	/** Stream a proof to an authorized WooCommerce manager. */
	public function view(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this payment proof.', 'eilmo-checkout-flow' ), '', array( 'response' => 403 ) );
		}

		$order_id = absint( $_GET['order_id'] ?? 0 );
		check_admin_referer( 'eilmo_cf_view_payment_proof_' . $order_id );
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'eilmo-checkout-flow' ), '', array( 'response' => 404 ) );
		}

		$file = self::get_order_file( $order );
		$mime = sanitize_mime_type( (string) $order->get_meta( self::META_MIME, true ) );
		$name = sanitize_file_name( (string) $order->get_meta( self::META_NAME, true ) );

		if ( ! is_file( $file ) || ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			wp_die( esc_html__( 'Payment proof was not found.', 'eilmo-checkout-flow' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . ( '' !== $name ? $name : 'payment-proof' ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Delete proof when an HPOS order is deleted. */
	public function delete_order_file( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$this->unlink_order_file( $order );
		}
	}

	/** Delete proof for legacy post-based order deletion. */
	public function delete_legacy_order_file( int $post_id ): void {
		if ( 'shop_order' === get_post_type( $post_id ) ) {
			$this->delete_order_file( $post_id );
		}
	}

	/** Resolve one configured manual method. */
	private static function get_method_config( string $method_key ): array {
		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$default_payment = is_array( $defaults['payment_methods'] ?? null ) ? $defaults['payment_methods'] : array();
		$stored_payment = is_array( $stored ) && is_array( $stored['payment_methods'] ?? null ) ? $stored['payment_methods'] : array();
		$settings = array_replace_recursive( $default_payment, $stored_payment );

		if ( array_key_exists( 'custom_methods', $stored_payment ) && is_array( $stored_payment['custom_methods'] ) ) {
			$settings['custom_methods'] = $stored_payment['custom_methods'];
		}

		$gateway_id = GatewaySettings::gateway_id( $method_key );
		if ( '' !== $gateway_id ) {
			return GatewaySettings::get( $gateway_id );
		}

		if ( 'bank_transfer' === $method_key ) {
			return is_array( $settings['bank_transfer'] ?? null ) ? $settings['bank_transfer'] : array();
		}

		foreach ( (array) ( $settings['custom_methods'] ?? array() ) as $method ) {
			if ( is_array( $method ) && sanitize_key( (string) ( $method['id'] ?? '' ) ) === $method_key ) {
				return $method;
			}
		}

		return array();
	}

	/** Get a non-public upload directory. */
	private static function get_storage_directory(): string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		return trailingslashit( (string) $uploads['basedir'] ) . 'eilmo-cf-private-payment-proofs';
	}

	/** Add Apache/IIS/index protections. */
	private function protect_directory( string $directory ): void {
		if ( ! file_exists( trailingslashit( $directory ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $directory ) . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( trailingslashit( $directory ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $directory ) . '.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( trailingslashit( $directory ) . 'web.config' ) ) {
			file_put_contents( trailingslashit( $directory ) . 'web.config', '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/** Simple per-IP upload throttling. */
	private function consume_rate_limit(): bool {
		$ip = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$key = 'eilmo_cf_proof_rate_' . md5( $ip );
		$count = absint( get_transient( $key ) );
		if ( $count >= self::MAX_UPLOADS_PER_HOUR ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Delete abandoned temporary uploads older than two hours. */
	private function cleanup_temporary_files(): void {
		$directory = trailingslashit( self::get_storage_directory() ) . 'temporary';
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$files = glob( trailingslashit( $directory ) . '*.{jpg,jpeg,png,webp}', GLOB_BRACE );
		if ( ! is_array( $files ) ) {
			return;
		}
		$cutoff = time() - ( 2 * HOUR_IN_SECONDS );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}

	/** Remove all proof metadata from an order object. */
	private static function clear_order_metadata( WC_Order $order ): void {
		foreach ( array( self::META_FILE, self::META_RELATIVE, self::META_NAME, self::META_MIME, self::META_METHOD ) as $meta_key ) {
			$order->delete_meta_data( $meta_key );
		}
	}

	/** Delete an attached proof file. */
	private function unlink_order_file( WC_Order $order ): void {
		$file = self::get_order_file( $order );
		if ( '' !== $file ) {
			wp_delete_file( $file );
		}
	}
}
