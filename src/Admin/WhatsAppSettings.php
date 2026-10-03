<?php
/**
 * WhatsApp Ordering settings.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WhatsApp Ordering settings.
 */
final class WhatsAppSettings implements RegistrableInterface {

	/**
	 * Option name.
	 */
	public const OPTION_NAME =
		'eilmo_cf_whatsapp_settings';

	/**
	 * Settings group.
	 */
	public const OPTION_GROUP =
		'eilmo_cf_whatsapp_settings_group';

	/**
	 * Register settings.
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
	 * Register WordPress setting.
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

				'sanitize_callback' =>
					array(
						$this,
						'sanitize',
					),

				'default' =>
					self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize(
		$input
	): array {

		$input =
			is_array(
				$input
			)
				? $input
				: array();

		$number =
			(string) (
				$input[
					'number'
				] ??
					''
			);

		$number =
			preg_replace(
				'/[^0-9]/',
				'',
				$number
			);

		if (
			! is_string(
				$number
			)
		) {
			$number =
				'';
		}

		$defaults =
			self::get_defaults();

		$button_label =
			sanitize_text_field(
				(string) (
					$input[
						'button_label'
					] ??
						''
				)
			);

		if (
			'' === $button_label
		) {
			$button_label =
				(string) $defaults[
					'button_label'
				];
		}

		$message_template =
			sanitize_textarea_field(
				(string) (
					$input[
						'message_template'
					] ??
						''
				)
			);

		if (
			'' ===
				trim(
					$message_template
				)
		) {
			$message_template =
				(string) $defaults[
					'message_template'
				];
		}

		$placement = sanitize_key( (string) ( $input['placement'] ?? $defaults['placement'] ?? 'below_summary' ) );
		if ( ! in_array( $placement, array( 'below_payment', 'below_summary', 'both' ), true ) ) {
			$placement = 'below_summary';
		}

		$style_input = isset( $input['style'] ) && is_array( $input['style'] )
			? $input['style']
			: array();
		$style_defaults = isset( $defaults['style'] ) && is_array( $defaults['style'] )
			? $defaults['style']
			: array();
		$style = array();

		foreach ( array( 'background', 'hover_background', 'active_background', 'text', 'border', 'focus_ring' ) as $key ) {
			$value = sanitize_hex_color( (string) ( $style_input[ $key ] ?? '' ) );
			$style[ $key ] = $value ?: (string) ( $style_defaults[ $key ] ?? '#25d366' );
		}

		$style['radius'] = is_numeric( $style_input['radius'] ?? null )
			? max( 0, min( 40, (int) $style_input['radius'] ) )
			: (int) ( $style_defaults['radius'] ?? 12 );

		return array(
			'number' =>
				$number,

			'button_label' =>
				$button_label,

			'placement' =>
				$placement,

			'message_template' =>
				$message_template,

			'style' =>
				$style,
		);
	}

	/**
	 * Get saved settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {

		$saved =
			get_option(
				self::OPTION_NAME,
				array()
			);

		$saved =
			is_array(
				$saved
			)
				? $saved
				: array();

		return wp_parse_args(
			$saved,
			self::get_defaults()
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {

		return array(
			'number' =>
				'',

			'button_label' =>
				__(
					'Order via WhatsApp',
					'eilmo-checkout-flow'
				),

			'placement' =>
				'below_summary',

			'message_template' =>
				__(
					"Hello, I would like to place an order.\n\nCustomer: {customer_name}\nPhone: {phone}\nEmail: {email}\nAddress: {address}\n\nProducts:\n{products}\n\nDelivery: {delivery}\nPayment: {payment}\nTotal: {total}\n\nCheckout Page: {checkout_url}",
					'eilmo-checkout-flow'
				),

			'style' => array(
				'background'       => '#25d366',
				'hover_background' => '#20bd5a',
				'active_background' => '#1da851',
				'text'             => '#ffffff',
				'border'           => '#25d366',
				'focus_ring'       => '#25d366',
				'radius'           => 12,
			),
		);
	}

	/**
	 * Build integration-owned frontend style variables.
	 *
	 * @param array<string, mixed>|null $style Optional style values.
	 *
	 * @return string
	 */
	public static function get_style_css_variables( ?array $style = null ): string {

		$settings = self::get();
		$defaults = self::get_defaults()['style'];
		$style = is_array( $style )
			? array_replace_recursive( $defaults, $style )
			: ( isset( $settings['style'] ) && is_array( $settings['style'] )
				? array_replace_recursive( $defaults, $settings['style'] )
				: $defaults );

		$variables = array(
			'--eilmo-whatsapp-background'        => sanitize_hex_color( (string) $style['background'] ) ?: $defaults['background'],
			'--eilmo-whatsapp-hover-background'  => sanitize_hex_color( (string) $style['hover_background'] ) ?: $defaults['hover_background'],
			'--eilmo-whatsapp-active-background' => sanitize_hex_color( (string) $style['active_background'] ) ?: $defaults['active_background'],
			'--eilmo-whatsapp-text'              => sanitize_hex_color( (string) $style['text'] ) ?: $defaults['text'],
			'--eilmo-whatsapp-border'            => sanitize_hex_color( (string) $style['border'] ) ?: $defaults['border'],
			'--eilmo-whatsapp-focus-ring'        => sanitize_hex_color( (string) $style['focus_ring'] ) ?: $defaults['focus_ring'],
			'--eilmo-whatsapp-radius'            => max( 0, min( 40, (int) $style['radius'] ) ) . 'px',
		);

		$output = '';
		foreach ( $variables as $name => $value ) {
			$output .= $name . ':' . $value . ';';
		}

		return $output;
	}
}
