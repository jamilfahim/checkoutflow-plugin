<?php
/**
 * Registrable contract.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Defines a contract for registrable plugin components.
 */
interface RegistrableInterface {

	/**
	 * Register hooks or functionality.
	 *
	 * @return void
	 */
	public function register(): void;
}