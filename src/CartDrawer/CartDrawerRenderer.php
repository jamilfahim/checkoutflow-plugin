<?php
/**
 * Cart Drawer renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\CartDrawer;

use EilmoCheckout\Access\CheckoutAccess;
use WC_Product;

\defined( 'ABSPATH' ) || exit;

/**
 * Renders the reusable WooCommerce Cart Drawer.
 */
final class CartDrawerRenderer {

	/**
	 * Render the complete drawer.
	 *
	 * @return string
	 */
	public function render(): string {

		$access =
			new CheckoutAccess();

		$requires_login =
			$access->requires_login();

		$count =
			$this->get_cart_count();

		$is_empty =
			$this->is_empty();

		ob_start();
		?>

		<div
			class="eilmo-cf-cart-drawer"
			data-eilmo-cart-drawer
			data-position="right"
			data-cart-empty="<?php echo $is_empty ? 'yes' : 'no'; ?>"
			hidden
		>

			<button
				type="button"
				class="eilmo-cf-cart-drawer__backdrop"
				data-eilmo-cart-drawer-close
				aria-label="<?php esc_attr_e( 'Close cart', 'eilmo-checkout-flow' ); ?>"
			></button>

			<aside
				class="eilmo-cf-cart-drawer__panel"
				role="dialog"
				aria-modal="true"
				aria-labelledby="eilmo-cf-cart-drawer-title"
			>

				<div
					class="eilmo-cf-cart-drawer__handle"
					aria-hidden="true"
				></div>

				<header class="eilmo-cf-cart-drawer__header">

					<div class="eilmo-cf-cart-drawer__heading">
						<h2
							id="eilmo-cf-cart-drawer-title"
							class="eilmo-cf-cart-drawer__title"
						>
							<?php esc_html_e( 'Your Cart', 'eilmo-checkout-flow' ); ?>
						</h2>

						<span
							class="eilmo-cf-cart-drawer__count"
							data-eilmo-cart-drawer-count
						>
							<?php echo esc_html( (string) $count ); ?>
						</span>
					</div>

					<button
						type="button"
						class="eilmo-cf-cart-drawer__close"
						data-eilmo-cart-drawer-close
						aria-label="<?php esc_attr_e( 'Close cart', 'eilmo-checkout-flow' ); ?>"
					>
						<svg
							viewBox="0 0 24 24"
							aria-hidden="true"
							focusable="false"
						>
							<path
								d="M6 6L18 18M18 6L6 18"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
							></path>
						</svg>
					</button>

				</header>

				<div class="eilmo-cf-cart-drawer__body">

					<div
						class="eilmo-cf-cart-drawer__message"
						data-eilmo-cart-drawer-message
						hidden
					></div>

					<div
						class="eilmo-cf-cart-drawer__items"
						data-eilmo-cart-drawer-items
					>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method escapes its own output.
						echo $this->render_items();
						?>
					</div>

				</div>

				<footer class="eilmo-cf-cart-drawer__footer">

					<div class="eilmo-cf-cart-drawer__subtotal">
						<span class="eilmo-cf-cart-drawer__subtotal-label">
							<?php esc_html_e( 'Subtotal', 'eilmo-checkout-flow' ); ?>
						</span>

						<span
							class="eilmo-cf-cart-drawer__subtotal-value"
							data-eilmo-cart-drawer-subtotal
						>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce formatted price HTML.
							echo $this->get_subtotal_html();
							?>
						</span>
					</div>

					<?php if ( $requires_login ) : ?>

						<a
							href="<?php echo esc_url( $access->get_login_url() ); ?>"
							class="eilmo-cf-cart-drawer__order eilmo-cf-cart-drawer__order--login<?php echo $is_empty ? ' is-disabled' : ''; ?>"
							data-eilmo-cart-order-login
							aria-disabled="<?php echo $is_empty ? 'true' : 'false'; ?>"
						>
							<?php echo esc_html( $access->get_login_button_label() ); ?>
						</a>

					<?php else : ?>

						<button
							type="button"
							class="eilmo-cf-cart-drawer__order"
							data-eilmo-cart-order-now
							<?php disabled( $is_empty ); ?>
						>
							<?php esc_html_e( 'Order Now', 'eilmo-checkout-flow' ); ?>
						</button>

					<?php endif; ?>

				</footer>

			</aside>

		</div>

		<?php

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Render cart items or empty state.
	 *
	 * @return string
	 */
	public function render_items(): string {

		$cart =
			$this->get_cart();

		if (
			! $cart ||
			$cart->is_empty()
		) {
			return $this->render_empty_state();
		}

		ob_start();

		foreach (
			$cart->get_cart() as
			$cart_key =>
			$cart_item
		) {
			$this->render_item(
				(string) $cart_key,
				$cart_item
			);
		}

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Get formatted cart subtotal.
	 *
	 * @return string
	 */
	public function get_subtotal_html(): string {

		$cart =
			$this->get_cart();

		if ( ! $cart ) {
			return wc_price(
				0
			);
		}

		return (string)
			$cart->get_cart_subtotal();
	}

	/**
	 * Get cart item count.
	 *
	 * @return int
	 */
	public function get_cart_count(): int {

		$cart =
			$this->get_cart();

		return $cart
			? absint(
				$cart->get_cart_contents_count()
			)
			: 0;
	}

	/**
	 * Determine whether cart is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {

		$cart =
			$this->get_cart();

		return (
			! $cart ||
			$cart->is_empty()
		);
	}

	/**
	 * Render one cart item.
	 *
	 * @param string              $cart_key  Cart key.
	 * @param array<string,mixed> $cart_item Cart item.
	 *
	 * @return void
	 */
	private function render_item(
		string $cart_key,
		array $cart_item
	): void {

		$product =
			isset(
				$cart_item[
					'data'
				]
			) &&
			$cart_item[
				'data'
			] instanceof WC_Product
				? $cart_item[
					'data'
				]
				: null;

		if (
			! $product ||
			! $product->exists()
		) {
			return;
		}

		$product_id =
			absint(
				$cart_item[
					'product_id'
				] ??
					$product->get_id()
			);

		$variation_id =
			absint(
				$cart_item[
					'variation_id'
				] ??
					0
			);

		$quantity =
			max(
				1,
				absint(
					$cart_item[
						'quantity'
					] ??
						1
				)
			);

		$minimum =
			max(
				1,
				absint(
					$product->get_min_purchase_quantity()
				)
			);

		$maximum =
			(int) $product->get_max_purchase_quantity();

		if (
			$product->is_sold_individually()
		) {
			$minimum = 1;
			$maximum = 1;
		}

		$product_name =
			$product->get_name();

		$product_url =
			$product->is_visible()
				? $product->get_permalink(
					$cart_item
				)
				: '';

		$image =
			$product->get_image(
				'woocommerce_thumbnail',
				array(
					'class' =>
						'eilmo-cf-cart-drawer__image-element',
				)
			);

		$variation_html =
			wc_get_formatted_cart_item_data(
				$cart_item,
				true
			);

		$price_html =
			$this->get_cart()
				? $this->get_cart()->get_product_price(
					$product
				)
				: wc_price(
					(float) $product->get_price()
				);
		?>

		<article
			class="eilmo-cf-cart-drawer__item"
			data-eilmo-cart-drawer-item
			data-cart-key="<?php echo esc_attr( $cart_key ); ?>"
			data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
			data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
			data-quantity="<?php echo esc_attr( (string) $quantity ); ?>"
			data-min="<?php echo esc_attr( (string) $minimum ); ?>"
			data-max="<?php echo esc_attr( (string) $maximum ); ?>"
		>

			<div class="eilmo-cf-cart-drawer__image">
				<?php if ( '' !== $product_url ) : ?>
					<a href="<?php echo esc_url( $product_url ); ?>">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product image HTML.
						echo $image;
						?>
					</a>
				<?php else : ?>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product image HTML.
					echo $image;
					?>
				<?php endif; ?>
			</div>

			<div class="eilmo-cf-cart-drawer__item-content">

				<div class="eilmo-cf-cart-drawer__item-top">
					<div class="eilmo-cf-cart-drawer__item-info">

						<?php if ( '' !== $product_url ) : ?>
							<a
								href="<?php echo esc_url( $product_url ); ?>"
								class="eilmo-cf-cart-drawer__item-title"
							>
								<?php echo esc_html( $product_name ); ?>
							</a>
						<?php else : ?>
							<span class="eilmo-cf-cart-drawer__item-title">
								<?php echo esc_html( $product_name ); ?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== trim( $variation_html ) ) : ?>
							<div class="eilmo-cf-cart-drawer__variation">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce formatted variation HTML.
								echo $variation_html;
								?>
							</div>
						<?php endif; ?>

					</div>

					<button
						type="button"
							class="eilmo-cf-cart-drawer__remove"
							data-eilmo-cart-remove
							aria-label="<?php
							/* translators: %s: Product name. */
							echo esc_attr( sprintf( __( 'Remove %s from cart', 'eilmo-checkout-flow' ), $product_name ) );
							?>"
					>
						<svg
							viewBox="0 0 24 24"
							aria-hidden="true"
							focusable="false"
						>
							<path
								d="M6 6L18 18M18 6L6 18"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
							></path>
						</svg>
					</button>
				</div>

				<div class="eilmo-cf-cart-drawer__item-bottom">

					<div class="eilmo-cf-cart-drawer__quantity">
						<button
							type="button"
							class="eilmo-cf-cart-drawer__quantity-button"
							data-eilmo-cart-quantity="decrease"
							aria-label="<?php esc_attr_e( 'Decrease quantity', 'eilmo-checkout-flow' ); ?>"
							<?php disabled( $quantity <= $minimum ); ?>
						>
							−
						</button>

						<span
							class="eilmo-cf-cart-drawer__quantity-value"
							data-eilmo-cart-quantity-value
						>
							<?php echo esc_html( (string) $quantity ); ?>
						</span>

						<button
							type="button"
							class="eilmo-cf-cart-drawer__quantity-button"
							data-eilmo-cart-quantity="increase"
							aria-label="<?php esc_attr_e( 'Increase quantity', 'eilmo-checkout-flow' ); ?>"
							<?php disabled( $maximum > 0 && $quantity >= $maximum ); ?>
						>
							+
						</button>
					</div>

					<div class="eilmo-cf-cart-drawer__price">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce formatted price HTML.
						echo $price_html;
						?>
					</div>

				</div>

			</div>

		</article>

		<?php
	}

	/**
	 * Render empty cart state.
	 *
	 * @return string
	 */
	private function render_empty_state(): string {

		ob_start();
		?>

		<div class="eilmo-cf-cart-drawer__empty">
			<span
				class="eilmo-cf-cart-drawer__empty-icon"
				aria-hidden="true"
			>
				<svg
					viewBox="0 0 24 24"
					focusable="false"
				>
					<path
						d="M3 4H5L7.2 14.2C7.35 14.9 7.98 15.4 8.7 15.4H17.3C18 15.4 18.62 14.92 18.79 14.24L20.3 8H6.2M9 20C9.55 20 10 19.55 10 19C10 18.45 9.55 18 9 18C8.45 18 8 18.45 8 19C8 19.55 8.45 20 9 20ZM17 20C17.55 20 18 19.55 18 19C18 18.45 17.55 18 17 18C16.45 18 16 18.45 16 19C16 19.55 16.45 20 17 20Z"
						fill="none"
						stroke="currentColor"
						stroke-width="1.8"
						stroke-linecap="round"
						stroke-linejoin="round"
					></path>
				</svg>
			</span>

			<h3 class="eilmo-cf-cart-drawer__empty-title">
				<?php esc_html_e( 'Your cart is empty', 'eilmo-checkout-flow' ); ?>
			</h3>

			<p class="eilmo-cf-cart-drawer__empty-text">
				<?php esc_html_e( 'Add something you like and it will appear here.', 'eilmo-checkout-flow' ); ?>
			</p>
		</div>

		<?php

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Get WooCommerce cart instance.
	 *
	 * @return \WC_Cart|null
	 */
	private function get_cart() {

		if (
			! function_exists(
				'WC'
			) ||
			! WC()->cart
		) {
			return null;
		}

		return WC()->cart;
	}
}
