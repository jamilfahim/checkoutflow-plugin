<?php
/**
 * Direct order-table management for WooCommerce order lists.
 *
 * @package EilmoCheckout
 */

namespace EilmoCheckout\Admin\Orders;

use EilmoCheckout\Admin\CheckoutSettings;
use EilmoCheckout\Contracts\RegistrableInterface;
use EilmoCheckout\Payment\Services\PaymentProof;
use EilmoCheckout\Orders\Services\PaymentStatusTracker;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds compact verification data directly to WooCommerce order-table columns.
 */
final class QuickOrderManagement implements RegistrableInterface {

    private const NONCE_ACTION = 'eilmo_cf_order_table_management';
    private const STATUS_AJAX  = 'eilmo_cf_order_table_status';

    private const COL_CUSTOMER = 'eilmo_cf_verify_customer';
    private const COL_PRODUCTS = 'eilmo_cf_verify_products';
    private const COL_STATUS   = 'eilmo_cf_verify_status';
    private const COL_PAYMENT  = 'eilmo_cf_verify_payment';
    private const COL_PROOF    = 'eilmo_cf_verify_proof';
    private const COL_ORDER_NOTE = 'eilmo_cf_verify_order_note';

    /** Register admin hooks. */
    public function register(): void {
        if ( ! is_admin() ) {
            return;
        }

        add_filter( 'manage_edit-shop_order_columns', array( $this, 'filter_columns' ), 999 );
        add_filter( 'manage_shop_order_posts_columns', array( $this, 'filter_columns' ), 999 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'filter_columns' ), 999 );

        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_legacy_column' ), 999, 2 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_column' ), 999, 2 );

        add_action( 'admin_head', array( $this, 'render_styles' ), 60 );
        add_action( 'admin_footer', array( $this, 'render_scripts_and_modal' ), 60 );
        add_filter( 'admin_body_class', array( $this, 'workflow_body_class' ) );

        add_action( 'wp_ajax_' . self::STATUS_AJAX, array( $this, 'ajax_status' ) );
    }

    /**
     * Build the direct-verification order table and apply show/hide choices.
     *
     * @param array<string,string> $columns Existing WooCommerce columns.
     * @return array<string,string>
     */
    public function filter_columns( array $columns ): array {
        $settings = $this->settings();
        if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
            return $this->filter_default_columns( $columns, $settings );
        }

        $visibility = isset( $settings['columns'] ) && is_array( $settings['columns'] )
            ? $settings['columns']
            : array();

        // Remove columns controlled by this feature when disabled.
        $groups = array(
            'order'   => array( 'order_number', 'order_title' ),
            'date'    => array( 'order_date', 'date' ),
            'security'=> array( 'eilmo_cf_security' ),
            'courier' => array( 'eilmo_cf_courier_success' ),
            'meta'    => array( 'eilmo_meta_purchase' ),
            'total'   => array( 'order_total', 'total' ),
            'origin'  => array( 'origin', 'order_origin' ),
            'actions' => array( 'wc_actions', 'order_actions' ),
        );

        foreach ( $groups as $setting_key => $keys ) {
            if ( 'yes' === ( $visibility[ $setting_key ] ?? 'yes' ) ) {
                continue;
            }
            foreach ( $keys as $key ) {
                unset( $columns[ $key ] );
            }
        }

        // The WooCommerce status cell is replaced by our inline-editable status cell.
        foreach ( array( 'order_status', 'status' ) as $status_key ) {
            unset( $columns[ $status_key ] );
        }

        foreach ( array(
            self::COL_CUSTOMER,
            self::COL_PRODUCTS,
            self::COL_STATUS,
            self::COL_PAYMENT,
            self::COL_PROOF,
            self::COL_ORDER_NOTE,
        ) as $custom_key ) {
            unset( $columns[ $custom_key ] );
        }

        $labels = array(
            self::COL_CUSTOMER => __( 'Customer / Address', 'eilmo-checkout-flow' ),
            self::COL_PRODUCTS => __( 'Products', 'eilmo-checkout-flow' ),
            self::COL_STATUS   => __( 'Status', 'eilmo-checkout-flow' ),
            self::COL_PAYMENT  => __( 'Payment', 'eilmo-checkout-flow' ),
            self::COL_PROOF      => __( 'Proof / Txn', 'eilmo-checkout-flow' ),
            self::COL_ORDER_NOTE => __( 'Order Note', 'eilmo-checkout-flow' ),
        );

        $enabled_custom = array(
            self::COL_CUSTOMER => 'customer',
            self::COL_PRODUCTS => 'products',
            self::COL_STATUS   => 'status',
            self::COL_PAYMENT  => 'payment',
            self::COL_PROOF      => 'proof',
            self::COL_ORDER_NOTE => 'order_note',
        );

        $output   = array();
        $inserted = false;

        foreach ( $columns as $key => $label ) {
            $output[ $key ] = $label;

            if ( ! $inserted && in_array( $key, array( 'order_number', 'order_title' ), true ) ) {
                foreach ( $enabled_custom as $custom_key => $setting_key ) {
                    if ( 'yes' === ( $visibility[ $setting_key ] ?? 'yes' ) ) {
                        $output[ $custom_key ] = $labels[ $custom_key ];
                    }
                }
                $inserted = true;
            }
        }

        if ( ! $inserted ) {
            $custom = array();
            foreach ( $enabled_custom as $custom_key => $setting_key ) {
                if ( 'yes' === ( $visibility[ $setting_key ] ?? 'yes' ) ) {
                    $custom[ $custom_key ] = $labels[ $custom_key ];
                }
            }
            $output = array_merge( $custom, $output );
        }

        // Keep Security / Block Customer immediately after the inline Status column.
        if ( isset( $output['eilmo_cf_security'] ) ) {
            $security_label = $output['eilmo_cf_security'];
            unset( $output['eilmo_cf_security'] );

            $reordered = array();
            $placed    = false;
            foreach ( $output as $key => $label ) {
                $reordered[ $key ] = $label;
                if ( ! $placed && self::COL_STATUS === $key ) {
                    $reordered['eilmo_cf_security'] = $security_label;
                    $placed = true;
                }
            }
            if ( ! $placed ) {
                $reordered['eilmo_cf_security'] = $security_label;
            }
            $output = $reordered;
        }

        // Keep Courier Performance beside verification data and before Date.
        if ( isset( $output['eilmo_cf_courier_success'] ) ) {
            $courier_label = $output['eilmo_cf_courier_success'];
            unset( $output['eilmo_cf_courier_success'] );

            $reordered = array();
            $placed    = false;
            foreach ( $output as $key => $label ) {
                if ( ! $placed && in_array( $key, array( 'order_date', 'date' ), true ) ) {
                    $reordered['eilmo_cf_courier_success'] = $courier_label;
                    $placed = true;
                }
                $reordered[ $key ] = $label;
            }
            if ( ! $placed ) {
                $reordered['eilmo_cf_courier_success'] = $courier_label;
            }
            $output = $reordered;
        }

        return $output;
    }

    /** Apply the default WooCommerce table's site-wide visibility choices. */
    private function filter_default_columns( array $columns, array $settings ): array {
        $visibility = isset( $settings['default_columns'] ) && is_array( $settings['default_columns'] )
            ? $settings['default_columns']
            : array();
        $groups = array(
            'order'    => array( 'order_number', 'order_title' ),
            'date'     => array( 'order_date', 'date' ),
            'status'   => array( 'order_status', 'status' ),
            'billing'  => array( 'billing_address' ),
            'shipping' => array( 'shipping_address' ),
            'courier'  => array( 'eilmo_cf_courier_success' ),
            'meta'     => array( 'eilmo_meta_purchase' ),
            'security' => array( 'eilmo_cf_security' ),
            'total'    => array( 'order_total', 'total' ),
            'origin'   => array( 'origin', 'order_origin' ),
            'actions'  => array( 'wc_actions', 'order_actions' ),
        );

        foreach ( $groups as $setting_key => $keys ) {
            if ( 'no' !== ( $visibility[ $setting_key ] ?? 'yes' ) ) {
                continue;
            }
            foreach ( $keys as $key ) {
                unset( $columns[ $key ] );
            }
        }

        return $columns;
    }

    /** Render classic order-list custom cells. */
    public function render_legacy_column( string $column, int $post_id ): void {
        if ( ! $this->is_custom_column( $column ) ) {
            return;
        }
        $order = wc_get_order( $post_id );
        if ( $order instanceof WC_Order ) {
            $this->render_column( $column, $order );
        }
    }

    /** Render HPOS order-list custom cells. */
    public function render_hpos_column( string $column, $order_value ): void {
        if ( ! $this->is_custom_column( $column ) ) {
            return;
        }
        $order = $order_value instanceof WC_Order ? $order_value : wc_get_order( absint( $order_value ) );
        if ( $order instanceof WC_Order ) {
            $this->render_column( $column, $order );
        }
    }

    /** Render one direct verification cell. */
    private function render_column( string $column, WC_Order $order ): void {
        switch ( $column ) {
            case self::COL_CUSTOMER:
                $this->render_customer_cell( $order );
                break;
            case self::COL_PRODUCTS:
                $this->render_products_cell( $order );
                break;
            case self::COL_STATUS:
                $this->render_status_cell( $order );
                break;
            case self::COL_PAYMENT:
                $this->render_payment_cell( $order );
                break;
            case self::COL_PROOF:
                $this->render_proof_cell( $order );
                break;
            case self::COL_ORDER_NOTE:
                $this->render_order_note_cell( $order );
                break;
        }
    }

    /** Customer name, phone and delivery/billing address required for order verification. */
    private function render_customer_cell( WC_Order $order ): void {
        $name          = trim( (string) $order->get_formatted_billing_full_name() );
        $phone         = trim( (string) $order->get_billing_phone() );
        $address_lines = $this->address_lines( $order );
        ?>
        <div class="eilmo-cf-otm-cell eilmo-cf-otm-customer">
            <strong><?php echo esc_html( $name ?: __( 'Customer', 'eilmo-checkout-flow' ) ); ?></strong>
            <?php if ( $phone ) : ?><span class="eilmo-cf-otm-phone"><?php echo esc_html( $phone ); ?></span><?php endif; ?>
            <?php if ( ! empty( $address_lines ) ) : ?>
                <span class="eilmo-cf-otm-address-lines">
                    <?php foreach ( $address_lines as $line ) : ?>
                        <span><?php echo esc_html( $line ); ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Compact product/variation/quantity summary. */
    private function render_products_cell( WC_Order $order ): void {
        $items = $order->get_items();
        if ( empty( $items ) ) {
            echo '<span class="eilmo-cf-otm-muted">—</span>';
            return;
        }
        $item_count = count( $items );
        $more_label = sprintf(
            /* translators: %d: number of products in the order. */
            __( 'Show all %d products', 'eilmo-checkout-flow' ),
            $item_count
        );
        ?>
        <div class="eilmo-cf-otm-cell eilmo-cf-otm-products">
            <?php foreach ( $items as $item ) : ?>
                <div class="eilmo-cf-otm-product-line">
                    <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                    <span>×<?php echo esc_html( (string) $item->get_quantity() ); ?></span>
                    <?php $meta = wc_display_item_meta( $item, array( 'echo' => false, 'separator' => ', ' ) ); ?>
                    <?php if ( $meta ) : ?><small><?php echo wp_kses_post( $meta ); ?></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ( $item_count > 2 ) : ?>
                <button type="button" class="eilmo-cf-otm-products-toggle" aria-expanded="false" data-more-label="<?php echo esc_attr( $more_label ); ?>" data-less-label="<?php esc_attr_e( 'Show fewer products', 'eilmo-checkout-flow' ); ?>"><?php echo esc_html( $more_label ); ?></button>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Inline editable order status. */
    private function render_status_cell( WC_Order $order ): void {
        $settings = $this->settings();
        if ( 'yes' !== ( $settings['allow_status_update'] ?? 'yes' ) ) {
            echo '<span class="eilmo-cf-otm-status-label">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';
            return;
        }
        ?>
        <div class="eilmo-cf-otm-status" data-order-id="<?php echo esc_attr( (string) $order->get_id() ); ?>">
            <select class="eilmo-cf-otm-status-select" aria-label="<?php esc_attr_e( 'Order status', 'eilmo-checkout-flow' ); ?>">
                <?php foreach ( wc_get_order_statuses() as $key => $label ) : $value = preg_replace( '/^wc-/', '', (string) $key ); ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $order->get_status(), $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button eilmo-cf-otm-status-save" title="<?php esc_attr_e( 'Update status', 'eilmo-checkout-flow' ); ?>"><?php esc_html_e( 'Update', 'eilmo-checkout-flow' ); ?></button>
            <span class="eilmo-cf-otm-status-message" role="status"></span>
        </div>
        <?php
    }

    /** Payment method, verification status, transaction and due amounts. */
    private function render_payment_cell( WC_Order $order ): void {
        $status      = PaymentStatusTracker::resolve_status( $order );
        $type        = sanitize_key( (string) $order->get_meta( '_eilmo_cf_payment_type', true ) );
        $method      = preg_replace( '#<small\b[^>]*>.*?</small>#is', '', (string) $order->get_payment_method_title() );
        $method      = sanitize_text_field( (string) $method );
        if ( '' === $method ) {
            $method = sanitize_text_field( (string) $order->get_meta( '_eilmo_cf_payment_method', true ) );
        }
        $pay_now     = (float) $order->get_meta( '_eilmo_cf_pay_now', true );
        $due         = (float) $order->get_meta( '_eilmo_cf_remaining_due', true );
        $is_cod      = 'cash_on_delivery' === $type || 'cod' === $order->get_payment_method();
        $is_verified = 'paid' === $status;
        ?>
        <div class="eilmo-cf-otm-cell eilmo-cf-otm-payment">
            <strong><?php echo esc_html( $method ?: '—' ); ?></strong>
            <?php if ( $status ) : ?><span class="eilmo-cf-otm-pill"><?php echo esc_html( 'advance' === $type && $is_verified ? __( 'Advance Paid', 'eilmo-checkout-flow' ) : $this->humanize( $status ) ); ?></span><?php endif; ?>
            <?php if ( in_array( $type, array( 'advance', 'full' ), true ) ) : ?><span class="eilmo-cf-otm-muted"><?php echo esc_html( 'advance' === $type ? __( 'Advance Payment', 'eilmo-checkout-flow' ) : __( 'Full Payment', 'eilmo-checkout-flow' ) ); ?></span><?php endif; ?>
            <?php if ( $is_cod && $due > 0 ) : ?>
                <span class="eilmo-cf-otm-money"><?php esc_html_e( 'Collect on delivery:', 'eilmo-checkout-flow' ); ?> <?php echo wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ); ?></span>
            <?php elseif ( 'advance' === $type ) : ?>
                <?php if ( $pay_now > 0 ) : ?><span class="eilmo-cf-otm-money"><?php echo esc_html( $is_verified ? __( 'Advance confirmed:', 'eilmo-checkout-flow' ) : __( 'Advance to verify:', 'eilmo-checkout-flow' ) ); ?> <?php echo wp_kses_post( wc_price( $pay_now, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
                <?php if ( $due > 0 ) : ?><span class="eilmo-cf-otm-money"><?php esc_html_e( 'Balance after advance:', 'eilmo-checkout-flow' ); ?> <?php echo wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
            <?php elseif ( 'full' === $type ) : ?>
                <?php if ( $pay_now > 0 ) : ?><span class="eilmo-cf-otm-money"><?php echo esc_html( $is_verified ? __( 'Payment confirmed:', 'eilmo-checkout-flow' ) : __( 'Payment to verify:', 'eilmo-checkout-flow' ) ); ?> <?php echo wp_kses_post( wc_price( $pay_now, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
                <?php if ( $due > 0 ) : ?><span class="eilmo-cf-otm-money"><?php esc_html_e( 'Remaining due:', 'eilmo-checkout-flow' ); ?> <?php echo wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
            <?php else : ?>
                <?php if ( $pay_now > 0 ) : ?><span class="eilmo-cf-otm-money"><?php echo esc_html( $is_verified ? __( 'Payment confirmed:', 'eilmo-checkout-flow' ) : __( 'Payment amount:', 'eilmo-checkout-flow' ) ); ?> <?php echo wp_kses_post( wc_price( $pay_now, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
                <?php if ( $due > 0 ) : ?><span class="eilmo-cf-otm-money"><?php esc_html_e( 'Remaining due:', 'eilmo-checkout-flow' ); ?> <?php echo wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ); ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Transaction ID and payment screenshot in one compact verification cell. */
    private function render_proof_cell( WC_Order $order ): void {
        $url         = $this->proof_url( $order );
        $transaction = trim( (string) ( $order->get_transaction_id() ?: $order->get_meta( '_eilmo_cf_payment_transaction_id', true ) ) );
        ?>
        <div class="eilmo-cf-otm-cell eilmo-cf-otm-proof-cell">
            <?php if ( $transaction ) : ?>
                <span class="eilmo-cf-otm-transaction" title="<?php esc_attr_e( 'Transaction ID', 'eilmo-checkout-flow' ); ?>">
                    <strong><?php esc_html_e( 'Txn:', 'eilmo-checkout-flow' ); ?></strong>
                    <code><?php echo esc_html( $transaction ); ?></code>
                </span>
            <?php endif; ?>
            <?php if ( $url ) : ?>
                <a class="eilmo-cf-otm-proof-open" href="<?php echo esc_url( $url ); ?>" title="<?php esc_attr_e( 'Open payment screenshot', 'eilmo-checkout-flow' ); ?>">
                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php esc_attr_e( 'Payment proof', 'eilmo-checkout-flow' ); ?>">
                    <span><?php esc_html_e( 'View proof', 'eilmo-checkout-flow' ); ?></span>
                </a>
            <?php elseif ( ! $transaction ) : ?>
                <span class="eilmo-cf-otm-muted"><?php esc_html_e( 'No proof', 'eilmo-checkout-flow' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Build compact address lines without repeating the customer name.
     *
     * @return array<int,string>
     */
    private function address_lines( WC_Order $order ): array {
        $shipping_values = array(
            (string) $order->get_shipping_address_1(),
            (string) $order->get_shipping_address_2(),
            (string) $order->get_shipping_city(),
            (string) $order->get_shipping_state(),
            (string) $order->get_shipping_postcode(),
            (string) $order->get_shipping_country(),
        );

        $use_shipping = '' !== trim( implode( '', $shipping_values ) );

        $values = array(
            'address_1' => $use_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
            'address_2' => $use_shipping ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
            'city'      => $use_shipping ? $order->get_shipping_city() : $order->get_billing_city(),
            'state'     => $use_shipping ? $order->get_shipping_state() : $order->get_billing_state(),
            'postcode'  => $use_shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
            'country'   => $use_shipping ? $order->get_shipping_country() : $order->get_billing_country(),
        );

        $lines = array();

        foreach ( array( 'address_1', 'address_2' ) as $key ) {
            $value = trim( (string) ( $values[ $key ] ?? '' ) );
            if ( '' !== $value ) {
                $lines[] = $value;
            }
        }

        $locality = array_filter(
            array_map(
                'trim',
                array(
                    (string) ( $values['city'] ?? '' ),
                    (string) ( $values['state'] ?? '' ),
                    (string) ( $values['postcode'] ?? '' ),
                )
            ),
            static fn( string $value ): bool => '' !== $value
        );

        if ( ! empty( $locality ) ) {
            $lines[] = implode( ', ', array_values( $locality ) );
        }

        $country_code = trim( (string) ( $values['country'] ?? '' ) );
        if ( '' !== $country_code && function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
            $countries = WC()->countries->get_countries();
            $lines[]   = isset( $countries[ $country_code ] ) ? (string) $countries[ $country_code ] : $country_code;
        } elseif ( '' !== $country_code ) {
            $lines[] = $country_code;
        }

        return array_values( array_unique( array_filter( $lines ) ) );
    }

    /** Customer-entered order note only; system timeline notes stay in the order editor. */
    private function render_order_note_cell( WC_Order $order ): void {
        $customer_note = trim( (string) $order->get_customer_note() );
        ?>
        <div class="eilmo-cf-otm-cell eilmo-cf-otm-order-note" title="<?php echo esc_attr( $customer_note ); ?>">
            <?php if ( $customer_note ) : ?>
                <span><?php echo esc_html( $customer_note ); ?></span>
            <?php else : ?>
                <span class="eilmo-cf-otm-muted">—</span>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Save one inline status change. */
    public function ajax_status(): void {
        $this->authorize_ajax();

        $settings = $this->settings();
        if ( 'yes' !== ( $settings['allow_status_update'] ?? 'yes' ) ) {
            wp_send_json_error( array( 'message' => __( 'Inline status updates are disabled.', 'eilmo-checkout-flow' ) ), 403 );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $status   = sanitize_key( (string) ( $_POST['status'] ?? '' ) );
        $order    = wc_get_order( $order_id );
        $statuses = wc_get_order_statuses();

        if ( ! $order instanceof WC_Order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'eilmo-checkout-flow' ) ), 404 );
        }
        if ( ! isset( $statuses[ 'wc-' . $status ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid order status.', 'eilmo-checkout-flow' ) ), 400 );
        }
        if ( $order->get_status() === $status ) {
            wp_send_json_success( array( 'message' => __( 'Status already selected.', 'eilmo-checkout-flow' ) ) );
        }

        try {
            $user = wp_get_current_user();
            $order->update_status(
                $status,
                sprintf(
                    __( 'Order status updated from the Orders table by %s.', 'eilmo-checkout-flow' ),
                    $user->display_name ?: $user->user_login
                ),
                true
            );
        } catch ( \Throwable $throwable ) {
            wp_send_json_error( array( 'message' => $throwable->getMessage() ?: __( 'The order status could not be updated.', 'eilmo-checkout-flow' ) ), 500 );
        }

        wp_send_json_success(
            array(
                'message' => sprintf( __( 'Updated to %s', 'eilmo-checkout-flow' ), $statuses[ 'wc-' . $status ] ),
            )
        );
    }

    /** Base control styles followed by the responsive Orders workflow layout. */
    public function render_styles(): void {
        if ( ! $this->is_order_list_screen() ) {
            return;
        }
        $settings = $this->settings();
        if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
            ?>
            <style id="eilmo-cf-standard-order-table-styles">
                body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) .wp-list-table.orders,
                body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) table.wc-orders-list-table { display: none !important; }
                body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) .tablenav.top::after {
                    content: "<?php echo esc_attr( __( 'Loading orders…', 'eilmo-checkout-flow' ) ); ?>";
                    display: block;
                    padding: 18px 12px;
                    color: #64748b;
                    font-weight: 600;
                }
            </style>
            <noscript><style>body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) .wp-list-table.orders,body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) table.wc-orders-list-table{display:table!important}body.eilmo-cf-order-standard:not(.eilmo-cf-standard-ready) .tablenav.top::after{display:none!important}</style></noscript>
            <script id="eilmo-cf-standard-order-table-ready">
                (function () {
                    function reveal() {
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                document.body.classList.add('eilmo-cf-standard-ready');
                            });
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', reveal, { once: true });
                    } else {
                        reveal();
                    }
                    setTimeout(function () {
                        if (document.body) document.body.classList.add('eilmo-cf-standard-ready');
                    }, 1500);
                })();
            </script>
            <?php
            return;
        }

        ?>
        <style id="eilmo-cf-order-table-management-styles">
        /* The workflow grid is prepared in the footer. Do not expose the
         * WooCommerce table layout for a frame before its compact rows exist. */
        body.eilmo-cf-order-workflow .wp-list-table.orders:not(.eilmo-cf-workflow-table),
        body.eilmo-cf-order-workflow .wp-list-table.table-view-list.orders:not(.eilmo-cf-workflow-table),
        body.eilmo-cf-order-workflow table.wc-orders-list-table:not(.eilmo-cf-workflow-table){display:none!important}
        body.eilmo-cf-order-workflow:not(.eilmo-cf-workflow-ready) .tablenav.top::after{content:"<?php echo esc_attr( __( 'Loading orders…', 'eilmo-checkout-flow' ) ); ?>";display:block;padding:18px 12px;color:#64748b;font-weight:600}
        /* Keep operational controls readable in the order workflow. */
        .woocommerce_page_wc-orders .column-eilmo_cf_courier_success .button,.post-type-shop_order .column-eilmo_cf_courier_success .button{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;overflow-wrap:normal;word-break:normal}
        .woocommerce_page_wc-orders .column-eilmo_cf_courier_success .eilmo-cf-courier__action-row,.post-type-shop_order .column-eilmo_cf_courier_success .eilmo-cf-courier__action-row{grid-template-columns:minmax(0,1fr) 34px}
        .eilmo-cf-order-link-stacked{display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;max-width:100%;line-height:1.22;text-decoration:none;white-space:normal;overflow:hidden}
        .eilmo-cf-order-link-stacked .eilmo-cf-order-id{display:block;white-space:nowrap;font-weight:700}
        .eilmo-cf-order-link-stacked .eilmo-cf-order-name{display:block;width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
        .woocommerce_page_wc-orders .wp-list-table tbody td:not(.column-order_number):not(.column-order_title) a:not(.eilmo-cf-otm-proof-open),.post-type-shop_order .wp-list-table tbody td:not(.column-order_number):not(.column-order_title) a:not(.eilmo-cf-otm-proof-open){cursor:default}
        .eilmo-cf-otm-status-select,.eilmo-cf-otm-status-save,.eilmo-cf-otm-proof-open,.woocommerce_page_wc-orders .eilmo-cf-courier-action,.post-type-shop_order .eilmo-cf-courier-action{cursor:pointer}
        .woocommerce_page_wc-orders .tablenav,.post-type-shop_order .tablenav{max-width:none}
        .eilmo-cf-otm-cell{display:flex;flex-direction:column;gap:2px;line-height:1.32;overflow-wrap:anywhere;word-break:break-word}.eilmo-cf-otm-phone{font-size:13px;font-weight:650;line-height:1.35;color:#1d2327;letter-spacing:.1px}.eilmo-cf-otm-address-lines{display:flex;flex-direction:column;gap:1px;color:#3c434a;margin-top:3px;font-size:12px;line-height:1.4}.eilmo-cf-otm-address-lines>span{display:block}.eilmo-cf-otm-cell strong{font-weight:650}.eilmo-cf-otm-muted{color:#646970}.eilmo-cf-otm-product-line{display:flex;flex-wrap:wrap;align-items:baseline;gap:3px 5px;padding-bottom:4px;margin-bottom:4px;border-bottom:1px solid #edf0f4}.eilmo-cf-otm-product-line:last-child{border-bottom:0;padding-bottom:0;margin-bottom:0}.eilmo-cf-otm-product-line small{display:block;width:100%;color:#646970}.eilmo-cf-otm-pill{display:inline-flex;align-self:flex-start;padding:2px 5px;border-radius:999px;background:#f0f4ff;color:#334155;font-weight:600}.eilmo-cf-otm-money{white-space:nowrap}.eilmo-cf-otm-order-note{max-height:74px;overflow:auto}.eilmo-cf-otm-transaction{display:flex;flex-direction:column;gap:1px}.eilmo-cf-otm-transaction code{font-size:.92em;white-space:normal;overflow-wrap:anywhere;background:#f6f7f7;padding:2px 4px;border-radius:4px}
        .eilmo-cf-otm-status{display:flex;flex-direction:column;gap:4px;align-items:stretch;width:100%;max-width:100%}.eilmo-cf-otm-status-select{width:100%;max-width:100%;min-width:0;height:27px;min-height:27px;padding:0 18px 0 4px;font-size:inherit}.eilmo-cf-otm-status-save{display:inline-flex;align-items:center;justify-content:center;width:100%;max-width:100%;min-width:0;height:25px;min-height:25px;padding:0 5px;font-size:10px;line-height:1}.eilmo-cf-otm-status-message{display:none;font-size:.88em;font-weight:600;color:#166534;white-space:nowrap;overflow-wrap:normal;word-break:normal}.eilmo-cf-otm-status-message.is-error{color:#991b1b}.eilmo-cf-otm-status-label{display:inline-flex;padding:3px 6px;border-radius:5px;background:#f3f4f6}
        .eilmo-cf-otm-proof-open{display:flex;flex-direction:column;align-items:flex-start;gap:3px;text-decoration:none}.eilmo-cf-otm-proof-open img{display:block;width:42px;height:36px;object-fit:cover;border:1px solid #dcdcde;border-radius:5px;background:#fff}.eilmo-cf-otm-proof-open span{font-size:.92em}
        .eilmo-cf-otm-proof-modal{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:28px;background:rgba(15,23,42,.78)}.eilmo-cf-otm-proof-modal.is-open{display:flex}.eilmo-cf-otm-proof-dialog{position:relative;max-width:min(1100px,94vw);max-height:92vh}.eilmo-cf-otm-proof-dialog img{display:block;max-width:94vw;max-height:88vh;border-radius:8px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.35)}.eilmo-cf-otm-proof-close{position:absolute;right:-12px;top:-12px;width:36px;height:36px;border:0;border-radius:50%;background:#fff;color:#111827;font-size:25px;line-height:34px;cursor:pointer;box-shadow:0 3px 14px rgba(0,0,0,.25)}
        <?php
        $workflow_css = EILMO_CF_PATH . 'assets/src/css/admin/order-workflow.css';
        if ( is_readable( $workflow_css ) ) {
            readfile( $workflow_css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile -- Bundled, trusted stylesheet.
        }
        ?>
        </style>
        <noscript><style>body.eilmo-cf-order-workflow .wp-list-table.orders,body.eilmo-cf-order-workflow table.wc-orders-list-table{display:table!important}body.eilmo-cf-order-workflow .tablenav.top::after{display:none!important}</style></noscript>
        <?php
    }

    /** Status AJAX behavior and payment-proof popup. */
    public function render_scripts_and_modal(): void {
        if ( ! $this->is_order_list_screen() ) {
            return;
        }
        $settings = $this->settings();
        if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
            return;
        }
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        ?>
        <div class="eilmo-cf-otm-proof-modal" id="eilmo-cf-otm-proof-modal" aria-hidden="true"><div class="eilmo-cf-otm-proof-dialog"><button type="button" class="eilmo-cf-otm-proof-close" aria-label="<?php esc_attr_e( 'Close', 'eilmo-checkout-flow' ); ?>">×</button><img src="" alt="<?php esc_attr_e( 'Payment proof', 'eilmo-checkout-flow' ); ?>"></div></div>
        <script id="eilmo-cf-order-table-management-script">
        (function(){'use strict';var ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( $nonce ); ?>;
        function post(data){var body=new URLSearchParams(data);return fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json();});}
        function closeProof(){var m=document.getElementById('eilmo-cf-otm-proof-modal');if(!m)return;m.classList.remove('is-open');m.setAttribute('aria-hidden','true');var i=m.querySelector('img');if(i)i.src='';}
        function columnKey(cell){for(var i=0;i<cell.classList.length;i++){var key=cell.classList[i];if(key.indexOf('column-')===0&&key!=='column-primary')return key;}return '';}
        function workflowToolbar(table){
            if(table.previousElementSibling&&table.previousElementSibling.classList.contains('eilmo-cf-workflow-toolbar'))return;
            var bar=document.createElement('div'),heading=document.createElement('strong'),selection=document.createElement('label'),check=document.createElement('input'),sort=document.createElement('span');
            bar.className='eilmo-cf-workflow-toolbar';heading.textContent=<?php echo wp_json_encode( __( 'Orders', 'eilmo-checkout-flow' ) ); ?>;
            selection.className='eilmo-cf-workflow-toolbar__select';check.type='checkbox';check.setAttribute('aria-label',<?php echo wp_json_encode( __( 'Select all orders on this page', 'eilmo-checkout-flow' ) ); ?>);
            selection.appendChild(check);selection.appendChild(document.createTextNode(<?php echo wp_json_encode( __( 'Select all', 'eilmo-checkout-flow' ) ); ?>));
            check.addEventListener('change',function(){var selectAll=check.checked;table.querySelectorAll('tbody .check-column input[type="checkbox"]').forEach(function(rowCheck){if(rowCheck.disabled)return;rowCheck.checked=selectAll;rowCheck.dispatchEvent(new Event('change',{bubbles:true}));});var nativeCheck=table.querySelector('thead .check-column input[type="checkbox"]');if(nativeCheck)nativeCheck.checked=selectAll;check.checked=selectAll;check.indeterminate=false;});
            table.addEventListener('change',function(e){if(!e.target.matches('tbody .check-column input[type="checkbox"]'))return;var boxes=Array.prototype.slice.call(table.querySelectorAll('tbody .check-column input[type="checkbox"]')).filter(function(box){return !box.disabled;});var selected=boxes.filter(function(box){return box.checked;}).length;check.checked=boxes.length>0&&selected===boxes.length;check.indeterminate=selected>0&&selected<boxes.length;});
            sort.className='eilmo-cf-workflow-toolbar__sort';
            ['column-order_number','column-order_title','column-order_date','column-date','column-order_total','column-total'].forEach(function(key){var head=table.querySelector('thead .'+key);if(!head||head.classList.contains('hidden'))return;var link=head.querySelector('a[href]');if(link)sort.appendChild(link.cloneNode(true));});
            bar.appendChild(heading);bar.appendChild(selection);if(sort.childElementCount)bar.appendChild(sort);table.parentNode.insertBefore(bar,table);
        }
        function prepareWorkflowTable(table){
            table.style.removeProperty('width');table.style.removeProperty('min-width');table.style.removeProperty('max-width');table.style.removeProperty('table-layout');
            var labels={};table.querySelectorAll('thead tr:first-child>th,thead tr:first-child>td').forEach(function(head){var key=columnKey(head);if(key)labels[key]=(head.textContent||'').replace(/\s+/g,' ').trim();});
            table.querySelectorAll('tbody tr').forEach(function(row){
                if(row.querySelectorAll('th,td').length<2)return;
                row.classList.add('eilmo-cf-workflow-row');
                var primary=false,secondary=false;
                Array.prototype.forEach.call(row.children,function(cell){
                    var key=columnKey(cell);
                    if(key&&labels[key]&&!cell.classList.contains('check-column'))cell.setAttribute('data-eilmo-label',labels[key]);
                    cell.style.removeProperty('width');cell.style.removeProperty('min-width');cell.style.removeProperty('max-width');
                    if(cell.classList.contains('hidden')||cell.classList.contains('check-column'))return;
                    if(['column-order_number','column-order_title','column-eilmo_cf_verify_customer','column-eilmo_cf_verify_products','column-eilmo_cf_verify_payment','column-eilmo_cf_verify_status'].indexOf(key)!==-1)primary=true;
                    else secondary=true;
                });
                row.classList.toggle('eilmo-cf-workflow-has-secondary',primary&&secondary);
            });
            workflowToolbar(table);
            table.classList.add('eilmo-cf-workflow-table');
        }
        function updateStatus(btn){var box=btn.closest('.eilmo-cf-otm-status'),select=box?box.querySelector('.eilmo-cf-otm-status-select'):null,msg=box?box.querySelector('.eilmo-cf-otm-status-message'):null;if(!box||!select)return;btn.disabled=true;if(msg){msg.style.display='none';msg.classList.remove('is-error');}post({action:<?php echo wp_json_encode( self::STATUS_AJAX ); ?>,_ajax_nonce:nonce,order_id:box.dataset.orderId,status:select.value}).then(function(res){if(msg){msg.textContent=res&&res.data&&res.data.message?res.data.message:(res&&res.success?'Updated.':'Unable to update.');msg.classList.toggle('is-error',!(res&&res.success));msg.style.display='block';}if(res&&res.success){box.dataset.currentStatus=select.value;select.blur();}}).catch(function(){if(msg){msg.textContent='Unable to update.';msg.classList.add('is-error');msg.style.display='block';}}).finally(function(){btn.disabled=false;});}
        function stackOrderLinks(){
            document.querySelectorAll('.wp-list-table tbody td.column-order_number, .wp-list-table tbody td.column-order_title').forEach(function(cell){
                var link=cell.querySelector('a.order-view')||cell.querySelector('a[href*="action=edit"]')||cell.querySelector('a');
                if(!link||link.dataset.eilmoOrderStacked)return;
                var text=(link.textContent||'').replace(/\s+/g,' ').trim(),idMatch=text.match(/#\d+/);
                if(!idMatch)return;
                var orderId=idMatch[0],orderName=text.replace(orderId,'').replace(/^Order\s*/i,'').replace(/^[-–—:]\s*/,'').trim();
                if(!orderName){orderName='Order';}
                link.dataset.eilmoOrderStacked='1';
                link.classList.add('eilmo-cf-order-link-stacked');
                link.setAttribute('title',text);
                while(link.firstChild){link.removeChild(link.firstChild);}
                var id=document.createElement('span'),name=document.createElement('span');
                id.className='eilmo-cf-order-id';id.textContent=orderId;
                name.className='eilmo-cf-order-name';name.textContent=orderName;
                link.appendChild(id);link.appendChild(name);
            });
        }
        function bind(){
            document.querySelectorAll('.wp-list-table.orders,table.wc-orders-list-table,.wp-list-table.table-view-list.orders').forEach(prepareWorkflowTable);
            document.body.classList.add('eilmo-cf-workflow-ready');
            stackOrderLinks();
            document.querySelectorAll('.wp-list-table tbody td:not(.column-order_number):not(.column-order_title)').forEach(function(cell){
                if(cell.dataset.eilmoRowGuard)return;
                cell.dataset.eilmoRowGuard='1';
                ['pointerdown','mousedown','click','dblclick'].forEach(function(type){
                    cell.addEventListener(type,function(e){e.stopPropagation();});
                });
            });
            document.querySelectorAll('.wp-list-table tbody td.column-order_number, .wp-list-table tbody td.column-order_title').forEach(function(cell){
                if(cell.dataset.eilmoOrderCellGuard)return;
                cell.dataset.eilmoOrderCellGuard='1';
                ['pointerdown','mousedown','click','dblclick'].forEach(function(type){
                    cell.addEventListener(type,function(e){
                        if(e.target.closest('a'))return;
                        e.stopPropagation();
                    });
                });
            });
            document.querySelectorAll('.eilmo-cf-otm-cell').forEach(function(cell){if(cell.dataset.eilmoBound)return;cell.dataset.eilmoBound='1';cell.addEventListener('click',function(e){e.stopPropagation();});});
            document.querySelectorAll('.eilmo-cf-otm-status-select').forEach(function(select){if(select.dataset.eilmoBound)return;select.dataset.eilmoBound='1';['pointerdown','mousedown','click','change'].forEach(function(type){select.addEventListener(type,function(e){e.stopPropagation();});});});
            document.querySelectorAll('.eilmo-cf-otm-status-save').forEach(function(btn){if(btn.dataset.eilmoBound)return;btn.dataset.eilmoBound='1';['pointerdown','mousedown'].forEach(function(type){btn.addEventListener(type,function(e){e.stopPropagation();});});btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();updateStatus(btn);});});
            document.querySelectorAll('.eilmo-cf-otm-proof-open').forEach(function(proof){if(proof.dataset.eilmoBound)return;proof.dataset.eilmoBound='1';proof.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var modal=document.getElementById('eilmo-cf-otm-proof-modal'),img=modal?modal.querySelector('img'):null;if(modal&&img){img.src=proof.getAttribute('href');modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');}});});
            document.querySelectorAll('.eilmo-cf-otm-products-toggle').forEach(function(btn){if(btn.dataset.eilmoBound)return;btn.dataset.eilmoBound='1';btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var products=btn.closest('.eilmo-cf-otm-products');if(!products)return;var expanded=products.classList.toggle('is-expanded');btn.setAttribute('aria-expanded',expanded?'true':'false');btn.textContent=expanded?btn.dataset.lessLabel:btn.dataset.moreLabel;});});
        }
        document.addEventListener('click',function(e){if(e.target.closest('.eilmo-cf-otm-proof-close')||e.target.id==='eilmo-cf-otm-proof-modal'){e.preventDefault();e.stopPropagation();closeProof();}});
        document.addEventListener('keydown',function(e){if(e.key==='Escape')closeProof();});
        var bindTimer=null;
        function scheduleBind(){if(bindTimer)clearTimeout(bindTimer);bindTimer=setTimeout(bind,25);}
        function startObserver(){
            var root=document.querySelector('.woocommerce_page_wc-orders .wrap, .post-type-shop_order .wrap, #wpbody-content')||document.body;
            if(!root||typeof MutationObserver==='undefined')return;
            var observer=new MutationObserver(function(mutations){
                var relevant=mutations.some(function(m){return m.addedNodes&&m.addedNodes.length;});
                if(relevant)scheduleBind();
            });
            observer.observe(root,{childList:true,subtree:true});
        }
        if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){bind();startObserver();setTimeout(bind,150);setTimeout(bind,600);});}
        else{bind();startObserver();setTimeout(bind,150);setTimeout(bind,600);}
        })();
        </script>
        <?php
    }

    /** @return bool */
    private function is_custom_column( string $column ): bool {
        return in_array(
            $column,
            array(
                self::COL_CUSTOMER,
                self::COL_PRODUCTS,
                self::COL_STATUS,
                self::COL_PAYMENT,
                self::COL_PROOF,
                    self::COL_ORDER_NOTE,
            ),
            true
        );
    }

    /** Secure payment proof URL for an authorized admin. */
    private function proof_url( WC_Order $order ): string {
        if ( ! class_exists( PaymentProof::class ) || '' === PaymentProof::get_order_file( $order ) ) {
            return '';
        }
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action'   => 'eilmo_cf_view_payment_proof',
                    'order_id' => $order->get_id(),
                ),
                admin_url( 'admin-ajax.php' )
            ),
            'eilmo_cf_view_payment_proof_' . $order->get_id()
        );
    }

    /** AJAX permission/nonce gate. */
    private function authorize_ajax(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to manage orders.', 'eilmo-checkout-flow' ) ), 403 );
        }
        check_ajax_referer( self::NONCE_ACTION );
    }

    /** @return array<string,mixed> */
    private function settings(): array {
        $defaults = CheckoutSettings::get_defaults();
        $defaults = isset( $defaults['order_management'] ) && is_array( $defaults['order_management'] ) ? $defaults['order_management'] : array();
        $stored   = get_option( CheckoutSettings::OPTION_NAME, array() );
        $saved    = is_array( $stored ) && isset( $stored['order_management'] ) && is_array( $stored['order_management'] ) ? $stored['order_management'] : array();
        return array_replace_recursive( $defaults, $saved );
    }

    /** Scope the responsive order layout to enabled WooCommerce order lists. */
    public function workflow_body_class( string $classes ): string {
        if ( ! $this->is_order_list_screen() ) {
            return $classes;
        }
        $settings = $this->settings();
        if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
            return $classes . ' eilmo-cf-order-standard';
        }
        return $classes . ' eilmo-cf-order-workflow';
    }

    /** Is current screen a WooCommerce order list? */
    private function is_order_list_screen(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }
        $id = (string) $screen->id;
        return in_array( $id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) || false !== strpos( $id, 'wc-orders' );
    }

    /** Decode JSON-like order meta. */
    private function decode_meta( $value ) {
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $decoded = json_decode( $value, true );
            return is_array( $decoded ) ? $decoded : $value;
        }
        return $value;
    }

    /** Compact risk decision label. */
    private function decision_summary( $decision ): string {
        if ( is_array( $decision ) ) {
            foreach ( array( 'label', 'risk_label', 'decision', 'risk', 'status' ) as $key ) {
                if ( isset( $decision[ $key ] ) && is_scalar( $decision[ $key ] ) && '' !== (string) $decision[ $key ] ) {
                    return $this->humanize( (string) $decision[ $key ] );
                }
            }
            return __( 'Review', 'eilmo-checkout-flow' );
        }
        return $decision ? $this->humanize( (string) $decision ) : '—';
    }

    /** Human-readable meta label. */
    private function humanize( string $value ): string {
        $value = trim( str_replace( array( '_', '-' ), ' ', $value ) );
        return '' === $value ? '—' : ucwords( $value );
    }
}
