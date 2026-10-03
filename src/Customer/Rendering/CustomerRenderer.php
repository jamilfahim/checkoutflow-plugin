<?php
/**
 * Customer information renderer.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Customer\Rendering;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders configurable customer information.
 */
final class CustomerRenderer {

	/**
	 * Render customer information section.
	 *
	 * @param array<string, mixed> $context Rendering context.
	 *
	 * @return string
	 */
	public function render(
		array $context = array()
	): string {

		$settings =
			$this->get_settings();
        $settings = \EilmoCheckout\Presentation\CheckoutLanguage::defaults( $settings );

		if (
			'yes' !== (
				$settings['enabled'] ??
					'yes'
			)
		) {
			return '';
		}

		$fields =
			$this->get_fields(
				$settings
			);

		if ( empty( $fields ) ) {
			return '';
		}

		$instance_id =
			$this->get_instance_id(
				$context
			);

		$layout =
			'one_column' === (
				$settings['layout'] ??
					'two_columns'
			)
				? 'one_column'
				: 'two_columns';

		$show_description =
			'yes' === (
				$settings['show_description'] ??
					'no'
			);

		$show_required_mark =
			'yes' === (
				$settings['show_required_mark'] ??
					'yes'
			);

		$autofill =
			'yes' === (
				$settings['autofill_logged_in'] ??
					'yes'
			);

		$last_name_enabled =
			isset(
				$fields['last_name']
			) &&
			is_array(
				$fields['last_name']
			) &&
			'yes' === (
				$fields['last_name']['enabled'] ??
					'no'
			);

		ob_start();
		?>
		<section
			class="eilmo-cf-customer-form"
			data-eilmo-customer-form
			data-layout="<?php echo esc_attr( $layout ); ?>"
		>

			<div class="eilmo-cf-customer-form__header">

				<h3 class="eilmo-cf-customer-form__title">
					<?php
					echo esc_html(
						(string) (
							$settings['title'] ??
								''
						)
					);
					?>
				</h3>

				<?php
				if (
					$show_description &&
					'' !== trim(
						(string) (
							$settings['description'] ??
								''
						)
					)
				) :
					?>
					<p class="eilmo-cf-customer-form__description">
						<?php
						echo esc_html(
							(string) $settings['description']
						);
						?>
					</p>
				<?php endif; ?>

			</div>

			<div class="eilmo-cf-customer-form__fields">

				<?php
				foreach (
					$fields as
					$field_key => $field
				) {

					if (
						! is_array( $field ) ||
						'yes' !== (
							$field['enabled'] ??
								'no'
						)
					) {
						continue;
					}

                    $optional_note = 'order_notes' === $field_key && 'yes' !== ( $field['required'] ?? 'no' );
                    if ( $optional_note ) {
                        echo '<details class="eilmo-cf-order-notes"><summary>' . esc_html( \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Add order notes (optional)' ) ) . '</summary>';
                    }
					$this->render_field(
						(string) $field_key,
						$field,
						$instance_id,
						$context,
						$autofill,
						$show_required_mark,
						$last_name_enabled
					);
                    if ( $optional_note ) { echo '</details>'; }
				}
				?>

			</div>

			<?php
			/*
			 * Country is still submitted internally
			 * when its visible field is disabled.
			 *
			 * This is particularly useful for phone
			 * normalization and final WooCommerce
			 * billing data.
			 */
			if (
				isset(
					$fields['country']
				) &&
				is_array(
					$fields['country']
				) &&
				'yes' !== (
					$fields['country']['enabled'] ??
						'no'
				)
			) :

				$hidden_country =
					$this->get_field_value(
						'country',
						$context,
						$autofill
					);
				?>

				<input
					type="hidden"
					name="billing_country"
					value="<?php echo esc_attr( $hidden_country ); ?>"
					data-eilmo-billing-country
					data-eilmo-customer-field="country"
				>

			<?php endif; ?>

			<div
				class="eilmo-cf-customer-form__error"
				data-eilmo-customer-form-error
				role="alert"
				hidden
			></div>

		</section>
		<?php

		$output =
			ob_get_clean();

		return false === $output
			? ''
			: $output;
	}

	/**
	 * Render customer field.
	 *
	 * @param string               $field_key          Field key.
	 * @param array<string, mixed> $field              Field configuration.
	 * @param string               $instance_id        Checkout instance.
	 * @param array<string, mixed> $context            Context.
	 * @param bool                 $autofill           Autofill.
	 * @param bool                 $show_required_mark Show required marker.
	 * @param bool                 $last_name_enabled  Last name enabled.
	 *
	 * @return void
	 */
	private function render_field(
		string $field_key,
		array $field,
		string $instance_id,
		array $context,
		bool $autofill,
		bool $show_required_mark,
		bool $last_name_enabled
	): void {

		$name =
			$this->get_field_name(
				$field_key
			);

		if ( '' === $name ) {
			return;
		}

		$type =
			$this->get_field_type(
				$field_key
			);

		$required =
			'yes' === (
				$field['required'] ??
					'no'
			);

		$width =
			'half' === (
				$field['width'] ??
					'full'
			)
				? 'half'
				: 'full';

		$label =
			sanitize_text_field(
				(string) (
					$field['label'] ??
						''
				)
			);

		$placeholder =
			sanitize_text_field(
				(string) (
					$field['placeholder'] ??
						''
				)
			);

		$value =
			$this->get_field_value(
				$field_key,
				$context,
				$autofill
			);

		$field_id =
			sanitize_html_class(
				$instance_id .
					'-customer-' .
					$field_key
			);

		$error_id =
			$field_id .
			'-error';

		$autocomplete =
			$this->get_autocomplete(
				$field_key,
				$last_name_enabled
			);

		$required_message =
			sprintf(
				/* translators: %s: Customer field label. */
				\EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Please enter %s.' ),
				$label
			);

		$invalid_message =
			'email' === $field_key
				? \EilmoCheckout\Presentation\CheckoutLanguage::copy( 'Please enter a valid email address.' )
				: $required_message;

		?>
		<div
			class="eilmo-cf-customer-field eilmo-cf-customer-field--<?php echo esc_attr( $width ); ?>"
			data-eilmo-customer-field-wrap="<?php echo esc_attr( $field_key ); ?>"
		>

			<label
				class="eilmo-cf-customer-field__label"
				for="<?php echo esc_attr( $field_id ); ?>"
			>
				<?php echo esc_html( $label ); ?>

				<?php if ( $required && $show_required_mark ) : ?>
					<span
						class="eilmo-cf-customer-field__required"
						aria-hidden="true"
					>*</span>
				<?php endif; ?>

			</label>

			<?php if ( 'textarea' === $type ) : ?>

				<textarea
					id="<?php echo esc_attr( $field_id ); ?>"
					class="eilmo-cf-customer-field__control eilmo-cf-customer-field__textarea"
					name="<?php echo esc_attr( $name ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					data-eilmo-customer-field="<?php echo esc_attr( $field_key ); ?>"
					data-required="<?php echo esc_attr( $required ? 'yes' : 'no' ); ?>"
					data-required-message="<?php echo esc_attr( $required_message ); ?>"
					data-invalid-message="<?php echo esc_attr( $invalid_message ); ?>"
					aria-describedby="<?php echo esc_attr( $error_id ); ?>"
					aria-invalid="false"
					rows="3"
					<?php if ( $required ) : ?>
						required
					<?php endif; ?>
				><?php echo esc_textarea( $value ); ?></textarea>

			<?php elseif ( 'country' === $type ) : ?>

				<select
					id="<?php echo esc_attr( $field_id ); ?>"
					class="eilmo-cf-customer-field__control eilmo-cf-customer-field__select"
					name="<?php echo esc_attr( $name ); ?>"
					data-eilmo-customer-field="<?php echo esc_attr( $field_key ); ?>"
					data-eilmo-billing-country
					data-required="<?php echo esc_attr( $required ? 'yes' : 'no' ); ?>"
					data-required-message="<?php echo esc_attr( $required_message ); ?>"
					data-invalid-message="<?php echo esc_attr( $invalid_message ); ?>"
					aria-describedby="<?php echo esc_attr( $error_id ); ?>"
					aria-invalid="false"
					autocomplete="country"
					<?php if ( $required ) : ?>
						required
					<?php endif; ?>
				>

					<?php
					foreach (
						$this->get_countries() as
						$country_code => $country_name
					) :
						?>

						<option
							value="<?php echo esc_attr( (string) $country_code ); ?>"
							<?php
							selected(
								$value,
								(string) $country_code
							);
							?>
						>
							<?php echo esc_html( (string) $country_name ); ?>
						</option>

					<?php endforeach; ?>

				</select>

			<?php else : ?>

				<input
					type="<?php echo esc_attr( $type ); ?>"
					id="<?php echo esc_attr( $field_id ); ?>"
					class="eilmo-cf-customer-field__control"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					data-eilmo-customer-field="<?php echo esc_attr( $field_key ); ?>"
					data-required="<?php echo esc_attr( $required ? 'yes' : 'no' ); ?>"
					data-required-message="<?php echo esc_attr( $required_message ); ?>"
					data-invalid-message="<?php echo esc_attr( $invalid_message ); ?>"
					aria-describedby="<?php echo esc_attr( $error_id ); ?>"
					aria-invalid="false"
					autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
					<?php if ( $required ) : ?>
						required
					<?php endif; ?>

					<?php if ( 'email' === $field_key ) : ?>
						data-eilmo-customer-email
					<?php endif; ?>

					<?php if ( 'phone' === $field_key ) : ?>
						data-eilmo-customer-phone
						inputmode="tel"
					<?php endif; ?>
				>

			<?php endif; ?>

			<span
				id="<?php echo esc_attr( $error_id ); ?>"
				class="eilmo-cf-customer-field__error"
				data-eilmo-customer-error
				hidden
			></span>

		</div>
		<?php
	}

	/**
	 * Get customer-information settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults =
			CheckoutSettings::get_defaults();

		$default_settings =
			isset(
				$defaults['customer_information']
			) &&
			is_array(
				$defaults['customer_information']
			)
				? $defaults['customer_information']
				: array();

		$stored =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$stored_settings =
			is_array( $stored ) &&
			isset(
				$stored['customer_information']
			) &&
			is_array(
				$stored['customer_information']
			)
				? $stored['customer_information']
				: array();

		return array_replace_recursive(
			$default_settings,
			$stored_settings
		);
	}

	/**
	 * Get fields sorted by admin sort order.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_fields(
		array $settings
	): array {

		$fields =
			isset(
				$settings['fields']
			) &&
			is_array(
				$settings['fields']
			)
				? $settings['fields']
				: array();

		$fields =
			array_filter(
				$fields,
				'is_array'
			);

		uasort(
			$fields,
			static function (
				array $first,
				array $second
			): int {

				return absint(
					$first['sort_order'] ??
						10
				) <=>
					absint(
						$second['sort_order'] ??
							10
					);
			}
		);

		return $fields;
	}

	/**
	 * Get field value.
	 *
	 * Explicit rendering context has first priority,
	 * followed by logged-in WooCommerce billing data.
	 *
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $context   Context.
	 * @param bool                 $autofill  Autofill.
	 *
	 * @return string
	 */
	private function get_field_value(
		string $field_key,
		array $context,
		bool $autofill
	): string {

		$field_name =
			$this->get_field_name(
				$field_key
			);

		foreach (
			array(
				$field_key,
				$field_name,
			) as $context_key
		) {

			if (
				'' !== $context_key &&
				array_key_exists(
					$context_key,
					$context
				)
			) {
				return $this->sanitize_field_value(
					$field_key,
					$context[ $context_key ]
				);
			}
		}

		if (
			! $autofill ||
			! is_user_logged_in()
		) {
			return 'country' === $field_key
				? $this->get_base_country()
				: '';
		}

		$user_id =
			get_current_user_id();

		$value =
			'';

		switch ( $field_key ) {

			case 'first_name':
				$value =
					get_user_meta(
						$user_id,
						'billing_first_name',
						true
					);

				if ( '' === (string) $value ) {
					$value =
						get_user_meta(
							$user_id,
							'first_name',
							true
						);
				}
				break;

			case 'last_name':
				$value =
					get_user_meta(
						$user_id,
						'billing_last_name',
						true
					);

				if ( '' === (string) $value ) {
					$value =
						get_user_meta(
							$user_id,
							'last_name',
							true
						);
				}
				break;

			case 'email':
				$value =
					get_user_meta(
						$user_id,
						'billing_email',
						true
					);

				if ( '' === (string) $value ) {

					$user =
						get_userdata(
							$user_id
						);

					$value =
						$user
							? $user->user_email
							: '';
				}
				break;

			case 'order_notes':
				$value =
					'';
				break;

			default:
				$value =
					get_user_meta(
						$user_id,
						'billing_' .
							$field_key,
						true
					);
				break;
		}

		if (
			'country' === $field_key &&
			'' === (string) $value
		) {
			$value =
				$this->get_base_country();
		}

		return $this->sanitize_field_value(
			$field_key,
			$value
		);
	}

	/**
	 * Sanitize field value.
	 *
	 * @param string $field_key Field key.
	 * @param mixed  $value     Value.
	 *
	 * @return string
	 */
	private function sanitize_field_value(
		string $field_key,
		$value
	): string {

		if ( 'email' === $field_key ) {
			return sanitize_email(
				(string) $value
			);
		}

		if ( 'country' === $field_key ) {
			return strtoupper(
				sanitize_key(
					(string) $value
				)
			);
		}

		if ( 'order_notes' === $field_key ) {
			return sanitize_textarea_field(
				(string) $value
			);
		}

		return sanitize_text_field(
			(string) $value
		);
	}

	/**
	 * Get WooCommerce input name.
	 *
	 * @param string $field_key Field key.
	 *
	 * @return string
	 */
	private function get_field_name(
		string $field_key
	): string {

		$field_names = array(
			'first_name' =>
				'billing_first_name',

			'last_name' =>
				'billing_last_name',

			'company' =>
				'billing_company',

			'phone' =>
				'billing_phone',

			'email' =>
				'billing_email',

			'address_1' =>
				'billing_address_1',

			'address_2' =>
				'billing_address_2',

			'city' =>
				'billing_city',

			'state' =>
				'billing_state',

			'postcode' =>
				'billing_postcode',

			'country' =>
				'billing_country',

			'order_notes' =>
				'order_comments',
		);

		return (string) (
			$field_names[ $field_key ] ??
				''
		);
	}

	/**
	 * Get HTML field type.
	 *
	 * @param string $field_key Field key.
	 *
	 * @return string
	 */
	private function get_field_type(
		string $field_key
	): string {

		switch ( $field_key ) {

			case 'email':
				return 'email';

			case 'phone':
				return 'tel';

			case 'country':
				return 'country';

			case 'order_notes':
				return 'textarea';

			default:
				return 'text';
		}
	}

	/**
	 * Get browser autocomplete value.
	 *
	 * @param string $field_key         Field key.
	 * @param bool   $last_name_enabled Last name enabled.
	 *
	 * @return string
	 */
	private function get_autocomplete(
		string $field_key,
		bool $last_name_enabled
	): string {

		$autocomplete = array(
			'first_name' =>
				$last_name_enabled
					? 'given-name'
					: 'name',

			'last_name' =>
				'family-name',

			'company' =>
				'organization',

			'phone' =>
				'tel',

			'email' =>
				'email',

			'address_1' =>
				'address-line1',

			'address_2' =>
				'address-line2',

			'city' =>
				'address-level2',

			'state' =>
				'address-level1',

			'postcode' =>
				'postal-code',
		);

		return (string) (
			$autocomplete[ $field_key ] ??
				'off'
		);
	}

	/**
	 * Get WooCommerce countries.
	 *
	 * @return array<string, string>
	 */
	private function get_countries(): array {

		if (
			function_exists( 'WC' ) &&
			WC() &&
			WC()->countries
		) {
			$countries =
				WC()->countries
					->get_countries();

			return is_array( $countries )
				? $countries
				: array();
		}

		return array();
	}

	/**
	 * Get WooCommerce store country.
	 *
	 * @return string
	 */
	private function get_base_country(): string {

		if (
			function_exists( 'WC' ) &&
			WC() &&
			WC()->countries
		) {
			$country =
				WC()->countries
					->get_base_country();

			return is_string( $country )
				? strtoupper(
					sanitize_key(
						$country
					)
				)
				: '';
		}

		return '';
	}

	/**
	 * Get checkout instance ID.
	 *
	 * @param array<string, mixed> $context Context.
	 *
	 * @return string
	 */
	private function get_instance_id(
		array $context
	): string {

		$instance_id =
			sanitize_html_class(
				(string) (
					$context['instance_id'] ??
						'checkout'
				)
			);

		return '' !== $instance_id
			? $instance_id
			: 'checkout';
	}
}