<?php
/**
 * Checkout settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Checkout settings.
 *
 * Checkout configuration is stored in the main WordPress option. Product
 * Display, Product Gallery and Security are owned by their dedicated settings
 * classes and are intentionally not registered or sanitized here.
 *
 * This class handles:
 * - Settings registration.
 * - Default main-settings schema.
 * - General non-security feature settings.
 * - Combo Offer library sanitization.
 * - Delivery method sanitization.
 * - Payment Option sanitization.
 * - Advance payment rule sanitization.
 * - Advance payment frontend text sanitization.
 * - Automatic discount notification sanitization.
 * - Full-payment discount frontend text sanitization.
 * - Discount rule sanitization.
 * - Coupon sanitization.
 *
 * Rendering of the admin settings page is handled separately.
 */
final class CheckoutSettings implements RegistrableInterface {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'eilmo_cf_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'eilmo_cf_settings_group';

	/**
	 * Settings schema version.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 52;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_init',
			array(
				$this,
				'register_settings',
			)
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		$this->maybe_migrate_settings();

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __(
					'Checkout Flow settings.',
					'eilmo-checkout-flow'
				),
				'sanitize_callback' => array(
					$this,
					'sanitize',
				),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Migrate presentation defaults without creating duplicate settings.
	 *
	 * Schema 21 moves the historical Special Offer / Order Bump default
	 * from Before Payment to Before Delivery. Schema 22 centralizes
	 * WhatsApp placement with the checkout layout controls so Easy Setup
	 * presets can control all checkout action placement without duplicate
	 * admin fields.
	 *
	 * @return void
	 */
	private function maybe_migrate_settings(): void {

		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return;
		}

		$schema_version = absint( $stored['schema_version'] ?? 0 );

		if ( $schema_version >= self::SCHEMA_VERSION ) {
			return;
		}

		if ( $schema_version < 21 ) {
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}

			$current_position = sanitize_key( (string) ( $stored['order_bumps']['position'] ?? 'before_payment' ) );

