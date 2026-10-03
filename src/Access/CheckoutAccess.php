<?php
/**
 * Checkout access control.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Access;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use EilmoCheckout\Admin\SecuritySettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use WC_Customer;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Controls checkout order access.
 *
 * Access settings now live under SecuritySettings and are
 * shared by:
 *
 * - Eilmo Checkout Flow.
 * - WooCommerce Classic Checkout.
 * - WooCommerce Checkout Block / Store API.
 *
 * Checkout content may remain visible. The final order
 * action is always protected server-side.
 */
final class CheckoutAccess implements RegistrableInterface {

	/**
	 * Everyone mode.
	 */
	public const MODE_EVERYONE =
		'everyone';

	/**
	 * Guest-only mode.
	 */
	public const MODE_GUEST_ONLY =
		'guest_only';

	/**
	 * Logged-in-only mode.
	 */
	public const MODE_LOGGED_IN_ONLY =
		'logged_in_only';

	/**
	 * Final Eilmo order AJAX action.
	 */
	private const ORDER_AJAX_ACTION =
		'eilmo_cf_create_order';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {

		/*
		 * ---------------------------------------------
		 * Eilmo Checkout Flow
		 * ---------------------------------------------
		 */
		add_action(
			'wp_ajax_' .
				self::ORDER_AJAX_ACTION,
			array(
				$this,
				'guard_order_request',
			),
			1
		);

		add_action(
			'wp_ajax_nopriv_' .
				self::ORDER_AJAX_ACTION,
			array(
				$this,
				'guard_order_request',
			),
			1
		);

		/*
		 * ---------------------------------------------
		 * WooCommerce Classic Checkout
		 * ---------------------------------------------
		 */
		add_action(
			'woocommerce_after_checkout_validation',
			array(
				$this,
				'guard_classic_checkout',
			),
			5,
			2
		);

		/*
		 * ---------------------------------------------
		 * WooCommerce Checkout Block / Store API
		 * ---------------------------------------------
		 */
		add_action(
			'woocommerce_store_api_checkout_update_customer_from_request',
			array(
				$this,
				'guard_store_api_checkout',
			),
			5,
			2
		);
	}

	/**
	 * Protect final Eilmo order request.
	 *
	 * Rendering a login link improves UX, but direct AJAX
	 * requests are still rejected here.
	 *
	 * @return void
	 */
	public function guard_order_request(): void {

		$error =
			$this->get_access_error();

		if ( ! $error instanceof WP_Error ) {
			return;
		}

		$data =
			array(
				'error_code' =>
					sanitize_key(
						(string) $error
							->get_error_code()
					),

				'message' =>
					sanitize_text_field(
						(string) $error
							->get_error_message()
					),
			);

		if ( $this->requires_login() ) {
			$data[
				'login_url'
			] =
				$this->get_login_url();
		}

		wp_send_json_error(
			$data,
			403
		);
	}

	/**
	 * Protect WooCommerce Classic Checkout.
	 *
	 * @param array<string,mixed> $data   Checkout data.
	 * @param WP_Error            $errors Checkout errors.
	 *
	 * @return void
	 */
	public function guard_classic_checkout(
		array $data,
		WP_Error $errors
	): void {

		unset( $data );

		$error =
			$this->get_access_error();

		if ( ! $error instanceof WP_Error ) {
			return;
		}

		$code =
			sanitize_key(
				(string) $error
					->get_error_code()
			);

		if ( '' === $code ) {
			$code =
				'checkout_access_restricted';
		}

		$errors->add(
			$code,
			sanitize_text_field(
				(string) $error
					->get_error_message()
			)
		);
	}

