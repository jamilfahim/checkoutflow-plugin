<?php
/**
 * Admin menu.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin;

use EilmoCheckout\Admin\Pages\AbandonedCheckoutsPage;
use EilmoCheckout\Admin\Pages\CourierSettingsPage;
use EilmoCheckout\Admin\Pages\DashboardPage;
use EilmoCheckout\Admin\Pages\DefaultCheckoutSettingsPage;
use EilmoCheckout\Admin\Pages\IntegrationsPage;
use EilmoCheckout\Admin\Pages\MetaTrackingSettingsPage;
use EilmoCheckout\Admin\Pages\OffersSettingsPage;
use EilmoCheckout\Admin\Pages\QuickCheckoutSettingsPage;
use EilmoCheckout\Admin\Pages\CheckoutSettingsPage;
use EilmoCheckout\Admin\Pages\SecurityPage;
use EilmoCheckout\Admin\Pages\WhatsAppOrdersPage;
use EilmoCheckout\Admin\Pages\HelpCenterPage;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Licensing\LicenseManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin admin menu.
 */
final class Menu implements RegistrableInterface {

    /**
     * Menu position.
     *
     * @var int
     */
    private const MENU_POSITION = 56;


    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {

        add_action(
            'admin_menu',
            array(
                $this,
                'register_menu',
            )
        );

        /*
         * The full admin stylesheet is intentionally loaded only on plugin and
         * order screens. Keep the menu mark stable on every wp-admin screen
         * with this tiny, isolated rule instead of loading the whole bundle.
         */
        add_action(
            'admin_head',
            array(
                $this,
                'render_menu_icon_styles',
            )
        );

    }


