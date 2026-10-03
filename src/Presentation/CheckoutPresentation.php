<?php
/** Per-instance presentation resolution; never changes order business rules. */
namespace EilmoCheckout\Presentation;

final class CheckoutPresentation {
    public static function resolve(array $global, array $instance = array()): array {
        $display = is_array($global['checkout_display'] ?? null) ? $global['checkout_display'] : array();
        $layout = is_array($display['layout'] ?? null) ? $display['layout'] : array();
        $legacy = ($layout['summary_position'] ?? 'right') === 'right' ? 'right_sticky' : 'inline';
        $preset = in_array($layout['preset'] ?? '', array('inline','right_sticky'), true) ? $layout['preset'] : $legacy;
        if (in_array($instance['checkout_layout'] ?? '', array('inline','right_sticky'), true)) {
            $preset = $instance['checkout_layout'];
        }
        /* An explicit checkout instance language overrides the global choice.
         * An empty instance setting continues to inherit the global language. */
        $language = $instance['checkout_language'] ?? '';
        if ($language === '' || $language === 'inherit') $language = $display['language'] ?? 'en';
        return array('layout'=>$preset,'language'=>in_array($language,array('en','bn'),true)?$language:'en');
    }

    public static function apply(array $settings, array $presentation): array {
        $settings['checkout_layout'] = $presentation['layout'];
        $settings['checkout_language'] = $presentation['language'];
        $settings['desktop_summary_position'] = $presentation['layout'] === 'right_sticky' ? 'right_sticky' : 'below';
        $settings['tablet_summary_position'] = 'below';
        $settings['mobile_summary_position'] = 'inline';
        $settings['checkout_details_position'] = 'main';
        $settings['order_button_placement'] = 'below_summary';
        $settings['show_summary'] = 'yes';
        $settings['mobile_order_sticky'] = 'yes';
        $settings['order_button_display']['show_amount'] = 'no';
        if (isset($settings['checkout_display']['layout']) && is_array($settings['checkout_display']['layout'])) {
            $settings['checkout_display']['layout']['whatsapp_placement'] = 'below_summary';
        }
        if (isset($settings['payment_methods']) && is_array($settings['payment_methods'])) {
            $settings['payment_methods']['footer_enabled'] = 'no';
        }
        if (isset($settings['single_product']) && is_array($settings['single_product'])) $settings['single_product']['summary_items_show_quantity'] = 'no';
        if (isset($settings['multiple_products']) && is_array($settings['multiple_products'])) $settings['multiple_products']['summary_items_show_quantity'] = 'no';
        if (isset($settings['checkout_display']['summary']) && is_array($settings['checkout_display']['summary'])) $settings['checkout_display']['summary']['show_quantity'] = 'no';
        return $settings;
    }
}
