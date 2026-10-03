<?php
/**
 * Order details renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Orders\Rendering;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Payment\Services\PaymentProof;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Displays Eilmo order, payment and item information
 * on WooCommerce order screens.
 */
final class OrderDetailsRenderer implements RegistrableInterface {

	/**
	 * Eilmo order source.
	 */
	private const META_CREATED_VIA =
		'_eilmo_cf_created_via';

	/**
	 * Payment type.
	 */
	private const META_PAYMENT_TYPE =
		'_eilmo_cf_payment_type';

	/**
	 * Advance amount.
	 */
	private const META_ADVANCE_AMOUNT =
		'_eilmo_cf_advance_amount';

	/**
	 * Advance rule ID.
	 */
	private const META_ADVANCE_RULE_ID =
		'_eilmo_cf_advance_rule_id';

	/**
	 * Advance rule type.
	 */
	private const META_ADVANCE_RULE_TYPE =
		'_eilmo_cf_advance_rule_type';

	/**
	 * Pay-now amount.
	 */
	private const META_PAY_NOW =
		'_eilmo_cf_pay_now';

	/**
	 * Remaining due.
	 */
	private const META_REMAINING_DUE =
		'_eilmo_cf_remaining_due';

	/**
	 * Payment source.
	 */
	private const META_PAYMENT_SOURCE =
		'_eilmo_cf_payment_source';

	/**
	 * Payment method key.
	 */
	private const META_PAYMENT_METHOD_KEY =
		'_eilmo_cf_payment_method_key';

	/**
	 * Payment transaction ID.
	 */
	private const META_TRANSACTION_ID =
		'_eilmo_cf_payment_transaction_id';

	/**
	 * Payment status.
	 */
	private const META_PAYMENT_STATUS =
		'_eilmo_cf_payment_status';

	/**
	 * Applied coupon code.
	 */
	private const META_COUPON_CODE =
		'_eilmo_cf_coupon_code';

	/**
	 * Applied coupon source.
	 */
	private const META_COUPON_SOURCE =
		'_eilmo_cf_coupon_source';

	/**
	 * Applied coupon type.
	 */
	private const META_COUPON_TYPE =
		'_eilmo_cf_coupon_type';

	/**
	 * Coupon discount amount.
	 */
	private const META_COUPON_DISCOUNT =
		'_eilmo_cf_coupon_discount';

	/**
	 * Coupon free-delivery benefit.
	 */
	private const META_COUPON_FREE_DELIVERY =
		'_eilmo_cf_coupon_free_delivery';

	/**
	 * Fulfilled Special Discount snapshots.
	 */
	private const META_APPLIED_SPECIAL_DISCOUNTS =
		'_eilmo_cf_applied_special_discounts';

	/*
	 * --------------------------------------------------
	 * Combo line-item metadata.
	 * --------------------------------------------------
	 */

	/**
	 * Combo component marker.
	 */
	private const ITEM_META_COMBO_COMPONENT =
		'_eilmo_cf_combo_component';

	/**
	 * Combo ID.
	 */
	private const ITEM_META_COMBO_ID =
		'_eilmo_cf_combo_id';

	/**
	 * Combo title.
	 */
	private const ITEM_META_COMBO_TITLE =
		'_eilmo_cf_combo_title';

	/**
	 * Combo discount.
	 */
	private const ITEM_META_COMBO_DISCOUNT =
		'_eilmo_cf_combo_discount_amount';

	/**
	 * Combo Automatic Discount compatibility.
	 */
	private const ITEM_META_COMBO_APPLY_AUTOMATIC =
		'_eilmo_cf_combo_apply_automatic_discount';

	/**
	 * Combo Coupon compatibility.
	 */
	private const ITEM_META_COMBO_APPLY_COUPON =
		'_eilmo_cf_combo_apply_coupon';

	/**
	 * Combo Full Payment compatibility.
	 */
	private const ITEM_META_COMBO_APPLY_FULL_PAYMENT =
		'_eilmo_cf_combo_apply_full_payment_discount';

	/*
	 * --------------------------------------------------
	 * Order Bump line-item metadata.
	 * --------------------------------------------------
	 */

	/**
	 * Order Bump marker.
	 */
	private const ITEM_META_ORDER_BUMP =
		'_eilmo_cf_order_bump';

	/**
	 * Order Bump ID.
	 */
	private const ITEM_META_ORDER_BUMP_ID =
		'_eilmo_cf_order_bump_id';

	/**
	 * Order Bump title.
	 */
	private const ITEM_META_ORDER_BUMP_TITLE =
		'_eilmo_cf_order_bump_title';

	/**
	 * Order Bump own discount.
	 */
	private const ITEM_META_ORDER_BUMP_DISCOUNT =
		'_eilmo_cf_order_bump_discount_amount';

	/**
	 * Order Bump Automatic Discount compatibility.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC =
		'_eilmo_cf_order_bump_apply_automatic_discount';

	/**
	 * Order Bump Coupon compatibility.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_COUPON =
		'_eilmo_cf_order_bump_apply_coupon';

	/**
	 * Order Bump Full Payment compatibility.
	 */
	private const ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT =
		'_eilmo_cf_order_bump_apply_full_payment_discount';

	/*
	 * --------------------------------------------------
	 * Shared line discount metadata.
	 * --------------------------------------------------
	 */

	/**
	 * Automatic Discount.
	 */
	private const ITEM_META_AUTOMATIC_DISCOUNT =
		'_eilmo_cf_automatic_discount_amount';

	/**
	 * Coupon Discount.
	 */
	private const ITEM_META_COUPON_DISCOUNT =
		'_eilmo_cf_coupon_discount_amount';

	/**
	 * Full Payment Discount.
	 */
	private const ITEM_META_FULL_PAYMENT_DISCOUNT =
		'_eilmo_cf_full_payment_discount_amount';

	/*
	 * --------------------------------------------------
	 * Delivery item metadata.
	 * --------------------------------------------------
	 */

	/**
	 * Delivery method ID.
	 */
	private const ITEM_META_DELIVERY_METHOD_ID =
		'_eilmo_cf_delivery_method_id';

	/**
	 * Delivery base charge.
	 */
	private const ITEM_META_DELIVERY_BASE_CHARGE =
		'_eilmo_cf_delivery_base_charge';

	/**
	 * Free delivery marker.
	 */
	private const ITEM_META_FREE_DELIVERY_APPLIED =
		'_eilmo_cf_free_delivery_applied';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * Dedicated WooCommerce admin meta box.
		 *
		 * Supports both:
		 * - Legacy shop_order edit screen.
		 * - HPOS WooCommerce order edit screen.
		 */
		add_action(
			'add_meta_boxes',
			array(
				$this,
				'register_admin_meta_box',
			),
			20,
			2
		);

