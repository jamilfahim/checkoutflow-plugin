<?php
/**
 * Product Gallery renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\ProductGallery\Rendering;

use EilmoCheckout\Core\Assets;
use EilmoCheckout\ProductGallery\Services\ProductGalleryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders reusable curated image galleries for a product.
 */
final class ProductGalleryRenderer {

	/**
	 * Repository.
	 *
	 * @var ProductGalleryRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param ProductGalleryRepository|null $repository Repository.
	 */
	public function __construct(
		?ProductGalleryRepository $repository = null
	) {

		$this->repository =
			$repository ?? new ProductGalleryRepository();
	}

	/**
	 * Render galleries assigned to this checkout for one product.
	 *
	 * @param int                  $product_id WooCommerce product ID.
	 * @param array<string, mixed> $settings   Checkout settings.
	 *
	 * @return string
	 */
	public function render_for_product(
		int $product_id,
		array $settings
	): string {

		if (
			$product_id <= 0
		) {
			return '';
		}

		$gallery_ids =
			isset( $settings['gallery_ids'] ) &&
			is_array( $settings['gallery_ids'] )
				? $settings['gallery_ids']
				: array();

		$galleries = ! empty( $gallery_ids )
			? $this->repository->get_by_ids( $gallery_ids )
			: $this->repository->get_linked_to_product( $product_id );

		if ( empty( $galleries ) ) {
			return '';
		}

		$display_settings =
			$this->repository->get_display_settings();

		foreach ( $galleries as $gallery ) {
			$linked_product_id =
				absint( $gallery['product_id'] ?? 0 );

			/*
			 * A reusable gallery must be explicitly linked to
			 * the product beside which it should render. This
			 * prevents one gallery from appearing on every item
			 * in a multiple-product checkout by accident.
			 */
			if (
				$linked_product_id <= 0 ||
				$product_id !== $linked_product_id
			) {
				continue;
			}

			return $this->render_gallery(
				$gallery,
				$display_settings
			);
		}

		/* Explicit IDs that do not match safely fall back to the linked gallery. */
		if ( ! empty( $gallery_ids ) ) {
			foreach ( $this->repository->get_linked_to_product( $product_id ) as $gallery ) {
				return $this->render_gallery( $gallery, $display_settings );
			}
		}

		return '';
	}

