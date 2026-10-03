<?php
/**
 * Special Offer renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\OrderBumps\Rendering;

use EilmoCheckout\OrderBumps\Services\OrderBumpCalculator;
use EilmoCheckout\OrderBumps\Services\OrderBumpResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Special Offers.
 */
final class OrderBumpRenderer {

	/**
	 * Resolver.
	 *
	 * @var OrderBumpResolver
	 */
	private $resolver;

	/**
	 * Calculator.
	 *
	 * @var OrderBumpCalculator
	 */
	private $calculator;

	/**
	 * Constructor.
	 *
	 * @param OrderBumpResolver|null   $resolver   Resolver.
	 * @param OrderBumpCalculator|null $calculator Calculator.
	 */
	public function __construct(
		?OrderBumpResolver $resolver = null,
		?OrderBumpCalculator $calculator = null
	) {

		$this->resolver =
			$resolver
				? $resolver
				: new OrderBumpResolver();

		$this->calculator =
			$calculator
				? $calculator
				: new OrderBumpCalculator();
	}

	/**
	 * Render Special Offer section.
	 *
	 * Supported context:
	 *
	 * array(
	 *     'instance_id'    => 'checkout-1',
	 *     'special_offer_ids' => array(),
	 *     'selected_special_offer_ids' => array(),
	 *     'title'          => '',
	 * )
	 *
	 * Empty special_offer_ids means all globally enabled
	 * Special Offers are available.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

		$settings =
			$this->resolver
				->get_settings();

		if (
			'yes' !==
				(
					$settings[
						'enabled'
					] ??
						'no'
				)
		) {
			return '';
		}

		$allowed_context_ids = $context['special_offer_ids'] ?? $context['order_bump_ids'] ?? array();
		$allowed_ids = is_array( $allowed_context_ids )
			? $this->normalize_offer_ids( $allowed_context_ids )
			: array();

		$offers =
			$this->resolver
				->get_available_offers(
					$allowed_ids
				);

		$selected_context_ids = $context['selected_special_offer_ids'] ?? $context['selected_order_bump_ids'] ?? array();
		$selected_ids = is_array( $selected_context_ids )
			? $this->normalize_offer_ids( $selected_context_ids )
			: array();

		if (
			empty(
				$offers
			)
		) {
			return '';
		}

		$instance_id =
			sanitize_html_class(
				(string) (
					$context[
						'instance_id'
					] ??
						'eilmo-checkout'
				)
			);

		if (
			'' ===
				$instance_id
		) {
			$instance_id =
				'eilmo-checkout';
		}

		$selection_mode =
			'single' ===
				(
					$settings[
						'selection_mode'
					] ??
						'multiple'
				)
					? 'single'
					: 'multiple';

		/*
		 * A rendering context may override only the
		 * frontend section title.
		 *
		 * Offer data, pricing and eligibility still
		 * come exclusively from the trusted resolver.
		 *
		 * Existing checkout instances do not pass a
		 * title, therefore their current global title
		 * behavior remains unchanged.
		 */
		$title =
			sanitize_text_field(
				(string) (
					$context[
						'title'
					] ??
						''
				)
			);

		if ( ! empty( $context['hide_title'] ) ) {
			$title = '';
		} elseif (
			'' ===
				$title
		) {
			$title =
				sanitize_text_field(
					(string) (
						$settings[
							'title'
						] ??
							__(
								'Special Discounts',
								'eilmo-checkout-flow'
							)
					)
				);
		}

		$style = isset( $settings['style'] ) && is_array( $settings['style'] )
			? $settings['style']
			: array();
		$style_attribute = $this->build_style_attribute( $style );
		$card_style = sanitize_key( (string) ( $context['card_style'] ?? $settings['card_style'] ?? 'promo_banner' ) );
		if ( ! in_array( $card_style, array( 'promo_banner', 'reward_tiles', 'compact_strip' ), true ) ) {
			$card_style = 'promo_banner';
		}
		$columns_desktop = absint( $context['columns_desktop'] ?? 0 );
		if ( $columns_desktop <= 0 ) {
			$columns_desktop = absint( $settings['columns_desktop'] ?? 1 );
		}
		$columns_desktop = max( 1, min( 'promo_banner' === $card_style ? 2 : 3, $columns_desktop ) );
		$display = array();
		foreach ( array( 'show_icon', 'show_label', 'show_title', 'show_subtitle', 'show_timer', 'show_condition', 'show_reward', 'show_regular_price', 'show_added_action' ) as $display_key ) {
			$display[ $display_key ] = 'no' !== (string) ( $settings[ $display_key ] ?? 'yes' );
		}

