<?php
/**
 * Meta user data builder.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking\Meta;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Builds normalized Meta user_data for browser-originated
 * server events and WooCommerce Purchase events.
 *
 * This is the single source of truth for Meta customer matching
 * data so Purchase and browser-originated CAPI events use the
 * same normalization and hashing rules.
 */
final class MetaUserDataBuilder {

	/**
	 * Attribution meta keys used on WooCommerce orders.
	 */
	private const META_FBP =
		'_eilmo_cf_meta_fbp';

	private const META_FBC =
		'_eilmo_cf_meta_fbc';

	private const META_CLIENT_IP =
		'_eilmo_cf_meta_client_ip';

	private const META_CLIENT_USER_AGENT =
		'_eilmo_cf_meta_client_user_agent';

	/**
	 * Build user_data from a WooCommerce order.
	 *
	 * Customer identifiers that require hashing are normalized
	 * and SHA-256 hashed before they leave WordPress. Browser
	 * identifiers, IP address and user agent remain unhashed.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	public function from_order(
		WC_Order $order
	): array {

		$user_data =
			array();

		$this->add_contact_fields(
			$user_data,
			array(
				'email' =>
					$order->get_billing_email(),

				'phone' =>
					$order->get_billing_phone(),

				'first_name' =>
					$order->get_billing_first_name(),

				'last_name' =>
					$order->get_billing_last_name(),

				'city' =>
					$order->get_billing_city(),

				'state' =>
					$order->get_billing_state(),

				'postcode' =>
					$order->get_billing_postcode(),

				'country' =>
					$order->get_billing_country(),
			)
		);

		$customer_id =
			absint(
				$order->get_customer_id()
			);

		if ( $customer_id > 0 ) {
			$this->add_hashed(
				$user_data,
				'external_id',
				(string) $customer_id
			);
		}

		$client_ip =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_CLIENT_IP,
					true
				)
			);

		if (
			'' === $client_ip &&
			method_exists(
				$order,
				'get_customer_ip_address'
			)
		) {
			$client_ip =
				sanitize_text_field(
					(string) $order
						->get_customer_ip_address()
				);
		}

		$this->add_ip(
			$user_data,
			$client_ip
		);

		$user_agent =
			sanitize_text_field(
				(string) $order->get_meta(
					self::META_CLIENT_USER_AGENT,
					true
				)
			);

		if (
			'' === $user_agent &&
			method_exists(
				$order,
				'get_customer_user_agent'
			)
		) {
			$user_agent =
				sanitize_text_field(
					(string) $order
						->get_customer_user_agent()
				);
		}

		$this->add_user_agent(
			$user_data,
			$user_agent
		);

		$this->add_browser_identifier(
			$user_data,
			'fbp',
			(string) $order->get_meta(
				self::META_FBP,
				true
			)
		);

		$this->add_browser_identifier(
			$user_data,
			'fbc',
			(string) $order->get_meta(
				self::META_FBC,
				true
			)
		);

		/**
		 * Filters Meta order user_data.
		 *
		 * This filter is intentionally applied here, not again in
		 * MetaEventBuilder, so integrations receive one predictable
		 * filter pass for Purchase customer matching data.
		 *
		 * @param array<string,mixed> $user_data User data.
		 * @param WC_Order            $order     Order.
		 */
		$user_data =
			apply_filters(
				'eilmo_cf/meta_tracking/user_data',
				$user_data,
				$order
			);

