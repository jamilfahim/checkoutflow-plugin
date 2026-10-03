<?php
/**
 * Blacklist admin actions.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Security\Blacklist\BlacklistRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Customer Blacklist admin actions.
 */
final class BlacklistActions implements RegistrableInterface {

	/**
	 * Register admin actions.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action(
			'admin_post_eilmo_cf_blacklist_add',
			array(
				$this,
				'add_entry',
			)
		);

		add_action(
			'admin_post_eilmo_cf_blacklist_remove',
			array(
				$this,
				'remove_entry',
			)
		);
	}

	/**
	 * Add blacklist entry.
	 *
	 * @return void
	 */
	public function add_entry(): void {

		$this->verify_access();
		$this->verify_feature();

		check_admin_referer(
			'eilmo_cf_blacklist_add'
		);

		$type =
			isset( $_POST['type'] )
				? sanitize_key(
					wp_unslash(
						$_POST['type']
					)
				)
				: '';

		$value =
			isset( $_POST['value'] )
				? sanitize_text_field(
					wp_unslash(
						$_POST['value']
					)
				)
				: '';

		$reason =
			isset( $_POST['reason'] )
				? sanitize_textarea_field(
					wp_unslash(
						$_POST['reason']
					)
				)
				: '';

		$repository =
			new BlacklistRepository();

		$result =
			$repository->add(
				$type,
				$value,
				$reason
			);

		if ( is_wp_error( $result ) ) {

			$code =
				$result->get_error_code();

			switch ( $code ) {

				case 'eilmo_cf_invalid_blacklist_type':
					$this->redirect(
						'invalid_type'
					);
					break;

				case 'eilmo_cf_invalid_blacklist_value':
					$this->redirect(
						'invalid_value'
					);
					break;

				case 'eilmo_cf_blacklist_exists':
					$this->redirect(
						'already_exists'
					);
					break;

				default:
					$this->redirect(
						'add_failed'
					);
					break;
			}
		}

		$this->redirect(
			'added'
		);
	}

	/**
	 * Remove blacklist entry.
	 *
	 * Removal is a soft delete. The repository marks
	 * the record as inactive instead of deleting it.
	 *
	 * @return void
	 */
	public function remove_entry(): void {

		$this->verify_access();
		$this->verify_feature();

		$entry_id =
			isset( $_POST['entry_id'] )
				? absint(
					wp_unslash(
						$_POST['entry_id']
					)
				)
				: 0;

		if ( $entry_id <= 0 ) {
			$this->redirect(
				'remove_failed'
			);
		}

		check_admin_referer(
			'eilmo_cf_blacklist_remove_' .
				$entry_id
		);

		$repository =
			new BlacklistRepository();

		if (
			! $repository->remove(
				$entry_id
			)
		) {
			$this->redirect(
				'remove_failed'
			);
		}

		$this->redirect(
			'removed'
		);
	}

	/**
	 * Verify current user capability.
	 *
	 * @return void
	 */
	private function verify_access(): void {

		if (
			current_user_can(
				$this->get_capability()
			)
		) {
			return;
		}

		wp_die(
			esc_html__(
				'You do not have permission to perform this action.',
				'eilmo-checkout-flow'
			)
		);
	}

	/**
	 * Verify Customer Blacklist is enabled.
	 *
	 * @return void
	 */
	private function verify_feature(): void {

		$settings =
			get_option(
				CheckoutSettings::OPTION_NAME,
				array()
			);

		$enabled =
			is_array( $settings ) &&
			isset(
				$settings[
					'general'
				][
					'customer_blacklist'
				]
			) &&
			'yes' ===
				$settings[
					'general'
				][
					'customer_blacklist'
				];

		if ( $enabled ) {
			return;
		}

		wp_die(
			esc_html__(
				'Customer Blacklist is currently disabled.',
				'eilmo-checkout-flow'
			)
		);
	}

	/**
	 * Redirect back to the Blacklist tab.
	 *
	 * @param string $notice Notice code.
	 *
	 * @return void
	 */
	private function redirect(
		string $notice
	): void {

		$url =
			add_query_arg(
				array(
					'page' =>
						'eilmo-checkout-security',

					'tab' =>
						'blacklist',

					'blacklist_notice' =>
						sanitize_key(
							$notice
						),
				),
				admin_url(
					'admin.php'
				)
			);

		wp_safe_redirect(
			$url
		);

		exit;
	}

	/**
	 * Get admin capability.
	 *
	 * @return string
	 */
	private function get_capability(): string {

		$capability =
			apply_filters(
				'eilmo_cf/admin_menu_capability',
				'manage_woocommerce'
			);

		if (
			! is_string( $capability ) ||
			'' === $capability
		) {
			return 'manage_woocommerce';
		}

		return sanitize_key(
			$capability
		);
	}
}