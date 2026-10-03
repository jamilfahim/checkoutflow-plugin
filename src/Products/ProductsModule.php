<?php
/**
 * Products module.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Products;

use EilmoCheckout\Contracts\ModuleInterface;
use EilmoCheckout\Products\Admin\VariationAdminFields;
use EilmoCheckout\Products\Services\DefaultVariationResolver;
use EilmoCheckout\Products\Services\ProductResolver;
use EilmoCheckout\Products\Services\ProductSelection;
use EilmoCheckout\Products\Services\SingleProductConfig;
use EilmoCheckout\Products\Services\SingleProductResolver;
use EilmoCheckout\Products\Services\StockService;
use EilmoCheckout\Products\Services\VariationBadgeService;
use EilmoCheckout\Products\Services\VariationResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the products module.
 */
final class ProductsModule implements ModuleInterface {

	/**
	 * Product resolver.
	 *
	 * @var ProductResolver|null
	 */
	private $product_resolver = null;

	/**
	 * Variation resolver.
	 *
	 * @var VariationResolver|null
	 */
	private $variation_resolver = null;

	/**
	 * Product selection service.
	 *
	 * @var ProductSelection|null
	 */
	private $product_selection = null;

	/**
	 * Stock service.
	 *
	 * @var StockService|null
	 */
	private $stock_service = null;

	/**
	 * Single Product configuration.
	 *
	 * @var SingleProductConfig|null
	 */
	private $single_product_config = null;

	/**
	 * Single Product resolver.
	 *
	 * @var SingleProductResolver|null
	 */
	private $single_product_resolver = null;

	/**
	 * Default variation resolver.
	 *
	 * @var DefaultVariationResolver|null
	 */
	private $default_variation_resolver = null;

	/**
	 * Variation badge service.
	 *
	 * @var VariationBadgeService|null
	 */
	private $variation_badge_service = null;

	/**
	 * Whether the module has been registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Get module identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'products';
	}

	/**
	 * Register products module.
	 *
	 * @return void
	 */
	public function register(): void {

		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		/*
		 * Initialize shared services.
		 *
		 * These instances can later be passed to renderers, checkout
		 * calculators, REST controllers and other modules.
		 */
		$this->product_resolver();
		$this->variation_resolver();
		$this->product_selection();
		$this->stock_service();
		$this->single_product_config();
		$this->single_product_resolver();
		$this->default_variation_resolver();
		$this->variation_badge_service();

		if ( is_admin() ) {
			$variation_fields = new VariationAdminFields(
				$this->variation_badge_service()
			);
			$variation_fields->register();
		}

		/**
		 * Fires after the products module has been registered.
		 *
		 * @param ProductsModule $module Products module instance.
		 */
		do_action(
			'eilmo_cf/products_registered',
			$this
		);
	}

	/**
	 * Get product resolver.
	 *
	 * @return ProductResolver
	 */
	public function product_resolver(): ProductResolver {

		if ( null === $this->product_resolver ) {
			$this->product_resolver = new ProductResolver();
		}

		return $this->product_resolver;
	}

	/**
	 * Get variation resolver.
	 *
	 * @return VariationResolver
	 */
	public function variation_resolver(): VariationResolver {

		if ( null === $this->variation_resolver ) {
			$this->variation_resolver = new VariationResolver();
		}

		return $this->variation_resolver;
	}

	/**
	 * Get product selection service.
	 *
	 * @return ProductSelection
	 */
	public function product_selection(): ProductSelection {

		if ( null === $this->product_selection ) {
			$this->product_selection = new ProductSelection();
		}

		return $this->product_selection;
	}

	/**
	 * Get stock service.
	 *
	 * @return StockService
	 */
	public function stock_service(): StockService {

		if ( null === $this->stock_service ) {
			$this->stock_service = new StockService();
		}

		return $this->stock_service;
	}

	/**
	 * Get Single Product configuration.
	 *
	 * @return SingleProductConfig
	 */
	public function single_product_config(): SingleProductConfig {

		if ( null === $this->single_product_config ) {
			$this->single_product_config = new SingleProductConfig();
		}

		return $this->single_product_config;
	}

	/**
	 * Get the exactly-one-product resolver.
	 *
	 * @return SingleProductResolver
	 */
	public function single_product_resolver(): SingleProductResolver {

		if ( null === $this->single_product_resolver ) {
			$this->single_product_resolver = new SingleProductResolver(
				$this->product_resolver()
			);
		}

		return $this->single_product_resolver;
	}

	/**
	 * Get default variation resolver.
	 *
	 * @return DefaultVariationResolver
	 */
	public function default_variation_resolver(): DefaultVariationResolver {

		if ( null === $this->default_variation_resolver ) {
			$this->default_variation_resolver = new DefaultVariationResolver(
				$this->variation_resolver()
			);
		}

		return $this->default_variation_resolver;
	}

	/**
	 * Get variation badge service.
	 *
	 * @return VariationBadgeService
	 */
	public function variation_badge_service(): VariationBadgeService {

		if ( null === $this->variation_badge_service ) {
			$this->variation_badge_service = new VariationBadgeService();
		}

		return $this->variation_badge_service;
	}
}