			if ( '' === $current_position || 'before_payment' === $current_position ) {
				$stored['order_bumps']['position'] = 'before_delivery';
			}
		}

		if ( $schema_version < 22 ) {
			if ( ! isset( $stored['checkout_display'] ) || ! is_array( $stored['checkout_display'] ) ) {
				$stored['checkout_display'] = array();
			}
			if ( ! isset( $stored['checkout_display']['layout'] ) || ! is_array( $stored['checkout_display']['layout'] ) ) {
				$stored['checkout_display']['layout'] = array();
			}

			if ( ! array_key_exists( 'whatsapp_placement', $stored['checkout_display']['layout'] ) ) {
				$legacy_whatsapp = get_option( WhatsAppSettings::OPTION_NAME, array() );
				$legacy_placement = is_array( $legacy_whatsapp )
					? sanitize_key( (string) ( $legacy_whatsapp['placement'] ?? 'below_summary' ) )
					: 'below_summary';

				if ( ! in_array( $legacy_placement, array( 'below_payment', 'below_summary', 'both' ), true ) ) {
					$legacy_placement = 'below_summary';
				}

				$stored['checkout_display']['layout']['whatsapp_placement'] = $legacy_placement;
			}
		}

		if ( $schema_version < 24 ) {
			/*
			 * An intermediate Product Settings build temporarily saved Product
			 * Display and Product Gallery in a separate option. Restore those
			 * values to their historical eilmo_cf_settings paths so existing
			 * names, runtime readers and admin JavaScript remain compatible.
			 */
			$separated_products = get_option(
				'eilmo_cf_product_settings',
				array()
			);

			if ( is_array( $separated_products ) && ! empty( $separated_products ) ) {
				if (
					isset( $separated_products['general']['product_gallery'] )
				) {
					if ( ! isset( $stored['general'] ) || ! is_array( $stored['general'] ) ) {
						$stored['general'] = array();
					}

					$stored['general']['product_gallery'] =
						$separated_products['general']['product_gallery'];
				}

				if (
					isset( $separated_products['checkout_display']['products'] ) &&
					is_array( $separated_products['checkout_display']['products'] )
				) {
					if ( ! isset( $stored['checkout_display'] ) || ! is_array( $stored['checkout_display'] ) ) {
						$stored['checkout_display'] = array();
					}

					$stored['checkout_display']['products'] =
						$separated_products['checkout_display']['products'];
				}

				if (
					isset( $separated_products['product_gallery'] ) &&
					is_array( $separated_products['product_gallery'] )
				) {
					$stored['product_gallery'] =
						$separated_products['product_gallery'];
				}
			}
		}

		if ( $schema_version < 29 ) {
			/*
			 * The original Advance option used a sentence-length heading which
			 * made the three-card Payment Option row unnecessarily tall. Only
			 * migrate exact historical defaults; merchant-authored text remains
			 * untouched.
			 */
			if ( ! isset( $stored['advance_payment'] ) || ! is_array( $stored['advance_payment'] ) ) {
				$stored['advance_payment'] = array();
			}

			$legacy_advance_label = sanitize_text_field(
				(string) ( $stored['advance_payment']['advance_label'] ?? '' )
			);

			if ( 'Pay {pay_now} Advance & Confirm Order' === $legacy_advance_label ) {
				$stored['advance_payment']['advance_label'] = 'Advance Payment';
			}

			if (
				isset( $stored['discounts']['full_payment'] ) &&
				is_array( $stored['discounts']['full_payment'] ) &&
				'Pay Full Amount' === sanitize_text_field(
					(string) ( $stored['discounts']['full_payment']['label'] ?? '' )
				)
			) {
				$stored['discounts']['full_payment']['label'] = 'Full Payment';
			}
		}

		if ( $schema_version < 31 ) {
			/*
			 * Highlighted Combo Offer cards are designed as a full-width
			 * promotional banner. Version 1.9.4 temporarily introduced the
			 * preset with the legacy two-column default, so migrate that exact
			 * combination once while leaving future layout choices untouched.
			 */
			if ( ! isset( $stored['combo_offers'] ) || ! is_array( $stored['combo_offers'] ) ) {
				$stored['combo_offers'] = array();
			}

			$combo_layout = sanitize_key(
				(string) ( $stored['combo_offers']['card_layout'] ?? 'two_columns' )
			);
			$combo_style = sanitize_key(
				(string) ( $stored['combo_offers']['card_style'] ?? 'highlighted' )
			);

			if ( 'highlighted' === $combo_style && 'two_columns' === $combo_layout ) {
				$stored['combo_offers']['card_layout'] = 'full_width';
			}

			if (
				isset( $stored['combo_offers']['style'] ) &&
				is_array( $stored['combo_offers']['style'] ) &&
				'#fbbf24' === strtolower(
					(string) ( $stored['combo_offers']['style']['selected_border'] ?? '' )
				)
			) {
				$stored['combo_offers']['style']['selected_border'] = '#a78bfa';
			}
		}

		if ( $schema_version < 32 ) {
			/*
			 * Special Offers use the historical order_bumps storage key.
			 * Add only missing presentation defaults; existing content,
			 * product IDs and merchant choices remain untouched.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}

			if ( ! array_key_exists( 'card_style', $stored['order_bumps'] ) ) {
				$stored['order_bumps']['card_style'] = 'promo_banner';
			}

			if ( ! array_key_exists( 'columns_desktop', $stored['order_bumps'] ) ) {
				$stored['order_bumps']['columns_desktop'] = 1;
			}
		}

		if ( $schema_version < 33 ) {
			/*
			 * Move only untouched Special Offer defaults to the warmer reference
			 * palette. Merchant-authored colours remain unchanged, while newly
			 * introduced border, timer-label and decoration tokens are filled in.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}

			if ( ! isset( $stored['order_bumps']['style'] ) || ! is_array( $stored['order_bumps']['style'] ) ) {
				$stored['order_bumps']['style'] = array();
			}

			$legacy_special_defaults = array(
				'gradient_start'   => array( '#fff8f0', '#fffdf8' ),
				'gradient_end'     => array( '#fff0f6', '#fff6f1' ),
				'accent_end'       => array( '#ec4899', '#8b5cf6' ),
				'title'            => array( '#201a2d', '#111827' ),
				'text'             => array( '#6b6475', '#64748b' ),
				'badge_end'        => array( '#ec4899', '#8b5cf6' ),
				'timer_background' => array( '#ffffff', '#fff0ed' ),
				'timer_text'       => array( '#201a2d', '#111827' ),
			);

			foreach ( $legacy_special_defaults as $key => $palette ) {
				$current = strtolower( (string) ( $stored['order_bumps']['style'][ $key ] ?? '' ) );
				if ( '' === $current || $palette[0] === $current ) {
					$stored['order_bumps']['style'][ $key ] = $palette[1];
				}
			}

			$new_special_defaults = array(
				'card_border'         => '#f1e4da',
				'label_color'         => '#f43f5e',
				'timer_label'         => '#6b7280',
				'timer_border'        => '#f7d9d2',
				'decoration_primary'  => '#f43f5e',
				'decoration_secondary' => '#7c3aed',
			);

			foreach ( $new_special_defaults as $key => $value ) {
				if ( ! array_key_exists( $key, $stored['order_bumps']['style'] ) ) {
					$stored['order_bumps']['style'][ $key ] = $value;
				}
			}
		}

		if ( $schema_version < 34 ) {
			/*
			 * Merge the two historical merchant-facing systems into one
			 * Special Discounts rule library. The old storage keys remain as
			 * runtime adapters, while existing content and rule IDs are kept.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}

			if ( ! isset( $stored['order_bumps']['offers'] ) || ! is_array( $stored['order_bumps']['offers'] ) ) {
				$stored['order_bumps']['offers'] = array();
			}

			$used_rule_ids = array();
			foreach ( $stored['order_bumps']['offers'] as &$legacy_offer ) {
				if ( ! is_array( $legacy_offer ) ) {
					continue;
				}

				$legacy_id = sanitize_key( (string) ( $legacy_offer['id'] ?? '' ) );
				if ( '' !== $legacy_id ) {
					$used_rule_ids[] = $legacy_id;
				}

				if ( ! isset( $legacy_offer['condition_type'] ) ) {
					$legacy_offer['condition_type'] = 'always';
				}
				if ( ! isset( $legacy_offer['apply_behavior'] ) ) {
					$legacy_offer['apply_behavior'] = 'customer_selectable';
				}
				if ( ! isset( $legacy_offer['ineligible_action'] ) ) {
					$legacy_offer['ineligible_action'] = 'show_locked';
				}

				$legacy_type = sanitize_key( (string) ( $legacy_offer['offer_type'] ?? '' ) );
				if ( in_array( $legacy_type, array( 'single_product', 'gift_box', 'buy_one_get_one' ), true ) ) {
					/*
					 * Paid standalone offers cannot be inferred safely as a
					 * conditional reward. Keep the product/card data, convert it
					 * to the supported gift shape, and disable it until the merchant
					 * confirms the qualifying condition.
					 */
					$legacy_offer['offer_type'] = 'free_gift';
					$legacy_offer['enabled'] = 'no';
					$legacy_offer['condition_type'] = 'specific_product';
					$legacy_offer['condition_product_id'] = absint( $legacy_offer['product_id'] ?? 0 );
					$legacy_offer['condition_product_quantity'] = 1;
					$legacy_offer['pricing_type'] = 'fixed_price';
					$legacy_offer['pricing_value'] = 0;
				}
			}
			unset( $legacy_offer );

			$legacy_discount_rules = isset( $stored['discounts']['automatic_rules'] ) && is_array( $stored['discounts']['automatic_rules'] )
				? $stored['discounts']['automatic_rules']
				: array();

			foreach ( $legacy_discount_rules as $index => $legacy_rule ) {
				if ( ! is_array( $legacy_rule ) ) {
					continue;
				}

				$rule_id = sanitize_key( (string) ( $legacy_rule['id'] ?? 'discount-rule-' . ( (int) $index + 1 ) ) );
				$base_id = '' !== $rule_id ? $rule_id : 'discount-rule-' . ( (int) $index + 1 );
				$suffix = 2;
				while ( in_array( $rule_id, $used_rule_ids, true ) ) {
					$rule_id = $base_id . '-' . $suffix;
					++$suffix;
				}
				$used_rule_ids[] = $rule_id;

				$legacy_type = sanitize_key( (string) ( $legacy_rule['type'] ?? 'percentage' ) );
				$reward_type = 'fixed' === $legacy_type
					? 'fixed_discount'
					: ( 'free_delivery' === $legacy_type ? 'free_delivery' : 'percentage_discount' );

				$stored['order_bumps']['offers'][] = array(
					'id'                         => $rule_id,
					'enabled'                    => 'no' === ( $legacy_rule['enabled'] ?? 'yes' ) ? 'no' : 'yes',
					'title'                      => sanitize_text_field( (string) ( $legacy_rule['name'] ?? 'Special Discount' ) ),
					'offer_type'                 => $reward_type,
					'condition_type'             => 'order_amount',
					'condition_minimum'          => (float) ( $legacy_rule['minimum'] ?? $legacy_rule['minimum_amount'] ?? 0 ),
					'condition_maximum'          => (float) ( $legacy_rule['maximum'] ?? $legacy_rule['maximum_amount'] ?? 0 ),
					'condition_product_id'       => 0,
					'condition_product_quantity' => 1,
					'condition_cart_quantity'    => 1,
					'apply_behavior'             => 'auto_apply',
					'ineligible_action'          => 'show_locked',
					'locked_text'                => 'Add {remaining_amount} more to unlock this reward.',
					'label'                      => 'SPECIAL DISCOUNT',
					'badge'                      => '',
					'pricing_type'               => 'percentage_discount' === $reward_type ? 'percentage_discount' : ( 'fixed_discount' === $reward_type ? 'fixed_discount' : 'regular_price' ),
					'pricing_value'              => (float) ( $legacy_rule['value'] ?? 0 ),
					'maximum_discount'           => (float) ( $legacy_rule['maximum_discount'] ?? 0 ),
					'product_id'                 => 0,
					'variation_id'               => 0,
					'quantity'                   => 0,
					'start_at'                   => '',
					'end_at'                     => '',
					'timer_enabled'              => 'no',
					'image_source'               => 'hidden',
					'image_media_id'             => 0,
					'description'                => '',
					'button_text'                => 'Apply Discount',
					'applied_text'               => 'Applied',
					'sort_order'                 => absint( $legacy_rule['priority'] ?? 10 ),
					'stop_processing'            => 'no' === ( $legacy_rule['stop_processing'] ?? 'yes' ) ? 'no' : 'yes',
				);
			}

			$stored['order_bumps']['title'] = sanitize_text_field( (string) ( $stored['order_bumps']['title'] ?? 'Special Discounts' ) );
			if ( 'Special Offer' === $stored['order_bumps']['title'] || '' === $stored['order_bumps']['title'] ) {
				$stored['order_bumps']['title'] = 'Special Discounts';
			}
			$stored['order_bumps']['enabled'] = (
				'yes' === ( $stored['discounts']['enabled'] ?? 'no' ) ||
				'yes' === ( $stored['order_bumps']['enabled'] ?? 'no' )
			) ? 'yes' : 'no';
			if ( ! isset( $stored['discounts'] ) || ! is_array( $stored['discounts'] ) ) {
				$stored['discounts'] = array();
			}
			$stored['discounts']['enabled'] = $stored['order_bumps']['enabled'];
		}

		if ( $schema_version < 35 ) {
			/*
			 * Reward and Compact presets were initially rendered as four-card
			 * previews while their saved frontend column value was still clamped
			 * to two. Promote only those presets to the intended four-column
			 * default; Promo Grid keeps the merchant's existing 1–2 choice.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}

			$special_card_style = sanitize_key(
				(string) ( $stored['order_bumps']['card_style'] ?? 'promo_banner' )
			);
			if ( in_array( $special_card_style, array( 'reward_tiles', 'compact_strip' ), true ) ) {
				$stored['order_bumps']['columns_desktop'] = 4;
			}
		}

		if ( $schema_version < 36 ) {
			/*
			 * Automatic cart rewards never need a merchant-facing Apply Behavior
			 * selector. Normalize them once and populate the real progress-text
			 * value while preserving every non-empty merchant customization.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['offers'] ) || ! is_array( $stored['order_bumps']['offers'] ) ) {
				$stored['order_bumps']['offers'] = array();
			}

			foreach ( $stored['order_bumps']['offers'] as &$special_discount ) {
				if ( ! is_array( $special_discount ) ) {
					continue;
				}
				$reward_type = sanitize_key( (string) ( $special_discount['offer_type'] ?? '' ) );
				if ( in_array( $reward_type, array( 'percentage_discount', 'fixed_discount', 'free_delivery' ), true ) ) {
					$special_discount['apply_behavior'] = 'auto_apply';
				}
				if ( '' === trim( sanitize_text_field( (string) ( $special_discount['locked_text'] ?? '' ) ) ) ) {
					$special_discount['locked_text'] = 'Add {remaining_amount} more to unlock this reward.';
				}
			}
			unset( $special_discount );
		}

		if ( $schema_version < 37 ) {
			/*
			 * Promo supports two columns; Reward and Compact support three.
			 * Version 1.9.16 briefly exposed a fourth column which made the
			 * real checkout cards too narrow, so clamp only that saved value.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			$stored['order_bumps']['columns_desktop'] = max(
				1,
				min( 3, absint( $stored['order_bumps']['columns_desktop'] ?? 1 ) )
			);
		}

		if ( $schema_version < 38 ) {
			/*
			 * Restore Special Discounts to the checkout-wide purple visual
			 * language. Only exact historical defaults are migrated so any
			 * merchant-authored colours remain untouched. Also give the
			 * customer-selectable state its own text without breaking the
			 * historical button_text storage key.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['style'] ) || ! is_array( $stored['order_bumps']['style'] ) ) {
				$stored['order_bumps']['style'] = array();
			}

			$purple_special_defaults = array(
				'gradient_start'       => array( '#fffdf8', '#f5f3ff' ),
				'gradient_end'         => array( '#fff6f1', '#ede9fe' ),
				'card_border'          => array( '#f1e4da', '#c4b5fd' ),
				'label_color'          => array( '#f43f5e', '#7c3aed' ),
				'timer_background'     => array( '#fff0ed', '#f5f3ff' ),
				'timer_border'         => array( '#f7d9d2', '#ddd6fe' ),
				'image_background'     => array( '#ffffff', '#f5f3ff' ),
				'decoration_primary'   => array( '#f43f5e', '#7c3aed' ),
				'decoration_secondary' => array( '#7c3aed', '#8b5cf6' ),
			);

			foreach ( $purple_special_defaults as $key => $palette ) {
				$current = strtolower( (string) ( $stored['order_bumps']['style'][ $key ] ?? '' ) );
				if ( '' === $current || $palette[0] === $current ) {
					$stored['order_bumps']['style'][ $key ] = $palette[1];
				}
			}

			if ( isset( $stored['order_bumps']['offers'] ) && is_array( $stored['order_bumps']['offers'] ) ) {
				foreach ( $stored['order_bumps']['offers'] as &$special_discount ) {
					if ( ! is_array( $special_discount ) ) {
						continue;
					}
					$before_apply_text = sanitize_text_field(
						(string) ( $special_discount['before_apply_text'] ?? $special_discount['button_text'] ?? 'Add Reward' )
					);
					$special_discount['before_apply_text'] = '' !== $before_apply_text ? $before_apply_text : 'Add Reward';
					$special_discount['button_text'] = $special_discount['before_apply_text'];
				}
				unset( $special_discount );
			}
		}

		if ( $schema_version < 39 ) {
			/*
			 * Normal progress and icon boxes should be visually clean until a
			 * merchant deliberately styles them. Migrate only the exact purple
			 * defaults introduced by schema 38; customized surfaces are kept.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['style'] ) || ! is_array( $stored['order_bumps']['style'] ) ) {
				$stored['order_bumps']['style'] = array();
			}

			$special_style = &$stored['order_bumps']['style'];
			if (
				'solid' === sanitize_key( (string) ( $special_style['progress_background_type'] ?? 'solid' ) ) &&
				'#f5f3ff' === strtolower( (string) ( $special_style['progress_background'] ?? '#f5f3ff' ) ) &&
				'#ede9fe' === strtolower( (string) ( $special_style['progress_background_end'] ?? '#ede9fe' ) )
			) {
				$special_style['progress_background_type'] = 'transparent';
			}
			if ( ! array_key_exists( 'promo_icon_background_type', $special_style ) ) {
				$special_style['promo_icon_background_type'] = 'transparent';
			}
			unset( $special_style );
		}

		if ( $schema_version < 40 ) {
			/*
			 * Make the selected icon state immediately recognizable while keeping
			 * every merchant-authored icon colour and surface untouched.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['style'] ) || ! is_array( $stored['order_bumps']['style'] ) ) {
				$stored['order_bumps']['style'] = array();
			}

			$special_style = &$stored['order_bumps']['style'];
			if (
				'solid' === sanitize_key( (string) ( $special_style['selected_icon_background_type'] ?? 'solid' ) ) &&
				'#ede9fe' === strtolower( (string) ( $special_style['selected_icon_background'] ?? '#ede9fe' ) ) &&
				'#ddd6fe' === strtolower( (string) ( $special_style['selected_icon_background_end'] ?? '#ddd6fe' ) ) &&
				'#7c3aed' === strtolower( (string) ( $special_style['selected_icon_color'] ?? '#7c3aed' ) )
			) {
				$special_style['selected_icon_background_type'] = 'gradient';
				$special_style['selected_icon_background'] = '#7c3aed';
				$special_style['selected_icon_background_end'] = '#8b5cf6';
				$special_style['selected_icon_color'] = '#ffffff';
			}
			unset( $special_style );
		}

		if ( $schema_version < 41 ) {
			/*
			 * Schema 40 introduced a purple/white selected icon default while the
			 * unified card design keeps artwork consistent across card states.
			 * Migrate only that exact generated default and preserve custom values.
			 */
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['style'] ) || ! is_array( $stored['order_bumps']['style'] ) ) {
				$stored['order_bumps']['style'] = array();
			}

			$special_style = &$stored['order_bumps']['style'];
			if (
				'gradient' === sanitize_key( (string) ( $special_style['selected_icon_background_type'] ?? 'gradient' ) ) &&
				'#7c3aed' === strtolower( (string) ( $special_style['selected_icon_background'] ?? '#7c3aed' ) ) &&
				'#8b5cf6' === strtolower( (string) ( $special_style['selected_icon_background_end'] ?? '#8b5cf6' ) ) &&
				'#ffffff' === strtolower( (string) ( $special_style['selected_icon_color'] ?? '#ffffff' ) )
			) {
				$special_style['selected_icon_background_type'] = 'solid';
				$special_style['selected_icon_background'] = '#f5f3ff';
				$special_style['selected_icon_background_end'] = '#ede9fe';
				$special_style['selected_icon_color'] = '#7c3aed';
			}
			unset( $special_style );
		}

		if ( $schema_version < 42 ) {
			/*
			 * Product Style historically supplied the complete checkout theme.
			 * Move those merchant values to their real owner once, then remove the
			 * duplicate key so Products contains product behaviour only.
			 */
			$legacy_style = isset( $stored['product_style'] ) && is_array( $stored['product_style'] )
				? $stored['product_style']
				: array();

			if ( ! isset( $stored['checkout_style'] ) || ! is_array( $stored['checkout_style'] ) ) {
				$stored['checkout_style'] = CheckoutStyle::sanitize(
					$legacy_style,
					CheckoutStyle::get_defaults()
				);
			}

			unset( $stored['product_style'] );
		}

		if ( $schema_version < 43 ) {
			/* Add the new presets once, without changing or duplicating saved methods. */
			if ( ! isset( $stored['payment_methods'] ) || ! is_array( $stored['payment_methods'] ) ) {
				$stored['payment_methods'] = array();
			}
			if ( ! isset( $stored['payment_methods']['custom_methods'] ) || ! is_array( $stored['payment_methods']['custom_methods'] ) ) {
				$stored['payment_methods']['custom_methods'] = array();
			}

			$default_settings = self::get_defaults();
			$presets = $default_settings['payment_methods']['custom_methods'] ?? array();
			foreach ( $presets as $preset ) {
				$preset_id = is_array( $preset ) ? sanitize_key( (string) ( $preset['id'] ?? '' ) ) : '';
				$matched_index = null;
				foreach ( $stored['payment_methods']['custom_methods'] as $method_index => $saved_method ) {
					if ( is_array( $saved_method ) && $preset_id === sanitize_key( (string) ( $saved_method['id'] ?? '' ) ) ) {
						$matched_index = $method_index;
						break;
					}
				}

				if ( null !== $matched_index ) {
					$stored['payment_methods']['custom_methods'][ $matched_index ] = array_replace(
						$preset,
						$stored['payment_methods']['custom_methods'][ $matched_index ]
					);
				} elseif ( '' !== $preset_id ) {
					$stored['payment_methods']['custom_methods'][] = $preset;
				}
			}
		}

		if ( $schema_version < 45 ) {
			/*
			 * Payment option badges now have their own shared theme tokens.
			 * Advance and Full Payment intentionally use the same values.
			 */
			if ( ! isset( $stored['checkout_style'] ) || ! is_array( $stored['checkout_style'] ) ) {
				$stored['checkout_style'] = array();
			}

			if ( ! array_key_exists( 'payment_badge_background', $stored['checkout_style'] ) ) {
				$stored['checkout_style']['payment_badge_background'] = '#7c3aed';
			}

			if ( ! array_key_exists( 'payment_badge_text', $stored['checkout_style'] ) ) {
				$stored['checkout_style']['payment_badge_text'] = '#ffffff';
			}
		}


		/*
		 * Schema 46: Special Discounts become one scoped CONDITION -> REWARD
		 * library. Existing offers/settings are preserved; only missing runtime
		 * fields are added and supported rewards become auto-applied.
		 */
		if ( $schema_version < 46 ) {
			if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
				$stored['order_bumps'] = array();
			}
			if ( ! isset( $stored['order_bumps']['offers'] ) || ! is_array( $stored['order_bumps']['offers'] ) ) {
				$stored['order_bumps']['offers'] = array();
			}
			foreach ( $stored['order_bumps']['offers'] as &$rule ) {
				if ( ! is_array( $rule ) ) { continue; }
				if ( ! isset( $rule['rule_scope'] ) ) { $rule['rule_scope'] = 'whole_cart'; }
				if ( ! isset( $rule['scope_product_ids'] ) || ! is_array( $rule['scope_product_ids'] ) ) { $rule['scope_product_ids'] = array(); }
				if ( ! isset( $rule['free_delivery_method_id'] ) ) { $rule['free_delivery_method_id'] = ''; }
				if ( ! isset( $rule['free_delivery_hide_other_methods'] ) ) { $rule['free_delivery_hide_other_methods'] = 'no'; }
				if ( in_array( sanitize_key( (string) ( $rule['offer_type'] ?? '' ) ), array( 'percentage_discount', 'fixed_discount', 'free_delivery', 'free_gift' ), true ) ) {
					$rule['apply_behavior'] = 'auto_apply';
				}
			}
			unset( $rule );
		}


		/*
		 * Schema 47: Free Delivery is owned only by Special Discounts.
		 * Migrate the historical Delivery > Free Delivery configuration into
		 * one canonical Special Discount rule, then remove the legacy branch.
		 */
		if ( $schema_version < 47 ) {
			$legacy_delivery = isset( $stored['delivery'] ) && is_array( $stored['delivery'] )
				? $stored['delivery']
				: array();
			$legacy_free = isset( $legacy_delivery['free_delivery'] ) && is_array( $legacy_delivery['free_delivery'] )
				? $legacy_delivery['free_delivery']
				: array();

			if ( 'yes' === (string) ( $legacy_free['enabled'] ?? 'no' ) ) {
				if ( ! isset( $stored['order_bumps'] ) || ! is_array( $stored['order_bumps'] ) ) {
					$stored['order_bumps'] = array();
				}
				if ( ! isset( $stored['order_bumps']['offers'] ) || ! is_array( $stored['order_bumps']['offers'] ) ) {
					$stored['order_bumps']['offers'] = array();
				}

				$minimum = max( 0.0, (float) ( $legacy_free['minimum_amount'] ?? 0 ) );
				$method_id = sanitize_key( (string) ( $legacy_free['method_id'] ?? '' ) );
				$hide_other = 'yes' === (string) ( $legacy_free['hide_other_methods'] ?? 'no' ) ? 'yes' : 'no';
				$equivalent_exists = false;
				$used_ids = array();

				foreach ( $stored['order_bumps']['offers'] as $existing_rule ) {
					if ( ! is_array( $existing_rule ) ) {
						continue;
					}
					$existing_id = sanitize_key( (string) ( $existing_rule['id'] ?? '' ) );
					if ( '' !== $existing_id ) {
						$used_ids[] = $existing_id;
					}
					if (
						'free_delivery' === sanitize_key( (string) ( $existing_rule['offer_type'] ?? '' ) ) &&
						abs( (float) ( $existing_rule['condition_minimum'] ?? 0 ) - $minimum ) < 0.0001 &&
						sanitize_key( (string) ( $existing_rule['free_delivery_method_id'] ?? '' ) ) === $method_id &&
						( 'yes' === (string) ( $existing_rule['free_delivery_hide_other_methods'] ?? 'no' ) ? 'yes' : 'no' ) === $hide_other
					) {
						$equivalent_exists = true;
					}
				}

				if ( ! $equivalent_exists ) {
					$rule_id = 'migrated-free-delivery';
					$suffix = 2;
					while ( in_array( $rule_id, $used_ids, true ) ) {
						$rule_id = 'migrated-free-delivery-' . $suffix;
						++$suffix;
					}

					$stored['order_bumps']['offers'][] = array(
						'id'                              => $rule_id,
						'enabled'                         => 'yes',
						'title'                           => __( 'Free Delivery', 'eilmo-checkout-flow' ),
						'offer_type'                      => 'free_delivery',
						'rule_scope'                      => 'whole_cart',
						'scope_product_ids'               => array(),
						'condition_type'                  => 'minimum_spend',
						'condition_minimum'               => $minimum,
						'condition_maximum'               => 0.0,
						'condition_product_id'            => 0,
						'condition_product_quantity'      => 1,
						'condition_cart_quantity'         => 1,
						'apply_behavior'                  => 'auto_apply',
						'ineligible_action'               => 'hide',
						'locked_text'                     => 'Add {remaining_amount} more to unlock this reward.',
						'pricing_type'                    => 'regular_price',
						'pricing_value'                   => 0.0,
						'maximum_discount'                => 0.0,
						'product_id'                      => 0,
						'variation_id'                    => 0,
						'quantity'                        => 0.0,
						'free_delivery_method_id'         => $method_id,
						'free_delivery_hide_other_methods'=> $hide_other,
						'start_at'                        => '',
						'end_at'                          => '',
						'sort_order'                      => 10,
						'stop_processing'                 => 'no',
					);
				}

				$stored['order_bumps']['enabled'] = 'yes';
			}

			if ( isset( $stored['delivery'] ) && is_array( $stored['delivery'] ) ) {
				unset( $stored['delivery']['free_delivery'] );
			}
		}

		/*
		 * Schema 48: the historical Premium Purple preset accidentally shipped
		 * with a green palette. Migrate only the exact untouched colour defaults;
		 * merchant-customized palettes remain unchanged.
		 */
		if ( $schema_version < 48 ) {
			$saved_style = isset( $stored['checkout_style'] ) && is_array( $stored['checkout_style'] )
				? $stored['checkout_style']
				: array();
			$old_green = array(
				'primary'                    => '#274c3d',
				'primary_hover'              => '#355f4e',
				'primary_soft'               => '#f3f7f0',
				'secondary'                  => '#355f4e',
				'text'                       => '#17392f',
				'muted_text'                 => '#738078',
				'background'                 => '#fffdfa',
				'muted_background'           => '#f7f4ec',
				'soft_background'            => '#e9efe2',
				'border'                     => '#dbe2d7',
				'border_strong'              => '#274c3d',
				'badge_background'           => '#274c3d',
				'badge_text'                 => '#ffffff',
				'payment_badge_background'   => '#7c3aed',
				'payment_badge_text'         => '#ffffff',
				'button_text'                => '#ffffff',
				'gradient_enabled'           => 'no',
				'gradient_end'               => '#355f4e',
			);
			$untouched = ! empty( $saved_style ) && 'premium_purple' === ( $saved_style['preset'] ?? 'premium_purple' );

			foreach ( $old_green as $key => $old_value ) {
				if ( ! array_key_exists( $key, $saved_style ) || strtolower( (string) $saved_style[ $key ] ) !== strtolower( (string) $old_value ) ) {
					$untouched = false;
					break;
				}
			}

			if ( $untouched ) {
				$purple = CheckoutStyle::get_defaults();
				foreach ( array_keys( $old_green ) as $key ) {
					$stored['checkout_style'][ $key ] = $purple[ $key ];
				}
			}
		}


		/*
		 * Schema 49: make the named Default Theme authoritative. Some installs
		 * reached schema 48 with the historical green values still stored under
		 * the premium_purple preset, so the exact-value migration could no longer
		 * repair them.
		 */
		if ( $schema_version < 49 ) {
			$saved_style = isset( $stored['checkout_style'] ) && is_array( $stored['checkout_style'] )
				? $stored['checkout_style']
				: array();

			$stored['checkout_style'] = CheckoutStyle::normalize_global_style( $saved_style );
		}

		/*
		 * Schema 50: repair installations where the historical green default was
		 * accidentally saved with preset=custom. The normalizer now recognizes the
		 * complete legacy palette signature before preserving genuine Custom themes.
		 * This migration is deliberately exact-signature based so merchant palettes
		 * that merely use green are left untouched.
		 */
		if ( $schema_version < 50 ) {
			$saved_style = isset( $stored['checkout_style'] ) && is_array( $stored['checkout_style'] )
				? $stored['checkout_style']
				: array();

			$stored['checkout_style'] = CheckoutStyle::normalize_global_style( $saved_style );
		}

		/*
		 * Schema 51: delivery-method names/descriptions are merchant content, not
		 * plugin copy. Add an optional Bangla value beside the existing English
		 * value without guessing translations for arbitrary custom methods. The
		 * two historical Dhaka labels can be seeded safely because they are known
		 * plugin-era defaults. Empty Bangla values intentionally fall back to the
		 * original English/custom value at render time.
		 */
		if ( $schema_version < 51 && isset( $stored['delivery']['methods'] ) && is_array( $stored['delivery']['methods'] ) ) {
			$known_delivery_bn = array(
				'Inside Dhaka'  => 'ঢাকার ভেতরে',
				'Outside Dhaka' => 'ঢাকার বাইরে',
			);

			foreach ( $stored['delivery']['methods'] as &$method ) {
				if ( ! is_array( $method ) ) {
					continue;
				}

				$label = sanitize_text_field( (string) ( $method['label'] ?? '' ) );
				if ( ! array_key_exists( 'label_bn', $method ) ) {
					if ( isset( $known_delivery_bn[ $label ] ) ) {
						$method['label_bn'] = $known_delivery_bn[ $label ];
					} elseif ( preg_match( '/[\x{0980}-\x{09FF}]/u', $label ) ) {
						$method['label_bn'] = $label;
					} else {
						$method['label_bn'] = '';
					}
				}

				if ( ! array_key_exists( 'description_bn', $method ) ) {
					$description = (string) ( $method['description'] ?? '' );
					$method['description_bn'] = preg_match( '/[\x{0980}-\x{09FF}]/u', $description ) ? $description : '';
				}
			}
			unset( $method );
		}


		/*
		 * Schema 52: tighten the English payment-option microcopy so all three
		 * cards stay compact and visually consistent. Only exact historical
		 * plugin defaults are migrated; merchant-authored/custom text is left
		 * untouched. Bangla catalog values are intentionally unchanged.
		 */
		if ( $schema_version < 52 ) {
			if ( isset( $stored['advance_payment'] ) && is_array( $stored['advance_payment'] ) ) {
				$advance_subtitle = (string) ( $stored['advance_payment']['advance_card_subtitle'] ?? '' );
				if ( 'Pay {pay_now} now' === $advance_subtitle ) {
					$stored['advance_payment']['advance_card_subtitle'] = 'Pay {pay_now} in advance.';
				}
			}

			if ( isset( $stored['payment_methods']['cash_on_delivery'] ) && is_array( $stored['payment_methods']['cash_on_delivery'] ) ) {
				$cod_description = (string) ( $stored['payment_methods']['cash_on_delivery']['description'] ?? '' );
				if ( 'Pay when your order is delivered.' === $cod_description ) {
					$stored['payment_methods']['cash_on_delivery']['description'] = 'Pay with cash upon delivery.';
				}
			}

			if ( isset( $stored['discounts']['full_payment'] ) && is_array( $stored['discounts']['full_payment'] ) ) {
				$full_description = (string) ( $stored['discounts']['full_payment']['description'] ?? '' );
				if ( in_array( $full_description, array( 'Pay in full today.', 'Pay the full amount today and enjoy an instant discount.' ), true ) ) {
					$stored['discounts']['full_payment']['description'] = 'Pay the full amount in advance.';
				}
			}
		}

		$stored['schema_version'] = self::SCHEMA_VERSION;
		update_option( self::OPTION_NAME, $stored, false );
	}

	/**
	 * Get complete default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {

		$defaults = array(

			/*
			 * Settings schema version.
			 */
			'schema_version' => self::SCHEMA_VERSION,

            /*
            * General.
            *
            * General feature switches live here.
            *
            * Checkout Security, Customer Blacklist and
            * Checkout Access now live exclusively in the
            * dedicated SecuritySettings option.
            *
            * Courier is enabled by default.
            */
			'general' => array(
                'enabled'                 => 'yes',
                'abandoned_checkout'      => 'no',
                'courier'                 => 'yes',
                'meta_tracking'           => 'no',
                'whatsapp_ordering'       => 'no',
                'single_product_checkout' => 'no',
				'default_checkout_integration' => 'no',
				'delete_data_on_uninstall'      => 'no',
            ),


            /*
             * Checkout display and layout.
             *
             * These are global presentation defaults. Shortcodes and Elementor
             * choose checkout-instance content/assignments only; presentation
             * stays consistent from these Plugin Settings.
             */
            'checkout_display' => array(
                'language' => 'en',
                'layout' => array(
                    'preset' => '',
                    'summary_position'          => 'right',
                    'summary_sticky'            => 'yes',
                    'mobile_summary_sticky'     => 'yes',
                    'checkout_details_position' => 'main',
                    'whatsapp_placement'        => 'below_summary',
                ),
                'summary' => array(
                    'show_selected_items' => 'yes',
                    'show_thumbnail'      => 'yes',
                    'show_variation'      => 'yes',
                    'show_quantity'       => 'yes',
                    'show_item_price'     => 'yes',
                    'title'               => __( 'Order Summary', 'eilmo-checkout-flow' ),
                    'selected_items_title'=> __( 'Your Order', 'eilmo-checkout-flow' ),
                    'product_total_label' => __( 'Product Total', 'eilmo-checkout-flow' ),
                    'combo_discount_label'=> __( 'Combo Discount', 'eilmo-checkout-flow' ),
                    'special_offer_label' => __( 'Special Discount', 'eilmo-checkout-flow' ),
                    'automatic_discount_label' => __( 'Special Discount', 'eilmo-checkout-flow' ),
                    'coupon_discount_label'    => __( 'Coupon Discount', 'eilmo-checkout-flow' ),
                    'delivery_charge_label'    => __( 'Delivery Charge', 'eilmo-checkout-flow' ),
                    'full_payment_discount_label' => __( 'Full Payment Discount', 'eilmo-checkout-flow' ),
                    'advance_payment_label'    => __( 'Advance Payment', 'eilmo-checkout-flow' ),
                    'grand_total_label'        => __( 'Grand Total', 'eilmo-checkout-flow' ),
                    'pay_now_label'            => __( 'Pay Now', 'eilmo-checkout-flow' ),
                    'remaining_due_label'      => __( 'Remaining Due', 'eilmo-checkout-flow' ),
                    'view_summary_label'       => __( 'View Summary', 'eilmo-checkout-flow' ),
                ),
                'order_button' => array(
                    'label'          => __( 'Order Now', 'eilmo-checkout-flow' ),
                    'processing_label'=> __( 'Processing...', 'eilmo-checkout-flow' ),
                    'placement'      => 'below_summary',
                    'mobile_sticky'  => 'yes',
                    'show_amount'    => 'no',
                    'amount_source'  => 'auto',
                    'footer_enabled' => 'yes',
                    'footer_text'    => __( 'Secure order • Fast delivery', 'eilmo-checkout-flow' ),
                    'footer_icon'    => 'shield',
                    'cod_button_template' => 'Confirm order',
                    'advance_button_template' => '{amount} Pay and confirm order',
                    'full_button_template' => 'Pay {amount}',
                ),
            ),

			/*
			 * Combo Offers.
			 *
			 * This section stores the global reusable
			 * Combo Offer library.
			 *
			 * Which offers are shown on a specific checkout
			 * page is controlled by that checkout instance
			 * (Elementor / shortcode), not globally here.
			 */
			'combo_offers' => array(
				'enabled' => 'no',
				'position' => 'after_products',
				'priority' => 10,
				'card_layout' => 'full_width',
				'card_style' => 'highlighted',

				'title' => __(
					'Combo Offers',
					'eilmo-checkout-flow'
				),

				/*
				 * Combo-specific design tokens. Keeping these inside a named
				 * card-style layer lets future presets coexist without changing
				 * the reusable Combo Offer library.
				 */
				'style' => array(
					'gradient_start'         => '#5b21b6',
					'gradient_end'           => '#312e81',
					'card_border'            => '#6d28d9',
					'card_text'              => '#ffffff',
					'muted_text'             => '#ddd6fe',
					'icon_background'        => '#7c3aed',
					'icon_color'             => '#ffffff',
					'badge_background'       => '#fb7185',
					'badge_gradient_end'     => '#f43f5e',
					'badge_text'             => '#ffffff',
					'regular_price'          => '#c4b5fd',
					'price'                  => '#ffffff',
					'saving_background'      => '#7c3aed',
					'saving_text'            => '#ffffff',
					'image_background'       => '#ffffff',
					'plus_color'             => '#ffffff',
					'button_background'      => '#7c3aed',
					'button_text'            => '#ffffff',
					'button_hover_background'=> '#8b5cf6',
					'button_hover_text'      => '#ffffff',
					'selected_border'        => '#a78bfa',
					'selected_border_end'    => '#ec4899',
					'shadow_color'           => '#1e1b4b',
					'card_radius'             => 14,
					'card_padding'            => 18,
					'image_size'              => 74,
					'button_radius'           => 10,
				),

				/*
				 * Unlimited reusable Combo Offers.
				 */
				'offers' => array(),
			),

			/*
			 * One global theme for the complete checkout. Individual component
			 * styling is intentionally available only as an Elementor override.
			 * WhatsApp remains integration-owned and does not consume these values.
			 */
			'checkout_style' => CheckoutStyle::get_defaults(),

			/*
			 * Order Bumps.
			 *
			 * Global reusable Order Bump library.
			 *
			 * A checkout instance can later decide
			 * which configured bumps should be shown.
			 */
			'order_bumps' => array(
				'enabled' => 'no',
				'position' => 'before_delivery',
				'priority' => 20,
				'card_style' => 'promo_banner',
				'columns_desktop' => 1,
				'show_icon' => 'yes',
				'show_label' => 'yes',
				'show_title' => 'yes',
				'show_subtitle' => 'yes',
				'show_timer' => 'yes',
				'show_condition' => 'yes',
				'show_reward' => 'yes',
				'show_regular_price' => 'yes',
				'show_added_action' => 'yes',

				'title' => __(
					'Special Discounts',
					'eilmo-checkout-flow'
				),

				/*
				 * Supported:
				 *
				 * single
				 * multiple
				 */
				'selection_mode' =>
					'multiple',

				/*
				 * Special Offer presentation tokens.
				 *
				 * These live under the historical order_bumps key so
				 * existing checkout shortcodes and saved offers keep
				 * working while the customer-facing feature is named
				 * Special Offers.
				 */
				'style' => array(
					'background_type' => 'gradient',
					'gradient_start' => '#f5f3ff',
					'gradient_end' => '#ede9fe',
					'gradient_angle' => 135,
					'card_border' => '#c4b5fd',
					'accent_start' => '#7c3aed',
					'accent_end' => '#8b5cf6',
					'selected_background_type' => 'gradient',
					'selected_gradient_start' => '#f5f3ff',
					'selected_gradient_end' => '#ede9fe',
					'selected_gradient_angle' => 135,
					'selected_border_type' => 'gradient',
					'selected_border_start' => '#7c3aed',
					'selected_border_end' => '#8b5cf6',
					'selected_border_angle' => 135,
					'label_color' => '#7c3aed',
					'selected_label_color' => '#7c3aed',
					'title' => '#111827',
					'text' => '#64748b',
					'selected_title' => '#111827',
					'selected_text' => '#64748b',
					'badge_background_type' => 'gradient',
					'badge_background' => '#7c3aed',
					'badge_end' => '#8b5cf6',
					'badge_gradient_angle' => 135,
					'badge_text' => '#ffffff',
					'selected_badge_background_type' => 'gradient',
					'selected_badge_background' => '#7c3aed',
					'selected_badge_end' => '#8b5cf6',
					'selected_badge_text' => '#ffffff',
					'selected_badge_gradient_angle' => 135,
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
					'button_background_type' => 'gradient',
					'button_background' => '#7c3aed',
					'button_background_end' => '#8b5cf6',
					'button_gradient_angle' => 135,
					'button_text' => '#ffffff',
					'added_background_type' => 'gradient',
					'added_background' => '#7c3aed',
					'added_background_end' => '#8b5cf6',
					'added_gradient_angle' => 135,
					'added_text' => '#ffffff',
					'progress_background_type' => 'transparent',
					'progress_background' => '#f5f3ff',
					'progress_background_end' => '#ede9fe',
					'progress_gradient_angle' => 135,
					'progress_text' => '#7c3aed',
					'promo_icon_background_type' => 'transparent',
					'icon_background_type' => 'solid',
					'icon_color' => '#7c3aed',
					'image_background' => '#f5f3ff',
					'image_background_end' => '#ede9fe',
					'icon_gradient_angle' => 135,
					'selected_icon_background_type' => 'solid',
					'selected_icon_color' => '#7c3aed',
					'selected_icon_background' => '#f5f3ff',
					'selected_icon_background_end' => '#ede9fe',
					'selected_icon_gradient_angle' => 135,
					'decoration_primary' => '#7c3aed',
					'decoration_secondary' => '#8b5cf6',
					'card_radius' => 22,
					'card_padding' => 24,
					'image_size' => 150,
					'icon_size' => 30,
					'button_radius' => 12,
				),

				/*
				 * Unlimited reusable Order Bump offers.
				 */
				'offers' =>
					array(),
			),

			/*
			 * Delivery.
			 *
			 * No delivery method is hardcoded.
			 * Admin can create unlimited methods.
			 */
			'delivery' => array(
				'enabled' => 'no',

				'title' => __(
					'Delivery Method',
					'eilmo-checkout-flow'
				),

				'required'         => 'yes',
				'show_description' => 'yes',
				'default_method'   => '',
				'columns_desktop'  => 2,
				'columns_tablet'   => 2,
				'columns_mobile'   => 1,
				'content_layout'   => 'inline',
				'methods'          => array(),
			),

			/*
			 * Payment Options.
			 *
			 * The historical internal key remains
			 * "advance_payment" for backwards compatibility,
			 * while the admin/frontend concept is now
			 * Payment Options.
			 *
			 * Available top-level options:
			 *
			 * cash_on_delivery
			 * advance
			 * full
			 *
			 * Cash on Delivery does not require the separate
			 * Payment Method section. Advance and Full do.
			 *
			 * Supported advance placeholders:
			 *
			 * {pay_now}
			 * {remaining_due}
			 * {grand_total}
			 */
			'advance_payment' => array(
				'enabled' => 'no',
				'display_layout' => 'grid',
				'columns_desktop' => 3,
				'columns_tablet'  => 2,
				'columns_mobile'  => 1,
				'footer_enabled'  => 'yes',
				'footer_text'     => __( 'Your information is kept secure and private.', 'eilmo-checkout-flow' ),
				'footer_icon'     => 'shield',

				'title' => __(
					'Payment Option',
					'eilmo-checkout-flow'
				),

				/*
				 * Keep Cash on Delivery disabled by default so
				 * existing stores retain the previous Advance /
				 * Full two-option checkout after upgrading.
				 */
				'allow_cash_on_delivery' =>
					'no',

				'allow_advance_payment' =>
					'yes',

				'allow_full_payment' =>
					'yes',

				'default_payment_type' =>
					'advance',

				'calculation_basis' =>
					'grand_total',

				/*
				 * Advance-payment frontend text.
				 */
				'advance_label' => __(
					'Advance Payment',
					'eilmo-checkout-flow'
				),

				'advance_card_subtitle' => __(
					'Pay {pay_now} in advance.',
					'eilmo-checkout-flow'
				),

				'advance_description' => __(
					'Secure your order now. Pay the remaining {remaining_due} later.',
					'eilmo-checkout-flow'
				),

				'empty_description' => __(
					'Select a product to see the advance amount.',
					'eilmo-checkout-flow'
				),

				/*
				 * Unlimited advance-payment rules.
				 *
				 * Rules are preserved even while the Advance
				 * option itself is temporarily disabled.
				 */
				'rules' => array(),
			),

            /*
            * Payment methods.
            *
            * Country-specific methods such as bKash,
            * Nagad, UPI, GCash or M-Pesa are not
            * hardcoded. They can be created as custom
            * manual payment methods.
            */
            'payment_methods' => array(
                'enabled' => 'yes',

                'title' => __(
                    'Payment Method',
                    'eilmo-checkout-flow'
                ),

                'required'               => 'yes',
                'default_method'         => 'first_available',
                'display_layout'         => 'list',
                'columns_desktop'        => 3,
                'columns_tablet'         => 2,
                'columns_mobile'         => 1,

                /*
                * Cash on Delivery presentation data.
                *
                * COD is now selected from Payment Options,
                * not from the Payment Method list. These
                * values remain here for backwards-compatible
                * storage of the existing title/description.
                */
                'cash_on_delivery' => array(
                    'enabled' => 'yes',

                    'title' => __(
                        'Cash on Delivery',
                        'eilmo-checkout-flow'
                    ),

                    'description' => __(
                        'Pay with cash upon delivery.',
                        'eilmo-checkout-flow'
                    ),

                    'sort_order' => 10,
                ),

                /*
                * Fixed Eilmo bank-transfer method.
                */
                'bank_transfer' => array(
                    'enabled' => 'no',

                    'title' => __(
                        'Bank Transfer',
                        'eilmo-checkout-flow'
                    ),

                    'description' => __(
                        'Pay directly to our bank account.',
                        'eilmo-checkout-flow'
                    ),

                    'bank_name'      => '',
                    'account_name'   => '',
                    'account_number' => '',
                    'branch'         => '',
                    'routing_swift'  => '',
                    'instructions'   => '',

                    'transaction_id_required' => 'no',

                    'payment_proof_enabled' => 'no',

                    'payment_proof_label' => __(
                        'Payment Screenshot',
                        'eilmo-checkout-flow'
                    ),

                    'payment_proof_help' => __(
                        'Upload a JPG, PNG or WebP screenshot (maximum 5 MB).',
                        'eilmo-checkout-flow'
                    ),

                    'payment_proof_max_mb' => 5,

                    'transaction_id_label' => __(
                        'Transaction ID',
                        'eilmo-checkout-flow'
                    ),

                    'transaction_id_placeholder' => __(
                        'Enter transaction ID',
                        'eilmo-checkout-flow'
                    ),

                    'sort_order' => 20,
                ),

                /*
                * WooCommerce gateway integration source.
                */
                'woocommerce_gateways' => array(
                    'enabled'                => 'yes',
                    'show_description'       => 'yes',
                    'exclude_duplicate_cod'  => 'yes',
                    'exclude_duplicate_bacs' => 'no',
                    'sort_order'             => 30,
                ),

                /*
                * Unlimited international manual methods.
                */
                'custom_methods' => array(
                    array(
                        'id'                         => 'bkash',
                        'enabled'                    => 'no',
                        'title'                      => 'bKash',
                        'description'                => __( 'Pay securely using your bKash account.', 'eilmo-checkout-flow' ),
                        'instructions'               => __( 'Send the exact payable amount, then provide the Transaction ID or upload the payment screenshot.', 'eilmo-checkout-flow' ),
                        'account_label'               => __( 'bKash Number', 'eilmo-checkout-flow' ),
                        'account_value'               => '',
                        'transaction_id_required'     => 'yes',
                        'transaction_id_label'        => __( 'Transaction ID', 'eilmo-checkout-flow' ),
                        'transaction_id_placeholder'  => __( 'Enter bKash Transaction ID', 'eilmo-checkout-flow' ),
                        'payment_proof_enabled'       => 'yes',
                        'payment_proof_label'         => __( 'Payment Screenshot', 'eilmo-checkout-flow' ),
                        'payment_proof_help'          => __( 'Provide either the Transaction ID or a JPG, PNG or WebP payment screenshot (maximum 5 MB).', 'eilmo-checkout-flow' ),
                        'payment_proof_max_mb'        => 5,
                        'icon_url'                    => EILMO_CF_ASSETS_URL . 'images/bkash.png',
                        'sort_order'                  => 40,
                    ),
                    array(
                        'id'                         => 'nagad',
                        'enabled'                    => 'no',
                        'title'                      => 'Nagad',
                        'description'                => __( 'Pay securely using your Nagad account.', 'eilmo-checkout-flow' ),
                        'instructions'               => __( 'Send the exact payable amount, then provide the Transaction ID or upload the payment screenshot.', 'eilmo-checkout-flow' ),
                        'account_label'               => __( 'Nagad Number', 'eilmo-checkout-flow' ),
                        'account_value'               => '',
                        'transaction_id_required'     => 'yes',
                        'transaction_id_label'        => __( 'Transaction ID', 'eilmo-checkout-flow' ),
                        'transaction_id_placeholder'  => __( 'Enter Nagad Transaction ID', 'eilmo-checkout-flow' ),
                        'payment_proof_enabled'       => 'yes',
                        'payment_proof_label'         => __( 'Payment Screenshot', 'eilmo-checkout-flow' ),
                        'payment_proof_help'          => __( 'Provide either the Transaction ID or a JPG, PNG or WebP payment screenshot (maximum 5 MB).', 'eilmo-checkout-flow' ),
                        'payment_proof_max_mb'        => 5,
                        'icon_url'                    => EILMO_CF_ASSETS_URL . 'images/nagad.png',
                        'sort_order'                  => 50,
                    ),
                ),
            ),

            /*
            * Customer information.
            */
            'customer_information' => array(
                'enabled' => 'yes',

                'title' => __(
                    'Customer Information',
                    'eilmo-checkout-flow'
                ),

                'description' => __(
                    'Please provide your contact and delivery information.',
                    'eilmo-checkout-flow'
                ),

                'show_description' =>
                    'no',

                /*
                * Automatically populate available billing data
                * for logged-in WooCommerce customers.
                */
                'autofill_logged_in' =>
                    'yes',

                /*
                * Show * beside required field labels.
                */
                'show_required_mark' =>
                    'yes',

                /*
                * Available layouts:
                *
                * one_column
                * two_columns
                */
                'layout' =>
                    'two_columns',

                /*
                * Customer fields.
                *
                * Field keys intentionally map to standard
                * WooCommerce billing/order fields so final
                * order creation can use the same values.
                */
                'fields' => array(

                    'first_name' => array(
                        'enabled'     => 'yes',
                        'required'    => 'yes',
                        'label'       => __(
                            'Full Name',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter your full name',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'full',
                        'sort_order'  => 10,
                    ),

                    'last_name' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'Last Name',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter your last name',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 20,
                    ),

                    'company' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'Company',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Company name',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'full',
                        'sort_order'  => 30,
                    ),

                    'phone' => array(
                        'enabled'     => 'yes',
                        'required'    => 'yes',
                        'label'       => __(
                            'Phone Number',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter your phone number',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 40,
                    ),

                    'email' => array(
                        'enabled'     => 'yes',
                        'required'    => 'no',
                        'label'       => __(
                            'Email Address',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter your email address',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 50,
                    ),

                    'address_1' => array(
                        'enabled'     => 'yes',
                        'required'    => 'yes',
                        'label'       => __(
                            'Address',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'House, road, area',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'full',
                        'sort_order'  => 60,
                    ),

                    'address_2' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'Address Line 2',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Apartment, suite, unit, etc.',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'full',
                        'sort_order'  => 70,
                    ),

                    'city' => array(
                        'enabled'     => 'yes',
                        'required'    => 'yes',
                        'label'       => __(
                            'City',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter your city',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 80,
                    ),

                    'state' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'State / District',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter state or district',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 90,
                    ),

                    'postcode' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'Postcode',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Enter postcode',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'half',
                        'sort_order'  => 100,
                    ),

                    'country' => array(
                        'enabled'     => 'no',
                        'required'    => 'no',
                        'label'       => __(
                            'Country',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => '',
                        'width'       => 'half',
                        'sort_order'  => 110,
                    ),

                    'order_notes' => array(
                        'enabled'     => 'yes',
                        'required'    => 'no',
                        'label'       => __(
                            'Order Notes',
                            'eilmo-checkout-flow'
                        ),
                        'placeholder' => __(
                            'Add any special instructions for your order',
                            'eilmo-checkout-flow'
                        ),
                        'width'       => 'full',
                        'sort_order'  => 120,
                    ),
                ),
            ),

            /*
             * Quick Order Management.
             *
             * Keeps day-to-day order operations on the WooCommerce Orders
             * list so staff do not need to open the full order editor for
             * routine review, proof inspection or status changes.
             */
            'order_management' => array(
                'enabled'             => 'no',
                'allow_status_update' => 'yes',
                'default_columns'     => array(
                    'order'    => 'yes',
                    'date'     => 'yes',
                    'status'   => 'yes',
                    'billing'  => 'yes',
                    'shipping' => 'yes',
                    'courier'  => 'yes',
                    'meta'     => 'yes',
                    'security' => 'yes',
                    'total'    => 'yes',
                    'origin'   => 'yes',
                    'actions'  => 'yes',
                ),
                'columns'             => array(
                    'order'    => 'yes',
                    'customer' => 'yes',
                    'products' => 'yes',
                    'date'     => 'yes',
                    'status'   => 'yes',
                    'security' => 'yes',
                    'payment'  => 'yes',
                    'proof'    => 'yes',
                    'courier'  => 'yes',
                    'meta'       => 'no',
                    'order_note' => 'yes',
                    'total'    => 'yes',
                    'origin'   => 'yes',
                    'actions'  => 'yes',
                ),
            ),

			/*
			 * Discounts.
			 */
			'discounts' => array(
				'enabled'               => 'no',
				'stacking_mode'         => 'best_discount',
				'allow_coupon_stacking' => 'no',

				/*
				 * Automatic discount frontend notification.
				 *
				 * Tier ranges and discount values are NOT
				 * manually entered here.
				 *
				 * They are dynamically generated from
				 * automatic_rules.
				 *
				 * Supported placeholders:
				 *
				 * {discount}
				 * {remaining_amount}
				 * {current_total}
				 * {minimum_amount}
				 * {maximum_amount}
				 */
				'automatic_notification' => array(
					'enabled' => 'yes',

					'title' => __(
						'Spend More, Save More',
						'eilmo-checkout-flow'
					),

					/*
					 * Show all enabled automatic discount
					 * ranges below the notification title.
					 */
					'show_tiers' => 'yes',

					/*
					 * Show small dynamic status below tiers.
					 */
					'show_status' => 'yes',

					/*
					 * Product Total = 0.
					 */
					'empty_status' => __(
						'Select products to unlock your discount.',
						'eilmo-checkout-flow'
					),

					/*
					 * Matching discount rule found.
					 */
					'active_status' => __(
						'You are getting {discount} OFF.',
						'eilmo-checkout-flow'
					),

					/*
					 * No rule matched yet, but a higher
					 * discount tier is available.
					 */
					'upcoming_status' => __(
						'Add {remaining_amount} more to unlock {discount} OFF.',
						'eilmo-checkout-flow'
					),
				),

				/*
				 * Unlimited automatic order discount rules.
				 */
				'automatic_rules' => array(),

				/*
				 * Full-payment discount.
				 *
				 * Supported placeholders:
				 *
				 * {discount}
				 * {grand_total}
				 * {discounted_total}
				 */
				'full_payment' => array(
					'enabled'          => 'no',
					'type'             => 'percentage',
					'value'            => 0.0,
					'minimum_amount'   => 0.0,
					'maximum_discount' => 0.0,
					'basis'            => 'discounted_product_total',
					'free_delivery'    => 'no',

					'label' => __(
						'Full Payment',
						'eilmo-checkout-flow'
					),

					'description' => __(
						'Pay the full amount in advance.',
						'eilmo-checkout-flow'
					),

					'discount_badge' => __(
						'Get {discount} OFF',
						'eilmo-checkout-flow'
					),

					'free_delivery_badge' => __(
						'Free Delivery',
						'eilmo-checkout-flow'
					),
				),
			),

            /*
            * Coupons.
            */
            'coupons' => array(
                'enabled' => 'yes',

                /*
                * Available modes:
                *
                * woocommerce
                * custom
                * both
                */
                'mode' => 'woocommerce',

                /*
                * Customer identification used by
                * customer-limited custom coupons.
                *
                * Available modes:
                *
                * email
                * phone
                * email_or_phone
                *
                * email_or_phone means either matching
                * identifier is enough to consider the
                * customer the same customer.
                */
                'customer_identification' =>
                    'email_or_phone',

                /*
                * When enabled, guest customers must provide
                * the configured identity before coupons with
                * a per-customer limit or New Customer Only
                * restriction can be applied.
                *
                * Contact verification is intentionally not
                * handled by the Coupon module. That belongs
                * to the final checkout/order flow.
                */
                'require_contact_for_customer_limited' =>
                    'yes',

                /*
                * Unlimited plugin-managed coupons.
                */
                'custom_coupons' => array(),
			),
		);

		/*
		 * Product presentation and Gallery defaults are maintained by the
		 * dedicated ProductSettings class, while their historical option paths
		 * remain part of the single eilmo_cf_settings payload.
		 */
		$defaults = array_replace_recursive(
			$defaults,
			ProductSettings::get_defaults()
		);

		/**
		 * Filters default plugin settings.
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		$defaults = apply_filters(
			'eilmo_cf/settings_defaults',
			$defaults
		);

		return is_array( $defaults )
			? $defaults
			: array();
	}

	/**
	 * Sanitize complete settings payload.
	 *
	 * Settings are saved tab-by-tab in the admin interface.
	 * Sections that are not submitted must preserve their
	 * previously stored values.
	 *
	 * @param mixed $input Raw settings.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize(
		$input
	): array {

		$defaults = self::get_defaults();

		$stored = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/*
		 * Rules Engine has been removed.
		 *
		 * Remove legacy Rules Engine data before merging
		 * previously stored Checkout Flow settings.
		 */
		unset(
			$stored['rules_engine'],
			$stored['courier']
		);

		/*
		 * Courier detailed settings now live in the
		 * dedicated eilmo_cf_courier_settings option.
		 * Remove the temporary legacy General keys.
		 */
		if (
			isset( $stored['general'] ) &&
			is_array( $stored['general'] )
		) {
			unset(
				$stored['general']['courier_success'],
				$stored['general']['courier_one_click'],

				/*
				 * Security settings were migrated to the
				 * dedicated SecuritySettings option.
				 *
				 * SecuritySettings::maybe_migrate_legacy_settings()
				 * runs earlier on admin_init and also provides
				 * legacy fallbacks before migration, so these
				 * duplicate main-option keys no longer need to
				 * remain persisted here.
				 */
				$stored['general']['checkout_security'],
				$stored['general']['customer_blacklist'],
				$stored['general']['checkout_access'],
				$stored['general']['custom_login_url_enabled'],
				$stored['general']['custom_login_url']
			);
		}

		/*
		 * Invalid payload should never wipe existing settings.
		 */
		if ( ! is_array( $input ) ) {
			return array_replace_recursive(
				$defaults,
				$stored
			);
		}

		/*
		 * Start with defaults and previously saved settings.
		 */
		$sanitized = array_replace_recursive(
			$defaults,
			$stored
		);

		/*
		 * Indexed collections must be preserved exactly.
		 */
		if (
			isset( $stored['delivery']['methods'] ) &&
			is_array( $stored['delivery']['methods'] )
		) {
			$sanitized['delivery']['methods'] =
				$stored['delivery']['methods'];
		}

		if (
			isset( $stored['advance_payment']['rules'] ) &&
			is_array( $stored['advance_payment']['rules'] )
		) {
			$sanitized['advance_payment']['rules'] =
				$stored['advance_payment']['rules'];
		}

		if (
			isset( $stored['combo_offers']['offers'] ) &&
			is_array( $stored['combo_offers']['offers'] )
		) {
			$sanitized['combo_offers']['offers'] =
				$stored['combo_offers']['offers'];
		}

		if (
			isset( $stored['product_gallery']['galleries'] ) &&
			is_array( $stored['product_gallery']['galleries'] )
		) {
			$sanitized['product_gallery']['galleries'] =
				$stored['product_gallery']['galleries'];
		}

        if (
            isset(
                $stored[
                    'order_bumps'
                ][
                    'offers'
                ]
            ) &&
            is_array(
                $stored[
                    'order_bumps'
                ][
                    'offers'
                ]
            )
        ) {
            $sanitized[
                'order_bumps'
            ][
                'offers'
            ] =
                $stored[
                    'order_bumps'
                ][
                    'offers'
                ];
        }

        if (
            array_key_exists(
                'payment_methods',
                $input
            ) &&
            is_array(
                $input['payment_methods']
            )
        ) {
            /*
             * Payment Options also submits the legacy
             * payment_methods[cash_on_delivery] text fields.
             *
             * Use the already merged current section as the
             * fallback so saving Payment Options never resets
             * Bank Transfer, WooCommerce gateways or custom
             * payment methods that were configured elsewhere.
             */
            $sanitized['payment_methods'] =
                $this->sanitize_payment_methods(
                    $input['payment_methods'],
                    isset(
                        $sanitized['payment_methods']
                    ) &&
                    is_array(
                        $sanitized['payment_methods']
                    )
                        ? $sanitized['payment_methods']
                        : $defaults['payment_methods']
                );
        }

        if (
            array_key_exists(
                'customer_information',
                $input
            ) &&
            is_array(
                $input['customer_information']
            )
        ) {
            $sanitized['customer_information'] =
                $this->sanitize_customer_information(
                    $input['customer_information'],
                    $defaults['customer_information']
                );
        }

        if (
            array_key_exists(
                'order_management',
                $input
            ) &&
            is_array(
                $input['order_management']
            )
        ) {
            $sanitized['order_management'] =
                $this->sanitize_order_management(
                    $input['order_management'],
                    isset( $sanitized['order_management'] ) && is_array( $sanitized['order_management'] )
                        ? $sanitized['order_management']
                        : $defaults['order_management']
                );
        }

		if (
			isset( $stored['discounts']['automatic_rules'] ) &&
			is_array( $stored['discounts']['automatic_rules'] )
		) {
			$sanitized['discounts']['automatic_rules'] =
				$stored['discounts']['automatic_rules'];
		}

		if (
			isset( $stored['coupons']['custom_coupons'] ) &&
			is_array( $stored['coupons']['custom_coupons'] )
		) {
			$sanitized['coupons']['custom_coupons'] =
				$stored['coupons']['custom_coupons'];
		}

		$sanitized['schema_version'] =
			self::SCHEMA_VERSION;

		/*
		 * Only replace a section when that section
		 * was actually submitted by the active tab.
		 */
		if (
			array_key_exists(
				'general',
				$input
			) &&
			is_array( $input['general'] )
		) {
			$sanitized['general'] =
				$this->sanitize_general(
					$input['general'],
					isset( $sanitized['general'] ) && is_array( $sanitized['general'] )
						? $sanitized['general']
						: $defaults['general']
				);
		}

		if (
			array_key_exists(
				'checkout_display',
				$input
			) &&
			is_array( $input['checkout_display'] )
		) {
			$sanitized['checkout_display'] =
				$this->sanitize_checkout_display(
					$input['checkout_display'],
					isset( $sanitized['checkout_display'] ) && is_array( $sanitized['checkout_display'] )
						? $sanitized['checkout_display']
						: $defaults['checkout_display']
				);
		}

		if (
			array_key_exists( 'product_gallery', $input ) &&
			is_array( $input['product_gallery'] )
		) {
			$product_settings = new ProductSettings();

			$sanitized['product_gallery'] =
				$product_settings->sanitize_product_gallery(
					$input['product_gallery'],
					isset( $sanitized['product_gallery'] ) && is_array( $sanitized['product_gallery'] )
						? $sanitized['product_gallery']
						: $defaults['product_gallery']
				);
		}

		if (
			array_key_exists( 'single_product', $input ) &&
			is_array( $input['single_product'] )
		) {
			$product_settings = new ProductSettings();

			$sanitized['single_product'] =
				$product_settings->sanitize_single_product(
					$input['single_product'],
					isset( $sanitized['single_product'] ) && is_array( $sanitized['single_product'] )
						? $sanitized['single_product']
						: $defaults['single_product']
				);
		}

		if (
			array_key_exists( 'multiple_products', $input ) &&
			is_array( $input['multiple_products'] )
		) {
			$product_settings = new ProductSettings();

			$sanitized['multiple_products'] =
				$product_settings->sanitize_multiple_products(
					$input['multiple_products'],
					isset( $sanitized['multiple_products'] ) && is_array( $sanitized['multiple_products'] )
						? $sanitized['multiple_products']
						: $defaults['multiple_products']
				);
		}

		if (
			array_key_exists( 'checkout_style', $input ) &&
			is_array( $input['checkout_style'] )
		) {
			$sanitized['checkout_style'] = CheckoutStyle::sanitize(
				$input['checkout_style'],
				isset( $sanitized['checkout_style'] ) && is_array( $sanitized['checkout_style'] )
					? $sanitized['checkout_style']
					: $defaults['checkout_style']
			);
		}

		if (
			array_key_exists(
				'combo_offers',
				$input
			) &&
			is_array( $input['combo_offers'] )
		) {
			$sanitized['combo_offers'] =
				$this->sanitize_combo_offers(
					$input['combo_offers'],
					isset( $sanitized['combo_offers'] ) && is_array( $sanitized['combo_offers'] )
						? $sanitized['combo_offers']
						: $defaults['combo_offers']
				);
		}

		if (
			array_key_exists(
				'order_bumps',
				$input
			) &&
			is_array(
				$input[
					'order_bumps'
				]
			)
		) {
			$sanitized[
				'order_bumps'
			] =
				$this->sanitize_order_bumps(
					$input[
						'order_bumps'
					],
					isset( $sanitized['order_bumps'] ) && is_array( $sanitized['order_bumps'] )
						? $sanitized['order_bumps']
						: $defaults['order_bumps']
				);
		}

		if (
			array_key_exists(
				'delivery',
				$input
			) &&
			is_array( $input['delivery'] )
		) {
			$sanitized['delivery'] =
				$this->sanitize_delivery(
					$input['delivery'],
					$defaults['delivery']
				);
		}

		if (
			array_key_exists(
				'advance_payment',
				$input
			) &&
			is_array(
				$input['advance_payment']
			)
		) {
			$sanitized['advance_payment'] =
				$this->sanitize_advance_payment(
					$input['advance_payment'],
					$defaults['advance_payment']
				);
		}

		if (
			array_key_exists(
				'discounts',
				$input
			) &&
			is_array( $input['discounts'] )
		) {
			$sanitized['discounts'] =
				$this->sanitize_discounts(
					$input['discounts'],
					isset( $sanitized['discounts'] ) && is_array( $sanitized['discounts'] )
						? $sanitized['discounts']
						: $defaults['discounts']
				);
		}

		if (
			array_key_exists( 'order_bumps', $input ) &&
			isset( $sanitized['order_bumps'] ) &&
			is_array( $sanitized['order_bumps'] )
		) {
			if ( ! isset( $sanitized['discounts'] ) || ! is_array( $sanitized['discounts'] ) ) {
				$sanitized['discounts'] = $defaults['discounts'];
			}

			$sanitized['discounts']['enabled'] =
				'yes' === ( $sanitized['order_bumps']['enabled'] ?? 'no' ) ? 'yes' : 'no';
			$sanitized['discounts']['automatic_rules'] =
				$this->compile_automatic_rules_from_special_discounts(
					isset( $sanitized['order_bumps']['offers'] ) && is_array( $sanitized['order_bumps']['offers'] )
						? $sanitized['order_bumps']['offers']
						: array()
				);
		}

		if (
			array_key_exists(
				'coupons',
				$input
			) &&
			is_array( $input['coupons'] )
		) {
			$sanitized['coupons'] =
				$this->sanitize_coupons(
					$input['coupons'],
					$defaults['coupons']
				);
		}

		/**
		 * Filters sanitized settings before saving.
		 *
		 * @param array<string, mixed> $sanitized Sanitized settings.
		 * @param array<string, mixed> $input     Raw submitted settings.
		 * @param array<string, mixed> $stored    Previously stored settings.
		 */
		$sanitized = apply_filters(
			'eilmo_cf/settings_sanitized',
			$sanitized,
			$input,
			$stored
		);

		/*
		 * Courier detailed configuration belongs only to
		 * eilmo_cf_courier_settings. Runtime compatibility
		 * aliases must never be persisted in the main option.
		 */
		if ( is_array( $sanitized ) ) {
			unset(
				$sanitized['courier'],
				$sanitized['product_style']
			);

			if (
				isset( $sanitized['general'] ) &&
				is_array( $sanitized['general'] )
			) {
				unset(
					$sanitized['general']['courier_success'],
					$sanitized['general']['courier_one_click'],
					$sanitized['general']['checkout_security'],
					$sanitized['general']['customer_blacklist'],
					$sanitized['general']['checkout_access'],
					$sanitized['general']['custom_login_url_enabled'],
					$sanitized['general']['custom_login_url']
				);
			}
		}

		return is_array( $sanitized )
			? $sanitized
			: $stored;
	}

    /**
     * Sanitize Quick Order Management settings.
     *
     * @param array<string,mixed> $input    Input.
     * @param array<string,mixed> $defaults Defaults/current values.
     *
     * @return array<string,mixed>
     */
    private function sanitize_order_management(
        array $input,
        array $defaults
    ): array {

        $default_column_keys = array(
            'order',
            'date',
            'status',
            'billing',
            'shipping',
            'courier',
            'meta',
            'security',
            'total',
            'origin',
            'actions',
        );

        $default_columns = isset( $defaults['default_columns'] ) && is_array( $defaults['default_columns'] )
            ? $defaults['default_columns']
            : array();
        $input_default_columns = isset( $input['default_columns'] ) && is_array( $input['default_columns'] )
            ? $input['default_columns']
            : array();

        foreach ( $default_column_keys as $key ) {
            $default_columns[ $key ] = $this->sanitize_yes_no(
                $input_default_columns[ $key ] ?? $default_columns[ $key ] ?? 'yes',
                'yes'
            );
        }

        $column_keys = array(
            'order',
            'customer',
            'products',
            'date',
            'status',
            'security',
            'payment',
            'proof',
            'courier',
            'meta',
            'order_note',
            'total',
            'origin',
            'actions',
        );

        $columns = isset( $defaults['columns'] ) && is_array( $defaults['columns'] )
            ? $defaults['columns']
            : array();

        $input_columns = isset( $input['columns'] ) && is_array( $input['columns'] )
            ? $input['columns']
            : array();

        foreach ( $column_keys as $key ) {
            $columns[ $key ] = $this->sanitize_yes_no(
                $input_columns[ $key ] ?? 'no',
                'no'
            );
        }

        return array(
            'enabled' => $this->sanitize_yes_no(
                $input['enabled'] ?? $defaults['enabled'] ?? 'no',
                (string) ( $defaults['enabled'] ?? 'no' )
            ),
            'allow_status_update' => $this->sanitize_yes_no(
                $input['allow_status_update'] ?? $defaults['allow_status_update'] ?? 'yes',
                (string) ( $defaults['allow_status_update'] ?? 'yes' )
            ),
            'default_columns' => $default_columns,
            'columns'         => $columns,
        );
    }

    /**
     * Sanitize general settings.
     *
     * Security-specific settings are intentionally not
     * accepted here. They are owned by SecuritySettings.
     *
     * @param array<string, mixed> $input    Input.
     * @param array<string, mixed> $defaults Defaults.
     *
     * @return array<string, mixed>
     */
    private function sanitize_general(
        array $input,
        array $defaults
    ): array {

        return array(
            'enabled' =>
                $this->sanitize_yes_no(
                    $input[
                        'enabled'
                    ] ??
                        $defaults[
                            'enabled'
                        ],
                    $defaults[
                        'enabled'
                    ]
                ),

            'abandoned_checkout' =>
                $this->sanitize_yes_no(
                    $input[
                        'abandoned_checkout'
                    ] ??
                        $defaults[
                            'abandoned_checkout'
                        ],
                    $defaults[
                        'abandoned_checkout'
                    ]
                ),

            'courier' =>
                $this->sanitize_yes_no(
                    $input[
                        'courier'
                    ] ??
                        $defaults[
                            'courier'
                        ] ??
                        'yes',
                    (string) (
                        $defaults[
                            'courier'
                        ] ??
                            'yes'
                    )
                ),

            'whatsapp_ordering' =>
                $this->sanitize_yes_no(
                    $input[
                        'whatsapp_ordering'
                    ] ??
                        $defaults[
                            'whatsapp_ordering'
                        ],
                    $defaults[
                        'whatsapp_ordering'
                    ]
                ),

            'meta_tracking' =>
                $this->sanitize_yes_no(
                    $input[
                        'meta_tracking'
                    ] ??
                        $defaults[
                            'meta_tracking'
                        ] ??
                        'no',
                    (string) (
                        $defaults[
                            'meta_tracking'
                        ] ??
                            'no'
                    )
                ),

            'single_product_checkout' =>
                $this->sanitize_yes_no(
                    $input[
                        'single_product_checkout'
                    ] ??
                        $defaults[
                            'single_product_checkout'
                        ] ??
                        'no',
                    (string) (
                        $defaults[
                            'single_product_checkout'
                        ] ??
                            'no'
                        )
                ),

			'default_checkout_integration' =>
				$this->sanitize_yes_no(
					$input['default_checkout_integration'] ??
						$defaults['default_checkout_integration'] ??
						'no',
					(string) (
						$defaults['default_checkout_integration'] ??
							'no'
					)
				),

			'delete_data_on_uninstall' =>
				$this->sanitize_yes_no(
					$input['delete_data_on_uninstall'] ??
						$defaults['delete_data_on_uninstall'] ??
						'no',
					(string) (
						$defaults['delete_data_on_uninstall'] ??
							'no'
					)
				),

			'product_gallery' =>
				$this->sanitize_yes_no(
					$input['product_gallery'] ??
						$defaults['product_gallery'] ??
						'no',
					(string) (
						$defaults['product_gallery'] ??
							'no'
					)
				),
        );
    }

	/**
	 * Sanitize global checkout display settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_checkout_display(
		array $input,
		array $defaults
	): array {
		$layout_input   = isset( $input['layout'] ) && is_array( $input['layout'] ) ? $input['layout'] : array();
		$product_input  = isset( $input['products'] ) && is_array( $input['products'] ) ? $input['products'] : array();
		$summary_input  = isset( $input['summary'] ) && is_array( $input['summary'] ) ? $input['summary'] : array();
		$order_input    = isset( $input['order_button'] ) && is_array( $input['order_button'] ) ? $input['order_button'] : array();
		$layout_default = $defaults['layout'];
		$product_default= $defaults['products'];
		$summary_default= $defaults['summary'];
		$order_default  = $defaults['order_button'];

		$text = static function ( $value, $fallback ): string {
			$value = sanitize_text_field( (string) $value );
			return '' !== $value ? $value : sanitize_text_field( (string) $fallback );
		};
		$summary = array();
		foreach ( array(
			'title', 'selected_items_title', 'product_total_label', 'combo_discount_label',
			'special_offer_label', 'automatic_discount_label', 'coupon_discount_label',
			'delivery_charge_label', 'full_payment_discount_label', 'advance_payment_label',
			'grand_total_label', 'pay_now_label', 'remaining_due_label', 'view_summary_label',
		) as $key ) {
			$summary[ $key ] = $text( $summary_input[ $key ] ?? $summary_default[ $key ], $summary_default[ $key ] );
		}
		foreach ( array( 'show_selected_items', 'show_thumbnail', 'show_variation', 'show_quantity', 'show_item_price' ) as $key ) {
			$summary[ $key ] = $this->sanitize_yes_no( $summary_input[ $key ] ?? $summary_default[ $key ], $summary_default[ $key ] );
		}

		$product_settings = new ProductSettings();

		return array(
            'language' => $this->sanitize_choice( $input['language'] ?? 'en', array( 'en', 'bn' ), 'en' ),
			'layout' => array(
                'preset' => $this->sanitize_choice( $layout_input['preset'] ?? '', array( '', 'inline', 'right_sticky' ), '' ),
				'summary_position' => $this->sanitize_choice(
					$layout_input['summary_position'] ?? $layout_default['summary_position'],
					array( 'right', 'left', 'below' ),
					$layout_default['summary_position']
				),
				'summary_sticky' => $this->sanitize_yes_no( $layout_input['summary_sticky'] ?? $layout_default['summary_sticky'], $layout_default['summary_sticky'] ),
				'mobile_summary_sticky' => $this->sanitize_yes_no( $layout_input['mobile_summary_sticky'] ?? $layout_default['mobile_summary_sticky'], $layout_default['mobile_summary_sticky'] ),
				'checkout_details_position' => $this->sanitize_choice(
					$layout_input['checkout_details_position'] ?? $layout_default['checkout_details_position'],
					array( 'main', 'below_summary' ),
					$layout_default['checkout_details_position']
				),
				'whatsapp_placement' => $this->sanitize_choice(
					$layout_input['whatsapp_placement'] ?? $layout_default['whatsapp_placement'],
					array( 'below_payment', 'below_summary', 'both' ),
					$layout_default['whatsapp_placement']
				),
			),
			'products' => $product_settings->sanitize_product_display(
				$product_input,
				$product_default
			),
			'summary' => $summary,
			'order_button' => array(
				'label' => $text( $order_input['label'] ?? $order_default['label'], $order_default['label'] ),
				'processing_label' => $text( $order_input['processing_label'] ?? $order_default['processing_label'], $order_default['processing_label'] ),
				'placement' => $this->sanitize_choice( $order_input['placement'] ?? $order_default['placement'], array( 'below_payment', 'below_summary', 'both' ), $order_default['placement'] ),
				'mobile_sticky' => $this->sanitize_yes_no( $order_input['mobile_sticky'] ?? $order_default['mobile_sticky'], $order_default['mobile_sticky'] ),
				'show_amount' => $this->sanitize_yes_no( $order_input['show_amount'] ?? $order_default['show_amount'], $order_default['show_amount'] ),
				'amount_source' => $this->sanitize_choice( $order_input['amount_source'] ?? $order_default['amount_source'], array( 'auto', 'grand_total', 'pay_now' ), $order_default['amount_source'] ),
				'footer_enabled' => $this->sanitize_yes_no( $order_input['footer_enabled'] ?? $order_default['footer_enabled'], $order_default['footer_enabled'] ),
				'footer_text' => sanitize_text_field( (string) ( $order_input['footer_text'] ?? $order_default['footer_text'] ) ),
				'footer_icon' => $this->sanitize_choice( $order_input['footer_icon'] ?? $order_default['footer_icon'], array( 'shield', 'lock', 'none' ), $order_default['footer_icon'] ),
				'cod_button_template' => $text( $order_input['cod_button_template'] ?? $order_default['cod_button_template'], $order_default['cod_button_template'] ),
				'advance_button_template' => $text( $order_input['advance_button_template'] ?? $order_default['advance_button_template'], $order_default['advance_button_template'] ),
				'full_button_template' => $text( $order_input['full_button_template'] ?? $order_default['full_button_template'], $order_default['full_button_template'] ),
			),
		);
	}

	/**
	 * Sanitize Combo Offer settings.
	 *
	 * The Combo Offer library is global and reusable.
	 * Individual checkout instances decide which offer
	 * IDs are allowed to display and submit.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_combo_offers(
		array $input,
		array $defaults
	): array {

		$offers = isset( $defaults['offers'] ) && is_array( $defaults['offers'] )
			? $defaults['offers']
			: array();

		if (
			isset(
				$input['offers']
			) &&
			is_array(
				$input['offers']
			)
		) {
			$offers =
				$this->sanitize_combo_offer_library(
					$input['offers']
				);
		}

		$title =
			sanitize_text_field(
				(string) (
					$input['title'] ??
						$defaults['title'] ??
						''
				)
			);

		if (
			'' ===
				$title
		) {
			$title =
				sanitize_text_field(
					(string) (
						$defaults['title'] ??
							__(
								'Combo Offers',
								'eilmo-checkout-flow'
							)
					)
				);
		}

		return array(
			'enabled' =>
				$this->sanitize_yes_no(
					$input['enabled'] ??
						$defaults['enabled'] ??
						'no',
					(string) (
						$defaults['enabled'] ??
							'no'
					)
				),

			'title' =>
				$title,

			'position' =>
				$this->sanitize_choice(
					$input['position'] ?? $defaults['position'] ?? 'after_products',
					array( 'before_products', 'after_products', 'before_delivery', 'after_delivery', 'before_customer', 'after_customer', 'before_payment', 'after_payment', 'before_order' ),
					(string) ( $defaults['position'] ?? 'after_products' )
				),

			'priority' =>
				max( 0, min( 100, absint( $input['priority'] ?? $defaults['priority'] ?? 10 ) ) ),

			'card_layout' =>
				$this->sanitize_choice(
					$input['card_layout'] ?? $defaults['card_layout'] ?? 'full_width',
					array( 'full_width', 'two_columns' ),
					(string) ( $defaults['card_layout'] ?? 'full_width' )
				),

			'card_style' =>
				$this->sanitize_choice(
					$input['card_style'] ?? $defaults['card_style'] ?? 'highlighted',
					array( 'highlighted' ),
					(string) ( $defaults['card_style'] ?? 'highlighted' )
				),

			'style' =>
				$this->sanitize_combo_offer_style(
					isset( $input['style'] ) && is_array( $input['style'] )
						? $input['style']
						: array(),
					isset( $defaults['style'] ) && is_array( $defaults['style'] )
						? $defaults['style']
						: array()
				),
			'offers' =>
				$offers,
		);
	}

	/**
	 * Sanitize Combo Offer presentation tokens.
	 *
	 * @param array<string, mixed> $input    Input style.
	 * @param array<string, mixed> $defaults Default/current style.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_combo_offer_style(
		array $input,
		array $defaults
	): array {

		$colors = array(
			'gradient_start',
			'gradient_end',
			'card_border',
			'card_text',
			'muted_text',
			'icon_background',
			'icon_color',
			'badge_background',
			'badge_gradient_end',
			'badge_text',
			'regular_price',
			'price',
			'saving_background',
			'saving_text',
			'image_background',
			'plus_color',
			'button_background',
			'button_text',
			'button_hover_background',
			'button_hover_text',
			'selected_border',
			'selected_border_end',
			'shadow_color',
		);

		$sanitized = array();

		foreach ( $colors as $color ) {
			$fallback = sanitize_hex_color( (string) ( $defaults[ $color ] ?? '#000000' ) );
			$value    = sanitize_hex_color( (string) ( $input[ $color ] ?? $fallback ) );

			$sanitized[ $color ] = $value ? $value : ( $fallback ? $fallback : '#000000' );
		}

		$sanitized['card_radius'] = max( 0, min( 40, absint( $input['card_radius'] ?? $defaults['card_radius'] ?? 14 ) ) );
		$sanitized['card_padding'] = max( 8, min( 48, absint( $input['card_padding'] ?? $defaults['card_padding'] ?? 18 ) ) );
		$sanitized['image_size'] = max( 42, min( 140, absint( $input['image_size'] ?? $defaults['image_size'] ?? 74 ) ) );
		$sanitized['button_radius'] = max( 0, min( 40, absint( $input['button_radius'] ?? $defaults['button_radius'] ?? 10 ) ) );

		return $sanitized;
	}

	/**
	 * Sanitize global Combo Offer library.
	 *
	 * Supported pricing types:
	 *
	 * fixed_price
	 * percentage_discount
	 * fixed_discount
	 *
	 * @param array<mixed> $offers Offers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_combo_offer_library(
		array $offers
	): array {

		$sanitized =
			array();

		$used_ids =
			array();

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

			$title =
				sanitize_text_field(
					(string) (
						$offer['title'] ??
							''
					)
				);

			/*
			 * A reusable Combo Offer needs a title.
			 */
			if (
				'' ===
					$title
			) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$offer['id'] ??
							''
					)
				);

			if (
				'' ===
					$id
			) {
				$id =
					sanitize_key(
						$title
					);
			}

			if (
				'' ===
					$id
			) {
				$id =
					sprintf(
						'combo-offer-%d',
						absint(
							$index
						) +
							1
					);
			}

			$id =
				$this->make_unique_id(
					$id,
					$used_ids
				);

			$used_ids[] =
				$id;

			$pricing_type =
				$this->sanitize_choice(
					$offer['pricing_type'] ??
						'fixed_price',
					array(
						'fixed_price',
						'percentage_discount',
						'fixed_discount',
					),
					'fixed_price'
				);

			$pricing_value =
				$this->sanitize_amount(
					$offer['pricing_value'] ??
						0
				);

			if (
				'percentage_discount' ===
					$pricing_type
			) {
				$pricing_value =
					min(
						100,
						$pricing_value
					);
			}

			$items =
				isset(
					$offer['items']
				) &&
				is_array(
					$offer['items']
				)
					? $this->sanitize_combo_offer_items(
						$offer['items']
					)
					: array();

			/*
			 * Empty offers are not stored in the reusable
			 * library because they can never be purchased.
			 */
			if (
				empty(
					$items
				)
			) {
				continue;
			}

			$sanitized[] =
				array(
					'id' =>
						$id,

					'enabled' =>
						$this->sanitize_yes_no(
							$offer['enabled'] ??
								'yes',
							'yes'
						),

					'title' =>
						$title,

					'description' =>
						wp_kses_post(
							(string) (
								$offer['description'] ??
									''
							)
						),

					'badge' =>
						sanitize_text_field(
							(string) (
								$offer['badge'] ??
									''
							)
						),

					'eyebrow' =>
						sanitize_text_field(
							(string) ( $offer['eyebrow'] ?? 'COMBO OFFER' )
						),

					'icon_type' =>
						$this->sanitize_choice(
							$offer['icon_type'] ?? 'preset',
							array( 'preset', 'custom', 'none' ),
							'preset'
						),

					'icon_preset' =>
						$this->sanitize_choice(
							$offer['icon_preset'] ?? 'flame',
							array( 'flame', 'gift', 'star', 'bolt' ),
							'flame'
						),

					'icon_media_id' =>
						absint( $offer['icon_media_id'] ?? 0 ),

					'pricing_type' =>
						$pricing_type,

					'pricing_value' =>
						$pricing_value,

					'items' =>
						$items,

					'sort_order' =>
						absint(
							$offer['sort_order'] ??
								10
						),

					/*
					 * Discount compatibility.
					 *
					 * Preserve the historical Combo behavior by
					 * default:
					 *
					 * - Automatic Discount: no.
					 * - Coupon Discount: yes.
					 * - Full Payment Discount: yes.
					 *
					 * These values are later copied to the
					 * authoritative Combo line-item metadata so
					 * each selected Combo can control stacking
					 * independently.
					 */
					'apply_automatic_discount' =>
						$this->sanitize_yes_no(
							$offer['apply_automatic_discount'] ??
								'no',
							'no'
						),

					'apply_coupon' =>
						$this->sanitize_yes_no(
							$offer['apply_coupon'] ??
								'yes',
							'yes'
						),

					'apply_full_payment_discount' =>
						$this->sanitize_yes_no(
							$offer['apply_full_payment_discount'] ??
								'yes',
							'yes'
						),
				);
		}

		usort(
			$sanitized,
			static function (
				array $first,
				array $second
			): int {

				return (int) (
					$first['sort_order'] ??
						10
				) <=>
					(int) (
						$second['sort_order'] ??
							10
					);
			}
		);

		return array_values(
			$sanitized
		);
	}

	/**
	 * Sanitize products inside one Combo Offer.
	 *
	 * Duplicate product/variation rows are merged by
	 * increasing quantity. This keeps the stored Combo
	 * definition deterministic.
	 *
	 * @param array<mixed> $items Combo items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_combo_offer_items(
		array $items
	): array {

		$sanitized =
			array();

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
					$item['product_id'] ??
						0
				);

			$variation_id =
				absint(
					$item['variation_id'] ??
						0
				);

			$quantity =
				$this->sanitize_quantity(
					$item['quantity'] ??
						1
				);

			if (
				$product_id <= 0 ||
				$quantity <= 0
			) {
				continue;
			}

			$key =
				sprintf(
					'%1$d:%2$d',
					$product_id,
					$variation_id
				);

			if (
				isset(
					$sanitized[
						$key
					]
				)
			) {
				$sanitized[
					$key
				][
					'quantity'
				] =
					$this->sanitize_quantity(
						(
							$sanitized[
								$key
							][
								'quantity'
							] ??
								0
						) +
							$quantity
					);

				continue;
			}

			$sanitized[
				$key
			] =
				array(
					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						$quantity,

					'image_source' =>
						$this->sanitize_choice(
							$item['image_source'] ?? 'product',
							array( 'product', 'custom', 'hidden' ),
							'product'
						),

					'image_id' =>
						absint( $item['image_id'] ?? 0 ),
				);
		}

		return array_values(
			$sanitized
		);
	}


	/**
	 * Sanitize Order Bump settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_order_bumps(
		array $input,
		array $defaults
	): array {

		$settings =
			$defaults;

		$settings[
			'enabled'
		] =
			'yes' ===
				(
					$input[
						'enabled'
					] ??
						( $defaults['enabled'] ?? 'no' )
				)
					? 'yes'
					: 'no';

		$settings[
			'title'
		] =
			sanitize_text_field(
				(string) (
					$input[
						'title'
					] ??
						$defaults[
							'title'
						]
				)
			);

		$settings['position'] =
			$this->sanitize_choice(
				$input['position'] ?? $defaults['position'] ?? 'before_delivery',
				array( 'before_products', 'after_products', 'before_delivery', 'after_delivery', 'before_customer', 'after_customer', 'before_payment', 'after_payment', 'before_order' ),
				(string) ( $defaults['position'] ?? 'before_delivery' )
			);

		$settings['priority'] =
			max( 0, min( 100, absint( $input['priority'] ?? $defaults['priority'] ?? 20 ) ) );

		$settings['card_style'] =
			$this->sanitize_choice(
				$input['card_style'] ?? $defaults['card_style'] ?? 'promo_banner',
				array( 'promo_banner', 'reward_tiles', 'compact_strip' ),
				(string) ( $defaults['card_style'] ?? 'promo_banner' )
			);

		$columns_desktop = max(
			1,
			min( 3, absint( $input['columns_desktop'] ?? $defaults['columns_desktop'] ?? 1 ) )
		);
		if ( 'promo_banner' === $settings['card_style'] ) {
			$columns_desktop = min( 2, $columns_desktop );
		}
		$settings['columns_desktop'] = $columns_desktop;

		foreach ( array( 'show_icon', 'show_label', 'show_title', 'show_subtitle', 'show_timer', 'show_condition', 'show_reward', 'show_regular_price', 'show_added_action' ) as $display_key ) {
			$settings[ $display_key ] = 'no' === (string) ( $input[ $display_key ] ?? $defaults[ $display_key ] ?? 'yes' ) ? 'no' : 'yes';
		}

		$settings['style'] =
			$this->sanitize_special_offer_style(
				isset( $input['style'] ) && is_array( $input['style'] )
					? $input['style']
					: array(),
				isset( $defaults['style'] ) && is_array( $defaults['style'] )
					? $defaults['style']
					: array()
			);

		$selection_mode =
			sanitize_key(
				(string) (
					$input[
						'selection_mode'
					] ??
						( $defaults['selection_mode'] ?? 'multiple' )
				)
			);

		$settings[
			'selection_mode'
		] =
			in_array(
				$selection_mode,
				array(
					'single',
					'multiple',
				),
				true
			)
				? $selection_mode
				: 'multiple';

		if ( isset( $input['offers'] ) && is_array( $input['offers'] ) ) {
			$offers = $input['offers'];
		} elseif ( 'yes' === ( $input['offers_submitted'] ?? 'no' ) ) {
			/* An explicit empty repeater must delete the final saved rule. */
			$offers = array();
		} else {
			$offers = isset( $defaults['offers'] ) && is_array( $defaults['offers'] )
				? $defaults['offers']
				: array();
		}

		$sanitized_offers =
			array();

		$used_ids =
			array();

		foreach ( $offers as $offer ) {

			if ( ! is_array( $offer ) ) {
				continue;
			}

			/* Preserve omitted legacy presentation fields when the simplified rule UI saves. */
			$submitted_id = sanitize_key( (string) ( $offer['id'] ?? '' ) );
			foreach ( (array) ( $defaults['offers'] ?? array() ) as $existing_offer ) {
				if ( is_array( $existing_offer ) && '' !== $submitted_id && $submitted_id === sanitize_key( (string) ( $existing_offer['id'] ?? '' ) ) ) {
					$offer = array_replace( $existing_offer, $offer );
					break;
				}
			}

			$title =
				sanitize_text_field(
					(string) (
						$offer[
							'title'
						] ??
							''
					)
				);

			$id =
				sanitize_key(
					(string) (
						$offer[
							'id'
						] ??
							''
					)
				);

			if (
				'' === $id &&
				'' !== $title
			) {
				$id =
					sanitize_title(
						$title
					);
			}

			$offer_type =
				$this->sanitize_choice(
					$offer['offer_type'] ?? 'single_product',
					array(
						'single_product',
						'free_gift',
						'gift_box',
						'buy_one_get_one',
						'free_delivery',
						'percentage_discount',
						'fixed_discount',
					),
					'single_product'
				);

			$condition_type = $this->sanitize_choice(
				$offer['condition_type'] ?? 'always',
				array( 'always', 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal', 'specific_product', 'product_exists', 'cart_quantity', 'minimum_quantity', 'buy_x_quantity', 'current_product' ),
				'always'
			);

			$condition_minimum = $this->sanitize_amount( $offer['condition_minimum'] ?? 0 );
			$condition_maximum = $this->sanitize_amount( $offer['condition_maximum'] ?? 0 );
			if ( $condition_maximum > 0 && $condition_maximum < $condition_minimum ) {
				$condition_maximum = $condition_minimum;
			}

			$condition_product_id = absint( $offer['condition_product_id'] ?? 0 );
			$condition_product_quantity = max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) );
			$condition_cart_quantity = max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) );
			$rule_scope = $this->sanitize_choice(
				$offer['rule_scope'] ?? 'whole_cart',
				array( 'current_product', 'selected_products', 'whole_cart' ),
				'whole_cart'
			);
			$scope_product_ids = isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] )
				? array_values( array_unique( array_filter( array_map( 'absint', $offer['scope_product_ids'] ) ) ) )
				: array();
			$free_delivery_method_id = sanitize_key( (string) ( $offer['free_delivery_method_id'] ?? '' ) );
			$free_delivery_hide_other_methods = 'yes' === ( $offer['free_delivery_hide_other_methods'] ?? 'no' ) ? 'yes' : 'no';
			$apply_behavior = $this->sanitize_choice(
				$offer['apply_behavior'] ?? 'customer_selectable',
				array( 'auto_apply', 'customer_selectable' ),
				'customer_selectable'
			);
			if ( in_array( $offer_type, array( 'percentage_discount', 'fixed_discount', 'free_delivery', 'free_gift' ), true ) ) {
				$apply_behavior = 'auto_apply';
			}
			$ineligible_action = $this->sanitize_choice(
				$offer['ineligible_action'] ?? 'show_locked',
				array( 'show_locked', 'hide' ),
				'show_locked'
			);

			$product_id =
				absint(
					$offer[
						'product_id'
					] ??
						0
				);

			$variation_id =
				absint(
					$offer[
						'variation_id'
					] ??
						0
				);

			$quantity =
				isset(
					$offer[
						'quantity'
					]
				) &&
				is_numeric(
					$offer[
						'quantity'
					]
				)
					? max(
						0.01,
						(float) $offer[
							'quantity'
						]
					)
					: 1.0;

			if ( 'buy_one_get_one' === $offer_type ) {
				$quantity = 2.0;
			} elseif ( 'free_delivery' === $offer_type ) {
				$quantity = 0.0;
				$product_id = 0;
				$variation_id = 0;
			}

			if (
				'' === $id ||
				( ! in_array( $offer_type, array( 'free_delivery', 'percentage_discount', 'fixed_discount' ), true ) && $product_id <= 0 )
			) {
				continue;
			}

			if (
				in_array(
					$id,
					$used_ids,
					true
				)
			) {
				$suffix =
					2;

				$base_id =
					$id;

				while (
					in_array(
						$id,
						$used_ids,
						true
					)
				) {
					$id =
						$base_id .
						'-' .
						$suffix;

					++$suffix;
				}
			}

			$used_ids[] =
				$id;

			$pricing_type =
				sanitize_key(
					(string) (
						$offer[
							'pricing_type'
						] ??
							'regular_price'
					)
				);

			if (
				! in_array(
					$pricing_type,
					array(
						'regular_price',
						'fixed_price',
						'percentage_discount',
						'fixed_discount',
					),
					true
				)
			) {
				$pricing_type =
					'regular_price';
			}

			$pricing_value =
				isset(
					$offer[
						'pricing_value'
					]
				) &&
				is_numeric(
					$offer[
						'pricing_value'
					]
				)
					? max(
						0,
						(float) $offer[
							'pricing_value'
						]
					)
					: 0.0;

			if (
				'percentage_discount' ===
					$pricing_type
			) {
				$pricing_value =
					min(
						100,
						$pricing_value
					);
			}

			/*
			 * Product reward presets are normalized here so the
			 * renderer, preview and authoritative order creator all
			 * receive the same pricing instruction.
			 */
			if ( 'free_gift' === $offer_type ) {
				$pricing_type = 'fixed_price';
				$pricing_value = 0.0;
			} elseif ( 'buy_one_get_one' === $offer_type ) {
				$pricing_type = 'percentage_discount';
				$pricing_value = 50.0;
			} elseif ( 'free_delivery' === $offer_type ) {
				$pricing_type = 'regular_price';
				$pricing_value = 0.0;
			} elseif ( 'percentage_discount' === $offer_type ) {
				$pricing_type = 'percentage_discount';
				$pricing_value = min( 100, $pricing_value );
				$product_id = 0;
				$variation_id = 0;
				$quantity = 0.0;
			} elseif ( 'fixed_discount' === $offer_type ) {
				$pricing_type = 'fixed_discount';
				$product_id = 0;
				$variation_id = 0;
				$quantity = 0.0;
			}

			$start_at = sanitize_text_field( (string) ( $offer['start_at'] ?? '' ) );
			$end_at = sanitize_text_field( (string) ( $offer['end_at'] ?? '' ) );
			$date_pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/';
			$start_at = '' === $start_at || preg_match( $date_pattern, $start_at ) ? $start_at : '';
			$end_at = '' === $end_at || preg_match( $date_pattern, $end_at ) ? $end_at : '';
			$locked_text = sanitize_text_field( (string) ( $offer['locked_text'] ?? '' ) );
			if ( '' === trim( $locked_text ) ) {
				$locked_text = 'Add {remaining_amount} more to unlock this reward.';
			}

			$sanitized_offers[] =
				array(
					'id' =>
						$id,

					'enabled' =>
						'yes' ===
							(
								$offer[
									'enabled'
								] ??
									'yes'
							)
								? 'yes'
								: 'no',

					'title' =>
						$title,

					'offer_type' =>
						$offer_type,

					'rule_scope' =>
						$rule_scope,

					'scope_product_ids' =>
						$scope_product_ids,

					'condition_type' =>
						$condition_type,

					'condition_minimum' =>
						$condition_minimum,

					'condition_maximum' =>
						$condition_maximum,

					'condition_product_id' =>
						$condition_product_id,

					'condition_product_quantity' =>
						$condition_product_quantity,

					'condition_cart_quantity' =>
						$condition_cart_quantity,

					'apply_behavior' =>
						$apply_behavior,

					'ineligible_action' =>
						$ineligible_action,

					'locked_text' =>
						$locked_text,

					'label' =>
						sanitize_text_field( (string) ( $offer['label'] ?? 'LIMITED TIME OFFER' ) ),

					'before_apply_text' =>
						sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) ),

					'button_text' =>
						sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) ),

					'applied_text' =>
						sanitize_text_field( (string) ( $offer['applied_text'] ?? 'Added' ) ),

					'reward_title' =>
						sanitize_text_field( (string) ( $offer['reward_title'] ?? '' ) ),

					'start_at' =>
						$start_at,

					'end_at' =>
						$end_at,

					'timer_enabled' =>
						'no' === ( $offer['timer_enabled'] ?? 'yes' ) ? 'no' : 'yes',

					'image_source' =>
						$this->sanitize_choice(
							$offer['image_source'] ?? 'product',
							array( 'preset', 'product', 'custom', 'hidden' ),
							'product'
						),

					'icon_preset' =>
						$this->sanitize_choice(
							$offer['icon_preset'] ?? 'auto',
							array( 'auto', 'discount', 'delivery', 'gift', 'product' ),
							'auto'
						),

					'image_media_id' =>
						absint( $offer['image_media_id'] ?? 0 ),

					'image_position' =>
						$this->sanitize_choice(
							$offer['image_position'] ?? 'center',
							array( 'top', 'center', 'bottom' ),
							'center'
						),

					'description' =>
						wp_kses_post(
							(string) (
								$offer[
									'description'
								] ??
									''
							)
						),

					'badge' =>
						sanitize_text_field(
							(string) (
								$offer[
									'badge'
								] ??
									''
							)
						),

					'product_id' =>
						$product_id,

					'variation_id' =>
						$variation_id,

					'quantity' =>
						$quantity,

					'pricing_type' =>
						$pricing_type,

					'pricing_value' =>
						$pricing_value,

					'maximum_discount' =>
						$this->sanitize_amount( $offer['maximum_discount'] ?? 0 ),

					'free_delivery_method_id' =>
						$free_delivery_method_id,

					'free_delivery_hide_other_methods' =>
						$free_delivery_hide_other_methods,

					'sort_order' =>
						absint(
							$offer[
								'sort_order'
							] ??
								10
						),

					'stop_processing' =>
						'no' === ( $offer['stop_processing'] ?? 'yes' ) ? 'no' : 'yes',

					/*
					 * Default isolation:
					 *
					 * Order Bump receives only its own
					 * promotional pricing unless admin
					 * explicitly allows another discount.
					 */
					'apply_automatic_discount' =>
						'yes' ===
							(
								$offer[
									'apply_automatic_discount'
								] ??
									'no'
							)
								? 'yes'
								: 'no',

					'apply_coupon' =>
						'yes' ===
							(
								$offer[
									'apply_coupon'
								] ??
									'no'
							)
								? 'yes'
								: 'no',

					'apply_full_payment_discount' =>
						'yes' ===
							(
								$offer[
									'apply_full_payment_discount'
								] ??
									'yes'
							)
								? 'yes'
								: 'no',
				);
		}

		usort(
			$sanitized_offers,
			static function (
				array $first,
				array $second
			): int {

				return (
					(int) (
						$first[
							'sort_order'
						] ??
							10
					)
				) <=>
				(
					(int) (
						$second[
							'sort_order'
						] ??
							10
					)
				);
			}
		);

		$settings[
			'offers'
		] =
			array_values(
				$sanitized_offers
			);

		return $settings;
	}

	/**
	 * Sanitize Special Offer presentation tokens.
	 *
	 * @param array<string, mixed> $input    Input style.
	 * @param array<string, mixed> $defaults Default/current style.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_special_offer_style(
		array $input,
		array $defaults
	): array {

		$colors = array(
			'gradient_start',
			'gradient_end',
			'card_border',
			'accent_start',
			'accent_end',
			'selected_gradient_start',
			'selected_gradient_end',
			'selected_border_start',
			'selected_border_end',
			'label_color',
			'selected_label_color',
			'title',
			'text',
			'selected_title',
			'selected_text',
			'badge_background',
			'badge_end',
			'badge_text',
			'selected_badge_background',
			'selected_badge_end',
			'selected_badge_text',
			'timer_background',
			'timer_text',
			'timer_label',
			'timer_border',
			'selected_timer_background',
			'selected_timer_text',
			'selected_timer_label',
			'selected_timer_border',
			'regular_price',
			'offer_price',
			'selected_regular_price',
			'selected_offer_price',
			'button_background',
			'button_background_end',
			'button_text',
			'added_background',
			'added_background_end',
			'added_text',
			'progress_background',
			'progress_background_end',
			'progress_text',
			'icon_color',
			'image_background',
			'image_background_end',
			'selected_icon_color',
			'selected_icon_background',
			'selected_icon_background_end',
			'decoration_primary',
			'decoration_secondary',
		);

		$sanitized = array();
		$surface_types = array(
			'background_type',
			'selected_background_type',
			'selected_border_type',
			'button_background_type',
			'added_background_type',
			'progress_background_type',
			'badge_background_type',
			'selected_badge_background_type',
			'promo_icon_background_type',
			'icon_background_type',
			'selected_icon_background_type',
		);
		foreach ( $surface_types as $surface_type ) {
			$sanitized[ $surface_type ] = $this->sanitize_choice(
				$input[ $surface_type ] ?? $defaults[ $surface_type ] ?? 'gradient',
				array( 'transparent', 'solid', 'gradient' ),
				(string) ( $defaults[ $surface_type ] ?? 'gradient' )
			);
		}

		foreach ( $colors as $color ) {
			$fallback = sanitize_hex_color( (string) ( $defaults[ $color ] ?? '#000000' ) );
			$value = sanitize_hex_color( (string) ( $input[ $color ] ?? $fallback ) );
			$sanitized[ $color ] = $value ? $value : ( $fallback ? $fallback : '#000000' );
		}

		$sanitized['card_radius'] = max( 0, min( 40, absint( $input['card_radius'] ?? $defaults['card_radius'] ?? 22 ) ) );
		$sanitized['card_padding'] = max( 12, min( 48, absint( $input['card_padding'] ?? $defaults['card_padding'] ?? 24 ) ) );
		$sanitized['image_size'] = max( 72, min( 220, absint( $input['image_size'] ?? $defaults['image_size'] ?? 150 ) ) );
		$sanitized['icon_size'] = max( 12, min( 120, absint( $input['icon_size'] ?? $defaults['icon_size'] ?? 30 ) ) );
		$sanitized['button_radius'] = max( 0, min( 40, absint( $input['button_radius'] ?? $defaults['button_radius'] ?? 12 ) ) );
		foreach ( array( 'gradient_angle', 'selected_gradient_angle', 'selected_border_angle', 'badge_gradient_angle', 'selected_badge_gradient_angle', 'button_gradient_angle', 'added_gradient_angle', 'progress_gradient_angle', 'icon_gradient_angle', 'selected_icon_gradient_angle' ) as $angle ) {
			$sanitized[ $angle ] = max( 0, min( 360, absint( $input[ $angle ] ?? $defaults[ $angle ] ?? 135 ) ) );
		}

		return $sanitized;
	}

	/**
	 * Sanitize delivery settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_delivery(
		array $input,
		array $defaults
	): array {

		$methods = array();

		if (
			isset( $input['methods'] ) &&
			is_array( $input['methods'] )
		) {
			$methods =
				$this->sanitize_delivery_methods(
					$input['methods']
				);
		}

		$default_method =
			sanitize_key(
				(string) (
					$input['default_method'] ??
						$defaults['default_method']
				)
			);

		if (
			'' !== $default_method &&
			! $this->collection_contains_id(
				$methods,
				$default_method
			)
		) {
			$default_method = '';
		}

		return array(
			'enabled' =>
				$this->sanitize_yes_no(
					$input['enabled'] ??
						$defaults['enabled'],
					$defaults['enabled']
				),

			'title' =>
				sanitize_text_field(
					(string) (
						$input['title'] ??
							$defaults['title']
					)
				),

			'required' =>
				$this->sanitize_yes_no(
					$input['required'] ??
						$defaults['required'],
					$defaults['required']
				),

			'show_description' =>
				$this->sanitize_yes_no(
					$input['show_description'] ??
						$defaults['show_description'],
					$defaults['show_description']
				),

			'columns_desktop' =>
				max( 1, min( 4, absint( $input['columns_desktop'] ?? $defaults['columns_desktop'] ?? 2 ) ) ),

			'columns_tablet' =>
				max( 1, min( 3, absint( $input['columns_tablet'] ?? $defaults['columns_tablet'] ?? 2 ) ) ),

			'columns_mobile' =>
				max( 1, min( 2, absint( $input['columns_mobile'] ?? $defaults['columns_mobile'] ?? 1 ) ) ),

			'content_layout' =>
				$this->sanitize_choice(
					$input['content_layout'] ?? $defaults['content_layout'] ?? 'inline',
					array( 'inline', 'stacked' ),
					(string) ( $defaults['content_layout'] ?? 'inline' )
				),

			'default_method' =>
				$default_method,

			'methods' =>
				$methods,

		);
	}

	/**
	 * Sanitize delivery methods.
	 *
	 * @param array<mixed> $methods Methods.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_delivery_methods(
		array $methods
	): array {

		$sanitized = array();
		$used_ids  = array();

		foreach ( $methods as $index => $method ) {

			if ( ! is_array( $method ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $method['label'] ?? '' ) );
			$label_bn = sanitize_text_field( (string) ( $method['label_bn'] ?? '' ) );

			/* English/base remains the compatibility value. A Bangla-only method is
			 * still valid; use it as the fallback base so IDs and older integrations
			 * never receive an empty label. */
			if ( '' === $label && '' !== $label_bn ) {
				$label = $label_bn;
			}

			if ( '' === $label ) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$method['id'] ??
							''
					)
				);

			if ( '' === $id ) {
				$id = sanitize_key(
					$label
				);
			}

			if ( '' === $id ) {
				$id = sprintf(
					'delivery-method-%d',
					absint( $index ) + 1
				);
			}

			$id =
				$this->make_unique_id(
					$id,
					$used_ids
				);

			$used_ids[] = $id;

			$sanitized[] = array(
				'id' =>
					$id,

				'label' =>
					$label,

				'label_bn' =>
					$label_bn,

				'description' =>
					wp_kses_post(
						(string) (
							$method['description'] ??
								''
						)
					),

				'description_bn' =>
					wp_kses_post(
						(string) (
							$method['description_bn'] ??
								''
						)
					),

				'charge' =>
					$this->sanitize_amount(
						$method['charge'] ??
							0
					),

				'enabled' =>
					$this->sanitize_yes_no(
						$method['enabled'] ??
							'yes',
						'yes'
					),

				'sort_order' =>
					absint(
						$method['sort_order'] ??
							0
					),
			);
		}

		usort(
			$sanitized,
			static function (
				array $first,
				array $second
			): int {

				return (int) $first['sort_order']
					<=> (int) $second['sort_order'];
			}
		);

		return array_values(
			$sanitized
		);
	}

	/**
	 * Sanitize advance-payment settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_advance_payment(
		array $input,
		array $defaults
	): array {

		$allowed_payment_types =
			array(
				'cash_on_delivery',
				'advance',
				'full',
			);

		$allowed_bases =
			array(
				'product_total',
				'discounted_product_total',
				'grand_total',
			);

		$enabled =
			$this->sanitize_yes_no(
				$input['enabled'] ??
					$defaults['enabled'],
				$defaults['enabled']
			);

		$allow_cash_on_delivery =
			$this->sanitize_yes_no(
				$input['allow_cash_on_delivery'] ??
					$defaults['allow_cash_on_delivery'] ??
					'no',
				(string) (
					$defaults['allow_cash_on_delivery'] ??
						'no'
				)
			);

		$allow_advance_payment =
			$this->sanitize_yes_no(
				$input['allow_advance_payment'] ??
					$defaults['allow_advance_payment'] ??
					'yes',
				(string) (
					$defaults['allow_advance_payment'] ??
						'yes'
				)
			);

		$allow_full_payment =
			$this->sanitize_yes_no(
				$input['allow_full_payment'] ??
					$defaults['allow_full_payment'] ??
					'yes',
				(string) (
					$defaults['allow_full_payment'] ??
						'yes'
				)
			);

		/*
		 * Payment Options must have at least one available
		 * choice while the section is enabled.
		 *
		 * If the admin disables every option, preserve a
		 * valid checkout by restoring Advance Payment.
		 *
		 * When the master Payment Options switch is off,
		 * all three child choices may remain disabled.
		 */
		if (
			'yes' === $enabled &&
			'yes' !== $allow_cash_on_delivery &&
			'yes' !== $allow_advance_payment &&
			'yes' !== $allow_full_payment
		) {
			$allow_advance_payment =
				'yes';
		}

		$default_payment_type =
			$this->sanitize_choice(
				$input['default_payment_type'] ??
					$defaults['default_payment_type'],
				$allowed_payment_types,
				$defaults['default_payment_type']
			);

		$enabled_payment_types =
			array();

		if (
			'yes' ===
				$allow_cash_on_delivery
		) {
			$enabled_payment_types[] =
				'cash_on_delivery';
		}

		if (
			'yes' ===
				$allow_advance_payment
		) {
			$enabled_payment_types[] =
				'advance';
		}

		if (
			'yes' ===
				$allow_full_payment
		) {
			$enabled_payment_types[] =
				'full';
		}

		/*
		 * Keep Default Payment Option aligned with the
		 * options currently available to the customer.
		 *
		 * The order intentionally mirrors the backend UI:
		 * Cash on Delivery -> Advance -> Full.
		 */
		if (
			! empty(
				$enabled_payment_types
			) &&
			! in_array(
				$default_payment_type,
				$enabled_payment_types,
				true
			)
		) {
			$default_payment_type =
				(string)
				reset(
					$enabled_payment_types
				);
		}

		/*
		 * If Payment Options is disabled and every child
		 * option is also disabled, keep a valid stored
		 * default for future re-enabling.
		 */
		if (
			empty(
				$enabled_payment_types
			)
		) {
			$default_payment_type =
				$this->sanitize_choice(
					$defaults['default_payment_type'] ??
						'advance',
					$allowed_payment_types,
					'advance'
				);
		}

		$rules =
			array();

		if (
			isset(
				$input['rules']
			) &&
			is_array(
				$input['rules']
			)
		) {
			$rules =
				$this->sanitize_advance_rules(
					$input['rules']
				);
		}

		return array(
			'enabled' =>
				$enabled,

			'display_layout' =>
				$this->sanitize_choice( $input['display_layout'] ?? $defaults['display_layout'] ?? 'grid', array( 'grid', 'list' ), (string) ( $defaults['display_layout'] ?? 'grid' ) ),

			'columns_desktop' => max( 1, min( 4, absint( $input['columns_desktop'] ?? $defaults['columns_desktop'] ?? 3 ) ) ),
			'columns_tablet'  => max( 1, min( 4, absint( $input['columns_tablet'] ?? $defaults['columns_tablet'] ?? 2 ) ) ),
			'columns_mobile'  => max( 1, min( 2, absint( $input['columns_mobile'] ?? $defaults['columns_mobile'] ?? 1 ) ) ),

			'footer_enabled' => $this->sanitize_yes_no( $input['footer_enabled'] ?? $defaults['footer_enabled'] ?? 'yes', (string) ( $defaults['footer_enabled'] ?? 'yes' ) ),
			'footer_text' => sanitize_text_field( (string) ( $input['footer_text'] ?? $defaults['footer_text'] ?? '' ) ),
			'footer_icon' => $this->sanitize_choice( $input['footer_icon'] ?? $defaults['footer_icon'] ?? 'shield', array( 'shield', 'lock', 'none' ), (string) ( $defaults['footer_icon'] ?? 'shield' ) ),

			'title' =>
				sanitize_text_field(
					(string) (
						$input['title'] ??
							$defaults['title']
					)
				),

			'allow_cash_on_delivery' =>
				$allow_cash_on_delivery,

			'allow_advance_payment' =>
				$allow_advance_payment,

			'allow_full_payment' =>
				$allow_full_payment,

			'default_payment_type' =>
				$default_payment_type,

			'calculation_basis' =>
				$this->sanitize_choice(
					$input['calculation_basis'] ??
						$defaults['calculation_basis'],
					$allowed_bases,
					$defaults['calculation_basis']
				),

			'advance_label' =>
				$this->sanitize_template_text(
					$input['advance_label'] ??
						$defaults['advance_label'],
					$defaults['advance_label']
				),

			'advance_card_subtitle' =>
				$this->sanitize_template_text(
					$input['advance_card_subtitle'] ??
						$defaults['advance_card_subtitle'],
					$defaults['advance_card_subtitle']
				),

			'advance_description' =>
				$this->sanitize_template_text(
					$input['advance_description'] ??
						$defaults['advance_description'],
					$defaults['advance_description']
				),

			'empty_description' =>
				$this->sanitize_template_text(
					$input['empty_description'] ??
						$defaults['empty_description'],
					$defaults['empty_description']
				),

			'rules' =>
				$rules,
		);
	}

    /**
     * Sanitize payment-method settings.
     *
     * @param array<string, mixed> $input    Input.
     * @param array<string, mixed> $defaults Defaults.
     *
     * @return array<string, mixed>
     */
    private function sanitize_payment_methods(
        array $input,
        array $defaults
    ): array {

        $cash_input =
            isset( $input['cash_on_delivery'] ) &&
            is_array( $input['cash_on_delivery'] )
                ? $input['cash_on_delivery']
                : array();

        $cash_defaults =
            isset( $defaults['cash_on_delivery'] ) &&
            is_array( $defaults['cash_on_delivery'] )
                ? $defaults['cash_on_delivery']
                : array();

        $bank_input =
            isset( $input['bank_transfer'] ) &&
            is_array( $input['bank_transfer'] )
                ? $input['bank_transfer']
                : array();

        $bank_defaults =
            isset( $defaults['bank_transfer'] ) &&
            is_array( $defaults['bank_transfer'] )
                ? $defaults['bank_transfer']
                : array();

        $gateway_input =
            isset( $input['woocommerce_gateways'] ) &&
            is_array( $input['woocommerce_gateways'] )
                ? $input['woocommerce_gateways']
                : array();

        $gateway_defaults =
            isset( $defaults['woocommerce_gateways'] ) &&
            is_array( $defaults['woocommerce_gateways'] )
                ? $defaults['woocommerce_gateways']
                : array();

        /*
        * Payment Options can submit only the legacy
        * cash_on_delivery presentation fields.
        *
        * Preserve existing custom methods unless the
        * Payment Methods tab actually submits a new
        * custom_methods collection.
        */
        $custom_methods =
            isset( $defaults['custom_methods'] ) &&
            is_array( $defaults['custom_methods'] )
                ? $defaults['custom_methods']
                : array();

        if (
            array_key_exists(
                'custom_methods',
                $input
            ) &&
            is_array(
                $input['custom_methods']
            )
        ) {
            $custom_methods =
                $this->sanitize_custom_payment_methods(
                    $input['custom_methods']
                );
        }

        /*
        * Default Payment Method can point to:
        *
        * first_available
        * bank_transfer
        * or a custom manual method ID.
        *
        * Cash on Delivery is intentionally excluded here.
        * COD is now a top-level Payment Option and must
        * never be selected as the payment method used by
        * Advance or Full Payment.
        *
        * Individual WooCommerce gateways are dynamic,
        * therefore they are not stored here as a
        * permanent default method.
        */
        $allowed_default_methods = array(
            'first_available',
            'bkash',
            'nagad',
            'bacs',
            'eilmo_bkash',
            'eilmo_nagad',
        );

        if (
            'yes' ===
            $this->sanitize_yes_no(
                $bank_input['enabled'] ??
                    $bank_defaults['enabled'] ??
                    'no',
                (string) (
                    $bank_defaults['enabled'] ??
                        'no'
                )
            )
        ) {
            $allowed_default_methods[] =
                'bank_transfer';
        }

        foreach ( $custom_methods as $custom_method ) {

            if (
                ! isset( $custom_method['id'] ) ||
                ! is_string( $custom_method['id'] ) ||
                '' === $custom_method['id']
            ) {
                continue;
            }

            if (
                'yes' !== (
                    $custom_method['enabled'] ??
                        'no'
                )
            ) {
                continue;
            }

            $allowed_default_methods[] =
                $custom_method['id'];
        }

        $default_method =
            sanitize_key(
                (string) (
                    $input['default_method'] ??
                        $defaults['default_method'] ??
                        'first_available'
                )
            );

        if (
            ! in_array(
                $default_method,
                $allowed_default_methods,
                true
            )
        ) {
            $default_method =
                'first_available';
        }

        $title =
            sanitize_text_field(
                (string) (
                    $input['title'] ??
                        $defaults['title'] ??
                        ''
                )
            );

        if ( '' === $title ) {
            $title =
                sanitize_text_field(
                    (string) (
                        $defaults['title'] ??
                            __(
                                'Payment Method',
                                'eilmo-checkout-flow'
                            )
                    )
                );
        }

        return array(
            'enabled' =>
                $this->sanitize_yes_no(
                    $input['enabled'] ??
                        $defaults['enabled'] ??
                        'no',
                    (string) (
                        $defaults['enabled'] ??
                            'no'
                    )
                ),

            'title' =>
                $title,

            'required' =>
                $this->sanitize_yes_no(
                    $input['required'] ??
                        $defaults['required'] ??
                        'yes',
                    (string) (
                        $defaults['required'] ??
                            'yes'
                    )
                ),

            'display_layout' =>
                $this->sanitize_choice(
                    $input['display_layout'] ??
                        $defaults['display_layout'] ??
                        'list',
                    array( 'grid', 'list' ),
                    (string) ( $defaults['display_layout'] ?? 'list' )
                ),

            'columns_desktop' =>
                max(
                    1,
                    min(
                        6,
                        absint(
                            $input['columns_desktop'] ??
                                $defaults['columns_desktop'] ??
                                3
                        )
                    )
                ),

            'columns_tablet' =>
                max(
                    1,
                    min(
                        4,
                        absint(
                            $input['columns_tablet'] ??
                                $defaults['columns_tablet'] ??
                                2
                        )
                    )
                ),

            'columns_mobile' =>
                max(
                    1,
                    min(
                        2,
                        absint(
                            $input['columns_mobile'] ??
                                $defaults['columns_mobile'] ??
                                1
                        )
                    )
                ),

            'default_method' =>
                $default_method,

            /*
            * Backwards-compatible Cash on Delivery
            * presentation data.
            *
            * This is no longer part of the Payment Method
            * choices. Payment Options owns COD eligibility.
            */
            'cash_on_delivery' => array(
                'enabled' =>
                    $this->sanitize_yes_no(
                        $cash_input['enabled'] ??
                            $cash_defaults['enabled'] ??
                            'yes',
                        (string) (
                            $cash_defaults['enabled'] ??
                                'yes'
                        )
                    ),

                'title' =>
                    sanitize_text_field(
                        (string) (
                            $cash_input['title'] ??
                                $cash_defaults['title'] ??
                                ''
                        )
                    ),

                'description' =>
                    sanitize_textarea_field(
                        (string) (
                            $cash_input['description'] ??
                                $cash_defaults['description'] ??
                                ''
                        )
                    ),

                'sort_order' =>
                    absint(
                        $cash_input['sort_order'] ??
                            $cash_defaults['sort_order'] ??
                            10
                    ),
            ),

            /*
            * Fixed Eilmo Bank Transfer.
            */
            'bank_transfer' => array(
                'enabled' =>
                    $this->sanitize_yes_no(
                        $bank_input['enabled'] ??
                            $bank_defaults['enabled'] ??
                            'no',
                        (string) (
                            $bank_defaults['enabled'] ??
                                'no'
                        )
                    ),

                'title' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['title'] ??
                                $bank_defaults['title'] ??
                                ''
                        )
                    ),

                'description' =>
                    sanitize_textarea_field(
                        (string) (
                            $bank_input['description'] ??
                                $bank_defaults['description'] ??
                                ''
                        )
                    ),

                'bank_name' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['bank_name'] ??
                                $bank_defaults['bank_name'] ??
                                ''
                        )
                    ),

                'account_name' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['account_name'] ??
                                $bank_defaults['account_name'] ??
                                ''
                        )
                    ),

                'account_number' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['account_number'] ??
                                $bank_defaults['account_number'] ??
                                ''
                        )
                    ),

                'branch' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['branch'] ??
                                $bank_defaults['branch'] ??
                                ''
                        )
                    ),

                'routing_swift' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['routing_swift'] ??
                                $bank_defaults['routing_swift'] ??
                                ''
                        )
                    ),

                'instructions' =>
                    sanitize_textarea_field(
                        (string) (
                            $bank_input['instructions'] ??
                                $bank_defaults['instructions'] ??
                                ''
                        )
                    ),

                'transaction_id_required' =>
                    $this->sanitize_yes_no(
                        $bank_input['transaction_id_required'] ??
                            $bank_defaults['transaction_id_required'] ??
                            'no',
                        (string) (
                            $bank_defaults['transaction_id_required'] ??
                                'no'
                        )
                    ),

                'payment_proof_enabled' =>
                    $this->sanitize_yes_no(
                        $bank_input['payment_proof_enabled'] ??
                            $bank_defaults['payment_proof_enabled'] ??
                            'no',
                        (string) ( $bank_defaults['payment_proof_enabled'] ?? 'no' )
                    ),

                'payment_proof_label' =>
                    sanitize_text_field(
                        (string) ( $bank_input['payment_proof_label'] ?? $bank_defaults['payment_proof_label'] ?? '' )
                    ),

                'payment_proof_help' =>
                    sanitize_textarea_field(
                        (string) ( $bank_input['payment_proof_help'] ?? $bank_defaults['payment_proof_help'] ?? '' )
                    ),

                'payment_proof_max_mb' =>
                    max( 1, min( 10, absint( $bank_input['payment_proof_max_mb'] ?? $bank_defaults['payment_proof_max_mb'] ?? 5 ) ) ),

                'transaction_id_label' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['transaction_id_label'] ??
                                $bank_defaults['transaction_id_label'] ??
                                ''
                        )
                    ),

                'transaction_id_placeholder' =>
                    sanitize_text_field(
                        (string) (
                            $bank_input['transaction_id_placeholder'] ??
                                $bank_defaults['transaction_id_placeholder'] ??
                                ''
                        )
                    ),

                'sort_order' =>
                    absint(
                        $bank_input['sort_order'] ??
                            $bank_defaults['sort_order'] ??
                            20
                    ),
            ),

            /*
            * WooCommerce gateway integration.
            *
            * This is only an integration source.
            * Actual gateways such as Stripe, PayPal,
            * Razorpay, SSLCommerz etc. are loaded
            * dynamically on the frontend.
            */
            'woocommerce_gateways' => array(
                'enabled' =>
                    $this->sanitize_yes_no(
                        $gateway_input['enabled'] ??
                            $gateway_defaults['enabled'] ??
                            'yes',
                        (string) (
                            $gateway_defaults['enabled'] ??
                                'yes'
                        )
                    ),

                'show_description' =>
                    $this->sanitize_yes_no(
                        $gateway_input['show_description'] ??
                            $gateway_defaults['show_description'] ??
                            'yes',
                        (string) (
                            $gateway_defaults['show_description'] ??
                                'yes'
                        )
                    ),

                /*
                * Prevent WooCommerce COD from being shown
                * twice when Eilmo COD is enabled.
                */
                'exclude_duplicate_cod' =>
                    $this->sanitize_yes_no(
                        $gateway_input['exclude_duplicate_cod'] ??
                            $gateway_defaults['exclude_duplicate_cod'] ??
                            'yes',
                        (string) (
                            $gateway_defaults['exclude_duplicate_cod'] ??
                                'yes'
                        )
                    ),

                /*
                * Prevent WooCommerce BACS from being shown
                * twice when Eilmo Bank Transfer is enabled.
                */
                'exclude_duplicate_bacs' =>
                    $this->sanitize_yes_no(
                        $gateway_input['exclude_duplicate_bacs'] ??
                            $gateway_defaults['exclude_duplicate_bacs'] ??
                            'yes',
                        (string) (
                            $gateway_defaults['exclude_duplicate_bacs'] ??
                                'yes'
                        )
                    ),

                'sort_order' =>
                    absint(
                        $gateway_input['sort_order'] ??
                            $gateway_defaults['sort_order'] ??
                            30
                    ),
            ),

            /*
            * Unlimited custom manual methods.
            */
            'custom_methods' =>
                $custom_methods,
        );
    }

    /**
     * Sanitize custom manual payment methods.
     *
     * These methods are intentionally country-agnostic.
     *
     * Examples:
     * - bKash
     * - Nagad
     * - Rocket
     * - UPI
     * - GCash
     * - M-Pesa
     *
     * @param array<mixed> $methods Payment methods.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_custom_payment_methods(
        array $methods
    ): array {

        $sanitized = array();

        /*
        * Reserved IDs cannot be used by custom methods.
        */
        $used_ids = array(
            'first_available',
            'cash_on_delivery',
            'bank_transfer',
            'woocommerce_gateways',
        );

        foreach ( $methods as $index => $method ) {

            if ( ! is_array( $method ) ) {
                continue;
            }

            $title =
                sanitize_text_field(
                    (string) (
                        $method['title'] ??
                            ''
                    )
                );

            /*
            * A custom payment method without a title
            * is considered incomplete and is skipped.
            */
            if ( '' === $title ) {
                continue;
            }

            $id =
                sanitize_key(
                    (string) (
                        $method['id'] ??
                            ''
                    )
                );

            /*
            * Generate ID from title when admin leaves
            * the ID field empty.
            */
            if ( '' === $id ) {
                $id =
                    sanitize_key(
                        $title
                    );
            }

            if ( '' === $id ) {
                $id =
                    sprintf(
                        'payment-method-%d',
                        absint( $index ) + 1
                    );
            }

            /*
            * Protect fixed/reserved IDs and ensure all
            * custom method IDs remain unique.
            */
            $id =
                $this->make_unique_id(
                    $id,
                    $used_ids
                );

            $used_ids[] =
                $id;

            $transaction_id_required =
                $this->sanitize_yes_no(
                    $method['transaction_id_required'] ??
                        'no',
                    'no'
                );

            $payment_proof_enabled =
                $this->sanitize_yes_no(
                    $method['payment_proof_enabled'] ?? 'no',
                    'no'
                );

            $sanitized[] = array(
                'id' =>
                    $id,

                'enabled' =>
                    $this->sanitize_yes_no(
                        $method['enabled'] ??
                            'yes',
                        'yes'
                    ),

                'title' =>
                    $title,

                'description' =>
                    sanitize_textarea_field(
                        (string) (
                            $method['description'] ??
                                ''
                        )
                    ),

                'instructions' =>
                    sanitize_textarea_field(
                        (string) (
                            $method['instructions'] ??
                                ''
                        )
                    ),

                'account_label' =>
                    sanitize_text_field(
                        (string) (
                            $method['account_label'] ??
                                ''
                        )
                    ),

                'account_value' =>
                    sanitize_text_field(
                        (string) (
                            $method['account_value'] ??
                                ''
                        )
                    ),

                'transaction_id_required' =>
                    $transaction_id_required,

                'payment_proof_enabled' =>
                    $payment_proof_enabled,

                'payment_proof_label' =>
                    sanitize_text_field(
                        (string) ( $method['payment_proof_label'] ?? __( 'Payment Screenshot', 'eilmo-checkout-flow' ) )
                    ),

                'payment_proof_help' =>
                    sanitize_textarea_field(
                        (string) ( $method['payment_proof_help'] ?? __( 'Upload a JPG, PNG or WebP screenshot (maximum 5 MB).', 'eilmo-checkout-flow' ) )
                    ),

                'payment_proof_max_mb' =>
                    max( 1, min( 10, absint( $method['payment_proof_max_mb'] ?? 5 ) ) ),

                'transaction_id_label' =>
                    sanitize_text_field(
                        (string) (
                            $method['transaction_id_label'] ??
                                __(
                                    'Transaction ID',
                                    'eilmo-checkout-flow'
                                )
                        )
                    ),

                'transaction_id_placeholder' =>
                    sanitize_text_field(
                        (string) (
                            $method['transaction_id_placeholder'] ??
                                __(
                                    'Enter transaction ID',
                                    'eilmo-checkout-flow'
                                )
                        )
                    ),

                'icon_url' =>
                    esc_url_raw(
                        (string) (
                            $method['icon_url'] ??
                                ''
                        )
                    ),

                'sort_order' =>
                    absint(
                        $method['sort_order'] ??
                            40
                    ),
            );
        }

        /*
        * Lower sort-order values appear first.
        */
        usort(
            $sanitized,
            static function (
                array $first,
                array $second
            ): int {

                return (int) (
                    $first['sort_order'] ??
                        40
                ) <=>
                    (int) (
                        $second['sort_order'] ??
                            40
                    );
            }
        );

        return array_values(
            $sanitized
        );
    }

    /**
     * Sanitize customer-information settings.
     *
     * @param array<string, mixed> $input    Input.
     * @param array<string, mixed> $defaults Defaults.
     *
     * @return array<string, mixed>
     */
    private function sanitize_customer_information(
        array $input,
        array $defaults
    ): array {

        $input_fields =
            isset(
                $input['fields']
            ) &&
            is_array(
                $input['fields']
            )
                ? $input['fields']
                : array();

        $default_fields =
            isset(
                $defaults['fields']
            ) &&
            is_array(
                $defaults['fields']
            )
                ? $defaults['fields']
                : array();

        $fields = array();

        foreach (
            $default_fields as
            $field_key => $field_defaults
        ) {

            if (
                ! is_array(
                    $field_defaults
                )
            ) {
                continue;
            }

            $field_input =
                isset(
                    $input_fields[ $field_key ]
                ) &&
                is_array(
                    $input_fields[ $field_key ]
                )
                    ? $input_fields[ $field_key ]
                    : array();

            $enabled =
                $this->sanitize_yes_no(
                    $field_input['enabled'] ??
                        $field_defaults['enabled'] ??
                        'no',
                    (string) (
                        $field_defaults['enabled'] ??
                            'no'
                    )
                );

            $required =
                $this->sanitize_yes_no(
                    $field_input['required'] ??
                        $field_defaults['required'] ??
                        'no',
                    (string) (
                        $field_defaults['required'] ??
                            'no'
                    )
                );

            /*
            * Disabled fields cannot be required.
            */
            if ( 'yes' !== $enabled ) {
                $required = 'no';
            }

            $label =
                sanitize_text_field(
                    (string) (
                        $field_input['label'] ??
                            $field_defaults['label'] ??
                            ''
                    )
                );

            if ( '' === $label ) {
                $label =
                    sanitize_text_field(
                        (string) (
                            $field_defaults['label'] ??
                                ''
                        )
                    );
            }

            $placeholder =
                sanitize_text_field(
                    (string) (
                        $field_input['placeholder'] ??
                            $field_defaults['placeholder'] ??
                            ''
                    )
                );

            $fields[ $field_key ] = array(
                'enabled' =>
                    $enabled,

                'required' =>
                    $required,

                'label' =>
                    $label,

                'placeholder' =>
                    $placeholder,

                'width' =>
                    $this->sanitize_choice(
                        $field_input['width'] ??
                            $field_defaults['width'] ??
                            'full',
                        array(
                            'full',
                            'half',
                        ),
                        (string) (
                            $field_defaults['width'] ??
                                'full'
                        )
                    ),

                'sort_order' =>
                    absint(
                        $field_input['sort_order'] ??
                            $field_defaults['sort_order'] ??
                            10
                    ),
            );
        }

        uasort(
            $fields,
            static function (
                array $first,
                array $second
            ): int {

                return (int) (
                    $first['sort_order'] ??
                        10
                ) <=>
                    (int) (
                        $second['sort_order'] ??
                            10
                    );
            }
        );

        $title =
            sanitize_text_field(
                (string) (
                    $input['title'] ??
                        $defaults['title']
                )
            );

        if ( '' === $title ) {
            $title =
                sanitize_text_field(
                    (string) $defaults['title']
                );
        }

        return array(
            'enabled' =>
                $this->sanitize_yes_no(
                    $input['enabled'] ??
                        $defaults['enabled'],
                    $defaults['enabled']
                ),

            'title' =>
                $title,

            'description' =>
                sanitize_textarea_field(
                    (string) (
                        $input['description'] ??
                            $defaults['description']
                    )
                ),

            'show_description' =>
                $this->sanitize_yes_no(
                    $input['show_description'] ??
                        $defaults['show_description'],
                    $defaults['show_description']
                ),

            'autofill_logged_in' =>
                $this->sanitize_yes_no(
                    $input['autofill_logged_in'] ??
                        $defaults['autofill_logged_in'],
                    $defaults['autofill_logged_in']
                ),

            'show_required_mark' =>
                $this->sanitize_yes_no(
                    $input['show_required_mark'] ??
                        $defaults['show_required_mark'],
                    $defaults['show_required_mark']
                ),

            'layout' =>
                $this->sanitize_choice(
                    $input['layout'] ??
                        $defaults['layout'],
                    array(
                        'one_column',
                        'two_columns',
                    ),
                    $defaults['layout']
                ),

            'fields' =>
                $fields,
        );
    }

	/**
	 * Sanitize advance payment rules.
	 *
	 * @param array<mixed> $rules Rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_advance_rules(
		array $rules
	): array {

		$sanitized = array();
		$used_ids  = array();

		$allowed_types = array(
			'fixed',
			'percentage',
			'full_payment',
			'no_advance',
		);

		foreach ( $rules as $index => $rule ) {

			if ( ! is_array( $rule ) ) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$rule['id'] ??
							''
					)
				);

			if ( '' === $id ) {
				$id = sprintf(
					'advance-rule-%d',
					absint( $index ) + 1
				);
			}

			$id =
				$this->make_unique_id(
					$id,
					$used_ids
				);

			$used_ids[] = $id;

			$minimum =
				$this->sanitize_amount(
					$rule['minimum_amount'] ??
						$rule['minimum'] ??
						0
				);

			$maximum =
				$this->sanitize_optional_amount(
					$rule['maximum_amount'] ??
						$rule['maximum'] ??
						''
				);

			if (
				null !== $maximum &&
				$maximum < $minimum
			) {
				$maximum = $minimum;
			}

			$type =
				$this->sanitize_choice(
					$rule['type'] ??
						'fixed',
					$allowed_types,
					'fixed'
				);

			$value =
				$this->sanitize_amount(
					$rule['value'] ??
						0
				);

			if ( 'percentage' === $type ) {
				$value = min(
					100,
					$value
				);
			}

			$minimum_pay =
				$this->sanitize_amount(
					$rule['minimum_pay_amount'] ??
						$rule['minimum_pay'] ??
						0
				);

			$maximum_pay =
				$this->sanitize_optional_amount(
					$rule['maximum_pay_amount'] ??
						$rule['maximum_pay'] ??
						''
				);

			if (
				null !== $maximum_pay &&
				$maximum_pay < $minimum_pay
			) {
				$maximum_pay = $minimum_pay;
			}

			$sanitized[] = array(
				'id' =>
					$id,

				'name' =>
					sanitize_text_field(
						(string) (
							$rule['name'] ??
								''
						)
					),

				'enabled' =>
					$this->sanitize_yes_no(
						$rule['enabled'] ??
							'yes',
						'yes'
					),

				'priority' =>
					absint(
						$rule['priority'] ??
							10
					),

				'minimum_amount' =>
					$minimum,

				/*
				 * 0 means no upper limit.
				 */
				'maximum_amount' =>
					null === $maximum
						? 0.0
						: $maximum,

				'type' =>
					$type,

				'value' =>
					$value,

				'minimum_pay_amount' =>
					$minimum_pay,

				'maximum_pay_amount' =>
					null === $maximum_pay
						? 0.0
						: $maximum_pay,

				'stop_processing' =>
					$this->sanitize_yes_no(
						$rule['stop_processing'] ??
							'yes',
						'yes'
					),
			);
		}

		return $this->sort_rules_by_priority(
			$sanitized
		);
	}

	/**
	 * Sanitize discount settings.
	 *
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $defaults Defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_discounts(
		array $input,
		array $defaults
	): array {

		$automatic_notification_input =
			isset( $input['automatic_notification'] ) &&
			is_array( $input['automatic_notification'] )
				? $input['automatic_notification']
				: array();

		$automatic_notification_defaults =
			isset( $defaults['automatic_notification'] ) &&
			is_array( $defaults['automatic_notification'] )
				? $defaults['automatic_notification']
				: array();

		$automatic_rules =
			isset( $defaults['automatic_rules'] ) && is_array( $defaults['automatic_rules'] )
				? $defaults['automatic_rules']
				: array();

		if (
			isset( $input['automatic_rules'] ) &&
			is_array( $input['automatic_rules'] )
		) {
			$automatic_rules =
				$this->sanitize_discount_rules(
					$input['automatic_rules']
				);
		}

		$full_payment_input =
			isset( $input['full_payment'] ) &&
			is_array( $input['full_payment'] )
				? $input['full_payment']
				: array();

		$full_payment_defaults =
			$defaults['full_payment'];

		$full_payment_type =
			$this->sanitize_choice(
				$full_payment_input['type'] ??
					$full_payment_defaults['type'],
				array(
					'fixed',
					'percentage',
				),
				$full_payment_defaults['type']
			);

		$full_payment_value =
			$this->sanitize_amount(
				$full_payment_input['value'] ??
					$full_payment_defaults['value']
			);

		if ( 'percentage' === $full_payment_type ) {
			$full_payment_value = min(
				100,
				$full_payment_value
			);
		}

		return array(
			'enabled' =>
				$this->sanitize_yes_no(
					$input['enabled'] ??
						$defaults['enabled'],
					$defaults['enabled']
				),

			'stacking_mode' =>
				$this->sanitize_choice(
					$input['stacking_mode'] ??
						$defaults['stacking_mode'],
					array(
						'best_discount',
						'stack_allowed',
						'priority',
					),
					$defaults['stacking_mode']
				),

			'allow_coupon_stacking' =>
				$this->sanitize_yes_no(
					$input['allow_coupon_stacking'] ??
						$defaults['allow_coupon_stacking'],
					$defaults['allow_coupon_stacking']
				),

			/*
			 * Global automatic discount notification.
			 *
			 * Percentage/fixed values and ranges are
			 * dynamically generated from automatic_rules.
			 */
			'automatic_notification' => array(
				'enabled' =>
					$this->sanitize_yes_no(
						$automatic_notification_input['enabled'] ??
							$automatic_notification_defaults['enabled'] ??
							'yes',
						$automatic_notification_defaults['enabled'] ??
							'yes'
					),

				'title' =>
					$this->sanitize_template_text(
						$automatic_notification_input['title'] ??
							$automatic_notification_defaults['title'] ??
							__(
								'Spend More, Save More',
								'eilmo-checkout-flow'
							),
						(string) (
							$automatic_notification_defaults['title'] ??
								__(
									'Spend More, Save More',
									'eilmo-checkout-flow'
								)
						)
					),

				'show_tiers' =>
					$this->sanitize_yes_no(
						$automatic_notification_input['show_tiers'] ??
							$automatic_notification_defaults['show_tiers'] ??
							'yes',
						$automatic_notification_defaults['show_tiers'] ??
							'yes'
					),

				'show_status' =>
					$this->sanitize_yes_no(
						$automatic_notification_input['show_status'] ??
							$automatic_notification_defaults['show_status'] ??
							'yes',
						$automatic_notification_defaults['show_status'] ??
							'yes'
					),

				'empty_status' =>
					$this->sanitize_template_text(
						$automatic_notification_input['empty_status'] ??
							$automatic_notification_defaults['empty_status'] ??
							__(
								'Select products to unlock your discount.',
								'eilmo-checkout-flow'
							),
						(string) (
							$automatic_notification_defaults['empty_status'] ??
								__(
									'Select products to unlock your discount.',
									'eilmo-checkout-flow'
								)
						)
					),

				'active_status' =>
					$this->sanitize_template_text(
						$automatic_notification_input['active_status'] ??
							$automatic_notification_defaults['active_status'] ??
							__(
								'You are getting {discount} OFF.',
								'eilmo-checkout-flow'
							),
						(string) (
							$automatic_notification_defaults['active_status'] ??
								__(
									'You are getting {discount} OFF.',
									'eilmo-checkout-flow'
								)
						)
					),

				'upcoming_status' =>
					$this->sanitize_template_text(
						$automatic_notification_input['upcoming_status'] ??
							$automatic_notification_defaults['upcoming_status'] ??
							__(
								'Add {remaining_amount} more to unlock {discount} OFF.',
								'eilmo-checkout-flow'
							),
						(string) (
							$automatic_notification_defaults['upcoming_status'] ??
								__(
									'Add {remaining_amount} more to unlock {discount} OFF.',
									'eilmo-checkout-flow'
								)
						)
					),
			),

			'automatic_rules' =>
				$automatic_rules,

			'full_payment' => array(
				'enabled' =>
					$this->sanitize_yes_no(
						$full_payment_input['enabled'] ??
							$full_payment_defaults['enabled'],
						$full_payment_defaults['enabled']
					),

				'type' =>
					$full_payment_type,

				'value' =>
					$full_payment_value,

				'minimum_amount' =>
					$this->sanitize_amount(
						$full_payment_input['minimum_amount'] ??
							$full_payment_defaults['minimum_amount']
					),

				'maximum_discount' =>
					$this->sanitize_amount(
						$full_payment_input['maximum_discount'] ??
							$full_payment_defaults['maximum_discount']
					),

				'basis' =>
					$this->sanitize_choice(
						$full_payment_input['basis'] ??
							$full_payment_defaults['basis'],
						array(
							'product_total',
							'discounted_product_total',
							'grand_total',
						),
						$full_payment_defaults['basis']
					),

				'free_delivery' =>
					$this->sanitize_yes_no(
						$full_payment_input['free_delivery'] ??
							$full_payment_defaults['free_delivery'] ??
							'no',
						$full_payment_defaults['free_delivery'] ?? 'no'
					),

				'label' =>
					$this->sanitize_template_text(
						$full_payment_input['label'] ??
							$full_payment_defaults['label'],
						$full_payment_defaults['label']
					),

				'description' =>
					$this->sanitize_template_text(
						$full_payment_input['description'] ??
							$full_payment_defaults['description'],
						$full_payment_defaults['description']
					),

				'discount_badge' =>
					$this->sanitize_template_text(
						$full_payment_input['discount_badge'] ??
							$full_payment_defaults['discount_badge'],
						$full_payment_defaults['discount_badge']
					),

				'free_delivery_badge' =>
					$this->sanitize_template_text(
						$full_payment_input['free_delivery_badge'] ??
							$full_payment_defaults['free_delivery_badge'] ??
							__( 'Free Delivery', 'eilmo-checkout-flow' ),
						$full_payment_defaults['free_delivery_badge'] ??
							__( 'Free Delivery', 'eilmo-checkout-flow' )
					),
			),
		);
	}

	/**
	 * Compile cart-level rewards from the unified Special Discounts library.
	 *
	 * Product gifts remain in the legacy purchase-context adapter. Percentage,
	 * fixed and auto-applied delivery rewards are mirrored into the historical
	 * Automatic Discount calculator so existing totals, summaries and order meta
	 * remain fully compatible.
	 *
	 * @param array<int, array<string, mixed>> $offers Unified rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function compile_automatic_rules_from_special_discounts(
		array $offers
	): array {
		$rules = array();

		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}

			$reward_type = sanitize_key( (string) ( $offer['offer_type'] ?? '' ) );
			$apply_behavior = sanitize_key( (string) ( $offer['apply_behavior'] ?? 'customer_selectable' ) );

			if ( 'percentage_discount' === $reward_type ) {
				$type = 'percentage';
			} elseif ( 'fixed_discount' === $reward_type ) {
				$type = 'fixed';
			} elseif ( 'free_delivery' === $reward_type && 'auto_apply' === $apply_behavior ) {
				$type = 'free_delivery';
			} else {
				continue;
			}

			$locked_text = sanitize_text_field( (string) ( $offer['locked_text'] ?? '' ) );
			if ( '' === trim( $locked_text ) ) {
				$locked_text = 'Add {remaining_amount} more to unlock this reward.';
			}

			$rules[] = array(
				'id'                         => sanitize_key( (string) ( $offer['id'] ?? '' ) ),
				'name'                       => sanitize_text_field( (string) ( $offer['title'] ?? '' ) ),
				'enabled'                    => 'no' === ( $offer['enabled'] ?? 'yes' ) ? 'no' : 'yes',
				'priority'                   => absint( $offer['sort_order'] ?? 10 ),
				'minimum'                    => (float) ( $offer['condition_minimum'] ?? 0 ),
				'maximum'                    => (float) ( $offer['condition_maximum'] ?? 0 ),
				'type'                       => $type,
				'value'                      => 'free_delivery' === $type ? 0.0 : (float) ( $offer['pricing_value'] ?? 0 ),
				'maximum_discount'           => (float) ( $offer['maximum_discount'] ?? 0 ),
				'stop_processing'            => 'no' === ( $offer['stop_processing'] ?? 'yes' ) ? 'no' : 'yes',
				'condition_type'             => sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) ),
				'condition_product_id'       => absint( $offer['condition_product_id'] ?? 0 ),
				'condition_product_quantity' => max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ),
				'condition_cart_quantity'    => max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ),
				'rule_scope'                  => $this->sanitize_choice( $offer['rule_scope'] ?? 'whole_cart', array( 'current_product', 'selected_products', 'whole_cart' ), 'whole_cart' ),
				'scope_product_ids'           => isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $offer['scope_product_ids'] ) ) ) ) : array(),
				'free_delivery_method_id'     => sanitize_key( (string) ( $offer['free_delivery_method_id'] ?? '' ) ),
				'free_delivery_hide_other_methods' => 'yes' === ( $offer['free_delivery_hide_other_methods'] ?? 'no' ) ? 'yes' : 'no',
				'label'                      => sanitize_text_field( (string) ( $offer['label'] ?? 'SPECIAL DISCOUNT' ) ),
				'badge'                      => sanitize_text_field( (string) ( $offer['badge'] ?? '' ) ),
				'description'                => wp_kses_post( (string) ( $offer['description'] ?? '' ) ),
				'applied_text'               => sanitize_text_field( (string) ( $offer['applied_text'] ?? 'Applied' ) ),
				'before_apply_text'          => sanitize_text_field( (string) ( $offer['before_apply_text'] ?? $offer['button_text'] ?? 'Add Reward' ) ),
				'locked_text'                => $locked_text,
				'ineligible_action'          => sanitize_key( (string) ( $offer['ineligible_action'] ?? 'show_locked' ) ),
				'start_at'                   => sanitize_text_field( (string) ( $offer['start_at'] ?? '' ) ),
				'end_at'                     => sanitize_text_field( (string) ( $offer['end_at'] ?? '' ) ),
				'timer_enabled'              => 'no' === ( $offer['timer_enabled'] ?? 'yes' ) ? 'no' : 'yes',
				'image_source'               => sanitize_key( (string) ( $offer['image_source'] ?? 'hidden' ) ),
				'image_media_id'             => absint( $offer['image_media_id'] ?? 0 ),
				'icon_preset'                => $this->sanitize_choice(
					$offer['icon_preset'] ?? 'auto',
					array( 'auto', 'discount', 'delivery', 'gift', 'product' ),
					'auto'
				),
				'image_position'             => $this->sanitize_choice(
					$offer['image_position'] ?? 'center',
					array( 'top', 'center', 'bottom' ),
					'center'
				),
			);
		}

		return $this->sort_rules_by_priority( $rules );
	}

	/**
	 * Sanitize automatic discount rules.
	 *
	 * @param array<mixed> $rules Rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_discount_rules(
		array $rules
	): array {

		$sanitized = array();
		$used_ids  = array();

		$allowed_types = array(
			'fixed',
			'percentage',
			'free_delivery',
		);

		foreach ( $rules as $index => $rule ) {

			if ( ! is_array( $rule ) ) {
				continue;
			}

			$id =
				sanitize_key(
					(string) (
						$rule['id'] ??
							''
					)
				);

			if ( '' === $id ) {
				$id = sprintf(
					'discount-rule-%d',
					absint( $index ) + 1
				);
			}

			$id =
				$this->make_unique_id(
					$id,
					$used_ids
				);

			$used_ids[] = $id;

			$minimum =
				$this->sanitize_amount(
					$rule['minimum'] ??
						0
				);

			$maximum =
				$this->sanitize_optional_amount(
					$rule['maximum'] ??
						''
				);

			if (
				null !== $maximum &&
				$maximum < $minimum
			) {
				$maximum = $minimum;
			}

			$type =
				$this->sanitize_choice(
					$rule['type'] ??
						'percentage',
					$allowed_types,
					'percentage'
				);

			$value =
				$this->sanitize_amount(
					$rule['value'] ??
						0
				);

			if ( 'percentage' === $type ) {
				$value = min(
					100,
					$value
				);
			}

			if ( 'free_delivery' === $type ) {
				$value = 0.0;
			}

			$sanitized[] = array(
				'id' =>
					$id,

				'name' =>
					sanitize_text_field(
						(string) (
							$rule['name'] ??
								''
						)
					),

				'enabled' =>
					$this->sanitize_yes_no(
						$rule['enabled'] ??
							'yes',
						'yes'
					),

				'priority' =>
					absint(
						$rule['priority'] ??
							10
					),

				'minimum' =>
					$minimum,

				'maximum' =>
					$maximum,

				'type' =>
					$type,

				'value' =>
					$value,

				'maximum_discount' =>
					$this->sanitize_amount(
						$rule['maximum_discount'] ??
							0
					),

				'stop_processing' =>
					$this->sanitize_yes_no(
						$rule['stop_processing'] ??
							'yes',
						'yes'
					),
			);
		}

		return $this->sort_rules_by_priority(
			$sanitized
		);
	}

    /**
     * Sanitize coupon settings.
     *
     * @param array<string, mixed> $input    Input.
     * @param array<string, mixed> $defaults Defaults.
     *
     * @return array<string, mixed>
     */
    private function sanitize_coupons(
        array $input,
        array $defaults
    ): array {

        $coupons = array();

        if (
            isset( $input['custom_coupons'] ) &&
            is_array( $input['custom_coupons'] )
        ) {
            $coupons =
                $this->sanitize_custom_coupons(
                    $input['custom_coupons']
                );
        }

        return array(
            'enabled' =>
                $this->sanitize_yes_no(
                    $input['enabled'] ??
                        $defaults['enabled'],
                    $defaults['enabled']
                ),

            'mode' =>
                $this->sanitize_choice(
                    $input['mode'] ??
                        $defaults['mode'],
                    array(
                        'woocommerce',
                        'custom',
                        'both',
                    ),
                    $defaults['mode']
                ),

            'customer_identification' =>
                $this->sanitize_choice(
                    $input['customer_identification'] ??
                        $defaults['customer_identification'],
                    array(
                        'email',
                        'phone',
                        'email_or_phone',
                    ),
                    $defaults['customer_identification']
                ),

            'require_contact_for_customer_limited' =>
                $this->sanitize_yes_no(
                    $input['require_contact_for_customer_limited'] ??
                        $defaults['require_contact_for_customer_limited'],
                    $defaults['require_contact_for_customer_limited']
                ),

            'custom_coupons' =>
                $coupons,
        );
    }

    /**
     * Sanitize custom coupons.
     *
     * @param array<mixed> $coupons Coupons.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_custom_coupons(
        array $coupons
    ): array {

        $sanitized  = array();
        $used_codes = array();

        foreach ( $coupons as $coupon ) {

            if ( ! is_array( $coupon ) ) {
                continue;
            }

            $code = $this->sanitize_coupon_code(
                $coupon['code'] ??
                    ''
            );

            if ( '' === $code ) {
                continue;
            }

            /*
            * Custom coupon codes must be unique.
            */
            if (
                in_array(
                    $code,
                    $used_codes,
                    true
                )
            ) {
                continue;
            }

            $used_codes[] = $code;

            $type =
                $this->sanitize_choice(
                    $coupon['type'] ??
                        'percentage',
                    array(
                        'fixed',
                        'percentage',
                        'free_delivery',
                    ),
                    'percentage'
                );

            $value =
                $this->sanitize_amount(
                    $coupon['value'] ??
                        0
                );

            /*
            * Percentage cannot exceed 100%.
            */
            if ( 'percentage' === $type ) {
                $value = min(
                    100,
                    $value
                );
            }

            /*
            * Free Delivery does not use a monetary
            * discount value.
            */
            if ( 'free_delivery' === $type ) {
                $value = 0.0;
            }

            $minimum_spend =
                $this->sanitize_amount(
                    $coupon['minimum_spend'] ??
                        0
                );

            $maximum_spend =
                $this->sanitize_optional_amount(
                    $coupon['maximum_spend'] ??
                        ''
                );

            /*
            * Do not allow Maximum Spend below
            * Minimum Spend.
            */
            if (
                null !== $maximum_spend &&
                $maximum_spend < $minimum_spend
            ) {
                $maximum_spend =
                    $minimum_spend;
            }

            $maximum_discount =
                $this->sanitize_amount(
                    $coupon['maximum_discount'] ??
                        0
                );

            /*
            * Free Delivery has no monetary
            * maximum-discount cap.
            */
            if ( 'free_delivery' === $type ) {
                $maximum_discount = 0.0;
            }

            $usage_limit =
                absint(
                    $coupon['usage_limit'] ??
                        0
                );

            $per_customer_limit =
                absint(
                    $coupon['usage_limit_per_customer'] ??
                        0
                );

            /*
            * A per-customer limit cannot logically exceed
            * the coupon's global usage limit.
            *
            * 0 still means unlimited.
            */
            if (
                $usage_limit > 0 &&
                $per_customer_limit > $usage_limit
            ) {
                $per_customer_limit =
                    $usage_limit;
            }

            $start_date =
                $this->sanitize_date(
                    $coupon['start_date'] ??
                        ''
                );

            $expiry_date =
                $this->sanitize_date(
                    $coupon['expiry_date'] ??
                        ''
                );

            /*
            * Prevent an expiry date earlier than
            * the configured start date.
            */
            if (
                '' !== $start_date &&
                '' !== $expiry_date &&
                $expiry_date < $start_date
            ) {
                $expiry_date =
                    $start_date;
            }

            $sanitized[] = array(
                'code' =>
                    $code,

                'description' =>
                    sanitize_textarea_field(
                        (string) (
                            $coupon['description'] ??
                                ''
                        )
                    ),

                'enabled' =>
                    $this->sanitize_yes_no(
                        $coupon['enabled'] ??
                            'yes',
                        'yes'
                    ),

                'type' =>
                    $type,

                'value' =>
                    $value,

                'minimum_spend' =>
                    $minimum_spend,

                /*
                * 0 means no maximum spend.
                */
                'maximum_spend' =>
                    null === $maximum_spend
                        ? 0.0
                        : $maximum_spend,

                'maximum_discount' =>
                    $maximum_discount,

                /*
                * 0 means unlimited usage.
                */
                'usage_limit' =>
                    $usage_limit,

                /*
                * 0 means unlimited per customer.
                */
                'usage_limit_per_customer' =>
                    $per_customer_limit,

                'start_date' =>
                    $start_date,

                'expiry_date' =>
                    $expiry_date,

                'product_ids' =>
                    $this->sanitize_id_list(
                        $coupon['product_ids'] ??
                            array()
                    ),

                'excluded_product_ids' =>
                    $this->sanitize_id_list(
                        $coupon['excluded_product_ids'] ??
                            array()
                    ),

                'category_ids' =>
                    $this->sanitize_id_list(
                        $coupon['category_ids'] ??
                            array()
                    ),

                'excluded_category_ids' =>
                    $this->sanitize_id_list(
                        $coupon['excluded_category_ids'] ??
                            array()
                    ),

                'allow_stacking' =>
                    $this->sanitize_yes_no(
                        $coupon['allow_stacking'] ??
                            'no',
                        'no'
                    ),

                'new_customer_only' =>
                    $this->sanitize_yes_no(
                        $coupon['new_customer_only'] ??
                            'no',
                        'no'
                    ),
            );
        }

        return array_values(
            $sanitized
        );
    }


	/**
	 * Sanitize dynamic frontend text.
	 *
	 * Curly-brace placeholders such as {pay_now},
	 * {discount} and {remaining_amount} are preserved
	 * by sanitize_text_field().
	 *
	 * @param mixed  $value    Text.
	 * @param string $fallback Fallback text.
	 *
	 * @return string
	 */
	private function sanitize_template_text(
		$value,
		string $fallback = ''
	): string {

		$value =
			sanitize_text_field(
				(string) $value
			);

		if ( '' === $value ) {
			return sanitize_text_field(
				$fallback
			);
		}

		return $value;
	}


	/**
	 * Get array from parent array.
	 *
	 * @param array<string, mixed> $input Input.
	 * @param string               $key   Key.
	 *
	 * @return array<string, mixed>
	 */
	private function get_array(
		array $input,
		string $key
	): array {

		if (
			! isset( $input[ $key ] ) ||
			! is_array( $input[ $key ] )
		) {
			return array();
		}

		return $input[ $key ];
	}

	/**
	 * Sanitize yes/no value.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_yes_no(
		$value,
		string $fallback = 'no'
	): string {

		if ( 'yes' === $value ) {
			return 'yes';
		}

		if ( 'no' === $value ) {
			return 'no';
		}

		return 'yes' === $fallback
			? 'yes'
			: 'no';
	}

	/**
	 * Sanitize value against allowed choices.
	 *
	 * @param mixed         $value    Value.
	 * @param array<string> $allowed  Allowed values.
	 * @param string        $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_choice(
		$value,
		array $allowed,
		string $fallback
	): string {

		$value =
			sanitize_key(
				(string) $value
			);

		return in_array(
			$value,
			$allowed,
			true
		)
			? $value
			: $fallback;
	}


	/**
	 * Sanitize WooCommerce product quantity.
	 *
	 * @param mixed $value Quantity.
	 *
	 * @return float
	 */
	private function sanitize_quantity(
		$value
	): float {

		if (
			function_exists(
				'wc_stock_amount'
			)
		) {
			$value =
				wc_stock_amount(
					$value
				);
		}

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
	 * Sanitize monetary amount.
	 *
	 * @param mixed $value Value.
	 *
	 * @return float
	 */
	private function sanitize_amount(
		$value
	): float {

		if (
			function_exists(
				'wc_format_decimal'
			)
		) {
			$value =
				wc_format_decimal(
					$value,
					false
				);
		}

		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}

		return max(
			0.0,
			(float) $value
		);
	}

	/**
	 * Sanitize optional monetary amount.
	 *
	 * Empty value means no upper limit.
	 *
	 * @param mixed $value Value.
	 *
	 * @return float|null
	 */
	private function sanitize_optional_amount(
		$value
	): ?float {

		if (
			'' === $value ||
			null === $value
		) {
			return null;
		}

		return $this->sanitize_amount(
			$value
		);
	}


    /**
     * Sanitize coupon code.
     *
     * WooCommerce formatting is used when available so
     * Eilmo custom coupon codes follow the same canonical
     * format as native WooCommerce coupons.
     *
     * @param mixed $value Coupon code.
     *
     * @return string
     */
    private function sanitize_coupon_code(
        $value
    ): string {

        $value = trim(
            (string) $value
        );

        if ( '' === $value ) {
            return '';
        }

        if (
            function_exists(
                'wc_format_coupon_code'
            )
        ) {
            $value = wc_format_coupon_code(
                $value
            );
        } else {
            $value = sanitize_text_field(
                $value
            );
        }

        return strtolower(
            trim(
                $value
            )
        );
    }

	/**
	 * Sanitize date.
	 *
	 * @param mixed $value Date.
	 *
	 * @return string
	 */
	private function sanitize_date(
		$value
	): string {

		$value =
			sanitize_text_field(
				(string) $value
			);

		if ( '' === $value ) {
			return '';
		}

		$date =
			\DateTime::createFromFormat(
				'!Y-m-d',
				$value
			);

		if (
			false === $date ||
			$date->format( 'Y-m-d' ) !==
				$value
		) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitize an ID list.
	 *
	 * Accepts array or comma-separated values.
	 *
	 * @param mixed $values Values.
	 *
	 * @return array<int>
	 */
	private function sanitize_id_list(
		$values
	): array {

		if ( is_string( $values ) ) {
			$values =
				preg_split(
					'/[\s,]+/',
					$values
				);
		}

		if ( ! is_array( $values ) ) {
			return array();
		}

		$ids = array();

		foreach ( $values as $value ) {

			$id =
				absint(
					$value
				);

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values(
			array_unique(
				$ids
			)
		);
	}

	/**
	 * Determine whether collection contains ID.
	 *
	 * @param array<int, array<string, mixed>> $collection Collection.
	 * @param string                           $id         ID.
	 *
	 * @return bool
	 */
	private function collection_contains_id(
		array $collection,
		string $id
	): bool {

		foreach ( $collection as $item ) {

			if (
				isset( $item['id'] ) &&
				$id === $item['id']
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make collection ID unique.
	 *
	 * @param string        $id       Requested ID.
	 * @param array<string> $used_ids Existing IDs.
	 *
	 * @return string
	 */
	private function make_unique_id(
		string $id,
		array $used_ids
	): string {

		if (
			! in_array(
				$id,
				$used_ids,
				true
			)
		) {
			return $id;
		}

		$base    = $id;
		$counter = 2;

		do {

			$id = sprintf(
				'%1$s-%2$d',
				$base,
				$counter
			);

			++$counter;

		} while (
			in_array(
				$id,
				$used_ids,
				true
			)
		);

		return $id;
	}

	/**
	 * Sort rules by priority.
	 *
	 * Lower numbers run first.
	 *
	 * @param array<int, array<string, mixed>> $rules Rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_rules_by_priority(
		array $rules
	): array {

		usort(
			$rules,
			static function (
				array $first,
				array $second
			): int {

				$first_priority =
					isset(
						$first['priority']
					)
						? (int) $first['priority']
						: 10;

				$second_priority =
					isset(
						$second['priority']
					)
						? (int) $second['priority']
						: 10;

				return $first_priority
					<=> $second_priority;
			}
		);

		return array_values(
			$rules
		);
	}
}
