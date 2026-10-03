<?php
/**
 * Automatic discount notice renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Discounts\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Discounts\Services\SpecialDiscountEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the automatic discount notice on checkout.
 */
final class DiscountNoticeRenderer {

	/**
	 * Render automatic discount notice.
	 *
	 * Special Discount cards remain visible when the
	 * Full Payment module is enabled. The calculation
	 * services decide which reward wins or stacks.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render( array $context = array() ): string {
		$settings = $this->get_discount_settings();


		$notification = isset( $settings['automatic_notification'] ) &&
			is_array( $settings['automatic_notification'] )
				? $settings['automatic_notification']
				: array();

		$rules = $this->get_frontend_rules( $settings );
		$allowed_ids = isset( $context['special_offer_ids'] ) && is_array( $context['special_offer_ids'] )
			? array_values( array_filter( array_map( 'sanitize_key', $context['special_offer_ids'] ) ) )
			: array();
		$assignment_scope = sanitize_key( (string) ( $context['special_offer_scope'] ?? ( ! empty( $allowed_ids ) ? 'selected' : 'all' ) ) );
		if ( ! in_array( $assignment_scope, array( 'all', 'selected', 'none' ), true ) ) {
			$assignment_scope = 'none';
		}
		$rules = array_values(
			array_filter(
				$rules,
				static function ( array $rule ) use ( $allowed_ids, $assignment_scope ): bool {
					if ( ! empty( $rule['compatibility_rule'] ) ) {
						return true;
					}
					if ( 'none' === $assignment_scope ) {
						return false;
					}
					if ( 'all' === $assignment_scope ) {
						return true;
					}
					return in_array( sanitize_key( (string) ( $rule['id'] ?? '' ) ), $allowed_ids, true );
				}
			)
		);

		if ( empty( $rules ) ) {
			return '';
		}

		$product_total = $this->normalize_amount(
			$context['product_total'] ?? 0
		);

		$data = apply_filters(
			'eilmo_cf/discounts/notice_render_data',
			array(
				'instance_id' => $this->get_instance_id(
					$context
				),

				'title' => ! empty( $context['hide_title'] ) ? '' : (string) (
					$notification['title'] ??
						__(
							'Spend More, Save More',
							'eilmo-checkout-flow'
						)
				),

				'show_tiers' => true,

				// Every card owns its eligibility/applied status. A second global
				// notice duplicated the same message underneath the grid.
				'show_status' => false,

				'texts' => array(
					'empty_status' =>
						(string) (
							$notification['empty_status'] ??
								__(
									'Select products to unlock your discount.',
									'eilmo-checkout-flow'
								)
						),

					'active_status' =>
						(string) (
							$notification['active_status'] ??
								__(
									'You are getting {discount} OFF.',
									'eilmo-checkout-flow'
								)
						),

					'upcoming_status' =>
						(string) (
							$notification['upcoming_status'] ??
								__(
									'Add {remaining_amount} more to unlock {discount} OFF.',
									'eilmo-checkout-flow'
								)
						),
				),

				'rules' =>
					$rules,

				'product_total' =>
					$product_total,

				'card_style' =>
					(string) ( $context['card_style'] ?? '' ),

				'columns_desktop' =>
					absint( $context['columns_desktop'] ?? 0 ),
			),
			$context
		);

		if ( ! is_array( $data ) ) {
			return '';
		}

		ob_start();

		do_action(
			'eilmo_cf/discounts/notice_before',
			$data
		);

		$this->render_notice(
			$data
		);

		do_action(
			'eilmo_cf/discounts/notice_after',
			$data
		);

		$output = ob_get_clean();

		return false === $output
			? ''
			: (string) $output;
	}

	/**
	 * Render notice markup.
	 *
	 * @param array<string, mixed> $data Renderer data.
	 *
	 * @return void
	 */
	private function render_notice(
		array $data
	): void {
		$instance_id =
			sanitize_html_class(
				(string) (
					$data['instance_id'] ??
						''
				)
			);

		$title =
			sanitize_text_field(
				(string) (
					$data['title'] ??
						''
				)
			);

		$show_tiers =
			! empty(
				$data['show_tiers']
			);

		$show_status =
			! empty(
				$data['show_status']
			);

		$rules =
			isset( $data['rules'] ) &&
			is_array( $data['rules'] )
				? $data['rules']
				: array();

		$texts =
			isset( $data['texts'] ) &&
			is_array( $data['texts'] )
				? $data['texts']
				: array();

		$product_total =
			$this->normalize_amount(
				$data['product_total'] ??
					0
			);

		$card_settings = $this->get_special_discount_card_settings();
		$card_style = isset( $card_settings['style'] ) && is_array( $card_settings['style'] )
			? $card_settings['style']
			: array();
		$display = array();
		foreach ( array( 'show_icon', 'show_label', 'show_title', 'show_subtitle', 'show_timer', 'show_condition', 'show_reward', 'show_added_action' ) as $display_key ) {
			$display[ $display_key ] = 'no' !== (string) ( $card_settings[ $display_key ] ?? 'yes' );
		}
		$card_style_key = sanitize_key( (string) ( $data['card_style'] ?? $card_settings['card_style'] ?? 'promo_banner' ) );
		if ( ! in_array( $card_style_key, array( 'promo_banner', 'reward_tiles', 'compact_strip' ), true ) ) {
			$card_style_key = 'promo_banner';
		}
		$preset_class = array(
			'promo_banner'  => 'promo',
			'reward_tiles'  => 'reward',
			'compact_strip' => 'compact',
		)[ $card_style_key ] ?? 'promo';
		$columns_desktop = absint( $data['columns_desktop'] ?? 0 );
		if ( $columns_desktop <= 0 ) {
			$columns_desktop = absint( $card_settings['columns_desktop'] ?? 1 );
		}
		$columns_desktop = max( 1, min( 'promo_banner' === $card_style_key ? 2 : 3, $columns_desktop ) );

		$rules_json =
			$this->encode_json(
				$rules,
				'[]'
			);

		$texts_json =
			$this->encode_json(
				$texts,
				'{}'
			);

		$initial_status =
			'';

		if ( $show_status ) {
			$initial_status =
				$this->get_initial_status(
					$product_total,
					$rules,
					$texts
				);
		}

		?>
		<section
			id="<?php echo esc_attr( 'eilmo-cf-discount-notice-' . $instance_id ); ?>"
			class="eilmo-cf-discount-notice"
			data-eilmo-discount-notice
			data-rules="<?php echo esc_attr( $rules_json ); ?>"
			data-texts="<?php echo esc_attr( $texts_json ); ?>"
			data-product-total="<?php echo esc_attr( (string) $product_total ); ?>"
			data-columns-desktop="<?php echo esc_attr( (string) $columns_desktop ); ?>"
			data-card-style="<?php echo esc_attr( $card_style_key ); ?>"
			style="<?php echo esc_attr( $this->build_card_style_attribute( $card_style ) ); ?>"
		>

			<?php if ( '' !== $title ) : ?>

				<div class="eilmo-cf-discount-notice__title">
					<?php echo esc_html( $title ); ?>
				</div>

			<?php endif; ?>

			<?php if ( $show_tiers ) : ?>

				<div
					class="eilmo-cf-discount-notice__tiers"
					data-eilmo-discount-tiers
				>

					<?php foreach ( $rules as $rule ) : ?>

						<?php if ( ! is_array( $rule ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>

						<?php
						$rule_label = sanitize_text_field( (string) ( $rule['label'] ?? 'SPECIAL DISCOUNT' ) );
						$rule_badge = sanitize_text_field( (string) ( $rule['badge'] ?? '' ) );
						$rule_name = sanitize_text_field( (string) ( $rule['name'] ?? '' ) );
						$rule_description = wp_kses_post( (string) ( $rule['description'] ?? '' ) );
						$is_compact = 'compact_strip' === $card_style_key;
						$reward_text = $this->format_rule_discount( $rule );
						if ( $is_compact && '' === trim( $rule_description ) ) {
							$rule_description = esc_html( $this->format_rule_condition( $rule ) );
						}
						$end_timestamp = absint( $rule['end_timestamp'] ?? 0 );
						$initial_condition_type = sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) );
						$initial_minimum = $this->normalize_amount( $rule['minimum'] ?? 0 );
						$initial_maximum = $this->normalize_amount( $rule['maximum'] ?? 0 );
						$is_initial_active = $product_total > 0 && (
							'always' === $initial_condition_type ||
							(
								in_array( $initial_condition_type, array( '', 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) &&
								$product_total >= $initial_minimum &&
								( $initial_maximum <= 0 || $product_total <= $initial_maximum )
							)
						);
						$image_media_id = absint( $rule['image_media_id'] ?? 0 );
						$image_source = sanitize_key( (string) ( $rule['image_source'] ?? 'hidden' ) );
						$image_position = sanitize_key( (string) ( $rule['image_position'] ?? 'center' ) );
						if ( ! in_array( $image_position, array( 'top', 'center', 'bottom' ), true ) ) {
							$image_position = 'center';
						}
						$image_html = '';
						if ( 'preset' === $image_source ) {
							$image_html = $this->get_preset_icon_svg( (string) ( $rule['icon_preset'] ?? 'auto' ), (string) ( $rule['type'] ?? 'percentage' ) );
						} elseif ( 'custom' === $image_source && $image_media_id > 0 ) {
							$image_html = (string) wp_get_attachment_image( $image_media_id, 'large', false, array( 'class' => 'eilmo-cf-special-discount-card__thumbnail', 'loading' => 'lazy' ) );
						}
						?>
						<div
							class="eilmo-cf-discount-notice__tier eilmo-cf-special-offer eilmo-cf-special-discount-auto eilmo-cf-special-discount-card eilmo-cf-special-discount-card--<?php echo esc_attr( $preset_class ); ?><?php echo esc_attr( $is_initial_active ? ' is-selected' : '' ); ?>"
							data-eilmo-discount-tier
							data-rule-id="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>"
							data-condition-type="<?php echo esc_attr( (string) ( $rule['condition_type'] ?? 'order_amount' ) ); ?>"
							data-ineligible-action="<?php echo esc_attr( (string) ( $rule['ineligible_action'] ?? 'show_locked' ) ); ?>"
							data-locked-text="<?php echo esc_attr( (string) ( $rule['locked_text'] ?? '' ) ); ?>"
							data-card-style="<?php echo esc_attr( $card_style_key ); ?>"
							data-has-artwork="<?php echo esc_attr( ! empty( $display['show_icon'] ) && '' !== $image_html ? 'yes' : 'no' ); ?>"
							data-image-position="<?php echo esc_attr( $image_position ); ?>"
						>
							<div class="eilmo-cf-special-discount-card__content">
								<?php if ( ! empty( $display['show_label'] ) && ! $is_compact && ( '' !== $rule_label || '' !== $rule_badge ) ) : ?>
									<div class="eilmo-cf-special-discount-card__eyebrow">
										<?php if ( '' !== $rule_label ) : ?><span class="eilmo-cf-special-discount-card__label"><?php echo esc_html( $rule_label ); ?></span><?php endif; ?>
										<?php if ( '' !== $rule_badge ) : ?><span class="eilmo-cf-special-discount-card__badge"><?php echo esc_html( $rule_badge ); ?></span><?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $display['show_title'] ) ) : ?><strong class="eilmo-cf-special-discount-card__title"><?php echo esc_html( '' !== $rule_name ? $rule_name : $this->format_rule_discount( $rule ) ); ?></strong><?php endif; ?>
								<?php if ( ! empty( $display['show_subtitle'] ) && '' !== trim( $rule_description ) ) : ?><div class="eilmo-cf-special-discount-card__description"><?php echo wp_kses_post( $rule_description ); ?></div><?php endif; ?>
								<?php if ( ! empty( $display['show_timer'] ) && ! $is_compact && 'no' !== ( $rule['timer_enabled'] ?? 'yes' ) && $end_timestamp > 0 ) : ?>
									<div class="eilmo-cf-special-discount-card__timer" data-eilmo-special-offer-timer data-end-timestamp="<?php echo esc_attr( (string) $end_timestamp ); ?>">
										<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-days>00</strong><small><?php esc_html_e( 'Days', 'eilmo-checkout-flow' ); ?></small></span>
										<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-hours>00</strong><small><?php esc_html_e( 'Hours', 'eilmo-checkout-flow' ); ?></small></span>
										<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-minutes>00</strong><small><?php esc_html_e( 'Mins', 'eilmo-checkout-flow' ); ?></small></span>
										<span class="eilmo-cf-special-discount-card__timer-unit"><strong data-eilmo-timer-seconds>00</strong><small><?php esc_html_e( 'Secs', 'eilmo-checkout-flow' ); ?></small></span>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $display['show_condition'] ) && ! $is_compact ) : ?><div class="eilmo-cf-special-discount-card__state" data-eilmo-special-discount-auto-status<?php if ( $is_initial_active ) : ?> hidden<?php endif; ?>><?php echo esc_html( $this->format_rule_range( $rule ) ); ?></div><?php endif; ?>
								<?php if ( ! empty( $display['show_reward'] ) || ! empty( $display['show_added_action'] ) ) : ?><div class="eilmo-cf-special-discount-card__footer">
									<?php if ( ! empty( $display['show_reward'] ) ) : ?><strong class="eilmo-cf-special-discount-card__offer-price"><?php echo esc_html( $reward_text ); ?></strong><?php endif; ?>
									<?php if ( ! empty( $display['show_added_action'] ) ) : ?><span class="eilmo-cf-special-discount-card__action" data-eilmo-special-discount-auto-action<?php if ( ! $is_initial_active ) : ?> hidden<?php endif; ?>><span><?php echo esc_html( (string) ( $rule['applied_text'] ?? __( 'Applied', 'eilmo-checkout-flow' ) ) ); ?></span><span class="eilmo-cf-special-discount-card__inline-check" aria-hidden="true">✓</span></span><?php endif; ?>
								</div><?php endif; ?>
							</div>
							<?php if ( ! empty( $display['show_icon'] ) && '' !== $image_html ) : ?>
								<div class="eilmo-cf-special-discount-card__artwork" aria-hidden="true">
									<div class="eilmo-cf-special-discount-card__image">
										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded SVG or WordPress attachment HTML.
										echo $image_html;
										?>
									</div>
								</div>
							<?php endif; ?>

						</div>

					<?php endforeach; ?>

				</div>

			<?php endif; ?>

			<?php if ( $show_status ) : ?>

				<div
					class="eilmo-cf-discount-notice__status"
					data-eilmo-discount-status
					<?php if ( '' === $initial_status ) : ?>
						hidden
					<?php endif; ?>
				>
					<?php echo esc_html( $initial_status ); ?>
				</div>

			<?php endif; ?>

		</section>
		<?php
	}

	/**
	 * Prepare automatic discount rules for frontend.
	 *
	 * @param array<string, mixed> $settings Discount settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_frontend_rules(
		array $settings
	): array {
		/*
		 * The frontend preview is generated from the same canonical Special
		 * Discount rule source as server-side checkout/order calculations.
		 */
		$rules = class_exists( SpecialDiscountEngine::class )
			? ( new SpecialDiscountEngine() )->get_rules()
			: array();

		$prepared = array();
		$now_timestamp = time();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || 'no' === ( $rule['enabled'] ?? 'yes' ) ) {
				continue;
			}

			$start_timestamp = $this->parse_schedule_timestamp( $rule['start_at'] ?? '' );
			$end_timestamp   = $this->parse_schedule_timestamp( $rule['end_at'] ?? '' );
			if (
				( $start_timestamp > 0 && $now_timestamp < $start_timestamp ) ||
				( $end_timestamp > 0 && $now_timestamp >= $end_timestamp )
			) {
				continue;
			}

			$reward_type = sanitize_key( (string) ( $rule['offer_type'] ?? $rule['reward_type'] ?? '' ) );
			$legacy_type = sanitize_key( (string) ( $rule['type'] ?? '' ) );
			if ( '' === $reward_type ) {
				$reward_type = 'free_delivery' === $legacy_type
					? 'free_delivery'
					: ( 'fixed' === $legacy_type ? 'fixed_discount' : 'percentage_discount' );
			}

			if ( 'percentage_discount' === $reward_type ) {
				$type = 'percentage';
			} elseif ( 'fixed_discount' === $reward_type ) {
				$type = 'fixed';
			} elseif ( 'free_delivery' === $reward_type ) {
				$type = 'free_delivery';
			} else {
				/* Free Gift uses the existing hidden Special Offer adapter. */
				continue;
			}

			$value = 'free_delivery' === $type
				? 0.0
				: $this->normalize_amount( $rule['pricing_value'] ?? $rule['value'] ?? 0 );
			if ( 'percentage' === $type ) {
				$value = min( 100, $value );
			}

			$scope = sanitize_key( (string) ( $rule['rule_scope'] ?? 'whole_cart' ) );
			if ( ! in_array( $scope, array( 'current_product', 'selected_products', 'whole_cart' ), true ) ) {
				$scope = 'whole_cart';
			}

			$scope_product_ids = isset( $rule['scope_product_ids'] ) && is_array( $rule['scope_product_ids'] )
				? array_values( array_unique( array_filter( array_map( 'absint', $rule['scope_product_ids'] ) ) ) )
				: array();

			$prepared[] = array(
				'id'                         => sanitize_key( (string) ( $rule['id'] ?? '' ) ),
				'name'                       => sanitize_text_field( (string) ( $rule['title'] ?? $rule['name'] ?? '' ) ),
				'minimum'                    => $this->normalize_amount( $rule['condition_minimum'] ?? $rule['minimum'] ?? 0 ),
				'maximum'                    => $this->normalize_amount( $rule['condition_maximum'] ?? $rule['maximum'] ?? 0 ),
				'type'                       => $type,
				'value'                      => $value,
				'maximum_discount'           => $this->normalize_amount( $rule['maximum_discount'] ?? 0 ),
				'priority'                   => absint( $rule['sort_order'] ?? $rule['priority'] ?? 10 ),
				'stop_processing'            => 'yes' === ( $rule['stop_processing'] ?? 'no' ),
				'condition_type'             => sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) ),
				'condition_product_id'       => absint( $rule['condition_product_id'] ?? 0 ),
				'condition_product_quantity' => max( 1, absint( $rule['condition_product_quantity'] ?? $rule['condition_value'] ?? 1 ) ),
				'condition_cart_quantity'    => max( 1, absint( $rule['condition_cart_quantity'] ?? $rule['condition_value'] ?? 1 ) ),
				'rule_scope'                 => $scope,
				'scope_product_ids'          => $scope_product_ids,
				'free_delivery_method_id'    => sanitize_key( (string) ( $rule['free_delivery_method_id'] ?? $rule['delivery_method_id'] ?? '' ) ),
				'free_delivery_hide_other_methods' => 'yes' === ( $rule['free_delivery_hide_other_methods'] ?? $rule['hide_other_methods'] ?? 'no' ),
				'compatibility_rule'         => ! empty( $rule['_compatibility_delivery_rule'] ),
				'label'                      => sanitize_text_field( (string) ( $rule['label'] ?? 'SPECIAL DISCOUNT' ) ),
				'badge'                      => sanitize_text_field( (string) ( $rule['badge'] ?? '' ) ),
				'description'                => wp_kses_post( (string) ( $rule['description'] ?? '' ) ),
				'locked_text'                => sanitize_text_field( (string) ( $rule['locked_text'] ?? '' ) ),
				'ineligible_action'          => sanitize_key( (string) ( $rule['ineligible_action'] ?? 'show_locked' ) ),
				'start_at'                   => sanitize_text_field( (string) ( $rule['start_at'] ?? '' ) ),
				'end_at'                     => sanitize_text_field( (string) ( $rule['end_at'] ?? '' ) ),
				'end_timestamp'              => $end_timestamp,
				'timer_enabled'              => 'no' === ( $rule['timer_enabled'] ?? 'yes' ) ? 'no' : 'yes',
				'image_source'               => sanitize_key( (string) ( $rule['image_source'] ?? 'hidden' ) ),
				'image_media_id'             => absint( $rule['image_media_id'] ?? 0 ),
				'image_position'             => sanitize_key( (string) ( $rule['image_position'] ?? 'center' ) ),
				'icon_preset'                => sanitize_key( (string) ( $rule['icon_preset'] ?? 'auto' ) ),
				'applied_text'               => sanitize_text_field( (string) ( $rule['applied_text'] ?? 'Applied' ) ),
			);
		}

		usort(
			$prepared,
			static function ( array $first, array $second ): int {
				$priority = (int) ( $first['priority'] ?? 10 ) <=> (int) ( $second['priority'] ?? 10 );
				if ( 0 !== $priority ) {
					return $priority;
				}
				return (float) ( $first['minimum'] ?? 0 ) <=> (float) ( $second['minimum'] ?? 0 );
			}
		);

		return array_values( $prepared );
	}

	/**
	 * Return the saved or reward-matched compact SVG icon.
	 *
	 * @param string $preset Saved icon preset.
	 * @param string $type   Compiled automatic reward type.
	 *
	 * @return string
	 */
	private function get_preset_icon_svg( string $preset, string $type ): string {
		$preset = sanitize_key( $preset );
		if ( 'auto' === $preset || ! in_array( $preset, array( 'discount', 'delivery', 'gift', 'product' ), true ) ) {
			$preset = 'free_delivery' === sanitize_key( $type ) ? 'delivery' : 'discount';
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
	 * Parse an admin datetime-local value using the WordPress site timezone.
	 *
	 * @param mixed $value Date and time value.
	 *
	 * @return int
	 */
	private function parse_schedule_timestamp( $value ): int {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return 0;
		}

		try {
			$timezone = function_exists( 'wp_timezone' )
				? wp_timezone()
				: new \DateTimeZone( 'UTC' );
			$date = new \DateTimeImmutable( $value, $timezone );

			return max( 0, $date->getTimestamp() );
		} catch ( \Exception $exception ) {
			return 0;
		}
	}

	/**
	 * Get initial status text.
	 *
	 * JS will keep this status synchronized after
	 * product quantity changes.
	 *
	 * @param float                           $product_total Product total.
	 * @param array<int, array<string,mixed>> $rules         Rules.
	 * @param array<string, mixed>             $texts         Texts.
	 *
	 * @return string
	 */
	private function get_initial_status(
		float $product_total,
		array $rules,
		array $texts
	): string {
		if ( $product_total <= 0 ) {
			return sanitize_text_field(
				(string) (
					$texts['empty_status'] ??
						__(
							'Select products to unlock your discount.',
							'eilmo-checkout-flow'
						)
				)
			);
		}

		$matched_rule =
			$this->find_matching_rule(
				$product_total,
				$rules
			);

		if ( null !== $matched_rule ) {
			$discount_label =
				$this->format_rule_discount_value(
					$matched_rule,
					$product_total
				);

			return $this->replace_tokens(
				(string) (
					$texts['active_status'] ??
						'You are getting {discount} OFF.'
				),
				array(
					'discount' =>
						$discount_label,

					'current_total' =>
						$this->format_price_text(
							$product_total
						),

					'minimum_amount' =>
						$this->format_price_text(
							$this->normalize_amount(
								$matched_rule['minimum'] ??
									0
							)
						),

					'maximum_amount' =>
						$this->format_price_text(
							$this->normalize_amount(
								$matched_rule['maximum'] ??
									0
							)
						),
				)
			);
		}

		$upcoming_rule =
			$this->find_upcoming_rule(
				$product_total,
				$rules
			);

		if ( null === $upcoming_rule ) {
			return '';
		}

		$minimum =
			$this->normalize_amount(
				$upcoming_rule['minimum'] ??
					0
			);

		$remaining =
			$this->normalize_amount(
				max(
					0,
					$minimum -
						$product_total
				)
			);

		return $this->replace_tokens(
			(string) (
				$texts['upcoming_status'] ??
					'Add {remaining_amount} more to unlock {discount} OFF.'
			),
			array(
				'discount' =>
					$this->format_rule_discount_value(
						$upcoming_rule,
						$minimum
					),

				'remaining_amount' =>
					$this->format_price_text(
						$remaining
					),

				'current_total' =>
					$this->format_price_text(
						$product_total
					),

				'minimum_amount' =>
					$this->format_price_text(
						$minimum
					),

				'maximum_amount' =>
					$this->format_price_text(
						$this->normalize_amount(
							$upcoming_rule['maximum'] ??
								0
						)
					),
			)
		);
	}

	/**
	 * Find matching rule using half-open ranges.
	 *
	 * minimum <= amount < maximum
	 * maximum = 0 means no upper limit.
	 *
	 * @param float                           $amount Amount.
	 * @param array<int, array<string,mixed>> $rules  Rules.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_matching_rule(
		float $amount,
		array $rules
	): ?array {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			if ( ! in_array( sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) ), array( '', 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) ) {
				continue;
			}

			$minimum =
				$this->normalize_amount(
					$rule['minimum'] ??
						0
				);

			$maximum =
				$this->normalize_amount(
					$rule['maximum'] ??
						0
				);

			if ( $amount < $minimum ) {
				continue;
			}

			if (
				$maximum > 0 &&
				$amount > $maximum
			) {
				continue;
			}

			return $rule;
		}

		return null;
	}

	/**
	 * Find nearest upcoming discount rule.
	 *
	 * @param float                           $amount Amount.
	 * @param array<int, array<string,mixed>> $rules  Rules.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_upcoming_rule(
		float $amount,
		array $rules
	): ?array {
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			if ( ! in_array( sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) ), array( '', 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) ) {
				continue;
			}

			$minimum =
				$this->normalize_amount(
					$rule['minimum'] ??
						0
				);

			if ( $minimum > $amount ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Format range label.
	 *
	 * Example:
	 *
	 * ৳500 – ৳1,999.99
	 * ৳5,000+
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return string
	 */
	private function format_rule_range(
		array $rule
	): string {
		$minimum =
			$this->normalize_amount(
				$rule['minimum'] ??
					0
			);

		$maximum =
			$this->normalize_amount(
				$rule['maximum'] ??
					0
			);

		if ( $maximum <= 0 ) {
			return sprintf(
				/* translators: %s: minimum order amount. */
				__( '%s+', 'eilmo-checkout-flow' ),
				$this->format_price_text(
					$minimum
				)
			);
		}

		$display_maximum = max( $minimum, $maximum );

		return sprintf(
			/* translators: 1: minimum order amount, 2: maximum order amount. */
			__(
				'%1$s – %2$s',
				'eilmo-checkout-flow'
			),
			$this->format_price_text(
				$minimum
			),
			$this->format_price_text(
				$display_maximum
			)
		);
	}

	/**
	 * Build a short condition subtitle for Compact Grid cards.
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return string
	 */
	private function format_rule_condition( array $rule ): string {
		$condition_type = sanitize_key( (string) ( $rule['condition_type'] ?? 'order_amount' ) );

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
				max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) )
			);
		}

		return sprintf(
			/* translators: %s: minimum order amount. */
			__( 'On orders over %s.', 'eilmo-checkout-flow' ),
			$this->format_price_text( $this->normalize_amount( $rule['minimum'] ?? 0 ) )
		);
	}

	/**
	 * Format rule discount for tier list.
	 *
	 * @param array<string, mixed> $rule Rule.
	 *
	 * @return string
	 */
	private function format_rule_discount(
		array $rule
	): string {
		$type =
			sanitize_key(
				(string) (
					$rule['type'] ??
						'percentage'
				)
			);

		if ( 'free_delivery' === $type ) {
			return __(
				'Free Delivery',
				'eilmo-checkout-flow'
			);
		}

		$value =
			$this->normalize_amount(
				$rule['value'] ??
					0
			);

		if ( 'fixed' === $type ) {
			return sprintf(
				/* translators: %s: fixed discount amount. */
				__(
					'%s OFF',
					'eilmo-checkout-flow'
				),
				$this->format_price_text(
					$value
				)
			);
		}

		return sprintf(
			/* translators: %s: discount percentage. */
			__(
				'%s%% OFF',
				'eilmo-checkout-flow'
			),
			$this->format_percentage(
				$value
			)
		);
	}

	/**
	 * Format dynamic discount token value.
	 *
	 * Maximum discount cap is respected so the status
	 * can show the actual capped saving when required.
	 *
	 * @param array<string, mixed> $rule   Rule.
	 * @param float                $amount Applicable amount.
	 *
	 * @return string
	 */
	private function format_rule_discount_value(
		array $rule,
		float $amount
	): string {
		$type =
			sanitize_key(
				(string) (
					$rule['type'] ??
						'percentage'
				)
			);

		if ( 'free_delivery' === $type ) {
			return __(
				'Free Delivery',
				'eilmo-checkout-flow'
			);
		}

		$value =
			$this->normalize_amount(
				$rule['value'] ??
					0
			);

		if ( 'fixed' === $type ) {
			return $this->format_price_text(
				$value
			);
		}

		$value =
			min(
				100,
				$value
			);

		$raw_discount =
			$this->normalize_amount(
				$amount *
				(
					$value /
					100
				)
			);

		$maximum_discount =
			$this->normalize_amount(
				$rule['maximum_discount'] ??
					0
			);

		if (
			$maximum_discount > 0 &&
			$raw_discount >
				$maximum_discount
		) {
			return $this->format_price_text(
				$maximum_discount
			);
		}

		return sprintf(
			'%s%%',
			$this->format_percentage(
				$value
			)
		);
	}

	/**
	 * Replace text placeholders.
	 *
	 * @param string                $text   Text.
	 * @param array<string, string> $values Values.
	 *
	 * @return string
	 */
	private function replace_tokens(
		string $text,
		array $values
	): string {
		foreach ( $values as $token => $value ) {
			$text =
				str_replace(
					'{' . $token . '}',
					$value,
					$text
				);
		}

		return sanitize_text_field(
			$text
		);
	}

	/**
	 * Load discount settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_discount_settings(): array {
		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset( $defaults['discounts'] ) &&
			is_array( $defaults['discounts'] )
				? $defaults['discounts']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		if ( ! is_array( $stored ) ) {
			$stored =
				array();
		}

		$saved_settings =
			isset( $stored['discounts'] ) &&
			is_array( $stored['discounts'] )
				? $stored['discounts']
				: array();

		$settings =
			array_replace_recursive(
				$default_settings,
				$saved_settings
			);

		$settings['automatic_rules'] =
			isset( $saved_settings['automatic_rules'] ) &&
			is_array( $saved_settings['automatic_rules'] )
				? $saved_settings['automatic_rules']
				: (
					$default_settings['automatic_rules'] ??
						array()
				);

		$settings =
			apply_filters(
				'eilmo_cf/discounts/render_settings',
				$settings
			);

		return is_array( $settings )
			? $settings
			: $default_settings;
	}

	/**
	 * Load the unified Special Discount presentation settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_special_discount_card_settings(): array {
		$defaults = CheckoutSettings::get_defaults();
		$stored = get_option( CheckoutSettings::OPTION_NAME, array() );
		$default_settings = isset( $defaults['order_bumps'] ) && is_array( $defaults['order_bumps'] )
			? $defaults['order_bumps']
			: array();
		$saved_settings = is_array( $stored ) && isset( $stored['order_bumps'] ) && is_array( $stored['order_bumps'] )
			? $stored['order_bumps']
			: array();

		return array_replace_recursive( $default_settings, $saved_settings );
	}

	/**
	 * Build the same CSS tokens used by selectable product rewards.
	 *
	 * @param array<string, mixed> $style Style settings.
	 *
	 * @return string
	 */
	private function build_card_style_attribute( array $style ): string {
		$colors = array(
			'gradient_start' => '#f5f3ff', 'gradient_end' => '#ede9fe',
			'card_border' => '#c4b5fd', 'accent_start' => '#7c3aed',
			'accent_end' => '#8b5cf6', 'label_color' => '#7c3aed', 'selected_label_color' => '#7c3aed',
			'selected_gradient_start' => '#f5f3ff', 'selected_gradient_end' => '#ede9fe',
			'selected_border_start' => '#7c3aed', 'selected_border_end' => '#8b5cf6',
			'title' => '#111827', 'text' => '#64748b', 'selected_title' => '#111827', 'selected_text' => '#64748b',
			'badge_background' => '#7c3aed', 'badge_end' => '#8b5cf6',
			'badge_text' => '#ffffff', 'selected_badge_background' => '#7c3aed', 'selected_badge_end' => '#8b5cf6', 'selected_badge_text' => '#ffffff',
			'timer_background' => '#f5f3ff',
			'timer_text' => '#111827', 'timer_label' => '#6b7280',
			'timer_border' => '#ddd6fe', 'selected_timer_background' => '#ede9fe', 'selected_timer_text' => '#111827',
			'selected_timer_label' => '#6b7280', 'selected_timer_border' => '#c4b5fd',
			'regular_price' => '#64748b', 'offer_price' => '#7c3aed', 'selected_regular_price' => '#64748b', 'selected_offer_price' => '#7c3aed',
			'button_background' => '#7c3aed',
			'button_background_end' => '#8b5cf6', 'button_text' => '#ffffff',
			'added_background' => '#7c3aed', 'added_background_end' => '#8b5cf6', 'added_text' => '#ffffff',
			'progress_background' => '#f5f3ff', 'progress_background_end' => '#ede9fe', 'progress_text' => '#7c3aed',
			'icon_color' => '#7c3aed', 'image_background' => '#f5f3ff', 'image_background_end' => '#ede9fe',
			'selected_icon_color' => '#7c3aed', 'selected_icon_background' => '#f5f3ff', 'selected_icon_background_end' => '#ede9fe',
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
	 * Get unique renderer instance ID.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	private function get_instance_id(
		array $context
	): string {
		if (
			! empty(
				$context['instance_id']
			)
		) {
			return sanitize_html_class(
				(string) $context['instance_id']
			);
		}

		if (
			function_exists(
				'wp_unique_id'
			)
		) {
			return sanitize_html_class(
				wp_unique_id(
					'eilmo-cf-discount-'
				)
			);
		}

		return sanitize_html_class(
			uniqid(
				'eilmo-cf-discount-',
				false
			)
		);
	}

	/**
	 * Get smallest currency step.
	 *
	 * @return float
	 */
	private function get_currency_step(): float {
		$decimals =
			function_exists(
				'wc_get_price_decimals'
			)
				? wc_get_price_decimals()
				: 2;

		return 1 /
			pow(
				10,
				max(
					0,
					$decimals
				)
			);
	}

	/**
	 * Format percentage.
	 *
	 * @param float $value Percentage.
	 *
	 * @return string
	 */
	private function format_percentage(
		float $value
	): string {
		$formatted =
			number_format(
				$value,
				2,
				'.',
				''
			);

		return rtrim(
			rtrim(
				$formatted,
				'0'
			),
			'.'
		);
	}

	/**
	 * Format WooCommerce price.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function format_price(
		float $amount
	): string {
		if (
			function_exists(
				'wc_price'
			)
		) {
			return wc_price(
				$amount
			);
		}

		return esc_html(
			number_format_i18n(
				$amount,
				2
			)
		);
	}

	/**
	 * Format price as plain text.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private function format_price_text(
		float $amount
	): string {
		return wp_strip_all_tags(
			$this->format_price(
				$amount
			)
		);
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

		if ( ! is_numeric( $amount ) ) {
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

	/**
	 * Encode JSON safely.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function encode_json(
		$value,
		string $fallback
	): string {
		$json =
			wp_json_encode(
				$value
			);

		return false === $json
			? $fallback
			: $json;
	}
}