		ob_start();
		?>

		<section
			class="eilmo-cf-special-offers eilmo-cf-special-discount-list"
			data-eilmo-special-offers
			data-eilmo-order-bumps
			data-expired-message="<?php echo esc_attr__( 'Offer ended. Your total has been updated.', 'eilmo-checkout-flow' ); ?>"
			data-selection-mode="<?php echo esc_attr( $selection_mode ); ?>"
			data-columns-desktop="<?php echo esc_attr( (string) $columns_desktop ); ?>"
			data-card-style="<?php echo esc_attr( $card_style ); ?>"
			style="<?php echo esc_attr( $style_attribute ); ?>"
		>

			<?php if ( '' !== $title ) : ?>

				<div class="eilmo-cf-special-discount-list__header">

					<h3 class="eilmo-cf-special-discount-list__title">
						<?php echo esc_html( $title ); ?>
					</h3>

				</div>

			<?php endif; ?>

			<div class="eilmo-cf-special-discount-list__cards">

				<?php
				foreach (
					$offers as
						$index =>
						$offer
				) {

					if (
						! is_array(
							$offer
						)
					) {
						continue;
					}

					$this->render_offer(
						$offer,
						$instance_id,
						(int) $index,
						in_array(
							sanitize_key( (string) ( $offer['id'] ?? '' ) ),
							$selected_ids,
							true
						),
						$card_style,
						$display
					);
				}
				?>

			</div>

		</section>

		<?php

		$output =
			ob_get_clean();

