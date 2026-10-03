<?php
/**
 * Quick Checkout settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes Single Product Quick Checkout
 * settings.
 *
 * The feature master switch lives in the main General
 * settings.
 *
 * All detailed Quick Checkout configuration is stored
 * separately in this option.
 */
final class QuickCheckoutSettings implements RegistrableInterface {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME =
		'eilmo_cf_quick_checkout_settings';

	/**
	 * Option group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP =
		'eilmo_cf_quick_checkout_settings_group';

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
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type' =>
					'array',

				'description' =>
					__(
						'Eilmo Single Product Quick Checkout settings.',
						'eilmo-checkout-flow'
					),

				'sanitize_callback' =>
					array(
						$this,
						'sanitize',
					),

				'default' =>
					self::get_defaults(),

				'show_in_rest' =>
					false,
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {

		$checkout_style_defaults = CheckoutStyle::get_defaults();

		return array(
			'schema_version' =>
				5,

			/*
			 * Presentation.
			 */
			'presentation' => array(

				/*
				 * Supported:
				 *
				 * bottom_sheet
				 * modal
				 */
				'display_mode' =>
					'bottom_sheet',

				/*
				 * Desktop maximum width.
				 */
				'desktop_width' =>
					1180,
			),


			/*
			 * Visual design.
			 *
			 * Quick Checkout owns its own visual source. The default source is the
			 * canonical plugin purple palette and is deliberately independent from
			 * Checkout Settings -> Global Checkout Style. This prevents a custom
			 * campaign/form palette from repainting the product Quick Checkout.
			 * Merchants can still opt into a Quick Checkout-only custom palette.
			 */
			'style' => array(
				'source' => 'default',
				'custom' => $checkout_style_defaults,
			),

			/*
			 * Product page buttons.
			 */
			'buttons' => array(

				'show_order_button' =>
					'yes',

				'show_whatsapp_button' =>
					'yes',
			),

			/*
			 * Product page button appearance.
			 *
			 * These styles apply only to the two
			 * Single Product Quick Checkout action
			 * buttons:
			 *
			 * - Order Now.
			 * - Order via WhatsApp.
			 *
			 * Drawer Place Order styling remains
			 * independent.
			 */
			'button_styles' => array(

				'order' => array(

					'background_color' =>
						(string) $checkout_style_defaults['primary'],

					'text_color' =>
						'#ffffff',

					'border_color' =>
						(string) $checkout_style_defaults['primary'],

					'hover_background_color' =>
						(string) $checkout_style_defaults['primary_hover'],

					'hover_text_color' =>
						'#ffffff',

					'hover_border_color' =>
						(string) $checkout_style_defaults['primary_hover'],

					'border_width' =>
						1,

					'border_radius' =>
						4,
				),

				'whatsapp' => array(

					'background_color' =>
						'#25d366',

					'text_color' =>
						'#ffffff',

					'border_color' =>
						'#25d366',

					'hover_background_color' =>
						'#1ebe5d',

					'hover_text_color' =>
						'#ffffff',

					'hover_border_color' =>
						'#1ebe5d',

					'border_width' =>
						1,

					'border_radius' =>
						4,
				),
			),

			/*
			 * Quick Checkout content.
			 *
			 * These switches only control Quick Checkout.
			 * They do not enable globally disabled
			 * Eilmo features.
			 */
			'content' => array(

				'show_product_summary' =>
					'yes',

				/*
				 * Combo Offers.
				 *
				 * Offer IDs are the existing text-based
				 * Offer ID values configured in Eilmo.
				 */
				'show_combo_offers' =>
					'yes',

				'combo_offer_ids' =>
					array(),

				/*
				 * Special Offers / Order Bumps.
				 *
				 * Offer IDs are also text-based IDs.
				 */
				'show_order_bumps' =>
					'yes',

				'order_bump_ids' =>
					array(),

				'show_discounts' =>
					'yes',

				'show_coupons' =>
					'no',

				'show_delivery' =>
					'yes',

				'show_customer_information' =>
					'yes',

				'show_payment_methods' =>
					'yes',

				'show_advance_payment' =>
					'yes',
			),

