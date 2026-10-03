<?php
/**
 * Tracking module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Tracking;

use EilmoCheckout\Admin\MetaTrackingSettings;
use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Tracking\Admin\MetaOrderAdminPanel;
use EilmoCheckout\Tracking\Meta\MetaBrowserPixel;
use EilmoCheckout\Tracking\Meta\MetaServerEventController;
use EilmoCheckout\Tracking\Meta\MetaTrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers tracking integrations.
 */
final class TrackingModule implements ModuleInterface {

	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'tracking';
	}

	/**
	 * Register tracking services.
	 *
	 * The read-only order admin panel remains available
	 * even while Meta Tracking is disabled so historical
	 * Purchase state can still be inspected.
	 *
	 * @return void
	 */
	public function register(): void {

		$this->register_admin();

		if (
			! MetaTrackingSettings::is_enabled()
		) {
			return;
		}

		$this->register_purchase_tracking();
		$this->register_server_events();
		$this->register_browser_tracking();
	}

	/**
	 * Register tracking admin UI.
	 *
	 * @return void
	 */
	private function register_admin(): void {

		$panel =
			new MetaOrderAdminPanel();

		$panel->register();
	}

	/**
	 * Register authoritative Purchase tracking.
	 *
	 * @return void
	 */
	private function register_purchase_tracking(): void {

		$tracking =
			new MetaTrackingService();

		$tracking->register();
	}

	/**
	 * Register browser-originated server event bridge.
	 *
	 * @return void
	 */
	private function register_server_events(): void {

		if (
			! MetaTrackingSettings::feature_is_enabled(
				'conversions_api'
			)
		) {
			return;
		}

		$server_events =
			new MetaServerEventController();

		$server_events->register();
	}

	/**
	 * Register frontend Meta bridge.
	 *
	 * @return void
	 */
	private function register_browser_tracking(): void {

		$browser_pixel =
			new MetaBrowserPixel();

		$browser_pixel->register();
	}
}