	/**
	 * Protect WooCommerce Checkout Block / Store API.
	 *
	 * @param WC_Customer     $customer Customer.
	 * @param WP_REST_Request $request  Store API request.
	 *
	 * @return void
	 *
	 * @throws RouteException When checkout access is denied.
	 */
	public function guard_store_api_checkout(
		WC_Customer $customer,
		WP_REST_Request $request
	): void {

		unset( $customer );

		/*
		 * Restrict only the final POST checkout request.
		 * Read-only Store API traffic must not be blocked.
		 */
		if (
			'POST' !==
				strtoupper(
					(string) $request
						->get_method()
				)
		) {
			return;
		}

		$error =
			$this->get_access_error();

		if ( ! $error instanceof WP_Error ) {
			return;
		}

		$code =
			sanitize_key(
				(string) $error
					->get_error_code()
			);

		if ( '' === $code ) {
			$code =
				'checkout_access_restricted';
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Store API exception data is serialized by WooCommerce, not printed as HTML here.
		throw new RouteException(
			$code,
			sanitize_text_field(
				(string) $error
					->get_error_message()
			),
			403
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Determine whether login is required for the current
	 * visitor.
	 *
	 * @return bool
	 */
	public function requires_login(): bool {

		if (
			! SecuritySettings::is_enabled() ||
			is_user_logged_in()
		) {
			return false;
		}

		return (
			self::MODE_LOGGED_IN_ONLY ===
				$this->get_mode()
		);
	}

	/**
	 * Determine whether the current logged-in visitor is
	 * restricted because checkout is Guest Only.
	 *
	 * @return bool
	 */
	public function requires_guest(): bool {

		if (
			! SecuritySettings::is_enabled() ||
			! is_user_logged_in()
		) {
			return false;
		}

		return (
			self::MODE_GUEST_ONLY ===
				$this->get_mode()
		);
	}

	/**
	 * Determine whether current visitor may place an
	 * order.
	 *
	 * @return bool
	 */
	public function can_place_order(): bool {

		return (
			! $this->requires_login() &&
			! $this->requires_guest()
		);
	}

	/**
	 * Get checkout access mode.
	 *
	 * Security master OFF always behaves as Everyone.
	 *
	 * @return string
	 */
	public function get_mode(): string {

		if ( ! SecuritySettings::is_enabled() ) {
			return self::MODE_EVERYONE;
		}

		$access =
			$this->get_access_settings();

		$mode =
			sanitize_key(
				(string) (
					$access[
						'checkout_access'
					] ??
						self::MODE_EVERYONE
				)
			);

		if (
			! in_array(
				$mode,
				array(
					self::MODE_EVERYONE,
					self::MODE_GUEST_ONLY,
					self::MODE_LOGGED_IN_ONLY,
				),
				true
			)
		) {
			return self::MODE_EVERYONE;
		}

		return $mode;
	}

	/**
	 * Get login button label.
	 *
	 * Normal Checkout, Quick Checkout and Cart Checkout
	 * can use the same saved Security setting.
	 *
	 * @return string
	 */
	public function get_login_button_label(): string {

		$access =
			$this->get_access_settings();

		$default =
			__(
				'Log In to Order',
				'eilmo-checkout-flow'
			);

		$label =
			sanitize_text_field(
				(string) (
					$access[
						'login_button_text'
					] ??
						$default
				)
			);

		if ( '' === $label ) {
			$label = $default;
		}

		/**
		 * Filters checkout login button label.
		 *
		 * @param string $label Login button label.
		 */
		$label =
			apply_filters(
				'eilmo_cf/checkout_login_button_label',
				$label
			);

		if ( ! is_scalar( $label ) ) {
			return $default;
		}

		$label =
			sanitize_text_field(
				(string) $label
			);

		return '' !== $label
			? $label
			: $default;
	}

	/**
	 * Get login destination URL.
	 *
	 * Custom login pages receive a standard redirect_to
	 * query argument so compatible login forms can return
	 * the customer to the configured destination.
	 *
	 * @return string
	 */
	public function get_login_url(): string {

		$access =
			$this->get_access_settings();

		$redirect_url =
			$this->get_after_login_redirect_url();

		$custom_enabled =
			'yes' ===
				(
					$access[
						'custom_login_url_enabled'
					] ??
						'no'
				);

		if ( $custom_enabled ) {

			$custom_url =
				esc_url_raw(
					trim(
						(string) (
							$access[
								'custom_login_url'
							] ??
								''
						)
					)
				);

			if ( '' !== $custom_url ) {

				$custom_url =
					add_query_arg(
						'redirect_to',
						$redirect_url,
						$custom_url
					);

				/**
				 * Filters custom checkout login URL.
				 *
				 * @param string $custom_url   Custom URL.
				 * @param string $redirect_url Redirect destination.
				 */
				return (string)
					apply_filters(
						'eilmo_cf/checkout_login_url',
						$custom_url,
						$redirect_url
					);
			}
		}

		$default_url =
			wp_login_url(
				$redirect_url
			);

		/**
		 * Filters default checkout login URL.
		 *
		 * @param string $default_url  Login URL.
		 * @param string $redirect_url Redirect destination.
		 */
		return (string)
			apply_filters(
				'eilmo_cf/checkout_login_url',
				$default_url,
				$redirect_url
			);
	}

	/**
	 * Get post-login redirect destination.
	 *
	 * @return string
	 */
	public function get_after_login_redirect_url(): string {

		$access =
			$this->get_access_settings();

		$mode =
			sanitize_key(
				(string) (
					$access[
						'after_login'
					] ??
						'checkout'
				)
			);

		if ( 'custom_url' === $mode ) {

			$custom_url =
				esc_url_raw(
					trim(
						(string) (
							$access[
								'after_login_url'
							] ??
								''
						)
					)
				);

			if ( '' !== $custom_url ) {
				return wp_validate_redirect(
					$custom_url,
					$this->get_current_url()
				);
			}
		}

		return $this->get_current_url();
	}

	/**
	 * Get current access restriction error.
	 *
	 * @return WP_Error|null
	 */
	private function get_access_error(): ?WP_Error {

		if ( $this->requires_login() ) {

			$message =
				__(
					'Please log in before placing your order.',
					'eilmo-checkout-flow'
				);

			/**
			 * Filters logged-out checkout access message.
			 *
			 * @param string $message Message.
			 */
			$message =
				apply_filters(
					'eilmo_cf/checkout_login_required_message',
					$message
				);

			return new WP_Error(
				'checkout_login_required',
				sanitize_text_field(
					is_scalar( $message )
						? (string) $message
						: ''
				)
			);
		}

		if ( $this->requires_guest() ) {

			$message =
				__(
					'This checkout is available to guest customers only. Please log out before placing your order.',
					'eilmo-checkout-flow'
				);

			/**
			 * Filters Guest Only checkout access message.
			 *
			 * @param string $message Message.
			 */
			$message =
				apply_filters(
					'eilmo_cf/checkout_guest_only_message',
					$message
				);

			return new WP_Error(
				'checkout_guest_only',
				sanitize_text_field(
					is_scalar( $message )
						? (string) $message
						: ''
				)
			);
		}

		return null;
	}

	/**
	 * Get Security Checkout Access settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_access_settings(): array {

		$settings =
			SecuritySettings::get_settings();

		if (
			! isset(
				$settings[
					'access'
				]
			) ||
			! is_array(
				$settings[
					'access'
				]
			)
		) {
			return array();
		}

		return $settings[
			'access'
		];
	}

	/**
	 * Get current request URL.
	 *
	 * Normal Checkout:
	 * Returns the current checkout page.
	 *
	 * Quick Checkout:
	 * Returns the current WooCommerce product page.
	 *
	 * @return string
	 */
	private function get_current_url(): string {

		$request_uri =
			isset(
				$_SERVER[
					'REQUEST_URI'
				]
			)
				? esc_url_raw(
					wp_unslash(
					(string) $_SERVER[
						'REQUEST_URI'
					]
					)
				)
				: '/';

		if (
			'' ===
				trim(
					$request_uri
				)
		) {
			$request_uri =
				'/';
		}

		$url =
			home_url(
				$request_uri
			);

		return wp_validate_redirect(
			$url,
			home_url(
				'/'
			)
		);
	}
}
