<?php
/**
 * Offers and discounts settings page.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Pages;

use EilmoCheckout\Admin\CheckoutSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders promotional offer and discount settings.
 */
final class OffersSettingsPage {

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = 'eilmo-checkout-offers';

	/**
	 * Available tabs.
	 *
	 * @return array<string, string>
	 */
	public static function get_tabs(): array {

		return array(
			'combo_offers' => __( 'Combo Offers', 'eilmo-checkout-flow' ),
			'discounts' => __( 'Special Discounts', 'eilmo-checkout-flow' ),
			'coupons' => __( 'Coupons', 'eilmo-checkout-flow' ),
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to access this page.',
					'eilmo-checkout-flow'
				)
			);
		}

		$settings = $this->get_settings();
		$tab      = $this->get_current_tab();

		?>
		<div class="wrap eilmo-cf-admin">
			<div class="eilmo-cf-admin__header">
				<div class="eilmo-cf-admin__heading">
					<h1>
						<?php esc_html_e( 'Offers & Discounts', 'eilmo-checkout-flow' ); ?>
					</h1>
					<p>
						<?php esc_html_e( 'Manage combo offers, conditional special discounts and coupons.', 'eilmo-checkout-flow' ); ?>
					</p>
				</div>
			</div>

			<?php
			settings_errors();
			$this->render_tabs( $tab );
			?>

			<form
				method="post"
				action="options.php"
				class="eilmo-cf-settings-form"
				id="eilmo-cf-offers-settings-form"
			>
				<?php
				settings_fields( CheckoutSettings::OPTION_GROUP );
				$this->render_tab( $tab, $settings );
				?>
				<div class="eilmo-cf-settings-form__submit" data-eilmo-settings-submit-area>
					<?php
					submit_button(
						__( 'Save Changes', 'eilmo-checkout-flow' ),
						'primary',
						'submit',
						false
					);
					?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render settings tabs.
	 *
	 * @param string $current_tab Current tab.
	 *
	 * @return void
	 */
	private function render_tabs(
		string $current_tab
	): void {

		?>
		<nav
			class="nav-tab-wrapper eilmo-cf-settings-tabs"
			aria-label="<?php esc_attr_e( 'Offers & Discounts', 'eilmo-checkout-flow' ); ?>"
		>
			<?php foreach ( self::get_tabs() as $tab_key => $tab_label ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
						'tab'  => $tab_key,
					),
					admin_url( 'admin.php' )
				);

				$classes = array( 'nav-tab' );

				if ( $current_tab === $tab_key ) {
					$classes[] = 'nav-tab-active';
				}
				?>
				<a
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					href="<?php echo esc_url( $url ); ?>"
				>
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render current tab.
	 *
	 * @param string               $tab      Current tab.
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_tab(
		string $tab,
		array $settings
	): void {

		switch ( $tab ) {
			case 'discounts':
				$this->render_special_discounts(
					$settings['discounts'],
					$settings['order_bumps']
				);
				break;

			case 'coupons':
				$this->render_coupons( $settings['coupons'] );
				break;

			case 'combo_offers':
			default:
				$this->render_combo_offers( $settings['combo_offers'] );
				break;
		}
	}

    /**
     * Render Combo Offer settings.
     *
     * Combo Offers are stored globally here.
     *
     * Enabled offers are exposed to the Elementor Combo Button widget.
     * The checkout form never renders Combo Offer cards.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return void
     */
    private function render_combo_offers(
        array $settings
    ): void {

        $offers =
            isset(
                $settings['offers']
            ) &&
            is_array(
                $settings['offers']
            )
                ? $settings['offers']
                : array();

        ?>
        <section class="eilmo-cf-settings-section">

            <?php
            $this->render_section_header(
                __(
                    'Combo Offers',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Create reusable product combos here. Customers add combos only through the Elementor Combo Button widget; the checkout form never shows a Combo Offer card.',
                    'eilmo-checkout-flow'
                )
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Enable Combo Offers',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[combo_offers][enabled]',
                                'yes',
                                (string) (
                                    $settings['enabled'] ??
                                        'no'
                                )
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Globally enable the Combo Offer library. Enabled combos become available in the Elementor Combo Button widget.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <p class="description"><?php esc_html_e( 'Combo placement and design are intentionally not configured here. Build the marketing section in Elementor and use the Combo Button widget to add or remove the selected combo.', 'eilmo-checkout-flow' ); ?></p>


            <hr>

            <div class="eilmo-cf-admin-repeater">

                <div class="eilmo-cf-admin-repeater__heading">

                    <div>
                        <h2>
                            <?php
                            esc_html_e(
                                'Global Combo Library',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </h2>

                        <p>
                            <?php
                            esc_html_e(
                                'Create unlimited reusable combo offers. Products, quantities and pricing are recalculated from trusted server-side data when an order is placed.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="button button-secondary"
                        data-eilmo-add-combo-offer
                    >
                        <?php
                        esc_html_e(
                            'Add Combo Offer',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </button>

					</div>

					<div
                    class="eilmo-cf-admin-repeater__items"
                    data-eilmo-combo-offers
                >

                    <?php foreach ( $offers as $index => $offer ) : ?>

                        <?php
                        if (
                            ! is_array(
                                $offer
                            )
                        ) {
                            continue;
                        }

                        $this->render_combo_offer(
                            (int) $index,
                            $offer
                        );
                        ?>

                    <?php endforeach; ?>

                </div>

                <?php if ( empty( $offers ) ) : ?>

                    <div
                        class="notice notice-info inline"
                        data-eilmo-combo-empty-notice
                    >
                        <p>
                            <?php
                            esc_html_e(
                                'No combo offers have been created yet. Click Add Combo Offer to create your first reusable offer.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                <?php endif; ?>

            </div>

        </section>
        <?php
    }


    /**
     * Render one Combo Offer.
     *
     * @param int                  $index Combo index.
     * @param array<string, mixed> $offer Offer.
     *
     * @return void
     */
    private function render_combo_offer(
        int $index,
        array $offer
    ): void {

        $prefix =
            sprintf(
                'eilmo_cf_settings[combo_offers][offers][%d]',
                $index
            );

        $title =
            sanitize_text_field(
                (string) (
                    $offer['title'] ??
                        ''
                )
            );

        $offer_id =
            sanitize_key(
                (string) (
                    $offer['id'] ??
                        ''
                )
            );

        $pricing_type =
            sanitize_key(
                (string) (
                    $offer['pricing_type'] ??
                        'fixed_price'
                )
            );

		$icon_type = sanitize_key( (string) ( $offer['icon_type'] ?? 'preset' ) );
		$icon_preset = sanitize_key( (string) ( $offer['icon_preset'] ?? 'flame' ) );
		$icon_media_id = absint( $offer['icon_media_id'] ?? 0 );
		$icon_media_url = $icon_media_id > 0
			? (string) wp_get_attachment_image_url( $icon_media_id, 'thumbnail' )
			: '';

        $items =
            isset(
                $offer['items']
            ) &&
            is_array(
                $offer['items']
            )
                ? $offer['items']
                : array();

        ?>
        <div
            class="eilmo-cf-admin-repeater__item eilmo-cf-admin-combo"
            data-eilmo-combo-offer
            data-eilmo-combo-index="<?php echo esc_attr( (string) $index ); ?>"
        >

            <div class="eilmo-cf-admin-repeater__item-header">

                <button
                    type="button"
                    class="button-link eilmo-cf-admin-combo__toggle"
                    data-eilmo-combo-toggle
                    aria-expanded="false"
                >
                    <span
                        class="eilmo-cf-admin-combo__toggle-icon"
                        data-eilmo-combo-toggle-icon
                        aria-hidden="true"
                    >
                        ▶
                    </span>

                    <strong data-eilmo-combo-title>
                        <?php
                        echo esc_html(
                            '' !== $title
                                ? $title
                                : __(
                                    'Combo Offer',
                                    'eilmo-checkout-flow'
                                )
                        );
                        ?>
                    </strong>
                </button>

                <button
                    type="button"
                    class="button-link-delete"
                    data-eilmo-remove-repeater-item
                >
                    <?php
                    esc_html_e(
                        'Remove',
                        'eilmo-checkout-flow'
                    );
                    ?>
                </button>

            </div>

            <div
                class="eilmo-cf-admin-combo__content"
                data-eilmo-combo-content
                hidden
            >

                <div class="eilmo-cf-admin-grid">

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Combo Title',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <input
                            type="text"
                            name="<?php echo esc_attr( $prefix . '[title]' ); ?>"
                            value="<?php echo esc_attr( $title ); ?>"
                            placeholder="<?php esc_attr_e( 'e.g. T-Shirt + Cap Combo', 'eilmo-checkout-flow' ); ?>"
                            data-eilmo-combo-title-input
                        >
                    </div>

                    <div class="eilmo-cf-admin-field" hidden>
                        <label>
                            <?php
                            esc_html_e(
                                'Combo ID',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <input
                            type="text"
                            name="<?php echo esc_attr( $prefix . '[id]' ); ?>"
                            value="<?php echo esc_attr( $offer_id ); ?>"
                            placeholder="tshirt-cap-combo"
                            data-eilmo-combo-id
                        >

                        <p class="description">
                            <?php
                            esc_html_e(
                                'Leave empty to generate an ID from the Combo Title when settings are saved. IDs are kept unique automatically.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="eilmo-cf-admin-field" hidden>
                        <label>
                            <?php
                            esc_html_e(
                                'Badge',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <input
                            type="text"
                            name="<?php echo esc_attr( $prefix . '[badge]' ); ?>"
                            value="<?php
                            echo esc_attr(
                                (string) (
                                    $offer['badge'] ??
                                        ''
                                )
                            );
                            ?>"
                            placeholder="<?php esc_attr_e( 'e.g. Best Deal', 'eilmo-checkout-flow' ); ?>"
                        >
                    </div>

					<div class="eilmo-cf-admin-field" hidden>
						<label><?php esc_html_e( 'Eyebrow Label', 'eilmo-checkout-flow' ); ?></label>
						<input type="text" name="<?php echo esc_attr( $prefix . '[eyebrow]' ); ?>" value="<?php echo esc_attr( (string) ( $offer['eyebrow'] ?? 'COMBO OFFER' ) ); ?>" placeholder="<?php esc_attr_e( 'COMBO OFFER', 'eilmo-checkout-flow' ); ?>">
					</div>

					<div class="eilmo-cf-admin-field" hidden>
						<label><?php esc_html_e( 'Combo Icon', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix . '[icon_type]' ); ?>" data-eilmo-combo-icon-type>
							<?php $this->render_option( 'preset', __( 'Preset SVG Icon', 'eilmo-checkout-flow' ), $icon_type ); ?>
							<?php $this->render_option( 'custom', __( 'Custom Media Icon', 'eilmo-checkout-flow' ), $icon_type ); ?>
							<?php $this->render_option( 'none', __( 'No Icon', 'eilmo-checkout-flow' ), $icon_type ); ?>
						</select>
					</div>

					<div class="eilmo-cf-admin-field" data-eilmo-combo-icon-preset-wrap hidden>
						<label><?php esc_html_e( 'SVG Icon', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix . '[icon_preset]' ); ?>">
							<?php $this->render_option( 'flame', __( 'Flame', 'eilmo-checkout-flow' ), $icon_preset ); ?>
							<?php $this->render_option( 'gift', __( 'Gift', 'eilmo-checkout-flow' ), $icon_preset ); ?>
							<?php $this->render_option( 'star', __( 'Star', 'eilmo-checkout-flow' ), $icon_preset ); ?>
							<?php $this->render_option( 'bolt', __( 'Lightning', 'eilmo-checkout-flow' ), $icon_preset ); ?>
						</select>
					</div>

					<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-combo-icon-media-wrap hidden>
						<label><?php esc_html_e( 'Custom Icon', 'eilmo-checkout-flow' ); ?></label>
						<div class="eilmo-cf-admin-single-media" data-eilmo-combo-media-picker>
							<input type="hidden" name="<?php echo esc_attr( $prefix . '[icon_media_id]' ); ?>" value="<?php echo esc_attr( (string) $icon_media_id ); ?>" data-eilmo-combo-media-id>
							<div class="eilmo-cf-admin-single-media__preview" data-eilmo-combo-media-preview>
								<?php if ( '' !== $icon_media_url ) : ?><img src="<?php echo esc_url( $icon_media_url ); ?>" alt=""><?php endif; ?>
							</div>
							<div class="eilmo-cf-admin-single-media__actions">
								<button type="button" class="button button-secondary" data-eilmo-select-combo-media><?php esc_html_e( 'Select Icon', 'eilmo-checkout-flow' ); ?></button>
								<button type="button" class="button button-link-delete" data-eilmo-clear-combo-media<?php if ( $icon_media_id <= 0 ) : ?> hidden<?php endif; ?>><?php esc_html_e( 'Remove', 'eilmo-checkout-flow' ); ?></button>
							</div>
						</div>
					</div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Pricing Type',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <select
                            name="<?php echo esc_attr( $prefix . '[pricing_type]' ); ?>"
                            data-eilmo-combo-pricing-type
                        >
                            <?php
                            $this->render_option(
                                'fixed_price',
                                __(
                                    'Fixed Combo Price',
                                    'eilmo-checkout-flow'
                                ),
                                $pricing_type
                            );

                            $this->render_option(
                                'percentage_discount',
                                __(
                                    'Percentage Discount',
                                    'eilmo-checkout-flow'
                                ),
                                $pricing_type
                            );

                            $this->render_option(
                                'fixed_discount',
                                __(
                                    'Fixed Discount',
                                    'eilmo-checkout-flow'
                                ),
                                $pricing_type
                            );
                            ?>
                        </select>

                        <p class="description">
                            <?php
                            esc_html_e(
                                'Fixed Combo Price sets the final price of all combo items. Percentage Discount and Fixed Discount reduce the normal combined product price.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Pricing Value',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <input
                            type="number"
                            name="<?php echo esc_attr( $prefix . '[pricing_value]' ); ?>"
                            value="<?php
                            echo esc_attr(
                                (string) (
                                    $offer['pricing_value'] ??
                                        0
                                )
                            );
                            ?>"
                            min="0"
                            step="0.01"
                        >

                        <p class="description">
                            <?php
                            esc_html_e(
                                'For Fixed Combo Price enter the final combo price. For Percentage Discount enter a percentage. For Fixed Discount enter the amount to deduct.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Sort Order',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <input
                            type="number"
                            name="<?php echo esc_attr( $prefix . '[sort_order]' ); ?>"
                            value="<?php
                            echo esc_attr(
                                (string) (
                                    $offer['sort_order'] ??
                                        10
                                )
                            );
                            ?>"
                            min="0"
                            step="1"
                        >
                    </div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Enabled',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <?php
                        $this->render_switch(
                            $prefix . '[enabled]',
                            'yes',
                            (string) (
                                $offer['enabled'] ??
                                    'yes'
                            )
                        );
                        ?>
                    </div>

                    <div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" hidden>
                        <label>
                            <?php
                            esc_html_e(
                                'Description',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <textarea
                            name="<?php echo esc_attr( $prefix . '[description]' ); ?>"
                            rows="3"
                            placeholder="<?php esc_attr_e( 'Short description shown with this combo offer.', 'eilmo-checkout-flow' ); ?>"
                        ><?php
                        echo esc_textarea(
                            (string) (
                                $offer['description'] ??
                                    ''
                            )
                        );
                        ?></textarea>
                    </div>

                </div>

                <hr>

                <h3>
                    <?php
                    esc_html_e(
                        'Discount Compatibility',
                        'eilmo-checkout-flow'
                    );
                    ?>
                </h3>

                <p class="description">
                    <?php
                    esc_html_e(
                        'Choose which additional discounts may be applied to this Combo Offer after its own combo pricing has been calculated.',
                        'eilmo-checkout-flow'
                    );
                    ?>
                </p>

                <div class="eilmo-cf-admin-grid">

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Automatic Discount',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <?php
                        $this->render_switch(
                            $prefix . '[apply_automatic_discount]',
                            'yes',
                            (string) (
                                $offer['apply_automatic_discount'] ??
                                    'no'
                            )
                        );
                        ?>

                        <p class="description">
                            <?php
                            esc_html_e(
                                'Allow this Combo Offer total to participate in Automatic Discount calculation.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Coupon Discount',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <?php
                        $this->render_switch(
                            $prefix . '[apply_coupon]',
                            'yes',
                            (string) (
                                $offer['apply_coupon'] ??
                                    'yes'
                            )
                        );
                        ?>

                        <p class="description">
                            <?php
                            esc_html_e(
                                'Allow eligible coupons to discount this Combo Offer.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="eilmo-cf-admin-field">
                        <label>
                            <?php
                            esc_html_e(
                                'Full Payment Discount',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </label>

                        <?php
                        $this->render_switch(
                            $prefix . '[apply_full_payment_discount]',
                            'yes',
                            (string) (
                                $offer['apply_full_payment_discount'] ??
                                    'yes'
                            )
                        );
                        ?>

                        <p class="description">
                            <?php
                            esc_html_e(
                                'Allow the Full Payment Discount to apply to this Combo Offer.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                </div>

                <div class="eilmo-cf-admin-combo__products">

                    <div class="eilmo-cf-admin-repeater__heading">

                        <div>
                            <h3>
                                <?php
                                esc_html_e(
                                    'Combo Products',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </h3>

                            <p>
                                <?php
                                esc_html_e(
                                    'Add every product that belongs to this combo. Variation ID is optional for simple products.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="button button-secondary"
                            data-eilmo-add-combo-item
                            data-eilmo-combo-index="<?php echo esc_attr( (string) $index ); ?>"
                        >
                            <?php
                            esc_html_e(
                                'Add Product',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </button>

                    </div>

                    <div
                        class="eilmo-cf-admin-repeater__items"
                        data-eilmo-combo-items
                    >

                        <?php foreach ( $items as $item_index => $item ) : ?>

                            <?php
                            if (
                                ! is_array(
                                    $item
                                )
                            ) {
                                continue;
                            }

                            $this->render_combo_item(
                                $index,
                                (int) $item_index,
                                $item
                            );
                            ?>

                        <?php endforeach; ?>

                    </div>

                    <?php if ( empty( $items ) ) : ?>

                        <p
                            class="description"
                            data-eilmo-combo-items-empty
                        >
                            <?php
                            esc_html_e(
                                'Add at least one product to this combo. Two or more products are recommended for a traditional combo offer.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>
        <?php
    }

    /**
     * Render one product row inside a Combo Offer.
     *
     * @param int                  $combo_index Combo index.
     * @param int                  $item_index  Product row index.
     * @param array<string, mixed> $item        Item.
     *
     * @return void
     */
    private function render_combo_item(
        int $combo_index,
        int $item_index,
        array $item
    ): void {

        $prefix =
            sprintf(
                'eilmo_cf_settings[combo_offers][offers][%1$d][items][%2$d]',
                $combo_index,
                $item_index
            );

        $product_id =
            absint(
                $item['product_id'] ??
                    0
            );

        $variation_id =
            absint(
                $item['variation_id'] ??
                    0
            );

        $quantity =
            isset(
                $item['quantity']
            ) &&
            is_numeric(
                $item['quantity']
            )
                ? max(
                    0,
                    (float) $item['quantity']
                )
                : 1;

		$image_source = sanitize_key( (string) ( $item['image_source'] ?? 'product' ) );
		$image_id = absint( $item['image_id'] ?? 0 );
		$image_url = $image_id > 0
			? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' )
			: '';

        $product_label =
            $this->get_combo_product_label(
                $product_id,
                $variation_id
            );

        ?>
        <div
            class="eilmo-cf-admin-repeater__item eilmo-cf-admin-combo__product"
            data-eilmo-combo-item
            data-eilmo-combo-item-index="<?php echo esc_attr( (string) $item_index ); ?>"
        >

            <div class="eilmo-cf-admin-repeater__item-header">

                <strong data-eilmo-combo-item-title>
                    <?php
                    echo esc_html(
                        '' !== $product_label
                            ? $product_label
                            : __(
                                'Combo Product',
                                'eilmo-checkout-flow'
                            )
                    );
                    ?>
                </strong>

                <button
                    type="button"
                    class="button-link-delete"
                    data-eilmo-remove-repeater-item
                >
                    <?php
                    esc_html_e(
                        'Remove',
                        'eilmo-checkout-flow'
                    );
                    ?>
                </button>

            </div>

            <div class="eilmo-cf-admin-grid">

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Product ID',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="number"
                        name="<?php echo esc_attr( $prefix . '[product_id]' ); ?>"
                        value="<?php echo esc_attr( (string) $product_id ); ?>"
                        min="1"
                        step="1"
                        placeholder="123"
                        data-eilmo-combo-product-id
                    >

                    <p class="description">
                        <?php
                        esc_html_e(
                            'Enter the WooCommerce parent/simple product ID.',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </p>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Variation ID',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="number"
                        name="<?php echo esc_attr( $prefix . '[variation_id]' ); ?>"
                        value="<?php echo esc_attr( (string) $variation_id ); ?>"
                        min="0"
                        step="1"
                        placeholder="0"
                        data-eilmo-combo-variation-id
                    >

                    <p class="description">
                        <?php
                        esc_html_e(
                            'Use 0 for a simple product. For a variable product, enter the exact variation ID.',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </p>
                </div>

                <div class="eilmo-cf-admin-field">
                    <label>
                        <?php
                        esc_html_e(
                            'Quantity',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </label>

                    <input
                        type="number"
                        name="<?php echo esc_attr( $prefix . '[quantity]' ); ?>"
                        value="<?php echo esc_attr( (string) $quantity ); ?>"
                        min="0.01"
                        step="0.01"
                    >
                </div>

				<div class="eilmo-cf-admin-field">
					<label><?php esc_html_e( 'Image Source', 'eilmo-checkout-flow' ); ?></label>
					<select name="<?php echo esc_attr( $prefix . '[image_source]' ); ?>" data-eilmo-combo-image-source>
						<?php $this->render_option( 'product', __( 'Product Image', 'eilmo-checkout-flow' ), $image_source ); ?>
						<?php $this->render_option( 'custom', __( 'Custom Media Image', 'eilmo-checkout-flow' ), $image_source ); ?>
						<?php $this->render_option( 'hidden', __( 'Hide Image', 'eilmo-checkout-flow' ), $image_source ); ?>
					</select>
				</div>

				<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-combo-item-media-wrap>
					<label><?php esc_html_e( 'Custom Product Image', 'eilmo-checkout-flow' ); ?></label>
					<div class="eilmo-cf-admin-single-media" data-eilmo-combo-media-picker>
						<input type="hidden" name="<?php echo esc_attr( $prefix . '[image_id]' ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-eilmo-combo-media-id>
						<div class="eilmo-cf-admin-single-media__preview" data-eilmo-combo-media-preview>
							<?php if ( '' !== $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt=""><?php endif; ?>
						</div>
						<div class="eilmo-cf-admin-single-media__actions">
							<button type="button" class="button button-secondary" data-eilmo-select-combo-media><?php esc_html_e( 'Select Image', 'eilmo-checkout-flow' ); ?></button>
							<button type="button" class="button button-link-delete" data-eilmo-clear-combo-media<?php if ( $image_id <= 0 ) : ?> hidden<?php endif; ?>><?php esc_html_e( 'Remove', 'eilmo-checkout-flow' ); ?></button>
						</div>
					</div>
				</div>

            </div>

        </div>
        <?php
    }

    /**
     * Get readable product label for Combo Offer admin.
     *
     * @param int $product_id   Product ID.
     * @param int $variation_id Variation ID.
     *
     * @return string
     */
	private function get_combo_product_label(
        int $product_id,
        int $variation_id
    ): string {

        $lookup_id =
            $variation_id > 0
                ? $variation_id
                : $product_id;

        if (
            $lookup_id <= 0 ||
            ! function_exists(
                'wc_get_product'
            )
        ) {
            return '';
        }

        $product =
            wc_get_product(
                $lookup_id
            );

        if (
            ! $product
        ) {
            return '';
        }

        $name =
            sanitize_text_field(
                (string) $product
                    ->get_name()
            );

        if (
            '' === $name
        ) {
            return '';
        }

		return sprintf(
            /* translators: 1: Product name, 2: Product ID. */
            __(
                '%1$s (#%2$d)',
                'eilmo-checkout-flow'
            ),
            $name,
            $lookup_id
		);
	}

	/**
	 * Render the single merchant-facing Special Discounts system.
	 *
	 * The historical order_bumps storage key remains an internal compatibility
	 * layer, but it is no longer exposed as a separate feature or tab.
	 *
	 * @param array<string, mixed> $discounts Discount engine settings.
	 * @param array<string, mixed> $rules     Unified rule library settings.
	 *
	 * @return void
	 */
	private function render_special_discounts(
		array $discounts,
		array $rules
	): void {
		?>
		<input type="hidden" name="eilmo_cf_settings[discounts][stacking_mode]" value="<?php echo esc_attr( (string) ( $discounts['stacking_mode'] ?? 'best_discount' ) ); ?>">
		<input type="hidden" name="eilmo_cf_settings[discounts][allow_coupon_stacking]" value="<?php echo esc_attr( (string) ( $discounts['allow_coupon_stacking'] ?? 'no' ) ); ?>">
		<input type="hidden" name="eilmo_cf_settings[discounts][automatic_notification][enabled]" value="no">
		<input type="hidden" name="eilmo_cf_settings[discounts][automatic_notification][show_tiers]" value="no">
		<input type="hidden" name="eilmo_cf_settings[discounts][automatic_notification][show_status]" value="no">
		<?php
		$this->render_order_bumps( $rules );
	}

	/**
	 * Render Order Bumps.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return void
	 */
	private function render_order_bumps(
		array $settings
	): void {
		$offers = isset( $settings['offers'] ) && is_array( $settings['offers'] ) ? $settings['offers'] : array();
		?>
		<section class="eilmo-cf-settings-section eilmo-cf-admin-special-discounts-simple">
			<?php
			$this->render_section_header(
				__( 'Special Discounts', 'eilmo-checkout-flow' ),
				__( 'Automatic CONDITION → REWARD rules. Campaign artwork, countdowns and marketing copy belong in Elementor; checkout only applies the real benefit.', 'eilmo-checkout-flow' )
			);
			?>

			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Special Discounts', 'eilmo-checkout-flow' ); ?></th>
					<td><?php $this->render_switch( 'eilmo_cf_settings[order_bumps][enabled]', 'yes', (string) ( $settings['enabled'] ?? 'no' ) ); ?></td>
				</tr>
			</tbody></table>

			<?php /* Preserve legacy presentation settings without exposing them in the rule workflow. */ ?>
			<input type="hidden" name="eilmo_cf_settings[order_bumps][title]" value="<?php echo esc_attr( (string) ( $settings['title'] ?? 'Special Discounts' ) ); ?>">
			<input type="hidden" name="eilmo_cf_settings[order_bumps][selection_mode]" value="multiple">
			<input type="hidden" name="eilmo_cf_settings[order_bumps][position]" value="<?php echo esc_attr( (string) ( $settings['position'] ?? 'before_delivery' ) ); ?>">
			<input type="hidden" name="eilmo_cf_settings[order_bumps][priority]" value="<?php echo esc_attr( (string) absint( $settings['priority'] ?? 20 ) ); ?>">

			<hr>
			<div class="eilmo-cf-admin-repeater">
				<input type="hidden" name="eilmo_cf_settings[order_bumps][offers_submitted]" value="yes">
				<div class="eilmo-cf-admin-repeater__heading">
					<div>
						<h2><?php esc_html_e( 'Special Discount Rules', 'eilmo-checkout-flow' ); ?></h2>
						<p><?php esc_html_e( 'Each rule has one scope, one condition and one reward. Only fields relevant to that reward are shown.', 'eilmo-checkout-flow' ); ?></p>
					</div>
					<button type="button" class="button button-secondary" data-eilmo-add-order-bump><?php esc_html_e( 'Add Special Discount', 'eilmo-checkout-flow' ); ?></button>
				</div>

				<div class="eilmo-cf-admin-repeater__items" data-eilmo-order-bumps>
					<?php foreach ( $offers as $index => $offer ) : if ( is_array( $offer ) ) { $this->render_order_bump( (int) $index, $offer ); } endforeach; ?>
				</div>

				<?php if ( empty( $offers ) ) : ?>
					<div class="notice notice-info inline" data-eilmo-order-bump-empty><p><?php esc_html_e( 'No Special Discount rules have been created yet.', 'eilmo-checkout-flow' ); ?></p></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render one Order Bump.
	 *
	 * @param int                  $index Index.
	 * @param array<string, mixed> $offer Offer.
	 *
	 * @return void
	 */
	private function render_order_bump(
		int $index,
		array $offer
	): void {
		$prefix = sprintf( 'eilmo_cf_settings[order_bumps][offers][%d]', $index );
		$title = sanitize_text_field( (string) ( $offer['title'] ?? '' ) );
		$id = sanitize_key( (string) ( $offer['id'] ?? '' ) );
		$enabled = 'no' === ( $offer['enabled'] ?? 'yes' ) ? 'no' : 'yes';
		$scope = sanitize_key( (string) ( $offer['rule_scope'] ?? 'whole_cart' ) );
		if ( ! in_array( $scope, array( 'current_product', 'selected_products', 'whole_cart' ), true ) ) { $scope = 'whole_cart'; }
		$condition = sanitize_key( (string) ( $offer['condition_type'] ?? 'always' ) );
		$reward = sanitize_key( (string) ( $offer['offer_type'] ?? 'percentage_discount' ) );
		if ( ! in_array( $reward, array( 'percentage_discount', 'fixed_discount', 'free_gift', 'free_delivery' ), true ) ) { $reward = 'percentage_discount'; }
		$scope_ids = isset( $offer['scope_product_ids'] ) && is_array( $offer['scope_product_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $offer['scope_product_ids'] ) ) ) ) : array();
		$delivery_settings = $this->get_settings();
		$delivery_methods = isset( $delivery_settings['delivery']['methods'] ) && is_array( $delivery_settings['delivery']['methods'] ) ? $delivery_settings['delivery']['methods'] : array();

		$condition_label = __( 'Always', 'eilmo-checkout-flow' );
		if ( in_array( $condition, array( 'cart_quantity', 'minimum_quantity', 'buy_x_quantity' ), true ) ) {
			$condition_label = sprintf( __( 'Qty %d+', 'eilmo-checkout-flow' ), max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ) );
		} elseif ( in_array( $condition, array( 'order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal' ), true ) ) {
			$amount = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( (float) ( $offer['condition_minimum'] ?? 0 ) ) ) : (string) ( $offer['condition_minimum'] ?? 0 );
			$condition_label = 'minimum_spend' === $condition
				? sprintf( __( 'Spend %s+', 'eilmo-checkout-flow' ), $amount )
				: sprintf( __( 'Subtotal %s+', 'eilmo-checkout-flow' ), $amount );
		} elseif ( 'specific_product' === $condition || 'product_exists' === $condition ) {
			$condition_label = __( 'Selected product exists', 'eilmo-checkout-flow' );
		}
		$reward_label = array(
			'percentage_discount' => __( 'Percentage Discount', 'eilmo-checkout-flow' ),
			'fixed_discount'      => __( 'Fixed Discount', 'eilmo-checkout-flow' ),
			'free_gift'           => __( 'Free Gift', 'eilmo-checkout-flow' ),
			'free_delivery'       => __( 'Free Delivery', 'eilmo-checkout-flow' ),
		)[ $reward ];
		?>
		<div class="eilmo-cf-admin-repeater__item eilmo-cf-admin-special-offer" data-eilmo-special-offer-admin data-eilmo-order-bump>
			<div class="eilmo-cf-admin-repeater__item-header">
				<button type="button" class="button-link eilmo-cf-admin-special-offer__toggle" data-eilmo-special-offer-toggle aria-expanded="false">
					<span class="eilmo-cf-admin-special-offer__toggle-icon" data-eilmo-special-offer-toggle-icon aria-hidden="true">▶</span>
					<strong data-eilmo-order-bump-title><?php echo esc_html( '' !== $title ? $title : __( 'Special Discount', 'eilmo-checkout-flow' ) ); ?></strong>
					<small class="eilmo-cf-admin-rule-summary"><?php echo esc_html( ( 'yes' === $enabled ? __( 'Enabled', 'eilmo-checkout-flow' ) : __( 'Disabled', 'eilmo-checkout-flow' ) ) . ' · ' . $condition_label . ' → ' . $reward_label ); ?></small>
				</button>
				<button type="button" class="button-link-delete" data-eilmo-remove-repeater-item><?php esc_html_e( 'Remove', 'eilmo-checkout-flow' ); ?></button>
			</div>

			<div class="eilmo-cf-admin-special-offer__content" data-eilmo-special-offer-content hidden>
				<div class="eilmo-cf-admin-grid">
					<div class="eilmo-cf-admin-field">
						<label><?php esc_html_e( 'Rule Name', 'eilmo-checkout-flow' ); ?></label>
						<input type="text" name="<?php echo esc_attr( $prefix ); ?>[title]" value="<?php echo esc_attr( $title ); ?>" data-eilmo-order-bump-title-input placeholder="<?php esc_attr_e( 'Buy 2 Get Free Delivery', 'eilmo-checkout-flow' ); ?>">
						<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $id ); ?>" data-eilmo-order-bump-id>
					</div>
					<div class="eilmo-cf-admin-field"><label><?php esc_html_e( 'Enable', 'eilmo-checkout-flow' ); ?></label><?php $this->render_switch( $prefix . '[enabled]', 'yes', $enabled ); ?></div>

					<div class="eilmo-cf-admin-field">
						<label><?php esc_html_e( 'Scope', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix ); ?>[rule_scope]" data-eilmo-special-rule-scope>
							<?php $this->render_option( 'current_product', __( 'Current Checkout Product', 'eilmo-checkout-flow' ), $scope ); ?>
							<?php $this->render_option( 'selected_products', __( 'Selected Products', 'eilmo-checkout-flow' ), $scope ); ?>
							<?php $this->render_option( 'whole_cart', __( 'Whole Cart / Order', 'eilmo-checkout-flow' ), $scope ); ?>
						</select>
					</div>
					<div class="eilmo-cf-admin-field eilmo-cf-admin-field--wide" data-eilmo-special-scope-products<?php if ( 'selected_products' !== $scope ) : ?> hidden<?php endif; ?>>
						<label><?php esc_html_e( 'Products', 'eilmo-checkout-flow' ); ?></label>
						<select class="wc-product-search" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $prefix ); ?>[scope_product_ids][]" data-placeholder="<?php esc_attr_e( 'Search products…', 'eilmo-checkout-flow' ); ?>" data-action="woocommerce_json_search_products_and_variations">
							<?php foreach ( $scope_ids as $product_id ) : $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null; if ( $product ) : ?>
								<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected><?php echo esc_html( $product->get_formatted_name() ); ?></option>
							<?php endif; endforeach; ?>
						</select>
					</div>

					<div class="eilmo-cf-admin-field">
						<label><?php esc_html_e( 'Condition Type', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix ); ?>[condition_type]" data-eilmo-special-condition-type>
							<?php $this->render_option( 'always', __( 'Always / No minimum', 'eilmo-checkout-flow' ), $condition ); ?>
							<?php $this->render_option( 'minimum_quantity', __( 'Minimum Quantity', 'eilmo-checkout-flow' ), $condition ); ?>
							<?php $this->render_option( 'minimum_spend', __( 'Minimum Spend', 'eilmo-checkout-flow' ), $condition ); ?>
							<?php $this->render_option( 'minimum_subtotal', __( 'Minimum Subtotal', 'eilmo-checkout-flow' ), $condition ); ?>
							<?php $this->render_option( 'specific_product', __( 'Selected Product Exists', 'eilmo-checkout-flow' ), $condition ); ?>
						</select>
					</div>
					<div class="eilmo-cf-admin-field" data-eilmo-condition-cart-quantity><label><?php esc_html_e( 'Quantity', 'eilmo-checkout-flow' ); ?></label><input type="number" min="1" step="1" name="<?php echo esc_attr( $prefix ); ?>[condition_cart_quantity]" value="<?php echo esc_attr( (string) max( 1, absint( $offer['condition_cart_quantity'] ?? 1 ) ) ); ?>"></div>
					<div class="eilmo-cf-admin-field" data-eilmo-condition-amount><label data-eilmo-condition-amount-label><?php esc_html_e( 'Minimum Amount', 'eilmo-checkout-flow' ); ?></label><input type="number" min="0" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[condition_minimum]" value="<?php echo esc_attr( (string) ( $offer['condition_minimum'] ?? 0 ) ); ?>"></div>
					<div class="eilmo-cf-admin-field" data-eilmo-condition-product><label><?php esc_html_e( 'Required Product ID', 'eilmo-checkout-flow' ); ?></label><input type="number" min="1" step="1" name="<?php echo esc_attr( $prefix ); ?>[condition_product_id]" value="<?php echo esc_attr( (string) absint( $offer['condition_product_id'] ?? 0 ) ); ?>"></div>
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[condition_product_quantity]" value="<?php echo esc_attr( (string) max( 1, absint( $offer['condition_product_quantity'] ?? 1 ) ) ); ?>">
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[condition_maximum]" value="<?php echo esc_attr( (string) ( $offer['condition_maximum'] ?? 0 ) ); ?>">

					<div class="eilmo-cf-admin-field">
						<label><?php esc_html_e( 'Reward Type', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix ); ?>[offer_type]" data-eilmo-special-offer-type>
							<?php $this->render_option( 'percentage_discount', __( 'Percentage Discount', 'eilmo-checkout-flow' ), $reward ); ?>
							<?php $this->render_option( 'fixed_discount', __( 'Fixed Amount Discount', 'eilmo-checkout-flow' ), $reward ); ?>
							<?php $this->render_option( 'free_gift', __( 'Free Gift', 'eilmo-checkout-flow' ), $reward ); ?>
							<?php $this->render_option( 'free_delivery', __( 'Free Delivery', 'eilmo-checkout-flow' ), $reward ); ?>
						</select>
					</div>
					<div class="eilmo-cf-admin-field" data-eilmo-special-pricing-value-field><label><?php esc_html_e( 'Discount Value', 'eilmo-checkout-flow' ); ?></label><input type="number" min="0" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[pricing_value]" value="<?php echo esc_attr( (string) ( $offer['pricing_value'] ?? 0 ) ); ?>"><p class="description"><?php esc_html_e( 'Percentage for Percentage Discount, currency amount for Fixed Discount.', 'eilmo-checkout-flow' ); ?></p></div>
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[pricing_type]" value="<?php echo esc_attr( 'fixed_discount' === $reward ? 'fixed_discount' : ( 'percentage_discount' === $reward ? 'percentage_discount' : ( 'free_gift' === $reward ? 'fixed_price' : 'regular_price' ) ) ); ?>">
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[maximum_discount]" value="<?php echo esc_attr( (string) ( $offer['maximum_discount'] ?? 0 ) ); ?>">

					<div class="eilmo-cf-admin-field" data-eilmo-special-product-field>
						<label><?php esc_html_e( 'Gift Product', 'eilmo-checkout-flow' ); ?></label>
						<select class="wc-product-search" style="width:100%" name="<?php echo esc_attr( $prefix ); ?>[product_id]" data-placeholder="<?php esc_attr_e( 'Search a gift product…', 'eilmo-checkout-flow' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
							<?php $gift_id = absint( $offer['product_id'] ?? 0 ); $gift = $gift_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $gift_id ) : null; if ( $gift ) : ?><option value="<?php echo esc_attr( (string) $gift_id ); ?>" selected><?php echo esc_html( $gift->get_formatted_name() ); ?></option><?php endif; ?>
						</select>
					</div>
					<div class="eilmo-cf-admin-field" data-eilmo-special-product-field><label><?php esc_html_e( 'Gift Quantity', 'eilmo-checkout-flow' ); ?></label><input type="number" min="1" step="1" name="<?php echo esc_attr( $prefix ); ?>[quantity]" value="<?php echo esc_attr( (string) max( 1, absint( $offer['quantity'] ?? 1 ) ) ); ?>"></div>
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[variation_id]" value="<?php echo esc_attr( (string) absint( $offer['variation_id'] ?? 0 ) ); ?>">

					<div class="eilmo-cf-admin-field" data-eilmo-special-free-delivery-field>
						<label><?php esc_html_e( 'Delivery Method', 'eilmo-checkout-flow' ); ?></label>
						<select name="<?php echo esc_attr( $prefix ); ?>[free_delivery_method_id]">
							<option value=""><?php esc_html_e( 'All Delivery Methods', 'eilmo-checkout-flow' ); ?></option>
							<?php foreach ( $delivery_methods as $method ) : if ( ! is_array( $method ) ) { continue; } $method_id = sanitize_key( (string) ( $method['id'] ?? '' ) ); if ( '' === $method_id ) { continue; } ?><option value="<?php echo esc_attr( $method_id ); ?>" <?php selected( $method_id, sanitize_key( (string) ( $offer['free_delivery_method_id'] ?? '' ) ) ); ?>><?php echo esc_html( (string) ( $method['label'] ?? $method_id ) ); ?></option><?php endforeach; ?>
						</select>
					</div>
					<div class="eilmo-cf-admin-field" data-eilmo-special-free-delivery-field><label><?php esc_html_e( 'Hide Other Methods', 'eilmo-checkout-flow' ); ?></label><?php $this->render_switch( $prefix . '[free_delivery_hide_other_methods]', 'yes', (string) ( $offer['free_delivery_hide_other_methods'] ?? 'no' ) ); ?></div>

					<div class="eilmo-cf-admin-field"><label><?php esc_html_e( 'Start Date (Optional)', 'eilmo-checkout-flow' ); ?></label><input type="datetime-local" name="<?php echo esc_attr( $prefix ); ?>[start_at]" value="<?php echo esc_attr( (string) ( $offer['start_at'] ?? '' ) ); ?>"></div>
					<div class="eilmo-cf-admin-field"><label><?php esc_html_e( 'End Date (Optional)', 'eilmo-checkout-flow' ); ?></label><input type="datetime-local" name="<?php echo esc_attr( $prefix ); ?>[end_at]" value="<?php echo esc_attr( (string) ( $offer['end_at'] ?? '' ) ); ?>"></div>
					<div class="eilmo-cf-admin-field"><label><?php esc_html_e( 'Priority', 'eilmo-checkout-flow' ); ?></label><input type="number" min="0" step="1" name="<?php echo esc_attr( $prefix ); ?>[sort_order]" value="<?php echo esc_attr( (string) absint( $offer['sort_order'] ?? 10 ) ); ?>"></div>

					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[apply_behavior]" value="auto_apply">
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[ineligible_action]" value="hide">
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[stop_processing]" value="no">
				</div>
			</div>
		</div>
		<?php
	}



    /**
     * Render coupon settings.
     *
     * @param array<string, mixed> $settings Settings.
     *
     * @return void
     */
    private function render_coupons(
        array $settings
    ): void {

        $coupons =
            isset(
                $settings['custom_coupons']
            ) &&
            is_array(
                $settings['custom_coupons']
            )
                ? $settings['custom_coupons']
                : array();

        $customer_identification =
            (string) (
                $settings['customer_identification'] ??
                    'email_or_phone'
            );

        $require_contact =
            (string) (
                $settings['require_contact_for_customer_limited'] ??
                    'yes'
            );

        ?>
        <section class="eilmo-cf-settings-section">

            <?php
            $this->render_section_header(
                __(
                    'Coupon Settings',
                    'eilmo-checkout-flow'
                ),
                __(
                    'Use WooCommerce coupons, plugin-managed coupons, or both.',
                    'eilmo-checkout-flow'
                )
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Enable Coupons',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[coupons][enabled]',
                                'yes',
                                (string) (
                                    $settings['enabled'] ??
                                        'yes'
                                )
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Enable coupon functionality on the checkout flow.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="eilmo-cf-coupon-mode">
                                <?php
                                esc_html_e(
                                    'Coupon System',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="eilmo-cf-coupon-mode"
                                name="eilmo_cf_settings[coupons][mode]"
                            >
                                <?php
                                $mode =
                                    (string) (
                                        $settings['mode'] ??
                                            'woocommerce'
                                    );

                                $this->render_option(
                                    'woocommerce',
                                    __(
                                        'WooCommerce Coupons',
                                        'eilmo-checkout-flow'
                                    ),
                                    $mode
                                );

                                $this->render_option(
                                    'custom',
                                    __(
                                        'Eilmo Custom Coupons',
                                        'eilmo-checkout-flow'
                                    ),
                                    $mode
                                );

                                $this->render_option(
                                    'both',
                                    __(
                                        'Both',
                                        'eilmo-checkout-flow'
                                    ),
                                    $mode
                                );
                                ?>
                            </select>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Choose whether checkout accepts WooCommerce coupons, Eilmo custom coupons, or both.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <hr>

            <h2>
                <?php
                esc_html_e(
                    'Customer Identification',
                    'eilmo-checkout-flow'
                );
                ?>
            </h2>

            <p class="description">
                <?php
                esc_html_e(
                    'Control how guest customers are identified when checking per-customer coupon limits and New Customer Only restrictions.',
                    'eilmo-checkout-flow'
                );
                ?>
            </p>

            <table class="form-table" role="presentation">
                <tbody>

                    <tr>
                        <th scope="row">
                            <label for="eilmo-cf-coupon-customer-identification">
                                <?php
                                esc_html_e(
                                    'Identify Customer By',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </label>
                        </th>

                        <td>
                            <select
                                id="eilmo-cf-coupon-customer-identification"
                                name="eilmo_cf_settings[coupons][customer_identification]"
                            >
                                <?php
                                $this->render_option(
                                    'email',
                                    __(
                                        'Email',
                                        'eilmo-checkout-flow'
                                    ),
                                    $customer_identification
                                );

                                $this->render_option(
                                    'phone',
                                    __(
                                        'Phone',
                                        'eilmo-checkout-flow'
                                    ),
                                    $customer_identification
                                );

                                $this->render_option(
                                    'email_or_phone',
                                    __(
                                        'Email or Phone',
                                        'eilmo-checkout-flow'
                                    ),
                                    $customer_identification
                                );
                                ?>
                            </select>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Email or Phone is recommended. If either the email or phone matches a previous customer, the customer can be treated as the same customer.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Logged-in customers can also be identified by their WordPress/WooCommerce customer ID.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php
                            esc_html_e(
                                'Require Customer Contact',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </th>

                        <td>
                            <?php
                            $this->render_switch(
                                'eilmo_cf_settings[coupons][require_contact_for_customer_limited]',
                                'yes',
                                $require_contact
                            );
                            ?>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Require guest customer identity before applying coupons that have a Per Customer Limit or are restricted to New Customers.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>

                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Normal coupons without customer-specific restrictions can still be applied before the customer enters contact information.',
                                    'eilmo-checkout-flow'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <hr>

            <div class="eilmo-cf-admin-repeater">

                <div class="eilmo-cf-admin-repeater__heading">

                    <div>
                        <h2>
                            <?php
                            esc_html_e(
                                'Custom Coupons',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </h2>

                        <p>
                            <?php
                            esc_html_e(
                                'Create and manage plugin-controlled coupon codes.',
                                'eilmo-checkout-flow'
                            );
                            ?>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="button button-secondary"
                        data-eilmo-add-coupon
                    >
                        <?php
                        esc_html_e(
                            'Add Coupon',
                            'eilmo-checkout-flow'
                        );
                        ?>
                    </button>

                </div>

                <div data-eilmo-coupons>

                    <?php foreach ( $coupons as $index => $coupon ) : ?>

                        <?php
                        if ( ! is_array( $coupon ) ) {
                            continue;
                        }

                        $this->render_coupon(
                            (int) $index,
                            $coupon
                        );
                        ?>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>
        <?php
    }

	/**
	 * Render custom coupon.
	 *
	 * @param int                  $index  Index.
	 * @param array<string, mixed> $coupon Coupon.
	 *
	 * @return void
	 */
	private function render_coupon(
		int $index,
		array $coupon
	): void {

		$prefix = sprintf(
			'eilmo_cf_settings[coupons][custom_coupons][%d]',
			$index
		);

		?>
		<div class="eilmo-cf-admin-repeater__item">

			<div class="eilmo-cf-admin-repeater__item-header">

				<strong>
					<?php
					echo esc_html(
						! empty( $coupon['code'] )
							? strtoupper( (string) $coupon['code'] )
							: __(
								'Coupon',
								'eilmo-checkout-flow'
							)
					);
					?>
				</strong>

				<button
					type="button"
					class="button-link-delete"
					data-eilmo-remove-repeater-item
				>
					<?php esc_html_e( 'Remove', 'eilmo-checkout-flow' ); ?>
				</button>

			</div>

			<div class="eilmo-cf-admin-grid">

				<?php
				$this->render_text_field(
					$prefix . '[code]',
					__(
						'Coupon Code',
						'eilmo-checkout-flow'
					),
					(string) ( $coupon['code'] ?? '' )
				);
				?>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Discount Type', 'eilmo-checkout-flow' ); ?>
					</label>

					<select name="<?php echo esc_attr( $prefix . '[type]' ); ?>">
						<?php
						$type = (string) (
							$coupon['type'] ??
							'percentage'
						);

						$this->render_option( 'percentage', __( 'Percentage', 'eilmo-checkout-flow' ), $type );
						$this->render_option( 'fixed', __( 'Fixed Amount', 'eilmo-checkout-flow' ), $type );
						$this->render_option( 'free_delivery', __( 'Free Delivery', 'eilmo-checkout-flow' ), $type );
						?>
					</select>
				</div>

				<?php
				$this->render_number_field(
					$prefix . '[value]',
					__(
						'Value',
						'eilmo-checkout-flow'
					),
					$coupon['value'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[minimum_spend]',
					__(
						'Minimum Spend',
						'eilmo-checkout-flow'
					),
					$coupon['minimum_spend'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[maximum_spend]',
					__(
						'Maximum Spend',
						'eilmo-checkout-flow'
					),
					$coupon['maximum_spend'] ?? ''
				);

				$this->render_number_field(
					$prefix . '[maximum_discount]',
					__(
						'Maximum Discount',
						'eilmo-checkout-flow'
					),
					$coupon['maximum_discount'] ?? 0
				);

				$this->render_number_field(
					$prefix . '[usage_limit]',
					__(
						'Usage Limit',
						'eilmo-checkout-flow'
					),
					$coupon['usage_limit'] ?? 0,
					'1'
				);

				$this->render_number_field(
					$prefix . '[usage_limit_per_customer]',
					__(
						'Per Customer Limit',
						'eilmo-checkout-flow'
					),
					$coupon['usage_limit_per_customer'] ?? 0,
					'1'
				);
				?>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Enabled', 'eilmo-checkout-flow' ); ?>
					</label>

					<?php
					$this->render_switch(
						$prefix . '[enabled]',
						'yes',
						(string) ( $coupon['enabled'] ?? 'yes' )
					);
					?>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'Allow Stacking', 'eilmo-checkout-flow' ); ?>
					</label>

					<?php
					$this->render_switch(
						$prefix . '[allow_stacking]',
						'yes',
						(string) ( $coupon['allow_stacking'] ?? 'no' )
					);
					?>
				</div>

				<div class="eilmo-cf-admin-field">
					<label>
						<?php esc_html_e( 'New Customer Only', 'eilmo-checkout-flow' ); ?>
					</label>

					<?php
					$this->render_switch(
						$prefix . '[new_customer_only]',
						'yes',
						(string) ( $coupon['new_customer_only'] ?? 'no' )
					);
					?>
				</div>

			</div>

		</div>
		<?php
	}
	/**
	 * Render section heading.
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 *
	 * @return void
	 */
	private function render_section_header(
		string $title,
		string $description
	): void {
		?>
		<div class="eilmo-cf-settings-section__header">
			<h2><?php echo esc_html( $title ); ?></h2>

			<p>
				<?php echo esc_html( $description ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render yes/no switch.
	 *
	 * Hidden input ensures "no" is submitted when unchecked.
	 *
	 * @param string $name    Input name.
	 * @param string $value   Checked value.
	 * @param string $current Current value.
	 *
	 * @return void
	 */
	private function render_switch(
		string $name,
		string $value,
		string $current
	): void {
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr( $name ); ?>"
			value="no"
		>

		<label class="eilmo-cf-admin-switch">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( $current, $value ); ?>
			>

			<span class="eilmo-cf-admin-switch__slider"></span>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 *
	 * @return void
	 */
	private function render_text_field(
		string $name,
		string $label,
		string $value
	): void {
		?>
		<div class="eilmo-cf-admin-field">
			<label>
				<?php echo esc_html( $label ); ?>
			</label>

			<input
				type="text"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
			>
		</div>
		<?php
	}

	/**
	 * Render numeric field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param string $step  Step.
	 *
	 * @return void
	 */
	private function render_number_field(
		string $name,
		string $label,
		$value,
		string $step = '0.01'
	): void {
		?>
		<div class="eilmo-cf-admin-field">
			<label>
				<?php echo esc_html( $label ); ?>
			</label>

			<input
				type="number"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( (string) $value ); ?>"
				min="0"
				step="<?php echo esc_attr( $step ); ?>"
			>
		</div>
		<?php
	}

	/**
	 * Checkout placement slots available to movable modules.
	 *
	 * @return array<string, string>
	 */
	private function get_checkout_position_options(): array {
		return array(
			'before_products' => __( 'Before Products', 'eilmo-checkout-flow' ),
			'after_products' => __( 'After Products', 'eilmo-checkout-flow' ),
			'before_delivery' => __( 'Before Delivery', 'eilmo-checkout-flow' ),
			'after_delivery' => __( 'After Delivery', 'eilmo-checkout-flow' ),
			'before_customer' => __( 'Before Customer Information', 'eilmo-checkout-flow' ),
			'after_customer' => __( 'After Customer Information', 'eilmo-checkout-flow' ),
			'before_payment' => __( 'Before Payment', 'eilmo-checkout-flow' ),
			'after_payment' => __( 'After Payment', 'eilmo-checkout-flow' ),
			'before_order' => __( 'Before Order Button', 'eilmo-checkout-flow' ),
		);
	}

	/**
	 * Render select option.
	 *
	 * @param string $value   Option value.
	 * @param string $label   Option label.
	 * @param string $current Current value.
	 *
	 * @return void
	 */
	private function render_option(
		string $value,
		string $label,
		string $current
	): void {
		?>
		<option
			value="<?php echo esc_attr( $value ); ?>"
			<?php selected( $current, $value ); ?>
		>
			<?php echo esc_html( $label ); ?>
		</option>
		<?php
	}

	/**
	 * Get current tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {

		$tabs = self::get_tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
		$tab = isset( $_GET['tab'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: 'combo_offers';

		if (
			! array_key_exists(
				$tab,
				$tabs
			)
		) {
			return 'combo_offers';
		}

		return $tab;
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {

		$defaults = CheckoutSettings::get_defaults();

		$stored = get_option(
			CheckoutSettings::OPTION_NAME,
			array()
		);

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		/*
		 * Rules Engine has been removed. Ignore any legacy
		 * Rules Engine data that may still exist in the option
		 * until the settings are saved again.
		 */
		unset(
			$stored['rules_engine']
		);

		/*
		 * Security settings now live in the dedicated
		 * SecuritySettings option. Ignore duplicate
		 * legacy General keys until Settings.php removes
		 * them from the main option on the next save.
		 */
		if (
			isset(
				$stored[
					'general'
				]
			) &&
			is_array(
				$stored[
					'general'
				]
			)
		) {
			unset(
				$stored['general']['checkout_security'],
				$stored['general']['customer_blacklist'],
				$stored['general']['checkout_access'],
				$stored['general']['custom_login_url_enabled'],
				$stored['general']['custom_login_url']
			);
		}

		return $this->merge_settings(
			$defaults,
			$stored
		);
	}

	/**
	 * Recursively merge associative settings.
	 *
	 * Indexed collections replace defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored.
	 *
	 * @return array<string, mixed>
	 */
	private function merge_settings(
		array $defaults,
		array $stored
	): array {

		foreach ( $stored as $key => $value ) {

			if (
				isset( $defaults[ $key ] ) &&
				is_array( $defaults[ $key ] ) &&
				is_array( $value ) &&
				$this->is_associative(
					$defaults[ $key ]
				) &&
				$this->is_associative(
					$value
				)
			) {
				$defaults[ $key ] =
					$this->merge_settings(
						$defaults[ $key ],
						$value
					);

				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Determine whether array is associative.
	 *
	 * @param array<mixed> $array Array.
	 *
	 * @return bool
	 */
	private function is_associative(
		array $array
	): bool {

		if ( empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !== range(
			0,
			count( $array ) - 1
		);
	}

    /**
     * Format monetary value for admin display.
     *
     * @param mixed $amount Amount.
     *
     * @return string
     */
    private function format_admin_price(
        $amount
    ): string {

        if (
            function_exists( 'wc_price' )
        ) {
            return wp_strip_all_tags(
                wc_price(
                    (float) $amount
                )
            );
        }

        $amount = is_numeric( $amount )
            ? (float) $amount
            : 0.0;

        return number_format_i18n(
            $amount,
            2
        );
    }

}
