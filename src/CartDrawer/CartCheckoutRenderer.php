<?php
/**
 * Cart Checkout renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\CartDrawer;

use EilmoCheckout\Admin\QuickCheckoutSettings;
use WC_Product;

\defined( 'ABSPATH' ) || exit;

/**
 * Renders the Cart Checkout shell and compact cart selection.
 *
 * Cart Checkout reuses the normal CheckoutRenderer and order
 * pipeline. Customer-facing Cart Checkout labels are sourced
 * exclusively from Quick Checkout settings/defaults.
 */
final class CartCheckoutRenderer {

	/**
	 * Render the global Cart Checkout shell.
	 *
	 * @return string
	 */
	public function render(): string {

		$texts =
			$this->get_quick_texts();

		$drawer_title =
			$this->get_text(
				$texts,
				'drawer_title',
				__(
					'Almost Done!',
					'eilmo-checkout-flow'
				)
			);

		$drawer_description =
			$this->get_text(
				$texts,
				'drawer_description',
				__(
					'Review your selection and complete your order.',
					'eilmo-checkout-flow'
				)
			);

		$close_label =
			$this->get_text(
				$texts,
				'close_label',
				__(
					'Close',
					'eilmo-checkout-flow'
				)
			);

		ob_start();
		?>

		<div
			class="eilmo-cf-cart-checkout"
			data-eilmo-cart-checkout
			aria-hidden="true"
			hidden
		>

			<button
				type="button"
				class="eilmo-cf-cart-checkout__backdrop"
				data-eilmo-cart-checkout-close
				aria-label="<?php echo esc_attr( $close_label ); ?>"
			></button>

			<section
				class="eilmo-cf-cart-checkout__panel"
				data-eilmo-cart-checkout-panel
				role="dialog"
				aria-modal="true"
				aria-labelledby="eilmo-cf-cart-checkout-title"
			>

				<header class="eilmo-cf-cart-checkout__header">

					<div class="eilmo-cf-cart-checkout__heading">

						<h2
							id="eilmo-cf-cart-checkout-title"
							class="eilmo-cf-cart-checkout__title"
						>
							<?php echo esc_html( $drawer_title ); ?>
						</h2>

						<?php if ( '' !== $drawer_description ) : ?>

							<p class="eilmo-cf-cart-checkout__description">
								<?php echo esc_html( $drawer_description ); ?>
							</p>

						<?php endif; ?>

					</div>

					<button
						type="button"
						class="eilmo-cf-cart-checkout__close"
						data-eilmo-cart-checkout-close
						aria-label="<?php echo esc_attr( $close_label ); ?>"
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

				<div class="eilmo-cf-cart-checkout__body">

					<div
						class="eilmo-cf-cart-checkout__message"
						data-eilmo-cart-checkout-message
						role="alert"
						hidden
					></div>

					<div
						class="eilmo-cf-cart-checkout__loading"
						data-eilmo-cart-checkout-loading
						hidden
					>

						<span
							class="eilmo-cf-cart-checkout__spinner"
							aria-hidden="true"
						></span>

						<span>
							<?php
							esc_html_e(
								'Preparing checkout...',
								'eilmo-checkout-flow'
							);
							?>
						</span>

					</div>

					<div
						class="eilmo-cf-cart-checkout__content"
						data-eilmo-cart-checkout-content
					></div>

				</div>

			</section>

		</div>

		<?php

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Render compact WooCommerce cart selection.
	 *
	 * @return string
	 */
	public function render_selection(): string {

		$cart =
			$this->get_cart();

		if (
			! $cart ||
			$cart->is_empty()
		) {
			return '';
		}

		$texts =
			$this->get_quick_texts();

		$selection_heading =
			$this->get_text(
				$texts,
				'selection_heading',
				__(
					'Your Selection',
					'eilmo-checkout-flow'
				)
			);

		$change_selection =
			$this->get_text(
				$texts,
				'change_selection',
				__(
					'Change',
					'eilmo-checkout-flow'
				)
			);

		ob_start();
		?>

		<section
			class="eilmo-cf-cart-selection"
			data-eilmo-cart-checkout-selection
		>

			<div class="eilmo-cf-cart-selection__header">

				<h3 class="eilmo-cf-cart-selection__heading">
					<?php echo esc_html( $selection_heading ); ?>
				</h3>

				<button
					type="button"
					class="eilmo-cf-cart-selection__change"
					data-eilmo-cart-checkout-change
				>
					<?php echo esc_html( $change_selection ); ?>
				</button>

			</div>

			<div class="eilmo-cf-cart-selection__list">

				<?php
				foreach (
					$cart->get_cart() as
						$cart_key =>
							$cart_item
				) {

					$this->render_selection_item(
						(string) $cart_key,
						$cart_item
					);
				}
				?>

			</div>

		</section>

		<?php

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Prepare normal CheckoutRenderer settings for
	 * Cart Checkout.
	 *
	 * Only Quick Checkout text/defaults are used for
	 * customer-facing Cart Checkout labels.
	 *
	 * Main Checkout settings continue to provide
	 * business configuration only.
	 *
	 * @param array<string,mixed> $settings Checkout settings.
	 *
	 * @return array<string,mixed>
	 */
	public function prepare_checkout_settings(
		array $settings
	): array {

		$texts =
			$this->get_quick_texts();

		$settings[
			'combo_title'
		] =
			$this->get_text(
				$texts,
				'combo_heading',
				__(
					'Special Combo Offer',
					'eilmo-checkout-flow'
				)
			);

		$settings[
			'order_button_label'
		] =
			$this->get_text(
				$texts,
				'order_button',
				__(
					'Place Order',
					'eilmo-checkout-flow'
				)
			);

		$settings[
			'order_processing_label'
		] =
			$this->get_text(
				$texts,
				'processing_label',
				__(
					'Processing...',
					'eilmo-checkout-flow'
				)
			);

		return $settings;
	}

	/**
	 * Apply Quick Checkout labels to HTML generated
	 * by the shared CheckoutRenderer.
	 *
	 * This is intentionally scoped to Cart Checkout.
	 * Normal Checkout and Single Product Quick Checkout
	 * remain unchanged.
	 *
	 * @param string $html Shared checkout HTML.
	 *
	 * @return string
	 */
	public function apply_quick_checkout_texts(
		string $html
	): string {

		if (
			'' ===
				trim(
					$html
				)
		) {
			return $html;
		}

		$texts =
			$this->get_quick_texts();

		$sections =
			array(

				array(
					'section' =>
						'eilmo-cf-combo-offers',

					'header' =>
						'eilmo-cf-combo-offers__header',

					'title' =>
						'eilmo-cf-combo-offers__title',

					'text' =>
						$this->get_text(
							$texts,
							'combo_heading',
							__(
								'Special Combo Offer',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-special-discount-list',

					'header' =>
						'eilmo-cf-special-discount-list__header',

					'title' =>
						'eilmo-cf-special-discount-list__title',

					'text' =>
						$this->get_text(
							$texts,
							'order_bump_heading',
							__(
								'You May Also Like',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-delivery',

					'header' =>
						'eilmo-cf-delivery__header',

					'title' =>
						'eilmo-cf-delivery__title',

					'text' =>
						$this->get_text(
							$texts,
							'delivery_heading',
							__(
								'Delivery',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'eilmo-cf-delivery__required',
				),

				array(
					'section' =>
						'eilmo-cf-customer-form',

					'header' =>
						'eilmo-cf-customer-form__header',

					'title' =>
						'eilmo-cf-customer-form__title',

					'text' =>
						$this->get_text(
							$texts,
							'customer_heading',
							__(
								'Customer Information',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-advance-payment',

					'header' =>
						'eilmo-cf-advance-payment__header',

					'title' =>
						'eilmo-cf-advance-payment__title',

					'text' =>
						$this->get_text(
							$texts,
							'advance_payment_heading',
							__(
								'Payment Option',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-payment-methods',

					'header' =>
						'eilmo-cf-payment-methods__header',

					'title' =>
						'eilmo-cf-payment-methods__title',

					'text' =>
						$this->get_text(
							$texts,
							'payment_heading',
							__(
								'Payment Method',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-coupon',

					'header' =>
						'eilmo-cf-coupon__header',

					'title' =>
						'eilmo-cf-coupon__title',

					'text' =>
						$this->get_text(
							$texts,
							'coupon_heading',
							__(
								'Coupon',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),

				array(
					'section' =>
						'eilmo-cf-summary',

					'header' =>
						'eilmo-cf-summary__header',

					'title' =>
						'eilmo-cf-summary__title',

					'text' =>
						$this->get_text(
							$texts,
							'summary_heading',
							__(
								'Order Summary',
								'eilmo-checkout-flow'
							)
						),

					'preserve' =>
						'',
				),
			);

		foreach (
			$sections as
				$section
		) {

			$title =
				(string) (
					$section[
						'text'
					] ??
						''
				);

			if (
				'' ===
					$title
			) {
				continue;
			}

			$html =
				$this->ensure_section_heading(
					$html,
					(string) (
						$section[
							'section'
						] ??
							''
					),
					(string) (
						$section[
							'header'
						] ??
							''
					),
					(string) (
						$section[
							'title'
						] ??
							''
					),
					$title
				);

			$html =
				$this->replace_heading_text(
					$html,
					(string) (
						$section[
							'title'
						] ??
							''
					),
					$title,
					(string) (
						$section[
							'preserve'
						] ??
							''
					)
				);
		}

		return $html;
	}

	/**
	 * Render hidden normal-product adapters for checkout.js.
	 *
	 * These adapters intentionally use the same data contract as the
	 * working Single Product Quick Checkout adapter. Frontend prices are
	 * display/calculation previews only; the order server reloads prices.
	 *
	 * @param array<int,array<string,int>> $items Normalized cart items.
	 *
	 * @return string
	 */
	public function render_product_adapters(
		array $items
	): string {

		if (
			empty(
				$items
			)
		) {
			return '';
		}

		ob_start();

		foreach (
			$items as
				$item
		) {

			if (
				! is_array(
					$item
				)
			) {
				continue;
			}

			$product_id =
				absint(
					$item[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$item[
						'variation_id'
					] ??
						0
				);

			$quantity =
				max(
					1,
					absint(
						$item[
							'quantity'
						] ??
							1
					)
				);

			if (
				$product_id <= 0
			) {
				continue;
			}

			$product =
				wc_get_product(
					$variation_id > 0
						? $variation_id
						: $product_id
				);

			if (
				! $product instanceof WC_Product ||
				! $product->exists()
			) {
				continue;
			}

			if (
				$variation_id > 0 &&
				absint(
					$product->get_parent_id()
				) !==
					$product_id
			) {
				continue;
			}

			if (
				0 ===
					$variation_id &&
				$product->is_type(
					'variable'
				)
			) {
				continue;
			}

			$price =
				function_exists(
					'wc_get_price_to_display'
				)
					? (float) wc_get_price_to_display(
						$product
					)
					: (float) $product->get_price();

			$price =
				max(
					0.0,
					$price
				);
			?>

			<div
				class="eilmo-cf-cart-checkout__product-adapter"
				data-eilmo-cart-checkout-product-adapter
				data-summary-quantity-locked="yes"
				data-item-name="<?php echo esc_attr( $product->get_name() ); ?>"
				data-item-variation="<?php echo esc_attr( $product->is_type( 'variation' ) ? wc_get_formatted_variation( $product, true, false, true ) : '' ); ?>"
				data-item-image="<?php echo esc_url( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '' ); ?>"
				data-eilmo-product-item
				data-eilmo-item
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-parent-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-parent-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
				data-price="<?php echo esc_attr( wc_format_decimal( $price ) ); ?>"
				data-product-price="<?php echo esc_attr( wc_format_decimal( $price ) ); ?>"
				hidden
			>

				<input
					type="hidden"
					value="<?php echo esc_attr( (string) $quantity ); ?>"
					data-eilmo-quantity-input
					data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					data-parent-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					data-parent-id="<?php echo esc_attr( (string) $product_id ); ?>"
					data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
					data-price="<?php echo esc_attr( wc_format_decimal( $price ) ); ?>"
				>

			</div>

			<?php
		}

		$output =
			ob_get_clean();

		return false !== $output
			? $output
			: '';
	}

	/**
	 * Render one compact selection item.
	 *
	 * @param string              $cart_key  Cart key.
	 * @param array<string,mixed> $cart_item Cart item.
	 *
	 * @return void
	 */
	private function render_selection_item(
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

		$product_name =
			sanitize_text_field(
				(string) $product->get_name()
			);

		if (
			$product->is_type(
				'variation'
			)
		) {
			$parent =
				wc_get_product(
					$product->get_parent_id()
				);

			if (
				$parent instanceof WC_Product &&
				$parent->exists()
			) {
				$product_name =
					sanitize_text_field(
						(string) $parent->get_name()
					);
			}
		}

		$image =
			$product->get_image(
				'woocommerce_thumbnail',
				array(
					'class' =>
						'eilmo-cf-cart-selection__image-element',

					'loading' =>
						'lazy',
				)
			);

		$variation_text =
			$this->get_variation_text(
				$cart_item,
				$product
			);

		$price_html =
			(string) $product->get_price_html();

		if (
			'' ===
				trim(
					$price_html
				)
		) {
			$display_price =
				function_exists(
					'wc_get_price_to_display'
				)
					? (float) wc_get_price_to_display(
						$product
					)
					: (float) $product->get_price();

			$price_html =
				wc_price(
					$display_price
				);
		}
		?>

		<article
			class="eilmo-cf-cart-selection__item"
			data-cart-key="<?php echo esc_attr( $cart_key ); ?>"
		>

			<div class="eilmo-cf-cart-selection__image">

				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product image HTML.
				echo $image;
				?>

			</div>

			<div class="eilmo-cf-cart-selection__content">

				<strong class="eilmo-cf-cart-selection__title">
					<?php echo esc_html( $product_name ); ?>
				</strong>

				<?php if ( '' !== $variation_text ) : ?>

					<span class="eilmo-cf-cart-selection__variation">
						<?php echo esc_html( $variation_text ); ?>
					</span>

				<?php endif; ?>

				<span class="eilmo-cf-cart-selection__quantity">

					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: quantity. */
							__(
								'Qty: %d',
								'eilmo-checkout-flow'
							),
							$quantity
						)
					);
					?>

				</span>

			</div>

			<div class="eilmo-cf-cart-selection__price">

				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce formatted price HTML.
				echo $price_html;
				?>

			</div>

		</article>

		<?php
	}

	/**
	 * Build readable variation value text.
	 *
	 * Example: Blue, M
	 *
	 * @param array<string,mixed> $cart_item Cart item.
	 * @param WC_Product          $product   Product.
	 *
	 * @return string
	 */
	private function get_variation_text(
		array $cart_item,
		WC_Product $product
	): string {

		$variation =
			isset(
				$cart_item[
					'variation'
				]
			) &&
			is_array(
				$cart_item[
					'variation'
				]
			)
				? $cart_item[
					'variation'
				]
				: array();

		if (
			empty(
				$variation
			) &&
			$product->is_type(
				'variation'
			)
		) {
			$variation =
				$product
					->get_variation_attributes();
		}

		$values =
			array();

		foreach (
			$variation as
				$attribute =>
					$value
		) {

			$value =
				wc_clean(
					(string) $value
				);

			if (
				'' ===
					$value
			) {
				continue;
			}

			$taxonomy =
				str_replace(
					'attribute_',
					'',
					(string) $attribute
				);

			$label =
				$value;

			if (
				taxonomy_exists(
					$taxonomy
				)
			) {
				$term =
					get_term_by(
						'slug',
						$value,
						$taxonomy
					);

				if (
					$term &&
					! is_wp_error(
						$term
					)
				) {
					$label =
						(string) $term->name;
				}
			}

			$label =
				sanitize_text_field(
					$label
				);

			if (
				'' !==
					$label &&
				! in_array(
					$label,
					$values,
					true
				)
			) {
				$values[] =
					$label;
			}
		}

		return implode(
			', ',
			$values
		);
	}

	/**
	 * Get Quick Checkout customer-facing texts.
	 *
	 * Resolution:
	 *
	 * Saved Quick Checkout value
	 * -> Quick Checkout default value.
	 *
	 * Main Settings are never read here.
	 *
	 * @return array<string,string>
	 */
	private function get_quick_texts(): array {

		$defaults =
			QuickCheckoutSettings::get_defaults();

		$settings =
			QuickCheckoutSettings::get_settings();

		$default_texts =
			isset(
				$defaults[
					'texts'
				]
			) &&
			is_array(
				$defaults[
					'texts'
				]
			)
				? $defaults[
					'texts'
				]
				: array();

		$saved_texts =
			isset(
				$settings[
					'texts'
				]
			) &&
			is_array(
				$settings[
					'texts'
				]
			)
				? $settings[
					'texts'
				]
				: array();

		$keys =
			array_values(
				array_unique(
					array_merge(
						array_keys(
							$default_texts
						),
						array_keys(
							$saved_texts
						)
					)
				)
			);

		$texts =
			array();

		foreach (
			$keys as
				$key
		) {

			$key =
				(string) $key;

			$fallback =
				sanitize_text_field(
					(string) (
						$default_texts[
							$key
						] ??
							''
					)
				);

			$value =
				sanitize_text_field(
					(string) (
						$saved_texts[
							$key
						] ??
							$fallback
					)
				);

			if (
				'' ===
					trim(
						$value
					)
			) {
				$value =
					$fallback;
			}

			$texts[
				$key
			] =
				$value;
		}

		return $texts;
	}

	/**
	 * Get one Quick Checkout text.
	 *
	 * @param array<string,string> $texts    Texts.
	 * @param string               $key      Key.
	 * @param string               $fallback Quick fallback.
	 *
	 * @return string
	 */
	private function get_text(
		array $texts,
		string $key,
		string $fallback
	): string {

		$value =
			sanitize_text_field(
				(string) (
					$texts[
						$key
					] ??
						''
				)
			);

		if (
			'' !==
				trim(
					$value
				)
		) {
			return $value;
		}

		return sanitize_text_field(
			$fallback
		);
	}

	/**
	 * Ensure a shared checkout section contains its title.
	 *
	 * Some shared renderers may omit the title/header when
	 * their global Main Settings title is empty. Cart Checkout
	 * must not depend on that global title, so the Quick title
	 * is restored locally when required.
	 *
	 * @param string $html          HTML.
	 * @param string $section_class Section class.
	 * @param string $header_class  Header class.
	 * @param string $title_class   Title class.
	 * @param string $title         Quick title.
	 *
	 * @return string
	 */
	private function ensure_section_heading(
		string $html,
		string $section_class,
		string $header_class,
		string $title_class,
		string $title
	): string {

		if (
			'' ===
				$section_class ||
			'' ===
				$header_class ||
			'' ===
				$title_class ||
			'' ===
				$title ||
			$this->html_has_class(
				$html,
				$title_class
			)
		) {
			return $html;
		}

		$title_html =
			'<h3 class="' .
			esc_attr(
				$title_class
			) .
			'">' .
			esc_html(
				$title
			) .
			'</h3>';

		$header_pattern =
			$this->get_opening_tag_pattern(
				'div',
				$header_class
			);

		$result =
			preg_replace_callback(
				$header_pattern,
				static function (
					array $matches
				) use (
					$title_html
				): string {

					return (string) (
						$matches[0] ??
							''
					) .
						$title_html;
				},
				$html,
				1
			);

		if (
			is_string(
				$result
			) &&
			$result !==
				$html
		) {
			return $result;
		}

		$section_pattern =
			$this->get_opening_tag_pattern(
				'section',
				$section_class
			);

		$header_html =
			'<div class="' .
			esc_attr(
				$header_class
			) .
			'">' .
			$title_html .
			'</div>';

		$result =
			preg_replace_callback(
				$section_pattern,
				static function (
					array $matches
				) use (
					$header_html
				): string {

					return (string) (
						$matches[0] ??
							''
					) .
						$header_html;
				},
				$html,
				1
			);

		return is_string(
			$result
		)
			? $result
			: $html;
	}

	/**
	 * Replace a heading's visible text while preserving
	 * one optional child element such as Delivery's
	 * required marker.
	 *
	 * @param string $html           HTML.
	 * @param string $class_name     Heading class.
	 * @param string $text           Quick Checkout text.
	 * @param string $preserve_class Optional child class.
	 *
	 * @return string
	 */
	private function replace_heading_text(
		string $html,
		string $class_name,
		string $text,
		string $preserve_class = ''
	): string {

		if (
			'' ===
				$class_name ||
			'' ===
				$text
		) {
			return $html;
		}

		$class =
			preg_quote(
				$class_name,
				'/'
			);

		$pattern =
			'/<(?P<tag>h[1-6])\b' .
			'(?P<attributes>' .
				'(?=[^>]*\bclass\s*=\s*' .
					'["\'][^"\']*\b' .
					$class .
					'\b[^"\']*["\']' .
				')' .
				'[^>]*' .
			')>' .
			'(?P<content>.*?)' .
			'<\/(?P=tag)>/is';

		$result =
			preg_replace_callback(
				$pattern,
				function (
					array $matches
				) use (
					$text,
					$preserve_class
				): string {

					$preserved =
						'';

					if (
						'' !==
							$preserve_class
					) {
						$preserved =
							$this->find_element_by_class(
								(string) (
									$matches[
										'content'
									] ??
										''
								),
								'span',
								$preserve_class
							);
					}

					$content =
						esc_html(
							$text
						);

					if (
						'' !==
							$preserved
					) {
						$content .=
							' ' .
							$preserved;
					}

					return '<' .
						(string) (
							$matches[
								'tag'
							] ??
								'h3'
						) .
						(string) (
							$matches[
								'attributes'
							] ??
								''
						) .
						'>' .
						$content .
						'</' .
						(string) (
							$matches[
								'tag'
							] ??
								'h3'
						) .
						'>';
				},
				$html
			);

		return is_string(
			$result
		)
			? $result
			: $html;
	}

	/**
	 * Find a child element by class.
	 *
	 * @param string $html       HTML.
	 * @param string $tag        Tag.
	 * @param string $class_name Class.
	 *
	 * @return string
	 */
	private function find_element_by_class(
		string $html,
		string $tag,
		string $class_name
	): string {

		$tag =
			preg_quote(
				$tag,
				'/'
			);

		$class =
			preg_quote(
				$class_name,
				'/'
			);

		$pattern =
			'/<' .
			$tag .
			'\b' .
			'(?=[^>]*\bclass\s*=\s*' .
				'["\'][^"\']*\b' .
				$class .
				'\b[^"\']*["\']' .
			')' .
			'[^>]*>' .
			'.*?' .
			'<\/' .
			$tag .
			'>/is';

		if (
			1 ===
				preg_match(
					$pattern,
					$html,
					$matches
				) &&
			isset(
				$matches[0]
			)
		) {
			return (string) $matches[0];
		}

		return '';
	}

	/**
	 * Check whether HTML contains an element with class.
	 *
	 * @param string $html       HTML.
	 * @param string $class_name Class.
	 *
	 * @return bool
	 */
	private function html_has_class(
		string $html,
		string $class_name
	): bool {

		$class =
			preg_quote(
				$class_name,
				'/'
			);

		$pattern =
			'/<[^>]+\bclass\s*=\s*' .
			'["\'][^"\']*\b' .
			$class .
			'\b[^"\']*["\']' .
			'[^>]*>/is';

		return 1 ===
			preg_match(
				$pattern,
				$html
			);
	}

	/**
	 * Build an opening-tag pattern by class.
	 *
	 * @param string $tag        Tag.
	 * @param string $class_name Class.
	 *
	 * @return string
	 */
	private function get_opening_tag_pattern(
		string $tag,
		string $class_name
	): string {

		$tag =
			preg_quote(
				$tag,
				'/'
			);

		$class =
			preg_quote(
				$class_name,
				'/'
			);

		return '/<' .
			$tag .
			'\b' .
			'(?=[^>]*\bclass\s*=\s*' .
				'["\'][^"\']*\b' .
				$class .
				'\b[^"\']*["\']' .
			')' .
			'[^>]*>/is';
	}

	/**
	 * Get WooCommerce cart.
	 *
	 * @return \WC_Cart|null
	 */
	private function get_cart() {

		if (
			! function_exists(
				'WC'
			)
		) {
			return null;
		}

		if (
			null ===
				WC()->cart &&
			function_exists(
				'wc_load_cart'
			)
		) {
			wc_load_cart();
		}

		return WC()->cart;
	}
}