	/**
	 * Render the native WooCommerce featured and gallery images in the same UI.
	 *
	 * @param \WC_Product $product Product.
	 *
	 * @return string
	 */
	public function render_native_for_product( \WC_Product $product ): string {

		$image_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						'absint',
						array_merge(
							array( $product->get_image_id() ),
							$product->get_gallery_image_ids()
						)
					)
				)
			)
		);

		if ( empty( $image_ids ) ) {
			return '';
		}

		return $this->render_gallery(
			array(
				'id'        => 'woocommerce-product-' . $product->get_id(),
				'name'      => $product->get_name(),
				'image_ids' => $image_ids,
			),
			$this->repository->get_display_settings()
		);
	}


	/**
	 * Legacy compatibility endpoint for removed grouped galleries.
	 *
	 * @param string $gallery_id Group gallery ID.
	 *
	 * @return string
	 */
	public function render_for_group(
		string $gallery_id
	): string {

		unset( $gallery_id );

		return '';
	}

	/**
	 * Render one gallery slider.
	 *
	 * @param array<string, mixed> $gallery          Gallery.
	 * @param array<string, mixed> $display_settings Display settings.
	 *
	 * @return string
	 */
	private function render_gallery(
		array $gallery,
		array $display_settings
	): string {

		Assets::enqueue_legacy_frontend_style();

		$image_ids =
			isset( $gallery['image_ids'] ) &&
			is_array( $gallery['image_ids'] )
				? array_values(
					array_filter(
						array_map( 'absint', $gallery['image_ids'] )
					)
				)
				: array();

		$images = array();

		foreach ( $image_ids as $image_id ) {

			$display = wp_get_attachment_image_src(
				$image_id,
				'woocommerce_single'
			);

			$full = wp_get_attachment_image_src(
				$image_id,
				'full'
			);

			if ( ! $display || ! $full ) {
				continue;
			}

			$alt = get_post_meta(
				$image_id,
				'_wp_attachment_image_alt',
				true
			);

			$images[] = array(
				'id'          => $image_id,
				'display_url' => (string) $display[0],
				'full_url'    => (string) $full[0],
				'alt'         => sanitize_text_field( (string) $alt ),
			);
		}

		if ( empty( $images ) ) {
			return '';
		}

		$gallery_id =
			sanitize_key( (string) ( $gallery['id'] ?? '' ) );

		$gallery_name =
			sanitize_text_field( (string) ( $gallery['name'] ?? '' ) );

		$has_multiple = count( $images ) > 1;

		$show_arrows =
			$has_multiple &&
			'yes' === ( $display_settings['show_arrows'] ?? 'yes' );

		$show_dots =
			$has_multiple &&
			'yes' === ( $display_settings['show_dots'] ?? 'yes' );

		$show_thumbnails =
			$has_multiple &&
			'yes' === ( $display_settings['show_thumbnails'] ?? 'yes' );

		$lightbox =
			'yes' === ( $display_settings['lightbox'] ?? 'yes' );

		$loop =
			'yes' === ( $display_settings['loop'] ?? 'yes' );

		ob_start();
		?>
		<div
			class="eilmo-cf-product-gallery<?php echo $show_thumbnails ? ' eilmo-cf-product-gallery--has-thumbnails' : ''; ?>"
			data-eilmo-product-gallery
			data-gallery-id="<?php echo esc_attr( $gallery_id ); ?>"
			data-loop="<?php echo $loop ? 'yes' : 'no'; ?>"
			data-lightbox="<?php echo $lightbox ? 'yes' : 'no'; ?>"
			aria-label="<?php echo esc_attr( $gallery_name ); ?>"
		>
			<div class="eilmo-cf-product-gallery__viewport">
				<div
					class="eilmo-cf-product-gallery__track"
					data-eilmo-gallery-track
				>
					<?php foreach ( $images as $index => $image ) : ?>
						<div
							class="eilmo-cf-product-gallery__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-eilmo-gallery-slide
							data-index="<?php echo esc_attr( (string) $index ); ?>"
						>
							<?php if ( $lightbox ) : ?>
								<button
									type="button"
									class="eilmo-cf-product-gallery__image-button"
									data-eilmo-gallery-open-lightbox
									aria-label="<?php esc_attr_e( 'View larger image', 'eilmo-checkout-flow' ); ?>"
								>
							<?php endif; ?>

							<img
								class="eilmo-cf-product-gallery__image"
								src="<?php echo esc_url( $image['display_url'] ); ?>"
								data-full-src="<?php echo esc_url( $image['full_url'] ); ?>"
								alt="<?php echo esc_attr( $image['alt'] ); ?>"
								<?php if ( $index > 0 ) : ?>loading="lazy"<?php endif; ?>
							>

							<?php if ( $lightbox ) : ?>
								<span
									class="eilmo-cf-product-gallery__zoom"
									aria-hidden="true"
								>
									<svg class="eilmo-cf-product-gallery__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
										<circle cx="11" cy="11" r="6.5"></circle>
										<path d="M16 16l4.25 4.25"></path>
										<path d="M11 8v6M8 11h6"></path>
									</svg>
								</span>
								</button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $show_arrows ) : ?>
					<button
						type="button"
						class="eilmo-cf-product-gallery__arrow eilmo-cf-product-gallery__arrow--prev"
						data-eilmo-gallery-prev
						aria-label="<?php esc_attr_e( 'Previous image', 'eilmo-checkout-flow' ); ?>"
					>
						<svg class="eilmo-cf-product-gallery__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path d="M15 18l-6-6 6-6"></path>
						</svg>
					</button>

					<button
						type="button"
						class="eilmo-cf-product-gallery__arrow eilmo-cf-product-gallery__arrow--next"
						data-eilmo-gallery-next
						aria-label="<?php esc_attr_e( 'Next image', 'eilmo-checkout-flow' ); ?>"
					>
						<svg class="eilmo-cf-product-gallery__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path d="M9 18l6-6-6-6"></path>
						</svg>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $show_dots ) : ?>
				<div
					class="eilmo-cf-product-gallery__dots"
					data-eilmo-gallery-dots
					aria-label="<?php esc_attr_e( 'Gallery navigation', 'eilmo-checkout-flow' ); ?>"
				>
					<?php foreach ( $images as $index => $image ) : ?>
						<button
							type="button"
							class="eilmo-cf-product-gallery__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-eilmo-gallery-dot="<?php echo esc_attr( (string) $index ); ?>"
							aria-label="<?php
							echo esc_attr(
								sprintf(
									/* translators: %d: Gallery image number. */
									__( 'Show image %d', 'eilmo-checkout-flow' ),
									$index + 1
								)
							);
							?>"
						></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $show_thumbnails ) : ?>
				<div
					class="eilmo-cf-product-gallery__thumbnails"
					data-eilmo-gallery-thumbnails
				>
					<?php foreach ( $images as $index => $image ) : ?>
						<button
							type="button"
							class="eilmo-cf-product-gallery__thumbnail<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-eilmo-gallery-thumbnail="<?php echo esc_attr( (string) $index ); ?>"
							aria-label="<?php
							echo esc_attr(
								sprintf(
									/* translators: %d: Gallery image number. */
									__( 'Show image %d', 'eilmo-checkout-flow' ),
									$index + 1
								)
							);
							?>"
						>
							<img
								src="<?php echo esc_url( $image['display_url'] ); ?>"
								alt=""
								loading="lazy"
							>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php

		$output = ob_get_clean();

		return false !== $output
			? $output
			: '';
	}
}
