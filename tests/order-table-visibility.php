<?php
/** Focused regression for default and managed WooCommerce order columns. */
namespace EilmoCheckout\Admin {
    final class CheckoutSettings {
        public const OPTION_NAME = 'eilmo_cf_settings';

        public static function get_defaults(): array {
            return array(
                'order_management' => array(
                    'enabled' => 'no',
                    'default_columns' => array(
                        'order' => 'yes', 'date' => 'yes', 'status' => 'yes',
                        'billing' => 'yes', 'shipping' => 'yes', 'courier' => 'yes',
                        'meta' => 'yes', 'security' => 'yes', 'total' => 'yes',
                        'origin' => 'yes', 'actions' => 'yes',
                    ),
                    'columns' => array( 'meta' => 'no', 'courier' => 'yes', 'security' => 'yes' ),
                ),
            );
        }
    }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    function get_option( $name, $default = array() ) { return $GLOBALS['order_table_settings'] ?? $default; }
    function __( $text, $domain = '' ) { return $text; }

    require dirname( __DIR__ ) . '/src/Contracts/RegistrableInterface.php';
    require dirname( __DIR__ ) . '/src/Admin/Orders/QuickOrderManagement.php';

    $table = new \EilmoCheckout\Admin\Orders\QuickOrderManagement();
    $columns = array(
        'cb' => 'Select', 'order_number' => 'Order', 'order_date' => 'Date',
        'order_status' => 'Status', 'billing_address' => 'Billing',
        'shipping_address' => 'Ship to', 'eilmo_cf_courier_success' => 'Courier',
        'eilmo_meta_purchase' => 'Meta', 'eilmo_cf_security' => 'Security',
        'order_total' => 'Total', 'origin' => 'Origin', 'wc_actions' => 'Actions',
        'another_plugin_column' => 'Other',
    );

    $GLOBALS['order_table_settings'] = array();
    if ( $columns !== $table->filter_columns( $columns ) ) {
        throw new \RuntimeException( 'Existing default table must remain unchanged.' );
    }

    $GLOBALS['order_table_settings'] = array(
        'order_management' => array(
            'default_columns' => array(
                'billing' => 'no', 'courier' => 'no', 'meta' => 'no', 'security' => 'no',
            ),
        ),
    );
    $filtered = $table->filter_columns( $columns );
    foreach ( array( 'billing_address', 'eilmo_cf_courier_success', 'eilmo_meta_purchase', 'eilmo_cf_security' ) as $key ) {
        if ( isset( $filtered[ $key ] ) ) {
            throw new \RuntimeException( 'Default visibility did not hide ' . $key );
        }
    }
    foreach ( array( 'order_number', 'order_total', 'another_plugin_column' ) as $key ) {
        if ( ! isset( $filtered[ $key ] ) ) {
            throw new \RuntimeException( 'Default visibility removed unrelated column ' . $key );
        }
    }

    $GLOBALS['order_table_settings']['order_management']['enabled'] = 'yes';
    $managed = $table->filter_columns( $columns );
    if ( ! isset( $managed['eilmo_cf_courier_success'], $managed['eilmo_cf_security'] ) || isset( $managed['eilmo_meta_purchase'] ) ) {
        throw new \RuntimeException( 'Managed table must use its own visibility settings.' );
    }

    echo "Order table visibility regression passed.\n";
}
