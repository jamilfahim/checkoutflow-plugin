<?php
/**
 * Module contract.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Defines a contract for plugin modules.
 */
interface ModuleInterface extends RegistrableInterface {

	/**
	 * Get the unique module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;
}