		return is_array( $user_data )
			? $user_data
			: array();
	}

	/**
	 * Build user_data from the current first-party request.
	 *
	 * Checkout contact values are accepted only from the
	 * site's own nonce-protected AJAX request and are hashed
	 * server-side before leaving WordPress.
	 *
	 * @param array<string,mixed> $customer   Customer data.
	 * @param string              $source_url Source URL.
	 *
	 * @return array<string,mixed>
	 */
	public function from_request(
		array $customer = array(),
		string $source_url = ''
	): array {

		$user_data =
			array();

		$this->add_contact_fields(
			$user_data,
			array(
				'email' =>
					(string) (
						$customer['billing_email'] ??
							''
					),

				'phone' =>
					(string) (
						$customer['billing_phone'] ??
							''
					),

				'first_name' =>
					(string) (
						$customer['billing_first_name'] ??
							''
					),

				'last_name' =>
					(string) (
						$customer['billing_last_name'] ??
							''
					),

				'city' =>
					(string) (
						$customer['billing_city'] ??
							''
					),

				'state' =>
					(string) (
						$customer['billing_state'] ??
							''
					),

				'postcode' =>
					(string) (
						$customer['billing_postcode'] ??
							''
					),

				'country' =>
					(string) (
						$customer['billing_country'] ??
							''
					),
			)
		);

		if ( is_user_logged_in() ) {
			$user_id =
				get_current_user_id();

			if ( $user_id > 0 ) {
				$this->add_hashed(
					$user_data,
					'external_id',
					(string) $user_id
				);
			}
		}

		$this->add_ip(
			$user_data,
			$this->get_current_ip()
		);

		$user_agent =
			isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field(
					wp_unslash(
						(string) $_SERVER['HTTP_USER_AGENT']
					)
				)
				: '';

		$this->add_user_agent(
			$user_data,
			$user_agent
		);

		$this->add_browser_identifier(
			$user_data,
			'fbp',
			$this->get_cookie(
				'_fbp'
			)
		);

		$fbc =
			$this->get_cookie(
				'_fbc'
			);

		if ( '' === $fbc ) {
			$fbc =
				$this->build_fbc_from_url(
					$source_url
				);
		}

		$this->add_browser_identifier(
			$user_data,
			'fbc',
			$fbc
		);

		/**
		 * Filters Meta request user_data.
		 *
		 * @param array<string,mixed> $user_data  User data.
		 * @param array<string,mixed> $customer   Customer data.
		 * @param string              $source_url Source URL.
		 */
		$user_data =
			apply_filters(
				'eilmo_cf/meta_tracking/request_user_data',
				$user_data,
				$customer,
				$source_url
			);

		return is_array( $user_data )
			? $user_data
			: array();
	}

	/**
	 * Add normalized and hashed contact fields.
	 *
	 * @param array<string,mixed> $user_data User data.
	 * @param array<string,mixed> $contact   Contact values.
	 *
	 * @return void
	 */
	private function add_contact_fields(
		array &$user_data,
		array $contact
	): void {

		$this->add_hashed(
			$user_data,
			'em',
			$this->normalize_email(
				(string) (
					$contact['email'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'ph',
			$this->normalize_phone(
				(string) (
					$contact['phone'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'fn',
			$this->normalize_text(
				(string) (
					$contact['first_name'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'ln',
			$this->normalize_text(
				(string) (
					$contact['last_name'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'ct',
			$this->normalize_text(
				(string) (
					$contact['city'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'st',
			$this->normalize_text(
				(string) (
					$contact['state'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'zp',
			$this->normalize_postcode(
				(string) (
					$contact['postcode'] ??
						''
				)
			)
		);

		$this->add_hashed(
			$user_data,
			'country',
			$this->normalize_country(
				(string) (
					$contact['country'] ??
						''
				)
			)
		);
	}

	/**
	 * Add one SHA-256 hashed value.
	 *
	 * @param array<string,mixed> $user_data User data.
	 * @param string              $key       Meta key.
	 * @param string              $value     Normalized value.
	 *
	 * @return void
	 */
	private function add_hashed(
		array &$user_data,
		string $key,
		string $value
	): void {

		if ( '' === $value ) {
			return;
		}

		$user_data[ $key ] =
			array(
				hash(
					'sha256',
					$value
				),
			);
	}

	/**
	 * Add validated IP address.
	 *
	 * @param array<string,mixed> $user_data User data.
	 * @param string              $ip        IP.
	 *
	 * @return void
	 */
	private function add_ip(
		array &$user_data,
		string $ip
	): void {

		$ip =
			trim(
				$ip
			);

		if (
			'' !== $ip &&
			false !==
				filter_var(
					$ip,
					FILTER_VALIDATE_IP
				)
		) {
			$user_data['client_ip_address'] =
				$ip;
		}
	}

	/**
	 * Add user agent.
	 *
	 * @param array<string,mixed> $user_data User data.
	 * @param string              $user_agent User agent.
	 *
	 * @return void
	 */
	private function add_user_agent(
		array &$user_data,
		string $user_agent
	): void {

		$user_agent =
			trim(
				$user_agent
			);

		if ( '' !== $user_agent ) {
			$user_data['client_user_agent'] =
				$user_agent;
		}
	}

	/**
	 * Add an unhashed Meta browser identifier.
	 *
	 * @param array<string,mixed> $user_data User data.
	 * @param string              $key       Browser identifier key.
	 * @param string              $value     Browser identifier value.
	 *
	 * @return void
	 */
	private function add_browser_identifier(
		array &$user_data,
		string $key,
		string $value
	): void {

		if (
			! in_array(
				$key,
				array(
					'fbp',
					'fbc',
				),
				true
			)
		) {
			return;
		}

		$value =
			sanitize_text_field(
				trim(
					$value
				)
			);

		if ( '' !== $value ) {
			$user_data[ $key ] =
				$value;
		}
	}

	/**
	 * Get first-party cookie.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return string
	 */
	private function get_cookie(
		string $name
	): string {

		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				(string) $_COOKIE[ $name ]
			)
		);
	}

	/**
	 * Get current client IP.
	 *
	 * @return string
	 */
	private function get_current_ip(): string {

		$ip =
			'';

		if ( class_exists( '\\WC_Geolocation' ) ) {
			$ip =
				sanitize_text_field(
					(string) \WC_Geolocation
						::get_ip_address()
				);
		}

		if (
			'' === $ip &&
			isset( $_SERVER['REMOTE_ADDR'] )
		) {
			$ip =
				sanitize_text_field(
					wp_unslash(
						(string) $_SERVER['REMOTE_ADDR']
					)
				);
		}

		return false !==
			filter_var(
				$ip,
				FILTER_VALIDATE_IP
			)
				? $ip
				: '';
	}

	/**
	 * Build fbc from fbclid in the original source URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function build_fbc_from_url(
		string $url
	): string {

		$query =
			wp_parse_url(
				$url,
				PHP_URL_QUERY
			);

		if (
			! is_string( $query ) ||
			'' === $query
		) {
			return '';
		}

		$args =
			array();

		parse_str(
			$query,
			$args
		);

		$fbclid =
			sanitize_text_field(
				(string) (
					$args['fbclid'] ??
						''
				)
			);

		if ( '' === $fbclid ) {
			return '';
		}

		return sprintf(
			'fb.1.%1$d.%2$s',
			time() * 1000,
			$fbclid
		);
	}

	/**
	 * Normalize email.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function normalize_email(
		string $value
	): string {

		$value =
			strtolower(
				trim(
					$value
				)
			);

		return is_email( $value )
			? $value
			: '';
	}

	/**
	 * Normalize phone to digits only.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function normalize_phone(
		string $value
	): string {

		$value =
			preg_replace(
				'/\D+/',
				'',
				$value
			);

		return is_string( $value )
			? $value
			: '';
	}

	/**
	 * Normalize common text identifiers.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function normalize_text(
		string $value
	): string {

		$value =
			strtolower(
				trim(
					wp_strip_all_tags(
						$value
					)
				)
			);

		$value =
			preg_replace(
				'/\s+/u',
				'',
				$value
			);

		return is_string( $value )
			? $value
			: '';
	}

	/**
	 * Normalize postcode.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function normalize_postcode(
		string $value
	): string {

		$value =
			strtolower(
				trim(
					$value
				)
			);

		$value =
			preg_replace(
				'/[^a-z0-9]/',
				'',
				$value
			);

		return is_string( $value )
			? $value
			: '';
	}

	/**
	 * Normalize country code.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	private function normalize_country(
		string $value
	): string {

		$value =
			strtolower(
				trim(
					$value
				)
			);

		$value =
			preg_replace(
				'/[^a-z]/',
				'',
				$value
			);

		return is_string( $value )
			? $value
			: '';
	}
}