<?php
/**
 * Product Gallery repository.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ProductGallery\Services;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the reusable Product Gallery library.
 */
final class ProductGalleryRepository {

	/**
	 * Determine whether Product Gallery is globally enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {

		return true;
	}

	/**
	 * Get Product Gallery display settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_display_settings(): array {

		$settings = $this->get_settings();

		return isset( $settings['product_gallery'] ) &&
			is_array( $settings['product_gallery'] )
				? $settings['product_gallery']
				: array();
	}

	/**
	 * Get all enabled reusable galleries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_enabled_galleries(): array {

		$display_settings = $this->get_display_settings();

		$galleries = isset( $display_settings['galleries'] ) &&
			is_array( $display_settings['galleries'] )
				? $display_settings['galleries']
				: array();

		return array_values(
			array_filter(
				$galleries,
				static function ( $gallery ): bool {

					return (
						is_array( $gallery ) &&
						'yes' === ( $gallery['enabled'] ?? 'yes' ) &&
						absint( $gallery['product_id'] ?? 0 ) > 0 &&
						! empty( $gallery['id'] ) &&
						! empty( $gallery['image_ids'] ) &&
						is_array( $gallery['image_ids'] )
					);
				}
			)
		);
	}

	/**
	 * Get enabled galleries for one scope.
	 *
	 * Galleries saved before scope support are treated as product galleries.
	 *
	 * @param string $scope Gallery scope: product or group.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_enabled_by_scope(
		string $scope
	): array {

		return 'product' === $scope ? $this->get_enabled_galleries() : array();
	}

	/**
	 * Get enabled galleries linked to one WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_linked_to_product( int $product_id ): array {

		if ( $product_id <= 0 ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->get_enabled_galleries(),
				static function ( $gallery ) use ( $product_id ): bool {
					return $product_id === absint( $gallery['product_id'] ?? 0 );
				}
			)
		);
	}

	/**
	 * Get one enabled gallery by ID and scope.
	 *
	 * @param string $gallery_id Gallery ID.
	 * @param string $scope      Gallery scope.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_one_by_id_and_scope(
		string $gallery_id,
		string $scope
	): ?array {

		$gallery_id = sanitize_key( $gallery_id );

		if ( '' === $gallery_id ) {
			return null;
		}

		foreach ( $this->get_enabled_by_scope( $scope ) as $gallery ) {
			if ( $gallery_id === sanitize_key( (string) ( $gallery['id'] ?? '' ) ) ) {
				return $gallery;
			}
		}

		return null;
	}

	/**
	 * Get enabled galleries by requested IDs.
	 *
	 * Requested order is preserved.
	 *
	 * @param array<int, string> $gallery_ids Gallery IDs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_ids(
		array $gallery_ids
	): array {

		$normalized_ids = array();

		foreach ( $gallery_ids as $gallery_id ) {
			$gallery_id = sanitize_key( (string) $gallery_id );

			if ( '' !== $gallery_id ) {
				$normalized_ids[] = $gallery_id;
			}
		}

		$normalized_ids = array_values(
			array_unique( $normalized_ids )
		);

		if ( empty( $normalized_ids ) ) {
			return array();
		}

		$indexed = array();

		foreach ( $this->get_enabled_galleries() as $gallery ) {
			$id = sanitize_key( (string) ( $gallery['id'] ?? '' ) );

			if ( '' !== $id ) {
				$indexed[ $id ] = $gallery;
			}
		}

		$result = array();

		foreach ( $normalized_ids as $gallery_id ) {
			if ( isset( $indexed[ $gallery_id ] ) ) {
				$result[] = $indexed[ $gallery_id ];
			}
		}

		return $result;
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults = CheckoutSettings::get_defaults();
		$stored   = get_option( CheckoutSettings::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_replace_recursive(
			$defaults,
			$stored
		);

		/*
		 * Preserve indexed gallery rows exactly. Recursive
		 * replacement can otherwise merge numeric indexes.
		 */
		if (
			isset( $stored['product_gallery']['galleries'] ) &&
			is_array( $stored['product_gallery']['galleries'] )
		) {
			$settings['product_gallery']['galleries'] =
				$stored['product_gallery']['galleries'];
		}

		return is_array( $settings )
			? $settings
			: array();
	}
}