    /**
     * Constrain the custom menu image on every WordPress admin screen.
     *
     * @return void
     */
    public function render_menu_icon_styles(): void {

        ?>
        <style id="eilmo-cf-admin-menu-icon-css">
            #adminmenu .toplevel_page_eilmo-checkout .wp-menu-image {
                width: 36px ;
                height: 34px ;
                overflow: hidden ;
            }
            #adminmenu .toplevel_page_eilmo-checkout .wp-menu-image img {
                display: block ;
                width: 20px ;
                min-width: 20px ;
                max-width: 20px ;
                height: 20px ;
                min-height: 20px ;
                max-height: 20px ;
                margin: 0 auto ;
                padding: 7px 0 0 ;
                box-sizing: content-box ;
                object-fit: contain ;
                opacity: 1 ;
                filter: none ;
            }
        </style>
        <?php
    }


    /**
     * Register plugin admin menu.
     *
     * @return void
     */
    public function register_menu(): void {

        $capability = $this->get_capability();


        /**
         * Parent menu.
         */
        add_menu_page(
            __(
                'Checkout Flow',
                'eilmo-checkout-flow'
            ),
            __(
                'Checkout Flow',
                'eilmo-checkout-flow'
            ),
            $capability,
            DashboardPage::PAGE_SLUG,
            array(
                $this,
                'render_dashboard_page',
            ),
            EILMO_CF_ASSETS_URL . 'images/admin-menu-icon.png',
            self::MENU_POSITION
        );


        /**
         * Dashboard.
         */
        add_submenu_page(
            DashboardPage::PAGE_SLUG,
            __(
                'Checkout Flow Dashboard',
                'eilmo-checkout-flow'
            ),
            __(
                'Dashboard',
                'eilmo-checkout-flow'
            ),
            $capability,
            DashboardPage::PAGE_SLUG,
            array(
                $this,
                'render_dashboard_page',
            )
        );

        /*
         * Before activation the Dashboard is the complete admin surface.
         * No feature callback or direct submenu route is registered.
         */
        if ( ! ( new LicenseManager() )->is_usable() ) {
            return;
        }

        /**
         * Checkout Settings.
         */
        add_submenu_page(
            DashboardPage::PAGE_SLUG,
            __(
                'Checkout Flow Settings',
                'eilmo-checkout-flow'
            ),
            __(
                'Checkout Settings',
                'eilmo-checkout-flow'
            ),
            $capability,
            CheckoutSettingsPage::PAGE_SLUG,
            array(
                $this,
                'render_settings_page',
            )
        );


		/**
		 * Default WooCommerce Checkout.
		 */
		if (
			$this->is_feature_enabled(
				'default_checkout_integration'
			)
		) {

			add_submenu_page(
				DashboardPage::PAGE_SLUG,
				__(
					'Default Checkout Settings',
					'eilmo-checkout-flow'
				),
				__(
					'Default Checkout',
					'eilmo-checkout-flow'
				),
				$capability,
				DefaultCheckoutSettingsPage::PAGE_SLUG,
				array(
					$this,
					'render_default_checkout_settings_page',
				)
			);

		}


        /**
         * Offers and Discounts.
         */
        add_submenu_page(
            DashboardPage::PAGE_SLUG,
            __(
                'Offers & Discounts',
                'eilmo-checkout-flow'
            ),
            __(
                'Offers & Discounts',
                'eilmo-checkout-flow'
            ),
            $capability,
            OffersSettingsPage::PAGE_SLUG,
            array(
                $this,
                'render_offers_settings_page',
            )
        );


        /**
         * Single Product Quick Checkout.
         */
        if (
            $this->is_feature_enabled(
                'single_product_checkout'
            )
        ) {

            add_submenu_page(
                DashboardPage::PAGE_SLUG,
                __(
                    'Quick Checkout Settings',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Quick Checkout',
                    'eilmo-checkout-flow'
                ),
                $capability,
                QuickCheckoutSettingsPage::PAGE_SLUG,
                array(
                    $this,
                    'render_quick_checkout_settings_page',
                )
            );

        }


        /**
         * Integrations.
         *
         * Courier, Meta Tracking and WhatsApp keep their existing
         * settings classes and option names, but share one
         * user-facing menu with dedicated tabs.
         */
        if (
            $this->is_feature_enabled( 'courier' ) ||
            $this->is_feature_enabled( 'meta_tracking' ) ||
            $this->is_feature_enabled( 'whatsapp_ordering' )
        ) {

            add_submenu_page(
                DashboardPage::PAGE_SLUG,
                __(
                    'Checkout Flow Integrations',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Integrations',
                    'eilmo-checkout-flow'
                ),
                $capability,
                IntegrationsPage::PAGE_SLUG,
                array(
                    $this,
                    'render_integrations_page',
                )
            );

            /*
             * Keep legacy page slugs registered for old
             * bookmarks, then hide them from the submenu.
             */
            if ( $this->is_feature_enabled( 'courier' ) ) {
                add_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    __( 'Courier Settings', 'eilmo-checkout-flow' ),
                    __( 'Courier', 'eilmo-checkout-flow' ),
                    $capability,
                    CourierSettingsPage::PAGE_SLUG,
                    array(
                        $this,
                        'render_courier_settings_page',
                    )
                );

                remove_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    CourierSettingsPage::PAGE_SLUG
                );
            }

            if ( $this->is_feature_enabled( 'meta_tracking' ) ) {
                add_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    __( 'Meta Tracking Settings', 'eilmo-checkout-flow' ),
                    __( 'Meta Tracking', 'eilmo-checkout-flow' ),
                    $capability,
                    MetaTrackingSettingsPage::PAGE_SLUG,
                    array(
                        $this,
                        'render_meta_tracking_settings_page',
                    )
                );

                remove_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    MetaTrackingSettingsPage::PAGE_SLUG
                );
            }

            if ( $this->is_feature_enabled( 'whatsapp_ordering' ) ) {
                add_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    __( 'WhatsApp Orders', 'eilmo-checkout-flow' ),
                    __( 'WhatsApp Orders', 'eilmo-checkout-flow' ),
                    $capability,
                    WhatsAppOrdersPage::PAGE_SLUG,
                    array(
                        $this,
                        'render_whatsapp_orders_page',
                    )
                );

                remove_submenu_page(
                    DashboardPage::PAGE_SLUG,
                    WhatsAppOrdersPage::PAGE_SLUG
                );
            }

        }


        /**
         * Abandoned Checkouts.
         */
        if (
            $this->is_feature_enabled(
                'abandoned_checkout'
            )
        ) {

            add_submenu_page(
                DashboardPage::PAGE_SLUG,
                __(
                    'Abandoned Checkouts',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Abandoned Checkouts',
                    'eilmo-checkout-flow'
                ),
                $capability,
                AbandonedCheckoutsPage::PAGE_SLUG,
                array(
                    $this,
                    'render_abandoned_checkouts_page',
                )
            );

        }


        /**
         * Security.
         */
        add_submenu_page(
            DashboardPage::PAGE_SLUG,
            __(
                'Checkout Security',
                'eilmo-checkout-flow'
            ),
            __(
                'Security',
                'eilmo-checkout-flow'
            ),
            $capability,
            SecurityPage::PAGE_SLUG,
            array(
                $this,
                'render_security_page',
            )
        );

        /**
         * Help Center and License.
         */
        add_submenu_page(
            DashboardPage::PAGE_SLUG,
            __( 'Checkout Flow Help Center', 'eilmo-checkout-flow' ),
            __( 'Help Center', 'eilmo-checkout-flow' ),
            $capability,
            HelpCenterPage::PAGE_SLUG,
            array( $this, 'render_help_center_page' )
        );

    }


    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page(): void {

        $this->verify_access();

        $page = new DashboardPage();

        $page->render();

    }


    /**
     * Render checkout settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {

        $this->verify_access();

        $page = new CheckoutSettingsPage();

        $page->render();

    }


	/**
	 * Render Default Checkout settings page.
	 *
	 * @return void
	 */
	public function render_default_checkout_settings_page(): void {

		$this->verify_access();

		if (
			! $this->is_feature_enabled(
				'default_checkout_integration'
			)
		) {

			$this->redirect_to_settings();

		}

		$page = new DefaultCheckoutSettingsPage();

		$page->render();

	}


    /**
     * Render Offers and Discounts settings page.
     *
     * @return void
     */
    public function render_offers_settings_page(): void {

        $this->verify_access();

        $page = new OffersSettingsPage();

        $page->render();

    }


    /**
     * Render Integrations page.
     *
     * @return void
     */
    public function render_integrations_page(): void {

        $this->verify_access();

        $page = new IntegrationsPage();

        $page->render();

    }


    /**
     * Render Quick Checkout settings page.
     *
     * @return void
     */
    public function render_quick_checkout_settings_page(): void {

        $this->verify_access();

        if (
            ! $this->is_feature_enabled(
                'single_product_checkout'
            )
        ) {

            $this->redirect_to_settings();

        }

        $page = new QuickCheckoutSettingsPage();

        $page->render();

    }


    /**
     * Render Courier settings page.
     *
     * @return void
     */
    public function render_courier_settings_page(): void {

        $this->verify_access();

        if (
            ! $this->is_feature_enabled(
                'courier'
            )
        ) {

            $this->redirect_to_settings();

        }

        $this->redirect_to_integration_tab(
            IntegrationsPage::TAB_COURIER
        );

    }


    /**
     * Render Meta Tracking settings page.
     *
     * @return void
     */
    public function render_meta_tracking_settings_page(): void {

        $this->verify_access();

        if (
            ! $this->is_feature_enabled(
                'meta_tracking'
            )
        ) {

            $this->redirect_to_settings();

        }

        $this->redirect_to_integration_tab(
            IntegrationsPage::TAB_META_TRACKING
        );

    }


    /**
     * Render abandoned checkouts page.
     *
     * @return void
     */
    public function render_abandoned_checkouts_page(): void {

        $this->verify_access();

        $page = new AbandonedCheckoutsPage();

        $page->render();

    }


    /**
     * Render security page.
     *
     * @return void
     */
    public function render_security_page(): void {

        $this->verify_access();

        $page = new SecurityPage();

        $page->render();

    }


    /**
     * Render Help Center page.
     *
     * @return void
     */
    public function render_help_center_page(): void {

        $this->verify_access();

        $page = new HelpCenterPage();

        $page->render();

    }


    /**
     * Redirect legacy WhatsApp settings page to its integration tab.
     *
     * @return void
     */
    public function render_whatsapp_orders_page(): void {

        $this->verify_access();

        if (
            ! $this->is_feature_enabled(
                'whatsapp_ordering'
            )
        ) {

            $this->redirect_to_settings();

        }

        $this->redirect_to_integration_tab(
            IntegrationsPage::TAB_WHATSAPP
        );

    }


    /**
     * Redirect to settings.
     *
     * @return void
     */
    private function redirect_to_settings(): void {

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => CheckoutSettingsPage::PAGE_SLUG,
                    'tab'  => 'general',
                ),
                admin_url(
                    'admin.php'
                )
            ) . '#eilmo-cf-optional-features'
        );

        exit;

    }


    /**
     * Redirect a legacy integration URL to its tab.
     *
     * @param string $tab Integration tab.
     *
     * @return void
     */
    private function redirect_to_integration_tab(
        string $tab
    ): void {

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => IntegrationsPage::PAGE_SLUG,
                    'tab'  => sanitize_key( $tab ),
                ),
                admin_url( 'admin.php' )
            )
        );

        exit;

    }


    /**
     * Verify access.
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
                'You do not have permission to access this page.',
                'eilmo-checkout-flow'
            )
        );

    }


    /**
     * Check feature enabled.
     *
     * @param string $feature Feature key.
     *
     * @return bool
     */
    private function is_feature_enabled(
        string $feature
    ): bool {

        $stored = get_option(
            CheckoutSettings::OPTION_NAME,
            array()
        );

        if (
            ! is_array(
                $stored
            )
        ) {

            $stored = array();

        }

        $settings = array_replace_recursive(
            CheckoutSettings::get_defaults(),
            $stored
        );

        return (
            isset(
                $settings['general'][$feature]
            )
            &&
            'yes' ===
            $settings['general'][$feature]
        );

    }


    /**
     * Get capability.
     *
     * @return string
     */
    private function get_capability(): string {

        return apply_filters(
            'eilmo_cf/admin_menu_capability',
            'manage_woocommerce'
        );

    }

}