			/*
			 * Customer-facing text.
			 */
			'texts' => array(

				'product_order_button' =>
					__(
						'Order Now',
						'eilmo-checkout-flow'
					),

				'product_whatsapp_button' =>
					__(
						'Order via WhatsApp',
						'eilmo-checkout-flow'
					),

				'drawer_title' =>
					__(
						'Almost Done!',
						'eilmo-checkout-flow'
					),

				'drawer_description' =>
					__(
						'Review your selection and complete your order.',
						'eilmo-checkout-flow'
					),

				'selection_heading' =>
					__(
						'Your Selection',
						'eilmo-checkout-flow'
					),

				'change_selection' =>
					__(
						'Change',
						'eilmo-checkout-flow'
					),

				'offer_heading' =>
					__(
						'Complete Your Order',
						'eilmo-checkout-flow'
					),

				'combo_heading' =>
					__(
						'Special Combo Offer',
						'eilmo-checkout-flow'
					),

				'order_bump_heading' =>
					__(
						'You May Also Like',
						'eilmo-checkout-flow'
					),

				'recommended_badge' =>
					__(
						'Recommended',
						'eilmo-checkout-flow'
					),

				'more_offers' =>
					__(
						'View More Offers',
						'eilmo-checkout-flow'
					),

				'delivery_heading' =>
					__(
						'Delivery',
						'eilmo-checkout-flow'
					),

				'customer_heading' =>
					__(
						'Customer Information',
						'eilmo-checkout-flow'
					),

				'payment_heading' =>
					__(
						'Payment Method',
						'eilmo-checkout-flow'
					),

				'advance_payment_heading' =>
					__(
						'Payment Option',
						'eilmo-checkout-flow'
					),

				'coupon_heading' =>
					__(
						'Coupon',
						'eilmo-checkout-flow'
					),

				'summary_heading' =>
					__(
						'Order Summary',
						'eilmo-checkout-flow'
					),

				'total_label' =>
					__(
						'Total',
						'eilmo-checkout-flow'
					),

				'order_button' =>
					__(
						'Place Order',
						'eilmo-checkout-flow'
					),

				'whatsapp_button' =>
					__(
						'Continue on WhatsApp',
						'eilmo-checkout-flow'
					),

				'added_label' =>
					__(
						'Added to your order',
						'eilmo-checkout-flow'
					),

				'variation_required' =>
					__(
						'Please select product options first.',
						'eilmo-checkout-flow'
					),

				'close_label' =>
					__(
						'Close',
						'eilmo-checkout-flow'
					),
			),
		);
	}

	/**
	 * Get stored settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {

		$stored =
			get_option(
				self::OPTION_NAME,
				array()
			);

		if (
			! is_array(
				$stored
			)
		) {
			$stored =
				array();
		}

		$schema_version =
			absint(
				$stored[
					'schema_version'
				] ?? 0
			);

		/*
		 * Version 2 introduced the wider Quick Checkout shell. Version 3 normalizes
		 * untouched legacy widths to the production two-column 1180px layout.
		 */
		if ( $schema_version < 2 ) {
			if ( ! isset( $stored['presentation'] ) || ! is_array( $stored['presentation'] ) ) {
				$stored['presentation'] = array();
			}

			$stored_width = absint( $stored['presentation']['desktop_width'] ?? 0 );
			if ( 0 === $stored_width || $stored_width <= 800 ) {
				$stored['presentation']['desktop_width'] = 1180;
			}
		}

		/*
		 * Version 3 moves Quick Checkout onto the shared checkout visual
		 * architecture. Existing installs inherit their current global style,
		 * so the migration is visual-only and does not alter checkout data.
		 */
		if ( $schema_version < 3 ) {
			if ( ! isset( $stored['style'] ) || ! is_array( $stored['style'] ) ) {
				$stored['style'] = array( 'source' => 'global' );
			}

			$stored['schema_version'] = 3;
		}

		/*
		 * Version 4 aligns untouched Quick Checkout defaults with the canonical
		 * Eilmo purple palette. Custom merchant colours and WhatsApp brand colours
		 * are intentionally preserved.
		 */
		if ( $schema_version < 4 ) {
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
				'success'                    => '#16a34a',
				'warning'                    => '#d97706',
				'danger'                     => '#dc2626',
				'badge_background'           => '#274c3d',
				'badge_text'                 => '#ffffff',
				'payment_badge_background'   => '#7c3aed',
				'payment_badge_text'         => '#ffffff',
				'button_text'                => '#ffffff',
				'gradient_enabled'           => 'no',
				'gradient_end'               => '#355f4e',
			);
			$custom = isset( $stored['style']['custom'] ) && is_array( $stored['style']['custom'] )
				? $stored['style']['custom']
				: array();
			$untouched_green = ! empty( $custom );

			foreach ( $old_green as $key => $value ) {
				if ( ! isset( $custom[ $key ] ) || strtolower( (string) $custom[ $key ] ) !== $value ) {
					$untouched_green = false;
					break;
				}
			}

			if ( $untouched_green ) {
				$purple = CheckoutStyle::get_defaults();
				foreach ( array_keys( $old_green ) as $key ) {
					$stored['style']['custom'][ $key ] = $purple[ $key ];
				}
			}

			$old_order_button = array(
				'background_color'       => '#2563eb',
				'text_color'             => '#ffffff',
				'border_color'           => '#2563eb',
				'hover_background_color' => '#1d4ed8',
				'hover_text_color'       => '#ffffff',
				'hover_border_color'     => '#1d4ed8',
				'border_width'           => 1,
				'border_radius'          => 4,
			);
			$saved_order_button = isset( $stored['button_styles']['order'] ) && is_array( $stored['button_styles']['order'] )
				? $stored['button_styles']['order']
				: array();

			if ( $old_order_button == $saved_order_button ) {
				$stored['button_styles']['order'] = self::get_defaults()['button_styles']['order'];
			}

			$stored['schema_version'] = 4;
			update_option( self::OPTION_NAME, $stored, false );
		}

		/*
		 * Version 5 fully separates Quick Checkout from the campaign/form
		 * Global Checkout Style. Older builds used `source = global`, which
		 * intentionally called CheckoutStyle::get(); that is why a merchant's
		 * green Custom checkout form theme also made Quick Checkout green even
		 * though the CSS custom-property names had already been isolated.
		 *
		 * Migrate that legacy source to `default`: the canonical plugin purple
		 * palette. Existing Quick Checkout custom colours remain untouched.
		 */
		if ( $schema_version < 5 ) {
			if ( ! isset( $stored['style'] ) || ! is_array( $stored['style'] ) ) {
				$stored['style'] = array();
			}

			$legacy_source = sanitize_key( (string) ( $stored['style']['source'] ?? 'global' ) );
			if ( 'global' === $legacy_source || '' === $legacy_source ) {
				$stored['style']['source'] = 'default';
			}

			$stored['schema_version'] = 5;
			update_option( self::OPTION_NAME, $stored, false );
		}

		return array_replace_recursive(
			self::get_defaults(),
			$stored
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Submitted settings.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize(
		$input
	): array {

		$defaults =
			self::get_defaults();

		if (
			! is_array(
				$input
			)
		) {
			return $defaults;
		}

		$presentation =
			$this->get_array(
				$input,
				'presentation'
			);

		$style =
			$this->get_array(
				$input,
				'style'
			);

		$buttons =
			$this->get_array(
				$input,
				'buttons'
			);

		$button_styles =
			$this->get_array(
				$input,
				'button_styles'
			);

		$content =
			$this->get_array(
				$input,
				'content'
			);

		$texts =
			$this->get_array(
				$input,
				'texts'
			);

		/*
		 * Display mode.
		 */
		$display_mode =
			sanitize_key(
				(string) (
					$presentation[
						'display_mode'
					] ??
						$defaults[
							'presentation'
						][
							'display_mode'
						]
				)
			);

		if (
			! in_array(
				$display_mode,
				array(
					'bottom_sheet',
					'modal',
				),
				true
			)
		) {
			$display_mode =
				'bottom_sheet';
		}

		/*
		 * Desktop width.
		 */
		$desktop_width =
			absint(
				$presentation[
					'desktop_width'
				] ??
					$defaults[
						'presentation'
					][
						'desktop_width'
					]
			);

		$desktop_width =
			max(
				1040,
				min(
					1200,
					$desktop_width
				)
			);

		$order_button_style =
			$this->sanitize_button_style(
				$this->get_array(
					$button_styles,
					'order'
				),
				$defaults[
					'button_styles'
				][
					'order'
				]
			);

		$whatsapp_button_style =
			$this->sanitize_button_style(
				$this->get_array(
					$button_styles,
					'whatsapp'
				),
				$defaults[
					'button_styles'
				][
					'whatsapp'
				]
			);

		$style_source = sanitize_key( (string) ( $style['source'] ?? 'default' ) );
		if ( 'global' === $style_source ) {
			// Backward compatibility for forms opened before the schema-5 migration.
			$style_source = 'default';
		}
		if ( ! in_array( $style_source, array( 'default', 'custom' ), true ) ) {
			$style_source = 'default';
		}

		$custom_style = CheckoutStyle::sanitize(
			$this->get_array( $style, 'custom' ),
			CheckoutStyle::get_defaults()
		);

		return array(
			'schema_version' =>
				5,

			'presentation' => array(

				'display_mode' =>
					$display_mode,

				'desktop_width' =>
					$desktop_width,
			),

			'style' => array(
				'source' => $style_source,
				'custom' => $custom_style,
			),

			'buttons' => array(

				'show_order_button' =>
					$this->sanitize_yes_no(
						$buttons[
							'show_order_button'
						] ??
							null,
						(string) $defaults[
							'buttons'
						][
							'show_order_button'
						]
					),

				'show_whatsapp_button' =>
					$this->sanitize_yes_no(
						$buttons[
							'show_whatsapp_button'
						] ??
							null,
						(string) $defaults[
							'buttons'
						][
							'show_whatsapp_button'
						]
					),
			),

			'button_styles' => array(

				'order' =>
					$order_button_style,

				'whatsapp' =>
					$whatsapp_button_style,
			),

			'content' => array(

				'show_product_summary' =>
					$this->sanitize_yes_no(
						$content[
							'show_product_summary'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_product_summary'
						]
					),

				'show_combo_offers' =>
					$this->sanitize_yes_no(
						$content[
							'show_combo_offers'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_combo_offers'
						]
					),

				'combo_offer_ids' =>
					$this->sanitize_offer_ids(
						$content[
							'combo_offer_ids'
						] ??
							array()
					),

				'show_order_bumps' =>
					$this->sanitize_yes_no(
						$content[
							'show_order_bumps'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_order_bumps'
						]
					),

				'order_bump_ids' =>
					$this->sanitize_offer_ids(
						$content[
							'order_bump_ids'
						] ??
							array()
					),

				'show_discounts' =>
					$this->sanitize_yes_no(
						$content[
							'show_discounts'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_discounts'
						]
					),

				'show_coupons' =>
					$this->sanitize_yes_no(
						$content[
							'show_coupons'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_coupons'
						]
					),

				'show_delivery' =>
					$this->sanitize_yes_no(
						$content[
							'show_delivery'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_delivery'
						]
					),

				'show_customer_information' =>
					$this->sanitize_yes_no(
						$content[
							'show_customer_information'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_customer_information'
						]
					),

				'show_payment_methods' =>
					$this->sanitize_yes_no(
						$content[
							'show_payment_methods'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_payment_methods'
						]
					),

				'show_advance_payment' =>
					$this->sanitize_yes_no(
						$content[
							'show_advance_payment'
						] ??
							null,
						(string) $defaults[
							'content'
						][
							'show_advance_payment'
						]
					),
			),

			'texts' =>
				$this->sanitize_texts(
					$texts,
					$defaults[
						'texts'
					]
				),
		);
	}

	/**
	 * Sanitize one product-page button style.
	 *
	 * @param array<string,mixed> $input    Submitted style.
	 * @param array<string,mixed> $defaults Default style.
	 *
	 * @return array<string,mixed>
	 */
	private function sanitize_button_style(
		array $input,
		array $defaults
	): array {

		return array(

			'background_color' =>
				$this->sanitize_color(
					$input[
						'background_color'
					] ??
						null,
					(string) $defaults[
						'background_color'
					]
				),

			'text_color' =>
				$this->sanitize_color(
					$input[
						'text_color'
					] ??
						null,
					(string) $defaults[
						'text_color'
					]
				),

			'border_color' =>
				$this->sanitize_color(
					$input[
						'border_color'
					] ??
						null,
					(string) $defaults[
						'border_color'
					]
				),

			'hover_background_color' =>
				$this->sanitize_color(
					$input[
						'hover_background_color'
					] ??
						null,
					(string) $defaults[
						'hover_background_color'
					]
				),

			'hover_text_color' =>
				$this->sanitize_color(
					$input[
						'hover_text_color'
					] ??
						null,
					(string) $defaults[
						'hover_text_color'
					]
				),

			'hover_border_color' =>
				$this->sanitize_color(
					$input[
						'hover_border_color'
					] ??
						null,
					(string) $defaults[
						'hover_border_color'
					]
				),

			'border_width' =>
				$this->sanitize_integer_range(
					$input[
						'border_width'
					] ??
						null,
					0,
					10,
					(int) $defaults[
						'border_width'
					]
				),

			'border_radius' =>
				$this->sanitize_integer_range(
					$input[
						'border_radius'
					] ??
						null,
					0,
					100,
					(int) $defaults[
						'border_radius'
					]
				),
		);
	}

	/**
	 * Sanitize hexadecimal color.
	 *
	 * Invalid or empty values fall back to the
	 * configured default.
	 *
	 * @param mixed  $value    Color.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private function sanitize_color(
		$value,
		string $fallback
	): string {

		$value =
			is_scalar(
				$value
			)
				? trim(
					(string) $value
				)
				: '';

		if (
			'' !==
				$value
		) {
			$sanitized =
				sanitize_hex_color(
					$value
				);

			if (
				is_string(
					$sanitized
				) &&
				'' !==
					$sanitized
			) {
				return strtolower(
					$sanitized
				);
			}
		}

		$fallback_sanitized =
			sanitize_hex_color(
				$fallback
			);

		if (
			is_string(
				$fallback_sanitized
			) &&
			'' !==
				$fallback_sanitized
		) {
			return strtolower(
				$fallback_sanitized
			);
		}

		return '#6d28d9';
	}

	/**
	 * Sanitize integer within a range.
	 *
	 * @param mixed $value    Value.
	 * @param int   $minimum  Minimum.
	 * @param int   $maximum  Maximum.
	 * @param int   $fallback Fallback.
	 *
	 * @return int
	 */
	private function sanitize_integer_range(
		$value,
		int $minimum,
		int $maximum,
		int $fallback
	): int {

		if (
			! is_numeric(
				$value
			)
		) {
			return max(
				$minimum,
				min(
					$maximum,
					$fallback
				)
			);
		}

		$value =
			(int) $value;

		return max(
			$minimum,
			min(
				$maximum,
				$value
			)
		);
	}

	/**
	 * Sanitize configured text Offer IDs.
	 *
	 * Supports:
	 *
	 * - Comma-separated string.
	 * - Line-separated string.
	 * - Existing array values.
	 *
	 * Example:
	 *
	 * combo-one, gaming-bundle, special-deal
	 *
	 * @param mixed $value Offer IDs.
	 *
	 * @return array<int,string>
	 */
	private function sanitize_offer_ids(
		$value
	): array {

		if (
			is_string(
				$value
			)
		) {
			$value =
				preg_split(
					'/[\r\n,]+/',
					$value
				);

			if (
				false ===
					$value
			) {
				$value =
					array();
			}
		}

		if (
			! is_array(
				$value
			)
		) {
			return array();
		}

		$offer_ids =
			array();

		foreach (
			$value as $offer_id
		) {

			if (
				! is_scalar(
					$offer_id
				)
			) {
				continue;
			}

			$offer_id =
				sanitize_key(
					trim(
						(string) $offer_id
					)
				);

			if (
				'' ===
					$offer_id
			) {
				continue;
			}

			$offer_ids[] =
				$offer_id;
		}

		return array_values(
			array_unique(
				$offer_ids
			)
		);
	}

	/**
	 * Sanitize editable frontend text.
	 *
	 * Empty fields fall back to defaults.
	 *
	 * @param array<string,mixed> $input    Input.
	 * @param array<string,mixed> $defaults Defaults.
	 *
	 * @return array<string,string>
	 */
	private function sanitize_texts(
		array $input,
		array $defaults
	): array {

		$output =
			array();

		foreach (
			$defaults as
				$key =>
				$default
		) {

			$value =
				sanitize_text_field(
					(string) (
						$input[
							$key
						] ??
							''
					)
				);

			if (
				'' ===
					trim(
						$value
					)
			) {
				$value =
					(string) $default;
			}

			$output[
				(string) $key
			] =
				$value;
		}

		return $output;
	}

	/**
	 * Sanitize yes/no.
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

		$value =
			sanitize_key(
				(string) $value
			);

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

		return (
			'yes' ===
				$fallback
		)
			? 'yes'
			: 'no';
	}

	/**
	 * Get nested array.
	 *
	 * @param array<string,mixed> $source Source.
	 * @param string              $key    Key.
	 *
	 * @return array<string,mixed>
	 */
	private function get_array(
		array $source,
		string $key
	): array {

		if (
			! isset(
				$source[
					$key
				]
			) ||
			! is_array(
				$source[
					$key
				]
			)
		) {
			return array();
		}

		return $source[
			$key
		];
	}
}
