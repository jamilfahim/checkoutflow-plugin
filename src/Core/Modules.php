<?php
/**
 * Plugin module registry.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Core;

use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Licensing\LicenseManager;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin module registration.
 */
final class Modules {

	/**
	 * Registered modules.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private $modules = array();

	/**
	 * Add a module to the registry.
	 *
	 * @param ModuleInterface $module Module instance.
	 *
	 * @return self
	 */
	public function add( ModuleInterface $module ): self {

		$module_id = $module->get_id();

		if ( '' === $module_id ) {
			return $this;
		}

		$this->modules[ $module_id ] = $module;

		return $this;
	}

	/**
	 * Determine whether a module exists.
	 *
	 * @param string $module_id Module identifier.
	 *
	 * @return bool
	 */
	public function has( string $module_id ): bool {

		return isset( $this->modules[ $module_id ] );
	}

	/**
	 * Get a registered module.
	 *
	 * @param string $module_id Module identifier.
	 *
	 * @return ModuleInterface|null
	 */
	public function get( string $module_id ): ?ModuleInterface {

		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array<string, ModuleInterface>
	 */
	public function all(): array {

		return $this->modules;
	}

	/**
	 * Register all modules.
	 *
	 * @return void
	 */
	public function register_all(): void {

		/* Secondary runtime gate: module registration cannot be enabled by
		 * changing only the main plugin boot branch. */
		if ( ! ( new LicenseManager() )->is_usable() ) {
			return;
		}

		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}
}
