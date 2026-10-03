<?php
/**
 * Cached remote documentation and tutorial feed.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Support;

use EilmoCheckout\Licensing\LicenseRepository;

defined( 'ABSPATH' ) || exit;

final class RemoteContent {

	private const CACHE_KEY = 'eilmo_cf_remote_help_content';
	private const FALLBACK_OPTION = 'eilmo_cf_remote_help_fallback';
	private const FAILURE_CACHE_KEY = 'eilmo_cf_remote_help_failure';
	private const FETCH_LOCK_KEY = 'eilmo_cf_remote_help_fetch_lock';
	private const CACHE_TTL = 86400;
	private const FAILURE_TTL = 900;
	private const FETCH_LOCK_TTL = 30;

	/**
	 * Retrieve current content, falling back to the last successful response.
	 *
	 * Failed remote requests are cached briefly so an unavailable documentation
	 * service cannot add a network timeout to every Help Center page load.
	 *
	 * @return array{documents:array,videos:array,generated_at:string}
	 */
	public function get(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$fallback = get_option( self::FALLBACK_OPTION, array() );
		$fallback = is_array( $fallback ) ? $fallback : array();
		$normalized_fallback = $this->normalize( $fallback );

		if ( get_transient( self::FAILURE_CACHE_KEY ) ) {
			return $normalized_fallback;
		}

		$server = $this->server_url();
		if ( ! $server ) {
			return $normalized_fallback;
		}

		/* Avoid a request stampede when several admins load the page together. */
		if ( get_transient( self::FETCH_LOCK_KEY ) ) {
			return $normalized_fallback;
		}

		set_transient( self::FETCH_LOCK_KEY, 1, self::FETCH_LOCK_TTL );

		$response = wp_safe_remote_get(
			trailingslashit( $server ) . 'wp-json/eilmo/v2/products/eilmo-checkout-flow/content',
			array(
				'timeout'     => 5,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		delete_transient( self::FETCH_LOCK_KEY );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->remember_failure();
			return $normalized_fallback;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) || empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
			$this->remember_failure();
			return $normalized_fallback;
		}

		$content = $this->normalize( $body['data'] );
		update_option( self::FALLBACK_OPTION, $content, false );
		set_transient( self::CACHE_KEY, $content, (int) apply_filters( 'eilmo_cf/help_content_cache_ttl', self::CACHE_TTL ) );
		delete_transient( self::FAILURE_CACHE_KEY );

		return $content;
	}

	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::FAILURE_CACHE_KEY );
		delete_transient( self::FETCH_LOCK_KEY );
	}

	/** Record a temporary backoff after a failed content request. */
	private function remember_failure(): void {
		set_transient(
			self::FAILURE_CACHE_KEY,
			1,
			(int) apply_filters( 'eilmo_cf/help_content_failure_ttl', self::FAILURE_TTL )
		);
	}

	private function normalize( array $data ): array {
		return array(
			'documents'    => $this->normalize_items( isset( $data['documents'] ) && is_array( $data['documents'] ) ? $data['documents'] : array(), false ),
			'videos'       => $this->normalize_items( isset( $data['videos'] ) && is_array( $data['videos'] ) ? $data['videos'] : array(), true ),
			'generated_at' => sanitize_text_field( (string) ( $data['generated_at'] ?? '' ) ),
		);
	}

	private function normalize_items( array $items, bool $video ): array {
		$clean = array();
		foreach ( array_slice( $items, 0, 100 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['title'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$row = array(
				'id'          => absint( $item['id'] ?? 0 ),
				'title'       => sanitize_text_field( (string) $item['title'] ),
				'description' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'category'    => sanitize_text_field( (string) ( $item['category'] ?? '' ) ),
				'url'         => esc_url_raw( (string) $item['url'] ),
				'order'       => absint( $item['order'] ?? 0 ),
			);
			if ( $video ) {
				$row['duration']  = sanitize_text_field( (string) ( $item['duration'] ?? '' ) );
				$row['thumbnail'] = esc_url_raw( (string) ( $item['thumbnail'] ?? '' ) );
			}
			if ( $row['url'] ) {
				$clean[] = $row;
			}
		}
		return $clean;
	}

	private function server_url(): string {
		if ( defined( 'EILMO_CF_LICENSE_SERVER_URL' ) && EILMO_CF_LICENSE_SERVER_URL ) {
			return untrailingslashit( esc_url_raw( EILMO_CF_LICENSE_SERVER_URL ) );
		}
		$state = ( new LicenseRepository() )->get();
		return untrailingslashit( esc_url_raw( (string) $state['server_url'] ) );
	}
}