		/*
		 * ---------------------------------------------
		 * Order item presentation.
		 * ---------------------------------------------
		 *
		 * Eilmo keeps source-specific and technical metadata
		 * internally. WooCommerce already displays the final
		 * line total and effective discount on the right side,
		 * so the left side shows only useful purchase context:
		 *
		 * - Combo title.
		 * - Special Offer title.
		 *
		 * Delivery implementation metadata is hidden as well.
		 */
		add_filter(
			'woocommerce_hidden_order_itemmeta',
			array(
				$this,
				'hide_internal_item_meta',
			),
			PHP_INT_MAX
		);

		add_filter(
			'woocommerce_order_item_get_formatted_meta_data',
			array(
				$this,
				'format_eilmo_item_meta',
			),
			PHP_INT_MAX,
			2
		);

		/*
		 * Customer-facing order totals:
		 *
		 * - Thank You page.
		 * - My Account > Order.
		 */
		add_filter(
			'woocommerce_get_order_item_totals',
			array(
				$this,
				'add_customer_payment_rows',
			),
			20,
			3
		);

		/*
		 * WooCommerce emails.
		 */
		add_action(
			'woocommerce_email_after_order_table',
			array(
				$this,
				'render_email_payment_details',
			),
			20,
			4
		);
	}

	/**
	 * Hide internal Eilmo order-item metadata.
	 *
	 * Nothing is deleted from the database. These values
	 * remain available for calculations, reporting and
	 * future Eilmo processing.
	 *
	 * @param array<int, string> $hidden Hidden metadata.
	 *
	 * @return array<int, string>
	 */
	public function hide_internal_item_meta(
		array $hidden
	): array {

		$eilmo_hidden =
			array(
				/*
				 * Combo internals.
				 */
				self::ITEM_META_COMBO_COMPONENT,
				self::ITEM_META_COMBO_ID,
				self::ITEM_META_COMBO_TITLE,
				self::ITEM_META_COMBO_DISCOUNT,
				self::ITEM_META_COMBO_APPLY_AUTOMATIC,
				self::ITEM_META_COMBO_APPLY_COUPON,
				self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,

				/*
				 * Order Bump internals.
				 */
				self::ITEM_META_ORDER_BUMP,
				self::ITEM_META_ORDER_BUMP_ID,
				self::ITEM_META_ORDER_BUMP_TITLE,
				self::ITEM_META_ORDER_BUMP_DISCOUNT,
				self::ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC,
				self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
				self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,

				/*
				 * Source-specific discount metadata.
				 *
				 * WooCommerce already displays the effective line
				 * discount beside the final line total.
				 */
				self::ITEM_META_AUTOMATIC_DISCOUNT,
				self::ITEM_META_COUPON_DISCOUNT,
				self::ITEM_META_FULL_PAYMENT_DISCOUNT,

				/*
				 * Delivery technical metadata.
				 *
				 * WooCommerce already shows the shipping method
				 * title and charge.
				 */
				self::ITEM_META_DELIVERY_METHOD_ID,
				self::ITEM_META_DELIVERY_BASE_CHARGE,
				self::ITEM_META_FREE_DELIVERY_APPLIED,

				/*
				 * Known legacy Final Item Total keys.
				 */
				'_eilmo_cf_final_item_total',
				'_eilmo_cf_combo_final_item_total',
				'_eilmo_cf_order_bump_final_item_total',
				'_eilmo_cf_final_line_total',
				'_eilmo_cf_item_final_total',
			);

		return array_values(
			array_unique(
				array_merge(
					$hidden,
					$eilmo_hidden
				)
			)
		);
	}

	/**
	 * Format Eilmo order-item metadata.
	 *
	 * Visible Eilmo context is intentionally minimal:
	 *
	 * Combo: Keyboard+mouse
	 * Special Offer: This is special offer
	 *
	 * Discount metadata is not rebuilt on the left because
	 * WooCommerce already displays the effective discount
	 * beside the final line total. Delivery technical meta
	 * is also removed from the shipping row.
	 *
	 * @param array<int|string, mixed> $formatted_meta Metadata.
	 * @param mixed                    $item           Order item.
	 *
	 * @return array<int|string, mixed>
	 */
	public function format_eilmo_item_meta(
		array $formatted_meta,
		$item
	): array {

		if (
			! is_object(
				$item
			) ||
			! method_exists(
				$item,
				'get_meta'
			)
		) {
			return $formatted_meta;
		}

		$order =
			$this->resolve_item_order(
				$item
			);

		/*
		 * Never change metadata presentation for orders
		 * that were not created through Eilmo.
		 */
		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return $formatted_meta;
		}

		/*
		 * Remove technical, source-specific and legacy
		 * Eilmo metadata before rebuilding the small amount
		 * of useful purchase context shown to humans.
		 */
		foreach (
			$formatted_meta as
				$meta_id =>
					$meta
		) {

			if (
				! is_object(
					$meta
				)
			) {
				continue;
			}

			$key =
				isset(
					$meta->key
				)
					? (string) $meta->key
					: '';

			$display_key =
				isset(
					$meta->display_key
				)
					? $this->normalize_display_label(
						(string) $meta->display_key
					)
					: '';

			if (
				$this->is_final_item_total_meta(
					$key,
					$display_key
				) ||
				$this->is_internal_eilmo_item_meta(
					$key
				) ||
				in_array(
					$display_key,
					array(
						'combo discount',
						'automatic discount',
						'order bump discount',
						'coupon discount',
						'full payment discount',
						'final item total',
						'final line total',
						'item final total',
					),
					true
				)
			) {
				unset(
					$formatted_meta[
						$meta_id
					]
				);
			}
		}

		/*
		 * Combo information.
		 */
		$is_combo =
			'yes' ===
				(string) $item->get_meta(
					self::ITEM_META_COMBO_COMPONENT,
					true
				);

		$combo_title =
			sanitize_text_field(
				(string) $item->get_meta(
					self::ITEM_META_COMBO_TITLE,
					true
				)
			);

		if (
			$is_combo &&
			'' !==
				$combo_title
		) {
			$formatted_meta[] =
				$this->build_display_meta(
					'_eilmo_cf_display_combo',
					__(
						'Combo',
						'eilmo-checkout-flow'
					),
					$combo_title,
					esc_html(
						$combo_title
					)
				);
		}

		/*
		 * Order Bump information.
		 */
		$is_order_bump =
			'yes' ===
				(string) $item->get_meta(
					self::ITEM_META_ORDER_BUMP,
					true
				);

		$order_bump_title =
			sanitize_text_field(
				(string) $item->get_meta(
					self::ITEM_META_ORDER_BUMP_TITLE,
					true
				)
			);

		if (
			$is_order_bump &&
			'' !==
				$order_bump_title
		) {
			$formatted_meta[] =
				$this->build_display_meta(
					'_eilmo_cf_display_special_offer',
					__(
						'Special Discount',
						'eilmo-checkout-flow'
					),
					$order_bump_title,
					esc_html(
						$order_bump_title
					)
				);
		}

		return array_values(
			$formatted_meta
		);
	}

	/**
	 * Determine whether metadata is an internal
	 * Eilmo item field.
	 *
	 * @param string $key Metadata key.
	 *
	 * @return bool
	 */
	private function is_internal_eilmo_item_meta(
		string $key
	): bool {

		return in_array(
			$key,
			array(
				self::ITEM_META_COMBO_COMPONENT,
				self::ITEM_META_COMBO_ID,
				self::ITEM_META_COMBO_TITLE,
				self::ITEM_META_COMBO_DISCOUNT,
				self::ITEM_META_COMBO_APPLY_AUTOMATIC,
				self::ITEM_META_COMBO_APPLY_COUPON,
				self::ITEM_META_COMBO_APPLY_FULL_PAYMENT,

				self::ITEM_META_ORDER_BUMP,
				self::ITEM_META_ORDER_BUMP_ID,
				self::ITEM_META_ORDER_BUMP_TITLE,
				self::ITEM_META_ORDER_BUMP_DISCOUNT,
				self::ITEM_META_ORDER_BUMP_APPLY_AUTOMATIC,
				self::ITEM_META_ORDER_BUMP_APPLY_COUPON,
				self::ITEM_META_ORDER_BUMP_APPLY_FULL_PAYMENT,

				self::ITEM_META_AUTOMATIC_DISCOUNT,
				self::ITEM_META_COUPON_DISCOUNT,
				self::ITEM_META_FULL_PAYMENT_DISCOUNT,

				self::ITEM_META_DELIVERY_METHOD_ID,
				self::ITEM_META_DELIVERY_BASE_CHARGE,
				self::ITEM_META_FREE_DELIVERY_APPLIED,

				'_eilmo_cf_final_item_total',
				'_eilmo_cf_combo_final_item_total',
				'_eilmo_cf_order_bump_final_item_total',
				'_eilmo_cf_final_line_total',
				'_eilmo_cf_item_final_total',
			),
			true
		);
	}

	/**
	 * Determine whether metadata represents the
	 * redundant Final Item Total row.
	 *
	 * Older plugin versions may have stored this under
	 * different keys, therefore both raw keys and labels
	 * are checked.
	 *
	 * @param string $key         Raw metadata key.
	 * @param string $display_key Normalized display key.
	 *
	 * @return bool
	 */
	private function is_final_item_total_meta(
		string $key,
		string $display_key = ''
	): bool {

		$key =
			strtolower(
				trim(
					$key
				)
			);

		if (
			in_array(
				$key,
				array(
					'_eilmo_cf_final_item_total',
					'_eilmo_cf_combo_final_item_total',
					'_eilmo_cf_order_bump_final_item_total',
					'_eilmo_cf_final_line_total',
					'_eilmo_cf_item_final_total',
				),
				true
			)
		) {
			return true;
		}

		/*
		 * Catch unknown older Eilmo key variants such as:
		 *
		 * _eilmo_cf_xxx_final_total
		 * _eilmo_cf_final_xxx_total
		 */
		if (
			0 ===
				strpos(
					$key,
					'_eilmo_cf_'
				) &&
			false !==
				strpos(
					$key,
					'final'
				) &&
			false !==
				strpos(
					$key,
					'total'
				)
		) {
			return true;
		}

		return in_array(
			$display_key,
			array(
				'final item total',
				'final line total',
				'item final total',
			),
			true
		);
	}

	/**
	 * Normalize visible metadata label.
	 *
	 * @param string $label Label.
	 *
	 * @return string
	 */
	private function normalize_display_label(
		string $label
	): string {

		$label =
			wp_strip_all_tags(
				$label
			);

		$label =
			html_entity_decode(
				$label,
				ENT_QUOTES,
				get_bloginfo(
					'charset'
				) ?: 'UTF-8'
			);

		$label =
			preg_replace(
				'/\s+/',
				' ',
				$label
			);

		$label =
			is_string(
				$label
			)
				? $label
				: '';

		return strtolower(
			trim(
				rtrim(
					$label,
					':'
				)
			)
		);
	}

	/**
	 * Build formatted WooCommerce metadata object.
	 *
	 * @param string $key           Internal display key.
	 * @param string $display_key   Human-readable key.
	 * @param mixed  $value         Raw value.
	 * @param string $display_value Human-readable value.
	 *
	 * @return object
	 */
	private function build_display_meta(
		string $key,
		string $display_key,
		$value,
		string $display_value
	) {

		$meta =
			new \stdClass();

		$meta->id =
			0;

		$meta->key =
			$key;

		$meta->value =
			$value;

		$meta->display_key =
			$display_key;

		$meta->display_value =
			$display_value;

		return $meta;
	}

	/**
	 * Resolve parent order from WooCommerce order item.
	 *
	 * @param mixed $item Order item.
	 *
	 * @return WC_Order|null
	 */
	private function resolve_item_order(
		$item
	): ?WC_Order {

		if (
			! is_object(
				$item
			) ||
			! method_exists(
				$item,
				'get_order_id'
			)
		) {
			return null;
		}

		$order_id =
			absint(
				$item->get_order_id()
			);

		if (
			$order_id <= 0
		) {
			return null;
		}

		$order =
			wc_get_order(
				$order_id
			);

		return $order instanceof WC_Order
			? $order
			: null;
	}

	/**
	 * Register dedicated Eilmo payment meta box.
	 *
	 * Important:
	 *
	 * WooCommerce HPOS can pass a WC_Order object
	 * instead of WP_Post through the add_meta_boxes
	 * flow, therefore callback parameters are
	 * intentionally not strictly type-hinted.
	 *
	 * @param mixed $screen_id Screen/post type.
	 * @param mixed $object    Order/post object.
	 *
	 * @return void
	 */
	public function register_admin_meta_box(
		$screen_id = '',
		$object = null
	): void {

		unset( $screen_id );

		$order =
			$this->resolve_order(
				$object
			);

		/*
		 * Do not add an empty Eilmo box to normal
		 * WooCommerce orders.
		 */
		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$screen =
			$this->get_order_admin_screen();

		if ( '' === $screen ) {
			return;
		}

		if ( $this->should_render_special_discount_meta_box( $order ) ) {
			add_meta_box(
				'eilmo-cf-applied-special-discounts',
				__(
					'Applied Special Discounts',
					'eilmo-checkout-flow'
				),
				array(
					$this,
					'render_special_discount_meta_box',
				),
				$screen,
				'normal',
				'high'
			);
		}

		add_meta_box(
			'eilmo-cf-payment-details',
			__(
				'Checkout Flow Payment Details',
				'eilmo-checkout-flow'
			),
			array(
				$this,
				'render_admin_meta_box',
			),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Render every fulfilled Special Discount condition saved with the order.
	 *
	 * @param mixed $post_or_order WooCommerce order or WP_Post.
	 *
	 * @return void
	 */
	public function render_special_discount_meta_box(
		$post_or_order
	): void {
		$order = $this->resolve_order( $post_or_order );

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order( $order ) ||
			! $this->should_render_special_discount_meta_box( $order )
		) {
			return;
		}

		$rules = $order->get_meta(
			self::META_APPLIED_SPECIAL_DISCOUNTS,
			true
		);

		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return;
		}

		echo '<div class="eilmo-cf-order-special-discounts">';
		echo '<div class="eilmo-cf-order-special-discounts__grid">';

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$rule_id = sanitize_key( (string) ( $rule['id'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $rule['title'] ?? $rule_id ) );
			if ( '' === $title ) {
				$title = __( 'Special Discount', 'eilmo-checkout-flow' );
			}

			echo '<article class="eilmo-cf-order-special-discount">';
			echo '<header class="eilmo-cf-order-special-discount__header">';
			echo '<strong class="eilmo-cf-order-special-discount__title">';
			echo esc_html( $title );
			echo '</strong>';
			echo '<span class="eilmo-cf-order-special-discount__badge">';
			echo esc_html__( 'Applied', 'eilmo-checkout-flow' );
			echo '</span></header>';

			echo '<dl class="eilmo-cf-order-special-discount__details">';
			$this->render_special_discount_detail(
				__( 'Condition', 'eilmo-checkout-flow' ),
				esc_html( $this->format_special_discount_condition( $rule, $order ) )
			);
			$this->render_special_discount_detail(
				__( 'Benefit', 'eilmo-checkout-flow' ),
				$this->format_special_discount_benefit( $rule, $order )
			);
			echo '</dl>';

			if ( '' !== $rule_id ) {
				echo '<footer class="eilmo-cf-order-special-discount__footer">';
				echo '<span>' . esc_html__( 'Rule:', 'eilmo-checkout-flow' ) . '</span>';
				echo '<code>' . esc_html( $rule_id ) . '</code>';
				echo '</footer>';
			}

			echo '</article>';
		}

		echo '</div></div>';
	}

	/**
	 * Decide whether the Special Discount order meta box can exist.
	 *
	 * The current feature setting is authoritative. A saved applied-rule
	 * snapshot is also required, so disabled or empty sections do not register
	 * a meta box and do not render any fallback markup.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function should_render_special_discount_meta_box(
		WC_Order $order
	): bool {
		$settings = get_option( CheckoutSettings::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$enabled = 'no';
		if (
			isset( $settings['order_bumps'] ) &&
			is_array( $settings['order_bumps'] ) &&
			array_key_exists( 'enabled', $settings['order_bumps'] )
		) {
			$enabled = (string) $settings['order_bumps']['enabled'];
		} elseif (
			isset( $settings['discounts'] ) &&
			is_array( $settings['discounts'] )
		) {
			$enabled = (string) ( $settings['discounts']['enabled'] ?? 'no' );
		}

		if ( 'yes' !== $enabled ) {
			return false;
		}

		$rules = $order->get_meta(
			self::META_APPLIED_SPECIAL_DISCOUNTS,
			true
		);

		return is_array( $rules ) && ! empty( $rules );
	}

	/**
	 * Render one label/value pair in the applied rule snapshot.
	 *
	 * @param string $label Label.
	 * @param string $value Escaped value or safe WooCommerce price HTML.
	 *
	 * @return void
	 */
	private function render_special_discount_detail(
		string $label,
		string $value
	): void {
		echo '<div class="eilmo-cf-order-special-discount__detail">';
		echo '<dt>' . esc_html( $label ) . '</dt>';
		echo '<dd>' . wp_kses_post( $value ) . '</dd>';
		echo '</div>';
	}

	/**
	 * Format the condition that was fulfilled.
	 *
	 * @param array<string, mixed> $rule  Saved rule snapshot.
	 * @param WC_Order            $order Order.
	 *
	 * @return string
	 */
	private function format_special_discount_condition(
		array $rule,
		WC_Order $order
	): string {
		$type = sanitize_key( (string) ( $rule['condition_type'] ?? 'always' ) );
		$minimum = max( 0.0, (float) ( $rule['condition_minimum'] ?? 0 ) );
		$maximum = max( 0.0, (float) ( $rule['condition_maximum'] ?? 0 ) );

		if ( in_array( $type, array( 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) ) {
			if ( $minimum > 0 && $maximum > 0 ) {
				return sprintf(
					/* translators: 1: minimum order amount, 2: maximum order amount. */
					__( 'Order amount from %1$s to %2$s', 'eilmo-checkout-flow' ),
					wp_strip_all_tags( $this->format_price( $minimum, $order ) ),
					wp_strip_all_tags( $this->format_price( $maximum, $order ) )
				);
			}

			return sprintf(
				/* translators: %s: minimum order amount. */
				__( 'Order amount of %s or more', 'eilmo-checkout-flow' ),
				wp_strip_all_tags( $this->format_price( $minimum, $order ) )
			);
		}

		if ( 'cart_quantity' === $type ) {
			$quantity = max( 1, absint( $rule['condition_cart_quantity'] ?? 1 ) );
			return sprintf(
				/* translators: %d: minimum cart item quantity. */
				_n(
					'%d cart item or more',
					'%d cart items or more',
					$quantity,
					'eilmo-checkout-flow'
				),
				$quantity
			);
		}

		if ( 'specific_product' === $type ) {
			$product_name = sanitize_text_field(
				(string) ( $rule['condition_product_name'] ?? '' )
			);
			$product_id = absint( $rule['condition_product_id'] ?? 0 );
			if ( '' === $product_name && $product_id > 0 ) {
				$product = function_exists( 'wc_get_product' )
					? wc_get_product( $product_id )
					: false;
				$product_name = $product && method_exists( $product, 'get_name' )
					? sanitize_text_field( (string) $product->get_name() )
					: sprintf( '#%d', $product_id );
			}

			return sprintf(
				/* translators: 1: product name, 2: required quantity. */
				__( '%1$s × %2$d in cart', 'eilmo-checkout-flow' ),
				$product_name,
				max( 1, absint( $rule['condition_product_quantity'] ?? 1 ) )
			);
		}

		return __( 'Always available', 'eilmo-checkout-flow' );
	}

	/**
	 * Format the actual benefit received by the order.
	 *
	 * @param array<string, mixed> $rule  Saved rule snapshot.
	 * @param WC_Order            $order Order.
	 *
	 * @return string Safe HTML.
	 */
	private function format_special_discount_benefit(
		array $rule,
		WC_Order $order
	): string {
		$type = sanitize_key( (string) ( $rule['reward_type'] ?? '' ) );
		$amount = max( 0.0, (float) ( $rule['benefit_amount'] ?? 0 ) );

		if ( ! empty( $rule['free_delivery'] ) || 'free_delivery' === $type ) {
			$benefit = esc_html__( 'Free delivery', 'eilmo-checkout-flow' );
			if ( $amount > 0 ) {
				$benefit .= ' <span class="eilmo-cf-order-special-discount__saving">· ';
				$benefit .= sprintf(
					/* translators: %s: saved delivery amount. */
					esc_html__( 'saved %s', 'eilmo-checkout-flow' ),
					$this->format_price( $amount, $order )
				);
				$benefit .= '</span>';
			}
			return $benefit;
		}

		if ( in_array( $type, array( 'fixed', 'fixed_discount', 'percentage', 'percentage_discount' ), true ) ) {
			return sprintf(
				/* translators: %s: discount amount. */
				__( '%s off', 'eilmo-checkout-flow' ),
				$this->format_price( $amount, $order )
			);
		}

		$product_name = sanitize_text_field( (string) ( $rule['product_name'] ?? '' ) );
		$quantity = max( 1, absint( $rule['quantity'] ?? 1 ) );
		if ( '' !== $product_name ) {
			$benefit = sprintf(
				/* translators: 1: reward product name, 2: quantity. */
				esc_html__( '%1$s × %2$d', 'eilmo-checkout-flow' ),
				esc_html( $product_name ),
				$quantity
			);
			if ( $amount > 0 ) {
				$benefit .= ' <span class="eilmo-cf-order-special-discount__saving">· ';
				$benefit .= sprintf(
					/* translators: %s: reward value. */
					esc_html__( 'value %s', 'eilmo-checkout-flow' ),
					$this->format_price( $amount, $order )
				);
				$benefit .= '</span>';
			}
			return $benefit;
		}

		return $amount > 0
			? $this->format_price( $amount, $order )
			: esc_html__( 'Applied', 'eilmo-checkout-flow' );
	}

	/**
	 * Render dedicated admin payment meta box.
	 *
	 * @param mixed $post_or_order WooCommerce order or WP_Post.
	 *
	 * @return void
	 */
	public function render_admin_meta_box(
		$post_or_order
	): void {

		$order =
			$this->resolve_order(
				$post_or_order
			);

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$payment_type =
			$this->get_payment_type(
				$order
			);

		$advance_amount =
			$this->get_amount(
				$order,
				self::META_ADVANCE_AMOUNT
			);

		$pay_now =
			$this->get_amount(
				$order,
				self::META_PAY_NOW
			);

		$remaining_due =
			$this->get_amount(
				$order,
				self::META_REMAINING_DUE
			);

		$transaction_id =
			sanitize_text_field(
				(string) $order
					->get_meta(
						self::META_TRANSACTION_ID,
						true
					)
			);

		$payment_status =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_STATUS,
						true
					)
			);

		$payment_source =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_SOURCE,
						true
					)
			);

		$payment_method_key =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_METHOD_KEY,
						true
					)
			);

		$payment_method_title =
			sanitize_text_field(
				(string) $order
					->get_payment_method_title()
			);

		$proof_file = PaymentProof::get_order_file( $order );
		$proof_name = sanitize_file_name( (string) $order->get_meta( PaymentProof::META_NAME, true ) );
		$proof_url = '';
		if ( '' !== $proof_file ) {
			$proof_url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'eilmo_cf_view_payment_proof', 'order_id' => $order->get_id() ),
					admin_url( 'admin-ajax.php' )
				),
				'eilmo_cf_view_payment_proof_' . $order->get_id()
			);
		}

		if (
			'' ===
				$payment_method_title
		) {
			$payment_method_title =
				$this->format_key_label(
					$payment_method_key
				);
		}

		$advance_rule_id =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_ADVANCE_RULE_ID,
						true
					)
			);

		$advance_rule_type =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_ADVANCE_RULE_TYPE,
						true
					)
			);

		$coupon_details =
			$this->get_coupon_details(
				$order
			);

		?>
		<div class="eilmo-cf-order-payment-meta">

			<div class="eilmo-cf-order-payment-meta__grid">

				<?php
				$this->render_admin_item(
					__(
						'Payment Type',
						'eilmo-checkout-flow'
					),
					esc_html(
						$this->get_payment_type_label(
							$payment_type
						)
					)
				);

				$this->render_admin_item(
					__(
						'Payment Method',
						'eilmo-checkout-flow'
					),
					esc_html(
						$payment_method_title
					)
				);

				if (
					'advance' ===
						$payment_type
				) {
					$this->render_admin_item(
						__(
							'Advance Amount',
							'eilmo-checkout-flow'
						),
						$this->format_price(
							$advance_amount,
							$order
						),
						'amount'
					);
				}

				$this->render_admin_item(
					__(
						'Pay Now',
						'eilmo-checkout-flow'
					),
					$this->format_price(
						$pay_now,
						$order
					),
					'amount highlight'
				);

				$this->render_admin_item(
					__(
						'Remaining Due',
						'eilmo-checkout-flow'
					),
					$this->format_price(
						$remaining_due,
						$order
					),
					'amount due'
				);

				$payment_proof_html = array();

				if ( '' !== $transaction_id ) {
					$payment_proof_html[] = sprintf(
						'<span class="eilmo-cf-order-payment-meta__proof-entry"><span class="eilmo-cf-order-payment-meta__proof-label">%1$s</span><code class="eilmo-cf-order-payment-meta__code">%2$s</code></span>',
						esc_html__( 'Transaction ID', 'eilmo-checkout-flow' ),
						esc_html( $transaction_id )
					);
				}

				if ( '' !== $proof_url ) {
					$payment_proof_html[] = sprintf(
						'<button type="button" class="button eilmo-cf-payment-proof-button" data-eilmo-payment-proof-open data-proof-url="%1$s" data-proof-name="%2$s"><span class="dashicons dashicons-format-image" aria-hidden="true"></span> %3$s</button>',
						esc_url( $proof_url ),
						esc_attr( '' !== $proof_name ? $proof_name : __( 'Payment screenshot', 'eilmo-checkout-flow' ) ),
						esc_html__( 'View screenshot', 'eilmo-checkout-flow' )
					);
				}

				if ( ! empty( $payment_proof_html ) ) {
					$this->render_admin_item(
						__( 'Payment Proof', 'eilmo-checkout-flow' ),
						sprintf(
							'<span class="eilmo-cf-order-payment-meta__proof-list">%s</span>',
							implode( '', $payment_proof_html )
						),
						'proof'
					);
				}

				$this->render_admin_item(
					__(
						'Payment Status',
						'eilmo-checkout-flow'
					),
					sprintf(
						'<span class="eilmo-cf-order-payment-status eilmo-cf-order-payment-status--%1$s">%2$s</span>',
						esc_attr(
							$this->get_payment_status_tone(
								$payment_status
							)
						),
						esc_html(
							$this->get_payment_status_label(
								$payment_status
							)
						)
					)
				);

				/*
				 * Coupon information is completely conditional.
				 *
				 * If no coupon produced an actual discount or
				 * free-delivery benefit, none of these fields are
				 * rendered.
				 */
				if (
					! empty(
						$coupon_details[
							'applied'
						]
					)
				) {
					$this->render_admin_item(
						__(
							'Coupon Code',
							'eilmo-checkout-flow'
						),
						sprintf(
							'<code class="eilmo-cf-order-payment-meta__code">%s</code>',
							esc_html(
								(string) (
									$coupon_details[
										'code'
									] ??
										''
								)
							)
						)
					);

					$this->render_admin_item(
						__(
							'Coupon Discount',
							'eilmo-checkout-flow'
						),
						$this->format_price(
							(float) (
								$coupon_details[
									'discount'
								] ??
									0
							),
							$order
						),
						'amount'
					);

					$this->render_admin_item(
						__(
							'Coupon Source',
							'eilmo-checkout-flow'
						),
						esc_html(
							(string) (
								$coupon_details[
									'source_label'
								] ??
									''
							)
						)
					);

					if (
						! empty(
							$coupon_details[
								'free_delivery'
							]
						)
					) {
						$this->render_admin_item(
							__(
								'Coupon Benefit',
								'eilmo-checkout-flow'
							),
							esc_html__(
								'Free Delivery',
								'eilmo-checkout-flow'
							)
						);
					}
				}

				if (
					'' !==
						$payment_source
				) {
					$this->render_admin_item(
						__(
							'Payment Source',
							'eilmo-checkout-flow'
						),
						esc_html(
							$this->get_payment_source_label(
								$payment_source
							)
						)
					);
				}

				if (
					'advance' ===
						$payment_type &&
					'' !==
						$advance_rule_type
				) {
					$this->render_admin_item(
						__(
							'Advance Rule',
							'eilmo-checkout-flow'
						),
						esc_html(
							$this->format_key_label(
								$advance_rule_type
							)
						)
					);
				}
				?>

			</div>

			<?php
			if (
				'advance' ===
					$payment_type &&
				'' !==
					$advance_rule_id
			) :
				?>

				<div class="eilmo-cf-order-payment-meta__footer">

					<span>
						<?php
						esc_html_e(
							'Rule ID:',
							'eilmo-checkout-flow'
						);
						?>
					</span>

					<code>
						<?php
						echo esc_html(
							$advance_rule_id
						);
						?>
					</code>

				</div>

			<?php endif; ?>

		</div>

		<?php if ( '' !== $proof_url ) : ?>
			<div class="eilmo-cf-payment-proof-modal" data-eilmo-payment-proof-modal hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Payment screenshot', 'eilmo-checkout-flow' ); ?>">
				<div class="eilmo-cf-payment-proof-modal__backdrop" data-eilmo-payment-proof-close></div>
				<div class="eilmo-cf-payment-proof-modal__dialog">
					<div class="eilmo-cf-payment-proof-modal__header">
						<strong data-eilmo-payment-proof-title><?php echo esc_html( '' !== $proof_name ? $proof_name : __( 'Payment screenshot', 'eilmo-checkout-flow' ) ); ?></strong>
						<button type="button" class="button-link eilmo-cf-payment-proof-modal__close" data-eilmo-payment-proof-close aria-label="<?php esc_attr_e( 'Close', 'eilmo-checkout-flow' ); ?>">&times;</button>
					</div>
					<div class="eilmo-cf-payment-proof-modal__body"><img src="" alt="<?php esc_attr_e( 'Customer payment screenshot', 'eilmo-checkout-flow' ); ?>" data-eilmo-payment-proof-image></div>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render one admin payment item.
	 *
	 * @param string $label       Label.
	 * @param string $value_html  Value HTML.
	 * @param string $extra_class Extra class.
	 *
	 * @return void
	 */
	private function render_admin_item(
		string $label,
		string $value_html,
		string $extra_class = ''
	): void {

		$classes =
			array(
				'eilmo-cf-order-payment-meta__item',
			);

		if (
			'' !==
				$extra_class
		) {
			$extra_classes =
				preg_split(
					'/\s+/',
					$extra_class
				);

			if (
				is_array(
					$extra_classes
				)
			) {
				foreach (
					$extra_classes as
						$class
				) {
					$class =
						sanitize_html_class(
							(string) $class
						);

					if (
						'' !==
							$class
					) {
						$classes[] =
							'eilmo-cf-order-payment-meta__item--' .
								$class;
					}
				}
			}
		}

		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		>

			<span class="eilmo-cf-order-payment-meta__label">
				<?php echo esc_html( $label ); ?>
			</span>

			<div class="eilmo-cf-order-payment-meta__value">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Value HTML is constructed and escaped internally.
				echo $value_html;
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Add Eilmo payment rows to WooCommerce
	 * customer-facing order totals.
	 *
	 * @param array<string, array<string, mixed>> $total_rows  Rows.
	 * @param mixed                               $order       Order.
	 * @param string                              $tax_display Tax display.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_customer_payment_rows(
		array $total_rows,
		$order,
		$tax_display = ''
	): array {

		unset( $tax_display );

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return $total_rows;
		}

		$eilmo_rows =
			$this->get_customer_rows(
				$order
			);

		if (
			empty(
				$eilmo_rows
			)
		) {
			return $total_rows;
		}

		$rows =
			array();

		$inserted =
			false;

		foreach (
			$total_rows as
				$key =>
					$row
		) {
			$rows[
				$key
			] =
				$row;

			if (
				'payment_method' ===
					$key
			) {
				foreach (
					$eilmo_rows as
						$eilmo_key =>
							$eilmo_row
				) {
					$rows[
						$eilmo_key
					] =
						$eilmo_row;
				}

				$inserted =
					true;
			}
		}

		if (
			! $inserted
		) {
			foreach (
				$eilmo_rows as
					$eilmo_key =>
						$eilmo_row
			) {
				$rows[
					$eilmo_key
				] =
					$eilmo_row;
			}
		}

		return $rows;
	}

	/**
	 * Render Eilmo payment details in emails.
	 *
	 * @param mixed $order         Order.
	 * @param mixed $sent_to_admin Sent to admin.
	 * @param mixed $plain_text    Plain text.
	 * @param mixed $email         Email.
	 *
	 * @return void
	 */
	public function render_email_payment_details(
		$order,
		$sent_to_admin,
		$plain_text,
		$email
	): void {

		unset(
			$sent_to_admin,
			$email
		);

		if (
			! $order instanceof WC_Order ||
			! $this->is_eilmo_order(
				$order
			)
		) {
			return;
		}

		$rows =
			$this->get_email_rows(
				$order
			);

		if (
			empty(
				$rows
			)
		) {
			return;
		}

		if (
			$plain_text
		) {

			echo "\n";
			echo esc_html__(
				'Payment Details',
				'eilmo-checkout-flow'
			);
			echo "\n";

			foreach (
				$rows as
					$row
			) {
				echo esc_html(
					(string) (
						$row[
							'label'
						] ??
							''
					)
				);

				echo ' ';

				echo esc_html(
					wp_strip_all_tags(
						(string) (
							$row[
								'value'
							] ??
								''
						)
					)
				);

				echo "\n";
			}

			echo "\n";

			return;
		}

		?>
		<h2>
			<?php
			esc_html_e(
				'Payment Details',
				'eilmo-checkout-flow'
			);
			?>
		</h2>

		<table
			cellspacing="0"
			cellpadding="6"
			style="width:100%;border:1px solid #e5e5e5;"
			border="1"
		>

			<tbody>

				<?php foreach ( $rows as $row ) : ?>

					<tr>

						<th
							scope="row"
							style="text-align:left;"
						>
							<?php
							echo esc_html(
								(string) (
									$row[
										'label'
									] ??
										''
								)
							);
							?>
						</th>

						<td>
							<?php
							echo wp_kses_post(
								(string) (
									$row[
										'value'
									] ??
										''
								)
							);
							?>
						</td>

					</tr>

				<?php endforeach; ?>

			</tbody>

		</table>
		<?php
	}

	/**
	 * Build customer-facing payment rows.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_customer_rows(
		WC_Order $order
	): array {

		$payment_type =
			$this->get_payment_type(
				$order
			);

		$rows =
			array();

		$rows[
			'eilmo_payment_type'
		] =
			array(
				'label' =>
					__(
						'Payment Type:',
						'eilmo-checkout-flow'
					),

				'value' =>
					$this->get_payment_type_label(
						$payment_type
					),
			);

		if (
			'advance' ===
				$payment_type
		) {
			$rows[
				'eilmo_advance_amount'
			] =
				array(
					'label' =>
						__(
							'Advance Payment:',
							'eilmo-checkout-flow'
						),

					'value' =>
						$this->format_price(
							$this->get_amount(
								$order,
								self::META_ADVANCE_AMOUNT
							),
							$order
						),
				);
		}

		$rows[
			'eilmo_pay_now'
		] =
			array(
				'label' =>
					__(
						'Pay Now:',
						'eilmo-checkout-flow'
					),

				'value' =>
					$this->format_price(
						$this->get_amount(
							$order,
							self::META_PAY_NOW
						),
						$order
					),
			);

		$rows[
			'eilmo_remaining_due'
		] =
			array(
				'label' =>
					__(
						'Remaining Due:',
						'eilmo-checkout-flow'
					),

				'value' =>
					$this->format_price(
						$this->get_amount(
							$order,
							self::META_REMAINING_DUE
						),
						$order
					),
			);

		$transaction_id =
			sanitize_text_field(
				(string) $order
					->get_meta(
						self::META_TRANSACTION_ID,
						true
					)
			);

		if (
			'' !==
				$transaction_id
		) {
			$rows[
				'eilmo_transaction_id'
			] =
				array(
					'label' =>
						__(
							'Transaction ID:',
							'eilmo-checkout-flow'
						),

					'value' =>
						esc_html(
							$transaction_id
						),
				);
		}

		$payment_status =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_STATUS,
						true
					)
			);

		if (
			'' !==
				$payment_status
		) {
			$rows[
				'eilmo_payment_status'
			] =
				array(
					'label' =>
						__(
							'Payment Status:',
							'eilmo-checkout-flow'
						),

					'value' =>
						$this->get_payment_status_label(
							$payment_status
						),
				);
		}

		return $rows;
	}

	/**
	 * Build email rows.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_email_rows(
		WC_Order $order
	): array {

		$customer_rows =
			$this->get_customer_rows(
				$order
			);

		$rows =
			array();

		foreach (
			$customer_rows as
				$row
		) {

			if (
				! is_array(
					$row
				)
			) {
				continue;
			}

			$rows[] =
				array(
					'label' =>
						(string) (
							$row[
								'label'
							] ??
								''
						),

					'value' =>
						(string) (
							$row[
								'value'
							] ??
								''
						),
				);
		}

		return $rows;
	}

	/**
	 * Resolve WooCommerce order object.
	 *
	 * HPOS meta boxes receive WC_Order.
	 * Legacy order screens may receive WP_Post.
	 *
	 * @param mixed $object Object.
	 *
	 * @return WC_Order|null
	 */
	private function resolve_order(
		$object
	): ?WC_Order {

		if (
			$object instanceof
				WC_Order
		) {
			return $object;
		}

		if (
			$object instanceof
				WP_Post
		) {
			$order =
				wc_get_order(
					$object->ID
				);

			return $order instanceof WC_Order
				? $order
				: null;
		}

		if (
			is_object(
				$object
			) &&
			method_exists(
				$object,
				'get_id'
			)
		) {
			$order =
				wc_get_order(
					absint(
						$object->get_id()
					)
				);

			return $order instanceof WC_Order
				? $order
				: null;
		}

		return null;
	}

	/**
	 * Get WooCommerce order admin screen.
	 *
	 * Supports HPOS and legacy storage.
	 *
	 * @return string
	 */
	private function get_order_admin_screen(): string {

		$hpos_enabled =
			false;

		if (
			class_exists(
				CustomOrdersTableController::class
			) &&
			function_exists(
				'wc_get_container'
			)
		) {
			try {

				$controller =
					wc_get_container()
						->get(
							CustomOrdersTableController::class
						);

				if (
					is_object(
						$controller
					) &&
					method_exists(
						$controller,
						'custom_orders_table_usage_is_enabled'
					)
				) {
					$hpos_enabled =
						(bool) $controller
							->custom_orders_table_usage_is_enabled();
				}

			} catch ( \Throwable $throwable ) {

				$hpos_enabled =
					false;
			}
		}

		if (
			$hpos_enabled &&
			function_exists(
				'wc_get_page_screen_id'
			)
		) {
			return (string)
				wc_get_page_screen_id(
					'shop-order'
				);
		}

		return 'shop_order';
	}

	/**
	 * Determine whether order was created by Eilmo.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return bool
	 */
	private function is_eilmo_order(
		WC_Order $order
	): bool {

		return (
			'yes' === (string) $order->get_meta( '_eilmo_cf_admin_manual', true ) ||
			'eilmo-checkout-flow' ===
				(string) $order
					->get_meta(
						self::META_CREATED_VIA,
						true
					)
		);
	}

	/**
	 * Get payment type.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function get_payment_type(
		WC_Order $order
	): string {

		$type =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_PAYMENT_TYPE,
						true
					)
			);

		if ( 'cash_on_delivery' === $type ) {
			return 'cash_on_delivery';
		}

		return 'advance' === $type ? 'advance' : 'full';
	}

	/**
	 * Get payment type label.
	 *
	 * @param string $payment_type Payment type.
	 *
	 * @return string
	 */
	private function get_payment_type_label(
		string $payment_type
	): string {

		if ( 'cash_on_delivery' === $payment_type ) {
			return __( 'Cash on Delivery', 'eilmo-checkout-flow' );
		}

		if ( 'advance' === $payment_type ) {
			return __( 'Advance Payment', 'eilmo-checkout-flow' );
		}

		return __( 'Full Payment', 'eilmo-checkout-flow' );
	}

	/**
	 * Get payment status label.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function get_payment_status_label(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		$statuses =
			array(
				'unpaid' =>
					__(
						'Unpaid',
						'eilmo-checkout-flow'
					),

				'awaiting_verification' =>
					__(
						'Awaiting Verification',
						'eilmo-checkout-flow'
					),

				'pay_on_delivery' =>
					__(
						'Pay on Delivery',
						'eilmo-checkout-flow'
					),

				'payment_initiated' =>
					__(
						'Payment Initiated',
						'eilmo-checkout-flow'
					),

				'payment_failed' =>
					__(
						'Payment Failed',
						'eilmo-checkout-flow'
					),

				'payment_error' =>
					__(
						'Payment Error',
						'eilmo-checkout-flow'
					),

				'paid' =>
					__(
						'Paid',
						'eilmo-checkout-flow'
					),

				'not_required' =>
					__(
						'Payment Not Required',
						'eilmo-checkout-flow'
					),
			);

		if (
			isset(
				$statuses[
					$status
				]
			)
		) {
			return $statuses[
				$status
			];
		}

		if (
			'' ===
				$status
		) {
			return __(
				'Unpaid',
				'eilmo-checkout-flow'
			);
		}

		return $this->format_key_label(
			$status
		);
	}

	/**
	 * Get status visual tone.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function get_payment_status_tone(
		string $status
	): string {

		$status =
			sanitize_key(
				$status
			);

		if (
			'paid' ===
				$status
		) {
			return 'success';
		}

		if (
			in_array(
				$status,
				array(
					'payment_failed',
					'payment_error',
				),
				true
			)
		) {
			return 'danger';
		}

		if (
			in_array(
				$status,
				array(
					'awaiting_verification',
					'unpaid',
					'payment_initiated',
				),
				true
			)
		) {
			return 'warning';
		}

		if (
			'pay_on_delivery' ===
				$status
		) {
			return 'info';
		}

		return 'neutral';
	}

	/**
	 * Get applied coupon details for admin presentation.
	 *
	 * Coupon fields are considered visible only when the
	 * coupon produced a real benefit:
	 *
	 * - A positive coupon discount, or
	 * - An applied free-delivery benefit.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string, mixed>
	 */
	private function get_coupon_details(
		WC_Order $order
	): array {

		$code =
			sanitize_text_field(
				(string) $order
					->get_meta(
						self::META_COUPON_CODE,
						true
					)
			);

		$discount =
			$this->get_amount(
				$order,
				self::META_COUPON_DISCOUNT
			);

		$free_delivery =
			'yes' ===
				sanitize_key(
					(string) $order
						->get_meta(
							self::META_COUPON_FREE_DELIVERY,
							true
						)
				);

		$source =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_COUPON_SOURCE,
						true
					)
			);

		$type =
			sanitize_key(
				(string) $order
					->get_meta(
						self::META_COUPON_TYPE,
						true
					)
			);

		/*
		 * Legacy/native WooCommerce fallback.
		 *
		 * WooCommerce coupon order items already contain the
		 * applied code. This also allows older Checkout Flow
		 * orders to show useful coupon information when possible.
		 */
		$woocommerce_coupon_found =
			false;

		foreach (
			$order->get_items(
				'coupon'
			) as $coupon_item
		) {
			if ( ! is_object( $coupon_item ) ) {
				continue;
			}

			$item_code =
				method_exists(
					$coupon_item,
					'get_code'
				)
					? sanitize_text_field(
						(string) $coupon_item
							->get_code()
					)
					: '';

			if ( '' === $item_code ) {
				continue;
			}

			if ( '' === $code ) {
				$code =
					$item_code;
			}

			if (
				strtolower( $code ) !==
					strtolower( $item_code )
			) {
				continue;
			}

			$woocommerce_coupon_found =
				true;

			if (
				$discount <= 0 &&
				method_exists(
					$coupon_item,
					'get_discount'
				)
			) {
				$discount =
					max(
						0.0,
						(float) $coupon_item
							->get_discount()
					);
			}

			break;
		}

		if (
			'' === $source &&
			$woocommerce_coupon_found
		) {
			$source =
				'woocommerce';
		}

		$applied =
			'' !== $code &&
			(
				$discount > 0 ||
				$free_delivery
			);

		if ( ! $applied ) {
			return array(
				'applied' =>
					false,
			);
		}

		if ( '' === $source ) {
			$source =
				'eilmo';
		}

		return array(
			'applied' =>
				true,

			'code' =>
				$code,

			'source' =>
				$source,

			'source_label' =>
				$this->get_coupon_source_label(
					$source
				),

			'type' =>
				$type,

			'discount' =>
				$discount,

			'free_delivery' =>
				$free_delivery,
		);
	}

	/**
	 * Get coupon source label.
	 *
	 * Internal source keys stay unchanged for backward
	 * compatibility. Only the visible label uses the plugin
	 * product name.
	 *
	 * @param string $source Source.
	 *
	 * @return string
	 */
	private function get_coupon_source_label(
		string $source
	): string {

		$source =
			sanitize_key(
				$source
			);

		if ( 'woocommerce' === $source ) {
			return __(
				'WooCommerce Coupon',
				'eilmo-checkout-flow'
			);
		}

		if ( 'eilmo' === $source ) {
			return __(
				'CheckoutFlow Coupon',
				'eilmo-checkout-flow'
			);
		}

		return $this->format_key_label(
			$source
		);
	}

	/**
	 * Get payment source label.
	 *
	 * @param string $source Source.
	 *
	 * @return string
	 */
	private function get_payment_source_label(
		string $source
	): string {

		if (
			'woocommerce_gateway' ===
				$source
		) {
			return __(
				'WooCommerce Gateway',
				'eilmo-checkout-flow'
			);
		}

		if (
			'eilmo_manual' ===
				$source
		) {
			return __(
				'Manual Payment',
				'eilmo-checkout-flow'
			);
		}

		return $this->format_key_label(
			$source
		);
	}

	/**
	 * Format machine key as human label.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function format_key_label(
		string $value
	): string {

		$value =
			str_replace(
				array(
					'_',
					'-',
				),
				' ',
				$value
			);

		return ucwords(
			trim(
				$value
			)
		);
	}

	/**
	 * Get monetary order meta.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $meta_key Meta key.
	 *
	 * @return float
	 */
	private function get_amount(
		WC_Order $order,
		string $meta_key
	): float {

		$value =
			$order->get_meta(
				$meta_key,
				true
			);

		if (
			! is_numeric(
				$value
			)
		) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $value
		);
	}

	/**
	 * Format order currency amount.
	 *
	 * @param float    $amount Amount.
	 * @param WC_Order $order  Order.
	 *
	 * @return string
	 */
	private function format_price(
		float $amount,
		WC_Order $order
	): string {

		if (
			! function_exists(
				'wc_price'
			)
		) {
			return esc_html(
				number_format_i18n(
					$amount,
					2
				)
			);
		}

		return wp_kses_post(
			wc_price(
				$amount,
				array(
					'currency' =>
						$order->get_currency(),
				)
			)
		);
	}
}