		return false === $output
			? ''
			: $output;
	}

	/**
	 * Render one Special Offer.
	 *
	 * Frontend values are previews only.
	 *
	 * Product, variation, quantity, pricing and
	 * discount compatibility are resolved again
	 * server-side before order creation.
	 *
	 * @param array<string, mixed> $offer       Offer.
	 * @param string               $instance_id Checkout instance.
	 * @param int                  $index       Index.
	 * @param bool                 $is_selected Whether offer is selected.
	 * @param string               $card_style  Card presentation preset.
	 * @param array<string,bool>   $display     PHP-rendered element visibility.
	 *
	 * @return void
	 */
	private function render_offer(
		array $offer,
		string $instance_id,
		int $index,
		bool $is_selected,
		string $card_style,
		array $display
	): void {
		$offer_type = sanitize_key( (string) ( $offer['offer_type'] ?? 'single_product' ) );
		$is_free_delivery = 'free_delivery' === $offer_type;
		$product_id = absint( $offer['product_id'] ?? 0 );
		$variation_id = absint( $offer['variation_id'] ?? 0 );
		$quantity = $is_free_delivery ? 0.0 : $this->normalize_quantity( $offer['quantity'] ?? 1 );
		$product = null;

		if ( ! $is_free_delivery ) {
			if ( $product_id <= 0 || $quantity <= 0 ) {
				return;
			}
			$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
			if ( ! $product ) {
				return;
			}
		}

		$bump_id = sanitize_key( (string) ( $offer['id'] ?? '' ) );
		if ( '' === $bump_id ) {
			return;
		}

		$regular_total = 0.0;
		$discount = 0.0;
		$bump_total = 0.0;
		$calculation = array();
		if ( $product ) {
			$unit_price = function_exists( 'wc_get_price_to_display' )
				? (float) wc_get_price_to_display( $product )
				: (float) $product->get_price();
			$regular_total = $this->normalize_amount( $unit_price * $quantity );
			if ( $regular_total <= 0 && 'free_gift' !== $offer_type ) {
				return;
			}
			$calculation = $this->calculator->calculate( $offer, $regular_total );
			if ( ! is_array( $calculation ) || empty( $calculation['valid'] ) ) {
				return;
			}
			$discount = min( $regular_total, $this->normalize_amount( $calculation['discount'] ?? 0 ) );
			$bump_total = min( $regular_total, $this->normalize_amount( $calculation['bump_total'] ?? max( 0, $regular_total - $discount ) ) );
		}

		$product_name = $product ? sanitize_text_field( (string) $product->get_name() ) : __( 'Free Delivery', 'eilmo-checkout-flow' );
		$title = sanitize_text_field( (string) ( $offer['title'] ?? $product_name ) );
		$title = '' !== $title ? $title : $product_name;
		$description = wp_kses_post( (string) ( $offer['description'] ?? '' ) );
		$badge = sanitize_text_field( (string) ( $offer['badge'] ?? '' ) );
		$label = sanitize_text_field( (string) ( $offer['label'] ?? 'LIMITED TIME OFFER' ) );
		$before_apply_text = sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) );
		$applied_text = sanitize_text_field( (string) ( $offer['applied_text'] ?? 'Added' ) );
		$end_timestamp = absint( $offer['end_timestamp'] ?? 0 );
		$timer_enabled = 'no' !== ( $offer['timer_enabled'] ?? 'yes' ) && $end_timestamp > 0;
		$input_id = sanitize_html_class( $instance_id . '-order-bump-' . $index );
		$pricing_type = sanitize_key( (string) ( $calculation['pricing_type'] ?? $offer['pricing_type'] ?? 'regular_price' ) );
		$pricing_value = $this->normalize_amount( $calculation['pricing_value'] ?? $offer['pricing_value'] ?? 0 );
		$apply_automatic_discount = $this->normalize_yes_no( $offer['apply_automatic_discount'] ?? 'no', 'no' );
		$apply_coupon = $this->normalize_yes_no( $offer['apply_coupon'] ?? 'no', 'no' );
		$apply_full_payment_discount = $this->normalize_yes_no( $offer['apply_full_payment_discount'] ?? 'yes', 'yes' );
		$condition_type = sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) );
		$apply_behavior = sanitize_key( (string) ( $offer['apply_behavior'] ?? 'customer_selectable' ) );
		$ineligible_action = sanitize_key( (string) ( $offer['ineligible_action'] ?? 'show_locked' ) );
		$locked_text = sanitize_text_field( (string) ( $offer['locked_text'] ?? '' ) );
		$initial_progress_text = 'always' === $condition_type
			? ''
			: ( '' !== $locked_text ? $locked_text : $this->format_condition_subtitle( $offer, $condition_type ) );
		$preset_class = array(
			'promo_banner'  => 'promo',
			'reward_tiles'  => 'reward',
			'compact_strip' => 'compact',
		)[ $card_style ] ?? 'promo';
		$is_compact = 'compact_strip' === $card_style;
		$custom_reward_title = sanitize_text_field( (string) ( $offer['reward_title'] ?? '' ) );
		$reward_text = $is_free_delivery ? __( 'Free Delivery', 'eilmo-checkout-flow' ) : '';
		if ( 'free_gift' === $offer_type && $product ) {
			$reward_text = '' !== $custom_reward_title ? $custom_reward_title : $product_name;
		} elseif ( ! $is_free_delivery && $product ) {
			$reward_text = wp_strip_all_tags( wc_price( $bump_total ) );
		}
		if ( $is_compact && '' === trim( $description ) ) {
			$description = esc_html( $this->format_condition_subtitle( $offer, $condition_type ) );
		}

		$image_source = sanitize_key( (string) ( $offer['image_source'] ?? 'product' ) );
		$image_media_id = absint( $offer['image_media_id'] ?? 0 );
		$image_position = sanitize_key( (string) ( $offer['image_position'] ?? 'center' ) );
		if ( ! in_array( $image_position, array( 'top', 'center', 'bottom' ), true ) ) {
			$image_position = 'center';
		}
		$image_html = '';
		if ( 'preset' === $image_source ) {
			$image_html = $this->get_preset_icon_svg( (string) ( $offer['icon_preset'] ?? 'auto' ), $offer_type );
		} elseif ( 'custom' === $image_source && $image_media_id > 0 ) {
			$image_html = (string) wp_get_attachment_image( $image_media_id, 'large', false, array( 'class' => 'eilmo-cf-special-discount-card__thumbnail', 'loading' => 'lazy' ) );
		} elseif ( 'product' === $image_source && $product ) {
			$image_html = (string) $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'eilmo-cf-special-discount-card__thumbnail', 'loading' => 'lazy' ) );
		}
		$reward_title = 'free_gift' === $offer_type && $product && method_exists( $product, 'get_name' )
			? sanitize_text_field( (string) $product->get_name() )
			: $title;
		?>
		<label
			class="eilmo-cf-special-offer eilmo-cf-special-discount-card eilmo-cf-special-discount-card--<?php echo esc_attr( $preset_class ); ?><?php echo esc_attr( $is_selected ? ' is-selected' : '' ); ?>"
			for="<?php echo esc_attr( $input_id ); ?>"
			data-eilmo-special-offer
			data-eilmo-order-bump
			data-bump-id="<?php echo esc_attr( $bump_id ); ?>"
			data-offer-type="<?php echo esc_attr( $offer_type ); ?>"
			data-reward-title="<?php echo esc_attr( $reward_title ); ?>"
			data-rule-scope="<?php echo esc_attr( sanitize_key( (string) ( $offer['rule_scope'] ?? 'whole_cart' ) ) ); ?>"
			data-scope-product-ids="<?php echo esc_attr( wp_json_encode( isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] ) ? array_values( array_map( 'absint', $offer['scope_product_ids'] ) ) : array() ) ); ?>"
			data-free-delivery-method-id="<?php echo esc_attr( sanitize_key( (string) ( $offer['free_delivery_method_id'] ?? '' ) ) ); ?>"
			data-free-delivery-hide-other-methods="<?php echo esc_attr( 'yes' === ( $offer['free_delivery_hide_other_methods'] ?? 'no' ) ? 'yes' : 'no' ); ?>"
			data-free-delivery="<?php echo esc_attr( $is_free_delivery ? 'yes' : 'no' ); ?>"
			data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
			data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>"
			data-quantity="<?php echo esc_attr( (string) $quantity ); ?>"
			data-pricing-type="<?php echo esc_attr( $pricing_type ); ?>"
			data-pricing-value="<?php echo esc_attr( (string) $pricing_value ); ?>"
			data-regular-total="<?php echo esc_attr( (string) $regular_total ); ?>"
			data-discount="<?php echo esc_attr( (string) $discount ); ?>"
			data-bump-total="<?php echo esc_attr( (string) $bump_total ); ?>"
			data-apply-automatic-discount="<?php echo esc_attr( $apply_automatic_discount ); ?>"
			data-apply-coupon="<?php echo esc_attr( $apply_coupon ); ?>"
			data-apply-full-payment-discount="<?php echo esc_attr( $apply_full_payment_discount ); ?>"
			data-before-apply-text="<?php echo esc_attr( $before_apply_text ); ?>"
			data-button-text="<?php echo esc_attr( $before_apply_text ); ?>"
			data-applied-text="<?php echo esc_attr( $applied_text ); ?>"
			data-has-artwork="<?php echo esc_attr( ! empty( $display['show_icon'] ) && '' !== $image_html ? 'yes' : 'no' ); ?>"
			data-card-style="<?php echo esc_attr( $card_style ); ?>"
			data-image-position="<?php echo esc_attr( $image_position ); ?>"
			data-condition-type="<?php echo esc_attr( $condition_type ); ?>"
			data-condition-minimum="<?php echo esc_attr( (string) (float) ( $offer['condition_minimum'] ?? 0 ) ); ?>"
			data-condition-maximum="<?php echo esc_attr( (string) (float) ( $offer['condition_maximum'] ?? 0 ) ); ?>"
			data-condition-product-id="<?php echo esc_attr( (string) absint( $offer['condition_product_id'] ?? 0 ) ); ?>"
			data-condition-product-quantity="<?php echo esc_attr( (string) max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ) ); ?>"
			data-condition-cart-quantity="<?php echo esc_attr( (string) max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ) ); ?>"
			data-apply-behavior="<?php echo esc_attr( $apply_behavior ); ?>"
			data-ineligible-action="<?php echo esc_attr( $ineligible_action ); ?>"
			data-locked-text="<?php echo esc_attr( $locked_text ); ?>"
			data-end-timestamp="<?php echo esc_attr( (string) $end_timestamp ); ?>"
			data-expired="no"
		>
			<input id="<?php echo esc_attr( $input_id ); ?>" type="checkbox" class="eilmo-cf-special-discount-card__input" value="<?php echo esc_attr( $bump_id ); ?>" data-eilmo-special-offer-input data-eilmo-order-bump-input <?php checked( $is_selected ); ?>>
			<div class="eilmo-cf-special-discount-card__content">
				<?php if ( ! empty( $display['show_label'] ) && ! $is_compact && ( '' !== $label || '' !== $badge ) ) : ?>
					<div class="eilmo-cf-special-discount-card__eyebrow">
						<?php if ( '' !== $label ) : ?><span class="eilmo-cf-special-discount-card__label"><?php echo esc_html( $label ); ?></span><?php endif; ?>
						<?php if ( '' !== $badge ) : ?><span class="eilmo-cf-special-discount-card__badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $display['show_title'] ) ) : ?><strong class="eilmo-cf-special-discount-card__title"><?php echo esc_html( $title ); ?></strong><?php endif; ?>
				<?php if ( ! empty( $display['show_subtitle'] ) && '' !== trim( $description ) ) : ?><div class="eilmo-cf-special-discount-card__description"><?php echo wp_kses_post( $description ); ?></div><?php endif; ?>
				<?php if ( ! empty( $display['show_timer'] ) && ! $is_compact && $timer_enabled ) : ?>
					<div class="eilmo-cf-special-discount-card__timer" data-eilmo-special-offer-timer data-end-timestamp="<?php echo esc_attr( (string) $end_timestamp ); ?>">
						<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-days>00</strong><small><?php esc_html_e( 'Days', 'eilmo-checkout-flow' ); ?></small></span>
						<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-hours>00</strong><small><?php esc_html_e( 'Hours', 'eilmo-checkout-flow' ); ?></small></span>
						<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-minutes>00</strong><small><?php esc_html_e( 'Mins', 'eilmo-checkout-flow' ); ?></small></span>
						<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-seconds>00</strong><small><?php esc_html_e( 'Secs', 'eilmo-checkout-flow' ); ?></small></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $display['show_condition'] ) && ! $is_compact ) : ?><div class="eilmo-cf-special-discount-card__state" data-eilmo-special-discount-progress<?php if ( $is_selected || '' === $initial_progress_text ) : ?> hidden<?php endif; ?>><?php echo esc_html( $initial_progress_text ); ?></div><?php endif; ?>
				<?php if ( ! empty( $display['show_reward'] ) || ! empty( $display['show_added_action'] ) ) : ?><div class="eilmo-cf-special-discount-card__footer">
					<?php if ( ! empty( $display['show_reward'] ) ) : ?>
					<div class="eilmo-cf-special-discount-card__price">
						<?php if ( ! empty( $display['show_regular_price'] ) && ! $is_compact && ! in_array( $offer_type, array( 'free_delivery', 'free_gift' ), true ) && $discount > 0 ) : ?><del class="eilmo-cf-special-discount-card__regular-price"><?php echo wp_kses_post( wc_price( $regular_total ) ); ?></del><?php endif; ?>
						<strong class="eilmo-cf-special-discount-card__offer-price"><?php echo esc_html( $reward_text ); ?></strong>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $display['show_added_action'] ) ) : ?>
						<span class="eilmo-cf-special-discount-card__action" data-eilmo-special-added-action>
							<span data-eilmo-special-add-text<?php if ( $is_selected ) : ?> hidden<?php endif; ?>><?php echo esc_html( $before_apply_text ); ?></span>
							<span data-eilmo-special-applied-text<?php if ( ! $is_selected ) : ?> hidden<?php endif; ?>><?php echo esc_html( $applied_text ); ?><span class="eilmo-cf-special-discount-card__inline-check" aria-hidden="true">✓</span></span>
						</span>
					<?php endif; ?>
				</div><?php endif; ?>
			</div>
			<?php if ( ! empty( $display['show_icon'] ) && '' !== $image_html ) : ?>
				<div class="eilmo-cf-special-discount-card__artwork" aria-hidden="true">
					<div class="eilmo-cf-special-discount-card__image">
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded SVG or WordPress attachment HTML. ?>
						<?php echo $image_html; ?>
					</div>
				</div>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Return an accessible, code-owned SVG icon for compact rewards.
	 *
	 * @param string $preset     Saved icon preset.
	 * @param string $offer_type Reward type.
	 *
	 * @return string
	 */
	private function get_preset_icon_svg( string $preset, string $offer_type ): string {
		$preset = sanitize_key( $preset );
		if ( 'auto' === $preset || ! in_array( $preset, array( 'discount', 'delivery', 'gift', 'product' ), true ) ) {
			$preset = 'free_delivery' === $offer_type
				? 'delivery'
				: ( 'free_gift' === $offer_type ? 'gift' : 'product' );
		}

		$paths = array(
			'discount' => '<path d="M12 3l7 4v5c0 4.6-2.9 7.5-7 9-4.1-1.5-7-4.4-7-9V7l7-4z"/><path d="M9 15l6-6M9.5 9.5h.01M14.5 14.5h.01"/>',
			'delivery' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
			'gift' => '<path d="M4 10h16v11H4zM3 7h18v4H3zM12 7v14"/><path d="M12 7H8.5a2.5 2.5 0 1 1 2.2-3.7L12 7zm0 0h3.5a2.5 2.5 0 1 0-2.2-3.7L12 7z"/>',
			'product' => '<path d="M5 8h14l-1 13H6L5 8z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>',
		);

		return '<svg class="eilmo-cf-special-discount-card__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $preset ] . '</svg>';
	}

	/**
	 * Build a short condition subtitle for Compact Grid cards.
	 *
	 * @param array<string, mixed> $offer          Offer data.
	 * @param string               $condition_type Condition key.
	 *
	 * @return string
	 */
	private function format_condition_subtitle( array $offer, string $condition_type ): string {
		if ( 'always' === $condition_type ) {
			return __( 'Available with every eligible order.', 'eilmo-checkout-flow' );
		}

		if ( 'specific_product' === $condition_type ) {
			return __( 'Buy the required product to unlock.', 'eilmo-checkout-flow' );
		}

		if ( 'cart_quantity' === $condition_type ) {
			return sprintf(
				/* translators: %d: minimum cart quantity. */
				__( 'Add %d or more products.', 'eilmo-checkout-flow' ),
				max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) )
			);
		}

		$minimum = max( 0, (float) ( $offer['condition_minimum'] ?? 0 ) );
		$price = function_exists( 'wc_price' )
			? wp_strip_all_tags( wc_price( $minimum ) )
			: (string) $minimum;

		return sprintf(
			/* translators: %s: minimum order amount. */
			__( 'On orders over %s.', 'eilmo-checkout-flow' ),
			$price
		);
	}

	/**
	 * Build escaped CSS custom properties from saved style tokens.
	 *
	 * @param array<string, mixed> $style Style settings.
	 *
	 * @return string
	 */
	private function build_style_attribute(
		array $style
	): string {
		$colors = array(
			'gradient_start' => '#f5f3ff',
			'gradient_end' => '#ede9fe',
			'card_border' => '#c4b5fd',
			'accent_start' => '#7c3aed',
			'accent_end' => '#8b5cf6',
			'selected_gradient_start' => '#f5f3ff',
			'selected_gradient_end' => '#ede9fe',
			'selected_border_start' => '#7c3aed',
			'selected_border_end' => '#8b5cf6',
			'label_color' => '#7c3aed',
			'selected_label_color' => '#7c3aed',
			'title' => '#111827',
			'text' => '#64748b',
			'selected_title' => '#111827',
			'selected_text' => '#64748b',
			'badge_background' => '#7c3aed',
			'badge_end' => '#8b5cf6',
			'badge_text' => '#ffffff',
			'selected_badge_background' => '#7c3aed',
			'selected_badge_end' => '#8b5cf6',
			'selected_badge_text' => '#ffffff',
			'timer_background' => '#f5f3ff',
			'timer_text' => '#111827',
			'timer_label' => '#6b7280',
			'timer_border' => '#ddd6fe',
			'selected_timer_background' => '#ede9fe',
			'selected_timer_text' => '#111827',
			'selected_timer_label' => '#6b7280',
			'selected_timer_border' => '#c4b5fd',
			'regular_price' => '#64748b',
			'offer_price' => '#7c3aed',
			'selected_regular_price' => '#64748b',
			'selected_offer_price' => '#7c3aed',
			'button_background' => '#7c3aed',
			'button_background_end' => '#8b5cf6',
			'button_text' => '#ffffff',
			'added_background' => '#7c3aed',
			'added_background_end' => '#8b5cf6',
			'added_text' => '#ffffff',
			'progress_background' => '#f5f3ff',
			'progress_background_end' => '#ede9fe',
			'progress_text' => '#7c3aed',
			'icon_color' => '#7c3aed',
			'image_background' => '#f5f3ff',
			'image_background_end' => '#ede9fe',
			'selected_icon_color' => '#7c3aed',
			'selected_icon_background' => '#f5f3ff',
			'selected_icon_background_end' => '#ede9fe',
			'decoration_primary' => '#7c3aed',
			'decoration_secondary' => '#8b5cf6',
		);
		$tokens = array();
		foreach ( $colors as $key => $fallback ) {
			$value = sanitize_hex_color( (string) ( $style[ $key ] ?? $fallback ) );
			$tokens[] = '--eilmo-so-' . str_replace( '_', '-', $key ) . ':' . ( $value ? $value : $fallback );
		}
		$tokens[] = '--eilmo-so-card-radius:' . max( 0, min( 40, absint( $style['card_radius'] ?? 22 ) ) ) . 'px';
		$tokens[] = '--eilmo-so-card-padding:' . max( 12, min( 48, absint( $style['card_padding'] ?? 24 ) ) ) . 'px';
		$tokens[] = '--eilmo-so-image-size:' . max( 72, min( 220, absint( $style['image_size'] ?? 150 ) ) ) . 'px';
		$tokens[] = '--eilmo-so-icon-size:' . max( 12, min( 120, absint( $style['icon_size'] ?? 30 ) ) ) . 'px';
		$tokens[] = '--eilmo-so-button-radius:' . max( 0, min( 40, absint( $style['button_radius'] ?? 12 ) ) ) . 'px';
		$tokens = array_merge( $tokens, $this->build_surface_tokens( $style ) );

		return implode( ';', $tokens );
	}

	/**
	 * Build sanitized transparent, solid or gradient surface tokens.
	 *
	 * @param array<string,mixed> $style Style settings.
	 *
	 * @return array<int,string>
	 */
	private function build_surface_tokens( array $style ): array {
		$definitions = array(
			'card' => array( 'background_type', 'gradient_start', 'gradient_end', 'gradient_angle', '#f5f3ff', '#ede9fe' ),
			'selected-card' => array( 'selected_background_type', 'selected_gradient_start', 'selected_gradient_end', 'selected_gradient_angle', '#f5f3ff', '#ede9fe' ),
			'selected-border' => array( 'selected_border_type', 'selected_border_start', 'selected_border_end', 'selected_border_angle', '#7c3aed', '#8b5cf6' ),
			'badge' => array( 'badge_background_type', 'badge_background', 'badge_end', 'badge_gradient_angle', '#7c3aed', '#8b5cf6' ),
			'selected-badge' => array( 'selected_badge_background_type', 'selected_badge_background', 'selected_badge_end', 'selected_badge_gradient_angle', '#7c3aed', '#8b5cf6' ),
			'button' => array( 'button_background_type', 'button_background', 'button_background_end', 'button_gradient_angle', '#7c3aed', '#8b5cf6' ),
			'added' => array( 'added_background_type', 'added_background', 'added_background_end', 'added_gradient_angle', '#7c3aed', '#8b5cf6' ),
			'progress' => array( 'progress_background_type', 'progress_background', 'progress_background_end', 'progress_gradient_angle', '#f5f3ff', '#ede9fe' ),
			'promo-icon' => array( 'promo_icon_background_type', 'image_background', 'image_background_end', 'icon_gradient_angle', '#f5f3ff', '#ede9fe' ),
			'icon' => array( 'icon_background_type', 'image_background', 'image_background_end', 'icon_gradient_angle', '#f5f3ff', '#ede9fe' ),
			'selected-icon' => array( 'selected_icon_background_type', 'selected_icon_background', 'selected_icon_background_end', 'selected_icon_gradient_angle', '#f5f3ff', '#ede9fe' ),
		);
		$tokens = array();
		foreach ( $definitions as $name => $definition ) {
			$type = sanitize_key( (string) ( $style[ $definition[0] ] ?? 'gradient' ) );
			$type = in_array( $type, array( 'transparent', 'solid', 'gradient' ), true ) ? $type : 'gradient';
			$start = sanitize_hex_color( (string) ( $style[ $definition[1] ] ?? $definition[4] ) );
			$end = sanitize_hex_color( (string) ( $style[ $definition[2] ] ?? $definition[5] ) );
			$start = $start ? $start : $definition[4];
			$end = $end ? $end : $definition[5];
			$angle = max( 0, min( 360, absint( $style[ $definition[3] ] ?? 135 ) ) );
			$value = 'transparent' === $type
				? 'transparent'
				: ( 'solid' === $type ? $start : 'linear-gradient(' . $angle . 'deg,' . $start . ',' . $end . ')' );
			$tokens[] = '--eilmo-so-' . $name . '-surface:' . $value;
		}

		return $tokens;
	}

	/**
	 * Normalize Special Offer IDs.
	 *
	 * @param mixed $ids IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_offer_ids(
		$ids
	): array {

		if (
			! is_array(
				$ids
			)
		) {
			return array();
		}

		$normalized =
			array();

		foreach ( $ids as $id ) {

			$id =
				sanitize_key(
					(string) $id
				);

			if (
				'' ===
					$id ||
				in_array(
					$id,
					$normalized,
					true
				)
			) {
				continue;
			}

			$normalized[] =
				$id;
		}

		return $normalized;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param mixed $quantity Quantity.
	 *
	 * @return float
	 */
	private function normalize_quantity(
		$quantity
	): float {

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$quantity =
				wc_stock_amount(
					$quantity
				);
		}

		if (
			! is_numeric(
				$quantity
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $quantity
		);
	}

	/**
	 * Format quantity for frontend.
	 *
	 * @param float $quantity Quantity.
	 *
	 * @return string
	 */
	private function format_quantity(
		float $quantity
	): string {

		if (
			floor(
				$quantity
			) ===
				$quantity
		) {
			return (string) (int) $quantity;
		}

		return rtrim(
			rtrim(
				number_format(
					$quantity,
					4,
					'.',
					''
				),
				'0'
			),
			'.'
		);
	}

	/**
	 * Normalize Yes / No setting.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function normalize_yes_no(
		$value,
		string $fallback = 'no'
	): string {

		$fallback =
			'yes' ===
				$fallback
					? 'yes'
					: 'no';

		if (
			'yes' ===
				$value
		) {
			return 'yes';
		}

		if (
			'no' ===
				$value
		) {
			return 'no';
		}

		return $fallback;
	}

	/**
	 * Normalize monetary amount.
	 *
	 * @param mixed $amount Amount.
	 *
	 * @return float
	 */
	private function normalize_amount(
		$amount
	): float {

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$amount =
				wc_format_decimal(
					$amount,
					false
				);
		}

		if (
			! is_numeric(
				$amount
			)
		) {
			return 0.0;
		}

		$amount =
			max(
				0,
				(float) $amount
			);

		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return round(
			$amount,
			$decimals
		);
	}
}
